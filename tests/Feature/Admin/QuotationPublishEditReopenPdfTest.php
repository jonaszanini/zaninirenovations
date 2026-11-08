<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;
use Tests\Support\PublishesLinkedQuotations;

/**
 * Missing non-painting lifecycle legs: draft → publish → same-payload reopen
 * (totals stay) → meaningful qty/finish publish → persisted total + PDF
 * client/scope/total. Reuses PublishesLinkedQuotations fixtures.
 *
 * Does not duplicate:
 * - QuotationDraftClientFacingGuardTest (draft save must NOT rewrite PDF)
 * - StairsQuotationPagesTest same-payload-only (this adds draft-first + mutation)
 * - QuotationHotkeySaveStateTest markup-only (this asserts controls inside persist)
 * - QuotationEditorFooterBrowserTest create-only UI
 */
class QuotationPublishEditReopenPdfTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;
    use PublishesLinkedQuotations;

    /**
     * @return array<string, array{0: string}>
     */
    public function nonPaintingQuoteTypes(): array
    {
        $types = $this->allQuoteTypes();
        unset($types['painting']);

        return $types;
    }

    /**
     * @dataProvider nonPaintingQuoteTypes
     */
    public function test_draft_publish_qty_edit_reopen_and_pdf(string $type): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $publish = $this->publishPayload($type, $project->client_name);
        $draft = array_merge($publish, ['save_intent' => 'draft']);
        if ($type === 'tile') {
            $draft['is_draft'] = '1';
            $draft['client_project_id'] = $project->id;
            $publish['client_project_id'] = $project->id;
            $publish['is_draft'] = '0';
        }

        $storeRoute = $type === 'tile'
            ? route('admin.quotations.tile.store')
            : route('admin.quotations.'.$type.'.store', ['project' => $project->id]);

        $this->post($storeRoute, $draft)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $quote = $this->quoteModelClass($type)::query()
            ->where('client_name', $project->client_name)
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue((bool) $quote->is_draft, $type.' first save must be a draft');

        $draftHtml = $this->get(route('admin.quotations.'.$type.'.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertDistinctDraftAndPublish($draftHtml, $type.' draft edit');
        $this->assertHotkeyOnDraft($draftHtml, $type.' draft edit');

        $updateRoute = route('admin.quotations.'.$type.'.update', $quote);
        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->put($updateRoute, $publish)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft, $type.' must publish from draft');
        $expected = $this->lifecycleFixtureExpected($type);
        $this->assertEquals($expected['publishQty'], $this->storedLifecycleQty($type, $quote), $type.' published qty');
        $this->assertEqualsWithDelta(
            $expected['publishTotal'],
            $this->storedQuoteTotal($quote),
            0.02,
            $type.' published total must match independent fixture expected '.$expected['publishTotal']
        );

        [$quote, $cpq] = $this->ensureLinked($type, $quote, $project);
        $money1 = ClientProject::formatMoney($expected['publishTotal']);

        $publishedHtml = $this->get(route('admin.quotations.'.$type.'.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertDistinctDraftAndPublish($publishedHtml, $type.' published edit');
        $this->assertHotkeyOnPublish($publishedHtml, $type.' published edit');
        $this->assertFooterShowsMoney($publishedHtml, $money1, $type.' reopen after first publish');
        $this->assertSame($project->client_name, (string) $quote->client_name, $type.' stored client name');
        $this->assertTrue(
            str_contains($publishedHtml, $project->client_name) || str_contains($publishedHtml, (string) $project->name),
            $type.' edit must show the client or linked project after publish'
        );
        $this->assertStringContainsString('data-qh-view-pdf', $publishedHtml);

        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->put($updateRoute, $publish)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();
        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft, $type.' same-payload must stay published');
        $this->assertEqualsWithDelta(
            $expected['publishTotal'],
            $this->storedQuoteTotal($quote),
            0.02,
            $type.' same-payload publish must preserve the independent publish total'
        );

        $mutated = array_merge($publish, $this->typeFields($type, true));
        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->put($updateRoute, $mutated)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $quote->refresh();
        $this->assertEquals($expected['httpEditQty'], $this->storedLifecycleQty($type, $quote), $type.' edited qty');
        $this->assertEqualsWithDelta(
            $expected['httpEditTotal'],
            $this->storedQuoteTotal($quote),
            0.02,
            $type.' edited total must match independent fixture expected '.$expected['httpEditTotal']
        );
        $this->assertFalse((bool) $quote->is_draft, $type.' qty edit must stay published');

        $money2 = ClientProject::formatMoney($expected['httpEditTotal']);
        $reopenHtml = $this->get(route('admin.quotations.'.$type.'.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertFooterShowsMoney($reopenHtml, $money2, $type.' reopen after qty edit');
        $this->assertTrue(
            str_contains($reopenHtml, $project->client_name) || str_contains($reopenHtml, (string) $project->name),
            $type.' reopen must show the client or linked project'
        );

        $cpq->refresh();
        $this->assertEqualsWithDelta(
            $expected['httpEditTotal'],
            $cpq->pdfGrandTotal(),
            0.02,
            $type.' client PDF total must match the independent expected total, not a stale snapshot'
        );

        $items = $cpq->pdfLineItems();
        $this->assertNotEmpty($items, $type.' PDF must have line items after publish');
        $scopeBlob = strtolower(implode(' ', array_map(
            static fn ($row) => (string) ($row['description'] ?? ''),
            $items
        )));
        $this->assertNotSame('', trim($scopeBlob), $type.' PDF line descriptions must not be empty');

        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/quote-lifecycle-'.$type.'-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $pdfText = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString($money2, $pdfText, $type.' PDF must show independent expected total '.$money2);
        $this->assertStringContainsString($project->client_name, $pdfText, $type.' PDF must show client name');
        $this->assertStringContainsStringIgnoringCase($expected['scopeWord'], $pdfText, $type.' PDF must include fixture scope "'.$expected['scopeWord'].'"');
        $this->assertPdfContainsScope($pdfText, $scopeBlob, $type);
    }

    private function ensureLinked(string $type, object $quote, ClientProject $project): array
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', $this->quoteSourceType($type))
            ->where('source_id', $quote->id)
            ->first();
        if ($cpq === null) {
            $this->post(route('admin.quotations.'.$type.'.link', $quote), [
                'client_project_id' => $project->id,
            ])->assertSessionDoesntHaveErrors();
            $quote->refresh();
            $cpq = ClientProjectQuotation::query()
                ->where('source_type', $this->quoteSourceType($type))
                ->where('source_id', $quote->id)
                ->first();
        }
        $this->assertNotNull($cpq, $type.' must be linked before PDF');

        return [$quote, $cpq];
    }

    private function assertDistinctDraftAndPublish(string $html, string $label): void
    {
        $this->assertStringContainsString('data-qh-save-intent="publish"', $html, $label.' Publish intent');
        $this->assertStringContainsString('data-qh-save-intent="draft"', $html, $label.' draft intent');
        $this->assertMatchesRegularExpression(
            '/qh-actions__save[^>]*>[\s\S]{0,80}Publish/',
            $html,
            $label.' visible Publish'
        );
        $this->assertStringContainsString('Save as draft', $html, $label.' Save as draft');
        $this->assertStringContainsString('aria-label="Publish"', $html, $label.' Publish accessible name');
        $this->assertStringContainsString('aria-label="Save as draft"', $html, $label.' draft accessible name');
    }

    private function assertHotkeyOnDraft(string $html, string $label): void
    {
        $this->assertMatchesRegularExpression(
            '/data-qh-save-intent="draft"[^>]*data-hotkey-save|data-hotkey-save[^>]*data-qh-save-intent="draft"/',
            $html,
            $label.': Ctrl+S must target Save as draft'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-qh-save-intent="publish"[^>]*data-hotkey-save/',
            $html,
            $label.': Publish must not steal Ctrl+S on a draft'
        );
    }

    private function assertHotkeyOnPublish(string $html, string $label): void
    {
        $this->assertMatchesRegularExpression(
            '/data-qh-save-intent="publish"[^>]*data-hotkey-save|data-hotkey-save[^>]*data-qh-save-intent="publish"/',
            $html,
            $label.': Ctrl+S must target Publish'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-qh-save-intent="draft"[^>]*data-hotkey-save/',
            $html,
            $label.': Save as draft must not steal Ctrl+S on a published quote'
        );
    }

    private function assertFooterShowsMoney(string $html, string $money, string $label): void
    {
        $this->assertTrue(
            (bool) preg_match('/data-qh-footer-amount[^>]*>([^<]+)/', $html, $match),
            $label.': missing data-qh-footer-amount'
        );
        $shown = html_entity_decode(trim($match[1]), ENT_QUOTES, 'UTF-8');
        $this->assertSame($money, $shown, $label.': sticky footer must SSR the stored total');
    }

    private function assertPdfContainsScope(string $pdfText, string $scopeBlob, string $type): void
    {
        $haystack = strtolower($pdfText);
        $needles = array_values(array_filter(preg_split('/\s+/', $scopeBlob) ?: [], static function ($word) {
            $word = trim($word, " \t\n\r\0\x0B.,;:()[]\"'");

            return strlen($word) >= 5;
        }));
        $hit = false;
        foreach (array_slice($needles, 0, 12) as $word) {
            if (str_contains($haystack, strtolower($word))) {
                $hit = true;
                break;
            }
        }
        $this->assertTrue(
            $hit,
            $type.' PDF text must include a scope word from line items ('.implode(', ', array_slice($needles, 0, 6)).')'
        );
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use App\Models\LowDeckQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Services\QuoteAcceptanceService;
use App\Support\QuotationDraftFlash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Feature\QuoteAcceptance\MakesAcceptableQuotations;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\PublishesLinkedQuotations;

class QuotationDraftClientFacingGuardTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesAcceptableQuotations;
    use PublishesLinkedQuotations;

    /**
     * @dataProvider allQuoteTypes
     */
    public function test_draft_save_on_published_linked_quotes_does_not_update_client_quotation(string $type): void
    {
        $this->actingAsAdmin();

        [$quote, $cpq, $payload, $updateRoute] = $this->publishLinkedQuote($type);
        $this->assertFalse((bool) $quote->is_draft, "{$type} should start published");
        $this->assertTrue($quote->isLinked(), "{$type} should start linked");

        $publishedTotal = $this->quoteCalculatorTotal($type, $quote);
        $this->assertGreaterThan(0, $publishedTotal, "{$type} published total");
        $itemsBefore = $cpq->items;
        $cpqTotalBefore = $cpq->grandTotal();
        $snapshotBefore = $cpq->source_snapshot;

        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->put($updateRoute, $this->draftUpdatePayload($type, $quote, $payload))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $quote->refresh();
        $cpq->refresh();
        $this->assertTrue((bool) $quote->is_draft, "{$type} must stay a draft after Save as draft");
        $this->assertNotEquals($publishedTotal, $this->quoteCalculatorTotal($type, $quote), "{$type} draft must change the calculator total");
        $this->assertSame($itemsBefore, $cpq->items, "{$type} draft must not rewrite client quotation items");
        $this->assertEqualsWithDelta($cpqTotalBefore, $cpq->grandTotal(), 0.001, "{$type} draft must not change the project PDF total");
        $this->assertSame($snapshotBefore, $cpq->source_snapshot, "{$type} draft must not replace the client quotation snapshot");
        $this->assertEqualsWithDelta($cpqTotalBefore, $cpq->pdfGrandTotal(), 0.001, "{$type} draft must not change the public signed PDF total");
        $this->assertTrue(
            $cpq->fresh()->isSourceDraft(),
            "{$type} client quotation must pause after the source is saved as a draft"
        );
    }

    /**
     * @dataProvider allQuoteTypes
     */
    public function test_update_flash_says_saved_as_a_draft(string $type): void
    {
        $this->actingAsAdmin();

        [$quote, $cpq, $payload, $updateRoute] = $this->publishLinkedQuote($type);

        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->put($updateRoute, $this->draftUpdatePayload($type, $quote, $payload))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDraftUpdateFlash($type, $quote, (string) session('success'), true);
        $this->assertTrue(
            $cpq->fresh()->isSourceDraft(),
            "{$type} client-facing quotation must be paused after Save as draft"
        );

        $html = $this->get(route('admin.quotations.'.$type.'.edit', $quote->fresh()))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-qh-client-share="0"', $html);

        $this->from(route('admin.quotations.'.$type.'.edit', $quote->fresh()))
            ->put($updateRoute, $this->draftUpdatePayload($type, $quote->fresh(), $payload))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDraftUpdateFlash($type, $quote, (string) session('success'), false);
    }

    /**
     * @dataProvider allQuoteTypes
     */
    public function test_published_linked_edit_marks_client_share_for_draft_confirm(string $type): void
    {
        $this->actingAsAdmin();

        [$quote] = $this->publishLinkedQuote($type);
        $html = $this->get(route('admin.quotations.'.$type.'.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-qh-client-share="1"', $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function linkGuardTypes(): array
    {
        return [
            'fence' => ['fence', 'fence'],
            'low_deck' => ['low-deck', 'low deck'],
            'standard' => ['standard', 'standard'],
        ];
    }

    /**
     * @dataProvider linkGuardTypes
     */
    public function test_link_and_create_project_refuse_drafts(string $type, string $label): void
    {
        $this->actingAsAdmin();

        $quote = $this->makeUnlinkedDraft($type);
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->post(route('admin.quotations.'.$type.'.link', $quote), [
                'client_project_id' => $project->id,
            ])
            ->assertSessionHas('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertFalse($quote->isLinked());
        $this->assertSame(0, ClientProjectQuotation::query()->where('source_id', $quote->id)->count());

        $projectsBefore = ClientProject::query()->count();
        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->post(route('admin.quotations.'.$type.'.create-project', $quote), [
                'fp_client_name' => 'Draft Project Client',
                'fp_project_name' => 'Should not create '.$label,
            ])
            ->assertSessionHas('error', 'Publish the quotation before creating a project.');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertFalse($quote->isLinked());
        $this->assertSame($projectsBefore, ClientProject::query()->count());
    }

    /**
     * @dataProvider allQuoteTypes
     */
    public function test_email_sms_pdf_and_accept_are_blocked_when_source_is_draft(string $type): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation, $source] = $this->makeLinkedSentSource($type);
        $source->is_draft = true;
        $source->save();
        $quotation->refresh();
        $this->assertTrue($quotation->isSourceDraft());

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error', ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE);
        Mail::assertNothingSent();
        $sentAt = $quotation->sent_at;
        $this->assertNotNull($sentAt);
        $this->assertTrue($quotation->fresh()->sent_at->equalTo($sentAt));

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.sms', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertStringContainsString(
            ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE,
            (string) session('error')
        );

        $this->from(route('admin.client-projects.show', $project))
            ->get(route('admin.client-projects.quotation.pdf', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error', ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE);

        $this->assertRevisingClientPage(
            $this->get(URL::signedRoute('public.client-project.quotation-pdf', [
                'client_project' => $project,
                'quotation' => $quotation,
            ])),
            $project,
            $quotation
        );

        $acceptUrl = $quotation->signedAcceptUrl();
        $this->assertRevisingClientPage($this->get($acceptUrl), $project, $quotation);
        $this->assertRevisingClientPage($this->post($acceptUrl), $project, $quotation);
        $this->assertFalse($quotation->fresh()->isAccepted());

        config(['quote_accept.api_token' => 'test-quote-accept-token']);
        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
        ], ['Authorization' => 'Bearer test-quote-accept-token'])
            ->assertStatus(409)
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_REVISING]);
        $this->assertFalse($quotation->fresh()->isAccepted());
    }

    public function test_tile_edit_keeps_draft_flag_and_update_message_says_saved_as_a_draft(): void
    {
        $this->actingAsAdmin();

        $payload = $this->publishPayload('tile', 'Tile Draft Re-show');
        $payload['save_intent'] = 'draft';
        $payload['is_draft'] = '1';
        unset($payload['payment_installments'], $payload['include_debris_removal'], $payload['ask_reply_to_accept']);

        $this->post(route('admin.quotations.tile.store'), $payload)->assertRedirect();

        $quote = TileQuotation::query()->where('client_name', 'Tile Draft Re-show')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);

        $html = $this->get(route('admin.quotations.tile.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="tl-is-draft"[^>]*value="1"|value="1"[^>]*id="tl-is-draft"/',
            $html
        );

        $this->from(route('admin.quotations.tile.edit', $quote))
            ->put(route('admin.quotations.tile.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.tile.edit', $quote))
            ->assertSessionHas('success', 'Tile quotation saved as a draft.');

        $this->assertTrue((bool) $quote->fresh()->is_draft);
    }

    private function assertRevisingClientPage($response, ClientProject $project, ClientProjectQuotation $quotation): void
    {
        $quotation->loadMissing('project');
        $html = $response->assertStatus(409)
            ->assertViewIs('site.quotations.revising')
            ->assertSee(ClientProjectQuotation::SOURCE_REVISING_MESSAGE)
            ->assertDontSee('Accept quote')
            ->assertDontSee('Total incl. HST')
            ->assertDontSee('qa-summary')
            ->getContent();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('once it is published', $html);
        $this->assertStringNotContainsString($project->name, $html, "{$project->name} must not appear on the revising page");
        $this->assertStringNotContainsString($project->referenceCode(), $html, 'Project reference must not appear on the revising page');
        $this->assertStringNotContainsString($quotation->displayQuoteNumber(), $html, 'Quote number must not appear on the revising page');
        $this->assertStringNotContainsString(
            ClientProject::formatMoney((float) $quotation->grandTotal()),
            $html,
            'Total incl. HST must not appear on the revising page'
        );
    }

    private function assertDraftUpdateFlash(string $type, object $quote, string $message, bool $expectPaused): void
    {
        $label = $this->quoteTypeLabel($type);
        $this->assertStringContainsString($label.' quotation', $message);
        $this->assertStringContainsString('saved as a draft', $message);
        if ($expectPaused) {
            $this->assertStringContainsString(QuotationDraftFlash::CLIENT_LINK_PAUSED_SENTENCE, $message);
        } else {
            $this->assertStringNotContainsString('paused', $message);
        }

        if ($type === 'basement') {
            $number = trim((string) $quote->fresh()->quote_number);
            $this->assertNotSame('', $number);
            $this->assertStringContainsString($number, $message);
            if ($expectPaused) {
                $this->assertStringNotContainsString('not on the project', $message);
            }

            return;
        }

        $expected = $label.' quotation saved as a draft.';
        if ($expectPaused) {
            $expected .= ' '.QuotationDraftFlash::CLIENT_LINK_PAUSED_SENTENCE;
        }
        $this->assertSame($expected, $message);
    }

    private function makeUnlinkedDraft(string $type): FenceQuotation|LowDeckQuotation|StandardQuotation
    {
        return match ($type) {
            'fence' => FenceQuotation::query()->create([
                'quote_number' => 'FQ-DRAFT-LINK-1',
                'client_name' => 'Fence Draft Link',
                'is_draft' => true,
                'sides' => [['length_ft' => 20]],
                'calculation' => [],
                'grand_total' => 1500,
                'payment_installments' => null,
                'include_debris_removal' => null,
                'ask_reply_to_accept' => null,
            ]),
            'low-deck' => LowDeckQuotation::query()->create([
                'quote_number' => 'LD-DRAFT-LINK-1',
                'client_name' => 'Deck Draft Link',
                'is_draft' => true,
                'width_ft' => 12,
                'length_ft' => 16,
                'calculation' => [],
                'grand_total' => 1800,
                'payment_installments' => null,
                'include_debris_removal' => null,
                'ask_reply_to_accept' => null,
            ]),
            default => StandardQuotation::query()->create([
                'quote_number' => 'SQ-DRAFT-LINK-1',
                'client_name' => 'Standard Draft Link',
                'display_title' => 'Kitchen work',
                'is_draft' => true,
                'materials' => [],
                'grand_total' => 1130,
                'payment_installments' => null,
                'include_debris_removal' => null,
                'ask_reply_to_accept' => null,
            ]),
        };
    }
}

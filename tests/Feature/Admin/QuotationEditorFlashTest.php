<?php

namespace Tests\Feature\Admin;

use App\Support\QuotationEditorFlash;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\PublishesLinkedQuotations;

class QuotationEditorFlashTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use PublishesLinkedQuotations;

    /**
     * @dataProvider allQuoteTypes
     */
    public function test_edit_page_after_save_renders_toast_and_compact_status(string $type): void
    {
        $this->actingAsAdmin();

        $clientName = 'Flash save '.$type;
        $payload = array_merge([
            'client_name' => $clientName,
            'save_intent' => 'draft',
        ], $this->typeFields($type, true));
        if ($type !== 'flooring') {
            $payload['include_commute'] = 0;
        }
        if ($type === 'tile') {
            $payload['is_draft'] = '1';
        }

        if ($type === 'painting') {
            // New painting quotations are hidden (PR #156); save an existing one instead.
            $existing = $this->makeExistingPaintingQuote($clientName);
            $store = $this->from(route('admin.quotations.painting.edit', $existing))
                ->put(route('admin.quotations.painting.update', $existing), $payload);
        } else {
            $store = $this->from(route('admin.quotations.'.$type.'.create'))
                ->post(route('admin.quotations.'.$type.'.store'), $payload);
        }

        $store->assertSessionDoesntHaveErrors();
        $store->assertRedirect();
        $store->assertSessionHas('success');
        $success = (string) session('success');
        $this->assertNotSame('', $success, $type.' save must flash success');

        $quote = $this->quoteModelClass($type)::query()
            ->where('client_name', $clientName)
            ->latest('id')
            ->firstOrFail();

        $html = $this->get(route('admin.quotations.'.$type.'.edit', $quote))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-admin-toast-host', $html, $type.' reuses AdminToast host');
        $this->assertStringContainsString('data-qh-editor-toast', $html, $type.' quotation AdminToast marker');
        $this->assertStringContainsString('id="admin-toast-flash"', $html, $type.' existing AdminToast flash JSON');
        $this->assertSame($success, $this->toastFlashPayload($html)['success'] ?? null, $type.' save message is in AdminToast flash JSON');
        $this->assertStringContainsString('admin-toast.js', $html, $type.' existing AdminToast script');
        $this->assertMatchesRegularExpression(
            '/data-qh-editor-toast[^>]*\bhidden\b/',
            $html,
            $type.' AdminToast host stays empty until JS fills it'
        );

        $compact = QuotationEditorFlash::compact($success);
        $this->assertStringContainsString('data-qh-flash-status', $html, $type.' compact status');
        $this->assertStringContainsString($compact, $html, $type.' compact copy');
        $this->assertDoesNotMatchRegularExpression(
            '/data-qh-flash-status(?!-)\s[^>]*\bhidden\b/',
            $html,
            $type.' compact status must be visible after save'
        );

        $this->assertStringNotContainsString('data-admin-flash="success"', $html, $type.' old success banner');
        $this->assertStringNotContainsString('class="admin-flash admin-flash--success"', $html, $type.' old success banner class');
        $this->assertStringContainsString('admin-quotation-flash.js', $html, $type.' compact status JS');
    }

    public function test_create_page_without_flash_keeps_hidden_toast_host(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-qh-editor-toast', $html);
        $this->assertStringContainsString('data-admin-toast-host', $html);
        $this->assertStringContainsString('data-qh-flash-status', $html);
        $this->assertStringNotContainsString('data-admin-flash="success"', $html);
        $this->assertMatchesRegularExpression('/data-qh-editor-toast[^>]*\bhidden\b/', $html);
    }

    public function test_error_flash_uses_toast_not_banner_on_standard_edit(): void
    {
        $this->actingAsAdmin();

        $store = $this->from(route('admin.quotations.standard.create'))
            ->post(route('admin.quotations.standard.store'), array_merge([
                'client_name' => 'Flash error standard',
                'save_intent' => 'draft',
                'include_commute' => 0,
            ], $this->typeFields('standard', true)));
        $store->assertRedirect();
        $quote = $this->quoteModelClass('standard')::query()
            ->where('client_name', 'Flash error standard')
            ->firstOrFail();

        $html = $this->withSession(['error' => 'Could not save the quotation.'])
            ->get(route('admin.quotations.standard.edit', $quote))
            ->assertOk()
            ->getContent();

        $this->assertSame('Could not save the quotation.', $this->toastFlashPayload($html)['error'] ?? null);
        $this->assertStringContainsString('data-qh-editor-toast', $html);
        $this->assertStringContainsString('id="admin-toast-flash"', $html);
        $this->assertStringContainsString('data-qh-flash-status', $html);
        $this->assertStringContainsString('Could not save the quotation.', $html);
        $this->assertStringNotContainsString('data-admin-flash="error"', $html);
        $this->assertStringNotContainsString('class="admin-flash admin-flash--error"', $html);
    }

    /**
     * @return array{success?: mixed, error?: mixed, warning?: mixed}
     */
    private function toastFlashPayload(string $html): array
    {
        $this->assertTrue(
            (bool) preg_match('/id="admin-toast-flash"[^>]*>(.*?)<\/script>/s', $html, $m),
            'AdminToast flash JSON script is present'
        );
        $payload = json_decode(html_entity_decode((string) ($m[1] ?? ''), ENT_QUOTES, 'UTF-8'), true);

        return is_array($payload) ? $payload : [];
    }

    private function makeExistingPaintingQuote(string $clientName): \App\Models\PaintingQuotation
    {
        $paintLineId = \App\Models\PaintingPaintLine::query()->orderBy('id')->value('id');
        $inputs = array_merge(\App\Services\PaintingQuotationCalculator::defaultInputs(), [
            'total_property_sqf' => 1200,
            'paint_line_id' => $paintLineId,
        ]);
        $calc = (new \App\Services\PaintingQuotationCalculator)->calculate($inputs);

        return \App\Models\PaintingQuotation::query()->create([
            'quote_number' => \App\Models\PaintingQuotation::generateNumber(),
            'client_name' => $clientName,
            'is_draft' => true,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => round((float) ($calc['summary']['final_quote_total'] ?? 0), 2),
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => 0,
        ]);
    }
}

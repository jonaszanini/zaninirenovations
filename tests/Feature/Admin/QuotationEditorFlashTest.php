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

        $store = $this->from(route('admin.quotations.'.$type.'.create'))
            ->post(route('admin.quotations.'.$type.'.store'), $payload);

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

        $this->assertStringContainsString('data-qh-flash-toast', $html, $type.' toast host');
        $this->assertStringContainsString('data-admin-toast-host', $html, $type.' toast host is the shared toast');
        $this->assertStringContainsString('aria-live="polite"', $html, $type.' toast is polite');
        $this->assertStringContainsString('data-admin-toast-close', $html, $type.' toast close');
        $this->assertStringContainsString($success, $html, $type.' toast shows the save message');
        $this->assertDoesNotMatchRegularExpression(
            '/data-qh-flash-toast[^>]*\bhidden\b/',
            $html,
            $type.' toast must be visible after save'
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
        $this->assertStringContainsString('admin-quotation-flash.js', $html, $type.' shared flash JS');
    }

    public function test_create_page_without_flash_keeps_hidden_toast_host(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-qh-flash-toast', $html);
        $this->assertStringContainsString('data-qh-flash-status', $html);
        $this->assertStringNotContainsString('data-admin-flash="success"', $html);
        $this->assertMatchesRegularExpression('/data-qh-flash-toast[^>]*\bhidden\b/', $html);
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

        $this->assertStringContainsString('Could not save the quotation.', $html);
        $this->assertStringContainsString('admin-toast--error', $html);
        $this->assertStringNotContainsString('data-admin-flash="error"', $html);
        $this->assertStringNotContainsString('class="admin-flash admin-flash--error"', $html);
    }
}

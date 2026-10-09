<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

class AdminInfoTipBehaviourTest extends TestCase
{
    public function test_form_field_renders_tip_beside_the_label_not_inside_it(): void
    {
        $html = view('admin.partials.form_field', [
            'label' => 'Store SKU',
            'name' => 'store_sku',
            'help' => 'Needed only to sync Home Depot prices.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('admin-label-with-tip', $html);
        $this->assertStringContainsString('aria-label="About Store SKU"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*>[^<]*<span class="admin-info-tip-wrap"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<\/label>\s*<span class="admin-info-tip-wrap"/',
            $html
        );
    }

    public function test_checkbox_renders_tip_beside_the_label_not_inside_it(): void
    {
        $html = view('admin.partials.checkbox', [
            'name' => 'sync_prices',
            'id' => 'mat-bulk-sync',
            'label' => 'Sync prices after import',
            'info' => 'Uses SerpApi when configured.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('admin-label-with-tip', $html);
        $this->assertStringContainsString('aria-label="About Sync prices after import"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*>[\s\S]*admin-info-tip-wrap[\s\S]*<\/label>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<\/label>\s*<span class="admin-info-tip-wrap"/',
            $html
        );
    }

    public function test_filters_accordion_hint_is_an_info_tip_not_an_inline_paragraph(): void
    {
        $html = view('admin.partials.filters_accordion', [
            'id' => 'admin-filters-client-projects',
            'hint' => 'By default Completed and Rejected are hidden. Use All projects or the Status filter below to surface them.',
            'slot' => new \Illuminate\Support\HtmlString(''),
        ])->render();

        $this->assertStringContainsString('aria-label="About Filters"', $html);
        $this->assertStringContainsString('By default Completed and Rejected are hidden', $html);
        $this->assertStringNotContainsString('admin-filters-accordion__hint', $html);
    }

    public function test_reply_to_accept_help_is_a_tip_and_validation_error_still_renders(): void
    {
        $ok = view('admin.partials.quotation_reply_to_accept', [
            'quotation' => (object) ['ask_reply_to_accept' => null],
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('aria-label="About Ask client to reply I accept"', $ok);
        $this->assertStringContainsString('Required. Pick Yes or No before saving or emailing', $ok);
        $this->assertStringNotContainsString('cp-reply-to-accept__help', $ok);
        $this->assertStringNotContainsString('admin-form-error', $ok);

        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag([
            'ask_reply_to_accept' => ['Pick Yes or No before saving or emailing.'],
        ]));

        $bad = view('admin.partials.quotation_reply_to_accept', [
            'quotation' => (object) ['ask_reply_to_accept' => null],
            'errors' => $errors,
        ])->render();

        $this->assertStringContainsString('admin-form-error', $bad);
        $this->assertStringContainsString('Pick Yes or No before saving or emailing.', $bad);
        $this->assertStringContainsString('Required. Pick Yes or No before saving or emailing', $bad);
    }

    public function test_js_listens_for_scroll_and_exposes_refresh_ids(): void
    {
        $js = file_get_contents(public_path('js/admin-info-tip.js'));

        $this->assertStringContainsString("document.addEventListener('scroll', onViewportMove, { capture: true, passive: true })", $js);
        $this->assertStringContainsString("window.addEventListener('resize', onViewportMove)", $js);
        $this->assertStringContainsString("document.addEventListener('focusout'", $js);
        $this->assertStringContainsString('function isLinkTarget', $js);
        $this->assertStringContainsString('requestAnimationFrame', $js);
        $this->assertStringContainsString('isIconVisibleInClip', $js);
        $this->assertStringContainsString('idsAlreadyInDocument', $js);
        $this->assertStringContainsString("e.target.closest('summary')", $js);
        $this->assertStringContainsString('function refreshIds(scope)', $js);
        $this->assertStringContainsString('refreshIds: refreshIds', $js);
        $this->assertStringContainsString('btn.focus()', $js);
        $this->assertStringContainsString("e.key !== 'Escape'", $js);
        $this->assertStringContainsString('flex-wrap: nowrap', file_get_contents(public_path('css/admin.css')));
    }

    public function test_clone_sites_refresh_tip_ids(): void
    {
        $standard = file_get_contents(public_path('js/admin-standard-quotation.js'));
        $millwork = file_get_contents(public_path('js/admin-millwork-quotation.js'));
        $lumber = file_get_contents(public_path('js/admin-utilities-lumber-board.js'));

        $this->assertStringContainsString('AdminInfoTip.refreshIds', $standard);
        $this->assertGreaterThanOrEqual(3, substr_count($millwork, 'AdminInfoTip.refreshIds'));
        $this->assertStringContainsString("AdminInfoTip.html(", $lumber);
        $this->assertStringContainsString("'Calculation mode'", $lumber);
        $this->assertStringNotContainsString('class="util-tip"', $lumber);
    }

    public function test_invoice_and_millwork_leftovers_live_in_tips(): void
    {
        $invoice = file_get_contents(resource_path('views/admin/client_projects/_invoice.blade.php'));
        $millwork = file_get_contents(resource_path('views/admin/quotations/millwork/price_configuration.blade.php'));

        $this->assertStringContainsString('This project is billed by the hour. Log time on the', $invoice);
        $this->assertStringContainsString('One invoice per installment from your', $invoice);
        $this->assertStringContainsString('Subtotal comes from the pre-tax installment amount', $invoice);
        $this->assertStringContainsString('HST (13%) is added to each invoice once', $invoice);
        $this->assertStringContainsString('HST (13%) is added to the manual invoice subtotal once', $invoice);
        $this->assertStringContainsString('No payment plan yet.', $invoice);
        $this->assertStringContainsString('This will mark <strong id="cp-mark-paid-invoice-label">the invoice</strong>', $invoice);
        $this->assertStringContainsString('PDFs use this legal name/address', $invoice);
        $this->assertStringContainsString('<strong>Invoice bill-to:</strong>', $invoice);
        $this->assertStringNotContainsString('admin-form-help cp-invoice-bill-to__help', $invoice);
        $this->assertStringNotContainsString('cp-invoice-bill-to__help', $invoice);

        $this->assertDoesNotMatchRegularExpression(
            '/<p class="admin-form-help"[^>]*>\s*(<i class="fa fa-info-circle"><\/i>\s*)?This project is billed by the hour/',
            $invoice
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<p class="admin-form-help"[^>]*>\s*(<i class="fa fa-info-circle"><\/i>\s*)?Subtotal comes from the pre-tax/',
            $invoice
        );

        $this->assertStringContainsString('Imported Materials DB items are available only on Custom Millwork quotations.', $millwork);
        $this->assertStringContainsString('0.125 = 1/8″. Reserved between every saw cut on the cut plan.', $millwork);
        $this->assertStringNotContainsString('<p class="admin-form-help">Imported Materials DB items', $millwork);
        $this->assertStringNotContainsString('<p class="admin-form-help">0.125 = 1/8', $millwork);

        $piece = file_get_contents(resource_path('views/admin/quotations/millwork/_piece_card.blade.php'));
        $this->assertStringContainsString('Size sets — add groups if you have mixed sizes.', $piece);
        $this->assertStringContainsString('Open-back box: top, bottom, front, and ends.', $piece);
        $this->assertStringContainsString('mw-piece__sets-title', $piece);
        $this->assertStringNotContainsString('<p class="admin-form-help mw-piece__hint">Size sets', $piece);
        $this->assertStringNotContainsString('class="mw-piece__hint"', $piece);
        $this->assertStringContainsString('label="Openings"', $piece);

        $show = file_get_contents(resource_path('views/admin/client_projects/show.blade.php'));
        $this->assertStringContainsString('Status saves on change. Optional note is for this status only.', $show);
        $this->assertStringNotContainsString('class="cp-hint"', $show);
        $this->assertStringNotContainsString('class="cp-status-form-help"', $show);

        $financials = file_get_contents(resource_path('views/admin/client_projects/_financials.blade.php'));
        $this->assertStringContainsString('Use this if you updated accepted quotations', $financials);
        $this->assertStringContainsString('Choose how the client will pay.', $financials);
        $this->assertStringContainsString('Money returned from suppliers or credits.', $financials);
        $this->assertStringNotContainsString('class="cp-hint" title="', $financials);
    }

    public function test_serpapi_off_copy_is_inline_on_fence_and_low_deck_config(): void
    {
        $fence = file_get_contents(resource_path('views/admin/quotations/fence/config/index.blade.php'));
        $low = file_get_contents(resource_path('views/admin/quotations/low_deck/config/index.blade.php'));

        $this->assertStringContainsString('@unless ($usesSerpApi)', $fence);
        $this->assertStringContainsString('Online sync requires SerpApi', $fence);
        $this->assertStringContainsString('MATERIALS_SCRAPER_SERPAPI_KEY', $fence);
        $this->assertStringContainsString('admin.materials.config', $fence);
        $this->assertStringContainsString('serpapi.com/dashboard', $fence);

        $this->assertStringContainsString('@unless ($usesSerpApi)', $low);
        $this->assertStringContainsString('Online sync requires SerpApi', $low);
        $this->assertStringContainsString('php artisan materials:sync-prices', $low);
        $this->assertStringContainsString('admin.materials.config', $low);
        $this->assertStringContainsString('serpapi.com/dashboard', $low);
    }

    public function test_wizard_sections_keep_tips_beside_labels_and_group_legends(): void
    {
        $source = file_get_contents(resource_path('views/admin/quotations/basement/_wizard_sections.blade.php'));
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*>[^<]*@include\(\'admin\.partials\.info_tip\'/',
            $source
        );
        $this->assertStringContainsString('label="What are you quoting?"', $source);
        $this->assertStringContainsString('label="Trades to include"', $source);
        $this->assertStringNotContainsString("'info' => \$tradeInfo[", $source);

        $create = file_get_contents(resource_path('views/admin/quotations/basement/create.blade.php'));
        $this->assertStringContainsString('label="Client provides materials"', $create);
        $this->assertStringContainsString('label="Bathroom items"', $create);
        $this->assertStringNotContainsString("'info' => 'Client supplies the '", $create);

        $standard = file_get_contents(resource_path('views/admin/quotations/standard/create.blade.php'));
        $this->assertStringContainsString("'tipLabel' => 'Client PDF line items'", $standard);
        $this->assertStringContainsString('<strong>Combined:</strong>', $standard);
        $this->assertStringNotContainsString('sq-intro-choice-with-tip', $standard);

        $painting = file_get_contents(resource_path('views/admin/quotations/painting/create.blade.php'));
        $this->assertStringContainsString('label="Excluded rooms / areas"', $painting);
        $this->assertStringContainsString('label="Number of top coats"', $painting);
        $this->assertStringNotContainsString('<p class="pq-hint" style="margin:0 0 10px;">Subtract rooms', $painting);
        $this->assertStringNotContainsString('<p class="pq-hint">Fixed at', $painting);

        $tile = file_get_contents(resource_path('views/admin/quotations/tile/create.blade.php'));
        $this->assertStringContainsString('label="Measurements"', $tile);
        $this->assertStringNotContainsString('<div class="fl-step-card__hint">', $tile);

        $show = file_get_contents(resource_path('views/admin/client_projects/show.blade.php'));
        $this->assertStringContainsString('label="Reopen project"', $show);
        $this->assertStringNotContainsString('cp-reopen-form-help', $show);

        $low = file_get_contents(resource_path('views/admin/quotations/low_deck/create.blade.php'));
        $this->assertStringContainsString('label="Live breakdown"', $low);
        $this->assertStringContainsString('label="Layout diagrams"', $low);
        $this->assertStringNotContainsString('admin-form-help cp-breakdown-intro', $low);
        $this->assertStringNotContainsString('Enter dimensions to generate the foundations, framing, and decking layouts.</p>', $low);
    }

    public function test_component_without_label_still_renders_more_info_fallback(): void
    {
        $html = Blade::render('<x-admin.info-tip>Fallback copy.</x-admin.info-tip>');

        $this->assertStringContainsString('aria-label="More info"', $html);
        $this->assertStringContainsString('Fallback copy.', $html);
    }
}

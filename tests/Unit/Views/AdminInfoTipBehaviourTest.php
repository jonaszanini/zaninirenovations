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
        $this->assertStringContainsString('admin-label-with-tip__end', $html);
        $this->assertStringContainsString('aria-label="About Store SKU"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*>[^<]*<span class="admin-info-tip-wrap"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/admin-label-with-tip__end[\s\S]*admin-info-tip-wrap/',
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
        $this->assertStringContainsString('admin-label-with-tip__end', $html);
        $this->assertStringContainsString('admin-checkbox__tail', $html);
        $this->assertStringContainsString('aria-label="About Sync prices after import"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*>[\s\S]*admin-info-tip-wrap[\s\S]*<\/label>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/admin-checkbox__tail[^>]*>import<\/label>\s*<span class="admin-info-tip-wrap"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/admin-checkbox__text[^>]*>\s+/',
            $html
        );
    }

    public function test_checkbox_derives_rewriteable_id_from_name_not_uniqid(): void
    {
        $html = view('admin.partials.checkbox', [
            'name' => 'pieces[__P__][edge_banding]',
            'label' => 'Edge banding',
            'info' => 'Shrinks the raw cut so the finished size matches after banding.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('id="pieces-__P__-edge_banding"', $html);
        $this->assertStringContainsString('for="pieces-__P__-edge_banding"', $html);
        $this->assertStringNotContainsString('id="admin-checkbox-', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'pieces-__P__-edge_banding'));
    }

    public function test_checkbox_id_fails_if_name_is_ignored(): void
    {
        $a = view('admin.partials.checkbox', [
            'name' => 'works[0][labour_fixed_hst_included]',
            'label' => 'HST included',
            'info' => 'When checked, the amount is the total with tax.',
            'errors' => new ViewErrorBag,
        ])->render();
        $b = view('admin.partials.checkbox', [
            'name' => 'pieces[2][edge_banding]',
            'label' => 'Edge banding',
            'info' => 'Shrinks the raw cut.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('id="works-0-labour_fixed_hst_included"', $a);
        $this->assertStringContainsString('id="pieces-2-edge_banding"', $b);
        $this->assertStringNotContainsString('id="works-0-labour_fixed_hst_included"', $b);
        $this->assertSame(
            'works-0-labour_fixed_hst_included',
            \App\Support\AdminInfoTipInclude::controlIdFromName('works[0][labour_fixed_hst_included]')
        );
        $this->assertNotSame(
            \App\Support\AdminInfoTipInclude::controlIdFromName('works[0][labour_fixed_hst_included]'),
            \App\Support\AdminInfoTipInclude::controlIdFromName('pieces[2][edge_banding]')
        );
    }

    public function test_checkbox_entity_label_is_not_double_escaped(): void
    {
        $html = view('admin.partials.checkbox', [
            'name' => 'include_demo',
            'id' => 'br-include-demo',
            'label' => 'Include demolition &amp; debris',
            'info' => 'Demo the existing finish.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('Include demolition &amp; ', $html);
        $this->assertStringContainsString('debris', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('Include demolition &amp;amp; debris', $html);
    }

    public function test_checkbox_strong_label_does_not_leave_an_open_tag(): void
    {
        $html = view('admin.partials.checkbox', [
            'name' => 'client_provides_all',
            'id' => 'mw-client-all',
            'label' => '<strong>Client provides all materials</strong>',
            'info' => 'Labour only — material cost drops out of the total.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('<strong>Client provides all</strong>', $html);
        $this->assertStringContainsString('<strong>materials</strong>', $html);
        $this->assertStringNotContainsString('<strong>Client provides all</span>', $html);
        $this->assertSame(substr_count($html, '<strong>'), substr_count($html, '</strong>'));
        $this->assertDoesNotMatchRegularExpression('/<strong>[^<]*<\/span>/', $html);
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
        $this->assertStringContainsString("wrap.closest('summary')", $js);
        $this->assertStringContainsString('function bottomReserve', $js);
        $this->assertStringContainsString('spaceAbove >= need', $js);
        $this->assertStringContainsString('aboveEdge', $js);
        $this->assertStringContainsString('floor - need', $js);
        $this->assertStringContainsString("bubble.style.overflowY = 'auto'", $js);
        $this->assertStringContainsString('function bindSummaryGuard', $js);
        $this->assertStringContainsString('function refreshRepeatLabels', $js);
        $this->assertStringContainsString('function glueLastWord', $js);
        $this->assertStringContainsString('function setLabelText', $js);
        $this->assertStringContainsString('function glueLastTextNode', $js);
        $this->assertStringContainsString('function wrapElementAndTip', $js);
        $this->assertStringContainsString('function wrapHeadingCopy', $js);
        $this->assertStringContainsString('function lastUnlockedTextNode', $js);
        $this->assertStringContainsString('function makeWordEl', $js);
        $this->assertStringContainsString('function linkSplitLabels', $js);
        $this->assertStringContainsString('admin-label-with-tip__tail', $js);
        $this->assertStringContainsString('aria-labelledby', $js);
        $this->assertStringNotContainsString('function moveTrailingSkipped', $js);
        $this->assertStringNotContainsString('function skipGlueNode', $js);
        $this->assertStringNotContainsString('admin-heading-tip', $js);
        $this->assertStringContainsString('function aboutLabel', $js);
        $this->assertStringContainsString('function refreshControlIds', $js);
        $this->assertStringContainsString('refreshControlIds: refreshControlIds', $js);
        $this->assertStringContainsString('function uniquifyTipLabelIds', $js);
        $this->assertStringContainsString('uniquifyTipLabelIds(scope)', $js);
        $this->assertStringContainsString('id^="admin-tip-lab-"', $js);
        $this->assertStringContainsString('if (!scope || scope.nodeType !== 1) return', $js);
        $this->assertStringContainsString('AdminInfoTip.refreshControlIds requires an element scope', $js);
        $this->assertStringContainsString('function idFromControlName', $js);
        $this->assertStringContainsString("idFromControlName(input.getAttribute && input.getAttribute('name'))", $js);
        $this->assertStringContainsString("btn.setAttribute('aria-label', aboutLabel(aria))", $js);
        $this->assertStringContainsString("label.admin-checkbox, .admin-checkbox-row > .admin-label-with-tip__end", $js);
        $this->assertStringContainsString("'.mw-totals-bar'", $js);
        $this->assertStringContainsString('setLabelText: setLabelText', $js);
        $basementJs = file_get_contents(public_path('js/admin-basement-quotation.js'));
        $this->assertStringContainsString('AdminInfoTip.setLabelText', $basementJs);
        $this->assertStringNotContainsString('areaLabel.textContent', $basementJs);
        $this->assertStringContainsString("document.addEventListener('input'", $js);
        $this->assertStringContainsString("t.classList.contains('sq-work-title')", $js);
        $this->assertStringContainsString('function refreshIds(scope)', $js);
        $this->assertStringContainsString('refreshIds: refreshIds', $js);
        $this->assertStringContainsString('btn.focus()', $js);
        $this->assertStringContainsString("e.key !== 'Escape'", $js);
        $css = file_get_contents(public_path('css/admin.css'));
        $this->assertStringContainsString('pointer-events: auto', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.admin-label-with-tip\s*\{[^}]*white-space:\s*nowrap/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip\s*>\s*label[^}]*white-space:\s*normal/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip\s*>\s*label:not\(\.admin-checkbox\)[^}]*display:\s*inline/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip\s*>\s*label\.admin-checkbox[^}]*display:\s*inline-flex/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,220}text-indent:\s*-34px/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,220}display:\s*block/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)\s+\.admin-checkbox__mark\s*\{[^}]*vertical-align:\s*-6px/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}text-transform:\s*inherit/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}font-weight:\s*inherit/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-checkbox-row\s*\{[^}]*font-weight:\s*500/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}letter-spacing:\s*inherit/',
            $css
        );
        $this->assertStringContainsString('.admin-checkbox-row', $css);
        $this->assertMatchesRegularExpression(
            '/\.admin-checkbox__tail\s*,[\s\S]{0,500}white-space:\s*nowrap/',
            $css
        );
        $this->assertStringContainsString('.admin-heading-copy', $css);
        $this->assertStringNotContainsString('.admin-heading-tip', $css);
        $this->assertStringContainsString('padding-left: 64px', $css);
        $this->assertStringContainsString('text-indent: -64px', $css);
        $this->assertMatchesRegularExpression(
            '/\.admin-accordion__title\s*\{[^}]*white-space:\s*normal/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-accordion__title\s*\{[^}]*min-width:\s*0/',
            $css
        );
        $darkCss = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertMatchesRegularExpression(
            '/html\.admin-dark \.admin-checkbox-row[\s\S]{0,80}color:\s*var\(--admin-text\)/',
            $darkCss
        );
        $this->assertStringContainsString('html.admin-dark label.cp-required', $darkCss);
        $this->assertStringContainsString('html.admin-dark .admin-form-group .admin-label-with-tip__end > .cp-required', $darkCss);
        $this->assertStringContainsString('html.admin-dark .admin-form-group .admin-label-with-tip__end > label.cp-required', $darkCss);
        $this->assertStringContainsString('.admin-btn[hidden]', $css);
        $dialog = file_get_contents(public_path('js/admin-dialog.js'));
        $this->assertStringContainsString('global.AdminInfoTip = global.AdminInfoTip ||', $dialog);
        $this->assertStringContainsString("replace(/^\\s*About\\s+/i", $dialog);
        $this->assertStringNotContainsString("e.target.closest('.admin-info-tip')", $dialog);
        $this->assertStringContainsString('.admin-card > h3:has(.admin-info-tip)', $css);
        $this->assertStringContainsString('.admin-card > h3.sp-heading:has(.admin-info-tip)', $css);
        $vehicles = file_get_contents(resource_path('views/admin/vehicles/index.blade.php'));
        $this->assertStringContainsString('fuel costs, business contact, and your home / office address', $vehicles);
        $this->assertStringNotContainsString('admin-toolbar-lede', $vehicles);
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip__end\s*\{[^}]*white-space:\s*nowrap/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip__end\s*\{[^}]*display:\s*inline-flex/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip__end\s*>\s*label[\s\S]{0,220}display:\s*inline/',
            $css
        );
        $this->assertStringContainsString('admin-label-with-tip__tail', $css);
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip__end\s*>\s*\.admin-info-tip-wrap[\s\S]{0,80}display:\s*inline-flex/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip__end\s*>\s*label[\s\S]{0,160}z-index:\s*1/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.admin-label-with-tip__end\s*>\s*(label|span)[\s\S]{0,200}overflow-wrap:\s*anywhere/',
            $css
        );
        $this->assertStringContainsString('.admin-form-group label.cp-required', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.admin-form-group\s*>\s*label:has\(\.admin-info-tip\)[\s\S]{0,400}align-items:\s*center/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-label-with-tip\s*>\s*\.admin-info-tip-wrap[^}]*display:\s*inline-flex/',
            $css
        );
        $cpCss = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertStringContainsString('.br-layout--create .br-acc__summary .admin-info-tip', $cpCss);
        $this->assertStringContainsString('pointer-events: auto', $cpCss);
        $this->assertStringContainsString('.tm-chip-row > .admin-checkbox-row', $cpCss);
    }

    public function test_clone_sites_refresh_tip_ids(): void
    {
        $standard = file_get_contents(public_path('js/admin-standard-quotation.js'));
        $millwork = file_get_contents(public_path('js/admin-millwork-quotation.js'));
        $lumber = file_get_contents(public_path('js/admin-utilities-lumber-board.js'));

        $this->assertStringContainsString('AdminInfoTip.refreshControlIds', $standard);
        $this->assertStringContainsString('AdminInfoTip.refreshRepeatLabels', $standard);
        $this->assertGreaterThanOrEqual(3, substr_count($millwork, 'refreshControlIds'));
        $this->assertStringContainsString("label[for]", $millwork);
        $this->assertStringContainsString('AdminInfoTip.refreshRepeatLabels', $millwork);
        $this->assertStringContainsString("lab.setAttribute('for', next)", $millwork);
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
        $this->assertStringContainsString("'Openings — '", $piece);
        $this->assertStringContainsString("'Type — '", $piece);

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
        $this->assertStringNotContainsString('mat-serpapi-hint', $fence);

        $this->assertStringContainsString('@unless ($usesSerpApi)', $low);
        $this->assertStringContainsString('Online sync requires SerpApi', $low);
        $this->assertStringContainsString('php artisan materials:sync-prices', $low);
        $this->assertStringContainsString('admin.materials.config', $low);
        $this->assertStringContainsString('serpapi.com/dashboard', $low);
        $this->assertStringNotContainsString('mat-serpapi-hint', $low);

        $materials = file_get_contents(resource_path('views/admin/materials/config.blade.php'));
        $this->assertStringContainsString('Admin sync on this server uses SerpApi', $materials);
        $this->assertStringNotContainsString('mat-serpapi-hint', $materials);
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
        $fenceCreate = file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));
        $this->assertStringContainsString('Live breakdown', $fenceCreate);
        $this->assertStringContainsString('Preview updates as you type', $fenceCreate);
        $this->assertStringContainsString('label="Live breakdown"', $fenceCreate);
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

    public function test_millwork_step_one_intro_is_a_tip_and_accessible_names_are_unique(): void
    {
        $millwork = file_get_contents(resource_path('views/admin/quotations/millwork/_wizard_sections.blade.php'));
        $this->assertStringContainsString('Add each millwork item — cabinets, closets, vanities, trim, and more.', $millwork);
        $this->assertStringContainsString('label="Pieces"', $millwork);
        $this->assertStringContainsString('label="Materials & finish"', $millwork);
        $this->assertStringContainsString('Sheet size defaults to 4×8 from Price configuration', $millwork);
        $this->assertStringNotContainsString('<p class="admin-form-help">Add each millwork item', $millwork);
        $this->assertStringNotContainsString('<p class="admin-form-help">Sheet size defaults to 4×8', $millwork);

        $basement = file_get_contents(resource_path('views/admin/quotations/basement/_wizard_sections.blade.php'));
        $this->assertStringNotContainsString("'tipLabel' => 'How many fixtures'", $basement);
        $this->assertStringNotContainsString("'tipLabel' => 'How many pot lights'", $basement);
        $this->assertStringNotContainsString("'tipLabel' => 'How many'", $basement);

        $financials = file_get_contents(resource_path('views/admin/client_projects/_financials.blade.php'));
        $this->assertStringContainsString('label="Receipt (new expense)"', $financials);
        $this->assertStringContainsString(":label=\"'Receipt '.\$expense->expense_ref", $financials);
        $this->assertStringNotContainsString('<x-admin.info-tip label="Receipt">', $financials);

        $create = file_get_contents(resource_path('views/admin/image_creation/create.blade.php'));
        $this->assertSame(1, substr_count($create, 'label="Create image"'));
        $modify = file_get_contents(resource_path('views/admin/image_creation/modify.blade.php'));
        $this->assertSame(1, substr_count($modify, 'label="Modify image"'));
        $this->assertStringContainsString('JPEG, PNG, WebP, or AVIF up to', $modify);
        $this->assertStringContainsString('larger uploads use the File API', $modify);
        $nb2 = file_get_contents(resource_path('views/admin/image_creation/nano_banana_2.blade.php'));
        $this->assertSame(1, substr_count($nb2, 'label="Nano Banana 2"'));
    }

    public function test_leftover_static_explainers_live_in_tips(): void
    {
        $travel = file_get_contents(resource_path('views/admin/client_projects/_travel_cost.blade.php'));
        $this->assertStringContainsString('Round trip from your home / office', $travel);
        $this->assertStringNotContainsString('<p class="admin-form-help">Round trip from your home', $travel);

        $time = file_get_contents(resource_path('views/admin/client_projects/_time.blade.php'));
        $this->assertStringContainsString('Newest at the top. Tap a row to expand', $time);
        $this->assertStringNotContainsString('cp-time-saved-help', $time);

        $helper = file_get_contents(resource_path('views/admin/client_projects/_helper_time.blade.php'));
        $this->assertStringContainsString('Select who may log time here', $helper);
        $this->assertStringContainsString('Reimburse the helper for meals', $helper);
        $this->assertStringNotContainsString('<p class="admin-form-help cp-time-saved-help">Select who may log time', $helper);
        $this->assertStringNotContainsString('<p class="admin-form-help cp-time-saved-help">Reimburse the helper', $helper);

        $town = file_get_contents(resource_path('views/admin/partials/portfolio_town_and_completed.blade.php'));
        $this->assertStringContainsString('Short name on the public page', $town);
        $this->assertStringContainsString('newest completion first', $town);
        $this->assertStringNotContainsString('<p class="admin-form-help">Short name on the public page', $town);
        $this->assertStringNotContainsString('<p class="admin-form-help">Used to order projects', $town);

        $cat = file_get_contents(resource_path('views/admin/partials/portfolio_category_ai.blade.php'));
        $this->assertStringContainsString('Sends the title, description, and up to 8 photos', $cat);
        $this->assertStringNotContainsString('portfolio-category-ai__help', $cat);

        $create = file_get_contents(resource_path('views/admin/image_creation/create.blade.php'));
        $this->assertStringContainsString('id="nb2-size-help"', $create);
        $this->assertStringNotContainsString("'tipId' => 'nb2-size-help'", $create);
        $this->assertStringContainsString('<p class="admin-form-help" id="nb2-size-help">', $create);
        $nb2 = file_get_contents(resource_path('views/admin/image_creation/nano_banana_2.blade.php'));
        $this->assertStringContainsString('id="nb2-size-help"', $nb2);
        $this->assertStringNotContainsString("'tipId' => 'nb2-size-help'", $nb2);
        $this->assertStringContainsString('<p class="admin-form-help" id="nb2-size-help">', $nb2);

        $sched = file_get_contents(resource_path('views/admin/client_projects/_schedule_toggle.blade.php'));
        $this->assertStringContainsString('Same-day start & end is allowed for small projects.', $sched);
        $this->assertStringNotContainsString('<p class="admin-form-help">Same-day start', $sched);

        $history = file_get_contents(public_path('js/admin-ai-text-history.js'));
        $this->assertStringContainsString('AdminInfoTip.html', $history);
        $this->assertStringContainsString('Original text and each AI result are saved for this session', $history);
        $this->assertStringNotContainsString('admin-ai-text-history-modal__lede', $history);

        $aiImprove = file_get_contents(resource_path('views/admin/partials/portfolio_ai_improve.blade.php'));
        $this->assertStringContainsString('Latest projects photo prompt improvement', $aiImprove);
        $this->assertStringNotContainsString('<p class="admin-form-help">', $aiImprove);
        $descAi = file_get_contents(resource_path('views/admin/partials/portfolio_description_ai.blade.php'));
        $this->assertStringContainsString('Write a rough description first', $descAi);
        $this->assertStringNotContainsString('<p class="admin-form-help">Write a rough description first', $descAi);

        $edit = file_get_contents(resource_path('views/admin/client_projects/edit.blade.php'));
        $this->assertStringNotContainsString('(CC\'d)', $edit);
        $this->assertStringContainsString('Optional — CC\'d on every client email.', $edit);
        $clients = file_get_contents(resource_path('views/admin/clients/create.blade.php'));
        $this->assertStringNotContainsString('cp-inline-help">(optional)', $clients);
        $this->assertStringContainsString('Optional — CC\'d on every client email.', $clients);
    }

    public function test_required_star_sits_before_the_tip_in_blade(): void
    {
        $mat = file_get_contents(resource_path('views/admin/materials/_form.blade.php'));
        $this->assertMatchesRegularExpression(
            '#<label for="mat-name">Store name</label>\s*<span class="admin-label-with-tip__end">\s*<span class="cp-required">\*</span>\s*<x-admin.info-tip#',
            $mat
        );
        $invoice = file_get_contents(resource_path('views/admin/client_projects/invoice/edit.blade.php'));
        $this->assertMatchesRegularExpression(
            '#<label for="cp-invoice-due">Due date</label>\s*<span class="admin-label-with-tip__end">\s*<span class="cp-required">\*</span>\s*<x-admin.info-tip#',
            $invoice
        );
        $debris = file_get_contents(resource_path('views/admin/quotations/_debris_removal.blade.php'));
        $this->assertMatchesRegularExpression(
            '#<label for="q-debris-amount">Amount to charge</label>\s*<span class="admin-label-with-tip__end">\s*<span class="cp-required">\*</span>\s*<x-admin.info-tip#',
            $debris
        );
        $places = file_get_contents(resource_path('views/admin/partials/places_address_field.blade.php'));
        $this->assertStringContainsString('admin-label-with-tip__end', $places);
        $this->assertStringContainsString('<span class="cp-required">*</span>', $places);
        $tipped = file_get_contents(resource_path('views/admin/partials/tipped_label.blade.php'));
        $this->assertMatchesRegularExpression(
            '#admin-label-with-tip__end[\s\S]*<span class="cp-required">\*</span>[\s\S]*info_tip#',
            $tipped
        );
        $this->assertDoesNotMatchRegularExpression(
            '#\{\{ \$last \}\}@if \(\$required\)<span class="cp-required">\*</span>#',
            $tipped
        );
        $reply = file_get_contents(resource_path('views/admin/partials/quotation_reply_to_accept.blade.php'));
        $this->assertMatchesRegularExpression(
            '#admin-label-with-tip__end[\s\S]*<span class="cp-required">\*</span>[\s\S]*<x-admin.info-tip label="Ask client to reply I accept">#',
            $reply
        );
    }

    public function test_restating_field_tips_were_removed_from_dense_clusters(): void
    {
        $drywall = file_get_contents(resource_path('views/admin/quotations/basement/_wizard_sections.blade.php'));
        $this->assertStringContainsString("'tipLabel' => 'Door openings'", $drywall);
        $this->assertStringContainsString("'tipLabel' => 'Window openings'", $drywall);
        $this->assertStringContainsString("'tipLabel' => 'Inside corners'", $drywall);
        $this->assertStringContainsString("'tipLabel' => 'Other openings'", $drywall);
        $this->assertStringContainsString('skipped on Level 0', $drywall);
        $this->assertStringContainsString('added for labour', $drywall);
        $this->assertStringNotContainsString("'tipLabel' => 'Outside corners'", $drywall);
        $this->assertStringContainsString("'tipLabel' => 'Opening area'", $drywall);
        $this->assertStringContainsString("'tipLabel' => 'Level of finish'", $drywall);

        $elec = file_get_contents(resource_path('views/admin/quotations/basement/_electrical_fields.blade.php'));
        $this->assertStringContainsString("'tipLabel' => 'New outlets'", $elec);
        $this->assertStringContainsString("'tipLabel' => 'Switches'", $elec);
        $this->assertStringContainsString("'tipLabel' => 'Ceiling lights'", $elec);
        $this->assertStringContainsString("\$brInfo['outlet_qty']", $elec);
        $this->assertStringContainsString("\$brInfo['switch_qty']", $elec);
        $this->assertStringContainsString('Bathroom spots', $drywall);
        $this->assertStringContainsString("'tipLabel' => 'Basement ceiling lighting'", $elec);
        $this->assertStringContainsString("'tipLabel' => 'Smoke / CO'", $elec);
        $this->assertStringContainsString("'info' => \$brInfo['include_bath_fixture']", $drywall);
        $this->assertStringContainsString("'info' => \$brInfo['include_bath_spot']", $drywall);
        $this->assertStringNotContainsString("'tipLabel' => 'Electrical scope'", $drywall);
        $this->assertStringNotContainsString("'tipLabel' => 'Plumbing scope'", $drywall);
        $this->assertStringNotContainsString("'tipLabel' => 'How many fixtures'", $drywall);
        $this->assertMatchesRegularExpression(
            '#Tile / vinyl\s+@include\(\'admin\.partials\.info_tip\'[\s\S]{0,160}tipLabel\' => \'Tile / vinyl\'#',
            $drywall
        );
        $this->assertStringContainsString("'tipLabel' => 'Electrical scope'", $elec);
        $this->assertStringContainsString("id=\"br-electrical-fields\"", $elec);
        $this->assertStringNotContainsString('label="Electrical scope"', $elec);
        $plumb = file_get_contents(resource_path('views/admin/quotations/basement/_plumbing_fields.blade.php'));
        $this->assertStringContainsString("'tipLabel' => 'Plumbing scope'", $plumb);
        $this->assertStringContainsString("id=\"br-plumbing-fields\"", $plumb);
        $this->assertStringNotContainsString('label="Plumbing scope"', $plumb);

        $shed = file_get_contents(resource_path('views/admin/quotations/shed/_wizard_sections.blade.php'));
        $this->assertStringNotContainsString("'tipLabel' => 'Wall height (ft)'", $shed);
        $this->assertStringContainsString("'tipLabel' => 'Area'", $shed);
        $this->assertStringContainsString('without a building permit', $shed);
        $this->assertStringContainsString("'tipLabel' => 'Length (ft)'", $shed);

        $paint = file_get_contents(resource_path('views/admin/quotations/painting/price_configuration.blade.php'));
        $this->assertStringNotContainsString('label="Paint price per gallon"', $paint);
        $this->assertStringContainsString('label="Material add-on fixed fee"', $paint);

        $fence = file_get_contents(resource_path('views/admin/quotations/fence/config/index.blade.php'));
        $this->assertStringNotContainsString('label="Labour rate 6′ fence"', $fence);
        $this->assertStringNotContainsString('label="Labour rate 8′ fence"', $fence);
        $this->assertStringContainsString('label="Labour per additional gate"', $fence);
    }

    public function test_qty_lw_puts_label_id_on_the_wrapper_not_the_lead(): void
    {
        $html = view('admin.quotations.basement._qty_lw', [
            'name' => 'area_sf',
            'id' => 'br-area',
            'label' => 'Total basement area (sq ft)',
            'labelId' => 'br-area-label',
            'value' => 100,
            'modeName' => 'qty_dims[area_sf][mode]',
            'lengthName' => 'qty_dims[area_sf][length_ft]',
            'widthName' => 'qty_dims[area_sf][width_ft]',
            'info' => 'Finished floor area.',
        ])->render();

        $this->assertMatchesRegularExpression(
            '/<span class="admin-label-with-tip"[^>]*id="br-area-label"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*id="br-area-label"/',
            $html
        );
        $this->assertStringContainsString('Total basement area (sq', $html);
        $this->assertStringContainsString('>ft)</label>', $html);
        $this->assertStringNotContainsString('sq ft) ft)', $html);
        $this->assertStringContainsString('admin-info-tip-wrap', $html);
    }

    public function test_electrical_lights_label_keeps_the_full_js_updated_span(): void
    {
        $src = file_get_contents(resource_path('views/admin/quotations/basement/_electrical_fields.blade.php'));
        $this->assertMatchesRegularExpression(
            '/<span id="br-lights-label">\{\{ \$brVal\(\'lighting_type\', \'spot\'\) === \'fixture\' \? \'Fixtures\' : \'Pot lights\' \}\}<\/span>/',
            $src
        );
        $this->assertStringContainsString('id="br-lights-label"', $src);
        $this->assertStringNotContainsString('Pot lights lights', $src);
    }

    public function test_handwritten_checkboxes_and_leftover_explainers_use_tips(): void
    {
        $trim = file_get_contents(resource_path('views/admin/quotations/trimming/create.blade.php'));
        $this->assertStringContainsString("'label' => 'Remove existing'", $trim);
        $this->assertStringContainsString("'label' => 'Caulk & paint'", $trim);
        $this->assertStringContainsString("'label' => 'Client supplies materials'", $trim);
        $this->assertStringNotContainsString('<span class="admin-checkbox__text">Remove existing</span>', $trim);

        $work = file_get_contents(resource_path('views/admin/quotations/standard/_work_card.blade.php'));
        $this->assertStringContainsString("'label' => 'HST included'", $work);
        $this->assertStringNotContainsString('<span class="admin-checkbox__text">HST included</span>', $work);

        $wall = file_get_contents(resource_path('views/admin/quotations/basement/_wall_primary_fields.blade.php'));
        $this->assertStringContainsString("'id' => 'br-wall-include-door'", $wall);
        $this->assertStringContainsString("'label' => 'Include a door'", $wall);

        $low = file_get_contents(resource_path('views/admin/quotations/low_deck/create.blade.php'));
        $this->assertStringContainsString("'id' => 'ld-under-deck-gravel'", $low);
        $this->assertStringContainsString("'label' => 'Gravel under the deck'", $low);

        $sync = file_get_contents(resource_path('views/admin/client_projects/_client_directory_sync.blade.php'));
        $this->assertStringContainsString("'label' => 'Update central client record when saving'", $sync);

        $invoice = file_get_contents(resource_path('views/admin/client_projects/invoice/edit.blade.php'));
        $this->assertStringContainsString('Required before the invoice can be sent to the client.', $invoice);
        $this->assertStringNotContainsString('<p class="admin-form-help admin-form-help--tight">Required before the invoice', $invoice);

        $cp = file_get_contents(resource_path('views/admin/client_projects/create.blade.php'));
        $this->assertStringContainsString('Optional — CC\'d on every client email.', $cp);
        $this->assertStringNotContainsString('cp-inline-help">(optional — CC', $cp);

        $mat = file_get_contents(resource_path('views/admin/materials/config.blade.php'));
        $this->assertStringContainsString('Fill blanks only (short names: use Short names Gemini above).', $mat);
        $this->assertStringNotContainsString('Categories &amp; units — fill blanks only', $mat);

        $paint = file_get_contents(resource_path('views/admin/quotations/painting/create.blade.php'));
        $this->assertStringContainsString('Optional — appears on the client PDF.', $paint);
        $this->assertStringNotContainsString('(optional — appears on client PDF)', $paint);

        $floor = file_get_contents(resource_path('views/admin/quotations/flooring/create.blade.php'));
        $this->assertStringContainsString('Optional — appears on the client PDF.', $floor);
        $this->assertStringNotContainsString('(optional — appears on client PDF)', $floor);

        $basementJs = file_get_contents(public_path('js/admin-basement-quotation.js'));
        $this->assertStringContainsString("wall: 'Basement area (sq ft, optional)'", $basementJs);
        $this->assertStringContainsString('AdminInfoTip.setLabelText', $basementJs);
    }
}

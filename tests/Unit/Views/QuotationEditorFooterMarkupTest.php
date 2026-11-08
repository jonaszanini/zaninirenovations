<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class QuotationEditorFooterMarkupTest extends TestCase
{
    /** @var list<string> */
    private const NON_PAINTING = [
        'standard',
        'fence',
        'paving',
        'flooring',
        'tile',
        'stairs',
        'low_deck',
        'basement',
        'trimming',
        'shed',
        'millwork',
    ];

    public function test_shared_footer_is_a_live_total_and_real_save(): void
    {
        $partial = file_get_contents(resource_path('views/admin/partials/quotation_editor_footer.blade.php'));
        $this->assertNotFalse($partial);
        $this->assertStringContainsString('data-qh-footer', $partial);
        $this->assertStringContainsString('data-qh-footer-amount', $partial);
        $this->assertStringContainsString('data-qh-footer-save', $partial);
        $this->assertStringContainsString('qh-sticky-footer', $partial);
        $this->assertStringContainsString('ClientProject::formatMoney', $partial);
        $this->assertStringContainsString('qh-sticky-footer__save-label">Publish', $partial);
        $this->assertStringContainsString('aria-label="Publish"', $partial);
        $this->assertStringNotContainsString('Save quotation', $partial);
        $this->assertStringNotContainsString('qh-sticky-footer__save-short', $partial);
        $this->assertStringNotContainsString('qh-sticky-footer__save-full', $partial);
        $this->assertStringContainsString('data-qh-save-intent="publish"', $partial);
        $this->assertStringContainsString('type="submit"', $partial);
        $this->assertStringNotContainsString('1.13', $partial);
    }

    public function test_footer_js_mirrors_shared_total_and_layout_excludes_painting(): void
    {
        $js = file_get_contents(public_path('js/admin-quotation-editor-footer.js'));
        $this->assertStringContainsString("doc.querySelector('[data-qh-footer]')", $js);
        $this->assertStringContainsString("quote:grand-total", $js);
        $this->assertStringContainsString('__quoteGrandTotal', $js);
        $this->assertStringContainsString('unavailable', $js);
        $this->assertStringContainsString('data-qh-footer-unavailable', $js);
        $this->assertStringContainsString('[data-qh-header] .qh-actions__save', $js);
        $this->assertStringContainsString('preventDefault', $js);
        $this->assertStringNotContainsString('1.13', $js);
        $this->assertDoesNotMatchRegularExpression('/\*\s*1\.13|\+\s*hst|\+\s*tax/i', $js);

        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $this->assertStringContainsString("request()->routeIs('admin.quotations.*') && ! request()->routeIs('admin.quotations.painting.*')", $layout);
        $this->assertStringContainsString('admin-quotation-editor-footer.js', $layout);

        $leave = file_get_contents(public_path('js/admin-quotation-leave-save.js'));
        $this->assertStringContainsString('[data-qh-footer-save]', $leave);
    }

    public function test_eleven_non_painting_editors_include_footer_and_painting_does_not(): void
    {
        foreach (self::NON_PAINTING as $type) {
            $blade = file_get_contents(resource_path('views/admin/quotations/'.$type.'/create.blade.php'));
            $this->assertNotFalse($blade, $type.' create missing');
            if ($type === 'millwork') {
                $this->assertStringContainsString('data-qh-footer', $blade);
                $this->assertStringContainsString('data-qh-footer-save', $blade);
                $this->assertStringContainsString('qh-sticky-footer__save-label">Publish', $blade);
                $this->assertStringContainsString('aria-label="Publish"', $blade);
                $this->assertStringContainsString('data-qh-save-intent="publish"', $blade);
                $this->assertStringContainsString('qh-sticky-footer', $blade);
                $this->assertStringContainsString('id="mw-totals-bar"', $blade);
            } else {
                $this->assertStringContainsString('admin.partials.quotation_editor_footer', $blade, $type.' must reuse the shared footer');
            }
            if ($type !== 'painting') {
                $this->assertStringContainsString('projectForReturn', $blade);
                $this->assertStringContainsString("'saveLabel' => 'Publish'", $blade, $type.' header must opt in to Publish');
            }
        }

        $painting = file_get_contents(resource_path('views/admin/quotations/painting/create.blade.php'));
        $this->assertStringNotContainsString('quotation_editor_footer', $painting);
        $this->assertStringNotContainsString('data-qh-footer', $painting);
        $this->assertStringNotContainsString('qh-sticky-footer', $painting);
        $this->assertStringNotContainsString("'saveLabel' => 'Publish'", $painting);
        $this->assertStringContainsString("route('admin.quotations.painting.index'", $painting);

        $standard = file_get_contents(resource_path('views/admin/quotations/standard/create.blade.php'));
        $this->assertStringNotContainsString('sq-form-actions', $standard);
    }

    public function test_additional_cost_amount_column_keeps_a_label_sized_min(): void
    {
        $blade = file_get_contents(resource_path('views/admin/quotations/_amount_lines.blade.php'));
        $this->assertNotFalse($blade);
        $this->assertMatchesRegularExpression(
            '/\.q-ac-row--with-category\s*\{[^}]*minmax\(8\.5rem/',
            $blade
        );
        $this->assertMatchesRegularExpression(
            '/@container \(max-width: 720px\)[\s\S]{0,280}minmax\(8\.5rem/',
            $blade
        );
        $this->assertDoesNotMatchRegularExpression(
            '/@container \(max-width: 720px\)[\s\S]{0,220}minmax\(0,\s*1fr\)\s+minmax\(10\.5rem/',
            $blade
        );
        $this->assertStringNotContainsString('aria-label="Materials or labour"', $blade);
        $js = file_get_contents(public_path('js/admin-additional-costs.js'));
        $this->assertStringNotContainsString('aria-label="Materials or labour"', $js);
    }
}

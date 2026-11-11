<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class QuotationEditorFlashMarkupTest extends TestCase
{
    public function test_shared_partials_and_layout_hide_the_old_banner(): void
    {
        $status = file_get_contents(resource_path('views/admin/partials/quotation_flash_status.blade.php'));
        $toast = file_get_contents(resource_path('views/admin/partials/quotation_flash_toast.blade.php'));
        $header = file_get_contents(resource_path('views/admin/partials/quotation_editor_header.blade.php'));
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $js = file_get_contents(public_path('js/admin-quotation-flash.js'));
        $toastJs = file_get_contents(public_path('js/admin-toast.js'));
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertStringContainsString('data-qh-flash-status', $status);
        $this->assertStringContainsString('data-qh-flash-status-text', $status);
        $this->assertStringContainsString('admin.partials.quotation_flash_status', $header);
        $this->assertStringContainsString('admin.partials.quotation_flash_toast', $header);

        $this->assertStringContainsString('data-qh-flash-toast', $toast);
        $this->assertStringContainsString('data-admin-toast-host', $toast);
        $this->assertStringContainsString('aria-live="polite"', $toast);
        $this->assertStringContainsString('data-admin-toast-close', $toast);
        $this->assertStringContainsString('data-qh-flash-duration="6000"', $toast);

        $this->assertStringContainsString('QuotationEditorFlash::isEditorRequest()', $layout);
        $this->assertStringContainsString('@unless (\App\Support\QuotationEditorFlash::isEditorRequest())', $layout);
        $this->assertStringContainsString('admin-quotation-flash.js', $layout);

        $this->assertStringContainsString('QUOTE_MS', $toastJs);
        $this->assertStringContainsString('data-qh-flash-toast', $toastJs);
        $this->assertStringContainsString('mouseenter', $toastJs);
        $this->assertStringContainsString("applyPlacement(node, 'top-right')", $toastJs);

        $this->assertStringContainsString('[data-qh-flash-toast]', $css);
        $this->assertStringContainsString('backdrop-filter', $css);
        $this->assertStringContainsString('.qh-flash-status', $css);
        $this->assertStringContainsString('.qh-crumb-row', $css);

        $this->assertStringContainsString('Saved and linked to this project', $js);
        $this->assertStringContainsString('Saved as a draft', $js);
        $this->assertDoesNotMatchRegularExpression('/[—–]/u', $js);
        $this->assertDoesNotMatchRegularExpression('/[—–]/u', $status.$toast);
    }

    public function test_every_quotation_create_reuses_the_shared_header(): void
    {
        $types = [
            'standard', 'basement', 'millwork', 'flooring', 'trimming', 'painting',
            'shed', 'tile', 'fence', 'paving', 'stairs', 'low_deck',
        ];
        foreach ($types as $type) {
            $blade = file_get_contents(resource_path('views/admin/quotations/'.$type.'/create.blade.php'));
            $this->assertStringContainsString(
                'admin.partials.quotation_editor_header',
                $blade,
                $type.' create must reuse the shared quotation header (toast + compact status)'
            );
        }
    }
}

<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class QuotationEditorHeaderMarkupTest extends TestCase
{
    public function test_shared_header_keeps_hotkey_and_draft_ids(): void
    {
        $partial = file_get_contents(resource_path('views/admin/partials/quotation_editor_header.blade.php'));
        $this->assertNotFalse($partial);
        $this->assertStringContainsString('data-qh-header', $partial);
        $this->assertStringContainsString('data-hotkey-save', $partial);
        $this->assertStringContainsString('data-qh-cancel', $partial);
        $this->assertStringContainsString('qh-actions__save', $partial);
        $this->assertStringContainsString('qh-actions__draft', $partial);
        $this->assertStringContainsString('qh-actions__cancel', $partial);
        $this->assertStringContainsString('← Back', $partial);
        $this->assertStringContainsString('→ Next', $partial);

        $creates = [
            'basement' => 'br-btn-draft',
            'millwork' => 'mw-btn-draft',
            'shed' => 'sh-btn-draft',
        ];
        foreach ($creates as $type => $draftId) {
            $blade = file_get_contents(resource_path('views/admin/quotations/'.$type.'/create.blade.php'));
            $this->assertStringContainsString('admin.partials.quotation_editor_header', $blade);
            $this->assertStringContainsString("'".$draftId."'", $blade);
        }
    }

    public function test_wizard_next_does_not_skip_invalid_steps(): void
    {
        $mw = file_get_contents(public_path('js/admin-millwork-quotation.js'));
        $sh = file_get_contents(public_path('js/admin-shed-quotation.js'));
        $br = file_get_contents(public_path('js/admin-basement-quotation.js'));
        $leave = file_get_contents(public_path('js/admin-quotation-leave-save.js'));

        $this->assertStringContainsString('function validateCurrentStep', $mw);
        $this->assertStringContainsString('function validateCurrentStep', $sh);
        $this->assertStringContainsString('function validateCurrentStep', $br);
        $this->assertStringContainsString('if (!validateCurrentStep()) return', $mw);
        $this->assertStringContainsString('if (!validateCurrentStep()) return', $sh);
        $this->assertStringContainsString('if (!validateCurrentStep()) return', $br);
        $this->assertStringContainsString('[data-qh-cancel]', $leave);
    }
}

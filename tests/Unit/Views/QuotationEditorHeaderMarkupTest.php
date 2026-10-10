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
            'fence' => 'fq-btn-draft',
            'standard' => 'st-btn-draft',
            'low_deck' => 'ld-btn-draft',
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
        $this->assertStringContainsString('Discard changes?', $leave);
        $this->assertDoesNotMatchRegularExpression(
            '/cancelBtn[\s\S]{0,500}submitLeave\(/',
            $leave
        );
        $this->assertStringContainsString('.qh-wizard[hidden]', file_get_contents(public_path('css/admin.css')));
        $this->assertStringContainsString('This step is missing required information', $mw);
        $this->assertStringContainsString('This step is missing required information', $sh);
        $this->assertStringContainsString('function restorePublishOnlyRequired', $leave);
        $this->assertStringContainsString('window.allowUnload = true', $leave);
        $this->assertStringContainsString('Quotation not saved yet', $leave);
        $canAutosave = preg_match('/function canAutosave\(form\) \{([\s\S]*?)\n  function /', $leave, $m)
            ? ($m[1] ?? '')
            : '';
        $this->assertStringNotContainsString('relaxPublishOnlyRequired', $canAutosave);
        $tile = file_get_contents(resource_path('views/admin/quotations/tile/create.blade.php'));
        $this->assertStringContainsString("saveIntentInput.value = draft ? 'draft' : 'publish'", $tile);
        $this->assertStringContainsString("isDraftInput.value = draft ? '1' : '0'", $tile);
        $this->assertStringContainsString("name=\"save_intent\"", $tile);
        $this->assertStringContainsString("id=\"tl-is-draft\" value=\"{{ \$isEdit && \$quotation->is_draft ? '1' : '0' }}\"", $tile);
    }

    public function test_action_row_then_step_bar_and_gear_in_step_two(): void
    {
        $header = file_get_contents(resource_path('views/admin/partials/quotation_editor_header.blade.php'));
        $this->assertNotFalse($header);
        $save = strpos($header, 'qh-actions__save');
        $draft = strpos($header, 'qh-actions__draft');
        $cancel = strpos($header, 'qh-actions__cancel');
        $wizard = strpos($header, 'qh-wizard');
        $back = strpos($header, '← Back');
        $status = strpos($header, 'br-step-nav__status');
        $next = strpos($header, '→ Next');
        $this->assertNotFalse($save);
        $this->assertNotFalse($draft);
        $this->assertNotFalse($cancel);
        $this->assertNotFalse($wizard);
        $this->assertNotFalse($back);
        $this->assertNotFalse($status);
        $this->assertNotFalse($next);
        $this->assertLessThan($draft, $save, 'Save quotation must precede Save as draft');
        $this->assertLessThan($cancel, $draft, 'Save as draft must precede Cancel');
        $this->assertLessThan($wizard, $cancel, 'Step bar must follow Cancel');
        $this->assertLessThan($back, $wizard, 'Back must be inside the step bar');
        $this->assertLessThan($status, $back, 'Step counter must follow Back');
        $this->assertLessThan($next, $status, 'Next must follow the step counter');
        $this->assertFalse(strpos($header, 'qh-price-gear'), 'Gear must not live in the shared header');

        $step2Sources = [
            'basement/_wizard_sections.blade.php',
            'millwork/_wizard_sections.blade.php',
            'shed/_wizard_sections.blade.php',
            'flooring/create.blade.php',
            'trimming/create.blade.php',
            'painting/create.blade.php',
            'tile/create.blade.php',
            'fence/create.blade.php',
            'paving/create.blade.php',
            'stairs/create.blade.php',
            'low_deck/create.blade.php',
        ];
        foreach ($step2Sources as $file) {
            $blade = file_get_contents(resource_path('views/admin/quotations/'.$file));
            $this->assertNotFalse($blade, $file.' missing');
            $step = strpos($blade, 'data-qh-step="2"');
            $gear = strpos($blade, 'quotation_price_config_gear');
            $this->assertNotFalse($step, "{$file} must mark step 2");
            $this->assertNotFalse($gear, "{$file} must include the Price configuration gear");
            $this->assertLessThan(
                $gear,
                $step,
                "{$file}: gear must sit after the step-2 marker (inside that block)"
            );
        }

        $standard = file_get_contents(resource_path('views/admin/quotations/standard/create.blade.php'));
        $this->assertStringNotContainsString('quotation_price_config_gear', $standard);
        $this->assertStringNotContainsString('qh-price-gear', $standard);
    }
}

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
        $this->assertStringContainsString('is_draft', $partial);
        $this->assertStringContainsString('$qhIsPublished', $partial);
        $this->assertStringContainsString('data-qh-cancel', $partial);
        $this->assertStringContainsString('qh-actions__save', $partial);
        $this->assertStringContainsString('qh-actions__draft', $partial);
        $this->assertStringContainsString('qh-actions__cancel', $partial);
        $this->assertStringContainsString('data-qh-save-intent="publish"', $partial);
        $this->assertStringContainsString('data-qh-save-intent="draft"', $partial);
        $this->assertStringContainsString('aria-label="{{ $qhSaveLabel }}"', $partial);
        $this->assertStringContainsString('aria-label="Save as draft"', $partial);
        $this->assertStringContainsString("\$saveLabel ?? 'Save quotation'", $partial);
        $this->assertStringContainsString('Save as draft', $partial);
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
        $this->assertStringContainsString('sectionKeys.length + 1', $br);
        $this->assertStringContainsString("key === 'review' ? 'Publish'", $br);
        $this->assertStringNotContainsString("key === 'review' ? 'Save quotation'", $br);
        $this->assertStringNotContainsString("key === 'review' ? 'Save'", $br);
        $this->assertStringNotContainsString("' of ' + sectionKeys.length", $br);
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
        $this->assertStringContainsString('data-qh-client-share', $leave);
        $this->assertStringContainsString('stop their link from working until you publish it again', $leave);
        $this->assertStringContainsString('admin-hotkey-save:abort', $leave);
        $this->assertStringContainsString('runDraftSave', $leave);
        $this->assertStringContainsString('submitEditorForm', $leave);
        $this->assertStringContainsString('window.AdminDialog.confirm', $leave);
        $dialog = file_get_contents(public_path('js/admin-dialog.js'));
        $this->assertStringContainsString('admin-dialog:cancel', $dialog);
        $hotkey = file_get_contents(public_path('js/admin-hotkey-save.js'));
        $this->assertMatchesRegularExpression(
            '/function bind\(\)\s*\{[\s\S]*addEventListener\(\s*ABORT_EVENT/',
            $hotkey
        );
        $this->assertMatchesRegularExpression(
            '/function bind\(\)\s*\{[\s\S]*addEventListener\(\s*DIALOG_CANCEL_EVENT/',
            $hotkey
        );
        $this->assertStringContainsString("data-qh-draft-bypass", $hotkey);
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

        foreach ([
            'standard/create.blade.php',
            'basement/create.blade.php',
            'millwork/create.blade.php',
            'flooring/create.blade.php',
            'trimming/create.blade.php',
            'painting/create.blade.php',
            'shed/create.blade.php',
            'tile/create.blade.php',
            'fence/create.blade.php',
            'paving/create.blade.php',
            'stairs/create.blade.php',
            'low_deck/create.blade.php',
        ] as $file) {
            $blade = file_get_contents(resource_path('views/admin/quotations/'.$file));
            $this->assertStringContainsString('data-qh-client-share', $blade, $file.' must mark sent/published quotes for the draft confirm');
        }
    }

    public function test_header_hotkey_follows_new_draft_and_published_state(): void
    {
        $base = [
            'typeLabel' => 'Standard',
            'listUrl' => '/admin/quotations',
            'saveId' => 'sq-btn-save',
            'draftId' => 'st-btn-draft',
            'formId' => 'sq-standard-form',
        ];

        $new = view('admin.partials.quotation_editor_header', $base + ['isEdit' => false])->render();
        $this->assertMatchesRegularExpression('/id="st-btn-draft"[^>]*data-hotkey-save/', $new);
        $this->assertDoesNotMatchRegularExpression('/id="sq-btn-save"[^>]*data-hotkey-save/', $new);
        $this->assertStringContainsString('aria-label="Save quotation"', $new);
        $this->assertStringContainsString('Save quotation', $new);

        $optIn = view('admin.partials.quotation_editor_header', $base + [
            'isEdit' => false,
            'saveLabel' => 'Publish',
        ])->render();
        $this->assertStringContainsString('aria-label="Publish"', $optIn);
        $this->assertMatchesRegularExpression('/aria-label="Publish"[^>]*>[\s\S]{0,120}Publish/', $optIn);
        $this->assertStringNotContainsString('aria-label="Save quotation"', $optIn);

        $draft = view('admin.partials.quotation_editor_header', $base + [
            'isEdit' => true,
            'quotation' => (object) ['is_draft' => true],
        ])->render();
        $this->assertMatchesRegularExpression('/id="st-btn-draft"[^>]*data-hotkey-save/', $draft);
        $this->assertDoesNotMatchRegularExpression('/id="sq-btn-save"[^>]*data-hotkey-save/', $draft);

        $published = view('admin.partials.quotation_editor_header', $base + [
            'isEdit' => true,
            'quotation' => (object) ['is_draft' => false],
        ])->render();
        $this->assertMatchesRegularExpression('/id="sq-btn-save"[^>]*data-hotkey-save/', $published);
        $this->assertDoesNotMatchRegularExpression('/id="st-btn-draft"[^>]*data-hotkey-save/', $published);
    }
}

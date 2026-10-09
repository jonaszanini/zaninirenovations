<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class AdminHotkeySaveMarkupTest extends TestCase
{
    public function test_key_admin_pages_opt_in_the_safe_save_button(): void
    {
        $files = [
            'resources/views/admin/quotations/standard/create.blade.php' => ['data-hotkey-save', 'form="sq-standard-form"'],
            'resources/views/admin/quotations/fence/create.blade.php' => ['data-hotkey-save', 'form="fq-form"'],
            'resources/views/admin/quotations/basement/create.blade.php' => ['id="br-btn-draft" data-hotkey-save'],
            'resources/views/admin/quotations/millwork/create.blade.php' => ['id="mw-btn-draft" data-hotkey-save'],
            'resources/views/admin/quotations/shed/create.blade.php' => ['id="sh-btn-draft" data-hotkey-save'],
            'resources/views/admin/client_projects/edit.blade.php' => ['data-hotkey-save', 'Save changes'],
            'resources/views/admin/clients/edit.blade.php' => ['data-hotkey-save'],
            'resources/views/admin/client_projects/invoice/edit.blade.php' => ['data-cp-invoice-intent="draft"', 'data-hotkey-save'],
            'resources/views/admin/quotations/shared_prices/edit.blade.php' => ['data-hotkey-save', 'Save shared prices'],
            'resources/views/admin/utilities/_room_area_tool.blade.php' => ['data-ra-save-room data-hotkey-save'],
            'resources/views/admin/blog/posts/form.blade.php' => ['id="bp-btn-save"', 'data-hotkey-save'],
            'resources/views/admin/ai_instructions/edit.blade.php' => ['data-hotkey-save', 'Save changes'],
            'resources/views/admin/quotations/basement/price_configuration.blade.php' => ['data-hotkey-save', 'Save configuration'],
        ];

        foreach ($files as $rel => $needles) {
            $html = file_get_contents(base_path($rel));
            $this->assertNotFalse($html, $rel.' missing');
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, $rel.' should contain '.$needle);
            }
        }
    }

    public function test_publish_and_send_buttons_are_not_opted_in(): void
    {
        $pairs = [
            'resources/views/admin/quotations/basement/create.blade.php' => 'id="br-btn-publish"',
            'resources/views/admin/quotations/millwork/create.blade.php' => 'id="mw-btn-publish"',
            'resources/views/admin/quotations/shed/create.blade.php' => 'id="sh-btn-publish"',
            'resources/views/admin/blog/posts/form.blade.php' => 'id="bp-btn-publish"',
        ];

        foreach ($pairs as $rel => $idNeedle) {
            $html = file_get_contents(base_path($rel));
            $this->assertNotFalse($html);
            if (! preg_match('/<button[^>]*'.$idNeedle.'[^>]*>/', $html, $match)) {
                $this->fail($rel.' is missing '.$idNeedle);
            }
            $this->assertStringNotContainsString('data-hotkey-save', $match[0], $idNeedle.' must not have data-hotkey-save');
        }

        $invoice = file_get_contents(resource_path('views/admin/client_projects/invoice/edit.blade.php'));
        preg_match_all('/<button[^>]*data-cp-invoice-intent="close"[^>]*>/', $invoice, $closes);
        $this->assertNotEmpty($closes[0]);
        foreach ($closes[0] as $button) {
            $this->assertStringNotContainsString('data-hotkey-save', $button);
        }
    }
}

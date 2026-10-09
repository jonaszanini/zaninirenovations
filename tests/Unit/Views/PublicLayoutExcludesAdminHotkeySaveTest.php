<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class PublicLayoutExcludesAdminHotkeySaveTest extends TestCase
{
    public function test_public_layout_does_not_include_admin_save_scripts(): void
    {
        $html = file_get_contents(resource_path('views/site/layouts/public.blade.php'));
        $this->assertNotFalse($html);
        $this->assertStringNotContainsString('admin-hotkey-save.js', $html);
        $this->assertStringNotContainsString('admin-toast.js', $html);
        $this->assertStringNotContainsString('data-hotkey-save', $html);
    }

    public function test_login_layout_does_not_include_admin_save_scripts(): void
    {
        $html = file_get_contents(resource_path('views/admin/auth/login.blade.php'));
        $this->assertNotFalse($html);
        $this->assertStringNotContainsString('admin-hotkey-save.js', $html);
        $this->assertStringNotContainsString('admin-toast.js', $html);
    }

    public function test_admin_layout_includes_toast_and_hotkey_scripts(): void
    {
        $html = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $this->assertNotFalse($html);
        $this->assertStringContainsString('admin-hotkey-save.js', $html);
        $this->assertStringContainsString('admin-toast.js', $html);
        $this->assertStringContainsString('id="admin-toast-flash"', $html);
    }
}

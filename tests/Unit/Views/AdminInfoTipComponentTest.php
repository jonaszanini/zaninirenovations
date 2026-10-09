<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class AdminInfoTipComponentTest extends TestCase
{
    public function test_info_tip_component_is_accessible_and_uses_admin_tokens(): void
    {
        $blade = (string) file_get_contents(resource_path('views/components/admin/info-tip.blade.php'));

        $this->assertStringContainsString("class('admin-info-tip')", $blade);
        $this->assertStringContainsString('aria-expanded="false"', $blade);
        $this->assertStringContainsString('aria-controls=', $blade);
        $this->assertStringContainsString('admin-info-tip__bubble', $blade);
        $this->assertStringContainsString('role="tooltip"', $blade);

        $css = (string) file_get_contents(public_path('css/admin.css'));
        $this->assertStringContainsString('.admin-info-tip {', $css);
        $this->assertStringContainsString('var(--admin-primary)', $css);
        $this->assertStringContainsString('min(280px, 70vw)', $css);
    }
}

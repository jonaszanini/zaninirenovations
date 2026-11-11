<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class AdminActionsDropdownScriptTest extends TestCase
{
    public function test_click_listener_uses_capture_so_row_stop_propagation_cannot_swallow_taps(): void
    {
        $js = file_get_contents(public_path('js/admin-actions-dropdown.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString("closest('.admin-actions-dropdown__toggle')", $js);
        $this->assertMatchesRegularExpression(
            "/addEventListener\\('click',[\\s\\S]{0,900},\\s*true\\)/",
            $js
        );
        $this->assertStringContainsString("e.key === 'Escape'", $js);
        $this->assertStringContainsString('document.body.appendChild(menu)', $js);
        $this->assertStringContainsString("closeAll(null, false)", $js);
    }
}

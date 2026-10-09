<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class AdminDatePickerThemeTest extends TestCase
{
    public function test_dark_theme_sets_color_scheme_on_date_time_inputs(): void
    {
        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($dark);

        $this->assertMatchesRegularExpression(
            '/html\.admin-dark input\[type="date"\],\s*html\.admin-dark input\[type="time"\],\s*html\.admin-dark input\[type="datetime-local"\]\s*\{[^}]*color-scheme:\s*dark/s',
            $dark
        );
        $this->assertDoesNotMatchRegularExpression(
            '/::-webkit-calendar-picker-indicator\s*\{[^}]*filter:\s*invert/s',
            $dark
        );
        $this->assertMatchesRegularExpression(
            '/html\.admin-dark input\[type="time"\]::-webkit-calendar-picker-indicator/s',
            $dark
        );
    }

    public function test_light_theme_sets_color_scheme_on_date_time_inputs(): void
    {
        $light = file_get_contents(public_path('css/admin.css'));
        $this->assertNotFalse($light);
        $this->assertMatchesRegularExpression(
            '/\.admin-body input\[type="date"\],\s*\.admin-body input\[type="time"\],\s*\.admin-body input\[type="datetime-local"\]\s*\{[^}]*color-scheme:\s*light/s',
            $light
        );
    }

    public function test_admin_layout_loads_date_picker_script(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $this->assertNotFalse($layout);
        $this->assertStringContainsString('js/admin-date-picker.js', $layout);
        $this->assertFileExists(public_path('js/admin-date-picker.js'));

        $js = file_get_contents(public_path('js/admin-date-picker.js'));
        $this->assertStringContainsString('showPicker', $js);
        $this->assertStringContainsString("pointerType", $js);
        $this->assertStringContainsString("'touch'", $js);
        $this->assertStringContainsString("'pen'", $js);
    }
}

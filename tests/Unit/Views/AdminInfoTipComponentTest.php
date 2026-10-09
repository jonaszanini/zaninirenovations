<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminInfoTipComponentTest extends TestCase
{
    public function test_info_tip_renders_button_sibling_bubble_and_escapes_text(): void
    {
        $html = Blade::render(
            '<x-admin.info-tip text="Hello <em>xss</em>" label="About labour credit" />'
        );

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-label="About labour credit"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls=', $html);
        $this->assertStringContainsString('aria-describedby=', $html);
        $this->assertStringContainsString('admin-info-tip-wrap', $html);
        $this->assertStringContainsString('Hello &lt;em&gt;xss&lt;/em&gt;', $html);
        $this->assertStringNotContainsString('<em>xss</em>', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*>[\s\S]*admin-info-tip__bubble[\s\S]*<\/button>/',
            $html
        );

        $this->assertSame(1, preg_match('/aria-controls="([^"]+)"/', $html, $controls));
        $this->assertSame(1, preg_match('/aria-describedby="([^"]+)"/', $html, $described));
        $this->assertSame($controls[1], $described[1]);
        $this->assertSame(1, preg_match(
            '/id="([^"]+)"[^>]*class="[^"]*admin-info-tip__bubble/',
            $html,
            $bubble
        ));
        $this->assertSame($controls[1], $bubble[1]);
    }

    public function test_info_tip_ids_are_unique_across_renders(): void
    {
        $first = Blade::render('<x-admin.info-tip text="One" label="About labour credit" />');
        $second = Blade::render('<x-admin.info-tip text="Two" label="About labour credit" />');

        preg_match('/aria-controls="([^"]+)"/', $first, $a);
        preg_match('/aria-controls="([^"]+)"/', $second, $b);
        $this->assertNotSame('', $a[1] ?? '');
        $this->assertNotSame($a[1], $b[1] ?? '');
    }

    public function test_info_tip_slot_allows_trusted_html(): void
    {
        $html = Blade::render(
            '<x-admin.info-tip label="About labour credit"><strong>Trusted</strong></x-admin.info-tip>'
        );

        $this->assertStringContainsString('<strong>Trusted</strong>', $html);
        $this->assertStringContainsString('type="button"', $html);
    }

    public function test_info_tip_css_fits_390px_and_js_helper_exists(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));
        $this->assertStringContainsString('max-width: min(273px, calc(100vw - 24px))', $css);

        $js = (string) file_get_contents(public_path('js/admin-dialog.js'));
        $this->assertStringContainsString('window.adminInfoTip', $js);
        $this->assertStringContainsString('html: function (text, label)', $js);
        $this->assertStringContainsString("e.target.closest('.admin-info-tip__bubble')", $js);
        $this->assertStringContainsString('function positionBubble', $js);
    }
}

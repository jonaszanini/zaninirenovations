<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminInfoTipTest extends TestCase
{
    public function test_component_renders_accessible_sibling_button_and_keeps_text(): void
    {
        $html = Blade::render('<x-admin.info-tip label="Board style">Clear span between posts is at most the max spacing.</x-admin.info-tip>');

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('class="admin-info-tip"', $html);
        $this->assertStringContainsString('aria-label="About Board style"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('aria-describedby="', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('Clear span between posts is at most the max spacing.', $html);
        $this->assertStringContainsString('fa-info-circle', $html);
        $this->assertMatchesRegularExpression('/<\/button>\s*<span[^>]*class="admin-info-tip__bubble"/', $html);
        $buttonEnd = strpos($html, '</button>');
        $this->assertNotFalse($buttonEnd);
        $this->assertStringNotContainsString('admin-info-tip__bubble', substr($html, 0, $buttonEnd));
    }

    public function test_text_prop_is_escaped_and_slot_html_is_trusted(): void
    {
        $escaped = Blade::render('<x-admin.info-tip label="SKU" :text="$text" />', [
            'text' => '<script>alert(1)</script>',
        ]);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $escaped);

        $slot = Blade::render('<x-admin.info-tip label="Rates"><strong>Trusted</strong></x-admin.info-tip>');
        $this->assertStringContainsString('<strong>Trusted</strong>', $slot);
    }

    public function test_each_render_gets_a_unique_bubble_id(): void
    {
        $html = Blade::render(
            '<x-admin.info-tip label="A">One</x-admin.info-tip><x-admin.info-tip label="B">Two</x-admin.info-tip>'
        );

        preg_match_all('/aria-controls="([^"]+)"/', $html, $controls);
        preg_match_all('/aria-describedby="([^"]+)"/', $html, $described);
        preg_match_all('/id="(admin-info-tip-[^"]+)"/', $html, $ids);

        $this->assertCount(2, $controls[1]);
        $this->assertNotSame($controls[1][0], $controls[1][1]);
        $this->assertSame($controls[1], $described[1]);
        $this->assertSame($controls[1], $ids[1]);
    }

    public function test_include_wrapper_uses_the_slot_api(): void
    {
        $html = view('admin.partials.info_tip', [
            'text' => 'First gate is included in the per-foot rate.',
            'tipLabel' => 'Labour rate',
        ])->render();

        $this->assertStringContainsString('aria-label="About Labour rate"', $html);
        $this->assertStringContainsString('First gate is included in the per-foot rate.', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('aria-describedby="', $html);
    }

    public function test_empty_text_renders_nothing(): void
    {
        $html = view('admin.partials.info_tip', ['text' => null])->render();

        $this->assertSame('', trim($html));
    }

    public function test_text_attribute_matches_slot_api(): void
    {
        $html = Blade::render('<x-admin.info-tip :text="$text" label="Existing post credit" />', [
            'text' => 'An existing-post tick that actually removes a post also subtracts the configured labour credit.',
        ]);

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-label="About Existing post credit"', $html);
        $this->assertStringContainsString('An existing-post tick that actually removes a post also subtracts the configured labour credit.', $html);
        $this->assertMatchesRegularExpression('/<\/button>\s*<span[^>]*class="admin-info-tip__bubble"/', $html);
    }
}

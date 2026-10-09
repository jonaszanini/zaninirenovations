<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminInfoTipTest extends TestCase
{
    public function test_component_renders_accessible_button_and_keeps_text(): void
    {
        $html = Blade::render('<x-admin.info-tip>Clear span between posts is at most the max spacing.</x-admin.info-tip>');

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('class="admin-info-tip"', $html);
        $this->assertStringContainsString('aria-label="More info"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('Clear span between posts is at most the max spacing.', $html);
        $this->assertStringContainsString('fa-info-circle', $html);
    }

    public function test_include_wrapper_uses_the_slot_api(): void
    {
        $html = view('admin.partials.info_tip', [
            'text' => 'First gate is included in the per-foot rate.',
        ])->render();

        $this->assertStringContainsString('aria-label="More info"', $html);
        $this->assertStringContainsString('First gate is included in the per-foot rate.', $html);
        $this->assertStringContainsString('aria-controls="', $html);
    }

    public function test_empty_text_renders_nothing(): void
    {
        $html = view('admin.partials.info_tip', ['text' => null])->render();

        $this->assertSame('', trim($html));
    }

    public function test_text_attribute_matches_slot_api(): void
    {
        $html = Blade::render('<x-admin.info-tip :text="$text" />', [
            'text' => 'An existing-post tick that actually removes a post also subtracts the configured labour credit.',
        ]);

        $this->assertStringContainsString('class="admin-info-tip"', $html);
        $this->assertStringContainsString('aria-label="More info"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('An existing-post tick that actually removes a post also subtracts the configured labour credit.', $html);
    }
}

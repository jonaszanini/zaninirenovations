<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class FenceQuotationFormRestoreTest extends TestCase
{
    public function test_render_sides_casts_posted_zero_as_unchecked(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));

        $this->assertStringContainsString('function flagOn(', $blade);
        $this->assertStringContainsString("value === '1'", $blade);
        $this->assertStringContainsString('FenceSideFlags::normaliseSides', $blade);

        $this->assertStringContainsString('flagOn(side.already_have_post_left)', $blade);
        $this->assertStringContainsString('flagOn(side.already_have_post_right)', $blade);
        $this->assertStringContainsString('flagOn(side.gate_goes_here)', $blade);
        $this->assertStringContainsString('flagOn(side.connected_to_previous)', $blade);

        $this->assertStringNotContainsString('!!(side && side.already_have_post_left)', $blade);
        $this->assertStringNotContainsString('!!(side && side.already_have_post_right)', $blade);
        $this->assertStringNotContainsString('!!(side && side.gate_goes_here)', $blade);
        $this->assertStringNotContainsString('connected_to_previous === false ? false : true', $blade);
    }

    public function test_editor_exposes_existing_post_labour_credit_line(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));

        $this->assertStringContainsString('existing-post tick that actually removes a post', $blade);
        $this->assertStringContainsString('Existing post credit: -$', $blade);
        $this->assertStringContainsString('existing_post_labour_credit', $blade);
        $this->assertStringContainsString('fq-t-existing-post-credit-row', $blade);
        $this->assertStringNotContainsString('data-cp-money-entry="direct"', $blade);
    }

    public function test_config_page_uses_calculator_style_money_input_for_existing_post_credit(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/config/index.blade.php'));

        $this->assertStringContainsString('Labour credit per existing post', $blade);
        $this->assertStringContainsString('name="existing_post_labour_credit"', $blade);
        $this->assertStringContainsString('id="fq-existing-post-credit"', $blade);
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*class="[^"]*cp-money-input/',
            $blade
        );
        $this->assertStringNotContainsString('data-cp-money-entry="direct"', $blade);
    }
}

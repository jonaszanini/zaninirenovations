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
}

<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FencePriceBannerTipTest extends TestCase
{
    public function test_fence_banner_passes_tip_text_and_about_saved_unit_prices_label(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));

        $this->assertStringContainsString("'tipText'", $blade);
        $this->assertStringContainsString("'tipLabel' => 'About saved unit prices'", $blade);
        $this->assertStringContainsString('Save keeps the unit prices from the last save', $blade);
        $this->assertStringNotContainsString('FenceQuotationInfoTipTest', $blade);
    }

    public function test_info_tip_partial_renders_tip_text_and_label(): void
    {
        $html = Blade::render(
            '@include("admin.partials.info_tip", $tip)',
            [
                'tip' => [
                    'tipText' => 'Save keeps the unit prices from the last save. Quantities still follow the current runs. Re-price updates unit prices from the catalogue.',
                    'tipLabel' => 'About saved unit prices',
                ],
            ]
        );

        $this->assertStringContainsString('aria-label="About saved unit prices"', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('Save keeps the unit prices from the last save', $html);
    }
}

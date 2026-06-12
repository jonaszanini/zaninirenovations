<?php

namespace Tests\Unit\Services;

use App\Models\PaintingPaintLine;
use App\Models\PaintingSettings;
use App\Services\PaintingQuotationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaintingQuotationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_net_area_labor_materials_and_hst(): void
    {
        $settings = PaintingSettings::current();
        $paintLine = PaintingPaintLine::query()->where('key', 'dulux_lifemaster')->firstOrFail();

        $calc = (new PaintingQuotationCalculator)->calculate([
            'total_property_sqf' => 2000,
            'excluded_areas' => [
                ['name' => 'Bedroom 1', 'mode' => 'dimensions', 'length_ft' => 10, 'width_ft' => 12],
                ['name' => 'Bathrooms', 'mode' => 'sqf', 'sqf' => 100],
            ],
            'paint_line_id' => $paintLine->id,
            'coats' => 2,
            'surface_prep_description' => 'Prep text',
        ], $settings);

        $this->assertSame(1880.0, $calc['scope']['net_painting_area_sqf']);
        $this->assertSame(27, $calc['scope']['gallons_needed']);

        $labor = round(1880 * 2.51, 2);
        $materials = round(27 * 55 + 100, 2);
        $subtotal = round($labor + $materials, 2);
        $hst = round($subtotal * 0.13, 2);

        $this->assertSame($labor, $calc['summary']['labor_pre_hst']);
        $this->assertSame($materials, $calc['summary']['materials_pre_hst']);
        $this->assertSame($subtotal, $calc['summary']['subtotal_pre_hst']);
        $this->assertSame($hst, $calc['summary']['hst']);
        $this->assertSame(round($subtotal + $hst, 2), $calc['summary']['grand_total']);
    }
}

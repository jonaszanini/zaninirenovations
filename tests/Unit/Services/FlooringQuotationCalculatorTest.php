<?php

namespace Tests\Unit\Services;

use App\Models\FlooringSettings;
use App\Models\FlooringType;
use App\Services\FlooringQuotationCalculator;
use Tests\TestCase;

class FlooringQuotationCalculatorTest extends TestCase
{
    public function test_calculate_splits_labor_and_materials(): void
    {
        $settings = new FlooringSettings([
            'remove_carpet_dispose_rate' => 1.50,
            'rescrew_subfloor_rate' => 350.00,
            'install_shoe_moulding_rate' => 3.50,
            'caulk_paint_shoe_moulding_rate' => 2.25,
            'closet_door_reinstall_rate' => 75.00,
        ]);

        $type = new FlooringType([
            'name' => 'LVP Click System',
            'installation_price_per_sqft' => 4.25,
        ]);
        $type->id = 1;

        $inputs = [
            'flooring_type_id' => 1,
            'square_footage' => 200,
            'remove_carpet' => true,
            'rescrew_subfloor' => false,
            'install_shoe_moulding' => false,
            'shoe_moulding_linear_ft' => 0,
            'caulk_paint_shoe_moulding' => false,
            'caulk_paint_linear_ft' => 0,
            'closet_doors' => false,
            'closet_door_qty' => 0,
            'include_material_details' => true,
            'material_cost_per_sqft' => 2.50,
            'material_name' => 'Test Brand',
            'material_color' => 'Oak',
            'material_url' => 'https://example.com/floor',
        ];

        $calc = new FlooringQuotationCalculator;
        $result = $calc->calculate($inputs, $settings, collect([$type]));

        $this->assertSame(1150.0, $result['summary']['labor_pre_hst']);
        $this->assertSame(500.0, $result['summary']['materials_pre_hst']);
        $this->assertSame(1650.0, $result['summary']['subtotal_pre_hst']);
        $this->assertSame(214.5, $result['summary']['hst']);
        $this->assertSame(1864.5, $result['summary']['grand_total']);
    }
}

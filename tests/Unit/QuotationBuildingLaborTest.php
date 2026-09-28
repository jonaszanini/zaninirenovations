<?php

namespace Tests\Unit;

use App\Models\FlooringSettings;
use App\Models\FlooringType;
use App\Services\FlooringQuotationCalculator;
use App\Services\StandardQuotationCalculator;
use App\Support\QuotationBuildingLabor;
use Tests\TestCase;

class QuotationBuildingLaborTest extends TestCase
{
    public function test_disabled_adds_nothing(): void
    {
        $applied = QuotationBuildingLabor::apply(1000, ['include_building' => false, 'building_labor_percent' => 30]);

        $this->assertSame(0.0, $applied['surcharge']);
        $this->assertSame(1000.0, $applied['total']);
        $this->assertNull(QuotationBuildingLabor::breakdownRow(1000, ['include_building' => false]));
    }

    public function test_default_is_thirty_percent(): void
    {
        $applied = QuotationBuildingLabor::apply(1000, ['include_building' => true]);

        $this->assertSame(30.0, $applied['percent']);
        $this->assertSame(300.0, $applied['surcharge']);
        $this->assertSame(1300.0, $applied['total']);
    }

    public function test_custom_percent_is_clamped(): void
    {
        $this->assertSame(250.0, QuotationBuildingLabor::surchargeAmount(1000, ['include_building' => '1', 'building_labor_percent' => 25]));
        $this->assertSame(QuotationBuildingLabor::MAX_PERCENT, QuotationBuildingLabor::percent(['building_labor_percent' => 999]));
        $this->assertSame(0.0, QuotationBuildingLabor::percent(['building_labor_percent' => -5]));
    }

    public function test_flooring_surcharge_applies_to_labour_only(): void
    {
        $settings = new FlooringSettings([
            'remove_carpet_dispose_rate' => 1.50,
            'rescrew_subfloor_rate' => 350.00,
            'install_shoe_moulding_rate' => 3.50,
            'caulk_paint_shoe_moulding_rate' => 2.25,
            'closet_door_reinstall_rate' => 75.00,
        ]);
        $type = new FlooringType(['name' => 'LVP', 'installation_price_per_sqft' => 4.25]);
        $type->id = 1;

        $inputs = [
            'flooring_type_id' => 1,
            'square_footage' => 200,
            'remove_carpet' => true,
            'include_material_details' => true,
            'material_cost_per_sqft' => 2.50,
            'material_waste_percent' => 5,
            'material_delivery_fee' => 60,
            'include_building' => true,
            'building_labor_percent' => 30,
        ];

        $result = (new FlooringQuotationCalculator)->calculate($inputs, $settings, collect([$type]));

        $this->assertSame(1495.0, $result['summary']['labor_pre_hst']);
        $this->assertSame(585.0, $result['summary']['materials_pre_hst']);
        $keys = array_column($result['breakdown'] ?? [], 'key');
        $this->assertContains('building', $keys);
    }

    public function test_standard_compute_works_adds_surcharge_and_hst(): void
    {
        $works = [[
            'title' => 'Install',
            'materials' => [],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 1000,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
        ]];

        $calc = new StandardQuotationCalculator;
        $base = $calc->computeWorks($works, false, 0, true);
        $withBuilding = $calc->computeWorks($works, false, 0, true, true, 30);

        $this->assertEqualsWithDelta(300.0, $withBuilding['building_labor_surcharge'], 0.01);
        $this->assertEqualsWithDelta($base['labour_total'] + 300, $withBuilding['labour_total'], 0.01);
        $this->assertEqualsWithDelta($base['labour_hst'] + 39, $withBuilding['labour_hst'], 0.01);
        $this->assertEqualsWithDelta($base['grand_total'] + 339, $withBuilding['grand_total'], 0.01);
        $this->assertEqualsWithDelta($withBuilding['labour_total'], $withBuilding['works'][0]['labour_total'], 0.01);
    }
}

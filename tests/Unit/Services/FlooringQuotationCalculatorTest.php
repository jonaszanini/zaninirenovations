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
            'install_baseboard' => false,
            'baseboard_linear_ft' => 0,
            'caulk_paint_baseboard' => false,
            'closet_doors' => false,
            'closet_door_qty' => 0,
            'include_material_details' => true,
            'material_cost_per_sqft' => 2.50,
            'material_waste_percent' => 5,
            'material_delivery_fee' => 60,
            'material_name' => 'Test Brand',
            'material_color' => 'Oak',
            'material_url' => 'https://example.com/floor',
        ];

        $calc = new FlooringQuotationCalculator;
        $result = $calc->calculate($inputs, $settings, collect([$type]));

        $this->assertSame(1150.0, $result['summary']['labor_pre_hst']);
        $this->assertSame(585.0, $result['summary']['materials_pre_hst']);
        $this->assertSame(1735.0, $result['summary']['subtotal_pre_hst']);
        $this->assertSame(225.55, $result['summary']['hst']);
        $this->assertSame(1960.55, $result['summary']['grand_total']);
        $this->assertSame(585.0, $result['material_selection']['total_pre_tax']);
    }

    public function test_shoe_moulding_uses_library_flat_rate_when_present(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('shared_works')) {
                $this->markTestSkipped('Shared price library is not migrated.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable for shared price library.');
        }

        $work = \App\Models\SharedWork::query()->where('key', 'shoe_install')->first();
        if (! $work) {
            $this->markTestSkipped('shoe_install is not seeded.');
        }
        $work->flat_rate = 5.00;
        $work->save();
        \App\Services\SharedWorkCatalog::forgetCache();

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

        $result = (new FlooringQuotationCalculator)->calculate([
            'flooring_type_id' => 1,
            'square_footage' => 100,
            'install_shoe_moulding' => true,
            'shoe_moulding_linear_ft' => 40,
            'include_material_details' => false,
        ], $settings, collect([$type]));

        $shoe = collect($result['breakdown'])->firstWhere('key', 'shoe_moulding');
        $this->assertNotNull($shoe);
        $this->assertEqualsWithDelta(200.0, (float) $shoe['amount'], 0.01);

        \App\Services\SharedWorkCatalog::forgetCache();
    }

    public function test_baseboard_add_ons_use_fallback_sell_rates(): void
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

        $result = (new FlooringQuotationCalculator)->calculate([
            'flooring_type_id' => 1,
            'square_footage' => 100,
            'install_baseboard' => true,
            'caulk_paint_baseboard' => true,
            'baseboard_linear_ft' => 40,
            'include_material_details' => false,
        ], $settings, collect([$type]));

        $install = collect($result['breakdown'])->firstWhere('key', 'baseboard');
        $caulk = collect($result['breakdown'])->firstWhere('key', 'caulk_paint_baseboard');
        $this->assertNotNull($install);
        $this->assertNotNull($caulk);
        $this->assertEqualsWithDelta(40 * $settings->baseboardInstallRate(), (float) $install['amount'], 0.01);
        $this->assertEqualsWithDelta(40 * $settings->baseboardCaulkPaintRate(), (float) $caulk['amount'], 0.01);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\TrimmingSettings;
use App\Services\TrimmingQuotationCalculator;
use Tests\TestCase;

class TrimmingQuotationCalculatorTest extends TestCase
{
    public function test_empty_scope_has_zero_totals(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate(
            ['enabled_families' => []],
            $this->settings()
        );

        $this->assertSame(0.0, $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['grand_total']);
        $this->assertSame(0.0, $calc['scope']['total_lf']);
    }

    public function test_baseboard_takeoff_uses_waste_and_stick_packaging(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 80,
            'include_baseboard' => true,
            'include_shoe' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $this->settings());

        $this->assertGreaterThan(0, $calc['summary']['total_hours']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['materials_pre_hst']);
        $this->assertEqualsWithDelta(80.0, $calc['scope']['total_lf'], 0.01);
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] + $calc['summary']['materials_pre_hst'], 2),
            $calc['summary']['subtotal_pre_hst'],
            0.01
        );

        $base = collect($calc['materials'])->firstWhere('key', 'baseboard');
        $this->assertNotNull($base);
        $this->assertSame(11.0, (float) $base['qty']);
    }

    public function test_shoe_uses_shared_library_productivity_when_present(): void
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
        $originalRate = (float) $work->rate;
        $originalFlat = $work->flat_rate;
        $work->flat_rate = 2.125;
        $work->save();
        \App\Services\SharedWorkCatalog::forgetCache();

        $settings = $this->settings();
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 40,
            'include_shoe' => true,
            'include_baseboard' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $settings);

        $labor = collect($calc['labor'])->firstWhere('key', 'trim_shoe');
        $this->assertNotNull($labor);
        $this->assertEqualsWithDelta(1.0, (float) $labor['hours'], 0.01);

        $work->rate = $originalRate;
        $work->flat_rate = $originalFlat;
        $work->save();
        \App\Services\SharedWorkCatalog::forgetCache();
    }

    public function test_door_casing_auto_lf_and_rosettes(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['doors_windows'],
            'door_qty' => 2,
            'window_qty' => 0,
            'include_casing' => true,
            'include_rosettes' => true,
            'include_stool_apron' => false,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertEqualsWithDelta(34.0, $calc['scope']['total_lf'], 0.01);
        $rosettes = collect($calc['materials'])->firstWhere('key', 'rosette');
        $this->assertNotNull($rosettes);
        $this->assertSame(4.0, (float) $rosettes['qty']);
    }

    public function test_client_provided_materials_zero_material_total(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 40,
            'include_baseboard' => true,
            'client_provides_all' => true,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
    }

    public function test_wainscot_beadboard_derives_square_footage(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['wall_treatments'],
            'include_wainscoting' => true,
            'wainscot_style' => 'beadboard',
            'wainscot_wall_lf' => 24,
            'wainscot_height_in' => 36,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertEqualsWithDelta(72.0, $calc['scope']['wainscot_sf'], 0.01);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $sheets = collect($calc['materials'])->firstWhere('key', 'beadboard_sheet');
        $this->assertNotNull($sheets);
        $this->assertGreaterThanOrEqual(3, (float) $sheets['qty']);
    }

    public function test_ottawa_services_add_labor_and_materials(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['doors_windows', 'wall_treatments'],
            'door_qty' => 0,
            'window_qty' => 0,
            'include_casing' => false,
            'include_stool_apron' => false,
            'pocket_door_qty' => 1,
            'french_door_qty' => 1,
            'include_wainscoting' => true,
            'wainscot_style' => 'shaker',
            'wainscot_wall_lf' => 20,
            'wainscot_height_in' => 36,
            'include_stain' => true,
            'stain_lf' => 40,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'trim_pocket'));
        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'trim_french'));
        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'trim_stain'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'pocket_door'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'french_door'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'shaker_panel'));
    }

    public function test_publish_hint_when_no_quantities(): void
    {
        $hints = (new TrimmingQuotationCalculator)->validationHints([
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 0,
        ], false, $this->settings());

        $this->assertNotEmpty($hints);
    }

    public function test_eta_has_three_crew_scenarios(): void
    {
        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['ceiling'],
            'include_crown' => true,
            'crown_lf' => 40,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $this->settings());

        $this->assertCount(3, $calc['eta']['scenarios']);
        $this->assertSame(1, $calc['eta']['scenarios'][0]['crew']);
        $this->assertGreaterThan(
            $calc['eta']['scenarios'][2]['calendar_days'],
            $calc['eta']['scenarios'][0]['calendar_days']
        );
    }

    public function test_include_helper_toggles_helper_labor_cost(): void
    {
        $base = [
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 60,
            'include_baseboard' => true,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ];

        $withoutHelper = (new TrimmingQuotationCalculator)->calculate(array_merge($base, [
            'include_helper' => false,
        ]), $this->settings());

        $withHelper = (new TrimmingQuotationCalculator)->calculate(array_merge($base, [
            'include_helper' => true,
        ]), $this->settings());

        $hours = (float) $withoutHelper['summary']['total_hours'];
        $this->assertGreaterThan(0, $hours);
        $this->assertEqualsWithDelta($hours, (float) $withHelper['summary']['total_hours'], 0.01);

        $expectedWithout = round($hours * 80, 2);
        $expectedWith = round($hours * (80 + 40), 2);

        $this->assertEqualsWithDelta($expectedWithout, (float) $withoutHelper['summary']['labor_pre_hst'], 0.01);
        $this->assertEqualsWithDelta($expectedWith, (float) $withHelper['summary']['labor_pre_hst'], 0.01);
        $this->assertFalse($withoutHelper['summary']['include_helper']);
        $this->assertTrue($withHelper['summary']['include_helper']);
    }

    private function settings(): TrimmingSettings
    {
        return new TrimmingSettings([
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'waste_percent' => 10,
            'productivity' => TrimmingSettings::defaultProductivity(),
            'materials' => TrimmingSettings::defaultMaterials(),
        ]);
    }
}

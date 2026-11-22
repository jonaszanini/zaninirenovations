<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use App\Services\SharedWorkCatalog;
use Tests\TestCase;

class BasementFloodRestorationCalculatorTest extends TestCase
{
    public function test_preset_normalization_enabled_sections_label_and_fifth_preset_entry(): void
    {
        $this->assertSame('flood', BasementQuotationCalculator::normalizePreset('flood'));
        $this->assertSame('flood', BasementQuotationCalculator::normalizePreset(' FLOOD '));
        $this->assertSame('full', BasementQuotationCalculator::normalizePreset('unknown'));

        $expectedSections = [
            'overview',
            'insulation',
            'drywall',
            'flooring',
            'doors_trim',
            'painting',
            'finishing',
        ];
        $floodSections = BasementQuotationCalculator::presetEnabledSections('flood');
        $this->assertSame($expectedSections, $floodSections);

        foreach (['demolition', 'framing', 'electrical', 'plumbing', 'ceiling', 'bathroom'] as $excluded) {
            $this->assertNotContains($excluded, $floodSections);
        }

        $this->assertSame('Flood restoration', BasementQuotationCalculator::finishLabel(['scope_preset' => 'flood']));

        $presets = BasementQuotationCalculator::SCOPE_PRESETS;
        $keys = array_keys($presets);
        $this->assertCount(5, $presets);
        $this->assertSame('flood', $keys[4]);
        $this->assertSame('Flood Restoration', $presets['flood']);
    }

    public function test_acceptance_fixture_24_in_cut_calculations_and_productivity_driven_flood_joint(): void
    {
        $inputs = $this->acceptanceFixture();
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());

        $scope = $calc['scope'];
        $labor = collect($calc['labor'])->keyBy('key');
        $materials = collect($calc['materials'])->keyBy('key');

        $this->assertSame(400.0, $scope['drywall_wall_net_sf']);
        $this->assertSame(0.0, $scope['drywall_ceiling_sf']);
        $this->assertSame(240.0, $scope['insulation_wall_sf']);
        $this->assertSame(200.0, $scope['baseboard_lf']);
        $this->assertSame(1600.0, $scope['paint_wall_sf']);
        $this->assertSame(400.0, $scope['paint_primer_sf']);
        $this->assertSame(200.0, $scope['flood_wall_run_lf']);

        $this->assertTrue($labor->has('dw_flood_joint'));
        $floodJoint = $labor->get('dw_flood_joint');
        $this->assertSame('Tape & blend horizontal flood cut joint', $floodJoint['label']);
        $this->assertSame('200 lf horizontal joint', $floodJoint['detail']);
        $this->assertEqualsWithDelta(round(200 / 15, 2), (float) $floodJoint['hours'], 0.01);

        // Changing drywall_flood_joint_lf_per_hr in BasementSettings changes hours and drywall subtotal
        $fasterSettings = $this->settings(['drywall_flood_joint_lf_per_hr' => 25]);
        $fasterCalc = (new BasementQuotationCalculator)->calculate($inputs, $fasterSettings);
        $fasterJoint = collect($fasterCalc['labor'])->firstWhere('key', 'dw_flood_joint');
        $this->assertNotNull($fasterJoint);
        $this->assertEqualsWithDelta(round(200 / 25, 2), (float) $fasterJoint['hours'], 0.01);
        $this->assertLessThan((float) $floodJoint['hours'], (float) $fasterJoint['hours']);
        $this->assertLessThan(
            (float) $calc['summary']['section_subtotals_pre_hst']['drywall'],
            (float) $fasterCalc['summary']['section_subtotals_pre_hst']['drywall']
        );

        // Insulation: exterior wall batts + vapour barrier patch (NOT full vapor_barrier roll)
        $this->assertTrue($labor->has('ins_walls'));
        $this->assertTrue($labor->has('ins_vapor_patch'));
        $this->assertTrue($materials->has('insulation_batt'));
        $this->assertTrue($materials->has('vapor_barrier_patch'));
        $this->assertFalse($materials->has('vapor_barrier'));
        $this->assertEqualsWithDelta(132.0, (float) $materials->get('vapor_barrier_patch')['qty'], 0.01);

        // Shoe moulding uses 200 lf baseboard run
        $this->assertTrue($labor->has('trim_shoe'));
        $this->assertSame('200 lf', $labor->get('trim_shoe')['detail']);
        $this->assertTrue($materials->has('shoe'));
        $this->assertEqualsWithDelta(220.0, (float) $materials->get('shoe')['qty'], 0.01);

        // Flooring uses 800 sq ft LVP with 7% default waste = 856 sf
        $this->assertTrue($materials->has('flooring_finish'));
        $this->assertEqualsWithDelta(856.0, (float) $materials->get('flooring_finish')['qty'], 0.01);
        $this->assertEqualsWithDelta(7.0, (float) $calc['summary']['flooring_waste_percent'], 0.01);

        // Building permit stays off
        $this->assertSame(0.0, (float) $calc['summary']['permit_pre_hst']);
        $this->assertFalse($materials->has('building_permit'));
    }

    public function test_openings_deduct_from_editable_gross_and_48_in_doubles_before_that_deduction(): void
    {
        $settings = $this->settings();
        $calculator = new BasementQuotationCalculator;

        $cut24 = $calculator->calculate($this->acceptanceFixture([
            'drywall_door_openings' => 2,
            'drywall_window_openings' => 1,
        ]), $settings);
        // Hand check: wall run 120 + 40×2 = 200 lf. 24 in = 2 ft. Gross 400 sf.
        // Each door in the cut is 3 ft × 2 ft = 6 sf. Windows start at a 4 ft sill, so a 2 ft cut deducts nothing.
        // Net 400 − 12 = 388. Insulation stays exterior only: 120 × 2 = 240. Paint stays full height: 200 × 8 = 1600.
        $this->assertSame(388.0, $cut24['scope']['drywall_wall_net_sf']);
        $this->assertSame(240.0, $cut24['scope']['insulation_wall_sf']);
        $this->assertSame(1600.0, $cut24['scope']['paint_wall_sf']);

        $cut48 = $calculator->calculate($this->acceptanceFixture([
            'flood_cut_height_in' => 48,
            'drywall_door_openings' => 2,
            'drywall_window_openings' => 1,
        ]), $settings);
        // 48 in = 4 ft doubles gross to 800. Door deduction is 3 × 4 = 12 sf each (24). The cut meets the sill, so windows stay 0.
        $this->assertSame(776.0, $cut48['scope']['drywall_wall_net_sf']);
        $this->assertSame(480.0, $cut48['scope']['insulation_wall_sf']);
        $this->assertSame(1600.0, $cut48['scope']['paint_wall_sf']);

        $edited = $calculator->calculate($this->acceptanceFixture([
            'drywall_wall_sf' => 350,
            'drywall_door_openings' => 1,
        ]), $settings);
        $this->assertSame(344.0, $edited['scope']['drywall_wall_net_sf']);
    }

    public function test_flood_only_inputs_do_not_change_non_flood_summary(): void
    {
        $calculator = new BasementQuotationCalculator;
        $settings = $this->settings();
        $bases = [
            'full' => [
                'scope_preset' => 'full',
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
                'area_sqft' => 800,
                'ceiling_height_ft' => 8,
                'partition_lf' => 40,
                'insulate_walls' => true,
                'ceiling_type' => 'drywall',
                'flooring_type' => 'lvp',
                'owner_hourly_rate' => 85,
            ],
            'bathroom' => [
                'scope_preset' => 'bathroom',
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
                'area_sqft' => 80,
                'include_bathroom' => true,
                'include_toilet' => true,
                'owner_hourly_rate' => 85,
            ],
            'wall' => [
                'scope_preset' => 'wall',
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
                'partition_lf' => 20,
                'door_qty' => 1,
                'owner_hourly_rate' => 85,
            ],
            'custom' => [
                'scope_preset' => 'custom',
                'enabled_sections' => ['overview', 'flooring'],
                'area_sqft' => 500,
                'flooring_type' => 'lvp',
                'owner_hourly_rate' => 85,
            ],
        ];

        foreach ($bases as $preset => $inputs) {
            $plain = $calculator->calculate($inputs, $settings);
            $withFloodKeys = $calculator->calculate($inputs + [
                'flood_cut_height_in' => 48,
                'flood_partition_both_faces' => false,
                'flood_casing_mode' => 'full',
            ], $settings);

            $this->assertSame(
                json_encode($plain['summary']),
                json_encode($withFloodKeys['summary']),
                $preset.' summary changed when flood-only keys were present'
            );
            $this->assertSame(
                json_encode($plain['labor']),
                json_encode($withFloodKeys['labor']),
                $preset.' labor changed when flood-only keys were present'
            );
            $this->assertSame(
                json_encode($plain['materials']),
                json_encode($withFloodKeys['materials']),
                $preset.' materials changed when flood-only keys were present'
            );
        }

        $legsLabel = (string) (collect($calculator->calculate($this->acceptanceFixture(), $settings)['materials'])
            ->firstWhere('key', 'casing')['label'] ?? '');
        $this->assertStringNotContainsString('—', $legsLabel);
        $this->assertStringNotContainsString('–', $legsLabel);
    }

    public function test_48_in_cut_scales_drywall_insulation_and_primer_while_baseboard_and_wall_paint_stay_unchanged(): void
    {
        $inputs = $this->acceptanceFixture(['flood_cut_height_in' => 48]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());

        $scope = $calc['scope'];
        $this->assertSame(800.0, $scope['drywall_wall_net_sf']);
        $this->assertSame(480.0, $scope['insulation_wall_sf']);
        $this->assertSame(800.0, $scope['paint_primer_sf']);
        $this->assertSame(200.0, $scope['baseboard_lf']);
        $this->assertSame(1600.0, $scope['paint_wall_sf']);
    }

    public function test_single_face_partitions_compute_half_partition_run_for_drywall_and_paint(): void
    {
        $inputs = $this->acceptanceFixture(['flood_partition_both_faces' => false]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());

        $scope = $calc['scope'];
        $this->assertSame(160.0, $scope['flood_wall_run_lf']);
        $this->assertSame(320.0, $scope['drywall_wall_net_sf']);
        $this->assertSame(1280.0, $scope['paint_wall_sf']);
        $this->assertSame(240.0, $scope['insulation_wall_sf']);
    }

    public function test_casing_mode_legs_vs_full_is_config_driven_and_cheaper_on_legs(): void
    {
        $legsInputs = $this->acceptanceFixture(['flood_casing_mode' => 'legs', 'casing_openings' => 3]);
        $fullInputs = $this->acceptanceFixture(['flood_casing_mode' => 'full', 'casing_openings' => 3]);

        $settings = $this->settings();
        $legsCalc = (new BasementQuotationCalculator)->calculate($legsInputs, $settings);
        $fullCalc = (new BasementQuotationCalculator)->calculate($fullInputs, $settings);

        $legsLabor = collect($legsCalc['labor'])->firstWhere('key', 'trim_casing');
        $fullLabor = collect($fullCalc['labor'])->firstWhere('key', 'trim_casing');
        $legsMat = collect($legsCalc['materials'])->firstWhere('key', 'casing');
        $fullMat = collect($fullCalc['materials'])->firstWhere('key', 'casing');

        $this->assertNotNull($legsLabor);
        $this->assertNotNull($fullLabor);
        $this->assertNotNull($legsMat);
        $this->assertNotNull($fullMat);

        $this->assertSame('Door casing legs (reuse head)', $legsLabor['label']);
        $this->assertSame('Door casing', $fullLabor['label']);
        $this->assertEqualsWithDelta(2.1, (float) $legsLabor['hours'], 0.01);
        $this->assertEqualsWithDelta(3.0, (float) $fullLabor['hours'], 0.01);
        $this->assertStringContainsString('legs', strtolower($legsMat['label']));
        $this->assertEqualsWithDelta(22.0, (float) $legsMat['unit_price'], 0.01);
        $this->assertEqualsWithDelta(66.0, (float) $legsMat['amount'], 0.01);
        $this->assertEqualsWithDelta(32.0, (float) $fullMat['unit_price'], 0.01);
        $this->assertEqualsWithDelta(96.0, (float) $fullMat['amount'], 0.01);

        $this->assertLessThan((float) $fullLabor['hours'], (float) $legsLabor['hours']);
        $this->assertLessThan((float) $fullCalc['summary']['grand_total'], (float) $legsCalc['summary']['grand_total']);

        // Changing casing_legs_opening_hours and casing_legs material unit price changes total
        $customMats = BasementSettings::defaultMaterials();
        $customMats['casing_legs']['unit_price'] = 29.00;
        $customSettings = $this->settings(['casing_legs_opening_hours' => 0.9], $customMats);
        $customCalc = (new BasementQuotationCalculator)->calculate($legsInputs, $customSettings);

        $customLabor = collect($customCalc['labor'])->firstWhere('key', 'trim_casing');
        $customMat = collect($customCalc['materials'])->firstWhere('key', 'casing');
        $this->assertEqualsWithDelta(2.7, (float) $customLabor['hours'], 0.01);
        $this->assertEqualsWithDelta(87.0, (float) $customMat['amount'], 0.01);
        $this->assertGreaterThan((float) $legsCalc['summary']['grand_total'], (float) $customCalc['summary']['grand_total']);
    }

    public function test_enabling_ceiling_on_flood_adds_800_sf_ceiling_drywall_and_ceiling_paint(): void
    {
        $enabledSections = array_values(array_unique(array_merge(
            BasementQuotationCalculator::presetEnabledSections('flood'),
            ['ceiling']
        )));
        $inputs = $this->acceptanceFixture([
            'enabled_sections' => $enabledSections,
            'ceiling_type' => 'drywall',
        ]);

        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());

        $this->assertSame(800.0, $calc['scope']['drywall_ceiling_sf']);
        $this->assertSame(1200.0, $calc['scope']['paint_primer_sf']);

        $paintCeiling = collect($calc['labor'])->firstWhere('key', 'paint_ceiling');
        $this->assertNotNull($paintCeiling);
        $this->assertSame('800 sq ft', $paintCeiling['detail']);
    }

    public function test_bathroom_enabled_on_flood_adds_toilet_wax_ring_and_extra_materials_only_on_flood(): void
    {
        $enabledSections = array_values(array_unique(array_merge(
            BasementQuotationCalculator::presetEnabledSections('flood'),
            ['bathroom']
        )));
        $inputs = $this->acceptanceFixture([
            'enabled_sections' => $enabledSections,
            'include_bathroom' => true,
            'include_toilet' => true,
            'bath_tile_areas' => [
                ['label' => 'Shower', 'sqft' => 64, 'finish' => 'tile'],
            ],
            'extra_materials' => [
                ['name' => 'Custom tiled shower base material', 'quantity' => 1, 'unit_price' => 950],
            ],
        ]);

        $settings = $this->settings();
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $settings);

        $wax = collect($calc['materials'])->firstWhere('key', 'toilet_wax_ring');
        $this->assertNotNull($wax);
        $this->assertEqualsWithDelta(1.0, (float) $wax['qty'], 0.01);
        $this->assertEqualsWithDelta(
            (float) BasementSettings::defaultMaterials()['toilet_wax_ring']['unit_price'],
            (float) $wax['unit_price'],
            0.01
        );

        // Changing toilet_wax_ring price in BasementSettings updates the line and total
        $customMats = BasementSettings::defaultMaterials();
        $customMats['toilet_wax_ring']['unit_price'] = 24.50;
        $customCalc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings([], $customMats));
        $customWax = collect($customCalc['materials'])->firstWhere('key', 'toilet_wax_ring');
        $this->assertEqualsWithDelta(24.50, (float) $customWax['amount'], 0.01);
        $this->assertGreaterThan((float) $calc['summary']['grand_total'], (float) $customCalc['summary']['grand_total']);

        // Custom shower/tub extra material row
        $this->assertCount(1, $calc['extra_materials']);
        $this->assertSame('Custom tiled shower base material', $calc['extra_materials'][0]['label']);
        $this->assertEqualsWithDelta(950.0, (float) $calc['extra_materials'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(950.0, (float) $calc['summary']['extra_materials_pre_hst'], 0.01);

        // Non-flood quotes with include_toilet => true must NOT add toilet_wax_ring
        foreach (['bathroom', 'full'] as $preset) {
            $nonFlood = (new BasementQuotationCalculator)->calculate([
                'scope_preset' => $preset,
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections($preset),
                'area_sqft' => 120,
                'include_bathroom' => true,
                'include_toilet' => true,
                'owner_hourly_rate' => 85,
            ], $settings);
            $this->assertNull(collect($nonFlood['materials'])->firstWhere('key', 'toilet_wax_ring'));
        }
    }

    public function test_zero_area_with_positive_perimeter_calculates_nonzero_and_passes_validation(): void
    {
        $inputs = [
            'scope_preset' => 'flood',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('flood'),
            'area_sqft' => 0,
            'perimeter_lf' => 120,
            'partition_lf' => 40,
            'flood_cut_height_in' => 24,
            'flood_partition_both_faces' => true,
            'flood_casing_mode' => 'legs',
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'flooring_waste_percent' => 7.0,
            'insulate_walls' => true,
            'owner_hourly_rate' => 85,
        ];

        $calculator = new BasementQuotationCalculator;
        $this->assertSame([], $calculator->validationHints($inputs, false));

        $calc = $calculator->calculate($inputs, $this->settings());
        $this->assertGreaterThan(0, (float) $calc['summary']['labor_pre_hst']);
        $this->assertGreaterThan(0, (float) $calc['summary']['materials_pre_hst']);
        $this->assertGreaterThan(0, (float) $calc['summary']['grand_total']);
    }

    public function test_non_flood_regression_totals_unchanged_and_no_flood_line_leakage(): void
    {
        $calculator = new BasementQuotationCalculator;
        $settings = $this->settings();
        $hasSharedOverlay = SharedWorkCatalog::work('flooring_install') !== null;

        $fixtures = [
            'full' => [
                'inputs' => [
                    'scope_preset' => 'full',
                    'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
                    'area_sqft' => 800,
                    'ceiling_height_ft' => 8,
                    'room_count' => 3,
                    'partition_lf' => 40,
                    'insulate_walls' => true,
                    'ceiling_type' => 'drywall',
                    'flooring_type' => 'lvp',
                    'flooring_waste_percent' => 7.0,
                    'door_qty' => 2,
                    'casing_openings' => 2,
                    'include_shoe' => true,
                    'include_bathroom' => false,
                    'include_demolition' => false,
                    'owner_hourly_rate' => 85,
                    'helper_hourly_rate' => 45,
                    'trim_material_lf_v2' => true,
                    'corner_bead_by_height' => true,
                    'drywall_boxing_v2' => true,
                ],
                'no_db_grand' => 36295.97,
                'db_grand' => 40442.45,
            ],
            'bathroom' => [
                'inputs' => [
                    'scope_preset' => 'bathroom',
                    'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
                    'area_sqft' => 80,
                    'ceiling_height_ft' => 8,
                    'partition_lf' => 16,
                    'insulate_walls' => false,
                    'flooring_type' => 'none',
                    'include_bathroom' => true,
                    'bath_type' => 'full',
                    'include_vanity' => true,
                    'include_toilet' => true,
                    'include_shower' => true,
                    'include_exhaust_fan' => true,
                    'bath_tile_areas' => [['label' => 'Floor', 'sqft' => 40, 'finish' => 'tile']],
                    'owner_hourly_rate' => 85,
                    'trim_material_lf_v2' => true,
                    'corner_bead_by_height' => true,
                    'drywall_boxing_v2' => true,
                ],
                'no_db_grand' => 8521.04,
                'db_grand' => 9679.40,
            ],
            'wall' => [
                'inputs' => [
                    'scope_preset' => 'wall',
                    'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
                    'area_sqft' => 0,
                    'ceiling_height_ft' => 8,
                    'partition_lf' => 20,
                    'framing_door_openings' => 1,
                    'door_qty' => 1,
                    'casing_openings' => 1,
                    'flooring_type' => 'none',
                    'ceiling_type' => 'none',
                    'include_cleaning' => false,
                    'include_touchups' => false,
                    'owner_hourly_rate' => 85,
                    'trim_material_lf_v2' => true,
                    'corner_bead_by_height' => true,
                    'drywall_boxing_v2' => true,
                ],
                'no_db_grand' => 5493.24,
                'db_grand' => 6663.13,
            ],
            'custom' => [
                'inputs' => [
                    'scope_preset' => 'custom',
                    'enabled_sections' => ['overview', 'flooring'],
                    'area_sqft' => 500,
                    'flooring_type' => 'lvp',
                    'flooring_waste_percent' => 7.0,
                    'underlayment' => true,
                    'owner_hourly_rate' => 85,
                    'contractor_fee_percent' => 10,
                    'trim_material_lf_v2' => true,
                    'corner_bead_by_height' => true,
                    'drywall_boxing_v2' => true,
                ],
                'no_db_grand' => 5184.44,
                'db_grand' => 5664.69,
            ],
        ];

        $floodLaborKeys = ['dw_flood_joint', 'ins_vapor_patch'];
        $floodMaterialKeys = ['toilet_wax_ring', 'vapor_barrier_patch', 'casing_legs'];

        foreach ($fixtures as $preset => $spec) {
            $calc = $calculator->calculate($spec['inputs'], $settings);
            $summary = $calc['summary'];

            $this->assertTrue(is_finite((float) $summary['grand_total']), $preset.' grand_total must be finite');
            $this->assertGreaterThan(0, (float) $summary['grand_total'], $preset.' grand_total must be positive');
            $this->assertEqualsWithDelta(
                (float) $summary['grand_total'],
                (float) ($summary['reconciliation']['checks']['grand_from_subtotal_hst_trades'] ?? -1),
                0.02,
                $preset.' reconciliation mismatch'
            );

            $expectedGrand = $hasSharedOverlay ? $spec['db_grand'] : $spec['no_db_grand'];
            $this->assertEqualsWithDelta($expectedGrand, (float) $summary['grand_total'], 0.02, $preset.' regression grand total');

            $laborKeys = collect($calc['labor'])->pluck('key')->all();
            $matKeys = collect($calc['materials'])->pluck('key')->all();
            foreach ($floodLaborKeys as $fk) {
                $this->assertNotContains($fk, $laborKeys, $preset.' must not leak '.$fk);
            }
            foreach ($floodMaterialKeys as $fk) {
                $this->assertNotContains($fk, $matKeys, $preset.' must not leak '.$fk);
            }
            $this->assertNull($calc['scope']['flood_cut_height_in'] ?? null);
            $this->assertNull($calc['scope']['flood_wall_run_lf'] ?? null);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function acceptanceFixture(array $overrides = []): array
    {
        return array_merge([
            'scope_preset' => 'flood',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('flood'),
            'perimeter_lf' => 120,
            'partition_lf' => 40,
            'flood_cut_height_in' => 24,
            'flood_partition_both_faces' => true,
            'flood_casing_mode' => 'legs',
            'ceiling_height_ft' => 8,
            'area_sqft' => 800,
            'flooring_type' => 'lvp',
            'flooring_waste_percent' => BasementQuotationCalculator::flooringWasteDefault('lvp'),
            'casing_openings' => 3,
            'include_shoe' => true,
            'insulate_walls' => true,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'trim_material_lf_v2' => true,
            'corner_bead_by_height' => true,
            'drywall_boxing_v2' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, float|int>  $prodOverrides
     * @param  array<string, array<string, mixed>>|null  $materials
     */
    private function settings(array $prodOverrides = [], ?array $materials = null): BasementSettings
    {
        return new BasementSettings([
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => array_merge(BasementSettings::defaultProductivity(), $prodOverrides),
            'materials' => $materials ?? BasementSettings::defaultMaterials(),
        ]);
    }
}

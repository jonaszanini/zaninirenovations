<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementQuotationCalculatorTest extends TestCase
{
    public function test_empty_area_has_zero_totals(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate(
            ['area_sqft' => 0],
            $this->settings()
        );

        $this->assertSame(0.0, $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['grand_total']);
    }

    public function test_full_finish_produces_labor_materials_and_eta(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 800,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'include_demolition' => true,
            'demo_flooring_sf' => 800,
            'demo_bin_qty' => 1,
            'partition_lf' => 40,
            'outlet_qty' => 12,
            'switch_qty' => 6,
            'light_qty' => 10,
            'insulate_walls' => true,
            'drywall_wall_sf' => 640,
            'ceiling_type' => 'drywall',
            'flooring_type' => 'lvp',
            'flooring_sf' => 800,
            'door_qty' => 2,
            'paint_coats' => 2,
            'include_bathroom' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $this->settings());

        $this->assertGreaterThan(0, $calc['summary']['total_hours']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['materials_pre_hst']);
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] + $calc['summary']['materials_pre_hst'], 2),
            $calc['summary']['subtotal_pre_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] * 0.13, 2),
            $calc['summary']['labor_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] + $calc['summary']['labor_hst'], 2),
            $calc['summary']['labor_after_hst'],
            0.01
        );
        $this->assertCount(3, $calc['eta']['scenarios']);
        $this->assertSame(1, $calc['eta']['scenarios'][0]['crew']);
        $this->assertGreaterThan(
            $calc['eta']['scenarios'][2]['calendar_days'],
            $calc['eta']['scenarios'][0]['calendar_days']
        );
        $this->assertSame(800.0, $calc['scope']['area_sqft']);
        $this->assertGreaterThan(0, $calc['summary']['trades']['electrical']['labor_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['trades']['electrical']['materials_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['trades']['plumbing']['labor_pre_hst']);
        $this->assertLessThanOrEqual(
            $calc['summary']['labor_pre_hst'],
            $calc['summary']['trades']['electrical']['labor_pre_hst']
        );
        $this->assertEquals(
            $calc['summary']['grand_total'],
            round(
                $calc['summary']['labor_after_hst'] + $calc['summary']['materials_after_hst'],
                2
            )
        );
    }

    public function test_summary_splits_electrical_and_plumbing_without_changing_grand_total(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_plumbing' => true,
            'bath_rough_in' => true,
            'outlet_qty' => 4,
            'switch_qty' => 2,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $elec = $calc['summary']['trades']['electrical'];
        $plumb = $calc['summary']['trades']['plumbing'];
        $grand = $calc['summary']['grand_total'];

        $this->assertGreaterThan(0, $elec['labor_pre_hst']);
        $this->assertGreaterThan(0, $elec['materials_pre_hst']);
        $this->assertGreaterThan(0, $plumb['labor_pre_hst']);
        $this->assertGreaterThan(0, $plumb['materials_pre_hst']);
        $this->assertLessThanOrEqual(
            $calc['summary']['labor_pre_hst'],
            round($elec['labor_pre_hst'] + $plumb['labor_pre_hst'], 2)
        );
        $this->assertLessThanOrEqual(
            $calc['summary']['materials_pre_hst'],
            round($elec['materials_pre_hst'] + $plumb['materials_pre_hst'], 2)
        );
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_after_hst'] + $calc['summary']['materials_after_hst'], 2),
            $grand,
            0.01
        );
    }

    public function test_client_provided_materials_zero_material_total(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'include_demolition' => false,
            'client_provides_all' => true,
            'flooring_sf' => 400,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
    }

    public function test_client_provides_individual_bathroom_items(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_vanity' => true,
            'include_toilet' => true,
            'include_shower' => true,
            'include_tub' => true,
            'detector_qty' => 0,
            'client_provides_bath_items' => ['vanity', 'faucet', 'showerhead'],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $vanity = collect($calc['materials'])->firstWhere('key', 'vanity');
        $faucet = collect($calc['materials'])->firstWhere('key', 'faucet');
        $head = collect($calc['materials'])->firstWhere('key', 'showerhead');
        $toilet = collect($calc['materials'])->firstWhere('key', 'toilet');
        $frame = collect($calc['materials'])->firstWhere('key', 'shower_kit');
        $tub = collect($calc['materials'])->firstWhere('key', 'tub_kit');

        $this->assertNotNull($vanity);
        $this->assertTrue($vanity['client_provided']);
        $this->assertSame(0.0, $vanity['amount']);
        $this->assertTrue($faucet['client_provided']);
        $this->assertSame(0.0, $faucet['amount']);
        $this->assertTrue($head['client_provided']);
        $this->assertSame(0.0, $head['amount']);
        $this->assertFalse($toilet['client_provided']);
        $this->assertGreaterThan(0, $toilet['amount']);
        $this->assertFalse($frame['client_provided']);
        $this->assertGreaterThan(0, $frame['amount']);
        $this->assertFalse($tub['client_provided']);
        $this->assertGreaterThan(0, $tub['amount']);
        $this->assertContains('bath_vanity', collect($calc['labor'])->pluck('key')->all());
    }

    public function test_pedestal_sink_replaces_vanity_and_can_be_client_provided(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_vanity' => true,
            'include_pedestal_sink' => true,
            'detector_qty' => 0,
            'client_provides_bath_items' => ['pedestal_sink', 'faucet'],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $labor = collect($calc['labor'])->pluck('key')->all();
        $this->assertContains('bath_pedestal', $labor);
        $this->assertNotContains('bath_vanity', $labor);
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'vanity'));

        $sink = collect($calc['materials'])->firstWhere('key', 'pedestal_sink');
        $this->assertNotNull($sink);
        $this->assertSame('Pedestal bathroom sink (each)', $sink['label']);
        $this->assertTrue($sink['client_provided']);
        $this->assertSame(0.0, $sink['amount']);
        $this->assertEqualsWithDelta(2.0, collect($calc['labor'])->firstWhere('key', 'bath_pedestal')['hours'], 0.01);

        $faucet = collect($calc['materials'])->firstWhere('key', 'faucet');
        $this->assertNotNull($faucet);
        $this->assertTrue($faucet['client_provided']);
    }

    public function test_pedestal_sink_uses_lower_material_price_than_vanity(): void
    {
        $vanityQuote = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_vanity' => true,
            'include_toilet' => false,
            'include_shower' => false,
            'include_exhaust_fan' => false,
            'detector_qty' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $pedestalQuote = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_vanity' => false,
            'include_pedestal_sink' => true,
            'include_toilet' => false,
            'include_shower' => false,
            'include_exhaust_fan' => false,
            'detector_qty' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $vanityMat = collect($vanityQuote['materials'])->firstWhere('key', 'vanity');
        $pedestalMat = collect($pedestalQuote['materials'])->firstWhere('key', 'pedestal_sink');
        $this->assertNotNull($vanityMat);
        $this->assertNotNull($pedestalMat);
        $this->assertEqualsWithDelta(360.0, $vanityMat['amount'], 0.01);
        $this->assertEqualsWithDelta(180.0, $pedestalMat['amount'], 0.01);
        $this->assertEqualsWithDelta(4.0, collect($vanityQuote['labor'])->firstWhere('key', 'bath_vanity')['hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, collect($pedestalQuote['labor'])->firstWhere('key', 'bath_pedestal')['hours'], 0.01);
        $this->assertLessThan(
            $vanityQuote['summary']['labor_pre_hst'],
            $pedestalQuote['summary']['labor_pre_hst']
        );
    }

    public function test_legacy_bathroom_section_still_zeros_all_bath_materials(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_vanity' => true,
            'include_toilet' => true,
            'detector_qty' => 0,
            'client_provides_sections' => ['bathroom'],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        foreach (['vanity', 'faucet', 'toilet'] as $key) {
            $row = collect($calc['materials'])->firstWhere('key', $key);
            $this->assertNotNull($row, $key);
            $this->assertTrue($row['client_provided']);
            $this->assertSame(0.0, $row['amount']);
        }
    }

    public function test_overrides_replace_calculated_hours_and_qty(): void
    {
        $base = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'partition_lf' => 20,
            'owner_hourly_rate' => 100,
        ], $this->settings());

        $overridden = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'partition_lf' => 20,
            'owner_hourly_rate' => 100,
            'labor_override' => [
                'framing_walls' => ['hours' => 10],
            ],
        ], $this->settings());

        $framing = collect($overridden['labor'])->firstWhere('key', 'framing_walls');
        $this->assertNotNull($framing);
        $this->assertSame(10.0, $framing['hours']);
        $this->assertSame(1000.0, $framing['amount']);
        $this->assertNotEquals($base['summary']['total_hours'], $overridden['summary']['total_hours']);
    }

    public function test_validation_requires_area_when_publishing(): void
    {
        $hints = (new BasementQuotationCalculator)->validationHints(['area_sqft' => 0], false);
        $this->assertNotEmpty($hints);

        $draft = (new BasementQuotationCalculator)->validationHints(['area_sqft' => 0], true);
        $this->assertSame([], $draft);
    }

    public function test_bathroom_skip_omits_fixtures(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'include_bathroom' => false,
            'include_vanity' => true,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['labor'])->pluck('key')->all();
        $this->assertNotContains('bath_vanity', $keys);
        $this->assertNotContains('bath_pedestal', $keys);
        $this->assertNotContains('bath_toilet', $keys);
    }

    public function test_extra_materials_add_to_total(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'extra_materials' => [
                ['name' => 'Custom fixture', 'quantity' => 2, 'unit_price' => 25],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(50.0, $calc['summary']['materials_pre_hst']);
        $this->assertCount(1, $calc['extra_materials']);
    }

    public function test_plate_lumber_price_is_stud_divided_by_eight(): void
    {
        $materials = BasementSettings::defaultMaterials();
        $materials['stud_2x4']['unit_price'] = 8.00;
        $materials['plate_2x4']['unit_price'] = 99.00;
        $materials['plate_2x4']['catalog_material_id'] = 999;

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'partition_lf' => 40,
            'owner_hourly_rate' => 80,
            'materials_override' => [
                'plate_2x4' => ['unit_price' => 50],
            ],
        ], $this->settings($materials));

        $plate = collect($calc['materials'])->firstWhere('key', 'plate_2x4');
        $this->assertNotNull($plate);
        $this->assertSame(120.0, $plate['qty']);
        $this->assertSame('lf', $plate['unit']);
        $this->assertEqualsWithDelta(1.0, $plate['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(120.0, $plate['amount'], 0.01);
        $this->assertNull($plate['catalog_material_id']);
        $this->assertSame(0.0, $plate['delivery_cad']);
    }

    public function test_plate_lumber_follows_stud_unit_price_override(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'partition_lf' => 40,
            'owner_hourly_rate' => 80,
            'materials_override' => [
                'stud_2x4' => ['unit_price' => 16],
                'plate_2x4' => ['unit_price' => 99],
            ],
        ], $this->settings());

        $plate = collect($calc['materials'])->firstWhere('key', 'plate_2x4');
        $this->assertNotNull($plate);
        $this->assertEqualsWithDelta(2.0, $plate['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(240.0, $plate['amount'], 0.01);
    }

    public function test_package_sold_materials_count_bags_boxes_and_sticks(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => true,
            'insulation_wall_sf' => 400,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'outlet_qty' => 0,
            'switch_qty' => 0,
            'light_qty' => 0,
            'wiring_runs' => 10,
            'drywall_wall_sf' => 400,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 40,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $defaults = BasementSettings::defaultMaterials();
        $this->assertStringContainsString('17 L', $defaults['joint_compound']['name']);
        $this->assertStringContainsString('2x4x8', $defaults['stud_2x4']['name']);

        $batt = collect($calc['materials'])->firstWhere('key', 'insulation_batt');
        $this->assertNotNull($batt);
        $this->assertSame('bag', $batt['unit']);
        $this->assertSame(11.0, $batt['qty']);

        $vapor = collect($calc['materials'])->firstWhere('key', 'vapor_barrier');
        $this->assertNotNull($vapor);
        $this->assertSame('roll', $vapor['unit']);
        $this->assertSame(1.0, $vapor['qty']);

        $cable = collect($calc['materials'])->firstWhere('key', 'nm_cable');
        $this->assertNotNull($cable);
        $this->assertSame('box', $cable['unit']);
        $this->assertSame(2.0, $cable['qty']);

        $base = collect($calc['materials'])->firstWhere('key', 'baseboard');
        $this->assertNotNull($base);
        $this->assertSame('each', $base['unit']);
        $this->assertSame(6.0, $base['qty']);

        $mud = collect($calc['materials'])->firstWhere('key', 'joint_compound');
        $this->assertNotNull($mud);
        $this->assertSame('pail', $mud['unit']);
        $this->assertSame(2.0, $mud['qty']);
        $this->assertStringContainsString('17 L', $mud['label']);
    }

    public function test_paint_is_packed_into_can_sizes(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 350,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'paint_coats' => 1,
            'paint_colors' => 1,
            'baseboard_lf' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['materials'])->pluck('key')->all();
        $this->assertContains('paint_paint_1gal', $keys);
        $this->assertContains('paint_paint_quart', $keys);
        $this->assertContains('primer_primer_1gal', $keys);
        $this->assertContains('primer_primer_quart', $keys);
        $this->assertNotContains('paint_gallon', $keys);
        $this->assertSame(1.0, collect($calc['materials'])->firstWhere('key', 'paint_paint_1gal')['qty']);
        $this->assertSame(1.0, collect($calc['materials'])->firstWhere('key', 'paint_paint_quart')['qty']);
        $this->assertNotContains('paint_two_color', collect($calc['labor'])->pluck('key')->all());

        $pail = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 3500,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'paint_coats' => 1,
            'paint_colors' => 1,
            'baseboard_lf' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(2.0, collect($pail['materials'])->firstWhere('key', 'paint_paint_5gal')['qty']);
        $this->assertSame(1.0, collect($pail['materials'])->firstWhere('key', 'paint_paint_1gal')['qty']);
    }

    public function test_two_colors_packs_walls_and_ceiling_separately_and_adds_tape_labor(): void
    {
        $inputs = [
            'area_sqft' => 400,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 400,
            'drywall_ceiling_sf' => 400,
            'paint_wall_sf' => 400,
            'paint_ceiling_sf' => 400,
            'paint_trim_lf' => 0,
            'paint_coats' => 1,
            'baseboard_lf' => 0,
            'owner_hourly_rate' => 80,
        ];

        $one = (new BasementQuotationCalculator)->calculate($inputs + ['paint_colors' => 1], $this->settings());
        $two = (new BasementQuotationCalculator)->calculate($inputs + ['paint_colors' => 2], $this->settings());

        $twoKeys = collect($two['materials'])->pluck('key')->all();
        $this->assertContains('paint_walls_paint_1gal', $twoKeys);
        $this->assertContains('paint_ceil_paint_1gal', $twoKeys);
        $this->assertNotContains('paint_paint_1gal', $twoKeys);
        $this->assertStringContainsString('walls', (string) collect($two['materials'])->firstWhere('key', 'paint_walls_paint_1gal')['label']);
        $this->assertStringContainsString('ceiling', (string) collect($two['materials'])->firstWhere('key', 'paint_ceil_paint_1gal')['label']);
        $this->assertContains('paint_two_color', collect($two['labor'])->pluck('key')->all());
        $this->assertNotContains('paint_two_color', collect($one['labor'])->pluck('key')->all());

        $finishOne = collect($one['labor'])->firstWhere('key', 'dw_finish');
        $finishTwo = collect($two['labor'])->firstWhere('key', 'dw_finish');
        $this->assertGreaterThan($finishOne['hours'], $finishTwo['hours']);
        $this->assertStringContainsString('two-color', $finishTwo['detail']);

        $paintOne = collect($one['labor'])->firstWhere('key', 'paint_walls');
        $paintTwo = collect($two['labor'])->firstWhere('key', 'paint_walls');
        $this->assertGreaterThan($paintOne['hours'], $paintTwo['hours']);
    }

    public function test_trim_paint_uses_half_gallon_and_quart_only(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 100,
            'paint_coats' => 2,
            'baseboard_lf' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $trim = collect($calc['materials'])->filter(fn ($row) => str_starts_with((string) $row['key'], 'trim_'));
        $this->assertNotEmpty($trim);
        foreach ($trim as $row) {
            $this->assertTrue(
                str_contains($row['key'], 'half') || str_contains($row['key'], 'quart'),
                $row['key']
            );
            $this->assertFalse(str_contains($row['key'], '5gal'));
            $this->assertFalse(str_contains($row['key'], 'paint_1gal'));
        }
        $this->assertSame(1.0, collect($trim)->firstWhere('key', 'trim_trim_paint_half')['qty']);
        $this->assertSame(1.0, collect($trim)->firstWhere('key', 'trim_trim_paint_quart')['qty']);
    }

    public function test_spot_lights_use_pot_material_not_fixtures(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'lighting_type' => 'spot',
            'light_qty' => 8,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['materials'])->pluck('key')->all();
        $this->assertContains('pot_light', $keys);
        $this->assertNotContains('light_fixture', $keys);
        $pots = collect($calc['materials'])->firstWhere('key', 'pot_light');
        $this->assertSame(8.0, $pots['qty']);
        $this->assertSame('electrical', $pots['section']);
        $this->assertNotContains('fin_fixtures', collect($calc['labor'])->pluck('key')->all());
        $this->assertContains('elec_lights', collect($calc['labor'])->pluck('key')->all());
    }

    public function test_light_fixtures_use_fixture_material_in_electrical(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'lighting_type' => 'fixture',
            'light_qty' => 4,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['materials'])->pluck('key')->all();
        $this->assertContains('light_fixture', $keys);
        $this->assertNotContains('pot_light', $keys);
        $fixtures = collect($calc['materials'])->firstWhere('key', 'light_fixture');
        $this->assertSame(4.0, $fixtures['qty']);
        $this->assertSame('electrical', $fixtures['section']);
        $labor = collect($calc['labor'])->pluck('key')->all();
        $this->assertContains('elec_fixture_hang', $labor);
        $this->assertNotContains('fin_fixtures', $labor);
    }

    public function test_both_lighting_types_add_spots_and_fixtures(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'lighting_type' => 'both',
            'light_qty' => 10,
            'fixture_qty' => 2,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $pots = collect($calc['materials'])->firstWhere('key', 'pot_light');
        $fixtures = collect($calc['materials'])->firstWhere('key', 'light_fixture');
        $this->assertSame(10.0, $pots['qty']);
        $this->assertSame(2.0, $fixtures['qty']);
        $this->assertSame('electrical', $pots['section']);
        $this->assertSame('electrical', $fixtures['section']);
    }

    public function test_extra_material_delivery_is_added_once(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'extra_materials' => [
                [
                    'name' => 'Debris bin',
                    'quantity' => 2,
                    'unit_price' => 400,
                    'delivery_cad' => 80,
                ],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertEqualsWithDelta(880.0, $calc['summary']['materials_pre_hst'], 0.01);
        $this->assertCount(2, $calc['extra_materials']);
        $delivery = collect($calc['extra_materials'])->firstWhere('is_delivery', true);
        $this->assertNotNull($delivery);
        $this->assertSame(1.0, $delivery['qty']);
        $this->assertEqualsWithDelta(80.0, $delivery['amount'], 0.01);
    }

    public function test_missing_scope_preset_still_full_finish(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'include_demolition' => false,
            'flooring_type' => 'lvp',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame('Full finish', $calc['scope']['finish_label']);
        $this->assertNotEmpty(collect($calc['materials'])->firstWhere('key', 'flooring_finish'));
        $this->assertNotEmpty(collect($calc['labor'])->firstWhere('key', 'ins_walls'));
    }

    public function test_bathroom_preset_skips_whole_basement_trades(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'include_plumbing' => true,
            'bath_rough_in' => true,
            'insulate_walls' => true,
            'flooring_type' => 'lvp',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['labor'])->pluck('key')->all();
        $matKeys = collect($calc['materials'])->pluck('key')->all();
        $this->assertSame('Bathroom only', $calc['scope']['finish_label']);
        $this->assertContains('bath_vanity', $keys);
        $this->assertContains('plumb_bath', $keys);
        $this->assertNotContains('framing_walls', $keys);
        $this->assertNotContains('ins_walls', $keys);
        $this->assertNotContains('fl_install', $keys);
        $this->assertNotContains('trim_base', $keys);
        $this->assertEmpty(collect($calc['materials'])->firstWhere('key', 'flooring_finish'));
        $this->assertEmpty(collect($calc['materials'])->firstWhere('key', 'insulation_batt'));
        $this->assertEmpty(collect($calc['materials'])->firstWhere('key', 'baseboard'));
    }

    public function test_bathroom_preset_still_enables_electrical_and_plumbing_for_pricing(): void
    {
        $keys = BasementQuotationCalculator::presetEnabledSections('bathroom');
        $this->assertContains('electrical', $keys);
        $this->assertContains('plumbing', $keys);
        $this->assertContains('framing', $keys);
        $this->assertContains('bathroom', $keys);
        $this->assertNotContains('flooring', $keys);
    }

    public function test_bathroom_preset_prices_new_wall_and_duct_work(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'ceiling_height_ft' => 8,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'partition_lf' => 10,
            'bulkhead_lf' => 6,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $labor = collect($calc['labor']);
        $this->assertNotNull($labor->firstWhere('key', 'framing_walls'));
        $this->assertNotNull($labor->firstWhere('key', 'framing_bulkheads'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'stud_2x4'));

        $perimeter = round(4 * sqrt(80), 2);
        $expectedWallSf = round(
            ($perimeter * 8) + (10 * 8 * 2) + (6 * BasementQuotationCalculator::BULKHEAD_DRYWALL_SF_PER_LF),
            2
        );
        $hang = $labor->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString(number_format($expectedWallSf, 0), $hang['detail']);
    }

    public function test_wall_preset_uses_partition_for_drywall_and_paint(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'scope_preset' => 'wall',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
            'partition_lf' => 20,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame('New wall', $calc['scope']['finish_label']);
        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString('320', $hang['detail']);
        $this->assertNotContains('fl_install', collect($calc['labor'])->pluck('key')->all());
        $this->assertNotContains('ins_walls', collect($calc['labor'])->pluck('key')->all());
        $this->assertContains('framing_walls', collect($calc['labor'])->pluck('key')->all());
        $this->assertContains('trim_base', collect($calc['labor'])->pluck('key')->all());
        $base = collect($calc['labor'])->firstWhere('key', 'trim_base');
        $this->assertStringContainsString('40', $base['detail']);
    }

    public function test_wall_preset_drywall_is_both_sides_and_skips_ceiling(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 800,
            'ceiling_height_ft' => 8,
            'scope_preset' => 'wall',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
            'partition_lf' => 13.3,
            'door_qty' => 1,
            'framing_door_openings' => 1,
            'drywall_ceiling_sf' => 800,
            'paint_ceiling_sf' => 800,
            'ceiling_type' => 'drywall',
            'include_touchups' => false,
            'include_cleaning' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $labor = collect($calc['labor']);
        $hang = $labor->firstWhere('key', 'dw_hang');
        $mud = $labor->firstWhere('key', 'dw_finish');
        $paintCeil = $labor->firstWhere('key', 'paint_ceiling');
        $this->assertNotNull($hang);
        $this->assertNotNull($mud);
        $this->assertNull($paintCeil);
        $this->assertStringContainsString('213', $hang['detail']);
        $this->assertStringContainsString('213', $mud['detail']);
        $this->assertStringNotContainsString('800', $hang['detail']);
        $this->assertEqualsWithDelta(13.3 * 8 * 2 / 20, $mud['hours'], 0.02);
        $this->assertEqualsWithDelta(13.3 * 8 * 2 / 35, $hang['hours'], 0.02);
        $this->assertNull($labor->firstWhere('key', 'fin_touch'));
        $this->assertLessThan(40, $calc['summary']['total_hours']);
        $this->assertGreaterThan(15, $calc['summary']['total_hours']);
    }

    public function test_wall_preset_level_4_mud_hours_match_finish_rate_not_a_second_wall(): void
    {
        $settings = $this->settings();
        $prod = $settings->resolvedProductivity();
        $sf = 13.3 * 8 * 2;
        $expectedHours = round($sf / (float) $prod['drywall_finish_l4_sf_per_hr'], 2);
        $expectedAmount = round($expectedHours * 85, 2);

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 0,
            'ceiling_height_ft' => 8,
            'scope_preset' => 'wall',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
            'partition_lf' => 13.3,
            'include_touchups' => false,
            'owner_hourly_rate' => 85,
        ], $settings);

        $mud = collect($calc['labor'])->firstWhere('key', 'dw_finish');
        $this->assertNotNull($mud);
        $this->assertEqualsWithDelta($expectedHours, $mud['hours'], 0.02);
        $this->assertEqualsWithDelta($expectedAmount, $mud['amount'], 0.05);
    }

    public function test_wall_preset_enables_doors_trim(): void
    {
        $keys = BasementQuotationCalculator::presetEnabledSections('wall');
        $this->assertContains('doors_trim', $keys);
        $this->assertContains('framing', $keys);
        $this->assertNotContains('flooring', $keys);
        $this->assertNotContains('bathroom', $keys);
    }

    public function test_bathroom_electrical_defaults_are_three_outlets_two_switches_and_a_bath_fixture(): void
    {
        $defaults = BasementQuotationCalculator::electricalDefaultsForPreset('bathroom');
        $this->assertSame(3, $defaults['outlet_qty']);
        $this->assertSame(2, $defaults['switch_qty']);
        $this->assertSame('spot', $defaults['lighting_type']);
        $this->assertSame(0, $defaults['fixture_qty']);
        $this->assertSame(0, $defaults['light_qty']);
        $this->assertTrue($defaults['include_bath_fixture']);
        $this->assertSame(1, $defaults['bath_fixture_qty']);
        $this->assertFalse($defaults['include_bath_spot']);

        $calc = (new BasementQuotationCalculator)->calculate(array_merge([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_plumbing' => true,
            'detector_qty' => 0,
            'owner_hourly_rate' => 80,
        ], $defaults), $this->settings());

        $this->assertSame(3.0, collect($calc['materials'])->firstWhere('key', 'outlet_box')['qty']);
        $this->assertSame(2.0, collect($calc['materials'])->firstWhere('key', 'switch_device')['qty']);
        $this->assertSame(1.0, collect($calc['materials'])->firstWhere('key', 'bath_light_fixture')['qty']);
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'light_fixture'));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'pot_light'));
        $this->assertContains('bath_lights', collect($calc['labor'])->pluck('key')->all());
    }

    public function test_bathroom_and_basement_lighting_are_counted_separately(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 800,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'include_bathroom' => true,
            'lighting_type' => 'both',
            'light_qty' => 8,
            'fixture_qty' => 2,
            'include_bath_fixture' => true,
            'bath_fixture_qty' => 1,
            'include_bath_spot' => true,
            'bath_spot_qty' => 1,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(8.0, collect($calc['materials'])->firstWhere('key', 'pot_light')['qty']);
        $this->assertSame(2.0, collect($calc['materials'])->firstWhere('key', 'light_fixture')['qty']);
        $this->assertSame(1.0, collect($calc['materials'])->firstWhere('key', 'bath_pot_light')['qty']);
        $this->assertSame(1.0, collect($calc['materials'])->firstWhere('key', 'bath_light_fixture')['qty']);
        $labor = collect($calc['labor'])->pluck('key')->all();
        $this->assertContains('elec_lights', $labor);
        $this->assertContains('bath_lights', $labor);
        $this->assertContains('elec_fixture_hang', $labor);
        $this->assertContains('bath_fixture_hang', $labor);
        $runs = collect($calc['labor'])->firstWhere('key', 'elec_runs');
        $this->assertNotNull($runs);
        $this->assertStringContainsString('12', $runs['detail']);
    }

    public function test_bathroom_tile_areas_are_summed_and_named_on_labor(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'detector_qty' => 0,
            'bath_tile_areas' => [
                ['label' => 'Floor', 'sqft' => 40],
                ['label' => 'Shower', 'sqft' => 64],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $labor = collect($calc['labor'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($labor);
        $this->assertStringContainsString('Floor 40 sf', $labor['detail']);
        $this->assertStringContainsString('Shower 64 sf', $labor['detail']);
        $mat = collect($calc['materials'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($mat);
        $this->assertEqualsWithDelta(114.4, $mat['qty'], 0.01);
    }

    public function test_bathroom_tile_area_uses_length_times_width(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'detector_qty' => 0,
            'bath_tile_areas' => [
                ['label' => 'Floor', 'mode' => 'dimensions', 'length_ft' => 8, 'width_ft' => 5, 'sqft' => 0],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $labor = collect($calc['labor'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($labor);
        $this->assertStringContainsString('Floor 40 sf', $labor['detail']);
    }

    public function test_legacy_bath_tile_sf_still_quotes(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'detector_qty' => 0,
            'bath_tile_sf' => 50,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $labor = collect($calc['labor'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($labor);
        $this->assertStringContainsString('50', $labor['detail']);
    }

    public function test_bathroom_vinyl_floor_uses_lvp_not_tile(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'detector_qty' => 0,
            'bath_tile_areas' => [
                ['label' => 'Floor', 'sqft' => 40, 'finish' => 'vinyl'],
                ['label' => 'Shower', 'sqft' => 64, 'finish' => 'tile'],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $tileLabor = collect($calc['labor'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($tileLabor);
        $this->assertStringContainsString('Shower 64 sf', $tileLabor['detail']);
        $this->assertStringNotContainsString('Floor', $tileLabor['detail']);
        $this->assertEqualsWithDelta(round(64 / 12, 2), $tileLabor['hours'], 0.01);

        $vinylLabor = collect($calc['labor'])->firstWhere('key', 'bath_vinyl');
        $this->assertNotNull($vinylLabor);
        $this->assertSame('Bathroom vinyl flooring', $vinylLabor['label']);
        $this->assertStringContainsString('Floor 40 sf', $vinylLabor['detail']);
        $this->assertEqualsWithDelta(round(40 / 25, 2), $vinylLabor['hours'], 0.01);

        $underlayLabor = collect($calc['labor'])->firstWhere('key', 'bath_vinyl_underlay');
        $this->assertNotNull($underlayLabor);
        $this->assertEqualsWithDelta(round(40 / 80, 2), $underlayLabor['hours'], 0.01);

        $tileMat = collect($calc['materials'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($tileMat);
        $this->assertEqualsWithDelta(70.4, $tileMat['qty'], 0.01);
        $this->assertEqualsWithDelta(4.50, $tileMat['unit_price'], 0.01);

        $vinylMat = collect($calc['materials'])->firstWhere('key', 'bath_vinyl');
        $this->assertNotNull($vinylMat);
        $this->assertSame('Bathroom vinyl flooring', $vinylMat['label']);
        $this->assertEqualsWithDelta(43.2, $vinylMat['qty'], 0.01);
        $this->assertEqualsWithDelta(3.50, $vinylMat['unit_price'], 0.01);

        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'thinset_grout'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'bath_vinyl_underlay'));
    }

    public function test_bathroom_vinyl_only_skips_tile_materials(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'detector_qty' => 0,
            'bath_tile_areas' => [
                ['label' => 'Floor', 'sqft' => 40, 'finish' => 'vinyl'],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'bath_tile'));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'bath_tile'));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'thinset_grout'));
        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'bath_vinyl'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'bath_vinyl'));
    }

    public function test_disabled_painting_does_not_auto_fill_paint(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'scope_preset' => 'custom',
            'enabled_sections' => ['overview', 'drywall'],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $matKeys = collect($calc['materials'])->pluck('key')->all();
        $laborKeys = collect($calc['labor'])->pluck('key')->all();
        $this->assertContains('dw_hang', $laborKeys);
        $this->assertNotContains('paint_walls', $laborKeys);
        $this->assertEmpty(array_filter($matKeys, fn ($key) => str_contains((string) $key, 'paint')));
    }

    public function test_length_times_width_sets_work_area(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 0,
            'area_entry_mode' => 'dimensions',
            'area_length_ft' => 10,
            'area_width_ft' => 12,
            'flooring_type' => 'lvp',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(120.0, $calc['scope']['area_sqft']);
        $this->assertSame('dimensions', $calc['scope']['area_entry_mode']);
        $this->assertNotEmpty(collect($calc['materials'])->firstWhere('key', 'flooring_finish'));
    }

    public function test_incomplete_dimensions_do_not_keep_typed_sqft(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'area_entry_mode' => 'dimensions',
            'area_length_ft' => 10,
            'area_width_ft' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(0.0, $calc['scope']['area_sqft']);
        $this->assertSame(0.0, $calc['summary']['grand_total']);
    }

    public function test_trade_length_times_width_overrides_auto_qty(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'include_demolition' => true,
            'qty_dims' => [
                'demo_flooring_sf' => [
                    'mode' => 'dimensions',
                    'length_ft' => 8,
                    'width_ft' => 10,
                ],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $demo = collect($calc['labor'])->firstWhere('key', 'demo_floor');
        $this->assertNotNull($demo);
        $this->assertStringContainsString('80', $demo['detail']);
        $this->assertStringNotContainsString('400', $demo['detail']);
    }

    private function settings(?array $materials = null): BasementSettings
    {
        return new BasementSettings([
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => $materials ?? BasementSettings::defaultMaterials(),
        ]);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Services\BasementRoomAreaImportMapper;
use App\Services\OpenPlan3DMeasurementBridge;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaLayoutQuoteService;
use ReflectionMethod;
use Tests\TestCase;

class BasementRoomAreaImportMapperTest extends TestCase
{
    public function test_complete_payload_maps_every_basement_quantity_destination(): void
    {
        $opt = $this->fullOption();
        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 9);

        $this->assertSame(500.0, $proposed['area_sqft']);
        $this->assertSame(500.0, $proposed['flooring_sf']);
        $this->assertSame(8.0, $proposed['ceiling_height_ft']);
        $this->assertSame(1, $proposed['room_count']);
        $this->assertSame(90.0, $proposed['perimeter_lf']);
        $this->assertSame(12.0, $proposed['partition_lf']);
        $this->assertSame(16.0, $proposed['bulkhead_lf']);
        $this->assertSame(2, $proposed['framing_door_openings']);
        $this->assertSame(2, $proposed['door_qty']);
        $this->assertSame(2, $proposed['drywall_door_openings']);
        $this->assertSame(1, $proposed['window_trim_qty']);
        $this->assertSame(1, $proposed['drywall_window_openings']);
        $this->assertSame(1, $proposed['drywall_openings']);
        $this->assertSame(3, $proposed['casing_openings']);
        $this->assertSame(87.0, $proposed['baseboard_lf']);
        $this->assertSame(640.0, $proposed['insulation_wall_sf']);
        $this->assertSame(500.0, $proposed['insulation_ceiling_sf']);
        $this->assertSame(500.0, $proposed['ceiling_sf']);
        $this->assertSame(500.0, $proposed['drywall_ceiling_sf']);
        $this->assertSame(500.0, $proposed['paint_ceiling_sf']);
        $this->assertSame(750.0, $proposed['drywall_wall_sf']);
        $this->assertSame(480.0, $proposed['paint_wall_sf']);
        $this->assertSame(0.0, $proposed['drywall_opening_sf']);
        $this->assertSame(4, $proposed['drywall_inside_corners']);
        $this->assertSame(0, $proposed['drywall_corners']);
        $this->assertEqualsWithDelta(131.0, $proposed['paint_trim_lf'], 0.01);

        foreach (array_keys(BasementRoomAreaImportMapper::FIELD_IDS) as $key) {
            $this->assertArrayHasKey($key, $proposed);
            $this->assertNotNull($proposed[$key], $key.' should be filled from a complete drawing');
        }
        foreach (BasementRoomAreaImportMapper::NEVER_OVERWRITE as $key) {
            $this->assertArrayNotHasKey($key, $proposed);
        }
        $this->assertContains('drywall_ceiling_sf', BasementRoomAreaImportMapper::SKIP_FORM_KEYS);
    }

    public function test_missing_keys_are_null_so_manual_values_are_not_wiped(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 200,
        ], 8);

        $this->assertSame(200.0, $proposed['area_sqft']);
        $this->assertSame(200.0, $proposed['flooring_sf']);
        $this->assertSame(200.0, $proposed['ceiling_sf']);
        $this->assertSame(200.0, $proposed['drywall_ceiling_sf']);
        $this->assertNull($proposed['partition_lf']);
        $this->assertNull($proposed['bulkhead_lf']);
        $this->assertNull($proposed['baseboard_lf']);
        $this->assertNull($proposed['door_qty']);
        $this->assertNull($proposed['paint_trim_lf']);
        $this->assertNull($proposed['ceiling_height_ft']);
        $this->assertNull($proposed['drywall_opening_sf']);
    }

    public function test_measured_zero_is_not_treated_as_missing(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 80,
            'partition_lf' => 0,
            'bulkhead_lf' => 0,
            'door_count' => 0,
            'window_count' => 0,
            'opening_count' => 0,
            'baseboard_lf' => 0,
        ], 8);

        $this->assertSame(0.0, $proposed['partition_lf']);
        $this->assertSame(0.0, $proposed['bulkhead_lf']);
        $this->assertSame(0.0, $proposed['baseboard_lf']);
        $this->assertSame(0, $proposed['door_qty']);
        $this->assertSame(0, $proposed['window_trim_qty']);
    }

    public function test_default_unavailable_zero_ceiling_falls_back_to_floor(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 1500,
            'ceiling_area_sqft' => 0,
        ], 8);

        $this->assertFalse(BasementRoomAreaImportMapper::ceilingTakeoffAvailable([
            'area_sqft' => 1500,
            'ceiling_area_sqft' => 0,
        ]));
        $this->assertSame(1500.0, $proposed['ceiling_sf']);
        $this->assertSame(1500.0, $proposed['drywall_ceiling_sf']);
        $this->assertSame(1500.0, $proposed['paint_ceiling_sf']);
        $this->assertSame(1500.0, $proposed['insulation_ceiling_sf']);
    }

    public function test_explicit_unavailable_flag_falls_back_to_floor(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 1500,
            'ceiling_area_sqft' => 0,
            'ceiling_area_available' => false,
        ], 8);

        $this->assertSame(1500.0, $proposed['ceiling_sf']);
    }

    public function test_measured_zero_ceiling_is_imported_as_zero(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 0,
            'ceiling_area_sqft' => 0,
            'ceiling_area_available' => true,
            'excluded_area_sqft' => 1500,
            'polygon_count' => 1,
        ], 8);

        $this->assertSame(0.0, $proposed['ceiling_sf']);
        $this->assertSame(0.0, $proposed['drywall_ceiling_sf']);
        $this->assertSame(0.0, $proposed['paint_ceiling_sf']);
        $this->assertSame(0.0, $proposed['flooring_sf']);
    }

    public function test_exclusion_zero_without_flag_is_still_measured(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 0,
            'ceiling_area_sqft' => 0,
            'excluded_area_sqft' => 80,
            'polygon_count' => 1,
        ], 8);

        $this->assertTrue(BasementRoomAreaImportMapper::ceilingTakeoffAvailable([
            'area_sqft' => 0,
            'ceiling_area_sqft' => 0,
            'excluded_area_sqft' => 80,
            'polygon_count' => 1,
        ]));
        $this->assertSame(0.0, $proposed['ceiling_sf']);
    }

    public function test_missing_ceiling_and_floor_leave_ceiling_fields_null(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'perimeter_lf' => 90,
        ], 8);

        $this->assertNull($proposed['ceiling_sf']);
        $this->assertNull($proposed['drywall_ceiling_sf']);
        $this->assertNull($proposed['area_sqft']);
    }

    public function test_unavailable_zero_without_floor_does_not_wipe_manual_ceiling(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'perimeter_lf' => 90,
            'ceiling_area_sqft' => 0,
        ], 8);

        $this->assertNull($proposed['ceiling_sf']);
        $this->assertNull($proposed['drywall_ceiling_sf']);
        $this->assertNull($proposed['paint_ceiling_sf']);
    }

    public function test_canvas_empty_layout_metrics_default_zero_is_unavailable(): void
    {
        $empty = app(RoomAreaCanvasData::class)->emptyLayoutMetrics();
        $this->assertSame(0.0, $empty['ceiling_area_sqft']);
        $this->assertSame(0.0, $empty['area_sqft']);
        $this->assertFalse(BasementRoomAreaImportMapper::ceilingTakeoffAvailable($empty));

        $proposed = BasementRoomAreaImportMapper::proposedQuantities($empty, 8);
        $this->assertNull($proposed['ceiling_sf']);
        $this->assertNull($proposed['drywall_ceiling_sf']);
        $this->assertNull($proposed['paint_ceiling_sf']);
    }

    public function test_openplan_empty_layout_metrics_default_zero_is_unavailable(): void
    {
        $empty = app(OpenPlan3DMeasurementBridge::class)->emptyLayoutMetrics();
        $this->assertSame(0.0, $empty['ceiling_area_sqft']);
        $this->assertFalse(BasementRoomAreaImportMapper::ceilingTakeoffAvailable($empty));

        $proposed = BasementRoomAreaImportMapper::proposedQuantities($empty, 8);
        $this->assertNull($proposed['ceiling_sf']);
        $this->assertNull($proposed['drywall_ceiling_sf']);
    }

    public function test_empty_metrics_with_measured_floor_use_floor_not_placeholder_zero(): void
    {
        $opt = app(RoomAreaCanvasData::class)->emptyLayoutMetrics();
        $opt['area_sqft'] = 1500.0;
        $opt['perimeter_lf'] = 160.0;
        $this->assertSame(0.0, $opt['ceiling_area_sqft']);
        $this->assertFalse(BasementRoomAreaImportMapper::ceilingTakeoffAvailable($opt));

        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 8);
        $this->assertSame(1500.0, $proposed['ceiling_sf']);
        $this->assertSame(1500.0, $proposed['drywall_ceiling_sf']);
    }

    public function test_search_option_payload_nulls_unavailable_ceiling_and_keeps_measured_zero(): void
    {
        $service = app(RoomAreaLayoutQuoteService::class);
        $payloadFor = new ReflectionMethod(RoomAreaLayoutQuoteService::class, 'optionPayload');
        $payloadFor->setAccessible(true);

        $unavailable = app(RoomAreaCanvasData::class)->emptyLayoutMetrics();
        $unavailable['area_sqft'] = 1500.0;
        $unavailable['perimeter_lf'] = 160.0;
        $unavailablePayload = $payloadFor->invoke(
            $service,
            'room:1',
            'Rec',
            'room',
            $unavailable,
            false,
            'Rec',
            false
        );
        $this->assertFalse($unavailablePayload['ceiling_area_available']);
        $this->assertNull($unavailablePayload['ceiling_area_sqft']);
        $this->assertSame(1500.0, BasementRoomAreaImportMapper::proposedQuantities($unavailablePayload, 8)['ceiling_sf']);

        $measuredZero = app(RoomAreaCanvasData::class)->emptyLayoutMetrics();
        $measuredZero['area_sqft'] = 0.0;
        $measuredZero['ceiling_area_sqft'] = 0.0;
        $measuredZero['excluded_area_sqft'] = 80.0;
        $measuredZero['polygon_count'] = 1;
        $measuredZero['perimeter_lf'] = 40.0;
        $zeroPayload = $payloadFor->invoke(
            $service,
            'room:2',
            'Excluded',
            'room',
            $measuredZero,
            false,
            'Excluded',
            false
        );
        $this->assertTrue($zeroPayload['ceiling_area_available']);
        $this->assertSame(0.0, $zeroPayload['ceiling_area_sqft']);
        $this->assertSame(0.0, BasementRoomAreaImportMapper::proposedQuantities($zeroPayload, 8)['ceiling_sf']);
    }

    public function test_wall_finish_takeoffs_win_over_gross_metrics(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 100,
            'wall_area_sqft' => 900,
            'net_wall_area_sqft' => 800,
            'partition_lf' => 40,
            'baseboard_lf' => 90,
            'finish_drywall_sqft' => 210,
            'finish_insulation_sqft' => 180,
            'finish_stud_lf' => 12.5,
            'finish_baseboard_lf' => 70,
            'finish_casing_lf' => 18,
        ], 8);

        $this->assertSame(210.0, $proposed['drywall_wall_sf']);
        $this->assertNull($proposed['paint_wall_sf']);
        $this->assertSame(180.0, $proposed['insulation_wall_sf']);
        $this->assertSame(12.5, $proposed['partition_lf']);
        $this->assertSame(70.0, $proposed['baseboard_lf']);
        $this->assertSame(88.0, $proposed['paint_trim_lf']);
    }

    public function test_paint_wall_import_uses_selected_paint_finish_not_drywall_or_bulkhead(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 500,
            'net_wall_area_sqft' => 720,
            'finish_drywall_sqft' => 720,
            'finish_paint_sqft' => 208,
            'bulkhead_drywall_sqft' => 30,
            'bulkhead_lf' => 16,
            'bulkhead_corner_bead_lf' => 10,
            'ceiling_area_sqft' => 500,
        ], 8);

        $this->assertSame(750.0, $proposed['drywall_wall_sf']);
        $this->assertSame(208.0, $proposed['paint_wall_sf']);
        $this->assertSame(500.0, $proposed['paint_ceiling_sf']);
        $this->assertSame(16.0, $proposed['bulkhead_lf']);
        $this->assertArrayNotHasKey('bulkhead_corner_bead_lf', $proposed);
    }

    public function test_explicit_zero_paint_finish_imports_zero_not_unavailable(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 80,
            'finish_drywall_sqft' => 640,
            'finish_paint_sqft' => 0,
            'bulkhead_drywall_sqft' => 12,
        ], 8);

        $this->assertSame(0.0, $proposed['paint_wall_sf']);
        $this->assertSame(652.0, $proposed['drywall_wall_sf']);
    }

    public function test_missing_paint_finish_leaves_paint_wall_unavailable(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 80,
            'finish_drywall_sqft' => 640,
            'bulkhead_drywall_sqft' => 12,
        ], 8);

        $this->assertNull($proposed['paint_wall_sf']);
        $this->assertSame(652.0, $proposed['drywall_wall_sf']);
        $this->assertFalse(
            BasementRoomAreaImportMapper::present(['finish_paint_sqft' => null], 'finish_paint_sqft')
        );
    }

    public function test_mixed_legacy_paint_confirm_names_counts_and_previous_quantity(): void
    {
        $opt = [
            'finish_paint_sqft' => 0,
            'paint_walls_marked' => 0,
            'paint_walls_explicit_off' => 1,
            'paint_walls_unassigned' => 2,
            'paint_bulkheads_unassigned' => 1,
        ];
        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 8);
        $this->assertSame(0.0, $proposed['paint_wall_sf']);
        $current = ['paint_wall_sf' => 400, 'import_label' => 'Manual', 'area_sqft' => 80];
        $this->assertTrue(BasementRoomAreaImportMapper::needsImportConfirm($current, $proposed, $opt));
        $this->assertTrue(BasementRoomAreaImportMapper::paintAssignmentMixed($opt));
        $msg = BasementRoomAreaImportMapper::confirmImportMessage($current, $proposed, $opt);
        $this->assertStringContainsString('0 wall(s) marked Paint', $msg);
        $this->assertStringContainsString('2 wall(s) with no Paint choice', $msg);
        $this->assertStringContainsString('Imported wall paint: 0 sq ft', $msg);
        $this->assertStringContainsString('current wall paint of 400 sq ft', $msg);

        $missing = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 80,
            'finish_drywall_sqft' => 640,
            'paint_walls_unassigned' => 3,
        ], 8);
        $this->assertNull($missing['paint_wall_sf']);
        $this->assertFalse(BasementRoomAreaImportMapper::needsImportConfirm(
            array_merge($missing, ['paint_wall_sf' => 400, 'import_label' => 'Manual']),
            $missing,
            ['paint_walls_unassigned' => 3]
        ));
    }

    public function test_unmapped_keys_are_documented_and_exclude_corner_bead(): void
    {
        $this->assertContains('bulkhead_corner_bead_lf', BasementRoomAreaImportMapper::UNMAPPED_LAYOUT_KEYS);
        $this->assertContains('cabinet_base_lf', BasementRoomAreaImportMapper::UNMAPPED_LAYOUT_KEYS);
        $this->assertContains('stairs_treads', BasementRoomAreaImportMapper::UNMAPPED_LAYOUT_KEYS);
        $this->assertContains('framing_lf', BasementRoomAreaImportMapper::UNMAPPED_LAYOUT_KEYS);
        $this->assertNotContains('partition_lf', BasementRoomAreaImportMapper::UNMAPPED_LAYOUT_KEYS);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullOption(): array
    {
        return [
            'area_sqft' => 500,
            'ceiling_area_sqft' => 500,
            'ceiling_height_ft' => 8,
            'perimeter_lf' => 90,
            'wall_area_sqft' => 720,
            'net_wall_area_sqft' => 720,
            'inside_corners' => 4,
            'outside_corners' => 0,
            'polygon_count' => 1,
            'door_count' => 2,
            'window_count' => 1,
            'opening_count' => 1,
            'baseboard_lf' => 87,
            'casing_lf' => 44,
            'bulkhead_lf' => 16,
            'bulkhead_framing_lf' => 16,
            'bulkhead_drywall_sqft' => 30,
            'partition_lf' => 12,
            'finish_drywall_sqft' => 720,
            'finish_paint_sqft' => 480,
            'finish_insulation_sqft' => 640,
            'finish_stud_lf' => 12,
            'finish_baseboard_lf' => 87,
        ];
    }
}

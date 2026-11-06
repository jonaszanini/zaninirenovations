<?php

namespace Tests\Unit\Services;

use App\Services\BasementRoomAreaImportMapper;
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
        $this->assertSame(750.0, $proposed['paint_wall_sf']);
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

    public function test_zero_ceiling_area_falls_back_to_floor_area_instead_of_wiping(): void
    {
        $proposed = BasementRoomAreaImportMapper::proposedQuantities([
            'area_sqft' => 1500,
            'ceiling_area_sqft' => 0,
        ], 8);

        $this->assertSame(1500.0, $proposed['ceiling_sf']);
        $this->assertSame(1500.0, $proposed['drywall_ceiling_sf']);
        $this->assertSame(1500.0, $proposed['paint_ceiling_sf']);
        $this->assertSame(1500.0, $proposed['insulation_ceiling_sf']);
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
        $this->assertSame(180.0, $proposed['insulation_wall_sf']);
        $this->assertSame(12.5, $proposed['partition_lf']);
        $this->assertSame(70.0, $proposed['baseboard_lf']);
        $this->assertSame(88.0, $proposed['paint_trim_lf']);
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
            'finish_insulation_sqft' => 640,
            'finish_stud_lf' => 12,
            'finish_baseboard_lf' => 87,
        ];
    }
}

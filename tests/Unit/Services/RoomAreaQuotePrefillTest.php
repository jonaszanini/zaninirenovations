<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaQuotePrefill;
use Tests\TestCase;

class RoomAreaQuotePrefillTest extends TestCase
{
    public function test_flooring_inputs_use_floor_area_and_baseboard(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $inputs = $prefill->wizardInputs('flooring', $this->option());

        $this->assertSame(80.0, $inputs['square_footage']);
        $this->assertSame(88.0, $inputs['square_footage_with_waste']);
        $this->assertSame(10.0, $inputs['waste_percent']);
        $this->assertSame(5, $inputs['flooring_boxes']);
        $this->assertSame(30.0, $inputs['baseboard_linear_ft']);
        $this->assertTrue($inputs['install_baseboard']);
    }

    public function test_painting_adds_net_wall_and_ceiling(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $inputs = $prefill->wizardInputs('painting', $this->option());

        $this->assertSame(312.0, $inputs['total_property_sqf']);
    }

    public function test_millwork_prefills_cabinet_piece_width_from_linear_feet(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $inputs = $prefill->wizardInputs('millwork', $this->option());

        $this->assertCount(2, $inputs['pieces']);
        $this->assertSame('cabinet', $inputs['pieces'][0]['type']);
        $this->assertSame(36.0, $inputs['pieces'][0]['width']);
        $this->assertSame(24.0, $inputs['pieces'][0]['depth']);
        $this->assertSame('cabinet', $inputs['pieces'][1]['type']);
        $this->assertSame(24.0, $inputs['pieces'][1]['width']);
        $this->assertSame(12.0, $inputs['pieces'][1]['depth']);
    }

    public function test_stairs_prefills_total_steps_from_treads(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $inputs = $prefill->wizardInputs('stairs', $this->option());

        $this->assertSame(12, $inputs['total_steps']);
        $this->assertSame('standard', $inputs['segments'][0]['stair_type']);
        $this->assertSame(12, $inputs['segments'][0]['qty']);
    }

    public function test_trimming_includes_casing_lf(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $inputs = $prefill->wizardInputs('trimming', $this->option());

        $this->assertSame(44.67, $inputs['casing_lf']);
        $this->assertSame(1, $inputs['door_qty']);
        $this->assertSame(1, $inputs['window_qty']);
    }

    public function test_quotes_use_inside_area_when_thickness_present(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $option = $this->option();
        $option['area_sqft'] = 80.0;
        $option['inside_area_sqft'] = 80.0;
        $option['outside_area_sqft'] = 98.0;
        $inputs = $prefill->wizardInputs('flooring', $option);

        $this->assertSame(80.0, $inputs['square_footage']);
    }

    /**
     * @return array<string, mixed>
     */
    private function option(): array
    {
        return [
            'area_sqft' => 80.0,
            'perimeter_lf' => 36.0,
            'baseboard_lf' => 30.0,
            'wall_area_sqft' => 232.0,
            'net_wall_area_sqft' => 232.0,
            'ceiling_area_sqft' => 80.0,
            'flooring_waste_percent' => 10.0,
            'flooring_boxes' => 5,
            'flooring_box_sqft' => 20.0,
            'flooring_with_waste_sqft' => 88.0,
            'casing_lf' => 44.67,
            'door_count' => 1,
            'window_count' => 1,
            'cabinet_base_lf' => 3.0,
            'cabinet_upper_lf' => 2.0,
            'stairs_treads' => 12,
            'stairs_quote_type' => 'standard',
        ];
    }
}

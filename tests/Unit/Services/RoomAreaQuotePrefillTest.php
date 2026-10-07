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
        $this->assertSame(30.0, $inputs['baseboard_linear_ft']);
        $this->assertTrue($inputs['install_baseboard']);
    }

    public function test_painting_adds_wall_and_ceiling(): void
    {
        $prefill = app(RoomAreaQuotePrefill::class);
        $inputs = $prefill->wizardInputs('painting', $this->option());

        $this->assertSame(336.0, $inputs['total_property_sqf']);
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
            'wall_area_sqft' => 256.0,
            'door_count' => 1,
            'window_count' => 1,
        ];
    }
}

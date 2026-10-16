<?php

namespace Tests\Unit\Services;

use App\Services\OpenPlan3DPlanValidator;
use Tests\TestCase;

class OpenPlan3DPlanValidatorTest extends TestCase
{
    private OpenPlan3DPlanValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(OpenPlan3DPlanValidator::class);
    }

    private function rectangle(float $widthCm = 304.8, float $lengthCm = 365.76): array
    {
        return [
            'unit' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $widthCm, 'y' => 0], 'thickness' => 10, 'height' => 243.84],
                    ['id' => 'w2', 'start' => ['x' => $widthCm, 'y' => 0], 'end' => ['x' => $widthCm, 'y' => $lengthCm], 'thickness' => 10, 'height' => 243.84],
                    ['id' => 'w3', 'start' => ['x' => $widthCm, 'y' => $lengthCm], 'end' => ['x' => 0, 'y' => $lengthCm], 'thickness' => 10, 'height' => 243.84],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => $lengthCm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 243.84],
                ],
                'rooms' => [
                    [
                        'id' => 'r1',
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $widthCm, 'y' => 0],
                            ['x' => $widthCm, 'y' => $lengthCm],
                            ['x' => 0, 'y' => $lengthCm],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_accepts_a_normal_rectangle(): void
    {
        $this->assertTrue($this->validator->validate($this->rectangle())['valid']);
    }

    public function test_rejects_nan_coordinates(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'][0]['start']['x'] = 'NaN';

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('finite', (string) $result['message']);
    }

    public function test_rejects_infinite_wall_length(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'][0]['end']['x'] = INF;

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
    }

    public function test_rejects_self_intersecting_polygon(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 100, 'y' => 0],
            ['x' => 0, 'y' => 100],
        ];

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('crosses itself', (string) $result['message']);
    }

    public function test_rejects_negative_thickness(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'][0]['thickness'] = -4;

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('negative', (string) $result['message']);
    }

    public function test_rejects_impossible_room_area(): void
    {
        $huge = 200000; // cm → ~430k sq ft
        $result = $this->validator->validate($this->rectangle($huge, $huge));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('impossibly large', (string) $result['message']);
    }

    public function test_rejects_impossible_wall_length(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'][0]['end']['x'] = 50000; // ~1640 ft

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('impossibly large', (string) $result['message']);
    }
}

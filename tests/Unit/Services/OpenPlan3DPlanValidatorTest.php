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

    public function test_rejects_non_array_wall_entry(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'][] = 'invalid-string-wall';

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertSame('Wall entry must be an object.', $result['message']);
    }

    public function test_rejects_non_array_room_entry(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['rooms'][] = 12345;

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertSame('Room entry must be an object.', $result['message']);
    }

    public function test_rejects_non_array_item_entry(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['furniture'] = ['not-an-object'];

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertSame('Furniture entry must be an object.', $result['message']);
    }

    public function test_rejects_oversized_payload(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['blob'] = str_repeat('X', OpenPlan3DPlanValidator::MAX_PAYLOAD_BYTES + 10);

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds maximum allowed size', (string) $result['message']);
    }

    public function test_rejects_impossible_item_dimensions(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['furniture'] = [
            ['id' => 'f1', 'width' => 99999, 'depth' => 10, 'height' => 10],
        ];

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('width is impossibly large', (string) $result['message']);
    }

    public function test_rejects_impossible_coordinates(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'][0]['start']['x'] = 2000000;

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('coordinate is impossibly large', (string) $result['message']);
    }

    public function test_allows_existing_saved_plan_with_self_crossing_polygon(): void
    {
        $plan = $this->rectangle();
        $crossingPoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 100, 'y' => 0],
            ['x' => 0, 'y' => 100],
        ];
        $plan['floor']['rooms'][0]['polygon'] = $crossingPoly;

        // Without existing plan, it must be rejected
        $newResult = $this->validator->validate($plan);
        $this->assertFalse($newResult['valid']);
        $this->assertStringContainsString('crosses itself', (string) $newResult['message']);

        // With existing plan containing the exact same polygon, it must be allowed (downgraded to warning)
        $existingPlan = $this->rectangle();
        $existingPlan['floor']['rooms'][0]['polygon'] = $crossingPoly;

        $resaveResult = $this->validator->validate($plan, $existingPlan);
        $this->assertTrue($resaveResult['valid']);
        $this->assertNotNull($resaveResult['warning'] ?? null);

        // If the polygon is modified, it must still be rejected
        $modifiedPlan = $plan;
        $modifiedPlan['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => 120, 'y' => 120],
            ['x' => 120, 'y' => 0],
            ['x' => 0, 'y' => 120],
        ];
        $modifiedResult = $this->validator->validate($modifiedPlan, $existingPlan);
        $this->assertFalse($modifiedResult['valid']);
        $this->assertStringContainsString('crosses itself', (string) $modifiedResult['message']);
    }
}

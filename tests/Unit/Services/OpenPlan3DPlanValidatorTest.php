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

    public function test_converts_item_sizes_to_cm_using_plan_unit_before_comparing(): void
    {
        $planMeters = $this->rectangle(5.0, 6.0);
        $planMeters['unit'] = 'm';
        foreach ($planMeters['floor']['walls'] as &$w) {
            $w['thickness'] = 0.1;
            $w['height'] = 2.4;
        }
        unset($w);
        // In meters: width 120m = 12000 cm > MAX_ITEM_WIDTH_CM (10000 cm)
        $planMeters['floor']['furniture'] = [
            ['id' => 'f1', 'width' => 120.0, 'depth' => 1.0, 'height' => 1.0],
        ];

        $result = $this->validator->validate($planMeters);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('width is impossibly large', (string) $result['message']);

        // In meters: width 80m = 8000 cm <= 10000 cm -> valid
        $planMeters['floor']['furniture'][0]['width'] = 80.0;
        $this->assertTrue($this->validator->validate($planMeters)['valid']);

        // In cm: width 9500 cm <= 10000 cm -> valid
        $planCm = $this->rectangle();
        $planCm['unit'] = 'cm';
        $planCm['floor']['furniture'] = [
            ['id' => 'f2', 'width' => 9500.0, 'depth' => 50.0],
        ];
        $this->assertTrue($this->validator->validate($planCm)['valid']);

        // In cm: width 10001 cm > 10000 cm -> invalid
        $planCm['floor']['furniture'][0]['width'] = 10001.0;
        $resultCm = $this->validator->validate($planCm);
        $this->assertFalse($resultCm['valid']);
        $this->assertStringContainsString('width is impossibly large', (string) $resultCm['message']);
    }

    public function test_self_crossing_exemption_strictly_matches_same_room_id(): void
    {
        $crossingPoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 100, 'y' => 0],
            ['x' => 0, 'y' => 100],
        ];

        $existingPlan = $this->rectangle();
        $existingPlan['floor']['rooms'][0]['id'] = 'room-saved-1';
        $existingPlan['floor']['rooms'][0]['polygon'] = $crossingPoly;

        // Same polygon, but different room id -> must be rejected
        $planDifferentId = $this->rectangle();
        $planDifferentId['floor']['rooms'][0]['id'] = 'room-different-2';
        $planDifferentId['floor']['rooms'][0]['polygon'] = $crossingPoly;

        $diffResult = $this->validator->validate($planDifferentId, $existingPlan);
        $this->assertFalse($diffResult['valid']);
        $this->assertStringContainsString('crosses itself', (string) $diffResult['message']);

        // Same polygon, but no room id -> must be rejected
        $planNoId = $this->rectangle();
        unset($planNoId['floor']['rooms'][0]['id']);
        $planNoId['floor']['rooms'][0]['polygon'] = $crossingPoly;

        $noIdResult = $this->validator->validate($planNoId, $existingPlan);
        $this->assertFalse($noIdResult['valid']);
        $this->assertStringContainsString('crosses itself', (string) $noIdResult['message']);

        // Same polygon and same room id -> allowed with warning
        $planSameId = $this->rectangle();
        $planSameId['floor']['rooms'][0]['id'] = 'room-saved-1';
        $planSameId['floor']['rooms'][0]['polygon'] = $crossingPoly;

        $sameResult = $this->validator->validate($planSameId, $existingPlan);
        $this->assertTrue($sameResult['valid']);
        $this->assertNotNull($sameResult['warning']);
    }

    public function test_rejects_plan_with_invalid_wall_or_room_array_structures(): void
    {
        $plan = $this->rectangle();
        $plan['floor']['walls'] = 'not-an-array';

        $result = $this->validator->validate($plan);
        $this->assertFalse($result['valid']);
        $this->assertSame('Floor walls must be an array.', $result['message']);

        $plan2 = $this->rectangle();
        $plan2['floor']['rooms'] = 'not-an-array';

        $result2 = $this->validator->validate($plan2);
        $this->assertFalse($result2['valid']);
        $this->assertSame('Floor rooms must be an array.', $result2['message']);
    }

    public function test_whitelists_allowed_units_and_rejects_anything_else(): void
    {
        // Test each whitelisted unit with appropriate wall dimensions
        $unitsWithDims = [
            'cm' => ['w' => 300.0, 'l' => 400.0, 'thick' => 10.0, 'h' => 240.0],
            'm' => ['w' => 3.0, 'l' => 4.0, 'thick' => 0.1, 'h' => 2.4],
            'mm' => ['w' => 3000.0, 'l' => 4000.0, 'thick' => 100.0, 'h' => 2400.0],
            'in' => ['w' => 120.0, 'l' => 150.0, 'thick' => 4.0, 'h' => 96.0],
            'ft' => ['w' => 10.0, 'l' => 12.0, 'thick' => 0.33, 'h' => 8.0],
        ];

        foreach ($unitsWithDims as $unit => $dims) {
            $plan = $this->rectangle($dims['w'], $dims['l']);
            $plan['unit'] = $unit;
            foreach ($plan['floor']['walls'] as &$w) {
                $w['thickness'] = $dims['thick'];
                $w['height'] = $dims['h'];
            }
            unset($w);

            $res = $this->validator->validate($plan);
            $this->assertTrue($res['valid'], "Unit '{$unit}' must be accepted.");
        }

        foreach (['yards', 'km', 'pixels', 'banana', 'yd'] as $invalidUnit) {
            $plan = $this->rectangle();
            $plan['unit'] = $invalidUnit;
            $res = $this->validator->validate($plan);
            $this->assertFalse($res['valid'], "Unit '{$invalidUnit}' must be rejected.");
            $this->assertSame('Invalid plan unit. Allowed units are cm, m, mm, in, ft.', $res['message']);
        }

        // Floor-level invalid unit
        $planFloorInvalid = $this->rectangle();
        unset($planFloorInvalid['unit']);
        $planFloorInvalid['floor']['unit'] = 'lightyears';
        $resFloor = $this->validator->validate($planFloorInvalid);
        $this->assertFalse($resFloor['valid']);
        $this->assertSame('Invalid plan unit. Allowed units are cm, m, mm, in, ft.', $resFloor['message']);
    }

    public function test_converts_item_sizes_with_mm_unit(): void
    {
        $planMm = $this->rectangle(3000.0, 4000.0);
        $planMm['unit'] = 'mm';
        foreach ($planMm['floor']['walls'] as &$w) {
            $w['thickness'] = 100.0;
            $w['height'] = 2400.0;
        }
        unset($w);

        // 90,000 mm = 9,000 cm <= 10,000 cm limit -> valid
        $planMm['floor']['furniture'] = [
            ['id' => 'item-mm-ok', 'name' => 'MM Desk', 'width' => 90000.0, 'depth' => 4000.0],
        ];
        $resOk = $this->validator->validate($planMm);
        $this->assertTrue($resOk['valid']);

        // 110,000 mm = 11,000 cm > 10,000 cm limit -> invalid
        $planMm['floor']['furniture'] = [
            ['id' => 'item-mm-too-big', 'name' => 'Giant MM Desk', 'width' => 110000.0, 'depth' => 4000.0],
        ];
        $resTooBig = $this->validator->validate($planMm);
        $this->assertFalse($resTooBig['valid']);
        $this->assertStringContainsString('width is impossibly large', (string) $resTooBig['message']);
    }
}

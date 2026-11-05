<?php

namespace Tests\Unit\Services;

use App\Models\RoomAreaAssembly;
use App\Models\RoomAreaAssemblyLine;
use App\Services\OpenPlan3DMeasurementBridge;
use App\Services\RoomAreaAssemblyDefaults;
use App\Services\RoomAreaAssemblyEvaluator;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaFinishMath;
use App\Services\RoomAreaFormulaParser;
use App\Services\RoomAreaTemplates;
use Tests\TestCase;

class OpenPlan3DParityTest extends TestCase
{
    private OpenPlan3DMeasurementBridge $bridge;

    private RoomAreaCanvasData $canvasData;

    private RoomAreaFinishMath $finishMath;

    private RoomAreaTemplates $templates;

    private RoomAreaAssemblyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = app(OpenPlan3DMeasurementBridge::class);
        $this->canvasData = app(RoomAreaCanvasData::class);
        $this->finishMath = app(RoomAreaFinishMath::class);
        $this->templates = app(RoomAreaTemplates::class);
        $this->evaluator = app(RoomAreaAssemblyEvaluator::class);
    }

    /**
     * @dataProvider sampleRoomsProvider
     *
     * @param  array<string, mixed>  $canvas
     * @param  array<string, mixed>  $finish
     */
    public function test_openplan3d_measurements_match_canvas_measurements_exactly(
        string $description,
        array $canvas,
        float $scale,
        float $wallHeightFt,
        array $finish
    ): void {
        // 1. Classic Canvas metrics
        $oldMetrics = $this->canvasData->layoutMetrics($canvas, $scale, $wallHeightFt, $finish);

        // 2. OpenPlan3D model converted from canvas
        $openPlan = $this->bridge->fromCanvasData($canvas, $scale, $wallHeightFt, $finish);
        $this->assertTrue($this->bridge->isPlan($openPlan), "Converted openPlan model must be recognized as valid plan for: {$description}");

        // 3. OpenPlan3D bridge metrics
        $newMetrics = $this->bridge->layoutMetrics($openPlan, null, $finish);

        // 4. Parity assertions across all measurement fields
        $fieldsToCompare = [
            'area_sqft',
            'perimeter_lf',
            'baseboard_lf',
            'wall_height_ft',
            'wall_area_sqft',
            'ceiling_height_ft',
            'ceiling_area_sqft',
            'door_count',
            'window_count',
            'opening_count',
            'drywall_openings',
            'casing_lf',
            'inside_corners',
            'outside_corners',
            'flooring_boxes',
            'flooring_with_waste_sqft',
            'cabinet_base_lf',
            'cabinet_upper_lf',
            'stairs_count',
            'stairs_treads',
            'stairs_run_lf',
            'stairs_quote_type',
        ];

        foreach ($fieldsToCompare as $field) {
            $this->assertSame(
                $oldMetrics[$field],
                $newMetrics[$field],
                "Mismatch on field [{$field}] for [{$description}]. Canvas: {$oldMetrics[$field]}, OpenPlan3D: {$newMetrics[$field]}"
            );
        }

        // 5. Parity on all 32 Formula Variables
        $oldVars = RoomAreaFormulaParser::variablesFromMetrics($oldMetrics);
        $newVars = RoomAreaFormulaParser::variablesFromMetrics($newMetrics);
        $this->assertSame($oldVars, $newVars, "Formula variables mismatch for [{$description}]");

        // 6. Kit evaluation parity
        $this->assertKitOutputIdentical($oldMetrics, $newMetrics, $description);
    }

    private function assertKitOutputIdentical(array $oldMetrics, array $newMetrics, string $description): void
    {
        $assemblies = $this->sampleAssemblies();
        foreach ($assemblies as $assembly) {
            $oldResult = $this->evaluator->evaluateAssembly($assembly, $oldMetrics);
            $newResult = $this->evaluator->evaluateAssembly($assembly, $newMetrics);

            $this->assertCount(count($oldResult['lines']), $newResult['lines'], "Line count mismatch for kit [{$assembly->name}] in [{$description}]");

            foreach ($oldResult['lines'] as $idx => $oldLine) {
                $newLine = $newResult['lines'][$idx];
                $this->assertSame(
                    $oldLine['raw'],
                    $newLine['raw'],
                    "Raw quantity mismatch on line [{$oldLine['name']}] in [{$description}]"
                );
                $this->assertSame(
                    $oldLine['qty'],
                    $newLine['qty'],
                    "Rounded quantity mismatch on line [{$oldLine['name']}] in [{$description}]"
                );
                $this->assertSame(
                    $oldLine['error'],
                    $newLine['error'],
                    "Error state mismatch on line [{$oldLine['name']}] in [{$description}]"
                );
            }
        }
    }

    /**
     * @return list<RoomAreaAssembly>
     */
    private function sampleAssemblies(): array
    {
        $assemblies = [];
        foreach (RoomAreaAssemblyDefaults::definitions() as $def) {
            $assembly = new RoomAreaAssembly([
                'name' => $def['name'],
                'notes' => $def['notes'],
            ]);
            $assembly->id = rand(100, 999);
            $lines = [];
            foreach ($def['lines'] as $lineDef) {
                $line = new RoomAreaAssemblyLine([
                    'name' => $lineDef['name'],
                    'formula' => $lineDef['formula'],
                    'unit' => $lineDef['unit'],
                    'whole_units' => $lineDef['whole_units'],
                ]);
                $line->setRelation('material', null);
                $lines[] = $line;
            }
            $assembly->setRelation('lines', collect($lines));
            $assemblies[] = $assembly;
        }

        // Add custom trim and opening kit
        $customKit = new RoomAreaAssembly([
            'name' => 'Custom Finish Kit',
            'notes' => 'Casing, baseboard, and corner bead formulas',
        ]);
        $customKit->id = 888;
        $customLines = [
            new RoomAreaAssemblyLine(['name' => 'Window Casing LF', 'formula' => 'casing_lf', 'unit' => 'lf', 'whole_units' => false]),
            new RoomAreaAssemblyLine(['name' => 'Baseboard with 15% Waste', 'formula' => 'baseboard_lf * 1.15', 'unit' => 'lf', 'whole_units' => false]),
            new RoomAreaAssemblyLine(['name' => 'Drywall Cuts per Opening', 'formula' => 'drywall_openings * 4', 'unit' => 'ea', 'whole_units' => true]),
            new RoomAreaAssemblyLine(['name' => 'Total Wall Area Over 32', 'formula' => 'wall_area_sqft / 32', 'unit' => 'ea', 'whole_units' => false]),
        ];
        foreach ($customLines as $customLine) {
            $customLine->setRelation('material', null);
        }
        $customKit->setRelation('lines', collect($customLines));
        $assemblies[] = $customKit;

        return $assemblies;
    }

    /**
     * Provides 25 sample rooms spanning Rectangle, L, T, U, Alcove, Multi-corner,
     * varying wall heights, ceiling heights, doors, windows, openings, casing sides,
     * cabinets, stairs, and flooring settings.
     *
     * @return array<string, array<mixed>>
     */
    public static function sampleRoomsProvider(): array
    {
        $scale = 20.0; // 20 px = 1 ft

        $makeCanvas = function (array $polygonPtsFt, array $objects = [], float $wallThicknessIn = 4.5) use ($scale) {
            $vertsPx = [];
            foreach ($polygonPtsFt as $pt) {
                $vertsPx[] = ['x' => $pt['x'] * $scale, 'y' => $pt['y'] * $scale];
            }

            return [
                'v' => 4,
                'groups' => [
                    [
                        'id' => 'g1',
                        'color' => '#024768',
                        'polygons' => [
                            [
                                'id' => 'p1',
                                'name' => 'Room 1',
                                'vertices' => $vertsPx,
                            ],
                        ],
                    ],
                ],
                'objects' => $objects,
                'wall_thickness_in' => $wallThicknessIn,
            ];
        };

        $templates = new RoomAreaTemplates;

        return [
            '01_rectangle_clean' => [
                'description' => '01: 10x12 Rectangle with no openings',
                'canvas' => $makeCanvas($templates->rectangle(10, 12)),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '02_rectangle_with_door' => [
                'description' => '02: 12x14 Rectangle with standard interior door',
                'canvas' => $makeCanvas($templates->rectangle(12, 14), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 32, 'height' => 80, 'offset' => 24, 'casing_sides' => 2, 'door_face' => 'interior'],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '03_rectangle_door_and_window_4side' => [
                'description' => '03: 14x16 Rectangle with door and 4-side casing window',
                'canvas' => $makeCanvas($templates->rectangle(14, 16), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 18, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 48, 'offset' => 36, 'sill_height' => 36, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '04_rectangle_door_and_window_3side_stool' => [
                'description' => '04: 12x12 Rectangle with door and 3-side casing window (stool)',
                'canvas' => $makeCanvas($templates->rectangle(12, 12), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 32, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 48, 'height' => 36, 'offset' => 24, 'sill_height' => 36, 'casing_sides' => 3],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '05_rectangle_exterior_door_and_windows' => [
                'description' => '05: 16x20 Rectangle with exterior door (1-side casing) and 2 windows',
                'canvas' => $makeCanvas($templates->rectangle(16, 20), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 36, 'casing_sides' => 1, 'door_face' => 'exterior'],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 40, 'height' => 50, 'offset' => 24, 'sill_height' => 30, 'casing_sides' => 4],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 40, 'height' => 50, 'offset' => 100, 'sill_height' => 30, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '06_rectangle_cased_opening' => [
                'description' => '06: 10x15 Rectangle with cased opening and window',
                'canvas' => $makeCanvas($templates->rectangle(10, 15), [
                    ['id' => 'obj1', 'type' => 'opening', 'wall_id' => 'wall_p1_0_1', 'width' => 48, 'height' => 84, 'offset' => 20, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_3_0', 'width' => 36, 'height' => 36, 'offset' => 36, 'sill_height' => 42, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '07_l_shape_clean' => [
                'description' => '07: 16x12 L-shaped room (return 6x4) clean',
                'canvas' => $makeCanvas($templates->lShape(16, 12, 6, 4)),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '08_l_shape_with_doors_and_windows' => [
                'description' => '08: 16x12 L-shape with 2 doors and 2 windows',
                'canvas' => $makeCanvas($templates->lShape(16, 12, 6, 4), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 32, 'height' => 80, 'offset' => 24, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'door', 'wall_id' => 'wall_p1_5_0', 'width' => 32, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 48, 'offset' => 30, 'sill_height' => 36, 'casing_sides' => 4],
                    ['id' => 'obj4', 'type' => 'window', 'wall_id' => 'wall_p1_3_4', 'width' => 30, 'height' => 40, 'offset' => 10, 'sill_height' => 36, 'casing_sides' => 3],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '09_l_shape_heavy_openings' => [
                'description' => '09: 18x16 L-shape (return 8x6) with 3 doors, 1 opening, 2 windows',
                'canvas' => $makeCanvas($templates->lShape(18, 16, 8, 6), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 20, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'door', 'wall_id' => 'wall_p1_1_2', 'width' => 32, 'height' => 80, 'offset' => 18, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'opening', 'wall_id' => 'wall_p1_5_0', 'width' => 40, 'height' => 84, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj4', 'type' => 'door', 'wall_id' => 'wall_p1_4_5', 'width' => 32, 'height' => 80, 'offset' => 12, 'casing_sides' => 1, 'door_face' => 'exterior'],
                    ['id' => 'obj5', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 48, 'height' => 48, 'offset' => 18, 'sill_height' => 30, 'casing_sides' => 4],
                    ['id' => 'obj6', 'type' => 'window', 'wall_id' => 'wall_p1_3_4', 'width' => 36, 'height' => 48, 'offset' => 10, 'sill_height' => 30, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => ['flooring_waste_percent' => 12.0, 'flooring_box_sqft' => 22.5],
            ],
            '10_t_shape_clean' => [
                'description' => '10: 16x12 T-shaped room clean',
                'canvas' => $makeCanvas($templates->tShape(16, 4, 6, 8)),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '11_t_shape_with_openings' => [
                'description' => '11: 16x12 T-shape with 2 doors and 2 windows',
                'canvas' => $makeCanvas($templates->tShape(16, 4, 6, 8), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 30, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'door', 'wall_id' => 'wall_p1_4_5', 'width' => 32, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 40, 'offset' => 4, 'sill_height' => 36, 'casing_sides' => 4],
                    ['id' => 'obj4', 'type' => 'window', 'wall_id' => 'wall_p1_7_0', 'width' => 36, 'height' => 40, 'offset' => 4, 'sill_height' => 36, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '12_u_shape_clean' => [
                'description' => '12: 16x12 U-shaped room clean',
                'canvas' => $makeCanvas($templates->uShape(16, 12, 8, 8)),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '13_u_shape_with_openings' => [
                'description' => '13: 16x12 U-shape with 2 doors and 3 windows',
                'canvas' => $makeCanvas($templates->uShape(16, 12, 8, 8), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 24, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'opening', 'wall_id' => 'wall_p1_5_6', 'width' => 36, 'height' => 84, 'offset' => 10, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 48, 'offset' => 18, 'sill_height' => 36, 'casing_sides' => 4],
                    ['id' => 'obj4', 'type' => 'window', 'wall_id' => 'wall_p1_7_0', 'width' => 36, 'height' => 48, 'offset' => 18, 'sill_height' => 36, 'casing_sides' => 4],
                    ['id' => 'obj5', 'type' => 'window', 'wall_id' => 'wall_p1_4_5', 'width' => 36, 'height' => 36, 'offset' => 12, 'sill_height' => 36, 'casing_sides' => 3],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '14_powder_room' => [
                'description' => '14: 5x7 Powder room with pocket door',
                'canvas' => $makeCanvas($templates->rectangle(5, 7), [
                    ['id' => 'obj1', 'type' => 'door', 'subtype' => 'pocket', 'wall_id' => 'wall_p1_0_1', 'width' => 30, 'height' => 80, 'offset' => 10, 'casing_sides' => 2],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => ['flooring_waste_percent' => 15.0],
            ],
            '15_bathroom' => [
                'description' => '15: 8x10 Bathroom with door and high window',
                'canvas' => $makeCanvas($templates->rectangle(8, 10), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 32, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 36, 'height' => 24, 'offset' => 20, 'sill_height' => 60, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '16_master_bedroom' => [
                'description' => '16: 16x22 Master bedroom with double door, pocket door, and 3 windows',
                'canvas' => $makeCanvas($templates->rectangle(16, 22), [
                    ['id' => 'obj1', 'type' => 'door', 'subtype' => 'double', 'wall_id' => 'wall_p1_0_1', 'width' => 60, 'height' => 80, 'offset' => 36, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'door', 'subtype' => 'pocket', 'wall_id' => 'wall_p1_1_2', 'width' => 32, 'height' => 80, 'offset' => 18, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 48, 'height' => 60, 'offset' => 18, 'sill_height' => 24, 'casing_sides' => 4],
                    ['id' => 'obj4', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 48, 'height' => 60, 'offset' => 80, 'sill_height' => 24, 'casing_sides' => 4],
                    ['id' => 'obj5', 'type' => 'window', 'wall_id' => 'wall_p1_3_0', 'width' => 48, 'height' => 60, 'offset' => 60, 'sill_height' => 24, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.5,
                'finish' => ['ceiling_height_ft' => 8.5, 'flooring_waste_percent' => 10.0, 'flooring_box_sqft' => 24.0],
            ],
            '17_living_room' => [
                'description' => '17: 15x20 Living room with sliding patio door and 3 windows',
                'canvas' => $makeCanvas($templates->rectangle(15, 20), [
                    ['id' => 'obj1', 'type' => 'door', 'subtype' => 'sliding', 'wall_id' => 'wall_p1_1_2', 'width' => 72, 'height' => 80, 'offset' => 40, 'casing_sides' => 1, 'door_face' => 'exterior'],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_0_1', 'width' => 40, 'height' => 60, 'offset' => 20, 'sill_height' => 24, 'casing_sides' => 4],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_0_1', 'width' => 40, 'height' => 60, 'offset' => 80, 'sill_height' => 24, 'casing_sides' => 4],
                    ['id' => 'obj4', 'type' => 'window', 'wall_id' => 'wall_p1_3_0', 'width' => 48, 'height' => 60, 'offset' => 30, 'sill_height' => 24, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 9.0,
                'finish' => ['ceiling_height_ft' => 9.0],
            ],
            '18_kitchen_with_cabinets' => [
                'description' => '18: 12x16 Kitchen with base & upper cabinets, 1 door, 1 window, 1 opening',
                'canvas' => $makeCanvas($templates->rectangle(12, 16), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'opening', 'wall_id' => 'wall_p1_1_2', 'width' => 48, 'height' => 84, 'offset' => 24, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 36, 'height' => 36, 'offset' => 30, 'sill_height' => 42, 'casing_sides' => 4],
                    ['id' => 'cab1', 'type' => 'cabinet', 'subtype' => 'base', 'x' => 100, 'y' => 100, 'width' => 120, 'depth' => 24],
                    ['id' => 'cab2', 'type' => 'cabinet', 'subtype' => 'upper', 'x' => 100, 'y' => 100, 'width' => 96, 'depth' => 12],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '19_dining_room' => [
                'description' => '19: 14x14 Dining room with 2 wide cased openings',
                'canvas' => $makeCanvas($templates->rectangle(14, 14), [
                    ['id' => 'obj1', 'type' => 'opening', 'wall_id' => 'wall_p1_0_1', 'width' => 60, 'height' => 84, 'offset' => 20, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'opening', 'wall_id' => 'wall_p1_2_3', 'width' => 60, 'height' => 84, 'offset' => 20, 'casing_sides' => 2],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '20_hallway' => [
                'description' => '20: 4x24 Long hallway with 4 interior doors',
                'canvas' => $makeCanvas($templates->rectangle(4, 24), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_1_2', 'width' => 30, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'door', 'wall_id' => 'wall_p1_1_2', 'width' => 30, 'height' => 80, 'offset' => 70, 'casing_sides' => 2],
                    ['id' => 'obj3', 'type' => 'door', 'wall_id' => 'wall_p1_3_0', 'width' => 30, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj4', 'type' => 'door', 'wall_id' => 'wall_p1_3_0', 'width' => 30, 'height' => 80, 'offset' => 70, 'casing_sides' => 2],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '21_basement_with_stairs' => [
                'description' => '21: 24x30 Basement with U-shaped stairs, walkout door, and 3 windows',
                'canvas' => $makeCanvas($templates->rectangle(24, 30), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 36, 'casing_sides' => 1, 'door_face' => 'exterior'],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 24, 'offset' => 36, 'sill_height' => 54, 'casing_sides' => 4],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 24, 'offset' => 120, 'sill_height' => 54, 'casing_sides' => 4],
                    ['id' => 'obj4', 'type' => 'window', 'wall_id' => 'wall_p1_2_3', 'width' => 36, 'height' => 24, 'offset' => 60, 'sill_height' => 54, 'casing_sides' => 4],
                    ['id' => 'stair1', 'type' => 'stairs', 'subtype' => 'u', 'x' => 150, 'y' => 150, 'width' => 72, 'depth' => 96],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => ['flooring_waste_percent' => 8.0, 'flooring_box_sqft' => 20.0],
            ],
            '22_alcove_bedroom' => [
                'description' => '22: 8-sided polygon bedroom with closet alcove, bifold door, and 2 windows',
                'canvas' => $makeCanvas([
                    ['x' => 0, 'y' => 0],
                    ['x' => 14, 'y' => 0],
                    ['x' => 14, 'y' => 16],
                    ['x' => 6, 'y' => 16],
                    ['x' => 6, 'y' => 20],
                    ['x' => 0, 'y' => 20],
                    ['x' => 0, 'y' => 16],
                    ['x' => 0, 'y' => 6],
                ], [
                    ['id' => 'obj1', 'type' => 'door', 'subtype' => 'bifold', 'wall_id' => 'wall_p1_4_5', 'width' => 48, 'height' => 80, 'offset' => 6, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 48, 'offset' => 18, 'sill_height' => 36, 'casing_sides' => 4],
                    ['id' => 'obj3', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 36, 'height' => 48, 'offset' => 24, 'sill_height' => 36, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => [],
            ],
            '23_custom_9ft_walls' => [
                'description' => '23: 12x15 Rectangle with 9ft walls and ceiling',
                'canvas' => $makeCanvas($templates->rectangle(12, 15), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 84, 'offset' => 18, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 40, 'height' => 54, 'offset' => 24, 'sill_height' => 36, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 9.0,
                'finish' => ['ceiling_height_ft' => 9.0],
            ],
            '24_tray_ceiling' => [
                'description' => '24: 14x18 Rectangle with 10ft wall and 9ft tray ceiling',
                'canvas' => $makeCanvas($templates->rectangle(14, 18), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 84, 'offset' => 24, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 48, 'height' => 48, 'offset' => 30, 'sill_height' => 36, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 10.0,
                'finish' => ['ceiling_height_ft' => 9.0],
            ],
            '25_overlapping_adjacent_openings' => [
                'description' => '25: 14x16 Rectangle with 2 adjacent openings on Wall 1 to test union deduction',
                'canvas' => $makeCanvas($templates->rectangle(14, 16), [
                    ['id' => 'obj1', 'type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 36, 'height' => 80, 'offset' => 12, 'casing_sides' => 2],
                    ['id' => 'obj2', 'type' => 'window', 'wall_id' => 'wall_p1_0_1', 'width' => 48, 'height' => 48, 'offset' => 54, 'sill_height' => 36, 'casing_sides' => 4],
                ]),
                'scale' => $scale,
                'wallHeightFt' => 8.0,
                'finish' => ['wall_thickness_in' => 5.5],
            ],
        ];
    }

    public function test_basement_two_stub_partitions_replace_self_intersecting_stored_polygon(): void
    {
        $cmPerFt = 30.48;
        $plan = $this->basementStubRegressionPlan(0.0);
        $metrics = $this->bridge->safeLayoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $this->assertFalse($metrics['geometry_unmeasurable'] ?? false);
        $this->assertEquals(600.0, $metrics['area_sqft']);

        $walls = $plan['floor']['walls'];
        $loop = $this->bridge->extractOuterLoopAndPartitions($walls, 1.0);
        $this->assertCount(2, $loop['partition_walls']);
        $storedPoly = $plan['floor']['rooms'][0]['polygon'];
        $this->assertTrue($this->bridge->shouldReplaceRoomPolygonWithOuterLoop(
            $storedPoly,
            $loop,
            1.0,
            $plan['floor']['rooms'][0]['walls']
        ));
    }

    public function test_basement_stub_partitions_tolerate_endpoint_offset_from_perimeter(): void
    {
        $plan = $this->basementStubRegressionPlan(15.0);
        $metrics = $this->bridge->safeLayoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $this->assertFalse($metrics['geometry_unmeasurable'] ?? false);
        $this->assertEquals(600.0, $metrics['area_sqft']);

        $loop = $this->bridge->extractOuterLoopAndPartitions($plan['floor']['walls'], 1.0);
        $this->assertCount(2, $loop['partition_walls']);
    }

    public function test_partial_stored_polygon_expands_to_outer_loop_with_partition(): void
    {
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floors' => [[
                'id' => 'f1',
                'walls' => [
                    ['id' => 'wN', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 400, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
                    ['id' => 'wE', 'start' => ['x' => 400, 'y' => 0], 'end' => ['x' => 400, 'y' => 300], 'thickness' => 11.43, 'height' => 243.84],
                    ['id' => 'wS', 'start' => ['x' => 400, 'y' => 300], 'end' => ['x' => 0, 'y' => 300], 'thickness' => 11.43, 'height' => 243.84],
                    ['id' => 'wW', 'start' => ['x' => 0, 'y' => 300], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
                    ['id' => 'wP', 'start' => ['x' => 200, 'y' => 0], 'end' => ['x' => 200, 'y' => 300], 'thickness' => 11.43, 'height' => 243.84],
                ],
                'rooms' => [[
                    'id' => 'r1',
                    'walls' => ['wN', 'wE', 'wS', 'wW', 'wP'],
                    'polygon' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 200, 'y' => 0],
                        ['x' => 200, 'y' => 300],
                        ['x' => 0, 'y' => 300],
                    ],
                ]],
            ]],
            'activeFloorId' => 'f1',
        ];

        $metrics = $this->bridge->layoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $this->assertGreaterThan(80.0, $metrics['area_sqft']);
    }

    public function test_noisy_hand0_independent_endpoint_jitter_remains_measurable(): void
    {
        $path = base_path('tests/fixtures/openplan3d-hand0-drawn-plan.json');
        $base = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ([1.5, 2.5, 3.0] as $maxCm) {
            for ($trial = 0; $trial < 8; $trial++) {
                $plan = $this->jitterHand0PlanIndependent($base, $maxCm);
                $metrics = $this->bridge->safeLayoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
                $this->assertFalse(
                    $metrics['geometry_unmeasurable'] ?? false,
                    "Independent endpoint jitter {$maxCm} cm trial {$trial} must stay measurable"
                );
                $this->assertGreaterThan(
                    1300.0,
                    $metrics['area_sqft'],
                    "Area should match drawn basement (~1346 sq ft) at {$maxCm} cm noise"
                );
                $this->assertLessThan(1380.0, $metrics['area_sqft']);
            }
        }
    }

    public function test_extreme_hand0_jitter_is_geometry_unmeasurable_not_silent_zero(): void
    {
        $path = base_path('tests/fixtures/openplan3d-hand0-drawn-plan.json');
        $base = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        unset($base['floors']);
        $base['floor']['rooms'] = [];
        $plan = $this->jitterHand0PlanIndependent($base, 25.0);
        $metrics = $this->bridge->safeLayoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
        $this->assertTrue($metrics['geometry_unmeasurable'] ?? false);
        $this->assertEquals(0.0, $metrics['area_sqft']);
    }

    public function test_editor_drawn_hand0_plan_measures_floor_area_after_vertex_snap(): void
    {
        $path = base_path('tests/fixtures/openplan3d-hand0-drawn-plan.json');
        $this->assertFileExists($path);
        $plan = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $metrics = $this->bridge->safeLayoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
        $this->assertFalse($metrics['geometry_unmeasurable'] ?? false);
        $this->assertGreaterThan(1300.0, $metrics['area_sqft']);
        $this->assertLessThan(1360.0, $metrics['area_sqft']);
    }

    public function test_degenerate_zero_area_plan_returns_measurable_zero_for_save_guard(): void
    {
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 0, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                ],
                'rooms' => [
                    [
                        'id' => 'empty',
                        'name' => 'Untitled Project',
                        'walls' => ['w1'],
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 0, 'y' => 0],
                            ['x' => 0, 'y' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $metrics = $this->bridge->layoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
        $this->assertFalse($metrics['geometry_unmeasurable'] ?? false);
        $this->assertEquals(0.0, $metrics['area_sqft']);
    }

    public function test_true_bowtie_polygon_stays_geometry_unmeasurable(): void
    {
        $cmPerFt = 30.48;
        $plan = [
            'unit' => 'cm',
            'floor' => [
                'rooms' => [[
                    'id' => 'r1',
                    'polygon' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 10 * $cmPerFt, 'y' => 12 * $cmPerFt],
                        ['x' => 10 * $cmPerFt, 'y' => 0],
                        ['x' => 0, 'y' => 12 * $cmPerFt],
                    ],
                ]],
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 10 * $cmPerFt, 'y' => 0]],
                    ['id' => 'w2', 'start' => ['x' => 10 * $cmPerFt, 'y' => 0], 'end' => ['x' => 10 * $cmPerFt, 'y' => 12 * $cmPerFt]],
                    ['id' => 'w3', 'start' => ['x' => 10 * $cmPerFt, 'y' => 12 * $cmPerFt], 'end' => ['x' => 0, 'y' => 12 * $cmPerFt]],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => 12 * $cmPerFt], 'end' => ['x' => 0, 'y' => 0]],
                ],
            ],
        ];
        $metrics = $this->bridge->safeLayoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $this->assertTrue($metrics['geometry_unmeasurable'] ?? false);
        $this->assertEquals(0.0, $metrics['area_sqft']);
    }

    /**
     * @return array<string, mixed>
     */
    private function basementStubRegressionPlan(float $stubYOffsetCm): array
    {
        $cmPerFt = 30.48;

        return [
            'unit' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 30 * $cmPerFt, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cmPerFt],
                    ['id' => 'w2', 'start' => ['x' => 30 * $cmPerFt, 'y' => 0], 'end' => ['x' => 30 * $cmPerFt, 'y' => 20 * $cmPerFt], 'thickness' => 10, 'height' => 8 * $cmPerFt],
                    ['id' => 'w3', 'start' => ['x' => 30 * $cmPerFt, 'y' => 20 * $cmPerFt], 'end' => ['x' => 0, 'y' => 20 * $cmPerFt], 'thickness' => 10, 'height' => 8 * $cmPerFt],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => 20 * $cmPerFt], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cmPerFt],
                    ['id' => 'p1', 'start' => ['x' => 14 * $cmPerFt, 'y' => 20 * $cmPerFt - $stubYOffsetCm], 'end' => ['x' => 14 * $cmPerFt, 'y' => 8 * $cmPerFt], 'thickness' => 10, 'height' => 8 * $cmPerFt],
                    ['id' => 'p2', 'start' => ['x' => 22 * $cmPerFt, 'y' => 20 * $cmPerFt - $stubYOffsetCm], 'end' => ['x' => 22 * $cmPerFt, 'y' => 12 * $cmPerFt], 'thickness' => 10, 'height' => 8 * $cmPerFt],
                ],
                'rooms' => [
                    [
                        'id' => 'r1',
                        'name' => 'Basement',
                        'walls' => ['w1', 'w2', 'w3', 'w4', 'p1', 'p2'],
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 30 * $cmPerFt, 'y' => 0],
                            ['x' => 0, 'y' => 20 * $cmPerFt],
                            ['x' => 30 * $cmPerFt, 'y' => 20 * $cmPerFt],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $basePlan
     * @return array<string, mixed>
     */
    private function jitterHand0PlanIndependent(array $basePlan, float $maxOffsetCm): array
    {
        $plan = json_decode(json_encode($basePlan, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        foreach ($plan['floor']['walls'] as &$wall) {
            if (! is_array($wall['start'] ?? null) || ! is_array($wall['end'] ?? null)) {
                continue;
            }
            $wall['start']['x'] = (float) $wall['start']['x'] + (lcg_value() - 0.5) * 2 * $maxOffsetCm;
            $wall['start']['y'] = (float) $wall['start']['y'] + (lcg_value() - 0.5) * 2 * $maxOffsetCm;
            $wall['end']['x'] = (float) $wall['end']['x'] + (lcg_value() - 0.5) * 2 * $maxOffsetCm;
            $wall['end']['y'] = (float) $wall['end']['y'] + (lcg_value() - 0.5) * 2 * $maxOffsetCm;
        }
        unset($wall);

        return $plan;
    }
}

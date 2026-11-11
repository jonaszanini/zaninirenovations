<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\BasementQuotationCalculator;
use App\Services\BasementRoomAreaImportMapper;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class BasementRoomAreaImportTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_layout_search_fills_openings_and_baseboard_from_room_objects(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Import project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rec room',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 96],
                            ['x' => 0, 'y' => 96],
                        ],
                    ]],
                ]],
                'objects' => [
                    [
                        'id' => 'obj_1',
                        'type' => 'door',
                        'subtype' => 'single',
                        'wall_id' => 'wall_p1_0_1',
                        'width' => 36,
                        'offset' => 12,
                    ],
                    [
                        'id' => 'obj_2',
                        'type' => 'window',
                        'subtype' => 'fixed',
                        'wall_id' => 'wall_p1_1_2',
                        'width' => 48,
                        'offset' => 12,
                        'height' => 36,
                    ],
                    [
                        'id' => 'obj_3',
                        'type' => 'opening',
                        'subtype' => 'archway',
                        'wall_id' => 'wall_p1_2_3',
                        'width' => 36,
                        'offset' => 12,
                    ],
                ],
            ],
        ]);

        $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Rec']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'room:'.$room->id,
                'area_sqft' => 80,
                'perimeter_lf' => 36,
                'baseboard_lf' => 30,
                'door_count' => 1,
                'window_count' => 1,
                'opening_count' => 1,
                'drywall_openings' => 3,
            ]);
        $canvasOpt = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Rec']))
            ->json())->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($canvasOpt);
        $this->assertArrayNotHasKey('finish_drywall_sqft', $canvasOpt);
        $this->assertArrayNotHasKey('finish_stud_lf', $canvasOpt);
        $this->assertArrayNotHasKey('bulkhead_corner_bead_lf', $canvasOpt);
        $mapped = BasementRoomAreaImportMapper::proposedQuantities($canvasOpt, 8);
        $this->assertSame(1, $mapped['door_qty']);
        $this->assertSame(1, $mapped['window_trim_qty']);
        $this->assertSame(1, $mapped['drywall_openings']);
        $this->assertSame(30.0, $mapped['baseboard_lf']);
        $this->assertGreaterThan(0, (float) $mapped['area_sqft']);
        $this->assertGreaterThan(0, (float) $mapped['ceiling_sf']);
        $this->assertEqualsWithDelta((float) $mapped['area_sqft'], (float) $mapped['ceiling_sf'], 0.05);
        $this->assertTrue($canvasOpt['ceiling_area_available']);
        $this->assertGreaterThan(0, (float) $canvasOpt['ceiling_area_sqft']);
        $this->assertEqualsWithDelta((float) $canvasOpt['area_sqft'], (float) $canvasOpt['ceiling_area_sqft'], 0.05);
    }

    public function test_layout_search_exposes_bulkhead_and_partition_for_basement_import(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Bulkhead partition project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Living',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 600,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 30 * $cm, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'p1', 'start' => ['x' => 14 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 14 * $cm, 'y' => 8 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'p2', 'start' => ['x' => 22 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 22 * $cm, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Living',
                        'walls' => ['w1', 'w2', 'w3', 'w4', 'p1', 'p2'],
                    ]],
                ],
                'zanini' => [
                    'bulkheads' => [[
                        'rect' => [
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 10 * $cm,
                            'y2' => 1 * $cm,
                        ],
                        'drop_height_ft' => 2.0,
                        'against_wall' => true,
                    ]],
                ],
            ],
        ]);

        $payload = $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Living']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'room:'.$room->id,
                'partition_lf' => 19.67,
                'bulkhead_lf' => 27,
                'bulkhead_framing_lf' => 27,
                'bulkhead_drywall_sqft' => 30,
            ])
            ->json();
        $opt = collect($payload)->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($opt);
        $this->assertArrayHasKey('finish_drywall_sqft', $opt);
        $this->assertArrayHasKey('finish_stud_lf', $opt);
        $this->assertArrayHasKey('finish_baseboard_lf', $opt);
        $this->assertArrayHasKey('finish_insulation_sqft', $opt);
        $this->assertArrayHasKey('casing_lf', $opt);
        $this->assertArrayHasKey('ceiling_area_sqft', $opt);
        $this->assertTrue($opt['ceiling_area_available']);
        $this->assertGreaterThan(0, (float) $opt['ceiling_area_sqft']);
        $this->assertEqualsWithDelta((float) $opt['area_sqft'], (float) $opt['ceiling_area_sqft'], 0.05);
        $this->assertArrayNotHasKey('bulkhead_corner_bead_lf', $opt);
        $this->assertGreaterThan(0, (float) $opt['finish_drywall_sqft']);
        $this->assertSame(19.67, (float) $opt['finish_stud_lf']);
    }

    public function test_drywall_step_exposes_openings_and_baseboard_fields(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('id="br-dw-open"', false)
            ->assertSee('name="drywall_openings"', false)
            ->assertSee('id="br-base"', false)
            ->assertSee('name="baseboard_lf"', false)
            ->assertSee('name="drywall_door_openings"', false)
            ->assertSee('name="drywall_sheet_size"', false)
            ->assertSee('Opening area', false);
    }

    public function test_imported_odd_and_decimal_qty_draft_and_publish_without_html5_step_block(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'N1 baseboard project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rec 87',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 405,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 360, 'y' => 0],
                            ['x' => 360, 'y' => 162],
                            ['x' => 0, 'y' => 162],
                        ],
                    ]],
                ]],
            ],
        ]);

        $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Rec 87']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'room:'.$room->id,
                'baseboard_lf' => 87,
            ]);

        $createHtml = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-base")(?=[^>]*\bstep="0\.01")/',
            $createHtml
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input(?=[^>]*\bid="br-base")(?=[^>]*\bstep="10")/',
            $createHtml
        );
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-shoe")(?=[^>]*\bstep="0\.01")/',
            $createHtml
        );

        $enabled = array_merge(
            ['overview'],
            array_keys(BasementQuotationCalculator::tradeSections())
        );
        $payload = [
            'save_intent' => 'draft',
            'client_name' => 'N1 Import Client',
            'area_sqft' => 405,
            'ceiling_height_ft' => 8,
            'baseboard_lf' => 87,
            'include_shoe' => 1,
            'shoe_lf' => 12.5,
            'room_area_import_id' => 'room:'.$room->id,
            'room_area_import_label' => 'Rec 87',
            'enabled_sections' => $enabled,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];

        $this->post(route('admin.quotations.basement.store'), $payload);

        $quote = BasementQuotation::query()->where('client_name', 'N1 Import Client')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertEquals(87.0, (float) ($quote->inputs['baseboard_lf'] ?? 0));
        $this->assertEquals(12.5, (float) ($quote->inputs['shoe_lf'] ?? 0));
        $this->assertSame('room:'.$room->id, $quote->inputs['room_area_import_id'] ?? null);

        $payload['save_intent'] = 'publish';
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertEquals(87.0, (float) ($quote->inputs['baseboard_lf'] ?? 0));
        $this->assertEquals(12.5, (float) ($quote->inputs['shoe_lf'] ?? 0));

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-base")(?=[^>]*\bstep="0\.01")/',
            $editHtml
        );
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-base")(?=[^>]*\bvalue="87)/',
            $editHtml
        );
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-shoe")(?=[^>]*\bvalue="12\.5")/',
            $editHtml
        );
    }

    public function test_complete_layout_quantities_persist_without_overwriting_rates_or_finishes(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Full import project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rec complete',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 600,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 30 * $cm, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'p1', 'start' => ['x' => 14 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 14 * $cm, 'y' => 8 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Rec complete',
                        'walls' => ['w1', 'w2', 'w3', 'w4', 'p1'],
                    ]],
                    'doors' => [[
                        'id' => 'd1',
                        'wallId' => 'w1',
                        'type' => 'door',
                        'width' => 91.44,
                        'height' => 203.2,
                        'offset' => 100.0,
                    ]],
                    'windows' => [[
                        'id' => 'win1',
                        'wallId' => 'w2',
                        'type' => 'window',
                        'width' => 121.92,
                        'height' => 91.44,
                        'offset' => 100.0,
                    ]],
                ],
                'zanini' => [
                    'bulkheads' => [[
                        'rect' => [
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 10 * $cm,
                            'y2' => 1 * $cm,
                        ],
                        'drop_height_ft' => 2.0,
                        'against_wall' => true,
                    ]],
                    'wallFinishes' => [
                        'w1' => ['drywall' => true, 'paint' => true],
                        'w2' => ['drywall' => true, 'paint' => false],
                        'w3' => ['drywall' => true, 'paint' => true],
                        'w4' => ['drywall' => false, 'paint' => true],
                        'p1' => ['drywall' => true, 'paint' => false],
                    ],
                ],
            ],
        ]);

        $opt = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Rec complete']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($opt);
        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 8);
        $this->assertGreaterThan(0, (float) $proposed['area_sqft']);
        $this->assertGreaterThan(0, (float) $proposed['partition_lf']);
        $this->assertGreaterThan(0, (float) $proposed['bulkhead_lf']);
        $this->assertGreaterThan(0, (float) $proposed['drywall_wall_sf']);
        $this->assertSame(1, $proposed['door_qty']);
        $this->assertSame(1, $proposed['window_trim_qty']);

        $enabled = array_merge(
            ['overview'],
            array_keys(BasementQuotationCalculator::tradeSections())
        );
        $payload = array_merge([
            'save_intent' => 'draft',
            'client_name' => 'Full Import Client',
            'flooring_type' => 'lvp',
            'ceiling_type' => 'drywall',
            'finish_level' => 4,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'material_waste_percent' => 10,
            'room_area_import_id' => 'room:'.$room->id,
            'room_area_import_label' => 'Rec complete',
            'enabled_sections' => $enabled,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'insulate_walls' => 1,
        ], array_filter($proposed, fn ($v) => $v !== null));

        $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Full Import Client')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertEqualsWithDelta((float) $proposed['area_sqft'], (float) $quote->inputs['area_sqft'], 0.05);
        $this->assertEqualsWithDelta((float) $proposed['flooring_sf'], (float) $quote->inputs['flooring_sf'], 0.05);
        $this->assertEqualsWithDelta((float) $proposed['partition_lf'], (float) $quote->inputs['partition_lf'], 0.05);
        $this->assertEqualsWithDelta((float) $proposed['bulkhead_lf'], (float) $quote->inputs['bulkhead_lf'], 0.05);
        $this->assertEqualsWithDelta((float) $proposed['drywall_wall_sf'], (float) $quote->inputs['drywall_wall_sf'], 0.05);
        $this->assertNotNull($proposed['paint_wall_sf']);
        $this->assertEqualsWithDelta((float) $proposed['paint_wall_sf'], (float) $quote->inputs['paint_wall_sf'], 0.05);
        $this->assertNotEquals(
            (float) $proposed['drywall_wall_sf'],
            (float) $proposed['paint_wall_sf']
        );
        $bulkheadDw = (float) ($opt['bulkhead_drywall_sqft'] ?? 0);
        $finishDw = (float) ($opt['finish_drywall_sqft'] ?? 0);
        $this->assertEqualsWithDelta($finishDw + $bulkheadDw, (float) $proposed['drywall_wall_sf'], 0.05);
        $this->assertEqualsWithDelta((float) ($opt['finish_paint_sqft'] ?? 0), (float) $proposed['paint_wall_sf'], 0.05);
        $this->assertGreaterThan(0, (float) $proposed['ceiling_sf']);
        $this->assertEqualsWithDelta((float) $proposed['ceiling_sf'], (float) $quote->inputs['ceiling_sf'], 0.05);
        $this->assertEqualsWithDelta((float) $proposed['ceiling_sf'], (float) ($quote->calculation['scope']['drywall_ceiling_sf'] ?? 0), 0.05);
        $this->assertEqualsWithDelta((float) $proposed['baseboard_lf'], (float) $quote->inputs['baseboard_lf'], 0.05);
        $this->assertSame(1, (int) $quote->inputs['door_qty']);
        $this->assertSame(1, (int) $quote->inputs['window_trim_qty']);
        $this->assertSame(1, (int) $quote->inputs['framing_door_openings']);
        $this->assertSame('lvp', $quote->inputs['flooring_type']);
        $this->assertSame('drywall', $quote->inputs['ceiling_type']);
        $this->assertSame(80.0, (float) $quote->inputs['owner_hourly_rate']);
        $this->assertSame(10.0, (float) $quote->inputs['material_waste_percent']);
        $this->assertArrayHasKey('paint_walls_marked', $opt);
        $this->assertGreaterThan(0, (int) $opt['paint_walls_marked']);
        $this->assertGreaterThan(0, (int) $opt['paint_walls_explicit_off']);
        $this->assertSame(0, (int) ($opt['paint_walls_unassigned'] ?? 0));

        $laborKeys = collect($quote->calculation['labor'] ?? [])->pluck('key')->all();
        $this->assertContains('framing_walls', $laborKeys);
        $this->assertContains('fl_install', $laborKeys);
        $this->assertContains('trim_base', $laborKeys);

        $payload['save_intent'] = 'publish';
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertEqualsWithDelta((float) $proposed['flooring_sf'], (float) $quote->inputs['flooring_sf'], 0.05);
        $this->assertSame('lvp', $quote->inputs['flooring_type']);
        $this->assertSame(80.0, (float) $quote->inputs['owner_hourly_rate']);

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input(?=[^>]*\bid="br-partition")(?=[^>]*\bvalue=")/', $editHtml);
        $this->assertStringContainsString('name="flooring_type"', $editHtml);
        $this->assertMatchesRegularExpression('/name="flooring_type"[^>]*>[\s\S]*value="lvp"[^>]*selected/', $editHtml);
        $this->assertEqualsWithDelta((float) $proposed['paint_wall_sf'], (float) $quote->inputs['paint_wall_sf'], 0.05);
    }

    public function test_legacy_one_explicit_off_paint_is_mixed_not_unavailable(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Legacy paint project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Legacy paint mixed',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 600,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 30 * $cm, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Legacy paint mixed',
                        'walls' => ['w1', 'w2', 'w3', 'w4'],
                    ]],
                ],
                'zanini' => [
                    'wallFinishes' => [
                        'w1' => ['drywall' => true, 'paint' => false],
                        'w2' => ['drywall' => true],
                        'w3' => ['drywall' => true],
                        'w4' => ['drywall' => true],
                    ],
                ],
            ],
        ]);

        $opt = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Legacy paint mixed']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($opt);
        $this->assertArrayHasKey('finish_paint_sqft', $opt);
        $this->assertEqualsWithDelta(0.0, (float) $opt['finish_paint_sqft'], 0.05);
        $this->assertSame(1, (int) $opt['paint_walls_explicit_off']);
        $this->assertSame(3, (int) $opt['paint_walls_unassigned']);
        $this->assertTrue(BasementRoomAreaImportMapper::paintAssignmentMixed($opt));
        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 8);
        $this->assertSame(0.0, $proposed['paint_wall_sf']);
        $this->assertTrue(BasementRoomAreaImportMapper::needsImportConfirm(
            ['paint_wall_sf' => 400, 'import_label' => 'Manual', 'area_sqft' => 80],
            $proposed,
            $opt
        ));
    }

    public function test_paint_toggle_save_reload_import_persists_and_quote_pdf_matches(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Paint persist project',
            'scale_pixels_per_foot' => 12,
        ]);
        $planOn = $this->paintPlan('Paint persist room', $cm, [
            'w1' => ['drywall' => true, 'paint' => true],
            'w2' => ['drywall' => true, 'paint' => true],
            'w3' => ['drywall' => true, 'paint' => true],
            'w4' => ['drywall' => true, 'paint' => true],
        ]);

        $store = $this->postJson(route('admin.utilities.room-area.rooms.store', $project), [
            'name' => 'Paint persist room',
            'plan' => $planOn,
            'wall_height_ft' => 8.0,
        ]);
        $store->assertOk();
        $this->assertTrue((bool) $store->json('ok'));
        $roomId = (int) $store->json('room.id');
        $revision = (int) $store->json('room.revision');
        $this->assertGreaterThan(0, $roomId);

        $optOn = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Paint persist room']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$roomId);
        $this->assertIsArray($optOn);
        $paintOn = (float) ($optOn['finish_paint_sqft'] ?? 0);
        $this->assertGreaterThan(0, $paintOn);
        $this->assertGreaterThan(0, (int) ($optOn['paint_walls_marked'] ?? 0));
        $this->assertSame(0, (int) ($optOn['paint_walls_explicit_off'] ?? 1));

        $planPartial = $this->paintPlan('Paint persist room', $cm, [
            'w1' => ['drywall' => true, 'paint' => false],
            'w2' => ['drywall' => true, 'paint' => true],
            'w3' => ['drywall' => true, 'paint' => true],
            'w4' => ['drywall' => true, 'paint' => true],
        ]);
        $this->putJson(route('admin.utilities.room-area.rooms.update', $roomId), [
            'name' => 'Paint persist room',
            'plan' => $planPartial,
            'expected_revision' => $revision,
            'wall_height_ft' => 8.0,
        ])->assertOk()->assertJsonPath('ok', true);

        $optReload = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Paint persist room']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$roomId);
        $this->assertIsArray($optReload);
        $this->assertSame(1, (int) ($optReload['paint_walls_explicit_off'] ?? 0));
        $this->assertGreaterThan(0, (int) ($optReload['paint_walls_marked'] ?? 0));
        $paintReload = (float) ($optReload['finish_paint_sqft'] ?? 0);
        $this->assertGreaterThan(0, $paintReload);
        $this->assertLessThan($paintOn, $paintReload);

        $proposed = BasementRoomAreaImportMapper::proposedQuantities($optReload, 8);
        $this->assertNotNull($proposed['paint_wall_sf']);
        $this->assertEqualsWithDelta($paintReload, (float) $proposed['paint_wall_sf'], 0.05);

        $enabled = array_merge(
            ['overview'],
            array_keys(BasementQuotationCalculator::tradeSections())
        );
        $payload = array_merge([
            'save_intent' => 'draft',
            'client_name' => 'Paint Persist Client',
            'flooring_type' => 'lvp',
            'ceiling_type' => 'drywall',
            'finish_level' => 4,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'material_waste_percent' => 10,
            'room_area_import_id' => 'room:'.$roomId,
            'room_area_import_label' => 'Paint persist room',
            'enabled_sections' => $enabled,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'insulate_walls' => 1,
        ], array_filter($proposed, fn ($v) => $v !== null));

        $this->post(route('admin.quotations.basement.store'), $payload)->assertRedirect();
        $quote = BasementQuotation::query()->where('client_name', 'Paint Persist Client')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $payload['save_intent'] = 'publish';
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertEqualsWithDelta($paintReload, (float) ($quote->inputs['paint_wall_sf'] ?? 0), 0.05);
        $laborKeys = collect($quote->calculation['labor'] ?? [])->pluck('key')->all();
        $this->assertContains('paint_walls', $laborKeys);

        $clientProject = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $clientProject->id,
        ])->assertSessionHasNoErrors();
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);
        $this->assertEqualsWithDelta((float) $quote->final_quote_total, $cpq->pdfGrandTotal(), 0.02);
        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$clientProject, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/paint-persist-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $pdfText = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString(ClientProject::formatMoney((float) $quote->final_quote_total), $pdfText);
        $this->assertStringContainsString('Paint walls', $pdfText);
    }

    public function test_legacy_unassigned_layout_fills_paint_from_drywall_and_persists_on_save(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Legacy fill paint',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Legacy fill paint room',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 20 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 20 * $cm, 'y' => 0], 'end' => ['x' => 20 * $cm, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 20 * $cm, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Legacy fill paint room',
                        'walls' => ['w1', 'w2', 'w3', 'w4'],
                    ]],
                ],
                'zanini' => [
                    'wallFinishes' => [
                        'w1' => ['drywall' => true],
                        'w2' => ['drywall' => true],
                        'w3' => ['drywall' => true],
                        'w4' => ['drywall' => true],
                    ],
                ],
            ],
        ]);

        $opt = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Legacy fill paint room']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($opt);
        $this->assertArrayNotHasKey('finish_paint_sqft', $opt);

        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 8);
        $this->assertNotNull($proposed['paint_wall_sf']);
        $this->assertEqualsWithDelta((float) $proposed['drywall_wall_sf'], (float) $proposed['paint_wall_sf'], 0.05);
        $this->assertNotNull($proposed['paint_ceiling_sf']);
        $this->assertGreaterThan(0, (float) $proposed['paint_ceiling_sf']);

        $enabled = array_merge(['overview'], array_keys(BasementQuotationCalculator::tradeSections()));
        $payload = array_merge([
            'save_intent' => 'draft',
            'client_name' => 'Legacy Paint Fill Client',
            'flooring_type' => 'lvp',
            'ceiling_type' => 'drywall',
            'finish_level' => 4,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'material_waste_percent' => 10,
            'room_area_import_id' => 'room:'.$room->id,
            'room_area_import_label' => 'Legacy fill',
            'enabled_sections' => $enabled,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ], array_filter($proposed, fn ($v) => $v !== null));

        $this->post(route('admin.quotations.basement.store'), $payload)->assertRedirect();
        $quote = BasementQuotation::query()->where('client_name', 'Legacy Paint Fill Client')->first();
        $this->assertNotNull($quote);
        $this->assertEqualsWithDelta((float) $proposed['paint_wall_sf'], (float) ($quote->inputs['paint_wall_sf'] ?? 0), 0.05);
        $this->assertEqualsWithDelta((float) $proposed['paint_ceiling_sf'], (float) ($quote->inputs['paint_ceiling_sf'] ?? 0), 0.05);

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/\bid="br-p-wall"/', $editHtml);
        $this->assertEqualsWithDelta((float) $proposed['paint_wall_sf'], (float) ($quote->inputs['paint_wall_sf'] ?? 0), 0.05);
    }

    /**
     * @param  array<string, array{drywall?: bool, paint?: bool}>  $finishes
     * @return array<string, mixed>
     */
    private function paintPlan(string $name, float $cm, array $finishes): array
    {
        return [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 20 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                    ['id' => 'w2', 'start' => ['x' => 20 * $cm, 'y' => 0], 'end' => ['x' => 20 * $cm, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                    ['id' => 'w3', 'start' => ['x' => 20 * $cm, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                ],
                'rooms' => [[
                    'id' => 'r1',
                    'name' => $name,
                    'walls' => ['w1', 'w2', 'w3', 'w4'],
                ]],
            ],
            'zanini' => [
                'wallFinishes' => $finishes,
            ],
        ];
    }
}

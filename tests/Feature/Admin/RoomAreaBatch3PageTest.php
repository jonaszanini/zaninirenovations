<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\RoomAreaAssembly;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaAssemblyDefaults;
use App\Services\RoomAreaAssemblyEvaluator;
use App\Services\RoomAreaFormulaParser;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaBatch3PageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_kits_tab_exposes_editor_hooks_and_help_tip(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-ra-side-tab="kits"', false)
            ->assertSee('data-ra-kit-list', false)
            ->assertSee('data-ra-kit-new', false)
            ->assertSee('data-ra-kit-quote', false)
            ->assertSee('Add kits to quote')
            ->assertSee('data-ra-kit-name', false)
            ->assertSee('ceil(net_wall_sf * 1.1 / 32)', false)
            ->getContent();

        $this->assertStringContainsString('data-ra-help-pop="kits"', $html);
        $this->assertStringContainsString('util-ra--fullpage', $html);
        $this->assertStringContainsString('id="ra-catalog-options"', $html);
        $this->assertStringContainsString('Drywall wall', $html);
        $this->assertStringContainsString('LVP floor', $html);
        $this->assertStringContainsString('Baseboard', $html);
        $this->assertSame(3, RoomAreaAssembly::query()->count());
    }

    public function test_reload_saved_room_canvas_refit_and_resize_stage_are_required(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertNotFalse($js);

        preg_match('/function reloadSavedRoomCanvas\(room\) \{([\s\S]*?)\n  function /', $js, $reload);
        $this->assertArrayHasKey(1, $reload, 'reloadSavedRoomCanvas must be present');
        $this->assertStringContainsString('requestAnimationFrame(refitCanvasToLayout)', $reload[1]);

        preg_match('/function refitCanvasToLayout\(\) \{([\s\S]*?)\n  function /', $js, $refit);
        $this->assertArrayHasKey(1, $refit, 'refitCanvasToLayout must be present');
        $this->assertStringContainsString('resizeStage()', $refit[1]);
        $this->assertStringContainsString('fitViewport()', $refit[1]);
    }

    public function test_assets_wire_formula_parser_and_kit_styles(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertStringContainsString('function renderKits(', $js);
        $this->assertStringContainsString('RA.evaluateAssemblyLine(', $js);
        $this->assertStringContainsString('function bindKits(', $js);
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(/', $js);

        $lib = file_get_contents(public_path('js/room-area-lib.js'));
        $this->assertStringContainsString('function evaluateFormula(', $lib);
        $this->assertStringContainsString('function applyFormulaRounding(', $lib);
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(/', $lib);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertStringContainsString('.util-ra-kits', $css);
        $this->assertStringContainsString('.util-ra-kit__line', $css);

        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertStringContainsString('html.admin-dark .util-ra-kits', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-kit', $dark);
    }

    public function test_can_create_attach_and_evaluate_a_kit(): void
    {
        $this->actingAsAdmin();
        app(RoomAreaAssemblyDefaults::class)->ensure();

        $material = Material::create([
            'name' => '4x8 drywall',
            'price_cad' => 14.50,
            'unit' => 'sheet',
            'is_active' => true,
        ]);

        $create = $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Paint walls',
            'notes' => 'Gallons from net wall.',
            'lines' => [[
                'name' => 'Paint',
                'formula' => 'ceil(net_wall_sf / 350)',
                'unit' => 'gal',
                'whole_units' => true,
                'material_id' => $material->id,
            ]],
        ])->assertCreated()->assertJsonPath('ok', true);

        $assemblyId = (int) $create->json('assembly.id');
        $this->assertGreaterThan(0, $assemblyId);

        $room = $this->makeRoom();
        $this->postJson(route('admin.utilities.room-area.rooms.assemblies.sync', $room), [
            'assembly_ids' => [$assemblyId],
        ])->assertOk()->assertJsonPath('assembly_ids.0', $assemblyId);

        $eval = $this->postJson(route('admin.utilities.room-area.assemblies.evaluate'), [
            'layout' => 'room:'.$room->id,
            'assembly_ids' => [$assemblyId],
        ])->assertOk()->assertJsonPath('ok', true);

        $line = $eval->json('assemblies.0.lines.0');
        $this->assertSame('Paint', $line['name']);
        $this->assertNull($line['error']);
        $metrics = $room->layoutMetrics();
        $parser = new RoomAreaFormulaParser;
        $expected = $parser->applyRounding(
            $parser->evaluate('ceil(net_wall_sf / 350)', RoomAreaFormulaParser::variablesFromMetrics($metrics))['value'],
            true
        );
        $this->assertEquals($expected, $line['qty']);
        $this->assertSame($material->id, $line['material_id']);
    }

    public function test_rejects_unsafe_or_invalid_formulas(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Bad kit',
            'lines' => [[
                'name' => 'Hack',
                'formula' => '1e3',
                'unit' => 'ea',
                'whole_units' => true,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.formula']);

        $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Bad kit',
            'lines' => [[
                'name' => 'Hack',
                'formula' => 'process.exit(1)',
                'unit' => 'ea',
            ]],
        ])->assertStatus(422);
    }

    public function test_default_kits_match_evaluator_examples(): void
    {
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $room = $this->makeRoom();
        $metrics = $room->layoutMetrics();
        $evaluator = app(RoomAreaAssemblyEvaluator::class);
        $byName = [];
        foreach (RoomAreaAssembly::query()->with('lines')->get() as $assembly) {
            $byName[$assembly->name] = $evaluator->evaluateAssembly($assembly, $metrics);
        }

        $this->assertArrayHasKey('Drywall wall', $byName);
        $this->assertArrayHasKey('LVP floor', $byName);
        $this->assertArrayHasKey('Baseboard', $byName);
        $sheets = collect($byName['Drywall wall']['lines'])->firstWhere('name', 'Drywall sheets');
        $this->assertNotNull($sheets);
        $this->assertGreaterThan(0, $sheets['qty']);
        $this->assertNull($sheets['error']);
        $boxes = collect($byName['LVP floor']['lines'])->firstWhere('name', 'Floor boxes');
        $this->assertEquals($metrics['flooring_boxes'], $boxes['qty']);
    }

    private function makeRoom(): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Batch 3 kits',
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Kitchen',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 120,
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
                            ['x' => 144, 'y' => 0],
                            ['x' => 144, 'y' => 120],
                            ['x' => 0, 'y' => 120],
                        ],
                    ]],
                ]],
                'objects' => [[
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 12,
                ]],
            ],
        ]);
    }
}

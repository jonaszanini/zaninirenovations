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
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Add kits to quote')
            ->assertSee('data-ra-kit-name', false)
            ->assertSee('maxlength="120"', false)
            ->assertSee('maxlength="200"', false)
            ->assertSee('ceil(net_wall_sf * 1.12 / 32)', false)
            ->getContent();

        $this->assertStringContainsString('data-ra-help-pop="kits"', $html);
        $this->assertStringNotContainsString('data-ra-toolbar-overflow', $html);
        $this->assertStringContainsString('util-ra--fullpage', $html);
        $this->assertStringContainsString('id="ra-catalog-options"', $html);
        $this->assertStringContainsString('maxlength="120"', $html);
        $this->assertStringContainsString('maxlength="200"', $html);
        $this->assertStringContainsString('maxlength="20"', $html);
        $this->assertStringContainsString('Drywall wall', $html);
        $this->assertStringContainsString('LVP floor', $html);
        $this->assertStringContainsString('Baseboard', $html);
        $this->assertSame(3, RoomAreaAssembly::query()->count());
    }

    public function test_deleted_default_kits_are_not_recreated_on_get(): void
    {
        $this->actingAsAdmin();
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $this->assertSame(3, RoomAreaAssembly::query()->count());

        RoomAreaAssembly::query()->delete();
        $this->assertSame(0, RoomAreaAssembly::query()->count());

        $this->get(route('admin.utilities.room-area'))->assertOk();
        $this->assertSame(0, RoomAreaAssembly::query()->count());
    }

    public function test_unicode_formula_saves_without_server_error(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Unicode kit',
            'lines' => [[
                'name' => 'Sheets',
                'formula' => "ceil(net_wall_sf\u{00A0}×\u{00A0}1.12\u{00A0}÷\u{00A0}32)",
                'unit' => 'sheet',
                'whole_units' => true,
            ]],
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Invisible space kit',
            'lines' => [[
                'name' => 'Sheets',
                'formula' => "2\u{200B}+\u{0085}3\u{180E}*1",
                'unit' => 'sheet',
                'whole_units' => true,
            ]],
        ])->assertCreated()->assertJsonPath('ok', true);
    }

    public function test_emoji_formula_returns_422(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Emoji kit',
            'lines' => [[
                'name' => 'Sheets',
                'formula' => 'floor_sf + 😀',
                'unit' => 'sheet',
                'whole_units' => true,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.formula']);
    }

    public function test_three_200_sf_rooms_need_two_tape_rolls(): void
    {
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $assembly = RoomAreaAssembly::query()->where('name', 'Drywall wall')->firstOrFail();
        $project = RoomAreaProject::create([
            'name' => 'Three 200 sf walls',
            'scale_pixels_per_foot' => 12,
        ]);
        $vertices = [
            'v' => 3,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 120, 'y' => 0],
                        ['x' => 120, 'y' => 30],
                        ['x' => 0, 'y' => 30],
                    ],
                ]],
            ]],
            'objects' => [],
        ];
        foreach (['A', 'B', 'C'] as $name) {
            $room = RoomAreaRoom::create([
                'room_area_project_id' => $project->id,
                'name' => $name,
                'scale_pixels_per_foot' => 12,
                'area_sqft' => 25,
                'wall_height_ft' => 8,
                'vertices' => $vertices,
            ]);
            $room->assemblies()->sync([$assembly->id]);
            $this->assertEquals(200.0, $room->layoutMetrics()['net_wall_area_sqft']);
        }

        $project->refresh();
        $totals = app(RoomAreaAssemblyEvaluator::class)->evaluateProject($project);
        $tape = collect($totals[0]['lines'])->firstWhere('name', 'Tape (500 sf)');
        $this->assertNotNull($tape);
        $this->assertEquals(2.0, $tape['qty']);
    }

    public function test_project_totals_sum_raw_then_round_once(): void
    {
        $assembly = RoomAreaAssembly::create([
            'name' => 'Paint',
            'notes' => null,
            'sort_order' => 9,
        ]);
        $assembly->lines()->create([
            'name' => 'Gallons',
            'formula' => '1.4',
            'unit' => 'gal',
            'whole_units' => true,
            'sort_order' => 0,
        ]);

        $project = RoomAreaProject::create([
            'name' => 'Two rooms',
            'scale_pixels_per_foot' => 12,
        ]);
        $makeRoom = function (string $name) use ($project, $assembly) {
            $room = RoomAreaRoom::create([
                'room_area_project_id' => $project->id,
                'name' => $name,
                'scale_pixels_per_foot' => 12,
                'area_sqft' => 100,
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
                                ['x' => 120, 'y' => 120],
                                ['x' => 0, 'y' => 120],
                            ],
                        ]],
                    ]],
                    'objects' => [],
                ],
            ]);
            $room->assemblies()->sync([$assembly->id]);

            return $room;
        };
        // 1.4 + 1.4 = 2.8 → ceil once = 3, not 2+2 = 4.
        $makeRoom('A');
        $makeRoom('B');

        $project->refresh();
        $totals = app(RoomAreaAssemblyEvaluator::class)->evaluateProject($project);
        $line = $totals[0]['lines'][0];
        $this->assertEquals(3.0, $line['qty']);
    }

    public function test_assets_wire_formula_parser_and_kit_styles(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertStringContainsString('function renderKits(', $js);
        $this->assertStringContainsString('RA.evaluateAssemblyLine(', $js);
        $this->assertStringContainsString('function bindKits(', $js);
        $this->assertStringContainsString('typeof ResizeObserver', $js);
        $this->assertMatchesRegularExpression('/ResizeObserver\(function \(\) \{\s*resizeStage\(\);/', $js);
        $this->assertStringNotContainsString('isFullPage() && typeof ResizeObserver', $js);
        $this->assertStringContainsString('userAdjustedViewport', $js);
        $this->assertStringNotContainsString('if (state.userAdjustedViewport) return;', $js);
        $this->assertStringContainsString("if (/s$/i.test(unit)) return unit;", $js);
        $this->assertStringContainsString("if (/\\bpails\\b/i.test(unit)", $js);
        $this->assertStringContainsString("addEventListener('pageshow'", $js);
        $this->assertStringContainsString('var shiftPan', $js);
        $this->assertStringContainsString('e.shiftKey', $js);
        $this->assertStringContainsString('Click Save to keep changes', $js);
        $this->assertStringNotContainsString('function syncToolbarOverflow(', $js);
        $this->assertStringNotContainsString('data-ra-toolbar-overflow', $js);
        $this->assertStringContainsString('visibleWrapSize', $js);
        $inserter = file_get_contents(app_path('Services/RoomAreaQuoteLineInserter.php'));
        $this->assertStringContainsString('usesCurrentPdfFold()', $inserter);
        $calc = file_get_contents(app_path('Services/StandardQuotationCalculator.php'));
        $this->assertStringContainsString('$assignSurchargeRemainderToLabourJobs', $calc);
        $this->assertStringNotContainsString('$useCurrentPdfFold', $calc);
        $this->assertMatchesRegularExpression(
            '/function deleteCurrentKit\([\s\S]*payload\.ok === false[\s\S]*Kit deleted/',
            $js
        );
        $this->assertMatchesRegularExpression(
            '/function deleteCurrentKit\([\s\S]*friendlyNetworkError\([\s\S]*Could not delete that kit/',
            $js
        );
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(/', $js);

        $lib = file_get_contents(public_path('js/room-area-lib.js'));
        $this->assertStringContainsString('function evaluateFormula(', $lib);
        $this->assertStringContainsString('function applyFormulaRounding(', $lib);
        $this->assertStringContainsString('var insideArea', $lib);
        $this->assertStringContainsString('var insideCorners', $lib);
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(/', $lib);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertStringContainsString('.util-ra-kits', $css);
        $this->assertStringContainsString('.util-ra-kit__line', $css);
        $this->assertStringContainsString('.util-ra--fullpage .util-ra-feedback.is-error { color: var(--admin-danger); }', $css);
        $this->assertStringContainsString('position: absolute', $css);
        $this->assertStringContainsString('min-height: 240px', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,900}?max-height:\s*calc\(\(var\(--admin-control-height\) \* 2\)/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,400}?overflow-x:\s*visible/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,1200}?\.util-ra-help \{\s*display:\s*none/',
            $css
        );
        $this->assertStringContainsString('max-height: min(40vh, 280px)', $css);
        $this->assertStringContainsString('min-height: 12.5rem', $css);
        $this->assertStringContainsString('padding-top: 2.4em', $css);

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

    public function test_duplicate_kit_name_returns_422_on_create_and_rename(): void
    {
        $this->actingAsAdmin();
        $line = [
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'ea',
            'whole_units' => true,
        ];
        $first = $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Shared name kit',
            'lines' => [$line],
        ])->assertCreated();

        $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Shared name kit',
            'lines' => [$line],
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        $secondId = (int) $this->postJson(route('admin.utilities.room-area.assemblies.store'), [
            'name' => 'Other kit',
            'lines' => [$line],
        ])->assertCreated()->json('assembly.id');

        $this->putJson(route('admin.utilities.room-area.assemblies.update', $secondId), [
            'name' => 'Shared name kit',
            'lines' => [$line],
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        $this->putJson(route('admin.utilities.room-area.assemblies.update', (int) $first->json('assembly.id')), [
            'name' => 'Shared name kit',
            'lines' => [$line],
        ])->assertOk();
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
        $bead = collect($byName['Drywall wall']['lines'])->firstWhere('name', 'Corner bead');
        $this->assertSame('outside_corners * ceil(wall_height / 8)', $bead['formula']);
    }

    public function test_corner_bead_at_nine_feet_is_four_sticks_for_two_corners(): void
    {
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $assembly = RoomAreaAssembly::query()->where('name', 'Drywall wall')->firstOrFail();
        $evaluated = app(RoomAreaAssemblyEvaluator::class)->evaluateAssembly($assembly, [
            'area_sqft' => 100,
            'net_wall_area_sqft' => 200,
            'wall_height_ft' => 9,
            'outside_corners' => 2,
            'inside_corners' => 2,
            'flooring_boxes' => 6,
            'flooring_waste_percent' => 10,
        ]);
        $bead = collect($evaluated['lines'])->firstWhere('name', 'Corner bead');
        $this->assertEquals(4.0, $bead['qty']);
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

<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\UtilityCalculation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class LumberBoardCalculationUpdateTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_update_without_name_keeps_the_saved_name_and_updates_items(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create([
            'client_name' => 'Scott',
            'name' => 'Deck',
        ]);
        $calculation = UtilityCalculation::query()->create([
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'name' => 'Scott deck — all lumber',
            'client_project_id' => $project->id,
            'payload' => [
                'version' => 2,
                'board_types' => [[
                    'name' => '2x6',
                    'pieces' => [
                        ['desc' => 'Ledger', 'lengthVal' => '48', 'qty' => 2],
                    ],
                ]],
            ],
        ]);

        $withAddedItem = [
            'version' => 2,
            'board_types' => [[
                'name' => '2x6',
                'pieces' => [
                    ['desc' => 'Ledger', 'lengthVal' => '48', 'qty' => 2],
                    ['desc' => 'Rim joist', 'lengthVal' => '96', 'qty' => 1],
                ],
            ]],
        ];

        $this->putJson(route('admin.utilities.calculations.update', $calculation), [
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'client_project_id' => $project->id,
            'payload' => $withAddedItem,
        ])->assertOk()
            ->assertJsonPath('calculation.name', 'Scott deck — all lumber')
            ->assertJsonPath('calculation.payload.board_types.0.pieces.1.desc', 'Rim joist');

        $calculation->refresh();
        $this->assertSame('Scott deck — all lumber', $calculation->name);
        $this->assertSame($project->id, $calculation->client_project_id);
        $this->assertSame('Rim joist', $calculation->payload['board_types'][0]['pieces'][1]['desc']);
        $this->assertSame(1, $calculation->payload['board_types'][0]['pieces'][1]['qty']);

        $withBlankName = $withAddedItem;
        $withBlankName['board_types'][0]['pieces'][1]['qty'] = 4;

        $this->putJson(route('admin.utilities.calculations.update', $calculation), [
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'name' => '   ',
            'client_project_id' => $project->id,
            'payload' => $withBlankName,
        ])->assertOk()
            ->assertJsonPath('calculation.name', 'Scott deck — all lumber')
            ->assertJsonPath('calculation.payload.board_types.0.pieces.1.qty', 4);

        $calculation->refresh();
        $this->assertSame('Scott deck — all lumber', $calculation->name);
        $this->assertSame(4, $calculation->payload['board_types'][0]['pieces'][1]['qty']);
    }

    public function test_store_still_requires_a_name_and_update_can_rename(): void
    {
        $this->actingAsAdmin();
        $calculation = UtilityCalculation::query()->create([
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'name' => 'Original name',
            'payload' => ['version' => 2, 'board_types' => []],
        ]);

        $this->postJson(route('admin.utilities.calculations.store'), [
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'payload' => ['version' => 2, 'board_types' => []],
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        $this->assertSame(1, UtilityCalculation::query()->count());

        $this->putJson(route('admin.utilities.calculations.update', $calculation), [
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'name' => 'Renamed deck',
            'payload' => [
                'version' => 2,
                'board_types' => [[
                    'name' => '2x4',
                    'pieces' => [
                        ['desc' => 'Blocking', 'lengthVal' => '14', 'qty' => 6],
                    ],
                ]],
            ],
        ])->assertOk()->assertJsonPath('calculation.name', 'Renamed deck');

        $calculation->refresh();
        $this->assertSame('Renamed deck', $calculation->name);
        $this->assertSame('Blocking', $calculation->payload['board_types'][0]['pieces'][0]['desc']);
    }

    public function test_loaded_calculation_page_prefills_name_and_linked_project(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create([
            'client_name' => 'Scott',
            'name' => 'Deck',
        ]);
        $calculation = UtilityCalculation::query()->create([
            'tool' => UtilityCalculation::TOOL_LUMBER_BOARD,
            'name' => 'Scott deck — all lumber',
            'client_project_id' => $project->id,
            'payload' => [
                'version' => 2,
                'kerf' => true,
                'board_types' => [[
                    'id' => 'bt1',
                    'name' => '2x6 Joists',
                    'stock_unit' => 'ft',
                    'piece_unit' => 'in',
                    'stocks' => [['id' => 's1', 'lengthVal' => '8']],
                    'pieces' => [['desc' => 'Ledger', 'lengthVal' => '48', 'qty' => 2]],
                ]],
            ],
        ]);

        $html = $this->get(route('admin.utilities.lumber-board', [
            'project' => $project->id,
            'calculation' => $calculation->id,
        ]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="lb-save-name"[^>]*value="Scott deck — all lumber"/',
            $html
        );
        $this->assertStringContainsString('Update this calculation', $html);
        $this->assertStringContainsString('value="'.$project->id.'" selected', $html);
        $this->assertStringContainsString('Scott - Deck', $html);
        $this->assertStringContainsString('Scott deck — all lumber', $html);
    }
}

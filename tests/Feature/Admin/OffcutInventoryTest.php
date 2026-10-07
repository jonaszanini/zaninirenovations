<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\Material;
use App\Models\Offcut;
use App\Services\CutOptimizer\CutOptimizerEngine;
use App\Services\CutOptimizer\GrainDirection;
use App\Services\CutOptimizer\Part;
use App\Services\CutOptimizer\StockOption;
use App\Services\OffcutInventoryService;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class OffcutInventoryTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_is_redirected_from_offcut_index(): void
    {
        $this->get(route('admin.utilities.offcuts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_post_offcuts(): void
    {
        $this->post(route('admin.utilities.offcuts.store'), [
            'material_key' => '2x4',
            'length_in' => 24,
            'qty' => 1,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Offcut::query()->count());
    }

    public function test_admin_can_create_list_edit_mark_used_and_delete_offcut(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();

        $this->post(route('admin.utilities.offcuts.store'), [
            'material_key' => '2x4',
            'thickness_in' => 1.5,
            'length_in' => 36,
            'qty' => 2,
            'location' => 'Shop rack',
            'notes' => 'From last deck',
            'created_from_project_id' => $project->id,
        ])->assertRedirect(route('admin.utilities.offcuts.index'));

        $offcut = Offcut::query()->first();
        $this->assertNotNull($offcut);
        $this->assertSame('2x4', $offcut->material_key);
        $this->assertSame(2, (int) $offcut->qty);
        $this->assertTrue($offcut->isAvailable());

        $this->get(route('admin.utilities.offcuts.index'))
            ->assertOk()
            ->assertSee('2x4', false)
            ->assertSee('36 in', false)
            ->assertSee('Shop rack', false)
            ->assertSee('admin-btn', false)
            ->assertDontSee('data-cp-money-entry="direct"', false);

        $this->get(route('admin.utilities.offcuts.edit', $offcut))
            ->assertOk()
            ->assertSee('Edit offcut', false);

        $this->put(route('admin.utilities.offcuts.update', $offcut), [
            'material_key' => '2x6',
            'thickness_in' => 1.5,
            'length_in' => 48,
            'qty' => 3,
            'location' => 'Trailer',
            'notes' => 'Updated',
        ])->assertRedirect(route('admin.utilities.offcuts.index'));

        $offcut->refresh();
        $this->assertSame('2x6', $offcut->material_key);
        $this->assertEquals(48.0, (float) $offcut->length_in);

        $this->post(route('admin.utilities.offcuts.mark-used', $offcut), [
            'used_by_project_id' => $project->id,
        ])->assertRedirect(route('admin.utilities.offcuts.index'));

        $offcut->refresh();
        $this->assertFalse($offcut->isAvailable());
        $this->assertSame($project->id, (int) $offcut->used_by_project_id);

        $this->delete(route('admin.utilities.offcuts.destroy', $offcut))
            ->assertRedirect(route('admin.utilities.offcuts.index'));
        $this->assertSame(0, Offcut::query()->count());
    }

    public function test_available_json_bridges_sheet_offcuts_into_linear_view(): void
    {
        $this->actingAsAdmin();
        Offcut::query()->create([
            'material_key' => 'plywood',
            'thickness_in' => 0.75,
            'width_in' => 24,
            'length_in' => 48,
            'qty' => 1,
        ]);

        $response = $this->getJson(route('admin.utilities.offcuts.available'));
        $response->assertOk();
        $linear = $response->json('linear');
        $sheets = $response->json('sheets');
        $this->assertNotEmpty($sheets);
        $this->assertNotEmpty($linear);
        $bridged = collect($linear)->firstWhere('bridged', true);
        $this->assertNotNull($bridged);
        $this->assertEquals(48.0, (float) $bridged['length_in']);
        $this->assertEquals(24.0, (float) $bridged['usable_width_in']);
    }

    public function test_save_leftovers_respects_min_offcut_size(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.utilities.offcuts.save-leftovers'), [
            'min_offcut_in' => 12,
            'leftovers' => [
                ['material_key' => '2x4', 'length_in' => 8, 'qty' => 1],
                ['material_key' => '2x4', 'length_in' => 18, 'qty' => 1],
                ['material_key' => 'plywood', 'width_in' => 24, 'length_in' => 36, 'qty' => 1],
            ],
        ])->assertOk()->assertJsonPath('saved', 2);

        $this->assertSame(2, Offcut::query()->count());
        $this->assertTrue(Offcut::query()->where('length_in', 18)->exists());
        $this->assertTrue(Offcut::query()->where('width_in', 24)->exists());
        $this->assertFalse(Offcut::query()->where('length_in', 8)->exists());
    }

    public function test_optimizer_consumes_matching_offcuts_first(): void
    {
        $this->actingAsAdmin();
        $offcut = Offcut::query()->create([
            'material_key' => 'plywood',
            'thickness_in' => 0.75,
            'width_in' => 24,
            'length_in' => 48,
            'qty' => 1,
        ]);

        $engine = new CutOptimizerEngine();
        $inventory = new OffcutInventoryService();
        $result = $engine->optimizeWithOffcuts(
            [new Part('a', 'Small', 10, 12, 1, GrainDirection::ANY)],
            [new StockOption('full', '4×8', 48, 96, 72)],
            $inventory->asSheetStock([$offcut]),
            0.125,
            0,
            2
        );

        $this->assertSame([$offcut->id], $result['used_offcut_ids']);
        $this->assertCount(1, $result['sheets']);
        $this->assertSame($offcut->id, $result['sheets'][0]->offcutId);
        $this->assertEqualsWithDelta(24.0, $result['sheets'][0]->width, 0.01);
        $this->assertEqualsWithDelta(48.0, $result['sheets'][0]->length, 0.01);

        $this->postJson(route('admin.utilities.lumber-board.optimize-sheets'), [
            'parts' => [
                ['label' => 'Small', 'width' => 10, 'length' => 12, 'qty' => 1],
            ],
            'stock' => [
                ['label' => '4×8', 'width' => 48, 'length' => 96, 'price' => 72],
            ],
            'material_key' => 'plywood',
            'use_offcuts' => true,
            'min_offcut_in' => 2,
        ])->assertOk()
            ->assertJsonPath('used_offcut_ids.0', $offcut->id)
            ->assertJsonPath('estimated_cost', 0);

        $this->postJson(route('admin.utilities.offcuts.consume'), [
            'ids' => [$offcut->id],
        ])->assertOk();

        $offcut->refresh();
        $this->assertFalse($offcut->isAvailable());
    }

    public function test_catalog_price_is_shown_when_material_exists(): void
    {
        $this->actingAsAdmin();
        Material::query()->create([
            'name' => '2x4 SPF 8ft',
            'short_name' => '2x4',
            'price_cad' => 4.25,
            'is_active' => true,
        ]);
        Offcut::query()->create([
            'material_key' => '2x4',
            'length_in' => 28,
            'qty' => 1,
        ]);

        $this->get(route('admin.utilities.offcuts.index'))
            ->assertOk()
            ->assertSee('$4.25', false);
    }
}

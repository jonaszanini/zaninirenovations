<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\Material;
use App\Models\RoomAreaAssembly;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Services\RoomAreaAssemblyDefaults;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaAssemblyQuoteTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_quote_page_does_not_insert_until_explicit_post(): void
    {
        $this->actingAsAdmin();
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $room = $this->makeRoom();

        $this->get(route('admin.utilities.room-area.assemblies.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertSee('Add kits to quote')
            ->assertSee('Add to quote')
            ->assertSee('Drywall wall')
            ->assertSee('name="assembly_ids[]"', false);

        $this->assertSame(0, StandardQuotation::query()->count());
    }

    public function test_explicit_post_inserts_kit_materials_into_quotation(): void
    {
        $this->actingAsAdmin();
        $clientProject = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $room = $this->makeRoom($clientProject);
        $sheet = Material::create([
            'name' => 'Drywall 4x8',
            'price_cad' => 12.00,
            'unit' => 'sheet',
            'is_active' => true,
        ]);

        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => 'Test',
            'sort_order' => 0,
        ]);
        $line = $assembly->lines()->create([
            'name' => 'Drywall sheets',
            'formula' => 'ceil(net_wall_sf * 1.1 / 32)',
            'unit' => 'sheet',
            'whole_units' => true,
            'material_id' => $sheet->id,
            'sort_order' => 0,
        ]);
        $room->assemblies()->sync([$assembly->id]);

        $response = $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$room->id,
            'assembly_ids' => [$assembly->id],
            'unit_price' => [$line->id => 12],
            'catalog_material_id' => [$line->id => $sheet->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ]);

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $response->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $materials = $quote->flattenedMaterials();
        $this->assertCount(1, $materials);
        $this->assertGreaterThan(0, (float) $materials[0]['quantity']);
        $this->assertEquals(12.00, (float) $materials[0]['unit_price']);
        $this->assertStringContainsString('Drywall sheets', (string) $materials[0]['description']);
        $this->assertSame($clientProject->id, (int) $quote->client_project_id);
    }

    public function test_ignores_client_supplied_quantities_on_kit_quote(): void
    {
        $this->actingAsAdmin();
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $room = $this->makeRoom();
        $assembly = RoomAreaAssembly::query()->where('name', 'LVP floor')->firstOrFail();
        $room->assemblies()->sync([$assembly->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$room->id,
            'assembly_ids' => [$assembly->id],
            'qty' => [1 => 9999],
            'quantity' => [1 => 9999],
            'client_name' => 'Pat Owner',
            'display_title' => 'LVP only',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        foreach ($quote->flattenedMaterials() as $row) {
            $this->assertLessThan(200, (float) $row['quantity']);
        }
    }

    private function makeRoom(?ClientProject $clientProject = null): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Kit quote project',
            'client_project_id' => $clientProject?->id,
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Kitchen',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 120,
            'wall_height_ft' => 8,
            'flooring_waste_percent' => 10,
            'flooring_box_sqft' => 20,
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

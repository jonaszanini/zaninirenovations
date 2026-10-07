<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\Material;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaQuoteLinesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_open_create_quote(): void
    {
        $room = $this->makeRoom();

        $this->get(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->assertRedirect();
    }

    public function test_create_quote_page_renders_for_saved_room(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $this->get(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertSee('Create quote', false)
            ->assertSee('Add priced lines', false)
            ->assertSee('cp-money-input', false)
            ->assertDontSee('data-cp-money-entry="direct"', false)
            ->assertSee('name="trades[]"', false)
            ->assertSee('value="flooring"', false)
            ->assertSee('Start a calculator', false)
            ->assertSee('lf perimeter', false)
            ->assertDontSee('lf walls', false);
    }

    public function test_inserts_lines_into_new_quotation_with_server_computed_qty(): void
    {
        $this->actingAsAdmin();
        $clientProject = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $room = $this->makeRoom($clientProject);
        $flooring = Material::create([
            'name' => 'Oak LVP',
            'price_cad' => 4.50,
            'unit' => 'sq ft',
            'is_active' => true,
        ]);
        $baseboard = Material::create([
            'name' => 'MDF base',
            'price_cad' => 2.00,
            'unit' => 'linear ft',
            'is_active' => true,
        ]);

        $response = $this->from(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->post(route('admin.utilities.room-area.quote.store'), [
                'layout' => 'room:'.$room->id,
                'trades' => ['flooring', 'baseboard'],
                'waste' => ['flooring' => 10, 'baseboard' => 0],
                'unit_price' => ['flooring' => 4.50, 'baseboard' => 2.00],
                'catalog_material_id' => [
                    'flooring' => $flooring->id,
                    'baseboard' => $baseboard->id,
                ],
                'client_name' => 'Pat Owner',
                'display_title' => 'Kitchen — Room Area',
            ]);

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $response->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);

        $floorLine = collect($materials)->first(fn ($row) => str_contains((string) $row['description'], 'Flooring'));
        $baseLine = collect($materials)->first(fn ($row) => str_contains((string) $row['description'], 'Baseboard'));
        $this->assertNotNull($floorLine);
        $this->assertNotNull($baseLine);
        $this->assertEquals(88.0, (float) $floorLine['quantity']);
        $this->assertEquals(30.0, (float) $baseLine['quantity']);
        $this->assertEquals(4.50, (float) $floorLine['unit_price']);
        $this->assertEquals(2.00, (float) $baseLine['unit_price']);
        $this->assertEquals(456.00, (float) $quote->materials_subtotal_before_hst);
        $this->assertEquals(59.28, (float) $quote->materials_hst);
        $this->assertEquals(515.28, (float) $quote->grand_total);
        $this->assertSame($clientProject->id, (int) $quote->client_project_id);
    }

    public function test_ignores_client_supplied_quantities(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $this->post(route('admin.utilities.room-area.quote.store'), [
            'layout' => 'room:'.$room->id,
            'trades' => ['flooring'],
            'waste' => ['flooring' => 0],
            'unit_price' => ['flooring' => 5],
            'qty' => ['flooring' => 9999],
            'quantity' => ['flooring' => 9999],
            'client_name' => 'Pat Owner',
            'display_title' => 'Floor only',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $line = $quote->flattenedMaterials()[0];
        $this->assertEquals(80.0, (float) $line['quantity']);
    }

    private function makeRoom(?ClientProject $clientProject = null): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Quote project',
            'client_project_id' => $clientProject?->id,
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Kitchen',
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
    }
}

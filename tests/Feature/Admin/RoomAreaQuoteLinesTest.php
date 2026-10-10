<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\Material;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Services\StandardQuotationCalculator;
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
            ->assertSee('data-cp-money-entry="direct"', false)
            ->assertSee('name="trades[]"', false)
            ->assertSee('value="flooring"', false)
            ->assertSee('Start a calculator', false)
            ->assertSee('lf perimeter', false)
            ->assertSee('Millwork', false)
            ->assertSee('Stairs', false)
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

    public function test_adding_kit_lines_to_saved_quote_leaves_existing_lines_and_total_unchanged(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $works = [[
            'title' => 'Kitchen',
            'materials' => [
                [
                    'short_name' => 'LVP',
                    'description' => 'Price incl. HST flooring',
                    'url' => 'https://example.test/lvp',
                    'image_url' => null,
                    'unit_price' => 113.0,
                    'quantity' => 10.0,
                    'apply_hst' => true,
                    'hst_includes_unit' => true,
                    'catalog_material_id' => null,
                    'delivery_cad' => 0.0,
                    'entry_mode' => 'manual',
                    'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
                ],
                [
                    'short_name' => '',
                    'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
                    'url' => null,
                    'image_url' => '',
                    'unit_price' => 250.0,
                    'quantity' => 1.0,
                    'apply_hst' => true,
                    'hst_includes_unit' => false,
                    'catalog_material_id' => null,
                    'delivery_cad' => 0.0,
                    'entry_mode' => 'electrical',
                    'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
                ],
            ],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 400.0,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0.0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0.0,
        ]];

        $computed = app(StandardQuotationCalculator::class)->computeWorks($works, false, 0, true, false, 0);
        $quote = StandardQuotation::query()->create([
            'quote_number' => 'SQ-KEEP-1',
            'client_name' => 'Pat Owner',
            'display_title' => 'Saved kitchen',
            'includes_material' => true,
            'includes_labour' => true,
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 400.0,
            'materials' => $works[0]['materials'],
            'works' => $works,
            'materials_subtotal_before_hst' => $computed['materials_subtotal_before_hst'],
            'materials_hst' => $computed['materials_hst'],
            'materials_with_hst' => $computed['materials_with_hst'],
            'labour_total' => $computed['labour_total'],
            'labour_hst' => $computed['labour_hst'],
            'grand_total' => $computed['grand_total'],
            'include_labour_hst' => true,
            'contingency_enabled' => false,
            'contingency_percent' => 0,
        ]);
        $quote->refresh();

        $savedWork = $quote->resolvedWorks()[0];
        $savedLines = $savedWork['materials'];
        $savedTotal = (float) $quote->grand_total;
        $savedLabour = (float) $quote->labour_total;
        $this->assertTrue((bool) ($savedLines[0]['hst_includes_unit'] ?? false));
        $this->assertSame(StandardQuotation::LINE_KIND_ELECTRICAL, $savedLines[1]['line_kind'] ?? null);

        $this->from(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->post(route('admin.utilities.room-area.quote.store'), [
                'layout' => 'room:'.$room->id,
                'quotation_id' => $quote->id,
                'trades' => ['flooring'],
                'waste' => ['flooring' => 0],
                'unit_price' => ['flooring' => 0],
                'client_name' => 'Pat Owner',
                'display_title' => 'Saved kitchen',
            ])->assertRedirect();

        $quote->refresh();
        $afterWork = $quote->resolvedWorks()[0];
        $afterLines = $afterWork['materials'];
        $this->assertCount(count($savedLines) + 1, $afterLines);
        $this->assertEquals($savedLines, array_slice($afterLines, 0, count($savedLines)));
        $this->assertTrue((bool) ($afterLines[0]['hst_includes_unit'] ?? false));
        $this->assertSame(StandardQuotation::LINE_KIND_ELECTRICAL, $afterLines[1]['line_kind'] ?? null);
        $this->assertSame(StandardQuotation::LINE_KIND_MATERIAL, $afterLines[2]['line_kind'] ?? null);
        $this->assertSame($savedWork['labour_use_fixed'], $afterWork['labour_use_fixed']);
        $this->assertEquals($savedWork['labour_fixed_amount'], $afterWork['labour_fixed_amount']);
        $this->assertEquals($savedLabour, (float) $quote->labour_total);
        $this->assertEquals($savedTotal, (float) $quote->grand_total);
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

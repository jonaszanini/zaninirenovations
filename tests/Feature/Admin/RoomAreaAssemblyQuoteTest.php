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
        $room->assemblies()->sync(RoomAreaAssembly::query()->pluck('id')->all());

        $this->get(route('admin.utilities.room-area.assemblies.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertSee('Add kits to quote')
            ->assertSee('Add to quote')
            ->assertSee('Drywall wall')
            ->assertSee('name="assembly_ids[]"', false)
            ->assertSee('name="idempotency_token"', false);

        $this->assertSame(0, StandardQuotation::query()->count());
    }

    public function test_quote_page_does_not_precheck_kits_when_room_has_none(): void
    {
        $this->actingAsAdmin();
        app(RoomAreaAssemblyDefaults::class)->ensure();
        $room = $this->makeRoom();

        $this->get(route('admin.utilities.room-area.assemblies.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertDontSee('name="assembly_ids[]"', false)
            ->assertSee('No kits');
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

        $token = $this->quoteToken($room);
        $payload = [
            'layout' => 'room:'.$room->id,
            'idempotency_token' => $token,
            'assembly_ids' => [$assembly->id],
            'unit_price' => [$line->id => 12],
            'catalog_material_id' => [$line->id => $sheet->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ];

        $response = $this->post(route('admin.utilities.room-area.assemblies.quote.store'), $payload);

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $response->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $materials = $quote->flattenedMaterials();
        $this->assertCount(1, $materials);
        $this->assertGreaterThan(0, (float) $materials[0]['quantity']);
        $this->assertEquals(12.00, (float) $materials[0]['unit_price']);
        $this->assertStringContainsString('Drywall sheets', (string) $materials[0]['description']);
        $this->assertSame($clientProject->id, (int) $quote->client_project_id);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), $payload)->assertRedirect(
            route('admin.quotations.standard.edit', $quote)
        );
        $this->assertSame(1, StandardQuotation::query()->count());
        $this->assertCount(1, $quote->fresh()->flattenedMaterials());
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
            'idempotency_token' => $this->quoteToken($room),
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

    public function test_skips_assembly_line_ids_already_on_the_quote(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();
        $assembly = RoomAreaAssembly::create([
            'name' => 'Skip kit',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $line = $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $room->assemblies()->sync([$assembly->id]);

        $first = $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$room->id,
            'idempotency_token' => $this->quoteToken($room),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ]);
        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $first->assertRedirect(route('admin.quotations.standard.edit', $quote));
        $this->assertCount(1, $quote->flattenedMaterials());

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$room->id,
            'idempotency_token' => $this->quoteToken($room),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $this->assertCount(1, $quote->fresh()->flattenedMaterials());
        $this->assertSame($line->id, (int) ($quote->fresh()->flattenedMaterials()[0]['assembly_line_id'] ?? $line->id));
        $this->assertSame('room:'.$room->id, (string) ($quote->fresh()->flattenedMaterials()[0]['assembly_layout_id'] ?? ''));
    }

    public function test_same_kit_from_another_room_is_added_and_edit_keeps_the_key(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Two-room kit quote',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project);
        $bathroom = $this->makeRoom(null, 'Bathroom', $project);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $line = $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $kitchen->assemblies()->sync([$assembly->id]);
        $bathroom->assemblies()->sync([$assembly->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$kitchen->id,
            'idempotency_token' => $this->quoteToken($kitchen),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertCount(1, $quote->flattenedMaterials());
        $this->assertSame($line->id, (int) $quote->flattenedMaterials()[0]['assembly_line_id']);
        $this->assertSame('room:'.$kitchen->id, (string) $quote->flattenedMaterials()[0]['assembly_layout_id']);

        $this->get(route('admin.quotations.standard.edit', $quote))
            ->assertOk()
            ->assertSee('name="works[0][materials][0][assembly_line_id]"', false)
            ->assertSee('name="works[0][materials][0][assembly_layout_id]"', false)
            ->assertSee('value="'.$line->id.'"', false)
            ->assertSee('value="room:'.$kitchen->id.'"', false);

        $this->put(route('admin.quotations.standard.update', $quote), $this->quoteUpdatePayload($quote))
            ->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $quote->refresh();
        $this->assertSame($line->id, (int) $quote->flattenedMaterials()[0]['assembly_line_id']);
        $this->assertSame('room:'.$kitchen->id, (string) $quote->flattenedMaterials()[0]['assembly_layout_id']);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$bathroom->id,
            'idempotency_token' => $this->quoteToken($bathroom),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $quote->refresh();
        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);
        $layouts = collect($materials)->pluck('assembly_layout_id')->all();
        $this->assertEqualsCanonicalizing(['room:'.$kitchen->id, 'room:'.$bathroom->id], $layouts);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$kitchen->id,
            'idempotency_token' => $this->quoteToken($kitchen),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen kits',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $this->assertCount(2, $quote->fresh()->flattenedMaterials());
    }

    public function test_project_kits_then_room_kits_do_not_double_count(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Project kits quote',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project);
        $bath = $this->makeRoom(null, 'Bathroom', $project);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Shared kit',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $kitchen->assemblies()->sync([$assembly->id]);
        $bath->assemblies()->sync([$assembly->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Project kits',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $countAfterProject = count($quote->flattenedMaterials());
        $this->assertGreaterThan(0, $countAfterProject);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$kitchen->id,
            'idempotency_token' => $this->quoteToken($kitchen),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Project kits',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $this->assertCount($countAfterProject, $quote->fresh()->flattenedMaterials());
    }

    public function test_room_kits_then_project_kits_do_not_double_count(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Room then project',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project);
        $bath = $this->makeRoom(null, 'Bathroom', $project);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Shared kit two',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $kitchen->assemblies()->sync([$assembly->id]);
        $bath->assemblies()->sync([$assembly->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$kitchen->id,
            'idempotency_token' => $this->quoteToken($kitchen),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen first',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $countAfterRoom = count($quote->flattenedMaterials());

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen first',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $this->assertCount($countAfterRoom, $quote->fresh()->flattenedMaterials());
    }

    public function test_expired_quote_token_is_rejected_and_pruned(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();
        $assembly = RoomAreaAssembly::create([
            'name' => 'Expire kit',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $room->assemblies()->sync([$assembly->id]);

        $token = $this->quoteToken($room);
        \Illuminate\Support\Facades\DB::table('room_area_quote_tokens')->where('token', $token)->update([
            'expires_at' => now()->subHour(),
        ]);

        $this->from(route('admin.utilities.room-area.assemblies.quote', ['layout' => 'room:'.$room->id]))
            ->post(route('admin.utilities.room-area.assemblies.quote.store'), [
                'layout' => 'room:'.$room->id,
                'idempotency_token' => $token,
                'assembly_ids' => [$assembly->id],
                'client_name' => 'Pat Owner',
                'display_title' => 'Expired',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('idempotency_token');

        $this->get(route('admin.utilities.room-area.assemblies.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk();
        $this->assertDatabaseMissing('room_area_quote_tokens', ['token' => $token]);
    }

    private function quoteToken(RoomAreaRoom $room): string
    {
        return $this->quoteTokenForLayout('room:'.$room->id);
    }

    private function quoteTokenForLayout(string $layout): string
    {
        $html = $this->get(route('admin.utilities.room-area.assemblies.quote', ['layout' => $layout]))
            ->assertOk()
            ->getContent();
        preg_match('/name="idempotency_token" value="([^"]+)"/', $html, $match);
        $this->assertNotEmpty($match[1] ?? null, 'Quote page must issue an idempotency token');

        return $match[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteUpdatePayload(StandardQuotation $quote): array
    {
        $materials = [];
        foreach ($quote->flattenedMaterials() as $row) {
            $materials[] = [
                'short_name' => $row['short_name'] ?? '',
                'description' => $row['description'] ?? '',
                'unit_price' => $row['unit_price'] ?? 0,
                'quantity' => $row['quantity'] ?? 0,
                'apply_hst' => 1,
                'entry_mode' => $row['entry_mode'] ?? 'manual',
                'assembly_line_id' => $row['assembly_line_id'] ?? null,
                'assembly_layout_id' => $row['assembly_layout_id'] ?? null,
            ];
        }

        return [
            'client_name' => $quote->client_name,
            'display_title' => $quote->display_title,
            'document_type' => 'quotation',
            'pdf_detail_mode' => 'detailed',
            'includes_material' => 1,
            'payment_installments' => 1,
            'works' => [[
                'title' => 'Room Area',
                'labour_use_fixed' => 1,
                'labour_fixed_amount' => 0,
                'materials' => $materials,
            ]],
        ];
    }

    private function makeRoom(?ClientProject $clientProject = null, string $name = 'Kitchen', ?RoomAreaProject $project = null): RoomAreaRoom
    {
        $project ??= RoomAreaProject::create([
            'name' => 'Kit quote project',
            'client_project_id' => $clientProject?->id,
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => $name,
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

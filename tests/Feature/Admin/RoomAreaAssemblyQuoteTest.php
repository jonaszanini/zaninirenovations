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
            ->assertSee('name="works[0][materials][0][assembly_covered_layouts]"', false)
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

    public function test_room_kits_then_project_kits_add_only_rooms_not_on_quote(): void
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
        $line = $assembly->lines()->create([
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
        $this->assertCount(1, $quote->flattenedMaterials());
        $this->assertEquals(2.0, (float) $quote->flattenedMaterials()[0]['quantity']);
        $quoteNumber = $quote->quote_number;

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen first',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) use ($quoteNumber) {
                return is_string($message)
                    && str_contains($message, 'Added Shared kit two — Sheets (Bathroom) to '.$quoteNumber)
                    && str_contains($message, 'Skipped 1: Shared kit two — Kitchen Sheets')
                    && str_contains($message, 'Existing lines were left alone.');
            });

        $quote->refresh();
        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);
        $byLayout = collect($materials)->keyBy('assembly_layout_id');
        $this->assertTrue($byLayout->has('room:'.$kitchen->id));
        $this->assertTrue($byLayout->has('project:'.$project->id));
        $this->assertEquals(2.0, (float) $byLayout['room:'.$kitchen->id]['quantity']);
        $this->assertEquals(2.0, (float) $byLayout['project:'.$project->id]['quantity']);
        $this->assertSame($line->id, (int) $byLayout['project:'.$project->id]['assembly_line_id']);
    }

    public function test_project_add_skips_only_duplicated_kit_lines(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Partial overlap',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project);
        $bath = $this->makeRoom(null, 'Bathroom', $project);
        $drywall = RoomAreaAssembly::create([
            'name' => 'Overlap drywall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $drywall->lines()->create([
            'name' => 'Sheets',
            'formula' => '2',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $base = RoomAreaAssembly::create([
            'name' => 'Overlap base',
            'notes' => null,
            'sort_order' => 1,
        ]);
        $base->lines()->create([
            'name' => 'Baseboard',
            'formula' => '3',
            'unit' => 'lf',
            'whole_units' => false,
            'sort_order' => 0,
        ]);
        $kitchen->assemblies()->sync([$drywall->id]);
        $bath->assemblies()->sync([$base->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$kitchen->id,
            'idempotency_token' => $this->quoteToken($kitchen),
            'assembly_ids' => [$drywall->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen first',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertCount(1, $quote->flattenedMaterials());
        $quoteNumber = $quote->quote_number;

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$drywall->id, $base->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen first',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) use ($quoteNumber) {
                return is_string($message)
                    && str_contains($message, 'Added Overlap base — Baseboard (Bathroom) to '.$quoteNumber)
                    && str_contains($message, 'Skipped 1: Overlap drywall — Kitchen Sheets')
                    && str_contains($message, 'Existing lines were left alone.');
            });

        $materials = $quote->fresh()->flattenedMaterials();
        $this->assertCount(2, $materials);
        $names = collect($materials)->pluck('short_name')->implode(' ');
        $this->assertStringContainsString('Sheets', $names);
        $this->assertStringContainsString('Baseboard', $names);
    }

    public function test_project_add_then_new_room_is_quoted_on_readd(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Later hall',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project, 144, 120);
        $bath = $this->makeRoom(null, 'Bathroom', $project, 120, 96);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $line = $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => 'area_sqft * 0.096',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $kitchen->assemblies()->sync([$assembly->id]);
        $bath->assemblies()->sync([$assembly->id]);

        $this->assertEquals(12.0, app(\App\Services\RoomAreaAssemblyEvaluator::class)->evaluateAssembly($assembly, $kitchen->layoutMetrics())['lines'][0]['qty']);
        $this->assertEquals(8.0, app(\App\Services\RoomAreaAssemblyEvaluator::class)->evaluateAssembly($assembly, $bath->layoutMetrics())['lines'][0]['qty']);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Project first',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEquals(20.0, (float) $quote->flattenedMaterials()[0]['quantity']);
        $this->assertStringContainsString('room:'.$kitchen->id, (string) ($quote->flattenedMaterials()[0]['assembly_covered_layouts'] ?? ''));
        $this->assertStringContainsString('room:'.$bath->id, (string) ($quote->flattenedMaterials()[0]['assembly_covered_layouts'] ?? ''));

        $hall = $this->makeRoom(null, 'Hall', $project, 120, 96);
        $hall->assemblies()->sync([$assembly->id]);
        $this->assertEquals(8.0, app(\App\Services\RoomAreaAssemblyEvaluator::class)->evaluateAssembly($assembly, $hall->layoutMetrics())['lines'][0]['qty']);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Project first',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) {
                return is_string($message)
                    && str_contains($message, 'Added Drywall wall — Sheets (Hall)')
                    && ! str_contains($message, 'Those kit lines are already');
            });

        $quote->refresh();
        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);
        $this->assertEquals(20.0, (float) $materials[0]['quantity']);
        $this->assertEquals(8.0, (float) $materials[1]['quantity']);
        $this->assertSame($line->id, (int) $materials[0]['assembly_line_id']);
        $this->assertSame($line->id, (int) $materials[1]['assembly_line_id']);
        $this->assertStringContainsString('Hall', (string) ($materials[1]['short_name'] ?? ''));
        $this->assertStringContainsString('room:'.$hall->id, (string) ($materials[1]['assembly_covered_layouts'] ?? ''));
        $this->assertStringNotContainsString('room:'.$hall->id, (string) ($materials[0]['assembly_covered_layouts'] ?? ''));

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$hall->id,
            'idempotency_token' => $this->quoteToken($hall),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Project first',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $this->assertCount(2, $quote->fresh()->flattenedMaterials());
        $this->assertEquals(20.0, (float) $quote->fresh()->flattenedMaterials()[0]['quantity']);
        $this->assertEquals(8.0, (float) $quote->fresh()->flattenedMaterials()[1]['quantity']);
    }

    public function test_project_add_then_new_room_can_be_added_alone(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Hall alone',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project, 144, 120);
        $bath = $this->makeRoom(null, 'Bathroom', $project, 120, 96);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => 'area_sqft * 0.096',
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
            'display_title' => 'Project first',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEquals(20.0, (float) $quote->flattenedMaterials()[0]['quantity']);

        $hall = $this->makeRoom(null, 'Hall', $project, 120, 96);
        $hall->assemblies()->sync([$assembly->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$hall->id,
            'idempotency_token' => $this->quoteToken($hall),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Project first',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) {
                return is_string($message) && str_contains($message, 'Added Drywall wall — Sheets (Hall)');
            });

        $quote->refresh();
        $this->assertCount(2, $quote->flattenedMaterials());
        $this->assertEquals(20.0, (float) $quote->flattenedMaterials()[0]['quantity']);
        $this->assertEquals(8.0, (float) $quote->flattenedMaterials()[1]['quantity']);
        $this->assertStringContainsString('Hall', (string) ($quote->flattenedMaterials()[1]['short_name'] ?? ''));
    }

    public function test_remove_then_add_then_save_does_not_overwrite_remaining_row(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Renumber quote',
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
            'display_title' => 'Two rooms',
        ])->assertRedirect();
        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$bathroom->id,
            'idempotency_token' => $this->quoteToken($bathroom),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Two rooms',
        ])->assertRedirect();

        $quote->refresh();
        $this->assertCount(2, $quote->flattenedMaterials());
        $bathRow = collect($quote->flattenedMaterials())->first(
            fn ($row) => str_contains((string) ($row['assembly_covered_layouts'] ?? ''), 'room:'.$bathroom->id)
        );
        $this->assertNotNull($bathRow);

        $payload = $this->quoteUpdatePayload($quote);
        $payload['works'][0]['materials'] = [
            [
                'short_name' => $bathRow['short_name'] ?? 'Sheets',
                'description' => $bathRow['description'] ?? 'Bathroom',
                'unit_price' => $bathRow['unit_price'] ?? 0,
                'quantity' => $bathRow['quantity'] ?? 2,
                'apply_hst' => 1,
                'entry_mode' => $bathRow['entry_mode'] ?? 'manual',
                'assembly_line_id' => $bathRow['assembly_line_id'] ?? $line->id,
                'assembly_layout_id' => $bathRow['assembly_layout_id'] ?? ('room:'.$bathroom->id),
                'assembly_covered_layouts' => $bathRow['assembly_covered_layouts'] ?? null,
            ],
            [
                'short_name' => 'Tape',
                'description' => 'Joint tape',
                'unit_price' => 4,
                'quantity' => 3,
                'apply_hst' => 1,
                'entry_mode' => 'manual',
            ],
        ];

        $this->put(route('admin.quotations.standard.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $quote->refresh();
        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);
        $this->assertStringContainsString('room:'.$bathroom->id, (string) ($materials[0]['assembly_covered_layouts'] ?? ''));
        $this->assertSame($line->id, (int) ($materials[0]['assembly_line_id'] ?? 0));
        $this->assertStringContainsString('Tape', (string) ($materials[1]['short_name'] ?? ''));
        $this->assertEquals(3.0, (float) $materials[1]['quantity']);
        $this->assertStringNotContainsString('room:'.$kitchen->id, json_encode($materials));
    }

    public function test_covered_layouts_persist_for_100_rooms_without_truncation(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Hundred rooms',
            'scale_pixels_per_foot' => 12,
        ]);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => '1',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $rooms = [];
        for ($i = 1; $i <= 100; $i++) {
            $room = $this->makeRoom(null, 'R'.$i, $project, 24, 24);
            $room->assemblies()->sync([$assembly->id]);
            $rooms[] = $room;
        }

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Hundred',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $covered = (string) ($quote->flattenedMaterials()[0]['assembly_covered_layouts'] ?? '');
        $this->assertGreaterThan(500, strlen($covered));
        $decoded = json_decode($covered, true);
        $this->assertIsArray($decoded);
        $this->assertCount(100, $decoded);
        foreach ($rooms as $room) {
            $this->assertContains('room:'.$room->id, $decoded);
        }
        $this->assertEquals(100.0, (float) $quote->flattenedMaterials()[0]['quantity']);

        $this->put(route('admin.quotations.standard.update', $quote), $this->quoteUpdatePayload($quote))
            ->assertRedirect(route('admin.quotations.standard.edit', $quote));
        $quote->refresh();
        $saved = json_decode((string) ($quote->flattenedMaterials()[0]['assembly_covered_layouts'] ?? ''), true);
        $this->assertIsArray($saved);
        $this->assertCount(100, $saved);

        $extra = $this->makeRoom(null, 'R101', $project, 24, 24);
        $extra->assemblies()->sync([$assembly->id]);
        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Hundred',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $quote->refresh();
        $this->assertCount(2, $quote->flattenedMaterials());
        $this->assertEquals(100.0, (float) $quote->flattenedMaterials()[0]['quantity']);
        $this->assertEquals(1.0, (float) $quote->flattenedMaterials()[1]['quantity']);
        $first = json_decode((string) ($quote->flattenedMaterials()[0]['assembly_covered_layouts'] ?? ''), true);
        $after = json_decode((string) ($quote->flattenedMaterials()[1]['assembly_covered_layouts'] ?? ''), true);
        $this->assertIsArray($first);
        $this->assertCount(100, $first);
        $this->assertIsArray($after);
        $this->assertCount(1, $after);
        $this->assertContains('room:'.$extra->id, $after);
        $this->assertStringContainsString('R101', (string) ($quote->flattenedMaterials()[1]['short_name'] ?? ''));
    }

    public function test_line_without_kit_meta_is_ignored_for_coverage(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'No kit meta',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project, 144, 120);
        $bath = $this->makeRoom(null, 'Bathroom', $project, 120, 96);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => 'area_sqft * 0.096',
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
            'display_title' => 'No meta',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertNotEmpty($quote->flattenedMaterials()[0]['assembly_quoted_at'] ?? null);
        $this->assertNotEmpty($quote->flattenedMaterials()[0]['assembly_covered_layouts'] ?? null);
        $works = $quote->resolvedWorks();
        unset($works[0]['materials'][0]['assembly_covered_layouts'], $works[0]['materials'][0]['assembly_quoted_at']);
        $quote->works = $works;
        $quote->materials = $works[0]['materials'];
        $quote->save();

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'No meta',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) {
                return is_string($message)
                    && str_contains($message, 'Added Drywall wall — Sheets')
                    && ! str_contains($message, 'Those kit lines are already');
            });

        $quote->refresh();
        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);
        $this->assertEquals(20.0, (float) $materials[0]['quantity']);
        $this->assertEquals(20.0, (float) $materials[1]['quantity']);
        $this->assertNotEmpty($materials[1]['assembly_quoted_at'] ?? null);
        $this->assertStringContainsString('room:'.$kitchen->id, (string) ($materials[1]['assembly_covered_layouts'] ?? ''));
        $this->assertStringContainsString('room:'.$bath->id, (string) ($materials[1]['assembly_covered_layouts'] ?? ''));
    }

    public function test_two_projects_on_one_quote_add_separate_lines(): void
    {
        $this->actingAsAdmin();
        $projectA = RoomAreaProject::create([
            'name' => 'House A',
            'scale_pixels_per_foot' => 12,
        ]);
        $projectB = RoomAreaProject::create([
            'name' => 'House B',
            'scale_pixels_per_foot' => 12,
        ]);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => 'area_sqft * 0.096',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        foreach ([
            $this->makeRoom(null, 'Kitchen', $projectA, 144, 120),
            $this->makeRoom(null, 'Bathroom', $projectA, 120, 96),
            $this->makeRoom(null, 'Hall', $projectA, 120, 96),
            $this->makeRoom(null, 'Closet', $projectA, 48, 48),
        ] as $room) {
            $room->assemblies()->sync([$assembly->id]);
        }
        foreach ([$this->makeRoom(null, 'Kitchen', $projectB, 144, 120), $this->makeRoom(null, 'Bathroom', $projectB, 120, 96)] as $room) {
            $room->assemblies()->sync([$assembly->id]);
        }

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$projectA->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$projectA->id),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Two houses',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertCount(1, $quote->flattenedMaterials());
        $this->assertEquals(29.0, (float) $quote->flattenedMaterials()[0]['quantity']);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$projectB->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$projectB->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Two houses',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) {
                return is_string($message) && str_contains($message, 'Added Drywall wall — Sheets');
            });

        $quote->refresh();
        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);
        $this->assertEquals(29.0, (float) $materials[0]['quantity']);
        $this->assertEquals(20.0, (float) $materials[1]['quantity']);
        $this->assertEqualsWithDelta(49.0, (float) $materials[0]['quantity'] + (float) $materials[1]['quantity'], 0.01);
        $this->assertSame('Sheets (House A · Kitchen, Bathroom +2)', $materials[0]['short_name'] ?? null);
        $this->assertSame('Sheets (House B · Kitchen, Bathroom)', $materials[1]['short_name'] ?? null);
        $this->assertLessThanOrEqual(60, mb_strlen((string) ($materials[0]['short_name'] ?? '')));
        $this->assertLessThanOrEqual(60, mb_strlen((string) ($materials[1]['short_name'] ?? '')));
        $this->assertStringContainsString('House A', (string) ($materials[0]['short_name'] ?? ''));
        $this->assertStringContainsString('House B', (string) ($materials[1]['short_name'] ?? ''));
    }

    public function test_hand_edited_line_qty_is_left_untouched_when_adding_rooms(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Hand edit',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project, 144, 120);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => 'area_sqft * 0.096',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        $kitchen->assemblies()->sync([$assembly->id]);

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'room:'.$kitchen->id,
            'idempotency_token' => $this->quoteToken($kitchen),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Hand edit',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEquals(12.0, (float) $quote->flattenedMaterials()[0]['quantity']);

        $payload = $this->quoteUpdatePayload($quote);
        $payload['works'][0]['materials'][0]['quantity'] = 99;
        $this->put(route('admin.quotations.standard.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.standard.edit', $quote));
        $this->assertEquals(99.0, (float) $quote->fresh()->flattenedMaterials()[0]['quantity']);

        $hall = $this->makeRoom(null, 'Hall', $project, 120, 96);
        $hall->assemblies()->sync([$assembly->id]);
        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Hand edit',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote))
            ->assertSessionHas('success', function ($message) {
                return is_string($message) && str_contains($message, 'Added Drywall wall — Sheets (Hall)');
            });

        $quote->refresh();
        $this->assertCount(2, $quote->flattenedMaterials());
        $this->assertEquals(99.0, (float) $quote->flattenedMaterials()[0]['quantity']);
        $this->assertEquals(8.0, (float) $quote->flattenedMaterials()[1]['quantity']);
    }

    public function test_resized_room_is_not_silently_folded_into_existing_line(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Resize',
            'scale_pixels_per_foot' => 12,
        ]);
        $kitchen = $this->makeRoom(null, 'Kitchen', $project, 144, 120);
        $bath = $this->makeRoom(null, 'Bathroom', $project, 120, 96);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Sheets',
            'formula' => 'area_sqft * 0.096',
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
            'display_title' => 'Resize',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertCount(1, $quote->flattenedMaterials());
        $this->assertEquals(20.0, (float) $quote->flattenedMaterials()[0]['quantity']);

        $kitchen->update(['area_sqft' => 400]);
        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'quotation_id' => $quote->id,
            'client_name' => 'Pat Owner',
            'display_title' => 'Resize',
        ])->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $quote->refresh();
        $this->assertCount(1, $quote->flattenedMaterials());
        $this->assertEquals(20.0, (float) $quote->flattenedMaterials()[0]['quantity']);
    }

    public function test_skipped_message_lists_three_names_then_plus_more(): void
    {
        $this->actingAsAdmin();
        $controller = app(\App\Http\Controllers\Admin\RoomAreaAssemblyController::class);
        $method = new \ReflectionMethod($controller, 'formatSkippedParts');
        $method->setAccessible(true);

        $this->assertSame(
            'Skipped 4: Alpha, Beta, Gamma +1 more. Existing lines were left alone.',
            $method->invoke($controller, ['Alpha', 'Beta', 'Gamma', 'Delta'])
        );
    }

    public function test_auto_line_name_caps_at_sixty_and_lists_rooms_not_the_project(): void
    {
        $this->actingAsAdmin();
        $project = RoomAreaProject::create([
            'name' => 'Smith',
            'scale_pixels_per_foot' => 12,
        ]);
        $assembly = RoomAreaAssembly::create([
            'name' => 'Drywall wall',
            'notes' => null,
            'sort_order' => 0,
        ]);
        $assembly->lines()->create([
            'name' => 'Drywall',
            'formula' => '1',
            'unit' => 'sheet',
            'whole_units' => true,
            'sort_order' => 0,
        ]);
        foreach (['Kitchen', 'Bath', 'Hall', 'Closet', 'Laundry'] as $name) {
            $this->makeRoom(null, $name, $project, 24, 24)->assemblies()->sync([$assembly->id]);
        }

        $this->post(route('admin.utilities.room-area.assemblies.quote.store'), [
            'layout' => 'project:'.$project->id,
            'idempotency_token' => $this->quoteTokenForLayout('project:'.$project->id),
            'assembly_ids' => [$assembly->id],
            'client_name' => 'Pat Owner',
            'display_title' => 'Names',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $short = (string) ($quote->flattenedMaterials()[0]['short_name'] ?? '');
        $this->assertSame('Drywall (Kitchen, Bath +3)', $short);
        $this->assertLessThanOrEqual(60, mb_strlen($short));
        $this->assertStringNotContainsString('Smith', $short);
    }

    public function test_more_than_2000_rooms_are_quoted_in_chunks(): void
    {
        $this->actingAsAdmin();
        $controller = app(\App\Http\Controllers\Admin\RoomAreaAssemblyController::class);
        $chunk = new \ReflectionMethod($controller, 'chunkCoveredMaps');
        $chunk->setAccessible(true);
        $rooms = [];
        for ($i = 1; $i <= 2001; $i++) {
            $rooms['room:'.$i] = true;
        }
        $parts = $chunk->invoke($controller, $rooms);
        $this->assertCount(2, $parts);
        $this->assertCount(2000, $parts[0]);
        $this->assertCount(1, $parts[1]);

        $encode = new \ReflectionMethod($controller, 'encodeCoveredLayouts');
        $encode->setAccessible(true);
        $this->assertNotSame('', $encode->invoke($controller, $parts[0]));
        $this->assertSame('["room:2001"]', $encode->invoke($controller, $parts[1]));
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
                'assembly_covered_layouts' => $row['assembly_covered_layouts'] ?? null,
                'assembly_quoted_at' => $row['assembly_quoted_at'] ?? null,
            ];
        }

        return [
            'client_name' => $quote->client_name,
            'display_title' => $quote->display_title,
            'document_type' => 'quotation',
            'pdf_detail_mode' => 'detailed',
            'includes_material' => 1,
            'payment_installments' => 1,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => 0,
            'works' => [[
                'title' => 'Room Area',
                'labour_use_fixed' => 1,
                'labour_fixed_amount' => 0,
                'materials' => $materials,
            ]],
        ];
    }

    private function makeRoom(?ClientProject $clientProject = null, string $name = 'Kitchen', ?RoomAreaProject $project = null, int $widthPx = 144, int $heightPx = 120): RoomAreaRoom
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
            'area_sqft' => ($widthPx / 12) * ($heightPx / 12),
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
                            ['x' => $widthPx, 'y' => 0],
                            ['x' => $widthPx, 'y' => $heightPx],
                            ['x' => 0, 'y' => $heightPx],
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

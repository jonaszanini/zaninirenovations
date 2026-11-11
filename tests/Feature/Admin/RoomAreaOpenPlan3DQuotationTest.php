<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\Material;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Models\User;
use App\Services\OpenPlan3DMeasurementBridge;
use App\Services\StandardQuotationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAreaOpenPlan3DQuotationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ClientProject $clientProject;

    private RoomAreaProject $project;

    private OpenPlan3DMeasurementBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->clientProject = ClientProject::factory()->create([
            'client_name' => 'Sarah Connor',
            'name' => 'Basement Suite',
        ]);
        $this->project = RoomAreaProject::create([
            'name' => 'Basement Suite Plans',
            'client_project_id' => $this->clientProject->id,
            'scale_pixels_per_foot' => 20.0,
            'wall_height_ft' => 8.0,
        ]);
        $this->bridge = app(OpenPlan3DMeasurementBridge::class);
    }

    /**
     * Helper to create an openPlan3D room with 12ft x 15ft (180 sq ft) dimensions,
     * one 36" door, and one 48" window.
     */
    private function createSampleRoom(): RoomAreaRoom
    {
        // 12 ft x 15 ft = 365.76 cm x 457.2 cm
        $wCm = 12.0 * 30.48;
        $lCm = 15.0 * 30.48;

        $plan = [
            'unit' => 'cm',
            'version' => '1.0',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $wCm, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w2', 'start' => ['x' => $wCm, 'y' => 0], 'end' => ['x' => $wCm, 'y' => $lCm], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w3', 'start' => ['x' => $wCm, 'y' => $lCm], 'end' => ['x' => 0, 'y' => $lCm], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => $lCm], 'end' => ['x' => 0, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                ],
                'rooms' => [
                    [
                        'id' => 'r1',
                        'name' => 'Recreation Room',
                        'walls' => ['w1', 'w2', 'w3', 'w4'],
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $wCm, 'y' => 0],
                            ['x' => $wCm, 'y' => $lCm],
                            ['x' => 0, 'y' => $lCm],
                        ],
                    ],
                ],
                'doors' => [
                    [
                        'id' => 'd1',
                        'wallId' => 'w1',
                        'type' => 'door',
                        'width' => 91.44, // 36 in
                        'height' => 203.2, // 80 in
                        'offset' => 100.0,
                    ],
                ],
                'windows' => [
                    [
                        'id' => 'win1',
                        'wallId' => 'w2',
                        'type' => 'window',
                        'width' => 121.92, // 48 in
                        'height' => 91.44, // 36 in
                        'offset' => 100.0,
                        'casing_sides' => 4,
                    ],
                ],
            ],
        ];

        $metrics = $this->bridge->layoutMetrics($plan);

        return RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Recreation Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => $metrics['area_sqft'],
            'wall_height_ft' => 8.0,
            'ceiling_height_ft' => 8.0,
            'flooring_waste_percent' => 10.0,
            'flooring_box_sqft' => 20.0,
            'vertices' => $plan,
        ]);
    }

    public function test_renders_room_area_quote_page_with_openplan3d_metrics(): void
    {
        $room = $this->createSampleRoom();

        $response = $this->actingAs($this->admin)->get(
            route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id])
        );

        $response->assertOk();
        $response->assertSee('Create quote', false);
        $response->assertSee('Recreation Room', false);
        $response->assertSee('Flooring', false);
        $response->assertSee('Baseboard', false);
        $response->assertSee('Drywall walls', false);
    }

    public function test_creates_new_quotation_from_openplan3d_room(): void
    {
        $room = $this->createSampleRoom();

        $flooring = Material::create([
            'name' => 'Vinyl Plank Oak',
            'price_cad' => 5.00,
            'unit' => 'sq ft',
            'is_active' => true,
        ]);
        $baseboard = Material::create([
            'name' => 'Baseboard 5in',
            'price_cad' => 2.50,
            'unit' => 'linear ft',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.utilities.room-area.quote.store'),
            [
                'layout' => 'room:'.$room->id,
                'trades' => ['flooring', 'baseboard'],
                'waste' => ['flooring' => 10, 'baseboard' => 0],
                'unit_price' => ['flooring' => 5.00, 'baseboard' => 2.50],
                'catalog_material_id' => [
                    'flooring' => $flooring->id,
                    'baseboard' => $baseboard->id,
                ],
                'client_name' => 'Sarah Connor',
                'display_title' => 'Rec Room Flooring & Baseboard',
            ]
        );

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $response->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);

        $floorLine = collect($materials)->first(fn ($r) => str_contains((string) $r['description'], 'Flooring'));
        $baseLine = collect($materials)->first(fn ($r) => str_contains((string) $r['description'], 'Baseboard'));

        $this->assertNotNull($floorLine);
        $this->assertNotNull($baseLine);

        // Interior floor 171.25 sf (12×15 ft loop, 10 cm walls, interior-face area) + 10% waste → 188.38 sf
        $this->assertEquals(188.38, (float) $floorLine['quantity']);
        $this->assertEquals(5.00, (float) $floorLine['unit_price']);

        // finish_baseboard_lf 49.69: interior perimeter 52.69 lf minus 3 ft door casing width
        $this->assertEquals(49.69, (float) $baseLine['quantity']);
        $this->assertEquals(2.50, (float) $baseLine['unit_price']);

        $expectedSubtotal = (188.38 * 5.00) + (49.69 * 2.50); // 941.90 + 124.225 = 1066.125
        $this->assertEqualsWithDelta(1066.13, (float) $quote->materials_subtotal_before_hst, 0.02);
        $this->assertSame($this->clientProject->id, (int) $quote->client_project_id);
    }

    public function test_adds_openplan3d_lines_to_existing_quotation_without_overwriting_bug_84(): void
    {
        $room = $this->createSampleRoom();

        // Existing quote with custom lines and extra fees (commute, debris, contingency)
        $existingWork = [
            'title' => 'Initial Scope',
            'materials' => [
                [
                    'short_name' => 'Existing Tile',
                    'description' => 'Custom imported tile',
                    'unit_price' => 25.00,
                    'quantity' => 10.0,
                    'apply_hst' => true,
                    'hst_includes_unit' => true, // Bug #84: must NOT be cleared
                    'line_kind' => 'special_material', // Custom line kind: must NOT be overwritten
                    'catalog_material_id' => null,
                    'delivery_cad' => 0.0,
                ],
                [
                    'short_name' => 'Wiring',
                    'description' => 'Electrical rough-in',
                    'unit_price' => 75.00,
                    'quantity' => 2.0,
                    'apply_hst' => true,
                    'hst_includes_unit' => false,
                    'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
                    'catalog_material_id' => null,
                    'delivery_cad' => 0.0,
                ],
            ],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 500.00,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0.0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0.0,
        ];

        $calculator = app(StandardQuotationCalculator::class);
        $computed = $calculator->computeWorks([$existingWork], true, 10.0, true, false, 0.0);

        $quote = StandardQuotation::create([
            'quote_number' => StandardQuotation::generateNumber(),
            'client_name' => 'Sarah Connor',
            'client_project_id' => $this->clientProject->id,
            'display_title' => 'Initial Basement Quote',
            'version' => 1,
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'includes_material' => true,
            'includes_labour' => true,
            'materials' => $existingWork['materials'],
            'works' => [$existingWork],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 500.00,
            'labour_total' => 500.00,
            'contingency_enabled' => true,
            'contingency_percent' => 10.0,
            'include_commute' => true,
            'commute_amount' => 75.00,
            'commute_estimated_trips' => 1,
            'include_debris_removal' => true,
            'debris_disposal_cost' => 150.00,
            'grand_total' => 1200.00,
        ]);
        $quote->grand_total = $quote->editorGrandTotal($computed);
        $quote->save();

        $savedMaterialsCount = count($existingWork['materials']);

        // Insert openPlan3D room line
        $response = $this->actingAs($this->admin)->post(
            route('admin.utilities.room-area.quote.store'),
            [
                'layout' => 'room:'.$room->id,
                'quotation_id' => $quote->id,
                'trades' => ['flooring'],
                'waste' => ['flooring' => 0],
                'unit_price' => ['flooring' => 4.00],
                'client_name' => 'Sarah Connor',
                'display_title' => 'Initial Basement Quote',
            ]
        );

        $response->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $quote->refresh();
        $works = $quote->resolvedWorks();
        $materials = $works[0]['materials'];

        // Must have original 2 materials + 1 new line
        $this->assertCount($savedMaterialsCount + 1, $materials);

        // First line: hst_includes_unit and custom line_kind must remain intact (Bug #84 guard)
        $this->assertTrue((bool) ($materials[0]['hst_includes_unit'] ?? false));
        $this->assertSame('special_material', $materials[0]['line_kind']);

        // Second line: electrical line_kind must remain intact
        $this->assertSame(StandardQuotation::LINE_KIND_ELECTRICAL, $materials[1]['line_kind']);

        // Third line is the newly added openPlan3D flooring line
        $this->assertSame(StandardQuotation::LINE_KIND_MATERIAL, $materials[2]['line_kind']);
        $this->assertEquals(171.25, (float) $materials[2]['quantity']);
        $this->assertEquals(4.00, (float) $materials[2]['unit_price']);

        // Grand total must equal editorGrandTotal() including commute, debris, and contingency
        $this->assertEquals($quote->editorGrandTotal(), (float) $quote->grand_total);
    }

    public function test_prefills_flooring_wizard_from_openplan3d_room(): void
    {
        $room = $this->createSampleRoom();

        $response = $this->actingAs($this->admin)->get(
            route('admin.quotations.flooring.create', [
                'room_area_import_id' => 'room:'.$room->id,
                'project' => $this->clientProject->id,
            ])
        );

        $response->assertOk();
        $response->assertSee('value="171.25"', false);
        $response->assertSee('value="49.69"', false);
    }

    public function test_prefills_trimming_wizard_from_openplan3d_room(): void
    {
        $room = $this->createSampleRoom();

        $response = $this->actingAs($this->admin)->get(
            route('admin.quotations.trimming.create', [
                'room_area_import_id' => 'room:'.$room->id,
                'project' => $this->clientProject->id,
            ])
        );

        $response->assertOk();
        $response->assertSee('Recreation Room', false);
    }

    public function test_prefills_painting_wizard_from_openplan3d_room(): void
    {
        $room = $this->createSampleRoom();

        $response = $this->actingAs($this->admin)->get(
            route('admin.quotations.painting.create', [
                'room_area_import_id' => 'room:'.$room->id,
                'project' => $this->clientProject->id,
            ])
        );

        $response->assertOk();
        $response->assertSee('Recreation Room', false);
    }
}

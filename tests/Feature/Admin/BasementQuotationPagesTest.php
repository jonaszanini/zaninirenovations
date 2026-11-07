<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_hub_and_wizard_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Basement Renovation')
            ->assertSee('This hub does not list quotations');

        $this->get(route('admin.quotations.basement.index'))
            ->assertOk()
            ->assertSee('New basement quotation');

        $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('1 of 14')
            ->assertSee('Project Overview')
            ->assertSee('What are you quoting?')
            ->assertSee('Full basement')
            ->assertSee('New bathroom')
            ->assertSee('New wall')
            ->assertSee('Trades to include')
            ->assertSee('Total basement area')
            ->assertSee('Total sq ft')
            ->assertSee('Length × width')
            ->assertSee('Flooring')
            ->assertSee('Basement ceiling lighting')
            ->assertSee('Pot lights', false)
            ->assertSee("Electrician's quote")
            ->assertSee("Plumber's quote")
            ->assertDontSee('licensed')
            ->assertDontSee('licence')
            ->assertSee('Wall / ceiling colors')
            ->assertSee('fa-info-circle', false)
            ->assertSee('New drain, vent, and hot/cold supply')
            ->assertSee('Import layout')
            ->assertSee('Search rooms', false)
            ->assertSee('Project description', false)
            ->assertSee('Optional — appears on the client PDF', false)
            ->assertSee('4×8 (32 sq ft)', false)
            ->assertSee('4×12 (48 sq ft)', false)
            ->assertSee('Stud spacing')
            ->assertSee('City building permit — optional extra-charge service')
            ->assertSee('BCIN drawings — optional extra-charge service')
            ->assertSee('Client scope')
            ->assertSee('Contractor fee')
            ->assertDontSee('Invest —')
            ->assertDontSee('name="selected_value_tier"', false)
            ->assertDontSee('value_package_notes', false)
            ->assertSee('Commute expense')
            ->assertSee('Material waste')
            ->assertSee('id="br-step-status"', false)
            ->assertSee('class="br-step-nav__status"', false)
            ->assertSee('data-cp-money-entry="direct"', false)
            ->assertSee('Optional extras (rentals, extra labour, etc.)', false)
            ->assertDontSee('Optional extras (permits, rentals')
            ->assertSee('placeholder="e.g. Dumpster"', false)
            ->assertSee('data-br-demo-choice="1"', false)
            ->assertSee('data-br-demo-choice="0"', false)
            ->assertSee('Include demolition', false);

        $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->assertSee('Labour productivity')
            ->assertSee('class="admin-form-grid-4"', false)
            ->assertSee('Default materials')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Materials</button>', false)
            ->assertSee('Line item')
            ->assertSee('Materials DB')
            ->assertSee('Unit price (CAD$)')
            ->assertSee('>Each</option>', false)
            ->assertSee('>Sq ft</option>', false)
            ->assertSee('per sq ft', false)
            ->assertSee('From 2x4x8 stud', false)
            ->assertSee('cannot be linked separately', false)
            ->assertSee('17 L', false)
            ->assertSee('2x4x8', false)
            ->assertSee('5 gal', false)
            ->assertSee('Trim paint', false)
            ->assertSee('Tape / cut-in two colors', false)
            ->assertSee('br-config-source--manual', false)
            ->assertSee('br-config-source--derived', false)
            ->assertSee('From stud', false)
            ->assertSee('br-config-legend', false)
            ->assertSee('form="br-config-form"', false)
            ->assertSee('Save configuration');
    }

    public function test_drywall_step_offers_room_area_import(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('Import layout')
            ->assertSee('Search rooms', false)
            ->assertSee('name="perimeter_lf"', false)
            ->assertSee('data-br-layout-picker', false)
            ->assertSee('admin-basement-layout-import.js', false)
            ->assertSee('id="br-bulkhead"', false)
            ->assertSee('id="br-partition"', false);
    }

    public function test_project_context_sits_between_price_configuration_and_next(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'notes' => 'Site has a low beam in the rec room.',
        ]);

        $html = $this->get(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Project Context')
            ->assertSee('Site has a low beam in the rec room.')
            ->getContent();

        $price = strpos($html, 'Price configuration');
        $context = strpos($html, 'Project Context');
        $next = strpos($html, '→ Next');
        $live = strpos($html, 'Live breakdown');
        $header = strpos($html, 'data-qh-header');

        $this->assertNotFalse($price);
        $this->assertNotFalse($context);
        $this->assertNotFalse($next);
        $this->assertNotFalse($live);
        $this->assertNotFalse($header);
        $this->assertLessThan($context, $header);
        $this->assertGreaterThan($context, $next);
        $this->assertLessThan($live, $context);
        $this->assertStringContainsString('id="br-sticky-total"', $html);
        $this->assertStringContainsString('id="br-step-nav"', $html);
        $this->assertMatchesRegularExpression(
            '/<details class="admin-accordion admin-card cp-project-context-card">/',
            $html
        );
    }

    public function test_bathroom_preset_hides_flooring_in_progress_nav(): void
    {
        $this->actingAsAdmin();

        $calc = (new \App\Services\BasementQuotationCalculator)->calculate([
            'scope_preset' => 'bathroom',
            'area_sqft' => 50,
            'outlet_qty' => 3,
            'switch_qty' => 2,
            'include_bath_fixture' => true,
            'bath_fixture_qty' => 1,
        ]);
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Bath Client',
            'is_draft' => false,
            'inputs' => array_merge(
                \App\Services\BasementQuotationCalculator::defaultInputs(),
                [
                    'scope_preset' => 'bathroom',
                    'area_sqft' => 50,
                    'outlet_qty' => 3,
                    'switch_qty' => 2,
                    'include_bath_fixture' => true,
                    'bath_fixture_qty' => 1,
                    'enabled_sections' => \App\Services\BasementQuotationCalculator::presetEnabledSections('bathroom'),
                ]
            ),
            'calculation' => $calc,
            'final_quote_total' => $calc['summary']['final_quote_total'],
            'version' => 1,
        ]);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('New bathroom')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/data-br-goto="flooring"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-br-goto="electrical"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-br-goto="plumbing"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-br-goto="framing"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression('/id="br-outlets"[^>]*value="3"/', $html);
        $this->assertMatchesRegularExpression('/id="br-switches"[^>]*value="2"/', $html);
        $this->assertMatchesRegularExpression('/id="br-bath-fixture-qty"[^>]*value="1"/', $html);
        $this->assertStringContainsString('name="include_bath_fixture"', $html);
        $this->assertStringContainsString('Light fixture', $html);
        $this->assertStringContainsString('Spot light', $html);
        $this->assertStringContainsString('1 at the sink', $html);
        $this->assertStringContainsString('fa-info-circle', $html);
        $this->assertStringContainsString('New drain, vent, and hot/cold supply', $html);
        $this->assertStringContainsString('Add area', $html);
        $this->assertStringContainsString('e.g. Floor, Shower', $html);
        $this->assertStringContainsString('>Vinyl</span>', $html);
        $this->assertStringContainsString('name="bath_tile_areas[0][finish]"', $html);
        $this->assertMatchesRegularExpression(
            '/id="br-lighting-fixture-qty"[^>]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('Pedestal sink + faucet', $html);
        $this->assertStringContainsString('name="include_pedestal_sink"', $html);
        $this->assertStringContainsString('Pedestal sink', $html);
        $this->assertStringContainsString('id="br-include-pedestal"', $html);
        $this->assertMatchesRegularExpression(
            '/name="include_pedestal_sink"[^>]*value="1"/s',
            $html
        );
        $this->assertStringNotContainsString('value="pedestal_sink"', $html);
        $this->assertStringContainsString('id="br-plumbing-slot-bath"', $html);
        $this->assertStringContainsString('id="br-framing-slot-bath"', $html);
        $this->assertStringContainsString('Walls &amp; ducts', $html);
        $this->assertStringContainsString('New wall (lin. ft)', $html);
        $this->assertStringContainsString('Duct boxing (lin. ft)', $html);
    }

    public function test_price_configuration_keeps_active_tab_after_save(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.basement.price-configuration', ['tab' => 'materials']))
            ->assertOk()
            ->assertSee('id="br-config-tab" value="materials"', false);

        $this->post(route('admin.quotations.basement.database.update'), [
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'tab' => 'materials',
        ])->assertRedirect(route('admin.quotations.basement.price-configuration', ['tab' => 'materials']));
    }

    public function test_preview_returns_live_totals(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.basement.preview'), [
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'include_building_permit' => '1',
            'building_permit_amount' => '175.00',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.area_sqft', 850)
            ->assertJsonPath('summary.permit_pre_hst', 175)
            ->assertJsonPath('eta.scenarios.0.crew', 1);

        $withoutPermit = $this->post(route('admin.quotations.basement.preview'), [
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'include_building_permit' => '0',
            'building_permit_amount' => '175.00',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()->json();
        $this->assertEquals(0, (float) ($withoutPermit['summary']['permit_pre_hst'] ?? 0));
    }

    public function test_preview_with_posted_trade_keys_yields_a_positive_grand_total(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();
        foreach (array_keys(\App\Services\BasementQuotationCalculator::tradeSections()) as $tradeKey) {
            $this->assertMatchesRegularExpression(
                '/name="enabled_sections\[]"[^>]*value="'.preg_quote($tradeKey, '/').'"/',
                $html
            );
        }

        $keys = array_merge(
            ['overview'],
            array_keys(\App\Services\BasementQuotationCalculator::tradeSections())
        );
        $withKeys = $this->post(route('admin.quotations.basement.preview'), [
            'area_sqft' => 500,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'enabled_sections' => $keys,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()->json();
        $this->assertGreaterThan(0, (float) ($withKeys['summary']['grand_total'] ?? 0));
        $this->assertGreaterThan(0, (float) ($withKeys['summary']['labor_pre_hst'] ?? 0));

        $bogusOnes = $this->post(route('admin.quotations.basement.preview'), [
            'area_sqft' => 500,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'enabled_sections' => ['overview', '1', '1', '1'],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()->json();
        $this->assertSame(0.0, (float) ($bogusOnes['summary']['labor_pre_hst'] ?? -1));
        $this->assertSame(0.0, (float) ($bogusOnes['summary']['grand_total'] ?? -1));
    }

    public function test_preview_uses_imported_layout_perimeter(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.basement.preview'), [
            'area_sqft' => 338.3,
            'ceiling_height_ft' => 8,
            'perimeter_lf' => 93.04,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.suggested_perimeter_lf', 93.04)
            ->assertJsonPath('scope.measured_perimeter', true)
            ->assertJsonPath('scope.suggested_wall_sf', 744.32);
    }

    public function test_store_publishes_a_standalone_quotation(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.quotations.basement.store'), [
            'save_intent' => 'publish',
            'client_name' => 'Basement Test Client',
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ]);

        $quote = BasementQuotation::query()->where('client_name', 'Basement Test Client')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
        $this->assertStringContainsString('850', $quote->summaryDescription());
        $response->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $this->assertFalse((bool) $quote->include_commute);
        $this->assertSame(0.0, (float) $quote->commute_amount);
    }

    public function test_save_stays_on_quotation_and_shows_back_to_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->get(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee($project->name, false)
            ->assertSee('qh-crumb__project', false);

        $payload = [
            'save_intent' => 'publish',
            'client_name' => $project->client_name,
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];

        $store = $this->post(route('admin.quotations.basement.store', ['project' => $project->id]), $payload);

        $quote = BasementQuotation::query()->where('client_name', $project->client_name)->first();
        $this->assertNotNull($quote);
        $this->assertSame($project->id, $quote->client_project_id);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $this->get(route('admin.quotations.basement.edit', [
            'basement_quotation' => $quote,
            'return' => 'project',
        ]))
            ->assertOk()
            ->assertSee($project->name, false)
            ->assertSee('qh-crumb__project', false)
            ->assertSee('#quotation', false);

        $this->put(route('admin.quotations.basement.update', [
            'basement_quotation' => $quote,
            'return' => 'project',
        ]), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
    }

    public function test_room_area_layout_search_returns_saved_room(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Smith basement',
            'scale_pixels_per_foot' => 10,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rec room',
            'scale_pixels_per_foot' => 10,
            'area_sqft' => 50,
            'vertices' => [
                'v' => 2,
                'groups' => [
                    [
                        'id' => 'g1',
                        'color' => '#024768',
                        'polygons' => [
                            [
                                'id' => 'p1',
                                'vertices' => [
                                    ['x' => 0, 'y' => 0],
                                    ['x' => 100, 'y' => 0],
                                    ['x' => 100, 'y' => 50],
                                    ['x' => 0, 'y' => 50],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Rec']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'room:'.$room->id,
                'area_sqft' => 50,
                'perimeter_lf' => 30,
                'inside_corners' => 4,
                'outside_corners' => 0,
            ]);
    }
}

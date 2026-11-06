<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementProjectSaveDiscoverabilityTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_from_project_exposes_hidden_link_and_full_basement_list(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Basement finishing - Barrhaven',
            'client_name' => 'Brenda Furlong',
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $html = $this->get(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('name="link_client_project_id"', false)
            ->assertSee('value="'.$project->id.'"', false)
            ->assertSee('name="client_name"', false)
            ->assertSee('Brenda Furlong')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form method="post" action="[^"]*project='.$project->id.'[^"]*" id="br-main-form"/',
            $html
        );
        $this->assertStringContainsString(
            route('admin.client-projects.show', $project).'#quotation',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="payment_installments"[^>]*required/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="payment_installments"[^>]*checked/',
            $html
        );
    }

    public function test_quotations_hub_says_tiles_are_not_the_saved_list(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('This hub does not list quotations');
    }

    public function test_publish_from_hidden_project_id_links_even_without_query_string(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $response = $this->post(route('admin.quotations.basement.store'), $this->publishPayload([
            'client_name' => $project->client_name,
            'link_client_project_id' => $project->id,
        ]));

        $quote = BasementQuotation::query()->where('client_name', $project->client_name)->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertSame($project->id, (int) $quote->client_project_id);
        $this->assertSame($project->id, (int) $quote->intended_client_project_id);
        $response->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $response->assertSessionHas('success', function ($message) use ($quote) {
            return is_string($message)
                && str_contains($message, $quote->quote_number)
                && str_contains($message, 'linked to this project');
        });

        $this->assertNotNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $quote->id
        ));

        $this->get(route('admin.quotations.basement.index'))
            ->assertOk()
            ->assertSee($quote->quote_number, false);

        $this->get(route('admin.quotations.basement.index', ['project' => $project->id]))
            ->assertOk()
            ->assertDontSee($quote->quote_number, false)
            ->assertSee('Quotes already on this project are on the project Quotation tab', false);
    }

    public function test_save_draft_from_project_is_findable_and_not_on_project_tab(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $response = $this->post(
            route('admin.quotations.basement.store', ['project' => $project->id]),
            $this->publishPayload([
                'save_intent' => 'draft',
                'client_name' => $project->client_name,
                'link_client_project_id' => $project->id,
            ])
        );

        $quote = BasementQuotation::query()->where('client_name', $project->client_name)->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertNull($quote->client_project_id);
        $this->assertSame($project->id, (int) $quote->intended_client_project_id);
        $response->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $response->assertSessionHas('success', function ($message) use ($quote) {
            return is_string($message)
                && str_contains($message, $quote->quote_number)
                && str_contains($message, 'saved as a draft')
                && str_contains($message, 'not on the project')
                && str_contains($message, 'Basement Renovation quotations');
        });

        $this->assertNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $quote->id
        ));

        $this->get(route('admin.quotations.basement.index'))
            ->assertOk()
            ->assertSee($quote->quote_number, false);

        $this->get(route('admin.quotations.basement.index', ['project' => $project->id]))
            ->assertOk()
            ->assertSee($quote->quote_number, false);
    }

    public function test_publish_without_review_choices_shows_validation_errors_and_does_not_save(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $this->from(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->post(route('admin.quotations.basement.store'), [
                'save_intent' => 'publish',
                'client_name' => $project->client_name,
                'area_sqft' => 1500,
                'ceiling_height_ft' => 8,
                'link_client_project_id' => $project->id,
            ])
            ->assertRedirect(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->assertSessionHasErrors(['payment_installments']);

        $this->assertNull(BasementQuotation::query()->where('client_name', $project->client_name)->first());
    }

    public function test_room_area_import_publish_from_project_stays_linked_and_draft_stays_off_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);
        $layout = RoomAreaProject::create([
            'name' => 'Barrhaven layout',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $layout->id,
            'name' => 'Rec room',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 400,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 240, 'y' => 0],
                            ['x' => 240, 'y' => 192],
                            ['x' => 0, 'y' => 192],
                        ],
                    ]],
                ]],
            ],
        ]);

        $import = [
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'perimeter_lf' => 72,
            'room_area_import_id' => 'room:'.$room->id,
            'room_area_import_label' => 'Rec room',
            'enabled_sections' => array_merge(
                ['overview'],
                array_keys(BasementQuotationCalculator::tradeSections())
            ),
        ];

        $this->post(route('admin.quotations.basement.store'), $this->publishPayload([
            'save_intent' => 'draft',
            'client_name' => 'Draft Import '.$project->id,
            'link_client_project_id' => $project->id,
        ] + $import));

        $draft = BasementQuotation::query()->where('client_name', 'Draft Import '.$project->id)->first();
        $this->assertNotNull($draft);
        $this->assertTrue((bool) $draft->is_draft);
        $this->assertNull($draft->client_project_id);
        $this->assertSame($project->id, (int) $draft->intended_client_project_id);
        $this->assertSame('room:'.$room->id, $draft->inputs['room_area_import_id'] ?? null);
        $this->assertNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $draft->id
        ));

        $this->post(route('admin.quotations.basement.store'), $this->publishPayload([
            'client_name' => 'Publish Import '.$project->id,
            'link_client_project_id' => $project->id,
        ] + $import));

        $published = BasementQuotation::query()->where('id', '!=', $draft->id)->orderByDesc('id')->first();
        $this->assertNotNull($published);
        $this->assertFalse((bool) $published->is_draft);
        $this->assertSame($project->id, (int) $published->client_project_id);
        $this->assertSame('room:'.$room->id, $published->inputs['room_area_import_id'] ?? null);
        $this->assertNotNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $published->id
        ));
    }

    public function test_wizard_save_reports_invalid_fields_instead_of_failing_silently(): void
    {
        $src = file_get_contents(public_path('js/admin-basement-quotation.js'));
        $this->assertNotFalse($src);
        $this->assertStringContainsString('if (!form.checkValidity())', $src);
        $this->assertStringContainsString('AdminQuoteDraftValidation.reportInvalid', $src);
        $this->assertStringContainsString('reportValidity', $src);
    }

    public function test_draft_reopen_without_query_publishes_to_stored_project_once(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $this->post(route('admin.quotations.basement.store'), $this->publishPayload([
            'save_intent' => 'draft',
            'client_name' => $project->client_name,
            'link_client_project_id' => $project->id,
        ]));

        $quote = BasementQuotation::query()->where('client_name', $project->client_name)->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertNull($quote->client_project_id);
        $this->assertSame($project->id, (int) $quote->intended_client_project_id);
        $this->assertNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $quote->id
        ));

        $edit = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk();
        $html = $edit->getContent();
        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="link_client_project_id"[^>]*value="'.$project->id.'"/',
            $html
        );
        $this->assertStringContainsString(
            route('admin.client-projects.show', $project).'#quotation',
            $html
        );
        $this->assertStringContainsString(route('admin.quotations.basement.index'), $html);

        $publish = $this->put(
            route('admin.quotations.basement.update', $quote),
            $this->publishPayload([
                'client_name' => $project->client_name,
            ])
        );

        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertSame($project->id, (int) $quote->client_project_id);
        $this->assertSame($project->id, (int) $quote->intended_client_project_id);
        $publish->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $publish->assertSessionHas('success', function ($message) use ($quote) {
            return is_string($message)
                && str_contains($message, $quote->quote_number)
                && str_contains($message, 'published and linked to this project');
        });

        $this->assertSame(1, ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->count());

        $resave = $this->put(
            route('admin.quotations.basement.update', $quote),
            $this->publishPayload([
                'client_name' => $project->client_name,
            ])
        );
        $quote->refresh();
        $resave->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $resave->assertSessionHas('success', function ($message) use ($quote) {
            return is_string($message)
                && str_contains($message, $quote->quote_number)
                && str_contains($message, 'updated (v'.$quote->version.')')
                && str_contains($message, 'linked to this project');
        });
        $this->assertSame($project->id, (int) $quote->client_project_id);
        $this->assertSame(1, ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->count());
    }

    public function test_mismatched_or_invalid_project_is_rejected_and_stays_unlinked(): void
    {
        $this->actingAsAdmin();

        $intended = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);
        $other = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $this->post(route('admin.quotations.basement.store'), $this->publishPayload([
            'save_intent' => 'draft',
            'client_name' => $intended->client_name,
            'link_client_project_id' => $intended->id,
        ]));

        $quote = BasementQuotation::query()->where('client_name', $intended->client_name)->first();
        $this->assertNotNull($quote);

        $this->from(route('admin.quotations.basement.edit', $quote))
            ->put(route('admin.quotations.basement.update', $quote), $this->publishPayload([
                'client_name' => $intended->client_name,
                'link_client_project_id' => $other->id,
            ]))
            ->assertRedirect(route('admin.quotations.basement.edit', $quote))
            ->assertSessionHasErrors('link_client_project_id');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertNull($quote->client_project_id);
        $this->assertSame($intended->id, (int) $quote->intended_client_project_id);
        $this->assertNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $quote->id
        ));

        $this->from(route('admin.quotations.basement.edit', $quote))
            ->put(route('admin.quotations.basement.update', $quote), $this->publishPayload([
                'client_name' => $intended->client_name,
                'link_client_project_id' => 999999,
            ]))
            ->assertRedirect(route('admin.quotations.basement.edit', $quote))
            ->assertSessionHasErrors('link_client_project_id');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertNull($quote->client_project_id);
        $this->assertSame($intended->id, (int) $quote->intended_client_project_id);
        $this->assertNull(ClientProjectQuotation::findForSource(
            ClientProjectQuotation::SOURCE_BASEMENT,
            (int) $quote->id
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'client_name' => 'Basement Project Save Client',
            'area_sqft' => 1500,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ], $overrides);
    }
}

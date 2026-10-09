<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\MillworkQuotation;
use App\Services\MillworkQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\AssertsAdminInfoTipAria;

/**
 * Tester PR A acceptance check 5: red banner, PDF 422, no file.
 */
class MillworkCutPlanPlacementTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use AssertsAdminInfoTipAria;

    public function test_oversized_shelf_preview_lists_unplaced_parts(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.millwork.create'))
            ->assertOk()
            ->assertSee('mw-unplaced-banner', false)
            ->assertSee('Parts do not fit on any sheet', false);

        $this->post(route('admin.quotations.millwork.preview'), [
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => 0,
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => 0,
            'owner_hourly_rate' => 85,
        ])->assertOk()
            ->assertJsonPath('cut_plan.placed_part_count', 0)
            ->assertJsonPath('summary.labor_pre_hst', 0)
            ->assertJsonFragment(['reason' => 'Does not fit on any sheet we stock']);
    }

    public function test_edit_page_shows_red_unplaced_banner(): void
    {
        $this->actingAsAdmin();

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
        ]);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-UNPLACED-1',
            'client_name' => 'Oversized Shelf',
            'is_draft' => true,
            'cut_plan_version' => 2,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
                ]],
            ],
            'calculation' => $calc,
            'final_quote_total' => (float) ($calc['summary']['grand_total'] ?? 0),
        ]);

        $html = $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee('mw-unplaced-banner', false)
            ->assertSee('Parts do not fit on any sheet')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="mw-unplaced-banner"[^>]*hidden/',
            $html
        );
        $this->assertStringContainsString('100', $html);
    }

    public function test_pdf_endpoint_returns_422_instead_of_a_file_when_parts_are_unplaced(): void
    {
        $this->actingAsAdmin();

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
        ]);
        $this->assertNotEmpty($calc['cut_plan']['unplaced']);
        $this->assertSame(0.0, (float) $calc['summary']['labor_pre_hst']);

        $project = ClientProject::factory()->create([
            'client_name' => 'Oversized PDF',
        ]);
        $mw = MillworkQuotation::query()->create([
            'quote_number' => 'MW-UNPLACED-PDF',
            'client_name' => 'Oversized PDF',
            'client_project_id' => $project->id,
            'cut_plan_version' => 2,
            'is_draft' => false,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
                ]],
            ],
            'calculation' => $calc,
            'final_quote_total' => 0,
        ]);
        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'source_id' => $mw->id,
            'title' => 'Custom millwork',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'Q-UNPLACED-1',
            'version' => 1,
            'labour' => true,
            'material' => false,
            'items' => [[
                'description' => 'Custom millwork (labour)',
                'amount' => 0,
                'apply_hst' => true,
            ]],
        ]);

        $response = $this->get(route('admin.client-projects.quotation.pdf', [$project, $quotation]));
        $response->assertStatus(422);
        $this->assertStringNotContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertFalse(str_starts_with($response->getContent(), '%PDF'));
        $this->assertStringContainsString('do not fit on any sheet', $response->getContent());
    }

    public function test_saved_v1_cut_list_keeps_fixture_sheet_count(): void
    {
        $this->actingAsAdmin();

        $path = base_path('tests/fixtures/millwork-cut-plan-v1/closet_default.json');
        $fixture = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($fixture);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-CLOSET',
            'client_name' => 'Snapshot Closet',
            'is_draft' => false,
            'inputs' => [
                'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
            ],
            'calculation' => $fixture['calculation'],
            'final_quote_total' => (float) $fixture['grand_total'],
        ]);

        $this->get(route('admin.quotations.millwork.cut-list', $quote))
            ->assertOk()
            ->assertSee('Sheet 1:')
            ->assertSee('Sheet 2:')
            ->assertDontSee('Sheet 3:');
    }

    public function test_unplaced_blocks_sms_accept_and_shows_friendly_pdf_page(): void
    {
        $this->actingAsAdmin();
        [$project, $mw, $quotation] = $this->linkedUnplacedQuote();

        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$project, $quotation]));
        $pdf->assertStatus(422);
        $pdf->assertSee('Parts do not fit on any sheet');
        $pdf->assertDontSee('Whoops');
        $this->assertFalse(str_starts_with($pdf->getContent(), '%PDF'));

        $this->post(route('admin.client-projects.quotation.sms', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->post(route('admin.client-projects.quotation.status', [$project, $quotation]), [
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
        ])->assertRedirect()
            ->assertSessionHas('error');
        $quotation->refresh();
        $this->assertNotSame(ClientProjectQuotation::STATUS_ACCEPTED, $quotation->status);
    }

    public function test_old_quote_without_unplaced_key_is_not_blocked(): void
    {
        $this->actingAsAdmin();
        $presets = $this->legacyPresetInputs();
        foreach ($presets as $i => $inputs) {
            $project = ClientProject::factory()->create(['client_name' => 'Legacy Open '.$i]);
            $mw = MillworkQuotation::query()->create([
                'quote_number' => 'MW-V1-OPEN-'.$i,
                'client_name' => 'Legacy Open '.$i,
                'client_project_id' => $project->id,
                'is_draft' => false,
                'inputs' => $inputs,
                'calculation' => [
                    'cut_plan' => ['sheet_count' => 2, 'layouts' => []],
                    'summary' => ['grand_total' => 1663.90, 'labor_pre_hst' => 900, 'materials_pre_hst' => 200],
                ],
                'final_quote_total' => 1663.90,
            ]);
            $this->assertArrayNotHasKey('unplaced', $mw->calculation['cut_plan']);
            $this->assertFalse($mw->hasUnplacedCutParts());

            $quotation = ClientProjectQuotation::query()->create([
                'client_project_id' => $project->id,
                'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
                'source_id' => $mw->id,
                'title' => 'Custom millwork',
                'status' => ClientProjectQuotation::STATUS_DRAFT,
                'type' => ClientProjectQuotation::TYPE_QUOTATION,
                'quotation_number' => 'Q-V1-OPEN-'.$i,
                'version' => 1,
                'labour' => true,
                'material' => false,
                'items' => [['description' => 'Custom millwork (labour)', 'amount' => 900, 'apply_hst' => true]],
            ]);

            if ($i === 0) {
                $this->get(route('admin.client-projects.quotation.pdf', [$project, $quotation]))
                    ->assertOk()
                    ->assertDontSee('Quotation unavailable');
            }
        }
    }

    public function test_recalculate_button_preview_shows_was_now_notice_with_extras(): void
    {
        $this->actingAsAdmin();
        $path = base_path('tests/fixtures/millwork-cut-plan-v1/heavy_shelf.json');
        $fixture = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($fixture);

        $inputs = [
            'pieces' => [[
                'type' => 'kallax',
                'qty' => 1,
                'width' => 96,
                'height' => 84,
                'depth' => 24,
                'unit' => 'in',
                'columns' => 3,
                'rows' => 5,
                'include_back' => true,
                'edge_banding' => true,
                'finish' => 'none',
            ]],
            'include_install' => true,
            'owner_hourly_rate' => 85,
            '_builder_version' => 1,
        ];
        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-HEAVY',
            'client_name' => 'Legacy Heavy',
            'is_draft' => true,
            'inputs' => $inputs,
            'calculation' => $fixture['calculation'],
            'final_quote_total' => 3565.43,
            'include_commute' => true,
            'commute_amount' => 150,
            'include_debris_removal' => true,
            'debris_removal_amount' => 80,
            'additional_costs' => [[
                'description' => 'Hardware extras',
                'amount' => 100,
                'hst_included' => true,
                'category' => 'materials',
            ]],
        ]);

        $html = $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee('Recalculate with new cut plan')
            ->assertSee('mw-recalc-banner', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="mw-recalc-banner"[^>]*hidden/', $html);

        $payload = [
            'pieces' => $inputs['pieces'],
            'include_install' => 1,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'upgrade_cut_plan' => 1,
            '_builder_version' => 1,
            'include_commute' => 1,
            'commute_trip_percent' => 100,
            'commute_estimated_trips' => 1,
            'commute_round_trip_cost' => 150,
            'include_debris_removal' => 1,
            'debris_removal_amount' => 80,
            'additional_costs' => [[
                'description' => 'Hardware extras',
                'amount' => '100.00',
                'hst_included' => 1,
                'category' => 'materials',
            ]],
        ];

        $preview = $this->post(route('admin.quotations.millwork.calculate-existing', $quote), $payload)
            ->assertOk()
            ->assertJsonPath('recalc_notice.was', 3565.43)
            ->json();

        $this->assertIsArray($preview['recalc_notice'] ?? null);
        $this->assertStringContainsString('3,565.43', (string) $preview['recalc_notice']['was_label']);
        $this->assertMatchesRegularExpression('/\d,\d{3}\.\d{2}/', (string) $preview['recalc_notice']['now_label']);
        $this->assertGreaterThan(3565.43 - 0.01, (float) $preview['recalc_notice']['now']);
    }

    public function test_resaving_legacy_quote_unchanged_keeps_exact_total(): void
    {
        $this->actingAsAdmin();
        $inputs = [
            'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
            'include_install' => true,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            '_builder_version' => 1,
        ];
        $v1 = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $stored = $v1;
        unset($stored['cut_plan']['unplaced'], $stored['cut_plan']['version'], $stored['cut_plan']['builder_version']);
        $total = round((float) ($v1['summary']['grand_total'] ?? 0), 2);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-CLOSET-SAVE',
            'client_name' => 'Legacy Closet',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $stored,
            'final_quote_total' => $total,
        ]);

        $this->put(route('admin.quotations.millwork.update', $quote), [
            'save_intent' => 'publish',
            'client_name' => 'Legacy Closet',
            'pieces' => $inputs['pieces'],
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'include_install' => 1,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'ask_reply_to_accept' => '0',
            '_builder_version' => 1,
        ])->assertRedirect();

        $fresh = $quote->fresh();
        $this->assertEqualsWithDelta($total, (float) $fresh->final_quote_total, 0.001);
        $this->assertTrue($fresh->usesLegacyCutBuilder());
        $this->assertSame(1, $fresh->cutPlanVersion());
        $this->assertSame(1, $fresh->builderVersion());
        $this->assertArrayNotHasKey('unplaced', $fresh->calculation['cut_plan'] ?? []);
    }

    public function test_editing_legacy_quotes_matches_main_calculator_totals(): void
    {
        $this->actingAsAdmin();
        $cases = [
            'closet_labor_override' => [
                'expect' => 2057.70,
                'inputs' => [
                    'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                    'helper_hourly_rate' => 45,
                    'labor_override' => ['cut' => ['hours' => 5]],
                ],
            ],
            'closet_helper' => [
                'expect' => 2424.61,
                'inputs' => [
                    'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
                    'include_install' => true,
                    'include_helper' => true,
                    'owner_hourly_rate' => 85,
                    'helper_hourly_rate' => 45,
                ],
            ],
            'closet_plus_inch' => [
                'expect' => 1672.54,
                'inputs' => [
                    'pieces' => (static function () {
                        $p = MillworkQuotationCalculator::defaultPiece('closet');
                        $p['width'] = (float) $p['width'] + 1;

                        return [$p];
                    })(),
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                ],
            ],
            'tall_edited' => [
                'expect' => 1912.93,
                'inputs' => [
                    'pieces' => [[
                        'type' => 'cabinet',
                        'qty' => 1,
                        'width' => 25,
                        'height' => 84,
                        'depth' => 24,
                        'unit' => 'in',
                        'include_doors' => true,
                        'door_count' => 2,
                        'include_toe_kick' => true,
                        'include_back' => false,
                        'shelf_count' => 1,
                        'edge_banding' => true,
                    ]],
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                ],
            ],
            'closet_extra_materials' => [
                'expect' => null,
                'inputs' => [
                    'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                    'extra_materials' => [[
                        'name' => 'Cleats',
                        'quantity' => 1,
                        'unit' => 'each',
                        'unit_price' => 25,
                    ]],
                    'spare_sheets' => 1,
                    'waste_pct' => 15,
                    'include_building' => true,
                    'building_labor_percent' => 10,
                ],
            ],
            'doors' => [
                'expect' => 620.24,
                'inputs' => [
                    'pieces' => [[
                        'type' => 'cabinet_door',
                        'edge_banding' => true,
                        'sets' => [['qty' => 2, 'width' => 18, 'height' => 80, 'unit' => 'in']],
                    ]],
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                ],
            ],
        ];

        foreach ($cases as $name => $case) {
            $inputs = $case['inputs'];
            $expected = (float) ((new MillworkQuotationCalculator)->calculate($inputs, null, 1)['summary']['grand_total'] ?? 0);
            if ($case['expect'] !== null) {
                $this->assertEqualsWithDelta($case['expect'], $expected, 0.01, $name.' calculator');
            }

            $quote = MillworkQuotation::query()->create([
                'quote_number' => 'MW-LEGACY-'.strtoupper($name),
                'client_name' => 'Legacy '.$name,
                'is_draft' => false,
                'inputs' => [
                    'pieces' => $inputs['pieces'],
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                ],
                'calculation' => [
                    'cut_plan' => ['sheet_count' => 2, 'layouts' => []],
                    'summary' => ['grand_total' => 100.00],
                ],
                'final_quote_total' => 100.00,
            ]);

            $payload = [
                'save_intent' => 'publish',
                'client_name' => 'Legacy '.$name,
                'pieces' => $inputs['pieces'],
                'owner_hourly_rate' => $inputs['owner_hourly_rate'] ?? 85,
                'helper_hourly_rate' => $inputs['helper_hourly_rate'] ?? 45,
                'include_install' => ! empty($inputs['include_install']) ? 1 : 0,
                'include_helper' => ! empty($inputs['include_helper']) ? 1 : 0,
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ];
            if (! empty($inputs['labor_override'])) {
                $payload['labor_override'] = $inputs['labor_override'];
            }
            if (! empty($inputs['extra_materials'])) {
                $payload['extra_materials'] = $inputs['extra_materials'];
            }
            if (isset($inputs['spare_sheets'])) {
                $payload['spare_sheets'] = $inputs['spare_sheets'];
            }
            if (isset($inputs['waste_pct'])) {
                $payload['waste_pct'] = $inputs['waste_pct'];
            }
            if (! empty($inputs['include_building'])) {
                $payload['include_building'] = 1;
                $payload['building_labor_percent'] = $inputs['building_labor_percent'] ?? 10;
            }

            $this->put(route('admin.quotations.millwork.update', $quote), $payload)->assertRedirect();
            $fresh = $quote->fresh();
            $this->assertEqualsWithDelta($expected, (float) $fresh->final_quote_total, 0.01, $name.' saved');
            $this->assertSame(1, $fresh->cutPlanVersion());
            $this->assertArrayNotHasKey('unplaced', $fresh->calculation['cut_plan'] ?? []);
        }
    }

    public function test_client_unplaced_page_uses_plain_wording_and_configured_contact(): void
    {
        \App\Models\CompanySetting::current()->update([
            'contact_phone' => '(555) 010-9999',
            'contact_phone_tel' => '+15550109999',
            'contact_email' => 'shop@example.test',
        ]);

        [$project, $mw, $quotation] = $this->linkedUnplacedQuote();
        $url = \Illuminate\Support\Facades\URL::signedRoute('public.client-project.quotation-pdf', [
            'client_project' => $project,
            'quotation' => $quotation,
        ]);

        $this->get($url)
            ->assertStatus(422)
            ->assertSee('This quote is being updated')
            ->assertSee('Please contact us')
            ->assertSee('(555) 010-9999')
            ->assertSee('shop@example.test')
            ->assertDontSee('Change the size or pick a larger sheet');

        $accept = \Illuminate\Support\Facades\URL::signedRoute('public.quotations.accept', [
            'quotation' => $quotation,
        ]);
        $this->get($accept)
            ->assertOk()
            ->assertSee('This quote is being updated')
            ->assertSee('(555) 010-9999')
            ->assertSee('shop@example.test')
            ->assertDontSee('do not fit on any sheet');
    }

    public function test_available_accept_page_shows_business_contact(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ]);
        $project = ClientProject::factory()->create(['client_name' => 'Acceptable Closet']);
        $mw = MillworkQuotation::query()->create([
            'quote_number' => 'MW-OK-1',
            'client_name' => 'Acceptable Closet',
            'client_project_id' => $project->id,
            'cut_plan_version' => 2,
            'is_draft' => false,
            'inputs' => ['pieces' => [MillworkQuotationCalculator::defaultPiece('closet')]],
            'calculation' => $calc,
            'final_quote_total' => (float) ($calc['summary']['grand_total'] ?? 0),
        ]);
        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'source_id' => $mw->id,
            'title' => 'Custom millwork',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'Q-OK-1',
            'version' => 1,
            'labour' => true,
            'material' => false,
            'items' => [[
                'description' => 'Custom millwork (labour)',
                'amount' => (float) ($calc['summary']['grand_total'] ?? 0),
                'apply_hst' => true,
            ]],
        ]);

        $accept = \Illuminate\Support\Facades\URL::signedRoute('public.quotations.accept', [
            'quotation' => $quotation,
        ]);
        $this->get($accept)
            ->assertOk()
            ->assertSee('Accept this quote')
            ->assertSee('qa-contact', false);
    }

    public function test_vehicles_business_contact_help_is_behind_info_tip(): void
    {
        $this->actingAsAdmin();
        $html = $this->get(route('admin.vehicles.index'))
            ->assertOk()
            ->assertSee('Business contact')
            ->assertSee('admin-info-tip', false)
            ->assertSee('Shown to clients on quote accept pages and the blocked PDF page.')
            ->assertDontSee('aria-label="About About ', false)
            ->getContent();
        $this->assertAdminInfoTipAriaLabel($html, 'Business contact');

        $this->assertStringNotContainsString(
            '<p class="admin-form-help">Shown to clients on quote accept pages and the blocked PDF page.</p>',
            $html
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legacyPresetInputs(): array
    {
        return [
            ['pieces' => [MillworkQuotationCalculator::defaultPiece('closet')]],
            ['pieces' => [[
                'type' => 'kallax',
                'qty' => 1,
                'width' => 96,
                'height' => 84,
                'depth' => 24,
                'unit' => 'in',
                'columns' => 3,
                'rows' => 5,
                'include_back' => true,
            ]]],
            ['pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => 24,
                'height' => 84,
                'depth' => 24,
                'unit' => 'in',
                'include_doors' => true,
                'door_count' => 2,
                'include_toe_kick' => true,
                'include_back' => false,
                'shelf_count' => 1,
            ]]],
            ['pieces' => [[
                'type' => 'cabinet_door',
                'sets' => [['qty' => 2, 'width' => 18, 'height' => 80, 'unit' => 'in']],
            ]]],
        ];
    }

    /**
     * @return array{0: ClientProject, 1: MillworkQuotation, 2: ClientProjectQuotation}
     */
    private function linkedUnplacedQuote(): array
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
        ]);
        $project = ClientProject::factory()->create(['client_name' => 'Unplaced Blocks']);
        $mw = MillworkQuotation::query()->create([
            'quote_number' => 'MW-BLOCK-1',
            'client_name' => 'Unplaced Blocks',
            'client_project_id' => $project->id,
            'cut_plan_version' => 2,
            'is_draft' => false,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
                ]],
            ],
            'calculation' => $calc,
            'final_quote_total' => 0,
        ]);
        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'source_id' => $mw->id,
            'title' => 'Custom millwork',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'Q-BLOCK-1',
            'version' => 1,
            'labour' => true,
            'material' => false,
            'items' => [[
                'description' => 'Custom millwork (labour)',
                'amount' => 0,
                'apply_hst' => true,
            ]],
        ]);

        return [$project, $mw, $quotation];
    }
}

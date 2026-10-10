<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\MillworkQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\AssertsAdminInfoTipAria;

class MillworkQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use AssertsAdminInfoTipAria;

    public function test_hub_and_wizard_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Custom Millwork');

        $this->get(route('admin.quotations.millwork.index'))
            ->assertOk()
            ->assertSee('New millwork quotation');

        $create = $this->get(route('admin.quotations.millwork.create'))
            ->assertOk()
            ->assertSee('1 of 4')
            ->assertSee('Simple shelf')
            ->assertSee('Floating shelf')
            ->assertSee('Kallax shelf')
            ->assertSee('Cabinet')
            ->assertSee('Cabinet door only')
            ->assertSee('Bench seating')
            ->assertSee('Edge banding')
            ->assertSee('>In</option>', false)
            ->assertSee('>Ft</option>', false)
            ->assertSee('Cut plan')
            ->assertSee('Print cut list')
            ->assertDontSee('mw-cut-steps', false)
            ->assertSee('Live breakdown')
            ->assertSee('Create automatically from quotation')
            ->assertSee('Vanity')
            ->assertSee('Closet system')
            ->assertSee('Wainscoting')
            ->assertSee('Mantel')
            ->assertSee('Trim / casing / base')
            ->assertDontSee('Trim / casing / base / crown')
            ->assertSee('Casing + base')
            ->assertSee('data-mw-show="cabinet cabinet_door vanity closet bench-drawers"', false)
            ->assertSee('data-mw-hw="cabinet-family"', false)
            ->assertSee('data-mw-mat="back"', false)
            ->assertSee('ws_ply_14_48x96', false)
            ->assertSee('data-mw-mat="edge-banding"', false)
            ->assertSee('data-mw-mat="paint"', false)
            ->assertSee('data-mw-mat="stain"', false)
            ->assertSee('id="mw-hinge"', false)
            ->assertSee('width_unit', false)
            ->assertSee('hinge_qty', false)
            ->assertSee('Hinges (per unit)')
            ->assertSee('Hinges (total)')
            ->assertSee('mw-delivery-wrap', false)
            ->assertSee('mw-cut-plan-status', false)
            ->assertSee('Sheet size defaults to 4×8 from Price configuration.')
            ->assertSee('Waste % is applied to edge banding')
            ->assertSee('Applied to linear trim and wainscoting rails.')
            ->assertSee('CA$3.80')
            ->assertSee('CA$6.50')
            ->assertSee('Waste %')
            ->assertDontSee('Waste % (linear)')
            ->assertSee('mw-bar-expand', false)
            ->assertSee('Delivery')
            ->assertSee('How many')
            ->assertSee('More options')
            ->assertSee('Labour')
            ->assertSee('mw-totals-bar', false)
            ->assertSee('Clear coat');
        $this->assertAdminInfoTipAriaLabel($create->getContent(), 'Waste %');
        $this->assertAdminInfoTipAriaLabel($create->getContent(), 'Edge waste %');
        $this->assertAdminInfoTipAriaLabel($create->getContent(), 'Finish waste %');
        $this->assertAdminInfoTipAriaLabel($create->getContent(), 'Pieces');
        $this->assertAdminInfoTipAriaLabel($create->getContent(), 'Materials & finish');
        $this->assertAdminInfoTipAriaLabel($create->getContent(), 'Wood sheet');
        $create->assertSee('aria-label="About Pieces"', false);
        $create->assertSee('aria-label="About Wood sheet"', false);
        $create->assertSee('Primary 4×8 (or catalog size).', false);
        $create->assertDontSee('<label for="mw-waste">@include', false);
        $create->assertDontSee('<p class="admin-help">Add each millwork item', false);

        $this->get(route('admin.quotations.millwork.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('Millwork-only catalog')
            ->assertSee('Wood sheets')
            ->assertSee('mw-catalog-table')
            ->assertSee('mw-col-price')
            ->assertSee('mw-col-dims')
            ->assertSee('mw-col-thickness')
            ->assertSee('mw-col-kind')
            ->assertSee('Hinges')
            ->assertSee('Paint')
            ->assertSee('Stain')
            ->assertSee('Edge banding')
            ->assertSee('Coverage (LF)')
            ->assertSee('mw-col-coverage-lf')
            ->assertSee('Blade kerf')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Catalog</button>', false)
            ->assertSee('>Cut settings</button>', false)
            ->assertSee('Save configuration');
    }

    public function test_project_context_sits_between_price_configuration_and_next(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'notes' => 'Need floating shelves in the mudroom.',
        ]);

        $html = $this->get(route('admin.quotations.millwork.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Project Context')
            ->assertSee('Need floating shelves in the mudroom.')
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
        $this->assertLessThan($context, $next);
        $this->assertLessThan($live, $context);
    }

    public function test_preview_returns_cut_plan_and_totals(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.millwork.preview'), [
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => 1,
                'sets' => [
                    ['qty' => 2, 'length' => 41, 'depth' => 35, 'unit' => 'in'],
                ],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'include_install' => 0,
        ])->assertOk()
            ->assertJsonPath('cut_plan.sheet_count', 1)
            ->assertJsonPath('cut_plan.kerf_in', 0.125)
            ->assertJsonStructure([
                'summary' => ['labor_pre_hst', 'materials_pre_hst', 'grand_total'],
                'cut_plan' => ['layouts'],
            ]);
    }

    public function test_store_draft_and_edit_preview(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.millwork.store'), [
            'save_intent' => 'draft',
            'client_name' => 'Millwork Client',
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
            ]],
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ]);

        $quote = MillworkQuotation::query()->where('client_name', 'Millwork Client')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);

        $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee($quote->quote_number);

        $this->post(route('admin.quotations.millwork.calculate-existing', $quote), [
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 2, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.piece_count', 1);

        $this->get(route('admin.quotations.millwork.cut-list', $quote))
            ->assertOk()
            ->assertSee('Details')
            ->assertSee('Sheet 1:')
            ->assertSee($quote->quote_number)
            ->assertDontSee('Rip at')
            ->assertDontSee('mw-cut-steps', false);

        $this->post(route('admin.quotations.millwork.calculate-existing', $quote), [
            '_method' => 'PUT',
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 12, 'depth' => 8, 'unit' => 'in']],
            ]],
        ])->assertStatus(405);
    }

    public function test_materials_and_finish_fields_conditional_visibility(): void
    {
        $this->actingAsAdmin();

        // 1. Fresh create page: default shelf has finish: 'none', include_back: false, edge_banding: true
        $res = $this->get(route('admin.quotations.millwork.create'))
            ->assertOk();
        $html = $res->getContent();

        $this->assertMatchesRegularExpression('/data-mw-mat="back"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/data-mw-mat="paint"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/data-mw-mat="stain"\s+hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-mw-mat="edge-banding"\s+hidden/', $html);

        // 2. Draft quotation with kallax, include_back: 1, finish: paint, edge_banding: 0
        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-COND-1',
            'client_name' => 'Kallax Client',
            'inputs' => [
                'pieces' => [[
                    'type' => 'kallax',
                    'qty' => 1,
                    'width' => 35,
                    'height' => 15,
                    'depth' => 15,
                    'columns' => 3,
                    'rows' => 1,
                    'finish' => 'paint',
                    'edge_banding' => 0,
                    'include_back' => 1,
                    'unit' => 'in',
                ]],
            ],
            'calculation' => [],
            'status' => 'draft',
        ]);

        $resEdit = $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk();
        $htmlEdit = $resEdit->getContent();

        $this->assertDoesNotMatchRegularExpression('/data-mw-mat="back"\s+hidden/', $htmlEdit);
        $this->assertDoesNotMatchRegularExpression('/data-mw-mat="paint"\s+hidden/', $htmlEdit);
        $this->assertMatchesRegularExpression('/data-mw-mat="stain"\s+hidden/', $htmlEdit);
        $this->assertMatchesRegularExpression('/data-mw-mat="edge-banding"\s+hidden/', $htmlEdit);
    }

    public function test_publish_requires_dimensions(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Empty Millwork',
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => '', 'depth' => '', 'unit' => 'in']],
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('calculation');
    }

    public function test_preview_accepts_fraction_and_per_dimension_units(): void
    {
        $this->actingAsAdmin();

        $res = $this->post(route('admin.quotations.millwork.preview'), [
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => '2 1/2',
                'width_unit' => 'ft',
                'height' => '34 1/2',
                'height_unit' => 'in',
                'depth' => 24,
                'depth_unit' => 'in',
                'include_doors' => 0,
                'edge_banding' => 0,
                'shelf_count' => 0,
            ]],
            'owner_hourly_rate' => 80,
            'include_install' => 0,
        ])->assertOk();

        $this->assertSame([], $res->json('cut_plan.unplaced'));
        $this->assertGreaterThan(0, (float) $res->json('summary.grand_total'));
    }

    public function test_publish_rejects_dimension_over_limit(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Huge cabinet',
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => 300,
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => 0,
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('calculation');
    }

    public function test_v1_draft_accepts_stored_scientific_set_value(): void
    {
        $this->actingAsAdmin();

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-1E2',
            'client_name' => 'Sci draft',
            'is_draft' => true,
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 36, 'depth' => '1e2', 'unit' => 'in']],
                ]],
            ],
            'calculation' => [],
        ]);

        $this->from(route('admin.quotations.millwork.edit', $quote))
            ->put(route('admin.quotations.millwork.update', $quote), [
                'save_intent' => 'draft',
                'client_name' => 'Sci draft',
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 36, 'depth' => '1e2', 'unit' => 'in']],
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_v2_draft_accepts_dimension_over_limit(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'draft',
                'client_name' => 'Tall closet draft',
                'pieces' => [[
                    'type' => 'closet',
                    'qty' => 1,
                    'width' => 22,
                    'height' => 8,
                    'depth' => 2,
                    'unit' => 'ft',
                    'include_doors' => 0,
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull(MillworkQuotation::query()->where('client_name', 'Tall closet draft')->first());
    }

    public function test_draft_save_accepts_stored_zero_size_fields(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'draft',
                'client_name' => 'Zero draft',
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => 0,
                    'height' => '0',
                    'depth' => 24,
                    'length' => '0.0',
                    'thickness' => 0,
                    'unit' => 'in',
                    'include_doors' => 0,
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $quote = MillworkQuotation::query()->where('client_name', 'Zero draft')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
    }

    public function test_save_flags_thousands_comma_as_inline_error(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'draft',
                'client_name' => 'Comma thousands',
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => '1,500',
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => 0,
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('pieces.0.width');
    }

    public function test_draft_save_rejects_scientific_dimension_without_500(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'draft',
                'client_name' => 'Huge exp',
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => '1E400',
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => 0,
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('pieces.0.width');
    }

    public function test_publish_rejects_second_unsized_piece(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Mixed sizes',
                'pieces' => [
                    [
                        'type' => 'shelf',
                        'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
                    ],
                    [
                        'type' => 'shelf',
                        'sets' => [['qty' => 1, 'length' => '2\' 6"oops', 'depth' => 12, 'unit' => 'in']],
                    ],
                ],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors(['pieces.1.sets.0.length', 'calculation']);
    }

    public function test_preview_of_scientific_dimension_returns_json(): void
    {
        $this->actingAsAdmin();

        $res = $this->post(route('admin.quotations.millwork.preview'), [
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => '1E400',
                'height' => 34.5,
                'depth' => 24,
                'include_doors' => 0,
            ]],
            'owner_hourly_rate' => 80,
            'include_install' => 0,
        ]);

        $this->assertNotSame(500, $res->status());
        $res->assertOk();
        $this->assertIsArray($res->json());
        $this->assertTrue(is_finite((float) $res->json('summary.grand_total')));
    }

    public function test_v2_draft_resave_keeps_qty_and_spare_caps(): void
    {
        $this->actingAsAdmin();

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V2-QTY',
            'client_name' => 'Qty cap',
            'is_draft' => true,
            'cut_plan_version' => 2,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'qty' => 250,
                    'edge_banding' => false,
                    'sets' => [['qty' => 250, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
                ]],
                'spare_sheets' => 60,
                'include_install' => false,
                'owner_hourly_rate' => 85,
            ],
            'calculation' => [],
        ]);

        $this->from(route('admin.quotations.millwork.edit', $quote))
            ->put(route('admin.quotations.millwork.update', $quote), [
                'save_intent' => 'draft',
                'client_name' => 'Qty cap',
                'pieces' => [[
                    'type' => 'shelf',
                    'qty' => 250,
                    'edge_banding' => 0,
                    'sets' => [['qty' => 250, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
                ]],
                'spare_sheets' => 60,
                'include_install' => 0,
                'owner_hourly_rate' => 85,
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame(2, (int) $quote->cut_plan_version);
        $this->assertSame(250, (int) ($quote->inputs['pieces'][0]['qty'] ?? 0));
        $this->assertSame(250, (int) ($quote->inputs['pieces'][0]['sets'][0]['qty'] ?? 0));
        $this->assertSame(60, (int) ($quote->inputs['spare_sheets'] ?? 0));
    }

    public function test_v1_edit_hides_hinge_qty_and_keeps_in_ft_controls(): void
    {
        $this->actingAsAdmin();

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-EDIT',
            'client_name' => 'Legacy Edit',
            'is_draft' => true,
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => 36,
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => true,
                    'door_count' => 2,
                ]],
            ],
            'calculation' => [],
        ]);

        $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee('width_unit', false)
            ->assertSee('>In</option>', false)
            ->assertDontSee('name="pieces[0][hinge_qty]"', false);
    }

    public function test_script_clears_busy_state_on_persisted_pageshow(): void
    {
        $js = file_get_contents(public_path('js/admin-millwork-quotation.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString("addEventListener('pageshow'", $js);
        $this->assertStringContainsString('e.persisted', $js);
        $this->assertStringContainsString('setSaveBusy(false)', $js);
        $this->assertStringContainsString('function bindBeforeUnload', $js);
        $this->assertStringContainsString("form.getAttribute('data-quotation-user-edited') === '1'", $js);
        $this->assertStringContainsString('window.allowUnload', $js);
        $this->assertStringContainsString('window.AdminQuoteAllowUnload', $js);
        $this->assertStringContainsString('Preview timed out', $js);
        $this->assertStringContainsString('window.MillworkDimensionParser', $js);
        $this->assertStringContainsString('applyFieldErrors', $js);
        $this->assertStringContainsString('is-invalid', $js);
        $this->assertStringContainsString('field_errors', $js);
    }
}

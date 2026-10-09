<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProjectQuotation;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FenceExistingPostLabourCreditTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_config_page_exposes_existing_post_labour_credit_money_field(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.quotations.fence.config'));

        $response->assertOk();
        $response->assertSee('Labour credit per existing post', false);
        $response->assertSee('name="existing_post_labour_credit"', false);
        $response->assertSee('cp-money-input', false);
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*class="[^"]*cp-money-input/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*data-cp-money-entry="direct"/',
            $html
        );
        $this->assertStringContainsString('aria-label="About Labour credit"', $html);
    }

    public function test_formula_update_rejects_negative_existing_post_credit(): void
    {
        $this->actingAsAdmin();
        $settings = FenceFormulaSetting::current();

        $response = $this->from(route('admin.quotations.fence.config'))
            ->put(route('admin.quotations.fence.config.formula.update'), $this->formulaPayload([
                'existing_post_labour_credit' => -1,
            ]));

        $response->assertRedirect(route('admin.quotations.fence.config'));
        $response->assertSessionHasErrors('existing_post_labour_credit');
        $this->assertEqualsWithDelta(30.0, (float) $settings->fresh()->existing_post_labour_credit, 0.001);
    }

    public function test_formula_update_saves_existing_post_credit(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('admin.quotations.fence.config.formula.update'), $this->formulaPayload([
            'existing_post_labour_credit' => 45.50,
        ]));

        $response->assertRedirect();
        $this->assertEqualsWithDelta(45.50, (float) FenceFormulaSetting::current()->existing_post_labour_credit, 0.001);
    }

    public function test_preview_applies_existing_post_labour_credit(): void
    {
        $this->actingAsAdmin();

        $base = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24]],
            'existing_post_labour_credit' => 30,
        ]))->assertOk()->json();

        $left = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'existing_post_labour_credit' => 30,
        ]))->assertOk()->json();

        $this->assertSame(0, $base['totals']['existing_post_credit']['count']);
        $this->assertSame(1, $left['totals']['existing_post_credit']['count']);
        $this->assertEqualsWithDelta(30.0, $left['totals']['existing_post_credit']['amount'], 0.001);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 30,
            $left['totals']['labour_subtotal'],
            0.02
        );
    }

    public function test_new_quote_persists_config_credit_and_old_quote_stays_zero_until_reprice(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 30,
        ]))->assertRedirect();

        $new = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($new);
        $this->assertEqualsWithDelta(30.0, (float) $new->existing_post_labour_credit, 0.001);
        $this->assertSame(1, $new->calculation['totals']['existing_post_credit']['count']);

        $old = FenceQuotation::create([
            'quote_number' => 'FQ-TEST-OLD',
            'client_name' => 'Legacy fence',
            'fence_height_ft' => 6,
            'board_style' => FenceQuotation::BOARD_STYLE_STANDARD,
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 0,
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
            'calculation' => ['totals' => ['labour_subtotal' => 1320]],
            'materials_subtotal' => 0,
            'hst' => 0,
            'labour_total' => 1320,
            'grand_total' => 1320,
            'include_labour_hst' => true,
        ]);

        $this->put(route('admin.quotations.fence.update', $old), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 0,
        ]))->assertRedirect();

        $old->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $old->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(24 * 55, (float) $old->labour_total, 0.02);

        $repriceOnly = $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'reprice' => '1',
        ]);
        unset($repriceOnly['existing_post_labour_credit']);
        $this->put(route('admin.quotations.fence.update', $old), $repriceOnly)->assertRedirect();

        $old->refresh();
        $this->assertEqualsWithDelta(30.0, (float) $old->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(24 * 55 - 30, (float) $old->labour_total, 0.02);
    }

    public function test_editor_exposes_visible_existing_post_credit_money_field(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.fence.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Labour credit per existing post', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="existing_post_labour_credit"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*class="[^"]*cp-money-input/',
            $html
        );
        $this->assertSame(1, preg_match(
            '/<input\b[^>]*\bid="fq-existing-post-credit"[^>]*>/',
            $html,
            $creditInput
        ));
        $this->assertMatchesRegularExpression('/\brequired\b/', $creditInput[0]);
        $this->assertMatchesRegularExpression(
            '/\bdata-cp-money-entry="direct"/',
            $creditInput[0]
        );
        $this->assertStringContainsString('admin-info-tip', $html);
        $this->assertStringContainsString('aria-label="About Labour credit"', $html);
    }

    public function test_preview_zero_string_means_no_credit(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $json = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'existing_post_labour_credit' => '0',
        ]))->assertOk()->json();

        $this->assertEqualsWithDelta(0.0, (float) $json['inputs']['existing_post_labour_credit'], 0.001);
        $this->assertSame(1, $json['totals']['existing_post_credit']['count']);
        $this->assertEqualsWithDelta(0.0, $json['totals']['existing_post_credit']['amount'], 0.001);
        $this->assertEqualsWithDelta(24 * 55, $json['totals']['labour_subtotal'], 0.02);
    }

    public function test_preview_and_update_use_stored_credit_when_field_omitted(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $old = FenceQuotation::create([
            'quote_number' => 'FQ-TEST-FALLBACK',
            'client_name' => 'Stored credit',
            'fence_height_ft' => 6,
            'board_style' => FenceQuotation::BOARD_STYLE_STANDARD,
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 0,
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
            'calculation' => ['totals' => ['labour_subtotal' => 1320]],
            'materials_subtotal' => 0,
            'hst' => 0,
            'labour_total' => 1320,
            'grand_total' => 1320,
            'include_labour_hst' => true,
        ]);

        $omitted = $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'fence_quotation_id' => $old->id,
        ]);
        unset($omitted['existing_post_labour_credit']);

        $preview = $this->postJson(route('admin.quotations.fence.preview'), $omitted)
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta(0.0, (float) $preview['inputs']['existing_post_labour_credit'], 0.001);
        $this->assertEqualsWithDelta(24 * 55, $preview['totals']['labour_subtotal'], 0.02);

        $newOmitted = $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
        ]);
        unset($newOmitted['existing_post_labour_credit']);
        $newPreview = $this->postJson(route('admin.quotations.fence.preview'), $newOmitted)
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta(30.0, (float) $newPreview['inputs']['existing_post_labour_credit'], 0.001);
        $this->assertEqualsWithDelta(24 * 55 - 30, $newPreview['totals']['labour_subtotal'], 0.02);

        $update = $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
        ]);
        unset($update['existing_post_labour_credit']);
        $this->put(route('admin.quotations.fence.update', $old), $update)->assertRedirect();
        $old->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $old->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(24 * 55, (float) $old->labour_total, 0.02);
    }

    public function test_editor_restores_credit_after_validation_error(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.fence.create'))
            ->post(route('admin.quotations.fence.store'), $this->storePayload([
                'client_name' => '',
                'existing_post_labour_credit' => '45.50',
            ]));

        $response->assertRedirect(route('admin.quotations.fence.create'));
        $response->assertSessionHasErrors('client_name');
        $this->assertSame('45.50', session()->getOldInput('existing_post_labour_credit'));

        $html = $this->get(route('admin.quotations.fence.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*value="45.50"/',
            $html
        );
    }

    public function test_editor_rejects_negative_or_non_numeric_credit(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.fence.create'))
            ->post(route('admin.quotations.fence.store'), $this->storePayload([
                'existing_post_labour_credit' => -5,
            ]))
            ->assertRedirect(route('admin.quotations.fence.create'))
            ->assertSessionHasErrors('existing_post_labour_credit');

        $this->from(route('admin.quotations.fence.create'))
            ->post(route('admin.quotations.fence.store'), $this->storePayload([
                'existing_post_labour_credit' => 'abc',
            ]))
            ->assertRedirect(route('admin.quotations.fence.create'))
            ->assertSessionHasErrors('existing_post_labour_credit');

        $this->from(route('admin.quotations.fence.create'))
            ->post(route('admin.quotations.fence.store'), $this->storePayload([
                'existing_post_labour_credit' => [],
            ]))
            ->assertRedirect(route('admin.quotations.fence.create'))
            ->assertSessionHasErrors('existing_post_labour_credit');

        $this->assertSame(0, FenceQuotation::query()->count());
    }

    public function test_preview_clamped_credit_amount_is_the_actual_deduction(): void
    {
        $this->actingAsAdmin();

        $json = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [[
                'length_ft' => 8,
                'already_have_post_left' => 1,
                'already_have_post_right' => 1,
            ]],
            'labour_rate_per_ft' => 10,
            'existing_post_labour_credit' => 999,
        ]))->assertOk()->json();

        $this->assertSame(2, $json['totals']['existing_post_credit']['count']);
        $this->assertEqualsWithDelta(80.0, $json['totals']['labour_before_credit'], 0.001);
        $this->assertEqualsWithDelta(80.0, $json['totals']['existing_post_credit']['amount'], 0.001);
        $this->assertEqualsWithDelta(0.0, $json['totals']['labour_subtotal'], 0.001);
    }

    public function test_preview_applies_decimal_existing_post_credit(): void
    {
        $this->actingAsAdmin();

        $json = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'existing_post_labour_credit' => 12.50,
        ]))->assertOk()->json();

        $this->assertSame(1, $json['totals']['existing_post_credit']['count']);
        $this->assertEqualsWithDelta(12.50, (float) $json['inputs']['existing_post_labour_credit'], 0.001);
        $this->assertEqualsWithDelta(12.50, $json['totals']['existing_post_credit']['amount'], 0.001);
        $this->assertEqualsWithDelta(24 * 55 - 12.50, $json['totals']['labour_subtotal'], 0.02);
    }

    public function test_editor_total_matches_pdf_total_with_commute_debris_and_credit(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 30,
            'include_commute' => '1',
            'commute_round_trip_cost' => 40,
            'commute_trip_percent' => 100,
            'commute_estimated_trips' => 1,
            'include_debris_removal' => '1',
            'debris_removal_amount' => 50,
        ]))->assertRedirect();

        $quote = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEqualsWithDelta(30.0, $quote->calculation['totals']['existing_post_credit']['amount'], 0.02);
        $this->assertEqualsWithDelta(40.0, (float) $quote->commute_amount, 0.02);
        $this->assertEqualsWithDelta(50.0, (float) $quote->debris_removal_amount, 0.02);

        $editorTotal = round(
            (float) $quote->calculation['totals']['grand_total']
            + (float) $quote->commute_amount
            + (float) $quote->debris_removal_amount,
            2
        );
        $this->assertEqualsWithDelta($editorTotal, (float) $quote->grand_total, 0.02);

        $items = $quote->projectQuotationLineItems();
        $commuteLine = CommuteExpenseService::projectLineItem(
            (float) $quote->commute_amount,
            (int) $quote->commute_estimated_trips
        );
        $debrisLine = DebrisRemovalExpenseService::projectLineItem((float) $quote->debris_removal_amount);
        if ($commuteLine) {
            $items[] = $commuteLine;
        }
        if ($debrisLine) {
            $items[] = $debrisLine;
        }

        $pdf = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'items' => $items,
        ]);
        $this->assertEqualsWithDelta($editorTotal, $pdf->pdfGrandTotal(), 0.02);
    }

    public function test_blank_credit_preview_total_equals_saved_total(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $preview = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'existing_post_labour_credit' => '',
        ]))->assertOk()->json();

        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => '',
        ]))->assertRedirect();

        $quote = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEqualsWithDelta(30.0, (float) $preview['inputs']['existing_post_labour_credit'], 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $quote->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(
            $preview['totals']['grand_total'],
            $quote->calculation['totals']['grand_total'],
            0.02
        );
        $this->assertEqualsWithDelta(
            $preview['totals']['labour_subtotal'],
            (float) $quote->labour_total,
            0.02
        );
    }

    public function test_backfilled_quote_stays_unchanged_without_reprice(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $old = FenceQuotation::create([
            'quote_number' => 'FQ-TEST-BACKFILL',
            'client_name' => 'Backfilled fence',
            'fence_height_ft' => 6,
            'board_style' => FenceQuotation::BOARD_STYLE_STANDARD,
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 0,
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
            'calculation' => ['totals' => ['labour_subtotal' => 1320]],
            'materials_subtotal' => 0,
            'hst' => 0,
            'labour_total' => 1320,
            'grand_total' => 1320,
            'include_labour_hst' => true,
        ]);

        $this->put(route('admin.quotations.fence.update', $old), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 0,
        ]))->assertRedirect();

        $old->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $old->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(24 * 55, (float) $old->labour_total, 0.02);
        $this->assertEqualsWithDelta(0.0, $old->calculation['totals']['existing_post_credit']['amount'], 0.001);
    }

    public function test_editor_total_matches_pdf_with_labour_hst_off_and_additional_costs(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 30,
            'include_labour_hst' => '0',
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'additional_costs' => [
                [
                    'description' => 'Extra labour',
                    'amount' => 100,
                    'hst_included' => '0',
                    'category' => 'labour',
                ],
                [
                    'description' => 'Site setup',
                    'amount' => 25,
                    'hst_included' => '0',
                    'category' => 'labour',
                ],
            ],
        ]))->assertRedirect();

        $quote = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->include_labour_hst);
        $this->assertEqualsWithDelta(30.0, $quote->calculation['totals']['existing_post_credit']['amount'], 0.02);

        $editorTotal = (float) $quote->grand_total;
        $items = $quote->projectQuotationLineItems();
        foreach (\App\Services\AdditionalCostsService::projectLineItems($quote->additional_costs ?? []) as $line) {
            $items[] = $line;
        }

        $pdf = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'items' => $items,
        ]);
        $this->assertEqualsWithDelta($editorTotal, $pdf->pdfGrandTotal(), 0.02);
    }

    public function test_reprice_then_edit_credit_saves_catalogue_prices_matching_preview(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 0,
        ]))->assertRedirect();

        $quote = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEqualsWithDelta(0.0, (float) $quote->existing_post_labour_credit, 0.001);
        $this->assertSame('catalogue', $quote->calculation['price_source'] ?? null);

        $frozenPrices = collect($quote->calculation['materials'] ?? [])
            ->filter(fn ($row) => is_array($row) && ($row['key'] ?? '') !== '')
            ->mapWithKeys(fn (array $row) => [(string) $row['key'] => (float) $row['unit_price']])
            ->all();
        $this->assertNotEmpty($frozenPrices);

        foreach (FenceMaterial::query()->with('material')->get() as $row) {
            if ($row->material) {
                $row->material->update([
                    'price_cad' => round((float) $row->material->price_cad + 12.34, 2),
                ]);
            } else {
                $row->update([
                    'price_cad' => round((float) $row->price_cad + 12.34, 2),
                ]);
            }
        }

        $payload = [
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'existing_post_labour_credit' => 45,
            'reprice' => '1',
            'fence_quotation_id' => $quote->id,
        ];

        $preview = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload($payload))
            ->assertOk()
            ->json();

        $this->assertSame('catalogue', $preview['price_source'] ?? null);
        $this->assertEqualsWithDelta(45.0, (float) $preview['inputs']['existing_post_labour_credit'], 0.001);

        $this->put(
            route('admin.quotations.fence.update', $quote),
            $this->storePayload($payload)
        )->assertRedirect();

        $quote->refresh();
        $this->assertEqualsWithDelta(45.0, (float) $quote->existing_post_labour_credit, 0.001);
        $this->assertSame('catalogue', $quote->calculation['price_source'] ?? null);
        $this->assertEqualsWithDelta(
            $preview['totals']['labour_subtotal'],
            (float) $quote->labour_total,
            0.02
        );
        $this->assertEqualsWithDelta(
            $preview['totals']['grand_total'],
            $quote->calculation['totals']['grand_total'],
            0.02
        );
        $this->assertEqualsWithDelta(
            $preview['totals']['materials_subtotal'],
            (float) $quote->materials_subtotal,
            0.02
        );

        $savedPrices = collect($quote->calculation['materials'] ?? [])
            ->filter(fn ($row) => is_array($row) && ($row['key'] ?? '') !== '')
            ->mapWithKeys(fn (array $row) => [(string) $row['key'] => (float) $row['unit_price']])
            ->all();
        $previewPrices = collect($preview['materials'] ?? [])
            ->filter(fn ($row) => is_array($row) && ($row['key'] ?? '') !== '')
            ->mapWithKeys(fn (array $row) => [(string) $row['key'] => (float) $row['unit_price']])
            ->all();

        $this->assertSame(array_keys($previewPrices), array_keys($savedPrices));
        $changed = false;
        foreach ($previewPrices as $key => $livePrice) {
            $this->assertEqualsWithDelta($livePrice, $savedPrices[$key], 0.001, $key);
            if (array_key_exists($key, $frozenPrices) && abs($livePrice - $frozenPrices[$key]) > 0.009) {
                $changed = true;
            }
        }
        $this->assertTrue($changed, 'Re-price should persist live catalogue unit prices, not the frozen snapshot.');
    }

    public function test_labour_minus_credit_equals_net_including_additional_labour(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update([
            'existing_post_labour_credit' => 30,
            'labour_per_additional_gate' => 80,
        ]);

        $preview = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'gate_count' => 2,
            'existing_post_labour_credit' => 30,
        ]))->assertOk()->json();

        $gross = (float) $preview['totals']['labour_before_credit'];
        $credit = (float) $preview['totals']['existing_post_credit']['amount'];
        $net = (float) $preview['totals']['labour_subtotal'];
        $this->assertEqualsWithDelta(24 * 55 + 80, $gross, 0.02);
        $this->assertEqualsWithDelta(30.0, $credit, 0.001);
        $this->assertEqualsWithDelta($gross - $credit, $net, 0.001);

        $additionalLabour = 100.0;
        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'gate_count' => 2,
            'existing_post_labour_credit' => 30,
            'include_labour_hst' => '0',
            'additional_costs' => [[
                'description' => 'Extra crew',
                'amount' => $additionalLabour,
                'hst_included' => '0',
                'category' => 'labour',
            ]],
        ]))->assertRedirect();

        $quote = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $editorLabour = (float) $quote->calculation['totals']['labour_before_credit'] + $additionalLabour;
        $editorNet = (float) $quote->calculation['totals']['labour_subtotal'] + $additionalLabour;
        $this->assertEqualsWithDelta(
            $editorLabour - (float) $quote->calculation['totals']['existing_post_credit']['amount'],
            $editorNet,
            0.02
        );
        $this->assertEqualsWithDelta($gross + $additionalLabour, $editorLabour, 0.02);
        $this->assertEqualsWithDelta($net + $additionalLabour, $editorNet, 0.02);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formulaPayload(array $overrides = []): array
    {
        $settings = FenceFormulaSetting::current();

        return array_merge([
            'labour_rate_per_ft' => (float) $settings->labour_rate_per_ft,
            'labour_rate_8ft_per_ft' => (float) ($settings->labour_rate_8ft_per_ft ?? $settings->labour_rate_per_ft),
            'gate_rails' => (int) $settings->gate_rails,
            'concrete_per_post_6ft' => (float) $settings->concrete_per_post_6ft,
            'concrete_per_post_8ft' => (float) $settings->concrete_per_post_8ft,
            'labour_per_additional_gate' => (float) ($settings->labour_per_additional_gate ?? 0),
            'existing_post_labour_credit' => 30,
            'rails_per_section_6ft' => (int) $settings->rails_per_section_6ft,
            'rails_per_section_8ft' => (int) $settings->rails_per_section_8ft,
            'nails_per_picket' => (int) $settings->nails_per_picket,
            'board_width_in' => (float) $settings->board_width_in,
            'overlapping_in' => (float) ($settings->overlapping_in ?? 0),
            'max_post_spacing_ft' => (float) $settings->max_post_spacing_ft,
            'concrete_per_post_6x6_6ft' => (float) ($settings->concrete_per_post_6x6_6ft ?? 2.5),
            'concrete_per_post_6x6_8ft' => (float) ($settings->concrete_per_post_6x6_8ft ?? 3.5),
            'waste_extra_posts' => (int) ($settings->waste_extra_posts ?? 1),
            'waste_pickets_per_section' => (int) ($settings->waste_pickets_per_section ?? 1),
            'waste_extra_rails' => (int) ($settings->waste_extra_rails ?? 3),
            'waste_extra_brackets' => (int) ($settings->waste_extra_brackets ?? 6),
            'waste_extra_caps' => (int) ($settings->waste_extra_caps ?? 1),
            'waste_concrete_posts_per_extra_bag' => (int) ($settings->waste_concrete_posts_per_extra_bag ?? 3),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function previewPayload(array $overrides = []): array
    {
        return array_merge([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 30,
            'include_labour_hst' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge($this->previewPayload(), [
            'client_name' => 'Test fence client',
            'payment_installments' => 1,
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'ask_reply_to_accept' => '0',
        ], $overrides);
    }
}

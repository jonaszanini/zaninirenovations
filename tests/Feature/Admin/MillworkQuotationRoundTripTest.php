<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\MillworkQuotation;
use App\Services\MillworkQuotationCalculator;
use Symfony\Component\DomCrawler\Crawler;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class MillworkQuotationRoundTripTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_save_edit_preserves_mixed_millwork_job(): void
    {
        $this->actingAsAdmin();

        $payload = $this->samplePayload('Pantry Client');

        $this->post(route('admin.quotations.millwork.store'), $payload)
            ->assertRedirect();

        $quote = MillworkQuotation::query()->where('client_name', 'Pantry Client')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
        $this->assertCount(3, $quote->inputs['pieces'] ?? []);
        $this->assertSame('cabinet', $quote->inputs['pieces'][0]['type']);
        $this->assertSame('closet', $quote->inputs['pieces'][1]['type']);
        $this->assertSame('trim', $quote->inputs['pieces'][2]['type']);
        $this->assertSame('Kitchen', $quote->inputs['pieces'][0]['room']);
        $this->assertSame(10.0, (float) $quote->inputs['waste_pct']);
        $this->assertEqualsWithDelta(0.13, $quote->calculation['summary']['hst'] / max(0.01, $quote->calculation['summary']['subtotal_pre_hst']), 0.02);

        $edit = $this->get(route('admin.quotations.millwork.edit', $quote));
        $edit->assertOk()
            ->assertSee($quote->quote_number)
            ->assertSee('Pantry Client')
            ->assertSee('Kitchen pantry')
            ->assertSee('Reach-in closet')
            ->assertSee('Foyer trim')
            ->assertSee('mw-totals-bar', false);

        $payload['client_name'] = 'Pantry Client';
        $payload['pieces'][0]['width'] = 42;
        $payload['_method'] = 'PUT';

        $this->put(route('admin.quotations.millwork.update', $quote), $payload)
            ->assertRedirect();

        $fresh = $quote->fresh();
        $this->assertSame(42.0, (float) $fresh->inputs['pieces'][0]['width']);
        $this->assertSame('closet', $fresh->inputs['pieces'][1]['type']);
        $this->assertSame(72.0, (float) $fresh->inputs['pieces'][1]['width']);
        $this->assertGreaterThan(0, (float) $fresh->final_quote_total);
        $this->assertSame(2, (int) $fresh->version);
    }

    public function test_legacy_shelf_quote_still_opens_and_recalculates(): void
    {
        $this->actingAsAdmin();

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-LEGACY-1',
            'client_name' => 'Legacy Millwork',
            'is_draft' => false,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'edge_banding' => true,
                    'finish' => 'none',
                    'fastener' => 'screws',
                    'sets' => [['qty' => 2, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
                ]],
                'include_install' => false,
                'owner_hourly_rate' => 80,
            ],
            'calculation' => ['summary' => ['grand_total' => 100]],
            'final_quote_total' => 100,
        ]);

        $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee('Legacy Millwork')
            ->assertSee('Simple shelf');

        $this->put(route('admin.quotations.millwork.update', $quote), [
            'save_intent' => 'publish',
            'client_name' => 'Legacy Millwork',
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => 1,
                'sets' => [['qty' => 2, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'include_install' => 0,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'ask_reply_to_accept' => '0',
        ])->assertRedirect();

        $fresh = $quote->fresh();
        $this->assertSame('shelf', $fresh->inputs['pieces'][0]['type']);
        $this->assertGreaterThan(0, (float) $fresh->final_quote_total);
    }

    public function test_v1_edit_with_fractions_and_ft_matches_inch_total(): void
    {
        $this->actingAsAdmin();

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-FRAC',
            'client_name' => 'Legacy Fraction',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => 36,
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                ]],
            ],
            'calculation' => ['summary' => ['grand_total' => 0]],
            'final_quote_total' => 0,
        ]);

        $common = [
            'save_intent' => 'publish',
            'client_name' => 'Legacy Fraction',
            '_cut_plan_version' => 1,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'include_install' => 0,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'ask_reply_to_accept' => '0',
        ];
        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'include_doors' => 0,
            'include_back' => 0,
            'edge_banding' => 0,
            'shelf_count' => 0,
            'unit' => 'in',
        ];

        $this->put(route('admin.quotations.millwork.update', $quote), array_merge($common, [
            'pieces' => [array_merge($piece, [
                'width' => 36,
                'height' => 34.5,
                'depth' => 24,
            ])],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $inchTotal = (float) $quote->fresh()->final_quote_total;
        $this->assertGreaterThan(0, $inchTotal);

        $this->put(route('admin.quotations.millwork.update', $quote), array_merge($common, [
            'pieces' => [array_merge($piece, [
                'width' => '3',
                'width_unit' => 'ft',
                'height' => '34 1/2',
                'height_unit' => 'in',
                'depth' => '2 ft',
                'depth_unit' => 'in',
            ])],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame(1, (int) $fresh->cut_plan_version);
        $this->assertEqualsWithDelta($inchTotal, (float) $fresh->final_quote_total, 0.05);
    }

    public function test_v1_quote_resaves_untouched_at_the_same_total(): void
    {
        $this->actingAsAdmin();

        $cases = [
            'trim-ft' => [
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'length' => 20,
                'length_unit' => 'ft',
                'openings' => 3,
                'width' => 36,
                'width_unit' => 'ft',
                'height' => 80,
                'height_unit' => 'ft',
                'qty' => 1,
                'edge_banding' => 0,
            ],
            'cabinet-in' => [
                'type' => 'cabinet',
                'qty' => 1,
                'width' => 36,
                'width_unit' => 'in',
                'height' => 34.5,
                'height_unit' => 'in',
                'depth' => 24,
                'depth_unit' => 'in',
                'unit' => 'in',
                'include_doors' => 0,
                'include_back' => 0,
                'include_toe_kick' => 0,
                'edge_banding' => 0,
                'shelf_count' => 0,
            ],
            'cabinet-ft' => [
                'type' => 'cabinet',
                'qty' => 1,
                'width' => 3,
                'width_unit' => 'ft',
                'height' => 3,
                'height_unit' => 'ft',
                'depth' => 2,
                'depth_unit' => 'ft',
                'unit' => 'ft',
                'include_doors' => 0,
                'include_back' => 0,
                'include_toe_kick' => 0,
                'edge_banding' => 0,
                'shelf_count' => 0,
            ],
            'shelf-in' => [
                'type' => 'shelf',
                'edge_banding' => 0,
                'sets' => [['qty' => 2, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ],
        ];

        foreach ($cases as $name => $piece) {
            $inputs = [
                'pieces' => [$piece],
                'include_install' => false,
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ];
            $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
            $total = round((float) $calc['summary']['grand_total'], 2);
            $this->assertGreaterThan(0, $total, $name);

            $quote = MillworkQuotation::query()->create([
                'quote_number' => 'MW-V1-'.$name,
                'client_name' => 'V1 '.$name,
                'is_draft' => false,
                'cut_plan_version' => 1,
                'inputs' => $inputs,
                'calculation' => $calc,
                'final_quote_total' => $total,
            ]);

            $this->get(route('admin.quotations.millwork.edit', $quote))
                ->assertOk()
                ->assertSee($quote->quote_number);
            $this->assertEqualsWithDelta($total, (float) $quote->fresh()->final_quote_total, 0.001, $name.' GET must not change the stored total');

            $preview = $this->post(route('admin.quotations.millwork.calculate-existing', $quote), array_merge($piece, [
                'pieces' => [$piece],
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
                'include_install' => 0,
            ]));
            $preview->assertOk();
            $this->assertEqualsWithDelta(
                $total,
                (float) $preview->json('summary.grand_total'),
                0.05,
                $name.' opening the editor must keep the live total'
            );

            $this->put(route('admin.quotations.millwork.update', $quote), [
                'save_intent' => 'publish',
                'client_name' => 'V1 '.$name,
                '_cut_plan_version' => 1,
                'pieces' => [$piece],
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
                'include_install' => 0,
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()->assertSessionHasNoErrors();

            $fresh = $quote->fresh();
            $this->assertSame(1, (int) $fresh->cut_plan_version, $name);
            $this->assertEqualsWithDelta($total, (float) $fresh->final_quote_total, 0.05, $name.' re-save');
        }
    }

    public function test_untouched_v1_text_size_resave_keeps_exact_price(): void
    {
        $this->actingAsAdmin();

        $cases = [
            '23 1/2' => [
                'width' => '23 1/2',
                'height' => 34.5,
                'depth' => 24,
            ],
            '2 ft' => [
                'width' => '2 ft',
                'height' => 34.5,
                'depth' => 24,
            ],
            '762mm' => [
                'width' => 36,
                'height' => 34.5,
                'depth' => '762mm',
            ],
        ];

        foreach ($cases as $name => $dims) {
            $piece = array_merge([
                'type' => 'cabinet',
                'qty' => 1,
                'unit' => 'in',
                'include_doors' => false,
                'include_back' => false,
                'include_toe_kick' => false,
                'edge_banding' => false,
                'shelf_count' => 0,
            ], $dims);
            $this->assertArrayNotHasKey('stock_length_ft', $piece, $name);
            $this->assertArrayNotHasKey('width_unit', $piece, $name);
            $this->assertArrayNotHasKey('sets', $piece, $name);

            $inputs = [
                'pieces' => [$piece],
                'include_install' => false,
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ];
            $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
            $total = round((float) $calc['summary']['grand_total'], 2);
            $this->assertGreaterThan(0, $total, $name);

            $quote = MillworkQuotation::query()->create([
                'quote_number' => 'MW-V1-TEXT-'.preg_replace('/[^A-Za-z0-9]+/', '-', $name),
                'client_name' => 'V1 text '.$name,
                'is_draft' => false,
                'cut_plan_version' => 1,
                'inputs' => $inputs,
                'calculation' => $calc,
                'final_quote_total' => $total,
                'payment_installments' => 3,
                'include_debris_removal' => false,
                'include_commute' => false,
                'ask_reply_to_accept' => false,
            ]);

            $edit = $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk();
            $posted = $this->formValuesFromHtml($edit->getContent());
            $this->assertSame('12', (string) ($posted['pieces'][0]['stock_length_ft'] ?? ''), $name.' form posts stock_length_ft=12');
            $this->assertSame('in', (string) ($posted['pieces'][0]['width_unit'] ?? ''), $name.' form posts implied width unit');

            $preview = $this->post(route('admin.quotations.millwork.calculate-existing', $quote), $posted);
            $preview->assertOk();
            $this->assertSame(
                $total,
                round((float) $preview->json('summary.grand_total'), 2),
                $name.' live preview must keep the stored text-size price'
            );

            $this->put(route('admin.quotations.millwork.update', $quote), $posted)
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $fresh = $quote->fresh();
            $this->assertSame(1, (int) $fresh->cut_plan_version, $name);
            $this->assertEquals($piece['width'] ?? null, $fresh->inputs['pieces'][0]['width'] ?? null, $name.' width');
            $this->assertEquals($piece['depth'] ?? null, $fresh->inputs['pieces'][0]['depth'] ?? null, $name.' depth');
            $this->assertSame(
                $total,
                round((float) $fresh->final_quote_total, 2),
                $name.' re-save must keep the exact stored price'
            );
        }
    }

    public function test_v1_implied_unit_dropdown_is_unchanged_until_flipped(): void
    {
        $this->actingAsAdmin();

        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'unit' => 'in',
            'width' => 36,
            'height' => 34.5,
            'depth' => 24,
            'include_doors' => false,
            'include_back' => false,
            'include_toe_kick' => false,
            'edge_banding' => false,
            'shelf_count' => 0,
        ];
        $inputs = [
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $total = round((float) $calc['summary']['grand_total'], 2);
        $this->assertGreaterThan(0, $total);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-UNIT',
            'client_name' => 'V1 unit dropdown',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);

        $posted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
        );
        $this->assertSame('in', (string) ($posted['pieces'][0]['width_unit'] ?? ''));

        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $kept = $quote->fresh();
        $this->assertEquals(36, $kept->inputs['pieces'][0]['width'] ?? null);
        $this->assertSame($total, round((float) $kept->final_quote_total, 2));

        $posted['pieces'][0]['width_unit'] = 'ft';
        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $flipped = $quote->fresh();
        $this->assertEquals(432, $flipped->inputs['pieces'][0]['width'] ?? null);
        $this->assertNotEquals($total, round((float) $flipped->final_quote_total, 2));
    }

    public function test_sparse_v1_blank_depth_typed_as_ft_reprices_as_inches(): void
    {
        $this->actingAsAdmin();

        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'unit' => 'in',
            'width' => 36,
            'height' => 34.5,
        ];
        $this->assertArrayNotHasKey('depth', $piece);

        $inputs = [
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $stored = round((float) $calc['summary']['grand_total'], 2);
        $this->assertGreaterThan(0, $stored);

        $inchInputs = $inputs;
        $inchInputs['pieces'][0]['depth'] = 24;
        $inchTotal = round((float) (new MillworkQuotationCalculator)->calculate($inchInputs, null, 1)['summary']['grand_total'], 2);
        $rawTwoInch = $inputs;
        $rawTwoInch['pieces'][0]['depth'] = 2;
        $rawTwoTotal = round((float) (new MillworkQuotationCalculator)->calculate($rawTwoInch, null, 1)['summary']['grand_total'], 2);
        $this->assertSame($stored, $inchTotal);
        $this->assertNotEquals($inchTotal, $rawTwoTotal);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-R9-DEPTH',
            'client_name' => 'V1 typed depth',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $stored,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);

        $posted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
        );
        $this->assertSame('', (string) ($posted['pieces'][0]['depth'] ?? ''), 'blank depth is the rendered default');
        $posted['pieces'][0]['depth'] = '2 ft';

        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame(1, (int) $fresh->cut_plan_version);
        $this->assertEquals(24, $fresh->inputs['pieces'][0]['depth'] ?? null);
        $this->assertSame(
            $inchTotal,
            round((float) $fresh->final_quote_total, 2),
            'typing 2 ft into a blank depth must reprice as 24", not 2"'
        );
        $this->assertNotEquals($rawTwoTotal, round((float) $fresh->final_quote_total, 2));
    }

    public function test_sparse_v1_cabinet_and_closet_untouched_resave_keeps_exact_price(): void
    {
        $this->actingAsAdmin();

        $cases = [
            'cabinet-width-24' => [
                'type' => 'cabinet',
                'qty' => 1,
                'unit' => 'in',
                'width' => 24,
            ],
            'closet-width-24' => [
                'type' => 'closet',
                'qty' => 1,
                'unit' => 'in',
                'width' => 24,
            ],
        ];

        foreach ($cases as $name => $piece) {
            $this->assertArrayNotHasKey('height', $piece, $name);
            $this->assertArrayNotHasKey('include_doors', $piece, $name);
            $this->assertArrayNotHasKey('include_toe_kick', $piece, $name);
            $this->assertArrayNotHasKey('edge_banding', $piece, $name);

            $inputs = [
                'pieces' => [$piece],
                'include_install' => false,
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ];
            $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
            $total = round((float) $calc['summary']['grand_total'], 2);
            $this->assertGreaterThan(0, $total, $name);

            $quote = MillworkQuotation::query()->create([
                'quote_number' => 'MW-V1-SPARSE-'.preg_replace('/[^A-Za-z0-9]+/', '-', $name),
                'client_name' => 'V1 sparse '.$name,
                'is_draft' => false,
                'cut_plan_version' => 1,
                'inputs' => $inputs,
                'calculation' => $calc,
                'final_quote_total' => $total,
                'payment_installments' => 3,
                'include_debris_removal' => false,
                'include_commute' => false,
                'ask_reply_to_accept' => false,
            ]);

            $posted = $this->formValuesFromHtml(
                $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
            );
            $this->assertSame('', (string) ($posted['pieces'][0]['height'] ?? ''), $name.' height stays the rendered blank');
            if ($name === 'cabinet-width-24') {
                $this->assertSame('1', (string) ($posted['pieces'][0]['include_doors'] ?? ''), $name.' doors render the priced Yes');
                $this->assertSame('1', (string) ($posted['pieces'][0]['include_toe_kick'] ?? ''), $name.' toe kick renders the priced Yes');
                $this->assertSame('2', (string) ($posted['pieces'][0]['door_count'] ?? ''), $name.' door count renders 2');
                $this->assertSame('1', (string) ($posted['pieces'][0]['shelf_count'] ?? ''), $name.' shelf count renders 1');
            } else {
                $this->assertSame('0', (string) ($posted['pieces'][0]['include_doors'] ?? '0'), $name.' doors render the priced No');
                $this->assertSame('0', (string) ($posted['pieces'][0]['door_count'] ?? '0'), $name.' door count renders 0');
                $this->assertSame('3', (string) ($posted['pieces'][0]['shelf_count'] ?? ''), $name.' shelf count renders 3');
            }

            $this->put(route('admin.quotations.millwork.update', $quote), $posted)
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $fresh = $quote->fresh();
            $this->assertSame(1, (int) $fresh->cut_plan_version, $name);
            $this->assertSame(
                $total,
                round((float) $fresh->final_quote_total, 2),
                $name.' untouched re-save must keep the exact stored price'
            );
        }
    }

    public function test_empty_string_dims_resave_keeps_exact_v1_and_v2_prices(): void
    {
        $this->actingAsAdmin();

        $cases = [
            'v2-bench-blank-height' => [
                'version' => 2,
                'piece' => [
                    'type' => 'bench',
                    'qty' => 1,
                    'unit' => 'in',
                    'length' => 48,
                    'depth' => 16,
                    'height' => '',
                ],
            ],
            'v1-bench-blank-height' => [
                'version' => 1,
                'piece' => [
                    'type' => 'bench',
                    'qty' => 1,
                    'unit' => 'in',
                    'length' => 48,
                    'depth' => 16,
                    'height' => '',
                ],
            ],
            'v2-cabinet-unused-blanks' => [
                'version' => 2,
                'piece' => [
                    'type' => 'cabinet',
                    'qty' => 1,
                    'unit' => 'in',
                    'width' => 24,
                    'height' => 34.5,
                    'depth' => 24,
                    'length' => '',
                    'linear_ft' => '',
                    'include_doors' => true,
                    'include_toe_kick' => true,
                    'edge_banding' => true,
                    'shelf_count' => 1,
                    'door_count' => 2,
                ],
            ],
            'v1-cabinet-blank-height-draft' => [
                'version' => 1,
                'draft' => true,
                'piece' => [
                    'type' => 'cabinet',
                    'qty' => 1,
                    'unit' => 'in',
                    'width' => 24,
                    'height' => '',
                    'depth' => 24,
                ],
            ],
        ];

        foreach ($cases as $name => $case) {
            $this->assertArrayHasKey('height', $case['piece'], $name);
            $version = (int) $case['version'];
            $draft = ! empty($case['draft']);
            $inputs = [
                'pieces' => [$case['piece']],
                'include_install' => false,
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ];
            $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, $version);
            $total = round((float) $calc['summary']['grand_total'], 2);

            $quote = MillworkQuotation::query()->create([
                'quote_number' => 'MW-BLANK-'.preg_replace('/[^A-Za-z0-9]+/', '-', $name),
                'client_name' => 'Blank '.$name,
                'is_draft' => $draft,
                'cut_plan_version' => $version,
                'inputs' => $inputs,
                'calculation' => $calc,
                'final_quote_total' => $total,
                'payment_installments' => 3,
                'include_debris_removal' => false,
                'include_commute' => false,
                'ask_reply_to_accept' => false,
            ]);

            $posted = $this->formValuesFromHtml(
                $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
            );
            $this->assertSame(
                (string) $case['piece']['height'],
                (string) ($posted['pieces'][0]['height'] ?? 'missing'),
                $name.' form keeps stored height'
            );
            $this->assertSame($case['piece']['type'], (string) ($posted['pieces'][0]['type'] ?? ''), $name.' type');
            if (array_key_exists('length', $case['piece'])) {
                $this->assertSame((string) $case['piece']['length'], (string) ($posted['pieces'][0]['length'] ?? 'missing'), $name.' length');
            }
            if (array_key_exists('linear_ft', $case['piece'])) {
                $this->assertSame((string) $case['piece']['linear_ft'], (string) ($posted['pieces'][0]['linear_ft'] ?? 'missing'), $name.' linear_ft');
            }
            if ($draft) {
                $posted['save_intent'] = 'draft';
            }

            $this->put(route('admin.quotations.millwork.update', $quote), $posted)
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $fresh = $quote->fresh();
            $this->assertSame($version, (int) $fresh->cut_plan_version, $name);
            $this->assertSame(
                (string) $case['piece']['height'],
                (string) ($fresh->inputs['pieces'][0]['height'] ?? 'missing'),
                $name.' stored height stays put'
            );
            $this->assertSame(
                $total,
                round((float) $fresh->final_quote_total, 2),
                $name.' re-save must keep the exact stored price'
            );
        }
    }

    public function test_sparse_v1_form_renders_priced_flags_and_changing_them_is_an_edit(): void
    {
        $this->actingAsAdmin();

        $inputs = [
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'unit' => 'in',
                'width' => 24,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $total = round((float) $calc['summary']['grand_total'], 2);
        $this->assertGreaterThan(0, $total);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-FLAGS',
            'client_name' => 'V1 flag edits',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);

        $posted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
        );
        $this->assertSame('1', (string) ($posted['pieces'][0]['include_doors'] ?? ''));
        $this->assertSame('1', (string) ($posted['pieces'][0]['include_toe_kick'] ?? ''));
        $this->assertSame('2', (string) ($posted['pieces'][0]['door_count'] ?? ''));
        $this->assertSame('1', (string) ($posted['pieces'][0]['shelf_count'] ?? ''));

        $posted['pieces'][0]['include_doors'] = '0';
        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $doorsOff = round((float) $quote->fresh()->final_quote_total, 2);
        $this->assertNotEquals($total, $doorsOff, 'Doors No must count as an edit');

        $posted['pieces'][0]['include_doors'] = '1';
        $posted['pieces'][0]['include_toe_kick'] = '0';
        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertNotEquals($total, round((float) $quote->fresh()->final_quote_total, 2), 'toe kick off must count as an edit');

        $closetInputs = [
            'pieces' => [[
                'type' => 'closet',
                'qty' => 1,
                'unit' => 'in',
                'width' => 24,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $closetCalc = (new MillworkQuotationCalculator)->calculate($closetInputs, null, 1);
        $closetTotal = round((float) $closetCalc['summary']['grand_total'], 2);
        $closet = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-SHELVES',
            'client_name' => 'V1 shelf edits',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $closetInputs,
            'calculation' => $closetCalc,
            'final_quote_total' => $closetTotal,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);
        $closetPosted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $closet))->assertOk()->getContent()
        );
        $this->assertSame('3', (string) ($closetPosted['pieces'][0]['shelf_count'] ?? ''));
        $this->assertSame('0', (string) ($closetPosted['pieces'][0]['door_count'] ?? '0'));

        $closetPosted['pieces'][0]['shelf_count'] = '1';
        $this->put(route('admin.quotations.millwork.update', $closet), $closetPosted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertNotEquals(
            $closetTotal,
            round((float) $closet->fresh()->final_quote_total, 2),
            'shelf count 1 must count as an edit from priced 3'
        );

        $closetPosted['pieces'][0]['shelf_count'] = '3';
        $closetPosted['pieces'][0]['door_count'] = '2';
        $closetPosted['pieces'][0]['include_doors'] = '1';
        $this->put(route('admin.quotations.millwork.update', $closet), $closetPosted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertNotEquals(
            $closetTotal,
            round((float) $closet->fresh()->final_quote_total, 2),
            'door count 2 with doors on must count as an edit'
        );
    }

    public function test_invalid_size_text_shows_inline_error_and_does_not_save(): void
    {
        $this->actingAsAdmin();

        $inputs = [
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'unit' => 'in',
                'width' => 36,
                'height' => 34.5,
                'depth' => 24,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $total = round((float) $calc['summary']['grand_total'], 2);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-ABC',
            'client_name' => 'V1 invalid size',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);

        $posted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
        );
        $posted['pieces'][0]['width'] = 'abc';

        $this->from(route('admin.quotations.millwork.edit', $quote))
            ->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect(route('admin.quotations.millwork.edit', $quote))
            ->assertSessionHasErrors('pieces.0.width')
            ->assertSessionDoesntHaveErrors('pieces.0.length');

        $fresh = $quote->fresh();
        $this->assertSame(36, $fresh->inputs['pieces'][0]['width'] ?? null);
        $this->assertSame($total, round((float) $fresh->final_quote_total, 2));
        $this->assertStringContainsString(
            'Enter a valid size',
            session('errors')->first('pieces.0.width')
        );

        $html = $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->getContent();
        $crawler = new Crawler($html);
        $width = $crawler->filter('input[name="pieces[0][width]"]');
        $this->assertGreaterThan(0, $width->count(), 'visible width input must be on the form');
        $this->assertStringContainsString(
            'is-invalid',
            (string) $width->attr('class'),
            'invalid used-field error must render on the visible width input'
        );
        $widthWrap = $width->closest('.mw-dim-field');
        $this->assertGreaterThan(0, $widthWrap->count());
        $this->assertStringContainsString('Enter a valid size', $widthWrap->text());
        $length = $crawler->filter('input[name="pieces[0][length]"]');
        $this->assertGreaterThan(0, $length->count());
        $this->assertStringNotContainsString(
            'is-invalid',
            (string) $length->attr('class'),
            'hidden length must not carry the invalid-size error'
        );
    }

    public function test_untouched_legacy_text_sizes_resave_keeps_exact_price(): void
    {
        $this->actingAsAdmin();

        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'unit' => 'in',
            'width' => '36in.',
            'height' => 34.5,
            'depth' => "24''",
            'length' => 'n/a',
        ];
        $inputs = [
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $total = round((float) $calc['summary']['grand_total'], 2);
        $this->assertGreaterThan(0, $total);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-LEGACY-TEXT',
            'client_name' => 'V1 legacy text sizes',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);

        $posted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
        );
        $this->assertSame('36in.', (string) ($posted['pieces'][0]['width'] ?? ''));
        $this->assertSame("24''", (string) ($posted['pieces'][0]['depth'] ?? ''));
        $this->assertSame('n/a', (string) ($posted['pieces'][0]['length'] ?? ''));

        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->from(route('admin.quotations.millwork.edit', $quote))
            ->put(route('admin.quotations.millwork.update', $quote), array_merge($posted, ['save_intent' => 'draft']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame('36in.', $fresh->inputs['pieces'][0]['width'] ?? null);
        $this->assertSame("24''", $fresh->inputs['pieces'][0]['depth'] ?? null);
        $this->assertSame('n/a', $fresh->inputs['pieces'][0]['length'] ?? null);
        $this->assertArrayNotHasKey('_existing_pieces', $fresh->inputs);
        $this->assertSame($total, round((float) $fresh->final_quote_total, 2));
    }

    public function test_legacy_text_row_typing_2_ft_in_blank_depth_reprices_as_24_inches(): void
    {
        $this->actingAsAdmin();

        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'unit' => 'in',
            'width' => '36in.',
            'height' => 34.5,
            'length' => 'n/a',
        ];
        $this->assertArrayNotHasKey('depth', $piece);

        $inputs = [
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $storedCalc = (new MillworkQuotationCalculator)->calculate($inputs, null, 1);
        $stored = round((float) $storedCalc['summary']['grand_total'], 2);
        $inch = $inputs;
        $inch['pieces'][0]['width'] = 36;
        $inch['pieces'][0]['depth'] = 24;
        unset($inch['pieces'][0]['length']);
        $inchTotal = round((float) (new MillworkQuotationCalculator)->calculate($inch, null, 1)['summary']['grand_total'], 2);
        $twoInch = $inch;
        $twoInch['pieces'][0]['depth'] = 2;
        $twoTotal = round((float) (new MillworkQuotationCalculator)->calculate($twoInch, null, 1)['summary']['grand_total'], 2);
        $this->assertSame($stored, $inchTotal);
        $this->assertNotEquals($inchTotal, $twoTotal);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-2FT-MUTANT',
            'client_name' => 'V1 2 ft mutant',
            'is_draft' => false,
            'cut_plan_version' => 1,
            'inputs' => $inputs,
            'calculation' => $storedCalc,
            'final_quote_total' => $stored,
            'payment_installments' => 3,
            'include_debris_removal' => false,
            'include_commute' => false,
            'ask_reply_to_accept' => false,
        ]);

        $posted = $this->formValuesFromHtml(
            $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent()
        );
        $this->assertSame('', (string) ($posted['pieces'][0]['depth'] ?? ''));
        $this->assertSame('36in.', (string) ($posted['pieces'][0]['width'] ?? ''));
        $posted['pieces'][0]['depth'] = '2 ft';
        $posted['pieces'][0]['_calc_version'] = 3;

        $this->put(route('admin.quotations.millwork.update', $quote), $posted)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame('36in.', $fresh->inputs['pieces'][0]['width'] ?? null);
        $this->assertEquals(24, $fresh->inputs['pieces'][0]['depth'] ?? null);
        $this->assertSame($inchTotal, round((float) $fresh->final_quote_total, 2));
        $this->assertNotEquals($twoTotal, round((float) $fresh->final_quote_total, 2));
    }

    public function test_project_pdf_totals_match_calculator(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_name' => 'Elena Moretti',
        ]);

        $payload = $this->samplePayload('Elena Moretti');
        $payload['link_client_project_id'] = $project->id;
        $payload['delivery_amount'] = 45;
        $payload['markup_pct'] = 5;

        $this->post(route('admin.quotations.millwork.store'), $payload)
            ->assertRedirect();

        $quote = MillworkQuotation::query()->where('client_name', 'Elena Moretti')->first();
        $this->assertNotNull($quote);
        $this->assertTrue($quote->isLinked());

        $calc = (new MillworkQuotationCalculator)->calculate($quote->inputs);
        $summary = $calc['summary'];
        $this->assertEqualsWithDelta(
            (float) $summary['grand_total'],
            (float) $quote->final_quote_total,
            0.001
        );
        $this->assertEqualsWithDelta(
            (float) $summary['labor_pre_hst'],
            $quote->labourPreTaxAmountForProjectQuotation(),
            0.001
        );
        $this->assertEqualsWithDelta(
            (float) $summary['materials_pre_hst'] + (float) $summary['markup_amount'],
            $quote->materialsPreTaxAmountForProjectQuotation(),
            0.001
        );
        $this->assertEqualsWithDelta(
            (float) $summary['materials_goods_pre_hst'] + (float) $summary['delivery_amount'],
            (float) $summary['materials_pre_hst'],
            0.001
        );

        $cpq = ClientProjectQuotation::findForSource(ClientProjectQuotation::SOURCE_MILLWORK, (int) $quote->id);
        $this->assertNotNull($cpq);
        $this->assertEqualsWithDelta((float) $summary['labor_pre_hst'], $quote->labourPreTaxAmountForProjectQuotation(), 0.001);
        $this->assertEqualsWithDelta((float) $summary['subtotal_pre_hst'], $cpq->pdfSubtotal(), 0.001);
        $this->assertEqualsWithDelta((float) $summary['hst'], $cpq->pdfHst(), 0.001);
        $this->assertEqualsWithDelta((float) $summary['grand_total'], $cpq->pdfGrandTotal(), 0.001);

        $html = view('admin.client_projects.quotation.pdf', [
            'project' => $project->fresh(),
            'quotation' => $cpq->fresh(),
            'pdfPhotos' => collect(),
            'acceptUrl' => 'https://example.test/accept',
        ])->render();

        $fmt = fn (float $n) => 'CA$'.number_format($n, 2, '.', ',');
        $this->assertStringContainsString($fmt((float) $summary['labor_pre_hst']), $html);
        $this->assertStringContainsString($fmt($quote->materialsPreTaxAmountForProjectQuotation()), $html);
        $this->assertStringContainsString($fmt((float) $summary['grand_total']), $html);
        $this->assertStringContainsString('Custom millwork (labour)', $html);
        $this->assertStringContainsString('Kitchen — Kitchen pantry', $html);
        $this->assertStringContainsString('Foyer casing and baseboard, approx.', $html);
        $this->assertStringContainsString('lin. ft', $html);
        $this->assertStringContainsString('Zanini Renovations is fully insured.', $html);
        $this->assertStringContainsString('Warranty on workmanship', $html);
        $this->assertStringNotContainsString('Shop —', $html);
        $this->assertStringNotContainsString('Sheet goods:', $html);
        $this->assertStringNotContainsString('Edge banding:', $html);
        $this->assertStringNotContainsString('Warrantee', $html);
        $this->assertStringNotContainsString('Custom millwork (labor)', $html);
        $this->assertStringNotContainsString('licensed', $html);
        $this->assertStringNotContainsString('WSIB', $html);
        $this->assertStringNotContainsString('$3M', $html);

        $edit = $this->get(route('admin.quotations.millwork.edit', $quote));
        $edit->assertOk();
        $live = (new MillworkQuotationCalculator)->calculate($quote->fresh()->inputs);
        $this->assertEqualsWithDelta((float) $summary['grand_total'], (float) $live['summary']['grand_total'], 0.001);
        $this->assertEqualsWithDelta((float) $summary['materials_pre_hst'], (float) $live['summary']['materials_pre_hst'], 0.001);
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePayload(string $client): array
    {
        return [
            'save_intent' => 'publish',
            'client_name' => $client,
            'work_description' => 'Kitchen pantry built-in, reach-in closet, and foyer trim package.',
            'pieces' => [
                [
                    'type' => 'cabinet',
                    'name' => 'Kitchen pantry',
                    'room' => 'Kitchen',
                    'style' => 'tall',
                    'qty' => 1,
                    'unit' => 'in',
                    'width' => 36,
                    'height' => 84,
                    'depth' => 24,
                    'include_doors' => 1,
                    'door_count' => 2,
                    'shelf_count' => 4,
                    'include_back' => 1,
                    'include_toe_kick' => 1,
                    'edge_banding' => 1,
                    'finish' => 'paint',
                ],
                [
                    'type' => 'closet',
                    'name' => 'Reach-in closet',
                    'room' => 'Bedroom',
                    'qty' => 1,
                    'unit' => 'in',
                    'width' => 72,
                    'height' => 84,
                    'depth' => 14,
                    'columns' => 2,
                    'shelf_count' => 3,
                    'rod_count' => 1,
                    'include_doors' => 0,
                    'include_back' => 1,
                    'edge_banding' => 1,
                    'finish' => 'none',
                ],
                [
                    'type' => 'trim',
                    'name' => 'Foyer trim',
                    'room' => 'Foyer',
                    'profile' => 'mixed',
                    'linear_ft' => 48,
                    'unit' => 'ft',
                    'openings' => 2,
                    'stock_length_ft' => 16,
                    'qty' => 1,
                    'edge_banding' => 0,
                    'finish' => 'paint',
                ],
            ],
            'waste_pct' => 10,
            'edge_waste_pct' => 10,
            'finish_waste_pct' => 10,
            'markup_pct' => 0,
            'delivery_amount' => 45,
            'spare_sheets' => 0,
            'include_install' => 1,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'include_helper' => 0,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'ask_reply_to_accept' => '0',
        ];
    }

    /**
     * Fields a browser would submit from the millwork edit page, including
     * controls associated with #mw-main-form via the form="" attribute.
     * Disabled controls are omitted, matching a real form POST.
     *
     * @return array<string, mixed>
     */
    private function formValuesFromHtml(string $html): array
    {
        $crawler = new Crawler($html);
        $values = [];
        foreach ($crawler->filter('#mw-main-form input, #mw-main-form select, #mw-main-form textarea, [form="mw-main-form"]') as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $name = $node->getAttribute('name');
            if ($name === '' || $name === '_token' || $name === '_method') {
                continue;
            }
            if ($node->hasAttribute('disabled')) {
                continue;
            }
            $tag = strtolower($node->nodeName);
            $type = strtolower($node->getAttribute('type'));
            if (in_array($type, ['submit', 'button', 'file', 'image', 'reset'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && ! $node->hasAttribute('checked')) {
                continue;
            }
            if ($tag === 'select') {
                $selected = null;
                foreach ($node->getElementsByTagName('option') as $option) {
                    if ($option instanceof \DOMElement && $option->hasAttribute('selected')) {
                        $selected = $option->getAttribute('value');
                        break;
                    }
                }
                if ($selected === null) {
                    $first = $node->getElementsByTagName('option')->item(0);
                    $selected = $first instanceof \DOMElement ? $first->getAttribute('value') : '';
                }
                $this->assignFormValue($values, $name, $selected);
                continue;
            }
            if ($tag === 'textarea') {
                $this->assignFormValue($values, $name, $node->textContent);
                continue;
            }
            $this->assignFormValue($values, $name, $node->getAttribute('value'));
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assignFormValue(array &$values, string $name, mixed $value): void
    {
        if (! preg_match('/^([^\\[]+)((?:\\[[^\\]]*\\])*)$/', $name, $m)) {
            $values[$name] = $value;

            return;
        }
        $keys = [$m[1]];
        if ($m[2] !== '') {
            preg_match_all('/\\[([^\\]]*)\\]/', $m[2], $km);
            foreach ($km[1] as $k) {
                $keys[] = $k;
            }
        }
        $ref = &$values;
        $last = array_key_last($keys);
        foreach ($keys as $i => $k) {
            if ($i === $last) {
                $ref[$k] = $value;
                break;
            }
            if (! isset($ref[$k]) || ! is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }
    }
}

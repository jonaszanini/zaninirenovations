<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\MillworkQuotation;
use App\Services\MillworkQuotationCalculator;
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
}

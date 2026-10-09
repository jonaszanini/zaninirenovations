<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\MillworkQuotation;
use App\Services\MillworkQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

/**
 * Tester PR A acceptance check 5: red banner, PDF 422, no file.
 */
class MillworkCutPlanPlacementTest extends IntegrationTestCase
{
    use ActsAsAdmin;

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

    public function test_old_quote_without_unplaced_key_is_blocked_when_inputs_do_not_fit(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['client_name' => 'Legacy Unplaced']);
        $inputs = [
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
        ];
        $mw = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-UNPLACED',
            'client_name' => 'Legacy Unplaced',
            'client_project_id' => $project->id,
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => [
                'cut_plan' => ['sheet_count' => 0, 'layouts' => []],
                'summary' => ['grand_total' => 100, 'labor_pre_hst' => 50, 'materials_pre_hst' => 50],
            ],
            'final_quote_total' => 100,
        ]);
        $this->assertArrayNotHasKey('unplaced', $mw->calculation['cut_plan']);
        $this->assertTrue($mw->hasUnplacedCutParts());

        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'source_id' => $mw->id,
            'title' => 'Custom millwork',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'Q-V1-UNPLACED',
            'version' => 1,
            'labour' => true,
            'material' => false,
            'items' => [['description' => 'Custom millwork (labour)', 'amount' => 50, 'apply_hst' => true]],
        ]);

        $this->get(route('admin.client-projects.quotation.pdf', [$project, $quotation]))
            ->assertStatus(422)
            ->assertSee('do not fit on any sheet');
    }

    public function test_edit_page_shows_recalculated_notice_for_old_cut_plan(): void
    {
        $this->actingAsAdmin();
        $path = base_path('tests/fixtures/millwork-cut-plan-v1/heavy_shelf.json');
        $fixture = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($fixture);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-V1-HEAVY',
            'client_name' => 'Legacy Heavy',
            'is_draft' => true,
            'inputs' => [
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
            ],
            'calculation' => $fixture['calculation'],
            'final_quote_total' => (float) $fixture['grand_total'],
        ]);

        $html = $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee('Recalculated with the new cut plan')
            ->assertSee('mw-recalc-banner', false)
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="mw-recalc-banner"[^>]*hidden/', $html);
        $this->assertStringContainsString('3,235.43', $html);
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

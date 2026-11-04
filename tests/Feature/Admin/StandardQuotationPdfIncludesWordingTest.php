<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class StandardQuotationPdfIncludesWordingTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * @return array<string, array{0: bool, 1: bool, 2: string, 3: list<string>}>
     */
    public static function includesCombinationProvider(): array
    {
        return [
            'labour_only' => [
                false,
                true,
                'This quotation includes <strong>LABOUR</strong> for the following.',
                ['MATERIALS</strong> and <strong>LABOUR', 'MATERIALS</strong> for the following'],
            ],
            'material_only' => [
                true,
                false,
                'This quotation includes <strong>MATERIALS</strong> for the following.',
                ['MATERIALS</strong> and <strong>LABOUR', 'LABOUR</strong> for the following'],
            ],
            'both' => [
                true,
                true,
                'This quotation includes <strong>MATERIALS</strong> and <strong>LABOUR</strong> for the following.',
                [],
            ],
            'neither' => [
                false,
                false,
                'This quotation includes <strong>MATERIALS</strong> and <strong>LABOUR</strong> for the following.',
                ['For the following scope of work:'],
            ],
        ];
    }

    /**
     * @dataProvider includesCombinationProvider
     *
     * @param  list<string>  $mustNotContain
     */
    public function test_client_pdf_html_follows_includes_checkboxes(
        bool $includesMaterial,
        bool $includesLabour,
        string $expectedFragment,
        array $mustNotContain
    ): void {
        [$project, $cpq, $standard] = $this->linkedStandardWithPricedMaterialAndLabour(
            $includesMaterial,
            $includesLabour
        );

        $html = $this->renderClientPdfHtml($project, $cpq);

        $this->assertStringContainsString($expectedFragment, $html);
        foreach ($mustNotContain as $fragment) {
            $this->assertStringNotContainsString($fragment, $html);
        }

        $standard->refresh();
        $this->assertSame($includesMaterial, (bool) $standard->includes_material);
        $this->assertSame($includesLabour, (bool) $standard->includes_labour);
    }

    public function test_update_persists_includes_and_syncs_linked_client_quotation_flags(): void
    {
        $this->actingAsAdmin();

        [$project, $cpq, $standard] = $this->linkedStandardWithPricedMaterialAndLabour(true, true);
        $standard->update([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ]);
        $this->assertTrue((bool) $cpq->labour);
        $this->assertTrue((bool) $cpq->material);

        $payload = [
            'save_intent' => 'publish',
            'client_name' => $standard->client_name,
            'display_title' => $standard->display_title,
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'includes_material' => '0',
            'includes_labour' => '1',
            'payment_installments' => 1,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'works' => [[
                'title' => 'Install',
                'labour_use_fixed' => '1',
                'labour_fixed_amount' => '250',
                'materials' => [[
                    'short_name' => 'Hardware kit',
                    'unit_price' => '75',
                    'quantity' => '1',
                    'apply_hst' => '1',
                ]],
            ]],
        ];

        $this->from(route('admin.quotations.standard.edit', $standard))
            ->put(route('admin.quotations.standard.update', $standard), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $standard->refresh();
        $cpq->refresh();

        $this->assertFalse((bool) $standard->includes_material);
        $this->assertTrue((bool) $standard->includes_labour);
        $this->assertFalse((bool) $cpq->material);
        $this->assertTrue((bool) $cpq->labour);

        $html = $this->renderClientPdfHtml($project, $cpq);
        $this->assertStringContainsString(
            'This quotation includes <strong>LABOUR</strong> for the following.',
            $html
        );
        $this->assertStringNotContainsString(
            'MATERIALS</strong> and <strong>LABOUR',
            $html
        );
    }

    public function test_sync_updates_linked_line_title_when_includes_change(): void
    {
        $this->actingAsAdmin();

        [$project, $cpq, $standard] = $this->linkedStandardWithPricedMaterialAndLabour(true, true);
        $cpq->update(['title' => 'Labour and material']);
        $standard->update([
            'client_project_id' => $project->id,
            'linked_at' => now(),
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
        ]);

        $payload = [
            'save_intent' => 'publish',
            'client_name' => $standard->client_name,
            'display_title' => $standard->display_title,
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
            'includes_material' => '0',
            'includes_labour' => '1',
            'payment_installments' => 1,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'works' => [[
                'title' => 'Install',
                'labour_use_fixed' => '1',
                'labour_fixed_amount' => '250',
                'materials' => [[
                    'short_name' => 'Hardware kit',
                    'unit_price' => '75',
                    'quantity' => '1',
                    'apply_hst' => '1',
                ]],
            ]],
        ];

        $this->from(route('admin.quotations.standard.edit', $standard))
            ->put(route('admin.quotations.standard.update', $standard), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $cpq->refresh();
        $this->assertSame('Labour and material', $cpq->title);

        $combined = collect($cpq->items)->first();
        $this->assertIsArray($combined);
        $this->assertSame('Labour and material', $combined['description'] ?? null);
        $this->assertEqualsWithDelta(325.0, (float) ($combined['amount'] ?? 0), 0.01);
    }

    public function test_sync_preserves_custom_linked_line_title_on_save_without_changes(): void
    {
        $this->actingAsAdmin();

        $customTitle = 'Custom kitchen cabinet install';
        [$project, $cpq, $standard] = $this->linkedStandardWithPricedMaterialAndLabour(true, true);
        $cpq->update(['title' => $customTitle]);
        $standard->update([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ]);

        $payload = [
            'save_intent' => 'publish',
            'client_name' => $standard->client_name,
            'display_title' => $standard->display_title,
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'includes_material' => '1',
            'includes_labour' => '1',
            'payment_installments' => 1,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'works' => [[
                'title' => 'Install',
                'labour_use_fixed' => '1',
                'labour_fixed_amount' => '250',
                'materials' => [[
                    'short_name' => 'Hardware kit',
                    'unit_price' => '75',
                    'quantity' => '1',
                    'apply_hst' => '1',
                ]],
            ]],
        ];

        $this->from(route('admin.quotations.standard.edit', $standard))
            ->put(route('admin.quotations.standard.update', $standard), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $cpq->refresh();
        $this->assertSame($customTitle, $cpq->title);

        $firstLine = collect($cpq->items)->first();
        $this->assertIsArray($firstLine);
        $this->assertSame($customTitle, $firstLine['description'] ?? null);
    }

    public function test_legacy_neither_checked_uses_priced_lines_for_headline_and_summary_label(): void
    {
        [$project, $cpq, $standard] = $this->linkedStandardWithPricedMaterialAndLabour(false, false);

        $this->assertSame('Labour and material', $standard->pdfSummaryLineDescription());

        $html = $this->renderClientPdfHtml($project, $cpq);
        $this->assertStringContainsString(
            'This quotation includes <strong>MATERIALS</strong> and <strong>LABOUR</strong> for the following.',
            $html
        );
        $this->assertStringNotContainsString('For the following scope of work:', $html);
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectQuotation, 2: StandardQuotation}
     */
    private function linkedStandardWithPricedMaterialAndLabour(
        bool $includesMaterial,
        bool $includesLabour
    ): array {
        $project = ClientProject::factory()->create([
            'client_name' => 'Includes PDF Client',
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $standard = StandardQuotation::query()->create([
            'quote_number' => 'SQ-INC-'.random_int(10000, 99999),
            'client_name' => $project->client_name,
            'display_title' => 'IKEA assemble and installation',
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'includes_material' => $includesMaterial,
            'includes_labour' => $includesLabour,
            'is_draft' => false,
            'materials' => [[
                'short_name' => 'Hardware kit',
                'unit_price' => 75,
                'quantity' => 1,
                'apply_hst' => true,
            ]],
            'works' => [[
                'title' => 'Install',
                'materials' => [[
                    'short_name' => 'Hardware kit',
                    'unit_price' => 75,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 250,
            ]],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 250,
        ]);

        $standard->refresh();

        $cpq = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Install',
            'status' => ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => $includesLabour,
            'material' => $includesMaterial,
            'quotation_number' => 'QUO-INC-'.random_int(10000, 99999),
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $standard->id,
            'source_snapshot' => $standard->only($standard->getFillable()),
            'items' => $standard->projectQuotationLineItems($standard->pdfSummaryLineDescription()),
        ]);

        return [$project, $cpq, $standard];
    }

    private function renderClientPdfHtml(ClientProject $project, ClientProjectQuotation $quotation): string
    {
        return view('admin.client_projects.quotation.pdf', [
            'project' => $project,
            'quotation' => $quotation,
            'pdfPhotos' => collect(),
            'acceptUrl' => null,
        ])->render();
    }
}

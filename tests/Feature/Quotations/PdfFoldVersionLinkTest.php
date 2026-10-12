<?php

namespace Tests\Feature\Quotations;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PdfFoldVersionLinkTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_first_calculator_link_stores_the_v1_total(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $fence = FenceQuotation::query()->create([
            'quote_number' => 'FN-FOLD-V1-'.uniqid(),
            'client_name' => 'Legacy Fence',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => [], 'totals' => []],
            'pdf_fold_version' => 1,
        ]);

        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'source_id' => $fence->id,
        ]);
        $this->assertFalse($row->exists);
        $row->fill([
            'title' => 'Fence — '.$fence->quote_number,
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => ClientProjectQuotation::generateNumber(
                ClientProjectQuotation::TYPE_QUOTATION
            ),
            'version' => 1,
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $this->assertFalse($row->exists);
        $this->assertNull($row->pdf_fold_version);
        $this->assertSame(1, $row->resolvedPdfFoldVersion());
        $this->assertTrue($row->usesLegacyPdfFold());
        $this->assertFalse($row->usesCurrentPdfFold());
        $this->assertEquals(113.99, $row->pdfGrandTotal());
        $this->assertSame(['Material'], array_column($row->pdfLineItems(), 'description'));
        $row->save();

        $row->refresh();
        $this->assertSame(1, (int) $row->pdf_fold_version);
        $this->assertEquals('113.99', $row->total_amount);
        $this->assertEquals(113.99, $row->pdfGrandTotal());
        $this->assertSame(['Material'], array_column($row->pdfLineItems(), 'description'));
    }

    public function test_new_quote_defaults_to_fold_version_three(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $quote = new ClientProjectQuotation();
        $quote->fill([
            'client_project_id' => $project->id,
            'title' => 'New fold quote',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'quotation_number' => ClientProjectQuotation::generateNumber('quotation'),
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
            'version' => 1,
        ]);
        $this->assertFalse($quote->exists);
        $this->assertNull($quote->pdf_fold_version);
        $this->assertTrue($quote->usesCurrentPdfFold());
        $this->assertEquals(114.00, $quote->pdfGrandTotal());
        $quote->save();

        $quote->refresh();
        $this->assertSame(3, (int) $quote->pdf_fold_version);
        $this->assertEquals('114.00', $quote->total_amount);
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
    }

    public function test_duplicate_copies_legacy_fold_version(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $quotation = new ClientProjectQuotation();
        $quotation->fill([
            'client_project_id' => $project->id,
            'title' => 'Legacy fold quote',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'quotation_number' => ClientProjectQuotation::generateNumber('quotation'),
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
            'version' => 1,
            'pdf_fold_version' => 1,
        ]);
        $quotation->save();

        $response = $this->post(route('admin.client-projects.quotation.duplicate', [
            'client_project' => $project->id,
            'quotation' => $quotation->id,
        ]), [
            'duplicate_target' => 'same',
        ]);
        $response->assertRedirect();

        $copy = ClientProjectQuotation::query()->where('id', '!=', $quotation->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame(1, (int) $copy->pdf_fold_version);
        $this->assertEquals('113.99', $copy->total_amount);
    }
}

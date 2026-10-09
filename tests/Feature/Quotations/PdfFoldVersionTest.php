<?php

namespace Tests\Feature\Quotations;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PdfFoldVersionTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_new_rows_default_to_fold_version_two(): void
    {
        $this->actingAsAdmin();

        $fence = FenceQuotation::query()->create([
            'quote_number' => 'FN-FOLD-NEW-'.uniqid(),
            'client_name' => 'Fold Version Client',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => [], 'totals' => []],
        ]);
        $this->assertSame(2, (int) $fence->pdf_fold_version);

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
                ['description' => 'Material', 'amount' => 100, 'apply_hst' => true],
            ],
            'version' => 1,
        ]);
        $quote->save();

        $this->assertSame(2, (int) $quote->fresh()->pdf_fold_version);
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
                ['description' => 'Material', 'amount' => 100, 'apply_hst' => true],
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
        $this->assertSame(1, (int) $quotation->fresh()->pdf_fold_version);
        $this->assertEqualsWithDelta(113.99, $quotation->pdfGrandTotal(), 0.001);

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
        $this->assertEqualsWithDelta(113.99, $copy->pdfGrandTotal(), 0.001);
        $this->assertSame(['Material'], array_column($copy->pdfLineItems(), 'description'));
    }

    public function test_calculator_link_copies_fold_version_from_source(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $legacyFence = FenceQuotation::query()->create([
            'quote_number' => 'FN-FOLD-LEGACY-'.uniqid(),
            'client_name' => 'Legacy Fence',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => [], 'totals' => []],
            'pdf_fold_version' => 1,
        ]);
        $this->assertSame(1, (int) $legacyFence->pdf_fold_version);

        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'source_id' => $legacyFence->id,
        ]);
        $this->assertFalse($row->exists);
        $row->fill([
            'title' => 'Fence — '.$legacyFence->quote_number,
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => ClientProjectQuotation::generateNumber(
                ClientProjectQuotation::TYPE_QUOTATION
            ),
            'version' => 1,
            'items' => [
                ['description' => '6 ft standard board fence', 'amount' => 500, 'apply_hst' => true],
            ],
        ]);
        $row->save();

        $this->assertSame(1, (int) $row->fresh()->pdf_fold_version);

        $newFence = FenceQuotation::query()->create([
            'quote_number' => 'FN-FOLD-CUR-'.uniqid(),
            'client_name' => 'New Fence',
            'sides' => [['length_ft' => 16]],
            'calculation' => ['materials' => [], 'totals' => []],
        ]);
        $this->assertSame(2, (int) $newFence->pdf_fold_version);

        $linked = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'source_id' => $newFence->id,
        ]);
        $linked->fill([
            'title' => 'Fence — '.$newFence->quote_number,
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => ClientProjectQuotation::generateNumber(
                ClientProjectQuotation::TYPE_QUOTATION
            ),
            'version' => 1,
            'items' => [
                ['description' => '6 ft standard board fence', 'amount' => 400, 'apply_hst' => true],
            ],
        ]);
        $linked->save();

        $this->assertSame(2, (int) $linked->fresh()->pdf_fold_version);
    }
}

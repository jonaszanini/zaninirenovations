<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\Material;
use App\Models\StandardQuotation;
use Illuminate\Support\Facades\DB;

/**
 * Inserts Room Area priced lines into a new or existing Standard quotation.
 *
 * Standard is the quotation type that stores qty × unit price (calculator
 * types only keep a lump-sum on the project hub).
 */
class RoomAreaQuoteLineInserter
{
    public function __construct(
        private StandardQuotationCalculator $calculator,
    ) {}

    /**
     * @param  list<array{
     *   trade: string,
     *   label: string,
     *   description: string,
     *   short_name: string,
     *   unit: string,
     *   qty: float,
     *   unit_price: float,
     *   catalog_material_id: int|null,
     *   apply_hst: bool
     * }>  $lines
     */
    public function insert(
        array $lines,
        ?StandardQuotation $existing,
        ?ClientProject $clientProject,
        string $clientName,
        string $displayTitle,
        string $jobTitle,
    ): StandardQuotation {
        if ($lines === []) {
            throw new \InvalidArgumentException('At least one quote line is required.');
        }

        return DB::transaction(function () use ($lines, $existing, $clientProject, $clientName, $displayTitle, $jobTitle) {
            $materialRows = array_map(fn (array $line) => $this->materialRow($line), $lines);

            if ($existing) {
                $works = $existing->resolvedWorks();
                $first = $works[0] ?? [
                    'title' => $jobTitle,
                    'materials' => [],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 0,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ];
                $existingMaterials = is_array($first['materials'] ?? null) ? $first['materials'] : [];
                $first['materials'] = array_values(array_merge($existingMaterials, $materialRows));
                if (trim((string) ($first['title'] ?? '')) === '') {
                    $first['title'] = $jobTitle;
                }
                $works[0] = $first;
                $quote = $existing;
            } else {
                $works = [[
                    'title' => $jobTitle,
                    'materials' => $materialRows,
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 0,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ]];
                $quote = new StandardQuotation([
                    'quote_number' => StandardQuotation::generateNumber(),
                    'version' => 1,
                    'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
                    'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
                    'includes_material' => true,
                    'includes_labour' => false,
                    'contingency_enabled' => false,
                    'contingency_percent' => 0,
                    'include_labour_hst' => true,
                    'payment_installments' => 1,
                    'payment_percentages' => [100],
                ]);
            }

            $computed = $this->calculator->computeWorks(
                $this->worksForCompute($works),
                (bool) ($quote->contingency_enabled ?? false),
                (float) ($quote->contingency_percent ?? 0),
                (bool) ($quote->include_labour_hst ?? true),
                (bool) ($quote->include_building ?? false),
                (float) ($quote->building_labor_percent ?? 0)
            );

            $worksForStorage = [];
            $materialsForStorage = [];
            foreach ($computed['works'] as $i => $job) {
                $posted = $works[$i] ?? [];
                $jobMaterials = array_map(
                    fn (array $m) => $this->persistMaterial($m),
                    is_array($job['materials'] ?? null) ? $job['materials'] : []
                );
                foreach ($jobMaterials as $row) {
                    $materialsForStorage[] = $row;
                }
                $useFixed = filter_var($posted['labour_use_fixed'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $worksForStorage[] = [
                    'title' => trim((string) ($posted['title'] ?? '')),
                    'materials' => $jobMaterials,
                    'labour_mode' => (($posted['labour_mode'] ?? 'hours') === 'days') ? 'days' : 'hours',
                    'labour_quantity' => round((float) ($posted['labour_quantity'] ?? 0), 2),
                    'labour_hours' => $job['labour_hours'],
                    'labour_hourly_rate' => round((float) ($posted['labour_hourly_rate'] ?? 0), 2),
                    'labour_use_fixed' => $useFixed,
                    'labour_fixed_amount' => $useFixed ? round((float) ($posted['labour_fixed_amount'] ?? 0), 2) : null,
                    'labour_fixed_hst_included' => $useFixed && filter_var($posted['labour_fixed_hst_included'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            $quote->fill([
                'client_name' => $clientName,
                'display_title' => $displayTitle,
                'includes_material' => true,
                'materials' => $materialsForStorage,
                'works' => $worksForStorage,
                'labour_use_fixed' => true,
                'labour_fixed_amount' => (float) $computed['labour_total'],
                'labour_hours' => $computed['labour_hours'],
                'labour_total' => $computed['labour_total'],
                'labour_hst' => $computed['labour_hst'],
                'materials_subtotal_before_hst' => $computed['materials_subtotal_before_hst'],
                'materials_hst' => $computed['materials_hst'],
                'materials_with_hst' => $computed['materials_with_hst'],
                'contingency_enabled' => $computed['contingency_enabled'],
                'contingency_percent' => $computed['contingency_percent'],
                'contingency_amount' => $computed['contingency_amount'],
                'contingency_hst' => $computed['contingency_hst'],
                'grand_total' => $computed['grand_total'],
            ]);

            if ($clientProject && $this->projectAllowsLink($clientProject)) {
                $quote->client_project_id = $clientProject->id;
                if (! $quote->linked_at) {
                    $quote->linked_at = now();
                }
                if (trim((string) $quote->client_name) === '') {
                    $quote->client_name = trim((string) $clientProject->client_name) ?: $clientName;
                }
            }

            $quote->save();

            if ($quote->client_project_id && $clientProject && (int) $quote->client_project_id === (int) $clientProject->id) {
                $this->syncProjectQuotation($clientProject, $quote->fresh());
            }

            return $quote->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function materialRow(array $line): array
    {
        $catalogId = isset($line['catalog_material_id']) && (int) $line['catalog_material_id'] > 0
            ? (int) $line['catalog_material_id']
            : null;

        $short = trim((string) ($line['short_name'] ?? ''));
        $desc = trim((string) ($line['description'] ?? ''));
        if ($desc === '') {
            $desc = trim((string) ($line['label'] ?? 'Room Area'));
        }

        return [
            'short_name' => $short,
            'description' => $desc,
            'unit_price' => round(max(0.0, (float) ($line['unit_price'] ?? 0)), 2),
            'quantity' => (float) ($line['qty'] ?? 0),
            'apply_hst' => array_key_exists('apply_hst', $line)
                ? filter_var($line['apply_hst'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'hst_includes_unit' => false,
            'catalog_material_id' => $catalogId,
            'delivery_cad' => 0.0,
            'entry_mode' => $catalogId ? 'catalog' : 'manual',
            'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
            'url' => $line['url'] ?? null,
            'image_url' => $line['image_url'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $works
     * @return list<array<string, mixed>>
     */
    private function worksForCompute(array $works): array
    {
        $out = [];
        foreach ($works as $work) {
            if (! is_array($work)) {
                continue;
            }
            $out[] = [
                'title' => trim((string) ($work['title'] ?? '')),
                'materials' => is_array($work['materials'] ?? null) ? $work['materials'] : [],
                'labour_use_fixed' => filter_var($work['labour_use_fixed'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'labour_fixed_amount' => round((float) ($work['labour_fixed_amount'] ?? 0), 2),
                'labour_fixed_hst_included' => filter_var($work['labour_fixed_hst_included'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'labour_hourly_rate' => (float) ($work['labour_hourly_rate'] ?? 0),
                'labour_mode' => (string) ($work['labour_mode'] ?? 'hours'),
                'labour_quantity' => (float) ($work['labour_quantity'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function persistMaterial(array $m): array
    {
        $catalogId = isset($m['catalog_material_id']) && (int) $m['catalog_material_id'] > 0
            ? (int) $m['catalog_material_id']
            : null;

        return [
            'short_name' => (string) ($m['short_name'] ?? ''),
            'description' => (string) ($m['description'] ?? ''),
            'url' => $m['url'] ?? null,
            'image_url' => $m['image_url'] ?? null,
            'unit_price' => (float) ($m['unit_price'] ?? 0),
            'quantity' => (float) ($m['quantity'] ?? 0),
            'apply_hst' => array_key_exists('apply_hst', $m)
                ? filter_var($m['apply_hst'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'hst_includes_unit' => false,
            'catalog_material_id' => $catalogId,
            'delivery_cad' => round(max(0.0, (float) ($m['delivery_cad'] ?? 0)), 2),
            'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
            'entry_mode' => $catalogId ? 'catalog' : 'manual',
        ];
    }

    private function projectAllowsLink(ClientProject $project): bool
    {
        return ! in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true);
    }

    private function syncProjectQuotation(ClientProject $project, StandardQuotation $quote): void
    {
        $lineDescription = $quote->pdfSummaryLineDescription();
        $taxable = round((float) ($quote->materials_subtotal_before_hst ?? 0) + (float) ($quote->labour_total ?? 0), 2);
        $items = [[
            'description' => $lineDescription !== '' ? $lineDescription : $quote->summaryDescription(),
            'amount' => $taxable,
            'apply_hst' => true,
            'source_standard_quotation_id' => $quote->id,
        ]];

        /** @var ClientProjectQuotation $row */
        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $quote->id,
        ]);

        if (! $row->exists) {
            $row->fill([
                'title' => $quote->summaryDescription(),
                'status' => ClientProjectQuotation::STATUS_DRAFT,
                'type' => ClientProjectQuotation::TYPE_QUOTATION,
                'labour' => (bool) $quote->includes_labour,
                'material' => (bool) $quote->includes_material,
                'quotation_number' => ClientProjectQuotation::generateNumber(ClientProjectQuotation::TYPE_QUOTATION),
                'version' => 1,
            ]);
        } else {
            $row->labour = (bool) $quote->includes_labour;
            $row->material = (bool) $quote->includes_material;
        }

        $row->items = $items;
        $row->work_description = $quote->work_description;
        $row->source_snapshot = $quote->only($quote->getFillable());
        $row->save();

        $project->refresh();
        if (method_exists($project, 'syncFixedContractTotalsFromQuotation')) {
            $project->syncFixedContractTotalsFromQuotation();
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public function enrichLineFromCatalog(array $line): array
    {
        $catalogId = (int) ($line['catalog_material_id'] ?? 0);
        if ($catalogId <= 0) {
            return $line;
        }

        $material = Material::query()->find($catalogId);
        if (! $material) {
            $line['catalog_material_id'] = null;

            return $line;
        }

        $line['catalog_material_id'] = $material->id;
        if (trim((string) ($line['short_name'] ?? '')) === '') {
            $line['short_name'] = $material->displayName();
        }
        if (empty($line['url'])) {
            $line['url'] = $material->storeProductUrl();
        }
        if (empty($line['image_url'])) {
            $line['image_url'] = $material->previewImageUrl();
        }
        if ($material->unit) {
            $line['unit'] = (string) $material->unit;
        }

        return $line;
    }
}

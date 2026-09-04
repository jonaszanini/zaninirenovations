<?php

namespace App\Services;

use App\Models\StandardQuotation;
use App\Support\CatalogMaterialDelivery;

/**
 * Computes totals for a Standard standalone quotation from material rows
 * (unit price × qty, optional 13% HST per row, or tax-included unit pricing)
 * plus untaxed labour (hourly rate × hours).
 */
class StandardQuotationCalculator
{
    public const HST_RATE = 0.13;

    public const LABOUR_HOURS_PER_DAY = 8;

    /**
     * Parse one material row into pre-tax / HST / totals. Returns null when
     * the row is empty and should be ignored.
     *
     * @return array{
     *   hst_includes_unit: bool,
     *   apply_hst: bool,
     *   line_subtotal: float,
     *   line_hst: float,
     *   line_total_with_hst: float,
     *   taxable_pre: float,
     *   exempt_pre: float
     * }|null
     */
    public static function breakdownMaterialRow(array $row): ?array
    {
        $shortName = trim((string) ($row['short_name'] ?? ''));
        $desc = trim((string) ($row['description'] ?? ''));
        $unit = round((float) ($row['unit_price'] ?? 0), 2);
        $qty = (float) ($row['quantity'] ?? 0);
        $delivery = round(max(0.0, (float) ($row['delivery_cad'] ?? 0)), 2);

        if ($shortName === '' && $desc === '' && $unit == 0.0 && $delivery == 0.0 && ($qty == 0.0 || $qty === 0.0)) {
            return null;
        }

        $hstIncludes = filter_var($row['hst_includes_unit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $chargePreTaxHst = !array_key_exists('apply_hst', $row)
            || filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN);

        $lineGross = round(($unit * $qty) + $delivery, 2);

        if ($hstIncludes) {
            $lineSub = round($lineGross / (1 + self::HST_RATE), 2);
            $lineHst = round($lineGross - $lineSub, 2);
            $lineTotal = $lineGross;

            return [
                'hst_includes_unit' => true,
                'apply_hst' => true,
                'line_subtotal' => $lineSub,
                'line_hst' => $lineHst,
                'line_total_with_hst' => $lineTotal,
                'taxable_pre' => $lineSub,
                'exempt_pre' => 0.0,
            ];
        }

        if ($chargePreTaxHst) {
            $lineSub = $lineGross;
            $lineHst = round($lineSub * self::HST_RATE, 2);
            $lineTotal = round($lineSub + $lineHst, 2);

            return [
                'hst_includes_unit' => false,
                'apply_hst' => true,
                'line_subtotal' => $lineSub,
                'line_hst' => $lineHst,
                'line_total_with_hst' => $lineTotal,
                'taxable_pre' => $lineSub,
                'exempt_pre' => 0.0,
            ];
        }

        return [
            'hst_includes_unit' => false,
            'apply_hst' => false,
            'line_subtotal' => $lineGross,
            'line_hst' => 0.0,
            'line_total_with_hst' => $lineGross,
            'taxable_pre' => 0.0,
            'exempt_pre' => $lineGross,
        ];
    }

    /**
     * @param  array<int, array{short_name?: string, description?: string, unit_price?: float|int|string, quantity?: float|int|string, apply_hst?: bool|string|int, hst_includes_unit?: bool|string|int}>  $materialRows
     * @return array{
     *   materials: list<array{short_name: string, description: string, unit_price: float, quantity: float, apply_hst: bool, hst_includes_unit: bool, line_subtotal: float, line_hst: float, line_total_with_hst: float}>,
     *   materials_subtotal_before_hst: float,
     *   materials_subtotal_taxable: float,
     *   materials_subtotal_exempt: float,
     *   materials_hst: float,
     *   materials_with_hst: float,
     *   contingency_enabled: bool,
     *   contingency_percent: float,
     *   contingency_amount: float,
     *   contingency_amount_taxable: float,
     *   contingency_amount_exempt: float,
     *   contingency_hst: float,
     *   labour_hours: float,
     *   labour_total: float,
     *   labour_hst: float,
     *   pre_hst_taxable: float,
     *   pre_hst_exempt: float,
     *   total_hst: float,
     *   grand_total: float
     * }
     */
    public function compute(
        array $materialRows,
        float $hourlyRate,
        string $labourMode,
        float $labourQuantity,
        bool $contingencyEnabled = true,
        float $contingencyPercent = 10.0,
        bool $labourUseFixed = false,
        float $labourFixedAmount = 0.0,
        bool $labourFixedHstIncluded = false
    ): array {
        $cleanMaterials = [];
        $subBefore = 0.0;
        $subTaxable = 0.0;
        $subExempt = 0.0;
        $hstSum = 0.0;
        $withHstSum = 0.0;

        $materialRows = self::withLiveCatalogDelivery($materialRows);

        foreach ($materialRows as $row) {
            $b = self::breakdownMaterialRow($row);
            if ($b === null) {
                continue;
            }

            $shortName = trim((string) ($row['short_name'] ?? ''));
            $desc = trim((string) ($row['description'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $imageUrl = trim((string) ($row['image_url'] ?? ''));
            $unit = round((float) ($row['unit_price'] ?? 0), 2);
            $qty = (float) ($row['quantity'] ?? 0);
            $delivery = round(max(0.0, (float) ($row['delivery_cad'] ?? 0)), 2);

            $subBefore += $b['line_subtotal'];
            $subTaxable += $b['taxable_pre'];
            $subExempt += $b['exempt_pre'];
            $hstSum += $b['line_hst'];
            $withHstSum += $b['line_total_with_hst'];

            $catalogId = isset($row['catalog_material_id']) && $row['catalog_material_id'] !== ''
                ? (int) $row['catalog_material_id']
                : null;

            $lineKind = StandardQuotation::normalizeMaterialLineKind($row);

            $cleanMaterials[] = [
                'short_name' => $shortName,
                'description' => $desc,
                'url' => $url,
                'image_url' => $lineKind === StandardQuotation::LINE_KIND_ELECTRICAL ? '' : $imageUrl,
                'unit_price' => $unit,
                'quantity' => $qty,
                'delivery_cad' => $delivery,
                'apply_hst' => $b['apply_hst'],
                'hst_includes_unit' => $b['hst_includes_unit'],
                'catalog_material_id' => $catalogId > 0 ? $catalogId : null,
                'line_kind' => $lineKind,
                'entry_mode' => $lineKind === StandardQuotation::LINE_KIND_ELECTRICAL
                    ? 'electrical'
                    : (strtolower(trim((string) ($row['entry_mode'] ?? ''))) === 'catalog' || $catalogId > 0 ? 'catalog' : 'manual'),
                'line_subtotal' => $b['line_subtotal'],
                'line_hst' => $b['line_hst'],
                'line_total_with_hst' => $b['line_total_with_hst'],
            ];
        }

        $subBefore = round($subBefore, 2);
        $subTaxable = round($subTaxable, 2);
        $subExempt = round($subExempt, 2);
        $hstSum = round($hstSum, 2);
        $withHstSum = round($withHstSum, 2);

        // Contingency allowance: a buffer % applied on top of the materials
        // pre-HST subtotal. To stay consistent with the per-row HST toggles,
        // HST on the contingency mirrors the effective HST rate of the
        // materials it represents (so an all-exempt materials list yields
        // an exempt contingency, an all-taxed list yields full 13%, mixed
        // yields the prorated effective rate). The contingency is also
        // split into taxable / exempt portions for downstream linking.
        $contingencyPercent = max(0.0, $contingencyPercent);
        if (!$contingencyEnabled || $subBefore <= 0 || $contingencyPercent <= 0) {
            $contingencyAmount = 0.0;
            $contingencyAmountTaxable = 0.0;
            $contingencyAmountExempt = 0.0;
            $contingencyHst = 0.0;
        } else {
            $contingencyAmount = round($subBefore * ($contingencyPercent / 100.0), 2);
            $taxableRatio = $subBefore > 0 ? ($subTaxable / $subBefore) : 0.0;
            $contingencyAmountTaxable = round($contingencyAmount * $taxableRatio, 2);
            // Make the split sum exactly equal to the contingency total so
            // we don't lose a cent to rounding.
            $contingencyAmountExempt = round($contingencyAmount - $contingencyAmountTaxable, 2);
            $contingencyHst = round($contingencyAmountTaxable * self::HST_RATE, 2);
        }

        // Roll contingency into the materials totals so downstream consumers
        // (project quotation line item, grand total) inherit it without any
        // extra plumbing.
        $hstSumWithCont = round($hstSum + $contingencyHst, 2);
        $withHstWithCont = round($withHstSum + $contingencyAmount + $contingencyHst, 2);

        $labourMode = $labourMode === 'days' ? 'days' : 'hours';
        $qty = max(0.0, $labourQuantity);

        if ($labourUseFixed) {
            $labourHours = 0.0;
            if ($labourFixedAmount > 0.00001) {
                $fixedGross = round(max(0.0, $labourFixedAmount), 2);
                if ($labourFixedHstIncluded) {
                    $labourTotal = round($fixedGross / (1 + self::HST_RATE), 2);
                    $labourHst = round($fixedGross - $labourTotal, 2);
                } else {
                    $labourTotal = $fixedGross;
                    $labourHst = round($labourTotal * self::HST_RATE, 2);
                }
            } else {
                $labourTotal = 0.0;
                $labourHst = 0.0;
            }
        } else {
            $labourHours = $labourMode === 'days'
                ? round($qty * self::LABOUR_HOURS_PER_DAY, 2)
                : round($qty, 2);

            $labourTotal = round(max(0.0, $hourlyRate) * $labourHours, 2);

            // Labour is HST-taxable at 13% when priced from rate × time.
            $labourHst = round($labourTotal * self::HST_RATE, 2);
        }

        // Pre-HST splits used by the linking flow to push one or two line
        // items into the project quotation: a taxable line (apply_hst=true)
        // and, if needed, a separate exempt line (apply_hst=false).
        $preHstTaxable = round($subTaxable + $contingencyAmountTaxable + $labourTotal, 2);
        $preHstExempt = round($subExempt + $contingencyAmountExempt, 2);

        $totalHst = round($hstSumWithCont + $labourHst, 2);
        $grand = round($withHstWithCont + $labourTotal + $labourHst, 2);

        return [
            'materials' => $cleanMaterials,
            'materials_subtotal_before_hst' => $subBefore,
            'materials_subtotal_taxable' => $subTaxable,
            'materials_subtotal_exempt' => $subExempt,
            'materials_hst' => $hstSumWithCont,
            'materials_with_hst' => $withHstWithCont,
            'contingency_enabled' => $contingencyEnabled,
            'contingency_percent' => round($contingencyPercent, 2),
            'contingency_amount' => $contingencyAmount,
            'contingency_amount_taxable' => $contingencyAmountTaxable,
            'contingency_amount_exempt' => $contingencyAmountExempt,
            'contingency_hst' => $contingencyHst,
            'labour_hours' => $labourHours,
            'labour_total' => $labourTotal,
            'labour_hst' => $labourHst,
            'pre_hst_taxable' => $preHstTaxable,
            'pre_hst_exempt' => $preHstExempt,
            'total_hst' => $totalHst,
            'grand_total' => $grand,
        ];
    }

    /**
     * Compute one or more jobs, then apply quote-level contingency on the
     * combined materials pre-HST. Labour is the sum of each job.
     *
     * @param  list<array<string, mixed>>  $works
     * @return array<string, mixed>
     */
    public function computeWorks(
        array $works,
        bool $contingencyEnabled = true,
        float $contingencyPercent = 10.0
    ): array {
        if ($works === []) {
            $works = [[
                'title' => '',
                'materials' => [],
                'labour_hourly_rate' => 0,
                'labour_mode' => 'hours',
                'labour_quantity' => 0,
                'labour_use_fixed' => false,
                'labour_fixed_amount' => 0,
                'labour_fixed_hst_included' => false,
            ]];
        }

        $jobResults = [];
        $allMaterials = [];
        $labourHours = 0.0;
        $labourTotal = 0.0;
        $labourHst = 0.0;

        foreach ($works as $work) {
            if (! is_array($work)) {
                continue;
            }

            $job = $this->compute(
                is_array($work['materials'] ?? null) ? $work['materials'] : [],
                (float) ($work['labour_hourly_rate'] ?? 0),
                (string) ($work['labour_mode'] ?? 'hours'),
                (float) ($work['labour_quantity'] ?? 0),
                false,
                0.0,
                filter_var($work['labour_use_fixed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                (float) ($work['labour_fixed_amount'] ?? 0),
                filter_var($work['labour_fixed_hst_included'] ?? false, FILTER_VALIDATE_BOOLEAN)
            );

            $jobResults[] = array_merge($job, [
                'title' => trim((string) ($work['title'] ?? '')),
                'labour_use_fixed' => filter_var($work['labour_use_fixed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'labour_mode' => (($work['labour_mode'] ?? 'hours') === 'days') ? 'days' : 'hours',
                'labour_quantity' => round((float) ($work['labour_quantity'] ?? 0), 2),
                'labour_hourly_rate' => round((float) ($work['labour_hourly_rate'] ?? 0), 2),
                'labour_fixed_amount' => round((float) ($work['labour_fixed_amount'] ?? 0), 2),
                'labour_fixed_hst_included' => filter_var($work['labour_fixed_hst_included'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);

            foreach ($job['materials'] as $row) {
                $allMaterials[] = $row;
            }

            $labourHours += (float) $job['labour_hours'];
            $labourTotal += (float) $job['labour_total'];
            $labourHst += (float) $job['labour_hst'];
        }

        $labourHours = round($labourHours, 2);
        $labourTotal = round($labourTotal, 2);
        $labourHst = round($labourHst, 2);

        $combined = $this->compute(
            $allMaterials,
            0.0,
            'hours',
            0.0,
            $contingencyEnabled,
            $contingencyPercent,
            false,
            0.0,
            false
        );

        $preHstTaxable = round(
            (float) $combined['materials_subtotal_taxable']
            + (float) $combined['contingency_amount_taxable']
            + $labourTotal,
            2
        );
        $preHstExempt = round(
            (float) $combined['materials_subtotal_exempt']
            + (float) $combined['contingency_amount_exempt'],
            2
        );
        $totalHst = round((float) $combined['materials_hst'] + $labourHst, 2);
        $grand = round((float) $combined['materials_with_hst'] + $labourTotal + $labourHst, 2);

        return [
            'works' => $jobResults,
            'materials' => $combined['materials'],
            'materials_subtotal_before_hst' => $combined['materials_subtotal_before_hst'],
            'materials_subtotal_taxable' => $combined['materials_subtotal_taxable'],
            'materials_subtotal_exempt' => $combined['materials_subtotal_exempt'],
            'materials_hst' => $combined['materials_hst'],
            'materials_with_hst' => $combined['materials_with_hst'],
            'contingency_enabled' => $combined['contingency_enabled'],
            'contingency_percent' => $combined['contingency_percent'],
            'contingency_amount' => $combined['contingency_amount'],
            'contingency_amount_taxable' => $combined['contingency_amount_taxable'],
            'contingency_amount_exempt' => $combined['contingency_amount_exempt'],
            'contingency_hst' => $combined['contingency_hst'],
            'labour_hours' => $labourHours,
            'labour_total' => $labourTotal,
            'labour_hst' => $labourHst,
            'pre_hst_taxable' => $preHstTaxable,
            'pre_hst_exempt' => $preHstExempt,
            'total_hst' => $totalHst,
            'grand_total' => $grand,
        ];
    }

    /**
     * Overlay live catalog delivery onto linked rows (once per line, not × qty).
     *
     * @param  list<array<string, mixed>>  $materialRows
     * @return list<array<string, mixed>>
     */
    public static function withLiveCatalogDelivery(array $materialRows): array
    {
        $ids = [];
        foreach ($materialRows as $row) {
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $map = CatalogMaterialDelivery::mapForIds($ids);
        foreach ($materialRows as $i => $row) {
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0 && array_key_exists($id, $map)) {
                $materialRows[$i]['delivery_cad'] = $map[$id];
            }
        }

        return $materialRows;
    }
}

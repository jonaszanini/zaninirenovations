<?php

namespace App\Services;

/**
 * Computes totals for a Standard standalone quotation from material rows
 * (unit price × qty, optional 13% HST per row) plus untaxed labour
 * (hourly rate × hours).
 */
class StandardQuotationCalculator
{
    public const HST_RATE = 0.13;

    public const LABOUR_HOURS_PER_DAY = 8;

    /**
     * @param  array<int, array{short_name?: string, description?: string, unit_price?: float|int|string, quantity?: float|int|string, apply_hst?: bool|string|int}>  $materialRows
     * @return array{
     *   materials: list<array{short_name: string, description: string, unit_price: float, quantity: float, apply_hst: bool, line_subtotal: float, line_hst: float, line_total_with_hst: float}>,
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
        float $contingencyPercent = 10.0
    ): array {
        $cleanMaterials = [];
        $subBefore = 0.0;
        $subTaxable = 0.0;
        $subExempt = 0.0;
        $hstSum = 0.0;
        $withHstSum = 0.0;

        foreach ($materialRows as $row) {
            $shortName = trim((string) ($row['short_name'] ?? ''));
            $desc = trim((string) ($row['description'] ?? ''));
            $unit = round((float) ($row['unit_price'] ?? 0), 2);
            $qty = (float) ($row['quantity'] ?? 0);
            $applyHst = !array_key_exists('apply_hst', $row)
                || filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN);

            // Drop fully-empty rows (no name, no description, no money/qty).
            if ($shortName === '' && $desc === '' && $unit == 0.0 && ($qty == 0.0 || $qty === 0.0)) {
                continue;
            }

            $lineSub = round($unit * $qty, 2);
            $lineHst = $applyHst ? round($lineSub * self::HST_RATE, 2) : 0.0;
            $lineTotal = round($lineSub + $lineHst, 2);

            $subBefore += $lineSub;
            if ($applyHst) {
                $subTaxable += $lineSub;
            } else {
                $subExempt += $lineSub;
            }
            $hstSum += $lineHst;
            $withHstSum += $lineTotal;

            $cleanMaterials[] = [
                'short_name' => $shortName,
                'description' => $desc,
                'unit_price' => $unit,
                'quantity' => $qty,
                'apply_hst' => $applyHst,
                'line_subtotal' => $lineSub,
                'line_hst' => $lineHst,
                'line_total_with_hst' => $lineTotal,
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
        $labourHours = $labourMode === 'days'
            ? round($qty * self::LABOUR_HOURS_PER_DAY, 2)
            : round($qty, 2);

        $labourTotal = round(max(0.0, $hourlyRate) * $labourHours, 2);

        // Labour is now always HST-taxable (renovation services are HST
        // applicable in Ontario). The HST is computed and stored separately
        // so the totals box and grand summary can show the breakdown.
        $labourHst = round($labourTotal * self::HST_RATE, 2);

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
}

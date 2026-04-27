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
     *   materials_hst: float,
     *   materials_with_hst: float,
     *   contingency_enabled: bool,
     *   contingency_percent: float,
     *   contingency_amount: float,
     *   contingency_hst: float,
     *   labour_hours: float,
     *   labour_total: float,
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
        $hstSum = round($hstSum, 2);
        $withHstSum = round($withHstSum, 2);

        // Contingency allowance: a buffer % applied on top of the materials
        // pre-HST subtotal. To stay consistent with the per-row HST toggles,
        // HST on the contingency mirrors the effective HST rate of the
        // materials it represents (so an all-exempt materials list yields
        // an exempt contingency, an all-taxed list yields full 13%, mixed
        // yields the prorated effective rate).
        $contingencyPercent = max(0.0, $contingencyPercent);
        if (!$contingencyEnabled || $subBefore <= 0 || $contingencyPercent <= 0) {
            $contingencyAmount = 0.0;
            $contingencyHst = 0.0;
        } else {
            $contingencyAmount = round($subBefore * ($contingencyPercent / 100.0), 2);
            $effectiveHstRate = $subBefore > 0 ? ($hstSum / $subBefore) : 0.0;
            $contingencyHst = round($contingencyAmount * $effectiveHstRate, 2);
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
        $grand = round($withHstWithCont + $labourTotal, 2);

        return [
            'materials' => $cleanMaterials,
            'materials_subtotal_before_hst' => $subBefore,
            'materials_hst' => $hstSumWithCont,
            'materials_with_hst' => $withHstWithCont,
            'contingency_enabled' => $contingencyEnabled,
            'contingency_percent' => round($contingencyPercent, 2),
            'contingency_amount' => $contingencyAmount,
            'contingency_hst' => $contingencyHst,
            'labour_hours' => $labourHours,
            'labour_total' => $labourTotal,
            'grand_total' => $grand,
        ];
    }
}

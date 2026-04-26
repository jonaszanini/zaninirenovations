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
     * @param  array<int, array{description?: string, unit_price?: float|int|string, quantity?: float|int|string, apply_hst?: bool|string|int}>  $materialRows
     * @return array{
     *   materials: list<array{description: string, unit_price: float, quantity: float, apply_hst: bool, line_subtotal: float, line_hst: float, line_total_with_hst: float}>,
     *   materials_subtotal_before_hst: float,
     *   materials_hst: float,
     *   materials_with_hst: float,
     *   labour_hours: float,
     *   labour_total: float,
     *   grand_total: float
     * }
     */
    public function compute(
        array $materialRows,
        float $hourlyRate,
        string $labourMode,
        float $labourQuantity
    ): array {
        $cleanMaterials = [];
        $subBefore = 0.0;
        $hstSum = 0.0;
        $withHstSum = 0.0;

        foreach ($materialRows as $row) {
            $desc = trim((string) ($row['description'] ?? ''));
            $unit = round((float) ($row['unit_price'] ?? 0), 2);
            $qty = (float) ($row['quantity'] ?? 0);
            $applyHst = !array_key_exists('apply_hst', $row)
                || filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN);

            if ($desc === '' && $unit == 0.0 && ($qty == 0.0 || $qty === 0.0)) {
                continue;
            }

            $lineSub = round($unit * $qty, 2);
            $lineHst = $applyHst ? round($lineSub * self::HST_RATE, 2) : 0.0;
            $lineTotal = round($lineSub + $lineHst, 2);

            $subBefore += $lineSub;
            $hstSum += $lineHst;
            $withHstSum += $lineTotal;

            $cleanMaterials[] = [
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

        $labourMode = $labourMode === 'days' ? 'days' : 'hours';
        $qty = max(0.0, $labourQuantity);
        $labourHours = $labourMode === 'days'
            ? round($qty * self::LABOUR_HOURS_PER_DAY, 2)
            : round($qty, 2);

        $labourTotal = round(max(0.0, $hourlyRate) * $labourHours, 2);
        $grand = round($withHstSum + $labourTotal, 2);

        return [
            'materials' => $cleanMaterials,
            'materials_subtotal_before_hst' => $subBefore,
            'materials_hst' => $hstSum,
            'materials_with_hst' => $withHstSum,
            'labour_hours' => $labourHours,
            'labour_total' => $labourTotal,
            'grand_total' => $grand,
        ];
    }
}

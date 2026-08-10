<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Free-form additional costs on selected quotation calculators.
 *
 * Each row: description + amount + whether the amount already includes HST.
 * Totals are rolled into the materials portion for admin live breakdowns and
 * folded into materials on the client PDF (never shown as their own lines).
 */
class AdditionalCostsService
{
    public const HST_RATE = 0.13;

    public const LINE_PREFIX = 'Additional cost';

    /** @return array<string, mixed> */
    public static function validationRules(): array
    {
        return [
            'additional_costs' => ['nullable', 'array', 'max:40'],
            'additional_costs.*.description' => ['nullable', 'string', 'max:200'],
            'additional_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'additional_costs.*.hst_included' => ['nullable', 'in:0,1'],
        ];
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'additional_costs.max' => 'You can add at most 40 additional costs.',
            'additional_costs.*.description.max' => 'Keep each additional cost description under 200 characters.',
            'additional_costs.*.amount.numeric' => 'Enter a valid additional cost amount.',
            'additional_costs.*.amount.min' => 'Additional cost amounts cannot be negative.',
        ];
    }

    /**
     * @return list<array{description: string, amount: float, hst_included: bool}>
     */
    public function persistenceFromRequest(Request $request): array
    {
        $raw = $request->input('additional_costs', []);
        if (! is_array($raw)) {
            return [];
        }

        return self::normalizeRows($raw);
    }

    /**
     * @param  mixed  $rows
     * @return list<array{description: string, amount: float, hst_included: bool}>
     */
    public static function normalizeRows($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));
            $amountRaw = $row['amount'] ?? null;
            $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : 0.0;
            if ($amount < 0) {
                $amount = 0.0;
            }

            $hstRaw = $row['hst_included'] ?? '1';
            if (is_array($hstRaw)) {
                $hstIncluded = in_array('1', $hstRaw, true) || in_array(1, $hstRaw, true) || in_array(true, $hstRaw, true);
            } else {
                $hstIncluded = $hstRaw === true || $hstRaw === 1 || $hstRaw === '1';
            }

            // Drop completely empty rows.
            if ($description === '' && $amount <= 0) {
                continue;
            }

            $out[] = [
                'description' => $description !== '' ? $description : 'Additional cost',
                'amount' => $amount,
                'hst_included' => $hstIncluded,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{description?: string, amount?: float|int|string, hst_included?: bool|int|string}>  $rows
     */
    public static function materialsPreTaxTotal(array $rows): float
    {
        $sum = 0.0;
        foreach (self::normalizeRows($rows) as $row) {
            $sum += self::rowPreTax((float) $row['amount'], (bool) $row['hst_included']);
        }

        return round($sum, 2);
    }

    /**
     * @param  list<array{description?: string, amount?: float|int|string, hst_included?: bool|int|string}>  $rows
     */
    public static function materialsHstTotal(array $rows): float
    {
        return round(self::materialsPreTaxTotal($rows) * self::HST_RATE, 2);
    }

    /**
     * Tax-inclusive total to add to grand / final quote totals.
     *
     * @param  list<array{description?: string, amount?: float|int|string, hst_included?: bool|int|string}>  $rows
     */
    public static function taxInclusiveTotal(array $rows): float
    {
        $sum = 0.0;
        foreach (self::normalizeRows($rows) as $row) {
            $amount = (float) $row['amount'];
            if ($amount <= 0) {
                continue;
            }
            $sum += $row['hst_included']
                ? $amount
                : round($amount * (1 + self::HST_RATE), 2);
        }

        return round($sum, 2);
    }

    public static function rowPreTax(float $amount, bool $hstIncluded): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        if ($hstIncluded) {
            return round($amount / (1 + self::HST_RATE), 2);
        }

        return round($amount, 2);
    }

    /**
     * Hidden project lines (HST-inclusive amounts) for PDF folding into materials.
     *
     * @param  list<array{description?: string, amount?: float|int|string, hst_included?: bool|int|string}>  $rows
     * @return list<array{description: string, amount: float, apply_hst: bool, hst_included: bool}>
     */
    public static function projectLineItems(array $rows): array
    {
        $lines = [];
        foreach (self::normalizeRows($rows) as $row) {
            $taxIn = $row['hst_included']
                ? (float) $row['amount']
                : round((float) $row['amount'] * (1 + self::HST_RATE), 2);
            if ($taxIn <= 0) {
                continue;
            }

            $label = trim((string) $row['description']);
            $lines[] = [
                'description' => self::LINE_PREFIX.($label !== '' && $label !== 'Additional cost' ? ' — '.$label : ''),
                'amount' => $taxIn,
                'apply_hst' => false,
                'hst_included' => true,
            ];
        }

        return $lines;
    }

    public static function isAdditionalCostLineDescription(string $description): bool
    {
        return str_starts_with($description, self::LINE_PREFIX);
    }
}

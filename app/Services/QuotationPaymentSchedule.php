<?php

namespace App\Services;

/**
 * Client-facing payment schedule options for quotations (1–4 installments).
 *
 * Percent splits match {@see RecalculateRemainingInstallmentsService::REMAINING_DEFAULTS}.
 */
class QuotationPaymentSchedule
{
    public const ALLOWED = [1, 2, 3, 4];

    /** Legacy / missing value — previous hardcoded PDF wording. */
    public const DEFAULT_INSTALLMENTS = 3;

    /**
     * @return array<int, list<float>>
     */
    public static function percentagesByPlan(): array
    {
        return RecalculateRemainingInstallmentsService::REMAINING_DEFAULTS;
    }

    /**
     * @param array<int|string, float|int|string>|null $customPercentages
     * @return list<float>
     */
    public static function percentages(int $installments, ?array $customPercentages = null): array
    {
        $plan = self::normalize($installments);
        if (is_array($customPercentages) && count($customPercentages) === $plan) {
            $parsed = array_map(fn ($v) => (float) $v, array_values($customPercentages));
            $sum = array_sum($parsed);
            if (abs($sum - 100.0) < 0.1 && ! array_filter($parsed, fn ($v) => $v <= 0)) {
                return $parsed;
            }
        }

        $map = self::percentagesByPlan();

        return $map[$plan] ?? $map[self::DEFAULT_INSTALLMENTS];
    }

    public static function normalize(?int $installments): int
    {
        $n = (int) ($installments ?? 0);

        return in_array($n, self::ALLOWED, true) ? $n : self::DEFAULT_INSTALLMENTS;
    }

    /**
     * PDF / email bullet lines: [title, detail] pairs.
     *
     * @param array<int|string, float|int|string>|null $customPercentages
     * @return list<array{0: string, 1: string}>
     */
    public static function terms(?int $installments, ?array $customPercentages = null): array
    {
        $plan = self::normalize($installments);
        $pct = self::percentages($plan, $customPercentages);

        return match ($plan) {
            1 => [
                [
                    self::pctLabel($pct[0]).' Completion Payment',
                    'Due upon final completion of the work.',
                ],
            ],
            2 => [
                [
                    self::pctLabel($pct[0]).' Secure Booking Deposit',
                    'Required to reserve your project dates in our schedule.',
                ],
                [
                    self::pctLabel($pct[1]).' Completion Payment',
                    'Due upon final completion of the work.',
                ],
            ],
            4 => [
                [
                    self::pctLabel($pct[0]).' Secure Booking Deposit',
                    'Required to reserve your project dates in our schedule.',
                ],
                [
                    self::pctLabel($pct[1]).' Commencement Payment',
                    'Due one day prior to the project start date.',
                ],
                [
                    self::pctLabel($pct[2]).' Mid-project Payment',
                    'Due at the mid-point of the project.',
                ],
                [
                    self::pctLabel($pct[3]).' Completion Payment',
                    'Due upon final completion of the work.',
                ],
            ],
            default => [
                [
                    self::pctLabel($pct[0]).' Secure Booking Deposit',
                    'Required to reserve your project dates in our schedule.',
                ],
                [
                    self::pctLabel($pct[1]).' Commencement Payment',
                    'Due one day prior to the project start date.',
                ],
                [
                    self::pctLabel($pct[2]).' Completion Payment',
                    'Due upon final completion of the work.',
                ],
            ],
        };
    }

    /**
     * Option cards for the quotation form UI.
     *
     * @return list<array{value: int, label: string, summary: string}>
     */
    public static function formOptions(): array
    {
        $options = [];
        foreach (self::ALLOWED as $n) {
            $pct = self::percentages($n);
            $options[] = [
                'value' => $n,
                'label' => $n.' inst.',
                'summary' => implode(' / ', array_map(fn ($p) => self::pctLabel($p), $pct)),
            ];
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public static function validationRules(): array
    {
        return [
            'payment_installments' => ['required', 'integer', 'in:'.implode(',', self::ALLOWED)],
            'payment_percentages' => ['nullable', 'array'],
            'payment_percentages.*' => ['nullable', 'array'],
            'payment_percentages.*.*' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'payment_installments.required' => 'Choose how many payment installments this quotation will use (1–4).',
            'payment_installments.in' => 'Choose how many payment installments this quotation will use (1–4).',
        ];
    }

    protected static function pctLabel(float $pct): string
    {
        $formatted = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');

        return $formatted.'%';
    }
}

<?php

namespace App\Services;

use App\Support\CadMoney;
use Illuminate\Http\Request;

/**
 * Optional debris-removal charge on quotations.
 *
     * Amount is treated as HST-inclusive (same as commute): it is added to the
     * quotation grand total. The client PDF lists the entered amount as its own
     * line ("Debris removal and disposal") with HST included in that figure.
 */
class DebrisRemovalExpenseService
{
    public const LINE_DESCRIPTION = 'Debris removal and disposal';

    public static function isLineDescription(string $description): bool
    {
        return str_starts_with($description, 'Debris removal');
    }

    /** Strip thousands separators before Laravel `numeric` validation. */
    public static function normalizeAmountOnRequest(Request $request): void
    {
        if (! $request->exists('debris_removal_amount')) {
            return;
        }

        $request->merge([
            'debris_removal_amount' => CadMoney::stripGrouping($request->input('debris_removal_amount')),
        ]);
    }

    /** @return array<string, mixed> */
    public static function validationRules(): array
    {
        return [
            'include_debris_removal' => ['required', 'in:0,1'],
            'debris_removal_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'include_debris_removal.required' => 'Choose whether to include debris removal in this quotation.',
            'include_debris_removal.in' => 'Choose whether to include debris removal in this quotation.',
            'debris_removal_amount.numeric' => 'Enter a valid debris removal amount.',
            'debris_removal_amount.min' => 'Debris removal amount cannot be negative.',
        ];
    }

    /**
     * @return array{include_debris_removal: bool, debris_removal_amount: float}
     */
    public function persistenceFromRequest(Request $request): array
    {
        $includeRaw = $request->input('include_debris_removal');
        if ($request->has('include_debris_removal')) {
            if (is_array($includeRaw)) {
                $include = in_array('1', $includeRaw, true) || in_array(1, $includeRaw, true);
            } else {
                $include = $includeRaw === true || $includeRaw === 1 || $includeRaw === '1';
            }
        } else {
            $include = false;
        }

        $parsed = CadMoney::parse($request->input('debris_removal_amount', 0));
        $amount = $parsed !== null ? round($parsed, 2) : 0.0;
        if ($amount < 0) {
            $amount = 0.0;
        }

        if (! $include) {
            $amount = 0.0;
        }

        return [
            'include_debris_removal' => $include,
            'debris_removal_amount' => $amount,
        ];
    }

    /**
     * @return array{description: string, amount: float, apply_hst: bool, hst_included: bool}|null
     */
    public static function projectLineItem(float $amount): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        return [
            'description' => self::LINE_DESCRIPTION,
            'amount' => round($amount, 2),
            'apply_hst' => false,
            'hst_included' => true,
        ];
    }
}

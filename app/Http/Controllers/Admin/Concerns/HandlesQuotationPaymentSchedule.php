<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ClientProjectQuotation;
use App\Services\QuotationPaymentSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesQuotationPaymentSchedule
{
    /**
     * @return array{payment_installments: int, payment_percentages: list<float>|null}
     */
    protected function paymentScheduleFromRequest(Request $request): array
    {
        $installments = (int) $request->input('payment_installments');
        $percentagesRaw = $request->input('payment_percentages');
        $percentages = null;

        if (is_array($percentagesRaw) && isset($percentagesRaw[$installments])) {
            $planPcts = $percentagesRaw[$installments];
            if (is_array($planPcts) && count($planPcts) === $installments) {
                $parsed = array_values(array_map(fn ($v) => round((float) $v, 2), $planPcts));
                $sum = array_sum($parsed);
                if (abs($sum - 100.0) < 0.1 && ! array_filter($parsed, fn ($v) => $v <= 0)) {
                    $percentages = $parsed;
                }
            }
        }

        return [
            'payment_installments' => $installments,
            'payment_percentages' => $percentages,
        ];
    }

    protected function applyPaymentScheduleToProjectQuotation(
        ClientProjectQuotation $row,
        Model $sourceQuotation
    ): void {
        $n = (int) ($sourceQuotation->payment_installments ?? 0);
        if (in_array($n, QuotationPaymentSchedule::ALLOWED, true)) {
            $row->payment_installments = $n;
            $row->payment_percentages = $sourceQuotation->payment_percentages ?? null;
        }
    }
}

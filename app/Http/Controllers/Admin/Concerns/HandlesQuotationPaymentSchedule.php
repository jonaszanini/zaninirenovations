<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ClientProjectQuotation;
use App\Services\QuotationPaymentSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesQuotationPaymentSchedule
{
    /**
     * @return array{payment_installments: int}
     */
    protected function paymentScheduleFromRequest(Request $request): array
    {
        return [
            'payment_installments' => (int) $request->input('payment_installments'),
        ];
    }

    protected function applyPaymentScheduleToProjectQuotation(
        ClientProjectQuotation $row,
        Model $sourceQuotation
    ): void {
        $n = (int) ($sourceQuotation->payment_installments ?? 0);
        if (in_array($n, QuotationPaymentSchedule::ALLOWED, true)) {
            $row->payment_installments = $n;
        }
    }
}

<?php

namespace App\Services\Invoice;

use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Repairs invoices that are still unpaid while a payment was already recorded.
 *
 * An invoice is repaired only when its status is not paid and not refunded,
 * and at least one of these is true:
 * - the linked payment-plan installment has is_paid set
 * - paid_at is set
 * - paid_amount is greater than zero
 * - payment_method is filled
 *
 * Already paid invoices are skipped entirely: status, amount, paid date,
 * and reminder pause are left untouched, and no client email is sent.
 * This service never sends mail.
 */
class RepairPaidInvoiceMismatchService
{
    /**
     * @return Collection<int, ClientProjectInvoice>
     */
    public function mismatches(): Collection
    {
        return ClientProjectInvoice::query()
            ->with(['project.installments'])
            ->whereNotIn('status', [
                ClientProjectInvoice::STATUS_PAID,
                ClientProjectInvoice::STATUS_REFUNDED,
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (ClientProjectInvoice $invoice) => $this->isMismatch($invoice))
            ->values();
    }

    public function isMismatch(ClientProjectInvoice $invoice): bool
    {
        if ($invoice->isPaid() || $invoice->isRefunded()) {
            return false;
        }

        if ($invoice->paid_at !== null) {
            return true;
        }

        if (round((float) ($invoice->paid_amount ?? 0), 2) > 0) {
            return true;
        }

        if (filled($invoice->payment_method)) {
            return true;
        }

        return $invoice->linkedInstallmentIsPaid();
    }

    /**
     * @return list<string> invoice numbers that were changed
     */
    public function repair(): array
    {
        $changed = [];

        foreach ($this->mismatches() as $invoice) {
            $fresh = $invoice->fresh(['project.installments']);
            if (! $fresh || ! $this->isMismatch($fresh) || $fresh->isPaid() || $fresh->isRefunded()) {
                continue;
            }

            $paidOn = $fresh->paid_at ?? $this->recordedInstallmentPaidOn($fresh);
            if ($fresh->paid_at === null && $paidOn !== null) {
                $fresh->paid_at = $paidOn;
            }

            if (round((float) ($fresh->paid_amount ?? 0), 2) <= 0) {
                $fresh->paid_amount = round((float) $fresh->total, 2);
            }

            $fresh->status = ClientProjectInvoice::STATUS_PAID;
            if ($fresh->reminders_paused_at === null) {
                $fresh->reminders_paused_at = now();
            }

            $fresh->save();

            Log::info('Repaired paid invoice mismatch', [
                'invoice_number' => $fresh->invoice_number,
            ]);

            $changed[] = (string) $fresh->invoice_number;
        }

        return $changed;
    }

    private function recordedInstallmentPaidOn(ClientProjectInvoice $invoice): mixed
    {
        $installment = $invoice->linkedInstallment();
        if (! $installment instanceof ClientProjectInstallment || ! $installment->is_paid) {
            return null;
        }

        return $installment->paid_on;
    }
}

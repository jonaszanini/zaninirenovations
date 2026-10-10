<?php

namespace App\Services\Invoice;

use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Support\CadMoney;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pure invoice math: locked (installment-derived) subtotal/HST/total,
 * discount resolution, and line-item normalisation. Extracted from
 * ClientProjectInvoiceController — no persistence or I/O beyond a couple
 * of read/write queries needed to keep expense↔invoice links in sync.
 */
class InvoiceTotalsCalculator
{
    /**
     * The pre-HST amount the invoice's line items must sum to. For
     * installment-linked invoices this is the installment's pre-tax
     * amount (already persisted on the installment row as `amount`).
     * For manual invoices there is no lock — return whatever the user
     * passed (or the invoice's own subtotal as a fallback).
     */
    public function lockedSubtotal(ClientProject $project, ClientProjectInvoice $invoice, $manualOverride = null): float
    {
        if ($invoice->isManual()) {
            if ($manualOverride !== null && is_numeric($manualOverride)) {
                return round((float) $manualOverride, 2);
            }

            return round((float) $invoice->subtotal, 2);
        }

        $installment = $project->installments()
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->get($invoice->installment_number - 1);

        return round((float) ($installment->amount ?? 0), 2);
    }

    /**
     * HST that the invoice should bill, given a subtotal.
     *
     * Installment-linked invoices: HST is sliced proportionally from the
     * quotation's actual HST (some quotation lines may have been HST-exempt,
     * so 13% × subtotal would over-charge). The split is anchored on the
     * locked installment subtotal. When the billed subtotal differs (discount),
     * HST scales by the same ratio so the quotation's tax mix is preserved.
     *
     * Manual / hourly invoices: standard 13% on the supplied subtotal.
     */
    public function computeInvoiceHst(ClientProject $project, ClientProjectInvoice $invoice, float $subtotal): float
    {
        // Cash-paid invoices: no HST charged regardless of installment vs manual.
        if ($invoice->isHstExempt()) {
            return 0.0;
        }

        if ($invoice->isManual()) {
            return round($subtotal * ClientProjectInvoice::HST_RATE, 2);
        }

        $installment = $project->installments()
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->get($invoice->installment_number - 1);

        if (!$installment) {
            return round($subtotal * ClientProjectInvoice::HST_RATE, 2);
        }

        $lockedBase = round((float) ($installment->amount ?? 0), 2);
        $lockedHst = round((float) $installment->effectiveHst(), 2);

        if ($lockedBase <= 0.00001) {
            return round($subtotal * ClientProjectInvoice::HST_RATE, 2);
        }

        return round($lockedHst * ($subtotal / $lockedBase), 2);
    }

    /**
     * The HST amount the invoice will bill (matches whatever
     * computeInvoiceHst() resolves to at the locked subtotal). Used by
     * the editor to show the locked HST breakdown alongside the locked
     * subtotal/total without having to re-derive it on the front end.
     */
    public function lockedHst(ClientProject $project, ClientProjectInvoice $invoice): float
    {
        if ($invoice->isHstExempt()) {
            return 0.0;
        }

        if ($invoice->isManual()) {
            $sub = $this->lockedSubtotal($project, $invoice);

            return round($sub * ClientProjectInvoice::HST_RATE, 2);
        }

        return $this->computeInvoiceHst($project, $invoice, $this->lockedSubtotal($project, $invoice));
    }

    public function lockedTotalWithHst(ClientProject $project, ClientProjectInvoice $invoice): float
    {
        return round($this->lockedSubtotal($project, $invoice) + $this->lockedHst($project, $invoice), 2);
    }

    /**
     * Resolve installment invoice discount: percentage takes precedence over a fixed amount.
     *
     * @return array{0: float|null, 1: float|null, 2: float} Stored percent, stored amount column, dollars discounted.
     */
    public function resolveInstallmentDiscountFromRequest(Request $request, float $grossLineSubtotal): array
    {
        $rawPct = $request->input('discount_percent');
        $rawAmt = $request->input('discount_amount');
        $pct = is_numeric($rawPct) ? (float) $rawPct : 0.0;
        $amt = $this->parseHybridDiscountCadInput($rawAmt, $grossLineSubtotal);

        if ($pct > 0.00001) {
            $d = round(min($grossLineSubtotal * $pct / 100, $grossLineSubtotal), 2);

            return [round($pct, 4), null, $d];
        }

        if ($amt > 0.00001) {
            $d = round(min($amt, $grossLineSubtotal), 2);

            return [null, $d, $d];
        }

        return [null, null, 0.0];
    }

    /**
     * Discount CAD is typed as dollars (4.75 stays 4.75). Never treat
     * digit-only strings as minor units / 100.
     */
    public function parseHybridDiscountCadInput(mixed $raw, float $grossLineSubtotal): float
    {
        $parsed = CadMoney::parse($raw);
        if ($parsed === null || $parsed <= 0) {
            return 0.0;
        }

        return round(min($parsed, $grossLineSubtotal), 2);
    }

    public function normaliseItems(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($description === '' && $amount == 0.0) {
                continue;
            }
            $clean[] = [
                'description' => $description,
                'amount' => round($amount, 2),
            ];
        }

        return $clean;
    }

    /** @return array{0: float, 1: float} Labour (pre-HST) and materials (tax-included) totals. */
    public function partitionHourlyLineAmounts(array $items): array
    {
        $labour = 0.0;
        $materials = 0.0;
        foreach ($items as $row) {
            $amt = round((float) ($row['amount'] ?? 0), 2);
            $kind = $row['line_kind'] ?? ClientProjectInvoice::LINE_KIND_LABOUR;
            if ($kind === ClientProjectInvoice::LINE_KIND_MATERIALS || $kind === ClientProjectInvoice::LINE_KIND_GAS) {
                $materials += $amt;
            } elseif ($kind === ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT) {
                $labour -= abs($amt);
            } else {
                $labour += $amt;
            }
        }

        return [round($labour, 2), round($materials, 2)];
    }

    /**
     * Normalise hourly invoice rows + validate materials lines that reference
     * a project expense snapshot.
     *
     * @return array<int, array{description: string, amount: float, line_kind: string, expense_id?: int}>
     */
    public function normaliseHourlyInvoiceItems(ClientProject $project, array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            $kind = strtolower(trim((string) ($row['line_kind'] ?? ClientProjectInvoice::LINE_KIND_LABOUR)));
            if ($kind === ClientProjectInvoice::LINE_KIND_MATERIALS || $kind === ClientProjectInvoice::LINE_KIND_GAS) {
                // keep
            } elseif ($kind === ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT) {
                $kind = ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT;
            } else {
                $kind = ClientProjectInvoice::LINE_KIND_LABOUR;
            }
            if ($description === '' && $amount == 0.0) {
                continue;
            }

            if ($kind === ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT) {
                $amount = abs($amount);
                if ($amount < 0.0001) {
                    continue;
                }
            }
            if (($kind === ClientProjectInvoice::LINE_KIND_MATERIALS || $kind === ClientProjectInvoice::LINE_KIND_GAS) && $amount < 0) {
                throw ValidationException::withMessages([
                    'items' => 'Materials and gas amounts cannot be negative.',
                ]);
            }

            $out = [
                'description' => $description,
                'amount' => round($amount, 2),
                'line_kind' => $kind,
            ];

            $eid = isset($row['expense_id']) ? (int) $row['expense_id'] : null;
            if ($eid && $kind === ClientProjectInvoice::LINE_KIND_MATERIALS) {
                $owns = ClientProjectExpense::query()
                    ->where('client_project_id', $project->id)
                    ->where('id', $eid)
                    ->exists();
                if ($owns) {
                    $out['expense_id'] = $eid;
                }
            }

            $clean[] = $out;
        }

        return $clean;
    }

    /**
     * Keep client_project_expenses.client_project_invoice_id aligned with
     * materials lines that reference an expense snapshot.
     */
    public function syncHourlyExpenseLinks(ClientProject $project, ClientProjectInvoice $invoice, array $items): void
    {
        $wanted = [];
        foreach ($items as $row) {
            if (($row['line_kind'] ?? '') === ClientProjectInvoice::LINE_KIND_MATERIALS && ! empty($row['expense_id'])) {
                $wanted[] = (int) $row['expense_id'];
            }
        }
        $wanted = array_values(array_unique($wanted));

        $clear = ClientProjectExpense::query()
            ->where('client_project_id', $project->id)
            ->where('client_project_invoice_id', $invoice->id);

        if (count($wanted) > 0) {
            $clear->whereNotIn('id', $wanted);
        }

        $clear->update(['client_project_invoice_id' => null]);

        foreach ($wanted as $id) {
            ClientProjectExpense::query()
                ->where('client_project_id', $project->id)
                ->where('id', $id)
                ->update(['client_project_invoice_id' => $invoice->id]);
        }
    }
}

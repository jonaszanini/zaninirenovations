<?php

namespace App\Services\Invoice;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use App\Models\ClientProjectTimeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Builds new draft invoices (installment-linked, manual, or from unbilled
 * hourly time entries). Extracted from ClientProjectInvoiceController —
 * depends on InvoiceTotalsCalculator for the hourly labour/materials split.
 */
class InvoiceCreationService
{
    public function __construct(protected InvoiceTotalsCalculator $totals)
    {
    }

    /**
     * Snapshot the canonical installment description into the invoice at
     * creation time. We deliberately don't keep it perfectly in sync with
     * the Financials tab afterwards, because the number has already been
     * emailed to the client — changing wording on a paid/sent invoice
     * would be misleading.
     */
    public function createInvoice(ClientProject $project, ?int $installmentNumber): ClientProjectInvoice
    {
        $plan = (int) $project->payment_plan ?: 1;
        $description = null;
        $items = [];
        $subtotal = 0.0;

        // Installment-linked invoices get seeded with a single pre-filled
        // line item that mirrors the installment (description + pre-HST
        // amount), so the user usually just hits "Send". Manual invoices
        // stay empty and let the user enter everything.
        $hst = 0.0;

        if ($installmentNumber !== null) {
            $description = ClientProjectInvoice::installmentDescriptionFor($plan, $installmentNumber);

            $installment = $project->installments()
                ->orderBy('sort_order')
                ->get()
                ->values()
                ->get($installmentNumber - 1);

            if ($installment) {
                $subtotal = round((float) $installment->amount, 2);
                $items = [[
                    'description' => $description ?: 'Installment '.$installmentNumber,
                    'amount' => $subtotal,
                ]];
                // HST for installment-linked invoices comes from the
                // quotation's actual HST share, NOT a flat 13% — see
                // ClientProjectInstallment::effectiveHst() for the rationale.
                $hst = round((float) $installment->effectiveHst(), 2);
            } else {
                $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);
            }
        } else {
            // Manual invoice: seed pre-tax / HST from accepted quotations on fixed-price jobs.
            if (!$project->isHourly()) {
                $qSub = $project->quotationSubtotalForFinancials();
                if ($qSub !== null && $qSub >= 0.01) {
                    $subtotal = $qSub;
                    $hst = round((float) $project->acceptedQuotationsFinancialAggregateHst(), 2);
                } else {
                    $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);
                }
            } else {
                $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);
            }
        }

        $snap = $this->buildAcceptedQuotationsSnapshot($project);

        return $project->invoices()->create([
            'installment_number' => $installmentNumber,
            'installment_description' => $description,
            'invoice_number' => ClientProjectInvoice::generateNumber(),
            'items' => $items,
            'subtotal' => $subtotal,
            'hst' => $hst,
            'hst_exempt' => false,
            'total' => round($subtotal + $hst, 2),
            'status' => ClientProjectInvoice::STATUS_DRAFT,
            'version' => 1,
            'included_project_quotation_ids' => $snap['ids'],
            'included_quotations_snapshot' => $snap['rows'],
        ]);
    }

    /**
     * @return array{ids: list<int>, rows: list<array<string, mixed>>}
     */
    public function buildAcceptedQuotationsSnapshot(ClientProject $project): array
    {
        $accepted = ClientProjectQuotation::query()
            ->where('client_project_id', $project->id)
            ->where('status', ClientProjectQuotation::STATUS_ACCEPTED)
            ->orderBy('id')
            ->get(['id', 'title', 'quotation_number', 'status', 'total_amount']);

        return [
            'ids' => $accepted->pluck('id')->values()->all(),
            'rows' => $accepted->map(static fn (ClientProjectQuotation $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'quotation_number' => $q->quotation_number,
                'status' => $q->status,
                'total_amount' => $q->total_amount !== null ? (float) $q->total_amount : null,
            ])->values()->all(),
        ];
    }

    /**
     * Build a draft invoice for an hourly project from its unbilled,
     * closed time entries. Each entry becomes one editable line item
     * (description = "DD/MM/YYYY — note (or 'Hourly work')", amount =
     * hours × hourly_rate). Every entry is linked back to the new
     * invoice so it's excluded from the next "Create invoice from hours".
     *
     * If the admin deletes the draft later, the FK's ON DELETE SET NULL
     * releases the entries back to "unbilled".
     */
    public function createHourlyInvoice(ClientProject $project, $unbilledEntries): ClientProjectInvoice
    {
        $rate = round((float) ($project->hourly_rate ?? 0), 2);
        $items = [];

        foreach ($unbilledEntries as $entry) {
            $hours = round((float) ($entry->hours ?? 0), 2);
            if ($hours <= 0) {
                continue; // defensive: scopeClosed should already exclude these
            }

            // Build a parts list and join with " · ". Each component is
            // only appended when it has real content, so we never end up
            // with dangling separators.
            //
            //   [date] · [start – end] · [X.XX h @ $Y/h] · [note]
            //
            // The time range is only included when BOTH start_time and
            // end_time are set on the entry — i.e. the admin clocked in
            // and out. Legacy or bulk-entered rows (hours only) fall
            // back to date + total hours, per the user's explicit ask.
            $parts = [];

            if ($entry->occurred_on) {
                $parts[] = ClientProject::formatDate($entry->occurred_on);
            }

            if (!empty($entry->start_time) && !empty($entry->end_time)) {
                $parts[] = sprintf(
                    '%s – %s',
                    ClientProjectTimeEntry::formatTimeForDisplay($entry->start_time, $entry->occurred_on),
                    ClientProjectTimeEntry::formatTimeForDisplay($entry->end_time, $entry->occurred_on)
                );
            }

            $parts[] = sprintf('%.2f h @ %s/h', $hours, ClientProject::formatMoney($rate));

            $note = trim((string) ($entry->note ?? ''));
            if ($note !== '') {
                $parts[] = $note;
            }

            $items[] = [
                'description' => implode(' · ', $parts),
                'amount' => round($hours * $rate, 2),
                'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR,
            ];
        }

        $unbilledExpenses = $project->expenses()
            ->whereNull('client_project_invoice_id')
            ->orderBy('occurred_on')
            ->get();

        foreach ($unbilledExpenses as $exp) {
            $items[] = [
                'description' => sprintf(
                    'Materials · %s (%s)',
                    $exp->description,
                    $exp->occurred_on->format('M j, Y')
                ),
                // Amount entered on Financials tab is tax-included (Ontario POS style).
                'amount' => round((float) $exp->amount, 2),
                'line_kind' => ClientProjectInvoice::LINE_KIND_MATERIALS,
                'expense_id' => $exp->id,
            ];
        }

        [$labour, $materials] = $this->totals->partitionHourlyLineAmounts($items);
        $hst = round($labour * ClientProjectInvoice::HST_RATE, 2);
        $grand = round($labour + $hst + $materials, 2);

        // Use a transaction so the link-back to time entries is atomic
        // with the invoice insert — an exception anywhere rolls back
        // and leaves the entries unbilled.
        return DB::transaction(function () use ($project, $items, $labour, $hst, $grand, $unbilledEntries) {
            $snap = $this->buildAcceptedQuotationsSnapshot($project);

            $invoice = $project->invoices()->create([
                'installment_number' => null,
                'installment_description' => 'Hourly work',
                'invoice_number' => ClientProjectInvoice::generateNumber(),
                'items' => $items,
                'subtotal' => $labour,
                'hst' => $hst,
                'hst_exempt' => false,
                'total' => $grand,
                'status' => ClientProjectInvoice::STATUS_DRAFT,
                'version' => 1,
                'included_project_quotation_ids' => $snap['ids'],
                'included_quotations_snapshot' => $snap['rows'],
            ]);

            // Attach the time entries to this invoice so they're treated
            // as "billed" for future hourly invoice creation.
            $project->timeEntries()
                ->whereIn('id', $unbilledEntries->pluck('id'))
                ->update(['client_project_invoice_id' => $invoice->id]);

            $this->totals->syncHourlyExpenseLinks($project, $invoice, $items);

            return $invoice;
        });
    }
}

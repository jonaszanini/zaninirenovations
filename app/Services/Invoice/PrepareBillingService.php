<?php

namespace App\Services\Invoice;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Admin-only billing prep after a quotation is accepted.
 *
 * Prefills the project's existing payment plan (seeded from quote terms)
 * and creates the same {@see ClientProjectInvoice} DRAFT rows used by the
 * Invoice tab and global Financials → Invoices. Never sends, issues, or
 * charges. Drafts do not count as receivables.
 */
class PrepareBillingService
{
    public function __construct(protected InvoiceCreationService $creation)
    {
    }

    /**
     * @return array{
     *   allowed: bool,
     *   blockers: list<string>,
     *   conflicts: list<string>,
     *   can_confirm: bool,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float>,
     *   quote_terms: ?array{installments: int, percentages: list<float>, grand: float, pre_tax: float, from_quotation_schedule: bool},
     * }
     */
    public function preview(ClientProject $project): array
    {
        $blockers = $this->gateBlockers($project);
        $project->loadMissing(['installments', 'invoices', 'quotations']);

        $quoteTerms = $this->acceptedQuoteTerms($project);
        $conflicts = [];
        if ($blockers === [] && $quoteTerms !== null) {
            $conflicts = $this->planConflicts($project, $quoteTerms);
        }

        $rows = $blockers === [] ? $this->previewRows($project) : [];
        $totals = $this->totals($project, $rows);

        $needsDue = collect($rows)->contains(
            fn (array $row) => in_array($row['action'], ['create', 'reuse_draft'], true) && empty($row['due_on'])
        );

        return [
            'allowed' => $blockers === [],
            'blockers' => $blockers,
            'conflicts' => $conflicts,
            'can_confirm' => $blockers === [] && $conflicts === [] && $rows !== [] && ! $needsDue,
            'needs_due_dates' => $needsDue,
            'rows' => $rows,
            'totals' => $totals,
            'quote_terms' => $quoteTerms,
        ];
    }

    /**
     * Create missing installment drafts and stamp due dates on drafts.
     * Idempotent: existing sent/paid invoices are left alone; existing drafts
     * are reused (due date updated when provided).
     *
     * @param  array<int|string, string|null>  $dueOnByNumber
     * @return list<ClientProjectInvoice>
     */
    public function confirm(ClientProject $project, array $dueOnByNumber): array
    {
        return DB::transaction(function () use ($project, $dueOnByNumber) {
            /** @var ClientProject $locked */
            $locked = ClientProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $locked->load(['installments', 'invoices', 'quotations']);

            $preview = $this->preview($locked);
            if ($preview['blockers'] !== []) {
                throw ValidationException::withMessages(['billing' => $preview['blockers']]);
            }
            if ($preview['conflicts'] !== []) {
                throw ValidationException::withMessages(['billing' => $preview['conflicts']]);
            }

            $createdOrReused = [];
            foreach ($preview['rows'] as $row) {
                $n = (int) $row['number'];
                if ($row['action'] === 'skip_issued' || $row['action'] === 'skip_paid') {
                    continue;
                }

                $due = $this->normalizeDue($dueOnByNumber[$n] ?? ($row['due_on'] ?? null));
                if ($due === null) {
                    throw ValidationException::withMessages([
                        'due_on.'.$n => 'Set a due date for '.$row['label'].'. Dates are not invented.',
                    ]);
                }

                $invoice = $this->openOrCreateInstallmentDraft($locked, $n);
                if ($invoice->isDraft() && (string) optional($invoice->due_on)?->toDateString() !== $due) {
                    $invoice->due_on = $due;
                    $invoice->save();
                }
                $createdOrReused[] = $invoice;
            }

            return $createdOrReused;
        });
    }

    /**
     * One-at-a-time: reopen the first remaining installment draft, or create it.
     */
    public function openOrCreateNext(ClientProject $project): ClientProjectInvoice
    {
        return DB::transaction(function () use ($project) {
            /** @var ClientProject $locked */
            $locked = ClientProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $locked->load(['installments', 'invoices', 'quotations']);

            $blockers = $this->gateBlockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['billing' => $blockers]);
            }

            $preview = $this->preview($locked);
            if ($preview['conflicts'] !== []) {
                throw ValidationException::withMessages(['billing' => $preview['conflicts']]);
            }

            foreach ($preview['rows'] as $row) {
                if ($row['action'] === 'reuse_draft' || $row['action'] === 'create') {
                    return $this->openOrCreateInstallmentDraft($locked, (int) $row['number']);
                }
            }

            throw ValidationException::withMessages([
                'billing' => ['Every installment already has an issued or paid invoice.'],
            ]);
        });
    }

    public function openOrCreateInstallmentDraft(ClientProject $project, int $number): ClientProjectInvoice
    {
        $existing = $project->invoices
            ->first(fn (ClientProjectInvoice $inv) => (int) $inv->installment_number === $number);

        if ($existing) {
            return $existing;
        }

        $count = $project->installments->count();
        if ($number < 1 || $number > $count) {
            throw new RuntimeException('That installment does not exist on the current payment plan.');
        }

            $inst = $project->installments->sortBy('sort_order')->values()->get($number - 1);
        if ($inst && $inst->is_paid) {
            throw ValidationException::withMessages([
                'billing' => ['That installment is already marked paid — it will not be billed again.'],
            ]);
        }
        if ($inst && (float) $inst->amount < 0.01) {
            throw ValidationException::withMessages([
                'billing' => ['That installment has no amount to bill.'],
            ]);
        }

        try {
            return $this->creation->createInvoice($project, $number);
        } catch (\Illuminate\Database\QueryException $e) {
            $duplicate = ((string) $e->getCode() === '23000')
                || str_contains($e->getMessage(), 'cp_inv_project_installment_unique');
            if (! $duplicate) {
                throw $e;
            }
            $project->unsetRelation('invoices');
            $again = $project->invoices()
                ->where('installment_number', $number)
                ->first();
            if ($again) {
                return $again;
            }
            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    public function gateBlockers(ClientProject $project): array
    {
        $out = [];
        if ($project->excludesInvoicesFromReceivables()) {
            $out[] = 'Billing is locked while this project is '.$project->statusLabel().'.';
        }
        if ($project->isHourly()) {
            $out[] = 'Hourly projects bill from logged hours, not quotation installments.';
        }
        if (! $project->isAccepted()) {
            $out[] = 'Accept a quotation before preparing billing.';
        }
        $quotSub = $project->quotationSubtotalForFinancials();
        if ($quotSub === null || $quotSub < 0.01) {
            $out[] = 'Need an accepted quotation with priced line items. Terms are not invented.';
        }
        if ($project->installments()->count() === 0) {
            $out[] = 'No payment plan rows yet. Review the schedule on Financials (from the accepted quotation) before creating invoices.';
        }

        return $out;
    }

    /**
     * @return array{installments: int, percentages: list<float>, grand: float, pre_tax: float}|null
     */
    private function acceptedQuoteTerms(ClientProject $project): ?array
    {
        $pre = $project->quotationSubtotalForFinancials();
        $grand = $project->quotationGrandTotalForFinancials();
        if ($pre === null || $pre < 0.01) {
            return null;
        }

        $best = null;
        $bestSub = -1.0;
        $bestId = 0;
        foreach ($project->acceptedQuotationsForFinancials() as $quotation) {
            $schedule = $quotation->resolvedPaymentSchedule();
            if ($schedule === null) {
                continue;
            }
            $sub = (float) $quotation->subtotal();
            $id = (int) $quotation->id;
            if ($sub > $bestSub || ($sub === $bestSub && $id > $bestId)) {
                $best = $schedule;
                $bestSub = $sub;
                $bestId = $id;
            }
        }

        return [
            'installments' => (int) ($best['installments'] ?? ($project->payment_plan ?: 1)),
            'percentages' => $best['percentages'] ?? $project->installments->sortBy('sort_order')->values()
                ->map(fn (ClientProjectInstallment $i) => (float) $i->percentage)
                ->all(),
            'pre_tax' => round((float) $pre, 2),
            'grand' => round((float) ($grand ?? 0), 2),
            'from_quotation_schedule' => $best !== null,
        ];
    }

    /**
     * @param  array{installments: int, percentages: list<float>, grand: float, pre_tax: float, from_quotation_schedule: bool}  $quoteTerms
     * @return list<string>
     */
    private function planConflicts(ClientProject $project, array $quoteTerms): array
    {
        $installments = $project->installments->sortBy('sort_order')->values();
        $planN = $installments->count();
        $out = [];

        $planPre = round($installments->sum(fn (ClientProjectInstallment $i) => (float) $i->amount), 2);
        if (abs($planPre - (float) $quoteTerms['pre_tax']) > 0.05) {
            $out[] = 'Payment plan pre-tax ('.ClientProject::formatMoney($planPre)
                .') differs from the accepted quotation ('
                .ClientProject::formatMoney((float) $quoteTerms['pre_tax'])
                .'). Review Financials before creating invoices.';
        }

        if (! empty($quoteTerms['from_quotation_schedule'])) {
            if ($planN !== (int) $quoteTerms['installments']) {
                $out[] = 'Saved payment plan has '.$planN.' installment(s) but the accepted quotation terms are '
                    .(int) $quoteTerms['installments'].'. Review Financials — this is not recalculated silently.';
            }

            foreach ($installments as $i => $row) {
                $expected = (float) ($quoteTerms['percentages'][$i] ?? -1);
                if ($expected >= 0 && abs((float) $row->percentage - $expected) > 0.15) {
                    $out[] = 'Installment '.($i + 1).' is '.number_format((float) $row->percentage, 2)
                        .'% on Financials vs '.number_format($expected, 2).'% on the accepted quotation.';
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function previewRows(ClientProject $project): array
    {
        $installments = $project->installments->sortBy('sort_order')->values();
        $byNumber = $project->invoices->whereNotNull('installment_number')->keyBy('installment_number');
        $rows = [];

        foreach ($installments as $idx => $inst) {
            $n = $idx + 1;
            $invoice = $byNumber->get($n);
            $action = 'create';
            if ($inst->is_paid && ! $invoice) {
                $action = 'skip_paid';
            } elseif ($invoice && in_array($invoice->status, [
                ClientProjectInvoice::STATUS_SENT,
                ClientProjectInvoice::STATUS_PAID,
                ClientProjectInvoice::STATUS_REFUNDED,
            ], true)) {
                $action = 'skip_issued';
            } elseif ($invoice && $invoice->isDraft()) {
                $action = 'reuse_draft';
            }

            $sub = $invoice ? (float) $invoice->subtotal : (float) $inst->amount;
            $hst = $invoice ? (float) $invoice->hst : (float) $inst->effectiveHst();
            $total = $invoice ? (float) $invoice->total : (float) $inst->effectiveTotal();

            $rows[] = [
                'number' => $n,
                'label' => $inst->label ?: ClientProjectFinanceController::installmentLabel((int) $project->payment_plan, $idx),
                'percentage' => (float) $inst->percentage,
                'subtotal' => round($sub, 2),
                'hst' => round($hst, 2),
                'total' => round($total, 2),
                'due_on' => $invoice && $invoice->due_on ? $invoice->due_on->toDateString() : null,
                'invoice_id' => $invoice?->id,
                'invoice_status' => $invoice?->status,
                'invoice_number' => $invoice?->invoice_number,
                'action' => $action,
                'is_paid_installment' => (bool) $inst->is_paid,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float>
     */
    public function totals(ClientProject $project, array $rows = []): array
    {
        if ($rows === []) {
            $rows = $this->previewRows($project);
        }
        $scheduled = round(array_sum(array_column($rows, 'total')), 2);
        $draft = 0.0;
        $issued = 0.0;
        $paid = 0.0;
        foreach ($project->invoices as $inv) {
            $t = round((float) $inv->total, 2);
            if ($inv->isDraft()) {
                $draft += $t;
            } elseif ($inv->isSent()) {
                $issued += $t;
            } elseif ($inv->isPaid()) {
                $paid += round($t - (float) ($inv->refund_amount ?? 0), 2);
            }
        }

        return [
            'scheduled' => $scheduled,
            'draft' => round($draft, 2),
            'issued' => round($issued, 2),
            'paid' => round($paid, 2),
            'outstanding' => round($issued, 2),
        ];
    }

    private function normalizeDue(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }

        return $s;
    }
}

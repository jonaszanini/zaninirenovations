<?php

namespace App\Services;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Recalculate remaining (unpaid) installments when accepted quotation
 * totals change after some installments have already been paid.
 *
 * Paid installments and paid invoices are never modified. Unpaid
 * installment-linked invoices are deleted and recreated as drafts from
 * the new installment amounts.
 */
class RecalculateRemainingInstallmentsService
{
    /** Default percentage splits for a remaining-installment count (1–4). */
    public const REMAINING_DEFAULTS = [
        1 => [100.0],
        2 => [50.0, 50.0],
        3 => [30.0, 30.0, 40.0],
        4 => [20.0, 20.0, 30.0, 30.0],
    ];

    public function __construct(
        private ?ClientProjectStatusService $statusService = null,
    ) {}

    private function statusService(): ClientProjectStatusService
    {
        return $this->statusService ?? app(ClientProjectStatusService::class);
    }

    /**
     * Detect whether the locked payment plan is out of sync with accepted quotations.
     *
     * @return array{
     *   applicable: bool,
     *   has_discrepancy: bool,
     *   blocked: bool,
     *   block_reason: ?string,
     *   quotation_pre_tax: float,
     *   quotation_grand: float,
     *   plan_pre_tax: float,
     *   plan_grand: float,
     *   paid_pre_tax: float,
     *   paid_grand: float,
     *   remaining_pre_tax: float,
     *   remaining_grand: float,
     *   paid_count: int,
     *   unpaid_count: int,
     *   current_plan: int,
     *   max_remaining: int,
     *   unpaid_invoice_count: int,
     *   installment_invoice_count: int,
     *   finance_committed: bool,
     * }
     */
    public function analyze(ClientProject $project): array
    {
        $empty = [
            'applicable' => false,
            'has_discrepancy' => false,
            'blocked' => false,
            'block_reason' => null,
            'quotation_pre_tax' => 0.0,
            'quotation_grand' => 0.0,
            'plan_pre_tax' => 0.0,
            'plan_grand' => 0.0,
            'paid_pre_tax' => 0.0,
            'paid_grand' => 0.0,
            'remaining_pre_tax' => 0.0,
            'remaining_grand' => 0.0,
            'paid_count' => 0,
            'unpaid_count' => 0,
            'current_plan' => (int) ($project->payment_plan ?? 1),
            'max_remaining' => 4,
            'unpaid_invoice_count' => 0,
            'installment_invoice_count' => 0,
            'finance_committed' => false,
        ];

        if ($project->isHourly() || ! $project->isAccepted()) {
            return $empty;
        }

        $quotSub = $project->quotationSubtotalForFinancials();
        $quotGrand = $project->quotationGrandTotalForFinancials();
        if ($quotSub === null || $quotGrand === null || $quotSub < 0.01) {
            return array_merge($empty, [
                'applicable' => true,
                'blocked' => true,
                'block_reason' => 'Accept at least one quotation with line items before recalculating.',
            ]);
        }

        $installments = $project->relationLoaded('installments')
            ? $project->installments->sortBy('sort_order')->values()
            : $project->installments()->orderBy('sort_order')->get()->values();

        $planPreTax = round((float) ($project->total_amount ?? 0), 2);
        $planGrand = round($installments->sum(fn (ClientProjectInstallment $i) => $i->effectiveTotal()), 2);

        $paid = $installments->where('is_paid', true)->values();
        $unpaid = $installments->where('is_paid', false)->values();
        $paidPreTax = round($paid->sum(fn (ClientProjectInstallment $i) => (float) $i->amount), 2);
        $paidGrand = round($paid->sum(fn (ClientProjectInstallment $i) => $i->effectiveTotal()), 2);

        $remainingPreTax = round((float) $quotSub - $paidPreTax, 2);
        $remainingGrand = round((float) $quotGrand - $paidGrand, 2);

        $paidCount = $paid->count();
        $unpaidCount = $unpaid->count();
        $maxRemaining = max(0, 4 - $paidCount);

        $installmentInvoiceCount = $project->invoices()
            ->whereNotNull('installment_number')
            ->count();

        $unpaidInvoiceCount = $project->invoices()
            ->whereNotNull('installment_number')
            ->where('status', '!=', ClientProjectInvoice::STATUS_PAID)
            ->count();

        // Only treat quotation vs plan mismatch as a "project total has changed"
        // situation once finance is committed — installment invoices generated
        // and/or at least one installment already paid. First-time payment-plan
        // setup after accepting a proposal must not show that warning.
        $financeCommitted = $paidCount > 0 || $installmentInvoiceCount > 0;

        $amountsDiffer = abs((float) $quotSub - $planPreTax) > 0.01
            || abs((float) $quotGrand - $planGrand) > 0.01;

        $hasDiscrepancy = $financeCommitted && $amountsDiffer;

        $blocked = false;
        $blockReason = null;
        if ($remainingPreTax < -0.01) {
            $blocked = true;
            $blockReason = sprintf(
                'New project total (%s) is less than already paid (%s). Reduce paid amounts or adjust quotations before recalculating.',
                ClientProject::formatMoney((float) $quotGrand),
                ClientProject::formatMoney($paidGrand)
            );
        } elseif ($maxRemaining < 1 && $remainingPreTax > 0.01) {
            $blocked = true;
            $blockReason = 'All four installments are already paid, but a remaining balance still exists. Contact support to resolve this manually.';
        }

        return [
            'applicable' => true,
            'has_discrepancy' => $hasDiscrepancy,
            'blocked' => $blocked,
            'block_reason' => $blockReason,
            'quotation_pre_tax' => round((float) $quotSub, 2),
            'quotation_grand' => round((float) $quotGrand, 2),
            'plan_pre_tax' => $planPreTax,
            'plan_grand' => $planGrand,
            'paid_pre_tax' => $paidPreTax,
            'paid_grand' => $paidGrand,
            'remaining_pre_tax' => $remainingPreTax,
            'remaining_grand' => $remainingGrand,
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
            'current_plan' => (int) ($project->payment_plan ?? max(1, $installments->count())),
            'max_remaining' => $maxRemaining,
            'unpaid_invoice_count' => $unpaidInvoiceCount,
            'installment_invoice_count' => $installmentInvoiceCount,
            'finance_committed' => $financeCommitted,
        ];
    }

    /**
     * Build a preview of the new plan without writing anything.
     *
     * @param  'keep'|'defaults'  $splitMode  keep = renormalize current unpaid %; defaults = PLAN_DEFAULTS
     * @return array<string, mixed>
     */
    public function preview(ClientProject $project, int $remainingCount, string $splitMode = 'defaults'): array
    {
        $analysis = $this->analyze($project);
        if (! $analysis['applicable']) {
            throw new InvalidArgumentException('Recalculation is only available for accepted fixed-price projects.');
        }
        if ($analysis['blocked']) {
            throw new InvalidArgumentException($analysis['block_reason'] ?? 'Recalculation is blocked.');
        }

        $remainingCount = max(0, min(4, $remainingCount));
        if ($remainingCount > $analysis['max_remaining']) {
            throw new InvalidArgumentException(sprintf(
                'At most %d remaining installment(s) can be scheduled (%d already paid).',
                $analysis['max_remaining'],
                $analysis['paid_count']
            ));
        }

        if ($analysis['remaining_pre_tax'] > 0.01 && $remainingCount < 1) {
            throw new InvalidArgumentException('Choose at least one remaining installment for the unpaid balance.');
        }

        if ($analysis['remaining_pre_tax'] <= 0.01) {
            $remainingCount = 0;
        }

        $rows = $this->buildProposedRows($project, $analysis, $remainingCount, $splitMode);

        return [
            'analysis' => $analysis,
            'remaining_count' => $remainingCount,
            'new_plan' => $analysis['paid_count'] + $remainingCount,
            'split_mode' => $splitMode,
            'rows' => $rows,
            'unpaid_invoices_to_delete' => $analysis['unpaid_invoice_count'],
            'drafts_to_create' => $remainingCount,
        ];
    }

    /**
     * Apply the recalculation: update plan, delete unpaid invoices, create new drafts.
     *
     * @return array{preview: array<string, mixed>, deleted_invoices: int, created_invoices: int}
     */
    public function apply(ClientProject $project, int $remainingCount, string $splitMode = 'defaults'): array
    {
        $preview = $this->preview($project, $remainingCount, $splitMode);

        $deleted = 0;
        $created = 0;

        DB::transaction(function () use ($project, $preview, &$deleted, &$created) {
            $analysis = $preview['analysis'];
            $rows = $preview['rows'];
            $newPlan = (int) $preview['new_plan'];
            $newPreTax = (float) $analysis['quotation_pre_tax'];

            // 1) Delete unpaid installment-linked invoices (draft or sent).
            $unpaidInvoices = $project->invoices()
                ->whereNotNull('installment_number')
                ->where('status', '!=', ClientProjectInvoice::STATUS_PAID)
                ->get();

            foreach ($unpaidInvoices as $invoice) {
                $invoice->delete();
                $deleted++;
            }

            // 2) Rebuild installment rows — preserve paid rows by id order.
            $existing = $project->installments()->orderBy('sort_order')->get()->values();
            $paidExisting = $existing->where('is_paid', true)->values();
            $unpaidExisting = $existing->where('is_paid', false)->values();

            $paidRows = array_values(array_filter($rows, fn ($r) => ! empty($r['is_paid'])));
            $unpaidRows = array_values(array_filter($rows, fn ($r) => empty($r['is_paid'])));

            // Update paid installments in place (percentage may change with new total).
            foreach ($paidRows as $i => $row) {
                /** @var ClientProjectInstallment $inst */
                $inst = $paidExisting[$i];
                $inst->sort_order = (int) $row['sort_order'];
                $inst->label = (string) $row['label'];
                $inst->percentage = (float) $row['percentage'];
                // Amount stays the historical paid amount.
                $inst->amount = (float) $row['amount'];
                $inst->save();
            }

            // Reuse unpaid rows where possible, then create/delete extras.
            foreach ($unpaidRows as $i => $row) {
                if ($unpaidExisting->has($i)) {
                    /** @var ClientProjectInstallment $inst */
                    $inst = $unpaidExisting[$i];
                    $inst->sort_order = (int) $row['sort_order'];
                    $inst->label = (string) $row['label'];
                    $inst->percentage = (float) $row['percentage'];
                    $inst->amount = (float) $row['amount'];
                    $inst->is_paid = false;
                    $inst->paid_on = null;
                    $inst->save();
                } else {
                    $project->installments()->create([
                        'sort_order' => (int) $row['sort_order'],
                        'label' => (string) $row['label'],
                        'percentage' => (float) $row['percentage'],
                        'amount' => (float) $row['amount'],
                        'is_paid' => false,
                    ]);
                }
            }

            if ($unpaidExisting->count() > count($unpaidRows)) {
                $ids = $unpaidExisting->slice(count($unpaidRows))->pluck('id')->all();
                ClientProjectInstallment::whereIn('id', $ids)->delete();
            }

            $project->total_amount = $newPreTax;
            $project->payment_plan = max(1, $newPlan);
            $project->save();

            // 3) Create draft invoices for each unpaid installment.
            $project->refresh();
            $freshInstallments = $project->installments()->orderBy('sort_order')->get()->values();
            foreach ($freshInstallments as $idx => $inst) {
                if ($inst->is_paid) {
                    continue;
                }
                $this->createDraftInstallmentInvoice($project, $inst, $idx + 1);
                $created++;
            }
        });

        $note = sprintf(
            'Recalculated remaining installments: quotation total %s (was plan %s). Paid kept: %s. Remaining split across %d installment(s). Deleted %d unpaid invoice(s); created %d draft invoice(s).',
            ClientProject::formatMoney((float) $preview['analysis']['quotation_grand']),
            ClientProject::formatMoney((float) $preview['analysis']['plan_grand']),
            ClientProject::formatMoney((float) $preview['analysis']['paid_grand']),
            (int) $preview['remaining_count'],
            $deleted,
            $created
        );
        $this->statusService()->recordEvent($project->fresh(), $note);

        return [
            'preview' => $preview,
            'deleted_invoices' => $deleted,
            'created_invoices' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<array<string, mixed>>
     */
    private function buildProposedRows(
        ClientProject $project,
        array $analysis,
        int $remainingCount,
        string $splitMode
    ): array {
        $installments = $project->relationLoaded('installments')
            ? $project->installments->sortBy('sort_order')->values()
            : $project->installments()->orderBy('sort_order')->get()->values();

        $paid = $installments->where('is_paid', true)->values();
        $unpaid = $installments->where('is_paid', false)->values();

        $newPreTax = (float) $analysis['quotation_pre_tax'];
        $newGrand = (float) $analysis['quotation_grand'];
        $paidPreTax = (float) $analysis['paid_pre_tax'];
        $remainingPreTax = (float) $analysis['remaining_pre_tax'];
        $newPlan = $paid->count() + $remainingCount;

        $rows = [];
        $sort = 0;
        $allocatedPreTax = 0.0;
        $allocatedPct = 0.0;

        // Paid rows — freeze dollar amounts; recompute % of new total.
        foreach ($paid as $inst) {
            $amount = round((float) $inst->amount, 2);
            $pct = $newPreTax > 0 ? round($amount / $newPreTax * 100, 2) : 0.0;
            $rows[] = [
                'sort_order' => $sort,
                'label' => ClientProjectFinanceController::installmentLabel(max(1, $newPlan), $sort),
                'percentage' => $pct,
                'amount' => $amount,
                'total_incl_hst' => $inst->effectiveTotal(),
                'is_paid' => true,
                'kept' => true,
            ];
            $allocatedPreTax = round($allocatedPreTax + $amount, 2);
            $allocatedPct = round($allocatedPct + $pct, 2);
            $sort++;
        }

        if ($remainingCount < 1) {
            // Fix percentage drift on last paid row so plan sums to 100%.
            if ($rows !== []) {
                $sumPct = array_sum(array_column($rows, 'percentage'));
                $last = count($rows) - 1;
                $rows[$last]['percentage'] = round($rows[$last]['percentage'] + (100.0 - $sumPct), 2);
            }

            return $rows;
        }

        $remainingPctPool = round(100.0 - $allocatedPct, 2);
        $weights = $this->remainingWeights($remainingCount, $splitMode, $unpaid);
        $weightSum = array_sum($weights) ?: 1.0;
        $unpaidAllocatedPreTax = 0.0;
        $unpaidAllocatedPct = 0.0;

        // Distribute remaining pre-tax; last row absorbs rounding remainder.
        for ($i = 0; $i < $remainingCount; $i++) {
            $isLast = $i === $remainingCount - 1;
            if ($isLast) {
                $amount = round($remainingPreTax - $unpaidAllocatedPreTax, 2);
                $pct = round($remainingPctPool - $unpaidAllocatedPct, 2);
            } else {
                $share = $weights[$i] / $weightSum;
                $amount = round($remainingPreTax * $share, 2);
                $pct = round($remainingPctPool * $share, 2);
            }

            $amount = max(0.0, $amount);
            $hstShare = $newPreTax > 0 ? round($newGrand * ($amount / $newPreTax), 2) : 0.0;

            $rows[] = [
                'sort_order' => $sort,
                'label' => ClientProjectFinanceController::installmentLabel(max(1, $newPlan), $sort),
                'percentage' => $pct,
                'amount' => $amount,
                'total_incl_hst' => $hstShare,
                'is_paid' => false,
                'kept' => false,
            ];
            $unpaidAllocatedPreTax = round($unpaidAllocatedPreTax + $amount, 2);
            $unpaidAllocatedPct = round($unpaidAllocatedPct + $pct, 2);
            $allocatedPreTax = round($allocatedPreTax + $amount, 2);
            $sort++;
        }

        // Final percentage cleanup so paid+unpaid = 100.00.
        $sumPct = round(array_sum(array_column($rows, 'percentage')), 2);
        if (abs($sumPct - 100.0) > 0.001 && $rows !== []) {
            $last = count($rows) - 1;
            $rows[$last]['percentage'] = round($rows[$last]['percentage'] + (100.0 - $sumPct), 2);
        }

        // Final amount cleanup so paid+unpaid = newPreTax.
        $sumAmt = round(array_sum(array_column($rows, 'amount')), 2);
        if (abs($sumAmt - $newPreTax) > 0.001 && $rows !== []) {
            $last = count($rows) - 1;
            if (empty($rows[$last]['is_paid'])) {
                $rows[$last]['amount'] = round($rows[$last]['amount'] + ($newPreTax - $sumAmt), 2);
                $amount = (float) $rows[$last]['amount'];
                $rows[$last]['total_incl_hst'] = $newPreTax > 0
                    ? round($newGrand * ($amount / $newPreTax), 2)
                    : 0.0;
            }
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ClientProjectInstallment>  $unpaid
     * @return list<float>
     */
    private function remainingWeights(int $remainingCount, string $splitMode, $unpaid): array
    {
        if ($splitMode === 'keep' && $unpaid->count() === $remainingCount && $remainingCount > 0) {
            $weights = $unpaid->map(fn (ClientProjectInstallment $i) => max(0.0, (float) $i->percentage))->values()->all();
            if (array_sum($weights) > 0.01) {
                return $weights;
            }
        }

        return self::REMAINING_DEFAULTS[$remainingCount] ?? array_fill(0, $remainingCount, 1.0);
    }

    private function createDraftInstallmentInvoice(
        ClientProject $project,
        ClientProjectInstallment $installment,
        int $installmentNumber
    ): ClientProjectInvoice {
        $plan = (int) $project->payment_plan ?: 1;
        $description = ClientProjectInvoice::installmentDescriptionFor($plan, $installmentNumber)
            ?: (string) $installment->label;

        $subtotal = round((float) $installment->amount, 2);
        $hst = round((float) $installment->effectiveHst(), 2);

        $accepted = $project->relationLoaded('quotations')
            ? $project->quotations->where('status', \App\Models\ClientProjectQuotation::STATUS_ACCEPTED)->values()
            : $project->quotations()->where('status', \App\Models\ClientProjectQuotation::STATUS_ACCEPTED)->orderBy('id')->get();

        return $project->invoices()->create([
            'installment_number' => $installmentNumber,
            'installment_description' => $description,
            'invoice_number' => ClientProjectInvoice::generateNumber(),
            'items' => [[
                'description' => $description ?: 'Installment '.$installmentNumber,
                'amount' => $subtotal,
            ]],
            'subtotal' => $subtotal,
            'hst' => $hst,
            'hst_exempt' => false,
            'total' => round($subtotal + $hst, 2),
            'status' => ClientProjectInvoice::STATUS_DRAFT,
            'version' => 1,
            'included_project_quotation_ids' => $accepted->pluck('id')->values()->all(),
            'included_quotations_snapshot' => $accepted->map(static fn ($q) => [
                'id' => $q->id,
                'title' => $q->title,
                'quotation_number' => $q->quotation_number,
                'status' => $q->status,
                'total_amount' => $q->total_amount !== null ? (float) $q->total_amount : null,
            ])->values()->all(),
        ]);
    }
}

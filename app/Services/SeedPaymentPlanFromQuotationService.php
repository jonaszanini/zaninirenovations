<?php

namespace App\Services;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use Illuminate\Support\Facades\DB;

/**
 * Copy the accepted quotation's installment count and % split onto the
 * project's Financials payment plan when nothing has been paid yet and the
 * plan has never been given installment rows.
 *
 * Quotations store {@see ClientProjectQuotation::$payment_installments} and
 * {@see ClientProjectQuotation::$payment_percentages}; the Financials tab
 * reads {@see ClientProject::$payment_plan} and {@see ClientProjectInstallment}
 * rows. Without this sync the tab stays on the DB default (pay in full) until
 * the admin saves a plan. Once installment rows exist — including a saved
 * pay-in-full plan — this service leaves the structure alone.
 */
class SeedPaymentPlanFromQuotationService
{
    /**
     * @return bool True when the project's unpaid plan was created or replaced
     *              from an accepted quotation schedule.
     */
    public function sync(ClientProject $project, float $preTaxTotal): bool
    {
        if ($project->isHourly() || $preTaxTotal < 0.01) {
            return false;
        }

        if ($project->installments()->where('is_paid', true)->exists()) {
            return false;
        }

        $schedule = $this->scheduleFromAcceptedQuotations($project);
        if ($schedule === null) {
            return false;
        }

        if (! $this->shouldReplaceExistingPlan($project)) {
            return false;
        }

        $plan = $schedule['installments'];
        $percentages = $schedule['percentages'];

        DB::transaction(function () use ($project, $preTaxTotal, $plan, $percentages) {
            $project->payment_plan = $plan;
            $project->total_amount = $preTaxTotal;
            $project->saveQuietly();

            $existing = $project->installments()->orderBy('sort_order')->get()->values();

            foreach (range(0, $plan - 1) as $i) {
                $pct = (float) ($percentages[$i] ?? 0);
                $amount = round($preTaxTotal * $pct / 100, 2);
                $label = ClientProjectFinanceController::installmentLabel($plan, $i);

                if ($existing->has($i)) {
                    /** @var ClientProjectInstallment $row */
                    $row = $existing[$i];
                    $row->sort_order = $i;
                    $row->label = $label;
                    $row->percentage = $pct;
                    $row->amount = $amount;
                    $row->saveQuietly();
                } else {
                    $project->installments()->create([
                        'sort_order' => $i,
                        'label' => $label,
                        'percentage' => $pct,
                        'amount' => $amount,
                        'is_paid' => false,
                    ]);
                }
            }

            if ($existing->count() > $plan) {
                $idsToDelete = $existing->slice($plan)->pluck('id')->all();
                ClientProjectInstallment::whereIn('id', $idsToDelete)->delete();
            }
        });

        $project->unsetRelation('installments');

        return true;
    }

    /**
     * Replace only when Financials has never been given installment rows.
     *
     * Do not treat payment_plan=1 as an unused default once rows exist — that
     * is also a deliberately saved “Pay in full at the end” plan. Overwriting
     * it on every project show/quotation sync made pay-in-full appear not to
     * persist. Saved 2/3/4 plans were already left alone.
     */
    private function shouldReplaceExistingPlan(ClientProject $project): bool
    {
        return $project->installments()->count() === 0;
    }

    /**
     * When several quotations are accepted, use the one with the largest
     * pre-tax subtotal (then highest id) that has a valid payment schedule.
     *
     * @return array{installments: int, percentages: list<float>}|null
     */
    private function scheduleFromAcceptedQuotations(ClientProject $project): ?array
    {
        $best = null;
        $bestSub = -1.0;
        $bestId = 0;

        foreach ($project->acceptedQuotationsForFinancials() as $quotation) {
            $schedule = $quotation->resolvedPaymentSchedule();
            if ($schedule === null) {
                continue;
            }

            $sub = $quotation->subtotal();
            $id = (int) $quotation->id;
            if ($sub > $bestSub || ($sub === $bestSub && $id > $bestId)) {
                $best = $schedule;
                $bestSub = $sub;
                $bestId = $id;
            }
        }

        return $best;
    }
}

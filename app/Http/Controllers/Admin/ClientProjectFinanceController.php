<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectReturn;
use App\Models\ClientProjectTimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Handles CRUD for the financial + time sections of a ClientProject:
 *   - Total amount + payment plan + installment percentages
 *   - Mark an installment as paid / unpaid
 *   - Expenses (add / delete)
 *   - Returns / credits (add / delete)
 *   - Time entries (add / delete)
 */
class ClientProjectFinanceController extends Controller
{
    /**
     * Save total project amount, payment plan, and the installment percentages.
     * Regenerates the installment rows so the counts / labels stay consistent.
     */
    public function saveInstallments(Request $request, ClientProject $client_project)
    {
        if (!$client_project->isAccepted()) {
            return back()->with('error', 'Financials are available once the proposal is accepted.');
        }

        $data = $request->validate([
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_plan' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'percentages' => ['required_unless:payment_plan,1', 'array'],
            'percentages.*' => ['numeric', 'min:0', 'max:100'],
        ]);

        $total = round((float) $data['total_amount'], 2);
        $plan = (int) $data['payment_plan'];

        // Normalise percentages. For pay-in-full (1) force a single 100% row.
        if ($plan === 1) {
            $percentages = [100.0];
        } else {
            $percentages = array_values(array_map(fn ($v) => round((float) $v, 2), $data['percentages'] ?? []));
            $percentages = array_slice($percentages, 0, $plan);
            while (count($percentages) < $plan) {
                $percentages[] = 0.0;
            }

            $sum = array_sum($percentages);
            if (round($sum, 2) !== 100.00) {
                return back()
                    ->withErrors(['percentages' => 'Percentages must add up to exactly 100%. Current total: '.number_format($sum, 2).'%.'])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($client_project, $total, $plan, $percentages) {
            $client_project->total_amount = $total;
            $client_project->payment_plan = $plan;
            $client_project->save();

            $existing = $client_project->installments()->orderBy('sort_order')->get()->values();

            // Re-align the collection to exactly $plan rows, preserving paid state
            // where the index still exists.
            foreach (range(0, $plan - 1) as $i) {
                $pct = (float) ($percentages[$i] ?? 0);
                $amount = round($total * $pct / 100, 2);
                $label = $this->defaultInstallmentLabel($plan, $i);

                if ($existing->has($i)) {
                    /** @var ClientProjectInstallment $row */
                    $row = $existing[$i];
                    $row->sort_order = $i;
                    $row->label = $label;
                    $row->percentage = $pct;
                    $row->amount = $amount;
                    $row->save();
                } else {
                    $client_project->installments()->create([
                        'sort_order' => $i,
                        'label' => $label,
                        'percentage' => $pct,
                        'amount' => $amount,
                        'is_paid' => false,
                    ]);
                }
            }

            // Delete any extra rows beyond the new plan count.
            if ($existing->count() > $plan) {
                $idsToDelete = $existing->slice($plan)->pluck('id')->all();
                ClientProjectInstallment::whereIn('id', $idsToDelete)->delete();
            }
        });

        return back()->with('success', 'Payment plan saved.');
    }

    public function togglePaid(Request $request, ClientProject $client_project, ClientProjectInstallment $installment)
    {
        abort_unless($installment->client_project_id === $client_project->id, 404);

        $data = $request->validate([
            'is_paid' => ['required', 'boolean'],
            'paid_on' => ['nullable', 'date'],
        ]);

        $installment->is_paid = (bool) $data['is_paid'];
        $installment->paid_on = $data['is_paid']
            ? ($data['paid_on'] ?? now()->toDateString())
            : null;
        $installment->save();

        return back()->with('success', $installment->is_paid ? 'Installment marked paid.' : 'Installment marked unpaid.');
    }

    public function storeExpense(Request $request, ClientProject $client_project)
    {
        if (!$client_project->isAccepted()) {
            return back()->with('error', 'Expenses are available once the proposal is accepted.');
        }

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $client_project->expenses()->create($data);

        return back()->with('success', 'Expense added.');
    }

    public function destroyExpense(ClientProject $client_project, ClientProjectExpense $expense)
    {
        abort_unless($expense->client_project_id === $client_project->id, 404);
        $expense->delete();

        return back()->with('success', 'Expense removed.');
    }

    public function storeReturn(Request $request, ClientProject $client_project)
    {
        if (!$client_project->isAccepted()) {
            return back()->with('error', 'Returns are available once the proposal is accepted.');
        }

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $client_project->returns()->create($data);

        return back()->with('success', 'Return / credit added.');
    }

    public function destroyReturn(ClientProject $client_project, ClientProjectReturn $project_return)
    {
        abort_unless($project_return->client_project_id === $client_project->id, 404);
        $project_return->delete();

        return back()->with('success', 'Return / credit removed.');
    }

    public function storeTime(Request $request, ClientProject $client_project)
    {
        if (!$client_project->isAccepted()) {
            return back()->with('error', 'Time tracking is available once the proposal is accepted.');
        }

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $client_project->timeEntries()->create($data);

        return back()->with('success', 'Time entry added.');
    }

    public function destroyTime(ClientProject $client_project, ClientProjectTimeEntry $time_entry)
    {
        abort_unless($time_entry->client_project_id === $client_project->id, 404);
        $time_entry->delete();

        return back()->with('success', 'Time entry removed.');
    }

    private function defaultInstallmentLabel(int $plan, int $index): string
    {
        if ($plan === 1) {
            return 'Full payment';
        }

        $ordinals = ['1st', '2nd', '3rd', '4th'];

        return ($ordinals[$index] ?? ($index + 1).'th').' installment';
    }
}

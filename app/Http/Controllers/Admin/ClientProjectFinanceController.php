<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectExpensesSummaryClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectReturn;
use App\Models\ClientProjectTimeEntry;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\ProjectPhotoWebpStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

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
            'payment_plan' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'percentages' => ['required_unless:payment_plan,1', 'array'],
            'percentages.*' => ['numeric', 'min:0', 'max:100'],
        ]);

        $plan = (int) $data['payment_plan'];

        // If any installment is already paid, the plan count is locked —
        // changing it would destroy a paid financial record. Percentages and
        // total amount are still editable.
        $paidCount = $client_project->installments()->where('is_paid', true)->count();

        if ($paidCount === 0) {
            $total = $client_project->quotationSubtotalForFinancials();
            if ($total === null || $total < 0.01) {
                return back()
                    ->with('error', 'Accept at least one quotation with line items first (or add items to an accepted quotation). The payment plan uses the combined pre-tax subtotal of accepted quotations; invoices allocate HST in proportion to those lines.');
            }
            $total = round((float) $total, 2);
        } else {
            $total = round((float) $client_project->total_amount, 2);
            if ($total < 0.01) {
                return back()->with('error', 'The stored contract total is invalid. Restore the quotation or contact support.');
            }
        }
        if ($paidCount > 0 && $plan !== (int) $client_project->payment_plan) {
            return back()
                ->withErrors(['payment_plan' => 'The payment plan cannot be changed — one or more installments have already been marked paid.'])
                ->withInput();
        }

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

    public function togglePaid(
        Request $request,
        ClientProject $client_project,
        ClientProjectInstallment $installment,
        ClientProjectStatusService $statusService
    ) {
        abort_unless($installment->client_project_id === $client_project->id, 404);

        $data = $request->validate([
            'is_paid' => ['required', 'boolean'],
            'paid_on' => ['nullable', 'date'],
        ]);

        $wasPaid = (bool) $installment->is_paid;
        $installment->is_paid = (bool) $data['is_paid'];
        $installment->paid_on = $data['is_paid']
            ? ($data['paid_on'] ?? now()->toDateString())
            : null;
        $installment->save();

        // Flipping the first installment to paid is the trigger for the
        // admin calendar email. Check the schedule is actually set first —
        // if not, we surface a warning so the admin knows the email is
        // still pending on the dates being saved.
        if (!$wasPaid && $installment->is_paid) {
            $firstInstallment = $client_project->firstInstallment();
            $isFirstInstallment = $firstInstallment && $firstInstallment->id === $installment->id;

            if ($isFirstInstallment) {
                if (!$client_project->hasSchedule()) {
                    return back()
                        ->with('success', 'Installment marked paid.')
                        ->with('warning', 'Please set the project start and end dates — the calendar notification will be emailed once both are saved.');
                }

                $fresh = $client_project->fresh();
                $result = $statusService->maybeSendCalendarDatesEmail($fresh);
                $fixed = $statusService->maybeSendFixedScheduleConfirmationToClient($fresh->fresh());

                $success = 'Installment marked paid.';
                $warnings = [];
                if ($result === 'sent') {
                    $success .= ' Calendar notification emailed to the admin.';
                } elseif ($result === 'failed') {
                    $warnings[] = 'The calendar notification email failed to send. Check logs and try again.';
                }
                if ($fixed === 'sent') {
                    $success .= ' Schedule confirmation emailed to the client.';
                } elseif ($fixed === 'failed') {
                    $warnings[] = 'The client schedule confirmation email failed to send. Check logs.';
                }

                $redirect = back()->with('success', $success);
                if ($warnings !== []) {
                    $redirect->with('warning', implode(' ', $warnings));
                }

                return $redirect;
            }
        }

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
            'receipt' => ['nullable', 'file', 'mimes:webp,jpeg,jpg,png', 'max:10240'],
        ]);

        try {
            DB::transaction(function () use ($request, $client_project, $data) {
                ClientProject::whereKey($client_project->id)->lockForUpdate()->first();

                $nextRef = (int) ClientProjectExpense::where('client_project_id', $client_project->id)->max('expense_ref') + 1;

                $expense = $client_project->expenses()->create([
                    'expense_ref' => $nextRef,
                    'occurred_on' => $data['occurred_on'],
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'receipt_path' => null,
                ]);

                if ($request->hasFile('receipt')) {
                    $receiptPath = app(ProjectPhotoWebpStorage::class)->store(
                        $request->file('receipt'),
                        $client_project->id,
                        'expense-receipts',
                        'Receipt-'.$nextRef
                    );
                    $expense->update(['receipt_path' => $receiptPath]);
                }
            });
        } catch (RuntimeException $e) {
            return back()
                ->withErrors(['receipt' => $e->getMessage()])
                ->withInput();
        }

        return back()->with('success', 'Expense added.');
    }

    /**
     * Email the client an expenses table and attach receipt WebP files when present.
     */
    public function sendExpensesToClient(ClientProject $client_project, ClientProjectStatusService $statusService)
    {
        if (! $client_project->isAccepted()) {
            return back()
                ->withFragment('financials')
                ->with('error', 'Expenses email is available once the proposal is accepted.');
        }

        $client_project->load(['expenses', 'returns']);

        if ($client_project->expenses->isEmpty()) {
            return back()
                ->withFragment('financials')
                ->with('error', 'Add at least one expense before sending to the client.');
        }

        if (! $client_project->hasClientContactInfo()) {
            return back()
                ->withFragment('financials')
                ->with('error', 'Cannot send expenses — the client name and email are required. Edit the project to add them.');
        }

        $cc = $client_project->secondaryClientEmail();
        $count = $client_project->expenses->count();
        $receiptCount = $client_project->expenses->filter(fn ($e) => filled($e->receipt_path))->count();
        $grossExpenses = $client_project->totalExpensesAmount();
        $returnsTotal = $client_project->totalReturnsAmount();
        $netMaterials = $client_project->totalSpent();
        $subject = 'Project expenses: '.$client_project->name.' — '.config('mail.from.name', 'Zanini Renovations');
        $description = 'Expenses summary — '.$count.' '.Str::plural('expense', $count)
            .', expenses '.ClientProject::formatMoney($grossExpenses);
        if ($returnsTotal > 0) {
            $description .= ', returns '.ClientProject::formatMoney($returnsTotal);
        }
        $description .= ', net (materials) '.ClientProject::formatMoney($netMaterials);
        if ($receiptCount > 0) {
            $description .= ', '.$receiptCount.' receipt '.Str::plural('photo', $receiptCount).' attached';
        }

        try {
            $mailer = Mail::to($client_project->client_email, $client_project->client_name);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectExpensesSummaryClient($client_project));

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_EXPENSES_SUMMARY,
                $client_project->client_email,
                $cc,
                $subject,
                $description,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to email expenses summary', [
                'project_id' => $client_project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_EXPENSES_SUMMARY,
                $client_project->client_email,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            return back()
                ->withFragment('financials')
                ->with('error', 'Failed to send the expenses email: '.$e->getMessage());
        }

        $sendNote = 'Expenses summary emailed to '.$client_project->clientEmailRecipientsLabel().'.';

        try {
            $statusService->recordEvent($client_project, $sendNote);
        } catch (\Throwable $e) {
            Log::warning('Failed to record expenses-summary history entry', [
                'project_id' => $client_project->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('financials')
            ->with('success', $sendNote);
    }

    public function destroyExpense(ClientProject $client_project, ClientProjectExpense $expense)
    {
        abort_unless($expense->client_project_id === $client_project->id, 404);
        if ($expense->client_project_invoice_id !== null) {
            return back()->with(
                'error',
                'This expense is attached to an invoice line. Remove or edit it on that invoice draft first, then you can delete the expense.'
            );
        }
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

        $data = $this->validateTimeEntry($request);
        $payload = $this->buildTimeEntryPayload($data);

        if ($payload === null) {
            return back()
                ->withErrors(['hours' => 'Provide a start time, or hours, or both — an empty entry can\'t be saved.'])
                ->withInput();
        }

        $client_project->timeEntries()->create($payload);

        $message = $payload['hours'] === null
            ? 'Time entry started — add an end time later to close it.'
            : 'Time entry added.';

        return back()->with('success', $message);
    }

    public function updateTime(Request $request, ClientProject $client_project, ClientProjectTimeEntry $time_entry)
    {
        abort_unless($time_entry->client_project_id === $client_project->id, 404);

        $data = $this->validateTimeEntry($request);
        $payload = $this->buildTimeEntryPayload($data);

        if ($payload === null) {
            return back()
                ->withErrors(['hours' => 'Provide a start time, or hours, or both — an empty entry can\'t be saved.'])
                ->withInput();
        }

        $time_entry->fill($payload)->save();

        return back()->with('success', 'Time entry updated.');
    }

    public function destroyTime(ClientProject $client_project, ClientProjectTimeEntry $time_entry)
    {
        abort_unless($time_entry->client_project_id === $client_project->id, 404);
        $time_entry->delete();

        return back()->with('success', 'Time entry removed.');
    }

    /**
     * Shared validation for create/update of a time entry.
     * Either hours OR start_time must be provided. End time is optional;
     * if provided with start_time, duration is same-day when end > start,
     * or overnight when end < start (next calendar morning).
     */
    private function validateTimeEntry(Request $request): array
    {
        return $request->validate([
            'occurred_on' => ['required', 'date'],
            'start_time' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'end_time' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'hours' => ['nullable', 'numeric', 'min:0.01', 'max:24'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'start_time.regex' => 'Start time must look like HH:MM (24h).',
            'end_time.regex' => 'End time must look like HH:MM (24h).',
        ]);
    }

    /**
     * Build the persistable time-entry attributes from validated input.
     * Returns null when nothing meaningful was provided (refuses to save
     * a row that has no hours and no start time).
     *
     * Rules:
     *   - If both start_time & end_time are present, hours is recomputed
     *     from the range (any submitted hours value is ignored to keep
     *     the two in sync).
     *   - If only start_time is present, the entry stays "open" (hours = null).
     *   - If only hours is present (legacy / quick log), start/end stay null.
     */
    private function buildTimeEntryPayload(array $data): ?array
    {
        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;
        $hours = $data['hours'] ?? null;

        if ($end && !$start) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_time' => 'Pick a start time before setting an end time.',
            ]);
        }

        if ($start && $end) {
            $computed = ClientProjectTimeEntry::computeHoursFromRange($start, $end);
            if ($computed === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'end_time' => 'Start and end must differ. For overnight work, set end to the next morning on the clock (e.g. 20:00 → 8:00).',
                ]);
            }
            $hours = $computed;
        } elseif ($start && !$end) {
            // Open / in-progress entry — no hours yet.
            $hours = null;
        }

        // Refuse a completely blank entry.
        if (!$start && !$end && $hours === null) {
            return null;
        }

        return [
            'occurred_on' => $data['occurred_on'],
            'start_time' => $start ?: null,
            'end_time' => $end ?: null,
            'hours' => $hours,
            'note' => $data['note'] ?? null,
        ];
    }

    private function defaultInstallmentLabel(int $plan, int $index): string
    {
        return self::installmentLabel($plan, $index);
    }

    /**
     * Canonical payment-plan installment labels.
     *
     * Centralised so the live editor (JS), the stored rows, and any future
     * consumers stay in sync. Keep any wording changes here — the JS
     * defaults in admin-client-projects.js mirror this list.
     */
    public static function installmentLabel(int $plan, int $index): string
    {
        $map = [
            1 => ['Full payment on completion'],
            2 => [
                'On signing (holds the schedule)',
                'On completion',
            ],
            3 => [
                'On signing (holds the schedule)',
                'Day before work begins',
                'On completion',
            ],
            4 => [
                'On signing (holds the schedule)',
                'Day before work begins',
                'Mid-project',
                'On completion',
            ],
        ];

        if (isset($map[$plan][$index])) {
            return $map[$plan][$index];
        }

        $ordinals = ['1st', '2nd', '3rd', '4th'];

        return ($ordinals[$index] ?? ($index + 1).'th').' installment';
    }
}

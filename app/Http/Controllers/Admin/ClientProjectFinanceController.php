<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectExpensesSummaryClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectReturn;
use App\Models\ClientProjectTimeEntry;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\ProjectPhotoWebpStorage;
use App\Services\RecalculateRemainingInstallmentsService;
use App\Support\CadMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Handles CRUD for the financial + time sections of a ClientProject:
 *   - Total amount + payment plan + installment percentages
 *   - Mark an installment as paid / unpaid
 *   - Expenses (add / update / delete)
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
                    ->with('error', 'Mark at least one quotation as Accepted (with line items) on the Quotation tab before saving the payment plan.');
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

        $invoiceSync = ['updated' => 0, 'deleted' => 0];

        DB::transaction(function () use ($client_project, $total, $plan, $percentages, &$invoiceSync) {
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

            // Keep draft/sent (unpaid) installment invoices aligned with the
            // new plan; paid invoices are never touched. Installments that
            // never had an invoice yet simply pick up the new amounts when
            // the admin creates them later.
            $invoiceSync = $this->syncUnpaidInstallmentInvoices($client_project->fresh());
        });

        $success = 'Payment plan saved.';
        if ($invoiceSync['deleted'] > 0 || $invoiceSync['updated'] > 0) {
            $parts = [];
            if ($invoiceSync['updated'] > 0) {
                $parts[] = $invoiceSync['updated'].' unpaid invoice(s) updated';
            }
            if ($invoiceSync['deleted'] > 0) {
                $parts[] = $invoiceSync['deleted'].' orphan unpaid invoice(s) removed';
            }
            $success .= ' '.implode('; ', $parts).'.';
        }

        return back()->with('success', $success);
    }

    /**
     * Align unpaid installment-linked invoices with the current payment plan.
     *
     * - Paid invoices are never modified.
     * - Unpaid invoices whose installment_number no longer exists are deleted.
     * - Remaining unpaid invoices get fresh amounts / labels from the plan.
     *
     * @return array{updated: int, deleted: int}
     */
    private function syncUnpaidInstallmentInvoices(ClientProject $project): array
    {
        $plan = (int) ($project->payment_plan ?: 1);
        $installments = $project->installments()->orderBy('sort_order')->get()->values();

        $updated = 0;
        $deleted = 0;

        $invoices = $project->invoices()
            ->whereNotNull('installment_number')
            ->where('status', '!=', ClientProjectInvoice::STATUS_PAID)
            ->get();

        foreach ($invoices as $invoice) {
            $n = (int) $invoice->installment_number;
            $inst = ($n >= 1 && $n <= $installments->count())
                ? $installments->get($n - 1)
                : null;

            if ($inst === null) {
                $invoice->delete();
                $deleted++;
                continue;
            }

            $description = ClientProjectInvoice::installmentDescriptionFor($plan, $n)
                ?: (string) $inst->label;
            $subtotal = round((float) $inst->amount, 2);
            $hst = $invoice->isHstExempt()
                ? 0.0
                : round((float) $inst->effectiveHst(), 2);

            $invoice->installment_description = $description;
            $invoice->items = [[
                'description' => $description !== '' ? $description : 'Installment '.$n,
                'amount' => $subtotal,
            ]];
            $invoice->subtotal = $subtotal;
            $invoice->hst = $hst;
            $invoice->total = round($subtotal + $hst, 2);

            if (filled($invoice->invoice_pdf_path)) {
                Storage::disk('local')->delete($invoice->invoice_pdf_path);
                $invoice->invoice_pdf_path = null;
            }

            $invoice->save();
            $updated++;
        }

        return ['updated' => $updated, 'deleted' => $deleted];
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

    /**
     * JSON preview for recalculating remaining installments after quotation totals change.
     */
    public function previewRecalculateInstallments(
        Request $request,
        ClientProject $client_project,
        RecalculateRemainingInstallmentsService $service
    ) {
        if (! $client_project->isAccepted() || $client_project->isHourly()) {
            return response()->json(['error' => 'Recalculation is only available for accepted fixed-price projects.'], 422);
        }

        $data = $request->validate([
            'remaining_count' => ['required', 'integer', 'min:0', 'max:4'],
            'split_mode' => ['nullable', Rule::in(['keep', 'defaults'])],
        ]);

        try {
            $preview = $service->preview(
                $client_project,
                (int) $data['remaining_count'],
                (string) ($data['split_mode'] ?? 'defaults')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($preview);
    }

    /**
     * Apply recalculation of remaining installments and recreate unpaid draft invoices.
     */
    public function recalculateInstallments(
        Request $request,
        ClientProject $client_project,
        RecalculateRemainingInstallmentsService $service
    ) {
        if (! $client_project->isAccepted() || $client_project->isHourly()) {
            return back()
                ->withFragment('financials')
                ->with('error', 'Recalculation is only available for accepted fixed-price projects.');
        }

        $data = $request->validate([
            'remaining_count' => ['required', 'integer', 'min:0', 'max:4'],
            'split_mode' => ['nullable', Rule::in(['keep', 'defaults'])],
        ]);

        try {
            $result = $service->apply(
                $client_project,
                (int) $data['remaining_count'],
                (string) ($data['split_mode'] ?? 'defaults')
            );
        } catch (InvalidArgumentException $e) {
            return back()
                ->withFragment('financials')
                ->with('error', $e->getMessage());
        }

        $msg = sprintf(
            'Remaining installments recalculated. Deleted %d unpaid invoice(s) and created %d draft invoice(s). Paid installments were left unchanged.',
            (int) $result['deleted_invoices'],
            (int) $result['created_invoices']
        );

        return back()
            ->withFragment('financials')
            ->with('success', $msg);
    }

    public function storeExpense(Request $request, ClientProject $client_project)
    {
        if (!$client_project->isAccepted()) {
            return back()->with('error', 'Expenses are available once the proposal is accepted.');
        }

        $request->merge([
            'amount' => CadMoney::stripGrouping($request->input('amount')),
        ]);

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'receipt' => $this->expenseReceiptFileRules(false),
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
                    $receiptPath = app(ProjectPhotoWebpStorage::class)->storeExpenseReceipt(
                        $request->file('receipt'),
                        $client_project->id,
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
     * Email the client an expenses table and attach receipt files (WebP or PDF) when present.
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
            $description .= ', '.$receiptCount.' receipt '.Str::plural('file', $receiptCount).' attached';
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

    public function receipt(ClientProject $client_project, ClientProjectExpense $expense)
    {
        abort_unless((int) $expense->client_project_id === (int) $client_project->id, 404);

        $rel = ltrim(str_replace('\\', '/', (string) ($expense->receipt_path ?? '')), '/');
        abort_if($rel === '', 404);

        $bytes = \App\Support\PrivateAppFile::get($rel);
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => $expense->receiptAttachmentMime(),
            'Content-Disposition' => 'inline; filename="'.$expense->receiptAttachmentBasename().'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function updateExpense(Request $request, ClientProject $client_project, ClientProjectExpense $expense)
    {
        if (! $client_project->isAccepted()) {
            return back()
                ->withFragment('financials')
                ->with('error', 'Expenses are available once the proposal is accepted.');
        }

        abort_unless($expense->client_project_id === $client_project->id, 404);

        $billed = $expense->client_project_invoice_id !== null;

        if ($billed) {
            $validator = Validator::make($request->all(), [
                'receipt' => $this->expenseReceiptFileRules(true),
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'occurred_on' => ['required', 'date'],
                'description' => ['required', 'string', 'max:500'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'receipt' => $this->expenseReceiptFileRules(false),
            ]);
        }

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('open_expense_edit', $expense->id)
                ->withFragment('financials');
        }

        $data = $validator->validated();

        try {
            DB::transaction(function () use ($request, $client_project, $expense, $data, $billed) {
                ClientProject::whereKey($client_project->id)->lockForUpdate()->first();

                $ref = (int) $expense->expense_ref;
                $basename = 'Receipt-'.$ref;

                if ($billed) {
                    $oldPath = $expense->receipt_path;
                    $newPath = app(ProjectPhotoWebpStorage::class)->storeExpenseReceipt(
                        $request->file('receipt'),
                        $client_project->id,
                        $basename
                    );
                    if (filled($oldPath) && $oldPath !== $newPath) {
                        $this->deleteExpenseReceiptFromStorage($oldPath);
                    }
                    $expense->update(['receipt_path' => $newPath]);
                } else {
                    $update = [
                        'occurred_on' => $data['occurred_on'],
                        'description' => $data['description'],
                        'amount' => $data['amount'],
                    ];
                    if ($request->hasFile('receipt')) {
                        $oldPath = $expense->receipt_path;
                        $newPath = app(ProjectPhotoWebpStorage::class)->storeExpenseReceipt(
                            $request->file('receipt'),
                            $client_project->id,
                            $basename
                        );
                        if (filled($oldPath) && $oldPath !== $newPath) {
                            $this->deleteExpenseReceiptFromStorage($oldPath);
                        }
                        $update['receipt_path'] = $newPath;
                    }
                    $expense->update($update);
                }
            });
        } catch (RuntimeException $e) {
            return back()
                ->withErrors(['receipt' => $e->getMessage()])
                ->withInput()
                ->with('open_expense_edit', $expense->id)
                ->withFragment('financials');
        }

        return back()
            ->withFragment('financials')
            ->with('success', 'Expense updated.');
    }

    private function deleteExpenseReceiptFromStorage(?string $relativePath): void
    {
        if (! filled($relativePath)) {
            return;
        }

        $rel = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
        Storage::disk('public')->delete($rel);
        $mirror = public_path('storage/'.$rel);
        if (is_file($mirror)) {
            @unlink($mirror);
        }
    }

    /**
     * @return list<string>
     */
    private function expenseReceiptFileRules(bool $required): array
    {
        $inner = ['file', 'mimes:pdf,webp,jpeg,jpg,png', 'max:10240'];

        return $required ? array_merge(['required'], $inner) : array_merge(['nullable'], $inner);
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
     * when both are set, end must be later than start on the same day.
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
        [$start, $end] = ClientProjectTimeEntry::assertValidTimeEntryTimes(
            $data['start_time'] ?? null,
            $data['end_time'] ?? null
        );
        $hours = $data['hours'] ?? null;

        if ($start && $end) {
            $computed = ClientProjectTimeEntry::computeHoursFromRange($start, $end);
            if ($computed === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'end_time' => 'End time must be after start time.',
                ]);
            }
            $hours = $computed;
        } elseif ($start && ! $end) {
            // Open / in-progress entry — no hours yet.
            $hours = null;
        }

        // Refuse a completely blank entry.
        if (! $start && ! $end && $hours === null) {
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

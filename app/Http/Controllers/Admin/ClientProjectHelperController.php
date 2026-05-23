<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\HelperPaymentNotification;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectHelperPayment;
use App\Models\ClientProjectHelperTimeEntry;
use App\Models\ClientProjectTimeEntry;
use App\Models\Helper;
use App\Services\ClientCommunicationLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientProjectHelperController extends Controller
{
    public function syncProjectHelpers(Request $request, ClientProject $client_project)
    {
        if (! $client_project->isAccepted()) {
            return back()->with('error', 'Helper assignment is available once the proposal is accepted.');
        }

        $data = $request->validate([
            'helper_ids' => ['nullable', 'array'],
            'helper_ids.*' => ['integer', Rule::exists('helpers', 'id')],
        ]);

        $ids = Helper::query()
            ->active()
            ->whereIn('id', $data['helper_ids'] ?? [])
            ->pluck('id');

        $client_project->helpers()->sync($ids);

        return back()
            ->withFragment('time')
            ->with('success', 'Project helpers updated.');
    }

    public function storeHelperTime(Request $request, ClientProject $client_project)
    {
        if (! $client_project->isAccepted()) {
            return back()->with('error', 'Time tracking is available once the proposal is accepted.');
        }

        $data = $this->validateTimeEntry($request);
        $payload = $this->buildTimeEntryPayload($data);

        if ($payload === null) {
            return back()
                ->withErrors(['hours' => 'Provide a start time, or hours, or both — an empty entry can\'t be saved.'])
                ->withInput()
                ->withFragment('time');
        }

        $helperId = (int) $request->validate([
            'helper_id' => ['required', 'integer', Rule::exists('helpers', 'id')],
        ])['helper_id'];

        $this->assertHelperAssigned($client_project, $helperId);

        $client_project->helperTimeEntries()->create($payload + ['helper_id' => $helperId]);

        $message = $payload['hours'] === null
            ? 'Helper time entry started — add an end time later to close it.'
            : 'Helper time entry added.';

        return back()->withFragment('time')->with('success', $message);
    }

    public function updateHelperTime(
        Request $request,
        ClientProject $client_project,
        ClientProjectHelperTimeEntry $helper_time_entry
    ) {
        abort_unless($helper_time_entry->client_project_id === $client_project->id, 404);

        if ($helper_time_entry->isPaid()) {
            return back()->withFragment('time')->with('error', 'Paid helper time entries cannot be edited.');
        }

        $data = $this->validateTimeEntry($request);
        $payload = $this->buildTimeEntryPayload($data);

        if ($payload === null) {
            return back()
                ->withErrors(['hours' => 'Provide a start time, or hours, or both — an empty entry can\'t be saved.'])
                ->withInput()
                ->withFragment('time');
        }

        $helperId = (int) $request->validate([
            'helper_id' => ['required', 'integer', Rule::exists('helpers', 'id')],
        ])['helper_id'];

        $this->assertHelperAssigned($client_project, $helperId);

        $helper_time_entry->fill($payload + ['helper_id' => $helperId])->save();

        return back()->withFragment('time')->with('success', 'Helper time entry updated.');
    }

    public function destroyHelperTime(
        ClientProject $client_project,
        ClientProjectHelperTimeEntry $helper_time_entry
    ) {
        abort_unless($helper_time_entry->client_project_id === $client_project->id, 404);

        if ($helper_time_entry->isPaid()) {
            return back()->withFragment('time')->with('error', 'Paid helper time entries cannot be deleted.');
        }

        $helper_time_entry->delete();

        return back()->withFragment('time')->with('success', 'Helper time entry removed.');
    }

    public function markHelperTimePaid(Request $request, ClientProject $client_project)
    {
        if (! $client_project->isAccepted()) {
            return back()->with('error', 'Helper payments are available once the proposal is accepted.');
        }

        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer'],
            'payment_method' => [
                'required',
                Rule::in(array_keys(ClientProjectHelperPayment::paymentMethodOptions())),
            ],
            'paid_on' => ['nullable', 'date'],
        ]);

        $paidOn = $data['paid_on'] ?? now()->toDateString();
        $payment = null;
        $emailWarning = null;

        try {
            DB::transaction(function () use ($client_project, $data, $paidOn, &$payment) {
                ClientProject::whereKey($client_project->id)->lockForUpdate()->first();

                $entries = ClientProjectHelperTimeEntry::query()
                    ->where('client_project_id', $client_project->id)
                    ->whereIn('id', $data['entry_ids'])
                    ->with('helper')
                    ->get();

                if ($entries->count() !== count(array_unique($data['entry_ids']))) {
                    throw ValidationException::withMessages([
                        'entry_ids' => 'One or more selected entries could not be found.',
                    ]);
                }

                foreach ($entries as $entry) {
                    if ($entry->isPaid()) {
                        throw ValidationException::withMessages([
                            'entry_ids' => 'One or more selected days are already marked paid.',
                        ]);
                    }
                    if ($entry->hours === null || $entry->isOpen()) {
                        throw ValidationException::withMessages([
                            'entry_ids' => 'Only closed entries (with hours) can be marked paid.',
                        ]);
                    }
                }

                $helperIds = $entries->pluck('helper_id')->unique();
                if ($helperIds->count() !== 1) {
                    throw ValidationException::withMessages([
                        'entry_ids' => 'Select days for one helper at a time.',
                    ]);
                }

                /** @var Helper $helper */
                $helper = $entries->first()->helper;
                if (! $helper || ! $helper->is_active) {
                    throw ValidationException::withMessages([
                        'entry_ids' => 'The selected helper is inactive.',
                    ]);
                }

                $this->assertHelperAssigned($client_project, $helper->id);

                if (! filled($helper->email)) {
                    throw ValidationException::withMessages([
                        'entry_ids' => 'The helper must have an email address before marking days paid.',
                    ]);
                }

                $rate = round((float) $helper->hourly_rate, 2);
                $totalHours = round((float) $entries->sum(fn ($e) => (float) $e->hours), 2);
                $totalAmount = round($totalHours * $rate, 2);

                if ($totalAmount < 0.01) {
                    throw ValidationException::withMessages([
                        'entry_ids' => 'The selected entries total zero — check hours and the helper rate.',
                    ]);
                }

                $sortedDates = $entries
                    ->sortBy(fn ($e) => $e->occurred_on?->format('Y-m-d') ?? '')
                    ->map(fn ($e) => ClientProject::formatDate($e->occurred_on))
                    ->values();

                $datesLabel = $sortedDates->count() === 1
                    ? $sortedDates->first()
                    : $sortedDates->first().' – '.$sortedDates->last().' ('.$sortedDates->count().' days)';

                $nextRef = (int) ClientProjectExpense::where('client_project_id', $client_project->id)->max('expense_ref') + 1;

                $expense = $client_project->expenses()->create([
                    'expense_ref' => $nextRef,
                    'occurred_on' => $paidOn,
                    'description' => sprintf(
                        'Helper labour: %s — %s (%.2f h × %s)',
                        $helper->name,
                        $datesLabel,
                        $totalHours,
                        ClientProject::formatMoney($rate)
                    ),
                    'amount' => $totalAmount,
                ]);

                $payment = ClientProjectHelperPayment::create([
                    'client_project_id' => $client_project->id,
                    'helper_id' => $helper->id,
                    'payment_method' => $data['payment_method'],
                    'total_amount' => $totalAmount,
                    'total_hours' => $totalHours,
                    'hourly_rate' => $rate,
                    'client_project_expense_id' => $expense->id,
                    'paid_at' => $paidOn,
                ]);

                ClientProjectHelperTimeEntry::query()
                    ->whereIn('id', $entries->pluck('id'))
                    ->update(['helper_payment_id' => $payment->id]);
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        if (! $payment instanceof ClientProjectHelperPayment) {
            return back()->withFragment('time')->with('error', 'Could not record the helper payment.');
        }

        $payment->load(['helper', 'timeEntries', 'project']);

        try {
            Mail::to($payment->helper->email, $payment->helper->name)
                ->send(new HelperPaymentNotification($payment));

            $payment->update(['email_sent_at' => now()]);

            $entryCount = $payment->timeEntries->count();
            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_HELPER_PAYMENT,
                $payment->helper->email,
                null,
                'Payment for your work on '.$client_project->name,
                sprintf(
                    'Helper payment — %s, %s, %d %s, %s via %s',
                    $payment->helper->name,
                    ClientProject::formatMoney((float) $payment->total_amount),
                    $entryCount,
                    \Illuminate\Support\Str::plural('day', $entryCount),
                    ClientProject::formatDate($payment->paid_at),
                    $payment->paymentMethodLabel()
                ),
                ClientCommunicationLog::DIRECTION_TO_HELPER,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to email helper payment notification', [
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_HELPER_PAYMENT,
                $payment->helper->email,
                null,
                'Payment for your work on '.$client_project->name,
                'Helper payment email failed for '.$payment->helper->name,
                ClientCommunicationLog::DIRECTION_TO_HELPER,
                false,
                $e->getMessage(),
            );

            $emailWarning = 'Payment recorded and added to project expenses, but the email to the helper failed: '.$e->getMessage();
        }

        $redirect = back()
            ->withFragment('time')
            ->with('success', 'Helper days marked paid and recorded as a project expense.');

        if ($emailWarning) {
            $redirect->with('warning', $emailWarning);
        }

        return $redirect;
    }

    public function acknowledgeHelperPayment(ClientProjectHelperPayment $helper_payment)
    {
        if (! $helper_payment->isAcknowledged()) {
            $helper_payment->update(['acknowledged_at' => now()]);
        }

        return view('site.helper-payment-acknowledged', [
            'payment' => $helper_payment->load(['helper', 'project']),
        ]);
    }

    public function markHelperPaymentAcknowledged(
        ClientProject $client_project,
        ClientProjectHelperPayment $helper_payment
    ) {
        abort_unless($helper_payment->client_project_id === $client_project->id, 404);

        if (! $helper_payment->isAcknowledged()) {
            $helper_payment->update(['acknowledged_at' => now()]);
        }

        return back()->withFragment('time')->with('success', 'Payment marked as acknowledged by helper.');
    }

    private function assertHelperAssigned(ClientProject $project, int $helperId): void
    {
        if (! $project->helpers()->where('helpers.id', $helperId)->exists()) {
            throw ValidationException::withMessages([
                'helper_id' => 'Assign this helper to the project first.',
            ]);
        }
    }

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

    private function buildTimeEntryPayload(array $data): ?array
    {
        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;
        $hours = $data['hours'] ?? null;

        if ($end && ! $start) {
            throw ValidationException::withMessages([
                'start_time' => 'Pick a start time before setting an end time.',
            ]);
        }

        if ($start && $end) {
            $computed = ClientProjectTimeEntry::computeHoursFromRange($start, $end);
            if ($computed === null) {
                throw ValidationException::withMessages([
                    'end_time' => 'Start and end must differ. For overnight work, set end to the next morning on the clock (e.g. 20:00 → 8:00).',
                ]);
            }
            $hours = $computed;
        } elseif ($start && ! $end) {
            $hours = null;
        }

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
}

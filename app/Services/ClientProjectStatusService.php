<?php

namespace App\Services;

use App\Mail\ClientProjectCalendarEventAdmin;
use App\Mail\ClientProjectFixedScheduleConfirmationClient;
use App\Mail\ClientProjectQuoteReceivedClient;
use App\Mail\ClientProjectThankYouClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectStatusHistoryEntry;
use App\Services\ClientProjectContactService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Orchestrates status transitions for ClientProject, along with the
 * side-effects each transition triggers (history log, automated emails,
 * scheduled follow-ups, and auto-promotion rules).
 */
class ClientProjectStatusService
{
    /**
     * Apply a status transition. If `$toStatus === null` we just ensure the
     * project is bootstrapped (used right after creation).
     *
     * @return ClientProject the refreshed project
     */
    public function transition(ClientProject $project, ?string $toStatus, ?string $note = null): ClientProject
    {
        $fromStatus = $project->status;

        return DB::transaction(function () use ($project, $fromStatus, $toStatus, $note) {
            if ($toStatus !== null && $toStatus !== $fromStatus) {
                $project->status = $toStatus;
                $project->status_changed_at = now();

                // Reset bookkeeping that only makes sense for the pre-quote-sent stages.
                if (!in_array($toStatus, ClientProject::preQuoteSentStatuses(), true)) {
                    $project->last_quote_reminder_sent_at = null;
                }
                if ($toStatus === ClientProject::STATUS_QUOTE_SENT) {
                    $project->quote_sent_follow_up_due_at = now()->addDays(2);
                    $project->quote_sent_follow_up_sent_at = null;
                } else {
                    // Clear the pending follow-up if we leave Quote Sent.
                    if ($fromStatus === ClientProject::STATUS_QUOTE_SENT) {
                        $project->quote_sent_follow_up_due_at = null;
                    }
                }

                $project->save();

                $this->recordHistory($project, $fromStatus, $toStatus, $note);
            }

            $this->runSideEffects($project, $fromStatus, $toStatus);

            return $project->fresh([
                'installments',
                'expenses',
                'returns',
                'timeEntries',
                'statusHistory',
            ]);
        });
    }

    /**
     * Called right after a ClientProject is created. Ensures the initial
     * status is logged in history and — for fixed-price quote requests —
     * sends the "quote request received" acknowledgement to the client.
     *
     * The $sendClientEmail flag lets the caller suppress that email
     * when the admin unchecks the "notify client" checkbox on create
     * (e.g. the project was already discussed in person / on the phone).
     */
    public function onCreated(ClientProject $project, bool $sendClientEmail = true): void
    {
        $this->recordHistory($project, null, $project->status, 'Project created.');

        if ($sendClientEmail && $project->isAwaitingQuotePreparation()) {
            $this->safeSendClientQuoteReceived($project);
        }
    }

    /**
     * Log a non-transition event against the project history (e.g. an
     * email send, a file upload, a manual note). Uses the project's
     * current status on both sides so the UI can render it as
     * "unchanged status → event-only row".
     */
    public function recordEvent(ClientProject $project, string $note): void
    {
        $this->recordHistory($project, $project->status, $project->status, $note);
    }

    /**
     * Keep visit-related statuses aligned with visit_required / visit_date.
     * Visit required (no date) ↔ Visit scheduled (date set).
     */
    public function syncVisitStatusFromFields(ClientProject $project): ClientProject
    {
        if (! $project->visit_required) {
            return $project;
        }

        if ($project->status === ClientProject::STATUS_VISIT_REQUIRED && filled($project->visit_date)) {
            return $this->transition($project, ClientProject::STATUS_VISIT_SCHEDULED, 'Visit date set.');
        }

        if ($project->status === ClientProject::STATUS_VISIT_SCHEDULED && ! filled($project->visit_date)) {
            return $this->transition($project, ClientProject::STATUS_VISIT_REQUIRED, 'Visit date cleared.');
        }

        return $project;
    }

    private function recordHistory(ClientProject $project, ?string $fromStatus, string $toStatus, ?string $note): void
    {
        ClientProjectStatusHistoryEntry::create([
            'client_project_id' => $project->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'user_id' => optional(Auth::user())->id,
            'changed_at' => now(),
        ]);
    }

    private function runSideEffects(ClientProject $project, ?string $fromStatus, ?string $toStatus): void
    {
        if ($toStatus === null) {
            return;
        }

        // Historic note: there used to be an auto-promotion
        // Proposal Accepted → In Progress here. With the new "Scheduled"
        // status sitting between acceptance and active work, each
        // transition now carries distinct business meaning and the
        // user advances through them explicitly.

        if ($toStatus === ClientProject::STATUS_COMPLETED) {
            app(ClientProjectContactService::class)->captureClientSnapshot($project->fresh());
            $this->safeSendClientThankYou($project);
        }
    }

    /**
     * Conditionally notify the admin of a project's scheduled dates. The
     * email is only sent once BOTH conditions are true:
     *   - start_date + end_date are saved
     *   - the first installment of the payment plan is marked paid
     *
     * Hourly projects have no installments, so they're exempt from the
     * "first installment paid" gate and the email fires as soon as dates
     * are saved (preserving the previous behavior for that flow).
     *
     * Returns a status code so the caller can surface user-facing
     * feedback:
     *   - 'sent'             email sent successfully
     *   - 'waiting-dates'    first installment paid but dates not set
     *   - 'waiting-payment'  dates set but first installment not paid yet
     *   - 'skipped'          neither condition met, nothing to do
     *   - 'failed'           tried to send but SMTP/exception blew up
     */
    public function maybeSendCalendarDatesEmail(ClientProject $project): string
    {
        $datesSet = $project->hasSchedule();

        // Hourly projects: no payment plan → trigger on dates alone.
        // Fixed-price projects: also require first installment paid.
        $paymentGateOk = $project->isHourly() || $project->firstInstallmentPaid();

        if (!$datesSet && !$paymentGateOk) {
            return 'skipped';
        }
        if (!$datesSet) {
            return 'waiting-dates';
        }
        if (!$paymentGateOk) {
            return 'waiting-payment';
        }

        return $this->sendCalendarDatesEmail($project) ? 'sent' : 'failed';
    }

    /**
     * Low-level sender. Prefer maybeSendCalendarDatesEmail() which applies
     * the gating rules. Exposed only for unit tests / one-off retries.
     */
    public function sendCalendarDatesEmail(ClientProject $project): bool
    {
        $adminEmail = config('client_projects.calendar_email_to')
            ?: 'jonaszanini@gmail.com';

        $start = optional($project->start_date)->format('d/m/Y');
        $end = optional($project->end_date)->format('d/m/Y');
        $description = "Calendar event for project — schedule {$start} → {$end}";

        try {
            Mail::to($adminEmail)->send(new ClientProjectCalendarEventAdmin($project));

            $project->calendar_email_sent_at = now();
            $project->save();

            $this->recordEvent(
                $project,
                "Dates saved ({$start} → {$end}). Admin calendar notification emailed to {$adminEmail}."
            );

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_CALENDAR_EVENT_ADMIN,
                $adminEmail,
                null,
                'New scheduled project — '.($project->name ?? '#'.$project->id),
                $description,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('ClientProject calendar email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_CALENDAR_EVENT_ADMIN,
                $adminEmail,
                null,
                'New scheduled project — '.($project->name ?? '#'.$project->id),
                $description,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
                false,
                $e->getMessage(),
            );

            return false;
        }
    }

    /**
     * Email the client once on fixed-price projects when the first installment
     * is paid and both schedule dates are set — separate from invoice/receipt
     * mail. Idempotent via `fixed_schedule_confirmation_sent_at`.
     *
     * @return string 'sent'|'skipped-hourly'|'waiting-dates'|'waiting-payment'|'already-sent'|'no-contact'|'failed'
     */
    public function maybeSendFixedScheduleConfirmationToClient(ClientProject $project): string
    {
        if ($project->isHourly()) {
            return 'skipped-hourly';
        }
        if (!$project->hasSchedule()) {
            return 'waiting-dates';
        }
        if (!$project->firstInstallmentPaid()) {
            return 'waiting-payment';
        }
        if ($project->fixed_schedule_confirmation_sent_at) {
            return 'already-sent';
        }
        if (!$project->hasClientContactInfo()) {
            return 'no-contact';
        }

        $cc = $project->secondaryClientEmail();
        $start = optional($project->start_date)->format('M j, Y');
        $end = optional($project->end_date)->format('M j, Y');
        $description = "Schedule confirmation (fixed-price) — {$start} to {$end}, first installment paid";
        $subject = 'Your project schedule is confirmed — Zanini Renovations';

        try {
            $mailer = Mail::to($project->client_email, $project->client_name);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectFixedScheduleConfirmationClient($project));

            $project->fixed_schedule_confirmation_sent_at = now();
            $project->save();

            $this->recordEvent(
                $project,
                'Schedule confirmation (fixed price) emailed to '.$project->clientEmailRecipientsLabel().'.'
            );

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_FIXED_SCHEDULE_CONFIRMATION,
                $project->client_email,
                $cc,
                $subject,
                $description,
            );

            return 'sent';
        } catch (\Throwable $e) {
            Log::warning('Fixed-price schedule confirmation email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_FIXED_SCHEDULE_CONFIRMATION,
                $project->client_email,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            return 'failed';
        }
    }

    private function safeSendClientQuoteReceived(ClientProject $project): void
    {
        $email = trim((string) $project->client_email);
        if ($email === '') {
            return;
        }

        $cc = $project->secondaryClientEmail();
        $subject = "We've received your quote request — Zanini Renovations";
        $description = 'Auto-acknowledgement after quote request submitted';

        try {
            $mailer = Mail::to($email, $project->client_name);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectQuoteReceivedClient($project));

            $this->recordEvent($project, "Quote-received acknowledgement emailed to {$project->clientEmailRecipientsLabel()}.");

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_QUOTE_RECEIVED_AUTO,
                $email,
                $cc,
                $subject,
                $description,
            );
        } catch (\Throwable $e) {
            Log::warning('Client quote-received email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_QUOTE_RECEIVED_AUTO,
                $email,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );
        }
    }

    private function safeSendClientThankYou(ClientProject $project): void
    {
        $email = trim((string) $project->client_email);
        if ($email === '') {
            return;
        }

        $cc = $project->secondaryClientEmail();
        $subject = 'Thank you from Zanini Renovations';
        $description = 'Thank-you / Google review request after project completion';

        try {
            $mailer = Mail::to($email, $project->client_name);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectThankYouClient($project));

            $this->recordEvent($project, "Thank-you / review-request email sent to {$project->clientEmailRecipientsLabel()}.");

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_THANK_YOU,
                $email,
                $cc,
                $subject,
                $description,
            );
        } catch (\Throwable $e) {
            Log::warning('Client thank-you email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_THANK_YOU,
                $email,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );
        }
    }
}

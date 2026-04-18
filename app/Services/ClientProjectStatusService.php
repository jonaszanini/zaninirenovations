<?php

namespace App\Services;

use App\Mail\ClientProjectCalendarEventAdmin;
use App\Mail\ClientProjectQuoteReceivedClient;
use App\Mail\ClientProjectThankYouClient;
use App\Models\ClientProject;
use App\Models\ClientProjectStatusHistoryEntry;
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

                // Reset bookkeeping that only makes sense for a specific status.
                if ($toStatus !== ClientProject::STATUS_QUOTE_PENDING) {
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
     * Called right after a ClientProject is created. Ensures Quote Pending
     * status is logged in history + optional "thank you for your request"
     * email to the client.
     */
    public function onCreated(ClientProject $project): void
    {
        $this->recordHistory($project, null, $project->status, 'Project created.');

        if ($project->status === ClientProject::STATUS_QUOTE_PENDING) {
            $this->safeSendClientQuoteReceived($project);
        }
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

        // Proposal Accepted → auto-promote to In Progress (per spec).
        if ($toStatus === ClientProject::STATUS_PROPOSAL_ACCEPTED && !$this->alreadyPromoted($project)) {
            $project->status = ClientProject::STATUS_IN_PROGRESS;
            $project->status_changed_at = now();
            $project->save();
            $this->recordHistory(
                $project,
                ClientProject::STATUS_PROPOSAL_ACCEPTED,
                ClientProject::STATUS_IN_PROGRESS,
                'Auto-promoted after proposal acceptance.'
            );
        }

        if ($toStatus === ClientProject::STATUS_COMPLETED) {
            $this->safeSendClientThankYou($project);
        }
    }

    private function alreadyPromoted(ClientProject $project): bool
    {
        return $project->status === ClientProject::STATUS_IN_PROGRESS;
    }

    /**
     * Notify the admin of newly saved start/end dates. Only sent once per
     * date-save event (the controller passes `shouldNotify = true`).
     */
    public function notifyCalendarDatesSaved(ClientProject $project): void
    {
        try {
            $adminEmail = config('client_projects.calendar_email_to')
                ?: 'jonaszanini@gmail.com';

            Mail::to($adminEmail)->send(new ClientProjectCalendarEventAdmin($project));

            $project->calendar_email_sent_at = now();
            $project->save();
        } catch (\Throwable $e) {
            Log::warning('ClientProject calendar email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function safeSendClientQuoteReceived(ClientProject $project): void
    {
        $email = trim((string) $project->client_email);
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email, $project->client_name)
                ->send(new ClientProjectQuoteReceivedClient($project));
        } catch (\Throwable $e) {
            Log::warning('Client quote-received email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function safeSendClientThankYou(ClientProject $project): void
    {
        $email = trim((string) $project->client_email);
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email, $project->client_name)
                ->send(new ClientProjectThankYouClient($project));
        } catch (\Throwable $e) {
            Log::warning('Client thank-you email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

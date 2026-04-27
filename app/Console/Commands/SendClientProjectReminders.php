<?php

namespace App\Console\Commands;

use App\Mail\ClientProjectQuoteFollowUpAdmin;
use App\Mail\ClientProjectQuoteReminderAdmin;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the two kinds of admin reminder emails for Client Projects:
 *   1. Daily reminder for projects in Visit scheduled or Quote Pending (awaiting quote).
 *   2. "Quote Sent" follow-up reminder for any project whose 2-day follow-up
 *      due timestamp has passed and which hasn't been reminded yet.
 *
 * Scheduled once a day from App\Console\Kernel.
 */
class SendClientProjectReminders extends Command
{
    protected $signature = 'client-projects:send-reminders';

    protected $description = 'Send admin reminders for Visit scheduled / Quote Pending and Quote Sent projects.';

    public function handle(ClientProjectStatusService $statusService): int
    {
        $this->sendQuotePendingReminders($statusService);
        $this->sendQuoteSentFollowUps($statusService);

        return self::SUCCESS;
    }

    private function sendQuotePendingReminders(ClientProjectStatusService $statusService): void
    {
        $to = (string) config('client_projects.quote_reminder_email_to');
        if ($to === '') {
            $this->warn('No quote_reminder_email_to configured, skipping daily Quote Pending reminders.');

            return;
        }

        $projects = ClientProject::query()
            ->whereIn('status', ClientProject::preQuoteSentStatuses())
            ->orderBy('created_at')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No Visit scheduled / Quote Pending projects.');

            return;
        }

        $logger = app(ClientCommunicationLogger::class);

        foreach ($projects as $project) {
            $description = "Daily pre-quote reminder for project #{$project->id} (status: {$project->status})";
            try {
                Mail::to($to)->send(new ClientProjectQuoteReminderAdmin($project));
                $project->last_quote_reminder_sent_at = now();
                $project->save();
                $statusService->recordEvent($project, "Daily pre-quote reminder emailed to admin ({$to}).");

                $logger->logEmail(
                    $project,
                    ClientCommunicationLog::REASON_QUOTE_PENDING_REMINDER_ADMIN,
                    $to,
                    null,
                    'Daily reminder — quote still pending',
                    $description,
                    ClientCommunicationLog::DIRECTION_TO_ADMIN,
                );

                $this->info('Reminder sent for project #'.$project->id.' '.$project->name);
            } catch (\Throwable $e) {
                Log::error('Pre-quote reminder failed', [
                    'client_project_id' => $project->id,
                    'message' => $e->getMessage(),
                ]);

                $logger->logEmail(
                    $project,
                    ClientCommunicationLog::REASON_QUOTE_PENDING_REMINDER_ADMIN,
                    $to,
                    null,
                    'Daily reminder — quote still pending',
                    $description,
                    ClientCommunicationLog::DIRECTION_TO_ADMIN,
                    false,
                    $e->getMessage(),
                );

                $this->error('Failed for project #'.$project->id.': '.$e->getMessage());
            }
        }
    }

    private function sendQuoteSentFollowUps(ClientProjectStatusService $statusService): void
    {
        $to = (string) config('client_projects.quote_follow_up_email_to');
        if ($to === '') {
            $this->warn('No quote_follow_up_email_to configured, skipping Quote Sent follow-ups.');

            return;
        }

        $projects = ClientProject::query()
            ->where('status', ClientProject::STATUS_QUOTE_SENT)
            ->whereNotNull('quote_sent_follow_up_due_at')
            ->whereNull('quote_sent_follow_up_sent_at')
            ->where('quote_sent_follow_up_due_at', '<=', now())
            ->get();

        $logger = app(ClientCommunicationLogger::class);

        foreach ($projects as $project) {
            $description = "2-day Quote-Sent follow-up for project #{$project->id}";
            try {
                Mail::to($to)->send(new ClientProjectQuoteFollowUpAdmin($project));
                $project->quote_sent_follow_up_sent_at = now();
                $project->save();
                $statusService->recordEvent($project, "2-day Quote-Sent follow-up reminder emailed to admin ({$to}).");

                $logger->logEmail(
                    $project,
                    ClientCommunicationLog::REASON_QUOTE_SENT_FOLLOWUP_ADMIN,
                    $to,
                    null,
                    'Follow-up reminder — quote sent 2 days ago',
                    $description,
                    ClientCommunicationLog::DIRECTION_TO_ADMIN,
                );

                $this->info('Follow-up sent for project #'.$project->id.' '.$project->name);
            } catch (\Throwable $e) {
                Log::error('Quote Sent follow-up failed', [
                    'client_project_id' => $project->id,
                    'message' => $e->getMessage(),
                ]);

                $logger->logEmail(
                    $project,
                    ClientCommunicationLog::REASON_QUOTE_SENT_FOLLOWUP_ADMIN,
                    $to,
                    null,
                    'Follow-up reminder — quote sent 2 days ago',
                    $description,
                    ClientCommunicationLog::DIRECTION_TO_ADMIN,
                    false,
                    $e->getMessage(),
                );

                $this->error('Failed for project #'.$project->id.': '.$e->getMessage());
            }
        }
    }
}

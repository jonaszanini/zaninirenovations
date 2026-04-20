<?php

namespace App\Console\Commands;

use App\Mail\ClientProjectQuoteFollowUpAdmin;
use App\Mail\ClientProjectQuoteReminderAdmin;
use App\Models\ClientProject;
use App\Services\ClientProjectStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the two kinds of admin reminder emails for Client Projects:
 *   1. Daily "Quote Pending" reminder for every project still awaiting a quote.
 *   2. "Quote Sent" follow-up reminder for any project whose 2-day follow-up
 *      due timestamp has passed and which hasn't been reminded yet.
 *
 * Scheduled once a day from App\Console\Kernel.
 */
class SendClientProjectReminders extends Command
{
    protected $signature = 'client-projects:send-reminders';

    protected $description = 'Send admin reminders for Quote Pending and Quote Sent projects.';

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
            ->where('status', ClientProject::STATUS_QUOTE_PENDING)
            ->orderBy('created_at')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No Quote Pending projects.');

            return;
        }

        foreach ($projects as $project) {
            try {
                Mail::to($to)->send(new ClientProjectQuoteReminderAdmin($project));
                $project->last_quote_reminder_sent_at = now();
                $project->save();
                $statusService->recordEvent($project, "Daily Quote-Pending reminder emailed to admin ({$to}).");
                $this->info('Reminder sent for project #'.$project->id.' '.$project->name);
            } catch (\Throwable $e) {
                Log::error('Quote Pending reminder failed', [
                    'client_project_id' => $project->id,
                    'message' => $e->getMessage(),
                ]);
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

        foreach ($projects as $project) {
            try {
                Mail::to($to)->send(new ClientProjectQuoteFollowUpAdmin($project));
                $project->quote_sent_follow_up_sent_at = now();
                $project->save();
                $statusService->recordEvent($project, "2-day Quote-Sent follow-up reminder emailed to admin ({$to}).");
                $this->info('Follow-up sent for project #'.$project->id.' '.$project->name);
            } catch (\Throwable $e) {
                Log::error('Quote Sent follow-up failed', [
                    'client_project_id' => $project->id,
                    'message' => $e->getMessage(),
                ]);
                $this->error('Failed for project #'.$project->id.': '.$e->getMessage());
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\ClientProjectCommencementEveAdmin;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Services\ClientCommunicationLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Once per calendar day (Toronto), handle each project the day before its
 * scheduled start_date unless this start_date was already processed.
 *
 * Client email and SMS are disabled. Nothing is sent to the client.
 *
 * Optional admin email: CLIENT_PROJECTS_COMMENCEMENT_EVE_TO
 * (still sent when that address is configured).
 *
 * day_before_start_sent_for_date is set after the admin step, or when no
 * admin address is configured, so the same start date is not processed again.
 *
 * Runs via `automations:run-daily` (HTTP cron at 9am EST).
 */
class NotifyClientProjectCommencementEve extends Command
{
    protected $signature = 'client-projects:notify-commencement-eve {--dry-run : List matches without sending}';

    protected $description = 'Day before commencement reminder. Client email and SMS are disabled. Optional admin email still sends.';

    public function handle(ClientCommunicationLogger $logger): int
    {
        $tz = 'America/Toronto';
        $tomorrow = Carbon::tomorrow($tz)->toDateString();

        $adminEmailTo = (string) config('client_projects.commencement_eve_email_to');

        $projects = ClientProject::query()
            ->whereDate('start_date', $tomorrow)
            ->whereNotIn('status', [
                ClientProject::STATUS_CANCELLED,
                ClientProject::STATUS_REJECTED,
                ClientProject::STATUS_COMPLETED,
            ])
            ->where(function ($q) {
                $q->whereNull('day_before_start_sent_for_date')
                    ->orWhereColumn('day_before_start_sent_for_date', '!=', 'start_date');
            })
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with start date '.$tomorrow.' (or all already notified for current start date).');

            return self::SUCCESS;
        }

        $this->info('Projects starting tomorrow ('.$tomorrow.'): '.$projects->count().'. Client email and SMS are disabled.');

        foreach ($projects as $project) {
            if ($this->option('dry-run')) {
                $this->line($this->dryRunLine($project, $adminEmailTo));

                continue;
            }

            $startLabel = optional($project->start_date)->format('M j, Y') ?: 'tomorrow';

            if ($adminEmailTo !== '') {
                $this->sendAdminEmail($project, $adminEmailTo, $startLabel, $logger);
            }

            $project->day_before_start_sent_for_date = $project->start_date;
            $project->save();
        }

        return self::SUCCESS;
    }

    private function dryRunLine(ClientProject $project, string $adminEmailTo): string
    {
        $line = '  [dry-run] #'.$project->id.' '.$project->name;

        if ($adminEmailTo !== '') {
            $line .= ' → admin email → '.$adminEmailTo;
        } else {
            $line .= ' → no admin email configured';
        }

        return $line;
    }

    private function sendAdminEmail(
        ClientProject $project,
        string $adminEmailTo,
        string $startLabel,
        ClientCommunicationLogger $logger,
    ): void {
        $emailDescription = "Day-before-start admin reminder — project starts {$startLabel}";
        $subject = 'Project starts tomorrow — '.($project->name ?? '#'.$project->id);

        try {
            Mail::to($adminEmailTo)->send(new ClientProjectCommencementEveAdmin($project));

            $logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
                $adminEmailTo,
                null,
                $subject,
                $emailDescription,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
            );

            $this->info('Admin email sent for project #'.$project->id.' '.$project->name);
        } catch (\Throwable $e) {
            Log::error('Commencement eve admin email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            $logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
                $adminEmailTo,
                null,
                $subject,
                $emailDescription,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
                false,
                $e->getMessage(),
            );

            $this->error('Admin email failed for project #'.$project->id.': '.$e->getMessage());
        }
    }
}

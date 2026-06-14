<?php

namespace App\Console\Commands;

use App\Models\ClientProject;
use App\Services\ClientProjectStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Once per calendar day (Toronto), promote any project whose scheduled
 * start_date is today to "In Progress".
 *
 * Projects already at or past In Progress (in_progress, awaiting_final_payment,
 * completed, rejected) are left untouched so the automation never walks a
 * project backwards or re-activates closed work.
 *
 * Requires `php artisan schedule:run` every minute from cron.
 */
class StartDueClientProjects extends Command
{
    protected $signature = 'client-projects:start-due-today {--dry-run : List matches without changing status}';

    protected $description = 'Set projects whose start date is today to In Progress.';

    public function handle(ClientProjectStatusService $statusService): int
    {
        $tz = 'America/Toronto';
        $today = Carbon::today($tz)->toDateString();

        $projects = ClientProject::query()
            ->whereDate('start_date', $today)
            ->whereNotIn('status', [
                ClientProject::STATUS_IN_PROGRESS,
                ClientProject::STATUS_AWAITING_FINAL_PAYMENT,
                ClientProject::STATUS_COMPLETED,
                ClientProject::STATUS_CANCELLED,
                ClientProject::STATUS_REJECTED,
            ])
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No projects to start today ('.$today.').');

            return self::SUCCESS;
        }

        $this->info('Projects starting today ('.$today.'): '.$projects->count());

        foreach ($projects as $project) {
            if ($this->option('dry-run')) {
                $this->line('  [dry-run] #'.$project->id.' '.$project->name
                    .' ('.$project->statusLabel().' → In Progress)');

                continue;
            }

            $statusService->transition(
                $project,
                ClientProject::STATUS_IN_PROGRESS,
                'Automatically set to In Progress on scheduled start date ('.$today.').'
            );

            $this->info('Project #'.$project->id.' '.$project->name.' set to In Progress.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for every daily automated activity.
 *
 * The production trigger is the HTTP cron endpoint (GET /cron/run), which an
 * external scheduler hits once a day at 9am EST. That endpoint runs THIS
 * command, so each automation below fires unconditionally on every hit —
 * independent of Laravel's per-minute scheduler time matching.
 *
 * To add a new daily automation: build its own artisan command, then add its
 * signature to {@see self::DAILY_AUTOMATIONS}. Nothing else is required for it
 * to start running on the next cron hit.
 */
class RunDailyAutomations extends Command
{
    protected $signature = 'automations:run-daily';

    protected $description = 'Run every daily automated activity (driven by the daily cron hit).';

    /**
     * Ordered list of artisan commands to run on each daily cron hit.
     * Each command is independently idempotent for a given day.
     *
     * @var list<string>
     */
    private const DAILY_AUTOMATIONS = [
        'client-projects:send-reminders',
        'client-projects:notify-commencement-eve',
        'client-projects:start-due-today',
    ];

    public function handle(): int
    {
        $overall = self::SUCCESS;

        foreach (self::DAILY_AUTOMATIONS as $command) {
            $this->line('── Running '.$command.' ──');

            try {
                $exit = $this->call($command);
                if ($exit !== self::SUCCESS) {
                    $overall = self::FAILURE;
                    Log::warning('Daily automation returned non-zero exit', [
                        'command' => $command,
                        'exit' => $exit,
                    ]);
                }
            } catch (\Throwable $e) {
                $overall = self::FAILURE;
                Log::error('Daily automation threw an exception', [
                    'command' => $command,
                    'message' => $e->getMessage(),
                ]);
                $this->error($command.' failed: '.$e->getMessage());
            }
        }

        return $overall;
    }
}

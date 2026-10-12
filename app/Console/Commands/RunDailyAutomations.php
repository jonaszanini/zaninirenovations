<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for every daily automated activity.
 *
 * **Production:** cron-job.org hits GET /cron/run once daily at 9am Eastern;
 * CronController runs this command. Hostinger hPanel cron is not used.
 *
 * **Local / dev:** individual entries in App\Console\Kernel also schedule the
 * same commands when `schedule:run` runs every minute.
 *
 * To add a new daily automation: build its artisan command, add it to
 * {@see self::DAILY_AUTOMATIONS} and register it in Kernel.php.
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
        'client-projects:send-past-due-invoice-reminders',
        'client-projects:notify-commencement-eve',
        'client-projects:start-due-today',
        'blog:cleanup-orphans',
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

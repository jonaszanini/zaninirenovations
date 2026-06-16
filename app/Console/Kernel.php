<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // One schedule entry per daily automation (Laravel standard). These
        // only fire when `php artisan schedule:run` runs every minute — hPanel
        // cron does not work on production; cron-job.org → /cron/run instead.
        $dailyAt = static function (Schedule $schedule, string $command) {
            $schedule->command($command)
                ->dailyAt('09:00')
                ->timezone('America/Toronto')
                ->withoutOverlapping(120);
        };

        $dailyAt($schedule, 'client-projects:send-reminders');
        $dailyAt($schedule, 'client-projects:send-past-due-invoice-reminders');
        $dailyAt($schedule, 'client-projects:notify-commencement-eve');
        $dailyAt($schedule, 'client-projects:start-due-today');

        // TEMPORARY cron smoke test: when SMS_CRON_TEST=true the scheduler texts
        // the owner every minute, proving cron is invoking `schedule:run`. Set
        // SMS_CRON_TEST=false once confirmed so the texts stop. (See config/mocean.php.)
        if (config('mocean.cron_test')) {
            $schedule->command('sms:test')
                ->everyMinute()
                ->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}


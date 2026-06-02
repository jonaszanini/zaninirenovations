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
        // NOTE: The daily project automations (reminders, commencement-eve,
        // start-due-today, etc.) are NOT scheduled by time here. They run via
        // App\Console\Commands\RunDailyAutomations (`automations:run-daily`),
        // which the HTTP cron endpoint (GET /cron/run) invokes once a day at
        // 9am EST so every automation fires unconditionally on each hit. Add new
        // daily automations to that command's list, not here.

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


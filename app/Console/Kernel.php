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
        // Daily reminder emails for Quote Pending projects + follow-ups for
        // projects whose Quote Sent 2-day follow-up is due.
        $schedule->command('client-projects:send-reminders')
            ->dailyAt('09:00')
            ->timezone('America/Toronto')
            ->withoutOverlapping();

        // Admin email the calendar day before a project's scheduled start_date.
        $schedule->command('client-projects:notify-commencement-eve')
            ->dailyAt('09:00')
            ->timezone('America/Toronto')
            ->withoutOverlapping();
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


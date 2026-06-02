<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * HTTP entry point for the daily automations, used because the host's system
 * cron is unreliable (e.g. Hostinger shared plans where hPanel cron jobs
 * silently never execute).
 *
 * An external scheduler hits GET /cron/run?token=SECRET once a day at 9am EST.
 * Each hit runs `automations:run-daily`, which fires EVERY daily automation
 * unconditionally (see App\Console\Commands\RunDailyAutomations). `schedule:run`
 * is also invoked afterwards so any time-based scheduler entries still work.
 */
class CronController extends Controller
{
    public function run(Request $request)
    {
        $configured = (string) config('cron.http_token');

        // Endpoint stays hidden (404) unless a token is configured server-side.
        if ($configured === '') {
            abort(404);
        }

        $provided = (string) ($request->query('token', $request->header('X-Cron-Token', '')));

        if (! hash_equals($configured, $provided)) {
            abort(404);
        }

        // Run all daily automations unconditionally on every hit.
        $automationsExit = Artisan::call('automations:run-daily');
        $automationsOutput = trim(Artisan::output());

        // Then let the scheduler fire any remaining time-based entries.
        $scheduleExit = Artisan::call('schedule:run');

        $body = "automations:run-daily completed (exit {$automationsExit}) at ".now()->toIso8601String()."\n";
        if ($automationsOutput !== '') {
            $body .= $automationsOutput."\n";
        }
        $body .= "schedule:run completed (exit {$scheduleExit})\n";

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

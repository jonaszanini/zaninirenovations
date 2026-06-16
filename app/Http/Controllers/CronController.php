<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * HTTP entry point for daily automations on production.
 *
 * Hostinger hPanel cron does not run `schedule:run` reliably, so cron-job.org
 * hits GET /cron/run?token=SECRET once a day at 9am Eastern. Each hit runs
 * `automations:run-daily`, which fires every command in
 * App\Console\Commands\RunDailyAutomations unconditionally.
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

        // Run all daily automations unconditionally on every hit (HTTP fallback).
        $automationsExit = Artisan::call('automations:run-daily');
        $automationsOutput = trim(Artisan::output());

        $body = "automations:run-daily completed (exit {$automationsExit}) at ".now()->toIso8601String()."\n";
        if ($automationsOutput !== '') {
            $body .= $automationsOutput."\n";
        }

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

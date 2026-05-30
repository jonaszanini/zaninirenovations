<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * HTTP entry point for the Laravel scheduler, used as a fallback when the
 * host's system cron is unreliable (e.g. Hostinger shared plans where hPanel
 * cron jobs silently never execute).
 *
 * An external scheduler (cron-job.org, UptimeRobot, etc.) hits
 * GET /cron/run?token=SECRET every few minutes. Each hit runs
 * `schedule:run`, which fires whatever scheduled commands are due. The actual
 * jobs are still defined once in App\Console\Kernel — this only triggers them.
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

        $exitCode = Artisan::call('schedule:run');

        return response(
            "schedule:run completed (exit {$exitCode}) at ".now()->toIso8601String()."\n",
            200
        )->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

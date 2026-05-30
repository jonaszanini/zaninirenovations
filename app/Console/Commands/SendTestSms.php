<?php

namespace App\Console\Commands;

use App\Models\ClientProject;
use App\Services\MoceanSmsService;
use Illuminate\Console\Command;

/**
 * One-off diagnostic SMS. Used to confirm that:
 *   1. The Mocean gateway credentials work, and
 *   2. The Hostinger cron is actually invoking `php artisan schedule:run`
 *      (when scheduled every minute via SMS_CRON_TEST — see App\Console\Kernel).
 *
 * Run manually:   php artisan sms:test
 * Custom number:  php artisan sms:test --to="+1 613 555 0123"
 * Custom message: php artisan sms:test --message="Hello from prod"
 */
class SendTestSms extends Command
{
    protected $signature = 'sms:test
        {--to= : Destination phone in any format. Defaults to SMS_TEST_TO / config(mocean.test_to).}
        {--message= : Custom message body. Defaults to a timestamped diagnostic line.}';

    protected $description = 'Send a test SMS to verify the Mocean gateway and the scheduler/cron are working.';

    public function handle(MoceanSmsService $sms): int
    {
        $rawTo = (string) ($this->option('to') ?: config('mocean.test_to'));
        $to = ClientProject::phoneDigitsForSms($rawTo);

        if ($to === null) {
            $this->error("Could not parse a valid phone number from: '{$rawTo}'.");

            return self::FAILURE;
        }

        if (! $sms->isConfigured()) {
            $this->error('Mocean is not configured (missing MOCEAN_API_TOKEN). SMS not sent.');

            return self::FAILURE;
        }

        $message = (string) ($this->option('message') ?: sprintf(
            'Zanini Renovations: scheduler/cron test SMS — fired %s.',
            now()->timezone((string) config('app.timezone'))->format('M j, Y g:i A T')
        ));

        $result = $sms->send($to, $message);

        if ($result['ok']) {
            $this->info("Test SMS sent to {$to}. ({$result['detail']})");

            return self::SUCCESS;
        }

        $this->error("Test SMS failed: {$result['detail']}");

        return self::FAILURE;
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mocean SMS (https://moceanapi.com) — commencement reminders, etc.
    |--------------------------------------------------------------------------
    | Set MOCEAN_API_TOKEN (and optional MOCEAN_FROM) to enable SMS.
    | MOCEAN_FROM is the sender shown on the client's phone: alphanumeric
    | (e.g. ZANINI) or a Mocean number as digits with country code
    | (e.g. 18559154845). Commencement reminders use each project's
    | client_phone (10-digit NANP gets a leading 1 for Mocean mocean-to).
    */

    'send_url' => env('MOCEAN_SEND_URL', 'https://rest.moceanapi.com/rest/2/sms'),

    'api_token' => env('MOCEAN_API_TOKEN'),

    'from' => env('MOCEAN_FROM', 'MOCEAN'),

    /*
    |--------------------------------------------------------------------------
    | Scheduler / cron smoke test (temporary diagnostic)
    |--------------------------------------------------------------------------
    | `test_to` is the default recipient for the `sms:test` command.
    | When `cron_test` is true, the scheduler fires `sms:test` every minute
    | so you can confirm cron is invoking `php artisan schedule:run`. Turn it
    | OFF (SMS_CRON_TEST=false) once verified so you stop receiving texts.
    | If config is cached on prod, re-run `php artisan config:cache` after
    | toggling SMS_CRON_TEST so the change takes effect.
    */

    'test_to' => env('SMS_TEST_TO', '+1 647 834 7570'),

    'cron_test' => (bool) env('SMS_CRON_TEST', false),

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External HTTP cron trigger
    |--------------------------------------------------------------------------
    | Fallback for hosts whose system cron is unreliable. When CRON_HTTP_TOKEN
    | is set, GET /cron/run?token=THE_TOKEN runs `php artisan schedule:run`
    | over HTTP. Point a free external scheduler (e.g. cron-job.org) at that
    | URL every few minutes. If the token is blank, the endpoint 404s so it is
    | never exposed unprotected.
    */

    'http_token' => env('CRON_HTTP_TOKEN'),

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External HTTP cron trigger (production)
    |--------------------------------------------------------------------------
    | hPanel system cron is unreliable on Hostinger; production uses
    | cron-job.org to GET /cron/run?token=THE_TOKEN once daily at 9am Eastern.
    | That runs `automations:run-daily` (all daily commands). If the token is
    | blank, the endpoint 404s so it is never exposed unprotected.
    */

    'http_token' => env('CRON_HTTP_TOKEN'),

];

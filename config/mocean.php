<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mocean SMS (https://moceanapi.com) — commencement reminders, etc.
    |--------------------------------------------------------------------------
    | Set MOCEAN_API_TOKEN (and optional MOCEAN_FROM) to enable SMS.
    | Commencement reminders use each project's client_phone (10-digit NANP
    | gets a leading 1 for Mocean mocean-to).
    */

    'send_url' => env('MOCEAN_SEND_URL', 'https://rest.moceanapi.com/rest/2/sms'),

    'api_token' => env('MOCEAN_API_TOKEN'),

    'from' => env('MOCEAN_FROM', 'MOCEAN'),

];

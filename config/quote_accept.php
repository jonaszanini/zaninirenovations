<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quote-accept automation API
    |--------------------------------------------------------------------------
    | Bearer token for POST /api/quotations/accept (Gmail bot / automation).
    | Leave blank to reject every request. Compare with hash_equals.
    */

    'api_token' => env('QUOTE_ACCEPT_API_TOKEN'),

];

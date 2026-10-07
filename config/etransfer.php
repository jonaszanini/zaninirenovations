<?php

$etransferToken = env('ETRANSFER_API_TOKEN');
$quoteAcceptToken = env('QUOTE_ACCEPT_API_TOKEN');

return [

    /*
    |--------------------------------------------------------------------------
    | Incoming e-transfer automation API
    |--------------------------------------------------------------------------
    | Bearer token for POST /api/etransfers (Gmail bot / inbox watcher).
    | ETRANSFER_API_TOKEN is preferred. If it is unset or blank, the quote
    | acceptance token is reused so one secret can drive both automations.
    | Leave both blank to reject every request. Compare with hash_equals.
    */

    'api_token' => (is_string($etransferToken) && $etransferToken !== '')
        ? $etransferToken
        : $quoteAcceptToken,

];

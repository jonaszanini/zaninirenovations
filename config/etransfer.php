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

    /*
    |--------------------------------------------------------------------------
    | Minimum score for a confident invoice suggestion
    |--------------------------------------------------------------------------
    | Recency alone scores at most 10. Anything at or below this threshold is
    | listed as "no confident match" instead of becoming the top suggestion.
    */

    'min_match_score' => max(1, (int) env('ETRANSFER_MIN_MATCH_SCORE', 15)),

    /*
    |--------------------------------------------------------------------------
    | Own deposit / business addresses (never the Interac sender)
    |--------------------------------------------------------------------------
    | Auto-deposit emails include the destination address ("deposited into
    | your account at Scotiabank at zaninirenovations@gmail.com"). That is
    | our inbox, not the payer. Comma-separated extras via ETRANSFER_OWN_EMAILS.
    */

    'own_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ETRANSFER_OWN_EMAILS', ''))
    ))),

];

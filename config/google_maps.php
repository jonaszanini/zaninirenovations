<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Maps Platform (Places + Distance Matrix)
    |--------------------------------------------------------------------------
    |
    | Use two keys for best results on shared hosting:
    |
    | GOOGLE_MAPS_API_KEY — browser (HTTP referrer: your admin domain + localhost)
    |   Maps JavaScript API, Places API (New)
    |
    | GOOGLE_MAPS_SERVER_API_KEY — REQUIRED for admin travel estimates (Distance Matrix).
    |   Application restriction: your server's outbound IP. API restriction: Distance Matrix API.
    |   If your browser key has no referrer/IP restriction (dev only), you may set both env vars
    |   to the same key value temporarily — production should use separate keys.
    |
    */
    'api_key' => env('GOOGLE_MAPS_API_KEY'),

    'server_api_key' => env('GOOGLE_MAPS_SERVER_API_KEY'),

    /** Cache successful Distance Matrix responses (seconds). Failures are not cached. */
    'distance_cache_ttl' => (int) env('GOOGLE_MAPS_DISTANCE_CACHE_TTL', 86400),
];

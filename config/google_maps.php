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
    | GOOGLE_MAPS_SERVER_API_KEY — server (IP: your Hostinger outbound IP)
    |   Distance Matrix API only
    |
    | If GOOGLE_MAPS_SERVER_API_KEY is omitted, the browser key is used for both
    | (only works when that key has no application restriction or allows server IP).
    |
    */
    'api_key' => env('GOOGLE_MAPS_API_KEY'),

    'server_api_key' => env('GOOGLE_MAPS_SERVER_API_KEY'),

    /** Cache successful Distance Matrix responses (seconds). Failures are not cached. */
    'distance_cache_ttl' => (int) env('GOOGLE_MAPS_DISTANCE_CACHE_TTL', 86400),
];

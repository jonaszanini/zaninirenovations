<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Maps Platform (Places + Distance Matrix)
    |--------------------------------------------------------------------------
    |
    | Create an API key in Google Cloud Console and enable:
    | - Maps JavaScript API (Places Autocomplete in admin forms)
    | - Distance Matrix API (server-side travel estimates)
    |
    | Restrict the key by HTTP referrer (admin domain) and IP (server) as needed.
    |
    */
    'api_key' => env('GOOGLE_MAPS_API_KEY'),

    /** Cache Distance Matrix responses (seconds). */
    'distance_cache_ttl' => (int) env('GOOGLE_MAPS_DISTANCE_CACHE_TTL', 86400),
];

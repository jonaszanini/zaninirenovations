<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Material price sync
    |--------------------------------------------------------------------------
    |
    | Home Depot CA store pricing (Carleton Place = store 7263):
    |
    | - Production admin sync: set MATERIALS_SCRAPER_SERPAPI_KEY (SerpApi Home Depot Search).
    | - Local bulk sync: run `php artisan materials:sync-prices` on your PC (FBT API, no SerpApi credits).
    |
    */

    'scraper' => [
        // http | browsershot — local FBT fallback uses Browsershot when Node + Chrome are available.
        'fetcher' => env('MATERIALS_SCRAPER_FETCHER', 'http'),

        // Local only: retry FBT via headless Chrome after HTTP 403.
        'store_api_use_browsershot' => (bool) env('MATERIALS_SCRAPER_STORE_API_BROWSERSHOT', false),

        // SerpApi — used for admin sync on production when set. Free tier: 250 searches/month.
        'serpapi_key' => env('MATERIALS_SCRAPER_SERPAPI_KEY'),
        'serpapi_no_cache' => (bool) env('MATERIALS_SCRAPER_SERPAPI_NO_CACHE', false),

        'user_agent' => env(
            'MATERIALS_SCRAPER_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),

        // Page fetch timeout (HTML fallback and Browsershot).
        'timeout_seconds' => (int) env('MATERIALS_SCRAPER_TIMEOUT', 30),

        // Local FBT store-price API (fast but blocked on datacenter IPs).
        'api_timeout_seconds' => (int) env('MATERIALS_SCRAPER_API_TIMEOUT', 8),
        'api_retries' => (int) env('MATERIALS_SCRAPER_API_RETRIES', 4),
        'api_retry_delay_ms' => (int) env('MATERIALS_SCRAPER_API_RETRY_DELAY_MS', 200),

        'delay_after_load_ms' => (int) env('MATERIALS_SCRAPER_DELAY_AFTER_LOAD_MS', 5000),

        // Pause between product requests during local artisan sync.
        'delay_ms' => (int) env('MATERIALS_SCRAPER_DELAY_MS', 1500),

        'homedepot_store_id' => env('MATERIALS_SCRAPER_HD_STORE_ID'),
    ],

];

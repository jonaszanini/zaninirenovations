<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Material price sync (local Artisan command)
    |--------------------------------------------------------------------------
    |
    | Run `php artisan materials:sync-prices` on your PC — not on Hostinger.
    | Home Depot CA uses the store price API when MATERIALS_SCRAPER_HD_STORE_ID is set.
    |
    */

    'scraper' => [
        // http | browsershot — browsershot needs: composer require spatie/browsershot + Chrome
        'fetcher' => env('MATERIALS_SCRAPER_FETCHER', 'http'),

        'user_agent' => env(
            'MATERIALS_SCRAPER_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),

        // Page fetch timeout (HTTP or Browsershot HTML fallback).
        'timeout_seconds' => (int) env('MATERIALS_SCRAPER_TIMEOUT', 30),

        // Home Depot store-price API (FBT). Responds in ~300ms or hangs — keep timeout short.
        'api_timeout_seconds' => (int) env('MATERIALS_SCRAPER_API_TIMEOUT', 5),
        'api_retries' => (int) env('MATERIALS_SCRAPER_API_RETRIES', 3),
        'api_retry_delay_ms' => (int) env('MATERIALS_SCRAPER_API_RETRY_DELAY_MS', 100),

        // After page load (Browsershot): wait for retailer price JS to render.
        'delay_after_load_ms' => (int) env('MATERIALS_SCRAPER_DELAY_AFTER_LOAD_MS', 5000),

        // Pause between product requests (anti-bot courtesy).
        'delay_ms' => (int) env('MATERIALS_SCRAPER_DELAY_MS', 1500),

        // Home Depot CA store ID for localized shelf pricing (Carleton Place = 7263).
        // Without this, hdca-state returns an unlocalized list price that can be wrong.
        'homedepot_store_id' => env('MATERIALS_SCRAPER_HD_STORE_ID'),
    ],

];

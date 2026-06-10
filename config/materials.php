<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Material price sync (local Artisan command)
    |--------------------------------------------------------------------------
    |
    | Run `php artisan materials:sync-prices` on your PC — not on Hostinger.
    | Requires a headless browser for JavaScript-heavy retailer pages.
    |
    */

    'scraper' => [
        // http | browsershot — browsershot needs: composer require spatie/browsershot + Chrome
        'fetcher' => env('MATERIALS_SCRAPER_FETCHER', 'http'),

        'user_agent' => env(
            'MATERIALS_SCRAPER_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),

        'timeout_seconds' => (int) env('MATERIALS_SCRAPER_TIMEOUT', 60),

        // Pause between product requests (anti-bot courtesy).
        'delay_ms' => (int) env('MATERIALS_SCRAPER_DELAY_MS', 1500),
    ],

    'ollama' => [
        'enabled' => (bool) env('OLLAMA_ENABLED', true),
        'base_url' => rtrim((string) env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/'),
        'model' => (string) env('OLLAMA_MODEL', 'qwen3:14b'),
        'timeout_seconds' => (int) env('OLLAMA_TIMEOUT', 120),
    ],

];

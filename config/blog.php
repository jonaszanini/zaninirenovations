<?php

return [
    'author_name' => env('BLOG_AUTHOR_NAME', 'Jonas Zanini'),

    /** Words per minute for estimated read time on public pages. */
    'words_per_minute' => (int) env('BLOG_WORDS_PER_MINUTE', 200),

    /**
     * Comment anti-bot: math | recaptcha_v2 | recaptcha_v3
     * Math works without API keys; set RECAPTCHA_SITE_KEY + RECAPTCHA_SECRET_KEY for reCAPTCHA.
     */
    'comment_captcha' => env('BLOG_COMMENT_CAPTCHA', 'math'),

    'recaptcha_site_key' => env('RECAPTCHA_SITE_KEY', ''),
    'recaptcha_secret_key' => env('RECAPTCHA_SECRET_KEY', ''),

    /** Optional end-of-post CTA (stored now; public templates use it in Phase 3). */
    'cta_services' => [
        'decks' => 'Decks',
        'fences' => 'Fences',
        'basements' => 'Basements',
        'flooring' => 'Flooring',
        'trim' => 'Interior trim',
        'built-ins' => 'Custom built-ins',
        'drywall' => 'Drywall',
        'accent-walls' => 'Accent walls',
        'staircases' => 'Staircases',
        'contact' => 'Contact / quote',
    ],
];

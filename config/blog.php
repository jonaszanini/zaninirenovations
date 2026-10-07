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

    /** Optional in-post CTA. Keys match the admin cta_service select. */
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

    'phone_display' => '(647) 834-7570',
    'phone_tel' => '+16478347570',
    'estimate_url' => '/#contact-location',
    'service_url' => '/#services',

    'cta_copy' => [
        'generic' => [
            'title' => 'Planning a renovation in Ottawa or Gatineau?',
            'text' => 'Zanini Renovations can walk the site and send a free written estimate.',
        ],
        'decks' => [
            'title' => 'Need a deck in Ottawa or Gatineau?',
            'text' => 'We design and build decks that hold up through Ontario winters. Ask for a free estimate.',
        ],
        'fences' => [
            'title' => 'Looking for a new fence?',
            'text' => 'Privacy and yard fences built for Ottawa and Gatineau weather. Get a free estimate.',
        ],
        'basements' => [
            'title' => 'Framing or finishing a basement?',
            'text' => 'From layout to trim, we handle basement work in Ottawa and Gatineau. Free estimate.',
        ],
        'flooring' => [
            'title' => 'Planning new floors?',
            'text' => 'Hardwood, vinyl, and other flooring installed in Ottawa and Gatineau. Free estimate.',
        ],
        'trim' => [
            'title' => 'Interior trim and moulding?',
            'text' => 'Clean trim, casings, and baseboards for homes in Ottawa and Gatineau. Free estimate.',
        ],
        'built-ins' => [
            'title' => 'Want custom built-ins?',
            'text' => 'Shelving, benches, and millwork built to fit. Serving Ottawa and Gatineau.',
        ],
        'drywall' => [
            'title' => 'Need drywall work?',
            'text' => 'Hanging, taping, and finishing for renovations in Ottawa and Gatineau. Free estimate.',
        ],
        'accent-walls' => [
            'title' => 'Thinking about an accent wall?',
            'text' => 'Feature walls and finishes for Ottawa and Gatineau homes. Ask for a free estimate.',
        ],
        'staircases' => [
            'title' => 'Stairs that need an update?',
            'text' => 'Treads, railings, and staircase work in Ottawa and Gatineau. Free estimate.',
        ],
        'contact' => [
            'title' => 'Ready to talk through your project?',
            'text' => 'Tell us about the work in Ottawa or Gatineau and we will send a free estimate.',
        ],
    ],
];

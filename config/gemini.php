<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image-preview'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 120),
    'image_timeout_seconds' => (int) env('GEMINI_IMAGE_TIMEOUT_SECONDS', 180),
    /** Inline image data limit for Gemini generateContent (bytes). Larger uploads use the File API. */
    'inline_image_max_bytes' => (int) env('GEMINI_INLINE_IMAGE_MAX_BYTES', 7 * 1024 * 1024),
];

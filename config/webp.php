<?php

return [

    /*
    |--------------------------------------------------------------------------
    | cwebp binary (optional)
    |--------------------------------------------------------------------------
    |
    | Full path to Google's cwebp.exe (Windows) or cwebp (Linux/macOS).
    | Used when PHP GD has no imagewebp and Imagick is unavailable.
    |
    | Download: https://developers.google.com/speed/webp/download
    | Example (Windows): C:\webp\libwebp-1.4.0-windows-x64\bin\cwebp.exe
    |
    | If unset, the app also looks for base_path('bin/cwebp.exe') or 'cwebp' on PATH.
    |
    */
    'cwebp_binary' => env('CWEBP_PATH'),

    /*
    | Optional path to dwebp (WebP → PNG). Used to rotate images when GD cannot read WebP.
    | Usually next to cwebp in the same libwebp zip; auto-detected from cwebp's directory.
    */
    'dwebp_binary' => env('DWEBP_PATH'),

    /*
    | Optional path to ffmpeg (with libwebp). Rotates WebP without PHP WebP or dwebp.
    */
    'ffmpeg_binary' => env('FFMPEG_PATH'),

    /*
    | Longest side in pixels (width or height). Larger uploads are scaled down before WebP
    | encoding so portfolio images stay small and fast to load (e.g. phone photos).
    */
    'max_edge_px' => (int) env('WEBP_MAX_EDGE', 1920),

    /*
    | WebP quality 0–100. Lower = smaller files (try 72–80 for photos).
    */
    'quality' => (int) env('WEBP_QUALITY', 78),

    /*
    | cwebp encoding effort 0–6. Higher = slower encoding, often smaller files.
    */
    'cwebp_method' => (int) env('WEBP_CWEBP_METHOD', 6),

];

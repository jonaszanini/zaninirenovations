<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ImageMagick binary (optional)
    |--------------------------------------------------------------------------
    |
    | Used to trim Gemini letterboxing and crop to the requested aspect ratio
    | when PHP GD / Imagick are unavailable (common on shared hosting).
    |
    | Example (Windows): C:\Program Files\ImageMagick-7.1.1-Q16-HDRI\magick.exe
    |
    */
    'magick_binary' => env('MAGICK_PATH'),

];

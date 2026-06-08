<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nano Banana 2 (Gemini 3.1 Flash Image) — allowed request parameters
    |--------------------------------------------------------------------------
    |
    | aspectRatio and imageSize map directly to generationConfig.imageConfig
    | in the Gemini generateContent API. imageSize must use uppercase K.
    |
    */

    'default_aspect_ratio' => '16:9',

    'default_image_size' => '1K',

    'aspect_ratios' => [
        '1:1',
        '16:9',
        '9:16',
        '4:3',
    ],

    // Long-edge pixel targets per imageSize token (approximate output dimensions).
    'size_long_edge_px' => [
        '512' => 512,
        '1K' => 1024,
        '2K' => 2048,
        '4K' => 4096,
    ],

    'image_sizes' => [
        '512',
        '1K',
        '2K',
        '4K',
    ],

];

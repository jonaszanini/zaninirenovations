<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Room Area Drawing Engine
    |--------------------------------------------------------------------------
    |
    | Supported: "canvas" (legacy Konva canvas), "openplan3d"
    | Default: "openplan3d" (switched in PR 5).
    |
    */
    'engine' => env('ROOM_AREA_ENGINE', 'openplan3d'),
];

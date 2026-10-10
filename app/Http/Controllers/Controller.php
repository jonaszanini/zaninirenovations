<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsReplaySubmit;

abstract class Controller
{
    use RejectsReplaySubmit;
}


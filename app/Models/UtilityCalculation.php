<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UtilityCalculation extends Model
{
    use HasFactory;

    public const TOOL_PILLAR_SPINDLE = 'pillar_spindle';

    public const TOOL_LUMBER_BOARD = 'lumber_board';

    protected $fillable = [
        'tool',
        'name',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInstruction extends Model
{
    public const KEY_CONTRACT = 'contract';

    protected $fillable = [
        'key',
        'name',
        'instruction',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public static function forKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }
}

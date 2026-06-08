<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInstruction extends Model
{
    public const KEY_CONTRACT = 'contract';

    public const KEY_BLOG_POST = 'blog_post';

    public const KEY_IMAGE_MODIFY_PROMPT = 'image_modify_prompt';

    public const KEY_PORTFOLIO_DESCRIPTION = 'portfolio_description';

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

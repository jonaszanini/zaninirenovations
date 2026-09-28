<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInstruction extends Model
{
    public const KEY_CONTRACT = 'contract';

    public const KEY_BLOG_POST = 'blog_post';

    public const KEY_IMAGE_MODIFY_PROMPT = 'image_modify_prompt';

    public const KEY_PORTFOLIO_PHOTO_PROMPT = 'portfolio_photo_prompt';

    public const KEY_PORTFOLIO_DESCRIPTION = 'portfolio_description';

    public const KEY_QUOTATION_WORK_DESCRIPTION = 'quotation_work_description';

    public const KEY_QUOTATION_WORK_DESCRIPTION_FROM_QUOTE = 'quotation_work_description_from_quote';

    public const KEY_MATERIAL_SHORT_NAME = 'material_short_name';

    public const KEY_QUOTATION_CLIENT_EMAIL_MESSAGE = 'quotation_client_email_message';

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

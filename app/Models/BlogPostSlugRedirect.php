<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BlogPostSlugRedirect extends Model
{
    protected $fillable = [
        'blog_post_id',
        'slug',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public static function recordChange(BlogPost $post, string $previousSlug, string $newSlug): void
    {
        $previousSlug = trim($previousSlug);
        $newSlug = trim($newSlug);

        if ($previousSlug === '' || $previousSlug === $newSlug) {
            return;
        }

        DB::transaction(function () use ($post, $previousSlug, $newSlug) {
            static::query()
                ->where('blog_post_id', $post->id)
                ->where('slug', $newSlug)
                ->delete();

            static::query()->updateOrCreate(
                ['slug' => $previousSlug],
                ['blog_post_id' => $post->id]
            );
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AiGeneratedImage extends Model
{
    protected $fillable = [
        'prompt',
        'image_path',
    ];

    public function imageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        $url = Storage::disk('public')->url($this->image_path);
        $absolute = Storage::disk('public')->path($this->image_path);
        if (is_file($absolute)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.filemtime($absolute);
        } elseif ($this->created_at !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$this->created_at->getTimestamp();
        }

        return $url;
    }

    public function displayCreatedAt(): ?string
    {
        return $this->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
    }
}

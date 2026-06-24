<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TileProduct extends Model
{
    protected $fillable = [
        'name',
        'url',
        'image_path',
        'length_inches',
        'width_inches',
        'thickness_inches',
        'cost_per_sqft',
        'sort_order',
        'is_active',
    ];
    use HasFactory;

    public function publicImageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($this->image_path)) {
            return null;
        }

        return $disk->url($this->image_path);
    }
}

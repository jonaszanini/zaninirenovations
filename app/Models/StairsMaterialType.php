<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StairsMaterialType extends Model
{
    public const NOSING_INCLUDED = 'included';

    public const NOSING_CHOICE = 'choice';

    public const NOSING_LUXURY_VINYL_FLUSH = 'luxury_vinyl_flush';

    public const NOSING_ALUMINUM_DROP = 'aluminum_drop';

    public const NOSING_VINYL = 'vinyl_nosing';

    public const NOSING_RUBBER = 'rubber_nosing';

    protected $fillable = [
        'key',
        'name',
        'nosing_mode',
        'carpet_removal_per_step',
        'photo_path',
        'pricing',
        'sort_order',
    ];

    protected $casts = [
        'carpet_removal_per_step' => 'decimal:2',
        'pricing' => 'array',
        'sort_order' => 'integer',
    ];

    public static function nosingChoiceLabels(): array
    {
        return [
            self::NOSING_LUXURY_VINYL_FLUSH => 'Luxury Vinyl Flush Stair Nose',
            self::NOSING_ALUMINUM_DROP => 'Aluminum Drop Stair Nosing',
            self::NOSING_VINYL => 'Vinyl Stair Nosing',
            self::NOSING_RUBBER => 'Rubber Stair Nosing',
        ];
    }

    public static function defaultPricingArray(): array
    {
        return [
            'no_open' => [
                'standard' => 0.0,
                'winder' => 0.0,
                'double_landing' => 0.0,
                'single_landing' => 0.0,
            ],
            'one_open' => [
                'standard' => 0.0,
                'winder' => 0.0,
                'double_landing' => 0.0,
                'single_landing' => 0.0,
            ],
            'two_open' => [
                'standard' => 0.0,
            ],
            'nosing_prices' => [
                self::NOSING_LUXURY_VINYL_FLUSH => 0.0,
                self::NOSING_ALUMINUM_DROP => 0.0,
                self::NOSING_VINYL => 0.0,
                self::NOSING_RUBBER => 0.0,
            ],
        ];
    }

    public function mergedPricing(): array
    {
        return array_replace_recursive(self::defaultPricingArray(), is_array($this->pricing) ? $this->pricing : []);
    }

    public function photoUrl(): ?string
    {
        if (! filled($this->photo_path)) {
            return null;
        }
        if (! Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }
}

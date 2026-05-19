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

    public const KEY_VINYL_WITHOUT_NOSING = 'vinyl_without_nosing';

    protected $fillable = [
        'key',
        'name',
        'nosing_mode',
        'photo_path',
        'pricing',
        'sort_order',
    ];

    protected $casts = [
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

    /** Vinyl without nosing is quoted with no-open-side rates only (no one/two open variants). */
    public function supportsOpenSidePricing(): bool
    {
        return $this->key !== self::KEY_VINYL_WITHOUT_NOSING;
    }

    /**
     * Lines for admin UI (price config + quotation material cards). Database `name` stays full for exports/PDFs.
     *
     * @return list<string>
     */
    public function adminDisplayLines(): array
    {
        return match ($this->key) {
            'unfinished_solid_oak_tread' => ['Unfinished Solid', 'Oak Stair Tread'],
            'unfinished_oak_tread_cape' => ['Unfinished Oak', 'Stair Tread cape'],
            default => [$this->name],
        };
    }

    /**
     * URL for the material photo in admin (price config + quotation calculator).
     * Uses an authenticated route so images load even if public/storage is not linked to storage/app/public.
     */
    public function photoUrl(): ?string
    {
        if (! filled($this->photo_path)) {
            return null;
        }

        $path = str_replace('\\', '/', (string) $this->photo_path);
        $path = ltrim($path, '/');

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (! app()->runningInConsole()) {
            return route('admin.quotations.stairs.material-photo', $this);
        }

        return asset('storage/'.$path);
    }
}

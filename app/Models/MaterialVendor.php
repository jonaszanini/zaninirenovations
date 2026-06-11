<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MaterialVendor extends Model
{
    public const SLUG_HOME_DEPOT = 'home-depot';

    public const SLUG_AMAZON = 'amazon';

    protected $fillable = [
        'name',
        'slug',
        'is_default_for_homedepot',
        'sort_order',
    ];

    protected $casts = [
        'is_default_for_homedepot' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'vendor_id');
    }

    public static function homeDepot(): ?self
    {
        return static::query()->where('slug', self::SLUG_HOME_DEPOT)->first();
    }

    public static function homeDepotId(): ?int
    {
        $id = static::query()->where('slug', self::SLUG_HOME_DEPOT)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function amazon(): ?self
    {
        return static::query()->where('slug', self::SLUG_AMAZON)->first();
    }

    public static function amazonId(): ?int
    {
        $id = static::query()->where('slug', self::SLUG_AMAZON)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function defaultForHomeDepotImport(): ?self
    {
        return static::query()
            ->where('is_default_for_homedepot', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first()
            ?? static::homeDepot();
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'vendor';
        }

        $slug = $base;
        $n = 2;

        while (static::query()
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }
}

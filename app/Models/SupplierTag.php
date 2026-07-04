<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class SupplierTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function supplierSites(): BelongsToMany
    {
        return $this->belongsToMany(SupplierSite::class, 'supplier_site_supplier_tag');
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'tag';
        $candidate = $slug;
        $n = 2;

        while (static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$n;
            $n++;
        }

        return $candidate;
    }

    /**
     * @return list<int>
     */
    public static function syncFromNames(array $names): array
    {
        $ids = [];
        foreach ($names as $raw) {
            $name = trim((string) $raw);
            if ($name === '') {
                continue;
            }
            $tag = static::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if (! $tag) {
                $tag = static::create([
                    'name' => $name,
                    'slug' => static::uniqueSlug($name),
                ]);
            }
            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    public static function parseNamesFromInput(?string $input): array
    {
        if ($input === null || trim($input) === '') {
            return [];
        }

        $seen = [];
        $names = [];
        foreach (preg_split('/\s*,\s*/', $input) ?: [] as $part) {
            $name = trim($part);
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }
}

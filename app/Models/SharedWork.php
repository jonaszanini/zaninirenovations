<?php

namespace App\Models;

use App\Services\SharedWorkCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SharedWork extends Model
{
    public const KIND_PER_HOUR = 'per_hour';

    public const KIND_HOURS_EACH = 'hours_each';

    public const KIND_FLAT = 'flat_per_unit';

    public const MONEY_MAX = 999999.99;

    public const LABEL_MAX = 160;

    protected $fillable = [
        'key',
        'group_key',
        'label',
        'label_normalized',
        'unit',
        'labor_kind',
        'rate',
        'flat_rate',
        'catalog_material_id',
        'material_name',
        'material_unit',
        'material_unit_price',
        'consumers',
        'sort_order',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'flat_rate' => 'decimal:4',
        'material_unit_price' => 'decimal:4',
        'consumers' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $work) {
            $work->label = self::displayLabel((string) $work->label);
            if (Schema::hasColumn($work->getTable(), 'label_normalized')) {
                $work->label_normalized = self::normalizeLabel($work->label);
            }
        });
    }

    public function catalogMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'catalog_material_id');
    }

    public function isLinearFoot(): bool
    {
        return $this->unit === 'lf';
    }

    public function isSquareFoot(): bool
    {
        return $this->unit === 'sf';
    }

    public function isPricedPerArea(): bool
    {
        return $this->isLinearFoot() || $this->isSquareFoot();
    }

    public function isCustom(): bool
    {
        return ! SharedWorkCatalog::isCanonicalKey((string) $this->key);
    }

    /**
     * Hours-per-piece or units-per-hour label for the Cost (old Rate) field.
     */
    public function costUnitLabel(): string
    {
        return self::costUnitLabelFor((string) $this->unit, (string) $this->labor_kind);
    }

    public static function costUnitLabelFor(string $unit, ?string $kind = null): string
    {
        $unit = strtolower(trim($unit));
        if ($unit === '') {
            $unit = 'each';
        }
        if ($kind === self::KIND_HOURS_EACH || in_array($unit, ['each', 'opening'], true)) {
            return $unit === 'each' ? 'hrs per each' : 'hrs per '.$unit;
        }
        if (in_array($unit, ['lf', 'sf'], true) || $kind === self::KIND_PER_HOUR) {
            return $unit.' per hour';
        }

        return 'hrs per '.$unit;
    }

    /**
     * Stable lookup key from a display name. Never reused; rename must not change it.
     * Custom rows are prefixed with custom_ so they cannot collide with built-in keys.
     */
    public static function allocateUniqueKey(string $label): string
    {
        $base = self::slugFromLabel($label);
        $base = (string) preg_replace('/^custom_+/', '', $base);
        if ($base === '') {
            $base = 'item';
        }
        $base = substr($base, 0, 50);
        $key = 'custom_'.$base;
        if (strlen($key) > 64) {
            $key = substr($key, 0, 64);
        }
        $candidate = $key;
        $n = 2;
        while (static::query()->where('key', $candidate)->exists()) {
            $suffix = '_'.$n;
            $candidate = substr($key, 0, max(1, 64 - strlen($suffix))).$suffix;
            $n++;
        }

        return $candidate;
    }

    public static function slugFromLabel(string $label): string
    {
        $s = trim($label);
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $converted = $transliterator->transliterate($s);
                if (is_string($converted) && $converted !== '') {
                    $s = $converted;
                }
            }
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($converted) && $converted !== '') {
                $s = $converted;
            }
        }
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $s), '_'));

        return $base !== '' ? $base : 'item';
    }

    public static function displayLabel(string $label): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $label));
        $s = (string) preg_replace('/[\s.,;:!?…]+$/u', '', $s);

        return $s;
    }

    public static function normalizeLabel(string $label): string
    {
        return mb_strtolower(self::displayLabel($label));
    }
}

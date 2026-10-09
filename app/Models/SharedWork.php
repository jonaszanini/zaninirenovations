<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedWork extends Model
{
    public const KIND_PER_HOUR = 'per_hour';

    public const KIND_HOURS_EACH = 'hours_each';

    public const KIND_FLAT = 'flat_per_unit';

    protected $fillable = [
        'key',
        'group_key',
        'label',
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

    /**
     * Stable lookup key from a display name. Never reused; rename must not change it.
     */
    public static function allocateUniqueKey(string $label): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_'));
        if ($base === '') {
            $base = 'item';
        }
        $base = substr($base, 0, 50);
        $key = $base;
        $n = 2;
        while (static::query()->where('key', $key)->exists()) {
            $suffix = '_'.$n;
            $key = substr($base, 0, max(1, 64 - strlen($suffix))).$suffix;
            $n++;
        }

        return $key;
    }

    public static function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }
}

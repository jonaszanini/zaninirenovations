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
    public static function allocateUniqueKey(string $label, int $jitter = 0): string
    {
        $base = self::slugFromLabel($label);
        $base = (string) preg_replace('/^custom_+/', '', $base);
        if ($base === '') {
            $base = 'item';
        }
        if ($jitter > 0) {
            $base = substr($base, 0, 36).'_'.$jitter.'_'.bin2hex(random_bytes(2));
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

    public static function slugFromLabel(string $label, bool $forceFallback = false): string
    {
        $s = self::asciiFold(trim($label), $forceFallback);
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $s), '_'));

        return $base !== '' ? $base : 'item';
    }

    /**
     * Fold accents to ASCII. Prefer intl Transliterator; fall back to iconv
     * and a static Latin map when intl is missing (or $forceFallback).
     * Apostrophes, quotes, and combining marks are stripped on both paths
     * so slugs stay identical (Rock'n → rockn, leftover marks never become _).
     */
    public static function asciiFold(string $s, bool $forceFallback = false): string
    {
        if (! $forceFallback && class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $converted = $transliterator->transliterate($s);
                if (is_string($converted) && $converted !== '') {
                    return self::stripFoldMarks($converted);
                }
            }
        }
        $s = self::stripFoldMarks(strtr($s, self::asciiFoldMap()));
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($converted) && $converted !== '') {
                $s = $converted;
            }
        }

        return self::stripFoldMarks($s);
    }

    /**
     * Drop apostrophes/quotes and combining marks so TRANSLIT leftovers
     * cannot become `_` in the slug (Café → cafe, Rock'n → rockn).
     */
    private static function stripFoldMarks(string $s): string
    {
        $s = str_replace([
            "'", '`', '^', '~', '"',
            "\u{00B4}", "\u{02CB}", "\u{02C6}", "\u{02DC}", "\u{00A8}",
            "\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}",
            "\u{201C}", "\u{201D}", "\u{201E}",
            "\u{2032}", "\u{2033}", "\u{02BC}", "\u{02B9}",
        ], '', $s);

        return preg_replace('/\p{M}+/u', '', $s) ?? $s;
    }

    /**
     * @return array<string, string>
     */
    public static function asciiFoldMap(): array
    {
        return [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y',
            'Ā' => 'A', 'ā' => 'a', 'Ă' => 'A', 'ă' => 'a', 'Ą' => 'A', 'ą' => 'a',
            'Ć' => 'C', 'ć' => 'c', 'Č' => 'C', 'č' => 'c',
            'Ď' => 'D', 'ď' => 'd',
            'Ē' => 'E', 'ē' => 'e', 'Ė' => 'E', 'ė' => 'e', 'Ę' => 'E', 'ę' => 'e',
            'Ğ' => 'G', 'ğ' => 'g',
            'Ī' => 'I', 'ī' => 'i', 'İ' => 'I', 'ı' => 'i',
            'Ł' => 'L', 'ł' => 'l',
            'Ń' => 'N', 'ń' => 'n',
            'Ō' => 'O', 'ō' => 'o', 'Ő' => 'O', 'ő' => 'o',
            'Ś' => 'S', 'ś' => 's', 'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's',
            'Ť' => 'T', 'ť' => 't',
            'Ū' => 'U', 'ū' => 'u', 'Ű' => 'U', 'ű' => 'u',
            'Ÿ' => 'Y',
            'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ž' => 'Z', 'ž' => 'z',
        ];
    }

    public static function displayLabel(string $label): string
    {
        $s = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}", "\u{FEFF}"], ' ', $label);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        $s = (string) preg_replace('/[\s.,;:!?…]+$/u', '', $s);

        return $s;
    }

    public static function normalizeLabel(string $label): string
    {
        return mb_strtolower(self::displayLabel($label));
    }

    /**
     * Suffix a display name until $isTaken(normalized) is false.
     * Used by the unique-label migration before the index is added.
     */
    public static function uniquifyDisplayLabel(string $label, callable $isTaken): string
    {
        $base = self::displayLabel($label);
        $norm = self::normalizeLabel($base);
        if (! $isTaken($norm)) {
            return $base;
        }
        $n = 2;
        do {
            $suffix = ' ('.$n.')';
            $maxBase = self::LABEL_MAX - mb_strlen($suffix);
            $candidate = mb_substr($base, 0, max(1, $maxBase)).$suffix;
            $n++;
        } while ($isTaken(self::normalizeLabel($candidate)));

        return $candidate;
    }
}

<?php

namespace App\Services\Millwork;

use App\Services\CutOptimizer\GrainDirection;
use App\Services\CutOptimizer\Part;

class MillworkPartBuilder
{
    public const TYPES = [
        'shelf' => 'Simple shelf',
        'floating_shelf' => 'Floating shelf',
        'kallax' => 'Kallax shelf',
        'cabinet' => 'Cabinet',
        'cabinet_door' => 'Cabinet door only',
        'vanity' => 'Vanity',
        'closet' => 'Closet system',
        'bench' => 'Bench seating',
        'wainscoting' => 'Wainscoting',
        'mantel' => 'Mantel',
        'trim' => 'Trim / casing / base',
    ];

    public const TYPE_GROUPS = [
        'Shelving' => ['shelf', 'floating_shelf', 'kallax'],
        'Cabinets & built-ins' => ['cabinet', 'cabinet_door', 'vanity', 'closet', 'bench'],
        'Walls & trim' => ['wainscoting', 'mantel', 'trim'],
    ];

    public const CABINET_STYLES = [
        'base' => 'Base',
        'upper' => 'Upper',
        'tall' => 'Tall / pantry',
        'custom' => 'Custom',
    ];

    public const TRIM_PROFILES = [
        'baseboard' => 'Baseboard',
        'casing' => 'Casing',
        'crown' => 'Crown',
        'chair_rail' => 'Chair rail',
        'mixed' => 'Casing + base',
    ];

    /** Standard cabinet depths used as visible defaults (inches). */
    public const DEFAULT_BASE_DEPTH = 24.0;

    public const DEFAULT_UPPER_DEPTH = 12.0;

    public const DEFAULT_VANITY_DEPTH = 21.0;

    public const DEFAULT_CLOSET_DEPTH = 14.0;

    public const DEFAULT_BASE_HEIGHT = 34.5;

    public const DEFAULT_UPPER_HEIGHT = 30.0;

    public const DEFAULT_TALL_HEIGHT = 84.0;

    public const DEFAULT_CLOSET_HEIGHT = 84.0;

    public const DEFAULT_WAINSCOT_HEIGHT = 36.0;

    public const DEFAULT_SHEET_WIDTH = 48.0;

    public const DEFAULT_SHEET_LENGTH = 96.0;

    /** v1 keeps the pre-PR-A L/W order so saved quotes do not silently change. */
    public const BUILDER_VERSION = 2;

    private int $version;

    private int $pieceIndex = 0;

    private int $seq = 0;

    public function __construct(int $version = self::BUILDER_VERSION)
    {
        $this->version = $version >= 2 ? self::BUILDER_VERSION : 1;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array{
     *   parts: list<Part>,
     *   hardware: array<string, float>,
     *   edge_band_lf: float,
     *   paint_sf: float,
     *   stain_sf: float,
     *   fastener_count: int,
     *   box_sf: float,
     *   cube_count: int,
     *   unit_count: int,
     *   door_count: int,
     *   drawer_count: int,
     *   labels: list<string>
     * }
     */
    public function explode(array $piece, float $sheetThickness, float $edgeBandThickness, int $pieceIndex = 0): array
    {
        $this->pieceIndex = max(0, $pieceIndex);
        $type = self::normalizeType((string) ($piece['type'] ?? 'shelf'));
        $band = ! empty($piece['edge_banding']);
        $finish = self::normalizeFinish((string) ($piece['finish'] ?? 'none'));
        $fastener = (($piece['fastener'] ?? 'screws') === 'nails') ? 'nails' : 'screws';

        $empty = [
            'parts' => [],
            'hardware' => [
                'hinges' => 0.0,
                'pulls' => 0.0,
                'drawer_slides' => 0.0,
                'glue' => 0.0,
                'rods' => 0.0,
                'shelf_pins' => 0.0,
            ],
            'edge_band_lf' => 0.0,
            'paint_sf' => 0.0,
            'stain_sf' => 0.0,
            'fastener_count' => 0,
            'box_sf' => 0.0,
            'cube_count' => 0,
            'unit_count' => 0,
            'door_count' => 0,
            'drawer_count' => 0,
            'linear_lf' => 0.0,
            'labels' => [],
        ];

        if ($type === 'vanity') {
            $piece = $this->applyVanityDefaults($piece);
        }

        $result = match ($type) {
            'floating_shelf' => $this->floatingShelf($piece, $sheetThickness, $edgeBandThickness, $band),
            'kallax' => $this->kallax($piece, $sheetThickness, $edgeBandThickness, $band),
            'cabinet', 'vanity' => $this->cabinet($piece, $sheetThickness, $edgeBandThickness, $band),
            'closet' => $this->closet($piece, $sheetThickness, $edgeBandThickness, $band),
            'cabinet_door' => $this->cabinetDoor($piece, $edgeBandThickness, $band),
            'bench' => $this->bench($piece, $sheetThickness, $edgeBandThickness, $band),
            'wainscoting' => $this->wainscoting($piece, $edgeBandThickness, $band),
            'mantel' => $this->mantel($piece, $sheetThickness, $edgeBandThickness, $band),
            'trim' => $this->trimPackage($piece),
            default => $this->simpleShelf($piece, $edgeBandThickness, $band),
        };

        $result = array_merge($empty, $result);
        $result['hardware'] = array_merge($empty['hardware'], is_array($result['hardware'] ?? null) ? $result['hardware'] : []);
        $faceSf = 0.0;
        foreach ($result['parts'] as $part) {
            $faceSf += 2.0 * ($part->width * $part->length * max(1, $part->quantity)) / 144.0;
        }
        if ($finish === 'paint') {
            $result['paint_sf'] = round($faceSf, 2);
        } elseif (in_array($finish, ['stain', 'clear'], true)) {
            $result['stain_sf'] = round($faceSf, 2);
        }
        if ($fastener === 'nails') {
            $result['fastener_count'] = (int) ceil($result['fastener_count'] * 1.4);
        }

        return $result;
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[self::normalizeType($type)] ?? 'Simple shelf';
    }

    public static function normalizeType(string $type): string
    {
        $key = strtolower(trim($type));

        return array_key_exists($key, self::TYPES) ? $key : 'shelf';
    }

    public static function normalizeFinish(string $finish): string
    {
        $key = strtolower(trim($finish));

        return in_array($key, ['paint', 'stain', 'clear', 'none'], true) ? $key : 'none';
    }

    public static function hingesForDoorHeight(float $doorHeightIn): int
    {
        if ($doorHeightIn >= 72) {
            return 4;
        }
        if ($doorHeightIn >= 40) {
            return 3;
        }

        return 2;
    }

    public static function normalizeCabinetStyle(string $style): string
    {
        $key = strtolower(trim($style));

        return array_key_exists($key, self::CABINET_STYLES) ? $key : 'base';
    }

    public static function normalizeTrimProfile(string $profile): string
    {
        $key = strtolower(trim($profile));

        return array_key_exists($key, self::TRIM_PROFILES) ? $key : 'baseboard';
    }

    /**
     * Client-facing one-line description for a piece (PDF / email).
     *
     * @param  array<string, mixed>  $piece
     */
    public static function clientScopeLine(array $piece): string
    {
        $type = self::normalizeType((string) ($piece['type'] ?? 'shelf'));
        $room = trim((string) ($piece['room'] ?? ''));
        $name = trim((string) ($piece['name'] ?? ''));
        $title = trim(($room !== '' ? $room : '').($room !== '' && $name !== '' ? ' — ' : '').$name);
        if ($title === '') {
            $title = self::TYPES[$type] ?? 'Millwork';
        }
        $finish = match (self::normalizeFinish((string) ($piece['finish'] ?? 'none'))) {
            'paint' => 'Painted',
            'stain' => 'Stained',
            'clear' => 'Clear coat',
            default => 'Unfinished',
        };

        if ($type === 'trim') {
            $lf = self::trimQuotedLinearFeet($piece);
            $profile = self::normalizeTrimProfile((string) ($piece['profile'] ?? 'baseboard'));
            $profileLabel = $profile === 'mixed' ? 'casing and baseboard' : strtolower((string) self::TRIM_PROFILES[$profile]);
            $where = $room !== '' ? $room : ($name !== '' ? $name : 'Trim');
            $lfLabel = rtrim(rtrim(number_format($lf, 1, '.', ''), '0'), '.');
            $line = $where.' '.$profileLabel.', approx. '.$lfLabel.' lin. ft';
            if ($finish !== 'Unfinished') {
                $line .= '. '.$finish;
            }

            return $line.'.';
        }

        $kind = match ($type) {
            'cabinet' => self::normalizeCabinetStyle((string) ($piece['style'] ?? 'base')) === 'tall'
                ? 'Tall cabinet'
                : (self::CABINET_STYLES[self::normalizeCabinetStyle((string) ($piece['style'] ?? 'base'))] ?? 'Cabinet').' cabinet',
            'vanity' => 'Vanity',
            'closet' => 'Closet system',
            'wainscoting' => 'Wainscoting',
            'mantel' => 'Mantel',
            'bench' => 'Bench',
            'kallax' => 'Cube / kallax unit',
            'floating_shelf' => 'Floating shelf',
            'cabinet_door' => 'Cabinet door',
            default => 'Shelf',
        };
        $kind = trim(str_replace(' cabinet cabinet', ' cabinet', $kind));

        $w = self::dimInches($piece, ['width']);
        $h = self::dimInches($piece, ['height']);
        $d = self::dimInches($piece, ['depth']);
        $len = self::dimInches($piece, ['length']);
        $linearFt = self::positiveDim($piece, ['linear_ft']);
        $bits = [];
        if ($type === 'wainscoting' && ($linearFt > 0 || $len > 0)) {
            $lf = $linearFt > 0 ? $linearFt : ($len / 12.0);
            $bits[] = 'approx. '.rtrim(rtrim(number_format($lf, 1, '.', ''), '0'), '.').' lin. ft';
            if ($h > 0) {
                $bits[] = self::fmtStatic($h).' H';
            }
        } else {
            if ($w > 0) {
                $bits[] = self::fmtStatic($w).' W';
            }
            if ($len > 0 && $w <= 0) {
                $bits[] = self::fmtStatic($len).' L';
            }
            if ($h > 0) {
                $bits[] = self::fmtStatic($h).' H';
            }
            if ($d > 0) {
                $bits[] = self::fmtStatic($d).' D';
            }
        }
        $size = $bits === [] ? '' : ', '.implode(' × ', $bits);

        return $title.'. '.$kind.$size.'. '.$finish.'.';
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    public static function trimQuotedLinearFeet(array $piece): float
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $lf = self::positiveDim($piece, ['linear_ft']);
        if ($lf <= 0) {
            $len = self::dimInches($piece, ['length']);
            $lf = $len > 0 ? ($len / 12.0) : 0.0;
        }
        $openings = max(0, (int) ($piece['openings'] ?? 0));
        $openingW = (self::dimInches(array_merge($piece, ['type' => 'trim']), ['width']) ?: 32.0) / 12.0;
        $openingH = (self::dimInches(array_merge($piece, ['type' => 'trim']), ['height']) ?: 80.0) / 12.0;
        $casingLf = $openings * ((2 * $openingH) + $openingW);

        return round(($lf + $casingLf) * $qty, 1);
    }

    public static function fmtStatic(float $inches): string
    {
        $v = rtrim(rtrim(number_format($inches, 2, '.', ''), '0'), '.');

        return $v.'"';
    }

    /**
     * Explicit 0 stays 0. Missing / blank means one pull per door and drawer.
     *
     * @param  array<string, mixed>  $piece
     */
    public static function resolvedPullQty(array $piece, int $autoQty): int
    {
        if (! array_key_exists('pull_qty', $piece)) {
            return max(0, $autoQty);
        }
        $raw = $piece['pull_qty'];
        if ($raw === null || $raw === '') {
            return max(0, $autoQty);
        }

        return max(0, (int) $raw);
    }

    /**
     * Explicit 0 stays 0. Missing / blank means the auto hinge count.
     *
     * @param  array<string, mixed>  $piece
     */
    public static function resolvedHingeQty(array $piece, int $autoQty): int
    {
        if (! array_key_exists('hinge_qty', $piece)) {
            return max(0, $autoQty);
        }
        $raw = $piece['hinge_qty'];
        if ($raw === null || $raw === '') {
            return max(0, $autoQty);
        }

        return max(0, (int) $raw);
    }

    public static function toInches(float $value, string $unit): float
    {
        return strtolower($unit) === 'ft' ? $value * 12.0 : $value;
    }

    /**
     * Unit for one dimension. Falls back to the row / piece unit so legacy JSON stays valid.
     *
     * @param  array<string, mixed>  $row
     */
    public static function dimUnit(array $row, string $key, ?string $fallbackUnit = null): string
    {
        $specific = $row[$key.'_unit'] ?? null;
        if (is_string($specific) && $specific !== '') {
            return strtolower($specific) === 'ft' ? 'ft' : 'in';
        }
        $unit = $row['unit'] ?? $fallbackUnit ?? 'in';

        return strtolower((string) $unit) === 'ft' ? 'ft' : 'in';
    }

    /**
     * First positive dimension converted to inches, honoring per-dimension units.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    public static function dimInches(array $row, array $keys, ?string $fallbackUnit = null): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $forceInches = self::trimOpeningIsInches($row, $key);
            $pieceUnit = $forceInches
                ? 'in'
                : (strtolower((string) ($row['unit'] ?? $fallbackUnit ?? 'in')) === 'ft' ? 'ft' : 'in');
            $fieldUnit = $forceInches
                ? 'in'
                : MillworkDimensionParser::fieldUnitOf($key, $row, $fallbackUnit);
            if (! MillworkDimensionParser::shouldRewriteLegacyDim($row, $key, $pieceUnit)) {
                $raw = $row[$key];
                if ($raw === null || $raw === '') {
                    continue;
                }
                $n = (float) $raw;
                if (! is_finite($n) || $n <= 0) {
                    continue;
                }

                return self::toInches($n, $fieldUnit);
            }
            $parsed = MillworkDimensionParser::inspect($row[$key] ?? null, $fieldUnit);
            if ($parsed['status'] === MillworkDimensionParser::STATUS_OK && $parsed['inches'] > 0) {
                return $parsed['inches'];
            }
        }

        return 0.0;
    }

    /**
     * Main stored leftover trim W/H as inches even when the piece is Ft.
     */
    public static function trimOpeningIsInches(array $row, string $key): bool
    {
        return self::normalizeType((string) ($row['type'] ?? '')) === 'trim'
            && in_array($key, ['width', 'height'], true);
    }

    /**
     * First numeric dimension that is greater than zero (in the field's own unit).
     * Empty posted fields (hidden Width on a floating shelf) are skipped.
     * Fractions such as 23 1/2 are accepted on the v2 builder only.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    public static function positiveDim(array $row, array $keys): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $unit = MillworkDimensionParser::fieldUnitOf($key, $row);
            $parsed = MillworkDimensionParser::inspect($row[$key] ?? null, $unit);
            if ($parsed['status'] === MillworkDimensionParser::STATUS_OK && $parsed['inches'] > 0) {
                return $unit === 'ft' ? $parsed['inches'] / 12.0 : $parsed['inches'];
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function simpleShelf(array $piece, float $eb, bool $band): array
    {
        $parts = [];
        $labels = [];
        $unitCount = 0;
        $edgeLf = 0.0;
        $n = 0;
        foreach ($this->sets($piece) as $set) {
            $qty = max(0, (int) ($set['qty'] ?? 0));
            $len = self::dimInches($set, ['length', 'height'], (string) ($piece['unit'] ?? 'in'));
            $dep = self::dimInches($set, ['depth', 'width'], (string) ($piece['unit'] ?? 'in'));
            if ($qty <= 0 || $len <= 0 || $dep <= 0) {
                continue;
            }
            $unitCount += $qty;
            $n++;
            $label = $this->piecePrefix($piece).'Shelf '.$this->fmt($len).'×'.$this->fmt($dep);
            $labels[] = $qty.'× '.$label;
            $parts[] = $this->rect('shelf-'.$n, $label, $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
            $edgeLf += $band ? ($qty * ($len / 12.0)) : 0;
        }

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $unitCount * 4,
            'box_sf' => $this->partsSf($parts),
            'unit_count' => $unitCount,
            'labels' => $labels,
        ];
    }

    /**
     * Open-back hollow box that slides onto wall rods: top, bottom, front, left, right.
     * Outer thickness is the fascia/end height (not inner cavity height).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function floatingShelf(array $piece, float $mat, float $eb, bool $band): array
    {
        $parts = [];
        $labels = [];
        $unitCount = 0;
        $edgeLf = 0.0;
        $n = 0;
        $includeBack = ! empty($piece['include_back']);
        foreach ($this->sets($piece) as $set) {
            $qty = max(0, (int) ($set['qty'] ?? 0));
            $fallback = (string) ($set['unit'] ?? $piece['unit'] ?? 'in');
            $len = self::dimInches($set, ['length', 'height'], $fallback);
            $dep = self::dimInches($set, ['depth', 'width'], $fallback);
            $thk = self::dimInches($set, ['thickness'], $fallback);
            if ($thk <= 0) {
                $thk = self::dimInches($piece, ['thickness'], $fallback) ?: self::toInches(3, $fallback);
            }
            if ($qty <= 0 || $len <= 0 || $dep <= 0 || $thk <= 0) {
                continue;
            }
            $unitCount += $qty;
            $n++;
            $prefix = 'FS'.$n;
            $labels[] = $qty.'× '.$this->piecePrefix($piece).'floating '.$this->fmt($len).'×'.$this->fmt($dep).'×'.$this->fmt($thk);
            $parts[] = $this->rect($prefix.'-top', $prefix.' top', $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
            $parts[] = $this->rect($prefix.'-bot', $prefix.' bottom', $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
            $parts[] = $this->rect($prefix.'-front', $prefix.' front', $len, $thk, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
            $parts[] = $this->rect($prefix.'-end', $prefix.' end', $dep, $thk, $qty * 2, GrainDirection::ANY, $band, $eb, 'all');
            if ($includeBack) {
                $innerT = max(0.25, $thk - (2 * $mat));
                $parts[] = $this->rect($prefix.'-back', $prefix.' back', $len, $innerT, $qty, GrainDirection::LENGTHWISE, false, 0, 'none', 'back');
            }
            if ($band) {
                $edgeLf += $qty * (($len * 3) + ($dep * 4) + ($thk * 4)) / 12.0;
            }
        }

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $unitCount * 16,
            'box_sf' => $this->partsSf($parts),
            'unit_count' => $unitCount,
            'labels' => $labels,
            'hardware' => ['hinges' => 0, 'pulls' => 0, 'drawer_slides' => 0, 'glue' => $unitCount],
        ];
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function kallax(array $piece, float $mat, float $eb, bool $band): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $w = self::dimInches($piece, ['width']);
        $h = self::dimInches($piece, ['height']);
        $d = self::dimInches($piece, ['depth']);
        $cols = max(1, (int) ($piece['columns'] ?? $piece['partitions'] ?? 2));
        $rows = max(1, (int) ($piece['rows'] ?? 2));
        if ($w <= 0 || $h <= 0 || $d <= 0) {
            return [];
        }
        $includeBack = ! empty($piece['include_back']);
        $innerW = max(0.5, $w - (2 * $mat));
        $innerH = max(0.5, $h - (2 * $mat));
        $bayW = $cols > 0 ? $innerW / $cols : $innerW;
        $parts = [];
        $parts[] = $this->uprightRect('kx-side', 'Kallax side', $h, $d, $qty * 2, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        $parts[] = $this->rect('kx-tb', 'Kallax top/bottom', $innerW, $d, $qty * 2, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        if ($cols > 1) {
            $parts[] = $this->rect('kx-vert', 'Kallax vertical', $d, $innerH, $qty * ($cols - 1), GrainDirection::ANY, false, 0, 'none');
        }
        if ($rows > 1) {
            $parts[] = $this->rect('kx-horiz', 'Kallax shelf', $bayW, $d, $qty * ($rows - 1) * $cols, GrainDirection::ANY, $band, $eb, 'front');
        }
        if ($includeBack) {
            $parts[] = $this->rect('kx-back', 'Kallax back', $w, $h, $qty, GrainDirection::ANY, false, 0, 'none', 'back');
        }
        $edgeLf = $band ? $qty * (($w * 2) + ($h * 2) + (($rows - 1) * $cols * $bayW)) / 12.0 : 0;

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $qty * (16 + (($cols - 1) * 6) + (($rows - 1) * $cols * 4)),
            'box_sf' => round(($w * $h * $qty) / 144.0, 2),
            'cube_count' => $qty * $cols * $rows,
            'unit_count' => $qty,
            'labels' => [$qty.'× '.$this->piecePrefix($piece).'kallax '.$cols.'×'.$rows.' '.$this->fmt($w).'×'.$this->fmt($h).'×'.$this->fmt($d)],
            'hardware' => ['hinges' => 0, 'pulls' => 0, 'drawer_slides' => 0, 'glue' => $qty],
        ];
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function cabinet(array $piece, float $mat, float $eb, bool $band): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $w = self::dimInches($piece, ['width']);
        $h = self::dimInches($piece, ['height']);
        $d = self::dimInches($piece, ['depth']);
        if ($w <= 0 || $h <= 0 || $d <= 0) {
            return [];
        }
        $left = ($piece['left_side'] ?? 'panel') !== 'wall';
        $right = ($piece['right_side'] ?? 'panel') !== 'wall';
        $includeDoors = ! empty($piece['include_doors']);
        $doorCount = $includeDoors ? max(1, (int) ($piece['door_count'] ?? 2)) : 0;
        $overlay = self::normalizeOverlay((string) ($piece['overlay'] ?? 'full'));
        $shelfCount = max(0, (int) ($piece['shelf_count'] ?? 1));
        $includeBack = ! empty($piece['include_back']);
        $includeToe = ! empty($piece['include_toe_kick']);
        $drawerCount = max(0, (int) ($piece['drawer_count'] ?? 0));
        $pullsEach = self::resolvedPullQty($piece, $doorCount + $drawerCount);
        $toeH = $includeToe ? 4.5 : 0.0;
        $boxH = max($mat * 2, $h - $toeH);
        $innerW = max(0.5, $w - (($left ? $mat : 0) + ($right ? $mat : 0)));
        $parts = [];
        if ($left) {
            $parts[] = $this->uprightRect('cab-left', 'Cabinet left', $h, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        if ($right) {
            $parts[] = $this->uprightRect('cab-right', 'Cabinet right', $h, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        $parts[] = $this->rect('cab-top', 'Cabinet top', $innerW, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        $parts[] = $this->rect('cab-bot', 'Cabinet bottom', $innerW, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        if ($shelfCount > 0) {
            $parts[] = $this->rect('cab-shelf', 'Cabinet shelf', $innerW, max(4, $d - 1), $qty * $shelfCount, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        if ($includeBack) {
            $parts[] = $this->rect('cab-back', 'Cabinet back', $w, $boxH, $qty, GrainDirection::ANY, false, 0, 'none', 'back');
        }
        if ($includeToe) {
            $parts[] = $this->rect('cab-toe', 'Toe kick', $w, $toeH, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        $hingesEach = 0;
        if ($doorCount > 0) {
            $gap = $overlay === 'inset' ? 0.125 : ($overlay === 'half' ? 0.375 : 0);
            $doorW = max(4, ($w / $doorCount) - $gap);
            $doorH = max(8, $boxH - $gap);
            $parts[] = $this->uprightRect('cab-door', 'Cabinet door', $doorH, $doorW, $qty * $doorCount, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $hingesEach = self::resolvedHingeQty($piece, $doorCount * self::hingesForDoorHeight($doorH));
        }
        $slides = 0;
        if ($drawerCount > 0) {
            $dw = max(4, $innerW - 1);
            $dd = max(4, $d - 2);
            $drawerStack = $doorCount > 0 ? max(6, min(10, $boxH / 3)) : $boxH;
            $dh = max(4, ($drawerStack - (($drawerCount + 1) * $mat)) / max(1, $drawerCount));
            $dh = min(10, max(4, $dh));
            $parts[] = $this->rect('drw-front', 'Drawer front', $dw, $dh, $qty * $drawerCount, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $parts[] = $this->rect('drw-back', 'Drawer back', $dw, $dh, $qty * $drawerCount, GrainDirection::ANY, false, 0, 'none');
            $parts[] = $this->rect('drw-side', 'Drawer side', $dd, $dh, $qty * $drawerCount * 2, GrainDirection::LENGTHWISE, false, 0, 'none');
            $parts[] = $this->rect('drw-bot', 'Drawer bottom', $dw, $dd, $qty * $drawerCount, GrainDirection::ANY, false, 0, 'none', 'back');
            $slides = $qty * $drawerCount;
        }
        $edgeLf = 0.0;
        if ($band) {
            $edgeLf += $qty * (($w + $h) / 6.0);
            if ($doorCount > 0) {
                $edgeLf += $qty * $doorCount * (2 * (($w / max(1, $doorCount)) + $boxH)) / 12.0;
            }
        }

        return [
            'parts' => $parts,
            'edge_band_lf' => round(max(0, $edgeLf), 2),
            'fastener_count' => $qty * (24 + ($shelfCount * 4) + ($drawerCount * 12)),
            'box_sf' => $this->cabinetBoxSf($w, $h, $d, $qty, $left, $right, $includeBack, $boxH),
            'unit_count' => $qty,
            'door_count' => $qty * $doorCount,
            'drawer_count' => $qty * $drawerCount,
            'labels' => [$qty.'× '.$this->piecePrefix($piece).$this->cabinetKindLabel($piece).' '.$this->fmt($w).'×'.$this->fmt($h).'×'.$this->fmt($d)],
            'hardware' => [
                'hinges' => $qty * $hingesEach,
                'pulls' => $qty * $pullsEach,
                'drawer_slides' => $slides,
                'glue' => $qty,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function cabinetDoor(array $piece, float $eb, bool $band): array
    {
        $parts = [];
        $labels = [];
        $doors = 0;
        $hinges = 0;
        $edgeLf = 0.0;
        $n = 0;
        foreach ($this->sets($piece) as $set) {
            $qty = max(0, (int) ($set['qty'] ?? 0));
            $w = self::dimInches($set, ['width', 'depth'], (string) ($piece['unit'] ?? 'in'));
            $h = self::dimInches($set, ['height', 'length'], (string) ($piece['unit'] ?? 'in'));
            if ($qty <= 0 || $w <= 0 || $h <= 0) {
                continue;
            }
            $n++;
            $doors += $qty;
            $hinges += $qty * self::hingesForDoorHeight($h);
            $parts[] = $this->uprightRect('door-'.$n, 'Door '.$this->fmt($w).'×'.$this->fmt($h), $h, $w, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $labels[] = $qty.'× door '.$this->fmt($w).'×'.$this->fmt($h);
            if ($band) {
                $edgeLf += $qty * (2 * ($w + $h)) / 12.0;
            }
        }
        $pulls = self::resolvedPullQty($piece, $doors);
        $hinges = $doors > 0 ? self::resolvedHingeQty($piece, $hinges) : 0;

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $hinges * 2,
            'box_sf' => $this->partsSf($parts),
            'unit_count' => $doors,
            'door_count' => $doors,
            'labels' => $labels,
            'hardware' => ['hinges' => $hinges, 'pulls' => $pulls, 'drawer_slides' => 0, 'glue' => 0],
        ];
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function bench(array $piece, float $mat, float $eb, bool $band): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $len = self::dimInches($piece, ['length', 'width']);
        $dep = self::dimInches($piece, ['depth']);
        $h = self::dimInches($piece, ['height']) ?: 18.0;
        if ($len <= 0 || $dep <= 0 || $h <= 0) {
            return [];
        }
        $includeSides = ($piece['sides'] ?? 'panel') !== 'wall';
        $below = (($piece['below'] ?? 'divisions') === 'drawers') ? 'drawers' : 'divisions';
        $divs = max(0, (int) ($piece['division_count'] ?? 2));
        $drawers = $below === 'drawers' ? max(1, (int) ($piece['drawer_count'] ?? 2)) : 0;
        $includeBack = ! empty($piece['include_back']);
        $parts = [];
        $parts[] = $this->rect('bn-seat', 'Bench seat', $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        $parts[] = $this->rect('bn-front', 'Bench front', $len, $h, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        if ($includeSides) {
            $parts[] = $this->uprightRect('bn-side', 'Bench side', $h, $dep, $qty * 2, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        if ($includeBack) {
            $parts[] = $this->rect('bn-back', 'Bench back', $len, $h, $qty, GrainDirection::LENGTHWISE, false, 0, 'none', 'back');
        }
        $slides = 0;
        if ($below === 'divisions' && $divs > 0) {
            $parts[] = $this->rect('bn-div', 'Bench divider', $dep, $h, $qty * $divs, GrainDirection::ANY, false, 0, 'none');
        }
        if ($drawers > 0) {
            $innerW = max(4, ($len - ($includeSides ? 2 * $mat : 0) - (($drawers - 1) * $mat)) / $drawers);
            $dd = max(4, $dep - 2);
            $dh = max(4, $h - $mat - 1);
            $parts[] = $this->rect('bn-df', 'Bench drawer front', $innerW, $dh, $qty * $drawers, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $parts[] = $this->rect('bn-ds', 'Bench drawer side', $dd, $dh, $qty * $drawers * 2, GrainDirection::LENGTHWISE, false, 0, 'none');
            $parts[] = $this->rect('bn-db', 'Bench drawer back', $innerW, $dh, $qty * $drawers, GrainDirection::ANY, false, 0, 'none');
            $parts[] = $this->rect('bn-dbot', 'Bench drawer bottom', $innerW, $dd, $qty * $drawers, GrainDirection::ANY, false, 0, 'none', 'back');
            $slides = $qty * $drawers;
        }
        $edgeLf = $band ? $qty * ($len + $h) / 6.0 : 0;
        $pulls = $drawers > 0 ? self::resolvedPullQty($piece, $drawers) : 0;

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $qty * (20 + ($divs * 6) + ($drawers * 12)),
            'box_sf' => round(($len * $h * $qty) / 144.0, 2),
            'unit_count' => $qty,
            'drawer_count' => $qty * $drawers,
            'labels' => [$qty.'× '.$this->piecePrefix($piece).'bench '.$this->fmt($len).'×'.$this->fmt($dep).'×'.$this->fmt($h)],
            'hardware' => ['hinges' => 0, 'pulls' => $qty * $pulls, 'drawer_slides' => $slides, 'glue' => $qty],
        ];
    }

    /**
     * Closet system: sides, top/bottom, shelves, optional back/doors, hanging rods.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function closet(array $piece, float $mat, float $eb, bool $band): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $w = self::dimInches($piece, ['width', 'length']);
        $h = self::dimInches($piece, ['height']) ?: self::DEFAULT_CLOSET_HEIGHT;
        $d = self::dimInches($piece, ['depth']) ?: self::DEFAULT_CLOSET_DEPTH;
        if ($w <= 0 || $h <= 0 || $d <= 0) {
            return [];
        }
        $cols = max(1, (int) ($piece['columns'] ?? $piece['sections'] ?? 2));
        $shelfCount = max(0, (int) ($piece['shelf_count'] ?? 3));
        $rodCount = max(0, (int) ($piece['rod_count'] ?? 1));
        $includeDoors = ! empty($piece['include_doors']);
        $doorCount = $includeDoors ? max(1, (int) ($piece['door_count'] ?? $cols)) : 0;
        $includeBack = ! empty($piece['include_back']);
        $drawerCount = max(0, (int) ($piece['drawer_count'] ?? 0));
        $left = ($piece['left_side'] ?? 'panel') !== 'wall';
        $right = ($piece['right_side'] ?? 'panel') !== 'wall';
        $overlay = self::normalizeOverlay((string) ($piece['overlay'] ?? 'full'));
        $innerW = max(0.5, $w - (($left ? $mat : 0) + ($right ? $mat : 0)));
        $innerH = max(0.5, $h - (2 * $mat));
        $bayW = $cols > 0 ? $innerW / $cols : $innerW;
        $parts = [];
        if ($left) {
            $parts[] = $this->uprightRect('cl-left', 'Closet left', $h, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        if ($right) {
            $parts[] = $this->uprightRect('cl-right', 'Closet right', $h, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        $parts[] = $this->rect('cl-top', 'Closet top', $innerW, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        $parts[] = $this->rect('cl-bot', 'Closet bottom', $innerW, $d, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        if ($cols > 1) {
            $parts[] = $this->rect('cl-div', 'Closet divider', $d, $innerH, $qty * ($cols - 1), GrainDirection::ANY, $band, $eb, 'front');
        }
        if ($shelfCount > 0) {
            $parts[] = $this->rect('cl-shelf', 'Closet shelf', max(4, $bayW - 0.125), max(4, $d - 1), $qty * $shelfCount * $cols, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        if ($includeBack) {
            $parts[] = $this->rect('cl-back', 'Closet back', $w, $h, $qty, GrainDirection::ANY, false, 0, 'none', 'back');
        }
        $hingesEach = 0;
        if ($doorCount > 0) {
            $gap = $overlay === 'inset' ? 0.125 : ($overlay === 'half' ? 0.375 : 0);
            $doorW = max(4, ($w / $doorCount) - $gap);
            $doorH = max(8, $h - $gap);
            $parts[] = $this->uprightRect('cl-door', 'Closet door', $doorH, $doorW, $qty * $doorCount, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $hingesEach = self::resolvedHingeQty($piece, $doorCount * self::hingesForDoorHeight($doorH));
        }
        $slides = 0;
        if ($drawerCount > 0) {
            $dw = max(4, $bayW - 1);
            $dd = max(4, $d - 2);
            $dh = 6;
            $parts[] = $this->rect('cl-df', 'Closet drawer front', $dw, $dh, $qty * $drawerCount, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $parts[] = $this->rect('cl-ds', 'Closet drawer side', $dd, $dh, $qty * $drawerCount * 2, GrainDirection::LENGTHWISE, false, 0, 'none');
            $parts[] = $this->rect('cl-db', 'Closet drawer back', $dw, $dh, $qty * $drawerCount, GrainDirection::ANY, false, 0, 'none');
            $parts[] = $this->rect('cl-dbot', 'Closet drawer bottom', $dw, $dd, $qty * $drawerCount, GrainDirection::ANY, false, 0, 'none', 'back');
            $slides = $qty * $drawerCount;
        }
        $edgeLf = 0.0;
        if ($band) {
            $edgeLf += $qty * (($w + $h) / 6.0);
            if ($doorCount > 0) {
                $edgeLf += $qty * $doorCount * (2 * (($w / max(1, $doorCount)) + $h)) / 12.0;
            }
            if ($shelfCount > 0) {
                $edgeLf += $qty * $shelfCount * $cols * ($bayW / 12.0);
            }
        }
        $pulls = self::resolvedPullQty($piece, $doorCount + $drawerCount);

        return [
            'parts' => $parts,
            'edge_band_lf' => round(max(0, $edgeLf), 2),
            'fastener_count' => $qty * (20 + ($shelfCount * $cols * 4) + (($cols - 1) * 8) + ($drawerCount * 12)),
            'box_sf' => $this->cabinetBoxSf($w, $h, $d, $qty, $left, $right, $includeBack, $h),
            'unit_count' => $qty,
            'door_count' => $qty * $doorCount,
            'drawer_count' => $qty * $drawerCount,
            'linear_lf' => round($qty * $rodCount * ($innerW / 12.0), 2),
            'labels' => [$qty.'× '.$this->piecePrefix($piece).'closet '.$this->fmt($w).'×'.$this->fmt($h).'×'.$this->fmt($d)],
            'hardware' => [
                'hinges' => $qty * $hingesEach,
                'pulls' => $qty * $pulls,
                'drawer_slides' => $slides,
                'glue' => $qty,
                'rods' => $qty * $rodCount,
                'shelf_pins' => $qty * $shelfCount * $cols * 4,
            ],
        ];
    }

    /**
     * Wall paneling: sheet panels plus cap / stile linear footage.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function wainscoting(array $piece, float $eb, bool $band): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $wallIn = $this->linearFeetToInches($piece);
        if ($wallIn <= 0) {
            return [];
        }
        $heightIn = self::dimInches($piece, ['height']) ?: self::DEFAULT_WAINSCOT_HEIGHT;
        $panelW = 24.0;
        $panels = max(1, (int) ceil($wallIn / $panelW));
        $lastW = $wallIn - (($panels - 1) * $panelW);
        if ($lastW < 4) {
            $lastW = $panelW;
        }
        $parts = [];
        if ($panels > 1) {
            $parts[] = $this->rect('ws-panel', 'Wainscot panel', $panelW, $heightIn, $qty * ($panels - 1), GrainDirection::LENGTHWISE, $band, $eb, 'all');
        }
        $parts[] = $this->rect('ws-end', 'Wainscot end panel', max(4, $lastW), $heightIn, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'all');
        $stileCount = $panels + 1;
        $railLf = $qty * 2 * ($wallIn / 12.0);
        $stileLf = $qty * $stileCount * ($heightIn / 12.0);
        $linearLf = $railLf + $stileLf;
        $edgeLf = $band ? $qty * $panels * (2 * ($panelW + $heightIn)) / 12.0 : 0.0;

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $qty * ($panels * 8 + $stileCount * 4),
            'box_sf' => round(($wallIn * $heightIn * $qty) / 144.0, 2),
            'unit_count' => $qty,
            'linear_lf' => round($linearLf, 2),
            'labels' => [$qty.'× '.$this->piecePrefix($piece).'wainscoting '.$this->fmt($wallIn).' × '.$this->fmt($heightIn)],
            'hardware' => ['hinges' => 0, 'pulls' => 0, 'drawer_slides' => 0, 'glue' => $qty],
        ];
    }

    /**
     * Fireplace mantel box: top, front, two ends, optional bottom.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function mantel(array $piece, float $mat, float $eb, bool $band): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $len = self::dimInches($piece, ['length', 'width']) ?: 60;
        $dep = self::dimInches($piece, ['depth']) ?: 10;
        $h = self::dimInches($piece, ['height', 'thickness']) ?: 8;
        if ($len <= 0 || $dep <= 0 || $h <= 0) {
            return [];
        }
        $includeBottom = ! empty($piece['include_back']);
        $parts = [];
        $parts[] = $this->rect('mn-top', 'Mantel shelf', $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        $parts[] = $this->rect('mn-front', 'Mantel front', $len, $h, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        $parts[] = $this->rect('mn-end', 'Mantel end', $dep, $h, $qty * 2, GrainDirection::ANY, $band, $eb, 'all');
        if ($includeBottom) {
            $parts[] = $this->rect('mn-bot', 'Mantel bottom', max(4, $len - (2 * $mat)), max(4, $dep - $mat), $qty, GrainDirection::LENGTHWISE, false, 0, 'none');
        }
        $edgeLf = $band ? $qty * (($len * 2) + ($dep * 2) + ($h * 4)) / 12.0 : 0.0;

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $qty * 16,
            'box_sf' => round(($len * $dep * $qty) / 144.0, 2),
            'unit_count' => $qty,
            'labels' => [$qty.'× '.$this->piecePrefix($piece).'mantel '.$this->fmt($len).'×'.$this->fmt($dep).'×'.$this->fmt($h)],
            'hardware' => ['hinges' => 0, 'pulls' => 0, 'drawer_slides' => 0, 'glue' => $qty],
        ];
    }

    /**
     * Linear trim package. Piece count is ceil(LF × waste / stock length).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function trimPackage(array $piece): array
    {
        $qty = max(1, (int) ($piece['qty'] ?? 1));
        $lf = $this->linearFeetToInches($piece) / 12.0;
        if ($lf <= 0) {
            return [];
        }
        $openings = max(0, (int) ($piece['openings'] ?? 0));
        $openingW = (self::dimInches(array_merge($piece, ['type' => 'trim']), ['width']) ?: 32) / 12.0;
        $openingH = (self::dimInches(array_merge($piece, ['type' => 'trim']), ['height']) ?: 80) / 12.0;
        $casingLf = $openings * ((2 * $openingH) + $openingW);
        $totalLf = ($lf + $casingLf) * $qty;
        $profile = self::normalizeTrimProfile((string) ($piece['profile'] ?? 'baseboard'));
        $stockFt = self::positiveDim($piece, ['stock_length_ft']) ?: 12.0;
        $stockFt = max(1, $stockFt);
        $piecesNeeded = (int) max(1, ceil($totalLf / $stockFt));

        return [
            'parts' => [],
            'edge_band_lf' => 0.0,
            'fastener_count' => $piecesNeeded * 8,
            'box_sf' => 0.0,
            'unit_count' => $qty,
            'linear_lf' => round($totalLf, 2),
            'labels' => [$qty.'× '.$this->piecePrefix($piece).self::TRIM_PROFILES[$profile].' '.$this->fmtLf($totalLf)],
            'hardware' => ['hinges' => 0, 'pulls' => 0, 'drawer_slides' => 0, 'glue' => 0],
        ];
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function applyVanityDefaults(array $piece): array
    {
        if (self::positiveDim($piece, ['depth']) <= 0) {
            $piece['depth'] = self::DEFAULT_VANITY_DEPTH;
        }
        if (self::positiveDim($piece, ['height']) <= 0) {
            $piece['height'] = self::DEFAULT_BASE_HEIGHT;
        }
        if (! array_key_exists('include_toe_kick', $piece)) {
            $piece['include_toe_kick'] = true;
        }

        return $piece;
    }

    private function cabinetBoxSf(float $w, float $h, float $d, int $qty, bool $left, bool $right, bool $includeBack, float $boxH): float
    {
        $sides = ($left ? $d * $h : 0) + ($right ? $d * $h : 0);
        $tb = 2 * $w * $d;
        $back = $includeBack ? $w * max($boxH, $h) : 0;

        return round((($sides + $tb + $back) * $qty) / 144.0, 2);
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function cabinetKindLabel(array $piece): string
    {
        $type = self::normalizeType((string) ($piece['type'] ?? 'cabinet'));
        if ($type === 'vanity') {
            return 'vanity';
        }
        $style = self::normalizeCabinetStyle((string) ($piece['style'] ?? 'base'));
        if ($style === 'upper') {
            return 'upper cabinet';
        }
        if ($style === 'tall') {
            return 'tall cabinet';
        }

        return 'cabinet';
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function piecePrefix(array $piece): string
    {
        $bits = [];
        $room = trim((string) ($piece['room'] ?? ''));
        $name = trim((string) ($piece['name'] ?? ''));
        if ($room !== '') {
            $bits[] = $room;
        }
        if ($name !== '') {
            $bits[] = $name;
        }

        return $bits === [] ? '' : implode(' / ', $bits).' — ';
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function linearFeetToInches(array $piece): float
    {
        if (array_key_exists('linear_ft', $piece)) {
            $parsed = MillworkDimensionParser::inspect($piece['linear_ft'], 'ft');
            if ($parsed['status'] === MillworkDimensionParser::STATUS_OK && $parsed['inches'] > 0) {
                return $parsed['inches'];
            }
        }

        return self::dimInches($piece, ['length']);
    }

    private function fmtLf(float $lf): string
    {
        $v = rtrim(rtrim(number_format($lf, 2, '.', ''), '0'), '.');

        return $v.' lf';
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return list<array<string, mixed>>
     */
    private function sets(array $piece): array
    {
        $sets = $piece['sets'] ?? null;
        if (! is_array($sets) || $sets === []) {
            return [[
                'qty' => $piece['qty'] ?? 1,
                'length' => $piece['length'] ?? $piece['height'] ?? 0,
                'width' => $piece['width'] ?? $piece['depth'] ?? 0,
                'depth' => $piece['depth'] ?? $piece['width'] ?? 0,
                'height' => $piece['height'] ?? 0,
                'thickness' => $piece['thickness'] ?? 0,
                'unit' => $piece['unit'] ?? 'in',
            ]];
        }
        $out = [];
        foreach ($sets as $set) {
            if (is_array($set)) {
                $out[] = $set;
            }
        }

        return $out;
    }

    /**
     * Vertical panel: height is grain-length on v2, depth×height on v1.
     * Banding edges swap with the dims so the front edge stays the front edge.
     */
    private function uprightRect(
        string $id,
        string $label,
        float $height,
        float $depth,
        int $qty,
        string $grain,
        bool $band,
        float $eb,
        string $faces,
        string $materialKey = 'primary'
    ): Part {
        if ($this->version < 2) {
            return $this->rect($id, $label, $depth, $height, $qty, $grain, $band, $eb, $faces, $materialKey, false);
        }

        return $this->rect($id, $label, $height, $depth, $qty, $grain, $band, $eb, $faces, $materialKey, true);
    }

    private function rect(
        string $id,
        string $label,
        float $length,
        float $width,
        int $qty,
        string $grain,
        bool $band,
        float $eb,
        string $faces,
        string $materialKey = 'primary',
        bool $swapBanding = false
    ): Part {
        $this->seq++;
        $uniqueId = 'p'.$this->pieceIndex.'-'.$this->seq.'-'.$id;

        $top = $bottom = $left = $right = 0.0;
        if ($band && $eb > 0) {
            if ($faces === 'all') {
                $top = $bottom = $left = $right = $eb;
            } elseif ($faces === 'front') {
                if ($swapBanding) {
                    $left = $eb;
                } else {
                    $top = $eb;
                }
            }
        }

        return new Part(
            $uniqueId,
            $label,
            $width,
            $length,
            max(1, $qty),
            $grain,
            $top,
            $bottom,
            $left,
            $right,
            $materialKey,
            null,
            $this->pieceIndex
        );
    }

    /**
     * @param  list<Part>  $parts
     */
    private function partsSf(array $parts): float
    {
        $sf = 0.0;
        foreach ($parts as $part) {
            $sf += ($part->width * $part->length * $part->quantity) / 144.0;
        }

        return round($sf, 2);
    }

    private function fmt(float $inches): string
    {
        $v = rtrim(rtrim(number_format($inches, 2, '.', ''), '0'), '.');

        return $v.'"';
    }

    public static function normalizeOverlay(string $overlay): string
    {
        $key = strtolower(trim($overlay));

        return in_array($key, ['full', 'half', 'inset'], true) ? $key : 'full';
    }
}

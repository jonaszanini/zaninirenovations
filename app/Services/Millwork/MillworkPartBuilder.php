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
        'bench' => 'Bench seating',
    ];

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
    public function explode(array $piece, float $sheetThickness, float $edgeBandThickness): array
    {
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
            'labels' => [],
        ];

        $result = match ($type) {
            'floating_shelf' => $this->floatingShelf($piece, $sheetThickness, $edgeBandThickness, $band),
            'kallax' => $this->kallax($piece, $sheetThickness, $edgeBandThickness, $band),
            'cabinet' => $this->cabinet($piece, $sheetThickness, $edgeBandThickness, $band),
            'cabinet_door' => $this->cabinetDoor($piece, $edgeBandThickness, $band),
            'bench' => $this->bench($piece, $sheetThickness, $edgeBandThickness, $band),
            default => $this->simpleShelf($piece, $edgeBandThickness, $band),
        };

        $result = array_merge($empty, $result);
        $faceSf = 0.0;
        foreach ($result['parts'] as $part) {
            $faceSf += 2.0 * ($part->width * $part->length) / 144.0;
        }
        if ($finish === 'paint') {
            $result['paint_sf'] = round($faceSf, 2);
        } elseif ($finish === 'stain') {
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

        return in_array($key, ['paint', 'stain', 'none'], true) ? $key : 'none';
    }

    public static function toInches(float $value, string $unit): float
    {
        return strtolower($unit) === 'ft' ? $value * 12.0 : $value;
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
            $len = self::toInches((float) ($set['length'] ?? 0), (string) ($set['unit'] ?? 'in'));
            $dep = self::toInches((float) ($set['depth'] ?? $set['width'] ?? 0), (string) ($set['unit'] ?? 'in'));
            if ($qty <= 0 || $len <= 0 || $dep <= 0) {
                continue;
            }
            $unitCount += $qty;
            $n++;
            $label = 'Shelf '.$this->fmt($len).'×'.$this->fmt($dep);
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
            $unit = (string) ($set['unit'] ?? 'in');
            $len = self::toInches((float) ($set['length'] ?? 0), $unit);
            $dep = self::toInches((float) ($set['depth'] ?? $set['width'] ?? 0), $unit);
            $thk = self::toInches((float) ($set['thickness'] ?? $piece['thickness'] ?? 3), $unit);
            if ($qty <= 0 || $len <= 0 || $dep <= 0 || $thk <= 0) {
                continue;
            }
            $unitCount += $qty;
            $n++;
            $innerT = max(0.25, $thk - (2 * $mat));
            $prefix = 'FS'.$n;
            $labels[] = $qty.'× floating '.$this->fmt($len).'×'.$this->fmt($dep).'×'.$this->fmt($thk);
            $parts[] = $this->rect($prefix.'-top', $prefix.' top', $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
            $parts[] = $this->rect($prefix.'-bot', $prefix.' bottom', $len, $dep, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
            $parts[] = $this->rect($prefix.'-end', $prefix.' end', $dep, $innerT, $qty * 2, GrainDirection::ANY, $band, $eb, 'all');
            if ($includeBack) {
                $parts[] = $this->rect($prefix.'-back', $prefix.' back', $len, $innerT, $qty, GrainDirection::LENGTHWISE, false, 0, 'none');
            }
            if ($band) {
                $edgeLf += $qty * (($len * 2) + ($dep * 2) + ($innerT * 4)) / 12.0;
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
        $unit = (string) ($piece['unit'] ?? 'in');
        $w = self::toInches((float) ($piece['width'] ?? 0), $unit);
        $h = self::toInches((float) ($piece['height'] ?? 0), $unit);
        $d = self::toInches((float) ($piece['depth'] ?? 0), $unit);
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
        $parts[] = $this->rect('kx-side', 'Kallax side', $d, $h, $qty * 2, GrainDirection::LENGTHWISE, $band, $eb, 'front');
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
            'labels' => [$qty.'× kallax '.$cols.'×'.$rows.' '.$this->fmt($w).'×'.$this->fmt($h).'×'.$this->fmt($d)],
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
        $unit = (string) ($piece['unit'] ?? 'in');
        $w = self::toInches((float) ($piece['width'] ?? 0), $unit);
        $h = self::toInches((float) ($piece['height'] ?? 0), $unit);
        $d = self::toInches((float) ($piece['depth'] ?? 0), $unit);
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
        $pullsEach = max(0, (int) ($piece['pull_qty'] ?? ($doorCount + $drawerCount)));
        $toeH = $includeToe ? 4.5 : 0.0;
        $boxH = max($mat * 2, $h - $toeH);
        $innerW = max(0.5, $w - (($left ? $mat : 0) + ($right ? $mat : 0)));
        $parts = [];
        if ($left) {
            $parts[] = $this->rect('cab-left', 'Cabinet left', $d, $h, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
        }
        if ($right) {
            $parts[] = $this->rect('cab-right', 'Cabinet right', $d, $h, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'front');
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
        $hinges = 0;
        if ($doorCount > 0) {
            $gap = $overlay === 'inset' ? 0.125 : ($overlay === 'half' ? 0.375 : 0);
            $doorW = max(4, ($w / $doorCount) - $gap);
            $doorH = max(8, $boxH - $gap);
            $parts[] = $this->rect('cab-door', 'Cabinet door', $doorW, $doorH, $qty * $doorCount, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $perDoor = $doorH >= 40 ? 3 : 2;
            $hinges = $qty * $doorCount * $perDoor;
        }
        $slides = 0;
        if ($drawerCount > 0) {
            $dw = max(4, $innerW - 1);
            $dd = max(4, $d - 2);
            $dh = 6;
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
            'box_sf' => round(($w * $h * $qty) / 144.0, 2),
            'unit_count' => $qty,
            'door_count' => $qty * $doorCount,
            'drawer_count' => $qty * $drawerCount,
            'labels' => [$qty.'× cabinet '.$this->fmt($w).'×'.$this->fmt($h).'×'.$this->fmt($d)],
            'hardware' => [
                'hinges' => $hinges,
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
            $unit = (string) ($set['unit'] ?? 'in');
            $w = self::toInches((float) ($set['width'] ?? $set['depth'] ?? 0), $unit);
            $h = self::toInches((float) ($set['height'] ?? $set['length'] ?? 0), $unit);
            if ($qty <= 0 || $w <= 0 || $h <= 0) {
                continue;
            }
            $n++;
            $doors += $qty;
            $hinges += $qty * ($h >= 40 ? 3 : 2);
            $parts[] = $this->rect('door-'.$n, 'Door '.$this->fmt($w).'×'.$this->fmt($h), $w, $h, $qty, GrainDirection::LENGTHWISE, $band, $eb, 'all');
            $labels[] = $qty.'× door '.$this->fmt($w).'×'.$this->fmt($h);
            if ($band) {
                $edgeLf += $qty * (2 * ($w + $h)) / 12.0;
            }
        }
        $pulls = max(0, (int) ($piece['pull_qty'] ?? $doors));

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
        $unit = (string) ($piece['unit'] ?? 'in');
        $len = self::toInches((float) ($piece['length'] ?? $piece['width'] ?? 0), $unit);
        $dep = self::toInches((float) ($piece['depth'] ?? 0), $unit);
        $h = self::toInches((float) ($piece['height'] ?? 18), $unit);
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
            $parts[] = $this->rect('bn-side', 'Bench side', $dep, $h, $qty * 2, GrainDirection::LENGTHWISE, $band, $eb, 'front');
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
        $pulls = max(0, (int) ($piece['pull_qty'] ?? $drawers));

        return [
            'parts' => $parts,
            'edge_band_lf' => round($edgeLf, 2),
            'fastener_count' => $qty * (20 + ($divs * 6) + ($drawers * 12)),
            'box_sf' => round(($len * $h * $qty) / 144.0, 2),
            'unit_count' => $qty,
            'drawer_count' => $qty * $drawers,
            'labels' => [$qty.'× bench '.$this->fmt($len).'×'.$this->fmt($dep).'×'.$this->fmt($h)],
            'hardware' => ['hinges' => 0, 'pulls' => $qty * $pulls, 'drawer_slides' => $slides, 'glue' => $qty],
        ];
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
        string $materialKey = 'primary'
    ): Part {
        $top = $bottom = $left = $right = 0.0;
        if ($band && $eb > 0) {
            if ($faces === 'all') {
                $top = $bottom = $left = $right = $eb;
            } elseif ($faces === 'front') {
                $top = $eb;
            }
        }

        return new Part(
            $id,
            $label,
            $width,
            $length,
            max(1, $qty),
            $grain,
            $top,
            $bottom,
            $left,
            $right,
            $materialKey
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

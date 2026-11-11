<?php

namespace App\Services;

/**
 * Batch 2 finish takeoffs from a Room Area canvas: ceiling height, net wall
 * area (per wall, ceiling height minus the union of clamped openings),
 * flooring boxes, and casing LF.
 */
class RoomAreaFinishMath
{
    public const DEFAULT_WASTE_PERCENT = 10.0;

    public const DEFAULT_BOX_SQFT = 20.0;

    public const DIRECTION_LENGTH = 0;

    public const DIRECTION_WIDTH = 90;

    public const MIN_BOX_SQFT = 0.25;

    public const MAX_BOX_SQFT = 500.0;

    public const MAX_WASTE_PERCENT = 100.0;

    public const LEGACY_OPENING_HEIGHT_IN = 48.0;

    public const DOOR_FACE_INTERIOR = 'interior';

    public const DOOR_FACE_EXTERIOR = 'exterior';

    public function ceilingHeightFt(mixed $ceilingHeightFt, mixed $wallHeightFt): float
    {
        $wall = $this->canvas()->normalizeWallHeight($wallHeightFt);
        if ($ceilingHeightFt === null || $ceilingHeightFt === '' || ! is_numeric($ceilingHeightFt)) {
            return $wall;
        }

        return $this->canvas()->normalizeWallHeight($ceilingHeightFt);
    }

    public function ceilingAreaSqFt(float $floorAreaSqFt): float
    {
        return round(max(0.0, $floorAreaSqFt), 2);
    }

    /**
     * @return array{ok: bool, value: float, error: ?string}
     */
    public function parseWastePercent(mixed $percent): array
    {
        if ($percent === null) {
            return ['ok' => true, 'value' => self::DEFAULT_WASTE_PERCENT, 'error' => null];
        }
        if (is_int($percent) || is_float($percent)) {
            return $this->clampWasteResult((float) $percent);
        }

        $raw = trim((string) $percent);
        if ($raw === '') {
            return ['ok' => true, 'value' => self::DEFAULT_WASTE_PERCENT, 'error' => null];
        }

        $raw = str_replace(["\u{00A0}", ' '], '', $raw);
        $raw = str_replace('%', '', $raw);
        $raw = str_replace(',', '.', $raw);
        if ($raw === '' || preg_match('/[eE]/', $raw) || ! is_numeric($raw)) {
            return ['ok' => false, 'value' => self::DEFAULT_WASTE_PERCENT, 'error' => 'Enter waste as a percent, like 10 or 15%.'];
        }

        return $this->clampWasteResult((float) $raw);
    }

    public function normalizeWastePercent(mixed $percent): float
    {
        return $this->parseWastePercent($percent)['value'];
    }

    public function normalizeBoxSqFt(mixed $boxSqFt): float
    {
        if ($boxSqFt === null || $boxSqFt === '' || ! is_numeric($boxSqFt)) {
            return self::DEFAULT_BOX_SQFT;
        }
        $value = (float) $boxSqFt;
        if ($value < self::MIN_BOX_SQFT) {
            return 0.0;
        }
        if ($value > self::MAX_BOX_SQFT) {
            return self::MAX_BOX_SQFT;
        }

        return round($value, 2);
    }

    public function normalizeDirection(mixed $degrees): int
    {
        $value = is_numeric($degrees) ? (int) $degrees : self::DIRECTION_LENGTH;

        return abs($value) % 180 >= 45 ? self::DIRECTION_WIDTH : self::DIRECTION_LENGTH;
    }

    public function netWallAreaSqFt(float $lengthFt, float $heightFt, float $openingAreaSqFt = 0.0): float
    {
        return $this->roundTo(max(0.0, $lengthFt * $heightFt - max(0.0, $openingAreaSqFt)), 2);
    }

    public function openingAreaSqFt(float $widthIn, float $heightIn): float
    {
        if ($widthIn <= 0 || $heightIn <= 0) {
            return 0.0;
        }

        return ($widthIn / 12.0) * ($heightIn / 12.0);
    }

    /**
     * Union of opening rectangles on one wall, in sq ft. Heights are clamped
     * to the ceiling; overlapping openings are not double-subtracted.
     *
     * @param  list<array<string, mixed>>  $objects
     */
    public function unionOpeningAreaRawSqFt(array $objects, float $ceilingHeightFt, float $wallLengthIn = 0.0): float
    {
        return $this->unionRectAreaInches($this->openingRects($objects, $ceilingHeightFt, $wallLengthIn)) / 144.0;
    }

    public function unionOpeningAreaSqFt(array $objects, float $ceilingHeightFt, float $wallLengthIn = 0.0): float
    {
        return $this->roundTo($this->unionOpeningAreaRawSqFt($objects, $ceilingHeightFt, $wallLengthIn), 2);
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function clampOpeningOnWall(float $offset, float $width, float $wallLengthIn): array
    {
        if ($wallLengthIn <= 0) {
            return [max(0.0, $offset), max(0.0, $width)];
        }
        $width = min(max(0.0, $width), $wallLengthIn);
        $offset = max(0.0, min(max(0.0, $wallLengthIn - $width), $offset));

        return [$offset, $width];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function isLegacyOpeningPlaceholder(array $object, ?string $type = null): bool
    {
        $type = $type ?? (isset($object['type']) && is_string($object['type'])
            ? strtolower(trim($object['type']))
            : '');
        if ($type !== 'opening') {
            return false;
        }
        if (! isset($object['height']) || ! is_numeric($object['height'])) {
            return false;
        }

        return abs((float) $object['height'] - self::LEGACY_OPENING_HEIGHT_IN) < 0.001;
    }

    /**
     * Boxes needed, always rounded up. Zero when area or box size is missing.
     */
    public function flooringBoxes(float $floorAreaSqFt, mixed $wastePercent, mixed $boxSqFt): int
    {
        $area = max(0.0, $floorAreaSqFt);
        $box = $this->normalizeBoxSqFt($boxSqFt);
        if ($area <= 0 || $box < self::MIN_BOX_SQFT) {
            return 0;
        }
        $needed = $area * (1.0 + ($this->normalizeWastePercent($wastePercent) / 100.0));

        return (int) ceil($this->roundTo($needed / $box, 9));
    }

    public function roundTo(float $value, int $digits): float
    {
        $places = max(0, $digits);
        $factor = 10 ** $places;
        $shifted = $value * $factor;
        $sign = $shifted < 0.0 ? -1.0 : 1.0;

        return $sign * round(abs($shifted) + 1e-9, 0, PHP_ROUND_HALF_UP) / $factor;
    }

    public function flooringWithWasteSqFt(float $floorAreaSqFt, mixed $wastePercent): float
    {
        $area = max(0.0, $floorAreaSqFt);
        if ($area <= 0) {
            return 0.0;
        }

        return round($area * (1.0 + ($this->normalizeWastePercent($wastePercent) / 100.0)), 2);
    }

    /**
     * Window: 4 sides = (2W+2H)/12, 3 sides (stool) = (W+2H)/12.
     * Door / cased opening: faces × (W+2H)/12 (interior 2, exterior 1).
     */
    public function casingLf(float $widthIn, float $heightIn, int $sides, string $type = 'door'): float
    {
        $type = strtolower(trim($type));
        if ($widthIn <= 0 || $heightIn <= 0) {
            return 0.0;
        }
        if ($type === 'window') {
            if ($sides >= 4) {
                return round((2.0 * $widthIn + 2.0 * $heightIn) / 12.0, 2);
            }
            if ($sides === 3) {
                return round(($widthIn + (2.0 * $heightIn)) / 12.0, 2);
            }

            return 0.0;
        }

        $sides = max(0, min(2, $sides));
        if ($sides <= 0) {
            return 0.0;
        }

        return round($sides * ($widthIn + (2.0 * $heightIn)) / 12.0, 2);
    }

    public function defaultCasingSides(string $type, ?string $doorFace = null): int
    {
        return match (strtolower(trim($type))) {
            'door' => $this->normalizeDoorFace($doorFace) === self::DOOR_FACE_EXTERIOR ? 1 : 2,
            'window' => 4,
            'opening' => 2,
            default => 0,
        };
    }

    public function normalizeDoorFace(mixed $face): string
    {
        $value = is_string($face) ? strtolower(trim($face)) : '';

        return $value === self::DOOR_FACE_EXTERIOR
            ? self::DOOR_FACE_EXTERIOR
            : self::DOOR_FACE_INTERIOR;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function casingSidesForObject(array $object): int
    {
        $type = isset($object['type']) && is_string($object['type'])
            ? strtolower(trim($object['type']))
            : '';
        $doorFace = isset($object['door_face']) && is_string($object['door_face'])
            ? $object['door_face']
            : null;
        $rawSides = array_key_exists('casing_sides', $object) ? $object['casing_sides'] : null;
        if (is_string($rawSides)) {
            $rawSides = trim($rawSides);
        }
        if (! array_key_exists('casing_sides', $object) || $rawSides === null || $rawSides === '') {
            if ($this->isLegacyOpeningPlaceholder($object, $type)) {
                return 0;
            }

            return $this->defaultCasingSides($type, $doorFace);
        }
        if (! is_numeric($rawSides)) {
            return $this->defaultCasingSides($type, $doorFace);
        }
        $sides = (int) $rawSides;
        $max = $type === 'window' ? 4 : 2;

        return max(0, min($max, $sides));
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     */
    public function totalCasingLf(array $objects, float $wallHeightFt): float
    {
        $total = 0.0;
        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $type = isset($object['type']) && is_string($object['type'])
                ? strtolower(trim($object['type']))
                : '';
            if (! in_array($type, RoomAreaCanvasData::WALL_OBJECT_TYPES, true)) {
                continue;
            }
            $widthIn = isset($object['width']) ? (float) $object['width'] : 0.0;
            if ($widthIn <= 0) {
                continue;
            }
            $total += $this->casingLf(
                $widthIn,
                $this->objectHeightInches($object, $type, $wallHeightFt),
                $this->casingSidesForObject($object),
                $type
            );
        }

        return round($total, 2);
    }

    /**
     * @param  array{groups?: list<array<string, mixed>>, objects?: list<array<string, mixed>>}  $canvas
     * @return list<array{id: string, index: int, label: string, length_lf: float, opening_area_sqft: float, net_area_sqft: float}>
     */
    public function perWallNetAreas(array $canvas, float $pixelsPerFoot, float $ceilingHeightFt): array
    {
        if ($pixelsPerFoot <= 0) {
            return [];
        }

        $objectsByWall = [];
        foreach ($canvas['objects'] ?? [] as $object) {
            if (! is_array($object)) {
                continue;
            }
            $type = isset($object['type']) && is_string($object['type'])
                ? strtolower(trim($object['type']))
                : '';
            if (! in_array($type, RoomAreaCanvasData::WALL_OBJECT_TYPES, true)) {
                continue;
            }
            $wallId = isset($object['wall_id']) && is_string($object['wall_id'])
                ? $object['wall_id']
                : '';
            if ($wallId === '') {
                continue;
            }
            $object['type'] = $type;
            $objectsByWall[$wallId][] = $object;
        }

        $walls = [];
        $index = 0;
        foreach ($canvas['groups'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group['polygons'] ?? [] as $polygon) {
                if (! is_array($polygon)) {
                    continue;
                }
                $verts = $polygon['vertices'] ?? [];
                $count = is_array($verts) ? count($verts) : 0;
                $closed = ! array_key_exists('closed', $polygon) || $polygon['closed'] === null
                    ? true
                    : (bool) $polygon['closed'];
                if ($closed) {
                    if ($count < 3 || ! $this->polygons()->isSimplePolygon($verts)) {
                        continue;
                    }
                    $edgeCount = $count;
                } else {
                    if ($count < 2) {
                        continue;
                    }
                    $edgeCount = $count - 1;
                }
                $polyId = isset($polygon['id']) && is_string($polygon['id']) ? $polygon['id'] : 'p';
                for ($i = 0; $i < $edgeCount; $i++) {
                    $j = $closed ? ($i + 1) % $count : $i + 1;
                    $a = $verts[$i] ?? null;
                    $b = $verts[$j] ?? null;
                    if (! is_array($a) || ! is_array($b) || ! isset($a['x'], $a['y'], $b['x'], $b['y'])) {
                        continue;
                    }
                    $wallId = $this->canvas()->wallId($polyId, $i, $j);
                    $lengthFt = hypot((float) $b['x'] - (float) $a['x'], (float) $b['y'] - (float) $a['y']) / $pixelsPerFoot;
                    $deductRaw = $this->unionOpeningAreaRawSqFt($objectsByWall[$wallId] ?? [], $ceilingHeightFt, $lengthFt * 12.0);
                    $netExact = max(0.0, $lengthFt * $ceilingHeightFt - max(0.0, $deductRaw));
                    $index++;
                    $walls[] = [
                        'id' => $wallId,
                        'index' => $index,
                        'label' => 'W'.$index,
                        'length_lf' => round($lengthFt, 2),
                        'opening_area_sqft' => $this->roundTo($deductRaw, 2),
                        'net_area_sqft' => $this->roundTo($netExact, 2),
                        'net_area_sqft_exact' => $netExact,
                    ];
                }
            }
        }

        return $walls;
    }

    /**
     * @param  list<array{net_area_sqft?: float}>  $walls
     */
    public function sumNetWallArea(array $walls): float
    {
        $total = 0.0;
        foreach ($walls as $wall) {
            $total += (float) ($wall['net_area_sqft_exact'] ?? $wall['net_area_sqft'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $finish
     * @return array{
     *   ceiling_height_ft: float,
     *   ceiling_area_sqft: float,
     *   net_wall_area_sqft: float,
     *   walls: list<array{id: string, index: int, label: string, length_lf: float, opening_area_sqft: float, net_area_sqft: float}>,
     *   flooring_waste_percent: float,
     *   flooring_direction: int,
     *   flooring_box_sqft: float,
     *   flooring_boxes: int,
     *   flooring_with_waste_sqft: float,
     *   casing_lf: float
     * }
     */
    public function finishMetrics(array $canvas, float $pixelsPerFoot, float $wallHeightFt, float $floorAreaSqFt, array $finish = []): array
    {
        $ceiling = $this->ceilingHeightFt($finish['ceiling_height_ft'] ?? null, $wallHeightFt);
        $waste = $this->normalizeWastePercent($finish['flooring_waste_percent'] ?? self::DEFAULT_WASTE_PERCENT);
        $direction = $this->normalizeDirection($finish['flooring_direction'] ?? self::DIRECTION_LENGTH);
        $box = $this->normalizeBoxSqFt($finish['flooring_box_sqft'] ?? self::DEFAULT_BOX_SQFT);
        $walls = $this->perWallNetAreas($canvas, $pixelsPerFoot, $ceiling);

        return [
            'ceiling_height_ft' => $ceiling,
            'ceiling_area_sqft' => $this->ceilingAreaSqFt($floorAreaSqFt),
            'net_wall_area_sqft' => $this->sumNetWallArea($walls),
            'walls' => $walls,
            'flooring_waste_percent' => $waste,
            'flooring_direction' => $direction,
            'flooring_box_sqft' => $box,
            'flooring_boxes' => $this->flooringBoxes($floorAreaSqFt, $waste, $box),
            'flooring_with_waste_sqft' => $this->flooringWithWasteSqFt($floorAreaSqFt, $waste),
            'casing_lf' => $this->totalCasingLf($canvas['objects'] ?? [], $ceiling),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function objectHeightInches(array $object, string $type, float $wallHeightFt): float
    {
        $type = strtolower(trim($type));
        $ceilingIn = max(0.0, $wallHeightFt) * 12.0;
        $height = 0.0;
        $hasStoredHeight = isset($object['height']) && is_numeric($object['height']) && (float) $object['height'] > 0
            && ! $this->isLegacyOpeningPlaceholder($object, $type);
        if ($hasStoredHeight) {
            $height = (float) $object['height'];
        } else {
            $height = match ($type) {
                'window' => RoomAreaCanvasData::DEFAULT_WINDOW_HEIGHT_IN,
                'opening' => RoomAreaCanvasData::DEFAULT_OPENING_HEIGHT_IN,
                default => RoomAreaCanvasData::DEFAULT_DOOR_HEIGHT_IN,
            };
        }
        if ($ceilingIn > 0) {
            $height = min($height, $ceilingIn);
        }

        return max(0.0, $height);
    }

    /**
     * @return array{ok: bool, value: float, error: ?string}
     */
    private function clampWasteResult(float $value): array
    {
        if ($value < 0 || $value > self::MAX_WASTE_PERCENT) {
            return ['ok' => false, 'value' => self::DEFAULT_WASTE_PERCENT, 'error' => 'Waste must be between 0 and 100%.'];
        }

        return ['ok' => true, 'value' => round($value, 2), 'error' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     * @return list<array{x0: float, x1: float, y0: float, y1: float}>
     */
    private function openingRects(array $objects, float $ceilingHeightFt, float $wallLengthIn = 0.0): array
    {
        $ceilingIn = max(0.0, $ceilingHeightFt) * 12.0;
        $rects = [];
        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $type = isset($object['type']) && is_string($object['type'])
                ? strtolower(trim($object['type']))
                : '';
            if (! in_array($type, RoomAreaCanvasData::WALL_OBJECT_TYPES, true)) {
                continue;
            }
            $widthIn = isset($object['width']) ? (float) $object['width'] : 0.0;
            if ($widthIn <= 0) {
                continue;
            }
            $heightIn = $this->objectHeightInches($object, $type, $ceilingHeightFt);
            if ($heightIn <= 0) {
                continue;
            }
            $offset = isset($object['offset']) ? max(0.0, (float) $object['offset']) : 0.0;
            [$offset, $widthIn] = $this->clampOpeningOnWall($offset, $widthIn, $wallLengthIn);
            if ($widthIn <= 0) {
                continue;
            }
            $sill = 0.0;
            if ($type === 'window' && isset($object['sill_height']) && is_numeric($object['sill_height'])) {
                $sill = max(0.0, (float) $object['sill_height']);
            }
            $y0 = $sill;
            $y1 = $sill + $heightIn;
            if ($ceilingIn > 0) {
                $y0 = min($y0, $ceilingIn);
                $y1 = min($y1, $ceilingIn);
            }
            if ($y1 <= $y0) {
                continue;
            }
            $rects[] = [
                'x0' => $offset,
                'x1' => $offset + $widthIn,
                'y0' => $y0,
                'y1' => $y1,
            ];
        }

        return $rects;
    }

    /**
     * @param  list<array{x0: float, x1: float, y0: float, y1: float}>  $rects
     */
    private function unionRectAreaInches(array $rects): float
    {
        if ($rects === []) {
            return 0.0;
        }
        $xs = [];
        foreach ($rects as $rect) {
            $xs[] = $rect['x0'];
            $xs[] = $rect['x1'];
        }
        $xs = array_values(array_unique($xs));
        sort($xs, SORT_NUMERIC);
        $area = 0.0;
        $count = count($xs);
        for ($i = 0; $i < $count - 1; $i++) {
            $x0 = $xs[$i];
            $x1 = $xs[$i + 1];
            $dx = $x1 - $x0;
            if ($dx <= 0) {
                continue;
            }
            $spans = [];
            foreach ($rects as $rect) {
                if ($rect['x0'] < $x1 && $rect['x1'] > $x0) {
                    $spans[] = [$rect['y0'], $rect['y1']];
                }
            }
            if ($spans === []) {
                continue;
            }
            usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $cur0 = $spans[0][0];
            $cur1 = $spans[0][1];
            $cover = 0.0;
            for ($j = 1, $n = count($spans); $j < $n; $j++) {
                if ($spans[$j][0] <= $cur1) {
                    $cur1 = max($cur1, $spans[$j][1]);
                } else {
                    $cover += $cur1 - $cur0;
                    $cur0 = $spans[$j][0];
                    $cur1 = $spans[$j][1];
                }
            }
            $cover += $cur1 - $cur0;
            $area += $dx * $cover;
        }

        return $area;
    }

    private function canvas(): RoomAreaCanvasData
    {
        return app(RoomAreaCanvasData::class);
    }

    private function polygons(): PolygonAreaService
    {
        return app(PolygonAreaService::class);
    }
}

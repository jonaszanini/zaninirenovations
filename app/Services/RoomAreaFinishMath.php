<?php

namespace App\Services;

/**
 * Batch 2 finish takeoffs from a Room Area canvas: ceiling height, net wall
 * area (per wall, minus doors/windows/openings), flooring boxes, and casing LF.
 */
class RoomAreaFinishMath
{
    public const DEFAULT_WASTE_PERCENT = 10.0;

    public const DEFAULT_BOX_SQFT = 20.0;

    public const DIRECTION_LENGTH = 0;

    public const DIRECTION_WIDTH = 90;

    public const MIN_BOX_SQFT = 0.25;

    public const MAX_BOX_SQFT = 500.0;

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

    public function normalizeWastePercent(mixed $percent): float
    {
        $value = is_numeric($percent) ? (float) $percent : self::DEFAULT_WASTE_PERCENT;

        return round(max(0.0, min(50.0, $value)), 2);
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
        return round(max(0.0, $lengthFt * $heightFt - max(0.0, $openingAreaSqFt)), 2);
    }

    public function openingAreaSqFt(float $widthIn, float $heightIn): float
    {
        if ($widthIn <= 0 || $heightIn <= 0) {
            return 0.0;
        }

        return ($widthIn / 12.0) * ($heightIn / 12.0);
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

        return (int) ceil($needed / $box - 1e-9);
    }

    /**
     * One side is header + two legs: (W + 2H) / 12 lf.
     */
    public function casingLf(float $widthIn, float $heightIn, int $sides): float
    {
        $sides = max(0, min(2, $sides));
        if ($widthIn <= 0 || $heightIn <= 0 || $sides <= 0) {
            return 0.0;
        }

        return round($sides * ($widthIn + (2.0 * $heightIn)) / 12.0, 2);
    }

    public function defaultCasingSides(string $type): int
    {
        return match (strtolower(trim($type))) {
            'door' => 2,
            'window' => 1,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function casingSidesForObject(array $object): int
    {
        $type = isset($object['type']) && is_string($object['type'])
            ? strtolower(trim($object['type']))
            : '';
        if (array_key_exists('casing_sides', $object) && is_numeric($object['casing_sides'])) {
            return max(0, min(2, (int) $object['casing_sides']));
        }

        return $this->defaultCasingSides($type);
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
            if (! in_array($type, ['door', 'window'], true)) {
                continue;
            }
            $widthIn = isset($object['width']) ? (float) $object['width'] : 0.0;
            if ($widthIn <= 0) {
                continue;
            }
            $total += $this->casingLf(
                $widthIn,
                $this->objectHeightInches($object, $type, $wallHeightFt),
                $this->casingSidesForObject($object)
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
                if ($count < 3) {
                    continue;
                }
                $polyId = isset($polygon['id']) && is_string($polygon['id']) ? $polygon['id'] : 'p';
                for ($i = 0; $i < $count; $i++) {
                    $j = ($i + 1) % $count;
                    $a = $verts[$i] ?? null;
                    $b = $verts[$j] ?? null;
                    if (! is_array($a) || ! is_array($b) || ! isset($a['x'], $a['y'], $b['x'], $b['y'])) {
                        continue;
                    }
                    $wallId = $this->canvas()->wallId($polyId, $i, $j);
                    $lengthFt = hypot((float) $b['x'] - (float) $a['x'], (float) $b['y'] - (float) $a['y']) / $pixelsPerFoot;
                    $deduct = 0.0;
                    foreach ($objectsByWall[$wallId] ?? [] as $object) {
                        $type = strtolower(trim((string) ($object['type'] ?? '')));
                        $widthIn = isset($object['width']) ? (float) $object['width'] : 0.0;
                        if ($widthIn <= 0) {
                            continue;
                        }
                        $deduct += $this->openingAreaSqFt(
                            $widthIn,
                            $this->objectHeightInches($object, $type, $ceilingHeightFt)
                        );
                    }
                    $index++;
                    $walls[] = [
                        'id' => $wallId,
                        'index' => $index,
                        'label' => 'W'.$index,
                        'length_lf' => round($lengthFt, 2),
                        'opening_area_sqft' => round($deduct, 2),
                        'net_area_sqft' => $this->netWallAreaSqFt($lengthFt, $ceilingHeightFt, $deduct),
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
            $total += (float) ($wall['net_area_sqft'] ?? 0);
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
            'casing_lf' => $this->totalCasingLf($canvas['objects'] ?? [], $wallHeightFt),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function objectHeightInches(array $object, string $type, float $wallHeightFt): float
    {
        if (isset($object['height']) && is_numeric($object['height']) && (float) $object['height'] > 0) {
            return (float) $object['height'];
        }

        return match ($type) {
            'window' => RoomAreaCanvasData::DEFAULT_WINDOW_HEIGHT_IN,
            'opening' => $wallHeightFt * 12.0,
            default => RoomAreaCanvasData::DEFAULT_DOOR_HEIGHT_IN,
        };
    }

    private function canvas(): RoomAreaCanvasData
    {
        return app(RoomAreaCanvasData::class);
    }
}

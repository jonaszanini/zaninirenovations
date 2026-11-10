<?php

namespace App\Services;

/**
 * Interior-face floor polygons and wall edge lengths for OpenPlan3D centerline layouts.
 */
class RoomAreaInteriorGeometry
{
    public const WALL_JOIN_TOLERANCE_CM = 5.0;

    public const INTERIOR_FACE_MODE = 'interior_face';

    public function wallThicknessCm(float|int|string|null $thickness, float $cmPerUnit = 1.0): float
    {
        $t = (float) ($thickness ?? 11.43);
        if ($t <= 0) {
            $t = 11.43;
        }
        if ($cmPerUnit >= OpenPlan3DMeasurementBridge::CM_PER_FOOT - 0.01) {
            return $t * OpenPlan3DMeasurementBridge::CM_PER_INCH;
        }
        if ($cmPerUnit <= 1.01) {
            return $t;
        }

        return $t * $cmPerUnit;
    }

    public function __construct(
        private ?PolygonAreaService $polygonArea = null,
    ) {
        $this->polygonArea = $polygonArea ?? app(PolygonAreaService::class);
    }

    public function roomUsesInteriorPolygonDirectly(?array $room): bool
    {
        if (! is_array($room)) {
            return false;
        }
        $details = is_array($room['details'] ?? null) ? $room['details'] : [];
        $mode = strtolower(trim((string) ($room['floor_polygon_mode'] ?? $details['floor_polygon_mode'] ?? '')));

        return $mode === self::INTERIOR_FACE_MODE;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $centerlineVertsCm
     * @param  list<string>  $boundaryWallIds
     * @param  array<string, array<string, mixed>>  $wallsById
     * @param  list<array<string, mixed>>  $allWallsList
     */
    public function interiorFloorAreaSqFt(
        array $centerlineVertsCm,
        array $boundaryWallIds,
        array $wallsById,
        array $allWallsList,
        float $cmPerUnit = 1.0,
        bool $polygonAlreadyInterior = false,
    ): float {
        if (count($centerlineVertsCm) < 3) {
            return 0.0;
        }

        $ring = $polygonAlreadyInterior
            ? $this->normalizeCmRing($centerlineVertsCm)
            : $this->interiorPolygonCm($centerlineVertsCm, $boundaryWallIds, $wallsById, $cmPerUnit);

        if (count($ring) < 3) {
            return 0.0;
        }

        $verticesFt = [];
        foreach ($ring as $pt) {
            $verticesFt[] = [
                'x' => ((float) $pt['x'] * $cmPerUnit) / OpenPlan3DMeasurementBridge::CM_PER_FOOT,
                'y' => ((float) $pt['y'] * $cmPerUnit) / OpenPlan3DMeasurementBridge::CM_PER_FOOT,
            ];
        }

        return round($this->polygonArea->shoelaceArea($verticesFt), 2);
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $interiorVertsCm
     * @param  list<string>  $boundaryWallIds
     * @param  array<string, array<string, mixed>>  $wallsById
     */
    public function exteriorFloorAreaSqFt(
        array $ringVertsCm,
        array $boundaryWallIds,
        array $wallsById,
        float $cmPerUnit = 1.0,
        bool $polygonIsInteriorFace = false,
    ): float {
        if (count($ringVertsCm) < 3) {
            return 0.0;
        }

        $ring = $this->normalizeCmRing($ringVertsCm);
        $unit = max($cmPerUnit, 1e-9);
        $outwardFactor = 0.5;
        $offsets = $this->wallThicknessPerEdgePlan($ring, $boundaryWallIds, $wallsById, $cmPerUnit, $outwardFactor);
        $outer = $this->offsetPolygonByEdgeOffsetsCm($ring, $offsets, false);
        if (count($outer) < 3) {
            return 0.0;
        }

        $verticesFt = [];
        foreach ($outer as $pt) {
            $verticesFt[] = [
                'x' => ((float) $pt['x'] * $cmPerUnit) / OpenPlan3DMeasurementBridge::CM_PER_FOOT,
                'y' => ((float) $pt['y'] * $cmPerUnit) / OpenPlan3DMeasurementBridge::CM_PER_FOOT,
            ];
        }

        return round($this->polygonArea->shoelaceArea($verticesFt), 2);
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $centerlineVertsCm
     * @param  list<string>  $boundaryWallIds
     * @param  array<string, array<string, mixed>>  $wallsById
     * @return list<array{x: float, y: float}>
     */
    public function interiorPolygonCm(
        array $centerlineVertsCm,
        array $boundaryWallIds,
        array $wallsById,
        float $cmPerUnit = 1.0,
    ): array {
        $ring = $this->normalizeCmRing($centerlineVertsCm);
        if (count($ring) < 3) {
            return [];
        }

        $unit = max($cmPerUnit, 1e-9);
        $offsetsCm = $this->halfThicknessPerEdgeCm($ring, $boundaryWallIds, $wallsById, $cmPerUnit);
        $offsets = array_map(static fn (float $o): float => -abs($o) / $unit, $offsetsCm);

        return $this->offsetPolygonByEdgeOffsetsCm($ring, $offsets, true);
    }

    /**
     * @param  array<string, mixed>  $wall
     * @param  list<array<string, mixed>>  $allWalls
     */
    /**
     * @param  list<array{x: float|int, y: float|int}>  $ring
     */
    public function planRingPerimeterLf(array $ring, float $cmPerUnit = 1.0): float
    {
        if (count($ring) < 3) {
            return 0.0;
        }
        $unit = max($cmPerUnit, 1e-9);
        $lf = 0.0;
        $n = count($ring);
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $dx = ((float) $ring[$j]['x'] - (float) $ring[$i]['x']) * $unit;
            $dy = ((float) $ring[$j]['y'] - (float) $ring[$i]['y']) * $unit;
            $lf += hypot($dx, $dy) / OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        }

        return round($lf, 2);
    }

    public function wallPlanEdgeLengthCm(
        array $wall,
        array $allWalls,
        float $cmPerUnit = 1.0,
        bool $trimCornersAtJoints = true
    ): float {
        $center = $this->wallCenterlineLengthCm($wall, $cmPerUnit);
        if (isset($wall['curvePoint']) && is_array($wall['curvePoint'])) {
            return $center;
        }

        $trim = $this->wallEndpointTrimCm($wall, $allWalls, $cmPerUnit, $trimCornersAtJoints);

        return max(0.0, $center - $trim['start'] - $trim['end']);
    }

  /**
     * @param  array<string, mixed>  $wall
     */
    public function wallCenterlineLengthCm(array $wall, float $cmPerUnit = 1.0): float
    {
        if (! isset($wall['start'], $wall['end'])) {
            return 0.0;
        }
        $dx = ((float) $wall['end']['x'] - (float) $wall['start']['x']) * $cmPerUnit;
        $dy = ((float) $wall['end']['y'] - (float) $wall['start']['y']) * $cmPerUnit;

        return hypot($dx, $dy);
    }

    /**
     * @param  array<string, mixed>  $wall
     * @param  list<array<string, mixed>>  $allWalls
     * @return array{start: float, end: float}
     */
    public function wallEndpointTrimCm(
        array $wall,
        array $allWalls,
        float $cmPerUnit = 1.0,
        bool $trimCornersAtJoints = true
    ): array {
        $dx = ((float) $wall['end']['x'] - (float) $wall['start']['x']) * $cmPerUnit;
        $dy = ((float) $wall['end']['y'] - (float) $wall['start']['y']) * $cmPerUnit;
        $len = hypot($dx, $dy) ?: 1.0;
        $eps = self::WALL_JOIN_TOLERANCE_CM;

        $trimAt = function (array $point) use ($wall, $allWalls, $dx, $dy, $len, $cmPerUnit, $eps, $trimCornersAtJoints): float {
            $trim = 0.0;
            foreach ($allWalls as $other) {
                if (! is_array($other) || ($other['id'] ?? null) === ($wall['id'] ?? null)) {
                    continue;
                }
                if (! isset($other['start'], $other['end'])) {
                    continue;
                }
                $sx = (float) $other['start']['x'] * $cmPerUnit;
                $sy = (float) $other['start']['y'] * $cmPerUnit;
                $ex = (float) $other['end']['x'] * $cmPerUnit;
                $ey = (float) $other['end']['y'] * $cmPerUnit;
                $px = (float) $point['x'] * $cmPerUnit;
                $py = (float) $point['y'] * $cmPerUnit;
                $ox = $ex - $sx;
                $oy = $ey - $sy;
                $olen = hypot($ox, $oy) ?: 1.0;
                $atStart = hypot($sx - $px, $sy - $py) < $eps;
                $atEnd = hypot($ex - $px, $ey - $py) < $eps;
                $cross = ($dx / $len) * ($oy / $olen) - ($dy / $len) * ($ox / $olen);
                if ($atStart || $atEnd) {
                    if (! $trimCornersAtJoints || abs($cross) < 0.1) {
                        continue;
                    }
                    $joinHalf = $this->wallThicknessCm($other['thickness'] ?? null, $cmPerUnit) / 2.0;
                    $trim = max($trim, $joinHalf);

                    continue;
                }
                $t = (($px - $sx) * $ox + ($py - $sy) * $oy) / ($olen * $olen);
                $onInterior = $t > $eps / $olen && $t < 1 - $eps / $olen;
                if (! $onInterior) {
                    continue;
                }
                $projX = $sx + $t * $ox;
                $projY = $sy + $t * $oy;
                if (hypot($px - $projX, $py - $projY) >= $eps) {
                    continue;
                }
                if (abs($cross) < 0.1) {
                    continue;
                }
                $thick = $this->wallThicknessCm($other['thickness'] ?? null, $cmPerUnit);
                $trim = max($trim, $thick / 2.0);
            }

            return $trim;
        };

        return [
            'start' => $trimAt($wall['start']),
            'end' => $trimAt($wall['end']),
        ];
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     * @param  list<float>  $signedEdgeOffsetsCm negative=inward, positive=outward
     * @return list<array{x: float, y: float}>
     */
    public function offsetPolygonByEdgeOffsetsCm(array $vertices, array $signedEdgeOffsetsCm, bool $inward): array
    {
        $ring = $this->normalizeCmRing($vertices);
        $count = count($ring);
        if ($count < 3) {
            return array_map(static fn (array $v) => ['x' => (float) $v['x'], 'y' => (float) $v['y']], $vertices);
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $prev = $ring[($i - 1 + $count) % $count];
            $curr = $ring[$i];
            $next = $ring[($i + 1) % $count];
            $edgePrev = ($i - 1 + $count) % $count;
            $edgeCurr = $i;
            $d0 = abs((float) ($signedEdgeOffsetsCm[$edgePrev] ?? 0.0));
            $d1 = abs((float) ($signedEdgeOffsetsCm[$edgeCurr] ?? 0.0));
            if (! $inward) {
                $n1 = $this->outwardUnitNormal($prev, $curr, $ring);
                $n2 = $this->outwardUnitNormal($curr, $next, $ring);
            } else {
                $n1 = $this->inwardUnitNormal($prev, $curr, $ring);
                $n2 = $this->inwardUnitNormal($curr, $next, $ring);
            }
            $dot = $n1['x'] * $n2['x'] + $n1['y'] * $n2['y'];
            $denom = 1.0 + $dot;
            if (abs($denom) < 0.12) {
                $ox = $n1['x'] * $d0;
                $oy = $n1['y'] * $d0;
            } else {
                $ox = ($n1['x'] * $d0 + $n2['x'] * $d1) / $denom;
                $oy = ($n1['y'] * $d0 + $n2['y'] * $d1) / $denom;
            }
            $miter = hypot($ox, $oy);
            $maxDist = max($d0, $d1);
            $maxMiter = $maxDist > 0 ? $maxDist * 4.0 : 0.0;
            if ($miter > $maxMiter && $miter > 0) {
                $ox *= $maxMiter / $miter;
                $oy *= $maxMiter / $miter;
            }
            $out[] = [
                'x' => round((float) $curr['x'] + $ox, 4),
                'y' => round((float) $curr['y'] + $oy, 4),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $ring
     * @param  list<string>  $boundaryWallIds
     * @param  array<string, array<string, mixed>>  $wallsById
     * @return list<float>
     */
    /**
     * @return list<float>
     */
    private function wallThicknessPerEdgePlan(
        array $ring,
        array $boundaryWallIds,
        array $wallsById,
        float $cmPerUnit,
        float $outwardFactor,
    ): array {
        $count = count($ring);
        $unit = max($cmPerUnit, 1e-9);
        $mult = $outwardFactor > 0 ? $outwardFactor : 1.0;
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $wallId = $boundaryWallIds[$i] ?? $this->matchWallToEdge($ring[$i], $ring[$j], $wallsById, $cmPerUnit);
            $wall = $wallId !== null ? ($wallsById[(string) $wallId] ?? null) : null;
            $thickCm = $this->wallThicknessCm(is_array($wall) ? ($wall['thickness'] ?? null) : null, $cmPerUnit);
            $out[] = ($thickCm * $mult) / $unit;
        }

        return $out;
    }

    private function halfThicknessPerEdgeCm(
        array $ring,
        array $boundaryWallIds,
        array $wallsById,
        float $cmPerUnit,
    ): array {
        $count = count($ring);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $wallId = $boundaryWallIds[$i] ?? $this->matchWallToEdge($ring[$i], $ring[$j], $wallsById, $cmPerUnit);
            $wall = $wallId !== null ? ($wallsById[(string) $wallId] ?? null) : null;
            $thickCm = $this->wallThicknessCm(is_array($wall) ? ($wall['thickness'] ?? null) : null, $cmPerUnit);
            $out[] = max(0.0, $thickCm / 2.0);
        }

        return $out;
    }

    /**
     * @param  array{x: float|int, y: float|int}  $a
     * @param  array{x: float|int, y: float|int}  $b
     * @param  array<string, array<string, mixed>>  $wallsById
     */
    private function matchWallToEdge(array $a, array $b, array $wallsById, float $cmPerUnit): ?string
    {
        $eps = self::WALL_JOIN_TOLERANCE_CM / max($cmPerUnit, 1e-9);
        $bestId = null;
        $bestScore = PHP_FLOAT_MAX;
        foreach ($wallsById as $id => $wall) {
            if (! isset($wall['start'], $wall['end'])) {
                continue;
            }
            $s = $wall['start'];
            $e = $wall['end'];
            $d1 = hypot((float) $s['x'] - (float) $a['x'], (float) $s['y'] - (float) $a['y'])
                + hypot((float) $e['x'] - (float) $b['x'], (float) $e['y'] - (float) $b['y']);
            $d2 = hypot((float) $e['x'] - (float) $a['x'], (float) $e['y'] - (float) $a['y'])
                + hypot((float) $s['x'] - (float) $b['x'], (float) $s['y'] - (float) $b['y']);
            $score = min($d1, $d2);
            if ($score < $eps && $score < $bestScore) {
                $bestScore = $score;
                $bestId = (string) $id;
            }
        }

        return $bestId;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     * @return list<array{x: float, y: float}>
     */
    private function normalizeCmRing(array $vertices): array
    {
        $ring = [];
        foreach ($vertices as $vertex) {
            if (! is_array($vertex) || ! isset($vertex['x'], $vertex['y'])) {
                continue;
            }
            $ring[] = ['x' => (float) $vertex['x'], 'y' => (float) $vertex['y']];
        }

        return $this->polygonArea->normalizeRing($ring);
    }

    /**
     * @param  array{x: float|int, y: float|int}  $a
     * @param  array{x: float|int, y: float|int}  $b
     * @param  list<array{x: float|int, y: float|int}>  $polygon
     * @return array{x: float, y: float}
     */
    private function inwardUnitNormal(array $a, array $b, array $polygon): array
    {
        $out = $this->outwardUnitNormal($a, $b, $polygon);

        return ['x' => -$out['x'], 'y' => -$out['y']];
    }

    /**
     * @param  array{x: float|int, y: float|int}  $a
     * @param  array{x: float|int, y: float|int}  $b
     * @param  list<array{x: float|int, y: float|int}>  $polygon
     * @return array{x: float, y: float}
     */
    private function outwardUnitNormal(array $a, array $b, array $polygon): array
    {
        $dx = (float) $b['x'] - (float) $a['x'];
        $dy = (float) $b['y'] - (float) $a['y'];
        $len = hypot($dx, $dy) ?: 1.0;
        $nx = -$dy / $len;
        $ny = $dx / $len;
        $probe = [
            'x' => ((float) $a['x'] + (float) $b['x']) / 2 + $nx * 6,
            'y' => ((float) $a['y'] + (float) $b['y']) / 2 + $ny * 6,
        ];
        if ($this->polygonArea->pointInPolygon($probe, $polygon)) {
            $nx = -$nx;
            $ny = -$ny;
        }

        return ['x' => $nx, 'y' => $ny];
    }
}

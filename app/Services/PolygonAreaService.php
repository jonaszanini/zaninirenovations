<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Polygon area math for the Room Area utility (shoelace formula).
 */
class PolygonAreaService
{
    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     */
    public function shoelaceArea(array $vertices): float
    {
        return abs($this->signedShoelaceArea($vertices));
    }

    /**
     * Signed shoelace area in vertex units. Positive means counter-clockwise
     * in a Y-up coordinate system (clockwise on a typical screen/canvas).
     *
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     */
    public function signedShoelaceArea(array $vertices): float
    {
        $count = count($vertices);
        if ($count < 3) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $sum += ((float) $vertices[$i]['x'] * (float) $vertices[$j]['y'])
                - ((float) $vertices[$j]['x'] * (float) $vertices[$i]['y']);
        }

        return $sum / 2.0;
    }

    /**
     * Closed-polygon perimeter in feet (sum of edge lengths).
     *
     * @param  list<array{x: float|int, y: float|int}>  $pixelVertices
     */
    public function verticesToPerimeterFeet(array $pixelVertices, float $pixelsPerFoot): float
    {
        if ($pixelsPerFoot <= 0) {
            throw new InvalidArgumentException('Scale must be greater than zero.');
        }

        $count = count($pixelVertices);
        if ($count < 3) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $a = $pixelVertices[$i];
            $b = $pixelVertices[($i + 1) % $count];
            $dx = ((float) $b['x'] - (float) $a['x']) / $pixelsPerFoot;
            $dy = ((float) $b['y'] - (float) $a['y']) / $pixelsPerFoot;
            $sum += hypot($dx, $dy);
        }

        return round($sum, 2);
    }

    /**
     * Count drywall inside (tape) vs outside (bead) corners from polygon turns.
     * Nearly collinear vertices are skipped.
     *
     * @param  list<array{x: float|int, y: float|int}>  $pixelVertices
     * @return array{inside: int, outside: int}
     */
    public function cornerCounts(array $pixelVertices, float $minTurnDegrees = 20.0): array
    {
        $count = count($pixelVertices);
        if ($count < 3) {
            return ['inside' => 0, 'outside' => 0];
        }

        $signed = $this->signedShoelaceArea($pixelVertices);
        if (abs($signed) < 1e-9) {
            return ['inside' => 0, 'outside' => 0];
        }

        $positiveWinding = $signed > 0;
        $minTurn = deg2rad($minTurnDegrees);
        $inside = 0;
        $outside = 0;

        for ($i = 0; $i < $count; $i++) {
            $prev = $pixelVertices[($i - 1 + $count) % $count];
            $curr = $pixelVertices[$i];
            $next = $pixelVertices[($i + 1) % $count];
            $inX = (float) $curr['x'] - (float) $prev['x'];
            $inY = (float) $curr['y'] - (float) $prev['y'];
            $outX = (float) $next['x'] - (float) $curr['x'];
            $outY = (float) $next['y'] - (float) $curr['y'];
            if (hypot($inX, $inY) < 1e-6 || hypot($outX, $outY) < 1e-6) {
                continue;
            }

            $cross = ($inX * $outY) - ($inY * $outX);
            $dot = ($inX * $outX) + ($inY * $outY);
            $angle = atan2($cross, $dot);
            if (abs($angle) < $minTurn) {
                continue;
            }

            $convex = ($cross > 0) === $positiveWinding;
            if ($convex) {
                $inside++;
            } else {
                $outside++;
            }
        }

        return ['inside' => $inside, 'outside' => $outside];
    }

    /**
     * Ray-cast point-in-polygon. Boundary points count as inside.
     *
     * @param  array{x: float|int, y: float|int}  $point
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     */
    public function pointInPolygon(array $point, array $vertices): bool
    {
        $count = count($vertices);
        if ($count < 3) {
            return false;
        }

        $x = (float) $point['x'];
        $y = (float) $point['y'];
        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $vertices[$i]['x'];
            $yi = (float) $vertices[$i]['y'];
            $xj = (float) $vertices[$j]['x'];
            $yj = (float) $vertices[$j]['y'];
            if (abs($xj - $xi) < 1e-12 && abs($yj - $yi) < 1e-12) {
                continue;
            }
            $onSegment = abs(($x - $xi) * ($yj - $yi) - ($y - $yi) * ($xj - $xi)) < 1e-6
                && $x >= min($xi, $xj) - 1e-9
                && $x <= max($xi, $xj) + 1e-9
                && $y >= min($yi, $yj) - 1e-9
                && $y <= max($yi, $yj) + 1e-9;
            if ($onSegment) {
                return true;
            }
            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < (($xj - $xi) * ($y - $yi)) / (($yj - $yi) + 1e-12) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Offset a simple polygon outward by $distancePx (miter joins).
     *
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     * @return list<array{x: float, y: float}>
     */
    public function offsetPolygonOutward(array $vertices, float $distancePx): array
    {
        $count = count($vertices);
        if ($count < 3 || $distancePx <= 0) {
            return array_map(fn (array $v) => [
                'x' => (float) $v['x'],
                'y' => (float) $v['y'],
            ], $vertices);
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $prev = $vertices[($i - 1 + $count) % $count];
            $curr = $vertices[$i];
            $next = $vertices[($i + 1) % $count];
            $n1 = $this->outwardUnitNormal($prev, $curr, $vertices);
            $n2 = $this->outwardUnitNormal($curr, $next, $vertices);
            $dot = $n1['x'] * $n2['x'] + $n1['y'] * $n2['y'];
            $denom = 1.0 + $dot;
            if (abs($denom) < 0.12) {
                $ox = $n1['x'] * $distancePx;
                $oy = $n1['y'] * $distancePx;
            } else {
                $ox = ($n1['x'] + $n2['x']) * $distancePx / $denom;
                $oy = ($n1['y'] + $n2['y']) * $distancePx / $denom;
            }
            $miter = hypot($ox, $oy);
            $maxMiter = $distancePx * 4.0;
            if ($miter > $maxMiter && $miter > 0) {
                $ox *= $maxMiter / $miter;
                $oy *= $maxMiter / $miter;
            }
            $out[] = [
                'x' => round((float) $curr['x'] + $ox, 3),
                'y' => round((float) $curr['y'] + $oy, 3),
            ];
        }

        return $out;
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
        if ($this->pointInPolygon($probe, $polygon)) {
            $nx = -$nx;
            $ny = -$ny;
        }

        return ['x' => $nx, 'y' => $ny];
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     * @return list<array{x: float, y: float}>
     */
    public function normalizeRing(array $vertices, float $epsilon = 1e-6): array
    {
        if ($vertices === []) {
            return [];
        }

        $ring = [];
        foreach ($vertices as $vertex) {
            $point = ['x' => (float) $vertex['x'], 'y' => (float) $vertex['y']];
            if ($ring === []) {
                $ring[] = $point;

                continue;
            }
            $prev = $ring[count($ring) - 1];
            if (hypot($point['x'] - $prev['x'], $point['y'] - $prev['y']) < $epsilon) {
                continue;
            }
            $ring[] = $point;
        }

        if (count($ring) > 1) {
            $first = $ring[0];
            $last = $ring[count($ring) - 1];
            if (hypot($first['x'] - $last['x'], $first['y'] - $last['y']) < $epsilon) {
                array_pop($ring);
            }
        }

        if (count($ring) < 3) {
            return $ring;
        }

        $simplified = [];
        $count = count($ring);
        for ($i = 0; $i < $count; $i++) {
            $prev = $ring[($i - 1 + $count) % $count];
            $curr = $ring[$i];
            $next = $ring[($i + 1) % $count];
            $inX = $curr['x'] - $prev['x'];
            $inY = $curr['y'] - $prev['y'];
            $outX = $next['x'] - $curr['x'];
            $outY = $next['y'] - $curr['y'];
            $cross = ($inX * $outY) - ($inY * $outX);
            if (abs($cross) < $epsilon && ($inX * $outX + $inY * $outY) > 0) {
                continue;
            }
            $simplified[] = $curr;
        }

        return $simplified;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     */
    public function isSimplePolygon(array $vertices): bool
    {
        $vertices = $this->normalizeRing($vertices);
        $count = count($vertices);
        if ($count < 3) {
            return false;
        }

        for ($i = 0; $i < $count; $i++) {
            $a1 = $vertices[$i];
            $a2 = $vertices[($i + 1) % $count];

            for ($j = $i + 1; $j < $count; $j++) {
                if ($j === $i + 1) {
                    continue;
                }
                if ($i === 0 && $j === $count - 1) {
                    continue;
                }

                $b1 = $vertices[$j];
                $b2 = $vertices[($j + 1) % $count];

                if ($this->segmentsIntersect($a1, $a2, $b1, $b2)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $pixelVertices
     */
    public function verticesToSquareFeet(array $pixelVertices, float $pixelsPerFoot): float
    {
        if ($pixelsPerFoot <= 0) {
            throw new InvalidArgumentException('Scale must be greater than zero.');
        }

        $pixelVertices = $this->normalizeRing($pixelVertices);
        if (count($pixelVertices) < 3) {
            return 0.0;
        }

        if (! $this->isSimplePolygon($pixelVertices)) {
            throw new InvalidArgumentException('Room shape crosses itself — adjust corners.');
        }

        $footVertices = array_map(fn (array $vertex) => [
            'x' => $this->roundToSixteenthFoot((float) $vertex['x'] / $pixelsPerFoot),
            'y' => $this->roundToSixteenthFoot((float) $vertex['y'] / $pixelsPerFoot),
        ], $pixelVertices);

        return round($this->shoelaceArea($footVertices), 2);
    }

    private function roundToSixteenthFoot(float $feet): float
    {
        return round($feet * 16) / 16;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     * @return array{area_sqft: float, valid: bool, message: ?string}
     */
    public function validateRoom(array $vertices, float $pixelsPerFoot): array
    {
        $vertices = $this->normalizeRing($vertices);
        if (count($vertices) < 3) {
            return ['area_sqft' => 0.0, 'valid' => true, 'message' => null];
        }

        if ($pixelsPerFoot <= 0) {
            return ['area_sqft' => 0.0, 'valid' => false, 'message' => 'Calibrate scale before saving.'];
        }

        if (! $this->isSimplePolygon($vertices)) {
            return ['area_sqft' => 0.0, 'valid' => false, 'message' => 'Room shape crosses itself — adjust corners.'];
        }

        try {
            $area = $this->verticesToSquareFeet($vertices, $pixelsPerFoot);
        } catch (InvalidArgumentException $e) {
            return ['area_sqft' => 0.0, 'valid' => false, 'message' => $e->getMessage()];
        }

        return [
            'area_sqft' => $area,
            'valid' => true,
            'message' => null,
        ];
    }

    /**
     * True when every vertex lies on a negligible footprint (intentional 0-area / collapsed room).
     *
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     */
    public function isCollapsedZeroFootprint(array $vertices): bool
    {
        $vertices = $this->normalizeRing($vertices);
        if (count($vertices) < 3) {
            return true;
        }

        $minX = $maxX = (float) $vertices[0]['x'];
        $minY = $maxY = (float) $vertices[0]['y'];
        foreach ($vertices as $vertex) {
            $x = (float) $vertex['x'];
            $y = (float) $vertex['y'];
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }

        return hypot($maxX - $minX, $maxY - $minY) < 0.02;
    }

    /**
     * @param  array{x: float|int, y: float|int}  $p1
     * @param  array{x: float|int, y: float|int}  $p2
     * @param  array{x: float|int, y: float|int}  $p3
     * @param  array{x: float|int, y: float|int}  $p4
     */
    private function segmentsIntersect(array $p1, array $p2, array $p3, array $p4): bool
    {
        $d1 = $this->direction($p3, $p4, $p1);
        $d2 = $this->direction($p3, $p4, $p2);
        $d3 = $this->direction($p1, $p2, $p3);
        $d4 = $this->direction($p1, $p2, $p4);

        if ((($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0))
            && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0))) {
            return true;
        }

        if ($d1 === 0 && $this->onSegment($p3, $p4, $p1)) {
            return true;
        }
        if ($d2 === 0 && $this->onSegment($p3, $p4, $p2)) {
            return true;
        }
        if ($d3 === 0 && $this->onSegment($p1, $p2, $p3)) {
            return true;
        }
        if ($d4 === 0 && $this->onSegment($p1, $p2, $p4)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{x: float|int, y: float|int}  $p1
     * @param  array{x: float|int, y: float|int}  $p2
     * @param  array{x: float|int, y: float|int}  $p3
     */
    private function direction(array $p1, array $p2, array $p3): int
    {
        $cross = (((float) $p3['x'] - (float) $p1['x']) * ((float) $p2['y'] - (float) $p1['y']))
            - (((float) $p2['x'] - (float) $p1['x']) * ((float) $p3['y'] - (float) $p1['y']));

        if (abs($cross) < 1e-9) {
            return 0;
        }

        return $cross > 0 ? 1 : -1;
    }

    /**
     * @param  array{x: float|int, y: float|int}  $p1
     * @param  array{x: float|int, y: float|int}  $p2
     * @param  array{x: float|int, y: float|int}  $p3
     */
    private function onSegment(array $p1, array $p2, array $p3): bool
    {
        return min((float) $p1['x'], (float) $p2['x']) <= (float) $p3['x']
            && (float) $p3['x'] <= max((float) $p1['x'], (float) $p2['x'])
            && min((float) $p1['y'], (float) $p2['y']) <= (float) $p3['y']
            && (float) $p3['y'] <= max((float) $p1['y'], (float) $p2['y']);
    }
}

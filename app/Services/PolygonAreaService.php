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

        return abs($sum) / 2.0;
    }

    /**
     * @param  list<array{x: float|int, y: float|int}>  $vertices
     */
    public function isSimplePolygon(array $vertices): bool
    {
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

        if (! $this->isSimplePolygon($pixelVertices)) {
            throw new InvalidArgumentException('Polygon is self-intersecting.');
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
        if (count($vertices) < 3) {
            return ['area_sqft' => 0.0, 'valid' => false, 'message' => 'At least three corners are required.'];
        }

        if ($pixelsPerFoot <= 0) {
            return ['area_sqft' => 0.0, 'valid' => false, 'message' => 'Calibrate scale before saving.'];
        }

        if (! $this->isSimplePolygon($vertices)) {
            return ['area_sqft' => 0.0, 'valid' => false, 'message' => 'Shape crosses itself — adjust corners.'];
        }

        return [
            'area_sqft' => $this->verticesToSquareFeet($vertices, $pixelsPerFoot),
            'valid' => true,
            'message' => null,
        ];
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

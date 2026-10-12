<?php

namespace App\Services;

/**
 * Detects collinear overlapping wall segments shared by two polygons
 * (same canvas, different rooms/areas).
 */
class RoomAreaSharedWalls
{
    public const ANGLE_TOLERANCE_DEG = 8.0;

    public const MIN_OVERLAP_IN = 6.0;

    public const DEFAULT_GAP_PX = 6.0;

    public function __construct(
        private ?PolygonAreaService $polygonArea = null,
    ) {
        $this->polygonArea = $polygonArea ?? new PolygonAreaService;
    }

    /**
     * @param  array{groups?: list<array<string, mixed>>}  $canvas
     * @return list<array{
     *   a: array{polygon_id: string, start: int, end: int, wall_id: string},
     *   b: array{polygon_id: string, start: int, end: int, wall_id: string},
     *   overlap_px: float,
     *   overlap_lf: float,
     *   a1: array{x: float, y: float},
     *   a2: array{x: float, y: float}
     * }>
     */
    public function detect(array $canvas, float $pixelsPerFoot, ?float $gapPx = null): array
    {
        $walls = $this->collectWalls($canvas);
        if (count($walls) < 2) {
            return [];
        }

        $gap = $gapPx !== null && $gapPx > 0 ? $gapPx : $this->gapPx($pixelsPerFoot);
        $minOverlapPx = $this->minOverlapPx($pixelsPerFoot, $gap);
        $pairs = [];
        $seen = [];
        $count = count($walls);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $wa = $walls[$i];
                $wb = $walls[$j];
                if ($wa['polygon_id'] === $wb['polygon_id']) {
                    continue;
                }
                $hit = $this->sharedOverlap($wa, $wb, $gap, $minOverlapPx, $pixelsPerFoot);
                if ($hit === null) {
                    continue;
                }
                $key = $this->pairKey($wa['wall_id'], $wb['wall_id']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $pairs[] = $hit;
            }
        }

        return $pairs;
    }

    /**
     * Unique shared-wall length in feet (each overlapping pair counted once).
     *
     * @param  array{groups?: list<array<string, mixed>>}  $canvas
     */
    public function sharedLengthFeet(array $canvas, float $pixelsPerFoot, ?float $gapPx = null): float
    {
        $total = 0.0;
        foreach ($this->detect($canvas, $pixelsPerFoot, $gapPx) as $pair) {
            $total += (float) $pair['overlap_lf'];
        }

        return round($total, 2);
    }

    /**
     * Framing linear feet: each unique wall once (shared partition counted once).
     */
    public function framingLf(float $totalPerimeterLf, float $sharedLf): float
    {
        return round(max(0.0, $totalPerimeterLf - $sharedLf), 2);
    }

    /**
     * Exterior (non-shared) perimeter in feet.
     */
    public function exteriorLf(float $totalPerimeterLf, float $sharedLf): float
    {
        return round(max(0.0, $totalPerimeterLf - (2.0 * $sharedLf)), 2);
    }

    /**
     * Coincident vertex pairs on shared walls (same canvas coordinates).
     *
     * @param  array{groups?: list<array<string, mixed>>}  $canvas
     * @return list<array{
     *   a: array{polygon_id: string, index: int},
     *   b: array{polygon_id: string, index: int}
     * }>
     */
    public function linkedVertices(array $canvas, float $pixelsPerFoot, ?float $gapPx = null): array
    {
        $gap = $gapPx !== null && $gapPx > 0 ? $gapPx : $this->gapPx($pixelsPerFoot);
        $links = [];
        $seen = [];

        foreach ($this->detect($canvas, $pixelsPerFoot, $gap) as $pair) {
            $aWall = $this->wallEndpoints($canvas, $pair['a']);
            $bWall = $this->wallEndpoints($canvas, $pair['b']);
            if ($aWall === null || $bWall === null) {
                continue;
            }
            $aPts = [
                ['index' => $pair['a']['start'], 'pt' => $aWall['a']],
                ['index' => $pair['a']['end'], 'pt' => $aWall['b']],
            ];
            $bPts = [
                ['index' => $pair['b']['start'], 'pt' => $bWall['a']],
                ['index' => $pair['b']['end'], 'pt' => $bWall['b']],
            ];
            foreach ($aPts as $ap) {
                foreach ($bPts as $bp) {
                    if ($this->dist($ap['pt'], $bp['pt']) > $gap) {
                        continue;
                    }
                    $key = $pair['a']['polygon_id'].':'.$ap['index'].'|'.$pair['b']['polygon_id'].':'.$bp['index'];
                    $rev = $pair['b']['polygon_id'].':'.$bp['index'].'|'.$pair['a']['polygon_id'].':'.$ap['index'];
                    if (isset($seen[$key]) || isset($seen[$rev])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $links[] = [
                        'a' => ['polygon_id' => $pair['a']['polygon_id'], 'index' => $ap['index']],
                        'b' => ['polygon_id' => $pair['b']['polygon_id'], 'index' => $bp['index']],
                    ];
                }
            }
        }

        return $links;
    }

    public function gapPx(float $pixelsPerFoot): float
    {
        if ($pixelsPerFoot > 0) {
            return max(self::DEFAULT_GAP_PX, ($pixelsPerFoot / 12.0) * 2.0);
        }

        return self::DEFAULT_GAP_PX;
    }

    private function minOverlapPx(float $pixelsPerFoot, float $gapPx): float
    {
        if ($pixelsPerFoot > 0) {
            return max($gapPx, ($pixelsPerFoot / 12.0) * self::MIN_OVERLAP_IN);
        }

        return max($gapPx, 8.0);
    }

    /**
     * @param  array{groups?: list<array<string, mixed>>}  $canvas
     * @return list<array{polygon_id: string, start: int, end: int, wall_id: string, a: array{x: float, y: float}, b: array{x: float, y: float}}>
     */
    public function collectWalls(array $canvas): array
    {
        $walls = [];
        foreach ($canvas['groups'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group['polygons'] ?? [] as $polygon) {
                if (! is_array($polygon)) {
                    continue;
                }
                $id = isset($polygon['id']) && is_string($polygon['id']) ? $polygon['id'] : '';
                $verts = $polygon['vertices'] ?? [];
                if ($id === '' || ! is_array($verts) || count($verts) < 3) {
                    continue;
                }
                $n = count($verts);
                for ($i = 0; $i < $n; $i++) {
                    $j = ($i + 1) % $n;
                    $a = $this->asPoint($verts[$i] ?? null);
                    $b = $this->asPoint($verts[$j] ?? null);
                    if ($a === null || $b === null) {
                        continue;
                    }
                    $walls[] = [
                        'polygon_id' => $id,
                        'start' => $i,
                        'end' => $j,
                        'wall_id' => 'wall_'.$id.'_'.$i.'_'.$j,
                        'a' => $a,
                        'b' => $b,
                    ];
                }
            }
        }

        return $walls;
    }

    /**
     * @param  array{polygon_id: string, start: int, end: int, wall_id: string, a: array{x: float, y: float}, b: array{x: float, y: float}}  $wa
     * @param  array{polygon_id: string, start: int, end: int, wall_id: string, a: array{x: float, y: float}, b: array{x: float, y: float}}  $wb
     * @return array<string, mixed>|null
     */
    private function sharedOverlap(array $wa, array $wb, float $gapPx, float $minOverlapPx, float $pixelsPerFoot): ?array
    {
        $dxa = $wa['b']['x'] - $wa['a']['x'];
        $dya = $wa['b']['y'] - $wa['a']['y'];
        $lenA = hypot($dxa, $dya);
        if ($lenA < $minOverlapPx) {
            return null;
        }
        $dxb = $wb['b']['x'] - $wb['a']['x'];
        $dyb = $wb['b']['y'] - $wb['a']['y'];
        $lenB = hypot($dxb, $dyb);
        if ($lenB < $minOverlapPx) {
            return null;
        }

        $angA = atan2($dya, $dxa);
        $angB = atan2($dyb, $dxb);
        $delta = abs(atan2(sin($angA - $angB), cos($angA - $angB)));
        $parallel = $delta <= deg2rad(self::ANGLE_TOLERANCE_DEG)
            || abs($delta - M_PI) <= deg2rad(self::ANGLE_TOLERANCE_DEG);
        if (! $parallel) {
            return null;
        }

        $lineDist = max(
            $this->pointLineDistance($wb['a'], $wa['a'], $wa['b']),
            $this->pointLineDistance($wb['b'], $wa['a'], $wa['b']),
        );
        if ($lineDist > $gapPx) {
            return null;
        }

        $ux = $dxa / $lenA;
        $uy = $dya / $lenA;
        $ta0 = 0.0;
        $ta1 = $lenA;
        $tb0 = (($wb['a']['x'] - $wa['a']['x']) * $ux) + (($wb['a']['y'] - $wa['a']['y']) * $uy);
        $tb1 = (($wb['b']['x'] - $wa['a']['x']) * $ux) + (($wb['b']['y'] - $wa['a']['y']) * $uy);
        $bMin = min($tb0, $tb1);
        $bMax = max($tb0, $tb1);
        $overlapStart = max($ta0, $bMin);
        $overlapEnd = min($ta1, $bMax);
        $overlapPx = $overlapEnd - $overlapStart;
        if ($overlapPx < $minOverlapPx) {
            return null;
        }

        $p1 = [
            'x' => $wa['a']['x'] + $ux * $overlapStart,
            'y' => $wa['a']['y'] + $uy * $overlapStart,
        ];
        $p2 = [
            'x' => $wa['a']['x'] + $ux * $overlapEnd,
            'y' => $wa['a']['y'] + $uy * $overlapEnd,
        ];
        $overlapLf = $pixelsPerFoot > 0 ? $overlapPx / $pixelsPerFoot : 0.0;

        return [
            'a' => [
                'polygon_id' => $wa['polygon_id'],
                'start' => $wa['start'],
                'end' => $wa['end'],
                'wall_id' => $wa['wall_id'],
            ],
            'b' => [
                'polygon_id' => $wb['polygon_id'],
                'start' => $wb['start'],
                'end' => $wb['end'],
                'wall_id' => $wb['wall_id'],
            ],
            'overlap_px' => round($overlapPx, 3),
            'overlap_lf' => round($overlapLf, 2),
            'a1' => ['x' => round($p1['x'], 3), 'y' => round($p1['y'], 3)],
            'a2' => ['x' => round($p2['x'], 3), 'y' => round($p2['y'], 3)],
        ];
    }

    /**
     * @param  array{x: float, y: float}  $p
     * @param  array{x: float, y: float}  $a
     * @param  array{x: float, y: float}  $b
     */
    private function pointLineDistance(array $p, array $a, array $b): float
    {
        $dx = $b['x'] - $a['x'];
        $dy = $b['y'] - $a['y'];
        $lenSq = ($dx * $dx) + ($dy * $dy);
        if ($lenSq < 1e-12) {
            return $this->dist($p, $a);
        }
        $t = (($p['x'] - $a['x']) * $dx + ($p['y'] - $a['y']) * $dy) / $lenSq;
        $proj = ['x' => $a['x'] + $t * $dx, 'y' => $a['y'] + $t * $dy];

        return $this->dist($p, $proj);
    }

    /**
     * @param  array{x: float, y: float}  $a
     * @param  array{x: float, y: float}  $b
     */
    private function dist(array $a, array $b): float
    {
        return hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
    }

    /**
     * @param  array{groups?: list<array<string, mixed>>}  $canvas
     * @param  array{polygon_id: string, start: int, end: int}  $ref
     * @return array{a: array{x: float, y: float}, b: array{x: float, y: float}}|null
     */
    private function wallEndpoints(array $canvas, array $ref): ?array
    {
        foreach ($canvas['groups'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group['polygons'] ?? [] as $polygon) {
                if (! is_array($polygon) || ($polygon['id'] ?? '') !== $ref['polygon_id']) {
                    continue;
                }
                $verts = $polygon['vertices'] ?? [];
                $a = $this->asPoint($verts[$ref['start']] ?? null);
                $b = $this->asPoint($verts[$ref['end']] ?? null);
                if ($a === null || $b === null) {
                    return null;
                }

                return ['a' => $a, 'b' => $b];
            }
        }

        return null;
    }

    /**
     * @return array{x: float, y: float}|null
     */
    private function asPoint(mixed $raw): ?array
    {
        if (! is_array($raw) || ! isset($raw['x'], $raw['y'])) {
            return null;
        }

        return [
            'x' => (float) $raw['x'],
            'y' => (float) $raw['y'],
        ];
    }

    private function pairKey(string $a, string $b): string
    {
        return strcmp($a, $b) <= 0 ? $a.'|'.$b : $b.'|'.$a;
    }
}

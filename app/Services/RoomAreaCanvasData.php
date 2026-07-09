<?php

namespace App\Services;

/**
 * Normalizes, validates, and summarizes Room Area canvas data (v1 legacy + v2 multi-group).
 */
class RoomAreaCanvasData
{
    public const DEFAULT_GROUP_COLOR = '#024768';

    /** @var list<string> */
    public const GROUP_COLORS = [
        '#024768',
        '#2e7d32',
        '#e65100',
        '#6a1b9a',
        '#c62828',
        '#00838f',
        '#5d4037',
    ];

    private PolygonAreaService $polygonArea;

    public function __construct(
        ?PolygonAreaService $polygonArea = null,
    ) {
        $this->polygonArea = $polygonArea ?? new PolygonAreaService;
    }

    /**
     * @param  array<mixed>|null  $raw
     * @return array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}
     */
    public function normalize(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return $this->emptyCanvas();
        }

        if ($this->isLegacyVerticesList($raw)) {
            return [
                'v' => 2,
                'groups' => [
                    [
                        'id' => 'g1',
                        'color' => self::DEFAULT_GROUP_COLOR,
                        'polygons' => [
                            [
                                'id' => 'p1',
                                'vertices' => $this->normalizeVertexList($raw),
                            ],
                        ],
                    ],
                ],
            ];
        }

        if (($raw['v'] ?? null) === 2 && isset($raw['groups']) && is_array($raw['groups'])) {
            return $this->normalizeV2($raw);
        }

        return $this->emptyCanvas();
    }

    /**
     * @param  array<mixed>|null  $raw
     */
    public function isLegacyVerticesList(?array $raw): bool
    {
        if ($raw === null || $raw === [] || ! array_is_list($raw)) {
            return false;
        }

        $first = $raw[0] ?? null;

        return is_array($first)
            && array_key_exists('x', $first)
            && array_key_exists('y', $first)
            && ! array_key_exists('v', $raw);
    }

    /**
     * @param  array<mixed>  $input
     * @return array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}
     */
    private function normalizeV2(array $input): array
    {
        $groups = [];
        $groupIndex = 0;

        foreach ($input['groups'] as $group) {
            if (! is_array($group)) {
                continue;
            }
            $groupIndex++;
            $groupId = isset($group['id']) && is_string($group['id']) && $group['id'] !== ''
                ? $group['id']
                : 'g'.$groupIndex;
            $color = isset($group['color']) && is_string($group['color']) && $group['color'] !== ''
                ? $group['color']
                : self::GROUP_COLORS[($groupIndex - 1) % count(self::GROUP_COLORS)];

            $polygons = [];
            $polygonIndex = 0;
            foreach ($group['polygons'] ?? [] as $polygon) {
                if (! is_array($polygon)) {
                    continue;
                }
                $verts = $this->normalizeVertexList($polygon['vertices'] ?? []);
                if (count($verts) < 3) {
                    continue;
                }
                $polygonIndex++;
                $polygons[] = [
                    'id' => isset($polygon['id']) && is_string($polygon['id']) && $polygon['id'] !== ''
                        ? $polygon['id']
                        : 'p'.$polygonIndex,
                    'vertices' => $verts,
                ];
            }

            if ($polygons !== []) {
                $groups[] = [
                    'id' => $groupId,
                    'color' => $color,
                    'polygons' => $polygons,
                ];
            }
        }

        if ($groups === []) {
            return $this->emptyCanvas();
        }

        return [
            'v' => 2,
            'groups' => $groups,
        ];
    }

    /**
     * @param  list<mixed>  $vertices
     * @return list<array{x: float, y: float}>
     */
    private function normalizeVertexList(array $vertices): array
    {
        $out = [];
        foreach ($vertices as $vertex) {
            if (! is_array($vertex) || ! isset($vertex['x'], $vertex['y'])) {
                continue;
            }
            $out[] = [
                'x' => round((float) $vertex['x'], 3),
                'y' => round((float) $vertex['y'], 3),
            ];
        }

        return $out;
    }

    /**
     * @return array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}
     */
    public function emptyCanvas(): array
    {
        return [
            'v' => 2,
            'groups' => [],
        ];
    }

    /**
     * @param  array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}  $canvas
     * @return array{
     *   valid: bool,
     *   message: ?string,
     *   area_sqft: float,
     *   group_summaries: list<array{id: string, color: string, area_sqft: float, polygon_count: int}>
     * }
     */
    public function validate(array $canvas, float $pixelsPerFoot): array
    {
        $normalized = $this->normalize($canvas);
        $closedCount = 0;

        foreach ($normalized['groups'] as $group) {
            foreach ($group['polygons'] as $polygon) {
                if (count($polygon['vertices']) < 3) {
                    continue;
                }
                $closedCount++;
                $result = $this->polygonArea->validateRoom($polygon['vertices'], $pixelsPerFoot);
                if (! $result['valid']) {
                    return [
                        'valid' => false,
                        'message' => $result['message'] ?? 'Invalid polygon shape.',
                        'area_sqft' => 0.0,
                        'group_summaries' => [],
                    ];
                }
            }
        }

        if ($closedCount === 0) {
            return [
                'valid' => false,
                'message' => 'At least one closed area with three or more corners is required.',
                'area_sqft' => 0.0,
                'group_summaries' => [],
            ];
        }

        if ($pixelsPerFoot <= 0) {
            return [
                'valid' => false,
                'message' => 'Calibrate scale before saving.',
                'area_sqft' => 0.0,
                'group_summaries' => [],
            ];
        }

        $summaries = $this->groupSummaries($normalized, $pixelsPerFoot);

        return [
            'valid' => true,
            'message' => null,
            'area_sqft' => $this->totalAreaSqFt($summaries),
            'group_summaries' => $summaries,
        ];
    }

    /**
     * @param  array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}  $canvas
     * @return list<array{id: string, color: string, area_sqft: float, polygon_count: int}>
     */
    public function groupSummaries(array $canvas, float $pixelsPerFoot): array
    {
        $normalized = $this->normalize($canvas);
        $summaries = [];

        foreach ($normalized['groups'] as $group) {
            $groupArea = 0.0;
            $polygonCount = 0;

            foreach ($group['polygons'] as $polygon) {
                if (count($polygon['vertices']) < 3) {
                    continue;
                }
                if ($pixelsPerFoot <= 0) {
                    continue;
                }
                if (! $this->polygonArea->isSimplePolygon($polygon['vertices'])) {
                    continue;
                }
                $groupArea += $this->polygonArea->verticesToSquareFeet($polygon['vertices'], $pixelsPerFoot);
                $polygonCount++;
            }

            if ($polygonCount === 0) {
                continue;
            }

            $summaries[] = [
                'id' => $group['id'],
                'color' => $group['color'],
                'area_sqft' => round($groupArea, 2),
                'polygon_count' => $polygonCount,
            ];
        }

        return $summaries;
    }

    /**
     * @param  list<array{id: string, color: string, area_sqft: float, polygon_count: int}>  $summaries
     */
    public function totalAreaSqFt(array $summaries): float
    {
        $total = 0.0;
        foreach ($summaries as $summary) {
            $total += (float) $summary['area_sqft'];
        }

        return round($total, 2);
    }

    /**
     * @param  array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}  $canvas
     */
    public function totalAreaSqFtFromCanvas(array $canvas, float $pixelsPerFoot): float
    {
        return $this->totalAreaSqFt($this->groupSummaries($canvas, $pixelsPerFoot));
    }
}

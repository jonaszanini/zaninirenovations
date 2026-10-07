<?php

namespace App\Services;

/**
 * Normalizes, validates, and summarizes Room Area canvas data
 * (v1 legacy + v2 multi-group + v3 wall objects).
 */
class RoomAreaCanvasData
{
    public const DEFAULT_GROUP_COLOR = '#024768';

    public const OBJECT_TYPES = ['door', 'window', 'opening'];

    public const OBJECT_SUBTYPES = [
        'single',
        'double',
        'sliding',
        'pocket',
        'bifold',
        'archway',
        'fixed',
    ];

    public const OBJECT_SWINGS = ['left_in', 'left_out', 'right_in', 'right_out'];

    public const OBJECT_HINGES = ['left', 'right'];

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
     * @return array{
     *   v: int,
     *   groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>,
     *   objects?: list<array<string, mixed>>,
     *   background?: array{x: float, y: float, width: float, height: float}
     * }
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

        $version = (int) ($raw['v'] ?? 0);
        if (in_array($version, [2, 3], true) && isset($raw['groups']) && is_array($raw['groups'])) {
            return $this->normalizeGroupedCanvas($raw);
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
     * @return array{
     *   v: int,
     *   groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>,
     *   objects?: list<array<string, mixed>>,
     *   background?: array{x: float, y: float, width: float, height: float}
     * }
     */
    private function normalizeGroupedCanvas(array $input): array
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
                $item = [
                    'id' => isset($polygon['id']) && is_string($polygon['id']) && $polygon['id'] !== ''
                        ? $polygon['id']
                        : 'p'.$polygonIndex,
                    'vertices' => $verts,
                ];
                $name = $this->normalizeAreaName($polygon['name'] ?? null);
                if ($name !== null) {
                    $item['name'] = $name;
                }
                $polygons[] = $item;
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

        $objects = $this->normalizeObjects($input['objects'] ?? null, $groups);
        $version = ((int) ($input['v'] ?? 2) >= 3 || $objects !== []) ? 3 : 2;

        $canvas = [
            'v' => $version,
            'groups' => $groups,
        ];

        if ($version >= 3) {
            $canvas['objects'] = $objects;
        }

        $background = $this->normalizeBackground($input['background'] ?? null);
        if ($background !== null) {
            $canvas['background'] = $background;
        }

        return $canvas;
    }

    /**
     * @param  list<mixed>|null  $raw
     * @param  list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>  $groups
     * @return list<array<string, mixed>>
     */
    private function normalizeObjects(?array $raw, array $groups): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        $validWallIds = $this->collectWallIds($groups);
        $objects = [];
        $index = 0;

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $index++;
            $normalized = $this->normalizeObject($item, $index, $validWallIds);
            if ($normalized !== null) {
                $objects[] = $normalized;
            }
        }

        return $objects;
    }

    /**
     * @param  list<array{id: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>  $groups
     * @return list<string>
     */
    private function collectWallIds(array $groups): array
    {
        $ids = [];
        foreach ($groups as $group) {
            foreach ($group['polygons'] as $polygon) {
                $verts = $polygon['vertices'];
                $count = count($verts);
                if ($count < 2) {
                    continue;
                }
                for ($i = 0; $i < $count; $i++) {
                    $j = ($i + 1) % $count;
                    if ($count === 2 && $i === 1) {
                        break;
                    }
                    $ids[] = $this->wallId($polygon['id'], $i, $j);
                }
            }
        }

        return $ids;
    }

    public function wallId(string $polygonId, int $start, int $end): string
    {
        return 'wall_'.$polygonId.'_'.$start.'_'.$end;
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<string>  $validWallIds
     * @return array<string, mixed>|null
     */
    private function normalizeObject(array $item, int $index, array $validWallIds): ?array
    {
        $type = isset($item['type']) && is_string($item['type'])
            ? strtolower(trim($item['type']))
            : '';
        if (! in_array($type, self::OBJECT_TYPES, true)) {
            return null;
        }

        $subtype = isset($item['subtype']) && is_string($item['subtype'])
            ? strtolower(trim($item['subtype']))
            : $this->defaultSubtype($type);
        if (! in_array($subtype, self::OBJECT_SUBTYPES, true)) {
            $subtype = $this->defaultSubtype($type);
        }

        $wallId = isset($item['wall_id']) && is_string($item['wall_id'])
            ? trim($item['wall_id'])
            : '';
        if ($wallId === '' || ! preg_match('/^wall_[A-Za-z0-9._-]+_\d+_\d+$/', $wallId)) {
            return null;
        }
        if ($validWallIds !== [] && ! in_array($wallId, $validWallIds, true)) {
            return null;
        }

        $width = isset($item['width']) ? (float) $item['width'] : $this->defaultWidth($type, $subtype);
        if ($width < 6 || $width > 240) {
            return null;
        }

        $offset = isset($item['offset']) ? (float) $item['offset'] : 0.0;
        if ($offset < 0) {
            $offset = 0.0;
        }

        $id = isset($item['id']) && is_string($item['id']) && $item['id'] !== ''
            ? $item['id']
            : 'obj_'.$index;

        $object = [
            'id' => $id,
            'type' => $type,
            'subtype' => $subtype,
            'wall_id' => $wallId,
            'width' => round($width, 3),
            'offset' => round($offset, 3),
        ];

        if ($type === 'door' || $type === 'opening') {
            $swing = isset($item['swing']) && is_string($item['swing'])
                ? strtolower(trim($item['swing']))
                : 'left_in';
            $object['swing'] = in_array($swing, self::OBJECT_SWINGS, true) ? $swing : 'left_in';

            $hinge = isset($item['hinge']) && is_string($item['hinge'])
                ? strtolower(trim($item['hinge']))
                : 'left';
            $object['hinge'] = in_array($hinge, self::OBJECT_HINGES, true) ? $hinge : 'left';
        }

        if ($type === 'window' || $type === 'opening') {
            $height = isset($item['height']) ? (float) $item['height'] : 48.0;
            $sill = isset($item['sill_height']) ? (float) $item['sill_height'] : 36.0;
            if ($height > 0 && $height <= 240) {
                $object['height'] = round($height, 3);
            }
            if ($sill >= 0 && $sill <= 240) {
                $object['sill_height'] = round($sill, 3);
            }
        }

        return $object;
    }

    private function defaultSubtype(string $type): string
    {
        return match ($type) {
            'window' => 'fixed',
            'opening' => 'archway',
            default => 'single',
        };
    }

    private function defaultWidth(string $type, string $subtype): float
    {
        return match (true) {
            $type === 'door' && $subtype === 'double' => 60.0,
            $type === 'door' && in_array($subtype, ['sliding', 'pocket', 'bifold'], true) => 60.0,
            $type === 'window' => 36.0,
            $type === 'opening' => 36.0,
            default => 36.0,
        };
    }

    /**
     * @param  array<mixed>|null  $background
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    private function normalizeBackground(?array $background): ?array
    {
        if ($background === null || ! isset($background['x'], $background['y'], $background['width'], $background['height'])) {
            return null;
        }

        $width = (float) $background['width'];
        $height = (float) $background['height'];
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'x' => round((float) $background['x'], 3),
            'y' => round((float) $background['y'], 3),
            'width' => round($width, 3),
            'height' => round($height, 3),
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

    private function normalizeAreaName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $clean = preg_replace('/\s+/u', ' ', $name) ?? '';
        $clean = trim($clean);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean) ?? '';
        if ($clean === '') {
            return null;
        }

        $clean = function_exists('mb_substr') ? mb_substr($clean, 0, 80) : substr($clean, 0, 80);

        return $clean === '' ? null : $clean;
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

    /**
     * Floor area, wall perimeter, and drywall corner counts for a saved layout.
     *
     * @param  array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}  $canvas
     * @return array{area_sqft: float, perimeter_lf: float, inside_corners: int, outside_corners: int, polygon_count: int}
     */
    public function layoutMetrics(array $canvas, float $pixelsPerFoot): array
    {
        $normalized = $this->normalize($canvas);
        $area = 0.0;
        $perimeter = 0.0;
        $inside = 0;
        $outside = 0;
        $polygonCount = 0;

        if ($pixelsPerFoot <= 0) {
            return $this->emptyLayoutMetrics();
        }

        foreach ($normalized['groups'] as $group) {
            foreach ($group['polygons'] as $polygon) {
                if (count($polygon['vertices']) < 3) {
                    continue;
                }
                if (! $this->polygonArea->isSimplePolygon($polygon['vertices'])) {
                    continue;
                }
                $area += $this->polygonArea->verticesToSquareFeet($polygon['vertices'], $pixelsPerFoot);
                $perimeter += $this->polygonArea->verticesToPerimeterFeet($polygon['vertices'], $pixelsPerFoot);
                $corners = $this->polygonArea->cornerCounts($polygon['vertices']);
                $inside += $corners['inside'];
                $outside += $corners['outside'];
                $polygonCount++;
            }
        }

        if ($polygonCount === 0) {
            return $this->emptyLayoutMetrics();
        }

        return [
            'area_sqft' => round($area, 2),
            'perimeter_lf' => round($perimeter, 2),
            'inside_corners' => $inside,
            'outside_corners' => $outside,
            'polygon_count' => $polygonCount,
        ];
    }

    /**
     * @return array{area_sqft: float, perimeter_lf: float, inside_corners: int, outside_corners: int, polygon_count: int}
     */
    public function emptyLayoutMetrics(): array
    {
        return [
            'area_sqft' => 0.0,
            'perimeter_lf' => 0.0,
            'inside_corners' => 0,
            'outside_corners' => 0,
            'polygon_count' => 0,
        ];
    }
}

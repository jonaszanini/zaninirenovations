<?php

namespace App\Services;

/**
 * Normalizes, validates, and summarizes Room Area canvas data
 * (v1 legacy + v2 multi-group + v3 wall objects + v4 thickness / free objects).
 */
class RoomAreaCanvasData
{
    public const DEFAULT_GROUP_COLOR = '#024768';

    public const WALL_OBJECT_TYPES = ['door', 'window', 'opening'];

    public const FREE_OBJECT_TYPES = ['stairs', 'cabinet', 'fixture', 'furniture'];

    public const OBJECT_TYPES = ['door', 'window', 'opening', 'stairs', 'cabinet', 'fixture', 'furniture'];

    public const OBJECT_SUBTYPES = [
        'single',
        'double',
        'sliding',
        'pocket',
        'bifold',
        'archway',
        'fixed',
        'straight',
        'l',
        'u',
        'base',
        'upper',
        'corner',
        'island',
        'toilet',
        'tub',
        'shower',
        'vanity',
        'sofa',
        'bed',
        'dining_table',
    ];

    public const OBJECT_SWINGS = ['left_in', 'left_out', 'right_in', 'right_out'];

    public const OBJECT_HINGES = ['left', 'right'];

    public const WALL_THICKNESS_PRESETS_IN = [0.0, 3.5, 4.5, 5.5, 6.0];

    public const EDITOR_DIM_MODES = ['all', 'main', 'area', 'off'];

    public const EDITOR_UNIT_SYSTEMS = ['imperial', 'metric'];

    public const EDITOR_MEASURE_UNITS = ['meter', 'inch', 'feet_decimal', 'feet_inch_frac'];

    public const EDITOR_TEMPLATES = ['rect', 'l', 'u', 't', 'alcove'];

    public const DEFAULT_WALL_HEIGHT_FT = 8.0;

    public const DEFAULT_DOOR_HEIGHT_IN = 80.0;

    public const DEFAULT_WINDOW_HEIGHT_IN = 36.0;

    public const DEFAULT_OPENING_HEIGHT_IN = 84.0;

    public const STAIR_TREAD_IN = 10.0;

    public const MAX_WALL_THICKNESS_IN = 12.0;

    /** Real-world lock compare; ignores a few pixels of canvas resize. */
    public const LOCK_GEOM_TOLERANCE_FT = 0.05;

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

    private RoomAreaSharedWalls $sharedWalls;

    public function __construct(
        ?PolygonAreaService $polygonArea = null,
        ?RoomAreaSharedWalls $sharedWalls = null,
    ) {
        $this->polygonArea = $polygonArea ?? new PolygonAreaService;
        $this->sharedWalls = $sharedWalls ?? new RoomAreaSharedWalls($this->polygonArea);
    }

    /**
     * @param  array<mixed>|null  $raw
     * @return array{
     *   v: int,
     *   groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>,
     *   objects?: list<array<string, mixed>>,
     *   wall_thickness_in?: float,
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
        if (in_array($version, [2, 3, 4], true) && isset($raw['groups']) && is_array($raw['groups'])) {
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
     *   wall_thickness_in?: float,
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
        $thickness = $this->normalizeWallThickness($input['wall_thickness_in'] ?? 0);
        $hasFree = $this->hasFreeObjects($objects);
        $inputVersion = (int) ($input['v'] ?? 2);
        $version = 2;
        if ($thickness > 0 || $hasFree || $inputVersion >= 4) {
            $version = 4;
        } elseif ($inputVersion >= 3 || $objects !== []) {
            $version = 3;
        }

        $canvas = [
            'v' => $version,
            'groups' => $groups,
        ];

        if ($version >= 3) {
            $canvas['objects'] = $objects;
        }
        if ($thickness > 0) {
            $canvas['wall_thickness_in'] = $thickness;
        }

        $background = $this->normalizeBackground($input['background'] ?? null);
        if ($background !== null) {
            $canvas['background'] = $background;
        }

        $editor = $this->normalizeEditorPrefs($input['editor'] ?? null);
        if ($editor !== null) {
            $canvas['editor'] = $editor;
        }

        return $canvas;
    }

    /**
     * Optional Batch 1 editor prefs. Missing or invalid keys are dropped so
     * older saved rooms still load.
     *
     * @param  mixed  $raw
     * @return array{
     *   layers_locked?: bool,
     *   dimensions_mode?: string,
     *   unit_system?: string,
     *   angle_snap?: bool,
     *   template?: string
     * }|null
     */
    public function normalizeEditorPrefs($raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $out = [];
        if (array_key_exists('layers_locked', $raw)) {
            $out['layers_locked'] = filter_var($raw['layers_locked'], FILTER_VALIDATE_BOOLEAN);
        }
        $mode = isset($raw['dimensions_mode']) ? (string) $raw['dimensions_mode'] : '';
        if (in_array($mode, self::EDITOR_DIM_MODES, true)) {
            $out['dimensions_mode'] = $mode;
        }
        $units = isset($raw['unit_system']) ? (string) $raw['unit_system'] : '';
        if (in_array($units, self::EDITOR_UNIT_SYSTEMS, true)) {
            $out['unit_system'] = $units;
        }
        $measure = isset($raw['measure_unit']) ? (string) $raw['measure_unit'] : '';
        if (in_array($measure, self::EDITOR_MEASURE_UNITS, true)) {
            $out['measure_unit'] = $measure;
        }
        if (array_key_exists('angle_snap', $raw)) {
            $out['angle_snap'] = filter_var($raw['angle_snap'], FILTER_VALIDATE_BOOLEAN);
        }
        $template = isset($raw['template']) ? (string) $raw['template'] : '';
        if (in_array($template, self::EDITOR_TEMPLATES, true)) {
            $out['template'] = $template;
        }

        return $out === [] ? null : $out;
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

        if (in_array($type, self::FREE_OBJECT_TYPES, true)) {
            return $this->normalizeFreeObject($item, $index, $type, $subtype);
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

        if (in_array($type, ['door', 'window'], true)) {
            $sides = array_key_exists('casing_sides', $item) && is_numeric($item['casing_sides'])
                ? (int) $item['casing_sides']
                : ($type === 'door' ? 2 : 4);
            $object['casing_sides'] = max(0, min($type === 'window' ? 4 : 2, $sides));
        }

        if ($type === 'door') {
            $face = isset($item['door_face']) && is_string($item['door_face'])
                ? strtolower(trim($item['door_face']))
                : RoomAreaFinishMath::DOOR_FACE_INTERIOR;
            $object['door_face'] = $face === RoomAreaFinishMath::DOOR_FACE_EXTERIOR
                ? RoomAreaFinishMath::DOOR_FACE_EXTERIOR
                : RoomAreaFinishMath::DOOR_FACE_INTERIOR;
        }

        if ($type === 'opening') {
            $legacyPlaceholder = isset($item['height']) && is_numeric($item['height'])
                && abs((float) $item['height'] - RoomAreaFinishMath::LEGACY_OPENING_HEIGHT_IN) < 0.001;
            $sides = array_key_exists('casing_sides', $item) && is_numeric($item['casing_sides'])
                ? (int) $item['casing_sides']
                : ($legacyPlaceholder ? 0 : 2);
            $object['casing_sides'] = max(0, min(2, $sides));
        }

        if ($type === 'window') {
            $height = isset($item['height']) && is_numeric($item['height'])
                ? (float) $item['height']
                : self::DEFAULT_WINDOW_HEIGHT_IN;
            if ($height > 0 && $height <= 240) {
                $object['height'] = round($height, 3);
            }
            if (isset($item['sill_height']) && is_numeric($item['sill_height'])) {
                $sill = (float) $item['sill_height'];
                if ($sill >= 0 && $sill <= 240) {
                    $object['sill_height'] = round($sill, 3);
                }
            }
        } elseif ($type === 'opening') {
            if (isset($item['height']) && is_numeric($item['height'])) {
                $height = (float) $item['height'];
                if ($height > 0 && $height <= 240) {
                    $object['height'] = round($height, 3);
                }
            }
        } elseif ($type === 'door' && isset($item['height']) && is_numeric($item['height'])) {
            $height = (float) $item['height'];
            if ($height > 0 && $height <= 240) {
                $object['height'] = round($height, 3);
            }
        }

        return $object;
    }

    /**
     * @param  array<mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeFreeObject(array $item, int $index, string $type, string $subtype): ?array
    {
        if (! isset($item['x'], $item['y']) || ! is_numeric($item['x']) || ! is_numeric($item['y'])) {
            return null;
        }

        $width = isset($item['width']) ? (float) $item['width'] : $this->defaultFreeSize($type, $subtype)['width'];
        $depth = isset($item['depth']) ? (float) $item['depth'] : $this->defaultFreeSize($type, $subtype)['depth'];
        if ($width < 6 || $width > 360 || $depth < 6 || $depth > 360) {
            return null;
        }

        $rotation = isset($item['rotation']) && is_numeric($item['rotation'])
            ? (float) $item['rotation']
            : 0.0;
        $rotation = fmod($rotation, 360.0);
        if ($rotation < 0) {
            $rotation += 360.0;
        }

        $id = isset($item['id']) && is_string($item['id']) && $item['id'] !== ''
            ? $item['id']
            : 'obj_'.$index;

        return [
            'id' => $id,
            'type' => $type,
            'subtype' => $subtype,
            'x' => round((float) $item['x'], 3),
            'y' => round((float) $item['y'], 3),
            'width' => round($width, 3),
            'depth' => round($depth, 3),
            'rotation' => round($rotation, 3),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     */
    private function hasFreeObjects(array $objects): bool
    {
        foreach ($objects as $object) {
            if (isset($object['type']) && in_array($object['type'], self::FREE_OBJECT_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    private function defaultSubtype(string $type): string
    {
        return match ($type) {
            'window' => 'fixed',
            'opening' => 'archway',
            'stairs' => 'straight',
            'cabinet' => 'base',
            'fixture' => 'toilet',
            'furniture' => 'sofa',
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
     * @return array{width: float, depth: float}
     */
    public function defaultFreeSize(string $type, string $subtype): array
    {
        return match (true) {
            $type === 'stairs' && $subtype === 'l' => ['width' => 72.0, 'depth' => 72.0],
            $type === 'stairs' && $subtype === 'u' => ['width' => 72.0, 'depth' => 96.0],
            $type === 'stairs' => ['width' => 36.0, 'depth' => 120.0],
            $type === 'cabinet' && $subtype === 'upper' => ['width' => 36.0, 'depth' => 12.0],
            $type === 'cabinet' && $subtype === 'corner' => ['width' => 24.0, 'depth' => 24.0],
            $type === 'cabinet' && $subtype === 'island' => ['width' => 48.0, 'depth' => 36.0],
            $type === 'cabinet' => ['width' => 36.0, 'depth' => 24.0],
            $type === 'fixture' && $subtype === 'tub' => ['width' => 60.0, 'depth' => 32.0],
            $type === 'fixture' && $subtype === 'shower' => ['width' => 36.0, 'depth' => 36.0],
            $type === 'fixture' && $subtype === 'vanity' => ['width' => 36.0, 'depth' => 21.0],
            $type === 'fixture' => ['width' => 20.0, 'depth' => 30.0],
            $type === 'furniture' && $subtype === 'bed' => ['width' => 60.0, 'depth' => 80.0],
            $type === 'furniture' && $subtype === 'dining_table' => ['width' => 72.0, 'depth' => 36.0],
            $type === 'furniture' => ['width' => 84.0, 'depth' => 36.0],
            default => ['width' => 36.0, 'depth' => 24.0],
        };
    }

    public function normalizeWallThickness(mixed $inches): float
    {
        $value = is_numeric($inches) ? (float) $inches : 0.0;
        if ($value <= 0) {
            return 0.0;
        }
        if ($value > self::MAX_WALL_THICKNESS_IN) {
            return self::MAX_WALL_THICKNESS_IN;
        }

        return round($value, 2);
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
     * Floor area, wall perimeter, openings, and drywall-oriented wall metrics.
     *
     * v2 rooms without objects do not subtract anything from baseboard or wall area.
     *
     * @param  array{v: int, groups: list<array{id: string, color: string, polygons: list<array{id: string, vertices: list<array{x: float, y: float}>}>}>}  $canvas
     * @return array{
     *   area_sqft: float,
     *   perimeter_lf: float,
     *   baseboard_lf: float,
     *   wall_height_ft: float,
     *   wall_area_sqft: float,
     *   door_count: int,
     *   window_count: int,
     *   opening_count: int,
     *   drywall_openings: int,
     *   inside_corners: int,
     *   outside_corners: int,
     *   polygon_count: int,
     *   wall_thickness_in: float,
     *   inside_area_sqft: float,
     *   outside_area_sqft: float,
     *   shared_wall_lf: float,
     *   framing_lf: float,
     *   cabinet_base_lf: float,
     *   cabinet_upper_lf: float,
     *   stairs_count: int,
     *   stairs_treads: int,
     *   stairs_run_lf: float,
     *   stairs_quote_type: string
     * }
     */
    /**
     * @param  array<string, mixed>  $finish
     */
    public function layoutMetrics(array $canvas, float $pixelsPerFoot, float $wallHeightFt = self::DEFAULT_WALL_HEIGHT_FT, array $finish = []): array
    {
        $normalized = $this->normalize($canvas);
        $area = 0.0;
        $perimeter = 0.0;
        $inside = 0;
        $outside = 0;
        $polygonCount = 0;
        $wallHeightFt = $this->normalizeWallHeight($wallHeightFt);
        $thicknessIn = $this->normalizeWallThickness($normalized['wall_thickness_in'] ?? 0);

        if ($pixelsPerFoot <= 0) {
            return $this->emptyLayoutMetrics($wallHeightFt);
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
            return $this->emptyLayoutMetrics($wallHeightFt);
        }

        $objectMetrics = $this->objectDeductionMetrics($normalized['objects'] ?? [], $wallHeightFt);
        $library = $this->libraryMetrics($normalized['objects'] ?? []);
        $perimeter = round($perimeter, 2);
        $sharedLf = $this->sharedWalls->sharedLengthFeet($normalized, $pixelsPerFoot);
        $framing = $this->sharedWalls->framingLf($perimeter, $sharedLf);
        $baseboard = max(0.0, round($perimeter - $objectMetrics['baseboard_deduction_lf'], 2));
        $insideArea = round($area, 2);
        $outsideArea = $this->outsideAreaSqFt($insideArea, $perimeter, $sharedLf, $thicknessIn);
        $finishMetrics = app(RoomAreaFinishMath::class)->finishMetrics(
            $normalized,
            $pixelsPerFoot,
            $wallHeightFt,
            $insideArea,
            $finish
        );
        $wallArea = (float) ($finishMetrics['net_wall_area_sqft'] ?? 0);

        return [
            'area_sqft' => $insideArea,
            'perimeter_lf' => $perimeter,
            'baseboard_lf' => $baseboard,
            'wall_height_ft' => $wallHeightFt,
            'wall_area_sqft' => $wallArea,
            'door_count' => $objectMetrics['door_count'],
            'window_count' => $objectMetrics['window_count'],
            'opening_count' => $objectMetrics['opening_count'],
            'drywall_openings' => $objectMetrics['drywall_openings'],
            'inside_corners' => $inside,
            'outside_corners' => $outside,
            'polygon_count' => $polygonCount,
            'wall_thickness_in' => $thicknessIn,
            'inside_area_sqft' => $insideArea,
            'outside_area_sqft' => $outsideArea,
            'shared_wall_lf' => $sharedLf,
            'framing_lf' => $framing,
            'cabinet_base_lf' => $library['cabinet_base_lf'],
            'cabinet_upper_lf' => $library['cabinet_upper_lf'],
            'stairs_count' => $library['stairs_count'],
            'stairs_treads' => $library['stairs_treads'],
            'stairs_run_lf' => $library['stairs_run_lf'],
            'stairs_quote_type' => $library['stairs_quote_type'],
        ] + $finishMetrics;
    }

    /**
     * @return array{
     *   area_sqft: float,
     *   perimeter_lf: float,
     *   baseboard_lf: float,
     *   wall_height_ft: float,
     *   wall_area_sqft: float,
     *   door_count: int,
     *   window_count: int,
     *   opening_count: int,
     *   drywall_openings: int,
     *   inside_corners: int,
     *   outside_corners: int,
     *   polygon_count: int
     * }
     */
    public function emptyLayoutMetrics(float $wallHeightFt = self::DEFAULT_WALL_HEIGHT_FT): array
    {
        return [
            'area_sqft' => 0.0,
            'perimeter_lf' => 0.0,
            'baseboard_lf' => 0.0,
            'wall_height_ft' => $this->normalizeWallHeight($wallHeightFt),
            'wall_area_sqft' => 0.0,
            'door_count' => 0,
            'window_count' => 0,
            'opening_count' => 0,
            'drywall_openings' => 0,
            'inside_corners' => 0,
            'outside_corners' => 0,
            'polygon_count' => 0,
            'wall_thickness_in' => 0.0,
            'inside_area_sqft' => 0.0,
            'outside_area_sqft' => 0.0,
            'shared_wall_lf' => 0.0,
            'framing_lf' => 0.0,
            'cabinet_base_lf' => 0.0,
            'cabinet_upper_lf' => 0.0,
            'stairs_count' => 0,
            'stairs_treads' => 0,
            'stairs_run_lf' => 0.0,
            'stairs_quote_type' => 'standard',
        ] + app(RoomAreaFinishMath::class)->finishMetrics(
            $this->emptyCanvas(),
            0,
            $this->normalizeWallHeight($wallHeightFt),
            0.0
        );
    }

    public function outsideAreaSqFt(float $insideArea, float $perimeterLf, float $sharedLf, float $thicknessIn): float
    {
        if ($thicknessIn <= 0) {
            return round(max(0.0, $insideArea), 2);
        }
        $thicknessFt = $thicknessIn / 12.0;
        $exterior = $this->sharedWalls->exteriorLf($perimeterLf, $sharedLf);

        return round(max($insideArea, $insideArea + ($exterior * $thicknessFt)), 2);
    }

    public function normalizeWallHeight(mixed $height): float
    {
        $value = is_numeric($height) ? (float) $height : self::DEFAULT_WALL_HEIGHT_FT;
        if ($value < 1) {
            return self::DEFAULT_WALL_HEIGHT_FT;
        }
        if ($value > 20) {
            return 20.0;
        }

        return round($value, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     * @return array{
     *   door_count: int,
     *   window_count: int,
     *   opening_count: int,
     *   drywall_openings: int,
     *   baseboard_deduction_lf: float,
     *   wall_deduction_sqft: float
     * }
     */
    private function objectDeductionMetrics(array $objects, float $wallHeightFt): array
    {
        $doorCount = 0;
        $windowCount = 0;
        $openingCount = 0;
        $baseboardInches = 0.0;
        $wallDeduction = 0.0;

        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $type = isset($object['type']) && is_string($object['type'])
                ? strtolower(trim($object['type']))
                : '';
            if (! in_array($type, self::WALL_OBJECT_TYPES, true)) {
                continue;
            }

            $widthIn = isset($object['width']) ? (float) $object['width'] : 0.0;
            if ($widthIn <= 0) {
                continue;
            }

            $heightIn = $this->objectHeightInches($object, $type, $wallHeightFt);
            $openingArea = ($widthIn / 12.0) * ($heightIn / 12.0);

            if ($type === 'door') {
                $doorCount++;
                $baseboardInches += $widthIn;
                $wallDeduction += $openingArea;
            } elseif ($type === 'window') {
                $windowCount++;
                $wallDeduction += $openingArea;
            } else {
                $openingCount++;
                $baseboardInches += $widthIn;
            }
        }

        return [
            'door_count' => $doorCount,
            'window_count' => $windowCount,
            'opening_count' => $openingCount,
            'drywall_openings' => $doorCount + $windowCount + $openingCount,
            'baseboard_deduction_lf' => $baseboardInches / 12.0,
            'wall_deduction_sqft' => $wallDeduction,
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function objectHeightInches(array $object, string $type, float $wallHeightFt): float
    {
        return app(RoomAreaFinishMath::class)->objectHeightInches($object, $type, $wallHeightFt);
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     * @return array{
     *   cabinet_base_lf: float,
     *   cabinet_upper_lf: float,
     *   stairs_count: int,
     *   stairs_treads: int,
     *   stairs_run_lf: float,
     *   stairs_quote_type: string
     * }
     */
    public function libraryMetrics(array $objects): array
    {
        $baseIn = 0.0;
        $upperIn = 0.0;
        $stairsCount = 0;
        $treads = 0;
        $runIn = 0.0;
        $quoteType = 'standard';

        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $type = isset($object['type']) && is_string($object['type'])
                ? strtolower(trim($object['type']))
                : '';
            $subtype = isset($object['subtype']) && is_string($object['subtype'])
                ? strtolower(trim($object['subtype']))
                : '';

            if ($type === 'cabinet') {
                $width = isset($object['width']) ? (float) $object['width'] : 0.0;
                $depth = isset($object['depth']) ? (float) $object['depth'] : 0.0;
                if ($subtype === 'upper') {
                    $upperIn += max(0.0, $width);
                } elseif ($subtype === 'corner') {
                    $baseIn += max(0.0, $width) + max(0.0, $depth);
                } else {
                    $baseIn += max(0.0, $width);
                }
            } elseif ($type === 'stairs') {
                $stairsCount++;
                $depth = isset($object['depth']) ? (float) $object['depth'] : $this->defaultFreeSize('stairs', $subtype)['depth'];
                $runIn += max(0.0, $depth);
                $treads += $this->stairsTreadCount($object);
                if ($subtype === 'u') {
                    $quoteType = 'double_landing';
                } elseif ($subtype === 'l' && $quoteType !== 'double_landing') {
                    $quoteType = 'single_landing';
                }
            }
        }

        return [
            'cabinet_base_lf' => round($baseIn / 12.0, 2),
            'cabinet_upper_lf' => round($upperIn / 12.0, 2),
            'stairs_count' => $stairsCount,
            'stairs_treads' => max(0, $treads),
            'stairs_run_lf' => round($runIn / 12.0, 2),
            'stairs_quote_type' => $quoteType,
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function stairsTreadCount(array $object): int
    {
        $subtype = isset($object['subtype']) && is_string($object['subtype'])
            ? strtolower(trim($object['subtype']))
            : 'straight';
        $depth = isset($object['depth']) && is_numeric($object['depth'])
            ? (float) $object['depth']
            : $this->defaultFreeSize('stairs', $subtype)['depth'];
        $base = max(1, (int) round($depth / self::STAIR_TREAD_IN));

        return match ($subtype) {
            'l' => $base + 3,
            'u' => $base + 6,
            default => $base,
        };
    }

    public function isLayersLocked($canvas): bool
    {
        return is_array($canvas) && ! empty($canvas['editor']['layers_locked']);
    }

    /**
     * Keep the stored lock when the client omits canvas.editor.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $existing
     * @param  mixed  $rawInput
     * @return array<string, mixed>
     */
    public function preserveEditorLock(array $normalized, $existing, $rawInput): array
    {
        $existingLocked = is_array($existing) && ! empty($existing['editor']['layers_locked']);
        if (! $existingLocked) {
            return $normalized;
        }

        $rawEditor = is_array($rawInput) && isset($rawInput['editor']) && is_array($rawInput['editor'])
            ? $rawInput['editor']
            : null;
        $explicitUnlock = is_array($rawEditor)
            && array_key_exists('layers_locked', $rawEditor)
            && filter_var($rawEditor['layers_locked'], FILTER_VALIDATE_BOOLEAN) === false;

        if ($explicitUnlock) {
            return $normalized;
        }

        $editor = isset($normalized['editor']) && is_array($normalized['editor'])
            ? $normalized['editor']
            : [];
        $existingEditor = $this->normalizeEditorPrefs($existing['editor'] ?? []);
        if ($existingEditor !== null) {
            $editor = array_merge($existingEditor, $editor);
        }
        $editor['layers_locked'] = true;
        $normalized['editor'] = $editor;

        return $normalized;
    }

    /**
     * Keep stored vertices when a locked save is within the geometry tolerance
     * so sub-tolerance moves cannot accumulate across requests.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public function restoreStoredGeometryIfWithinTolerance(array $normalized, $existing, ?float $existingScale, ?float $incomingScale): array
    {
        if (! $this->isLayersLocked($existing) || ! is_array($existing)) {
            return $normalized;
        }
        if ($this->geometryDiffersInFeet($existing, $normalized, $existingScale, $incomingScale)) {
            return $normalized;
        }

        $factor = $this->scaleFactor($existingScale, $incomingScale);
        $normalized = $this->copyStoredVertices($normalized, $existing, $factor);
        if (isset($existing['background']) && is_array($existing['background'])
            && ! $this->backgroundLayoutDiffers($existing, $normalized, $existingScale, $incomingScale)) {
            $scaledBg = $this->scaleBackgroundLayout($existing['background'], $factor);
            if ($scaledBg !== null) {
                $normalized['background'] = $scaledBg;
            }
        }

        return $normalized;
    }

    /**
     * Keep the stored floor-plan layout when a locked save omits background.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public function preserveLockedBackground(array $normalized, $existing, ?float $existingScale = null, ?float $incomingScale = null): array
    {
        if (! $this->isLayersLocked($existing) || ! is_array($existing)) {
            return $normalized;
        }
        if (! isset($normalized['background']) && isset($existing['background'])) {
            $scaled = $this->scaleBackgroundLayout(
                $existing['background'],
                $this->scaleFactor($existingScale, $incomingScale)
            );
            $normalized['background'] = $scaled ?? $existing['background'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function scaleFactor(?float $existingScale, ?float $incomingScale): float
    {
        if ($existingScale === null || $existingScale <= 0 || $incomingScale === null || $incomingScale <= 0) {
            return 1.0;
        }

        return round($incomingScale, 4) / $existingScale;
    }

    /**
     * @param  array<string, mixed>|null  $background
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    private function scaleBackgroundLayout($background, float $factor): ?array
    {
        $norm = is_array($background) ? $this->normalizeBackground($background) : null;
        if ($norm === null) {
            return null;
        }
        foreach (['x', 'y', 'width', 'height'] as $key) {
            $norm[$key] = round($norm[$key] * $factor, 3);
        }

        return $norm;
    }

    private function copyStoredVertices(array $normalized, array $existing, float $factor = 1.0): array
    {
        $exGroups = $existing['groups'] ?? [];
        $inGroups = $normalized['groups'] ?? [];
        if (! is_array($exGroups) || ! is_array($inGroups) || count($exGroups) !== count($inGroups)) {
            return $normalized;
        }
        foreach ($exGroups as $gi => $exGroup) {
            $exPolys = is_array($exGroup) ? ($exGroup['polygons'] ?? []) : [];
            $inPolys = is_array($inGroups[$gi] ?? null) ? ($inGroups[$gi]['polygons'] ?? []) : [];
            if (! is_array($exPolys) || ! is_array($inPolys) || count($exPolys) !== count($inPolys)) {
                continue;
            }
            foreach ($exPolys as $pi => $exPoly) {
                $exVerts = is_array($exPoly) ? ($exPoly['vertices'] ?? []) : [];
                if (! is_array($exVerts) || ! isset($normalized['groups'][$gi]['polygons'][$pi])) {
                    continue;
                }
                $normalized['groups'][$gi]['polygons'][$pi]['vertices'] = array_map(function ($vertex) use ($factor) {
                    return [
                        'x' => ((float) ($vertex['x'] ?? 0)) * $factor,
                        'y' => ((float) ($vertex['y'] ?? 0)) * $factor,
                    ];
                }, $exVerts);
            }
        }

        return $normalized;
    }

    /**
     * When a saved room is locked, reject real-world geometry or scale changes
     * until the stored lock is already off. Unlocking in the same request as a
     * corner move is rejected. Area names and canvas-pixel resize (same feet)
     * are not geometry changes.
     *
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $incoming
     */
    public function lockedGeometryChangeMessage($existing, array $incoming, ?float $existingScale = null, ?float $incomingScale = null): ?string
    {
        if (! $this->isLayersLocked($existing)) {
            return null;
        }

        $exScale = $existingScale !== null && $existingScale > 0 ? $existingScale : null;
        $inScale = $incomingScale !== null && $incomingScale > 0 ? $incomingScale : $exScale;
        if ($exScale === null) {
            $exScale = $inScale;
        }

        if ($this->geometryDiffersInFeet($existing, $incoming, $exScale, $inScale)) {
            $pixelsSame = $this->vertexPixelsMatch($existing, $incoming);
            if ($pixelsSame && $existingScale !== null && $incomingScale !== null
                && abs($existingScale - $incomingScale) > 0.0001) {
                return 'Unlock layers to recalibrate scale.';
            }

            return 'Unlock layers to change walls or areas.';
        }

        if ($this->backgroundLayoutDiffers($existing, $incoming, $exScale, $inScale)) {
            return 'Unlock layers to change the floor plan.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $incoming
     */
    private function backgroundLayoutDiffers($existing, array $incoming, ?float $existingScale = null, ?float $incomingScale = null): bool
    {
        $incomingBg = $incoming['background'] ?? null;
        if (! is_array($incomingBg)) {
            return false;
        }
        $existingBg = is_array($existing) ? ($existing['background'] ?? null) : null;
        $incomingNorm = $this->normalizeBackground($incomingBg);
        $existingNorm = is_array($existingBg) ? $this->normalizeBackground($existingBg) : null;
        if ($incomingNorm === null) {
            return false;
        }
        if ($existingNorm === null) {
            return true;
        }

        $exScale = $existingScale !== null && $existingScale > 0 ? $existingScale : 1.0;
        $inScale = $incomingScale !== null && $incomingScale > 0 ? $incomingScale : $exScale;
        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (abs(($existingNorm[$key] / $exScale) - ($incomingNorm[$key] / $inScale)) > self::LOCK_GEOM_TOLERANCE_FT) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $incoming
     */
    private function geometryDiffersInFeet($existing, array $incoming, ?float $existingScale, ?float $incomingScale): bool
    {
        $exGroups = is_array($existing) ? ($existing['groups'] ?? []) : [];
        $inGroups = $incoming['groups'] ?? [];
        if (! is_array($exGroups) || ! is_array($inGroups)) {
            return true;
        }
        if (count($exGroups) !== count($inGroups)) {
            return true;
        }

        foreach ($exGroups as $gi => $exGroup) {
            $inGroup = $inGroups[$gi] ?? null;
            if (! is_array($exGroup) || ! is_array($inGroup)) {
                return true;
            }
            $exPolys = $exGroup['polygons'] ?? [];
            $inPolys = $inGroup['polygons'] ?? [];
            if (! is_array($exPolys) || ! is_array($inPolys) || count($exPolys) !== count($inPolys)) {
                return true;
            }
            foreach ($exPolys as $pi => $exPoly) {
                $inPoly = $inPolys[$pi] ?? null;
                if (! is_array($exPoly) || ! is_array($inPoly)) {
                    return true;
                }
                $exVerts = $exPoly['vertices'] ?? [];
                $inVerts = $inPoly['vertices'] ?? [];
                if (! is_array($exVerts) || ! is_array($inVerts) || count($exVerts) !== count($inVerts)) {
                    return true;
                }
                foreach ($exVerts as $vi => $exV) {
                    $inV = $inVerts[$vi] ?? null;
                    if (! is_array($exV) || ! is_array($inV)) {
                        return true;
                    }
                    $exFt = $this->pointInFeet($exV, $existingScale);
                    $inFt = $this->pointInFeet($inV, $incomingScale);
                    if ($exFt === null || $inFt === null) {
                        return true;
                    }
                    if (abs($exFt[0] - $inFt[0]) > self::LOCK_GEOM_TOLERANCE_FT
                        || abs($exFt[1] - $inFt[1]) > self::LOCK_GEOM_TOLERANCE_FT) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $incoming
     */
    private function vertexPixelsMatch($existing, array $incoming): bool
    {
        $exGroups = is_array($existing) ? ($existing['groups'] ?? []) : [];
        $inGroups = $incoming['groups'] ?? [];
        if (! is_array($exGroups) || ! is_array($inGroups) || count($exGroups) !== count($inGroups)) {
            return false;
        }
        foreach ($exGroups as $gi => $exGroup) {
            $inGroup = $inGroups[$gi] ?? null;
            $exPolys = is_array($exGroup) ? ($exGroup['polygons'] ?? []) : [];
            $inPolys = is_array($inGroup) ? ($inGroup['polygons'] ?? []) : [];
            if (! is_array($exPolys) || ! is_array($inPolys) || count($exPolys) !== count($inPolys)) {
                return false;
            }
            foreach ($exPolys as $pi => $exPoly) {
                $inPoly = $inPolys[$pi] ?? null;
                $exVerts = is_array($exPoly) ? ($exPoly['vertices'] ?? []) : [];
                $inVerts = is_array($inPoly) ? ($inPoly['vertices'] ?? []) : [];
                if (json_encode($exVerts) !== json_encode($inVerts)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array{0: float, 1: float}|null
     */
    private function pointInFeet(array $point, ?float $scale): ?array
    {
        if ($scale === null || $scale <= 0) {
            return null;
        }
        if (! isset($point['x'], $point['y']) || ! is_numeric($point['x']) || ! is_numeric($point['y'])) {
            return null;
        }

        return [(float) $point['x'] / $scale, (float) $point['y'] / $scale];
    }
}

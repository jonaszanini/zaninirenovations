<?php

namespace App\Services;

/**
 * Rejects openPlan3D save payloads that cannot be measured safely:
 * non-finite numbers, self-intersecting polygons, and impossible sizes.
 */
class OpenPlan3DPlanValidator
{
    public const MAX_ROOM_AREA_SQFT = 50000.0;

    public const MAX_WALL_LENGTH_FT = 500.0;

    public const MAX_THICKNESS_CM = 200.0;

    public const MAX_HEIGHT_CM = 2000.0;

    public const MAX_ITEM_WIDTH_CM = 10000.0;

    public const MAX_ITEM_DEPTH_CM = 10000.0;

    public const MAX_ITEM_RUN_CM = 10000.0;

    public const MAX_ITEM_LENGTH_CM = 10000.0;

    public const MAX_COORDINATE = 1000000.0;

    public const MAX_PAYLOAD_BYTES = 2097152; // 2 MB

    public const ALLOWED_UNITS = [
        'cm' => 1.0,
        'centimeter' => 1.0,
        'centimeters' => 1.0,
        'm' => 100.0,
        'meter' => 100.0,
        'meters' => 100.0,
        'mm' => 0.1,
        'millimeter' => 0.1,
        'millimeters' => 0.1,
        'in' => OpenPlan3DMeasurementBridge::CM_PER_INCH,
        'inch' => OpenPlan3DMeasurementBridge::CM_PER_INCH,
        'inches' => OpenPlan3DMeasurementBridge::CM_PER_INCH,
        'ft' => OpenPlan3DMeasurementBridge::CM_PER_FOOT,
        'feet' => OpenPlan3DMeasurementBridge::CM_PER_FOOT,
        'foot' => OpenPlan3DMeasurementBridge::CM_PER_FOOT,
    ];

    public function __construct(
        private ?PolygonAreaService $polygonArea = null,
    ) {
        $this->polygonArea = $polygonArea ?? app(PolygonAreaService::class);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>|null  $existingPlan
     * @return array{valid: bool, message: ?string, warning?: ?string}
     */
    public function validate(array $plan, ?array $existingPlan = null): array
    {
        $encoded = json_encode($plan);
        if ($encoded === false || strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            return ['valid' => false, 'message' => 'Floor plan data exceeds maximum allowed size.'];
        }

        $planUnitRaw = $plan['unit'] ?? $plan['units'] ?? null;
        if ($planUnitRaw !== null && $this->resolveUnitFactor($planUnitRaw) === null) {
            return ['valid' => false, 'message' => 'Invalid plan unit. Allowed units are cm, m, mm, in, ft.'];
        }

        $floors = $this->floors($plan);
        foreach ($floors as $floor) {
            $floorUnitRaw = $floor['unit'] ?? $floor['units'] ?? null;
            if ($floorUnitRaw !== null && $this->resolveUnitFactor($floorUnitRaw) === null) {
                return ['valid' => false, 'message' => 'Invalid plan unit. Allowed units are cm, m, mm, in, ft.'];
            }
        }

        $cmPerUnit = $this->cmPerUnit($plan, $floors);

        $warning = null;
        foreach ($floors as $floor) {
            $check = $this->validateFloor($floor, $cmPerUnit, $existingPlan);
            if (! $check['valid']) {
                return $check;
            }
            if (isset($check['warning'])) {
                $warning = $check['warning'];
            }
        }

        return ['valid' => true, 'message' => null, 'warning' => $warning];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    private function floors(array $plan): array
    {
        $floors = [];
        if (isset($plan['floor']) && is_array($plan['floor'])) {
            $floors[] = $plan['floor'];
        }
        foreach (['floors'] as $key) {
            if (! isset($plan[$key]) || ! is_array($plan[$key])) {
                continue;
            }
            foreach ($plan[$key] as $floor) {
                if (is_array($floor)) {
                    $floors[] = $floor;
                }
            }
        }
        if (isset($plan['project']['floors']) && is_array($plan['project']['floors'])) {
            foreach ($plan['project']['floors'] as $floor) {
                if (is_array($floor)) {
                    $floors[] = $floor;
                }
            }
        }
        if ($floors === [] && (isset($plan['walls']) || isset($plan['rooms']))) {
            $floors[] = $plan;
        }

        return $floors;
    }

    public function resolveUnitFactor(mixed $unit): ?float
    {
        if ($unit === null || $unit === '') {
            return 1.0;
        }
        if (! is_string($unit)) {
            return null;
        }
        $normalized = strtolower(trim($unit));

        return self::ALLOWED_UNITS[$normalized] ?? null;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $floors
     */
    private function cmPerUnit(array $plan, array $floors): float
    {
        $planUnitRaw = $plan['unit'] ?? $plan['units'] ?? null;
        $factor = $this->resolveUnitFactor($planUnitRaw);
        if ($factor !== null && $planUnitRaw !== null && $planUnitRaw !== '') {
            return $factor;
        }
        if ($floors !== []) {
            $floorUnitRaw = $floors[0]['unit'] ?? $floors[0]['units'] ?? null;
            $floorFactor = $this->resolveUnitFactor($floorUnitRaw);
            if ($floorFactor !== null && $floorUnitRaw !== null && $floorUnitRaw !== '') {
                return $floorFactor;
            }
        }

        return 1.0;
    }

    /**
     * @param  array<string, mixed>  $floor
     * @param  array<string, mixed>|null  $existingPlan
     * @return array{valid: bool, message: ?string, warning?: ?string}
     */
    private function validateFloor(array $floor, float $cmPerUnit, ?array $existingPlan = null): array
    {
        if (isset($floor['walls']) && ! is_array($floor['walls'])) {
            return ['valid' => false, 'message' => 'Floor walls must be an array.'];
        }
        foreach ($floor['walls'] ?? [] as $wall) {
            if (! is_array($wall)) {
                return ['valid' => false, 'message' => 'Wall entry must be an object.'];
            }
            $check = $this->validateWall($wall, $cmPerUnit);
            if (! $check['valid']) {
                return $check;
            }
        }

        $warning = null;
        if (isset($floor['rooms']) && ! is_array($floor['rooms'])) {
            return ['valid' => false, 'message' => 'Floor rooms must be an array.'];
        }
        foreach ($floor['rooms'] ?? [] as $room) {
            if (! is_array($room)) {
                return ['valid' => false, 'message' => 'Room entry must be an object.'];
            }
            $check = $this->validateRoom($room, $cmPerUnit, $existingPlan);
            if (! $check['valid']) {
                return $check;
            }
            if (isset($check['warning'])) {
                $warning = $check['warning'];
            }
        }

        foreach (['doors', 'windows', 'furniture', 'stairs'] as $listKey) {
            if (isset($floor[$listKey]) && ! is_array($floor[$listKey])) {
                return ['valid' => false, 'message' => 'Floor '.$listKey.' must be an array.'];
            }
            foreach ($floor[$listKey] ?? [] as $item) {
                if (! is_array($item)) {
                    $singular = match ($listKey) {
                        'furniture' => 'Furniture',
                        'stairs' => 'Stairs',
                        'doors' => 'Door',
                        'windows' => 'Window',
                        default => ucfirst(rtrim($listKey, 's')),
                    };
                    return ['valid' => false, 'message' => $singular.' entry must be an object.'];
                }
                $check = $this->validateSizedItem($item, $listKey, $cmPerUnit);
                if (! $check['valid']) {
                    return $check;
                }
            }
        }

        return ['valid' => true, 'message' => null, 'warning' => $warning];
    }

    /**
     * @param  array<string, mixed>  $wall
     * @return array{valid: bool, message: ?string}
     */
    private function validateWall(array $wall, float $cmPerUnit): array
    {
        if (isset($wall['start']) || isset($wall['end'])) {
            if (! is_array($wall['start'] ?? null) || ! is_array($wall['end'] ?? null)) {
                return ['valid' => false, 'message' => 'Each wall must have finite start and end coordinates.'];
            }
            foreach (['start', 'end'] as $end) {
                $check = $this->validatePoint($wall[$end], 'Wall '.$end);
                if (! $check['valid']) {
                    return $check;
                }
            }
            $dx = (float) $wall['end']['x'] - (float) $wall['start']['x'];
            $dy = (float) $wall['end']['y'] - (float) $wall['start']['y'];
            $lengthCm = hypot($dx, $dy) * $cmPerUnit;
            $lengthFt = $lengthCm / OpenPlan3DMeasurementBridge::CM_PER_FOOT;
            if ($lengthFt > self::MAX_WALL_LENGTH_FT) {
                return ['valid' => false, 'message' => 'Wall length is impossibly large.'];
            }
        }

        return $this->validateSizedItem($wall, 'wall', $cmPerUnit);
    }

    /**
     * @param  array<string, mixed>  $room
     * @param  array<string, mixed>|null  $existingPlan
     * @return array{valid: bool, message: ?string, warning?: ?string}
     */
    private function validateRoom(array $room, float $cmPerUnit, ?array $existingPlan = null): array
    {
        $roomLabel = $this->roomLabel($room);
        $poly = $room['polygon'] ?? $room['points'] ?? null;
        if (! is_array($poly) || $poly === []) {
            return ['valid' => true, 'message' => null];
        }

        $points = [];
        foreach ($poly as $vertex) {
            if (! is_array($vertex)) {
                return ['valid' => false, 'message' => 'Room polygon points must be coordinates.'];
            }
            $check = $this->validatePoint($vertex, 'Room polygon');
            if (! $check['valid']) {
                return $check;
            }
            $points[] = ['x' => (float) $vertex['x'], 'y' => (float) $vertex['y']];
        }

        if (count($points) < 3) {
            return ['valid' => true, 'message' => null];
        }

        $rawCount = count($points);
        $points = $this->polygonArea->normalizeRing($points);

        if (count($points) < 3) {
            if ($this->isDegenerateZeroFootprint($points, $rawCount)) {
                return ['valid' => true, 'message' => null];
            }

            return [
                'valid' => false,
                'message' => sprintf('Room “%s” needs at least three non-collinear corners.', $roomLabel),
            ];
        }

        if (! $this->polygonArea->isSimplePolygon($points)) {
            return [
                'valid' => false,
                'message' => sprintf('Room “%s” shape crosses itself — adjust corners.', $roomLabel),
            ];
        }

        $areaSqCm = $this->polygonArea->shoelaceArea($points) * ($cmPerUnit * $cmPerUnit);
        $areaSqFt = $areaSqCm / OpenPlan3DMeasurementBridge::SQCM_PER_SQFT;
        if ($areaSqFt > self::MAX_ROOM_AREA_SQFT) {
            return ['valid' => false, 'message' => 'Room area is impossibly large.'];
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{valid: bool, message: ?string}
     */
    private function validateSizedItem(array $item, string $kind, float $cmPerUnit = 1.0): array
    {
        foreach (['width', 'height', 'depth', 'thickness', 'thickness_cm', 'height_cm', 'offset', 'run', 'length'] as $field) {
            if (! array_key_exists($field, $item) || $item[$field] === null || $item[$field] === '') {
                continue;
            }
            if ($this->isBadNumber($item[$field])) {
                return ['valid' => false, 'message' => ucfirst($kind).' '.$field.' must be a finite number.'];
            }
            $value = (float) $item[$field];
            if ($value < 0) {
                return ['valid' => false, 'message' => ucfirst($kind).' '.$field.' cannot be negative.'];
            }
        }

        $thickness = $item['thickness'] ?? null;
        if ($thickness !== null && $thickness !== '') {
            $thicknessCm = (float) $thickness * $cmPerUnit;
            if ($thicknessCm > self::MAX_THICKNESS_CM) {
                return ['valid' => false, 'message' => ucfirst($kind).' thickness is impossibly large.'];
            }
        }
        $thicknessCmDirect = $item['thickness_cm'] ?? null;
        if ($thicknessCmDirect !== null && $thicknessCmDirect !== '' && (float) $thicknessCmDirect > self::MAX_THICKNESS_CM) {
            return ['valid' => false, 'message' => ucfirst($kind).' thickness is impossibly large.'];
        }

        $height = $item['height'] ?? null;
        if ($height !== null && $height !== '') {
            $heightCm = (float) $height * $cmPerUnit;
            if ($heightCm > self::MAX_HEIGHT_CM) {
                return ['valid' => false, 'message' => ucfirst($kind).' height is impossibly large.'];
            }
        }
        $heightCmDirect = $item['height_cm'] ?? null;
        if ($heightCmDirect !== null && $heightCmDirect !== '' && (float) $heightCmDirect > self::MAX_HEIGHT_CM) {
            return ['valid' => false, 'message' => ucfirst($kind).' height is impossibly large.'];
        }

        $width = $item['width'] ?? null;
        if ($width !== null && $width !== '') {
            $widthCm = (float) $width * $cmPerUnit;
            if ($widthCm > self::MAX_ITEM_WIDTH_CM) {
                return ['valid' => false, 'message' => ucfirst($kind).' width is impossibly large.'];
            }
        }

        $depth = $item['depth'] ?? null;
        if ($depth !== null && $depth !== '') {
            $depthCm = (float) $depth * $cmPerUnit;
            if ($depthCm > self::MAX_ITEM_DEPTH_CM) {
                return ['valid' => false, 'message' => ucfirst($kind).' depth is impossibly large.'];
            }
        }

        $run = $item['run'] ?? null;
        if ($run !== null && $run !== '') {
            $runCm = (float) $run * $cmPerUnit;
            if ($runCm > self::MAX_ITEM_RUN_CM) {
                return ['valid' => false, 'message' => ucfirst($kind).' run is impossibly large.'];
            }
        }

        $length = $item['length'] ?? null;
        if ($length !== null && $length !== '') {
            $lengthCm = (float) $length * $cmPerUnit;
            if ($lengthCm > self::MAX_ITEM_LENGTH_CM) {
                return ['valid' => false, 'message' => ucfirst($kind).' length is impossibly large.'];
            }
        }

        $offset = $item['offset'] ?? null;
        if ($offset !== null && $offset !== '' && abs((float) $offset) > self::MAX_COORDINATE) {
            return ['valid' => false, 'message' => ucfirst($kind).' offset is impossibly large.'];
        }

        if (isset($item['position'])) {
            $position = $item['position'];
            if (is_array($position)) {
                $check = $this->validatePoint($position, ucfirst($kind).' position');
                if (! $check['valid']) {
                    return $check;
                }
            } elseif (is_numeric($position) && ! $this->isBadNumber($position)) {
                if (abs((float) $position) > self::MAX_COORDINATE) {
                    return ['valid' => false, 'message' => ucfirst($kind).' position is impossibly large.'];
                }
            } else {
                return ['valid' => false, 'message' => ucfirst($kind).' position must be coordinates.'];
            }
        }

        foreach (['x', 'y', 'z'] as $axis) {
            if (array_key_exists($axis, $item) && $item[$axis] !== null && $item[$axis] !== '') {
                if ($this->isBadNumber($item[$axis])) {
                    return ['valid' => false, 'message' => ucfirst($kind).' '.$axis.' must be a finite number.'];
                }
                if (abs((float) $item[$axis]) > self::MAX_COORDINATE) {
                    return ['valid' => false, 'message' => ucfirst($kind).' '.$axis.' coordinate is impossibly large.'];
                }
            }
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array{valid: bool, message: ?string}
     */
    private function validatePoint(array $point, string $label): array
    {
        foreach (['x', 'y'] as $axis) {
            if (! array_key_exists($axis, $point)) {
                return ['valid' => false, 'message' => $label.' is missing '.$axis.'.'];
            }
            if ($this->isBadNumber($point[$axis])) {
                return ['valid' => false, 'message' => $label.' '.$axis.' must be a finite number.'];
            }
            if (abs((float) $point[$axis]) > self::MAX_COORDINATE) {
                return ['valid' => false, 'message' => $label.' '.$axis.' coordinate is impossibly large.'];
            }
        }
        if (array_key_exists('z', $point) && $point['z'] !== null && $point['z'] !== '') {
            if ($this->isBadNumber($point['z'])) {
                return ['valid' => false, 'message' => $label.' z must be a finite number.'];
            }
            if (abs((float) $point['z']) > self::MAX_COORDINATE) {
                return ['valid' => false, 'message' => $label.' z coordinate is impossibly large.'];
            }
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * @param  list<array{x: float, y: float}>  $points
     * @param  array<string, mixed>|null  $existingPlan
     */
    private function isExistingUnchangedPolygon(array $points, ?array $existingPlan, ?string $roomId = null): bool
    {
        if ($existingPlan === null || $roomId === null || trim($roomId) === '') {
            return false;
        }

        $existingRooms = $this->collectRooms($existingPlan);

        foreach ($existingRooms as $er) {
            if (isset($er['id']) && (string) $er['id'] === (string) $roomId) {
                $erPoly = $this->extractPolygonPoints($er);
                if ($this->polygonsMatch($points, $erPoly)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    private function collectRooms(array $plan): array
    {
        $rooms = [];
        foreach ($this->floors($plan) as $floor) {
            if (isset($floor['rooms']) && is_array($floor['rooms'])) {
                foreach ($floor['rooms'] as $room) {
                    if (is_array($room)) {
                        $rooms[] = $room;
                    }
                }
            }
        }

        return $rooms;
    }

    /**
     * @param  array<string, mixed>  $room
     * @return list<array{x: float, y: float}>
     */
    private function extractPolygonPoints(array $room): array
    {
        $poly = $room['polygon'] ?? $room['points'] ?? null;
        if (! is_array($poly)) {
            return [];
        }
        $points = [];
        foreach ($poly as $vertex) {
            if (is_array($vertex) && isset($vertex['x'], $vertex['y']) && is_numeric($vertex['x']) && is_numeric($vertex['y'])) {
                $points[] = ['x' => (float) $vertex['x'], 'y' => (float) $vertex['y']];
            }
        }

        return $points;
    }

    /**
     * @param  list<array{x: float, y: float}>  $polyA
     * @param  list<array{x: float, y: float}>  $polyB
     */
    private function polygonsMatch(array $polyA, array $polyB): bool
    {
        if (count($polyA) !== count($polyB) || count($polyA) === 0) {
            return false;
        }
        for ($i = 0; $i < count($polyA); $i++) {
            if (abs($polyA[$i]['x'] - $polyB[$i]['x']) > 0.001 ||
                abs($polyA[$i]['y'] - $polyB[$i]['y']) > 0.001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{x: float, y: float}>  $normalized
     */
    private function isDegenerateZeroFootprint(array $normalized, int $rawVertexCount): bool
    {
        if ($rawVertexCount < 1) {
            return true;
        }
        if (count($normalized) <= 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $room
     */
    private function roomLabel(array $room): string
    {
        $name = trim((string) ($room['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $id = trim((string) ($room['id'] ?? ''));

        return $id !== '' ? $id : 'Room';
    }

    /**
     * @param  list<array{x: mixed, y: mixed}>  $vertices
     */
    public function validateCanvasVertices(array $vertices, string $contextLabel = 'Canvas', ?string $roomName = null): ?string
    {
        $named = trim((string) ($roomName ?? ''));
        if ($named !== '') {
            $contextLabel = 'Room “'.$named.'” floor plan';
        }

        foreach ($vertices as $index => $vertex) {
            if (! is_array($vertex)) {
                return $contextLabel.' polygon points must be coordinates.';
            }
            foreach (['x', 'y'] as $axis) {
                if (! array_key_exists($axis, $vertex)) {
                    return $contextLabel.' point '.($index + 1).' is missing '.$axis.'.';
                }
                $label = $contextLabel.' point '.($index + 1).' '.$axis;
                if ($this->isBadNumber($vertex[$axis])) {
                    return $label.' must be a finite number.';
                }
                if (abs((float) $vertex[$axis]) > self::MAX_COORDINATE) {
                    return $label.' coordinate is impossibly large.';
                }
            }
        }

        return null;
    }

    public function isBadNumber(mixed $value): bool
    {
        if (is_bool($value) || is_array($value)) {
            return true;
        }
        if (! is_numeric($value)) {
            return true;
        }
        if (is_string($value) && preg_match('/^(nan|inf|-inf|infinity|-infinity)$/i', trim($value)) === 1) {
            return true;
        }

        return ! is_finite((float) $value);
    }
}

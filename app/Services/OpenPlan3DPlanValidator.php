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

    public function __construct(
        private ?PolygonAreaService $polygonArea = null,
    ) {
        $this->polygonArea = $polygonArea ?? app(PolygonAreaService::class);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{valid: bool, message: ?string}
     */
    public function validate(array $plan): array
    {
        $floors = $this->floors($plan);
        $cmPerUnit = $this->cmPerUnit($plan, $floors);

        foreach ($floors as $floor) {
            $check = $this->validateFloor($floor, $cmPerUnit);
            if (! $check['valid']) {
                return $check;
            }
        }

        return ['valid' => true, 'message' => null];
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

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $floors
     */
    private function cmPerUnit(array $plan, array $floors): float
    {
        $unit = strtolower((string) ($plan['unit'] ?? $plan['units'] ?? ''));
        if ($unit === '' && $floors !== []) {
            $unit = strtolower((string) ($floors[0]['unit'] ?? $floors[0]['units'] ?? ''));
        }

        return match ($unit) {
            'm', 'meter', 'meters' => 100.0,
            'in', 'inch', 'inches' => OpenPlan3DMeasurementBridge::CM_PER_INCH,
            'ft', 'feet', 'foot' => OpenPlan3DMeasurementBridge::CM_PER_FOOT,
            default => 1.0,
        };
    }

    /**
     * @param  array<string, mixed>  $floor
     * @return array{valid: bool, message: ?string}
     */
    private function validateFloor(array $floor, float $cmPerUnit): array
    {
        foreach ($floor['walls'] ?? [] as $wall) {
            if (! is_array($wall)) {
                continue;
            }
            $check = $this->validateWall($wall, $cmPerUnit);
            if (! $check['valid']) {
                return $check;
            }
        }

        foreach ($floor['rooms'] ?? [] as $room) {
            if (! is_array($room)) {
                continue;
            }
            $check = $this->validateRoom($room, $cmPerUnit);
            if (! $check['valid']) {
                return $check;
            }
        }

        foreach (['doors', 'windows', 'furniture', 'stairs'] as $listKey) {
            foreach ($floor[$listKey] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $check = $this->validateSizedItem($item, $listKey);
                if (! $check['valid']) {
                    return $check;
                }
            }
        }

        return ['valid' => true, 'message' => null];
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

        return $this->validateSizedItem($wall, 'wall');
    }

    /**
     * @param  array<string, mixed>  $room
     * @return array{valid: bool, message: ?string}
     */
    private function validateRoom(array $room, float $cmPerUnit): array
    {
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

        if (! $this->polygonArea->isSimplePolygon($points)) {
            return ['valid' => false, 'message' => 'Room shape crosses itself — adjust corners.'];
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
    private function validateSizedItem(array $item, string $kind): array
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

        $thickness = $item['thickness'] ?? $item['thickness_cm'] ?? null;
        if ($thickness !== null && $thickness !== '' && (float) $thickness > self::MAX_THICKNESS_CM) {
            return ['valid' => false, 'message' => ucfirst($kind).' thickness is impossibly large.'];
        }

        $height = $item['height'] ?? $item['height_cm'] ?? null;
        if ($height !== null && $height !== '' && (float) $height > self::MAX_HEIGHT_CM) {
            return ['valid' => false, 'message' => ucfirst($kind).' height is impossibly large.'];
        }

        if (isset($item['position']) && is_array($item['position'])) {
            $check = $this->validatePoint($item['position'], ucfirst($kind).' position');
            if (! $check['valid']) {
                return $check;
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
        }
        if (array_key_exists('z', $point) && $point['z'] !== null && $this->isBadNumber($point['z'])) {
            return ['valid' => false, 'message' => $label.' z must be a finite number.'];
        }

        return ['valid' => true, 'message' => null];
    }

    private function isBadNumber(mixed $value): bool
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

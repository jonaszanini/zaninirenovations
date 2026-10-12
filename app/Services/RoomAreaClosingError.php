<?php

namespace App\Services;

/**
 * Closing-error math for typed-wall room outlines.
 *
 * Headings use canvas coordinates: 0° = east (+x), 90° = south (+y).
 * Keep in sync with the Type walls helpers in public/js/admin-utilities-room-area.js.
 */
class RoomAreaClosingError
{
    public const DEFAULT_THRESHOLD_INCHES = 6.0;

    public const DEFAULT_SQUARE_SNAP_DEG = 8.0;

    private RoomAreaMeasurementParser $parser;

    public function __construct(?RoomAreaMeasurementParser $parser = null)
    {
        $this->parser = $parser ?? new RoomAreaMeasurementParser;
    }

    /**
     * @param  list<array{length_ft: float, heading_deg: float}>  $walls
     * @return array{
     *   points: list<array{x: float, y: float}>,
     *   dx_ft: float,
     *   dy_ft: float,
     *   gap_ft: float,
     *   gap_inches: float,
     *   label: string,
     *   can_autoclose: bool
     * }
     */
    public function analyze(array $walls, float $thresholdInches = self::DEFAULT_THRESHOLD_INCHES): array
    {
        $points = $this->walk($walls);
        $last = $points[count($points) - 1];
        $start = $points[0];
        $dx = $start['x'] - $last['x'];
        $dy = $start['y'] - $last['y'];
        $gapFt = hypot($dx, $dy);
        $gapIn = $gapFt * RoomAreaMeasurementParser::INCHES_PER_FOOT;
        $threshold = $thresholdInches > 0 ? $thresholdInches : self::DEFAULT_THRESHOLD_INCHES;

        return [
            'points' => $points,
            'dx_ft' => round($dx, 6),
            'dy_ft' => round($dy, 6),
            'gap_ft' => round($gapFt, 6),
            'gap_inches' => round($gapIn, 4),
            'label' => $this->gapLabel($gapIn),
            'can_autoclose' => $gapIn > (1 / 16) && $gapIn < $threshold,
        ];
    }

    /**
     * Walk walls from the origin. Units are feet.
     *
     * @param  list<array{length_ft?: float, heading_deg?: float}>  $walls
     * @return list<array{x: float, y: float}>
     */
    public function walk(array $walls, float $startX = 0.0, float $startY = 0.0): array
    {
        $points = [['x' => $startX, 'y' => $startY]];
        $x = $startX;
        $y = $startY;

        foreach ($walls as $wall) {
            if (! is_array($wall)) {
                continue;
            }
            $length = (float) ($wall['length_ft'] ?? 0);
            if ($length <= 0) {
                continue;
            }
            $heading = (float) ($wall['heading_deg'] ?? 0);
            $rad = deg2rad($heading);
            $x += $length * cos($rad);
            $y += $length * sin($rad);
            $points[] = ['x' => $x, 'y' => $y];
        }

        return $points;
    }

    /**
     * Distribute the closing vector across intermediate vertices so the last
     * point lands on the start. Returns vertices without a duplicate close point.
     *
     * @param  list<array{x: float, y: float}>  $points
     * @return list<array{x: float, y: float}>
     */
    public function distribute(array $points): array
    {
        $n = count($points);
        if ($n < 3) {
            return $points;
        }

        $start = $points[0];
        $last = $points[$n - 1];
        $dx = $start['x'] - $last['x'];
        $dy = $start['y'] - $last['y'];
        $steps = $n - 1;

        $out = [$start];
        for ($i = 1; $i < $n; $i++) {
            $t = $i / $steps;
            $out[] = [
                'x' => $points[$i]['x'] + ($dx * $t),
                'y' => $points[$i]['y'] + ($dy * $t),
            ];
        }

        $adjustedLast = $out[$n - 1];
        if (hypot($adjustedLast['x'] - $start['x'], $adjustedLast['y'] - $start['y']) < 1e-6) {
            array_pop($out);
        }

        return array_map(static function (array $p): array {
            return [
                'x' => round((float) $p['x'], 6),
                'y' => round((float) $p['y'], 6),
            ];
        }, $out);
    }

    /**
     * Snap near-orthogonal headings, rebuild the traverse, then distribute.
     *
     * @param  list<array{length_ft: float, heading_deg: float}>  $walls
     * @return array{
     *   walls: list<array{length_ft: float, heading_deg: float}>,
     *   points: list<array{x: float, y: float}>
     * }
     */
    public function autoSquare(array $walls, float $snapDeg = self::DEFAULT_SQUARE_SNAP_DEG): array
    {
        $snapped = [];
        foreach ($walls as $wall) {
            if (! is_array($wall)) {
                continue;
            }
            $length = (float) ($wall['length_ft'] ?? 0);
            if ($length <= 0) {
                continue;
            }
            $heading = (float) ($wall['heading_deg'] ?? 0);
            $snapped[] = [
                'length_ft' => $length,
                'heading_deg' => $this->snapHeading($heading, $snapDeg),
            ];
        }

        $points = $this->distribute($this->walk($snapped));

        return [
            'walls' => $snapped,
            'points' => $points,
        ];
    }

    public function gapLabel(float $gapInches): string
    {
        $abs = abs($gapInches);
        if ($abs < 1 / 16) {
            return 'closed';
        }

        return 'off by '.$this->parser->formatClosingGap($abs);
    }

    public function snapHeading(float $headingDeg, float $snapDeg = self::DEFAULT_SQUARE_SNAP_DEG): float
    {
        $norm = fmod($headingDeg, 360.0);
        if ($norm < 0) {
            $norm += 360.0;
        }
        $nearest = round($norm / 90.0) * 90.0;
        if ($nearest >= 360) {
            $nearest = 0.0;
        }
        $delta = abs($norm - $nearest);
        $delta = min($delta, 360.0 - $delta);
        if ($delta <= $snapDeg) {
            return $nearest;
        }

        return $norm;
    }

    /**
     * Next heading after a turn. Positive custom angles turn right (clockwise).
     */
    public function nextHeading(float $currentDeg, string $turn, float $customDeg = 0.0): float
    {
        $turn = strtolower(trim($turn));
        $next = match ($turn) {
            'left' => $currentDeg - 90.0,
            'custom' => $currentDeg + $customDeg,
            default => $currentDeg + 90.0,
        };
        $next = fmod($next, 360.0);
        if ($next < 0) {
            $next += 360.0;
        }

        return $next;
    }
}

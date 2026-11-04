<?php

namespace App\Services;

/**
 * Zanini overlays on openPlan3D plans: excluded area zones, bulkheads, wall finish takeoffs.
 */
class RoomAreaOpenPlan3DExtensions
{
    public const CM_PER_FOOT = 30.48;

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $finish
     */
    public function applyToMetrics(array $metrics, array $plan, array $finish = [], float $cmPerUnit = 1.0): array
    {
        $cmPerUnit = $cmPerUnit > 0 ? $cmPerUnit : 1.0;
        $zanini = $this->normalizeZanini($this->extractZanini($plan));

        $metrics = $this->applyExcludedZones($metrics, $zanini, $cmPerUnit, $finish);
        $metrics = $this->applyBulkheadMetrics($metrics, $zanini, $cmPerUnit);
        $metrics = $this->applyWallFinishMetrics($metrics, $zanini);

        return $metrics;
    }

    /**
     * @param  list<array<string, mixed>>  $rects
     */
    public function rectsUnionAreaSqFt(array $rects, float $cmPerUnit = 1.0): float
    {
        $normalized = [];
        foreach ($rects as $rect) {
            $n = $this->normalizeLegacyRect($rect);
            if ($n !== null) {
                $normalized[] = $n;
            }
        }

        return $this->rectangleUnionAreaSqFt($normalized, $cmPerUnit);
    }

    /**
     * @param  list<array{x0: float, y0: float, x1: float, y1: float}>  $rects
     */
    public function rectangleUnionAreaSqFt(array $rects, float $cmPerUnit = 1.0): float
    {
        if ($rects === []) {
            return 0.0;
        }

        $xs = [];
        foreach ($rects as $rect) {
            $xs[] = (float) $rect['x0'];
            $xs[] = (float) $rect['x1'];
        }
        $xs = array_values(array_unique($xs, SORT_REGULAR));
        sort($xs);

        $cmPerFoot = self::CM_PER_FOOT * ($cmPerUnit > 0 ? $cmPerUnit : 1.0);
        $totalCm2 = 0.0;

        for ($i = 0; $i < count($xs) - 1; $i++) {
            $x0 = $xs[$i];
            $x1 = $xs[$i + 1];
            $dx = $x1 - $x0;
            if ($dx <= 0) {
                continue;
            }

            $spans = [];
            foreach ($rects as $rect) {
                if ($rect['x0'] < $x1 && $rect['x1'] > $x0) {
                    $spans[] = [(float) $rect['y0'], (float) $rect['y1']];
                }
            }
            if ($spans === []) {
                continue;
            }

            usort($spans, fn ($a, $b) => $a[0] <=> $b[0]);
            $cur0 = $spans[0][0];
            $cur1 = $spans[0][1];
            $cover = 0.0;
            for ($j = 1; $j < count($spans); $j++) {
                if ($spans[$j][0] <= $cur1) {
                    $cur1 = max($cur1, $spans[$j][1]);
                } else {
                    $cover += $cur1 - $cur0;
                    $cur0 = $spans[$j][0];
                    $cur1 = $spans[$j][1];
                }
            }
            $cover += $cur1 - $cur0;
            $totalCm2 += $dx * $cover;
        }

        $sqFt = $totalCm2 / ($cmPerFoot * $cmPerFoot);

        return round(max(0.0, $sqFt), 2);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function extractZanini(array $plan): array
    {
        if (isset($plan['zanini']) && is_array($plan['zanini'])) {
            return $plan['zanini'];
        }

        $floor = $this->resolveActiveFloor($plan);
        if ($floor !== null && isset($floor['zanini']) && is_array($floor['zanini'])) {
            return $floor['zanini'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $zanini
     * @return array<string, mixed>
     */
    public function mergeZaniniIntoPlan(array $plan, array $zanini): array
    {
        $plan['zanini'] = $zanini;

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $zanini
     * @param  array<string, mixed>  $finish
     */
    private function applyExcludedZones(array $metrics, array $zanini, float $cmPerUnit, array $finish): array
    {
        $excludeRects = array_merge(
            $zanini['excluded_area_zones'] ?? [],
            $this->zonesToLegacyRects($zanini['zones'] ?? [], true)
        );
        $includeRects = $this->zonesToLegacyRects($zanini['zones'] ?? [], false);

        $excludedSqFt = $this->rectsUnionAreaSqFt($excludeRects, $cmPerUnit);
        $includedSqFt = $this->rectsUnionAreaSqFt($includeRects, $cmPerUnit);

        $area = (float) ($metrics['area_sqft'] ?? 0.0);
        $area = round(max(0.0, $area - $excludedSqFt + $includedSqFt), 2);

        $metrics['excluded_area_sqft'] = $excludedSqFt;
        $metrics['area_sqft'] = $area;
        $metrics['inside_area_sqft'] = $area;
        $metrics['ceiling_area_sqft'] = $area;

        $finishMath = app(RoomAreaFinishMath::class);
        $waste = (float) ($metrics['flooring_waste_percent'] ?? ($finish['flooring_waste_percent'] ?? RoomAreaFinishMath::DEFAULT_WASTE_PERCENT));
        $box = (float) ($metrics['flooring_box_sqft'] ?? ($finish['flooring_box_sqft'] ?? RoomAreaFinishMath::DEFAULT_BOX_SQFT));
        $metrics['flooring_with_waste_sqft'] = $finishMath->flooringWithWasteSqFt($area, $waste);
        $metrics['flooring_boxes'] = $finishMath->flooringBoxes($area, $waste, $box);

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $zanini
     */
    private function applyBulkheadMetrics(array $metrics, array $zanini, float $cmPerUnit): array
    {
        $framingLf = 0.0;
        $drywallSqFt = 0.0;
        $cornerBeadLf = 0.0;

        foreach ($zanini['bulkheads'] ?? [] as $bulkhead) {
            if (! is_array($bulkhead)) {
                continue;
            }
            $rect = $bulkhead['rect'] ?? $bulkhead;
            $normalized = $this->normalizeLegacyRect(is_array($rect) ? $rect : []);
            if ($normalized === null) {
                continue;
            }

            $lengthFt = abs($normalized['x1'] - $normalized['x0']) * $cmPerUnit / self::CM_PER_FOOT;
            $widthFt = abs($normalized['y1'] - $normalized['y0']) * $cmPerUnit / self::CM_PER_FOOT;
            $dropFt = max(0.0, (float) ($bulkhead['drop_height_ft'] ?? 1.0));
            $longFt = max($lengthFt, $widthFt);
            $shortFt = min($lengthFt, $widthFt);

            $perimeterLf = round(2.0 * ($lengthFt + $widthFt), 2);
            $framingLf += $perimeterLf;
            $drywallSqFt += round(2.0 * ($longFt * $dropFt + $shortFt * $dropFt) + ($longFt * $shortFt), 2);
            $cornerBeadLf += round(4.0 * $dropFt, 2);
        }

        $metrics['bulkhead_framing_lf'] = round($framingLf, 2);
        $metrics['bulkhead_drywall_sqft'] = round($drywallSqFt, 2);
        $metrics['bulkhead_corner_bead_lf'] = round($cornerBeadLf, 2);
        $metrics['bulkhead_lf'] = $metrics['bulkhead_framing_lf'];
        $metrics['bulkhead_drywall_sqft'] = $metrics['bulkhead_drywall_sqft'];

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $zanini
     */
    private function applyWallFinishMetrics(array $metrics, array $zanini): array
    {
        $walls = is_array($metrics['walls'] ?? null) ? $metrics['walls'] : [];
        $boundaryIds = array_map('strval', is_array($metrics['boundary_wall_ids'] ?? null) ? $metrics['boundary_wall_ids'] : []);
        $boundaryMap = array_fill_keys($boundaryIds, true);
        $finishes = is_array($zanini['wallFinishes'] ?? null) ? $zanini['wallFinishes'] : [];

        $drywall = 0.0;
        $insulation = 0.0;
        $studs = 0.0;
        $baseboard = 0.0;

        if ($finishes === [] && $walls !== []) {
            foreach ($walls as $wall) {
                if (! is_array($wall)) {
                    continue;
                }
                $wallId = (string) ($wall['id'] ?? '');
                $isBoundary = isset($boundaryMap[$wallId]);
                $lengthLf = (float) ($wall['length_lf'] ?? 0.0);
                $netSqFt = (float) ($wall['net_area_sqft'] ?? 0.0);
                $drywall += $netSqFt;
                if ($isBoundary) {
                    $insulation += $netSqFt;
                    $baseboard += $lengthLf;
                } else {
                    $studs += $lengthLf;
                    $baseboard += $lengthLf * 2.0;
                }
            }
        } else {
            foreach ($walls as $wall) {
                if (! is_array($wall)) {
                    continue;
                }
                $wallId = (string) ($wall['id'] ?? '');
                $isBoundary = isset($boundaryMap[$wallId]);
                $lengthLf = (float) ($wall['length_lf'] ?? 0.0);
                $netSqFt = (float) ($wall['net_area_sqft'] ?? 0.0);
                $flags = is_array($finishes[$wallId] ?? null) ? $finishes[$wallId] : [];

                if ($this->flagEnabled($flags, 'drywall', true)) {
                    $drywall += $netSqFt;
                }
                if ($this->flagEnabled($flags, 'insulation', $isBoundary)) {
                    $insulation += $netSqFt;
                }
                if ($this->flagEnabled($flags, 'studs', ! $isBoundary)) {
                    $studs += $lengthLf;
                }
                if ($this->flagEnabled($flags, 'baseboard', $isBoundary)) {
                    $baseboard += $lengthLf;
                } elseif ($this->flagEnabled($flags, 'baseboard', ! $isBoundary)) {
                    $baseboard += $lengthLf * 2.0;
                }
            }
        }

        $metrics['finish_drywall_sqft'] = round(max(0.0, $drywall), 2);
        $metrics['finish_insulation_sqft'] = round(max(0.0, $insulation), 2);
        $metrics['finish_stud_lf'] = round(max(0.0, $studs), 2);
        $metrics['finish_baseboard_lf'] = round(max(0.0, $baseboard), 2);
        $metrics['finish_casing_lf'] = round((float) ($metrics['casing_lf'] ?? 0.0), 2);

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeZanini(array $raw): array
    {
        return [
            'excluded_area_zones' => is_array($raw['excluded_area_zones'] ?? null) ? $raw['excluded_area_zones'] : [],
            'zones' => is_array($raw['zones'] ?? null) ? $raw['zones'] : [],
            'bulkheads' => is_array($raw['bulkheads'] ?? null) ? $raw['bulkheads'] : [],
            'wallFinishes' => is_array($raw['wallFinishes'] ?? null) ? $raw['wallFinishes'] : [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $zones
     * @return list<array<string, mixed>>
     */
    private function zonesToLegacyRects(array $zones, bool $exclude): array
    {
        $out = [];
        foreach ($zones as $zone) {
            if (! is_array($zone)) {
                continue;
            }
            $isExclude = ! empty($zone['exclude']);
            if ($isExclude !== $exclude) {
                continue;
            }
            $rect = $this->normalizeLegacyRect($zone);
            if ($rect !== null) {
                $out[] = [
                    'x1' => $rect['x0'],
                    'y1' => $rect['y0'],
                    'x2' => $rect['x1'],
                    'y2' => $rect['y1'],
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $rect
     * @return array{x0: float, y0: float, x1: float, y1: float}|null
     */
    private function normalizeLegacyRect(array $rect): ?array
    {
        if (isset($rect['x1'], $rect['y1'], $rect['x2'], $rect['y2'])) {
            $x0 = (float) $rect['x1'];
            $y0 = (float) $rect['y1'];
            $x1 = (float) $rect['x2'];
            $y1 = (float) $rect['y2'];
        } elseif (isset($rect['x0'], $rect['y0'], $rect['x1'], $rect['y1'])) {
            $x0 = (float) $rect['x0'];
            $y0 = (float) $rect['y0'];
            $x1 = (float) $rect['x1'];
            $y1 = (float) $rect['y1'];
        } elseif (isset($rect['x'], $rect['y'], $rect['width'], $rect['height'])) {
            $x0 = (float) $rect['x'];
            $y0 = (float) $rect['y'];
            $x1 = $x0 + (float) $rect['width'];
            $y1 = $y0 + (float) $rect['height'];
        } else {
            return null;
        }

        if ($x0 === $x1 || $y0 === $y1) {
            return null;
        }

        return [
            'x0' => min($x0, $x1),
            'y0' => min($y0, $y1),
            'x1' => max($x0, $x1),
            'y1' => max($y0, $y1),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>|null
     */
    private function resolveActiveFloor(array $plan): ?array
    {
        if (isset($plan['floor']) && is_array($plan['floor'])) {
            return $plan['floor'];
        }
        if (! isset($plan['floors']) || ! is_array($plan['floors'])) {
            return null;
        }
        $activeId = $plan['activeFloorId'] ?? null;
        if ($activeId) {
            foreach ($plan['floors'] as $floor) {
                if (is_array($floor) && ($floor['id'] ?? null) === $activeId) {
                    return $floor;
                }
            }
        }

        return is_array($plan['floors'][0] ?? null) ? $plan['floors'][0] : null;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private function flagEnabled(array $flags, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $flags)) {
            return $default;
        }

        return (bool) $flags[$key];
    }
}

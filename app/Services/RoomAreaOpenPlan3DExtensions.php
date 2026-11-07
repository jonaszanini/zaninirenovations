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
        $roomPolyCm = is_array($metrics['room_polygon_cm'] ?? null) ? $metrics['room_polygon_cm'] : [];
        $excludeRects = array_merge(
            $zanini['excluded_area_zones'] ?? [],
            $this->zonesToLegacyRects($zanini['zones'] ?? [], true)
        );

        $normalizedExclude = [];
        foreach ($excludeRects as $rect) {
            $n = $this->normalizeLegacyRect(is_array($rect) ? $rect : []);
            if ($n !== null) {
                $normalizedExclude[] = $n;
            }
        }

        $excludedSqFt = 0.0;
        if ($normalizedExclude !== []) {
            $excludedSqFt = $this->excludedZonesAreaSqFt($normalizedExclude, $roomPolyCm, $cmPerUnit);
        }
        $excludedSqFt = round(max(0.0, $excludedSqFt), 2);

        $area = (float) ($metrics['area_sqft'] ?? 0.0);
        $area = round(max(0.0, $area - $excludedSqFt), 2);

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
        $paintSqFt = 0.0;
        $paintMarked = 0;
        $paintOff = 0;
        $paintUnassigned = 0;
        $paintSeen = false;

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
            $againstWall = ! array_key_exists('against_wall', $bulkhead) || (bool) $bulkhead['against_wall'];

            $takeoff = $this->bulkheadTakeoff($longFt, $shortFt, $dropFt, $againstWall);
            $framingLf += $takeoff['framing_lf'];
            $drywallSqFt += $takeoff['drywall_sqft'];
            $cornerBeadLf += $takeoff['corner_bead_lf'];

            if (array_key_exists('paint', $bulkhead)) {
                $paintSeen = true;
                if ($this->flagEnabled($bulkhead, 'paint', false)) {
                    $paintMarked++;
                    $paintSqFt += $takeoff['drywall_sqft'];
                } else {
                    $paintOff++;
                }
            } else {
                $paintUnassigned++;
            }
        }

        $metrics['bulkhead_framing_lf'] = round($framingLf, 2);
        $metrics['bulkhead_drywall_sqft'] = round($drywallSqFt, 2);
        $metrics['bulkhead_corner_bead_lf'] = round($cornerBeadLf, 2);
        $metrics['bulkhead_lf'] = $metrics['bulkhead_framing_lf'];
        $metrics['paint_bulkheads_marked'] = $paintMarked;
        $metrics['paint_bulkheads_explicit_off'] = $paintOff;
        $metrics['paint_bulkheads_unassigned'] = $paintUnassigned;
        if ($paintSeen) {
            $metrics['bulkhead_paint_sqft'] = round(max(0.0, $paintSqFt), 2);
        } else {
            unset($metrics['bulkhead_paint_sqft']);
        }

        return $metrics;
    }

    /**
     * @return array{framing_lf: float, drywall_sqft: float, corner_bead_lf: float}
     */
    private function bulkheadTakeoff(float $longFt, float $shortFt, float $dropFt, bool $againstWall): array
    {
        $longFt = max(0.0, $longFt);
        $shortFt = max(0.0, $shortFt);
        $dropFt = max(0.0, $dropFt);

        $drywallSqFt = ($longFt * $shortFt) + ($longFt * $dropFt);
        if (! $againstWall) {
            $drywallSqFt += ($longFt * $dropFt) + (2.0 * $shortFt * $dropFt);
        }

        $cornerBeadLf = $againstWall ? $longFt : (2.0 * $longFt + 2.0 * $shortFt);

        $spacingFt = 16.0 / 12.0;
        $crossCount = $longFt > 0 ? max(0, (int) ceil($longFt / $spacingFt) - 1) : 0;
        $framingLf = (2.0 * $longFt) + ($crossCount * $shortFt);

        return [
            'framing_lf' => round($framingLf, 2),
            'drywall_sqft' => round(max(0.0, $drywallSqFt), 2),
            'corner_bead_lf' => round(max(0.0, $cornerBeadLf), 2),
        ];
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
        $paint = 0.0;
        $paintMarked = 0;
        $paintOff = 0;
        $paintUnassigned = 0;
        $paintSeen = false;

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
                $paintUnassigned++;
                if ($isBoundary) {
                    $insulation += $netSqFt;
                } else {
                    $studs += $lengthLf;
                }
            }
            $baseboard = (float) ($metrics['baseboard_lf'] ?? 0.0);
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
                if (array_key_exists('paint', $flags)) {
                    $paintSeen = true;
                    if ($this->flagEnabled($flags, 'paint', false)) {
                        $paintMarked++;
                        $paint += $netSqFt;
                    } else {
                        $paintOff++;
                    }
                } else {
                    $paintUnassigned++;
                }
                if ($this->flagEnabled($flags, 'insulation', $isBoundary)) {
                    $insulation += $netSqFt;
                }
                if ($this->flagEnabled($flags, 'studs', ! $isBoundary)) {
                    $studs += $lengthLf;
                }
                if ($this->flagEnabled($flags, 'baseboard', true)) {
                    $sides = $isBoundary ? 1 : (int) ($flags['baseboardSides'] ?? 2);
                    if (! $isBoundary) {
                        $sides = max(1, min(2, $sides));
                    }
                    $baseboard += $lengthLf * (float) $sides;
                }
            }
        }

        $bulkheadPaintSeen = array_key_exists('bulkhead_paint_sqft', $metrics);
        $bulkheadPaint = $bulkheadPaintSeen ? (float) $metrics['bulkhead_paint_sqft'] : 0.0;
        $metrics['finish_drywall_sqft'] = round(max(0.0, $drywall), 2);
        $metrics['finish_insulation_sqft'] = round(max(0.0, $insulation), 2);
        $metrics['finish_stud_lf'] = round(max(0.0, $studs), 2);
        $metrics['finish_baseboard_lf'] = round(max(0.0, $baseboard), 2);
        $metrics['finish_casing_lf'] = round((float) ($metrics['casing_lf'] ?? 0.0), 2);
        $metrics['paint_walls_marked'] = $paintMarked;
        $metrics['paint_walls_explicit_off'] = $paintOff;
        $metrics['paint_walls_unassigned'] = $paintUnassigned;
        if ($paintSeen || $bulkheadPaintSeen) {
            $metrics['finish_paint_sqft'] = round(max(0.0, $paint + $bulkheadPaint), 2);
        } else {
            unset($metrics['finish_paint_sqft']);
        }

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
            if (! empty($zone['mergedInto'])) {
                continue;
            }
            $isExclude = ! empty($zone['exclude']);
            if ($isExclude !== $exclude) {
                continue;
            }
            foreach ($this->collectZoneMemberLegacyRects($zone, $zones) as $rect) {
                $out[] = $rect;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $zone
     * @param  list<array<string, mixed>>  $zones
     * @return list<array<string, mixed>>
     */
    private function collectZoneMemberLegacyRects(array $zone, array $zones): array
    {
        $out = [];
        $seen = [];
        $push = function (array $shape) use (&$out, &$seen): void {
            $rect = $this->normalizeLegacyRect($shape);
            if ($rect === null) {
                return;
            }
            $key = implode('|', [$rect['x0'], $rect['y0'], $rect['x1'], $rect['y1']]);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = [
                'x1' => $rect['x0'],
                'y1' => $rect['y0'],
                'x2' => $rect['x1'],
                'y2' => $rect['y1'],
            ];
        };

        if (! empty($zone['rects']) && is_array($zone['rects'])) {
            foreach ($zone['rects'] as $member) {
                if (is_array($member)) {
                    $push($member);
                }
            }
        } else {
            $push($zone);
        }

        foreach ($zones as $merged) {
            if (! is_array($merged) || empty($merged['mergedInto'])) {
                continue;
            }
            if ((string) $merged['mergedInto'] !== (string) ($zone['id'] ?? '')) {
                continue;
            }
            $push($merged);
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
        $activeId = $plan['activeFloorId'] ?? ($plan['project']['activeFloorId'] ?? null);
        $activeId = is_string($activeId) ? $activeId : null;

        $resolveEntry = function (?array $floors) use ($activeId): array {
            if (! is_array($floors) || $floors === []) {
                return ['floor' => null, 'matched_by_id' => false];
            }
            if ($activeId) {
                foreach ($floors as $floor) {
                    if (is_array($floor) && ($floor['id'] ?? null) === $activeId) {
                        return ['floor' => $floor, 'matched_by_id' => true];
                    }
                }

                return ['floor' => null, 'matched_by_id' => false, 'active_missing' => true];
            }

            return [
                'floor' => is_array($floors[0] ?? null) ? $floors[0] : null,
                'matched_by_id' => false,
            ];
        };

        $planFloors = $resolveEntry($plan['floors'] ?? null);
        if ($planFloors['matched_by_id'] && is_array($planFloors['floor'])) {
            return $planFloors['floor'];
        }
        if (is_array($planFloors['floor']) && empty($planFloors['active_missing'])) {
            return $planFloors['floor'];
        }

        $projectFloors = $resolveEntry(
            isset($plan['project']['floors']) && is_array($plan['project']['floors']) ? $plan['project']['floors'] : null
        );
        if ($projectFloors['matched_by_id'] && is_array($projectFloors['floor'])) {
            return $projectFloors['floor'];
        }
        if (is_array($projectFloors['floor']) && empty($projectFloors['active_missing'])) {
            return $projectFloors['floor'];
        }

        if (isset($plan['floor']) && is_array($plan['floor'])) {
            return $plan['floor'];
        }

        return is_array($planFloors['floor'] ?? null)
            ? $planFloors['floor']
            : (is_array($projectFloors['floor'] ?? null) ? $projectFloors['floor'] : null);
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

    /**
     * @param  array{x0: float, y0: float, x1: float, y1: float}  $rect
     * @param  list<array{x: float, y: float}>  $polygonCm
     */
    public function rectInsidePolygonAreaSqFt(array $rect, array $polygonCm, float $cmPerUnit = 1.0): float
    {
        return $this->rectUnionPolygonIntersectionAreaSqFt([$rect], $polygonCm, $cmPerUnit);
    }

    /**
     * @param  list<array{x0: float, y0: float, x1: float, y1: float}>  $rects
     * @param  list<array{x: float, y: float}>  $polygonCm
     */
    private function excludedZonesAreaSqFt(array $rects, array $polygonCm, float $cmPerUnit): float
    {
        if ($rects === []) {
            return 0.0;
        }
        if ($polygonCm === []) {
            return $this->rectangleUnionAreaSqFt($rects, $cmPerUnit);
        }

        return $this->rectUnionPolygonIntersectionAreaSqFt($rects, $polygonCm, $cmPerUnit);
    }

    /**
     * @param  list<array{x0: float, y0: float, x1: float, y1: float}>  $rects
     * @param  list<array{x: float, y: float}>  $polygonCm
     */
    private function rectUnionPolygonIntersectionAreaSqFt(array $rects, array $polygonCm, float $cmPerUnit): float
    {
        $poly = [];
        foreach ($polygonCm as $pt) {
            if (! is_array($pt)) {
                continue;
            }
            $poly[] = ['x' => (float) ($pt['x'] ?? 0), 'y' => (float) ($pt['y'] ?? 0)];
        }
        if (count($poly) < 3) {
            return $this->rectangleUnionAreaSqFt($rects, $cmPerUnit);
        }

        $cmPerFoot = self::CM_PER_FOOT * ($cmPerUnit > 0 ? $cmPerUnit : 1.0);
        $xs = [];
        foreach ($rects as $rect) {
            $xs[] = (float) $rect['x0'];
            $xs[] = (float) $rect['x1'];
        }
        foreach ($poly as $pt) {
            $xs[] = $pt['x'];
        }
        $xs = array_values(array_unique($xs, SORT_REGULAR));
        sort($xs);

        $totalCm2 = 0.0;
        for ($i = 0; $i < count($xs) - 1; $i++) {
            $x0 = $xs[$i];
            $x1 = $xs[$i + 1];
            $dx = $x1 - $x0;
            if ($dx <= 0) {
                continue;
            }
            $xMid = ($x0 + $x1) / 2.0;
            $rectSpans = $this->rectUnionYSpansAtX($rects, $xMid);
            $polySpans = $this->polygonYSpansAtX($poly, $xMid);
            $totalCm2 += $dx * $this->spanListsIntersectionLength($rectSpans, $polySpans);
        }

        $sqFt = $totalCm2 / ($cmPerFoot * $cmPerFoot);

        return round(max(0.0, $sqFt), 2);
    }

    /**
     * @param  list<array{x0: float, y0: float, x1: float, y1: float}>  $rects
     * @return list<array{0: float, 1: float}>
     */
    private function rectUnionYSpansAtX(array $rects, float $x): array
    {
        $spans = [];
        foreach ($rects as $rect) {
            if ($rect['x0'] < $x && $rect['x1'] > $x) {
                $spans[] = [(float) $rect['y0'], (float) $rect['y1']];
            }
        }

        return $this->mergeYSpans($spans);
    }

    /**
     * @param  list<array{x: float, y: float}>  $poly
     * @return list<array{0: float, 1: float}>
     */
    private function polygonYSpansAtX(array $poly, float $x): array
    {
        $ys = [];
        $count = count($poly);
        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $x1 = $poly[$i]['x'];
            $y1 = $poly[$i]['y'];
            $x2 = $poly[$j]['x'];
            $y2 = $poly[$j]['y'];
            if ($x1 === $x2) {
                continue;
            }
            $minX = min($x1, $x2);
            $maxX = max($x1, $x2);
            if ($x < $minX || $x >= $maxX) {
                continue;
            }
            $t = ($x - $x1) / ($x2 - $x1);
            $ys[] = $y1 + $t * ($y2 - $y1);
        }
        sort($ys);
        $spans = [];
        for ($k = 0; $k + 1 < count($ys); $k += 2) {
            $spans[] = [$ys[$k], $ys[$k + 1]];
        }

        return $spans;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $spans
     * @return list<array{0: float, 1: float}>
     */
    private function mergeYSpans(array $spans): array
    {
        if ($spans === []) {
            return [];
        }
        usort($spans, fn ($a, $b) => $a[0] <=> $b[0]);
        $cur0 = $spans[0][0];
        $cur1 = $spans[0][1];
        $merged = [];
        for ($i = 1; $i < count($spans); $i++) {
            if ($spans[$i][0] <= $cur1) {
                $cur1 = max($cur1, $spans[$i][1]);
            } else {
                $merged[] = [$cur0, $cur1];
                $cur0 = $spans[$i][0];
                $cur1 = $spans[$i][1];
            }
        }
        $merged[] = [$cur0, $cur1];

        return $merged;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $b
     */
    private function spanListsIntersectionLength(array $a, array $b): float
    {
        $len = 0.0;
        foreach ($a as $sa) {
            foreach ($b as $sb) {
                $y0 = max($sa[0], $sb[0]);
                $y1 = min($sa[1], $sb[1]);
                if ($y1 > $y0) {
                    $len += $y1 - $y0;
                }
            }
        }

        return $len;
    }
}

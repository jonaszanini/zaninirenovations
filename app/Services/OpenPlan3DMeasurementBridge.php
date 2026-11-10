<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Measurements bridge for openPlan3D floor plans.
 *
 * Computes per-room measurements (floor area, perimeter, net wall area,
 * casing LF, baseboard LF, ceiling area, opening counts, corners, flooring boxes, etc.)
 * matching the exact calculation rules and rounding of the classic Room Area engine.
 */
class OpenPlan3DMeasurementBridge
{
    public const CM_PER_INCH = 2.54;

    public const CM_PER_FOOT = 30.48;

    public const SQCM_PER_SQFT = 929.0304;

    public const DEFAULT_WALL_HEIGHT_FT = 8.0;

    public const DEFAULT_DOOR_HEIGHT_IN = 80.0;

    public const DEFAULT_WINDOW_HEIGHT_IN = 36.0;

    public const DEFAULT_OPENING_HEIGHT_IN = 84.0;

    public const DEFAULT_WALL_THICKNESS_IN = 4.5;

    public function __construct(
        private ?RoomAreaFinishMath $finishMath = null,
        private ?PolygonAreaService $polygonArea = null,
        private ?RoomAreaCanvasData $canvasData = null,
        private ?RoomAreaOpenPlan3DExtensions $extensions = null,
        private ?RoomAreaInteriorGeometry $interiorGeometry = null,
    ) {
        $this->finishMath = $finishMath ?? app(RoomAreaFinishMath::class);
        $this->polygonArea = $polygonArea ?? app(PolygonAreaService::class);
        $this->canvasData = $canvasData ?? app(RoomAreaCanvasData::class);
        $this->extensions = $extensions ?? app(RoomAreaOpenPlan3DExtensions::class);
        $this->interiorGeometry = $interiorGeometry ?? app(RoomAreaInteriorGeometry::class);
    }

    /**
     * Detects if the given payload is an openPlan3D model.
     */
    public function isPlan(mixed $data): bool
    {
        if (! is_array($data) || $data === []) {
            return false;
        }

        if (isset($data['format']) && is_string($data['format']) && strtolower(trim($data['format'])) === 'openplan3d') {
            return true;
        }

        if (isset($data['engine']) && is_string($data['engine']) && strtolower(trim($data['engine'])) === 'openplan3d') {
            return true;
        }

        if (isset($data['floors']) && is_array($data['floors'])) {
            return true;
        }

        if (isset($data['floor']) && is_array($data['floor'])) {
            return true;
        }

        if (isset($data['project']) && is_array($data['project']) && isset($data['project']['floors'])) {
            return true;
        }

        // Single floor structure
        if (isset($data['walls']) && is_array($data['walls']) && (isset($data['rooms']) || isset($data['doors']) || isset($data['windows']))) {
            return true;
        }

        return false;
    }

    /**
     * Compute layout metrics for an openPlan3D plan (or room within the plan).
     *
     * @param  array<string, mixed>  $plan
     * @param  string|null  $roomId
     * @param  array<string, mixed>  $finish
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function layoutMetrics(
        array $plan,
        ?string $roomId = null,
        array $finish = [],
        array $options = []
    ): array {
        $floor = $this->resolveFloor($plan);
        if ($floor === null) {
            $wallHeight = (float) ($finish['wall_height_ft'] ?? self::DEFAULT_WALL_HEIGHT_FT);

            return $this->emptyLayoutMetrics($wallHeight);
        }

        $unit = strtolower((string) ($options['unit'] ?? $plan['units'] ?? $floor['units'] ?? 'cm'));
        $cmPerUnit = match ($unit) {
            'm', 'meter', 'meters' => 100.0,
            'mm', 'millimeter', 'millimeters' => 0.1,
            'in', 'inch', 'inches' => self::CM_PER_INCH,
            'ft', 'feet', 'foot' => self::CM_PER_FOOT,
            default => 1.0, // cm
        };

        $walls = $this->indexWalls($floor['walls'] ?? []);
        if ($walls === []) {
            $wallHeight = (float) ($finish['wall_height_ft'] ?? self::DEFAULT_WALL_HEIGHT_FT);

            return $this->emptyLayoutMetrics($wallHeight);
        }

        $resolvedRooms = $this->resolveRooms($floor, $walls, $cmPerUnit);
        if ($resolvedRooms === []) {
            throw new InvalidArgumentException('Room geometry could not be measured.');
        }

        if ($roomId === null && count($resolvedRooms) > 1) {
            $totalInsideArea = 0.0;
            $totalPerimeter = 0.0;
            $totalBaseboard = 0.0;
            $totalNetWallArea = 0.0;
            $totalDoorCount = 0;
            $totalWindowCount = 0;
            $totalOpeningCount = 0;
            $totalDrywallOpenings = 0;
            $totalInsideCorners = 0;
            $totalOutsideCorners = 0;
            $totalPolygonCount = count($resolvedRooms);
            $totalSharedWallLf = 0.0;
            $totalFramingLf = 0.0;
            $totalPartitionLf = 0.0;
            $totalOutsideArea = 0.0;
            $totalCeilingArea = 0.0;
            $totalCasingLf = 0.0;
            $allWalls = [];
            $firstMetrics = null;

            foreach ($resolvedRooms as $r) {
                $sub = $this->layoutMetrics($plan, (string) ($r['id'] ?? $r['name']), $finish);
                $firstMetrics ??= $sub;
                $totalInsideArea += $sub['area_sqft'];
                $totalPerimeter += $sub['perimeter_lf'];
                $totalBaseboard += $sub['baseboard_lf'];
                $totalNetWallArea += $sub['net_wall_area_sqft'];
                $totalDoorCount += $sub['door_count'];
                $totalWindowCount += $sub['window_count'];
                $totalOpeningCount += $sub['opening_count'];
                $totalDrywallOpenings += $sub['drywall_openings'];
                $totalInsideCorners += $sub['inside_corners'];
                $totalOutsideCorners += $sub['outside_corners'];
                $totalSharedWallLf += $sub['shared_wall_lf'];
                $totalFramingLf += $sub['framing_lf'];
                $totalPartitionLf += $sub['partition_lf'] ?? 0;
                $totalOutsideArea += $sub['outside_area_sqft'];
                $totalCeilingArea += $sub['ceiling_area_sqft'];
                $totalCasingLf += $sub['casing_lf'];
                foreach ($sub['walls'] as $w) {
                    $allWalls[] = $w;
                }
            }

            $firstMetrics ??= $this->emptyLayoutMetrics();
            $wastePercent = $firstMetrics['flooring_waste_percent'];
            $boxSqFt = $firstMetrics['flooring_box_sqft'];
            $flooringBoxes = $this->finishMath->flooringBoxes($totalInsideArea, $wastePercent, $boxSqFt);
            $flooringWithWasteSqFt = $this->finishMath->flooringWithWasteSqFt($totalInsideArea, $wastePercent);

            $library = $this->resolveLibraryMetrics($floor, $cmPerUnit);

            return $this->extensions->applyToMetrics([
                'area_sqft' => $this->roundTo($totalInsideArea, 2),
                'perimeter_lf' => $this->roundTo($totalPerimeter, 2),
                'baseboard_lf' => $this->roundTo($totalBaseboard, 2),
                'wall_height_ft' => $firstMetrics['wall_height_ft'],
                'wall_area_sqft' => $this->roundTo($totalNetWallArea, 2),
                'door_count' => $totalDoorCount,
                'window_count' => $totalWindowCount,
                'opening_count' => $totalOpeningCount,
                'drywall_openings' => $totalDrywallOpenings,
                'inside_corners' => $totalInsideCorners,
                'outside_corners' => $totalOutsideCorners,
                'polygon_count' => $totalPolygonCount,
                'wall_thickness_in' => $firstMetrics['wall_thickness_in'],
                'inside_area_sqft' => $this->roundTo($totalInsideArea, 2),
                'outside_area_sqft' => $this->roundTo($totalOutsideArea, 2),
                'shared_wall_lf' => $this->roundTo($totalSharedWallLf, 2),
                'framing_lf' => $this->roundTo($totalFramingLf, 2),
                'partition_lf' => $this->roundTo($totalPartitionLf, 2),
                'cabinet_base_lf' => $library['cabinet_base_lf'],
                'cabinet_upper_lf' => $library['cabinet_upper_lf'],
                'stairs_count' => $library['stairs_count'],
                'stairs_treads' => $library['stairs_treads'],
                'stairs_run_lf' => $library['stairs_run_lf'],
                'stairs_quote_type' => $library['stairs_quote_type'],
                'ceiling_height_ft' => $firstMetrics['ceiling_height_ft'],
                'ceiling_area_sqft' => $this->roundTo($totalCeilingArea, 2),
                'net_wall_area_sqft' => $this->roundTo($totalNetWallArea, 2),
                'walls' => $allWalls,
                'flooring_waste_percent' => $wastePercent,
                'flooring_direction' => $firstMetrics['flooring_direction'],
                'flooring_box_sqft' => $boxSqFt,
                'flooring_boxes' => $flooringBoxes,
                'flooring_with_waste_sqft' => $flooringWithWasteSqFt,
                'casing_lf' => $this->roundTo($totalCasingLf, 2),
            ], $plan, $finish, $cmPerUnit);
        }

        // Pick matching room or first room
        $selectedRoom = null;
        if ($roomId !== null && $roomId !== '') {
            foreach ($resolvedRooms as $r) {
                if (($r['id'] ?? '') === $roomId || ($r['name'] ?? '') === $roomId) {
                    $selectedRoom = $r;
                    break;
                }
            }
        }
        if ($selectedRoom === null) {
            $selectedRoom = $resolvedRooms[0];
        }

        $polygonVertices = $selectedRoom['polygon'] ?? [];
        if (count($polygonVertices) < 3) {
            throw new InvalidArgumentException('Room geometry could not be measured.');
        }

        // Room finish and heights
        $roomDetails = is_array($selectedRoom['details'] ?? null) ? $selectedRoom['details'] : [];
        $rawWallHeight = $finish['wall_height_ft']
            ?? $selectedRoom['wall_height_ft']
            ?? ($selectedRoom['wall_height'] ?? null)
            ?? ($selectedRoom['height'] ?? null);
        if ($rawWallHeight === null && isset($walls[array_keys($walls)[0]]['height'])) {
            $rawWallHeight = ((float) $walls[array_keys($walls)[0]]['height'] * $cmPerUnit) / self::CM_PER_FOOT;
        }
        $wallHeightFt = $this->canvasData->normalizeWallHeight($rawWallHeight ?? self::DEFAULT_WALL_HEIGHT_FT);

        $rawCeilingHeight = $finish['ceiling_height_ft']
            ?? (isset($roomDetails['ceilingHeight']) && is_numeric($roomDetails['ceilingHeight']) ? (((float) $roomDetails['ceilingHeight'] * $cmPerUnit) / self::CM_PER_FOOT) : null)
            ?? $wallHeightFt;
        $ceilingHeightFt = $this->finishMath->ceilingHeightFt($rawCeilingHeight, $wallHeightFt);

        $boundaryWallIds = array_values(array_map(
            'strval',
            $selectedRoom['boundary_wall_ids'] ?? $selectedRoom['walls'] ?? []
        ));
        $allWallsList = array_values($walls);
        $alreadyInterior = $this->interiorGeometry->roomUsesInteriorPolygonDirectly($selectedRoom);

        $interiorRingCm = $alreadyInterior
            ? $polygonVertices
            : $this->interiorGeometry->interiorPolygonCm($polygonVertices, $boundaryWallIds, $walls, $cmPerUnit);
        if (! $alreadyInterior && count($interiorRingCm) < 3) {
            $interiorRingCm = $polygonVertices;
        }

        $verticesFt = [];
        foreach ($interiorRingCm as $pt) {
            $xCm = (float) ($pt['x'] ?? 0) * $cmPerUnit;
            $yCm = (float) ($pt['y'] ?? 0) * $cmPerUnit;
            $verticesFt[] = [
                'x' => $xCm / self::CM_PER_FOOT,
                'y' => $yCm / self::CM_PER_FOOT,
            ];
        }

        $areaValidation = $this->polygonArea->validateRoom($verticesFt, 1.0);
        if (! $areaValidation['valid']) {
            throw new InvalidArgumentException($areaValidation['message'] ?? 'Room geometry could not be measured.');
        }
        $insideAreaSqFt = $this->roundTo((float) $areaValidation['area_sqft'], 2);
        if ($insideAreaSqFt <= 0.01) {
            if ($this->polygonArea->isCollapsedZeroFootprint($verticesFt)) {
                $insideAreaSqFt = 0.0;
            } else {
                throw new InvalidArgumentException('Room shape crosses itself — adjust corners.');
            }
        }

        $ceilingAreaSqFt = $this->finishMath->ceilingAreaSqFt($insideAreaSqFt);

        $corners = $this->polygonArea->cornerCounts($verticesFt);
        $insideCorners = $corners['inside'];
        $outsideCorners = $corners['outside'];

        // Room walls and perimeter
        $roomWallIds = $selectedRoom['walls'] ?? [];
        $boundaryWalls = $this->resolveBoundaryWalls($selectedRoom, $walls, $cmPerUnit, $allWallsList);
        $partitionWalls = $selectedRoom['partition_walls'] ?? [];

        $perimeter = 0.0;
        foreach ($boundaryWalls as $bw) {
            $perimeter += $bw['length_ft'];
        }
        $perimeter = $this->roundTo($perimeter, 2);

        // Openings associated with this room's walls
        $allOpenings = $this->resolveFloorOpenings($floor, $cmPerUnit);
        $allRoomWallIds = array_merge(
            array_column($boundaryWalls, 'id'),
            array_column($partitionWalls, 'id')
        );
        $wallIdSet = array_flip($allRoomWallIds);
        $boundaryWallIdMap = array_flip(array_column($boundaryWalls, 'id'));
        $openingsByWall = [];
        $doorCount = 0;
        $windowCount = 0;
        $openingCount = 0;
        $baseboardDeductionInches = 0.0;

        foreach ($allOpenings as $op) {
            $wId = $op['wallId'];
            if (! isset($wallIdSet[$wId])) {
                continue;
            }
            $openingsByWall[$wId][] = $op;

            $type = $op['type'];
            $isBoundaryWall = isset($boundaryWallIdMap[$wId]);
            if ($type === 'door') {
                $doorCount++;
                if ($isBoundaryWall) {
                    $baseboardDeductionInches += $op['width_in'];
                }
            } elseif ($type === 'window') {
                $windowCount++;
            } else {
                $openingCount++;
                if ($isBoundaryWall) {
                    $baseboardDeductionInches += $op['width_in'];
                }
            }
        }

        $drywallOpenings = $doorCount + $windowCount + $openingCount;
        $baseboardDeductionLf = $this->roundTo($baseboardDeductionInches / 12.0, 2);
        $baseboardLf = max(0.0, $this->roundTo($perimeter - $baseboardDeductionLf, 2));

        // Casing LF
        $casingLf = 0.0;
        foreach ($allOpenings as $op) {
            $wId = $op['wallId'];
            if (! isset($wallIdSet[$wId])) {
                continue;
            }
            $casingSides = $this->resolveCasingSides($op);
            $casingLf += $this->finishMath->casingLf($op['width_in'], $op['height_in'], $casingSides, $op['type']);
        }
        $casingLf = $this->roundTo($casingLf, 2);

        // Per-wall net area and opening deductions
        $perWallNetAreas = [];
        $totalNetWallArea = 0.0;
        $wallIdx = 1;

        foreach ($boundaryWalls as $bw) {
            $wId = $bw['id'];
            $lengthFt = $bw['length_ft'];
            $wallLengthIn = $lengthFt * 12.0;
            $wallOpenings = $openingsByWall[$wId] ?? [];

            // Convert to canvas object format for unionOpeningAreaRawSqFt
            $canvasObjects = [];
            foreach ($wallOpenings as $wo) {
                $canvasObjects[] = [
                    'type' => $wo['type'],
                    'width' => $wo['width_in'],
                    'height' => $wo['height_in'],
                    'offset' => $wo['offset_in'],
                    'sill_height' => $wo['sill_height_in'],
                ];
            }

            $deductRaw = $this->finishMath->unionOpeningAreaRawSqFt($canvasObjects, $ceilingHeightFt, $wallLengthIn);
            $deductSqFt = $this->roundTo($deductRaw, 2);
            $netWallArea = $this->finishMath->netWallAreaSqFt($lengthFt, $ceilingHeightFt, $deductRaw);
            $totalNetWallArea += $netWallArea;

            $perWallNetAreas[] = [
                'id' => $wId,
                'index' => $wallIdx,
                'label' => 'W'.$wallIdx,
                'length_lf' => $this->roundTo($lengthFt, 2),
                'opening_area_sqft' => $deductSqFt,
                'net_area_sqft' => $netWallArea,
            ];
            $wallIdx++;
        }

        // Partition walls (interior): count both faces (2x)
        $partitionLf = 0.0;
        foreach ($partitionWalls as $pw) {
            $wId = (string) $pw['id'];
            $lengthFt = $this->interiorGeometry->wallPlanEdgeLengthCm($pw, $allWallsList, $cmPerUnit) / self::CM_PER_FOOT;
            $partitionLf += $lengthFt;
            $wallLengthIn = $lengthFt * 12.0;
            $wallOpenings = $openingsByWall[$wId] ?? [];

            $canvasObjects = [];
            foreach ($wallOpenings as $wo) {
                $canvasObjects[] = [
                    'type' => $wo['type'],
                    'width' => $wo['width_in'],
                    'height' => $wo['height_in'],
                    'offset' => $wo['offset_in'],
                    'sill_height' => $wo['sill_height_in'],
                ];
            }

            $deductRaw = $this->finishMath->unionOpeningAreaRawSqFt($canvasObjects, $ceilingHeightFt, $wallLengthIn);
            $deductSqFt = $this->roundTo($deductRaw * 2.0, 2);
            $netWallAreaSingle = $this->finishMath->netWallAreaSqFt($lengthFt, $ceilingHeightFt, $deductRaw);
            $netWallArea = $this->roundTo($netWallAreaSingle * 2.0, 2);
            $totalNetWallArea += $netWallArea;

            $perWallNetAreas[] = [
                'id' => $wId,
                'index' => $wallIdx,
                'label' => 'W'.$wallIdx,
                'length_lf' => $this->roundTo($lengthFt, 2),
                'opening_area_sqft' => $deductSqFt,
                'net_area_sqft' => $netWallArea,
            ];
            $wallIdx++;
        }

        $totalNetWallArea = $this->roundTo($totalNetWallArea, 2);

        // Flooring settings & metrics
        $wastePercent = $this->finishMath->normalizeWastePercent(
            $finish['flooring_waste_percent'] ?? $roomDetails['flooring_waste_percent'] ?? RoomAreaFinishMath::DEFAULT_WASTE_PERCENT
        );
        $direction = $this->finishMath->normalizeDirection(
            $finish['flooring_direction'] ?? $roomDetails['flooring_direction'] ?? RoomAreaFinishMath::DIRECTION_LENGTH
        );
        $boxSqFt = $this->finishMath->normalizeBoxSqFt(
            $finish['flooring_box_sqft'] ?? $roomDetails['flooring_box_sqft'] ?? RoomAreaFinishMath::DEFAULT_BOX_SQFT
        );

        $flooringBoxes = $this->finishMath->flooringBoxes($insideAreaSqFt, $wastePercent, $boxSqFt);
        $flooringWithWasteSqFt = $this->finishMath->flooringWithWasteSqFt($insideAreaSqFt, $wastePercent);

        // Wall thickness
        $firstWall = reset($boundaryWalls) ?: [];
        $rawThicknessIn = isset($firstWall['thickness_cm'])
            ? ($firstWall['thickness_cm'] / self::CM_PER_INCH)
            : ($finish['wall_thickness_in'] ?? self::DEFAULT_WALL_THICKNESS_IN);
        $thicknessIn = $this->canvasData->normalizeWallThickness($rawThicknessIn);

        // Shared walls & outside area
        $sharedLf = $this->detectSharedWallLf($floor, $selectedRoom, $walls, $cmPerUnit, $allWallsList);
        $framingLf = max(0.0, $this->roundTo($perimeter - $sharedLf, 2));
        $outsideAreaSqFt = $this->roundTo(
            $this->interiorGeometry->exteriorFloorAreaSqFt($interiorRingCm, $boundaryWallIds, $walls, $cmPerUnit),
            2
        );
        if ($outsideAreaSqFt <= $insideAreaSqFt) {
            $outsideAreaSqFt = max($insideAreaSqFt, $this->canvasData->outsideAreaSqFt($insideAreaSqFt, $perimeter, $sharedLf, $thicknessIn));
        }

        // Library items (cabinets, stairs)
        $library = $this->resolveLibraryMetrics($floor, $cmPerUnit);

        $metrics = [
            'area_sqft' => $insideAreaSqFt,
            'perimeter_lf' => $perimeter,
            'baseboard_lf' => $baseboardLf,
            'wall_height_ft' => $wallHeightFt,
            'wall_area_sqft' => $totalNetWallArea,
            'door_count' => $doorCount,
            'window_count' => $windowCount,
            'opening_count' => $openingCount,
            'drywall_openings' => $drywallOpenings,
            'inside_corners' => $insideCorners,
            'outside_corners' => $outsideCorners,
            'polygon_count' => 1,
            'wall_thickness_in' => $thicknessIn,
            'inside_area_sqft' => $insideAreaSqFt,
            'outside_area_sqft' => $outsideAreaSqFt,
            'shared_wall_lf' => $sharedLf,
            'framing_lf' => $framingLf,
            'partition_lf' => $this->roundTo($partitionLf, 2),
            'cabinet_base_lf' => $library['cabinet_base_lf'],
            'cabinet_upper_lf' => $library['cabinet_upper_lf'],
            'stairs_count' => $library['stairs_count'],
            'stairs_treads' => $library['stairs_treads'],
            'stairs_run_lf' => $library['stairs_run_lf'],
            'stairs_quote_type' => $library['stairs_quote_type'],
            'ceiling_height_ft' => $ceilingHeightFt,
            'ceiling_area_sqft' => $ceilingAreaSqFt,
            'net_wall_area_sqft' => $totalNetWallArea,
            'walls' => $perWallNetAreas,
            'flooring_waste_percent' => $wastePercent,
            'flooring_direction' => $direction,
            'flooring_box_sqft' => $boxSqFt,
            'flooring_boxes' => $flooringBoxes,
            'flooring_with_waste_sqft' => $flooringWithWasteSqFt,
            'casing_lf' => $casingLf,
            'boundary_wall_ids' => $boundaryWallIds,
            'room_polygon_cm' => array_map(
                fn ($pt) => ['x' => (float) ($pt['x'] ?? 0), 'y' => (float) ($pt['y'] ?? 0)],
                $polygonVertices
            ),
        ];

        return $this->extensions->applyToMetrics($metrics, $plan, $finish, $cmPerUnit);
    }

    /**
     * Default empty layout metrics structure.
     */
    public function emptyLayoutMetrics(float $wallHeightFt = self::DEFAULT_WALL_HEIGHT_FT): array
    {
        $normalizedHeight = $this->canvasData->normalizeWallHeight($wallHeightFt);

        return [
            'area_sqft' => 0.0,
            'perimeter_lf' => 0.0,
            'baseboard_lf' => 0.0,
            'wall_height_ft' => $normalizedHeight,
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
            'partition_lf' => 0.0,
            'bulkhead_lf' => 0.0,
            'bulkhead_framing_lf' => 0.0,
            'bulkhead_drywall_sqft' => 0.0,
            'cabinet_base_lf' => 0.0,
            'cabinet_upper_lf' => 0.0,
            'stairs_count' => 0,
            'stairs_treads' => 0,
            'stairs_run_lf' => 0.0,
            'stairs_quote_type' => 'standard',
            'ceiling_height_ft' => $normalizedHeight,
            'ceiling_area_sqft' => 0.0,
            'net_wall_area_sqft' => 0.0,
            'walls' => [],
            'flooring_waste_percent' => RoomAreaFinishMath::DEFAULT_WASTE_PERCENT,
            'flooring_direction' => RoomAreaFinishMath::DIRECTION_LENGTH,
            'flooring_box_sqft' => RoomAreaFinishMath::DEFAULT_BOX_SQFT,
            'flooring_boxes' => 0,
            'flooring_with_waste_sqft' => 0.0,
            'casing_lf' => 0.0,
        ];
    }

    /**
     * Like layoutMetrics but never throws — for read-only payloads when stored geometry is invalid.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $finish
     * @return array<string, mixed>
     */
    public function safeLayoutMetrics(array $plan, ?string $roomId = null, array $finish = []): array
    {
        try {
            return $this->layoutMetrics($plan, $roomId, $finish);
        } catch (\InvalidArgumentException $e) {
            $wallHeight = (float) ($finish['wall_height_ft'] ?? self::DEFAULT_WALL_HEIGHT_FT);
            $metrics = $this->emptyLayoutMetrics($wallHeight);
            $metrics['geometry_unmeasurable'] = true;
            $message = trim($e->getMessage());
            $metrics['geometry_message'] = $message !== ''
                ? $message
                : 'Room geometry could not be measured.';

            return $metrics;
        }
    }

    /**
     * Convert classic canvas data to openPlan3D project structure.
     *
     * @param  array<string, mixed>  $canvas
     * @param  float  $pixelsPerFoot
     * @param  float  $wallHeightFt
     * @param  array<string, mixed>  $finish
     * @return array<string, mixed>
     */
    public function fromCanvasData(
        array $canvas,
        float $pixelsPerFoot,
        float $wallHeightFt = self::DEFAULT_WALL_HEIGHT_FT,
        array $finish = []
    ): array {
        $normalized = $this->canvasData->normalize($canvas);
        $wallHeightFt = $this->canvasData->normalizeWallHeight($wallHeightFt);
        $ceilingHeightFt = $this->finishMath->ceilingHeightFt($finish['ceiling_height_ft'] ?? null, $wallHeightFt);
        $wallThicknessIn = $this->canvasData->normalizeWallThickness($normalized['wall_thickness_in'] ?? self::DEFAULT_WALL_THICKNESS_IN);

        $walls = [];
        $rooms = [];
        $doors = [];
        $windows = [];
        $furniture = [];
        $stairs = [];

        $wallHeightCm = $wallHeightFt * self::CM_PER_FOOT;
        $wallThicknessCm = ($wallThicknessIn > 0 ? $wallThicknessIn : self::DEFAULT_WALL_THICKNESS_IN) * self::CM_PER_INCH;

        // Map polygons to walls and rooms
        $wallLengthsIn = [];
        $roomIndex = 1;

        foreach ($normalized['groups'] ?? [] as $group) {
            foreach ($group['polygons'] ?? [] as $polygon) {
                $verts = $polygon['vertices'] ?? [];
                $count = count($verts);
                if ($count < 3) {
                    continue;
                }

                $polyId = (string) ($polygon['id'] ?? 'p'.$roomIndex);
                $roomWallIds = [];
                $roomVertsCm = [];

                for ($i = 0; $i < $count; $i++) {
                    $j = ($i + 1) % $count;
                    $p1 = $verts[$i];
                    $p2 = $verts[$j];

                    $x1Cm = ($p1['x'] / $pixelsPerFoot) * self::CM_PER_FOOT;
                    $y1Cm = ($p1['y'] / $pixelsPerFoot) * self::CM_PER_FOOT;
                    $x2Cm = ($p2['x'] / $pixelsPerFoot) * self::CM_PER_FOOT;
                    $y2Cm = ($p2['y'] / $pixelsPerFoot) * self::CM_PER_FOOT;

                    $roomVertsCm[] = ['x' => $this->roundTo($x1Cm, 4), 'y' => $this->roundTo($y1Cm, 4)];

                    $wallId = $this->canvasData->wallId($polyId, $i, $j);
                    $roomWallIds[] = $wallId;

                    $lenIn = (hypot($p2['x'] - $p1['x'], $p2['y'] - $p1['y']) / $pixelsPerFoot) * 12.0;
                    $wallLengthsIn[$wallId] = $lenIn;

                    $walls[] = [
                        'id' => $wallId,
                        'start' => ['x' => $this->roundTo($x1Cm, 4), 'y' => $this->roundTo($y1Cm, 4)],
                        'end' => ['x' => $this->roundTo($x2Cm, 4), 'y' => $this->roundTo($y2Cm, 4)],
                        'thickness' => $this->roundTo($wallThicknessCm, 4),
                        'height' => $this->roundTo($wallHeightCm, 4),
                        'color' => '#ffffff',
                    ];
                }

                $roomAreaSqFt = 0.0;
                try {
                    $roomAreaSqFt = $this->polygonArea->verticesToSquareFeet($verts, $pixelsPerFoot);
                } catch (\InvalidArgumentException $e) {
                    $roomAreaSqFt = 0.0;
                }
                $roomAreaM2 = $roomAreaSqFt * 0.09290304;

                $rooms[] = [
                    'id' => $polyId,
                    'name' => (string) ($polygon['name'] ?? 'Room '.$roomIndex),
                    'walls' => $roomWallIds,
                    'polygon' => $roomVertsCm,
                    'floorTexture' => 'hardwood',
                    'area' => $this->roundTo($roomAreaM2, 2),
                    'details' => [
                        'ceilingHeight' => $this->roundTo($ceilingHeightFt * self::CM_PER_FOOT, 4),
                        'flooring_waste_percent' => $finish['flooring_waste_percent'] ?? RoomAreaFinishMath::DEFAULT_WASTE_PERCENT,
                        'flooring_direction' => $finish['flooring_direction'] ?? RoomAreaFinishMath::DIRECTION_LENGTH,
                        'flooring_box_sqft' => $finish['flooring_box_sqft'] ?? RoomAreaFinishMath::DEFAULT_BOX_SQFT,
                        'floor_polygon_mode' => RoomAreaInteriorGeometry::INTERIOR_FACE_MODE,
                    ],
                ];
                $roomIndex++;
            }
        }

        // Map objects (doors, windows, openings, furniture, stairs)
        foreach ($normalized['objects'] ?? [] as $obj) {
            $type = strtolower((string) ($obj['type'] ?? ''));
            $subtype = strtolower((string) ($obj['subtype'] ?? ''));
            $wallId = (string) ($obj['wall_id'] ?? '');

            if (in_array($type, RoomAreaCanvasData::WALL_OBJECT_TYPES, true)) {
                $wallLenIn = $wallLengthsIn[$wallId] ?? 0.0;
                $widthIn = (float) ($obj['width'] ?? 36.0);
                $heightIn = $this->finishMath->objectHeightInches($obj, $type, $wallHeightFt);
                $offsetIn = (float) ($obj['offset'] ?? 0.0);

                // OpenPlan3D position is center (0 to 1)
                $centerIn = $offsetIn + ($widthIn / 2.0);
                $position = $wallLenIn > 0 ? min(1.0, max(0.0, $centerIn / $wallLenIn)) : 0.5;

                $widthCm = $widthIn * self::CM_PER_INCH;
                $heightCm = $heightIn * self::CM_PER_INCH;

                if ($type === 'window') {
                    $sillIn = (float) ($obj['sill_height'] ?? 0.0);
                    $windows[] = [
                        'id' => (string) ($obj['id'] ?? 'win_'.count($windows)),
                        'wallId' => $wallId,
                        'position' => $this->roundTo($position, 4),
                        'width' => $this->roundTo($widthCm, 4),
                        'height' => $this->roundTo($heightCm, 4),
                        'sillHeight' => $this->roundTo($sillIn * self::CM_PER_INCH, 4),
                        'type' => $subtype ?: 'standard',
                        'details' => [
                            'casing_sides' => (int) ($obj['casing_sides'] ?? 4),
                            'offset' => $offsetIn,
                        ],
                    ];
                } else {
                    // door or cased opening
                    $isOpening = $type === 'opening';
                    $doors[] = [
                        'id' => (string) ($obj['id'] ?? 'door_'.count($doors)),
                        'wallId' => $wallId,
                        'position' => $this->roundTo($position, 4),
                        'width' => $this->roundTo($widthCm, 4),
                        'height' => $this->roundTo($heightCm, 4),
                        'type' => $isOpening ? 'opening' : ($subtype ?: 'single'),
                        'swingDirection' => str_contains((string) ($obj['swing'] ?? ''), 'right') ? 'right' : 'left',
                        'flipSide' => str_contains((string) ($obj['swing'] ?? ''), 'out'),
                        'details' => [
                            'casing_sides' => (int) ($obj['casing_sides'] ?? ($isOpening ? 2 : 2)),
                            'door_face' => (string) ($obj['door_face'] ?? 'interior'),
                            'offset' => $offsetIn,
                        ],
                    ];
                }
            } elseif ($type === 'stairs') {
                $depthIn = (float) ($obj['depth'] ?? 120.0);
                $stairs[] = [
                    'id' => (string) ($obj['id'] ?? 'stair_'.count($stairs)),
                    'position' => ['x' => (float) ($obj['x'] ?? 0), 'y' => (float) ($obj['y'] ?? 0)],
                    'rotation' => (float) ($obj['rotation'] ?? 0),
                    'width' => (float) ($obj['width'] ?? 36.0) * self::CM_PER_INCH,
                    'depth' => $depthIn * self::CM_PER_INCH,
                    'riserCount' => $this->canvasData->stairsTreadCount($obj),
                    'direction' => 'up',
                    'stairType' => match ($subtype) {
                        'l' => 'l-shaped',
                        'u' => 'u-shaped',
                        default => 'straight',
                    },
                ];
            } elseif ($type === 'cabinet' || $type === 'furniture' || $type === 'fixture') {
                $furniture[] = [
                    'id' => (string) ($obj['id'] ?? 'furn_'.count($furniture)),
                    'catalogId' => $type === 'cabinet' ? 'cabinet_'.$subtype : $type.'_'.$subtype,
                    'sourceCategory' => $type,
                    'position' => ['x' => (float) ($obj['x'] ?? 0), 'y' => (float) ($obj['y'] ?? 0)],
                    'rotation' => (float) ($obj['rotation'] ?? 0),
                    'width' => (float) ($obj['width'] ?? 36.0) * self::CM_PER_INCH,
                    'depth' => (float) ($obj['depth'] ?? 24.0) * self::CM_PER_INCH,
                    'height' => 80.0,
                    'scale' => ['x' => 1, 'y' => 1, 'z' => 1],
                ];
            }
        }

        $floorId = 'floor-1';

        return [
            'format' => 'openplan3d',
            'version' => 1,
            'units' => 'cm',
            'id' => 'proj-1',
            'name' => 'Project 1',
            'activeFloorId' => $floorId,
            'floors' => [
                [
                    'id' => $floorId,
                    'name' => 'Ground Floor',
                    'level' => 0,
                    'walls' => $walls,
                    'rooms' => $rooms,
                    'doors' => $doors,
                    'windows' => $windows,
                    'furniture' => $furniture,
                    'stairs' => $stairs,
                    'guides' => [],
                    'measurements' => [],
                    'annotations' => [],
                    'textAnnotations' => [],
                    'groups' => [],
                ],
            ],
        ];
    }

    // --- Internal Helpers ---

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>|null
     */
    /**
     * Compute distance and projection from point p to segment [s, e].
     *
     * @param  array{x: float, y: float}  $p
     * @param  array{x: float, y: float}  $s
     * @param  array{x: float, y: float}  $e
     * @return array{dist: float, t: float, proj: array{x: float, y: float}}
     */
    private function distToSegment(array $p, array $s, array $e): array
    {
        $dx = $e['x'] - $s['x'];
        $dy = $e['y'] - $s['y'];
        $l2 = $dx * $dx + $dy * $dy;
        if ($l2 < 1e-6) {
            return [
                'dist' => hypot($p['x'] - $s['x'], $p['y'] - $s['y']),
                't' => 0.0,
                'proj' => $s,
            ];
        }
        $t = max(0.0, min(1.0, (($p['x'] - $s['x']) * $dx + ($p['y'] - $s['y']) * $dy) / $l2));
        $proj = [
            'x' => $s['x'] + $t * $dx,
            'y' => $s['y'] + $t * $dy,
        ];
        $dist = hypot($p['x'] - $proj['x'], $p['y'] - $proj['y']);

        return ['dist' => $dist, 't' => $t, 'proj' => $proj];
    }

    /**
     * Simplify ring by removing collinear vertices.
     *
     * @param  list<array{x: float, y: float}>  $pts
     * @return list<array{x: float, y: float}>
     */
    private function simplifyRing(array $pts): array
    {
        $n = count($pts);
        if ($n < 3) {
            return $pts;
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $prev = $pts[($i - 1 + $n) % $n];
            $curr = $pts[$i];
            $next = $pts[($i + 1) % $n];
            $dx1 = $curr['x'] - $prev['x'];
            $dy1 = $curr['y'] - $prev['y'];
            $dx2 = $next['x'] - $curr['x'];
            $dy2 = $next['y'] - $curr['y'];
            $len1 = hypot($dx1, $dy1);
            $len2 = hypot($dx2, $dy2);
            if ($len1 > 1e-6 && $len2 > 1e-6) {
                $cross = abs($dx1 * $dy2 - $dy1 * $dx2);
                $dot = $dx1 * $dx2 + $dy1 * $dy2;
                if (($cross / ($len1 * $len2)) < 0.02 && $dot > 0) {
                    continue;
                }
            }
            $out[] = $curr;
        }

        return count($out) >= 3 ? $out : $pts;
    }

    /**
     * Extract outer boundary polygon and identify interior partition walls.
     *
     * Handles:
     * - T-junctions by splitting host walls logically at intersection points.
     * - Free-ended / dead-end interior partition walls via iterative degree-1 pruning.
     * - Traversing the outer perimeter from the extreme vertex.
     *
     * @param  list<array<string, mixed>>  $walls
     * @return array{
     *     outer_polygon: list<array{x: float, y: float}>,
     *     boundary_wall_ids: list<string>,
     *     partition_wall_ids: list<string>,
     *     partition_walls: list<array<string, mixed>>
     * }
     */
    public function extractOuterLoopAndPartitions(array $walls, float $cmPerUnit = 1.0, ?float $eps = null): array
    {
        if ($eps === null) {
            $eps = $this->partitionToleranceCm($walls, $cmPerUnit);
        }

        if (count($walls) < 3) {
            return [
                'outer_polygon' => [],
                'boundary_wall_ids' => [],
                'partition_wall_ids' => array_column($walls, 'id'),
                'partition_walls' => array_values($walls),
            ];
        }

        $epsUnit = $eps / ($cmPerUnit > 0 ? $cmPerUnit : 1.0);

        // 1. Collect all segments
        $rawSegments = [];
        foreach ($walls as $w) {
            if (! is_array($w) || ! isset($w['start'], $w['end'])) {
                continue;
            }
            $start = $this->snapPlanPoint($w['start'], $cmPerUnit);
            $end = $this->snapPlanPoint($w['end'], $cmPerUnit);
            $rawSegments[] = [
                'wallId' => (string) ($w['id'] ?? uniqid('w_')),
                'start' => $start,
                'end' => $end,
                'wall' => $w,
                'splitPoints' => [],
            ];
        }

        if (count($rawSegments) < 3) {
            return [
                'outer_polygon' => [],
                'boundary_wall_ids' => [],
                'partition_wall_ids' => array_column($walls, 'id'),
                'partition_walls' => array_values($walls),
            ];
        }

        $this->clusterSegmentEndpoints($rawSegments, $cmPerUnit, $walls);

        $allEndpoints = [];
        foreach ($rawSegments as $s) {
            $allEndpoints[] = $s['start'];
            $allEndpoints[] = $s['end'];
        }

        // 2. Detect T-junctions: endpoints lying on other segment interiors
        for ($i = 0; $i < count($rawSegments); $i++) {
            $s = &$rawSegments[$i];
            $dx = $s['end']['x'] - $s['start']['x'];
            $dy = $s['end']['y'] - $s['start']['y'];
            $len = hypot($dx, $dy);
            if ($len < $epsUnit) {
                continue;
            }

            foreach ($allEndpoints as $pt) {
                if (hypot($pt['x'] - $s['start']['x'], $pt['y'] - $s['start']['y']) < $epsUnit) {
                    continue;
                }
                if (hypot($pt['x'] - $s['end']['x'], $pt['y'] - $s['end']['y']) < $epsUnit) {
                    continue;
                }

                $res = $this->distToSegment($pt, $s['start'], $s['end']);
                if ($res['dist'] < $epsUnit && $res['t'] * $len > $epsUnit && (1.0 - $res['t']) * $len > $epsUnit) {
                    $already = false;
                    foreach ($s['splitPoints'] as $sp) {
                        if (hypot($sp['point']['x'] - $res['proj']['x'], $sp['point']['y'] - $res['proj']['y']) < $epsUnit) {
                            $already = true;
                            break;
                        }
                    }
                    if (! $already) {
                        $s['splitPoints'][] = ['point' => $res['proj'], 't' => $res['t']];
                    }
                }
            }
        }
        unset($s);

        // 3. Subdivide segments at split points
        $subSegments = [];
        foreach ($rawSegments as $s) {
            if ($s['splitPoints'] === []) {
                $subSegments[] = [
                    'wallId' => $s['wallId'],
                    'start' => $s['start'],
                    'end' => $s['end'],
                    'wall' => $s['wall'],
                ];
            } else {
                usort($s['splitPoints'], fn ($a, $b) => $a['t'] <=> $b['t']);
                $cur = $s['start'];
                foreach ($s['splitPoints'] as $sp) {
                    if (hypot($sp['point']['x'] - $cur['x'], $sp['point']['y'] - $cur['y']) >= $epsUnit) {
                        $subSegments[] = [
                            'wallId' => $s['wallId'],
                            'start' => $cur,
                            'end' => $sp['point'],
                            'wall' => $s['wall'],
                        ];
                        $cur = $sp['point'];
                    }
                }
                if (hypot($s['end']['x'] - $cur['x'], $s['end']['y'] - $cur['y']) >= $epsUnit) {
                    $subSegments[] = [
                        'wallId' => $s['wallId'],
                        'start' => $cur,
                        'end' => $s['end'],
                        'wall' => $s['wall'],
                    ];
                }
            }
        }

        // 4. Cluster vertices into unique node IDs
        $nodes = [];
        $nodeClusterUnit = $this->endpointClusterToleranceCm($walls, $cmPerUnit) / ($cmPerUnit > 0 ? $cmPerUnit : 1.0);
        $findNode = function ($pt) use (&$nodes, $nodeClusterUnit, $cmPerUnit) {
            $snapped = $this->snapPlanPoint($pt, $cmPerUnit);
            foreach ($nodes as $id => $nPt) {
                if (hypot($nPt['x'] - $snapped['x'], $nPt['y'] - $snapped['y']) < $nodeClusterUnit) {
                    return $id;
                }
            }
            $newId = count($nodes);
            $nodes[$newId] = $snapped;

            return $newId;
        };

        $edges = [];
        foreach ($subSegments as $seg) {
            $u = $findNode($seg['start']);
            $v = $findNode($seg['end']);
            if ($u !== $v) {
                $edges[] = [
                    'u' => $u,
                    'v' => $v,
                    'wallId' => $seg['wallId'],
                    'wall' => $seg['wall'],
                    'start' => $nodes[$u],
                    'end' => $nodes[$v],
                ];
            }
        }

        // 5. Degree-1 iterative pruning (trim partition dead ends)
        $activeEdges = $edges;
        $partitionWallIds = [];

        $pruning = true;
        while ($pruning && count($activeEdges) >= 3) {
            $pruning = false;
            $degrees = array_fill(0, count($nodes), 0);
            foreach ($activeEdges as $e) {
                $degrees[$e['u']]++;
                $degrees[$e['v']]++;
            }

            $nextEdges = [];
            foreach ($activeEdges as $e) {
                if ($degrees[$e['u']] < 2 || $degrees[$e['v']] < 2) {
                    $partitionWallIds[$e['wallId']] = true;
                    $pruning = true;
                } else {
                    $nextEdges[] = $e;
                }
            }
            $activeEdges = $nextEdges;
        }

        if (count($activeEdges) < 3) {
            return [
                'outer_polygon' => [],
                'boundary_wall_ids' => [],
                'partition_wall_ids' => array_column($walls, 'id'),
                'partition_walls' => array_values($walls),
            ];
        }

        // 6. Trace outer boundary cycle
        $adj = [];
        foreach ($activeEdges as $idx => $e) {
            $adj[$e['u']][] = ['to' => $e['v'], 'edgeIdx' => $idx];
            $adj[$e['v']][] = ['to' => $e['u'], 'edgeIdx' => $idx];
        }

        // Start at extreme vertex (min X, then min Y)
        $startNode = null;
        $minX = INF;
        $minY = INF;
        foreach ($activeEdges as $e) {
            foreach ([$e['u'], $e['v']] as $nId) {
                $pt = $nodes[$nId];
                if ($pt['x'] < $minX || (abs($pt['x'] - $minX) < 1e-4 && $pt['y'] < $minY)) {
                    $minX = $pt['x'];
                    $minY = $pt['y'];
                    $startNode = $nId;
                }
            }
        }

        $cycle = [];
        $cycleWallIds = [];
        $currNode = $startNode;
        $prevAngle = -M_PI / 2.0;
        $visitedEdges = [];

        $maxSteps = count($activeEdges) * 2;
        for ($step = 0; $step < $maxSteps; $step++) {
            $cycle[] = $nodes[$currNode];
            $neighbors = $adj[$currNode] ?? [];
            if ($neighbors === []) {
                break;
            }

            $bestNext = null;
            $bestDiff = INF;
            $bestEdgeIdx = null;

            foreach ($neighbors as $nbr) {
                $toNode = $nbr['to'];
                $eIdx = $nbr['edgeIdx'];
                if (($visitedEdges[$eIdx] ?? 0) >= 1) {
                    continue;
                }

                $toPt = $nodes[$toNode];
                $edgeAngle = atan2($toPt['y'] - $nodes[$currNode]['y'], $toPt['x'] - $nodes[$currNode]['x']);

                $incoming = $prevAngle + M_PI;
                $diff = $edgeAngle - $incoming;
                while ($diff <= 0) {
                    $diff += 2 * M_PI;
                }
                while ($diff > 2 * M_PI) {
                    $diff -= 2 * M_PI;
                }

                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestNext = $toNode;
                    $bestEdgeIdx = $eIdx;
                }
            }

            if ($bestNext === null) {
                break;
            }

            $visitedEdges[$bestEdgeIdx] = ($visitedEdges[$bestEdgeIdx] ?? 0) + 1;
            $cycleWallIds[] = $activeEdges[$bestEdgeIdx]['wallId'];

            if ($bestNext === $startNode && count($cycle) >= 3) {
                break;
            }

            $prevAngle = atan2($nodes[$bestNext]['y'] - $nodes[$currNode]['y'], $nodes[$bestNext]['x'] - $nodes[$currNode]['x']);
            $currNode = $bestNext;
        }

        $cycle = $this->simplifyRing($cycle);

        $boundaryWallIds = array_values(array_unique($cycleWallIds));
        $boundaryWallIdMap = array_flip($boundaryWallIds);

        $partitionWalls = [];
        foreach ($walls as $w) {
            $wId = (string) $w['id'];
            if (! isset($boundaryWallIdMap[$wId])) {
                $partitionWalls[] = $w;
                $partitionWallIds[$wId] = true;
            }
        }

        return [
            'outer_polygon' => $cycle,
            'boundary_wall_ids' => $boundaryWallIds,
            'partition_wall_ids' => array_keys($partitionWallIds),
            'partition_walls' => $partitionWalls,
        ];
    }

    private function countDrawableWalls(?array $floor): int
    {
        if (! is_array($floor) || ! isset($floor['walls']) || ! is_array($floor['walls'])) {
            return 0;
        }
        $n = 0;
        foreach ($floor['walls'] as $w) {
            if (is_array($w) && isset($w['start'], $w['end'])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>|null>  $candidates
     * @return array<string, mixed>|null
     */
    private function pickBestFloor(array $candidates): ?array
    {
        $best = null;
        $bestCount = -1;
        foreach ($candidates as $floor) {
            if (! is_array($floor)) {
                continue;
            }
            $count = $this->countDrawableWalls($floor);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $floor;
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>|null  $floors
     * @return array<string, mixed>|null
     */
    private function resolveFloorFromList(?array $floors, ?string $activeId): ?array
    {
        if (! is_array($floors) || $floors === []) {
            return null;
        }
        if ($activeId) {
            foreach ($floors as $floor) {
                if (is_array($floor) && ($floor['id'] ?? null) === $activeId) {
                    return $floor;
                }
            }

            return null;
        }

        return is_array($floors[0] ?? null) ? $floors[0] : null;
    }

    /**
     * @return array{floor: ?array<string, mixed>, matched_by_id: bool, active_missing?: bool}
     */
    private function resolveActiveFloorEntry(?array $floors, ?string $activeId): array
    {
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
    }

    private function resolveFloor(array $plan): ?array
    {
        $activeId = $plan['activeFloorId'] ?? ($plan['project']['activeFloorId'] ?? null);
        $activeId = is_string($activeId) ? $activeId : null;

        $planFloors = $this->resolveActiveFloorEntry($plan['floors'] ?? null, $activeId);
        if ($planFloors['matched_by_id'] && is_array($planFloors['floor'])) {
            return $planFloors['floor'];
        }
        if (is_array($planFloors['floor']) && empty($planFloors['active_missing'])) {
            return $planFloors['floor'];
        }

        $projectFloorsList = isset($plan['project']['floors']) && is_array($plan['project']['floors'])
            ? $plan['project']['floors']
            : null;
        $projectFloors = $this->resolveActiveFloorEntry($projectFloorsList, $activeId);
        if ($projectFloors['matched_by_id'] && is_array($projectFloors['floor'])) {
            return $projectFloors['floor'];
        }
        if (is_array($projectFloors['floor']) && empty($projectFloors['active_missing'])) {
            return $projectFloors['floor'];
        }

        if (isset($plan['floor']) && is_array($plan['floor'])) {
            return $plan['floor'];
        }
        if (isset($plan['walls']) && is_array($plan['walls'])) {
            return $plan;
        }

        return is_array($planFloors['floor'] ?? null)
            ? $planFloors['floor']
            : (is_array($projectFloors['floor'] ?? null) ? $projectFloors['floor'] : null);
    }

    /**
     * @param  list<array<string, mixed>>  $rawWalls
     * @return array<string, array<string, mixed>>
     */
    private function indexWalls(array $rawWalls): array
    {
        $indexed = [];
        foreach ($rawWalls as $w) {
            if (! is_array($w) || ! isset($w['id'], $w['start'], $w['end'])) {
                continue;
            }
            $indexed[(string) $w['id']] = $w;
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, mixed>>  $walls
     */
    private function partitionToleranceCm(array $walls, float $cmPerUnit): float
    {
        $baseCm = 20.0;
        $maxCm = 30.0;
        $minLenCm = INF;
        foreach ($walls as $w) {
            if (! is_array($w) || ! isset($w['start'], $w['end'])) {
                continue;
            }
            $dx = ((float) ($w['end']['x'] ?? 0) - (float) ($w['start']['x'] ?? 0)) * $cmPerUnit;
            $dy = ((float) ($w['end']['y'] ?? 0) - (float) ($w['start']['y'] ?? 0)) * $cmPerUnit;
            $lenCm = hypot($dx, $dy);
            if ($lenCm > 0.01 && $lenCm < $minLenCm) {
                $minLenCm = $lenCm;
            }
        }
        if (! is_finite($minLenCm)) {
            return $baseCm;
        }

        return min($maxCm, max($baseCm, $minLenCm * 0.08));
    }

    /**
     * @param  list<array<string, mixed>>  $walls
     */
    private function endpointClusterToleranceCm(array $walls, float $cmPerUnit): float
    {
        $junctionCm = $this->partitionToleranceCm($walls, $cmPerUnit);
        $drawNoiseCm = 3.5;

        return min($junctionCm, max($drawNoiseCm, $junctionCm * 0.45));
    }

    /**
     * @param  list<array{wallId: string, start: array{x: float, y: float}, end: array{x: float, y: float}, wall: array<string, mixed>, splitPoints: list<mixed>}>  $rawSegments
     * @param  list<array<string, mixed>>  $walls
     */
    private function clusterSegmentEndpoints(array &$rawSegments, float $cmPerUnit, array $walls): void
    {
        $clusterCm = $this->endpointClusterToleranceCm($walls, $cmPerUnit);
        $epsUnit = $clusterCm / ($cmPerUnit > 0 ? $cmPerUnit : 1.0);
        $refs = [];
        foreach ($rawSegments as $si => $seg) {
            $refs[] = ['si' => $si, 'key' => 'start', 'x' => $seg['start']['x'], 'y' => $seg['start']['y']];
            $refs[] = ['si' => $si, 'key' => 'end', 'x' => $seg['end']['x'], 'y' => $seg['end']['y']];
        }
        $count = count($refs);
        if ($count === 0) {
            return;
        }

        $parent = range(0, $count - 1);
        $find = function ($i) use (&$parent, &$find) {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };
        $unite = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (hypot($refs[$i]['x'] - $refs[$j]['x'], $refs[$i]['y'] - $refs[$j]['y']) < $epsUnit) {
                    $unite($i, $j);
                }
            }
        }

        $sums = [];
        $counts = [];
        for ($i = 0; $i < $count; $i++) {
            $root = $find($i);
            $sums[$root]['x'] = ($sums[$root]['x'] ?? 0) + $refs[$i]['x'];
            $sums[$root]['y'] = ($sums[$root]['y'] ?? 0) + $refs[$i]['y'];
            $counts[$root] = ($counts[$root] ?? 0) + 1;
        }

        for ($i = 0; $i < $count; $i++) {
            $root = $find($i);
            $n = max(1, $counts[$root] ?? 1);
            $canon = $this->snapPlanPoint([
                'x' => $sums[$root]['x'] / $n,
                'y' => $sums[$root]['y'] / $n,
            ], $cmPerUnit);
            $rawSegments[$refs[$i]['si']][$refs[$i]['key']] = $canon;
        }

        foreach ($rawSegments as $si => &$seg) {
            foreach (['start', 'end'] as $key) {
                $pt = $seg[$key];
                $best = null;
                $bestDist = $epsUnit;
                foreach ($rawSegments as $other) {
                    $res = $this->distToSegment($pt, $other['start'], $other['end']);
                    $segLen = hypot($other['end']['x'] - $other['start']['x'], $other['end']['y'] - $other['start']['y']);
                    if ($segLen < 1e-6) {
                        continue;
                    }
                    if ($res['dist'] >= $bestDist) {
                        continue;
                    }
                    if ($res['t'] * $segLen <= $epsUnit || (1.0 - $res['t']) * $segLen <= $epsUnit) {
                        continue;
                    }
                    $bestDist = $res['dist'];
                    $best = $this->snapPlanPoint($res['proj'], $cmPerUnit);
                }
                if ($best !== null) {
                    $seg[$key] = $best;
                }
            }
        }
        unset($seg);
    }

    /**
     * @param  list<array{x: float, y: float}>  $polygon
     */
    private function polygonAreaSqFt(array $polygon, float $cmPerUnit): float
    {
        if (count($polygon) < 3) {
            return 0.0;
        }
        $verticesFt = [];
        foreach ($polygon as $pt) {
            $verticesFt[] = [
                'x' => ((float) ($pt['x'] ?? 0) * $cmPerUnit) / self::CM_PER_FOOT,
                'y' => ((float) ($pt['y'] ?? 0) * $cmPerUnit) / self::CM_PER_FOOT,
            ];
        }
        $validation = $this->polygonArea->validateRoom($verticesFt, 1.0);

        return $validation['valid'] ? (float) $validation['area_sqft'] : 0.0;
    }

    private function snapPlanCoordinate(float $value, float $cmPerUnit): float
    {
        $unitScale = $cmPerUnit > 0 ? $cmPerUnit : 1.0;
        $cm = $value * $unitScale;
        $snappedCm = round($cm * 100.0) / 100.0;

        return $snappedCm / $unitScale;
    }

    /**
     * @return array{x: float, y: float}
     */
    private function snapPlanPoint(array $pt, float $cmPerUnit): array
    {
        return [
            'x' => $this->snapPlanCoordinate((float) ($pt['x'] ?? 0), $cmPerUnit),
            'y' => $this->snapPlanCoordinate((float) ($pt['y'] ?? 0), $cmPerUnit),
        ];
    }

    /**
     * @param  list<array{x: float, y: float}>  $outerPolygon
     */
    private function outerLoopHasUsableArea(array $outerPolygon, float $cmPerUnit): bool
    {
        if (count($outerPolygon) < 3) {
            return false;
        }
        $verticesFt = [];
        foreach ($outerPolygon as $pt) {
            $verticesFt[] = [
                'x' => ((float) ($pt['x'] ?? 0) * $cmPerUnit) / self::CM_PER_FOOT,
                'y' => ((float) ($pt['y'] ?? 0) * $cmPerUnit) / self::CM_PER_FOOT,
            ];
        }
        if (! $this->polygonArea->isSimplePolygon($verticesFt)) {
            return false;
        }
        $validation = $this->polygonArea->validateRoom($verticesFt, 1.0);

        return $validation['valid'] && (float) $validation['area_sqft'] > 0.01;
    }

    /**
     * @param  list<array{x: float, y: float}>  $storedPoly
     */
    private function storedPolygonMeasurable(array $storedPoly, float $cmPerUnit): bool
    {
        if (count($storedPoly) < 3) {
            return false;
        }
        $verticesFt = [];
        foreach ($storedPoly as $pt) {
            $verticesFt[] = [
                'x' => ((float) ($pt['x'] ?? 0) * $cmPerUnit) / self::CM_PER_FOOT,
                'y' => ((float) ($pt['y'] ?? 0) * $cmPerUnit) / self::CM_PER_FOOT,
            ];
        }
        if (! $this->polygonArea->isSimplePolygon($verticesFt)) {
            return false;
        }
        $validation = $this->polygonArea->validateRoom($verticesFt, 1.0);

        return $validation['valid'] && (float) $validation['area_sqft'] > 0.01;
    }

    /**
     * @param  list<array{x: float, y: float}>  $storedPoly
     * @param  array{outer_polygon: list<array{x: float, y: float}>, partition_walls: list<array<string, mixed>>}  $loop
     */
    public function shouldReplaceRoomPolygonWithOuterLoop(array $storedPoly, array $loop, float $cmPerUnit, array $roomWallIds = []): bool
    {
        $outer = $loop['outer_polygon'] ?? [];
        if (! $this->outerLoopHasUsableArea($outer, $cmPerUnit)) {
            return false;
        }
        if (count($storedPoly) < 3) {
            return true;
        }

        $partitions = count($loop['partition_walls'] ?? []);
        $outerArea = $this->polygonAreaSqFt($outer, $cmPerUnit);
        $storedArea = $this->polygonAreaSqFt($storedPoly, $cmPerUnit);
        $storedMeasurable = $this->storedPolygonMeasurable($storedPoly, $cmPerUnit);

        if (! $storedMeasurable) {
            return true;
        }

        if ($partitions > 0 && $outerArea > $storedArea + 0.5) {
            return count($roomWallIds) > 0;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $floor
     * @param  array<string, array<string, mixed>>  $walls
     * @return list<array<string, mixed>>
     */
    private function resolveRooms(array $floor, array $walls, float $cmPerUnit): array
    {
        $rooms = [];
        $rawRooms = $floor['rooms'] ?? [];

        if (is_array($rawRooms) && count($rawRooms) > 0) {
            $isSingleRoom = count($rawRooms) === 1;
            foreach ($rawRooms as $r) {
                if (! is_array($r)) {
                    continue;
                }
                // Determine candidate walls for this room
                $candidateWalls = [];
                if ($isSingleRoom) {
                    $candidateWalls = array_values($walls);
                } else {
                    $rWallIds = $r['walls'] ?? [];
                    if (is_array($rWallIds) && count($rWallIds) > 0) {
                        $idMap = array_flip($rWallIds);
                        foreach ($walls as $wid => $w) {
                            if (isset($idMap[$wid])) {
                                $candidateWalls[] = $w;
                            }
                        }
                    } else {
                        $candidateWalls = array_values($walls);
                    }
                }

                if (count($candidateWalls) < 1) {
                    continue;
                }

                $loop = $this->extractOuterLoopAndPartitions($candidateWalls, $cmPerUnit);
                $r['boundary_wall_ids'] = $loop['boundary_wall_ids'];
                $r['partition_walls'] = $loop['partition_walls'];
                $r['partition_wall_ids'] = $loop['partition_wall_ids'];

                $storedPoly = [];
                if (isset($r['polygon']) && is_array($r['polygon'])) {
                    $storedPoly = $r['polygon'];
                } elseif (isset($r['vertices']) && is_array($r['vertices'])) {
                    $storedPoly = $r['vertices'];
                }

                if ($this->shouldReplaceRoomPolygonWithOuterLoop($storedPoly, $loop, $cmPerUnit, is_array($r['walls'] ?? null) ? $r['walls'] : [])) {
                    $r['polygon'] = $loop['outer_polygon'];
                } elseif (count($storedPoly) >= 3) {
                    $r['polygon'] = $storedPoly;
                } elseif ($this->outerLoopHasUsableArea($loop['outer_polygon'], $cmPerUnit)) {
                    $r['polygon'] = $loop['outer_polygon'];
                }

                if (empty($r['walls']) && count($loop['boundary_wall_ids']) > 0) {
                    $r['walls'] = $loop['boundary_wall_ids'];
                }

                if (isset($r['polygon']) && is_array($r['polygon']) && count($r['polygon']) >= 3) {
                    $rooms[] = $r;
                }
            }
        }

        // Fallback: If no rooms are explicitly defined, construct from outer loop of walls
        if ($rooms === [] && (! is_array($rawRooms) || count($rawRooms) === 0)) {
            $loop = $this->extractOuterLoopAndPartitions(array_values($walls), $cmPerUnit);
            if (count($loop['outer_polygon']) >= 3 && $this->outerLoopHasUsableArea($loop['outer_polygon'], $cmPerUnit)) {
                $rooms[] = [
                    'id' => 'r1',
                    'name' => 'Room 1',
                    'walls' => $loop['boundary_wall_ids'],
                    'polygon' => $loop['outer_polygon'],
                    'boundary_wall_ids' => $loop['boundary_wall_ids'],
                    'partition_walls' => $loop['partition_walls'],
                    'partition_wall_ids' => $loop['partition_wall_ids'],
                ];
            }
        }

        return $rooms;
    }

    /**
     * @param  array<string, mixed>  $room
     * @param  array<string, array<string, mixed>>  $walls
     * @return list<array{x: float, y: float}>
     */
    private function resolveRoomPolygon(array $room, array $walls): array
    {
        // Check for pre-existing polygon
        if (isset($room['polygon']) && is_array($room['polygon']) && count($room['polygon']) >= 3) {
            return $room['polygon'];
        }
        if (isset($room['vertices']) && is_array($room['vertices']) && count($room['vertices']) >= 3) {
            return $room['vertices'];
        }

        // Chain walls by ID
        $wallIds = $room['walls'] ?? [];
        if (! is_array($wallIds) || count($wallIds) < 2) {
            return [];
        }

        $roomWalls = [];
        foreach ($wallIds as $wid) {
            if (isset($walls[(string) $wid])) {
                $roomWalls[] = $walls[(string) $wid];
            }
        }

        return $this->traceWallsToPolygon($roomWalls);
    }

    /**
     * @param  list<array<string, mixed>>  $roomWalls
     * @return list<array{x: float, y: float}>
     */
    private function traceWallsToPolygon(array $roomWalls): array
    {
        $count = count($roomWalls);
        if ($count < 3) {
            return [];
        }

        $verts = [];
        $eps = 1.0; // 1 cm epsilon

        $remaining = $roomWalls;
        $first = array_shift($remaining);
        $verts[] = ['x' => (float) $first['start']['x'], 'y' => (float) $first['start']['y']];
        $currentPt = ['x' => (float) $first['end']['x'], 'y' => (float) $first['end']['y']];

        while ($remaining !== []) {
            $foundIdx = null;
            $reverse = false;

            foreach ($remaining as $idx => $w) {
                $sx = (float) $w['start']['x'];
                $sy = (float) $w['start']['y'];
                $ex = (float) $w['end']['x'];
                $ey = (float) $w['end']['y'];

                if (hypot($sx - $currentPt['x'], $sy - $currentPt['y']) < $eps) {
                    $foundIdx = $idx;
                    $reverse = false;
                    break;
                }
                if (hypot($ex - $currentPt['x'], $ey - $currentPt['y']) < $eps) {
                    $foundIdx = $idx;
                    $reverse = true;
                    break;
                }
            }

            if ($foundIdx === null) {
                // If not connected directly, push currentPt and pick closest
                $verts[] = $currentPt;
                $next = array_shift($remaining);
                $currentPt = ['x' => (float) $next['end']['x'], 'y' => (float) $next['end']['y']];
                continue;
            }

            $w = $remaining[$foundIdx];
            unset($remaining[$foundIdx]);
            $remaining = array_values($remaining);

            $verts[] = $currentPt;
            $currentPt = $reverse
                ? ['x' => (float) $w['start']['x'], 'y' => (float) $w['start']['y']]
                : ['x' => (float) $w['end']['x'], 'y' => (float) $w['end']['y']];
        }

        return $verts;
    }

    /**
     * @param  array<string, mixed>  $room
     * @param  array<string, array<string, mixed>>  $walls
     * @return list<array{id: string, length_ft: float, thickness_cm: float, height_cm: float}>
     */
    private function resolveBoundaryWalls(array $room, array $walls, float $cmPerUnit, array $allWallsList = []): array
    {
        $out = [];
        $wallIds = $room['boundary_wall_ids'] ?? $room['walls'] ?? [];
        if ($allWallsList === []) {
            $allWallsList = array_values($walls);
        }

        if (is_array($wallIds) && count($wallIds) > 0) {
            foreach ($wallIds as $wid) {
                $w = $walls[(string) $wid] ?? null;
                if ($w === null) {
                    continue;
                }
                $lenCm = $this->interiorGeometry->wallPlanEdgeLengthCm($w, $allWallsList, $cmPerUnit);
                $out[] = [
                    'id' => (string) $w['id'],
                    'length_ft' => $lenCm / self::CM_PER_FOOT,
                    'thickness_cm' => $this->interiorGeometry->wallThicknessCm($w['thickness'] ?? null, $cmPerUnit),
                    'height_cm' => ((float) ($w['height'] ?? 243.84)) * $cmPerUnit,
                ];
            }
        }

        // If no explicit wall references, infer from polygon edges
        if ($out === []) {
            $polygon = $room['polygon'] ?? [];
            $n = count($polygon);
            for ($i = 0; $i < $n; $i++) {
                $j = ($i + 1) % $n;
                $dx = ((float) $polygon[$j]['x'] - (float) $polygon[$i]['x']) * $cmPerUnit;
                $dy = ((float) $polygon[$j]['y'] - (float) $polygon[$i]['y']) * $cmPerUnit;
                $lenCm = hypot($dx, $dy);
                $out[] = [
                    'id' => 'wall_'.($i + 1),
                    'length_ft' => $lenCm / self::CM_PER_FOOT,
                    'thickness_cm' => 11.43,
                    'height_cm' => 243.84,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $floor
     * @return list<array<string, mixed>>
     */
    private function resolveFloorOpenings(array $floor, float $cmPerUnit): array
    {
        $openings = [];
        $walls = $this->indexWalls($floor['walls'] ?? []);

        // Doors
        foreach ($floor['doors'] ?? [] as $d) {
            if (! is_array($d)) {
                continue;
            }
            $wId = (string) ($d['wallId'] ?? '');
            $wall = $walls[$wId] ?? null;
            $wallLenIn = 0.0;
            if ($wall !== null) {
                $dx = ((float) $wall['end']['x'] - (float) $wall['start']['x']) * $cmPerUnit;
                $dy = ((float) $wall['end']['y'] - (float) $wall['start']['y']) * $cmPerUnit;
                $wallLenIn = (hypot($dx, $dy) / self::CM_PER_INCH);
            }

            $rawType = strtolower((string) ($d['type'] ?? 'single'));
            $isOpening = $rawType === 'opening' || $rawType === 'archway';
            $type = $isOpening ? 'opening' : 'door';

            $widthIn = ((float) ($d['width'] ?? 81.28) * $cmPerUnit) / self::CM_PER_INCH;
            $heightIn = isset($d['height']) && (float) $d['height'] > 0
                ? (((float) $d['height'] * $cmPerUnit) / self::CM_PER_INCH)
                : ($isOpening ? self::DEFAULT_OPENING_HEIGHT_IN : self::DEFAULT_DOOR_HEIGHT_IN);

            $pos = (float) ($d['position'] ?? 0.5);
            $offsetIn = isset($d['details']['offset']) && is_numeric($d['details']['offset'])
                ? (float) $d['details']['offset']
                : ($pos * $wallLenIn - ($widthIn / 2.0));

            $openings[] = [
                'id' => (string) ($d['id'] ?? 'door_'.count($openings)),
                'wallId' => $wId,
                'type' => $type,
                'width_in' => max(0.0, $widthIn),
                'height_in' => max(0.0, $heightIn),
                'offset_in' => max(0.0, $offsetIn),
                'sill_height_in' => 0.0,
                'details' => $d['details'] ?? [],
            ];
        }

        // Windows
        foreach ($floor['windows'] ?? [] as $w) {
            if (! is_array($w)) {
                continue;
            }
            $wId = (string) ($w['wallId'] ?? '');
            $wall = $walls[$wId] ?? null;
            $wallLenIn = 0.0;
            if ($wall !== null) {
                $dx = ((float) $wall['end']['x'] - (float) $wall['start']['x']) * $cmPerUnit;
                $dy = ((float) $wall['end']['y'] - (float) $wall['start']['y']) * $cmPerUnit;
                $wallLenIn = (hypot($dx, $dy) / self::CM_PER_INCH);
            }

            $widthIn = ((float) ($w['width'] ?? 91.44) * $cmPerUnit) / self::CM_PER_INCH;
            $heightIn = isset($w['height']) && (float) $w['height'] > 0
                ? (((float) $w['height'] * $cmPerUnit) / self::CM_PER_INCH)
                : self::DEFAULT_WINDOW_HEIGHT_IN;

            $sillIn = isset($w['sillHeight']) && (float) $w['sillHeight'] >= 0
                ? (((float) $w['sillHeight'] * $cmPerUnit) / self::CM_PER_INCH)
                : ((float) ($w['details']['sill_height'] ?? 0.0));

            $pos = (float) ($w['position'] ?? 0.5);
            $offsetIn = isset($w['details']['offset']) && is_numeric($w['details']['offset'])
                ? (float) $w['details']['offset']
                : ($pos * $wallLenIn - ($widthIn / 2.0));

            $openings[] = [
                'id' => (string) ($w['id'] ?? 'win_'.count($openings)),
                'wallId' => $wId,
                'type' => 'window',
                'width_in' => max(0.0, $widthIn),
                'height_in' => max(0.0, $heightIn),
                'offset_in' => max(0.0, $offsetIn),
                'sill_height_in' => max(0.0, $sillIn),
                'details' => $w['details'] ?? [],
            ];
        }

        return $openings;
    }

    /**
     * @param  array<string, mixed>  $opening
     */
    private function resolveCasingSides(array $opening): int
    {
        $details = $opening['details'] ?? [];
        if (isset($details['casing_sides']) && is_numeric($details['casing_sides'])) {
            return (int) $details['casing_sides'];
        }

        $type = $opening['type'];
        if ($type === 'window') {
            return 4;
        }

        if ($type === 'door') {
            $doorFace = (string) ($details['door_face'] ?? 'interior');

            return strtolower($doorFace) === 'exterior' ? 1 : 2;
        }

        return 2; // opening
    }

    /**
     * @param  array<string, mixed>  $floor
     * @param  array<string, mixed>  $selectedRoom
     * @param  array<string, array<string, mixed>>  $walls
     */
    private function detectSharedWallLf(array $floor, array $selectedRoom, array $walls, float $cmPerUnit, array $allWallsList = []): float
    {
        if ($allWallsList === []) {
            $allWallsList = array_values($walls);
        }
        $allRooms = $floor['rooms'] ?? [];
        if (! is_array($allRooms) || count($allRooms) < 2) {
            return 0.0;
        }

        $selectedWallIds = array_flip($selectedRoom['walls'] ?? []);
        $sharedWallIds = [];

        foreach ($allRooms as $r) {
            if (! is_array($r) || ($r['id'] ?? '') === ($selectedRoom['id'] ?? '')) {
                continue;
            }
            foreach ($r['walls'] ?? [] as $wid) {
                if (isset($selectedWallIds[(string) $wid])) {
                    $sharedWallIds[(string) $wid] = true;
                }
            }
        }

        $sharedLf = 0.0;
        foreach (array_keys($sharedWallIds) as $wid) {
            $w = $walls[$wid] ?? null;
            if ($w === null) {
                continue;
            }
            $sharedLf += $this->interiorGeometry->wallPlanEdgeLengthCm($w, $allWallsList, $cmPerUnit) / self::CM_PER_FOOT;
        }

        return $this->roundTo($sharedLf, 2);
    }

    /**
     * Interior floor area in sq ft (for hosted room labels and imports).
     *
     * @param  list<array{x: float|int, y: float|int}>  $centerlinePolygonCm
     * @param  list<string>  $boundaryWallIds
     * @param  array<string, array<string, mixed>>  $wallsById
     * @param  list<array<string, mixed>>  $allWallsList
     */
    public function interiorFloorAreaSqFtForLabel(
        array $centerlinePolygonCm,
        array $boundaryWallIds,
        array $wallsById,
        array $allWallsList,
        float $cmPerUnit = 1.0,
        bool $polygonAlreadyInterior = false,
    ): float {
        return $this->interiorGeometry->interiorFloorAreaSqFt(
            $centerlinePolygonCm,
            $boundaryWallIds,
            $wallsById,
            $allWallsList,
            $cmPerUnit,
            $polygonAlreadyInterior
        );
    }

    /**
     * @param  array<string, mixed>  $floor
     * @return array{
     *   cabinet_base_lf: float,
     *   cabinet_upper_lf: float,
     *   stairs_count: int,
     *   stairs_treads: int,
     *   stairs_run_lf: float,
     *   stairs_quote_type: string
     * }
     */
    private function resolveLibraryMetrics(array $floor, float $cmPerUnit): array
    {
        $baseIn = 0.0;
        $upperIn = 0.0;

        foreach ($floor['furniture'] ?? [] as $f) {
            if (! is_array($f)) {
                continue;
            }
            $cat = strtolower((string) ($f['catalogId'] ?? ($f['sourceCategory'] ?? '')));
            $widthIn = ((float) ($f['width'] ?? 36.0) * $cmPerUnit) / self::CM_PER_INCH;
            $depthIn = ((float) ($f['depth'] ?? 24.0) * $cmPerUnit) / self::CM_PER_INCH;

            if (str_contains($cat, 'cabinet')) {
                if (str_contains($cat, 'upper')) {
                    $upperIn += max(0.0, $widthIn);
                } elseif (str_contains($cat, 'corner')) {
                    $baseIn += max(0.0, $widthIn) + max(0.0, $depthIn);
                } else {
                    $baseIn += max(0.0, $widthIn);
                }
            }
        }

        $stairsCount = 0;
        $treads = 0;
        $runIn = 0.0;
        $quoteType = 'standard';

        foreach ($floor['stairs'] ?? [] as $s) {
            if (! is_array($s)) {
                continue;
            }
            $stairsCount++;
            $stairType = strtolower((string) ($s['stairType'] ?? 'straight'));
            $depthIn = ((float) ($s['depth'] ?? 300.0) * $cmPerUnit) / self::CM_PER_INCH;
            $runIn += max(0.0, $depthIn);

            $risers = (int) ($s['riserCount'] ?? 0);
            if ($risers <= 0) {
                $base = max(1, (int) round($depthIn / RoomAreaCanvasData::STAIR_TREAD_IN));
                $risers = match (true) {
                    str_contains($stairType, 'u') => $base + 6,
                    str_contains($stairType, 'l') => $base + 3,
                    default => $base,
                };
            }
            $treads += $risers;

            if (str_contains($stairType, 'u')) {
                $quoteType = 'double_landing';
            } elseif (str_contains($stairType, 'l') && $quoteType !== 'double_landing') {
                $quoteType = 'single_landing';
            }
        }

        return [
            'cabinet_base_lf' => $this->roundTo($baseIn / 12.0, 2),
            'cabinet_upper_lf' => $this->roundTo($upperIn / 12.0, 2),
            'stairs_count' => $stairsCount,
            'stairs_treads' => max(0, $treads),
            'stairs_run_lf' => $this->roundTo($runIn / 12.0, 2),
            'stairs_quote_type' => $quoteType,
        ];
    }

    private function roundTo(float $value, int $digits): float
    {
        return $this->finishMath->roundTo($value, $digits);
    }
}

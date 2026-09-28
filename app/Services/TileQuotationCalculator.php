<?php

namespace App\Services;

use App\Models\TileEdge;
use App\Models\TileMortarType;
use App\Models\TileGroutType;
use App\Models\TileProduct;
use App\Support\QuotationBuildingLabor;

class TileQuotationCalculator
{
    /**
     * Compute the tile quotation math based on inputs.
     *
     * @return array<string, mixed>
     */
    public function compute(
        float $lengthFt,
        float $widthFt,
        float $wasteMarginPercent,
        ?int $tileProductId,
        bool $includeTileMaterial,
        string $trowelSize,
        ?int $mortarTypeId,
        float $jointWidthDecimal,
        ?int $groutTypeId,
        float $laborRate,
        float $totalAreaSqft = 0,
        bool $includeDemolition = false,
        string $demolitionComplexity = 'standard',
        float $customDemolitionRate = 0.0,
        bool $includeDisposalFee = false,
        bool $includeEdge = false,
        ?int $tileEdgeId = null,
        float $edgeLf = 0.0,
        bool $includeBuilding = false,
        float $buildingLaborPercent = QuotationBuildingLabor::DEFAULT_PERCENT
    ): array {
        // 1. Total Area
        $netArea = $totalAreaSqft > 0 ? $totalAreaSqft : ($lengthFt * $widthFt);
        $totalArea = $netArea * (1 + ($wasteMarginPercent / 100));

        // Models
        $mortar = $mortarTypeId ? TileMortarType::query()->find($mortarTypeId) : null;
        $grout = $groutTypeId ? TileGroutType::query()->find($groutTypeId) : null;
        $tile = $tileProductId ? TileProduct::query()->find($tileProductId) : null;
        $edge = ($includeEdge && $tileEdgeId) ? TileEdge::query()->find($tileEdgeId) : null;

        // 2. Mortar Calculation
        $mortarBags = 0;
        $mortarTotalPrice = 0.0;
        $mortarCoverage = 0.0;

        if ($mortar && $totalArea > 0) {
            $baseCoverage = 0;
            if ($trowelSize === '1/4x1/4') {
                $baseCoverage = 90;
            } elseif ($trowelSize === '1/4x3/8') {
                $baseCoverage = 70;
            } elseif ($trowelSize === '1/2x1/2') {
                $baseCoverage = 40;
            }

            if ($baseCoverage > 0) {
                $bagWeight = (float) $mortar->bag_weight_lbs > 0 ? (float) $mortar->bag_weight_lbs : 50.0;
                $mortarCoverage = ($bagWeight / 50.0) * $baseCoverage;

                $mortarBags = (int) ceil($totalArea / $mortarCoverage);
                $mortarTotalPrice = $mortarBags * (float) $mortar->cost_price;
            }
        }

        // 3. Grout Calculation
        $groutBags = 0;
        $groutTotalPrice = 0.0;
        $groutPoundsPerSqft = 0.0;
        $groutTotalPounds = 0.0;

        if ($grout && $tile && $tile->length_inches > 0 && $tile->width_inches > 0 && $totalArea > 0) {
            $l = (float) $tile->length_inches;
            $w = (float) $tile->width_inches;
            $t = (float) $tile->thickness_inches;
            $j = $jointWidthDecimal;
            $d = (float) $grout->density_factor;

            if ($l > 0 && $w > 0) {
                $groutPoundsPerSqft = (($l + $w) * $t * $j * $d) / ($l * $w);
                $groutTotalPounds = $groutPoundsPerSqft * $totalArea;
                $bagWeight = (float) $grout->bag_weight_lbs > 0 ? (float) $grout->bag_weight_lbs : 10.0;
                $groutBags = (int) ceil($groutTotalPounds / $bagWeight);
                $groutTotalPrice = $groutBags * (float) $grout->cost_price;
            }
        }

        // 4. Tile Calculation
        $tileTotalPrice = 0.0;
        if ($tile && $includeTileMaterial) {
            $tileTotalPrice = $totalArea * (float) $tile->cost_per_sqft;
        }

        // 5. Tile Edge — sold in 8 ft pieces; round LF up to whole pieces
        $edgePieces = 0;
        $edgeTotalPrice = 0.0;
        $edgeUnitPrice = 0.0;
        $edgeName = null;
        $edgeThickness = null;
        $edgeColor = null;
        $normalizedEdgeLf = max(0, $edgeLf);

        if ($includeEdge && $edge && $normalizedEdgeLf > 0) {
            $edgePieces = (int) ceil($normalizedEdgeLf / TileEdge::PIECE_LENGTH_FT);
            $edgeUnitPrice = (float) $edge->unit_price;
            $edgeTotalPrice = $edgePieces * $edgeUnitPrice;
            $edgeName = $edge->name;
            $edgeThickness = $edge->thickness;
            $edgeColor = $edge->color;
        }

        // 6. Labor
        $laborTotal = $totalArea * $laborRate;
        $buildingInputs = [
            'include_building' => $includeBuilding,
            'building_labor_percent' => $buildingLaborPercent,
        ];
        $buildingSurcharge = QuotationBuildingLabor::surchargeAmount($laborTotal, $buildingInputs);
        $laborTotal = round($laborTotal + $buildingSurcharge, 2);

        // 7. Demolition & Prep Work
        $demolitionTotal = 0.0;
        $demolitionRate = 0.0;
        $disposalFee = 0.0;

        if ($includeDemolition && $netArea > 0) {
            if ($demolitionComplexity === 'standard') {
                $demolitionRate = 4.00;
            } elseif ($demolitionComplexity === 'heavy') {
                $demolitionRate = 6.50;
            } else {
                $demolitionRate = $customDemolitionRate;
            }

            $demolitionTotal = $netArea * $demolitionRate;

            if ($includeDisposalFee) {
                $disposalFee = 60.00;
                $demolitionTotal += $disposalFee;
            }
        }

        // Subtotals
        $materialsTotal = $mortarTotalPrice + $groutTotalPrice + $tileTotalPrice + $edgeTotalPrice;
        $subtotal = $materialsTotal + $laborTotal + $demolitionTotal;

        // HST
        $hstRate = \App\Models\TileQuotation::HST_RATE;
        $hst = $subtotal * $hstRate;
        $grandTotal = $subtotal + $hst;

        return [
            'net_area' => round($netArea, 2),
            'total_area' => round($totalArea, 2),

            'mortar_bags' => $mortarBags,
            'mortar_total_price' => round($mortarTotalPrice, 2),

            'grout_total_pounds' => round($groutTotalPounds, 2),
            'grout_bags' => $groutBags,
            'grout_total_price' => round($groutTotalPrice, 2),

            'tile_total_price' => round($tileTotalPrice, 2),

            'include_edge' => $includeEdge && $edgePieces > 0,
            'edge_lf' => round($normalizedEdgeLf, 2),
            'edge_piece_length_ft' => TileEdge::PIECE_LENGTH_FT,
            'edge_pieces' => $edgePieces,
            'edge_unit_price' => round($edgeUnitPrice, 4),
            'edge_total_price' => round($edgeTotalPrice, 2),
            'edge_name' => $edgeName,
            'edge_thickness' => $edgeThickness,
            'edge_color' => $edgeColor,

            'labor_total' => round($laborTotal, 2),
            'include_building' => $includeBuilding,
            'building_labor_percent' => QuotationBuildingLabor::percent($buildingInputs),
            'building_labor_surcharge' => round($buildingSurcharge, 2),

            'include_demolition' => $includeDemolition,
            'demolition_rate' => round($demolitionRate, 2),
            'disposal_fee' => round($disposalFee, 2),
            'demolition_total' => round($demolitionTotal, 2),

            'materials_subtotal' => round($materialsTotal, 2),
            'subtotal' => round($subtotal, 2),
            'hst' => round($hst, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}

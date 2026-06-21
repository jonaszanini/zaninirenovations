<?php

namespace App\Services;

use App\Models\TileMortarType;
use App\Models\TileGroutType;
use App\Models\TileProduct;

class TileQuotationCalculator
{
    /**
     * Compute the tile quotation math based on inputs.
     * 
     * @param float $lengthFt
     * @param float $widthFt
     * @param float $wasteMarginPercent
     * @param int|null $tileProductId
     * @param bool $includeTileMaterial
     * @param string $trowelSize ("1/4x1/4", "1/4x3/8", "1/2x1/2")
     * @param int|null $mortarTypeId
     * @param float $jointWidthDecimal
     * @param int|null $groutTypeId
     * @param float $laborRate
     * @return array
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
        float $laborRate
    ): array {
        // 1. Total Area
        $netArea = $lengthFt * $widthFt;
        $totalArea = $netArea * (1 + ($wasteMarginPercent / 100));

        // Models
        $mortar = $mortarTypeId ? TileMortarType::query()->find($mortarTypeId) : null;
        $grout = $groutTypeId ? TileGroutType::query()->find($groutTypeId) : null;
        $tile = $tileProductId ? TileProduct::query()->find($tileProductId) : null;

        // 2. Mortar Calculation
        $mortarBags = 0;
        $mortarTotalPrice = 0.0;
        $mortarCoverage = 0.0;

        if ($mortar && $totalArea > 0) {
            if ($trowelSize === '1/4x1/4') {
                $mortarCoverage = (float) $mortar->coverage_14_14;
            } elseif ($trowelSize === '1/4x3/8') {
                $mortarCoverage = (float) $mortar->coverage_14_38;
            } elseif ($trowelSize === '1/2x1/2') {
                $mortarCoverage = (float) $mortar->coverage_12_12;
            }
            
            if ($mortarCoverage > 0) {
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

        // 5. Labor
        $laborTotal = $totalArea * $laborRate;

        // Subtotals
        $materialsTotal = $mortarTotalPrice + $groutTotalPrice + $tileTotalPrice;
        $subtotal = $materialsTotal + $laborTotal;

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
            
            'labor_total' => round($laborTotal, 2),
            
            'materials_subtotal' => round($materialsTotal, 2),
            'subtotal' => round($subtotal, 2),
            'hst' => round($hst, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}

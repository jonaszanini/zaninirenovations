<?php

namespace App\Services;

use App\Models\PavingMarbleType;

/**
 * Decorative marble beds — bagged aggregate with shallow excavation (additive to paver stones).
 */
class PavingMarbleCalculator
{
    public const MARBLE_SPREAD_HRS_PER_SQ_FT = 0.015;

    public const EXCAVATION_FACTOR = 0.4;

    public const GEOTEXTILE_HRS_PER_SQ_FT = 0.007;

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>|null
     */
    public static function resolveInputs(array $inputs): ?array
    {
        if (! filter_var($inputs['marble_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $area = max(0.0, (float) ($inputs['marble_area_sq_ft'] ?? 0));
        if ($area <= 0) {
            return null;
        }

        $depthIn = max(0.5, (float) ($inputs['marble_depth_in'] ?? 2));
        $includeGeo = filter_var($inputs['marble_include_geotextile'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! empty($inputs['marble_type_id'])) {
            $type = PavingMarbleType::query()->find($inputs['marble_type_id']);
            if ($type) {
                return self::snapshotFromType($type, $area, $depthIn, $includeGeo);
            }
        }

        if (isset($inputs['marble_name'], $inputs['marble_price_per_bag'], $inputs['marble_sq_ft_per_bag_at_reference_depth'])) {
            return self::snapshotFromStored($inputs, $area, $depthIn, $includeGeo);
        }

        return null;
    }

    /**
     * @param  array<string, float>  $rates  rate_key => price_before_tax
     * @return array<string, mixed>|null
     */
    public static function calculate(
        array $inputs,
        array $rates,
        float $waste,
        float $hstRate,
        float $ownerRate,
        float $helperRate,
        bool $includeHelper = false,
    ): ?array {
        $resolved = self::resolveInputs($inputs);
        if ($resolved === null) {
            return null;
        }

        $area = (float) $resolved['area_sq_ft'];
        $depthIn = (float) $resolved['depth_in'];
        $includeGeo = (bool) $resolved['include_geotextile'];
        $cuFtPerBag = (float) $resolved['cu_ft_per_bag'];
        $refDepth = max(0.01, (float) $resolved['reference_depth_in']);

        $volumeCuFt = $area * ($depthIn / 12) * (1 + $waste);
        $bags = $cuFtPerBag > 0 ? (int) ceil($volumeCuFt / $cuFtPerBag - 1e-9) : 0;

        if (! empty($resolved['marble_type_id'])) {
            $type = PavingMarbleType::query()->find($resolved['marble_type_id']);
            if ($type) {
                $unitPrice = $type->effectivePricePerBag($bags);
                $resolved['effective_price_per_bag'] = $unitPrice;
            } else {
                $unitPrice = (float) ($resolved['effective_price_per_bag'] ?? $resolved['price_per_bag'] ?? 0);
            }
        } else {
            $pricePerBag = (float) ($resolved['price_per_bag'] ?? 0);
            $bulkMin = (int) ($resolved['bulk_min_bags'] ?? 0);
            $bulkPct = (float) ($resolved['bulk_discount_pct'] ?? 0);
            $unitPrice = $pricePerBag;
            if ($bags >= $bulkMin && $bulkMin > 0 && $bulkPct > 0) {
                $unitPrice = round($pricePerBag * (1 - ($bulkPct / 100)), 4);
            }
            $resolved['effective_price_per_bag'] = $unitPrice;
        }

        $bagCost = round($bags * $unitPrice, 2);

        $r = fn (string $key) => (float) ($rates[$key] ?? 0.0);

        $materialRows = [];
        $materialRows[] = self::matRow(
            ($resolved['marble_name'] ?? 'Decorative marble').' (bags)',
            'bags',
            $bags,
            $unitPrice,
            $bagCost,
            $hstRate,
            sprintf(
                '%s sq ft × %s in. depth; %s cu ft/bag at %s in. reference; %s%% waste',
                self::formatQty($area),
                self::formatQty($depthIn),
                self::formatQty($cuFtPerBag),
                self::formatQty($refDepth),
                round($waste * 100, 1),
            ),
        );

        $delivery = (float) ($resolved['delivery_cost'] ?? 0);
        if ($delivery > 0) {
            $materialRows[] = self::matRow(
                ($resolved['marble_name'] ?? 'Marble').' delivery',
                'flat',
                1,
                $delivery,
                $delivery,
                $hstRate,
                'Delivery cost for marble',
            );
        }

        $addCost = (float) ($resolved['additional_cost'] ?? 0);
        if ($addCost > 0) {
            $desc = filled($resolved['additional_cost_description'] ?? null)
                ? (string) $resolved['additional_cost_description']
                : ($resolved['marble_name'] ?? 'Marble').' additional cost';
            $materialRows[] = self::matRow($desc, 'flat', 1, $addCost, $addCost, $hstRate, 'Additional marble cost');
        }

        $qtyGeoRolls = $includeGeo ? (int) ceil($area / 150 - 1e-9) : 0;
        $costGeo = $qtyGeoRolls * $r('geotextile_roll');
        $qtyStapleBoxes = $includeGeo ? (int) ceil($area / 150 * 2 - 1e-9) : 0;
        $costStaples = $qtyStapleBoxes * $r('fabric_staples_box');

        $materialRows[] = self::matRow(
            'Marble bed geotextile fabric',
            'rolls',
            $qtyGeoRolls,
            $r('geotextile_roll'),
            $costGeo,
            $hstRate,
            $includeGeo ? '150 sq ft per roll for marble bed' : 'Disabled',
        );
        $materialRows[] = self::matRow(
            'Marble bed fabric staples',
            'boxes',
            $qtyStapleBoxes,
            $r('fabric_staples_box'),
            $costStaples,
            $hstRate,
            $includeGeo ? '~2 boxes per roll of fabric' : 'Disabled',
        );

        $marbleVolumeCuYd = $area > 0 && $depthIn > 0 ? ($area * ($depthIn / 12) / 27) : 0.0;
        $qtyTonsDisposal = $marbleVolumeCuYd > 0 ? (int) ceil($marbleVolumeCuYd * 1.5 - 1e-9) : 0;
        $costDisposal = $qtyTonsDisposal * $r('disposal_clean_fill_ton');
        $materialRows[] = self::matRow(
            'Marble bed disposal (clean fill)',
            'tons',
            $qtyTonsDisposal,
            $r('disposal_clean_fill_ton'),
            $costDisposal,
            $hstRate,
            sprintf('%s cu yd shallow excavation × 1.5 tons/cu yd', self::formatQty($marbleVolumeCuYd)),
        );

        $matPre = 0.0;
        $matHst = 0.0;
        foreach ($materialRows as $row) {
            $matPre += $row['before_tax'];
            $matHst += $row['hst'];
        }
        $matPre = round($matPre, 2);
        $matHst = round($matHst, 2);

        $helperEnabled = $includeHelper && $helperRate > 0;
        $labourDefs = self::labourHours($area, $depthIn, $includeGeo, $helperEnabled);
        $labourRows = [];
        $ownerHrsSum = 0.0;
        $helperHrsSum = 0.0;
        $ownerCostSum = 0.0;
        $helperCostSum = 0.0;

        foreach ($labourDefs as $def) {
            $oh = $def['owner_hrs'];
            $hh = $def['helper_hrs'];
            $oc = round($oh * $ownerRate, 2);
            $hc = round($hh * $helperRate, 2);
            $ownerHrsSum += $oh;
            $helperHrsSum += $hh;
            $ownerCostSum += $oc;
            $helperCostSum += $hc;
            $labourRows[] = [
                'task' => $def['task'],
                'owner_hrs' => round($oh, 2),
                'helper_hrs' => round($hh, 2),
                'owner_rate' => $ownerRate,
                'helper_rate' => $helperRate,
                'owner_cost' => $oc,
                'helper_cost' => $hc,
                'total' => round($oc + $hc, 2),
            ];
        }

        $labourTotal = round($ownerCostSum + $helperCostSum, 2);
        $withTax = round($matPre + $matHst + $labourTotal, 2);

        return [
            'enabled' => true,
            'marble_type_id' => $resolved['marble_type_id'] ?? null,
            'marble_name' => $resolved['marble_name'] ?? '',
            'area_sq_ft' => round($area, 2),
            'depth_in' => round($depthIn, 2),
            'bags' => $bags,
            'include_geotextile' => $includeGeo,
            'effective_price_per_bag' => $unitPrice,
            'resolved' => $resolved,
            'material_rows' => $materialRows,
            'labour_rows' => $labourRows,
            'materials_subtotal_before_tax' => $matPre,
            'materials_hst' => $matHst,
            'materials_with_tax' => round($matPre + $matHst, 2),
            'labour_total' => $labourTotal,
            'subtotal_with_tax_and_labour' => $withTax,
        ];
    }

    /**
     * @return list<array{task: string, owner_hrs: float, helper_hrs: float}>
     */
    public static function labourHours(float $area, float $depthIn, bool $includeGeo, bool $helperEnabled): array
    {
        $h = static function (float $owner) use ($helperEnabled): array {
            return [
                'owner_hrs' => $owner,
                'helper_hrs' => $helperEnabled ? $owner : 0.0,
            ];
        };

        $excavHrs = $area > 0 && $depthIn > 0
            ? round(($area * $depthIn / 12 / 27) * self::EXCAVATION_FACTOR, 2)
            : 0.0;

        return [
            array_merge(['task' => 'Marble bed excavation'], $h($excavHrs)),
            array_merge(['task' => 'Marble bed geotextile installation'], $h($includeGeo ? round($area * self::GEOTEXTILE_HRS_PER_SQ_FT, 2) : 0.0)),
            array_merge(['task' => 'Marble spread & finish'], $h(round($area * self::MARBLE_SPREAD_HRS_PER_SQ_FT, 2))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshotFromType(PavingMarbleType $type, float $area, float $depthIn, bool $includeGeo): array
    {
        $bagsPreview = self::estimateBags($type, $area, $depthIn, 0.05);

        return [
            'marble_type_id' => (int) $type->id,
            'marble_name' => $type->name,
            'area_sq_ft' => round($area, 2),
            'depth_in' => round($depthIn, 2),
            'include_geotextile' => $includeGeo,
            'reference_depth_in' => (float) $type->reference_depth_in,
            'sq_ft_per_bag_at_reference_depth' => (float) $type->sq_ft_per_bag_at_reference_depth,
            'cu_ft_per_bag' => $type->cuFtPerBag(),
            'price_per_bag' => (float) $type->price_per_bag,
            'effective_price_per_bag' => $type->effectivePricePerBag($bagsPreview),
            'bulk_min_bags' => $type->bulk_min_bags,
            'bulk_discount_pct' => $type->bulk_discount_pct,
            'delivery_cost' => $type->delivery_cost ? (float) $type->delivery_cost : null,
            'additional_cost' => $type->additional_cost ? (float) $type->additional_cost : null,
            'additional_cost_description' => $type->additional_cost_description,
            'stone_size_in' => $type->stone_size_in ? (float) $type->stone_size_in : null,
            'product_url' => $type->product_url,
            'image_url' => $type->image_url,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected static function snapshotFromStored(array $inputs, float $area, float $depthIn, bool $includeGeo): array
    {
        $refDepth = max(0.01, (float) ($inputs['marble_reference_depth_in'] ?? 2));
        $sqFtPerBag = (float) ($inputs['marble_sq_ft_per_bag_at_reference_depth'] ?? 0);
        $cuFtPerBag = round($sqFtPerBag * ($refDepth / 12), 4);
        $bagsPreview = $cuFtPerBag > 0 ? (int) ceil($area * ($depthIn / 12) * 1.05 / $cuFtPerBag - 1e-9) : 0;
        $pricePerBag = (float) ($inputs['marble_price_per_bag'] ?? 0);
        $bulkMin = (int) ($inputs['marble_bulk_min_bags'] ?? 0);
        $bulkPct = (float) ($inputs['marble_bulk_discount_pct'] ?? 0);
        $effective = $pricePerBag;
        if ($bagsPreview >= $bulkMin && $bulkMin > 0 && $bulkPct > 0) {
            $effective = round($pricePerBag * (1 - ($bulkPct / 100)), 4);
        }

        return [
            'marble_type_id' => isset($inputs['marble_type_id']) ? (int) $inputs['marble_type_id'] : null,
            'marble_name' => (string) ($inputs['marble_name'] ?? 'Decorative marble'),
            'area_sq_ft' => round($area, 2),
            'depth_in' => round($depthIn, 2),
            'include_geotextile' => $includeGeo,
            'reference_depth_in' => $refDepth,
            'sq_ft_per_bag_at_reference_depth' => $sqFtPerBag,
            'cu_ft_per_bag' => $cuFtPerBag,
            'price_per_bag' => $pricePerBag,
            'effective_price_per_bag' => $effective,
            'bulk_min_bags' => $bulkMin ?: null,
            'bulk_discount_pct' => $bulkPct ?: null,
            'delivery_cost' => isset($inputs['marble_delivery_cost']) ? (float) $inputs['marble_delivery_cost'] : null,
            'additional_cost' => isset($inputs['marble_additional_cost']) ? (float) $inputs['marble_additional_cost'] : null,
            'additional_cost_description' => $inputs['marble_additional_cost_description'] ?? null,
        ];
    }

    public static function estimateBags(PavingMarbleType $type, float $area, float $depthIn, float $waste = 0.05): int
    {
        $cuFt = $type->cuFtPerBag();
        if ($cuFt <= 0 || $area <= 0) {
            return 0;
        }

        $volume = $area * ($depthIn / 12) * (1 + $waste);

        return (int) ceil($volume / $cuFt - 1e-9);
    }

    /**
     * @return array{label: string, unit: string, qty: float|int, unit_price: float, before_tax: float, hst: float, with_tax: float, note: string}
     */
    protected static function matRow(
        string $label,
        string $unit,
        float|int $qty,
        float $unitPrice,
        float $beforeTax,
        float $hstRate,
        string $note,
    ): array {
        $bt = round($beforeTax, 2);
        $hst = round($bt * $hstRate, 2);

        return [
            'label' => $label,
            'unit' => $unit,
            'qty' => $qty,
            'unit_price' => round($unitPrice, 4),
            'before_tax' => $bt,
            'hst' => $hst,
            'with_tax' => round($bt + $hst, 2),
            'note' => $note,
        ];
    }

    protected static function formatQty(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

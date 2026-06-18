<?php

namespace App\Services;

use App\Models\PavingMaterialRate;
use App\Models\PavingPricingTier;
use App\Models\PavingStoneType;
use Illuminate\Support\Collection;

/**
 * Replicates logic from Zanini_Paving_Calculator_v2.xlsx — "ESTIMATE CALCULATOR" sheet.
 *
 * Final quote (B79) defaults to the built-up suggested total (B75 = D71), not the
 * separate market tier table on "PRICE REFERENCE & DATABASE".
 *
 * Note: the workbook's polymeric row mistakenly pulled the staples unit price (E17);
 * this engine always uses {@see PavingMaterialRate} key `polymeric_sand_bag` (E15 / $40).
 */
class PavingQuotationCalculator
{
    /** @var array<string, float> */
    protected array $rates = [];

    /** @var list<array{area: float, pps: float}> */
    protected array $tiers = [];

    public function __construct(?Collection $rateRows = null, ?Collection $tierRows = null)
    {
        $rates = $rateRows ?? PavingMaterialRate::query()->orderBy('sort_order')->get();
        foreach ($rates as $row) {
            $this->rates[(string) $row->rate_key] = (float) $row->price_before_tax;
        }

        $tiers = $tierRows ?? PavingPricingTier::query()->orderBy('area_sq_ft')->get();
        foreach ($tiers as $t) {
            $this->tiers[] = [
                'area' => (float) $t->area_sq_ft,
                'pps' => (float) $t->price_per_sq_ft,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs): array
    {
        $area = max(0.0, (float) ($inputs['total_area_sq_ft'] ?? 0));
        $waste = (float) ($inputs['waste_factor'] ?? 0.05);
        $depthIn = max(0.0, (float) ($inputs['excavation_depth_in'] ?? 12));
        $hst = (float) ($inputs['hst_rate'] ?? 0.13);
        $helperRate = array_key_exists('helper_hourly_rate', $inputs)
            ? max(0.0, (float) $inputs['helper_hourly_rate'])
            : 21.0;
        $ownerRate = array_key_exists('owner_hourly_rate', $inputs)
            ? max(0.0, (float) $inputs['owner_hourly_rate'])
            : 65.0;
        $miniDays = max(0.0, (float) ($inputs['mini_skid_days'] ?? 0));
        $plateDays = max(0.0, (float) ($inputs['plate_compactor_days'] ?? 0));
        $profitMargin = max(0.0, (float) ($inputs['profit_margin'] ?? 0.05));

        $poly = filter_var($inputs['add_polymeric_sand'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $geo = filter_var($inputs['include_geotextile'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $oldRemoval = filter_var($inputs['include_old_surface_removal'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $stoneSqFt = isset($inputs['stone_sq_ft_per_stone']) ? (float) $inputs['stone_sq_ft_per_stone'] : 0.0;
        $stonePrice = isset($inputs['stone_price_per_stone']) ? (float) $inputs['stone_price_per_stone'] : 0.0;
        $stonePricePerSqFt = isset($inputs['stone_price_per_sq_ft']) ? (float) $inputs['stone_price_per_sq_ft'] : 0.0;

        $marbleOn = filter_var($inputs['marble_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $marbleArea = $marbleOn ? max(0.0, (float) ($inputs['marble_area_sq_ft'] ?? 0)) : 0.0;
        $marbleArea = min($marbleArea, $area);
        $regularArea = max(0.0, $area - $marbleArea);

        $marbleSqFt = isset($inputs['marble_sq_ft_per_stone']) ? (float) $inputs['marble_sq_ft_per_stone'] : 0.0;
        $marblePrice = isset($inputs['marble_price_per_stone']) ? (float) $inputs['marble_price_per_stone'] : 0.0;
        $marblePricePerSqFt = isset($inputs['marble_price_per_sq_ft']) ? (float) $inputs['marble_price_per_sq_ft'] : 0.0;

        if ($stoneSqFt > 0) {
            $qtyMain = $area > 0 ? (int) ceil(($regularArea / $stoneSqFt) * (1 + $waste) - 1e-9) : 0;
            $costMain = $qtyMain * $stonePrice;
            $matRowMain = $this->matRow('Main paving stones', 'stones', $qtyMain, $stonePrice, $costMain, $hst, 'Area ÷ sq ft per stone × (1 + waste) — regular portion');
        } else {
            $qtyMain = $regularArea > 0 ? round($regularArea * (1 + $waste), 2) : 0.0;
            $costMain = $qtyMain * $stonePricePerSqFt;
            $matRowMain = $this->matRow('Main paving stones', 'sq ft', $qtyMain, $stonePricePerSqFt, $costMain, $hst, 'Area × (1 + waste) × price per sq ft');
        }

        if ($marbleOn && $marbleArea > 0) {
            if ($marbleSqFt > 0) {
                $qtyMarble = (int) ceil(($marbleArea / $marbleSqFt) * (1 + $waste) - 1e-9);
                $costMarble = $qtyMarble * $marblePrice;
                $matRowMarble = $this->matRow('Marble / accent paving stones', 'stones', $qtyMarble, $marblePrice, $costMarble, $hst, 'Marble inlay area ÷ sq ft per stone × (1 + waste)');
            } else {
                $qtyMarble = round($marbleArea * (1 + $waste), 2);
                $costMarble = $qtyMarble * $marblePricePerSqFt;
                $matRowMarble = $this->matRow('Marble / accent paving stones', 'sq ft', $qtyMarble, $marblePricePerSqFt, $costMarble, $hst, 'Marble inlay area × (1 + waste) × price per sq ft');
            }
        } else {
            $qtyMarble = 0;
            $costMarble = 0.0;
            $matRowMarble = $this->matRow('Marble / accent paving stones', 'stones', 0, 0, 0, $hst, '—');
        }

        $r = fn (string $key) => (float) ($this->rates[$key] ?? 0.0);

        $qtyGranular = $this->ceilingStep(($area * ($depthIn / 12) * 0.7407 / 27), 0.5);
        $costGranular = $qtyGranular * $r('crushed_stone_cu_yd');

        $qtyBedding = $this->ceilingStep(($area * (1 / 12) / 27), 0.5);
        $costBedding = $qtyBedding * $r('bedding_sand_cu_yd');

        $qtyPoly = $poly ? (int) ceil($area / 50 - 1e-9) : 0;
        $costPoly = $qtyPoly * $r('polymeric_sand_bag');

        $qtyGeoRolls = $geo ? (int) ceil($area / 150 - 1e-9) : 0;
        $costGeo = $qtyGeoRolls * $r('geotextile_roll');

        $qtyStapleBoxes = $geo ? (int) ceil($area / 150 * 2 - 1e-9) : 0;
        $costStaples = $qtyStapleBoxes * $r('fabric_staples_box');

        $qtyEdge = $area > 0 ? (int) ceil((sqrt($area) * 4 / 6) * 1.1 - 1e-9) : 0;
        $costEdge = $qtyEdge * $r('edge_restraint_piece');

        $qtySpikes = $qtyEdge > 0 ? (int) ceil($qtyEdge / 3 - 1e-9) : 0;
        $costSpikes = $qtySpikes * $r('edging_spikes_pack');

        $materialRows = [
            $matRowMain,
            $matRowMarble,
            $this->matRow('Granular base (crushed 3/4")', 'cu yd', $qtyGranular, $r('crushed_stone_cu_yd'), $costGranular, $hst, '8" compacted base: area × depth(ft) × 0.7407 ÷ 27; 0.5 yd steps'),
            $this->matRow('Bedding sand', 'cu yd', $qtyBedding, $r('bedding_sand_cu_yd'), $costBedding, $hst, '1" layer; 0.5 yd steps'),
            $this->matRow('Polymeric sand', 'bags', $qtyPoly, $r('polymeric_sand_bag'), $costPoly, $hst, '~50 sq ft per bag when enabled'),
            $this->matRow('Geotextile fabric', 'rolls', $qtyGeoRolls, $r('geotextile_roll'), $costGeo, $hst, '150 sq ft per roll when enabled'),
            $this->matRow('Fabric staples', 'boxes', $qtyStapleBoxes, $r('fabric_staples_box'), $costStaples, $hst, '~2 boxes per roll of fabric'),
            $this->matRow('Edge restraints (6 ft)', 'pieces', $qtyEdge, $r('edge_restraint_piece'), $costEdge, $hst, 'Perimeter √(area)×4 ÷ 6 ft + 10%'),
            $this->matRow('Edging spikes', 'packs', $qtySpikes, $r('edging_spikes_pack'), $costSpikes, $hst, '~1 spike pack per 3 edge pieces'),
        ];

        $matPre = 0.0;
        $matHst = 0.0;
        foreach ($materialRows as $row) {
            $matPre += $row['before_tax'];
            $matHst += $row['hst'];
        }
        $matPre = round($matPre, 2);
        $matHst = round($matHst, 2);
        $matWithTax = round($matPre + $matHst, 2);

        $qtyBins = $area > 0 && $depthIn > 0
            ? (int) ceil($area * ($depthIn / 12) / 27 / 14 - 1e-9)
            : 0;
        $costBins = $qtyBins * $r('bin_rental_14yd');

        $qtyTonsDisposal = $area > 0 && $depthIn > 0
            ? (int) ceil($area * ($depthIn / 12) / 27 * 1.5 - 1e-9)
            : 0;
        $costDisposal = $qtyTonsDisposal * $r('disposal_clean_fill_ton');

        $costMini = $miniDays * $r('mini_skid_steer_day');
        $costPlate = $plateDays * $r('plate_compactor_day');

        $fixedRows = [
            $this->matRow('Bin rental (14 yd)', 'bins', $qtyBins, $r('bin_rental_14yd'), $costBins, $hst, 'Excavation volume ÷ 14 cu yd per bin'),
            $this->matRow('Disposal (clean fill)', 'tons', $qtyTonsDisposal, $r('disposal_clean_fill_ton'), $costDisposal, $hst, '~1.5 tons per cu yd excavated'),
            $this->matRow('Mini skid steer', 'days', $miniDays, $r('mini_skid_steer_day'), $costMini, $hst, 'From project inputs'),
            $this->matRow('Plate compactor', 'days', $plateDays, $r('plate_compactor_day'), $costPlate, $hst, 'From project inputs'),
        ];

        $fixPre = 0.0;
        $fixHst = 0.0;
        foreach ($fixedRows as $row) {
            $fixPre += $row['before_tax'];
            $fixHst += $row['hst'];
        }
        $fixPre = round($fixPre, 2);
        $fixHst = round($fixHst, 2);
        $fixWithTax = round($fixPre + $fixHst, 2);

        $labourDefs = $this->labourHours(
            $area,
            $depthIn,
            $poly,
            $geo,
            $oldRemoval,
            $helperRate > 0
        );

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

        $ownerHrsSum = round($ownerHrsSum, 2);
        $helperHrsSum = round($helperHrsSum, 2);
        $ownerCostSum = round($ownerCostSum, 2);
        $helperCostSum = round($helperCostSum, 2);
        $labourTotal = round($ownerCostSum + $helperCostSum, 2);

        $preTaxMatFix = round($matPre + $fixPre, 2);
        $hstMatFix = round($matHst + $fixHst, 2);
        $withTaxMatFix = round($matWithTax + $fixWithTax, 2);

        $subtotalPreTaxModel = round($preTaxMatFix + $labourTotal, 2);
        $totalWithTaxMaterialsFixedPlusLabour = round($withTaxMatFix + $labourTotal, 2);
        $profitAmount = round($totalWithTaxMaterialsFixedPlusLabour * $profitMargin, 2);
        $builtUpGrandTotal = round($totalWithTaxMaterialsFixedPlusLabour + $profitAmount, 2);

        // Excel B74 = D71/B5, B75 = ROUND(B74*B5,2) — suggested quote equals built-up total (D71).
        $formulaPricePerSqFt = $area > 0 ? round($builtUpGrandTotal / $area, 4) : 0.0;
        $referenceSuggestedTotal = $builtUpGrandTotal;

        // Optional market reference from the pricing-tier table (not used for final quote in Excel).
        $marketRefPps = $this->interpolatePricePerSqFt($area);
        $marketReferenceTotal = round($marketRefPps * $area, 2);

        $manual = $inputs['manual_price_override'] ?? null;
        $manualNum = is_numeric($manual) && (float) $manual > 0 ? round((float) $manual, 2) : null;

        // Excel B79 = IF(ISNUMBER(B78), B78, B75)
        $finalQuote = $manualNum ?? $referenceSuggestedTotal;

        $grossProfit = round($finalQuote - $withTaxMatFix - $helperCostSum, 2);
        $marginPct = $finalQuote > 0 ? round($grossProfit / $finalQuote, 6) : 0.0;
        $totalLabourHrs = $ownerHrsSum + $helperHrsSum;
        $effectiveHr = $totalLabourHrs > 0 ? round($grossProfit / $totalLabourHrs, 4) : 0.0;
        $workingDays = $totalLabourHrs > 0 ? round($totalLabourHrs / 2 / 8, 1) : 0.0;

        return [
            'inputs' => $inputs,
            'areas' => [
                'total_sq_ft' => round($area, 2),
                'regular_sq_ft' => round($regularArea, 2),
                'marble_sq_ft' => round($marbleArea, 2),
            ],
            'materials' => [
                'rows' => $materialRows,
                'subtotal_before_tax' => $matPre,
                'hst' => $matHst,
                'with_tax' => $matWithTax,
                'per_sq_ft_before_tax' => $area > 0 ? round($matPre / $area, 4) : 0.0,
            ],
            'fixed_costs' => [
                'rows' => $fixedRows,
                'subtotal_before_tax' => $fixPre,
                'hst' => $fixHst,
                'with_tax' => $fixWithTax,
                'per_sq_ft_before_tax' => $area > 0 ? round($fixPre / $area, 4) : 0.0,
            ],
            'labour' => [
                'rows' => $labourRows,
                'owner_hours' => $ownerHrsSum,
                'helper_hours' => $helperHrsSum,
                'owner_cost' => $ownerCostSum,
                'helper_cost' => $helperCostSum,
                'total' => $labourTotal,
            ],
            'summary' => [
                'pre_tax_materials_and_fixed' => $preTaxMatFix,
                'hst_on_materials_and_fixed' => $hstMatFix,
                'with_tax_materials_and_fixed' => $withTaxMatFix,
                'labour_total' => $labourTotal,
                'subtotal_pre_tax_model' => $subtotalPreTaxModel,
                'total_with_tax_mats_fixed_plus_labour' => $totalWithTaxMaterialsFixedPlusLabour,
                'profit_margin_rate' => $profitMargin,
                'profit_margin_amount' => $profitAmount,
                'built_up_grand_total' => $builtUpGrandTotal,
                'reference_price_per_sq_ft' => $formulaPricePerSqFt,
                'reference_suggested_total' => $referenceSuggestedTotal,
                'market_reference_price_per_sq_ft' => round($marketRefPps, 4),
                'market_reference_total' => $marketReferenceTotal,
                'manual_price_override' => $manualNum,
                'final_quote_total' => $finalQuote,
                'gross_profit' => $grossProfit,
                'margin_percent' => $marginPct,
                'effective_hourly_profit' => $effectiveHr,
                'working_days' => $workingDays,
                'cost_per_sq_ft_built_up' => $area > 0 ? round($builtUpGrandTotal / $area, 4) : 0.0,
            ],
        ];
    }

    /**
     * @return list<array{task: string, owner_hrs: float, helper_hrs: float}>
     */
    protected function labourHours(
        float $area,
        float $depthIn,
        bool $poly,
        bool $geo,
        bool $oldRemoval,
        bool $helperEnabled
    ): array {
        $b5 = $area;
        $b15 = $depthIn;
        $h = static function (float $owner) use ($helperEnabled): array {
            return [
                'owner_hrs' => $owner,
                'helper_hrs' => $helperEnabled ? $owner : 0.0,
            ];
        };

        $rows = [];
        $rows[] = array_merge(['task' => 'Site preparation & layout'], $h(round(3 + $b5 / max(1.0, $b5) * 0, 2)));
        $rows[] = array_merge(['task' => 'Excavation (with skid steer)'], $h(round(($b5 * $b15 / 12 / 27) * 0.4, 2)));
        $rows[] = array_merge(['task' => 'Geotextile fabric installation'], $h($geo ? round($b5 * 0.007, 2) : 0.0));
        $rows[] = array_merge(['task' => 'Base material & compaction'], $h(round($b5 * 0.026, 2)));
        $rows[] = array_merge(['task' => 'Bedding sand & leveling'], $h(round($b5 * 0.015, 2)));
        $rows[] = array_merge(['task' => 'Paver installation'], $h(round($b5 * 0.055, 2)));
        $rows[] = array_merge(['task' => 'Edge restraints installation'], $h(round(sqrt(max(0.0, $b5)) * 4 / 6 * 0.17, 2)));
        $rows[] = array_merge(['task' => 'Polymeric sand application'], $h($poly ? round($b5 * 0.009, 2) : 0.0));
        $rows[] = array_merge(['task' => 'Old surface removal'], $h($oldRemoval ? round($b5 * 0.015, 2) : 0.0));
        $rows[] = array_merge(['task' => 'Final cleanup & site tidy'], $h(round(2 + $b5 / 500 * 1, 2)));

        return $rows;
    }

    /**
     * @return array{label: string, unit: string, qty: float|int, unit_price: float, before_tax: float, hst: float, with_tax: float, note: string}
     */
    protected function matRow(
        string $label,
        string $unit,
        float|int $qty,
        float $unitPrice,
        float $beforeTax,
        float $hstRate,
        string $note
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

    protected function ceilingStep(float $x, float $step): float
    {
        if ($x <= 0) {
            return 0.0;
        }

        return ceil($x / $step - 1e-9) * $step;
    }

    public function interpolatePricePerSqFt(float $area): float
    {
        if ($area <= 0 || $this->tiers === []) {
            return 0.0;
        }

        $first = $this->tiers[0];
        if ($area <= $first['area']) {
            return $first['pps'];
        }

        $last = $this->tiers[count($this->tiers) - 1];
        if ($area >= $last['area']) {
            return $last['pps'];
        }

        for ($i = 0; $i < count($this->tiers) - 1; $i++) {
            $a0 = $this->tiers[$i]['area'];
            $a1 = $this->tiers[$i + 1]['area'];
            if ($area >= $a0 && $area <= $a1) {
                $p0 = $this->tiers[$i]['pps'];
                $p1 = $this->tiers[$i + 1]['pps'];
                if ($a1 <= $a0) {
                    return $p0;
                }
                $t = ($area - $a0) / ($a1 - $a0);

                return $p0 + $t * ($p1 - $p0);
            }
        }

        return $last['pps'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(?PavingStoneType $defaultStone = null): array
    {
        $stone = $defaultStone ?? PavingStoneType::query()->orderBy('sort_order')->first();

        return [
            'total_area_sq_ft' => 168,
            'area_input_mode' => 'total',
            'area_length_ft' => null,
            'area_width_ft' => null,
            'paving_stone_type_id' => $stone?->id,
            'stone_sq_ft_per_stone' => $stone && $stone->sq_ft_per_stone ? (float) $stone->sq_ft_per_stone : null,
            'stone_price_per_stone' => $stone && $stone->price_per_stone ? (float) $stone->price_per_stone : null,
            'stone_price_per_sq_ft' => $stone && $stone->price_per_sq_ft ? (float) $stone->price_per_sq_ft : null,
            'helper_hourly_rate' => 21,
            'owner_hourly_rate' => 65,
            'mini_skid_days' => 1,
            'plate_compactor_days' => 1,
            'profit_margin' => 0.05,
            'waste_factor' => 0.05,
            'excavation_depth_in' => 12,
            'hst_rate' => 0.13,
            'add_polymeric_sand' => true,
            'include_old_surface_removal' => false,
            'include_geotextile' => true,
            'marble_enabled' => false,
            'marble_stone_type_id' => null,
            'marble_area_sq_ft' => 0,
            'marble_sq_ft_per_stone' => null,
            'marble_price_per_stone' => null,
            'marble_price_per_sq_ft' => null,
            'manual_price_override' => null,
        ];
    }
}

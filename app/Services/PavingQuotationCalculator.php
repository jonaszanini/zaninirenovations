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
    private const STONE_COMPACTION_FACTOR = 1.20;

    private const STONE_TONS_PER_CU_YD = 1.4;

    private const SAND_TONS_PER_CU_YD = 1.35;

    private const DEFAULT_BEDDING_SAND_DEPTH_IN = 1.0;

    /** Typical paver thickness when the stone type has no height on file. */
    private const DEFAULT_PAVER_HEIGHT_IN = 2.0;

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
        $stoneEntries = PavingStoneEntryResolver::normalize($inputs);
        $area = PavingStoneEntryResolver::totalArea($stoneEntries);
        $inputs['stone_entries'] = $stoneEntries;
        $inputs['total_area_sq_ft'] = $area;

        $primaryEntry = PavingStoneEntryResolver::primaryEntryForCoverage($stoneEntries);
        if ($primaryEntry) {
            $inputs['paving_stone_type_id'] = $primaryEntry['stone_type_id'] ?? null;
            $inputs['stone_length_in'] = $primaryEntry['length_in'] ?? null;
            $inputs['stone_width_in'] = $primaryEntry['width_in'] ?? null;
        }

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

        $materialRows = [];
        foreach ($stoneEntries as $entry) {
            $stoneName = (string) ($entry['stone_name'] ?? 'Paving stones');
            $line = PavingStoneEntryResolver::materialLine($entry, $waste, true);
            $materialRows[] = $this->matRow(
                $stoneName.' paving stones',
                $line['unit'],
                $line['qty'],
                $line['unit_price'],
                $line['before_tax'],
                $hst,
                $line['note'],
            );

            $delivery = isset($entry['delivery_cost']) ? (float) $entry['delivery_cost'] : 0.0;
            if ($delivery > 0) {
                $materialRows[] = $this->matRow(
                    $stoneName.' delivery',
                    'flat',
                    1,
                    $delivery,
                    $delivery,
                    $hst,
                    'Delivery cost for '.$stoneName,
                );
            }

            $addCost = isset($entry['additional_cost']) ? (float) $entry['additional_cost'] : 0.0;
            if ($addCost > 0) {
                $desc = isset($entry['additional_cost_description']) && trim((string) $entry['additional_cost_description']) !== ''
                    ? trim((string) $entry['additional_cost_description'])
                    : $stoneName.' additional cost';
                $materialRows[] = $this->matRow($desc, 'flat', 1, $addCost, $addCost, $hst, 'Additional cost for '.$stoneName);
            }
        }

        $r = fn (string $key) => (float) ($this->rates[$key] ?? 0.0);

        $excavationDepthIn = max(0.0, (float) ($inputs['excavation_depth_in'] ?? 12));
        $sandDepthIn = max(0.0, (float) ($inputs['bedding_sand_depth_in'] ?? self::DEFAULT_BEDDING_SAND_DEPTH_IN));
        $paverHeightIn = PavingStoneEntryResolver::maxPaverHeightIn($stoneEntries);
        if ($paverHeightIn <= 0) {
            $paverHeightIn = $this->resolveStoneHeightIn($inputs);
        }
        $stoneDepthIn = $this->resolveCrushedBaseDepthIn($excavationDepthIn, $paverHeightIn, $sandDepthIn);

        $inputs['stone_height_in'] = $paverHeightIn;
        $inputs['crushed_base_depth_in'] = $stoneDepthIn;

        $stoneCuFt = $area > 0 && $stoneDepthIn > 0
            ? $area * ($stoneDepthIn / 12) * self::STONE_COMPACTION_FACTOR
            : 0.0;
        $qtyGranular = $this->ceilingStep($stoneCuFt / 27, 0.5);
        $qtyGranularTons = $this->ceilingStep($qtyGranular * self::STONE_TONS_PER_CU_YD, 0.5);
        $costGranular = $qtyGranular * $r('crushed_stone_cu_yd');

        $sandCuFt = $area > 0 && $sandDepthIn > 0
            ? $area * ($sandDepthIn / 12)
            : 0.0;
        $qtyBedding = $this->ceilingStep($sandCuFt / 27, 0.5);
        $qtyBeddingTons = $this->ceilingStep($qtyBedding * self::SAND_TONS_PER_CU_YD, 0.5);
        $costBedding = $qtyBedding * $r('bedding_sand_cu_yd');

        [$paverLengthIn, $paverWidthIn] = $this->resolvePaverDimensions($inputs);
        $polyCoverage = $this->polymericSandCoverageSqFt($paverLengthIn, $paverWidthIn);
        $qtyPoly = $poly && $area > 0
            ? (int) ceil($area / $polyCoverage - 1e-9)
            : 0;
        $costPoly = $qtyPoly * $r('polymeric_sand_bag');

        $qtyGeoRolls = $geo ? (int) ceil($area / 150 - 1e-9) : 0;
        $costGeo = $qtyGeoRolls * $r('geotextile_roll');

        $qtyStapleBoxes = $geo ? (int) ceil($area / 150 * 2 - 1e-9) : 0;
        $costStaples = $qtyStapleBoxes * $r('fabric_staples_box');

        $qtyEdge = $area > 0 ? (int) ceil((sqrt($area) * 4 / 6) * 1.1 - 1e-9) : 0;
        $costEdge = $qtyEdge * $r('edge_restraint_piece');

        $qtySpikes = $qtyEdge > 0 ? (int) ceil($qtyEdge / 3 - 1e-9) : 0;
        $costSpikes = $qtySpikes * $r('edging_spikes_pack');

        $materialRows = array_merge($materialRows, [
            $this->matRow(
                'Crushed stone base (3/4 in.)',
                'cu yd',
                $qtyGranular,
                $r('crushed_stone_cu_yd'),
                $costGranular,
                $hst,
                sprintf(
                    '%s cu ft loose (%s in. base = %s in. excavation − %s in. paver − %s in. sand, × 1.20 compaction); order %s cu yd + %s tons',
                    $this->formatQty($stoneCuFt),
                    $this->formatQty($stoneDepthIn),
                    $this->formatQty($excavationDepthIn),
                    $this->formatQty($paverHeightIn),
                    $this->formatQty($sandDepthIn),
                    $this->formatQty($qtyGranular),
                    $this->formatQty($qtyGranularTons),
                ),
                $qtyGranularTons,
            ),
            $this->matRow(
                'Bedding sand',
                'cu yd',
                $qtyBedding,
                $r('bedding_sand_cu_yd'),
                $costBedding,
                $hst,
                sprintf(
                    '%s cu ft (%s in. screeded layer); order %s cu yd + %s tons',
                    $this->formatQty($sandCuFt),
                    $this->formatQty($sandDepthIn),
                    $this->formatQty($qtyBedding),
                    $this->formatQty($qtyBeddingTons),
                ),
                $qtyBeddingTons,
            ),
            $this->matRow(
                'Polymeric sand',
                'bags',
                $qtyPoly,
                $r('polymeric_sand_bag'),
                $costPoly,
                $hst,
                sprintf(
                    'Paver face %s×%s in. (%s sq in.); %s sq ft per bag',
                    $this->formatQty($paverLengthIn),
                    $this->formatQty($paverWidthIn),
                    $this->formatQty($paverLengthIn * $paverWidthIn),
                    $this->formatQty($polyCoverage),
                ),
            ),
            $this->matRow('Geotextile fabric', 'rolls', $qtyGeoRolls, $r('geotextile_roll'), $costGeo, $hst, '150 sq ft per roll when enabled'),
            $this->matRow('Fabric staples', 'boxes', $qtyStapleBoxes, $r('fabric_staples_box'), $costStaples, $hst, '~2 boxes per roll of fabric'),
            $this->matRow('Edge restraints (6 ft)', 'pieces', $qtyEdge, $r('edge_restraint_piece'), $costEdge, $hst, 'Perimeter √(area)×4 ÷ 6 ft + 10%'),
            $this->matRow('Edging spikes', 'packs', $qtySpikes, $r('edging_spikes_pack'), $costSpikes, $hst, '~1 spike pack per 3 edge pieces'),
        ]);

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

        $marbleSection = PavingMarbleCalculator::calculate(
            $inputs,
            $this->rates,
            $waste,
            $hst,
            $ownerRate,
            $helperRate,
        );

        if ($marbleSection !== null) {
            $materialRows = array_merge($materialRows, $marbleSection['material_rows']);
            $labourRows = array_merge($labourRows, $marbleSection['labour_rows']);

            $matPre = 0.0;
            $matHst = 0.0;
            foreach ($materialRows as $row) {
                $matPre += $row['before_tax'];
                $matHst += $row['hst'];
            }
            $matPre = round($matPre, 2);
            $matHst = round($matHst, 2);
            $matWithTax = round($matPre + $matHst, 2);

            $ownerHrsSum = 0.0;
            $helperHrsSum = 0.0;
            $ownerCostSum = 0.0;
            $helperCostSum = 0.0;
            foreach ($labourRows as $row) {
                $ownerHrsSum += (float) ($row['owner_hrs'] ?? 0);
                $helperHrsSum += (float) ($row['helper_hrs'] ?? 0);
                $ownerCostSum += (float) ($row['owner_cost'] ?? 0);
                $helperCostSum += (float) ($row['helper_cost'] ?? 0);
            }
            $ownerHrsSum = round($ownerHrsSum, 2);
            $helperHrsSum = round($helperHrsSum, 2);
            $ownerCostSum = round($ownerCostSum, 2);
            $helperCostSum = round($helperCostSum, 2);
            $labourTotal = round($ownerCostSum + $helperCostSum, 2);

            $inputs = array_merge($inputs, self::marbleInputSnapshot($marbleSection));
        }

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
                'stone_entries' => array_map(static fn (array $entry) => [
                    'stone_name' => $entry['stone_name'] ?? '',
                    'area_sq_ft' => PavingStoneEntryResolver::resolvedArea($entry),
                    'input_mode' => $entry['input_mode'] ?? 'sqft',
                ], $stoneEntries),
                'marble_area_sq_ft' => $marbleSection ? (float) ($marbleSection['area_sq_ft'] ?? 0) : 0.0,
            ],
            'marble' => $marbleSection,
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
        string $note,
        ?float $qtyTons = null,
    ): array {
        $bt = round($beforeTax, 2);
        $hst = round($bt * $hstRate, 2);

        $row = [
            'label' => $label,
            'unit' => $unit,
            'qty' => $qty,
            'unit_price' => round($unitPrice, 4),
            'before_tax' => $bt,
            'hst' => $hst,
            'with_tax' => round($bt + $hst, 2),
            'note' => $note,
        ];

        if ($qtyTons !== null && $qtyTons > 0) {
            $row['qty_tons'] = $qtyTons;
        }

        return $row;
    }

    protected function resolveStoneHeightIn(array $inputs): float
    {
        $height = (float) ($inputs['stone_height_in'] ?? 0);
        if ($height > 0) {
            return $height;
        }

        $stoneId = $inputs['paving_stone_type_id'] ?? null;
        if ($stoneId) {
            $stone = PavingStoneType::query()->find($stoneId);
            if ($stone && $stone->height_in) {
                return (float) $stone->height_in;
            }
        }

        return self::DEFAULT_PAVER_HEIGHT_IN;
    }

    protected function resolveCrushedBaseDepthIn(float $excavationDepthIn, float $paverHeightIn, float $sandDepthIn): float
    {
        return max(0.0, $excavationDepthIn - $paverHeightIn - $sandDepthIn);
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected function resolvePaverDimensions(array $inputs): array
    {
        $length = (float) ($inputs['stone_length_in'] ?? 0);
        $width = (float) ($inputs['stone_width_in'] ?? 0);
        if ($length > 0 && $width > 0) {
            return [$length, $width];
        }

        $stoneId = $inputs['paving_stone_type_id'] ?? null;
        if ($stoneId) {
            $stone = PavingStoneType::query()->find($stoneId);
            if ($stone && $stone->length_in && $stone->width_in) {
                return [(float) $stone->length_in, (float) $stone->width_in];
            }
        }

        return [6.0, 9.0];
    }

    protected function polymericSandCoverageSqFt(float $paverLengthIn, float $paverWidthIn): float
    {
        $paverAreaSqIn = max(0.0, $paverLengthIn) * max(0.0, $paverWidthIn);

        if ($paverAreaSqIn <= 32) {
            return 70.0;
        }

        if ($paverAreaSqIn <= 72) {
            return 85.0;
        }

        return 110.0;
    }

    protected function formatQty(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        $formatted = number_format($value, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted !== '' ? $formatted : '0';
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
     * @param  array<string, mixed>  $marbleSection
     * @return array<string, mixed>
     */
    protected static function marbleInputSnapshot(array $marbleSection): array
    {
        $resolved = $marbleSection['resolved'] ?? [];

        return [
            'marble_enabled' => true,
            'marble_type_id' => $marbleSection['marble_type_id'] ?? null,
            'marble_area_sq_ft' => $marbleSection['area_sq_ft'] ?? 0,
            'marble_depth_in' => $marbleSection['depth_in'] ?? 2,
            'marble_include_geotextile' => $marbleSection['include_geotextile'] ?? true,
            'marble_name' => $marbleSection['marble_name'] ?? '',
            'marble_reference_depth_in' => $resolved['reference_depth_in'] ?? null,
            'marble_sq_ft_per_bag_at_reference_depth' => $resolved['sq_ft_per_bag_at_reference_depth'] ?? null,
            'marble_price_per_bag' => $resolved['price_per_bag'] ?? null,
            'marble_effective_price_per_bag' => $marbleSection['effective_price_per_bag'] ?? null,
            'marble_bulk_min_bags' => $resolved['bulk_min_bags'] ?? null,
            'marble_bulk_discount_pct' => $resolved['bulk_discount_pct'] ?? null,
            'marble_delivery_cost' => $resolved['delivery_cost'] ?? null,
            'marble_additional_cost' => $resolved['additional_cost'] ?? null,
            'marble_additional_cost_description' => $resolved['additional_cost_description'] ?? null,
            'marble_bags' => $marbleSection['bags'] ?? 0,
        ];
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
            'bedding_sand_depth_in' => self::DEFAULT_BEDDING_SAND_DEPTH_IN,
            'stone_length_in' => $stone && $stone->length_in ? (float) $stone->length_in : null,
            'stone_width_in' => $stone && $stone->width_in ? (float) $stone->width_in : null,
            'stone_height_in' => $stone && $stone->height_in ? (float) $stone->height_in : null,
            'hst_rate' => 0.13,
            'add_polymeric_sand' => true,
            'include_old_surface_removal' => false,
            'include_geotextile' => true,
            'marble_enabled' => false,
            'marble_type_id' => null,
            'marble_area_sq_ft' => 0,
            'marble_depth_in' => 2,
            'marble_include_geotextile' => true,
            'manual_price_override' => null,
        ];
    }
}

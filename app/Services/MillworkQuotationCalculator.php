<?php

namespace App\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\CutOptimizer\CutOptimizerEngine;
use App\Services\CutOptimizer\Part;
use App\Services\CutOptimizer\StockOption;
use App\Services\CutOptimizer\SvgVisualizer;
use App\Services\Millwork\Frozen\V2MillworkQuotationCalculator;
use App\Services\Millwork\LegacyMillworkQuotationCalculator;
use App\Services\Millwork\MillworkDimensionParser;
use App\Services\Millwork\MillworkPanelSplitter;
use App\Services\Millwork\MillworkPartBuilder;
use App\Support\QuotationBuildingLabor;
use App\Support\QuotationHelperLabor;

class MillworkQuotationCalculator
{
    public const SECTIONS = [
        'pieces' => 'Pieces',
        'materials' => 'Materials & finish',
        'hardware' => 'Hardware',
        'review' => 'Review & cut plan',
    ];

    public const HST_RATE = MillworkQuotation::HST_RATE;

    /** Stored cut plans without this key (or with a lower value) keep the v1 snapshot. */
    public const CUT_PLAN_VERSION = 2;

    /** New parsing, inch defaults, and 240" used-field limits. Saved v2 stays on the frozen builder. */
    public const PARSING_VERSION = 3;

    /** Simple-shelf structure when a row has a height. New quotes only; saved v3 stays on the board price. */
    public const CALC_VERSION = 4;

    public const MAX_QTY = 200;

    public const MAX_COUNT = 100;

    public const MAX_DIM_IN = 240.0;

    public const MAX_LINEAR_FT = 500.0;

    public const MAX_SPARE_SHEETS = 50;

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'pieces' => [self::defaultPiece('shelf')],
            'sheet_id' => '',
            'back_sheet_id' => '',
            'edge_banding_id' => '',
            'paint_id' => '',
            'stain_id' => '',
            'hinge_id' => '',
            'pull_id' => '',
            'screw_id' => '',
            'nail_id' => '',
            'slide_id' => '',
            'glue_id' => '',
            'rod_id' => '',
            'pin_id' => '',
            'trim_id' => '',
            'include_install' => true,
            'client_provides_all' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'include_helper' => false,
            'include_building' => false,
            'building_labor_percent' => QuotationBuildingLabor::DEFAULT_PERCENT,
            'waste_pct' => 10,
            'edge_waste_pct' => 10,
            'finish_waste_pct' => 10,
            'markup_pct' => 0,
            'delivery_amount' => 0,
            'spare_sheets' => 0,
            'extra_materials' => [],
            'labor_override' => [],
            'materials_override' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPiece(string $type = 'shelf'): array
    {
        $type = MillworkPartBuilder::normalizeType($type);
        $base = [
            'type' => $type,
            'name' => '',
            'room' => '',
            'qty' => 1,
            'unit' => 'in',
            'edge_banding' => $type !== 'trim',
            'finish' => 'none',
            'fastener' => 'screws',
            'include_back' => false,
            'include_doors' => in_array($type, ['cabinet', 'vanity'], true),
            'include_toe_kick' => in_array($type, ['cabinet', 'vanity'], true),
            'left_side' => 'panel',
            'right_side' => 'panel',
            'sides' => 'panel',
            'below' => 'divisions',
            'overlay' => 'full',
            'hinge_style' => 'overlay',
            'style' => $type === 'cabinet' ? 'base' : '',
            'profile' => $type === 'trim' ? 'baseboard' : '',
            'door_count' => $type === 'closet' ? 0 : 2,
            'shelf_count' => $type === 'closet' ? 3 : 1,
            'drawer_count' => $type === 'vanity' ? 1 : 0,
            'division_count' => 2,
            'columns' => $type === 'closet' ? 2 : 2,
            'rows' => 2,
            'rod_count' => 1,
            'openings' => 0,
            'stock_length_ft' => 12,
            'linear_ft' => '',
            'same_size' => true,
            'sets' => [[
                'qty' => 1,
                'length' => '',
                'width' => '',
                'depth' => '',
                'height' => '',
                'thickness' => $type === 'floating_shelf' ? '3' : '',
                'unit' => 'in',
            ]],
        ];

        if ($type === 'cabinet') {
            $base['height'] = MillworkPartBuilder::DEFAULT_BASE_HEIGHT;
            $base['depth'] = MillworkPartBuilder::DEFAULT_BASE_DEPTH;
        } elseif ($type === 'vanity') {
            $base['width'] = 36;
            $base['height'] = MillworkPartBuilder::DEFAULT_BASE_HEIGHT;
            $base['depth'] = MillworkPartBuilder::DEFAULT_VANITY_DEPTH;
        } elseif ($type === 'closet') {
            $base['width'] = 72;
            $base['height'] = MillworkPartBuilder::DEFAULT_CLOSET_HEIGHT;
            $base['depth'] = MillworkPartBuilder::DEFAULT_CLOSET_DEPTH;
            $base['include_doors'] = false;
        } elseif ($type === 'wainscoting') {
            $base['unit'] = 'ft';
            $base['height'] = MillworkPartBuilder::DEFAULT_WAINSCOT_HEIGHT;
            $base['linear_ft'] = '';
        } elseif ($type === 'mantel') {
            $base['length'] = 60;
            $base['depth'] = 10;
            $base['height'] = 8;
        } elseif ($type === 'trim') {
            $base['unit'] = 'ft';
            $base['linear_ft'] = '';
            $base['edge_banding'] = false;
        } elseif ($type === 'bench') {
            $base['height'] = 18;
            $base['depth'] = 16;
        }

        return $base;
    }

    /**
     * Fill flags and counts a v1 row never stored with the values calculate()
     * already uses, so the edit form shows the priced defaults.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function withEffectiveFormDefaults(array $piece, bool $forLegacyQuote): array
    {
        if (! $forLegacyQuote) {
            return $piece;
        }
        $defaults = self::defaultPiece((string) ($piece['type'] ?? 'shelf'));
        foreach ([
            'include_doors', 'include_toe_kick', 'include_back', 'edge_banding',
            'door_count', 'shelf_count', 'drawer_count', 'division_count',
            'columns', 'rows', 'rod_count', 'openings',
        ] as $key) {
            if (! array_key_exists($key, $piece) && array_key_exists($key, $defaults)) {
                $piece[$key] = $defaults[$key];
            }
        }

        return $piece;
    }

    /**
     * 1 = legacy cut plan, 2 = frozen v2 builder, 3 = new parsing, 4 = shelf structure.
     *
     * @param  array<string, mixed>  $inputs
     */
    public static function resolveCalcVersion(array $inputs, ?int $explicit = null): int
    {
        $raw = $explicit;
        if ($raw === null && isset($inputs['_calc_version']) && is_numeric($inputs['_calc_version'])) {
            $raw = (int) $inputs['_calc_version'];
        }
        if ($raw === null && isset($inputs['_cut_plan_version']) && is_numeric($inputs['_cut_plan_version'])) {
            $raw = (int) $inputs['_cut_plan_version'];
        }
        if ($raw === null && isset($inputs['_builder_version']) && is_numeric($inputs['_builder_version'])) {
            $raw = (int) $inputs['_builder_version'];
        }
        if ($raw === null) {
            return self::CALC_VERSION;
        }
        if ($raw >= self::CALC_VERSION) {
            return self::CALC_VERSION;
        }
        if ($raw >= self::PARSING_VERSION) {
            return self::PARSING_VERSION;
        }
        if ($raw >= self::CUT_PLAN_VERSION) {
            return self::CUT_PLAN_VERSION;
        }

        return 1;
    }

    /**
     * True when any stored piece has been edited onto the v3 path.
     *
     * @param  array<string, mixed>  $inputs
     */
    public static function hasEditedPiece(array $inputs): bool
    {
        foreach ($inputs['pieces'] ?? [] as $piece) {
            if (is_array($piece) && MillworkPartBuilder::usesNewParsing($piece)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function resolveCutPlanVersion(array $inputs, ?int $explicit = null): int
    {
        return self::resolveCalcVersion($inputs, $explicit) >= self::CUT_PLAN_VERSION
            ? self::CUT_PLAN_VERSION
            : 1;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs, ?MillworkSettings $settings = null, ?int $cutPlanVersion = null): array
    {
        $settings = $settings ?? MillworkSettings::current();
        $calcVersion = self::resolveCalcVersion($inputs, $cutPlanVersion);
        $cutPlanVersion = $calcVersion >= self::CUT_PLAN_VERSION ? self::CUT_PLAN_VERSION : 1;
        if ($cutPlanVersion < self::CUT_PLAN_VERSION) {
            return $this->calculateLegacy($inputs, $settings);
        }
        if ($calcVersion < self::PARSING_VERSION && ! self::hasEditedPiece($inputs)) {
            return $this->calculateFrozenV2($inputs, $settings);
        }
        $inputs = array_merge(self::defaultInputs(), $inputs);
        $inputs['_calc_version'] = $calcVersion;
        $inputs['_cut_plan_version'] = $calcVersion;
        $prod = $settings->resolvedProductivity();
        $cut = $settings->resolvedCutSettings();
        $catalog = $settings->resolvedCatalog();
        $ownerRate = round(max(0, (float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate)), 2);
        $helperRate = round(max(0, (float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate)), 2);
        $includeHelper = QuotationHelperLabor::enabled($inputs);
        $providesAll = ! empty($inputs['client_provides_all']);

        $sheetId = (string) ($inputs['sheet_id'] ?? '');
        $backId = (string) ($inputs['back_sheet_id'] ?? '');
        $sheet = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_WOOD_SHEETS, $sheetId);
        $backSheet = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_WOOD_SHEETS, $backId, true)
            ?? $this->firstSheetOfThickness($catalog, 0.25)
            ?? $sheet;
        if ($backId === '' || $backId === $sheetId) {
            $thinBack = $this->firstSheetOfThickness($catalog, 0.25);
            if ($thinBack) {
                $backSheet = $thinBack;
            }
        }
        $thickness = (float) ($sheet['thickness_in'] ?? 0.75);
        $ebItem = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_EDGE_BANDING, (string) ($inputs['edge_banding_id'] ?? ''));
        $ebThick = (float) ($cut['edge_band_thickness_in'] ?? ($ebItem['thickness_in'] ?? 0.018));

        $builder = new MillworkPartBuilder($calcVersion);
        $primaryParts = [];
        $backParts = [];
        $explodedByPiece = [];
        $hardware = ['hinges' => 0.0, 'pulls' => 0.0, 'drawer_slides' => 0.0, 'glue' => 0.0, 'rods' => 0.0, 'shelf_pins' => 0.0];
        $edgeLf = 0.0;
        $paintSf = 0.0;
        $stainSf = 0.0;
        $fasteners = 0;
        $boxSf = 0.0;
        $cubeCount = 0;
        $unitCount = 0;
        $doorCount = 0;
        $drawerCount = 0;
        $shelfUnits = 0;
        $floatingUnits = 0;
        $benchSf = 0.0;
        $closetSf = 0.0;
        $cabinetBoxSf = 0.0;
        $closetUnits = 0;
        $mantelUnits = 0;
        $wainscotLf = 0.0;
        $trimLf = 0.0;
        $labels = [];
        $usesNails = false;
        $wastePct = $this->pct($inputs['waste_pct'] ?? 10, 10);
        $edgeWastePct = $this->pct($inputs['edge_waste_pct'] ?? 10, 10);
        $finishWastePct = $this->pct($inputs['finish_waste_pct'] ?? 10, 10);
        $markupPct = $this->pct($inputs['markup_pct'] ?? 0, 0);
        $deliveryAmount = round(max(0, (float) ($inputs['delivery_amount'] ?? 0)), 2);
        $spareSheets = max(0, (int) ($inputs['spare_sheets'] ?? 0));

        foreach ($this->pieces($inputs) as $idx => $piece) {
            $exploded = $builder->explode($piece, $thickness, ! empty($piece['edge_banding']) ? $ebThick : 0.0, $idx);
            $explodedByPiece[$idx] = ['piece' => $piece, 'exploded' => $exploded];
            foreach ($exploded['parts'] as $part) {
                if ($part->materialKey === 'back') {
                    $backParts[] = $part;
                } else {
                    $primaryParts[] = $part;
                }
            }
            foreach ($hardware as $k => $_) {
                $hardware[$k] += (float) ($exploded['hardware'][$k] ?? 0);
            }
            $edgeLf += (float) $exploded['edge_band_lf'];
            $paintSf += (float) $exploded['paint_sf'];
            $stainSf += (float) $exploded['stain_sf'];
            $fasteners += (int) $exploded['fastener_count'];
            $boxSf += (float) $exploded['box_sf'];
            $cubeCount += (int) $exploded['cube_count'];
            $unitCount += (int) $exploded['unit_count'];
            $doorCount += (int) $exploded['door_count'];
            $drawerCount += (int) $exploded['drawer_count'];
            $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
            if ($type === 'shelf') {
                $shelfUnits += (int) $exploded['unit_count'];
            }
            if ($type === 'floating_shelf') {
                $floatingUnits += (int) $exploded['unit_count'];
            }
            if ($type === 'bench') {
                $benchSf += (float) $exploded['box_sf'];
            }
            if (in_array($type, ['cabinet', 'vanity'], true)) {
                $cabinetBoxSf += (float) $exploded['box_sf'];
            }
            if ($type === 'closet') {
                $closetSf += (float) $exploded['box_sf'];
                $closetUnits += (int) $exploded['unit_count'];
            }
            if ($type === 'mantel') {
                $mantelUnits += (int) $exploded['unit_count'];
            }
            if ($type === 'wainscoting') {
                $wainscotLf += (float) ($exploded['linear_lf'] ?? 0);
            }
            if ($type === 'trim') {
                $trimLf += (float) ($exploded['linear_lf'] ?? 0);
            }
            if ((($piece['fastener'] ?? 'screws') === 'nails')) {
                $usesNails = true;
            }
            foreach ($exploded['labels'] as $label) {
                $labels[] = $label;
            }
        }

        $cutPlan = $this->previewCutPlanOrSkip(
            $inputs,
            $primaryParts,
            $backParts,
            $sheet,
            $backSheet,
            $catalog[MillworkSettings::SLOT_WOOD_SHEETS] ?? [],
            (float) ($cut['kerf_in'] ?? 0.125),
            (float) ($cut['trim_in'] ?? 0.125),
            (float) ($cut['min_offcut_in'] ?? 2.0)
        );
        $cutPlan['builder_version'] = $builder->version();

        $this->chargePlacedPartsOnly(
            $explodedByPiece,
            $cutPlan,
            $hardware,
            $edgeLf,
            $paintSf,
            $stainSf,
            $fasteners,
            $boxSf,
            $shelfUnits,
            $floatingUnits,
            $cubeCount,
            $cabinetBoxSf,
            $doorCount,
            $benchSf,
            $closetSf,
            $mantelUnits,
            $wainscotLf,
            $trimLf
        );

        $edgeLfQuoted = $edgeLf * (1 + ($edgeWastePct / 100));
        $paintSfQuoted = $paintSf * (1 + ($finishWastePct / 100));
        $stainSfQuoted = $stainSf * (1 + ($finishWastePct / 100));
        $trimLfQuoted = $trimLf * (1 + ($wastePct / 100));
        $wainscotLfQuoted = $wainscotLf * (1 + ($wastePct / 100));

        $labor = $this->laborRows(
            $prod,
            $ownerRate,
            $helperRate,
            $includeHelper,
            $cutPlan['sheet_count'],
            $shelfUnits,
            $floatingUnits,
            $cubeCount,
            $cabinetBoxSf,
            $doorCount,
            $benchSf,
            $edgeLfQuoted,
            $paintSfQuoted,
            $stainSfQuoted,
            ! empty($inputs['include_install']),
            is_array($inputs['labor_override'] ?? null) ? $inputs['labor_override'] : [],
            $closetSf,
            $mantelUnits,
            $wainscotLfQuoted,
            $trimLfQuoted,
            $boxSf
        );
        $totalHours = 0.0;
        $laborPre = 0.0;
        foreach ($labor as $row) {
            $totalHours += (float) $row['hours'];
            $laborPre += (float) $row['amount'];
        }
        $totalHours = round($totalHours, 2);
        $laborPre = round($laborPre, 2);
        $buildingRow = QuotationBuildingLabor::breakdownRow($laborPre, $inputs);
        $laborPre = QuotationBuildingLabor::apply($laborPre, $inputs)['total'];
        if ($buildingRow !== null) {
            $labor[] = [
                'key' => 'building',
                'label' => $buildingRow['label'],
                'hours' => 0.0,
                'amount' => $buildingRow['amount'],
            ];
        }

        $materials = $this->materialRows(
            $cutPlan,
            $sheet,
            $backSheet,
            $ebItem,
            $edgeLfQuoted,
            $catalog,
            $inputs,
            $hardware,
            $paintSfQuoted,
            $stainSfQuoted,
            $fasteners,
            $usesNails,
            $providesAll,
            is_array($inputs['materials_override'] ?? null) ? $inputs['materials_override'] : [],
            $trimLfQuoted,
            $spareSheets,
            $wastePct
        );
        $extraMaterials = $this->extraMaterials($inputs, $providesAll);
        $matGoods = 0.0;
        foreach (array_merge($materials, $extraMaterials) as $row) {
            if (! empty($row['client_provided'])) {
                continue;
            }
            $matGoods += (float) ($row['amount'] ?? 0);
        }
        $matGoods = round($matGoods, 2);
        if ($deliveryAmount > 0 && ! $providesAll) {
            $extraMaterials[] = [
                'key' => 'delivery',
                'label' => 'Delivery',
                'qty' => 1,
                'unit' => 'each',
                'unit_price' => $deliveryAmount,
                'amount' => $deliveryAmount,
                'client_provided' => false,
                'catalog_material_id' => null,
                'delivery_cad' => $deliveryAmount,
            ];
        }
        $matPre = round($matGoods + (! $providesAll ? $deliveryAmount : 0), 2);
        $preMarkup = round($laborPre + $matPre, 2);
        $markupAmt = round($preMarkup * ($markupPct / 100), 2);
        if ($markupAmt > 0) {
            $labor[] = [
                'key' => 'markup',
                'label' => 'Markup ('.$this->fmtPct($markupPct).')',
                'hours' => 0.0,
                'amount' => $markupAmt,
            ];
        }
        $subtotal = round($preMarkup + $markupAmt, 2);
        $laborHst = round($laborPre * self::HST_RATE, 2);
        $matHst = round(($matPre + $markupAmt) * self::HST_RATE, 2);
        $hst = round($laborHst + $matHst, 2);
        $grand = round($subtotal + $hst, 2);

        $breakdown = [];
        foreach ($labor as $row) {
            $breakdown[] = ['key' => $row['key'], 'label' => $row['label'], 'amount' => $row['amount']];
        }

        return $this->withFieldErrors($inputs, [
            'scope' => [
                'piece_count' => count($this->pieces($inputs)),
                'piece_summary' => implode('; ', $labels),
                'sheet_count' => $cutPlan['sheet_count'],
                'finish_label' => $this->finishSummary($inputs),
                'labels' => $labels,
            ],
            'sheet' => $sheet,
            'back_sheet' => $backParts !== [] ? $backSheet : null,
            'edge_banding' => $edgeLf > 0 ? $ebItem : null,
            'cut_plan' => $cutPlan,
            'labor' => $labor,
            'materials' => $materials,
            'extra_materials' => $extraMaterials,
            'eta' => $this->etaScenarios($totalHours, (float) $settings->hours_per_day, $ownerRate, $helperRate),
            'breakdown' => $breakdown,
            'summary' => [
                'total_hours' => $totalHours,
                'owner_hourly_rate' => $ownerRate,
                'helper_hourly_rate' => $helperRate,
                'include_helper' => $includeHelper,
                'include_building' => QuotationBuildingLabor::enabled($inputs),
                'building_labor_percent' => QuotationBuildingLabor::percent($inputs),
                'building_labor_surcharge' => $buildingRow['amount'] ?? 0.0,
                'labor_pre_hst' => $laborPre,
                'labor_hst' => $laborHst,
                'labor_after_hst' => round($laborPre + $laborHst, 2),
                'materials_goods_pre_hst' => $matGoods,
                'materials_pre_hst' => $matPre,
                'materials_hst' => $matHst,
                'materials_after_hst' => round($matPre + $matHst, 2),
                'subtotal_pre_hst' => $subtotal,
                'hst' => $hst,
                'grand_total' => $grand,
                'final_quote_total' => $grand,
                'client_provides_all' => $providesAll,
                'paint_sf' => round($paintSfQuoted, 2),
                'stain_sf' => round($stainSfQuoted, 2),
                'edge_band_lf' => round($edgeLfQuoted, 2),
                'edge_band_coverage_lf' => (float) ($ebItem['coverage_lf'] ?? 0),
                'fastener_count' => $fasteners,
                'waste_pct' => $wastePct,
                'edge_waste_pct' => $edgeWastePct,
                'finish_waste_pct' => $finishWastePct,
                'markup_pct' => $markupPct,
                'markup_amount' => $markupAmt,
                'delivery_amount' => $deliveryAmount,
                'spare_sheets' => $spareSheets,
                'trim_lf' => round($trimLfQuoted, 2),
                'wainscot_lf' => round($wainscotLfQuoted, 2),
            ],
        ]);
    }

    /**
     * Main's millwork calculator, part builder, and packer — used only for
     * quotes that have not been switched to the new cut plan.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function calculateLegacy(array $inputs, ?MillworkSettings $settings): array
    {
        $out = (new LegacyMillworkQuotationCalculator)->calculate($inputs, $settings);
        if (isset($out['cut_plan']) && is_array($out['cut_plan'])) {
            unset($out['cut_plan']['unplaced'], $out['cut_plan']['placed_by_id'], $out['cut_plan']['builder_version']);
            $out['cut_plan']['version'] = 1;
        }

        return $this->withFieldErrors($inputs, $out);
    }

    /**
     * Main 578b5081 v2 calculator + builder. Untouched saved v2 only.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function calculateFrozenV2(array $inputs, ?MillworkSettings $settings): array
    {
        return (new V2MillworkQuotationCalculator)->calculate($inputs, $settings, 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function withFieldErrors(array $inputs, array $out): array
    {
        $errors = [];
        $this->collectDimensionFieldErrors($inputs, $errors);
        $out['field_errors'] = $errors;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, bool $draft, ?MillworkSettings $settings = null): array
    {
        return array_values($this->validationMessages($inputs, $draft, $settings));
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, string>
     */
    public function validationMessages(array $inputs, bool $draft, ?MillworkSettings $settings = null): array
    {
        $messages = [];
        $this->collectDimensionFieldErrors($inputs, $messages);
        $calcVersion = self::resolveCalcVersion($inputs);

        if ($calcVersion < self::CUT_PLAN_VERSION) {
            if ($draft) {
                return $messages;
            }
            $legacy = (new LegacyMillworkQuotationCalculator)->validationHints($inputs, false);
            if ($legacy !== []) {
                $messages['calculation'] = $messages['calculation'] ?? implode(' ', $legacy);
            }

            return $messages;
        }

        if ($draft) {
            return $messages;
        }

        if ($calcVersion < self::PARSING_VERSION) {
            $messages = $this->mainV2ValidationMessages($inputs, $settings, $messages);
            $limitHint = $this->dimensionLimitHint($inputs);
            if ($limitHint !== null) {
                $messages['calculation'] = $limitHint;
            }

            return $messages;
        }

        $limitHint = $this->dimensionLimitHint($inputs);
        if ($limitHint !== null) {
            $messages['calculation'] = $limitHint;
        }

        $hasPart = false;
        $unsized = [];
        foreach ($this->pieces($inputs) as $idx => $piece) {
            if ($this->pieceIsSized($piece)) {
                $hasPart = true;
            } else {
                $unsized[] = $idx;
            }
        }
        if ($unsized !== []) {
            foreach ($unsized as $idx) {
                $key = 'pieces.'.$idx;
                if (! isset($messages[$key])) {
                    $messages[$key] = 'This piece needs quantity and dimensions.';
                }
            }
            $messages['calculation'] = $messages['calculation']
                ?? 'Every millwork piece needs quantity and dimensions before publishing.';
        }
        if (! $hasPart) {
            $messages['calculation'] = 'Add at least one millwork piece with quantity and dimensions.';
        }
        if ($hasPart && $limitHint === null && ! $this->hasInvalidPostedDimension($inputs)) {
            $preview = $this->calculate($inputs, $settings);
            $unplaced = is_array($preview['cut_plan']['unplaced'] ?? null) ? $preview['cut_plan']['unplaced'] : [];
            if ($unplaced !== []) {
                $labels = array_values(array_unique(array_filter(array_map(
                    static fn ($row) => trim((string) ($row['label'] ?? '')),
                    $unplaced
                ))));
                $list = $labels !== [] ? ' ('.implode(', ', $labels).')' : '';
                $messages['calculation'] = 'These parts do not fit on any sheet we stock'.$list.'. Change the size or pick a larger sheet before publishing.';
            }
        }

        return $messages;
    }

    /**
     * Main's publish rules for saved v2 quotes: at least one sized piece, then unplaced.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, string>  $messages
     * @return array<string, string>
     */
    private function mainV2ValidationMessages(array $inputs, ?MillworkSettings $settings, array $messages): array
    {
        $hasPart = false;
        foreach ($this->pieces($inputs) as $piece) {
            if ($this->pieceIsSized($piece)) {
                $hasPart = true;
                break;
            }
        }
        if (! $hasPart) {
            $messages['calculation'] = 'Add at least one millwork piece with quantity and dimensions.';
        }
        if ($hasPart) {
            $preview = $this->calculate($inputs, $settings);
            $unplaced = is_array($preview['cut_plan']['unplaced'] ?? null) ? $preview['cut_plan']['unplaced'] : [];
            if ($unplaced !== []) {
                $labels = array_values(array_unique(array_filter(array_map(
                    static fn ($row) => trim((string) ($row['label'] ?? '')),
                    $unplaced
                ))));
                $list = $labels !== [] ? ' ('.implode(', ', $labels).')' : '';
                $messages['calculation'] = 'These parts do not fit on any sheet we stock'.$list.'. Change the size or pick a larger sheet before publishing.';
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, string>  $messages
     */
    private function collectDimensionFieldErrors(array $inputs, array &$messages): void
    {
        $rawPieces = is_array($inputs['pieces'] ?? null) ? array_values($inputs['pieces']) : [];
        $savedPieces = is_array($inputs['_existing_pieces'] ?? null) ? array_values($inputs['_existing_pieces']) : [];
        $quoteIsV3 = self::resolveCalcVersion($inputs) >= self::PARSING_VERSION;

        foreach ($rawPieces as $pi => $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $saved = is_array($savedPieces[$pi] ?? null) ? $savedPieces[$pi] : [];
            $used = $this->usedParseKeys($piece);
            $only = $this->keysToValidateForRow($piece, $saved, $used, $quoteIsV3);
            $this->collectRowDimensionErrors($piece, 'pieces.'.$pi, $messages, null, $only);
            $savedSets = is_array($saved['sets'] ?? null) ? array_values($saved['sets']) : [];
            foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $si => $set) {
                if (! is_array($set)) {
                    continue;
                }
                $set['_calc_version'] = $piece['_calc_version'] ?? $set['_calc_version'] ?? null;
                $savedSet = is_array($savedSets[$si] ?? null) ? $savedSets[$si] : [];
                $setUsed = $this->usedSetLimitKeys($piece);
                $setOnly = $this->keysToValidateForRow($set, $savedSet, $setUsed, $quoteIsV3);
                $this->collectRowDimensionErrors(
                    $set,
                    'pieces.'.$pi.'.sets.'.$si,
                    $messages,
                    (string) ($piece['unit'] ?? 'in'),
                    $setOnly
                );
            }
        }
        $this->collectCountFieldErrors($inputs, $messages);
    }

    /**
     * v3 quotes check every used field. v1/v2 only check used fields whose
     * posted value changed from the stored row, even after JS marks the
     * piece v3, so leftover text like 36in. or n/a does not block a save
     * or land on a hidden unused input.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $saved
     * @param  list<string>  $used
     * @return list<string>
     */
    private function keysToValidateForRow(array $row, array $saved, array $used, bool $validateUnchanged): array
    {
        if ($used === []) {
            return [];
        }
        if ($validateUnchanged || $saved === []) {
            return $used;
        }
        $changed = [];
        foreach ($used as $key) {
            if ($this->dimensionValueChanged($row, $saved, $key)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $saved
     */
    private function dimensionValueChanged(array $current, array $saved, string $key): bool
    {
        if (! array_key_exists($key, $saved)) {
            $cur = $current[$key] ?? '';

            return $cur !== '' && $cur !== null;
        }

        return $this->dimensionCompareToken($current[$key] ?? '') !== $this->dimensionCompareToken($saved[$key] ?? '');
    }

    private function dimensionCompareToken(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? (string) $value));
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, string>  $messages
     */
    private function collectCountFieldErrors(array $inputs, array &$messages): void
    {
        $quoteIsV3 = self::resolveCalcVersion($inputs) >= self::PARSING_VERSION;
        if ($quoteIsV3 || self::hasEditedPiece($inputs)) {
            if ((int) ($inputs['spare_sheets'] ?? 0) > self::MAX_SPARE_SHEETS) {
                $messages['spare_sheets'] = 'Spare sheets cannot be more than '.self::MAX_SPARE_SHEETS.'.';
            }
        }

        $labels = [
            'qty' => 'Quantity',
            'door_count' => 'Door count',
            'shelf_count' => 'Shelf count',
            'drawer_count' => 'Drawer count',
            'division_count' => 'Division count',
            'columns' => 'Columns',
            'rows' => 'Rows',
            'rod_count' => 'Rod count',
            'openings' => 'Openings',
            'pull_qty' => 'Pull quantity',
            'hinge_qty' => 'Hinge quantity',
        ];

        foreach ($this->pieces($inputs) as $pi => $piece) {
            if (! $quoteIsV3 && ! MillworkPartBuilder::usesNewParsing($piece)) {
                continue;
            }
            if ((int) ($piece['qty'] ?? 0) > self::MAX_QTY) {
                $messages['pieces.'.$pi.'.qty'] = $labels['qty'].' cannot be more than '.self::MAX_QTY.'.';
            }
            foreach ($labels as $key => $label) {
                if ($key === 'qty') {
                    continue;
                }
                if (! array_key_exists($key, $piece)) {
                    continue;
                }
                if ((int) $piece[$key] > self::MAX_COUNT) {
                    $messages['pieces.'.$pi.'.'.$key] = $label.' cannot be more than '.self::MAX_COUNT.'.';
                }
            }
            foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $si => $set) {
                if (is_array($set) && (int) ($set['qty'] ?? 0) > self::MAX_QTY) {
                    $messages['pieces.'.$pi.'.sets.'.$si.'.qty'] = $labels['qty'].' cannot be more than '.self::MAX_QTY.'.';
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $messages
     */
    /**
     * @param  list<string>|null  $onlyKeys
     */
    private function collectRowDimensionErrors(array $row, string $prefix, array &$messages, ?string $fallbackUnit = null, ?array $onlyKeys = null): void
    {
        foreach (['width', 'height', 'depth', 'length', 'thickness', 'linear_ft', 'stock_length_ft'] as $key) {
            if ($onlyKeys !== null && ! in_array($key, $onlyKeys, true)) {
                continue;
            }
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $unit = $fallbackUnit !== null && ! in_array($key, ['linear_ft', 'stock_length_ft'], true)
                ? MillworkDimensionParser::fieldUnitOf($key, $row, $fallbackUnit)
                : MillworkDimensionParser::fieldUnitOf($key, $row);
            $parsed = MillworkDimensionParser::inspect($row[$key], $unit);
            if ($parsed['status'] === MillworkDimensionParser::STATUS_INVALID) {
                $messages[$prefix.'.'.$key] = $parsed['message'] ?? MillworkDimensionParser::INVALID_MESSAGE;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function pieceIsSized(array $piece): bool
    {
        $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
        $read = MillworkPartBuilder::usesNewParsing($piece)
            ? static fn (array $row, array $keys): float => MillworkPartBuilder::dimInches($row, $keys)
            : static fn (array $row, array $keys): float => MillworkPartBuilder::positiveDim($row, $keys);
        if (in_array($type, ['shelf', 'floating_shelf', 'cabinet_door'], true)) {
            foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $set) {
                if (! is_array($set) || (int) ($set['qty'] ?? 0) <= 0) {
                    continue;
                }
                $set['_calc_version'] = $piece['_calc_version'] ?? $set['_calc_version'] ?? null;
                $len = $read($set, ['length', 'height']);
                $wid = $read($set, ['width', 'depth']);
                if ($len > 0 && $wid > 0) {
                    return true;
                }
            }

            return false;
        }
        if (in_array($type, ['trim', 'wainscoting'], true)) {
            return $read($piece, ['linear_ft', 'length']) > 0;
        }
        if ($type === 'mantel') {
            $len = $read($piece, ['length', 'width']);
            $dep = $read($piece, ['depth']);

            return $len > 0 && $dep > 0;
        }
        $w = $read($piece, ['width', 'length']);
        $h = $read($piece, ['height']);
        $d = $read($piece, ['depth']);

        return $w > 0 && ($h > 0 || $type === 'bench') && $d > 0;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function hasInvalidPostedDimension(array $inputs): bool
    {
        $messages = [];
        $this->collectDimensionFieldErrors($inputs, $messages);

        return $messages !== [];
    }

    /**
     * @param  list<Part>  $primary
     * @param  list<Part>  $backs
     * @param  array<string, mixed>|null  $sheet
     * @param  array<string, mixed>|null  $backSheet
     * @param  list<array<string, mixed>>  $allSheets
     * @return array<string, mixed>
     */
    private function previewCutPlanOrSkip(
        array $inputs,
        array $primary,
        array $backs,
        ?array $sheet,
        ?array $backSheet,
        array $allSheets,
        float $kerf,
        float $trim,
        float $minOffcut
    ): array {
        if ($this->shouldSkipCutPlanPreview($inputs)) {
            $limitHint = $this->dimensionLimitHint($inputs);

            return [
                'version' => self::CUT_PLAN_VERSION,
                'sheet_count' => 0,
                'yield_pct' => 0.0,
                'kerf_in' => $kerf,
                'layouts' => [],
                'stock_used' => [],
                'original_part_count' => 0,
                'placed_part_count' => 0,
                'placed_by_id' => [],
                'unplaced' => [],
                'seams' => [],
                'preview_skipped' => true,
                'preview_message' => $limitHint ?? 'Fix the highlighted sizes to update the cut plan.',
            ];
        }

        return $this->buildCutPlan($primary, $backs, $sheet, $backSheet, $allSheets, $kerf, $trim, $minOffcut);
    }

    /**
     * Never drop an untouched v2 cut plan. Skip only when a v3 / edited piece
     * has an invalid typed size (not a stored over-240" used field).
     *
     * @param  array<string, mixed>  $inputs
     */
    private function shouldSkipCutPlanPreview(array $inputs): bool
    {
        $countErrors = [];
        $this->collectCountFieldErrors($inputs, $countErrors);
        if ($countErrors !== []) {
            return true;
        }
        foreach ($this->pieces($inputs) as $piece) {
            if (! MillworkPartBuilder::usesNewParsing($piece)) {
                continue;
            }
            $messages = [];
            $this->collectRowDimensionErrors($piece, 'p', $messages, null, $this->usedParseKeys($piece));
            foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $set) {
                if (is_array($set)) {
                    $set['_calc_version'] = $piece['_calc_version'] ?? null;
                    $this->collectRowDimensionErrors($set, 's', $messages, (string) ($piece['unit'] ?? 'in'), $this->usedSetLimitKeys($piece));
                }
            }
            if ($messages !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function dimensionLimitHint(array $inputs): ?string
    {
        $quoteIsV3 = self::resolveCalcVersion($inputs) >= self::PARSING_VERSION;
        if (($quoteIsV3 || self::hasEditedPiece($inputs)) && (int) ($inputs['spare_sheets'] ?? 0) > self::MAX_SPARE_SHEETS) {
            return 'Spare sheets cannot be more than '.self::MAX_SPARE_SHEETS.'.';
        }
        foreach ($this->pieces($inputs) as $piece) {
            if (! $quoteIsV3 && ! MillworkPartBuilder::usesNewParsing($piece)) {
                continue;
            }
            if ((int) ($piece['qty'] ?? 0) > self::MAX_QTY) {
                return 'Quantity cannot be more than '.self::MAX_QTY.'.';
            }
            foreach (['door_count', 'shelf_count', 'drawer_count', 'division_count', 'columns', 'rows', 'rod_count', 'openings', 'pull_qty', 'hinge_qty'] as $countKey) {
                if (array_key_exists($countKey, $piece) && (int) $piece[$countKey] > self::MAX_COUNT) {
                    return 'Count cannot be more than '.self::MAX_COUNT.'.';
                }
            }
            $linearFt = MillworkPartBuilder::positiveDim($piece, ['linear_ft']);
            if ($linearFt > self::MAX_LINEAR_FT) {
                return 'Linear footage cannot be more than '.self::MAX_LINEAR_FT.' ft.';
            }
            foreach ($this->usedLimitKeys($piece) as $key) {
                if (MillworkPartBuilder::trimOpeningIsInches($piece, $key)) {
                    continue;
                }
                if (MillworkPartBuilder::dimInches($piece, [$key]) > self::MAX_DIM_IN) {
                    return 'Each width, height, depth, and length must be 240 inches (20 ft) or less.';
                }
            }
            foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $set) {
                if (! is_array($set)) {
                    continue;
                }
                if ((int) ($set['qty'] ?? 0) > self::MAX_QTY) {
                    return 'Quantity cannot be more than '.self::MAX_QTY.'.';
                }
                $set['_calc_version'] = $piece['_calc_version'] ?? null;
                foreach ($this->usedSetLimitKeys($piece) as $key) {
                    if (MillworkPartBuilder::dimInches($set, [$key], (string) ($piece['unit'] ?? 'in')) > self::MAX_DIM_IN) {
                        return 'Each width, height, depth, and length must be 240 inches (20 ft) or less.';
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fields the piece type actually uses. Hidden leftover trim depth / unused
     * wainscot width must not trip the 240" check.
     *
     * @param  array<string, mixed>  $piece
     * @return list<string>
     */
    private function usedLimitKeys(array $piece): array
    {
        $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));

        return match ($type) {
            'shelf', 'cabinet_door', 'floating_shelf' => [],
            'kallax', 'cabinet', 'vanity', 'closet' => ['width', 'height', 'depth'],
            'bench' => ['length', 'width', 'depth', 'height'],
            'mantel' => ['length', 'width', 'depth', 'height'],
            'wainscoting' => ['height'],
            'trim' => ((int) ($piece['openings'] ?? 0) > 0) ? ['width', 'height'] : [],
            default => ['width', 'height', 'depth'],
        };
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return list<string>
     */
    private function usedParseKeys(array $piece): array
    {
        $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
        $keys = $this->usedLimitKeys($piece);
        if (in_array($type, ['trim', 'wainscoting'], true)) {
            $keys[] = 'linear_ft';
            $keys[] = 'length';
            $keys[] = 'stock_length_ft';
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return list<string>
     */
    private function usedSetLimitKeys(array $piece): array
    {
        $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));

        return match ($type) {
            'floating_shelf' => ['length', 'height', 'depth', 'width', 'thickness'],
            'shelf', 'cabinet_door' => ['length', 'height', 'depth', 'width'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function pieces(array $inputs): array
    {
        $raw = $inputs['pieces'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $quoteCalc = self::resolveCalcVersion($inputs);
        $out = [];
        foreach ($raw as $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $merged = array_merge(self::defaultPiece((string) ($piece['type'] ?? 'shelf')), $piece);
            if (! isset($merged['_calc_version']) || $merged['_calc_version'] === '' || $merged['_calc_version'] === null) {
                $merged['_calc_version'] = $quoteCalc;
            } else {
                $merged['_calc_version'] = (int) $merged['_calc_version'] >= self::CALC_VERSION
                    ? self::CALC_VERSION
                    : (int) $merged['_calc_version'];
            }
            $out[] = $merged;
        }

        return $out;
    }

    /**
     * @param  list<Part>  $primary
     * @param  list<Part>  $backs
     * @param  array<string, mixed>|null  $sheet
     * @param  array<string, mixed>|null  $backSheet
     * @param  list<array<string, mixed>>  $allSheets
     * @return array<string, mixed>
     */
    private function buildCutPlan(
        array $primary,
        array $backs,
        ?array $sheet,
        ?array $backSheet,
        array $allSheets,
        float $kerf,
        float $trim,
        float $minOffcut
    ): array {
        $engine = new CutOptimizerEngine();
        $visual = new SvgVisualizer();
        $splitter = new MillworkPanelSplitter();
        $layouts = [];
        $sheetCount = 0;
        $usedByStock = [];
        $unplaced = [];
        $seams = [];
        $placedById = [];
        $originalPartCount = $this->partQty($primary) + $this->partQty($backs);
        $packedPartCount = 0;

        $runGroup = function (array $parts, ?array $preferred, string $group) use (
            $engine,
            $visual,
            $splitter,
            $allSheets,
            $kerf,
            $trim,
            $minOffcut,
            &$layouts,
            &$sheetCount,
            &$usedByStock,
            &$unplaced,
            &$seams,
            &$packedPartCount,
            &$placedById
        ): void {
            if ($parts === []) {
                return;
            }
            $stocks = $this->stockOptionsFor($preferred, $allSheets);
            $split = $splitter->splitOversizedBacks($parts, $stocks);
            foreach ($split['seams'] as $seam) {
                $seams[] = $seam;
            }
            $toPack = $split['parts'];
            $sheets = $engine->optimize($toPack, $stocks, $kerf, $trim, $minOffcut);
            $placedCounts = [];
            foreach ($sheets as $packed) {
                $sheetCount++;
                $described = $visual->describe($packed);
                $described['group'] = $group;
                $layouts[] = $described;
                $packedPartCount += count($packed->placedParts);
                foreach ($packed->placedParts as $placed) {
                    $id = $placed->part->id;
                    $placedCounts[$id] = ($placedCounts[$id] ?? 0) + 1;
                    $baseId = $this->packerBaseId($id);
                    $placedById[$baseId] = ($placedById[$baseId] ?? 0) + 1;
                }
                $key = $this->inchKey($packed->width, $packed->length).'|'.$group;
                if (! isset($usedByStock[$key])) {
                    $priced = $this->pricePackedSheet($packed, $preferred, $stocks);
                    $usedByStock[$key] = [
                        'label' => $priced['name'],
                        'width' => $packed->width,
                        'length' => $packed->length,
                        'qty' => 0,
                        'unit_price' => $priced['unit_price'],
                        'catalog_material_id' => $priced['catalog_material_id'],
                        'name' => $priced['name'],
                    ];
                }
                $usedByStock[$key]['qty']++;
            }
            $missing = [];
            foreach ($engine->flattenParts($toPack) as $part) {
                $id = $part->id;
                if (($placedCounts[$id] ?? 0) > 0) {
                    $placedCounts[$id]--;
                    continue;
                }
                $missing[] = [
                    'id' => $this->packerBaseId($part->id),
                    'label' => $this->packerBaseLabel($part->id, $part->label),
                    'size' => $this->partSizeLabel($part),
                    'reason' => 'Does not fit on any sheet we stock',
                    'piece_index' => $part->sourcePieceIndex,
                ];
            }
            foreach ($this->groupUnplaced($missing) as $row) {
                $unplaced[] = $row;
            }
        };

        $runGroup($primary, $sheet, 'primary');
        $runGroup($backs, $backSheet, 'back');

        $totalWaste = 0.0;
        $totalArea = 0.0;
        foreach ($layouts as $layout) {
            $stats = $layout['stats'] ?? [];
            $totalWaste += (float) ($stats['waste_pct'] ?? 0) * (float) ($stats['sheet_area'] ?? 0) / 100.0;
            $totalArea += (float) ($stats['sheet_area'] ?? 0);
        }

        return [
            'version' => self::CUT_PLAN_VERSION,
            'sheet_count' => $sheetCount,
            'yield_pct' => $totalArea > 0 ? round(100 - ($totalWaste / $totalArea * 100), 1) : 0.0,
            'kerf_in' => $kerf,
            'layouts' => $layouts,
            'stock_used' => array_values($usedByStock),
            'original_part_count' => $originalPartCount,
            'placed_part_count' => $packedPartCount,
            'placed_by_id' => $placedById,
            'unplaced' => $unplaced,
            'seams' => $seams,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $preferred
     * @param  list<array<string, mixed>>  $allSheets
     * @return list<StockOption>
     */
    private function stockOptionsFor(?array $preferred, array $allSheets): array
    {
        $kind = (string) ($preferred['material_kind'] ?? 'plywood');
        $species = MillworkSettings::sheetSpecies($preferred ?? []);
        $thk = (float) ($preferred['thickness_in'] ?? 0.75);
        $preferredId = (string) ($preferred['id'] ?? '');
        $options = [];
        if (is_array($preferred) && $preferred !== []) {
            $options[] = $this->stockOptionFromRow($preferred);
        }
        foreach ($allSheets as $row) {
            if ($preferredId !== '' && (string) ($row['id'] ?? '') === $preferredId) {
                continue;
            }
            if (abs((float) ($row['thickness_in'] ?? 0) - $thk) > 0.06) {
                continue;
            }
            if (($row['material_kind'] ?? '') !== $kind) {
                continue;
            }
            if (MillworkSettings::sheetSpecies($row) !== $species) {
                continue;
            }
            $options[] = $this->stockOptionFromRow($row);
        }
        if ($options === []) {
            $w = (float) ($preferred['width_in'] ?? 48);
            $l = (float) ($preferred['length_in'] ?? 96);
            $options[] = new StockOption(
                (string) ($preferred['id'] ?? 'default'),
                (string) ($preferred['name'] ?? '4×8 sheet'),
                $w > 0 ? $w : 48,
                $l > 0 ? $l : 96,
                (float) ($preferred['unit_price'] ?? 0),
                isset($preferred['catalog_material_id']) ? (int) $preferred['catalog_material_id'] : null
            );
            if ($thk >= 0.7 && is_array($preferred) && MillworkSettings::allowsSyntheticSheetSwap($preferred)) {
                $fallbackPrice = (float) ($preferred['unit_price'] ?? 0);
                $options[] = new StockOption('half', '4×4 sheet', 48, 48, round($fallbackPrice * 0.55, 2));
                $options[] = new StockOption('quarter', '2×4 sheet', 24, 48, round($fallbackPrice * 0.32, 2));
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stockOptionFromRow(array $row): StockOption
    {
        return new StockOption(
            (string) ($row['id'] ?? uniqid('st')),
            (string) ($row['name'] ?? 'Sheet'),
            (float) ($row['width_in'] ?? 48),
            (float) ($row['length_in'] ?? 96),
            (float) ($row['unit_price'] ?? 0),
            isset($row['catalog_material_id']) ? (int) $row['catalog_material_id'] : null
        );
    }

    /**
     * @param  array<string, mixed>|null  $preferred
     * @param  list<StockOption>  $stocks
     * @return array{name: string, unit_price: float, catalog_material_id: int|null}
     */
    private function pricePackedSheet($packed, ?array $preferred, array $stocks): array
    {
        $name = $packed->label !== '' ? $packed->label : $this->inchKey($packed->width, $packed->length);
        $price = (float) ($preferred['unit_price'] ?? 0);
        $catalogId = $preferred['catalog_material_id'] ?? null;
        $preferredMatches = is_array($preferred)
            && abs((float) ($preferred['width_in'] ?? 0) - $packed->width) < 0.2
            && abs((float) ($preferred['length_in'] ?? 0) - $packed->length) < 0.2;
        if ($preferredMatches) {
            return [
                'name' => (string) ($preferred['name'] ?? $name),
                'unit_price' => (float) ($preferred['unit_price'] ?? 0),
                'catalog_material_id' => $preferred['catalog_material_id'] ?? null,
            ];
        }
        foreach ($stocks as $opt) {
            if (abs($opt->width - $packed->width) < 0.2 && abs($opt->length - $packed->length) < 0.2) {
                $name = $opt->label;
                $price = $opt->unitPrice;
                $catalogId = $opt->catalogMaterialId;
                break;
            }
        }

        return [
            'name' => $name,
            'unit_price' => $price,
            'catalog_material_id' => $catalogId,
        ];
    }

    /**
     * @param  list<Part>  $parts
     */
    private function partQty(array $parts): int
    {
        $n = 0;
        foreach ($parts as $part) {
            $n += max(1, (int) $part->quantity);
        }

        return $n;
    }

    /**
     * @param  array<string, float>  $prod
     * @param  array<string, array<string, float>>  $overrides
     * @return list<array<string, mixed>>
     */
    private function laborRows(
        array $prod,
        float $ownerRate,
        float $helperRate,
        bool $includeHelper,
        int $sheetCount,
        int $shelfUnits,
        int $floatingUnits,
        int $cubeCount,
        float $boxSf,
        int $doorCount,
        float $benchSf,
        float $edgeLf,
        float $paintSf,
        float $stainSf,
        bool $includeInstall,
        array $overrides,
        float $closetSf = 0.0,
        int $mantelUnits = 0,
        float $wainscotLf = 0.0,
        float $trimLf = 0.0,
        float $installSf = 0.0
    ): array {
        $rows = [];
        $add = function (string $key, string $label, float $hours) use (&$rows, $ownerRate, $helperRate, $includeHelper, $overrides): void {
            if (isset($overrides[$key]['hours']) && is_numeric($overrides[$key]['hours'])) {
                $hours = (float) $overrides[$key]['hours'];
            }
            $hours = round(max(0, $hours), 2);
            if ($hours <= 0) {
                return;
            }
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'hours' => $hours,
                'amount' => QuotationHelperLabor::amount($hours, $ownerRate, $helperRate, $includeHelper),
            ];
        };

        $add('cut', 'Shop — cut / nest sheets', $sheetCount * (float) ($prod['cut_hours_per_sheet'] ?? 0.45));
        $add('shelf', 'Shop — simple shelves', $shelfUnits * (float) ($prod['shelf_hours_each'] ?? 0.35));
        $add('floating', 'Shop — floating shelves', $floatingUnits * (float) ($prod['floating_shelf_hours_each'] ?? 1.25));
        $add('kallax', 'Shop — cube / kallax', $cubeCount * (float) ($prod['kallax_hours_per_cube'] ?? 0.85));
        $cabRate = (float) ($prod['cabinet_sf_per_hr'] ?? 4);
        $add('cabinet', 'Shop — cabinet / vanity boxes', $cabRate > 0 ? $boxSf / $cabRate : 0);
        $closetRate = (float) ($prod['closet_sf_per_hr'] ?? 3.5);
        $add('closet', 'Shop — closet system', $closetRate > 0 ? $closetSf / $closetRate : 0);
        $add('doors', 'Shop — doors / fronts', $doorCount * (float) ($prod['door_hours_each'] ?? 1));
        $benchRate = (float) ($prod['bench_sf_per_hr'] ?? 3);
        $add('bench', 'Shop — bench seating', $benchRate > 0 ? $benchSf / $benchRate : 0);
        $add('mantel', 'Shop — mantel', $mantelUnits * (float) ($prod['mantel_hours_each'] ?? 2.5));
        $ebRate = (float) ($prod['edge_band_lf_per_hr'] ?? 30);
        $add('edge', 'Shop — edge banding', $ebRate > 0 ? $edgeLf / $ebRate : 0);
        $paintRate = (float) ($prod['paint_sf_per_hr'] ?? 40);
        $add('paint', 'Shop — paint', $paintRate > 0 ? $paintSf / $paintRate : 0);
        $stainRate = (float) ($prod['stain_sf_per_hr'] ?? 35);
        $add('stain', 'Shop — stain / clear coat', $stainRate > 0 ? $stainSf / $stainRate : 0);
        $wainRate = (float) ($prod['wainscot_lf_per_hr'] ?? 8);
        $add('wainscot', 'Wainscoting', $wainRate > 0 ? $wainscotLf / $wainRate : 0);
        $trimRate = (float) ($prod['trim_lf_per_hr'] ?? 20);
        $add('trim', 'Trim / casing / base', $trimRate > 0 ? $trimLf / $trimRate : 0);
        if ($includeInstall) {
            $instRate = (float) ($prod['install_sf_per_hr'] ?? 8);
            $add('install', 'On-site install', $instRate > 0 ? $installSf / $instRate : 0);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $cutPlan
     * @param  array<string, mixed>|null  $sheet
     * @param  array<string, mixed>|null  $backSheet
     * @param  array<string, mixed>|null  $ebItem
     * @param  array<string, list<array<string, mixed>>>  $catalog
     * @param  array<string, mixed>  $inputs
     * @param  array<string, float>  $hardware
     * @param  array<string, array<string, float>>  $overrides
     * @return list<array<string, mixed>>
     */
    private function materialRows(
        array $cutPlan,
        ?array $sheet,
        ?array $backSheet,
        ?array $ebItem,
        float $edgeLf,
        array $catalog,
        array $inputs,
        array $hardware,
        float $paintSf,
        float $stainSf,
        int $fasteners,
        bool $usesNails,
        bool $providesAll,
        array $overrides,
        float $trimLf = 0.0,
        int $spareSheets = 0,
        float $wastePct = 10.0
    ): array {
        $rows = [];
        $n = 0;
        foreach ($cutPlan['stock_used'] ?? [] as $stock) {
            $n++;
            $qty = (float) ($stock['qty'] ?? 0);
            if ($n === 1 && $spareSheets > 0) {
                $qty += $spareSheets;
            }
            $price = (float) ($stock['unit_price'] ?? 0);
            $rows[] = $this->matRow('sheet_'.$n, (string) ($stock['name'] ?? 'Sheet'), $qty, 'sheet', $price, $providesAll, $stock['catalog_material_id'] ?? null, $overrides);
        }
        if ($edgeLf > 0 && $ebItem) {
            $coverageLf = (float) ($ebItem['coverage_lf'] ?? 0);
            if ($coverageLf > 0) {
                $qty = (int) max(1, ceil($edgeLf / $coverageLf));
                $unit = (string) ($ebItem['unit'] ?? 'roll');
                if ($unit === 'lf') {
                    $unit = 'roll';
                }
            } else {
                $qty = (int) ceil($edgeLf);
                $unit = (string) ($ebItem['unit'] ?? 'lf');
            }
            $rows[] = $this->matRow('edge', (string) $ebItem['name'], $qty, $unit, (float) $ebItem['unit_price'], $providesAll, $ebItem['catalog_material_id'] ?? null, $overrides);
        }
        $this->appendPackaging($rows, $catalog[MillworkSettings::SLOT_PAINTS] ?? [], $paintSf, 'paint', $providesAll, $overrides, (string) ($inputs['paint_id'] ?? ''));
        $this->appendPackaging($rows, $catalog[MillworkSettings::SLOT_STAINS] ?? [], $stainSf, 'stain', $providesAll, $overrides, (string) ($inputs['stain_id'] ?? ''));

        $hinge = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_HINGES, (string) ($inputs['hinge_id'] ?? ''));
        if ($hardware['hinges'] > 0 && $hinge) {
            $rows[] = $this->matRow('hinge', (string) $hinge['name'], (float) $hardware['hinges'], 'each', (float) $hinge['unit_price'], $providesAll, $hinge['catalog_material_id'] ?? null, $overrides);
        }
        $pull = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_PULLS, (string) ($inputs['pull_id'] ?? ''));
        if ($hardware['pulls'] > 0 && $pull) {
            $rows[] = $this->matRow('pull', (string) $pull['name'], (float) $hardware['pulls'], 'each', (float) $pull['unit_price'], $providesAll, $pull['catalog_material_id'] ?? null, $overrides);
        }
        $slide = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_DRAWER_SLIDES, (string) ($inputs['slide_id'] ?? ''));
        if ($hardware['drawer_slides'] > 0 && $slide) {
            $rows[] = $this->matRow('slide', (string) $slide['name'], (float) $hardware['drawer_slides'], 'pair', (float) $slide['unit_price'], $providesAll, $slide['catalog_material_id'] ?? null, $overrides);
        }
        $glue = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_GLUE, (string) ($inputs['glue_id'] ?? ''));
        if ($hardware['glue'] > 0 && $glue) {
            $rows[] = $this->matRow('glue', (string) $glue['name'], max(1, ceil($hardware['glue'] / 4)), 'each', (float) $glue['unit_price'], $providesAll, $glue['catalog_material_id'] ?? null, $overrides);
        }
        $rod = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_RODS, (string) ($inputs['rod_id'] ?? ''));
        if (($hardware['rods'] ?? 0) > 0 && $rod) {
            $rows[] = $this->matRow('rod', (string) $rod['name'], (float) $hardware['rods'], 'each', (float) $rod['unit_price'], $providesAll, $rod['catalog_material_id'] ?? null, $overrides);
        }
        $pins = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_SHELF_PINS, (string) ($inputs['pin_id'] ?? ''));
        if (($hardware['shelf_pins'] ?? 0) > 0 && $pins) {
            $per = max(1, (int) ($pins['qty_per_pack'] ?? 20));
            $packs = (int) max(1, ceil($hardware['shelf_pins'] / $per));
            $rows[] = $this->matRow('pins', (string) $pins['name'], $packs, (string) ($pins['unit'] ?? 'pack'), (float) $pins['unit_price'], $providesAll, $pins['catalog_material_id'] ?? null, $overrides);
        }
        if ($trimLf > 0) {
            $trimItem = $this->pickCatalogItem($catalog, MillworkSettings::SLOT_TRIM, (string) ($inputs['trim_id'] ?? ''));
            if ($trimItem) {
                $qty = round($trimLf, 2);
                $rows[] = $this->matRow('trim', (string) $trimItem['name'], $qty, 'lf', (float) $trimItem['unit_price'], $providesAll, $trimItem['catalog_material_id'] ?? null, $overrides);
            }
        }

        $fastSlot = $usesNails ? MillworkSettings::SLOT_NAILS : MillworkSettings::SLOT_SCREWS;
        $fastId = $usesNails ? (string) ($inputs['nail_id'] ?? '') : (string) ($inputs['screw_id'] ?? '');
        $fastItem = $this->pickCatalogItem($catalog, $fastSlot, $fastId);
        if ($fasteners > 0 && $fastItem) {
            $per = max(1, (int) ($fastItem['qty_per_pack'] ?? 1));
            $packs = (int) ceil($fasteners / $per);
            $rows[] = $this->matRow('fastener', (string) $fastItem['name'], $packs, (string) ($fastItem['unit'] ?? 'box'), (float) $fastItem['unit_price'], $providesAll, $fastItem['catalog_material_id'] ?? null, $overrides);
        }

        return array_values(array_filter($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, float>>  $overrides
     */
    private function appendPackaging(
        array &$rows,
        array $items,
        float $areaSf,
        string $key,
        bool $providesAll,
        array $overrides,
        string $preferredId
    ): void {
        if ($areaSf <= 0 || $items === []) {
            return;
        }
        $mix = $this->bestCoverageMix($items, $areaSf, $preferredId);
        foreach ($mix as $i => $line) {
            $rows[] = $this->matRow(
                $key.'_'.$i,
                (string) $line['name'],
                (float) $line['qty'],
                (string) $line['unit'],
                (float) $line['unit_price'],
                $providesAll,
                $line['catalog_material_id'] ?? null,
                $overrides
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function bestCoverageMix(array $items, float $areaSf, string $preferredId): array
    {
        $usable = [];
        foreach ($items as $item) {
            $cov = (float) ($item['coverage_sf'] ?? 0);
            if ($cov <= 0) {
                continue;
            }
            $usable[] = $item;
        }
        if ($usable === []) {
            return [];
        }
        usort($usable, function (array $a, array $b): int {
            $ca = (float) $a['coverage_sf'];
            $cb = (float) $b['coverage_sf'];
            $pa = (float) $a['unit_price'] / max(0.01, $ca);
            $pb = (float) $b['unit_price'] / max(0.01, $cb);

            return $pa <=> $pb;
        });
        $need = $areaSf;
        $counts = [];
        foreach ($usable as $item) {
            $counts[$item['id']] = 0;
        }
        $remaining = $need;
        while ($remaining > 0.5) {
            $best = null;
            $bestWasteCost = INF;
            foreach ($usable as $item) {
                $cov = (float) $item['coverage_sf'];
                $price = (float) $item['unit_price'];
                $over = max(0, $cov - $remaining);
                $score = $price + ($over * 0.02);
                if ($preferredId !== '' && (string) $item['id'] === $preferredId) {
                    $score *= 0.95;
                }
                if ($score < $bestWasteCost) {
                    $bestWasteCost = $score;
                    $best = $item;
                }
            }
            if ($best === null) {
                break;
            }
            $counts[$best['id']]++;
            $remaining -= (float) $best['coverage_sf'];
            if (array_sum($counts) > 40) {
                break;
            }
        }
        $out = [];
        foreach ($usable as $item) {
            $qty = (int) ($counts[$item['id']] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $out[] = [
                'name' => $item['name'],
                'qty' => $qty,
                'unit' => $item['unit'] ?? 'each',
                'unit_price' => $item['unit_price'],
                'catalog_material_id' => $item['catalog_material_id'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, float>>  $overrides
     * @return array<string, mixed>
     */
    private function matRow(
        string $key,
        string $label,
        float $qty,
        string $unit,
        float $unitPrice,
        bool $providesAll,
        $catalogId,
        array $overrides
    ): array {
        if (isset($overrides[$key]['qty']) && is_numeric($overrides[$key]['qty'])) {
            $qty = (float) $overrides[$key]['qty'];
        }
        if (isset($overrides[$key]['unit_price']) && is_numeric($overrides[$key]['unit_price'])) {
            $unitPrice = (float) $overrides[$key]['unit_price'];
        }
        $qty = round(max(0, $qty), 2);
        $client = $providesAll;
        $amount = $client ? 0.0 : round($qty * $unitPrice, 2);

        return [
            'key' => $key,
            'label' => $label,
            'qty' => $qty,
            'unit' => $unit,
            'unit_price' => round($unitPrice, 4),
            'amount' => $amount,
            'client_provided' => $client,
            'catalog_material_id' => $catalogId ? (int) $catalogId : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function extraMaterials(array $inputs, bool $providesAll): array
    {
        $extra = [];
        foreach (is_array($inputs['extra_materials'] ?? null) ? $inputs['extra_materials'] : [] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $qty = round(max(0, (float) ($row['quantity'] ?? $row['qty'] ?? 0)), 2);
            if ($name === '' && $qty <= 0) {
                continue;
            }
            $price = round(max(0, (float) ($row['unit_price'] ?? 0)), 4);
            $client = $providesAll || ! empty($row['client_provided']);
            $delivery = round(max(0, (float) ($row['delivery_cad'] ?? 0)), 2);
            $amount = $client ? 0.0 : round(($qty * $price) + $delivery, 2);
            $extra[] = [
                'key' => 'extra_'.$i,
                'label' => $name !== '' ? $name : 'Extra material',
                'qty' => $qty,
                'unit' => trim((string) ($row['unit'] ?? 'each')) ?: 'each',
                'unit_price' => $price,
                'amount' => $amount,
                'client_provided' => $client,
                'catalog_material_id' => (int) ($row['catalog_material_id'] ?? 0) ?: null,
                'delivery_cad' => $delivery,
            ];
        }

        return $extra;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $catalog
     * @return array<string, mixed>|null
     */
    private function pickCatalogItem(array $catalog, string $slot, string $id, bool $preferThin = false): ?array
    {
        $rows = $catalog[$slot] ?? [];
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        if ($id !== '') {
            foreach ($rows as $row) {
                if ((string) ($row['id'] ?? '') === $id) {
                    return $row;
                }
            }
        }
        if ($preferThin) {
            $thin = null;
            foreach ($rows as $row) {
                $thk = (float) ($row['thickness_in'] ?? 0.75);
                if ($thk <= 0.3 && ($thin === null || $thk < (float) ($thin['thickness_in'] ?? 1))) {
                    $thin = $row;
                }
            }
            if ($thin) {
                return $thin;
            }
        }

        return $rows[0];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $catalog
     * @return array<string, mixed>|null
     */
    private function firstSheetOfThickness(array $catalog, float $thickness): ?array
    {
        foreach ($catalog[MillworkSettings::SLOT_WOOD_SHEETS] ?? [] as $row) {
            if (abs((float) ($row['thickness_in'] ?? 0) - $thickness) < 0.06) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function finishSummary(array $inputs): string
    {
        $finishes = [];
        foreach ($this->pieces($inputs) as $piece) {
            $finishes[] = MillworkPartBuilder::normalizeFinish((string) ($piece['finish'] ?? 'none'));
        }
        $finishes = array_values(array_unique($finishes));
        if ($finishes === [] || $finishes === ['none']) {
            return 'Unfinished';
        }

        $labels = [
            'paint' => 'Paint',
            'stain' => 'Stain',
            'clear' => 'Clear coat / lacquer',
            'none' => 'Unfinished',
        ];

        return implode(' / ', array_map(fn ($f) => $labels[$f] ?? ucfirst($f), $finishes));
    }

    private function pct($value, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return round(max(0, min(100, (float) $value)), 2);
    }

    private function fmtPct(float $pct): string
    {
        return rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.').'%';
    }

    private function inchKey(float $w, float $l): string
    {
        $a = rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
        $b = rtrim(rtrim(number_format($l, 2, '.', ''), '0'), '.');

        return $a.'×'.$b;
    }

    /** Longest × shortest, with inch marks (100"×12"). */
    private function partSizeLabel(Part $part): string
    {
        return $this->sizeLabel($part->length, $part->width);
    }

    private function sizeLabel(float $a, float $b): string
    {
        return $this->fmtInches(max($a, $b)).'×'.$this->fmtInches(min($a, $b));
    }

    private function fmtInches(float $inches): string
    {
        return rtrim(rtrim(number_format($inches, 2, '.', ''), '0'), '.').'"';
    }

    /**
     * Charge labour, edge banding, finish, and hardware for placed parts only.
     * A piece with one leftover set keeps the sets that fit.
     *
     * @param  array<int, array<string, mixed>>  $explodedByPiece
     * @param  array<string, mixed>  $cutPlan
     * @param  array<string, float>  $hardware
     */
    private function chargePlacedPartsOnly(
        array $explodedByPiece,
        array $cutPlan,
        array &$hardware,
        float &$edgeLf,
        float &$paintSf,
        float &$stainSf,
        int &$fasteners,
        float &$boxSf,
        int &$shelfUnits,
        int &$floatingUnits,
        int &$cubeCount,
        float &$cabinetBoxSf,
        int &$doorCount,
        float &$benchSf,
        float &$closetSf,
        int &$mantelUnits,
        float &$wainscotLf,
        float &$trimLf
    ): void {
        $unplaced = is_array($cutPlan['unplaced'] ?? null) ? $cutPlan['unplaced'] : [];
        if ($unplaced === []) {
            return;
        }
        if ((int) ($cutPlan['placed_part_count'] ?? 0) === 0) {
            $this->resetShopCharges(
                $hardware,
                $edgeLf,
                $paintSf,
                $stainSf,
                $fasteners,
                $boxSf,
                $shelfUnits,
                $floatingUnits,
                $cubeCount,
                $cabinetBoxSf,
                $doorCount,
                $benchSf,
                $closetSf,
                $mantelUnits,
                $wainscotLf,
                $trimLf
            );

            return;
        }

        $unplacedIndexes = [];
        foreach ($unplaced as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ((array) ($row['piece_indexes'] ?? []) as $i) {
                $unplacedIndexes[(int) $i] = true;
            }
            if (array_key_exists('piece_index', $row) && $row['piece_index'] !== null && $row['piece_index'] !== '') {
                $unplacedIndexes[(int) $row['piece_index']] = true;
            }
        }

        $placedById = is_array($cutPlan['placed_by_id'] ?? null) ? $cutPlan['placed_by_id'] : [];
        $this->resetShopCharges(
            $hardware,
            $edgeLf,
            $paintSf,
            $stainSf,
            $fasteners,
            $boxSf,
            $shelfUnits,
            $floatingUnits,
            $cubeCount,
            $cabinetBoxSf,
            $doorCount,
            $benchSf,
            $closetSf,
            $mantelUnits,
            $wainscotLf,
            $trimLf
        );

        foreach ($explodedByPiece as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $ex = is_array($row['exploded'] ?? null) ? $row['exploded'] : [];
            $piece = is_array($row['piece'] ?? null) ? $row['piece'] : [];
            if (! isset($unplacedIndexes[(int) $idx])) {
                $this->addExplodedShopCharges(
                    $piece,
                    $ex,
                    $hardware,
                    $edgeLf,
                    $paintSf,
                    $stainSf,
                    $fasteners,
                    $boxSf,
                    $shelfUnits,
                    $floatingUnits,
                    $cubeCount,
                    $cabinetBoxSf,
                    $doorCount,
                    $benchSf,
                    $closetSf,
                    $mantelUnits,
                    $wainscotLf,
                    $trimLf
                );
                continue;
            }
            $this->addPlacedOnlyShopCharges(
                $piece,
                $ex,
                $placedById,
                $hardware,
                $edgeLf,
                $paintSf,
                $stainSf,
                $fasteners,
                $boxSf,
                $shelfUnits,
                $floatingUnits,
                $cubeCount,
                $cabinetBoxSf,
                $doorCount,
                $benchSf,
                $closetSf,
                $mantelUnits,
                $wainscotLf,
                $trimLf
            );
        }
    }

    /**
     * @param  array<string, float>  $hardware
     */
    private function resetShopCharges(
        array &$hardware,
        float &$edgeLf,
        float &$paintSf,
        float &$stainSf,
        int &$fasteners,
        float &$boxSf,
        int &$shelfUnits,
        int &$floatingUnits,
        int &$cubeCount,
        float &$cabinetBoxSf,
        int &$doorCount,
        float &$benchSf,
        float &$closetSf,
        int &$mantelUnits,
        float &$wainscotLf,
        float &$trimLf
    ): void {
        $hardware = ['hinges' => 0.0, 'pulls' => 0.0, 'drawer_slides' => 0.0, 'glue' => 0.0, 'rods' => 0.0, 'shelf_pins' => 0.0];
        $edgeLf = 0.0;
        $paintSf = 0.0;
        $stainSf = 0.0;
        $fasteners = 0;
        $boxSf = 0.0;
        $shelfUnits = 0;
        $floatingUnits = 0;
        $cubeCount = 0;
        $cabinetBoxSf = 0.0;
        $doorCount = 0;
        $benchSf = 0.0;
        $closetSf = 0.0;
        $mantelUnits = 0;
        $wainscotLf = 0.0;
        $trimLf = 0.0;
    }

    /**
     * @param  array<string, mixed>  $piece
     * @param  array<string, mixed>  $ex
     * @param  array<string, float>  $hardware
     */
    private function addExplodedShopCharges(
        array $piece,
        array $ex,
        array &$hardware,
        float &$edgeLf,
        float &$paintSf,
        float &$stainSf,
        int &$fasteners,
        float &$boxSf,
        int &$shelfUnits,
        int &$floatingUnits,
        int &$cubeCount,
        float &$cabinetBoxSf,
        int &$doorCount,
        float &$benchSf,
        float &$closetSf,
        int &$mantelUnits,
        float &$wainscotLf,
        float &$trimLf
    ): void {
        foreach ($hardware as $k => $_) {
            $hardware[$k] += (float) ($ex['hardware'][$k] ?? 0);
        }
        $edgeLf += (float) ($ex['edge_band_lf'] ?? 0);
        $paintSf += (float) ($ex['paint_sf'] ?? 0);
        $stainSf += (float) ($ex['stain_sf'] ?? 0);
        $fasteners += (int) ($ex['fastener_count'] ?? 0);
        $boxSf += (float) ($ex['box_sf'] ?? 0);
        $this->addTypeShopCharges(
            MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf')),
            (float) ($ex['box_sf'] ?? 0),
            (int) ($ex['unit_count'] ?? 0),
            (int) ($ex['door_count'] ?? 0),
            (int) ($ex['cube_count'] ?? 0),
            (float) ($ex['linear_lf'] ?? 0),
            $shelfUnits,
            $floatingUnits,
            $cubeCount,
            $cabinetBoxSf,
            $doorCount,
            $benchSf,
            $closetSf,
            $mantelUnits,
            $wainscotLf,
            $trimLf
        );
    }

    /**
     * @param  array<string, mixed>  $piece
     * @param  array<string, mixed>  $ex
     * @param  array<string, int>  $placedById
     * @param  array<string, float>  $hardware
     */
    private function addPlacedOnlyShopCharges(
        array $piece,
        array $ex,
        array &$placedById,
        array &$hardware,
        float &$edgeLf,
        float &$paintSf,
        float &$stainSf,
        int &$fasteners,
        float &$boxSf,
        int &$shelfUnits,
        int &$floatingUnits,
        int &$cubeCount,
        float &$cabinetBoxSf,
        int &$doorCount,
        float &$benchSf,
        float &$closetSf,
        int &$mantelUnits,
        float &$wainscotLf,
        float &$trimLf
    ): void {
        $parts = is_array($ex['parts'] ?? null) ? $ex['parts'] : [];
        $finish = MillworkPartBuilder::normalizeFinish((string) ($piece['finish'] ?? 'none'));
        $totalQty = 0;
        $placedQty = 0;
        $placedBox = 0.0;
        foreach ($parts as $part) {
            if (! $part instanceof Part) {
                continue;
            }
            $qty = max(1, (int) $part->quantity);
            $totalQty += $qty;
            $id = $part->id;
            $got = min($qty, (int) ($placedById[$id] ?? 0));
            $placedById[$id] = max(0, (int) ($placedById[$id] ?? 0) - $got);
            $placedQty += $got;
            if ($got <= 0) {
                continue;
            }
            $placedBox += ($part->width * $part->length * $got) / 144.0;
            $edgeLf += $this->partEdgeLf($part) * $got;
            $face = 2.0 * ($part->width * $part->length) / 144.0 * $got;
            if ($finish === 'paint') {
                $paintSf += $face;
            } elseif (in_array($finish, ['stain', 'clear'], true)) {
                $stainSf += $face;
            }
        }

        $ratio = 1.0;
        if ($parts !== [] && $totalQty > 0) {
            $ratio = $placedQty / $totalQty;
        }

        foreach ($hardware as $k => $_) {
            $hardware[$k] += (float) ($ex['hardware'][$k] ?? 0) * $ratio;
        }
        $fasteners += (int) round(((int) ($ex['fastener_count'] ?? 0)) * $ratio);
        $scaledBox = $placedBox;
        $boxSf += $scaledBox;
        $this->addTypeShopCharges(
            MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf')),
            $scaledBox,
            (int) round(((int) ($ex['unit_count'] ?? 0)) * $ratio),
            (int) round(((int) ($ex['door_count'] ?? 0)) * $ratio),
            (int) round(((int) ($ex['cube_count'] ?? 0)) * $ratio),
            (float) ($ex['linear_lf'] ?? 0) * $ratio,
            $shelfUnits,
            $floatingUnits,
            $cubeCount,
            $cabinetBoxSf,
            $doorCount,
            $benchSf,
            $closetSf,
            $mantelUnits,
            $wainscotLf,
            $trimLf
        );
    }

    private function addTypeShopCharges(
        string $type,
        float $boxSf,
        int $units,
        int $doors,
        int $cubes,
        float $linear,
        int &$shelfUnits,
        int &$floatingUnits,
        int &$cubeCount,
        float &$cabinetBoxSf,
        int &$doorCount,
        float &$benchSf,
        float &$closetSf,
        int &$mantelUnits,
        float &$wainscotLf,
        float &$trimLf
    ): void {
        if ($type === 'shelf') {
            $shelfUnits += $units;
        }
        if ($type === 'floating_shelf') {
            $floatingUnits += $units;
        }
        if ($type === 'kallax') {
            $cubeCount += $cubes;
        }
        if (in_array($type, ['cabinet', 'vanity'], true)) {
            $cabinetBoxSf += $boxSf;
        }
        if ($type === 'closet') {
            $closetSf += $boxSf;
        }
        if ($type === 'bench') {
            $benchSf += $boxSf;
        }
        if ($type === 'mantel') {
            $mantelUnits += $units;
        }
        if ($type === 'wainscoting') {
            $wainscotLf += $linear;
        }
        if ($type === 'trim') {
            $trimLf += $linear;
        }
        $doorCount += $doors;
    }

    private function packerBaseId(string $id): string
    {
        return (string) preg_replace('/#\d+$/', '', $id);
    }

    private function packerBaseLabel(string $id, string $label): string
    {
        if (! str_contains($id, '#')) {
            return $label;
        }

        return (string) preg_replace('/\s+\d+$/', '', $label);
    }

    private function partEdgeLf(Part $part): float
    {
        $lf = 0.0;
        if ($part->edgeBandTop > 0) {
            $lf += $part->length / 12.0;
        }
        if ($part->edgeBandBottom > 0) {
            $lf += $part->length / 12.0;
        }
        if ($part->edgeBandLeft > 0) {
            $lf += $part->width / 12.0;
        }
        if ($part->edgeBandRight > 0) {
            $lf += $part->width / 12.0;
        }

        return $lf;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupUnplaced(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $size = $this->canonicalUnplacedSize((string) ($row['size'] ?? ''));
            $name = $this->unplacedNameWithoutSize((string) ($row['label'] ?? 'Part'));
            $key = $name.'|'.$size;
            if (! isset($groups[$key])) {
                $groups[$key] = $row + [
                    'qty' => 0,
                    'piece_indexes' => [],
                    'size' => $size,
                    'name' => $name,
                ];
            }
            $groups[$key]['qty']++;
            if (isset($row['piece_index'])) {
                $groups[$key]['piece_indexes'][] = $row['piece_index'];
            }
        }
        $out = [];
        foreach ($groups as $group) {
            $qty = (int) ($group['qty'] ?? 1);
            $name = (string) ($group['name'] ?? 'Part');
            $size = (string) ($group['size'] ?? '');
            $indexes = array_values(array_unique(array_map('intval', $group['piece_indexes'] ?? [])));
            $label = $qty > 1 ? $qty.'× '.$name : $name;
            if ($size !== '') {
                $label .= ' '.$size;
            }
            $out[] = [
                'id' => $group['id'] ?? '',
                'label' => $label,
                'size' => $size,
                'qty' => $qty,
                'reason' => $group['reason'] ?? 'Does not fit on any sheet we stock',
                'piece_index' => $indexes[0] ?? $group['piece_index'] ?? null,
                'piece_indexes' => $indexes,
            ];
        }

        return $out;
    }

    private function unplacedNameWithoutSize(string $label): string
    {
        $stripped = preg_replace('/\s*\d+(?:\.\d+)?"\s*[×xX]\s*\d+(?:\.\d+)?"/u', '', $label) ?? $label;
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);

        return $stripped !== '' ? $stripped : 'Part';
    }

    private function canonicalUnplacedSize(string $size): string
    {
        if (preg_match('/(\d+(?:\.\d+)?)"\s*[×xX]\s*(\d+(?:\.\d+)?)"/u', $size, $m)) {
            return $this->sizeLabel((float) $m[1], (float) $m[2]);
        }

        return $size;
    }

    /**
     * @return array<string, mixed>
     */
    private function etaScenarios(float $totalHours, float $hoursPerDay, float $ownerRate, float $helperRate): array
    {
        $scenario = function (int $crew, string $label) use ($totalHours, $hoursPerDay, $ownerRate, $helperRate): array {
            $days = $totalHours > 0 ? round($totalHours / ($crew * $hoursPerDay), 2) : 0.0;
            if ($crew === 1) {
                $cost = round($totalHours * $ownerRate, 2);
            } elseif ($crew === 2) {
                $cost = round(($totalHours / 2) * $ownerRate + ($totalHours / 2) * $helperRate, 2);
            } else {
                $cost = round(($totalHours / 3) * $ownerRate + ((2 * $totalHours) / 3) * $helperRate, 2);
            }

            return [
                'crew' => $crew,
                'label' => $label,
                'calendar_days' => $days,
                'labor_cost' => $cost,
            ];
        };

        return [
            'hours_per_day' => $hoursPerDay,
            'total_hours' => $totalHours,
            'scenarios' => [
                $scenario(1, 'Owner'),
                $scenario(2, 'Owner + 1 helper'),
                $scenario(3, 'Owner + 2 helpers'),
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\CutOptimizer\CutOptimizerEngine;
use App\Services\CutOptimizer\Part;
use App\Services\CutOptimizer\StockOption;
use App\Services\CutOptimizer\SvgVisualizer;
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
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs, ?MillworkSettings $settings = null): array
    {
        $settings = $settings ?? MillworkSettings::current();
        $inputs = array_merge(self::defaultInputs(), $inputs);
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

        $builder = new MillworkPartBuilder();
        $primaryParts = [];
        $backParts = [];
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
            $exploded = $builder->explode($piece, $thickness, ! empty($piece['edge_banding']) ? $ebThick : 0.0);
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

        $cutPlan = $this->buildCutPlan(
            $primaryParts,
            $backParts,
            $sheet,
            $backSheet,
            $catalog[MillworkSettings::SLOT_WOOD_SHEETS] ?? [],
            (float) ($cut['kerf_in'] ?? 0.125),
            (float) ($cut['trim_in'] ?? 0.125),
            (float) ($cut['min_offcut_in'] ?? 2.0)
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

        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, bool $draft): array
    {
        if ($draft) {
            return [];
        }
        $hints = [];
        $hasPart = false;
        foreach ($this->pieces($inputs) as $piece) {
            $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
            if (in_array($type, ['shelf', 'floating_shelf', 'cabinet_door'], true)) {
                foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $set) {
                    if ((int) ($set['qty'] ?? 0) > 0) {
                        $len = MillworkPartBuilder::positiveDim($set, ['length', 'height']);
                        $wid = MillworkPartBuilder::positiveDim($set, ['width', 'depth']);
                        if ($len > 0 && $wid > 0) {
                            $hasPart = true;
                        }
                    }
                }
            } elseif (in_array($type, ['trim', 'wainscoting'], true)) {
                $lf = MillworkPartBuilder::positiveDim($piece, ['linear_ft', 'length']);
                if ($lf > 0) {
                    $hasPart = true;
                }
            } elseif ($type === 'mantel') {
                $len = MillworkPartBuilder::positiveDim($piece, ['length', 'width']);
                $dep = MillworkPartBuilder::positiveDim($piece, ['depth']);
                if ($len > 0 && $dep > 0) {
                    $hasPart = true;
                }
            } else {
                $w = MillworkPartBuilder::positiveDim($piece, ['width', 'length']);
                $h = MillworkPartBuilder::positiveDim($piece, ['height']);
                $d = MillworkPartBuilder::positiveDim($piece, ['depth']);
                if ($w > 0 && ($h > 0 || $type === 'bench') && $d > 0) {
                    $hasPart = true;
                }
            }
        }
        if (! $hasPart) {
            $hints[] = 'Add at least one millwork piece with quantity and dimensions.';
        }

        return $hints;
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
        $out = [];
        foreach ($raw as $piece) {
            if (is_array($piece)) {
                $out[] = array_merge(self::defaultPiece((string) ($piece['type'] ?? 'shelf')), $piece);
            }
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
        $layouts = [];
        $sheetCount = 0;
        $usedByStock = [];

        $runGroup = function (array $parts, ?array $preferred, string $group) use ($engine, $visual, $allSheets, $kerf, $trim, $minOffcut, &$layouts, &$sheetCount, &$usedByStock): void {
            if ($parts === []) {
                return;
            }
            $stocks = $this->stockOptionsFor($preferred, $allSheets);
            $sheets = $engine->optimize($parts, $stocks, $kerf, $trim, $minOffcut);
            foreach ($sheets as $packed) {
                $sheetCount++;
                $described = $visual->describe($packed);
                $described['group'] = $group;
                $layouts[] = $described;
                $key = $this->inchKey($packed->width, $packed->length).'|'.$group;
                if (! isset($usedByStock[$key])) {
                    $usedByStock[$key] = [
                        'label' => $packed->label !== '' ? $packed->label : $this->inchKey($packed->width, $packed->length),
                        'width' => $packed->width,
                        'length' => $packed->length,
                        'qty' => 0,
                        'unit_price' => (float) ($preferred['unit_price'] ?? 0),
                        'catalog_material_id' => $preferred['catalog_material_id'] ?? null,
                        'name' => $preferred['name'] ?? $packed->label,
                    ];
                    foreach ($stocks as $opt) {
                        if (abs($opt->width - $packed->width) < 0.2 && abs($opt->length - $packed->length) < 0.2) {
                            $usedByStock[$key]['unit_price'] = $opt->unitPrice;
                            $usedByStock[$key]['catalog_material_id'] = $opt->catalogMaterialId;
                            $usedByStock[$key]['name'] = $opt->label;
                            break;
                        }
                    }
                }
                $usedByStock[$key]['qty']++;
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
            'sheet_count' => $sheetCount,
            'yield_pct' => $totalArea > 0 ? round(100 - ($totalWaste / $totalArea * 100), 1) : 0.0,
            'kerf_in' => $kerf,
            'layouts' => $layouts,
            'stock_used' => array_values($usedByStock),
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
        $grade = (string) ($preferred['grade'] ?? 'cabinet');
        $thk = (float) ($preferred['thickness_in'] ?? 0.75);
        $options = [];
        foreach ($allSheets as $row) {
            if (abs((float) ($row['thickness_in'] ?? 0) - $thk) > 0.06) {
                continue;
            }
            if (($row['material_kind'] ?? '') !== $kind) {
                continue;
            }
            $options[] = new StockOption(
                (string) ($row['id'] ?? uniqid('st')),
                (string) ($row['name'] ?? 'Sheet'),
                (float) ($row['width_in'] ?? 48),
                (float) ($row['length_in'] ?? 96),
                (float) ($row['unit_price'] ?? 0),
                isset($row['catalog_material_id']) ? (int) $row['catalog_material_id'] : null
            );
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
            if ($thk >= 0.7) {
                $options[] = new StockOption('half', '4×4 sheet', 48, 48, round(((float) ($preferred['unit_price'] ?? 0)) * 0.55, 2));
                $options[] = new StockOption('quarter', '2×4 sheet', 24, 48, round(((float) ($preferred['unit_price'] ?? 0)) * 0.32, 2));
            }
        }

        return $options;
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
        $add('trim', 'Trim / casing / base / crown', $trimRate > 0 ? $trimLf / $trimRate : 0);
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

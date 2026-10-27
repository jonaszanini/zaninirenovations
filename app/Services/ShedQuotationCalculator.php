<?php

namespace App\Services;

use App\Models\Material;
use App\Models\ShedQuotation;
use App\Models\ShedSettings;
use App\Support\CatalogMaterialDelivery;
use App\Support\QuotationHelperLabor;

class ShedQuotationCalculator
{
    public const PERMIT_AREA_SQM = 15.0;

    public const SQFT_PER_SQM = 10.7639;

    public const SECTIONS = [
        'type' => 'Shed type',
        'size' => 'Size',
        'foundation' => 'Foundation',
        'framing' => 'Floor & framing',
        'roof' => 'Roof',
        'siding' => 'Siding',
        'openings' => 'Doors & windows',
        'interior' => 'Interior finish',
    ];

    public const SHED_TYPES = [
        'gable' => 'Gable',
        'lean_to' => 'Lean-to',
        'gambrel' => 'Gambrel',
        'saltbox' => 'Saltbox',
    ];

    public const FOUNDATIONS = [
        'pad' => 'Concrete pad',
        'piles' => 'Helical piles',
        'skids' => 'Pressure-treated skids',
        'blocks' => 'Deck blocks',
    ];

    public const SIDING = [
        'vinyl' => 'Vinyl',
        'bnb' => 'Board and batten',
        'wood' => 'Wood',
        'hardie' => 'Hardie board',
        'house_wrap' => 'House wrap',
        'weather_coat' => 'Weather coat',
        'none' => 'Not now',
    ];

    public const ROOF = [
        'asphalt' => 'Asphalt shingles',
        'metal_black' => 'Metal — Black',
        'metal_galvanized' => 'Metal — Silver',
        'house_wrap' => 'House wrap',
        'weather_coat' => 'Weather coat',
        'none' => 'Not now',
    ];

    public const DOOR_TYPES = [
        'custom' => 'Custom built',
        'prehung' => 'Pre-hung',
        'rollup' => 'Roll-up',
    ];

    public const WINDOW_TYPES = [
        'slider' => 'Standard slider',
        'transom' => 'Transom',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'shed_type' => 'gable',
            'attached' => false,
            'length_ft' => 0,
            'width_ft' => 0,
            'wall_height_ft' => 8,
            'roof_pitch' => 6,
            'overhang_in' => 12,
            'foundation' => 'skids',
            'pad_thickness_in' => 4,
            'gravel_depth_in' => 4,
            'pile_qty' => null,
            'skid_qty' => null,
            'block_qty' => null,
            'include_site_level' => false,
            'joist_size' => '2x6',
            'joist_spacing_in' => 16,
            'floor_sheathing' => 'osb_58',
            'stud_size' => '2x4',
            'stud_spacing_in' => 16,
            'wall_sheathing' => true,
            'siding' => 'vinyl',
            'roof_type' => 'asphalt',
            'door_type' => 'custom',
            'door_qty' => 1,
            'double_door' => false,
            'window_type' => 'slider',
            'window_qty' => 2,
            'interior_finish' => 'rough',
            'include_electrical' => false,
            'outlet_qty' => 2,
            'light_qty' => 1,
            'include_subpanel' => false,
            'include_loft' => false,
            'include_paint' => false,
            'include_ramp' => false,
            'include_gutters' => false,
            'include_plumbing' => false,
            'client_provides_all' => false,
            'client_provides_sections' => [],
            'owner_hourly_rate' => SharedWorkCatalog::ownerHourlyRate(),
            'helper_hourly_rate' => 45,
            'include_helper' => false,
            'extra_materials' => [],
            'labor_override' => [],
            'materials_override' => [],
        ];
    }

    public static function shedTypeLabel(string $type): string
    {
        return self::SHED_TYPES[self::normalizeShedType($type)] ?? 'Gable';
    }

    public static function foundationLabel(string $type): string
    {
        $key = array_key_exists($type, self::FOUNDATIONS) ? $type : 'skids';

        return self::FOUNDATIONS[$key];
    }

    public static function sidingLabel(string $type): string
    {
        $key = self::normalizeSiding($type);

        return self::SIDING[$key];
    }

    public static function normalizeSiding(mixed $type): string
    {
        $value = strtolower(trim((string) $type));

        return array_key_exists($value, self::SIDING) ? $value : 'vinyl';
    }

    public static function hasFinishSiding(string $type): bool
    {
        return in_array(self::normalizeSiding($type), ['vinyl', 'bnb', 'wood', 'hardie'], true);
    }

    public static function hasWeatherCoatSiding(string $type): bool
    {
        return self::normalizeSiding($type) === 'weather_coat';
    }

    public static function hasHouseWrapSiding(string $type): bool
    {
        return self::normalizeSiding($type) === 'house_wrap';
    }

    public static function roofLabel(string $type): string
    {
        $key = self::normalizeRoofType($type);

        return self::ROOF[$key];
    }

    public static function normalizeRoofType(mixed $type): string
    {
        $value = strtolower(trim((string) $type));
        if ($value === 'metal' || $value === 'metal_roof' || $value === 'metal_silver') {
            return $value === 'metal_silver' ? 'metal_galvanized' : 'metal_black';
        }

        return array_key_exists($value, self::ROOF) ? $value : 'asphalt';
    }

    public static function isMetalRoof(string $type): bool
    {
        $key = self::normalizeRoofType($type);

        return $key === 'metal_black' || $key === 'metal_galvanized';
    }

    public static function hasFinishRoof(string $type): bool
    {
        $key = self::normalizeRoofType($type);

        return $key === 'asphalt' || self::isMetalRoof($key);
    }

    public static function hasWeatherCoatRoof(string $type): bool
    {
        return self::normalizeRoofType($type) === 'weather_coat';
    }

    public static function hasHouseWrapRoof(string $type): bool
    {
        return self::normalizeRoofType($type) === 'house_wrap';
    }

    public static function metalMaterialKey(string $type): string
    {
        return self::normalizeRoofType($type) === 'metal_galvanized'
            ? ShedSettings::MATERIAL_METAL_GALVANIZED
            : ShedSettings::MATERIAL_METAL_BLACK;
    }

    public static function normalizeShedType(mixed $type): string
    {
        $value = strtolower(trim((string) $type));

        return array_key_exists($value, self::SHED_TYPES) ? $value : 'gable';
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, bool $draft = false): array
    {
        if ($draft) {
            return [];
        }
        $length = max(0, (float) ($inputs['length_ft'] ?? 0));
        $width = max(0, (float) ($inputs['width_ft'] ?? 0));
        if ($length <= 0 || $width <= 0) {
            return ['Enter length and width in feet.'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs, ?ShedSettings $settings = null): array
    {
        $settings = $settings ?? ShedSettings::current();
        $prod = $settings->resolvedProductivity();
        $matCatalog = $this->resolveMaterialCatalog($settings);
        $inputs = array_merge(self::defaultInputs(), $inputs);
        $inputs['shed_type'] = self::normalizeShedType($inputs['shed_type'] ?? 'gable');
        $inputs['roof_type'] = self::normalizeRoofType($inputs['roof_type'] ?? 'asphalt');
        $inputs['siding'] = self::normalizeSiding($inputs['siding'] ?? 'vinyl');

        $length = round(max(0, (float) $inputs['length_ft']), 2);
        $width = round(max(0, (float) $inputs['width_ft']), 2);
        $height = round(max(0, (float) $inputs['wall_height_ft']), 2) ?: 8;
        $pitch = max(1, (int) $inputs['roof_pitch']);
        $overhangFt = round(max(0, (float) $inputs['overhang_in']) / 12, 4);
        $area = round($length * $width, 2);
        $areaSqm = round($area / self::SQFT_PER_SQM, 2);

        if ($area <= 0) {
            return $this->emptyCalculation($inputs, $settings, $height);
        }

        $perimeter = round(2 * ($length + $width), 2);
        $doorQty = max(0, (int) $inputs['door_qty']);
        $windowQty = max(0, (int) $inputs['window_qty']);
        $doubleDoor = ! empty($inputs['double_door']);
        $doorSf = $doorQty * ($doubleDoor ? 42 : 21);
        if (($inputs['door_type'] ?? '') === 'rollup') {
            $doorSf = $doorQty * 56;
        }
        $windowSf = $windowQty * (($inputs['window_type'] ?? 'slider') === 'transom' ? 6 : 12);
        $openingSf = $doorSf + $windowSf;
        $wallGross = round($perimeter * $height, 2);
        $wallNet = round(max(0, $wallGross - $openingSf), 2);

        $pitchFactor = sqrt(1 + ($pitch / 12) ** 2);
        $roofFootprint = round(($length + 2 * $overhangFt) * ($width + 2 * $overhangFt), 2);
        $multKey = 'roof_mult_'.$inputs['shed_type'];
        $typeMult = max(0.5, (float) ($prod[$multKey] ?? 1));
        $roofSf = round($roofFootprint * $pitchFactor * $typeMult, 2);

        $permit = $this->permitWarnings($inputs, $areaSqm, $area);

        $labor = [];
        $materials = [];
        $providesAll = ! empty($inputs['client_provides_all']);
        $provideSections = is_array($inputs['client_provides_sections'] ?? null)
            ? array_values(array_filter(array_map('strval', $inputs['client_provides_sections'])))
            : [];

        $clientProvidedMat = function (string $section) use ($providesAll, $provideSections): bool {
            return $providesAll || in_array($section, $provideSections, true);
        };

        $addLabor = function (string $key, string $section, string $label, string $detail, float $hours) use (&$labor, $inputs): void {
            $override = is_array($inputs['labor_override'][$key] ?? null) ? $inputs['labor_override'][$key] : [];
            if (isset($override['hours']) && is_numeric($override['hours'])) {
                $hours = max(0, (float) $override['hours']);
            }
            $hours = round($hours, 2);
            if ($hours <= 0) {
                return;
            }
            $labor[] = [
                'key' => $key,
                'section' => $section,
                'label' => $label,
                'detail' => $detail,
                'hours' => $hours,
            ];
        };

        $addMat = function (
            string $key,
            string $section,
            float $qty,
            ?string $catalogKey = null,
            ?string $labelOverride = null
        ) use (&$materials, $inputs, $matCatalog, $clientProvidedMat): void {
            $qty = round(max(0, $qty), 2);
            if ($qty <= 0) {
                return;
            }
            $catalogKey = $catalogKey ?: $key;
            $def = $matCatalog[$catalogKey] ?? [
                'name' => $key,
                'unit' => 'each',
                'unit_price' => 0,
                'catalog_material_id' => null,
                'delivery_cad' => 0,
            ];
            $override = is_array($inputs['materials_override'][$key] ?? null) ? $inputs['materials_override'][$key] : [];
            if (isset($override['qty']) && is_numeric($override['qty'])) {
                $qty = round(max(0, (float) $override['qty']), 2);
            }
            $unitPrice = (float) $def['unit_price'];
            if (isset($override['unit_price']) && is_numeric($override['unit_price'])) {
                $unitPrice = round(max(0, (float) $override['unit_price']), 4);
            }
            if (ShedSettings::isDerivedPlate($key)) {
                $studKey = $key === ShedSettings::MATERIAL_PLATE_2X6
                    ? ShedSettings::MATERIAL_LUMBER_2X6
                    : ShedSettings::MATERIAL_LUMBER_2X4;
                $studPrice = (float) ($matCatalog[$studKey]['unit_price'] ?? 0);
                $studOverride = is_array($inputs['materials_override'][$studKey] ?? null)
                    ? $inputs['materials_override'][$studKey]
                    : [];
                if (isset($studOverride['unit_price']) && is_numeric($studOverride['unit_price'])) {
                    $studPrice = round(max(0, (float) $studOverride['unit_price']), 4);
                }
                $unitPrice = $studPrice > 0
                    ? round($studPrice / ShedSettings::PLATE_FEET_PER_BOARD, 4)
                    : 0.0;
            }
            $clientProvided = $clientProvidedMat($section);
            $amount = $clientProvided ? 0.0 : round($qty * $unitPrice, 2);
            $derived = ShedSettings::isDerivedPlate($key);
            $materials[] = [
                'key' => $key,
                'section' => $section,
                'label' => ($labelOverride !== null && $labelOverride !== '') ? $labelOverride : $def['name'],
                'qty' => $qty,
                'unit' => $derived ? 'lf' : $def['unit'],
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'catalog_material_id' => $derived ? null : ($def['catalog_material_id'] ?? null),
                'client_provided' => $clientProvided,
                'delivery_cad' => ($clientProvided || $derived)
                    ? 0.0
                    : round(max(0, (float) ($def['delivery_cad'] ?? 0)), 2),
            ];
        };

        $perHr = function (float $qty, float $rate): float {
            if ($qty <= 0) {
                return 0;
            }

            return $rate > 0 ? $qty / $rate : 0;
        };

        $waste = ShedSettings::MATERIAL_WASTE;

        if (! empty($inputs['include_site_level'])) {
            $addLabor('site_level', 'foundation', 'Site leveling', number_format($area, 0).' sq ft', $perHr($area, (float) $prod['site_level_sf_per_hr']));
        }

        $foundation = (string) ($inputs['foundation'] ?? 'skids');
        if ($foundation === 'pad') {
            $thick = max(2, (float) $inputs['pad_thickness_in']);
            $gravelIn = max(0, (float) $inputs['gravel_depth_in']);
            $cy = round(($area * ($thick / 12)) / 27, 2);
            $gravelCy = $gravelIn > 0 ? round(($area * ($gravelIn / 12)) / 27, 2) : 0;
            $addLabor('foundation_pad', 'foundation', 'Concrete pad', number_format($area, 0).' sq ft × '.$thick.'"', $perHr($area, (float) $prod['pad_sf_per_hr']));
            $addMat('concrete', 'foundation', max(0.25, $cy), 'concrete');
            $addMat('gravel', 'foundation', $gravelCy, 'gravel');
        } elseif ($foundation === 'piles') {
            $autoPiles = max(4, (int) ceil(($length / 8 + 1) * ($width / 8 + 1)));
            $piles = $inputs['pile_qty'] === null || $inputs['pile_qty'] === ''
                ? $autoPiles
                : max(0, (int) $inputs['pile_qty']);
            $addLabor('foundation_piles', 'foundation', 'Helical piles', $piles.' pile(s)', $piles * (float) $prod['pile_hours_each']);
            $addMat('helical_pile', 'foundation', $piles, 'helical_pile');
        } elseif ($foundation === 'blocks') {
            $autoBlocks = max(6, (int) ceil(($length / 4 + 1) * ($width / 4 + 1)));
            $blocks = $inputs['block_qty'] === null || $inputs['block_qty'] === ''
                ? $autoBlocks
                : max(0, (int) $inputs['block_qty']);
            $excavateHrs = $blocks * (float) ($prod['block_excavate_hours_each'] ?? 0);
            $setHrs = $blocks * (float) ($prod['block_hours_each'] ?? 0);
            $addLabor(
                'foundation_blocks',
                'foundation',
                'Deck blocks',
                'excavate '.$blocks.' hole(s), 3/4" gravel, set '.$blocks.' block(s)',
                $excavateHrs + $setHrs
            );
            $addMat('deck_block', 'foundation', $blocks, 'deck_block');
            $addMat('gravel_34_bag', 'foundation', $blocks, 'gravel_34_bag');
        } else {
            $autoSkids = max(3, (int) ceil($width / 4) + 1);
            $skids = $inputs['skid_qty'] === null || $inputs['skid_qty'] === ''
                ? $autoSkids
                : max(0, (int) $inputs['skid_qty']);
            $addLabor('foundation_skids', 'foundation', 'Pressure-treated skids', $skids.' skid(s)', $skids * (float) $prod['skid_hours_each']);
            $addMat('pt_skid', 'foundation', $skids, 'pt_skid');
        }

        $joistSpacing = in_array((int) $inputs['joist_spacing_in'], [12, 16, 24], true)
            ? (int) $inputs['joist_spacing_in']
            : 16;
        $joistCount = max(3, (int) ceil(($length * 12) / $joistSpacing) + 1);
        $joistBoards = $joistCount + 2;
        $studSpacing = in_array((int) $inputs['stud_spacing_in'], [12, 16, 24], true)
            ? (int) $inputs['stud_spacing_in']
            : 16;
        $studCount = max(8, (int) ceil(($perimeter * 12) / $studSpacing) + 8);
        $platesLf = round($perimeter * 3, 0);

        $addLabor('floor_frame', 'framing', 'Floor framing', number_format($area, 0).' sq ft, '.$joistCount.' joists', $perHr($area, (float) $prod['floor_frame_sf_per_hr']));
        $addLabor('wall_frame', 'framing', 'Wall framing', number_format($perimeter, 0).' lf × '.$height.' ft', $perHr($perimeter, (float) $prod['wall_frame_lf_per_hr']));

        $joistKey = ($inputs['joist_size'] ?? '2x6') === '2x4' ? 'lumber_2x4' : 'lumber_2x6';
        $studKey = ($inputs['stud_size'] ?? '2x4') === '2x6' ? 'lumber_2x6' : 'lumber_2x4';
        $plateKey = $studKey === 'lumber_2x6' ? 'plate_2x6' : 'plate_2x4';
        $addMat($joistKey.'_floor', 'framing', $joistBoards, $joistKey, ($inputs['joist_size'] ?? '2x6').' floor joists');
        $addMat($studKey.'_walls', 'framing', $studCount, $studKey, ($inputs['stud_size'] ?? '2x4').' wall studs');
        $addMat($plateKey, 'framing', $platesLf, $plateKey);

        $floorSheathing = ($inputs['floor_sheathing'] ?? 'osb_58') === 'plywood_34' ? 'plywood_34' : 'osb_58';
        $floorSheets = max(1, (int) ceil(($area * $waste) / ShedSettings::PANEL_SF));
        $addMat($floorSheathing, 'framing', $floorSheets, $floorSheathing);
        if (! empty($inputs['wall_sheathing'])) {
            $wallSheets = max(1, (int) ceil(($wallNet * $waste) / ShedSettings::PANEL_SF));
            $addMat('wall_osb', 'framing', $wallSheets, 'wall_osb');
        }
        $addMat('fasteners', 'framing', 1, 'fasteners');

        $addLabor('roof_frame', 'roof', 'Roof framing', number_format($roofSf, 0).' sq ft', $perHr($roofSf, (float) $prod['roof_frame_sf_per_hr']));
        $rafterCount = max(4, (int) ceil(($length * 12) / 16) + 1) * 2;
        $addMat('lumber_2x6_rafters', 'roof', $rafterCount, 'lumber_2x6', 'Roof rafters');
        $roofSheets = max(1, (int) ceil(($roofSf * $waste) / ShedSettings::PANEL_SF));
        $addMat('osb_58_roof', 'roof', $roofSheets, 'osb_58', 'Roof sheathing');
        $addMat('fascia', 'roof', round($perimeter + 8 * $overhangFt, 0), 'fascia');
        $roofType = (string) $inputs['roof_type'];
        if (self::hasFinishRoof($roofType)) {
            $addLabor('roof_cover', 'roof', 'Roof covering', self::roofLabel($roofType), $perHr($roofSf, (float) $prod['roof_cover_sf_per_hr']));
            $addMat('underlayment', 'roof', round($roofSf * $waste, 0), 'underlayment');
            $eaveLf = round(($length + 2 * $overhangFt) * 2, 0);
            $addMat('ice_water', 'roof', round($eaveLf * 3, 0), 'ice_water');
            $addMat('drip_edge', 'roof', round($perimeter + 8 * $overhangFt, 0), 'drip_edge');
            $addMat('ridge_vent', 'roof', round($length + 2 * $overhangFt, 0), 'ridge_vent');
            if (self::isMetalRoof($roofType)) {
                $panels = max(1, (int) ceil(($roofSf * $waste) / ShedSettings::METAL_PANEL_SF));
                $metalKey = self::metalMaterialKey($roofType);
                $addMat($metalKey, 'roof', $panels, $metalKey);
            } else {
                $bundles = max(1, (int) ceil(($roofSf * $waste) / ShedSettings::SHINGLE_SF_PER_BUNDLE));
                $addMat('asphalt_shingle', 'roof', $bundles, 'asphalt_shingle');
            }
        } elseif (self::hasHouseWrapRoof($roofType)) {
            $addLabor('roof_wrap', 'roof', 'Roof house wrap', number_format($roofSf, 0).' sq ft', $perHr($roofSf, (float) ($prod['house_wrap_sf_per_hr'] ?? 150)));
            $addMat('house_wrap_roof', 'roof', round($roofSf * $waste, 0), 'house_wrap', 'House wrap (roof)');
        } elseif (self::hasWeatherCoatRoof($roofType)) {
            $addLabor('roof_weather', 'roof', 'Roof weather coat', number_format($roofSf, 0).' sq ft', $perHr($roofSf, (float) $prod['paint_sf_per_hr']));
            $addMat('weather_coat_roof', 'roof', ShedSettings::weatherCoatGallons($roofSf), ShedSettings::MATERIAL_WEATHER_COAT);
        }

        $sidingType = (string) $inputs['siding'];
        if (self::hasFinishSiding($sidingType)) {
            $sidingKey = match ($sidingType) {
                'bnb' => 'siding_bnb',
                'wood' => 'siding_wood',
                'hardie' => 'siding_hardie',
                default => 'siding_vinyl',
            };
            $addLabor('siding', 'siding', 'Siding', self::sidingLabel($sidingType).' — '.number_format($wallNet, 0).' sq ft', $perHr($wallNet, (float) $prod['siding_sf_per_hr']));
            $addMat('house_wrap', 'siding', round($wallNet * $waste, 0), 'house_wrap');
            $addMat($sidingKey, 'siding', round($wallNet * $waste, 0), $sidingKey);
            $addMat('corner_trim', 'siding', round($height * 4 + $perimeter * 0.15, 0), 'corner_trim');
        } elseif (self::hasHouseWrapSiding($sidingType)) {
            $addLabor('siding_wrap', 'siding', 'Wall house wrap', number_format($wallNet, 0).' sq ft', $perHr($wallNet, (float) ($prod['house_wrap_sf_per_hr'] ?? 150)));
            $addMat('house_wrap_siding', 'siding', round($wallNet * $waste, 0), 'house_wrap', 'House wrap (walls)');
        } elseif (self::hasWeatherCoatSiding($sidingType)) {
            $addLabor('siding_weather', 'siding', 'Wall weather coat', number_format($wallNet, 0).' sq ft', $perHr($wallNet, (float) $prod['paint_sf_per_hr']));
            $addMat('weather_coat_siding', 'siding', ShedSettings::weatherCoatGallons($wallNet), ShedSettings::MATERIAL_WEATHER_COAT);
        }

        $doorMat = match ((string) ($inputs['door_type'] ?? 'custom')) {
            'prehung' => 'door_prehung',
            'rollup' => 'door_rollup',
            default => 'door_custom',
        };
        $windowMat = (($inputs['window_type'] ?? 'slider') === 'transom') ? 'window_transom' : 'window_slider';
        $addLabor('doors', 'openings', 'Doors', $doorQty.' '.$this->doorTypeLabel((string) $inputs['door_type']), $doorQty * (float) $prod['door_hours_each']);
        $addLabor('windows', 'openings', 'Windows', $windowQty.' '.$this->windowTypeLabel((string) $inputs['window_type']), $windowQty * (float) $prod['window_hours_each']);
        $addMat($doorMat, 'openings', $doorQty, $doorMat);
        $addMat($windowMat, 'openings', $windowQty, $windowMat);

        if (($inputs['interior_finish'] ?? 'rough') === 'finished') {
            $addLabor('insulation', 'interior', 'Insulation & vapor barrier', number_format($wallNet, 0).' sq ft walls', $perHr($wallNet, (float) $prod['insulation_sf_per_hr']));
            $addLabor('drywall_hang', 'interior', 'Hang drywall', number_format($wallNet, 0).' sq ft', $perHr($wallNet, (float) $prod['drywall_hang_sf_per_hr']));
            $addLabor('drywall_finish', 'interior', 'Tape / mud / sand', number_format($wallNet, 0).' sq ft', $perHr($wallNet, (float) $prod['drywall_finish_sf_per_hr']));
            $addMat('insulation_batt', 'interior', max(1, (int) ceil(($wallNet * $waste) / ShedSettings::BATT_SF_PER_BAG)), 'insulation_batt');
            $addMat('vapor_barrier', 'interior', max(1, (int) ceil(($wallNet * $waste) / ShedSettings::VAPOR_SF_PER_ROLL)), 'vapor_barrier');
            $dwSheets = max(1, (int) ceil(($wallNet * $waste) / ShedSettings::PANEL_SF));
            $addMat('drywall_sheet', 'interior', $dwSheets, 'drywall_sheet');
            $addMat('joint_compound', 'interior', max(1, (int) ceil($wallNet / ShedSettings::JOINT_COMPOUND_SF_PER_PAIL)), 'joint_compound');
            $addMat('drywall_tape', 'interior', max(1, (int) ceil($wallNet / ShedSettings::DRYWALL_TAPE_SF_PER_ROLL)), 'drywall_tape');
        }

        if (! empty($inputs['include_electrical'])) {
            $outlets = max(0, (int) $inputs['outlet_qty']);
            $lights = max(0, (int) $inputs['light_qty']);
            $addLabor('elec_outlets', 'interior', 'Outlets', $outlets.' location(s)', $outlets * (float) $prod['outlet_hours_each']);
            $addLabor('elec_lights', 'interior', 'Lights', $lights.' fixture(s)', $lights * (float) $prod['light_hours_each']);
            $addMat('outlet_box', 'interior', $outlets, 'outlet_box');
            $addMat('light_fixture', 'interior', $lights, 'light_fixture');
            if (! empty($inputs['include_subpanel'])) {
                $addLabor('elec_subpanel', 'interior', 'Subpanel', 'Allowance', (float) $prod['subpanel_hours']);
                $addMat('subpanel', 'interior', 1, 'subpanel');
            }
        }

        if (! empty($inputs['include_loft'])) {
            $loftSf = round($area * 0.4, 2);
            $addLabor('loft', 'interior', 'Storage loft', number_format($loftSf, 0).' sq ft', $perHr($loftSf, (float) $prod['loft_sf_per_hr']));
            $addMat('loft_osb', 'interior', max(1, (int) ceil(($loftSf * $waste) / ShedSettings::PANEL_SF)), 'loft_osb');
        }

        if (! empty($inputs['include_paint']) && self::hasFinishSiding((string) $inputs['siding'])) {
            $addLabor('paint', 'siding', 'Exterior paint / stain', number_format($wallNet, 0).' sq ft', $perHr($wallNet, (float) $prod['paint_sf_per_hr']));
            $gallons = max(1, (int) ceil(($wallNet * ShedSettings::PAINT_WASTE) / ShedSettings::PAINT_SF_PER_GALLON));
            $addMat('paint_exterior', 'siding', $gallons, 'paint_exterior');
        }
        if (! empty($inputs['include_ramp'])) {
            $addLabor('ramp', 'openings', 'Access ramp', 'Package', (float) $prod['ramp_hours']);
            $addMat('ramp', 'openings', 1, 'ramp');
        }
        if (! empty($inputs['include_gutters'])) {
            $gutterLf = round(($length + 2 * $overhangFt) * 2, 0);
            $addLabor('gutters', 'roof', 'Gutters', $gutterLf.' lf', $perHr($gutterLf, (float) $prod['gutter_lf_per_hr']));
            $addMat('gutter', 'roof', $gutterLf, 'gutter');
        }

        $extraMaterials = $this->extraMaterials($inputs, $providesAll);
        $materials = $this->appendDeliveryLines($materials);
        $extraMaterials = $this->appendDeliveryLines($extraMaterials);

        $ownerRate = max(0, (float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate));
        $helperRate = max(0, (float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate));
        $includeHelper = QuotationHelperLabor::enabled($inputs);
        $hoursPerDay = max(0.5, (float) $settings->hours_per_day);

        $totalHours = 0.0;
        foreach ($labor as &$row) {
            $totalHours += $row['hours'];
            $row['amount'] = QuotationHelperLabor::amount((float) $row['hours'], $ownerRate, $helperRate, $includeHelper);
        }
        unset($row);
        $totalHours = round($totalHours, 2);
        $laborPre = round(QuotationHelperLabor::amount($totalHours, $ownerRate, $helperRate, $includeHelper), 2);

        $matPre = 0.0;
        foreach ($materials as $row) {
            $matPre += (float) $row['amount'];
        }
        foreach ($extraMaterials as $row) {
            $matPre += (float) $row['amount'];
        }
        $matPre = round($matPre, 2);
        $matHst = round($matPre * ShedQuotation::HST_RATE, 2);
        $laborHst = round($laborPre * ShedQuotation::HST_RATE, 2);
        $subtotal = round($laborPre + $matPre, 2);
        $hst = round($laborHst + $matHst, 2);
        $grand = round($subtotal + $hst, 2);

        $eta = $this->etaScenarios($totalHours, $hoursPerDay, $ownerRate, $helperRate);

        $breakdown = [];
        foreach ($labor as $row) {
            $breakdown[] = [
                'key' => $row['key'],
                'label' => $row['label'],
                'amount' => $row['amount'],
            ];
        }

        return [
            'scope' => [
                'shed_type' => $inputs['shed_type'],
                'shed_type_label' => self::shedTypeLabel($inputs['shed_type']),
                'length_ft' => $length,
                'width_ft' => $width,
                'wall_height_ft' => $height,
                'area_sqft' => $area,
                'area_sqm' => $areaSqm,
                'perimeter_ft' => $perimeter,
                'wall_sf' => $wallNet,
                'roof_sf' => $roofSf,
                'roof_pitch' => $pitch,
                'attached' => ! empty($inputs['attached']),
                'permit_required' => $permit !== [],
                'permit_warnings' => $permit,
                'finish_label' => (($inputs['interior_finish'] ?? 'rough') === 'finished') ? 'Fully finished' : 'Rough / shell',
            ],
            'labor' => $labor,
            'materials' => $materials,
            'extra_materials' => $extraMaterials,
            'eta' => $eta,
            'breakdown' => $breakdown,
            'summary' => [
                'total_hours' => $totalHours,
                'owner_hourly_rate' => $ownerRate,
                'helper_hourly_rate' => $helperRate,
                'include_helper' => $includeHelper,
                'labor_pre_hst' => $laborPre,
                'labor_hst' => $laborHst,
                'labor_after_hst' => round($laborPre + $laborHst, 2),
                'materials_pre_hst' => $matPre,
                'materials_hst' => $matHst,
                'materials_after_hst' => round($matPre + $matHst, 2),
                'subtotal_pre_hst' => $subtotal,
                'hst' => $hst,
                'grand_total' => $grand,
                'final_quote_total' => $grand,
                'client_provides_all' => $providesAll,
            ],
        ];
    }

    public static function permitExemptMaxSqFt(): float
    {
        return round(self::PERMIT_AREA_SQM * self::SQFT_PER_SQM, 1);
    }

    public static function permitExemptMaxLabel(): string
    {
        return self::permitExemptMaxSqFt().' sq ft';
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function permitWarnings(array $inputs, float $areaSqm, float $areaSqft = 0): array
    {
        $warnings = [];
        $maxLabel = self::permitExemptMaxLabel();
        if ($areaSqm > self::PERMIT_AREA_SQM) {
            $warnings[] = 'Building permit required. This floor is '.$areaSqft.' sq ft. Max without a permit: '.$maxLabel.'.';
        }
        if (! empty($inputs['attached']) || ($inputs['shed_type'] ?? '') === 'lean_to' && ! empty($inputs['attached'])) {
            $warnings[] = 'Attached sheds are not permit-exempt, even under '.$maxLabel.'.';
        }
        if (($inputs['interior_finish'] ?? 'rough') === 'finished') {
            $warnings[] = 'A fully finished interior may not qualify as storage-only. Confirm permit requirements with the municipality.';
        }
        if (! empty($inputs['include_electrical'])) {
            $warnings[] = 'Permanent electrical typically needs an ESA notification and may require a building permit.';
        }
        if (! empty($inputs['include_plumbing'])) {
            $warnings[] = 'Plumbing disqualifies the OBC shed permit exemption.';
        }
        if (! empty($inputs['include_loft'])) {
            $warnings[] = 'A loft or second storey is not covered by the one-storey storage-shed exemption.';
        }

        return $warnings;
    }

    private function doorTypeLabel(string $type): string
    {
        return self::DOOR_TYPES[$type] ?? self::DOOR_TYPES['custom'];
    }

    private function windowTypeLabel(string $type): string
    {
        return self::WINDOW_TYPES[$type] ?? self::WINDOW_TYPES['slider'];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function emptyCalculation(array $inputs, ShedSettings $settings, float $height): array
    {
        $ownerRate = max(0, (float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate));
        $helperRate = max(0, (float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate));

        return [
            'scope' => [
                'shed_type' => self::normalizeShedType($inputs['shed_type'] ?? 'gable'),
                'shed_type_label' => self::shedTypeLabel((string) ($inputs['shed_type'] ?? 'gable')),
                'length_ft' => 0,
                'width_ft' => 0,
                'wall_height_ft' => $height,
                'area_sqft' => 0,
                'area_sqm' => 0,
                'perimeter_ft' => 0,
                'wall_sf' => 0,
                'roof_sf' => 0,
                'roof_pitch' => (int) ($inputs['roof_pitch'] ?? 6),
                'attached' => ! empty($inputs['attached']),
                'permit_required' => false,
                'permit_warnings' => [],
                'finish_label' => 'Rough / shell',
            ],
            'labor' => [],
            'materials' => [],
            'extra_materials' => [],
            'eta' => $this->etaScenarios(0, (float) $settings->hours_per_day, $ownerRate, $helperRate),
            'breakdown' => [],
            'summary' => [
                'total_hours' => 0.0,
                'owner_hourly_rate' => $ownerRate,
                'helper_hourly_rate' => $helperRate,
                'include_helper' => QuotationHelperLabor::enabled($inputs),
                'labor_pre_hst' => 0.0,
                'labor_hst' => 0.0,
                'labor_after_hst' => 0.0,
                'materials_pre_hst' => 0.0,
                'materials_hst' => 0.0,
                'materials_after_hst' => 0.0,
                'subtotal_pre_hst' => 0.0,
                'hst' => 0.0,
                'grand_total' => 0.0,
                'final_quote_total' => 0.0,
                'client_provides_all' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function extraMaterials(array $inputs, bool $providesAll): array
    {
        $extraMaterials = [];
        $extraCatalogIds = [];
        foreach (is_array($inputs['extra_materials'] ?? null) ? $inputs['extra_materials'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0) {
                $extraCatalogIds[] = $id;
            }
        }
        $extraDeliveries = CatalogMaterialDelivery::mapForIds($extraCatalogIds);
        foreach (is_array($inputs['extra_materials'] ?? null) ? $inputs['extra_materials'] : [] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? $row['short_name'] ?? ''));
            $qty = round(max(0, (float) ($row['quantity'] ?? $row['qty'] ?? 0)), 2);
            $unitPrice = round(max(0, (float) ($row['unit_price'] ?? 0)), 4);
            if ($name === '' || $qty <= 0) {
                continue;
            }
            $clientProvided = $providesAll || ! empty($row['client_provided']);
            $catalogId = (int) ($row['catalog_material_id'] ?? 0) ?: null;
            $delivery = 0.0;
            if (! $clientProvided) {
                if ($catalogId && array_key_exists($catalogId, $extraDeliveries)) {
                    $delivery = $extraDeliveries[$catalogId];
                } else {
                    $delivery = round(max(0, (float) ($row['delivery_cad'] ?? 0)), 2);
                }
            }
            $extraMaterials[] = [
                'key' => 'extra_'.$i,
                'section' => 'extra',
                'label' => $name,
                'qty' => $qty,
                'unit' => trim((string) ($row['unit'] ?? 'each')) ?: 'each',
                'unit_price' => $unitPrice,
                'amount' => $clientProvided ? 0.0 : round($qty * $unitPrice, 2),
                'catalog_material_id' => $catalogId,
                'client_provided' => $clientProvided,
                'delivery_cad' => $delivery,
            ];
        }

        return $extraMaterials;
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
                $scenario(1, 'Working alone (Owner)'),
                $scenario(2, 'Owner + 1 Helper'),
                $scenario(3, 'Owner + 2 Helpers'),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function resolveMaterialCatalog(ShedSettings $settings): array
    {
        $rows = $settings->resolvedMaterials();
        $ids = [];
        foreach ($rows as $row) {
            if (! empty($row['catalog_material_id'])) {
                $ids[] = (int) $row['catalog_material_id'];
            }
        }
        $catalog = [];
        if ($ids !== []) {
            $catalog = Material::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');
        }
        foreach ($rows as $key => $row) {
            if (ShedSettings::isDerivedPlate($key)) {
                continue;
            }
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0 && isset($catalog[$id])) {
                /** @var Material $mat */
                $mat = $catalog[$id];
                $rows[$key]['catalog_material_id'] = $id;
                $rows[$key]['delivery_cad'] = CatalogMaterialDelivery::cad($mat);
            }
        }

        return ShedSettings::applyDerivedMaterialPrices($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function appendDeliveryLines(array $rows): array
    {
        $out = $rows;
        foreach ($rows as $row) {
            if (! empty($row['is_delivery']) || ! empty($row['client_provided'])) {
                continue;
            }
            if ((float) ($row['qty'] ?? 0) <= 0) {
                continue;
            }
            $delivery = round(max(0.0, (float) ($row['delivery_cad'] ?? 0)), 2);
            if ($delivery <= 0) {
                continue;
            }
            $out[] = [
                'key' => $row['key'].'_delivery',
                'section' => $row['section'],
                'label' => $row['label'].' delivery',
                'qty' => 1.0,
                'unit' => 'flat',
                'unit_price' => $delivery,
                'amount' => $delivery,
                'catalog_material_id' => $row['catalog_material_id'] ?? null,
                'client_provided' => false,
                'delivery_cad' => 0.0,
                'is_delivery' => true,
            ];
        }

        return $out;
    }
}

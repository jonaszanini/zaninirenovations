<?php

namespace App\Services;

use App\Models\Material;
use App\Models\TrimmingQuotation;
use App\Models\TrimmingSettings;
use App\Support\CatalogMaterialDelivery;
use App\Support\QuotationBuildingLabor;
use App\Support\QuotationHelperLabor;

class TrimmingQuotationCalculator
{
    public const FAMILIES = [
        'doors_windows' => 'Doors & windows',
        'floor_wall' => 'Floor to wall',
        'ceiling' => 'Ceiling to wall',
        'wall_treatments' => 'Wall treatments',
    ];

    public const WAINSCOT_STYLES = [
        'beadboard' => 'Beadboard',
        'raised_panel' => 'Raised panels',
        'board_and_batten' => 'Board and batten',
        'shaker' => 'Shaker panels',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'enabled_families' => [],
            'door_qty' => 0,
            'window_qty' => 0,
            'include_casing' => true,
            'include_architrave' => false,
            'include_plinth' => false,
            'include_rosettes' => false,
            'include_stool_apron' => true,
            'include_jamb_extensions' => false,
            'include_interior_doors' => false,
            'include_slab_swap' => false,
            'include_picture_frame_windows' => false,
            'pocket_door_qty' => 0,
            'french_door_qty' => 0,
            'picture_frame_window_qty' => null,
            'casing_lf' => null,
            'window_casing_lf' => null,
            'architrave_lf' => null,
            'plinth_qty' => null,
            'rosette_qty' => null,
            'stool_apron_qty' => null,
            'jamb_qty' => null,
            'floor_lf' => 0,
            'include_baseboard' => true,
            'include_base_cap' => false,
            'include_built_up_baseboard' => false,
            'include_shoe' => false,
            'include_quarter_round' => false,
            'baseboard_lf' => null,
            'base_cap_lf' => null,
            'built_up_baseboard_lf' => null,
            'shoe_lf' => null,
            'quarter_round_lf' => null,
            'include_crown' => true,
            'include_cove' => false,
            'include_built_up_crown' => false,
            'crown_lf' => 0,
            'cove_lf' => 0,
            'built_up_crown_lf' => 0,
            'include_chair_rail' => false,
            'include_picture_rail' => false,
            'include_box_moulding' => false,
            'include_wainscoting' => false,
            'chair_rail_lf' => 0,
            'picture_rail_lf' => 0,
            'box_moulding_lf' => 0,
            'wainscot_style' => 'beadboard',
            'wainscot_wall_lf' => 0,
            'wainscot_height_in' => 36,
            'include_remove_existing' => false,
            'remove_lf' => null,
            'include_caulk_paint' => false,
            'include_stain' => false,
            'paint_lf' => null,
            'stain_lf' => null,
            'paint_coats' => 2,
            'client_provides_all' => false,
            'client_provides_sections' => [],
            'owner_hourly_rate' => (float) (TrimmingSettings::current()->owner_hourly_rate ?? \App\Support\QuotationSharedLabourRates::LEGACY_OWNER_HOURLY),
            'helper_hourly_rate' => 45,
            'include_helper' => false,
            'include_building' => false,
            'building_labor_percent' => QuotationBuildingLabor::DEFAULT_PERCENT,
            'extra_materials' => [],
            'labor_override' => [],
            'materials_override' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function familyEnabled(array $inputs, string $family): bool
    {
        $enabled = $inputs['enabled_families'] ?? [];
        if (! is_array($enabled)) {
            return false;
        }

        return in_array($family, $enabled, true);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public static function normalizeEnabledFamilies(array $inputs): array
    {
        $raw = $inputs['enabled_families'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $allowed = array_keys(self::FAMILIES);

        return array_values(array_intersect($allowed, array_map('strval', $raw)));
    }

    public static function normalizeWainscotStyle(mixed $style): string
    {
        $value = strtolower(trim((string) $style));

        return array_key_exists($value, self::WAINSCOT_STYLES) ? $value : 'beadboard';
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, bool $draft = false, ?TrimmingSettings $settings = null): array
    {
        if ($draft) {
            return [];
        }

        $inputs = array_merge(self::defaultInputs(), $inputs);
        $families = self::normalizeEnabledFamilies($inputs);
        if ($families === []) {
            return ['Turn on at least one trim family and enter quantities.'];
        }

        $calc = $this->calculate($inputs, $settings);
        $lf = (float) ($calc['scope']['total_lf'] ?? 0);
        $doors = (int) ($inputs['door_qty'] ?? 0);
        $windows = (int) ($inputs['window_qty'] ?? 0);
        $hours = (float) ($calc['summary']['total_hours'] ?? 0);
        if ($lf <= 0 && $doors <= 0 && $windows <= 0 && $hours <= 0) {
            return ['Enter linear feet or opening counts for the families you turned on.'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs, ?TrimmingSettings $settings = null): array
    {
        $settings = $settings ?? TrimmingSettings::current();
        $prod = $settings->resolvedProductivity();
        $matCatalog = $this->resolveMaterialCatalog($settings);
        $inputs = array_merge(self::defaultInputs(), $inputs);
        $inputs['enabled_families'] = self::normalizeEnabledFamilies($inputs);
        $inputs['wainscot_style'] = self::normalizeWainscotStyle($inputs['wainscot_style'] ?? 'beadboard');
        $waste = $settings->resolvedWasteFactor();

        $on = function (string $family) use ($inputs): bool {
            return self::familyEnabled($inputs, $family);
        };

        $labor = [];
        $materials = [];
        $scopeLf = 0.0;
        $installLf = 0.0;

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
            $lookup = $catalogKey ?: $key;
            $def = isset($matCatalog[$lookup]) ? $matCatalog[$lookup] : [
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
            $clientProvided = $clientProvidedMat($section);
            $amount = $clientProvided ? 0.0 : round($qty * $unitPrice, 2);
            $materials[] = [
                'key' => $key,
                'section' => $section,
                'label' => ($labelOverride !== null && $labelOverride !== '') ? $labelOverride : $def['name'],
                'qty' => $qty,
                'unit' => $def['unit'],
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'catalog_material_id' => $def['catalog_material_id'] ?? null,
                'client_provided' => $clientProvided,
                'delivery_cad' => $clientProvided ? 0.0 : round(max(0, (float) ($def['delivery_cad'] ?? 0)), 2),
            ];
        };

        $perHr = function (float $qty, float $rate): float {
            if ($qty <= 0 || $rate <= 0) {
                return 0;
            }

            return $qty / $rate;
        };

        $stick = function (float $lf) use ($waste): float {
            return $this->packages($lf * $waste, TrimmingSettings::STICK_FEET);
        };

        $doors = max(0, (int) $inputs['door_qty']);
        $windows = max(0, (int) $inputs['window_qty']);
        $openings = $doors + $windows;

        if ($on('doors_windows')) {
            $doorCasingLf = ! empty($inputs['include_casing'])
                ? $this->qtyOr($inputs['casing_lf'] ?? null, $doors * TrimmingSettings::CASING_LF_PER_DOOR)
                : 0.0;
            $windowCasingLf = ! empty($inputs['include_casing'])
                ? $this->qtyOr($inputs['window_casing_lf'] ?? null, $windows * TrimmingSettings::WINDOW_CASING_LF_PER_WINDOW)
                : 0.0;
            $casingLf = $doorCasingLf + $windowCasingLf;
            if ($casingLf > 0) {
                $addLabor('trim_casing', 'doors_windows', 'Casing', number_format($casingLf, 0).' lf', $perHr($casingLf, (float) $prod['casing_lf_per_hr']));
                $addMat('casing', 'doors_windows', $stick($casingLf), 'casing');
                $installLf += $casingLf;
            }

            $archLf = ! empty($inputs['include_architrave'])
                ? $this->qtyOr($inputs['architrave_lf'] ?? null, $openings * TrimmingSettings::ARCHITRAVE_LF_PER_OPENING)
                : 0.0;
            if ($archLf > 0) {
                $addLabor('trim_architrave', 'doors_windows', 'Architrave', number_format($archLf, 0).' lf', $perHr($archLf, (float) $prod['architrave_lf_per_hr']));
                $addMat('architrave', 'doors_windows', $stick($archLf), 'architrave');
                $installLf += $archLf;
            }

            $plinth = ! empty($inputs['include_plinth'])
                ? (int) $this->qtyOr($inputs['plinth_qty'] ?? null, $doors * 2)
                : 0;
            if ($plinth > 0) {
                $addLabor('trim_plinth', 'doors_windows', 'Plinth blocks', $plinth.' pc', $plinth * (float) $prod['plinth_hours_each']);
                $addMat('plinth', 'doors_windows', $plinth, 'plinth');
            }

            $rosettes = ! empty($inputs['include_rosettes'])
                ? (int) $this->qtyOr($inputs['rosette_qty'] ?? null, $openings * 2)
                : 0;
            if ($rosettes > 0) {
                $addLabor('trim_rosettes', 'doors_windows', 'Rosettes', $rosettes.' pc', $rosettes * (float) $prod['rosette_hours_each']);
                $addMat('rosette', 'doors_windows', $rosettes, 'rosette');
            }

            $stool = ! empty($inputs['include_stool_apron'])
                ? (int) $this->qtyOr($inputs['stool_apron_qty'] ?? null, $windows)
                : 0;
            if ($stool > 0) {
                $addLabor('trim_stool', 'doors_windows', 'Window stool & apron', $stool.' window(s)', $stool * (float) $prod['stool_apron_hours_each']);
                $addMat('stool_apron', 'doors_windows', $stool, 'stool_apron');
            }

            $jambs = ! empty($inputs['include_jamb_extensions'])
                ? (int) $this->qtyOr($inputs['jamb_qty'] ?? null, $openings)
                : 0;
            if ($jambs > 0) {
                $addLabor('trim_jambs', 'doors_windows', 'Jamb extensions', $jambs.' opening(s)', $jambs * (float) $prod['jamb_hours_each']);
                $addMat('jamb_extension', 'doors_windows', $jambs, 'jamb_extension');
            }

            if (! empty($inputs['include_interior_doors']) && $doors > 0) {
                $addLabor('trim_doors', 'doors_windows', 'Pre-hung interior doors', $doors.' door(s)', $doors * (float) $prod['door_install_hours_each']);
                $addMat('interior_door', 'doors_windows', $doors, 'interior_door');
                $addMat('door_hardware', 'doors_windows', $doors, 'door_hardware');
            }

            if (! empty($inputs['include_slab_swap']) && $doors > 0) {
                $addLabor('trim_slab', 'doors_windows', 'Door slab swap', $doors.' door(s)', $doors * (float) ($prod['door_slab_hours_each'] ?? 2.41));
                $addMat('door_slab', 'doors_windows', $doors, 'door_slab');
            }

            $pictureWindows = ! empty($inputs['include_picture_frame_windows'])
                ? (int) $this->qtyOr($inputs['picture_frame_window_qty'] ?? null, $windows)
                : 0;
            if ($pictureWindows > 0) {
                $addLabor(
                    'trim_picture_frame_window',
                    'doors_windows',
                    'Picture-frame window casing',
                    $pictureWindows.' window(s)',
                    $pictureWindows * (float) ($prod['window_picture_frame_hours_each'] ?? 2)
                );
                $addMat('window_picture_frame', 'doors_windows', $pictureWindows, 'window_picture_frame');
            }

            $pocketQty = max(0, (int) ($inputs['pocket_door_qty'] ?? 0));
            if ($pocketQty > 0) {
                $addLabor('trim_pocket', 'doors_windows', 'Pocket doors (retrofit)', $pocketQty.' opening(s)', $pocketQty * (float) ($prod['pocket_door_hours_each'] ?? 24.82));
                $addMat('pocket_door', 'doors_windows', $pocketQty, 'pocket_door');
            }

            $frenchQty = max(0, (int) ($inputs['french_door_qty'] ?? 0));
            if ($frenchQty > 0) {
                $addLabor('trim_french', 'doors_windows', 'French / double doors', $frenchQty.' pair(s)', $frenchQty * (float) ($prod['french_door_hours_each'] ?? 7.82));
                $addMat('french_door', 'doors_windows', $frenchQty, 'french_door');
            }
        }

        $floorLf = max(0, (float) $inputs['floor_lf']);
        if ($on('floor_wall') && $floorLf > 0) {
            $profiles = [
                ['flag' => 'include_baseboard', 'override' => 'baseboard_lf', 'key' => 'baseboard', 'label' => 'Baseboards', 'prod' => 'baseboard_lf_per_hr', 'mat' => 'baseboard'],
                ['flag' => 'include_base_cap', 'override' => 'base_cap_lf', 'key' => 'base_cap', 'label' => 'Base cap', 'prod' => 'base_cap_lf_per_hr', 'mat' => 'base_cap'],
                ['flag' => 'include_built_up_baseboard', 'override' => 'built_up_baseboard_lf', 'key' => 'built_up_baseboard', 'label' => 'Built-up / hardwood baseboard', 'prod' => 'built_up_baseboard_lf_per_hr', 'mat' => 'built_up_baseboard'],
                ['flag' => 'include_shoe', 'override' => 'shoe_lf', 'key' => 'shoe', 'label' => 'Shoe moulding', 'prod' => 'shoe_lf_per_hr', 'mat' => 'shoe'],
                ['flag' => 'include_quarter_round', 'override' => 'quarter_round_lf', 'key' => 'quarter_round', 'label' => 'Quarter round', 'prod' => 'quarter_round_lf_per_hr', 'mat' => 'quarter_round'],
            ];
            foreach ($profiles as $row) {
                if (empty($inputs[$row['flag']])) {
                    continue;
                }
                $lf = $this->qtyOr($inputs[$row['override']] ?? null, $floorLf);
                if ($lf <= 0) {
                    continue;
                }
                $addLabor('trim_'.$row['key'], 'floor_wall', $row['label'], number_format($lf, 0).' lf', $perHr($lf, (float) $prod[$row['prod']]));
                $addMat($row['mat'], 'floor_wall', $stick($lf), $row['mat']);
                $installLf += $lf;
            }
        }

        if ($on('ceiling')) {
            if (! empty($inputs['include_crown'])) {
                $lf = max(0, (float) $inputs['crown_lf']);
                if ($lf > 0) {
                    $addLabor('trim_crown', 'ceiling', 'Crown moulding', number_format($lf, 0).' lf', $perHr($lf, (float) $prod['crown_lf_per_hr']));
                    $addMat('crown', 'ceiling', $stick($lf), 'crown');
                    $installLf += $lf;
                }
            }
            if (! empty($inputs['include_cove'])) {
                $lf = max(0, (float) $inputs['cove_lf']);
                if ($lf > 0) {
                    $addLabor('trim_cove', 'ceiling', 'Cove moulding', number_format($lf, 0).' lf', $perHr($lf, (float) $prod['cove_lf_per_hr']));
                    $addMat('cove', 'ceiling', $stick($lf), 'cove');
                    $installLf += $lf;
                }
            }
            if (! empty($inputs['include_built_up_crown'])) {
                $lf = max(0, (float) $inputs['built_up_crown_lf']);
                if ($lf > 0) {
                    $addLabor('trim_built_up_crown', 'ceiling', 'Built-up / complex crown', number_format($lf, 0).' lf', $perHr($lf, (float) ($prod['built_up_crown_lf_per_hr'] ?? 6.59)));
                    $addMat('built_up_crown', 'ceiling', $stick($lf), 'built_up_crown');
                    $installLf += $lf;
                }
            }
        }

        $wainscotSf = 0.0;
        if ($on('wall_treatments')) {
            $chairLf = ! empty($inputs['include_chair_rail']) ? max(0, (float) $inputs['chair_rail_lf']) : 0.0;
            if ($chairLf > 0) {
                $addLabor('trim_chair', 'wall_treatments', 'Chair rail', number_format($chairLf, 0).' lf', $perHr($chairLf, (float) $prod['chair_rail_lf_per_hr']));
                $addMat('chair_rail', 'wall_treatments', $stick($chairLf), 'chair_rail');
                $installLf += $chairLf;
            }
            $picLf = ! empty($inputs['include_picture_rail']) ? max(0, (float) $inputs['picture_rail_lf']) : 0.0;
            if ($picLf > 0) {
                $addLabor('trim_picture', 'wall_treatments', 'Picture rail', number_format($picLf, 0).' lf', $perHr($picLf, (float) $prod['picture_rail_lf_per_hr']));
                $addMat('picture_rail', 'wall_treatments', $stick($picLf), 'picture_rail');
                $installLf += $picLf;
            }
            $boxLf = ! empty($inputs['include_box_moulding']) ? max(0, (float) $inputs['box_moulding_lf']) : 0.0;
            if ($boxLf > 0) {
                $addLabor('trim_box', 'wall_treatments', 'Picture-frame moulding', number_format($boxLf, 0).' lf', $perHr($boxLf, (float) $prod['box_moulding_lf_per_hr']));
                $addMat('box_moulding', 'wall_treatments', $stick($boxLf), 'box_moulding');
                $installLf += $boxLf;
            }

            if (! empty($inputs['include_wainscoting'])) {
                $wallLf = max(0, (float) $inputs['wainscot_wall_lf']);
                $heightIn = max(1, (float) $inputs['wainscot_height_in']);
                $wainscotSf = round($wallLf * ($heightIn / 12), 2);
                $style = $inputs['wainscot_style'];
                $styleLabel = self::WAINSCOT_STYLES[$style] ?? 'Wainscoting';
                $prodKey = match ($style) {
                    'raised_panel' => 'wainscot_raised_sf_per_hr',
                    'board_and_batten' => 'wainscot_bnb_sf_per_hr',
                    'shaker' => 'wainscot_shaker_sf_per_hr',
                    default => 'wainscot_beadboard_sf_per_hr',
                };
                if ($wainscotSf > 0) {
                    $addLabor('trim_wainscot', 'wall_treatments', $styleLabel.' wainscoting', number_format($wainscotSf, 0).' sq ft', $perHr($wainscotSf, (float) ($prod[$prodKey] ?? $prod['wainscot_beadboard_sf_per_hr'])));
                    if ($style === 'raised_panel') {
                        $addMat('raised_panel', 'wall_treatments', round($wainscotSf * $waste, 2), 'raised_panel');
                    } elseif ($style === 'shaker') {
                        $addMat('shaker_panel', 'wall_treatments', round($wainscotSf * $waste, 2), 'shaker_panel');
                    } else {
                        $addMat('beadboard_sheet', 'wall_treatments', $this->packages($wainscotSf * $waste, TrimmingSettings::PANEL_SF), 'beadboard_sheet');
                    }
                    if ($style === 'board_and_batten') {
                        $battenLf = round($wallLf * 1.5 + ($wallLf * ($heightIn / 12) / 1.33), 2);
                        $addLabor('trim_battens', 'wall_treatments', 'Battens', number_format($battenLf, 0).' lf', $perHr($battenLf, (float) $prod['batten_lf_per_hr']));
                        $addMat('batten', 'wall_treatments', $stick($battenLf), 'batten');
                        $installLf += $battenLf;
                    }
                    $installLf += $wallLf;
                }
            }
        }

        $scopeLf = round($installLf, 2);

        if (! empty($inputs['include_remove_existing'])) {
            $removeLf = $this->qtyOr($inputs['remove_lf'] ?? null, $scopeLf);
            if ($removeLf > 0) {
                $addLabor('trim_remove', 'prep_finish', 'Remove existing trim', number_format($removeLf, 0).' lf', $perHr($removeLf, (float) $prod['remove_lf_per_hr']));
            }
        }

        $paintLf = 0.0;
        if (! empty($inputs['include_caulk_paint'])) {
            $paintLf = $this->qtyOr($inputs['paint_lf'] ?? null, $scopeLf);
            if ($paintLf > 0) {
                $addLabor('trim_paint', 'prep_finish', 'Caulk, fill & paint', number_format($paintLf, 0).' lf', $perHr($paintLf, (float) $prod['caulk_paint_lf_per_hr']));
                $addMat('caulk', 'prep_finish', $this->packages($paintLf, TrimmingSettings::CAULK_LF_PER_TUBE), 'caulk');
                $coats = max(1, (int) ($inputs['paint_coats'] ?? 2));
                $quarts = (int) ceil(($paintLf * $coats) / TrimmingSettings::TRIM_LF_PER_QUART);
                foreach ($this->packQuarts($quarts) as $catalogKey => $qty) {
                    $addMat('paint_'.$catalogKey, 'prep_finish', (float) $qty, $catalogKey);
                }
            }
        }

        if (! empty($inputs['include_stain'])) {
            $stainLf = $this->qtyOr($inputs['stain_lf'] ?? null, $scopeLf);
            if ($stainLf > 0) {
                $addLabor('trim_stain', 'prep_finish', 'Stain & clear coat', number_format($stainLf, 0).' lf', $perHr($stainLf, (float) ($prod['stain_lf_per_hr'] ?? 17.17)));
            }
        }

        if ($installLf > 0 || $wainscotSf > 0) {
            $addMat('fasteners', 'prep_finish', 1, 'fasteners');
        }

        $extraMaterials = $this->extraMaterialRows($inputs, $providesAll);
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
        $buildingRow = QuotationBuildingLabor::breakdownRow($laborPre, $inputs);
        $laborPre = QuotationBuildingLabor::apply($laborPre, $inputs)['total'];

        $matPre = 0.0;
        foreach ($materials as $row) {
            $matPre += (float) $row['amount'];
        }
        foreach ($extraMaterials as $row) {
            $matPre += (float) $row['amount'];
        }
        $matPre = round($matPre, 2);
        $matHst = round($matPre * TrimmingQuotation::HST_RATE, 2);
        $laborHst = round($laborPre * TrimmingQuotation::HST_RATE, 2);
        $subtotal = round($laborPre + $matPre, 2);
        $hst = round($laborHst + $matHst, 2);
        $grand = round($subtotal + $hst, 2);

        $breakdown = [];
        foreach ($labor as $row) {
            $breakdown[] = [
                'key' => $row['key'],
                'section' => 'labor',
                'trade' => $row['section'],
                'label' => $row['label'],
                'detail' => $row['detail'].' · '.$row['hours'].' hr',
                'amount' => $row['amount'],
            ];
        }
        if ($buildingRow !== null) {
            $breakdown[] = $buildingRow;
        }
        foreach (array_merge($materials, $extraMaterials) as $row) {
            if ((float) $row['amount'] <= 0 && ! empty($row['client_provided'])) {
                continue;
            }
            if ((float) $row['qty'] <= 0) {
                continue;
            }
            $breakdown[] = [
                'key' => $row['key'],
                'section' => 'materials',
                'trade' => $row['section'],
                'label' => $row['label'],
                'detail' => rtrim(rtrim(number_format((float) $row['qty'], 2, '.', ''), '0'), '.').' '.$row['unit']
                    .(! empty($row['client_provided']) ? ' · client provided' : ''),
                'amount' => $row['amount'],
            ];
        }

        return [
            'scope' => [
                'families' => $inputs['enabled_families'],
                'door_qty' => $doors,
                'window_qty' => $windows,
                'total_lf' => $scopeLf,
                'wainscot_sf' => $wainscotSf,
                'paint_lf' => round($paintLf, 2),
                'waste_percent' => (float) $settings->waste_percent,
            ],
            'labor' => $labor,
            'materials' => $materials,
            'extra_materials' => $extraMaterials,
            'eta' => $this->etaScenarios($totalHours, $hoursPerDay, $ownerRate, $helperRate),
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

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function extraMaterialRows(array $inputs, bool $providesAll): array
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
    private function resolveMaterialCatalog(TrimmingSettings $settings): array
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
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0 && isset($catalog[$id])) {
                /** @var Material $mat */
                $mat = $catalog[$id];
                $rows[$key]['catalog_material_id'] = $id;
                $rows[$key]['delivery_cad'] = CatalogMaterialDelivery::cad($mat);
            }
        }

        return $rows;
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

    /**
     * @return array<string, int>
     */
    private function packQuarts(int $quarts): array
    {
        $out = [];
        $left = max(0, $quarts);
        foreach (TrimmingSettings::trimPaintCanSizes() as $size) {
            if ($left <= 0) {
                break;
            }
            $n = intdiv($left, $size['quarts']);
            if ($n > 0) {
                $out[$size['key']] = $n;
                $left -= $n * $size['quarts'];
            }
        }
        if ($left > 0) {
            $out['trim_paint_quart'] = ($out['trim_paint_quart'] ?? 0) + $left;
        }

        return $out;
    }

    private function packages(float $needed, float $perPackage): float
    {
        if ($needed <= 0 || $perPackage <= 0) {
            return 0.0;
        }

        return (float) max(1, (int) ceil($needed / $perPackage));
    }

    private function qtyOr(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return round(max(0, $fallback), 2);
        }

        return round(max(0, (float) $value), 2);
    }
}

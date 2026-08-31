<?php

namespace App\Services;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Models\Material;
use App\Support\CatalogMaterialDelivery;

class BasementQuotationCalculator
{
    public const SECTIONS = [
        'overview' => 'Project Overview',
        'demolition' => 'Demolition & Preparation',
        'framing' => 'Framing',
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing',
        'insulation' => 'Insulation & Vapor Barrier',
        'drywall' => 'Drywall / Mud / Tape / Sand',
        'ceiling' => 'Ceiling Options',
        'flooring' => 'Flooring',
        'doors_trim' => 'Doors, Trim & Baseboards',
        'painting' => 'Painting',
        'bathroom' => 'Bathroom',
        'finishing' => 'Final Details & Cleanup',
    ];

    public const SCOPE_PRESETS = [
        'full' => 'Full basement',
        'bathroom' => 'Bathroom only',
        'wall' => 'New wall',
        'custom' => 'Custom',
    ];

    /**
     * Trade steps shown as include-checkboxes (Overview is always on).
     *
     * @return array<string, string>
     */
    public static function tradeSections(): array
    {
        $rows = self::SECTIONS;
        unset($rows['overview']);

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function presetEnabledSections(string $preset): array
    {
        $preset = self::normalizePreset($preset);

        return match ($preset) {
            'bathroom' => ['overview', 'plumbing', 'electrical', 'drywall', 'painting', 'bathroom', 'finishing'],
            'wall' => ['overview', 'framing', 'drywall', 'painting', 'finishing'],
            'custom' => ['overview'],
            default => array_keys(self::SECTIONS),
        };
    }

    public static function normalizePreset(mixed $preset): string
    {
        $value = strtolower(trim((string) $preset));

        return in_array($preset = $value, ['full', 'bathroom', 'wall', 'custom'], true) ? $preset : 'full';
    }

    /**
     * Form defaults when the user picks a scope preset. Empty array means
     * do not overwrite electrical fields (custom).
     *
     * @return array<string, int|string>
     */
    public static function electricalDefaultsForPreset(string $preset): array
    {
        $preset = self::normalizePreset($preset);
        if ($preset === 'custom') {
            return [];
        }
        if ($preset === 'bathroom') {
            return [
                'outlet_qty' => 3,
                'switch_qty' => 2,
                'lighting_type' => 'both',
                'light_qty' => 0,
                'fixture_qty' => 1,
            ];
        }

        return [
            'outlet_qty' => 0,
            'switch_qty' => 0,
            'lighting_type' => 'spot',
            'light_qty' => 0,
            'fixture_qty' => 0,
        ];
    }

    public static function normalizeAreaMode(mixed $mode): string
    {
        return strtolower(trim((string) $mode)) === 'dimensions' ? 'dimensions' : 'sqft';
    }

    /**
     * Square-footage fields that can be entered as length × width.
     *
     * @return list<string>
     */
    public static function qtyLwKeys(): array
    {
        return [
            'demo_flooring_sf',
            'demo_drywall_sf',
            'insulation_wall_sf',
            'insulation_ceiling_sf',
            'underslab_sf',
            'drywall_wall_sf',
            'drywall_ceiling_sf',
            'ceiling_sf',
            'flooring_sf',
            'paint_wall_sf',
            'paint_ceiling_sf',
            'bath_tile_sf',
        ];
    }

    /**
     * When the user enters length × width, overwrite the matching sq ft field.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public static function applyAreaEntry(array $inputs): array
    {
        $mode = self::normalizeAreaMode($inputs['area_entry_mode'] ?? 'sqft');
        $inputs['area_entry_mode'] = $mode;
        if ($mode === 'dimensions') {
            $len = round(max(0, (float) ($inputs['area_length_ft'] ?? 0)), 4);
            $wid = round(max(0, (float) ($inputs['area_width_ft'] ?? 0)), 4);
            $inputs['area_sqft'] = ($len > 0 && $wid > 0) ? round($len * $wid, 2) : 0.0;
        }

        $raw = is_array($inputs['qty_dims'] ?? null) ? $inputs['qty_dims'] : [];
        $clean = [];
        foreach (self::qtyLwKeys() as $field) {
            $row = is_array($raw[$field] ?? null) ? $raw[$field] : [];
            $rowMode = self::normalizeAreaMode($row['mode'] ?? 'sqft');
            $lenRaw = $row['length_ft'] ?? null;
            $widRaw = $row['width_ft'] ?? null;
            $len = ($lenRaw === null || $lenRaw === '') ? null : round(max(0, (float) $lenRaw), 4);
            $wid = ($widRaw === null || $widRaw === '') ? null : round(max(0, (float) $widRaw), 4);
            $clean[$field] = [
                'mode' => $rowMode,
                'length_ft' => $len,
                'width_ft' => $wid,
            ];
            if ($rowMode === 'dimensions' && $len !== null && $wid !== null && $len > 0 && $wid > 0) {
                $inputs[$field] = round($len * $wid, 2);
            }
        }
        $inputs['qty_dims'] = $clean;

        return self::applyBathTileAreas($inputs);
    }

    /**
     * @return list<array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null}>
     */
    public static function defaultBathTileAreas(): array
    {
        return [
            self::blankBathTileArea('Floor'),
            self::blankBathTileArea('Shower'),
        ];
    }

    /**
     * @return array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null}
     */
    public static function blankBathTileArea(string $label = ''): array
    {
        return [
            'label' => $label,
            'sqft' => 0.0,
            'mode' => 'sqft',
            'length_ft' => null,
            'width_ft' => null,
        ];
    }

    /**
     * Named tile zones (floor, shower, …) summed into bath_tile_sf.
     * A saved quote with only bath_tile_sf still works.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public static function applyBathTileAreas(array $inputs): array
    {
        $raw = $inputs['bath_tile_areas'] ?? null;
        $areas = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $normalized = self::normalizeBathTileArea($row);
                if ($normalized !== null) {
                    $areas[] = $normalized;
                }
            }
        }

        if ($areas === []) {
            $legacy = round(max(0, (float) ($inputs['bath_tile_sf'] ?? 0)), 2);
            $dims = is_array($inputs['qty_dims']['bath_tile_sf'] ?? null) ? $inputs['qty_dims']['bath_tile_sf'] : [];
            $inputs['bath_tile_areas'] = $legacy > 0
                ? [[
                    'label' => 'Tile work',
                    'sqft' => $legacy,
                    'mode' => self::normalizeAreaMode($dims['mode'] ?? 'sqft'),
                    'length_ft' => $dims['length_ft'] ?? null,
                    'width_ft' => $dims['width_ft'] ?? null,
                ]]
                : [];
            $inputs['bath_tile_sf'] = $legacy;

            return $inputs;
        }

        $total = 0.0;
        foreach ($areas as $row) {
            $total += (float) $row['sqft'];
        }
        $inputs['bath_tile_areas'] = $areas;
        $inputs['bath_tile_sf'] = round($total, 2);

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null}|null
     */
    public static function normalizeBathTileArea(array $row): ?array
    {
        $label = trim((string) ($row['label'] ?? ''));
        $mode = self::normalizeAreaMode($row['mode'] ?? 'sqft');
        $lenRaw = $row['length_ft'] ?? null;
        $widRaw = $row['width_ft'] ?? null;
        $len = ($lenRaw === null || $lenRaw === '') ? null : round(max(0, (float) $lenRaw), 4);
        $wid = ($widRaw === null || $widRaw === '') ? null : round(max(0, (float) $widRaw), 4);
        $sqft = round(max(0, (float) ($row['sqft'] ?? 0)), 2);
        if ($mode === 'dimensions' && $len !== null && $wid !== null && $len > 0 && $wid > 0) {
            $sqft = round($len * $wid, 2);
        }
        if ($label === '' && $sqft <= 0) {
            return null;
        }

        return [
            'label' => $label,
            'sqft' => $sqft,
            'mode' => $mode,
            'length_ft' => $len,
            'width_ft' => $wid,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     */
    public static function bathTileLaborDetail(array $areas, float $total): string
    {
        $bits = [];
        foreach ($areas as $row) {
            $sf = (float) ($row['sqft'] ?? 0);
            if ($sf <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $bits[] = ($label !== '' ? $label.' ' : '').number_format($sf, 0).' sf';
        }

        return $bits !== [] ? implode(' + ', $bits) : number_format($total, 0).' sq ft';
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public static function enabledSections(array $inputs): array
    {
        $preset = self::normalizePreset($inputs['scope_preset'] ?? null);
        $raw = $inputs['enabled_sections'] ?? null;
        $allowed = array_keys(self::SECTIONS);
        if ($raw === null) {
            $keys = self::presetEnabledSections($preset);
        } else {
            $keys = is_array($raw) ? array_values(array_intersect($allowed, array_map('strval', $raw))) : [];
            if ($keys === [] && $preset !== 'custom') {
                $keys = self::presetEnabledSections($preset);
            }
        }
        if (! in_array('overview', $keys, true)) {
            array_unshift($keys, 'overview');
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function sectionEnabled(array $inputs, string $section): bool
    {
        return in_array($section, self::enabledSections($inputs), true);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function finishLabel(array $inputs): string
    {
        $preset = self::normalizePreset($inputs['scope_preset'] ?? null);
        $bath = ! empty($inputs['include_bathroom']) && self::sectionEnabled($inputs, 'bathroom');

        return match ($preset) {
            'bathroom' => 'Bathroom only',
            'wall' => 'New wall',
            'custom' => 'Custom',
            default => $bath ? 'Full finish with bathroom' : 'Full finish',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'area_sqft' => 0,
            'area_entry_mode' => 'sqft',
            'area_length_ft' => null,
            'area_width_ft' => null,
            'qty_dims' => [],
            'ceiling_height_ft' => 8,
            'room_count' => 1,
            'existing_notes' => '',
            'scope_preset' => 'full',
            'enabled_sections' => null,
            'include_demolition' => false,
            'demo_flooring_sf' => null,
            'demo_drywall_sf' => 0,
            'demo_bin_qty' => 1,
            'partition_lf' => 0,
            'bulkhead_lf' => 0,
            'header_qty' => 0,
            'framing_door_openings' => 0,
            'outlet_qty' => 0,
            'switch_qty' => 0,
            'light_qty' => 0,
            'lighting_type' => 'spot',
            'panel_upgrade' => false,
            'detector_qty' => 2,
            'wiring_runs' => 0,
            'include_plumbing' => false,
            'bath_rough_in' => false,
            'sink_laundry' => false,
            'wet_bar' => false,
            'floor_drain' => false,
            'wh_reloc' => false,
            'insulate_walls' => true,
            'insulation_wall_sf' => null,
            'insulate_ceiling' => false,
            'insulation_ceiling_sf' => null,
            'underslab' => false,
            'underslab_sf' => null,
            'drywall_wall_sf' => null,
            'drywall_ceiling_sf' => null,
            'drywall_corners' => 0,
            'drywall_openings' => 0,
            'finish_level' => 4,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => null,
            'subfloor_prep' => false,
            'underlayment' => true,
            'flooring_type' => 'lvp',
            'flooring_sf' => null,
            'transition_qty' => 0,
            'door_qty' => 0,
            'baseboard_lf' => null,
            'casing_openings' => 0,
            'window_trim_qty' => 0,
            'paint_wall_sf' => null,
            'paint_ceiling_sf' => null,
            'paint_trim_lf' => null,
            'paint_coats' => 2,
            'paint_colors' => 1,
            'include_bathroom' => false,
            'bath_type' => 'full',
            'include_vanity' => true,
            'include_toilet' => true,
            'include_shower' => true,
            'include_tub' => false,
            'bath_tile_sf' => 0,
            'include_exhaust_fan' => true,
            'fixture_qty' => 0,
            'hardware_sets' => 0,
            'include_cleaning' => true,
            'include_touchups' => true,
            'client_provides_all' => false,
            'client_provides_sections' => [],
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'extra_materials' => [],
            'labor_override' => [],
            'materials_override' => [],
        ];
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

        $hints = [];
        $inputs = self::applyAreaEntry($inputs);
        $preset = self::normalizePreset($inputs['scope_preset'] ?? null);
        $mode = self::normalizeAreaMode($inputs['area_entry_mode'] ?? 'sqft');
        $area = max(0, (float) ($inputs['area_sqft'] ?? 0));
        $partition = max(0, (float) ($inputs['partition_lf'] ?? 0));
        if ($preset === 'wall') {
            if ($partition <= 0 && $area <= 0) {
                $hints[] = $mode === 'dimensions'
                    ? 'Enter the new wall length in linear feet (Framing), or length and width for cleaning.'
                    : 'Enter the new wall length in linear feet (Framing), or an area for cleaning.';
            }
        } elseif ($area <= 0) {
            if ($mode === 'dimensions') {
                $hints[] = 'Enter length and width in feet to calculate the work area.';
            } else {
                $hints[] = $preset === 'bathroom'
                    ? 'Enter the bathroom / work area in square feet.'
                    : 'Enter the total basement area in square feet.';
            }
        }

        return $hints;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs, ?BasementSettings $settings = null): array
    {
        $settings = $settings ?? BasementSettings::current();
        $prod = $settings->resolvedProductivity();
        $matCatalog = $this->resolveMaterialCatalog($settings);
        $inputs = array_merge(self::defaultInputs(), $inputs);
        $inputs['scope_preset'] = self::normalizePreset($inputs['scope_preset'] ?? null);
        $inputs = self::applyAreaEntry($inputs);

        $area = round(max(0, (float) $inputs['area_sqft']), 2);
        $height = round(max(0, (float) $inputs['ceiling_height_ft']), 2) ?: 8;
        $partitionLf = max(0, (float) $inputs['partition_lf']);
        if ($area <= 0 && $partitionLf <= 0) {
            return $this->emptyCalculation($inputs, $settings, $height);
        }
        $perimeter = $area > 0 ? round(4 * sqrt($area), 2) : 0;
        $suggestedWallSf = round($perimeter * $height, 2);
        $wallBothSidesSf = round($partitionLf * $height * 2, 2);
        $dwWallFallback = $inputs['scope_preset'] === 'wall' ? $wallBothSidesSf : $suggestedWallSf;
        $paintColors = ((int) ($inputs['paint_colors'] ?? 1) === 2) ? 2 : 1;
        $on = function (string $section) use ($inputs): bool {
            return self::sectionEnabled($inputs, $section);
        };
        $ceilingType = (string) ($inputs['ceiling_type'] ?? 'drywall');
        $flType = (string) ($inputs['flooring_type'] ?? 'lvp');
        $dwWall = 0.0;
        $dwCeil = 0.0;
        $baseLf = 0.0;
        $casing = 0;
        $windows = 0;

        $labor = [];
        $materials = [];

        $providesAll = ! empty($inputs['client_provides_all']);
        $provideSections = is_array($inputs['client_provides_sections'] ?? null)
            ? array_values(array_filter(array_map('strval', $inputs['client_provides_sections'])))
            : [];

        $chargeMats = function (string $section) use ($providesAll, $provideSections): bool {
            if ($providesAll) {
                return false;
            }

            return ! in_array($section, $provideSections, true);
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
        ) use (&$materials, $inputs, $matCatalog, $chargeMats): void {
            $qty = round(max(0, $qty), 2);
            if ($qty <= 0) {
                return;
            }
            $def = $catalogKey && isset($matCatalog[$catalogKey]) ? $matCatalog[$catalogKey] : [
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
            if ($key === BasementSettings::MATERIAL_PLATE_2X4) {
                $studPrice = (float) ($matCatalog[BasementSettings::MATERIAL_STUD_2X4]['unit_price'] ?? 0);
                $studOverride = is_array($inputs['materials_override'][BasementSettings::MATERIAL_STUD_2X4] ?? null)
                    ? $inputs['materials_override'][BasementSettings::MATERIAL_STUD_2X4]
                    : [];
                if (isset($studOverride['unit_price']) && is_numeric($studOverride['unit_price'])) {
                    $studPrice = round(max(0, (float) $studOverride['unit_price']), 4);
                }
                $unitPrice = $studPrice > 0
                    ? round($studPrice / BasementSettings::PLATE_FEET_PER_BOARD, 4)
                    : 0.0;
            }
            $clientProvided = ! $chargeMats($section);
            $amount = $clientProvided ? 0.0 : round($qty * $unitPrice, 2);
            $derivedPlate = $key === BasementSettings::MATERIAL_PLATE_2X4;
            $materials[] = [
                'key' => $key,
                'section' => $section,
                'label' => ($labelOverride !== null && $labelOverride !== '') ? $labelOverride : $def['name'],
                'qty' => $qty,
                'unit' => $derivedPlate ? 'lf' : $def['unit'],
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'catalog_material_id' => $derivedPlate ? null : $def['catalog_material_id'],
                'client_provided' => $clientProvided,
                'delivery_cad' => ($clientProvided || $derivedPlate)
                    ? 0.0
                    : round(max(0, (float) ($def['delivery_cad'] ?? 0)), 2),
            ];
        };

        $perHr = function (float $qty, float $perHr, float $fallbackHours = 0): float {
            if ($qty <= 0) {
                return 0;
            }
            if ($perHr > 0) {
                return $qty / $perHr;
            }

            return $fallbackHours;
        };

        // --- Demolition ---
        if ($on('demolition') && ! empty($inputs['include_demolition'])) {
            $demoFloor = $this->qtyOr($inputs['demo_flooring_sf'], $area);
            $demoDrywall = $this->qtyOr($inputs['demo_drywall_sf'], 0);
            $bins = max(0, (int) $inputs['demo_bin_qty']);
            $addLabor('demo_floor', 'demolition', 'Remove existing flooring', number_format($demoFloor, 0).' sq ft', $perHr($demoFloor, (float) $prod['demo_floor_sf_per_hr']));
            $addLabor('demo_drywall', 'demolition', 'Remove drywall / ceiling', number_format($demoDrywall, 0).' sq ft', $perHr($demoDrywall, (float) $prod['demo_drywall_sf_per_hr']));
            $addLabor('demo_bins', 'demolition', 'Debris bins / haul-away', $bins.' bin(s)', $bins * (float) $prod['bin_hours_each']);
            $addMat('dump_bin', 'demolition', $bins, 'dump_bin');
        }

        // --- Framing ---
        $bulkheadLf = max(0, (float) $inputs['bulkhead_lf']);
        $headers = max(0, (int) $inputs['header_qty']);
        $frameDoors = max(0, (int) $inputs['framing_door_openings']);
        if ($on('framing')) {
            $addLabor('framing_walls', 'framing', 'Partition wall framing', number_format($partitionLf, 0).' lf × '.$height.' ft', $perHr($partitionLf, (float) $prod['framing_lf_per_hr']));
            $addLabor('framing_bulkheads', 'framing', 'Bulkheads / duct boxing', number_format($bulkheadLf, 0).' lf', $perHr($bulkheadLf, (float) $prod['bulkhead_lf_per_hr']));
            $addLabor('framing_headers', 'framing', 'Headers / beams', $headers.' opening(s)', $headers * (float) $prod['header_hours_each']);
            $addLabor('framing_doors', 'framing', 'Door openings', $frameDoors.' opening(s)', $frameDoors * (float) $prod['door_opening_hours_each']);
            if ($partitionLf > 0) {
                $studs = max(1, (int) ceil(($partitionLf * 12 / 16) + 2));
                $plates = round($partitionLf * 3, 0);
                $addMat('stud_2x4', 'framing', $studs, 'stud_2x4');
                $addMat('plate_2x4', 'framing', $plates, 'plate_2x4');
            }
        }

        // --- Electrical ---
        if ($on('electrical')) {
        $outlets = max(0, (int) $inputs['outlet_qty']);
        $switches = max(0, (int) $inputs['switch_qty']);
        [, $spots, $fixtures] = $this->lightingCounts($inputs);
        $lightingPoints = $spots + $fixtures;
        $detectors = max(0, (int) $inputs['detector_qty']);
        $runs = max(0, (int) $inputs['wiring_runs']);
        if ($runs <= 0) {
            $runs = $outlets + $switches + $lightingPoints;
        }
        $addLabor('elec_outlets', 'electrical', 'New outlets', $outlets.' location(s)', $outlets * (float) $prod['outlet_hours_each']);
        $addLabor('elec_switches', 'electrical', 'Switches', $switches.' location(s)', $switches * (float) $prod['switch_hours_each']);
        $lightDetail = $this->lightingLaborDetail($spots, $fixtures);
        $addLabor('elec_lights', 'electrical', 'Lighting wiring', $lightDetail, $lightingPoints * (float) $prod['light_hours_each']);
        if ($fixtures > 0) {
            $addLabor('elec_fixture_hang', 'electrical', 'Hang light fixtures', $fixtures.' fixture(s)', $fixtures * (float) $prod['fixture_hours_each']);
        }
        $addLabor('elec_detectors', 'electrical', 'Smoke / CO detectors', $detectors.' unit(s)', $detectors * (float) $prod['detector_hours_each']);
        $addLabor('elec_runs', 'electrical', 'Wiring runs', $runs.' run(s)', $runs * (float) $prod['wiring_run_hours_each']);
        if (! empty($inputs['panel_upgrade'])) {
            $addLabor('elec_panel', 'electrical', 'Panel upgrade', 'Allowance', (float) $prod['panel_upgrade_hours']);
            $addMat('panel_upgrade', 'electrical', 1, 'panel_upgrade');
        }
        $addMat('outlet_box', 'electrical', $outlets, 'outlet_box');
        $addMat('switch_device', 'electrical', $switches, 'switch_device');
        $addMat('pot_light', 'electrical', $spots, 'pot_light');
        $addMat('light_fixture', 'electrical', $fixtures, 'light_fixture');
        $addMat('smoke_co', 'electrical', $detectors, 'smoke_co');
        $addMat('nm_cable', 'electrical', $this->packages($runs * 25, BasementSettings::NMD90_FEET_PER_BOX), 'nm_cable');
        }

        // --- Plumbing ---
        if ($on('plumbing') && ! empty($inputs['include_plumbing'])) {
            if (! empty($inputs['bath_rough_in'])) {
                $addLabor('plumb_bath', 'plumbing', 'Bathroom rough-in', 'Drain / vent / supply', (float) $prod['plumbing_bath_rough_hours']);
                $addMat('bath_rough_in', 'plumbing', 1, 'bath_rough_in');
            }
            if (! empty($inputs['sink_laundry'])) {
                $addLabor('plumb_sink', 'plumbing', 'Sink / laundry hookup', 'Supply + drain', (float) $prod['sink_hours'] + (float) $prod['laundry_hours'] / 2);
                $addMat('sink_kit', 'plumbing', 1, 'sink_kit');
            }
            if (! empty($inputs['wet_bar'])) {
                $addLabor('plumb_bar', 'plumbing', 'Wet bar rough-in', 'Supply + drain', (float) $prod['wet_bar_hours']);
            }
            if (! empty($inputs['floor_drain'])) {
                $addLabor('plumb_drain', 'plumbing', 'Floor drain', '1 location', (float) $prod['floor_drain_hours']);
                $addMat('floor_drain', 'plumbing', 1, 'floor_drain');
            }
            if (! empty($inputs['wh_reloc'])) {
                $addLabor('plumb_wh', 'plumbing', 'Water heater relocation', 'Disconnect / relocate / reconnect', (float) $prod['wh_reloc_hours']);
            }
        }

        // --- Insulation ---
        if ($on('insulation')) {
            $insWall = $this->qtyOr($inputs['insulation_wall_sf'], ! empty($inputs['insulate_walls']) ? $suggestedWallSf : 0);
            $insCeil = $this->qtyOr($inputs['insulation_ceiling_sf'], ! empty($inputs['insulate_ceiling']) ? $area : 0);
            $insSlab = $this->qtyOr($inputs['underslab_sf'], ! empty($inputs['underslab']) ? $area : 0);
            if (! empty($inputs['insulate_walls'])) {
                $addLabor('ins_walls', 'insulation', 'Wall insulation + vapor barrier', number_format($insWall, 0).' sq ft', $perHr($insWall, (float) $prod['insulation_wall_sf_per_hr']));
                $addMat('insulation_batt', 'insulation', $this->packages($insWall * 1.05, BasementSettings::BATT_SF_PER_BAG), 'insulation_batt');
                $addMat('vapor_barrier', 'insulation', $this->packages($insWall * 1.1, BasementSettings::VAPOR_SF_PER_ROLL), 'vapor_barrier');
            }
            if (! empty($inputs['insulate_ceiling'])) {
                $addLabor('ins_ceiling', 'insulation', 'Ceiling insulation', number_format($insCeil, 0).' sq ft', $perHr($insCeil, (float) $prod['insulation_ceiling_sf_per_hr']));
                $addMat('insulation_batt_ceil', 'insulation', $this->packages($insCeil * 1.05, BasementSettings::BATT_SF_PER_BAG), 'insulation_batt');
            }
            if (! empty($inputs['underslab'])) {
                $addLabor('ins_slab', 'insulation', 'Under-slab insulation', number_format($insSlab, 0).' sq ft', $perHr($insSlab, (float) $prod['underslab_sf_per_hr']));
                $addMat('underslab_insulation', 'insulation', $this->packages($insSlab, BasementSettings::PANEL_SF), 'underslab_insulation');
            }
        }

        // --- Drywall ---
        if ($on('drywall')) {
            $dwWall = $this->qtyOr($inputs['drywall_wall_sf'], $dwWallFallback);
            $dwCeil = $this->qtyOr(
                $inputs['drywall_ceiling_sf'],
                $on('ceiling') && in_array($ceilingType, ['drywall', 'paint'], true) ? $area : 0
            );
            $dwSf = $dwWall + $dwCeil;
            $level = max(1, min(4, (int) ($inputs['finish_level'] ?? 4)));
            $finishKey = 'drywall_finish_l'.$level.'_sf_per_hr';
            $finishHours = $perHr($dwSf, (float) ($prod[$finishKey] ?? $prod['drywall_finish_l4_sf_per_hr']));
            $finishDetail = number_format($dwSf, 0).' sq ft';
            if ($paintColors === 2) {
                $finishHours *= BasementSettings::TWO_COLOR_DRYWALL_FINISH_FACTOR;
                $finishDetail .= ', two-color';
            }
            $addLabor('dw_hang', 'drywall', 'Hang drywall', number_format($dwSf, 0).' sq ft', $perHr($dwSf, (float) $prod['drywall_hang_sf_per_hr']));
            $addLabor('dw_finish', 'drywall', 'Mud / tape / sand (Level '.$level.')', $finishDetail, $finishHours);
            $sheets = $dwSf > 0 ? max(1, (int) ceil($dwSf / BasementSettings::PANEL_SF * 1.1)) : 0;
            $addMat('drywall_sheet', 'drywall', $sheets, 'drywall_sheet');
            $addMat('joint_compound', 'drywall', $this->packages($dwSf, BasementSettings::JOINT_COMPOUND_SF_PER_PAIL), 'joint_compound');
            $addMat('drywall_tape', 'drywall', $this->packages($dwSf, BasementSettings::DRYWALL_TAPE_SF_PER_ROLL), 'drywall_tape');
            $corners = max(0, (int) $inputs['drywall_corners']);
            $openings = max(0, (int) $inputs['drywall_openings']);
            $addMat('corner_bead', 'drywall', $corners, 'corner_bead');
            $addLabor('dw_openings', 'drywall', 'Cut & finish openings', $openings.' opening(s)', $openings * (float) ($prod['drywall_opening_hours_each'] ?? 0.4));
        }

        // --- Ceiling ---
        if ($on('ceiling')) {
            $ceilSf = $this->qtyOr($inputs['ceiling_sf'], $area);
            if ($ceilingType === 'drop') {
                $addLabor('ceil_drop', 'ceiling', 'Drop ceiling', number_format($ceilSf, 0).' sq ft', $perHr($ceilSf, (float) $prod['ceiling_drop_sf_per_hr']));
                $addMat('drop_ceiling', 'ceiling', $ceilSf * 1.08, 'drop_ceiling');
            }
        }

        // --- Flooring ---
        if ($on('flooring')) {
            $flSf = $this->qtyOr($inputs['flooring_sf'], $area);
            $flCatalog = match ($flType) {
                'laminate' => 'laminate',
                'carpet' => 'carpet',
                'tile' => 'tile',
                'epoxy' => 'epoxy',
                default => 'lvp',
            };
            if ($flType !== 'none' && $flSf > 0) {
                if (! empty($inputs['subfloor_prep'])) {
                    $addLabor('fl_subfloor', 'flooring', 'Subfloor prep / leveling', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['subfloor_sf_per_hr']));
                    $addMat('subfloor_panel', 'flooring', $this->packages($flSf, BasementSettings::PANEL_SF), 'subfloor_panel');
                }
                if (! empty($inputs['underlayment']) && $flType !== 'epoxy' && $flType !== 'tile') {
                    $addLabor('fl_underlay', 'flooring', 'Underlayment', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['underlayment_sf_per_hr']));
                    $addMat('underlayment', 'flooring', $this->packages($flSf * 1.08, BasementSettings::UNDERLAYMENT_SF_PER_ROLL), 'underlayment');
                }
                $addLabor('fl_install', 'flooring', $this->flooringLabel($flType).' install', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['flooring_sf_per_hr']));
                $addMat('flooring_finish', 'flooring', $flSf * 1.08, $flCatalog);
                $transitions = max(0, (int) $inputs['transition_qty']);
                $addLabor('fl_transitions', 'flooring', 'Transitions', $transitions.' piece(s)', $transitions * (float) $prod['transition_hours_each']);
                $addMat('transition', 'flooring', $transitions, 'transition');
            }
        }

        // --- Doors / trim ---
        if ($on('doors_trim')) {
            $doors = max(0, (int) $inputs['door_qty']);
            $baseLf = $this->qtyOr($inputs['baseboard_lf'], $perimeter);
            $casing = max(0, (int) $inputs['casing_openings']);
            if ($casing <= 0) {
                $casing = $doors;
            }
            $windows = max(0, (int) $inputs['window_trim_qty']);
            $addLabor('trim_doors', 'doors_trim', 'Interior doors', $doors.' door(s)', $doors * (float) $prod['door_install_hours_each']);
            $addLabor('trim_base', 'doors_trim', 'Baseboards', number_format($baseLf, 0).' lf', $perHr($baseLf, (float) $prod['baseboard_lf_per_hr']));
            $addLabor('trim_casing', 'doors_trim', 'Door casing', $casing.' opening(s)', $casing * (float) $prod['casing_opening_hours']);
            $addLabor('trim_windows', 'doors_trim', 'Window trim', $windows.' window(s)', $windows * (float) $prod['window_trim_hours_each']);
            $addMat('interior_door', 'doors_trim', $doors, 'interior_door');
            $addMat('baseboard', 'doors_trim', $this->packages($baseLf * 1.08, BasementSettings::BASEBOARD_FEET_PER_STICK), 'baseboard');
            $addMat('casing', 'doors_trim', $casing, 'casing');
            $addMat('window_trim', 'doors_trim', $windows, 'window_trim');
            $addMat('door_hardware', 'doors_trim', $doors, 'door_hardware');
        }

        // --- Painting ---
        if ($on('painting')) {
            $coats = max(1, (int) ($inputs['paint_coats'] ?? 2));
            $pWall = $this->qtyOr($inputs['paint_wall_sf'], $dwWall ?: $dwWallFallback);
            $pCeil = $this->qtyOr(
                $inputs['paint_ceiling_sf'],
                (! $on('ceiling') || in_array($ceilingType, ['drop', 'none'], true)) ? 0 : ($dwCeil ?: $area)
            );
            $pTrim = $this->qtyOr($inputs['paint_trim_lf'], $baseLf + ($casing * 17) + ($windows * 16));
            $colorFactor = $paintColors === 2 ? BasementSettings::TWO_COLOR_PAINT_FACTOR : 1.0;
            $addLabor(
                'paint_walls',
                'painting',
                'Paint walls ('.$coats.' coat'.($coats === 1 ? '' : 's').($paintColors === 2 ? ', color 1' : '').')',
                number_format($pWall, 0).' sq ft',
                $perHr($pWall * $coats, (float) $prod['paint_wall_sf_per_hr']) * $colorFactor
            );
            $addLabor(
                'paint_ceiling',
                'painting',
                'Paint ceiling'.($paintColors === 2 ? ' (color 2)' : ''),
                number_format($pCeil, 0).' sq ft',
                $perHr($pCeil * $coats, (float) $prod['paint_ceiling_sf_per_hr']) * $colorFactor
            );
            $addLabor('paint_trim', 'painting', 'Paint trim', number_format($pTrim, 0).' lf', $perHr($pTrim, (float) $prod['paint_trim_lf_per_hr']));
            $colorBreakLf = $inputs['scope_preset'] === 'wall' ? $partitionLf : $perimeter;
            if ($paintColors === 2 && $colorBreakLf > 0) {
                $addLabor(
                    'paint_two_color',
                    'painting',
                    'Tape / cut-in (two colors)',
                    number_format($colorBreakLf, 0).' lf color break',
                    $perHr($colorBreakLf, (float) $prod['paint_two_color_lf_per_hr'])
                );
            }

            $addPaintPack = function (string $prefix, float $sf, int $surfaceCoats, array $sizes, string $note = '') use ($addMat, $matCatalog): void {
                $packed = $this->packQuarts($this->quartsFromGallons($this->paintGallonsNeeded($sf, $surfaceCoats)), $sizes);
                foreach ($packed as $catalogKey => $qty) {
                    $name = (string) ($matCatalog[$catalogKey]['name'] ?? $catalogKey);
                    if ($note !== '') {
                        $name .= ' — '.$note;
                    }
                    $addMat($prefix.'_'.$catalogKey, 'painting', (float) $qty, $catalogKey, $name);
                }
            };

            $addPaintPack('primer', $pWall + $pCeil, 1, BasementSettings::primerCanSizes());
            if ($paintColors === 2) {
                $addPaintPack('paint_walls', $pWall, $coats, BasementSettings::paintCanSizes(), 'walls');
                $addPaintPack('paint_ceil', $pCeil, $coats, BasementSettings::paintCanSizes(), 'ceiling');
            } else {
                $addPaintPack('paint', $pWall + $pCeil, $coats, BasementSettings::paintCanSizes());
            }
            $trimQuarts = $this->trimQuartsNeeded($pTrim, $coats);
            foreach ($this->packQuarts($trimQuarts, BasementSettings::trimPaintCanSizes()) as $catalogKey => $qty) {
                $addMat('trim_'.$catalogKey, 'painting', (float) $qty, $catalogKey);
            }
        }

        // --- Bathroom ---
        if ($on('bathroom') && ! empty($inputs['include_bathroom'])) {
            $half = ($inputs['bath_type'] ?? 'full') === 'half';
            if (! empty($inputs['include_vanity'])) {
                $addLabor('bath_vanity', 'bathroom', 'Vanity', 'Install', (float) $prod['vanity_hours']);
                $addMat('vanity', 'bathroom', 1, 'vanity');
            }
            if (! empty($inputs['include_toilet'])) {
                $addLabor('bath_toilet', 'bathroom', 'Toilet', 'Install', (float) $prod['toilet_hours']);
                $addMat('toilet', 'bathroom', 1, 'toilet');
            }
            if (! $half && ! empty($inputs['include_shower'])) {
                $addLabor('bath_shower', 'bathroom', 'Shower', 'Install', (float) $prod['shower_hours']);
                $addMat('shower_kit', 'bathroom', 1, 'shower_kit');
            }
            if (! $half && ! empty($inputs['include_tub'])) {
                $addLabor('bath_tub', 'bathroom', 'Tub', 'Install', (float) $prod['tub_hours']);
                $addMat('tub_kit', 'bathroom', 1, 'tub_kit');
            }
            if (! empty($inputs['include_exhaust_fan'])) {
                $addLabor('bath_fan', 'bathroom', 'Exhaust fan', 'Install + duct', (float) $prod['exhaust_fan_hours']);
                $addMat('exhaust_fan', 'bathroom', 1, 'exhaust_fan');
            }
            $tileSf = max(0, (float) $inputs['bath_tile_sf']);
            if ($tileSf > 0) {
                $tileAreas = is_array($inputs['bath_tile_areas'] ?? null) ? $inputs['bath_tile_areas'] : [];
                $addLabor(
                    'bath_tile',
                    'bathroom',
                    'Bathroom tile',
                    self::bathTileLaborDetail($tileAreas, $tileSf),
                    $perHr($tileSf, (float) $prod['tile_sf_per_hr'])
                );
                $addMat('bath_tile', 'bathroom', $tileSf * 1.1, 'tile');
                $addMat('thinset_grout', 'bathroom', $this->packages($tileSf * 1.1, BasementSettings::THINSET_SF_PER_BAG), 'thinset_grout');
            }
        }

        // --- Finishing ---
        if ($on('finishing')) {
            $hw = max(0, (int) $inputs['hardware_sets']);
            if ($hw > 0) {
                $addLabor('fin_hw', 'finishing', 'Hardware', $hw.' set(s)', (float) $prod['hardware_hours']);
            }
            if (! empty($inputs['include_cleaning']) && $area > 0) {
                $addLabor('fin_clean', 'finishing', 'Final cleaning', number_format($area, 0).' sq ft', ($area / 100) * (float) $prod['cleaning_hours_per_100sf']);
                $addMat('cleaning', 'finishing', 1, 'cleaning');
            }
            if (! empty($inputs['include_touchups'])) {
                $addLabor('fin_touch', 'finishing', 'Touch-ups', 'Walkthrough punch list', (float) $prod['touchup_hours']);
            }
        }

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
            $amount = $clientProvided ? 0.0 : round($qty * $unitPrice, 2);
            $extraMaterials[] = [
                'key' => 'extra_'.$i,
                'section' => 'extra',
                'label' => $name,
                'qty' => $qty,
                'unit' => trim((string) ($row['unit'] ?? 'each')) ?: 'each',
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'catalog_material_id' => $catalogId,
                'client_provided' => $clientProvided,
                'delivery_cad' => $delivery,
            ];
        }

        $materials = $this->appendDeliveryLines($materials);
        $extraMaterials = $this->appendDeliveryLines($extraMaterials);

        $ownerRate = max(0, (float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate));
        $helperRate = max(0, (float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate));
        $hoursPerDay = max(0.5, (float) $settings->hours_per_day);

        $totalHours = 0.0;
        foreach ($labor as &$row) {
            $totalHours += $row['hours'];
            $row['amount'] = round($row['hours'] * $ownerRate, 2);
        }
        unset($row);
        $totalHours = round($totalHours, 2);
        $laborPre = round($totalHours * $ownerRate, 2);

        $matPre = 0.0;
        foreach ($materials as $row) {
            $matPre += (float) $row['amount'];
        }
        foreach ($extraMaterials as $row) {
            $matPre += (float) $row['amount'];
        }
        $matPre = round($matPre, 2);
        $matHst = round($matPre * BasementQuotation::HST_RATE, 2);
        $laborHst = round($laborPre * BasementQuotation::HST_RATE, 2);
        $subtotal = round($laborPre + $matPre, 2);
        $hst = round($laborHst + $matHst, 2);
        $grand = round($subtotal + $hst, 2);

        $eta = $this->etaScenarios($totalHours, $hoursPerDay, $ownerRate, $helperRate);

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
                    .($row['client_provided'] ? ' · client provided' : ''),
                'amount' => $row['amount'],
            ];
        }

        $finishLabel = self::finishLabel($inputs);

        return [
            'scope' => [
                'area_sqft' => $area,
                'area_entry_mode' => self::normalizeAreaMode($inputs['area_entry_mode'] ?? 'sqft'),
                'area_length_ft' => round(max(0, (float) ($inputs['area_length_ft'] ?? 0)), 4),
                'area_width_ft' => round(max(0, (float) ($inputs['area_width_ft'] ?? 0)), 4),
                'ceiling_height_ft' => $height,
                'room_count' => max(0, (int) $inputs['room_count']),
                'suggested_wall_sf' => $suggestedWallSf,
                'suggested_perimeter_lf' => $perimeter,
                'finish_label' => $finishLabel,
                'ceiling_type' => $ceilingType,
                'flooring_type' => $flType,
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
                'labor_pre_hst' => $laborPre,
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
     * @return array<string, array{name: string, unit: string, unit_price: float, catalog_material_id: int|null}>
     */
    private function resolveMaterialCatalog(BasementSettings $settings): array
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
            if ($key === BasementSettings::MATERIAL_PLATE_2X4) {
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

        return BasementSettings::applyDerivedMaterialPrices($rows);
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

    private function paintGallonsNeeded(float $sf, int $coats): float
    {
        if ($sf <= 0 || $coats <= 0) {
            return 0.0;
        }

        return ($sf * $coats / BasementSettings::PAINT_SF_PER_GALLON) * BasementSettings::PAINT_WASTE;
    }

    private function quartsFromGallons(float $gallons): int
    {
        if ($gallons <= 0) {
            return 0;
        }

        return max(1, (int) ceil($gallons * 4));
    }

    private function trimQuartsNeeded(float $lf, int $coats): int
    {
        if ($lf <= 0 || $coats <= 0) {
            return 0;
        }

        return max(1, (int) ceil(($lf * $coats / BasementSettings::TRIM_LF_PER_QUART) * BasementSettings::PAINT_WASTE));
    }

    /**
     * @param  list<array{key: string, quarts: int}>  $sizes  Largest first
     * @return array<string, int>
     */
    private function packQuarts(int $needed, array $sizes): array
    {
        if ($needed <= 0 || $sizes === []) {
            return [];
        }

        $out = [];
        foreach ($sizes as $size) {
            $quarts = (int) ($size['quarts'] ?? 0);
            $key = (string) ($size['key'] ?? '');
            if ($quarts <= 0 || $key === '' || $needed <= 0) {
                continue;
            }
            $count = intdiv($needed, $quarts);
            if ($count > 0) {
                $out[$key] = $count;
                $needed -= $count * $quarts;
            }
        }
        if ($needed > 0) {
            $smallest = (string) ($sizes[count($sizes) - 1]['key'] ?? '');
            if ($smallest !== '') {
                $out[$smallest] = ($out[$smallest] ?? 0) + 1;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{0: string, 1: int, 2: int}  type, spot qty, fixture qty
     */
    private function lightingCounts(array $inputs): array
    {
        $type = strtolower(trim((string) ($inputs['lighting_type'] ?? 'spot')));
        if (! in_array($type, ['fixture', 'spot', 'both'], true)) {
            $type = 'spot';
        }
        $spots = max(0, (int) ($inputs['light_qty'] ?? 0));
        $fixtures = max(0, (int) ($inputs['fixture_qty'] ?? 0));
        if ($type === 'spot') {
            $fixtures = 0;
        } elseif ($type === 'fixture') {
            if ($fixtures <= 0) {
                $fixtures = $spots;
            }
            $spots = 0;
        }

        return [$type, $spots, $fixtures];
    }

    private function lightingLaborDetail(int $spots, int $fixtures): string
    {
        $bits = [];
        if ($spots > 0) {
            $bits[] = $spots.' spot light'.($spots === 1 ? '' : 's');
        }
        if ($fixtures > 0) {
            $bits[] = $fixtures.' fixture'.($fixtures === 1 ? '' : 's');
        }

        return $bits !== [] ? implode(', ', $bits) : 'None';
    }

    /**
     * Count of bags, boxes, rolls, or sticks for a continuous takeoff.
     */
    private function packages(float $needed, float $perPackage): float
    {
        if ($needed <= 0 || $perPackage <= 0) {
            return 0.0;
        }

        return (float) max(1, (int) ceil($needed / $perPackage));
    }

    /**
     * Blank or null uses the suggested fallback; an explicit 0 skips that quantity.
     */
    private function qtyOr(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return round(max(0, $fallback), 2);
        }

        return round(max(0, (float) $value), 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function emptyCalculation(array $inputs, BasementSettings $settings, float $height): array
    {
        $ownerRate = max(0, (float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate));
        $helperRate = max(0, (float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate));
        $hoursPerDay = max(0.5, (float) $settings->hours_per_day);

        return [
            'scope' => [
                'area_sqft' => 0.0,
                'ceiling_height_ft' => $height,
                'room_count' => max(0, (int) ($inputs['room_count'] ?? 0)),
                'suggested_wall_sf' => 0.0,
                'suggested_perimeter_lf' => 0.0,
                'finish_label' => self::finishLabel($inputs),
                'ceiling_type' => (string) ($inputs['ceiling_type'] ?? 'drywall'),
                'flooring_type' => (string) ($inputs['flooring_type'] ?? 'lvp'),
            ],
            'labor' => [],
            'materials' => [],
            'extra_materials' => [],
            'eta' => $this->etaScenarios(0, $hoursPerDay, $ownerRate, $helperRate),
            'breakdown' => [],
            'summary' => [
                'total_hours' => 0.0,
                'owner_hourly_rate' => $ownerRate,
                'helper_hourly_rate' => $helperRate,
                'labor_pre_hst' => 0.0,
                'materials_pre_hst' => 0.0,
                'materials_hst' => 0.0,
                'materials_after_hst' => 0.0,
                'subtotal_pre_hst' => 0.0,
                'hst' => 0.0,
                'grand_total' => 0.0,
                'final_quote_total' => 0.0,
                'client_provides_all' => ! empty($inputs['client_provides_all']),
            ],
        ];
    }

    private function flooringLabel(string $type): string
    {
        return match ($type) {
            'laminate' => 'Laminate',
            'tile' => 'Tile',
            'carpet' => 'Carpet',
            'epoxy' => 'Epoxy',
            'none' => 'No flooring',
            default => 'LVP',
        };
    }
}

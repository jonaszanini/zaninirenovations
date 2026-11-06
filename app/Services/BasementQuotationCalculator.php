<?php

namespace App\Services;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Models\Material;
use App\Support\CatalogMaterialDelivery;
use App\Support\QuotationHelperLabor;

class BasementQuotationCalculator
{
    /** Extra drywall for boxed ducts / soffits (three ~12 in faces) on bathroom-only quotes. */
    public const BULKHEAD_DRYWALL_SF_PER_LF = 3.0;

    public const SECTIONS = [
        'overview' => 'Project Overview',
        'demolition' => 'Demolition & Preparation',
        'framing' => 'Framing',
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing',
        'insulation' => 'Insulation & Vapour Barrier',
        'ceiling' => 'Ceiling Options',
        'drywall' => 'Drywall / Mud / Tape / Sand',
        'flooring' => 'Flooring',
        'doors_trim' => 'Doors, Trim & Baseboards',
        'painting' => 'Painting',
        'bathroom' => 'Bathroom',
        'finishing' => 'Final Details & Cleanup',
    ];

    /** Drywall sheet sizes offered on the Drywall step. */
    public const SHEET_SIZES = [
        '4x8' => '4×8 (32 sq ft)',
        '4x12' => '4×12 (48 sq ft)',
    ];

    /** Allowed stud on-centre spacing (inches). */
    public const STUD_SPACINGS = [12, 16, 24];

    public const SCOPE_PRESETS = [
        'full' => 'Full basement',
        'bathroom' => 'New bathroom',
        'wall' => 'New wall',
        'custom' => 'Custom',
    ];

    /**
     * Finish floors offered on the basement wizard.
     * Hardwood / engineered wood / vinyl (LVP) / laminate / tile only — no carpet.
     *
     * @var array<string, string>
     */
    public const FLOORING_TYPES = [
        'hardwood' => 'Hardwood',
        'engineered' => 'Engineered wood',
        'lvp' => 'LVP',
        'laminate' => 'Laminate',
        'tile' => 'Tile',
        'none' => 'Skip flooring',
    ];

    /** Gypsum finish levels. Level 0 is hang only — no tape, mud, or sand. */
    public const FINISH_LEVELS = [
        0 => 'Level 0 — hang only',
        1 => 'Level 1 — fire tape',
        2 => 'Level 2 — tape + coat',
        3 => 'Level 3 — economy',
        4 => 'Level 4 — paint-ready',
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
            // Electrical, plumbing, and framing stay enabled; electrical/plumbing
            // are qualified-trade scope plus priced lines. The wizard nests those
            // fields inside the Bathroom step for this preset.
            'bathroom' => ['overview', 'framing', 'plumbing', 'electrical', 'drywall', 'painting', 'bathroom', 'finishing'],
            'wall' => ['overview', 'framing', 'drywall', 'doors_trim', 'painting', 'finishing'],
            'custom' => ['overview'],
            default => array_keys(self::SECTIONS),
        };
    }

    public static function normalizePreset(mixed $preset): string
    {
        $value = strtolower(trim((string) $preset));

        return in_array($preset = $value, ['full', 'bathroom', 'wall', 'custom'], true) ? $preset : 'full';
    }

    public static function normalizeFinishLevel(mixed $level, int $default = 4): int
    {
        if ($level === null || $level === '') {
            return $default;
        }
        $level = (int) $level;

        return array_key_exists($level, self::FINISH_LEVELS) ? $level : $default;
    }

    public static function normalizeSheetSize(mixed $size): string
    {
        return strtolower(trim((string) $size)) === '4x12' ? '4x12' : '4x8';
    }

    public static function drywallSheetSf(mixed $size): float
    {
        return self::normalizeSheetSize($size) === '4x12'
            ? (float) BasementSettings::PANEL_SF_4X12
            : (float) BasementSettings::PANEL_SF;
    }

    public static function drywallSheetCatalogKey(mixed $size): string
    {
        return self::normalizeSheetSize($size) === '4x12' ? 'drywall_sheet_4x12' : 'drywall_sheet';
    }

    public static function normalizeStudSpacing(mixed $inches): int
    {
        $n = (int) $inches;

        return in_array($n, self::STUD_SPACINGS, true) ? $n : BasementSettings::STUD_SPACING_DEFAULT;
    }

    /**
     * Door / window / archway SF to take out of wall drywall.
     *
     * An explicit drywall_opening_sf (including 0) wins. Otherwise doors are
     * 21 sf, windows 12 sf, and leftover drywall_openings count as 21 sf each
     * so existing quotes that only stored a combined count still deduct.
     *
     * @param  array<string, mixed>  $inputs
     */
    public static function drywallOpeningSf(array $inputs): float
    {
        if (array_key_exists('drywall_opening_sf', $inputs)
            && $inputs['drywall_opening_sf'] !== null
            && $inputs['drywall_opening_sf'] !== '') {
            return round(max(0, (float) $inputs['drywall_opening_sf']), 2);
        }

        $doors = max(0, (int) ($inputs['drywall_door_openings'] ?? 0));
        $windows = max(0, (int) ($inputs['drywall_window_openings'] ?? 0));
        $listed = max(0, (int) ($inputs['drywall_openings'] ?? 0));
        $extra = max(0, $listed - $doors - $windows);

        return round(
            ($doors * BasementSettings::DRYWALL_DOOR_SF)
            + ($windows * BasementSettings::DRYWALL_WINDOW_SF)
            + ($extra * BasementSettings::DRYWALL_GENERIC_OPENING_SF),
            2
        );
    }

    /**
     * Cut-and-finish opening count (labor). Uses the listed total when present.
     *
     * @param  array<string, mixed>  $inputs
     */
    public static function drywallOpeningCount(array $inputs): int
    {
        $listed = max(0, (int) ($inputs['drywall_openings'] ?? 0));
        $split = max(0, (int) ($inputs['drywall_door_openings'] ?? 0))
            + max(0, (int) ($inputs['drywall_window_openings'] ?? 0));

        return max($listed, $split);
    }

    /**
     * Form defaults when the user picks a scope preset. Empty array means
     * do not overwrite electrical / bathroom-lighting fields (custom).
     *
     * @return array<string, int|string|bool>
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
                'lighting_type' => 'spot',
                'light_qty' => 0,
                'fixture_qty' => 0,
                'include_bathroom' => true,
                'include_bath_fixture' => true,
                'bath_fixture_qty' => 1,
                'include_bath_spot' => false,
                'bath_spot_qty' => 0,
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

    /**
     * Fixture checkboxes under Review → Bathroom items (client supplies the product).
     *
     * @return array<string, string>
     */
    public static function clientProvidesBathItemLabels(): array
    {
        return [
            'vanity' => 'Vanity',
            'pedestal_sink' => 'Pedestal sink',
            'faucet' => 'Faucet',
            'shower_frame' => 'Shower frame',
            'tub' => 'Tub',
            'showerhead' => 'Showerhead',
            'toilet' => 'Toilet',
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public static function normalizeClientProvidesBathItems(array $inputs): array
    {
        $allowed = array_keys(self::clientProvidesBathItemLabels());
        $raw = $inputs['client_provides_bath_items'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_intersect($allowed, array_map('strval', $raw)));
    }

    public static function bathProvideItemForMaterial(string $catalogOrKey): ?string
    {
        return match ($catalogOrKey) {
            'vanity' => 'vanity',
            'pedestal_sink' => 'pedestal_sink',
            'faucet' => 'faucet',
            'shower_kit' => 'shower_frame',
            'showerhead' => 'showerhead',
            'tub_kit' => 'tub',
            'toilet' => 'toilet',
            default => null,
        };
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

        return self::applyBathTileAreas(self::normalizeBathSinkChoice($inputs));
    }

    /**
     * Vanity and pedestal sink are alternatives. Pedestal wins if both are posted.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public static function normalizeBathSinkChoice(array $inputs): array
    {
        if (! empty($inputs['include_pedestal_sink'])) {
            $inputs['include_pedestal_sink'] = true;
            $inputs['include_vanity'] = false;
        } else {
            $inputs['include_pedestal_sink'] = false;
        }

        $inputs['bath_sink_qty'] = self::bathSinkQty($inputs) ?: 1;

        return $inputs;
    }

    /**
     * Vanity or pedestal count. Two sinks means two fixtures and two faucets.
     */
    public static function bathSinkQty(array $inputs): int
    {
        if (empty($inputs['include_vanity']) && empty($inputs['include_pedestal_sink'])) {
            return 0;
        }

        return ((int) ($inputs['bath_sink_qty'] ?? 1) === 2) ? 2 : 1;
    }

    /**
     * @return list<array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null, finish: string}>
     */
    public static function defaultBathTileAreas(): array
    {
        return [
            self::blankBathTileArea('Floor'),
            self::blankBathTileArea('Shower'),
        ];
    }

    /**
     * @return array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null, finish: string}
     */
    public static function blankBathTileArea(string $label = ''): array
    {
        return [
            'label' => $label,
            'sqft' => 0.0,
            'mode' => 'sqft',
            'length_ft' => null,
            'width_ft' => null,
            'finish' => 'tile',
        ];
    }

    /**
     * Tile (ceramic / porcelain) or vinyl (LVP) on a bathroom floor/wall area.
     */
    public static function normalizeBathTileFinish(mixed $finish): string
    {
        return strtolower(trim((string) $finish)) === 'vinyl' ? 'vinyl' : 'tile';
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
                    'finish' => 'tile',
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
     * @return array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null, finish: string}|null
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
            'finish' => self::normalizeBathTileFinish($row['finish'] ?? null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     * @return array{tile: float, vinyl: float, tile_rows: list<array<string, mixed>>, vinyl_rows: list<array<string, mixed>>}
     */
    public static function splitBathTileAreasByFinish(array $areas): array
    {
        $tile = 0.0;
        $vinyl = 0.0;
        $tileRows = [];
        $vinylRows = [];
        foreach ($areas as $row) {
            $sf = (float) ($row['sqft'] ?? 0);
            if ($sf <= 0) {
                continue;
            }
            if (self::normalizeBathTileFinish($row['finish'] ?? null) === 'vinyl') {
                $vinyl += $sf;
                $vinylRows[] = $row;
            } else {
                $tile += $sf;
                $tileRows[] = $row;
            }
        }

        return [
            'tile' => round($tile, 2),
            'vinyl' => round($vinyl, 2),
            'tile_rows' => $tileRows,
            'vinyl_rows' => $vinylRows,
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
            'bathroom' => 'New bathroom',
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
            'demo_include_flooring' => true,
            'demo_include_studs' => false,
            'demo_include_drywall' => true,
            'demo_include_baseboard' => false,
            'demo_flooring_sf' => null,
            'demo_drywall_sf' => 0,
            'demo_studs_lf' => null,
            'demo_baseboard_lf' => null,
            'demo_bin_qty' => 1,
            'partition_lf' => 0,
            'bulkhead_lf' => 0,
            'header_qty' => 0,
            'framing_door_openings' => 0,
            'stud_spacing_in' => BasementSettings::STUD_SPACING_DEFAULT,
            'outlet_qty' => 0,
            'switch_qty' => 0,
            'light_qty' => 0,
            'lighting_type' => 'spot',
            'include_bath_fixture' => false,
            'bath_fixture_qty' => 1,
            'include_bath_spot' => false,
            'bath_spot_qty' => 0,
            'panel_upgrade' => false,
            'detector_qty' => 2,
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
            'perimeter_lf' => null,
            'room_area_import_id' => '',
            'room_area_import_label' => '',
            'drywall_wall_sf' => null,
            'drywall_ceiling_sf' => null,
            'drywall_corners' => 0,
            'drywall_inside_corners' => 0,
            'drywall_openings' => 0,
            'drywall_door_openings' => 0,
            'drywall_window_openings' => 0,
            'drywall_opening_sf' => null,
            'drywall_sheet_size' => '4x8',
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
            'include_shoe' => false,
            'shoe_lf' => null,
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
            'include_pedestal_sink' => false,
            'bath_sink_qty' => 1,
            'include_toilet' => true,
            'include_shower' => true,
            'include_tub' => false,
            'bath_tile_sf' => 0,
            'include_exhaust_fan' => true,
            'fixture_qty' => 0,
            'hardware_sets' => 0,
            'include_cleaning' => true,
            'include_touchups' => true,
            'include_building_permit' => false,
            'building_permit_amount' => 0,
            'material_waste_percent' => 10,
            'client_provides_all' => false,
            'client_provides_sections' => [],
            'client_provides_bath_items' => [],
            'electrical_lines' => [],
            'plumbing_lines' => [],
            'owner_hourly_rate' => \App\Support\QuotationSharedLabourRates::LEGACY_OWNER_HOURLY,
            'helper_hourly_rate' => 45,
            'include_helper' => false,
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
            if ($partition <= 0) {
                $hints[] = 'Enter the new wall length in linear feet.';
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
        $originalInputs = $inputs;
        $inputs = array_merge(self::defaultInputs(), $inputs);
        $inputs['scope_preset'] = self::normalizePreset($inputs['scope_preset'] ?? null);
        $inputs['stud_spacing_in'] = self::normalizeStudSpacing($inputs['stud_spacing_in'] ?? BasementSettings::STUD_SPACING_DEFAULT);
        $inputs['drywall_sheet_size'] = self::normalizeSheetSize($inputs['drywall_sheet_size'] ?? '4x8');
        $inputs = self::applyAreaEntry($inputs);
        $permitAmt = round(max(0, (float) ($inputs['building_permit_amount'] ?? 0)), 2);
        if (! empty($inputs['include_building_permit']) && $permitAmt > 0) {
            $override = is_array($inputs['materials_override'] ?? null) ? $inputs['materials_override'] : [];
            if (! isset($override['building_permit']['unit_price']) || ! is_numeric($override['building_permit']['unit_price'])) {
                $override['building_permit']['unit_price'] = $permitAmt;
            }
            $inputs['materials_override'] = $override;
        }

        $area = round(max(0, (float) $inputs['area_sqft']), 2);
        $height = round(max(0, (float) $inputs['ceiling_height_ft']), 2) ?: 8;
        $partitionLf = max(0, (float) $inputs['partition_lf']);
        $bulkheadLf = max(0, (float) $inputs['bulkhead_lf']);
        if ($area <= 0 && $partitionLf <= 0) {
            return $this->emptyCalculation($inputs, $settings, $height);
        }
        $measuredPerimeter = round(max(0, (float) ($inputs['perimeter_lf'] ?? 0)), 2);
        $squarePerimeter = $area > 0 ? round(4 * sqrt($area), 2) : 0;
        $perimeter = $measuredPerimeter > 0 ? $measuredPerimeter : $squarePerimeter;
        $suggestedWallSf = round($perimeter * $height, 2);
        $wallBothSidesSf = round($partitionLf * $height * 2, 2);
        $dwWallFallback = $inputs['scope_preset'] === 'wall' ? $wallBothSidesSf : $suggestedWallSf;
        if ($inputs['scope_preset'] === 'bathroom') {
            $dwWallFallback = round(
                $suggestedWallSf
                + ($partitionLf * $height * 2)
                + ($bulkheadLf * self::BULKHEAD_DRYWALL_SF_PER_LF),
                2
            );
        }
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
        $provideBathItems = self::normalizeClientProvidesBathItems($inputs);

        $clientProvidedMat = function (string $section, ?string $catalogKey, string $lineKey) use ($providesAll, $provideSections, $provideBathItems): bool {
            if ($providesAll || in_array($section, $provideSections, true)) {
                return true;
            }
            $item = self::bathProvideItemForMaterial($catalogKey ?: $lineKey);

            return $item !== null && in_array($item, $provideBathItems, true);
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
            $clientProvided = $clientProvidedMat($section, $catalogKey, $key);
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
            if (! empty($inputs['demo_include_flooring'])) {
                $demoFloor = $this->qtyOr($inputs['demo_flooring_sf'], $area);
                $addLabor('demo_floor', 'demolition', 'Remove existing flooring', number_format($demoFloor, 0).' sq ft', $perHr($demoFloor, (float) $prod['demo_floor_sf_per_hr']));
            }
            if (! empty($inputs['demo_include_studs'])) {
                $demoStuds = $this->qtyOr($inputs['demo_studs_lf'], $partitionLf);
                $addLabor('demo_studs', 'demolition', 'Remove existing studs', number_format($demoStuds, 0).' lf', $perHr($demoStuds, (float) $prod['demo_studs_lf_per_hr']));
            }
            if (! empty($inputs['demo_include_drywall'])) {
                $demoDrywall = $this->qtyOr($inputs['demo_drywall_sf'], 0);
                $addLabor('demo_drywall', 'demolition', 'Remove drywall / ceiling', number_format($demoDrywall, 0).' sq ft', $perHr($demoDrywall, (float) $prod['demo_drywall_sf_per_hr']));
            }
            if (! empty($inputs['demo_include_baseboard'])) {
                $demoBaseFallback = $inputs['scope_preset'] === 'wall' ? round($partitionLf * 2, 2) : $perimeter;
                $demoBase = $this->qtyOr($inputs['demo_baseboard_lf'], $demoBaseFallback);
                $addLabor('demo_baseboard', 'demolition', 'Remove existing baseboard', number_format($demoBase, 0).' lf', $perHr($demoBase, (float) $prod['demo_baseboard_lf_per_hr']));
            }
            $bins = max(0, (int) $inputs['demo_bin_qty']);
            $addLabor('demo_bins', 'demolition', 'Debris bins / haul-away', $bins.' bin(s)', $bins * (float) $prod['bin_hours_each']);
            $addMat('dump_bin', 'demolition', $bins, 'dump_bin');
        }

        // --- Framing ---
        $headers = max(0, (int) $inputs['header_qty']);
        $frameDoors = max(0, (int) $inputs['framing_door_openings']);
        $studSpacing = self::normalizeStudSpacing($inputs['stud_spacing_in'] ?? BasementSettings::STUD_SPACING_DEFAULT);
        $inputs['stud_spacing_in'] = $studSpacing;
        if ($on('framing')) {
            $addLabor('framing_walls', 'framing', 'Partition wall framing', number_format($partitionLf, 0).' lf × '.$height.' ft, '.$studSpacing.'" o.c.', $perHr($partitionLf, (float) $prod['framing_lf_per_hr']));
            $addLabor('framing_bulkheads', 'framing', 'Bulkheads / duct boxing', number_format($bulkheadLf, 0).' lf', $perHr($bulkheadLf, (float) $prod['bulkhead_lf_per_hr']));
            $addLabor('framing_headers', 'framing', 'Headers / beams', $headers.' opening(s)', $headers * (float) $prod['header_hours_each']);
            $addLabor('framing_doors', 'framing', 'Door openings', $frameDoors.' opening(s)', $frameDoors * (float) $prod['door_opening_hours_each']);
            if ($partitionLf > 0) {
                $studs = max(1, (int) ceil($this->withMaterialWaste(($partitionLf * 12 / $studSpacing) + 2, $inputs)));
                $plates = round($this->withMaterialWaste($partitionLf * 3, $inputs), 0);
                $addMat('stud_2x4', 'framing', $studs, 'stud_2x4');
                $addMat('plate_2x4', 'framing', $plates, 'plate_2x4');
            }
        }

        // --- Electrical ---
        // Scope questions (outlets, switches, lights, panel) stay on the form
        // for a qualified electrician. Pricing is the description/amount lines.

        // --- Plumbing ---
        // Scope questions stay for a qualified plumber. Pricing is the lines.

        // --- Insulation ---
        if ($on('insulation')) {
            $insWall = $this->qtyOr($inputs['insulation_wall_sf'], ! empty($inputs['insulate_walls']) ? $suggestedWallSf : 0);
            $insCeil = $this->qtyOr($inputs['insulation_ceiling_sf'], ! empty($inputs['insulate_ceiling']) ? $area : 0);
            $insSlab = $this->qtyOr($inputs['underslab_sf'], ! empty($inputs['underslab']) ? $area : 0);
            if (! empty($inputs['insulate_walls'])) {
                $addLabor('ins_walls', 'insulation', 'Wall insulation + vapour barrier', number_format($insWall, 0).' sq ft', $perHr($insWall, (float) $prod['insulation_wall_sf_per_hr']));
                $addMat('insulation_batt', 'insulation', $this->packages($this->withMaterialWaste($insWall, $inputs), BasementSettings::BATT_SF_PER_BAG), 'insulation_batt');
                $addMat('vapor_barrier', 'insulation', $this->packages($this->withMaterialWaste($insWall, $inputs), BasementSettings::VAPOR_SF_PER_ROLL), 'vapor_barrier');
            }
            if (! empty($inputs['insulate_ceiling'])) {
                $addLabor('ins_ceiling', 'insulation', 'Ceiling insulation', number_format($insCeil, 0).' sq ft', $perHr($insCeil, (float) $prod['insulation_ceiling_sf_per_hr']));
                $addMat('insulation_batt_ceil', 'insulation', $this->packages($this->withMaterialWaste($insCeil, $inputs), BasementSettings::BATT_SF_PER_BAG), 'insulation_batt');
            }
            if (! empty($inputs['underslab'])) {
                $addLabor('ins_slab', 'insulation', 'Under-slab insulation', number_format($insSlab, 0).' sq ft', $perHr($insSlab, (float) $prod['underslab_sf_per_hr']));
                $addMat('underslab_insulation', 'insulation', $this->packages($this->withMaterialWaste($insSlab, $inputs), BasementSettings::PANEL_SF), 'underslab_insulation');
            }
        }

        // --- Drywall ---
        $sheetSize = self::normalizeSheetSize($inputs['drywall_sheet_size'] ?? '4x8');
        $inputs['drywall_sheet_size'] = $sheetSize;
        $openingSf = 0.0;
        $dwWallGross = 0.0;
        if ($on('drywall')) {
            $dwWallGross = $this->qtyOr($inputs['drywall_wall_sf'], $dwWallFallback);
            $openingSf = self::drywallOpeningSf($inputs);
            $dwWall = round(max(0, $dwWallGross - $openingSf), 2);
            $skipCeilingFinish = $inputs['scope_preset'] === 'wall' && ! $on('ceiling');
            $dwCeil = $this->resolvedDrywallCeilingSf(
                $inputs,
                $originalInputs,
                $area,
                $ceilingType,
                $on('ceiling'),
                $skipCeilingFinish
            );
            $dwSf = $dwWall + $dwCeil;
            $level = self::normalizeFinishLevel($inputs['finish_level'] ?? 4);
            $hangOnly = $level === 0;
            $hangDetail = number_format($dwSf, 0).' sq ft';
            if ($openingSf > 0) {
                $hangDetail .= ' ('.number_format($openingSf, 0).' sf openings deducted)';
            }
            $addLabor('dw_hang', 'drywall', 'Hang drywall', $hangDetail, $perHr($dwSf, (float) $prod['drywall_hang_sf_per_hr']));
            if (! $hangOnly) {
                $finishKey = 'drywall_finish_l'.$level.'_sf_per_hr';
                $finishHours = $perHr($dwSf, (float) ($prod[$finishKey] ?? $prod['drywall_finish_l4_sf_per_hr']));
                $finishDetail = number_format($dwSf, 0).' sq ft';
                if ($paintColors === 2) {
                    $finishHours *= BasementSettings::TWO_COLOR_DRYWALL_FINISH_FACTOR;
                    $finishDetail .= ', two-color';
                }
                $addLabor('dw_finish', 'drywall', 'Mud / tape / sand (Level '.$level.')', $finishDetail, $finishHours);
            }
            $dwOrderSf = $this->withMaterialWaste($dwSf, $inputs);
            $sheetSf = self::drywallSheetSf($sheetSize);
            $sheetKey = self::drywallSheetCatalogKey($sheetSize);
            $sheets = $dwOrderSf > 0 ? max(1, (int) ceil($dwOrderSf / $sheetSf)) : 0;
            $addMat($sheetKey, 'drywall', $sheets, $sheetKey);
            if ($sheets > 0) {
                $screwsEach = $sheetSize === '4x12'
                    ? BasementSettings::DRYWALL_SCREWS_PER_4X12
                    : BasementSettings::DRYWALL_SCREWS_PER_4X8;
                $addMat(
                    'drywall_screws',
                    'drywall',
                    $this->packages($sheets * $screwsEach, BasementSettings::DRYWALL_SCREWS_PER_BOX),
                    'drywall_screws'
                );
            }
            $corners = max(0, (int) $inputs['drywall_corners']);
            $insideCorners = max(0, (int) ($inputs['drywall_inside_corners'] ?? 0));
            $openings = self::drywallOpeningCount($inputs);
            if (! $hangOnly) {
                $addMat('joint_compound', 'drywall', $this->packages($dwOrderSf, BasementSettings::JOINT_COMPOUND_SF_PER_PAIL), 'joint_compound');
                $addMat('drywall_tape', 'drywall', $this->packages($dwOrderSf, BasementSettings::DRYWALL_TAPE_SF_PER_ROLL), 'drywall_tape');
                $addMat('corner_bead', 'drywall', $corners, 'corner_bead');
                $addLabor(
                    'dw_inside_corners',
                    'drywall',
                    'Tape inside corners',
                    $insideCorners.' corner(s)',
                    $insideCorners * (float) ($prod['drywall_inside_corner_hours_each'] ?? 0.3)
                );
            }
            $addLabor(
                'dw_openings',
                'drywall',
                $hangOnly ? 'Cut openings' : 'Cut & finish openings',
                $openings.' opening(s)',
                $openings * (float) ($prod['drywall_opening_hours_each'] ?? 0.4)
            );
        }

        // --- Ceiling ---
        if ($on('ceiling') && $ceilingType === 'drop') {
            $ceilSf = $this->qtyOr($inputs['ceiling_sf'], $area);
            $addLabor('ceil_drop', 'ceiling', 'Drop ceiling', number_format($ceilSf, 0).' sq ft', $perHr($ceilSf, (float) $prod['ceiling_drop_sf_per_hr']));
            $addMat('drop_ceiling', 'ceiling', $this->withMaterialWaste($ceilSf, $inputs), 'drop_ceiling');
        }

        // --- Flooring ---
        if ($on('flooring')) {
            $flSf = $this->qtyOr($inputs['flooring_sf'], $area);
            $flCatalog = match ($flType) {
                'laminate' => 'laminate',
                'tile' => 'tile',
                'hardwood' => 'hardwood',
                'engineered' => 'engineered',
                'epoxy' => 'epoxy',
                default => 'lvp',
            };
            if ($flType !== 'none' && $flSf > 0) {
                if (! empty($inputs['subfloor_prep'])) {
                    $addLabor('fl_subfloor', 'flooring', 'Subfloor prep / leveling', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['subfloor_sf_per_hr']));
                    $addMat('subfloor_panel', 'flooring', $this->packages($this->withMaterialWaste($flSf, $inputs), BasementSettings::PANEL_SF), 'subfloor_panel');
                }
                if (! empty($inputs['underlayment']) && $flType !== 'epoxy' && $flType !== 'tile') {
                    $addLabor('fl_underlay', 'flooring', 'Underlayment', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['underlayment_sf_per_hr']));
                    $addMat('underlayment', 'flooring', $this->packages($this->withMaterialWaste($flSf, $inputs), BasementSettings::UNDERLAYMENT_SF_PER_ROLL), 'underlayment');
                }
                $addLabor('fl_install', 'flooring', $this->flooringLabel($flType).' install', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['flooring_sf_per_hr']));
                $addMat('flooring_finish', 'flooring', $this->withMaterialWaste($flSf, $inputs), $flCatalog);
                $transitions = max(0, (int) $inputs['transition_qty']);
                $addLabor('fl_transitions', 'flooring', 'Transitions', $transitions.' piece(s)', $transitions * (float) $prod['transition_hours_each']);
                $addMat('transition', 'flooring', $transitions, 'transition');
            }
        }

        // --- Doors / trim ---
        if ($on('doors_trim')) {
            $doors = max(0, (int) $inputs['door_qty']);
            $baseFallback = $inputs['scope_preset'] === 'wall' ? round($partitionLf * 2, 2) : $perimeter;
            $baseLf = $this->qtyOr($inputs['baseboard_lf'], $baseFallback);
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
            $addMat('baseboard', 'doors_trim', $this->packages($this->withMaterialWaste($baseLf, $inputs), BasementSettings::BASEBOARD_FEET_PER_STICK), 'baseboard');
            if (! empty($inputs['include_shoe'])) {
                $shoeLf = $this->qtyOr($inputs['shoe_lf'] ?? null, $baseLf);
                $addLabor('trim_shoe', 'doors_trim', 'Shoe moulding', number_format($shoeLf, 0).' lf', $perHr($shoeLf, (float) ($prod['shoe_lf_per_hr'] ?? 30)));
                $addMat('shoe', 'doors_trim', $this->packages($this->withMaterialWaste($shoeLf, $inputs), BasementSettings::BASEBOARD_FEET_PER_STICK), 'shoe');
            }
            $addMat('casing', 'doors_trim', $casing, 'casing');
            $addMat('window_trim', 'doors_trim', $windows, 'window_trim');
            $addMat('door_hardware', 'doors_trim', $doors, 'door_hardware');
        }

        // --- Painting ---
        if ($on('painting')) {
            $coats = max(1, (int) ($inputs['paint_coats'] ?? 2));
            $pWall = $this->qtyOr($inputs['paint_wall_sf'], $dwWall ?: $dwWallFallback);
            $skipCeilingFinish = $inputs['scope_preset'] === 'wall' && ! $on('ceiling');
            $pCeil = $skipCeilingFinish ? 0.0 : $this->qtyOr(
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

            $addPaintPack = function (string $prefix, float $sf, int $surfaceCoats, array $sizes, string $note = '') use ($addMat, $matCatalog, $inputs): void {
                $orderSf = $this->withMaterialWaste($sf, $inputs);
                $packed = $this->packQuarts($this->quartsFromGallons($this->paintGallonsNeeded($orderSf, $surfaceCoats)), $sizes);
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
            $trimQuarts = $this->trimQuartsNeeded($this->withMaterialWaste($pTrim, $inputs), $coats);
            foreach ($this->packQuarts($trimQuarts, BasementSettings::trimPaintCanSizes()) as $catalogKey => $qty) {
                $addMat('trim_'.$catalogKey, 'painting', (float) $qty, $catalogKey);
            }
        }

        // --- Bathroom ---
        if ($on('bathroom') && ! empty($inputs['include_bathroom'])) {
            $half = ($inputs['bath_type'] ?? 'full') === 'half';
            $sinkQty = self::bathSinkQty($inputs);
            if (! empty($inputs['include_vanity']) && $sinkQty > 0) {
                $addLabor(
                    'bath_vanity',
                    'bathroom',
                    'Vanity',
                    $sinkQty === 2 ? 'Install × 2' : 'Install',
                    (float) $prod['vanity_hours'] * $sinkQty
                );
                $addMat('vanity', 'bathroom', $sinkQty, 'vanity');
                $addMat('faucet', 'bathroom', $sinkQty, 'faucet');
            } elseif (! empty($inputs['include_pedestal_sink']) && $sinkQty > 0) {
                $addLabor(
                    'bath_pedestal',
                    'bathroom',
                    'Pedestal sink',
                    $sinkQty === 2 ? 'Install × 2' : 'Install',
                    (float) $prod['pedestal_sink_hours'] * $sinkQty
                );
                $addMat('pedestal_sink', 'bathroom', $sinkQty, 'pedestal_sink');
                $addMat('faucet', 'bathroom', $sinkQty, 'faucet');
            }
            if (! empty($inputs['include_toilet'])) {
                $addLabor('bath_toilet', 'bathroom', 'Toilet', 'Install', (float) $prod['toilet_hours']);
                $addMat('toilet', 'bathroom', 1, 'toilet');
            }
            if (! $half && ! empty($inputs['include_shower'])) {
                $addLabor('bath_shower', 'bathroom', 'Shower', 'Install', (float) $prod['shower_hours']);
                $addMat('shower_kit', 'bathroom', 1, 'shower_kit');
                $addMat('showerhead', 'bathroom', 1, 'showerhead');
            }
            if (! $half && ! empty($inputs['include_tub'])) {
                $addLabor('bath_tub', 'bathroom', 'Tub', 'Install', (float) $prod['tub_hours']);
                $addMat('tub_kit', 'bathroom', 1, 'tub_kit');
            }
            if (! empty($inputs['include_exhaust_fan'])) {
                $addLabor('bath_fan', 'bathroom', 'Exhaust fan', 'Install + duct', (float) $prod['exhaust_fan_hours']);
                $addMat('exhaust_fan', 'bathroom', 1, 'exhaust_fan');
            }
            $tileAreas = is_array($inputs['bath_tile_areas'] ?? null) ? $inputs['bath_tile_areas'] : [];
            $tileSplit = self::splitBathTileAreasByFinish($tileAreas);
            $tileSf = $tileSplit['tile'];
            $vinylSf = $tileSplit['vinyl'];
            if ($tileSf > 0) {
                $addLabor(
                    'bath_tile',
                    'bathroom',
                    'Bathroom tile',
                    self::bathTileLaborDetail($tileSplit['tile_rows'], $tileSf),
                    $perHr($tileSf, (float) $prod['tile_sf_per_hr'])
                );
                $addMat('bath_tile', 'bathroom', $this->withMaterialWaste($tileSf, $inputs), 'tile');
                $addMat('thinset_grout', 'bathroom', $this->packages($this->withMaterialWaste($tileSf, $inputs), BasementSettings::THINSET_SF_PER_BAG), 'thinset_grout');
            }
            if ($vinylSf > 0) {
                $addLabor(
                    'bath_vinyl_underlay',
                    'bathroom',
                    'Bathroom vinyl underlayment',
                    number_format($vinylSf, 0).' sq ft',
                    $perHr($vinylSf, (float) $prod['underlayment_sf_per_hr'])
                );
                $addMat(
                    'bath_vinyl_underlay',
                    'bathroom',
                    $this->packages($this->withMaterialWaste($vinylSf, $inputs), BasementSettings::UNDERLAYMENT_SF_PER_ROLL),
                    'underlayment',
                    'Bathroom vinyl underlayment'
                );
                $addLabor(
                    'bath_vinyl',
                    'bathroom',
                    'Bathroom vinyl flooring',
                    self::bathTileLaborDetail($tileSplit['vinyl_rows'], $vinylSf),
                    $perHr($vinylSf, (float) $prod['flooring_sf_per_hr'])
                );
                $addMat('bath_vinyl', 'bathroom', $this->withMaterialWaste($vinylSf, $inputs), 'lvp', 'Bathroom vinyl flooring');
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
            if (! empty($inputs['include_building_permit']) && $permitAmt > 0) {
                $addMat('building_permit', 'finishing', 1, 'building_permit', 'Building permit');
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

        $matAll = 0.0;
        $permitPre = 0.0;
        foreach ($materials as $row) {
            $amount = (float) $row['amount'];
            $matAll += $amount;
            if (($row['key'] ?? '') === 'building_permit') {
                $permitPre += $amount;
            }
        }
        foreach ($extraMaterials as $row) {
            $matAll += (float) $row['amount'];
        }
        $matAll = round($matAll, 2);
        $permitPre = round($permitPre, 2);
        $matPre = round($matAll - $permitPre, 2);
        $matHst = round($matPre * BasementQuotation::HST_RATE, 2);
        $permitHst = round($permitPre * BasementQuotation::HST_RATE, 2);
        $laborHst = round($laborPre * BasementQuotation::HST_RATE, 2);
        $subtotal = round($laborPre + $matPre + $permitPre, 2);
        $hst = round($laborHst + $matHst + $permitHst, 2);
        $trade = $this->tradeLineSummary($inputs);
        $grand = round($subtotal + $hst + $trade['tax_in'], 2);
        $wastePercent = $this->materialWastePercent($inputs);

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
                'measured_perimeter' => $measuredPerimeter > 0,
                'finish_label' => $finishLabel,
                'ceiling_type' => $ceilingType,
                'flooring_type' => $flType,
                'stud_spacing_in' => $studSpacing,
                'drywall_sheet_size' => $sheetSize,
                'drywall_sheet_sf' => self::drywallSheetSf($sheetSize),
                'drywall_opening_sf' => $openingSf,
                'drywall_wall_gross_sf' => $dwWallGross,
                'drywall_wall_net_sf' => $dwWall,
                'drywall_ceiling_sf' => $dwCeil,
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
                'permit_pre_hst' => $permitPre,
                'permit_hst' => $permitHst,
                'permit_after_hst' => round($permitPre + $permitHst, 2),
                'material_waste_percent' => $wastePercent,
                'subtotal_pre_hst' => $subtotal,
                'hst' => $hst,
                'grand_total' => $grand,
                'final_quote_total' => $grand,
                'client_provides_all' => $providesAll,
                'trade_lines_pre_hst' => $trade['pre_hst'],
                'trade_lines_hst' => $trade['hst'],
                'trade_lines_tax_in' => $trade['tax_in'],
                'trades' => [
                    'electrical' => $trade['electrical'],
                    'plumbing' => $trade['plumbing'],
                ],
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
     * Electrical / plumbing trade lines (same HST handling as additional costs).
     * Priced from a qualified electrician or plumber.
     *
     * @param  array<string, mixed>  $inputs
     * @return array{
     *     electrical: array{labor_pre_hst: float, materials_pre_hst: float, pre_hst: float, hst: float, tax_in: float},
     *     plumbing: array{labor_pre_hst: float, materials_pre_hst: float, pre_hst: float, hst: float, tax_in: float},
     *     pre_hst: float,
     *     hst: float,
     *     tax_in: float
     * }
     */
    private function tradeLineSummary(array $inputs): array
    {
        $elecRows = self::sectionEnabled($inputs, 'electrical')
            ? AdditionalCostsService::normalizeRows($inputs['electrical_lines'] ?? [], 'Electrical work')
            : [];
        $plumbRows = self::sectionEnabled($inputs, 'plumbing')
            ? AdditionalCostsService::normalizeRows($inputs['plumbing_lines'] ?? [], 'Plumbing work')
            : [];
        $elecPre = AdditionalCostsService::materialsPreTaxTotal($elecRows);
        $elecHst = AdditionalCostsService::materialsHstTotal($elecRows);
        $elecTax = AdditionalCostsService::taxInclusiveTotal($elecRows);
        $plumbPre = AdditionalCostsService::materialsPreTaxTotal($plumbRows);
        $plumbHst = AdditionalCostsService::materialsHstTotal($plumbRows);
        $plumbTax = AdditionalCostsService::taxInclusiveTotal($plumbRows);

        $trade = function (float $pre, float $hst, float $taxIn): array {
            return [
                'labor_pre_hst' => 0.0,
                'materials_pre_hst' => 0.0,
                'pre_hst' => $pre,
                'hst' => $hst,
                'tax_in' => $taxIn,
            ];
        };

        return [
            'electrical' => $trade($elecPre, $elecHst, $elecTax),
            'plumbing' => $trade($plumbPre, $plumbHst, $plumbTax),
            'pre_hst' => round($elecPre + $plumbPre, 2),
            'hst' => round($elecHst + $plumbHst, 2),
            'tax_in' => round($elecTax + $plumbTax, 2),
        ];
    }

    /**
     * Ordering buffer for bulk materials (sheets, sticks, SF coverage). Default 10%.
     * Counted fixtures (doors, toilets, etc.) are not multiplied.
     *
     * @param  array<string, mixed>  $inputs
     */
    private function materialWastePercent(array $inputs): float
    {
        if (! array_key_exists('material_waste_percent', $inputs) || $inputs['material_waste_percent'] === null || $inputs['material_waste_percent'] === '') {
            return 10.0;
        }

        return round(max(0.0, min(100.0, (float) $inputs['material_waste_percent'])), 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function withMaterialWaste(float $qty, array $inputs): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        return round($qty * (1 + ($this->materialWastePercent($inputs) / 100)), 4);
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
     * Hang/mud ceiling area. New quotes follow Ceiling Options (ceiling_sf).
     * Legacy quotes that stored a different drywall_ceiling_sf keep that figure.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $originalInputs
     */
    private function resolvedDrywallCeilingSf(
        array $inputs,
        array $originalInputs,
        float $area,
        string $ceilingType,
        bool $ceilingOn,
        bool $skipCeilingFinish,
    ): float {
        if ($skipCeilingFinish) {
            return 0.0;
        }
        $drywallType = in_array($ceilingType, ['drywall', 'paint'], true);
        if (BasementCeilingQuantities::usesSplit($originalInputs)) {
            return $this->qtyOr(
                $inputs['drywall_ceiling_sf'] ?? null,
                ($ceilingOn && $drywallType) ? $area : 0.0
            );
        }
        if (! $ceilingOn || ! $drywallType) {
            return 0.0;
        }

        return $this->qtyOr($inputs['ceiling_sf'] ?? null, $area);
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
        $includeHelper = QuotationHelperLabor::enabled($inputs);
        $hoursPerDay = max(0.5, (float) $settings->hours_per_day);
        $trade = $this->tradeLineSummary($inputs);

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
                'include_helper' => $includeHelper,
                'labor_pre_hst' => 0.0,
                'labor_hst' => 0.0,
                'labor_after_hst' => 0.0,
                'materials_pre_hst' => 0.0,
                'materials_hst' => 0.0,
                'materials_after_hst' => 0.0,
                'material_waste_percent' => $this->materialWastePercent($inputs),
                'subtotal_pre_hst' => 0.0,
                'hst' => 0.0,
                'grand_total' => $trade['tax_in'],
                'final_quote_total' => $trade['tax_in'],
                'client_provides_all' => ! empty($inputs['client_provides_all']),
                'trade_lines_pre_hst' => $trade['pre_hst'],
                'trade_lines_hst' => $trade['hst'],
                'trade_lines_tax_in' => $trade['tax_in'],
                'trades' => [
                    'electrical' => $trade['electrical'],
                    'plumbing' => $trade['plumbing'],
                ],
            ],
        ];
    }

    private function flooringLabel(string $type): string
    {
        return match ($type) {
            'hardwood' => 'Hardwood',
            'engineered' => 'Engineered wood',
            'laminate' => 'Laminate',
            'tile' => 'Tile',
            'epoxy' => 'Epoxy',
            'none' => 'No flooring',
            default => 'LVP',
        };
    }
}

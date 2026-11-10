<?php

namespace App\Services;

/**
 * Room Area layout option → basement quotation quantity fields.
 *
 * Only layout takeoffs. Rates, finish choices, and shared prices are never mapped.
 * Null in the proposed set means “not on the drawing” — leave the form field alone.
 * Explicit 0 means the drawing measured zero.
 *
 * Corner bead (CB-1/CB-2), wall-height sticks, and bulkhead bead are not mapped.
 */
class BasementRoomAreaImportMapper
{
    /**
     * Proposed quantity key → wizard input id.
     *
     * @var array<string, string>
     */
    public const FIELD_IDS = [
        'area_sqft' => 'br-area',
        'ceiling_height_ft' => 'br-height',
        'room_count' => 'br-rooms',
        'perimeter_lf' => 'br-perimeter-lf',
        'partition_lf' => 'br-partition',
        'bulkhead_lf' => 'br-bulkhead',
        'framing_door_openings' => 'br-frame-doors',
        'insulation_wall_sf' => 'br-ins-wall-sf',
        'insulation_ceiling_sf' => 'br-ins-ceil-sf',
        'ceiling_sf' => 'br-ceil-sf',
        'drywall_wall_sf' => 'br-dw-wall',
        'drywall_ceiling_sf' => 'br-dw-ceil',
        'drywall_door_openings' => 'br-dw-door-open',
        'drywall_window_openings' => 'br-dw-window-open',
        'drywall_openings' => 'br-dw-open',
        'drywall_opening_sf' => 'br-dw-opening-sf',
        'drywall_corners' => 'br-dw-corners',
        'drywall_inside_corners' => 'br-dw-inside-corners',
        'flooring_sf' => 'br-fl-sf',
        'door_qty' => 'br-doors',
        'baseboard_lf' => 'br-base',
        'casing_openings' => 'br-casing',
        'window_trim_qty' => 'br-windows',
        'paint_wall_sf' => 'br-p-wall',
        'paint_ceiling_sf' => 'br-p-ceil',
        'paint_trim_lf' => 'br-p-trim',
    ];

    /**
     * Sq ft fields that use the length×width toggle.
     *
     * @var list<string>
     */
    public const LW_KEYS = [
        'area_sqft',
        'insulation_wall_sf',
        'insulation_ceiling_sf',
        'ceiling_sf',
        'drywall_wall_sf',
        'drywall_ceiling_sf',
        'flooring_sf',
        'paint_wall_sf',
        'paint_ceiling_sf',
    ];

    /**
     * Proposed hang area is derived from Ceiling area on the form.
     * Import never writes the legacy drywall_ceiling_sf field.
     *
     * @var list<string>
     */
    public const SKIP_FORM_KEYS = [
        'drywall_ceiling_sf',
    ];

    /**
     * Quote configuration that import must never write.
     *
     * @var list<string>
     */
    public const NEVER_OVERWRITE = [
        'owner_hourly_rate',
        'helper_hourly_rate',
        'include_helper',
        'material_waste_percent',
        'flooring_type',
        'ceiling_type',
        'finish_level',
        'drywall_sheet_size',
        'paint_colors',
        'paint_coats',
        'stud_spacing_in',
        'underlayment',
        'subfloor_prep',
        'include_shoe',
        'scope_preset',
        'enabled_sections',
        'electrical_lines',
        'plumbing_lines',
        'labor_override',
        'materials_override',
        'insulate_walls',
        'insulate_ceiling',
        'underslab',
    ];

    /**
     * Layout keys with no basement destination (other wizards, on-hold, or not a basement qty).
     *
     * @var list<string>
     */
    public const UNMAPPED_LAYOUT_KEYS = [
        'bulkhead_corner_bead_lf',
        'cabinet_base_lf',
        'cabinet_upper_lf',
        'stairs_count',
        'stairs_treads',
        'stairs_run_lf',
        'stairs_quote_type',
        'framing_lf',
        'wall_thickness_in',
        'outside_area_sqft',
        'shared_wall_lf',
        'flooring_waste_percent',
        'flooring_boxes',
        'flooring_box_sqft',
        'flooring_with_waste_sqft',
        'flooring_direction',
        'wall_height_ft',
    ];

    public const CONFIRM_MESSAGE = 'This layout’s quantities differ from the values currently in the form. Replace the form values with the imported layout?';

    public static function present(array $opt, string $key): bool
    {
        return array_key_exists($key, $opt) && $opt[$key] !== null && $opt[$key] !== '';
    }

    /**
     * @param  array<string, mixed>  $opt
     */
    public static function paintWallsMarked(array $opt): int
    {
        return max(0, (int) ($opt['paint_walls_marked'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $opt
     */
    public static function paintWallsUnassigned(array $opt): int
    {
        return max(0, (int) ($opt['paint_walls_unassigned'] ?? 0));
    }

    /**
     * Mixed legacy: some walls/bulkheads have an explicit Paint choice and others do not.
     *
     * @param  array<string, mixed>  $opt
     */
    public static function paintAssignmentMixed(array $opt): bool
    {
        $unassigned = self::paintWallsUnassigned($opt)
            + max(0, (int) ($opt['paint_bulkheads_unassigned'] ?? 0));
        $assigned = self::paintWallsMarked($opt)
            + max(0, (int) ($opt['paint_walls_explicit_off'] ?? 0))
            + max(0, (int) ($opt['paint_bulkheads_marked'] ?? 0))
            + max(0, (int) ($opt['paint_bulkheads_explicit_off'] ?? 0));

        return $unassigned > 0 && $assigned > 0;
    }

    /**
     * @param  array<string, float|int|string|null>  $current
     * @param  array<string, float|int|null>  $proposed
     * @param  array<string, mixed>  $opt
     */
    public static function valuesDiverge(mixed $currentVal, mixed $proposedVal): bool
    {
        if ($proposedVal === null) {
            return false;
        }
        if (is_numeric($currentVal) && is_numeric($proposedVal)) {
            return abs((float) $currentVal - (float) $proposedVal) > 0.009;
        }

        return (string) $currentVal !== (string) $proposedVal;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     */
    public static function wouldOverwrite(array $current, array $proposed): bool
    {
        $hasQty = false;
        foreach (self::FIELD_IDS as $key => $_id) {
            if (abs((float) ($current[$key] ?? 0)) > 0.009) {
                $hasQty = true;
                break;
            }
        }
        if (! $hasQty && trim((string) ($current['import_label'] ?? '')) === '') {
            return false;
        }
        foreach ($proposed as $key => $value) {
            if (in_array($key, self::SKIP_FORM_KEYS, true) || $value === null) {
                continue;
            }
            if (self::valuesDiverge($current[$key] ?? 0, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     * @param  array<string, mixed>  $opt
     */
    public static function needsImportConfirm(array $current, array $proposed, array $opt = []): bool
    {
        if (self::wouldOverwrite($current, $proposed)) {
            return true;
        }
        if (! self::paintAssignmentMixed($opt)) {
            return false;
        }
        if (! array_key_exists('paint_wall_sf', $proposed) || $proposed['paint_wall_sf'] === null) {
            return false;
        }

        return self::valuesDiverge($current['paint_wall_sf'] ?? 0, $proposed['paint_wall_sf']);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     * @param  array<string, mixed>  $opt
     */
    public static function confirmImportMessage(array $current, array $proposed, array $opt = []): string
    {
        $parts = [self::CONFIRM_MESSAGE];
        $marked = self::paintWallsMarked($opt);
        $unassigned = self::paintWallsUnassigned($opt);
        $off = max(0, (int) ($opt['paint_walls_explicit_off'] ?? 0));
        $bhMarked = max(0, (int) ($opt['paint_bulkheads_marked'] ?? 0));
        $bhUnassigned = max(0, (int) ($opt['paint_bulkheads_unassigned'] ?? 0));
        $imported = $proposed['paint_wall_sf'] ?? null;
        $previous = $current['paint_wall_sf'] ?? null;

        if ($marked > 0 || $unassigned > 0 || $off > 0 || $imported !== null || $bhMarked > 0 || $bhUnassigned > 0) {
            $parts[] = 'Paint: '.$marked.' wall(s) marked Paint, '.$unassigned.' wall(s) with no Paint choice'
                .($off > 0 ? ', '.$off.' wall(s) explicitly off' : '').'.';
            if ($bhMarked > 0 || $bhUnassigned > 0) {
                $parts[] = 'Bulkheads: '.$bhMarked.' marked Paint, '.$bhUnassigned.' with no Paint choice.';
            }
            if ($imported !== null) {
                $parts[] = 'Imported wall paint: '.self::formatQty((float) $imported).' sq ft.';
            }
            if ($previous !== null && $imported !== null && self::valuesDiverge($previous, $imported)) {
                $parts[] = 'This replaces the current wall paint of '.self::formatQty((float) $previous).' sq ft.';
            }
            if ($unassigned > 0 && $imported !== null) {
                $parts[] = 'Walls without a Paint choice are not counted as painted. Confirm to import this quantity.';
            }
        }

        return implode(' ', $parts);
    }

    public static function formatQty(float $n): string
    {
        $rounded = round($n, 2);
        $text = rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');

        return $text === '' ? '0' : $text;
    }

    /**
     * Search JSON always used to emit ceiling_area_sqft (emptyLayoutMetrics
     * defaults it to 0.0). That 0 is a struct placeholder, not a measurement.
     * A successful drawing sets ceiling from floor after exclusions, so floor > 0
     * with ceiling 0 and no exclusions is treated as unavailable.
     *
     * Explicit ceiling_area_available from the layout option wins.
     *
     * @param  array<string, mixed>  $opt
     */
    public static function ceilingTakeoffAvailable(array $opt): bool
    {
        if (! empty($opt['geometry_unmeasurable'])) {
            return false;
        }
        if (array_key_exists('ceiling_area_available', $opt) && $opt['ceiling_area_available'] !== null && $opt['ceiling_area_available'] !== '') {
            return (bool) $opt['ceiling_area_available'];
        }
        if (! self::present($opt, 'ceiling_area_sqft')) {
            return false;
        }
        $ceiling = (float) $opt['ceiling_area_sqft'];
        $area = self::present($opt, 'area_sqft') ? (float) $opt['area_sqft'] : null;
        $excluded = self::present($opt, 'excluded_area_sqft') ? (float) $opt['excluded_area_sqft'] : 0.0;
        $polygons = (int) ($opt['polygon_count'] ?? 0);
        if ($ceiling <= 0 && ($area === null || $area <= 0) && $excluded <= 0 && $polygons <= 0) {
            return false;
        }
        if ($ceiling <= 0 && $area !== null && $area > 0 && $excluded <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Measured ceiling (including 0) when available; otherwise measured floor;
     * otherwise null so the form is not wiped.
     *
     * @param  array<string, mixed>  $opt
     */
    public static function resolveImportedCeilingArea(array $opt, ?float $area): ?float
    {
        if (self::ceilingTakeoffAvailable($opt) && self::present($opt, 'ceiling_area_sqft')) {
            return (float) $opt['ceiling_area_sqft'];
        }
        if ($area !== null && $area > 0) {
            return $area;
        }

        return null;
    }

    /**
     * Wall paint on import: explicit Paint-marked area when present; otherwise the
     * same gross wall drywall area imported for the Drywall step (incl. bulkhead drywall).
     *
     * @param  array<string, mixed>  $opt
     */
    public static function resolveImportedPaintWallSf(array $opt, ?float $drywallWallSf): ?float
    {
        if (self::present($opt, 'finish_paint_sqft')) {
            return (float) $opt['finish_paint_sqft'];
        }
        if ($drywallWallSf !== null) {
            return $drywallWallSf;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $opt
     * @return array<string, float|int|null>
     */
    public static function proposedQuantities(array $opt, ?float $formCeilingHeightFt = null): array
    {
        $height = 8.0;
        if (self::present($opt, 'ceiling_height_ft') && (float) $opt['ceiling_height_ft'] > 0) {
            $height = (float) $opt['ceiling_height_ft'];
        } elseif ($formCeilingHeightFt !== null && $formCeilingHeightFt > 0) {
            $height = $formCeilingHeightFt;
        }

        $peri = self::present($opt, 'perimeter_lf') ? (float) $opt['perimeter_lf'] : null;
        $area = self::present($opt, 'area_sqft') ? (float) $opt['area_sqft'] : null;
        $ceilingArea = self::resolveImportedCeilingArea($opt, $area);

        $netWall = null;
        if (self::present($opt, 'finish_drywall_sqft')) {
            $netWall = (float) $opt['finish_drywall_sqft'];
        } elseif (self::present($opt, 'net_wall_area_sqft')) {
            $netWall = (float) $opt['net_wall_area_sqft'];
        } elseif (self::present($opt, 'wall_area_sqft')) {
            $netWall = (float) $opt['wall_area_sqft'];
        }

        $bulkheadDrywall = self::present($opt, 'bulkhead_drywall_sqft')
            ? max(0.0, (float) $opt['bulkhead_drywall_sqft'])
            : 0.0;
        $measuredNet = $netWall !== null;
        if ($netWall === null && $peri !== null && $peri > 0) {
            $netWall = $peri * $height;
        }
        $wallSf = $netWall !== null ? $netWall + $bulkheadDrywall : null;

        $insWall = null;
        if (self::present($opt, 'finish_insulation_sqft')) {
            $insWall = (float) $opt['finish_insulation_sqft'];
        } elseif (self::present($opt, 'net_wall_area_sqft')) {
            $insWall = (float) $opt['net_wall_area_sqft'];
        } elseif (self::present($opt, 'wall_area_sqft')) {
            $insWall = (float) $opt['wall_area_sqft'];
        }

        $partition = null;
        if (self::present($opt, 'finish_stud_lf')) {
            $partition = (float) $opt['finish_stud_lf'];
        } elseif (self::present($opt, 'partition_lf')) {
            $partition = (float) $opt['partition_lf'];
        }

        $baseboard = null;
        if (self::present($opt, 'finish_baseboard_lf')) {
            $baseboard = (float) $opt['finish_baseboard_lf'];
        } elseif (self::present($opt, 'baseboard_lf')) {
            $baseboard = (float) $opt['baseboard_lf'];
        }

        $bulkhead = null;
        if (self::present($opt, 'bulkhead_lf')) {
            $bulkhead = (float) $opt['bulkhead_lf'];
        } elseif (self::present($opt, 'bulkhead_framing_lf')) {
            $bulkhead = (float) $opt['bulkhead_framing_lf'];
        }

        $doors = self::present($opt, 'door_count') ? max(0, (int) $opt['door_count']) : null;
        $windows = self::present($opt, 'window_count') ? max(0, (int) $opt['window_count']) : null;
        $openings = self::present($opt, 'opening_count') ? max(0, (int) $opt['opening_count']) : null;

        $casingOpenings = null;
        if ($doors !== null || $openings !== null) {
            $casingOpenings = ($doors ?? 0) + ($openings ?? 0);
        }

        $casingLf = null;
        if (self::present($opt, 'finish_casing_lf')) {
            $casingLf = (float) $opt['finish_casing_lf'];
        } elseif (self::present($opt, 'casing_lf')) {
            $casingLf = (float) $opt['casing_lf'];
        }
        $paintTrim = null;
        if ($baseboard !== null || $casingLf !== null) {
            $paintTrim = max(0.0, ($baseboard ?? 0.0) + ($casingLf ?? 0.0));
        }

        $openingSf = $measuredNet ? 0.0 : null;

        return [
            'area_sqft' => $area,
            'ceiling_height_ft' => self::present($opt, 'ceiling_height_ft') ? $height : null,
            'room_count' => self::present($opt, 'polygon_count') ? max(0, (int) $opt['polygon_count']) : null,
            'perimeter_lf' => $peri,
            'partition_lf' => $partition,
            'bulkhead_lf' => $bulkhead,
            'framing_door_openings' => $doors,
            'insulation_wall_sf' => $insWall,
            'insulation_ceiling_sf' => $ceilingArea,
            'ceiling_sf' => $ceilingArea,
            'drywall_wall_sf' => $wallSf,
            'drywall_ceiling_sf' => $ceilingArea,
            'drywall_door_openings' => $doors,
            'drywall_window_openings' => $windows,
            'drywall_openings' => $openings,
            'drywall_opening_sf' => $openingSf,
            'drywall_corners' => self::present($opt, 'outside_corners') ? max(0, (int) $opt['outside_corners']) : null,
            'drywall_inside_corners' => self::present($opt, 'inside_corners') ? max(0, (int) $opt['inside_corners']) : null,
            'flooring_sf' => $area,
            'door_qty' => $doors,
            'baseboard_lf' => $baseboard,
            'casing_openings' => $casingOpenings,
            'window_trim_qty' => $windows,
            'paint_wall_sf' => self::resolveImportedPaintWallSf($opt, $wallSf),
            'paint_ceiling_sf' => $ceilingArea,
            'paint_trim_lf' => $paintTrim,
        ];
    }
}

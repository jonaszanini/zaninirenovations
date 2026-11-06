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

    public static function present(array $opt, string $key): bool
    {
        return array_key_exists($key, $opt) && $opt[$key] !== null && $opt[$key] !== '';
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
        $ceilingArea = null;
        if (self::present($opt, 'ceiling_area_sqft') && (float) $opt['ceiling_area_sqft'] > 0) {
            $ceilingArea = (float) $opt['ceiling_area_sqft'];
        } elseif ($area !== null && $area > 0) {
            $ceilingArea = $area;
        }

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

        $paintTrim = null;
        if (self::present($opt, 'casing_lf') || self::present($opt, 'finish_casing_lf')) {
            $casingLf = self::present($opt, 'finish_casing_lf')
                ? (float) $opt['finish_casing_lf']
                : (float) $opt['casing_lf'];
            $paintTrim = max(0.0, ($baseboard ?? 0.0) + $casingLf);
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
            'paint_wall_sf' => $wallSf,
            'paint_ceiling_sf' => $ceilingArea,
            'paint_trim_lf' => $paintTrim,
        ];
    }
}

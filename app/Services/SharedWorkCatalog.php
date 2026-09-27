<?php

namespace App\Services;

use App\Models\BasementSettings;
use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Models\TrimmingSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SharedWorkCatalog
{
    /** @var Collection<string, SharedWork>|null */
    private static ?Collection $worksByKey = null;

    public const GROUPS = [
        'flooring' => 'Flooring',
        'trim' => 'Trim',
        'doors' => 'Doors',
        'demolition' => 'Demolition',
        'painting' => 'Painting',
        'drywall' => 'Drywall',
        'insulation' => 'Insulation',
        'tile' => 'Tile',
    ];

    public const CONSUMER_LABELS = [
        'flooring' => 'Flooring',
        'trimming' => 'Trimming',
        'basement' => 'Basement',
        'painting' => 'Painting',
        'shed' => 'Shed',
        'tile' => 'Tile',
        'millwork' => 'Millwork',
    ];

    /**
     * Canonical shared works. Seed + admin + overlay all read this map.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $basementProd = BasementSettings::defaultProductivity();
        $basementMat = BasementSettings::defaultMaterials();
        $trimProd = TrimmingSettings::defaultProductivity();
        $trimMat = TrimmingSettings::defaultMaterials();

        return [
            'remove_floor' => [
                'group_key' => 'demolition',
                'label' => 'Remove existing floor / carpet',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['demo_floor_sf_per_hr'],
                'flat_rate' => 1.50,
                'consumers' => ['basement', 'flooring'],
                'productivity_keys' => [
                    'basement' => ['demo_floor_sf_per_hr'],
                ],
                'flat_keys' => [
                    'flooring' => ['remove_carpet_dispose_rate'],
                ],
            ],
            'flooring_install' => [
                'group_key' => 'flooring',
                'label' => 'Install flooring',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['flooring_sf_per_hr'],
                'flat_rate' => 4.25,
                'consumers' => ['basement', 'flooring'],
                'productivity_keys' => [
                    'basement' => ['flooring_sf_per_hr'],
                ],
            ],
            'underlayment' => [
                'group_key' => 'flooring',
                'label' => 'Install underlayment',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['underlayment_sf_per_hr'],
                'flat_rate' => self::defaultLinearRateFromProductivity((float) $basementProd['underlayment_sf_per_hr']),
                'material' => $basementMat['underlayment'],
                'material_keys' => [
                    'basement' => ['underlayment'],
                ],
                'consumers' => ['basement', 'flooring'],
                'productivity_keys' => [
                    'basement' => ['underlayment_sf_per_hr'],
                ],
            ],
            'subfloor' => [
                'group_key' => 'flooring',
                'label' => 'Install subfloor',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['subfloor_sf_per_hr'],
                'flat_rate' => self::defaultLinearRateFromProductivity((float) $basementProd['subfloor_sf_per_hr']),
                'material' => $basementMat['subfloor_panel'],
                'material_keys' => [
                    'basement' => ['subfloor_panel'],
                ],
                'consumers' => ['basement'],
                'productivity_keys' => [
                    'basement' => ['subfloor_sf_per_hr'],
                ],
            ],
            'transition' => [
                'group_key' => 'flooring',
                'label' => 'Install transition',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => (float) $basementProd['transition_hours_each'],
                'material' => $basementMat['transition'],
                'material_keys' => [
                    'basement' => ['transition'],
                ],
                'consumers' => ['basement', 'flooring'],
                'productivity_keys' => [
                    'basement' => ['transition_hours_each'],
                ],
            ],
            'shoe_install' => [
                'group_key' => 'trim',
                'label' => 'Install shoe moulding',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['shoe_lf_per_hr'],
                'flat_rate' => 3.90,
                'material' => $trimMat['shoe'],
                'material_keys' => [
                    'trimming' => ['shoe'],
                    'basement' => ['shoe'],
                ],
                'consumers' => ['trimming', 'flooring', 'basement'],
                'productivity_keys' => [
                    'trimming' => ['shoe_lf_per_hr'],
                    'basement' => ['shoe_lf_per_hr'],
                ],
                'flat_keys' => [
                    'flooring' => ['install_shoe_moulding_rate'],
                ],
            ],
            'shoe_caulk_paint' => [
                'group_key' => 'trim',
                'label' => 'Caulk & paint shoe moulding',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['caulk_paint_lf_per_hr'],
                'flat_rate' => 3.25,
                'consumers' => ['trimming', 'flooring', 'basement'],
                'productivity_keys' => [
                    'trimming' => ['caulk_paint_lf_per_hr'],
                ],
                'flat_keys' => [
                    'flooring' => ['caulk_paint_shoe_moulding_rate'],
                ],
            ],
            'baseboard' => [
                'group_key' => 'trim',
                'label' => 'Install baseboard',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['baseboard_lf_per_hr'],
                'flat_rate' => 4.75,
                'material' => $basementMat['baseboard'],
                'material_keys' => [
                    'basement' => ['baseboard'],
                    'trimming' => ['baseboard'],
                ],
                'consumers' => ['basement', 'trimming', 'flooring'],
                'productivity_keys' => [
                    'basement' => ['baseboard_lf_per_hr'],
                    'trimming' => ['baseboard_lf_per_hr'],
                ],
            ],
            'baseboard_caulk_paint' => [
                'group_key' => 'trim',
                'label' => 'Caulk & paint baseboard',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['caulk_paint_lf_per_hr'],
                'flat_rate' => 3.25,
                'consumers' => ['flooring'],
            ],
            'casing_lf' => [
                'group_key' => 'trim',
                'label' => 'Install casing (linear feet)',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['casing_lf_per_hr'],
                'flat_rate' => 6.10,
                'material' => $trimMat['casing'],
                'material_keys' => [
                    'trimming' => ['casing'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['casing_lf_per_hr'],
                ],
            ],
            'casing_opening' => [
                'group_key' => 'trim',
                'label' => 'Install casing (per opening)',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => (float) $basementProd['casing_opening_hours'],
                'material' => $basementMat['casing'],
                'material_keys' => [
                    'basement' => ['casing'],
                ],
                'consumers' => ['basement'],
                'productivity_keys' => [
                    'basement' => ['casing_opening_hours'],
                ],
            ],
            'window_trim' => [
                'group_key' => 'trim',
                'label' => 'Install window trim',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => (float) $basementProd['window_trim_hours_each'],
                'material' => $basementMat['window_trim'],
                'material_keys' => [
                    'basement' => ['window_trim'],
                ],
                'consumers' => ['basement'],
                'productivity_keys' => [
                    'basement' => ['window_trim_hours_each'],
                ],
            ],
            'door_install' => [
                'group_key' => 'doors',
                'label' => 'Hang pre-hung interior door',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(235.00),
                'material' => $basementMat['interior_door'],
                'material_keys' => [
                    'basement' => ['interior_door'],
                    'trimming' => ['interior_door'],
                ],
                'consumers' => ['basement', 'trimming'],
                'productivity_keys' => [
                    'basement' => ['door_install_hours_each'],
                    'trimming' => ['door_install_hours_each'],
                ],
            ],
            'door_slab_swap' => [
                'group_key' => 'doors',
                'label' => 'Interior door slab swap',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(205.00),
                'material' => $trimMat['door_slab'] ?? $basementMat['interior_door'],
                'material_keys' => [
                    'trimming' => ['door_slab'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['door_slab_hours_each'],
                ],
            ],
            'pocket_door' => [
                'group_key' => 'doors',
                'label' => 'Pocket door (retrofit)',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(2110.00),
                'material' => $trimMat['pocket_door'] ?? [],
                'material_keys' => [
                    'trimming' => ['pocket_door'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['pocket_door_hours_each'],
                ],
            ],
            'french_door' => [
                'group_key' => 'doors',
                'label' => 'French / double door pair',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(665.00),
                'material' => $trimMat['french_door'] ?? [],
                'material_keys' => [
                    'trimming' => ['french_door'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['french_door_hours_each'],
                ],
            ],
            'stool_apron' => [
                'group_key' => 'trim',
                'label' => 'Window stool & apron',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(214.00),
                'material' => $trimMat['stool_apron'],
                'material_keys' => [
                    'trimming' => ['stool_apron'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['stool_apron_hours_each'],
                ],
            ],
            'jamb_extension' => [
                'group_key' => 'trim',
                'label' => 'Custom jamb extension',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(136.00),
                'material' => $trimMat['jamb_extension'],
                'material_keys' => [
                    'trimming' => ['jamb_extension'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['jamb_hours_each'],
                ],
            ],
            'window_picture_frame' => [
                'group_key' => 'trim',
                'label' => 'Picture-frame window casing',
                'unit' => 'each',
                'labor_kind' => SharedWork::KIND_HOURS_EACH,
                'rate' => self::hoursFromLaborAmount(170.00),
                'material' => $trimMat['window_picture_frame'] ?? [],
                'material_keys' => [
                    'trimming' => ['window_picture_frame'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['window_picture_frame_hours_each'],
                ],
            ],
            'quarter_round' => [
                'group_key' => 'trim',
                'label' => 'Install quarter round',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['quarter_round_lf_per_hr'],
                'flat_rate' => 3.90,
                'material' => $trimMat['quarter_round'],
                'material_keys' => [
                    'trimming' => ['quarter_round'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['quarter_round_lf_per_hr'],
                ],
            ],
            'base_cap' => [
                'group_key' => 'trim',
                'label' => 'Install base cap',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['base_cap_lf_per_hr'],
                'flat_rate' => 5.10,
                'material' => $trimMat['base_cap'],
                'material_keys' => [
                    'trimming' => ['base_cap'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['base_cap_lf_per_hr'],
                ],
            ],
            'built_up_baseboard' => [
                'group_key' => 'trim',
                'label' => 'Install built-up / hardwood baseboard',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => 0,
                'flat_rate' => 7.25,
                'material' => $trimMat['built_up_baseboard'] ?? [],
                'material_keys' => [
                    'trimming' => ['built_up_baseboard'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['built_up_baseboard_lf_per_hr'],
                ],
            ],
            'crown' => [
                'group_key' => 'trim',
                'label' => 'Install crown moulding',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['crown_lf_per_hr'],
                'flat_rate' => 6.60,
                'material' => $trimMat['crown'],
                'material_keys' => [
                    'trimming' => ['crown'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['crown_lf_per_hr'],
                ],
            ],
            'cove' => [
                'group_key' => 'trim',
                'label' => 'Install cove moulding',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['cove_lf_per_hr'],
                'flat_rate' => 6.60,
                'material' => $trimMat['cove'],
                'material_keys' => [
                    'trimming' => ['cove'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['cove_lf_per_hr'],
                ],
            ],
            'built_up_crown' => [
                'group_key' => 'trim',
                'label' => 'Install built-up / complex crown',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => 0,
                'flat_rate' => 12.90,
                'material' => $trimMat['built_up_crown'] ?? [],
                'material_keys' => [
                    'trimming' => ['built_up_crown'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['built_up_crown_lf_per_hr'],
                ],
            ],
            'chair_rail' => [
                'group_key' => 'trim',
                'label' => 'Install chair rail',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['chair_rail_lf_per_hr'],
                'flat_rate' => 5.10,
                'material' => $trimMat['chair_rail'],
                'material_keys' => [
                    'trimming' => ['chair_rail'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['chair_rail_lf_per_hr'],
                ],
            ],
            'picture_rail' => [
                'group_key' => 'trim',
                'label' => 'Install picture rail',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['picture_rail_lf_per_hr'],
                'flat_rate' => 5.10,
                'material' => $trimMat['picture_rail'],
                'material_keys' => [
                    'trimming' => ['picture_rail'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['picture_rail_lf_per_hr'],
                ],
            ],
            'box_moulding' => [
                'group_key' => 'trim',
                'label' => 'Install picture-frame / box moulding',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['box_moulding_lf_per_hr'],
                'flat_rate' => 19.90,
                'material' => $trimMat['box_moulding'],
                'material_keys' => [
                    'trimming' => ['box_moulding'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['box_moulding_lf_per_hr'],
                ],
            ],
            'remove_trim' => [
                'group_key' => 'demolition',
                'label' => 'Remove existing trim',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['remove_lf_per_hr'],
                'flat_rate' => 1.23,
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['remove_lf_per_hr'],
                ],
            ],
            'stain_trim' => [
                'group_key' => 'painting',
                'label' => 'Stain & clear coat trim',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => 0,
                'flat_rate' => 4.95,
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['stain_lf_per_hr'],
                ],
            ],
            'wainscot_beadboard' => [
                'group_key' => 'trim',
                'label' => 'Beadboard wainscoting',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['wainscot_beadboard_sf_per_hr'],
                'flat_rate' => 10.20,
                'material' => $trimMat['beadboard_sheet'],
                'material_keys' => [
                    'trimming' => ['beadboard_sheet'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['wainscot_beadboard_sf_per_hr'],
                ],
            ],
            'wainscot_bnb' => [
                'group_key' => 'trim',
                'label' => 'Board & batten wainscoting',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['wainscot_bnb_sf_per_hr'],
                'flat_rate' => 10.20,
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['wainscot_bnb_sf_per_hr'],
                ],
            ],
            'wainscot_raised' => [
                'group_key' => 'trim',
                'label' => 'Raised-panel wainscoting',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $trimProd['wainscot_raised_sf_per_hr'],
                'flat_rate' => 25.50,
                'material' => $trimMat['raised_panel'],
                'material_keys' => [
                    'trimming' => ['raised_panel'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['wainscot_raised_sf_per_hr'],
                ],
            ],
            'wainscot_shaker' => [
                'group_key' => 'trim',
                'label' => 'Shaker-style wall paneling',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => 0,
                'flat_rate' => 15.60,
                'material' => $trimMat['shaker_panel'] ?? [],
                'material_keys' => [
                    'trimming' => ['shaker_panel'],
                ],
                'consumers' => ['trimming'],
                'productivity_keys' => [
                    'trimming' => ['wainscot_shaker_sf_per_hr'],
                ],
            ],
            'paint_walls' => [
                'group_key' => 'painting',
                'label' => 'Paint walls',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['paint_wall_sf_per_hr'],
                'flat_rate' => 2.51,
                'material' => $basementMat['paint_1gal'],
                'material_keys' => [
                    'basement' => ['paint_1gal'],
                ],
                'consumers' => ['basement', 'painting', 'shed'],
                'productivity_keys' => [
                    'basement' => ['paint_wall_sf_per_hr'],
                    'shed' => ['paint_sf_per_hr'],
                ],
                'flat_keys' => [
                    'painting' => ['labor_per_sqf'],
                ],
            ],
            'paint_ceiling' => [
                'group_key' => 'painting',
                'label' => 'Paint ceiling',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['paint_ceiling_sf_per_hr'],
                'flat_rate' => self::defaultLinearRateFromProductivity((float) $basementProd['paint_ceiling_sf_per_hr']),
                'material' => $basementMat['paint_1gal'],
                'consumers' => ['basement'],
                'productivity_keys' => [
                    'basement' => ['paint_ceiling_sf_per_hr'],
                ],
            ],
            'paint_trim' => [
                'group_key' => 'painting',
                'label' => 'Paint / caulk trim',
                'unit' => 'lf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['paint_trim_lf_per_hr'],
                'flat_rate' => 3.25,
                'consumers' => ['basement'],
                'productivity_keys' => [
                    'basement' => ['paint_trim_lf_per_hr'],
                ],
            ],
            'drywall_hang' => [
                'group_key' => 'drywall',
                'label' => 'Hang drywall',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['drywall_hang_sf_per_hr'],
                'flat_rate' => self::defaultLinearRateFromProductivity((float) $basementProd['drywall_hang_sf_per_hr']),
                'material' => $basementMat['drywall_sheet'],
                'material_keys' => [
                    'basement' => ['drywall_sheet'],
                    'shed' => ['drywall_sheet'],
                ],
                'consumers' => ['basement', 'shed'],
                'productivity_keys' => [
                    'basement' => ['drywall_hang_sf_per_hr'],
                    'shed' => ['drywall_hang_sf_per_hr'],
                ],
            ],
            'drywall_finish' => [
                'group_key' => 'drywall',
                'label' => 'Finish drywall (level 4 / default)',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['drywall_finish_l4_sf_per_hr'],
                'flat_rate' => self::defaultLinearRateFromProductivity((float) $basementProd['drywall_finish_l4_sf_per_hr']),
                'consumers' => ['basement', 'shed'],
                'productivity_keys' => [
                    'basement' => ['drywall_finish_l4_sf_per_hr'],
                    'shed' => ['drywall_finish_sf_per_hr'],
                ],
            ],
            'insulation' => [
                'group_key' => 'insulation',
                'label' => 'Install insulation',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['insulation_wall_sf_per_hr'],
                'flat_rate' => self::defaultLinearRateFromProductivity((float) $basementProd['insulation_wall_sf_per_hr']),
                'material' => $basementMat['insulation_batt'],
                'material_keys' => [
                    'basement' => ['insulation_batt'],
                    'shed' => ['insulation_batt'],
                ],
                'consumers' => ['basement', 'shed'],
                'productivity_keys' => [
                    'basement' => ['insulation_wall_sf_per_hr'],
                    'shed' => ['insulation_sf_per_hr'],
                ],
            ],
            'tile_install' => [
                'group_key' => 'tile',
                'label' => 'Install tile',
                'unit' => 'sf',
                'labor_kind' => SharedWork::KIND_FLAT,
                'rate' => (float) $basementProd['tile_sf_per_hr'],
                'flat_rate' => 12.0,
                'material' => $basementMat['tile'],
                'material_keys' => [
                    'basement' => ['tile'],
                ],
                'consumers' => ['basement', 'tile'],
                'productivity_keys' => [
                    'basement' => ['tile_sf_per_hr'],
                ],
            ],
        ];
    }

    public static function definition(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }

    public static function work(string $key): ?SharedWork
    {
        return self::worksByKey()->get($key);
    }

    /**
     * @return Collection<int, SharedWork>
     */
    public static function allWorks(): Collection
    {
        return self::worksByKey()->sortBy([
            ['sort_order', 'asc'],
            ['label', 'asc'],
        ])->values();
    }

    public static function forgetCache(): void
    {
        self::$worksByKey = null;
    }

    /**
     * @return Collection<string, SharedWork>
     */
    private static function worksByKey(): Collection
    {
        if (self::$worksByKey instanceof Collection) {
            return self::$worksByKey;
        }

        try {
            if (! Schema::hasTable('shared_works')) {
                self::$worksByKey = collect();

                return self::$worksByKey;
            }
            self::$worksByKey = SharedWork::query()->get()->keyBy('key');
        } catch (\Throwable $e) {
            self::$worksByKey = collect();
        }

        return self::$worksByKey;
    }

    /**
     * Overlay shared labor rates onto a calculator productivity array.
     * Linear- and square-foot works store a sell $/unit; productivity is derived so hours × owner = qty × $/unit.
     *
     * @param  array<string, float|int|string>  $productivity
     * @return array<string, float|int|string>
     */
    public static function overlayProductivity(string $consumer, array $productivity, ?float $ownerHourly = null): array
    {
        foreach (self::definitions() as $key => $def) {
            $work = self::work($key);
            if (! $work) {
                continue;
            }
            $prodKeys = $def['productivity_keys'][$consumer] ?? [];
            foreach ($prodKeys as $prodKey) {
                $productivity[$prodKey] = self::productivityRateForWork($work, $def, $ownerHourly);
            }
        }

        return $productivity;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    public static function productivityRateForWork(SharedWork $work, array $def, ?float $ownerHourly = null): float
    {
        $unit = $work->unit ?: (string) ($def['unit'] ?? '');
        if ($unit === 'lf' || $unit === 'sf') {
            $flat = $work->flat_rate !== null
                ? (float) $work->flat_rate
                : (float) ($def['flat_rate'] ?? 0);
            if ($flat > 0) {
                $owner = $ownerHourly ?? self::ownerHourlyRate();
                if ($owner > 0) {
                    return $owner / $flat;
                }
            }
        }

        return (float) $work->rate;
    }

    public static function defaultLinearRateFromProductivity(float $lfPerHour, float $ownerHourly = 85.0): float
    {
        if ($lfPerHour <= 0) {
            return 0.0;
        }

        return round($ownerHourly / $lfPerHour, 4);
    }

    public static function hoursFromLaborAmount(float $laborCad, float $ownerHourly = 85.0): float
    {
        if ($ownerHourly <= 0) {
            return 0.0;
        }

        return round($laborCad / $ownerHourly, 4);
    }

    public static function ownerHourlyRate(): float
    {
        try {
            return (float) SharedCrewRate::current()->owner_hourly_rate;
        } catch (\Throwable $e) {
            return 85.0;
        }
    }

    public static function unitDisplayRate(SharedWork $work): float
    {
        if ($work->flat_rate !== null && (float) $work->flat_rate > 0) {
            return (float) $work->flat_rate;
        }
        $rate = (float) $work->rate;
        $owner = self::ownerHourlyRate();
        if ($rate > 0 && $owner > 0) {
            return round($owner / $rate, 4);
        }

        return 0.0;
    }

    public static function linearFootDisplayRate(SharedWork $work): float
    {
        return self::unitDisplayRate($work);
    }

    /**
     * Overlay shared material slots onto a calculator materials array.
     *
     * @param  array<string, array<string, mixed>>  $materials
     * @return array<string, array<string, mixed>>
     */
    public static function overlayMaterials(string $consumer, array $materials): array
    {
        foreach (self::definitions() as $key => $def) {
            $work = self::work($key);
            if (! $work) {
                continue;
            }
            $matKeys = $def['material_keys'][$consumer] ?? [];
            if ($matKeys === []) {
                continue;
            }
            $slot = self::materialSlotFromWork($work, $def);
            foreach ($matKeys as $matKey) {
                $existing = $materials[$matKey] ?? [];
                $materials[$matKey] = array_merge($existing, $slot);
            }
        }

        return $materials;
    }

    /**
     * Flat $/unit used by Flooring / Painting / Tile forms.
     */
    public static function flatRate(string $key, ?float $fallback = null): float
    {
        $work = self::work($key);
        if ($work && $work->flat_rate !== null) {
            return (float) $work->flat_rate;
        }
        $def = self::definition($key);
        if ($def && isset($def['flat_rate'])) {
            return (float) $def['flat_rate'];
        }

        return $fallback ?? 0.0;
    }

    /**
     * Sell $/unit: library flat rate, else effective hours × crew + material, else fallback.
     */
    public static function sellRate(string $key, ?float $fallback = null): float
    {
        $flat = self::flatRate($key, null);
        if ($flat > 0) {
            return $flat;
        }
        $work = self::work($key);
        if ($work) {
            $effective = self::effectiveSellRate($work);
            if ($effective !== null && $effective > 0) {
                return $effective;
            }
        }

        return $fallback ?? 0.0;
    }

    /**
     * Effective sell $/unit at the shared crew owner rate (hours × owner + material).
     */
    public static function effectiveSellRate(SharedWork $work): ?float
    {
        if ($work->isPricedPerArea() || $work->labor_kind === SharedWork::KIND_FLAT) {
            if ($work->flat_rate !== null && (float) $work->flat_rate > 0) {
                return (float) $work->flat_rate;
            }
        }

        $crew = SharedCrewRate::current();
        $owner = (float) $crew->owner_hourly_rate;
        $rate = (float) $work->rate;
        if ($rate <= 0) {
            return $work->flat_rate !== null ? (float) $work->flat_rate : null;
        }

        $labor = $work->labor_kind === SharedWork::KIND_HOURS_EACH
            ? $rate * $owner
            : ($owner / $rate);

        $material = 0.0;
        if ($work->material_unit_price !== null && (float) $work->material_unit_price > 0) {
            $material = (float) $work->material_unit_price;
            if ($work->material_unit === 'stick_8ft' || $work->material_unit === 'stick') {
                $material = $material / 8;
            }
        }

        return round($labor + $material, 2);
    }

    public static function rateLine(string $key): string
    {
        $work = self::work($key);
        $def = self::definition($key);
        if (! $work && ! $def) {
            return '';
        }

        $unit = $work->unit ?? $def['unit'];
        $flat = $work && $work->flat_rate !== null
            ? (float) $work->flat_rate
            : (float) ($def['flat_rate'] ?? 0);
        if ($flat <= 0 && $work && in_array($unit, ['lf', 'sf'], true)) {
            $flat = self::unitDisplayRate($work);
        }
        if ($flat > 0) {
            return '$'.number_format($flat, 2).' / '.$unit;
        }

        if ($work) {
            $effective = self::effectiveSellRate($work);
            if ($effective !== null) {
                return '$'.number_format($effective, 2).' / '.$unit.' effective';
            }
        }

        $rate = $work ? (float) $work->rate : (float) $def['rate'];
        $kind = $work->labor_kind ?? $def['labor_kind'];
        if ($kind === SharedWork::KIND_HOURS_EACH) {
            return number_format($rate, 2).' hr / '.$unit;
        }

        return number_format($rate, 1).' '.$unit.' / hr';
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array{name: string, unit: string, unit_price: float, catalog_material_id: int|null}
     */
    public static function materialSlotFromWork(?SharedWork $work, array $def): array
    {
        $fallback = $def['material'] ?? [
            'name' => '',
            'unit' => 'each',
            'unit_price' => 0.0,
            'catalog_material_id' => null,
        ];

        if (! $work) {
            return $fallback;
        }

        return [
            'name' => $work->material_name ?: ($fallback['name'] ?? ''),
            'unit' => $work->material_unit ?: ($fallback['unit'] ?? 'each'),
            'unit_price' => $work->material_unit_price !== null
                ? (float) $work->material_unit_price
                : (float) ($fallback['unit_price'] ?? 0),
            'catalog_material_id' => $work->catalog_material_id ?: ($fallback['catalog_material_id'] ?? null),
        ];
    }

    /**
     * Productivity keys that now live in the library (hide on type price pages).
     *
     * @return list<string>
     */
    public static function sharedProductivityKeys(string $consumer): array
    {
        $keys = [];
        foreach (self::definitions() as $def) {
            foreach ($def['productivity_keys'][$consumer] ?? [] as $k) {
                $keys[] = $k;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Material keys that now live in the library.
     *
     * @return list<string>
     */
    public static function sharedMaterialKeys(string $consumer): array
    {
        $keys = [];
        foreach (self::definitions() as $def) {
            foreach ($def['material_keys'][$consumer] ?? [] as $k) {
                $keys[] = $k;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    public static function worksForConsumer(string $consumer): array
    {
        $keys = [];
        foreach (self::definitions() as $key => $def) {
            if (in_array($consumer, $def['consumers'], true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function sharedPricesUrl(?string $workKey = null): string
    {
        $url = route('admin.quotations.shared-prices.edit');
        if ($workKey) {
            return $url.'#work-'.$workKey;
        }

        return $url;
    }
}

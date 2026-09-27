<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrimmingSettings extends Model
{
    public const STICK_FEET = 8;

    public const PANEL_SF = 32;

    public const CASING_LF_PER_DOOR = 17;

    public const WINDOW_CASING_LF_PER_WINDOW = 16;

    public const ARCHITRAVE_LF_PER_OPENING = 4;

    public const TRIM_LF_PER_QUART = 100;

    public const CAULK_LF_PER_TUBE = 50;

    protected $fillable = [
        'owner_hourly_rate',
        'helper_hourly_rate',
        'hours_per_day',
        'waste_percent',
        'productivity',
        'materials',
    ];

    protected $casts = [
        'owner_hourly_rate' => 'decimal:2',
        'helper_hourly_rate' => 'decimal:2',
        'hours_per_day' => 'decimal:2',
        'waste_percent' => 'decimal:2',
        'productivity' => 'array',
        'materials' => 'array',
    ];

    public static function current(): self
    {
        $row = static::query()->find(1);
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'id' => 1,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'waste_percent' => 10,
            'productivity' => null,
            'materials' => null,
        ]);
    }

    /**
     * @return array<string, float>
     */
    public static function defaultProductivity(): array
    {
        return [
            'casing_lf_per_hr' => 13.9344,
            'architrave_lf_per_hr' => 14,
            'plinth_hours_each' => 0.15,
            'rosette_hours_each' => 0.12,
            'stool_apron_hours_each' => 2.5176,
            'jamb_hours_each' => 1.6,
            'door_install_hours_each' => 2.7647,
            'door_slab_hours_each' => 2.4118,
            'pocket_door_hours_each' => 24.8235,
            'french_door_hours_each' => 7.8235,
            'window_picture_frame_hours_each' => 2.0,
            'baseboard_lf_per_hr' => 17.8947,
            'base_cap_lf_per_hr' => 16.6667,
            'built_up_baseboard_lf_per_hr' => 11.7241,
            'shoe_lf_per_hr' => 21.7949,
            'quarter_round_lf_per_hr' => 21.7949,
            'crown_lf_per_hr' => 12.8788,
            'cove_lf_per_hr' => 12.8788,
            'built_up_crown_lf_per_hr' => 6.5891,
            'chair_rail_lf_per_hr' => 16.6667,
            'picture_rail_lf_per_hr' => 16.6667,
            'box_moulding_lf_per_hr' => 4.2714,
            'wainscot_beadboard_sf_per_hr' => 8.3333,
            'wainscot_raised_sf_per_hr' => 3.3333,
            'wainscot_bnb_sf_per_hr' => 8.3333,
            'wainscot_shaker_sf_per_hr' => 5.4487,
            'batten_lf_per_hr' => 20,
            'remove_lf_per_hr' => 69.1057,
            'caulk_paint_lf_per_hr' => 26.1538,
            'stain_lf_per_hr' => 17.1717,
        ];
    }

    /**
     * @return array<string, array{name: string, unit: string, unit_price: float, catalog_material_id: int|null}>
     */
    public static function defaultMaterials(): array
    {
        $row = function (string $name, string $unit, float $price): array {
            return [
                'name' => $name,
                'unit' => $unit,
                'unit_price' => $price,
                'catalog_material_id' => null,
            ];
        };

        $stick = function (string $name, float $perLf) use ($row): array {
            return $row($name, 'each', round($perLf * self::STICK_FEET, 4));
        };

        return [
            'baseboard' => $stick('8 ft baseboard (each)', 2.75),
            'base_cap' => $stick('8 ft base cap (each)', 1.40),
            'built_up_baseboard' => $stick('8 ft built-up / hardwood baseboard (each)', 7.00),
            'shoe' => $stick('8 ft shoe moulding (each)', 1.50),
            'quarter_round' => $stick('8 ft quarter round (each)', 1.56),
            'casing' => $stick('8 ft casing (each)', 3.80),
            'architrave' => $stick('8 ft architrave / header (each)', 2.40),
            'crown' => $stick('8 ft crown moulding (each)', 3.95),
            'cove' => $stick('8 ft cove moulding (each)', 3.95),
            'built_up_crown' => $stick('8 ft built-up / complex crown (each)', 11.30),
            'chair_rail' => $stick('8 ft chair rail (each)', 3.10),
            'picture_rail' => $stick('8 ft picture rail (each)', 3.10),
            'box_moulding' => $stick('8 ft picture-frame / box moulding (each)', 3.10),
            'plinth' => $row('Plinth block (each)', 'each', 8.50),
            'rosette' => $row('Rosette / corner block (each)', 'each', 6.50),
            'stool_apron' => $row('Window stool + apron kit (each)', 'each', 63.00),
            'jamb_extension' => $row('Jamb extension set (per opening)', 'each', 49.50),
            'window_picture_frame' => $row('Picture-frame window casing kit (each)', 'each', 41.00),
            'interior_door' => $row('Prehung interior door (each)', 'each', 190.00),
            'door_slab' => $row('Interior door slab, hollow-core (each)', 'each', 106.00),
            'pocket_door' => $row('Pocket door frame + slab (each)', 'each', 650.00),
            'french_door' => $row('French / double door pair (each)', 'each', 1410.00),
            'door_hardware' => $row('Interior door handle set (each)', 'each', 35.00),
            'beadboard_sheet' => $row('Beadboard 4x8 panel (sheet)', 'sheet', 217.60),
            'raised_panel' => $row('Raised-panel wainscot (per sq ft)', 'sf', 17.00),
            'shaker_panel' => $row('Shaker wall paneling (per sq ft)', 'sf', 8.50),
            'batten' => $stick('8 ft batten (each)', 1.60),
            'caulk' => $row('Painter’s caulk (tube)', 'tube', 6.50),
            'fasteners' => $row('Finish nails / adhesive (lump)', 'ls', 77.50),
            'trim_paint_quart' => $row('Trim paint, quart / 946 mL (each)', 'quart', 22.00),
            'trim_paint_half' => $row('Trim paint, 1/2 gal / 1.89 L can (each)', 'can', 38.00),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function resolvedProductivity(): array
    {
        $defaults = self::defaultProductivity();
        $stored = is_array($this->productivity) ? $this->productivity : [];
        foreach ($defaults as $key => $value) {
            if (isset($stored[$key]) && is_numeric($stored[$key])) {
                $defaults[$key] = (float) $stored[$key];
            }
        }

        return \App\Services\SharedWorkCatalog::overlayProductivity('trimming', $defaults, (float) $this->owner_hourly_rate);
    }

    /**
     * @return array<string, array{name: string, unit: string, unit_price: float, catalog_material_id: int|null}>
     */
    public function resolvedMaterials(): array
    {
        $defaults = self::defaultMaterials();
        $stored = is_array($this->materials) ? $this->materials : [];
        foreach ($defaults as $key => $row) {
            if (! isset($stored[$key]) || ! is_array($stored[$key])) {
                continue;
            }
            $catalogId = (int) ($stored[$key]['catalog_material_id'] ?? 0);
            $row['catalog_material_id'] = $catalogId > 0 ? $catalogId : null;

            $storedUnit = trim((string) ($stored[$key]['unit'] ?? ''));
            $unitMatches = $storedUnit === '' || $storedUnit === $row['unit'];
            if ($unitMatches && isset($stored[$key]['unit_price']) && is_numeric($stored[$key]['unit_price'])) {
                $row['unit_price'] = round((float) $stored[$key]['unit_price'], 4);
            }

            if ($catalogId > 0) {
                $name = trim((string) ($stored[$key]['name'] ?? ''));
                if ($name !== '') {
                    $row['name'] = $name;
                }
            }

            $defaults[$key] = $row;
        }

        return \App\Services\SharedWorkCatalog::overlayMaterials('trimming', $defaults);
    }

    public function resolvedWasteFactor(): float
    {
        $pct = max(0, (float) $this->waste_percent);

        return 1 + ($pct / 100);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function productivityGroups(): array
    {
        return [
            'Doors & windows' => [
                'casing_lf_per_hr' => 'Casing (linear ft / hour)',
                'architrave_lf_per_hr' => 'Architrave (linear ft / hour)',
                'plinth_hours_each' => 'Plinth block (hours each)',
                'rosette_hours_each' => 'Rosette (hours each)',
                'stool_apron_hours_each' => 'Window stool + apron (hours each)',
                'jamb_hours_each' => 'Jamb extension (hours / opening)',
                'door_install_hours_each' => 'Pre-hung interior door (hours each)',
                'door_slab_hours_each' => 'Door slab swap (hours each)',
                'pocket_door_hours_each' => 'Pocket door retrofit (hours each)',
                'french_door_hours_each' => 'French / double door pair (hours each)',
                'window_picture_frame_hours_each' => 'Picture-frame window (hours each)',
            ],
            'Floor to wall' => [
                'baseboard_lf_per_hr' => 'Baseboard (linear ft / hour)',
                'base_cap_lf_per_hr' => 'Base cap (linear ft / hour)',
                'built_up_baseboard_lf_per_hr' => 'Built-up baseboard (linear ft / hour)',
                'shoe_lf_per_hr' => 'Shoe moulding (linear ft / hour)',
                'quarter_round_lf_per_hr' => 'Quarter round (linear ft / hour)',
            ],
            'Ceiling to wall' => [
                'crown_lf_per_hr' => 'Crown moulding (linear ft / hour)',
                'cove_lf_per_hr' => 'Cove moulding (linear ft / hour)',
                'built_up_crown_lf_per_hr' => 'Built-up crown (linear ft / hour)',
            ],
            'Wall treatments' => [
                'chair_rail_lf_per_hr' => 'Chair rail (linear ft / hour)',
                'picture_rail_lf_per_hr' => 'Picture rail (linear ft / hour)',
                'box_moulding_lf_per_hr' => 'Picture-frame / box moulding (linear ft / hour)',
                'wainscot_beadboard_sf_per_hr' => 'Beadboard wainscot (sq ft / hour)',
                'wainscot_raised_sf_per_hr' => 'Raised-panel wainscot (sq ft / hour)',
                'wainscot_bnb_sf_per_hr' => 'Board and batten (sq ft / hour)',
                'wainscot_shaker_sf_per_hr' => 'Shaker paneling (sq ft / hour)',
                'batten_lf_per_hr' => 'Battens (linear ft / hour)',
            ],
            'Prep & finish' => [
                'remove_lf_per_hr' => 'Remove existing trim (linear ft / hour)',
                'caulk_paint_lf_per_hr' => 'Caulk, fill & paint (linear ft / hour)',
                'stain_lf_per_hr' => 'Stain & clear coat (linear ft / hour)',
            ],
        ];
    }

    /**
     * @return list<array{key: string, quarts: int}>
     */
    public static function trimPaintCanSizes(): array
    {
        return [
            ['key' => 'trim_paint_half', 'quarts' => 2],
            ['key' => 'trim_paint_quart', 'quarts' => 1],
        ];
    }
}

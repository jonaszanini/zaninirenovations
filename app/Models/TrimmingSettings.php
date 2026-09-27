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
            'casing_lf_per_hr' => 18,
            'architrave_lf_per_hr' => 14,
            'plinth_hours_each' => 0.15,
            'rosette_hours_each' => 0.12,
            'stool_apron_hours_each' => 0.75,
            'jamb_hours_each' => 0.5,
            'door_install_hours_each' => 2,
            'baseboard_lf_per_hr' => 20,
            'base_cap_lf_per_hr' => 22,
            'shoe_lf_per_hr' => 30,
            'quarter_round_lf_per_hr' => 28,
            'crown_lf_per_hr' => 10,
            'cove_lf_per_hr' => 14,
            'chair_rail_lf_per_hr' => 16,
            'picture_rail_lf_per_hr' => 16,
            'box_moulding_lf_per_hr' => 12,
            'wainscot_beadboard_sf_per_hr' => 18,
            'wainscot_raised_sf_per_hr' => 10,
            'wainscot_bnb_sf_per_hr' => 12,
            'batten_lf_per_hr' => 20,
            'remove_lf_per_hr' => 25,
            'caulk_paint_lf_per_hr' => 30,
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
            'baseboard' => $stick('8 ft baseboard (each)', 2.10),
            'base_cap' => $stick('8 ft base cap (each)', 1.40),
            'shoe' => $stick('8 ft shoe moulding (each)', 0.95),
            'quarter_round' => $stick('8 ft quarter round (each)', 0.85),
            'casing' => $stick('8 ft casing (each)', 1.90),
            'architrave' => $stick('8 ft architrave / header (each)', 2.40),
            'crown' => $stick('8 ft crown moulding (each)', 3.20),
            'cove' => $stick('8 ft cove moulding (each)', 2.20),
            'chair_rail' => $stick('8 ft chair rail (each)', 2.00),
            'picture_rail' => $stick('8 ft picture rail (each)', 1.80),
            'box_moulding' => $stick('8 ft picture-frame / box moulding (each)', 1.70),
            'plinth' => $row('Plinth block (each)', 'each', 8.50),
            'rosette' => $row('Rosette / corner block (each)', 'each', 6.50),
            'stool_apron' => $row('Window stool + apron kit (each)', 'each', 28.00),
            'jamb_extension' => $row('Jamb extension set (per opening)', 'each', 22.00),
            'interior_door' => $row('Prehung interior door (each)', 'each', 280.00),
            'door_hardware' => $row('Interior door handle set (each)', 'each', 35.00),
            'beadboard_sheet' => $row('Beadboard 4x8 panel (sheet)', 'sheet', 42.00),
            'raised_panel' => $row('Raised-panel wainscot (per sq ft)', 'sf', 8.50),
            'batten' => $stick('8 ft batten (each)', 1.60),
            'caulk' => $row('Painter’s caulk (tube)', 'tube', 6.50),
            'fasteners' => $row('Finish nails / adhesive (lump)', 'ls', 18.00),
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

        return \App\Services\SharedWorkCatalog::overlayProductivity('trimming', $defaults);
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
                'door_install_hours_each' => 'Interior door (hours each)',
            ],
            'Floor to wall' => [
                'baseboard_lf_per_hr' => 'Baseboard (linear ft / hour)',
                'base_cap_lf_per_hr' => 'Base cap (linear ft / hour)',
                'shoe_lf_per_hr' => 'Shoe moulding (linear ft / hour)',
                'quarter_round_lf_per_hr' => 'Quarter round (linear ft / hour)',
            ],
            'Ceiling to wall' => [
                'crown_lf_per_hr' => 'Crown moulding (linear ft / hour)',
                'cove_lf_per_hr' => 'Cove moulding (linear ft / hour)',
            ],
            'Wall treatments' => [
                'chair_rail_lf_per_hr' => 'Chair rail (linear ft / hour)',
                'picture_rail_lf_per_hr' => 'Picture rail (linear ft / hour)',
                'box_moulding_lf_per_hr' => 'Picture-frame / box moulding (linear ft / hour)',
                'wainscot_beadboard_sf_per_hr' => 'Beadboard wainscot (sq ft / hour)',
                'wainscot_raised_sf_per_hr' => 'Raised-panel wainscot (sq ft / hour)',
                'wainscot_bnb_sf_per_hr' => 'Board and batten (sq ft / hour)',
                'batten_lf_per_hr' => 'Battens (linear ft / hour)',
            ],
            'Prep & finish' => [
                'remove_lf_per_hr' => 'Remove existing trim (linear ft / hour)',
                'caulk_paint_lf_per_hr' => 'Caulk, fill & paint (linear ft / hour)',
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

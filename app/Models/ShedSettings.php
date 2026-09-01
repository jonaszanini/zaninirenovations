<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShedSettings extends Model
{
    public const MATERIAL_LUMBER_2X4 = 'lumber_2x4';

    public const MATERIAL_LUMBER_2X6 = 'lumber_2x6';

    public const MATERIAL_PLATE_2X4 = 'plate_2x4';

    public const MATERIAL_PLATE_2X6 = 'plate_2x6';

    public const PLATE_FEET_PER_BOARD = 8;

    public const PANEL_SF = 32;

    public const BATT_SF_PER_BAG = 40;

    public const VAPOR_SF_PER_ROLL = 1000;

    public const JOINT_COMPOUND_SF_PER_PAIL = 200;

    public const DRYWALL_TAPE_SF_PER_ROLL = 250;

    public const SHINGLE_SF_PER_SQUARE = 100;

    /** Typical architectural bundle (3 bundles ≈ 1 square). */
    public const SHINGLE_SF_PER_BUNDLE = 33.3;

    public const PAINT_SF_PER_GALLON = 350;

    public const PAINT_WASTE = 1.1;

    public const MATERIAL_WASTE = 1.1;

    protected $fillable = [
        'owner_hourly_rate',
        'helper_hourly_rate',
        'hours_per_day',
        'productivity',
        'materials',
    ];

    protected $casts = [
        'owner_hourly_rate' => 'decimal:2',
        'helper_hourly_rate' => 'decimal:2',
        'hours_per_day' => 'decimal:2',
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
            'site_level_sf_per_hr' => 80,
            'pad_sf_per_hr' => 25,
            'pile_hours_each' => 1.5,
            'skid_hours_each' => 0.75,
            'block_hours_each' => 0.4,
            'floor_frame_sf_per_hr' => 40,
            'wall_frame_lf_per_hr' => 10,
            'roof_frame_sf_per_hr' => 20,
            'roof_cover_sf_per_hr' => 35,
            'siding_sf_per_hr' => 30,
            'door_hours_each' => 2.5,
            'window_hours_each' => 1.5,
            'insulation_sf_per_hr' => 40,
            'drywall_hang_sf_per_hr' => 35,
            'drywall_finish_sf_per_hr' => 25,
            'outlet_hours_each' => 0.5,
            'light_hours_each' => 0.6,
            'subpanel_hours' => 6,
            'paint_sf_per_hr' => 80,
            'ramp_hours' => 4,
            'gutter_lf_per_hr' => 12,
            'loft_sf_per_hr' => 20,
            'roof_mult_gable' => 1.0,
            'roof_mult_lean_to' => 1.0,
            'roof_mult_gambrel' => 1.30,
            'roof_mult_saltbox' => 1.15,
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

        return [
            'lumber_2x4' => $row('2x4x8 SPF stud (each)', 'each', 5.50),
            'lumber_2x6' => $row('2x6x8 SPF (each)', 'each', 8.75),
            'plate_2x4' => $row('2x4 plate lumber (lf)', 'lf', round(5.50 / self::PLATE_FEET_PER_BOARD, 4)),
            'plate_2x6' => $row('2x6 plate lumber (lf)', 'lf', round(8.75 / self::PLATE_FEET_PER_BOARD, 4)),
            'osb_58' => $row('5/8" OSB 4x8 (sheet)', 'sheet', 28.00),
            'plywood_34' => $row('3/4" plywood 4x8 (sheet)', 'sheet', 42.00),
            'wall_osb' => $row('7/16" OSB wall sheathing 4x8 (sheet)', 'sheet', 22.00),
            'concrete' => $row('Ready-mix concrete (cubic yard)', 'cy', 185.00),
            'gravel' => $row('Granular A gravel (cubic yard)', 'cy', 55.00),
            'helical_pile' => $row('Helical pile installed (each)', 'each', 325.00),
            'pt_skid' => $row('Pressure-treated 6x6 skid (each)', 'each', 85.00),
            'deck_block' => $row('Deck block (each)', 'each', 12.00),
            'asphalt_shingle' => $row(
                'Asphalt shingles (bundle / 33.3 sq ft)',
                'bundle',
                round(95.00 * self::SHINGLE_SF_PER_BUNDLE / self::SHINGLE_SF_PER_SQUARE, 4)
            ),
            'metal_roof' => $row('Metal roofing (per sq ft)', 'sf', 4.25),
            'underlayment' => $row('Roof underlayment (per sq ft)', 'sf', 0.45),
            'ice_water' => $row('Ice & water shield (per sq ft)', 'sf', 1.10),
            'drip_edge' => $row('Drip edge (lf)', 'lf', 2.40),
            'fascia' => $row('Fascia board (lf)', 'lf', 4.80),
            'ridge_vent' => $row('Ridge vent (lf)', 'lf', 6.50),
            'siding_vinyl' => $row('Vinyl siding (per sq ft)', 'sf', 3.20),
            'siding_bnb' => $row('Board and batten (per sq ft)', 'sf', 5.80),
            'siding_wood' => $row('Wood siding (per sq ft)', 'sf', 6.40),
            'siding_hardie' => $row('Hardie board siding (per sq ft)', 'sf', 7.20),
            'house_wrap' => $row('House wrap (per sq ft)', 'sf', 0.35),
            'corner_trim' => $row('Corner / trim (lf)', 'lf', 4.20),
            'door_custom' => $row('Custom-built shed door (each)', 'each', 380.00),
            'door_prehung' => $row('Pre-hung exterior door (each)', 'each', 420.00),
            'door_rollup' => $row('Roll-up door (each)', 'each', 890.00),
            'window_slider' => $row('Standard slider window (each)', 'each', 185.00),
            'window_transom' => $row('Transom window (each)', 'each', 145.00),
            'insulation_batt' => $row('R-14 batt insulation (bag, ~40 sq ft)', 'bag', round(1.20 * self::BATT_SF_PER_BAG, 4)),
            'vapor_barrier' => $row('6 mil vapor barrier, 10x100 ft roll (each)', 'roll', round(0.25 * self::VAPOR_SF_PER_ROLL, 4)),
            'drywall_sheet' => $row('1/2" drywall 4x8 sheet (each)', 'sheet', 18.00),
            'joint_compound' => $row('17 L all-purpose joint compound (pail)', 'pail', 18.00),
            'drywall_tape' => $row('Drywall paper tape, 250 ft roll (each)', 'roll', 8.00),
            'paint_exterior' => $row('Exterior paint, 1 gal (each)', 'gallon', 62.00),
            'gutter' => $row('Gutter + downspout (lf)', 'lf', 9.50),
            'ramp' => $row('Pressure-treated ramp package (each)', 'each', 180.00),
            'outlet_box' => $row('Electrical box + receptacle + cover (each)', 'each', 8.50),
            'light_fixture' => $row('Light fixture (each)', 'each', 45.00),
            'subpanel' => $row('Subpanel allowance (each)', 'each', 650.00),
            'fasteners' => $row('Nails, screws, hangers (lump)', 'ls', 85.00),
            'loft_osb' => $row('Loft decking 5/8" OSB 4x8 (sheet)', 'sheet', 28.00),
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

        return $defaults;
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
            if ($key === 'asphalt_shingle' && $storedUnit === 'sq' && $row['unit'] !== 'sq') {
                if ($catalogId > 0) {
                    // Linked catalog items are sold per bundle; keep the stored pack price.
                    $unitMatches = true;
                } elseif (isset($stored[$key]['unit_price']) && is_numeric($stored[$key]['unit_price'])) {
                    $row['unit_price'] = round(
                        (float) $stored[$key]['unit_price'] * self::SHINGLE_SF_PER_BUNDLE / self::SHINGLE_SF_PER_SQUARE,
                        4
                    );
                    $unitMatches = false;
                }
            }
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

        return self::applyDerivedMaterialPrices($defaults);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function applyDerivedMaterialPrices(array $rows): array
    {
        $map = [
            self::MATERIAL_PLATE_2X4 => self::MATERIAL_LUMBER_2X4,
            self::MATERIAL_PLATE_2X6 => self::MATERIAL_LUMBER_2X6,
        ];
        foreach ($map as $plateKey => $studKey) {
            if (! isset($rows[$plateKey])) {
                continue;
            }
            $studPrice = round(max(0, (float) ($rows[$studKey]['unit_price'] ?? 0)), 4);
            $plate = $rows[$plateKey];
            $plate['catalog_material_id'] = null;
            $plate['unit'] = 'lf';
            $plate['delivery_cad'] = 0;
            $plate['unit_price'] = $studPrice > 0
                ? round($studPrice / self::PLATE_FEET_PER_BOARD, 4)
                : 0.0;
            $rows[$plateKey] = $plate;
        }

        return $rows;
    }

    public static function isDerivedPlate(string $key): bool
    {
        return $key === self::MATERIAL_PLATE_2X4 || $key === self::MATERIAL_PLATE_2X6;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function productivityGroups(): array
    {
        return [
            'Site & foundation' => [
                'site_level_sf_per_hr' => 'Site leveling (sq ft / hour)',
                'pad_sf_per_hr' => 'Concrete pad (sq ft / hour)',
                'pile_hours_each' => 'Helical pile (hours each)',
                'skid_hours_each' => 'PT skid (hours each)',
                'block_hours_each' => 'Deck block (hours each)',
            ],
            'Floor & walls' => [
                'floor_frame_sf_per_hr' => 'Floor framing (sq ft / hour)',
                'wall_frame_lf_per_hr' => 'Wall framing (linear ft / hour)',
            ],
            'Roof & siding' => [
                'roof_frame_sf_per_hr' => 'Roof framing (sq ft / hour)',
                'roof_cover_sf_per_hr' => 'Roof covering (sq ft / hour)',
                'siding_sf_per_hr' => 'Siding (sq ft / hour)',
            ],
            'Openings' => [
                'door_hours_each' => 'Door install (hours each)',
                'window_hours_each' => 'Window install (hours each)',
            ],
            'Interior & extras' => [
                'insulation_sf_per_hr' => 'Insulation (sq ft / hour)',
                'drywall_hang_sf_per_hr' => 'Hang drywall (sq ft / hour)',
                'drywall_finish_sf_per_hr' => 'Finish drywall (sq ft / hour)',
                'outlet_hours_each' => 'Outlet (hours each)',
                'light_hours_each' => 'Light fixture (hours each)',
                'subpanel_hours' => 'Subpanel (hours)',
                'paint_sf_per_hr' => 'Exterior paint (sq ft / hour)',
                'ramp_hours' => 'Ramp (hours)',
                'gutter_lf_per_hr' => 'Gutters (linear ft / hour)',
                'loft_sf_per_hr' => 'Loft (sq ft / hour)',
            ],
            'Roof area multipliers' => [
                'roof_mult_gable' => 'Gable roof area multiplier',
                'roof_mult_lean_to' => 'Lean-to roof area multiplier',
                'roof_mult_gambrel' => 'Gambrel roof area multiplier',
                'roof_mult_saltbox' => 'Saltbox roof area multiplier',
            ],
        ];
    }
}

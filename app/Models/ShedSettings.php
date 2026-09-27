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

    public const MATERIAL_METAL_BLACK = 'metal_roof_black';

    public const MATERIAL_METAL_GALVANIZED = 'metal_roof_galvanized';

    /** Vicwest UltraVic 36 in × 93 in panel. */
    public const METAL_PANEL_SF = 23.25;

    public const PAINT_SF_PER_GALLON = 350;

    public const WEATHER_COAT_SF_PER_GALLON = 300;

    public const MATERIAL_WEATHER_COAT = 'weather_coat';

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
            'block_excavate_hours_each' => 0.35,
            'floor_frame_sf_per_hr' => 40,
            'wall_frame_lf_per_hr' => 10,
            'roof_frame_sf_per_hr' => 20,
            'roof_cover_sf_per_hr' => 35,
            'siding_sf_per_hr' => 30,
            'house_wrap_sf_per_hr' => 150,
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
            'gravel_34_bag' => $row('3/4" crushed gravel (bag, ~0.5 cu ft)', 'bag', 6.50),
            'helical_pile' => $row('Helical pile installed (each)', 'each', 325.00),
            'pt_skid' => $row('Pressure-treated 6x6 skid (each)', 'each', 85.00),
            'deck_block' => $row('Deck block (each)', 'each', 12.00),
            'asphalt_shingle' => $row(
                'Asphalt shingles (bundle / 33.3 sq ft)',
                'bundle',
                round(95.00 * self::SHINGLE_SF_PER_BUNDLE / self::SHINGLE_SF_PER_SQUARE, 4)
            ),
            self::MATERIAL_METAL_BLACK => $row('Metal roof Black (36×93 panel)', 'panel', 72.28),
            self::MATERIAL_METAL_GALVANIZED => $row('Metal roof Silver (36×93 panel)', 'panel', 63.38),
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
            self::MATERIAL_WEATHER_COAT => $row('Weather coat, 1 gal (each)', 'gallon', 48.00),
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

        return \App\Services\SharedWorkCatalog::overlayProductivity('shed', $defaults);
    }

    /**
     * @return array<string, array{name: string, unit: string, unit_price: float, catalog_material_id: int|null}>
     */
    public function resolvedMaterials(): array
    {
        $defaults = self::defaultMaterials();
        $stored = is_array($this->materials) ? $this->materials : [];
        if (! isset($stored[self::MATERIAL_METAL_BLACK]) && isset($stored['metal_roof']) && is_array($stored['metal_roof'])) {
            $stored[self::MATERIAL_METAL_BLACK] = $stored['metal_roof'];
        }
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

        $defaults = \App\Services\SharedWorkCatalog::overlayMaterials('shed', $defaults);

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

    public static function isMetalRoofPanel(string $key): bool
    {
        return $key === self::MATERIAL_METAL_BLACK || $key === self::MATERIAL_METAL_GALVANIZED;
    }

    public static function weatherCoatGallons(float $sf): int
    {
        if ($sf <= 0 || self::WEATHER_COAT_SF_PER_GALLON <= 0) {
            return 0;
        }

        return max(1, (int) ceil(($sf * self::PAINT_WASTE) / self::WEATHER_COAT_SF_PER_GALLON));
    }

    /**
     * Short “what / where / why” copy for price-configuration info icons.
     *
     * @return array<string, string>
     */
    public static function materialInfoTips(): array
    {
        return [
            'lumber_2x4' => '2×4 studs for walls, and joists when 2×4 floors are selected.',
            'lumber_2x6' => '2×6 boards for floor joists, roof rafters, and 2×6 walls.',
            'plate_2x4' => 'Top and bottom plates on 2×4 walls. Price is the 2×4 stud ÷ 8 ft.',
            'plate_2x6' => 'Top and bottom plates on 2×6 walls. Price is the 2×6 ÷ 8 ft.',
            'osb_58' => '4×8 sheets for the floor deck and roof sheathing.',
            'plywood_34' => 'Thicker 4×8 floor decking when 3/4" plywood is chosen.',
            'wall_osb' => '4×8 wall sheathing under the siding for structure and nailing.',
            'concrete' => 'Ready-mix for a poured concrete pad foundation.',
            'gravel' => 'Granular A base under a poured concrete pad (cubic yards).',
            'gravel_34_bag' => '3/4" crushed gravel bags under each deck block for drainage (one bag per hole).',
            'helical_pile' => 'Ground screw piles the shed sits on (pile foundation).',
            'pt_skid' => 'Pressure-treated 6×6 runners the floor frame sits on.',
            'deck_block' => 'Blocks set in excavated holes on a gravel bed.',
            'asphalt_shingle' => 'Asphalt roof covering. Sold in bundles (~33.3 sq ft), not squares.',
            self::MATERIAL_METAL_BLACK => 'Black steel roof panels (36×93 in) over the roof deck.',
            self::MATERIAL_METAL_GALVANIZED => 'Silver galvanized steel roof panels (36×93 in) over the roof deck.',
            'underlayment' => 'Waterproof layer over the roof sheathing, under shingles or metal.',
            'ice_water' => 'Self-stick membrane at the eaves to stop ice-dam leaks.',
            'drip_edge' => 'Metal flashing on roof edges so water drips clear of the fascia.',
            'fascia' => 'Board on the eave edge. Gutters and drip edge fasten to it.',
            'ridge_vent' => 'Vent along the roof peak to let hot air out.',
            'siding_vinyl' => 'Vinyl cladding on the exterior walls.',
            'siding_bnb' => 'Vertical wall boards with battens covering the joints.',
            'siding_wood' => 'Wood cladding on the exterior walls.',
            'siding_hardie' => 'Fiber-cement wall cladding (Hardie-style), not roof shingles.',
            'house_wrap' => 'Weather barrier. Used under finish siding, or as a temporary roof or wall cover.',
            'corner_trim' => 'Trim at outside corners and around doors and windows.',
            self::MATERIAL_WEATHER_COAT => 'Sealer on roof or wall sheathing until finish covering is installed.',
            'door_custom' => 'Site-built exterior door in the wall opening.',
            'door_prehung' => 'Factory door already in a frame, set in the wall opening.',
            'door_rollup' => 'Overhead door in a wide wall opening.',
            'window_slider' => 'Sliding window in the wall for light and air.',
            'window_transom' => 'Small high window in the wall for extra light.',
            'insulation_batt' => 'Batt insulation in the walls on a finished interior.',
            'vapor_barrier' => 'Plastic sheet on the warm side of the insulation.',
            'drywall_sheet' => 'Interior wall boards on a finished interior.',
            'joint_compound' => 'Mud used to tape and finish drywall joints.',
            'drywall_tape' => 'Paper tape over drywall seams before mudding.',
            'paint_exterior' => 'Exterior paint or stain for wood, Hardie, or board-and-batten.',
            'gutter' => 'Eave gutters and downspouts to carry roof water away.',
            'ramp' => 'Pressure-treated ramp at the door for access.',
            'outlet_box' => 'Interior receptacle box, outlet, and cover.',
            'light_fixture' => 'Light fixture inside or on the shed.',
            'subpanel' => 'Electrical subpanel allowance if the shed is powered.',
            'fasteners' => 'Nails, screws, and hangers used throughout the build.',
            'loft_osb' => '4×8 sheets for a storage loft floor inside the shed.',
        ];
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
                'block_excavate_hours_each' => 'Deck block hole excavate (hours each)',
                'block_hours_each' => 'Deck block set (hours each)',
            ],
            'Floor & walls' => [
                'floor_frame_sf_per_hr' => 'Floor framing (sq ft / hour)',
                'wall_frame_lf_per_hr' => 'Wall framing (linear ft / hour)',
            ],
            'Roof & siding' => [
                'roof_frame_sf_per_hr' => 'Roof framing (sq ft / hour)',
                'roof_cover_sf_per_hr' => 'Roof covering (sq ft / hour)',
                'siding_sf_per_hr' => 'Siding (sq ft / hour)',
                'house_wrap_sf_per_hr' => 'House wrap (sq ft / hour)',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasementSettings extends Model
{
    public const MATERIAL_STUD_2X4 = 'stud_2x4';

    public const MATERIAL_PLATE_2X4 = 'plate_2x4';

    /** 2x4 plates are takeoff in lf; price comes from an 8-ft stud board. */
    public const PLATE_FEET_PER_BOARD = 8;

    public const BASEBOARD_FEET_PER_STICK = 8;

    /** 10 ft × 100 ft roll. */
    public const VAPOR_SF_PER_ROLL = 1000;

    /** 4x8 sheet. */
    public const PANEL_SF = 32;

    public const BATT_SF_PER_BAG = 40;

    public const UNDERLAYMENT_SF_PER_ROLL = 100;

    public const THINSET_SF_PER_BAG = 50;

    public const JOINT_COMPOUND_SF_PER_PAIL = 200;

    public const DRYWALL_TAPE_SF_PER_ROLL = 250;

    public const PAINT_SF_PER_GALLON = 350;

    public const PAINT_WASTE = 1.1;

    /** Linear feet of trim per quart (2 coats folded into takeoff separately). */
    public const TRIM_LF_PER_QUART = 100;

    public const TWO_COLOR_PAINT_FACTOR = 1.2;

    public const TWO_COLOR_DRYWALL_FINISH_FACTOR = 1.15;

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
     * Hours of work per unit (or hours each) for every basement trade.
     *
     * @return array<string, float>
     */
    public static function defaultProductivity(): array
    {
        return [
            'demo_floor_sf_per_hr' => 50,
            'demo_studs_lf_per_hr' => 25,
            'demo_drywall_sf_per_hr' => 40,
            'demo_baseboard_lf_per_hr' => 40,
            'bin_hours_each' => 0.75,
            'framing_lf_per_hr' => 8,
            'bulkhead_lf_per_hr' => 6,
            'header_hours_each' => 2,
            'door_opening_hours_each' => 1.5,
            'insulation_wall_sf_per_hr' => 40,
            'insulation_ceiling_sf_per_hr' => 50,
            'underslab_sf_per_hr' => 30,
            'drywall_hang_sf_per_hr' => 35,
            'drywall_finish_l1_sf_per_hr' => 80,
            'drywall_finish_l2_sf_per_hr' => 50,
            'drywall_finish_l3_sf_per_hr' => 30,
            'drywall_finish_l4_sf_per_hr' => 20,
            'drywall_opening_hours_each' => 0.4,
            'drywall_inside_corner_hours_each' => 0.3,
            'ceiling_drop_sf_per_hr' => 25,
            'subfloor_sf_per_hr' => 40,
            'underlayment_sf_per_hr' => 80,
            'flooring_sf_per_hr' => 25,
            'transition_hours_each' => 0.35,
            'door_install_hours_each' => 2,
            'baseboard_lf_per_hr' => 20,
            'casing_opening_hours' => 1,
            'window_trim_hours_each' => 1,
            'paint_wall_sf_per_hr' => 80,
            'paint_ceiling_sf_per_hr' => 70,
            'paint_trim_lf_per_hr' => 30,
            'paint_two_color_lf_per_hr' => 20,
            'vanity_hours' => 4,
            'pedestal_sink_hours' => 2,
            'toilet_hours' => 2,
            'shower_hours' => 12,
            'tub_hours' => 10,
            'exhaust_fan_hours' => 2,
            'tile_sf_per_hr' => 12,
            'hardware_hours' => 2,
            'cleaning_hours_per_100sf' => 0.5,
            'touchup_hours' => 2,
        ];
    }

    /**
     * Default unit prices used when a catalog material is not linked.
     *
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
            'dump_bin' => $row('Debris bin / dumpster (each)', 'each', 650),
            'stud_2x4' => $row('2x4x8 SPF stud (each)', 'each', 5.50),
            'plate_2x4' => $row('2x4 plate lumber (lf)', 'lf', round(5.50 / self::PLATE_FEET_PER_BOARD, 4)),
            'drywall_sheet' => $row('1/2" drywall 4x8 sheet (each)', 'sheet', 18.00),
            'joint_compound' => $row('17 L all-purpose joint compound (pail)', 'pail', 18.00),
            'drywall_tape' => $row('Drywall paper tape, 250 ft roll (each)', 'roll', 8.00),
            'corner_bead' => $row('8 ft corner bead (each)', 'each', 6.50),
            'insulation_batt' => $row('R-14 batt insulation, 15 in (bag, ~40 sq ft)', 'bag', round(1.20 * self::BATT_SF_PER_BAG, 4)),
            'vapor_barrier' => $row('6 mil vapor barrier, 10x100 ft roll (each)', 'roll', round(0.25 * self::VAPOR_SF_PER_ROLL, 4)),
            'underslab_insulation' => $row('2 in XPS rigid insulation 4x8 (sheet)', 'sheet', round(2.40 * self::PANEL_SF, 4)),
            'vanity' => $row('Bathroom vanity (each)', 'each', 360.00),
            'pedestal_sink' => $row('Pedestal bathroom sink (each)', 'each', 180.00),
            'faucet' => $row('Bathroom faucet (each)', 'each', 90.00),
            'toilet' => $row('Toilet (each)', 'each', 280.00),
            'shower_kit' => $row('Shower frame (each)', 'each', 800.00),
            'showerhead' => $row('Showerhead (each)', 'each', 100.00),
            'tub_kit' => $row('Bathtub (each)', 'each', 750.00),
            'exhaust_fan' => $row('Bathroom exhaust fan (each)', 'each', 180.00),
            'tile' => $row('Ceramic / porcelain tile (per sq ft)', 'sf', 4.50),
            'thinset_grout' => $row('Thinset & grout, 50 lb bag (each)', 'bag', round(1.10 * self::THINSET_SF_PER_BAG, 4)),
            'lvp' => $row('LVP flooring (per sq ft)', 'sf', 3.50),
            'laminate' => $row('Laminate flooring (per sq ft)', 'sf', 2.75),
            'carpet' => $row('Carpet + pad (per sq ft)', 'sf', 3.25),
            'epoxy' => $row('Epoxy floor coating (per sq ft)', 'sf', 4.00),
            'underlayment' => $row('Floor underlayment, 100 sq ft roll (each)', 'roll', round(0.45 * self::UNDERLAYMENT_SF_PER_ROLL, 4)),
            'subfloor_panel' => $row('Subfloor 4x8 panel (sheet)', 'sheet', round(2.80 * self::PANEL_SF, 4)),
            'transition' => $row('Floor transition strip (each)', 'each', 18.00),
            'interior_door' => $row('Prehung interior door (each)', 'each', 280.00),
            'baseboard' => $row('8 ft baseboard (each)', 'each', round(2.10 * self::BASEBOARD_FEET_PER_STICK, 4)),
            'casing' => $row('Door casing set (per opening)', 'opening', 32.00),
            'window_trim' => $row('Window trim kit (per window)', 'each', 45.00),
            'paint_5gal' => $row('Interior paint, 5 gal / 18.9 L pail (each)', 'pail', 240.00),
            'paint_1gal' => $row('Interior paint, 1 gal / 3.78 L can (each)', 'gallon', 55.00),
            'paint_half' => $row('Interior paint, 1/2 gal / 1.89 L can (each)', 'can', 32.00),
            'paint_quart' => $row('Interior paint, quart / 946 mL (each)', 'quart', 18.00),
            'primer_5gal' => $row('Primer, 5 gal / 18.9 L pail (each)', 'pail', 195.00),
            'primer_1gal' => $row('Primer, 1 gal / 3.78 L can (each)', 'gallon', 45.00),
            'primer_half' => $row('Primer, 1/2 gal / 1.89 L can (each)', 'can', 26.00),
            'primer_quart' => $row('Primer, quart / 946 mL (each)', 'quart', 14.00),
            'trim_paint_half' => $row('Trim paint, 1/2 gal / 1.89 L can (each)', 'can', 38.00),
            'trim_paint_quart' => $row('Trim paint, quart / 946 mL (each)', 'quart', 22.00),
            'drop_ceiling' => $row('Drop ceiling tile + grid (per sq ft)', 'sf', 3.50),
            'door_hardware' => $row('Interior door handle set (each)', 'each', 35.00),
            'cleaning' => $row('Final cleaning supplies (lump sum)', 'ls', 75.00),
        ];
    }

    /**
     * Largest-first can sizes for wall / ceiling paint (quarts).
     *
     * @return list<array{key: string, quarts: int}>
     */
    public static function paintCanSizes(): array
    {
        return [
            ['key' => 'paint_5gal', 'quarts' => 20],
            ['key' => 'paint_1gal', 'quarts' => 4],
            ['key' => 'paint_half', 'quarts' => 2],
            ['key' => 'paint_quart', 'quarts' => 1],
        ];
    }

    /**
     * @return list<array{key: string, quarts: int}>
     */
    public static function primerCanSizes(): array
    {
        return [
            ['key' => 'primer_5gal', 'quarts' => 20],
            ['key' => 'primer_1gal', 'quarts' => 4],
            ['key' => 'primer_half', 'quarts' => 2],
            ['key' => 'primer_quart', 'quarts' => 1],
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
     * Plate lumber is the same 8-ft 2x4 as studs, priced per linear foot.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function applyDerivedMaterialPrices(array $rows): array
    {
        if (! isset($rows[self::MATERIAL_PLATE_2X4])) {
            return $rows;
        }

        $studPrice = round(max(0, (float) ($rows[self::MATERIAL_STUD_2X4]['unit_price'] ?? 0)), 4);
        $plate = $rows[self::MATERIAL_PLATE_2X4];
        $plate['catalog_material_id'] = null;
        $plate['unit'] = 'lf';
        $plate['delivery_cad'] = 0;
        $plate['unit_price'] = $studPrice > 0
            ? round($studPrice / self::PLATE_FEET_PER_BOARD, 4)
            : 0.0;
        $rows[self::MATERIAL_PLATE_2X4] = $plate;

        return $rows;
    }

    /**
     * Grouped labels for the price-configuration productivity grid.
     *
     * @return array<string, array<string, string>>
     */
    public static function productivityGroups(): array
    {
        return [
            'Demolition & preparation' => [
                'demo_floor_sf_per_hr' => 'Remove flooring (sq ft / hour)',
                'demo_studs_lf_per_hr' => 'Remove studs (linear ft / hour)',
                'demo_drywall_sf_per_hr' => 'Remove drywall / ceiling (sq ft / hour)',
                'demo_baseboard_lf_per_hr' => 'Remove baseboard (linear ft / hour)',
                'bin_hours_each' => 'Debris bin handling (hours each)',
            ],
            'Framing' => [
                'framing_lf_per_hr' => 'Partition walls (linear ft / hour)',
                'bulkhead_lf_per_hr' => 'Bulkheads / duct boxing (linear ft / hour)',
                'header_hours_each' => 'Header / beam (hours each)',
                'door_opening_hours_each' => 'Door opening framing (hours each)',
            ],
            'Insulation' => [
                'insulation_wall_sf_per_hr' => 'Wall insulation (sq ft / hour)',
                'insulation_ceiling_sf_per_hr' => 'Ceiling insulation (sq ft / hour)',
                'underslab_sf_per_hr' => 'Under-slab insulation (sq ft / hour)',
            ],
            'Drywall & ceiling' => [
                'drywall_hang_sf_per_hr' => 'Hang drywall (sq ft / hour)',
                'drywall_finish_l1_sf_per_hr' => 'Finish Level 1 (sq ft / hour)',
                'drywall_finish_l2_sf_per_hr' => 'Finish Level 2 (sq ft / hour)',
                'drywall_finish_l3_sf_per_hr' => 'Finish Level 3 (sq ft / hour)',
                'drywall_finish_l4_sf_per_hr' => 'Finish Level 4 (sq ft / hour)',
                'drywall_opening_hours_each' => 'Cut & finish openings (hours each)',
                'drywall_inside_corner_hours_each' => 'Tape inside corners (hours each)',
                'ceiling_drop_sf_per_hr' => 'Drop ceiling (sq ft / hour)',
            ],
            'Flooring' => [
                'subfloor_sf_per_hr' => 'Subfloor prep (sq ft / hour)',
                'underlayment_sf_per_hr' => 'Underlayment (sq ft / hour)',
                'flooring_sf_per_hr' => 'Flooring install (sq ft / hour)',
                'transition_hours_each' => 'Transition strip (hours each)',
            ],
            'Doors, trim & paint' => [
                'door_install_hours_each' => 'Interior door (hours each)',
                'baseboard_lf_per_hr' => 'Baseboard (linear ft / hour)',
                'casing_opening_hours' => 'Door casing (hours / opening)',
                'window_trim_hours_each' => 'Window trim (hours each)',
                'paint_wall_sf_per_hr' => 'Paint walls (sq ft / hour)',
                'paint_ceiling_sf_per_hr' => 'Paint ceiling (sq ft / hour)',
                'paint_trim_lf_per_hr' => 'Paint trim (linear ft / hour)',
                'paint_two_color_lf_per_hr' => 'Tape / cut-in two colors (linear ft / hour)',
            ],
            'Bathroom & finishing' => [
                'vanity_hours' => 'Vanity install (hours)',
                'pedestal_sink_hours' => 'Pedestal sink install (hours)',
                'toilet_hours' => 'Toilet install (hours)',
                'shower_hours' => 'Shower install (hours)',
                'tub_hours' => 'Tub install (hours)',
                'exhaust_fan_hours' => 'Exhaust fan (hours)',
                'tile_sf_per_hr' => 'Bathroom tile (sq ft / hour)',
                'hardware_hours' => 'Hardware install (hours)',
                'cleaning_hours_per_100sf' => 'Final cleaning (hours / 100 sq ft)',
                'touchup_hours' => 'Touch-ups (hours)',
            ],
        ];
    }
}

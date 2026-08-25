<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasementSettings extends Model
{
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
            'demo_drywall_sf_per_hr' => 40,
            'bin_hours_each' => 0.75,
            'framing_lf_per_hr' => 8,
            'bulkhead_lf_per_hr' => 6,
            'header_hours_each' => 2,
            'door_opening_hours_each' => 1.5,
            'outlet_hours_each' => 0.4,
            'switch_hours_each' => 0.35,
            'light_hours_each' => 0.5,
            'panel_upgrade_hours' => 8,
            'detector_hours_each' => 0.4,
            'wiring_run_hours_each' => 0.6,
            'plumbing_bath_rough_hours' => 16,
            'sink_hours' => 4,
            'laundry_hours' => 6,
            'wet_bar_hours' => 8,
            'floor_drain_hours' => 4,
            'wh_reloc_hours' => 6,
            'insulation_wall_sf_per_hr' => 40,
            'insulation_ceiling_sf_per_hr' => 50,
            'underslab_sf_per_hr' => 30,
            'drywall_hang_sf_per_hr' => 35,
            'drywall_finish_l1_sf_per_hr' => 80,
            'drywall_finish_l2_sf_per_hr' => 50,
            'drywall_finish_l3_sf_per_hr' => 30,
            'drywall_finish_l4_sf_per_hr' => 20,
            'drywall_opening_hours_each' => 0.4,
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
            'vanity_hours' => 4,
            'toilet_hours' => 2,
            'shower_hours' => 12,
            'tub_hours' => 10,
            'exhaust_fan_hours' => 2,
            'tile_sf_per_hr' => 12,
            'fixture_hours_each' => 0.4,
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
            'dump_bin' => $row('Debris bin / dumpster', 'each', 650),
            'stud_2x4' => $row('2x4 stud', 'each', 5.50),
            'plate_2x4' => $row('2x4 plate lumber', 'lf', 1.85),
            'drywall_sheet' => $row('1/2" drywall sheet 4x8', 'sheet', 18.00),
            'joint_compound' => $row('Joint compound pail', 'pail', 18.00),
            'drywall_tape' => $row('Drywall tape roll', 'roll', 8.00),
            'corner_bead' => $row('Corner bead', 'each', 6.50),
            'insulation_batt' => $row('R-14 batt insulation', 'sf', 1.20),
            'vapor_barrier' => $row('6 mil vapor barrier', 'sf', 0.25),
            'underslab_insulation' => $row('Under-slab rigid insulation', 'sf', 2.40),
            'outlet_box' => $row('Electrical box + device', 'each', 8.50),
            'switch_device' => $row('Switch + cover', 'each', 7.50),
            'pot_light' => $row('Recessed / pot light', 'each', 28.00),
            'smoke_co' => $row('Smoke / CO detector', 'each', 45.00),
            'nm_cable' => $row('NMD90 cable', 'ft', 1.20),
            'panel_upgrade' => $row('Panel upgrade allowance', 'each', 1800.00),
            'bath_rough_in' => $row('Bathroom rough-in kit', 'each', 850.00),
            'sink_kit' => $row('Sink / laundry hookup kit', 'each', 220.00),
            'floor_drain' => $row('Floor drain assembly', 'each', 95.00),
            'vanity' => $row('Vanity + faucet', 'each', 450.00),
            'toilet' => $row('Toilet', 'each', 280.00),
            'shower_kit' => $row('Shower kit', 'each', 900.00),
            'tub_kit' => $row('Tub kit', 'each', 750.00),
            'exhaust_fan' => $row('Bath exhaust fan', 'each', 180.00),
            'tile' => $row('Ceramic / porcelain tile', 'sf', 4.50),
            'thinset_grout' => $row('Thinset & grout', 'sf', 1.10),
            'lvp' => $row('LVP flooring', 'sf', 3.50),
            'laminate' => $row('Laminate flooring', 'sf', 2.75),
            'carpet' => $row('Carpet + pad', 'sf', 3.25),
            'epoxy' => $row('Epoxy floor coating', 'sf', 4.00),
            'underlayment' => $row('Floor underlayment', 'sf', 0.45),
            'subfloor_panel' => $row('Subfloor / sleepers', 'sf', 2.80),
            'transition' => $row('Floor transition strip', 'each', 18.00),
            'interior_door' => $row('Prehung interior door', 'each', 280.00),
            'baseboard' => $row('Baseboard', 'lf', 2.10),
            'casing' => $row('Door casing', 'opening', 32.00),
            'window_trim' => $row('Window trim kit', 'each', 45.00),
            'paint_gallon' => $row('Interior paint', 'gallon', 55.00),
            'primer_gallon' => $row('Primer', 'gallon', 45.00),
            'drop_ceiling' => $row('Drop ceiling grid + tile', 'sf', 3.50),
            'light_fixture' => $row('Lighting fixture', 'each', 85.00),
            'door_hardware' => $row('Door hardware set', 'each', 35.00),
            'cleaning' => $row('Final cleaning supplies', 'ls', 75.00),
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
            $name = trim((string) ($stored[$key]['name'] ?? ''));
            if ($name !== '') {
                $row['name'] = $name;
            }
            $unit = trim((string) ($stored[$key]['unit'] ?? ''));
            if ($unit !== '') {
                $row['unit'] = $unit;
            }
            if (isset($stored[$key]['unit_price']) && is_numeric($stored[$key]['unit_price'])) {
                $row['unit_price'] = round((float) $stored[$key]['unit_price'], 4);
            }
            $catalogId = (int) ($stored[$key]['catalog_material_id'] ?? 0);
            $row['catalog_material_id'] = $catalogId > 0 ? $catalogId : null;
            $defaults[$key] = $row;
        }

        return $defaults;
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
                'demo_drywall_sf_per_hr' => 'Remove drywall / ceiling (sq ft / hour)',
                'bin_hours_each' => 'Debris bin handling (hours each)',
            ],
            'Framing' => [
                'framing_lf_per_hr' => 'Partition walls (linear ft / hour)',
                'bulkhead_lf_per_hr' => 'Bulkheads / duct boxing (linear ft / hour)',
                'header_hours_each' => 'Header / beam (hours each)',
                'door_opening_hours_each' => 'Door opening framing (hours each)',
            ],
            'Electrical' => [
                'outlet_hours_each' => 'Outlet (hours each)',
                'switch_hours_each' => 'Switch (hours each)',
                'light_hours_each' => 'Lighting point (hours each)',
                'panel_upgrade_hours' => 'Panel upgrade (hours)',
                'detector_hours_each' => 'Smoke / CO detector (hours each)',
                'wiring_run_hours_each' => 'Wiring run (hours each)',
            ],
            'Plumbing' => [
                'plumbing_bath_rough_hours' => 'Bathroom rough-in (hours)',
                'sink_hours' => 'Sink hookup (hours)',
                'laundry_hours' => 'Laundry hookup (hours)',
                'wet_bar_hours' => 'Wet bar rough-in (hours)',
                'floor_drain_hours' => 'Floor drain (hours)',
                'wh_reloc_hours' => 'Water heater relocation (hours)',
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
            ],
            'Bathroom & finishing' => [
                'vanity_hours' => 'Vanity install (hours)',
                'toilet_hours' => 'Toilet install (hours)',
                'shower_hours' => 'Shower install (hours)',
                'tub_hours' => 'Tub install (hours)',
                'exhaust_fan_hours' => 'Exhaust fan (hours)',
                'tile_sf_per_hr' => 'Bathroom tile (sq ft / hour)',
                'fixture_hours_each' => 'Light fixture (hours each)',
                'hardware_hours' => 'Hardware install (hours)',
                'cleaning_hours_per_100sf' => 'Final cleaning (hours / 100 sq ft)',
                'touchup_hours' => 'Touch-ups (hours)',
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Models\Material;
use App\Support\CatalogMaterialDelivery;

class BasementQuotationCalculator
{
    public const SECTIONS = [
        'overview' => 'Project Overview',
        'demolition' => 'Demolition & Preparation',
        'framing' => 'Framing',
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing',
        'insulation' => 'Insulation & Vapor Barrier',
        'drywall' => 'Drywall / Mud / Tape / Sand',
        'ceiling' => 'Ceiling Options',
        'flooring' => 'Flooring',
        'doors_trim' => 'Doors, Trim & Baseboards',
        'painting' => 'Painting',
        'bathroom' => 'Bathroom',
        'finishing' => 'Final Details & Cleanup',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'area_sqft' => 0,
            'ceiling_height_ft' => 8,
            'room_count' => 1,
            'existing_notes' => '',
            'include_demolition' => false,
            'demo_flooring_sf' => null,
            'demo_drywall_sf' => 0,
            'demo_bin_qty' => 1,
            'partition_lf' => 0,
            'bulkhead_lf' => 0,
            'header_qty' => 0,
            'framing_door_openings' => 0,
            'outlet_qty' => 0,
            'switch_qty' => 0,
            'light_qty' => 0,
            'panel_upgrade' => false,
            'detector_qty' => 2,
            'wiring_runs' => 0,
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
            'drywall_wall_sf' => null,
            'drywall_ceiling_sf' => null,
            'drywall_corners' => 0,
            'drywall_openings' => 0,
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
            'casing_openings' => 0,
            'window_trim_qty' => 0,
            'paint_wall_sf' => null,
            'paint_ceiling_sf' => null,
            'paint_trim_lf' => null,
            'paint_coats' => 2,
            'include_bathroom' => false,
            'bath_type' => 'full',
            'include_vanity' => true,
            'include_toilet' => true,
            'include_shower' => true,
            'include_tub' => false,
            'bath_tile_sf' => 0,
            'include_exhaust_fan' => true,
            'fixture_qty' => 0,
            'hardware_sets' => 0,
            'include_cleaning' => true,
            'include_touchups' => true,
            'client_provides_all' => false,
            'client_provides_sections' => [],
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
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
        if (max(0, (float) ($inputs['area_sqft'] ?? 0)) <= 0) {
            $hints[] = 'Enter the total basement area in square feet.';
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
        $inputs = array_merge(self::defaultInputs(), $inputs);

        $area = round(max(0, (float) $inputs['area_sqft']), 2);
        $height = round(max(0, (float) $inputs['ceiling_height_ft']), 2) ?: 8;
        if ($area <= 0) {
            return $this->emptyCalculation($inputs, $settings, $height);
        }
        $perimeter = $area > 0 ? round(4 * sqrt($area), 2) : 0;
        $suggestedWallSf = round($perimeter * $height, 2);

        $labor = [];
        $materials = [];

        $providesAll = ! empty($inputs['client_provides_all']);
        $provideSections = is_array($inputs['client_provides_sections'] ?? null)
            ? array_values(array_filter(array_map('strval', $inputs['client_provides_sections'])))
            : [];

        $chargeMats = function (string $section) use ($providesAll, $provideSections): bool {
            if ($providesAll) {
                return false;
            }

            return ! in_array($section, $provideSections, true);
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
            ?string $catalogKey = null
        ) use (&$materials, $inputs, $matCatalog, $chargeMats): void {
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
            $clientProvided = ! $chargeMats($section);
            $amount = $clientProvided ? 0.0 : round($qty * $unitPrice, 2);
            $derivedPlate = $key === BasementSettings::MATERIAL_PLATE_2X4;
            $materials[] = [
                'key' => $key,
                'section' => $section,
                'label' => $def['name'],
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
        if (! empty($inputs['include_demolition'])) {
            $demoFloor = $this->qtyOr($inputs['demo_flooring_sf'], $area);
            $demoDrywall = $this->qtyOr($inputs['demo_drywall_sf'], 0);
            $bins = max(0, (int) $inputs['demo_bin_qty']);
            $addLabor('demo_floor', 'demolition', 'Remove existing flooring', number_format($demoFloor, 0).' sq ft', $perHr($demoFloor, (float) $prod['demo_floor_sf_per_hr']));
            $addLabor('demo_drywall', 'demolition', 'Remove drywall / ceiling', number_format($demoDrywall, 0).' sq ft', $perHr($demoDrywall, (float) $prod['demo_drywall_sf_per_hr']));
            $addLabor('demo_bins', 'demolition', 'Debris bins / haul-away', $bins.' bin(s)', $bins * (float) $prod['bin_hours_each']);
            $addMat('dump_bin', 'demolition', $bins, 'dump_bin');
        }

        // --- Framing ---
        $partitionLf = max(0, (float) $inputs['partition_lf']);
        $bulkheadLf = max(0, (float) $inputs['bulkhead_lf']);
        $headers = max(0, (int) $inputs['header_qty']);
        $frameDoors = max(0, (int) $inputs['framing_door_openings']);
        $addLabor('framing_walls', 'framing', 'Partition wall framing', number_format($partitionLf, 0).' lf × '.$height.' ft', $perHr($partitionLf, (float) $prod['framing_lf_per_hr']));
        $addLabor('framing_bulkheads', 'framing', 'Bulkheads / duct boxing', number_format($bulkheadLf, 0).' lf', $perHr($bulkheadLf, (float) $prod['bulkhead_lf_per_hr']));
        $addLabor('framing_headers', 'framing', 'Headers / beams', $headers.' opening(s)', $headers * (float) $prod['header_hours_each']);
        $addLabor('framing_doors', 'framing', 'Door openings', $frameDoors.' opening(s)', $frameDoors * (float) $prod['door_opening_hours_each']);
        if ($partitionLf > 0) {
            $studs = max(1, (int) ceil(($partitionLf * 12 / 16) + 2));
            $plates = round($partitionLf * 3, 0);
            $addMat('stud_2x4', 'framing', $studs, 'stud_2x4');
            $addMat('plate_2x4', 'framing', $plates, 'plate_2x4');
        }

        // --- Electrical ---
        $outlets = max(0, (int) $inputs['outlet_qty']);
        $switches = max(0, (int) $inputs['switch_qty']);
        $lights = max(0, (int) $inputs['light_qty']);
        $detectors = max(0, (int) $inputs['detector_qty']);
        $runs = max(0, (int) $inputs['wiring_runs']);
        if ($runs <= 0) {
            $runs = $outlets + $switches + $lights;
        }
        $addLabor('elec_outlets', 'electrical', 'New outlets', $outlets.' location(s)', $outlets * (float) $prod['outlet_hours_each']);
        $addLabor('elec_switches', 'electrical', 'Switches', $switches.' location(s)', $switches * (float) $prod['switch_hours_each']);
        $addLabor('elec_lights', 'electrical', 'Lighting points', $lights.' fixture(s)', $lights * (float) $prod['light_hours_each']);
        $addLabor('elec_detectors', 'electrical', 'Smoke / CO detectors', $detectors.' unit(s)', $detectors * (float) $prod['detector_hours_each']);
        $addLabor('elec_runs', 'electrical', 'Wiring runs', $runs.' run(s)', $runs * (float) $prod['wiring_run_hours_each']);
        if (! empty($inputs['panel_upgrade'])) {
            $addLabor('elec_panel', 'electrical', 'Panel upgrade', 'Allowance', (float) $prod['panel_upgrade_hours']);
            $addMat('panel_upgrade', 'electrical', 1, 'panel_upgrade');
        }
        $addMat('outlet_box', 'electrical', $outlets, 'outlet_box');
        $addMat('switch_device', 'electrical', $switches, 'switch_device');
        $addMat('pot_light', 'electrical', $lights, 'pot_light');
        $addMat('smoke_co', 'electrical', $detectors, 'smoke_co');
        $addMat('nm_cable', 'electrical', $this->packages($runs * 25, BasementSettings::NMD90_FEET_PER_BOX), 'nm_cable');

        // --- Plumbing ---
        if (! empty($inputs['include_plumbing'])) {
            if (! empty($inputs['bath_rough_in'])) {
                $addLabor('plumb_bath', 'plumbing', 'Bathroom rough-in', 'Drain / vent / supply', (float) $prod['plumbing_bath_rough_hours']);
                $addMat('bath_rough_in', 'plumbing', 1, 'bath_rough_in');
            }
            if (! empty($inputs['sink_laundry'])) {
                $addLabor('plumb_sink', 'plumbing', 'Sink / laundry hookup', 'Supply + drain', (float) $prod['sink_hours'] + (float) $prod['laundry_hours'] / 2);
                $addMat('sink_kit', 'plumbing', 1, 'sink_kit');
            }
            if (! empty($inputs['wet_bar'])) {
                $addLabor('plumb_bar', 'plumbing', 'Wet bar rough-in', 'Supply + drain', (float) $prod['wet_bar_hours']);
            }
            if (! empty($inputs['floor_drain'])) {
                $addLabor('plumb_drain', 'plumbing', 'Floor drain', '1 location', (float) $prod['floor_drain_hours']);
                $addMat('floor_drain', 'plumbing', 1, 'floor_drain');
            }
            if (! empty($inputs['wh_reloc'])) {
                $addLabor('plumb_wh', 'plumbing', 'Water heater relocation', 'Disconnect / relocate / reconnect', (float) $prod['wh_reloc_hours']);
            }
        }

        // --- Insulation ---
        $insWall = $this->qtyOr($inputs['insulation_wall_sf'], ! empty($inputs['insulate_walls']) ? $suggestedWallSf : 0);
        $insCeil = $this->qtyOr($inputs['insulation_ceiling_sf'], ! empty($inputs['insulate_ceiling']) ? $area : 0);
        $insSlab = $this->qtyOr($inputs['underslab_sf'], ! empty($inputs['underslab']) ? $area : 0);
        if (! empty($inputs['insulate_walls'])) {
            $addLabor('ins_walls', 'insulation', 'Wall insulation + vapor barrier', number_format($insWall, 0).' sq ft', $perHr($insWall, (float) $prod['insulation_wall_sf_per_hr']));
            $addMat('insulation_batt', 'insulation', $this->packages($insWall * 1.05, BasementSettings::BATT_SF_PER_BAG), 'insulation_batt');
            $addMat('vapor_barrier', 'insulation', $this->packages($insWall * 1.1, BasementSettings::VAPOR_SF_PER_ROLL), 'vapor_barrier');
        }
        if (! empty($inputs['insulate_ceiling'])) {
            $addLabor('ins_ceiling', 'insulation', 'Ceiling insulation', number_format($insCeil, 0).' sq ft', $perHr($insCeil, (float) $prod['insulation_ceiling_sf_per_hr']));
            $addMat('insulation_batt_ceil', 'insulation', $this->packages($insCeil * 1.05, BasementSettings::BATT_SF_PER_BAG), 'insulation_batt');
        }
        if (! empty($inputs['underslab'])) {
            $addLabor('ins_slab', 'insulation', 'Under-slab insulation', number_format($insSlab, 0).' sq ft', $perHr($insSlab, (float) $prod['underslab_sf_per_hr']));
            $addMat('underslab_insulation', 'insulation', $this->packages($insSlab, BasementSettings::PANEL_SF), 'underslab_insulation');
        }

        // --- Drywall ---
        $ceilingType = (string) ($inputs['ceiling_type'] ?? 'drywall');
        $dwWall = $this->qtyOr($inputs['drywall_wall_sf'], $suggestedWallSf);
        $dwCeil = $this->qtyOr(
            $inputs['drywall_ceiling_sf'],
            in_array($ceilingType, ['drywall', 'paint'], true) ? $area : 0
        );
        $dwSf = $dwWall + $dwCeil;
        $level = max(1, min(4, (int) ($inputs['finish_level'] ?? 4)));
        $finishKey = 'drywall_finish_l'.$level.'_sf_per_hr';
        $addLabor('dw_hang', 'drywall', 'Hang drywall', number_format($dwSf, 0).' sq ft', $perHr($dwSf, (float) $prod['drywall_hang_sf_per_hr']));
        $addLabor('dw_finish', 'drywall', 'Mud / tape / sand (Level '.$level.')', number_format($dwSf, 0).' sq ft', $perHr($dwSf, (float) ($prod[$finishKey] ?? $prod['drywall_finish_l4_sf_per_hr'])));
        $sheets = $dwSf > 0 ? max(1, (int) ceil($dwSf / BasementSettings::PANEL_SF * 1.1)) : 0;
        $addMat('drywall_sheet', 'drywall', $sheets, 'drywall_sheet');
        $addMat('joint_compound', 'drywall', $this->packages($dwSf, BasementSettings::JOINT_COMPOUND_SF_PER_PAIL), 'joint_compound');
        $addMat('drywall_tape', 'drywall', $this->packages($dwSf, BasementSettings::DRYWALL_TAPE_SF_PER_ROLL), 'drywall_tape');
        $corners = max(0, (int) $inputs['drywall_corners']);
        $openings = max(0, (int) $inputs['drywall_openings']);
        $addMat('corner_bead', 'drywall', $corners, 'corner_bead');
        $addLabor('dw_openings', 'drywall', 'Cut & finish openings', $openings.' opening(s)', $openings * (float) ($prod['drywall_opening_hours_each'] ?? 0.4));

        // --- Ceiling ---
        $ceilSf = $this->qtyOr($inputs['ceiling_sf'], $area);
        if ($ceilingType === 'drop') {
            $addLabor('ceil_drop', 'ceiling', 'Drop ceiling', number_format($ceilSf, 0).' sq ft', $perHr($ceilSf, (float) $prod['ceiling_drop_sf_per_hr']));
            $addMat('drop_ceiling', 'ceiling', $ceilSf * 1.08, 'drop_ceiling');
        }

        // --- Flooring ---
        $flSf = $this->qtyOr($inputs['flooring_sf'], $area);
        $flType = (string) ($inputs['flooring_type'] ?? 'lvp');
        $flCatalog = match ($flType) {
            'laminate' => 'laminate',
            'carpet' => 'carpet',
            'tile' => 'tile',
            'epoxy' => 'epoxy',
            default => 'lvp',
        };
        if ($flType !== 'none' && $flSf > 0) {
            if (! empty($inputs['subfloor_prep'])) {
                $addLabor('fl_subfloor', 'flooring', 'Subfloor prep / leveling', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['subfloor_sf_per_hr']));
                $addMat('subfloor_panel', 'flooring', $this->packages($flSf, BasementSettings::PANEL_SF), 'subfloor_panel');
            }
            if (! empty($inputs['underlayment']) && $flType !== 'epoxy' && $flType !== 'tile') {
                $addLabor('fl_underlay', 'flooring', 'Underlayment', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['underlayment_sf_per_hr']));
                $addMat('underlayment', 'flooring', $this->packages($flSf * 1.08, BasementSettings::UNDERLAYMENT_SF_PER_ROLL), 'underlayment');
            }
            $addLabor('fl_install', 'flooring', $this->flooringLabel($flType).' install', number_format($flSf, 0).' sq ft', $perHr($flSf, (float) $prod['flooring_sf_per_hr']));
            $addMat('flooring_finish', 'flooring', $flSf * 1.08, $flCatalog);
            $transitions = max(0, (int) $inputs['transition_qty']);
            $addLabor('fl_transitions', 'flooring', 'Transitions', $transitions.' piece(s)', $transitions * (float) $prod['transition_hours_each']);
            $addMat('transition', 'flooring', $transitions, 'transition');
        }

        // --- Doors / trim ---
        $doors = max(0, (int) $inputs['door_qty']);
        $baseLf = $this->qtyOr($inputs['baseboard_lf'], $perimeter);
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
        $addMat('baseboard', 'doors_trim', $this->packages($baseLf * 1.08, BasementSettings::BASEBOARD_FEET_PER_STICK), 'baseboard');
        $addMat('casing', 'doors_trim', $casing, 'casing');
        $addMat('window_trim', 'doors_trim', $windows, 'window_trim');
        $addMat('door_hardware', 'doors_trim', $doors, 'door_hardware');

        // --- Painting ---
        $coats = max(1, (int) ($inputs['paint_coats'] ?? 2));
        $pWall = $this->qtyOr($inputs['paint_wall_sf'], $dwWall ?: $suggestedWallSf);
        $pCeil = $this->qtyOr($inputs['paint_ceiling_sf'], in_array($ceilingType, ['drop', 'none'], true) ? 0 : ($dwCeil ?: $area));
        $pTrim = $this->qtyOr($inputs['paint_trim_lf'], $baseLf + ($casing * 17) + ($windows * 16));
        $addLabor('paint_walls', 'painting', 'Paint walls ('.$coats.' coat'.($coats === 1 ? '' : 's').')', number_format($pWall, 0).' sq ft', $perHr($pWall * $coats, (float) $prod['paint_wall_sf_per_hr']));
        $addLabor('paint_ceiling', 'painting', 'Paint ceiling', number_format($pCeil, 0).' sq ft', $perHr($pCeil * $coats, (float) $prod['paint_ceiling_sf_per_hr']));
        $addLabor('paint_trim', 'painting', 'Paint trim', number_format($pTrim, 0).' lf', $perHr($pTrim, (float) $prod['paint_trim_lf_per_hr']));
        $paintGallons = $pWall + $pCeil > 0 ? max(1, (int) ceil((($pWall + $pCeil) * $coats) / 350)) : 0;
        $primerGallons = $pWall + $pCeil > 0 ? max(1, (int) ceil(($pWall + $pCeil) / 350)) : 0;
        $addMat('primer_gallon', 'painting', $primerGallons, 'primer_gallon');
        $addMat('paint_gallon', 'painting', $paintGallons, 'paint_gallon');

        // --- Bathroom ---
        if (! empty($inputs['include_bathroom'])) {
            $half = ($inputs['bath_type'] ?? 'full') === 'half';
            if (! empty($inputs['include_vanity'])) {
                $addLabor('bath_vanity', 'bathroom', 'Vanity', 'Install', (float) $prod['vanity_hours']);
                $addMat('vanity', 'bathroom', 1, 'vanity');
            }
            if (! empty($inputs['include_toilet'])) {
                $addLabor('bath_toilet', 'bathroom', 'Toilet', 'Install', (float) $prod['toilet_hours']);
                $addMat('toilet', 'bathroom', 1, 'toilet');
            }
            if (! $half && ! empty($inputs['include_shower'])) {
                $addLabor('bath_shower', 'bathroom', 'Shower', 'Install', (float) $prod['shower_hours']);
                $addMat('shower_kit', 'bathroom', 1, 'shower_kit');
            }
            if (! $half && ! empty($inputs['include_tub'])) {
                $addLabor('bath_tub', 'bathroom', 'Tub', 'Install', (float) $prod['tub_hours']);
                $addMat('tub_kit', 'bathroom', 1, 'tub_kit');
            }
            if (! empty($inputs['include_exhaust_fan'])) {
                $addLabor('bath_fan', 'bathroom', 'Exhaust fan', 'Install + duct', (float) $prod['exhaust_fan_hours']);
                $addMat('exhaust_fan', 'bathroom', 1, 'exhaust_fan');
            }
            $tileSf = max(0, (float) $inputs['bath_tile_sf']);
            if ($tileSf > 0) {
                $addLabor('bath_tile', 'bathroom', 'Bathroom tile', number_format($tileSf, 0).' sq ft', $perHr($tileSf, (float) $prod['tile_sf_per_hr']));
                $addMat('bath_tile', 'bathroom', $tileSf * 1.1, 'tile');
                $addMat('thinset_grout', 'bathroom', $this->packages($tileSf * 1.1, BasementSettings::THINSET_SF_PER_BAG), 'thinset_grout');
            }
        }

        // --- Finishing ---
        $fixtures = max(0, (int) $inputs['fixture_qty']);
        if ($fixtures <= 0) {
            $fixtures = $lights;
        }
        $hw = max(0, (int) $inputs['hardware_sets']);
        $addLabor('fin_fixtures', 'finishing', 'Lighting fixtures', $fixtures.' fixture(s)', $fixtures * (float) $prod['fixture_hours_each']);
        $addMat('light_fixture', 'finishing', $fixtures, 'light_fixture');
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
        $hoursPerDay = max(0.5, (float) $settings->hours_per_day);

        $totalHours = 0.0;
        foreach ($labor as &$row) {
            $totalHours += $row['hours'];
            $row['amount'] = round($row['hours'] * $ownerRate, 2);
        }
        unset($row);
        $totalHours = round($totalHours, 2);
        $laborPre = round($totalHours * $ownerRate, 2);

        $matPre = 0.0;
        foreach ($materials as $row) {
            $matPre += (float) $row['amount'];
        }
        foreach ($extraMaterials as $row) {
            $matPre += (float) $row['amount'];
        }
        $matPre = round($matPre, 2);
        $matHst = round($matPre * BasementQuotation::HST_RATE, 2);
        $laborHst = round($laborPre * BasementQuotation::HST_RATE, 2);
        $subtotal = round($laborPre + $matPre, 2);
        $hst = round($laborHst + $matHst, 2);
        $grand = round($subtotal + $hst, 2);

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

        $finishLabel = ! empty($inputs['include_bathroom']) ? 'Full finish with bathroom' : 'Full finish';

        return [
            'scope' => [
                'area_sqft' => $area,
                'ceiling_height_ft' => $height,
                'room_count' => max(0, (int) $inputs['room_count']),
                'suggested_wall_sf' => $suggestedWallSf,
                'suggested_perimeter_lf' => $perimeter,
                'finish_label' => $finishLabel,
                'ceiling_type' => $ceilingType,
                'flooring_type' => $flType,
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
                'labor_pre_hst' => $laborPre,
                'materials_pre_hst' => $matPre,
                'materials_hst' => $matHst,
                'materials_after_hst' => round($matPre + $matHst, 2),
                'subtotal_pre_hst' => $subtotal,
                'hst' => $hst,
                'grand_total' => $grand,
                'final_quote_total' => $grand,
                'client_provides_all' => $providesAll,
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
                $rows[$key]['name'] = $mat->displayName();
                $rows[$key]['unit_price'] = round((float) $mat->price_cad, 4);
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
        $hoursPerDay = max(0.5, (float) $settings->hours_per_day);

        return [
            'scope' => [
                'area_sqft' => 0.0,
                'ceiling_height_ft' => $height,
                'room_count' => max(0, (int) ($inputs['room_count'] ?? 0)),
                'suggested_wall_sf' => 0.0,
                'suggested_perimeter_lf' => 0.0,
                'finish_label' => ! empty($inputs['include_bathroom']) ? 'Full finish with bathroom' : 'Full finish',
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
                'labor_pre_hst' => 0.0,
                'materials_pre_hst' => 0.0,
                'materials_hst' => 0.0,
                'materials_after_hst' => 0.0,
                'subtotal_pre_hst' => 0.0,
                'hst' => 0.0,
                'grand_total' => 0.0,
                'final_quote_total' => 0.0,
                'client_provides_all' => ! empty($inputs['client_provides_all']),
            ],
        ];
    }

    private function flooringLabel(string $type): string
    {
        return match ($type) {
            'laminate' => 'Laminate',
            'tile' => 'Tile',
            'carpet' => 'Carpet',
            'epoxy' => 'Epoxy',
            'none' => 'No flooring',
            default => 'LVP',
        };
    }
}

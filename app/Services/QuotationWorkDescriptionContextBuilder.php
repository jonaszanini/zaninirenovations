<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\TileEdge;
use App\Models\TileGroutType;
use App\Models\TileMortarType;
use App\Models\TileProduct;

class QuotationWorkDescriptionContextBuilder
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function build(?string $quotationType, ?array $context): string
    {
        $type = is_string($quotationType) ? trim($quotationType) : '';
        if ($type === '' || ! is_array($context) || $context === []) {
            return '';
        }

        return match ($type) {
            'painting' => $this->painting($context),
            'fence' => $this->fence($context),
            'low_deck' => $this->lowDeck($context),
            'paving' => $this->paving($context),
            'standard' => $this->standard($context),
            'trimming' => $this->trimming($context),
            'shed' => $this->shed($context),
            'basement' => $this->basement($context),
            'millwork' => $this->millwork($context),
            'stairs' => $this->stairs($context),
            'flooring' => $this->flooring($context),
            'tile' => $this->tile($context),
            default => $this->generic($type, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function painting(array $c): string
    {
        $lines = ['Quotation type: Residential interior painting'];

        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $inputs = [
            'total_property_sqf' => (float) ($c['total_property_sqf'] ?? 0),
            'excluded_areas' => is_array($c['excluded_areas'] ?? null) ? $c['excluded_areas'] : [],
            'paint_line_id' => isset($c['paint_line_id']) ? (int) $c['paint_line_id'] : null,
            'coats' => (int) ($c['coats'] ?? PaintingQuotationCalculator::DEFAULT_COATS),
            'surface_prep_description' => (string) ($c['surface_prep_description'] ?? ''),
        ];

        if ($inputs['total_property_sqf'] > 0) {
            $calc = (new PaintingQuotationCalculator)->calculate($inputs);
            $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
            $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

            $this->appendLine($lines, 'Total property size', $this->sqfLabel($scope['total_property_sqf'] ?? $inputs['total_property_sqf']));
            $excluded = (float) ($scope['excluded_total_sqf'] ?? 0);
            if ($excluded > 0) {
                $this->appendLine($lines, 'Excluded areas total', $this->sqfLabel($excluded));
                foreach ($scope['excluded_areas'] ?? [] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $name = trim((string) ($row['name'] ?? ''));
                    $detail = trim((string) ($row['detail'] ?? ''));
                    if ($name !== '') {
                        $lines[] = '  - '.$name.($detail !== '' ? ' ('.$detail.')' : '');
                    }
                }
            }
            $this->appendLine($lines, 'Net painting area', $this->sqfLabel($scope['net_painting_area_sqf'] ?? 0));
            $paintName = trim((string) ($scope['paint_line_name'] ?? ($c['paint_line_name'] ?? '')));
            if ($paintName !== '') {
                $coats = (int) ($scope['coats'] ?? $inputs['coats']);
                $lines[] = 'Paint: '.$paintName.' ('.$coats.' top coats)';
            }
            $gallons = (int) ($scope['gallons_needed'] ?? 0);
            if ($gallons > 0) {
                $lines[] = 'Estimated paint: '.$gallons.' gallon(s)';
            }
            if (($summary['labor_pre_hst'] ?? 0) > 0 || ($summary['materials_pre_hst'] ?? 0) > 0) {
                $lines[] = 'Pricing snapshot: labour '.ClientProject::formatMoney((float) ($summary['labor_pre_hst'] ?? 0))
                    .', materials '.ClientProject::formatMoney((float) ($summary['materials_pre_hst'] ?? 0))
                    .', grand total '.ClientProject::formatMoney((float) ($summary['grand_total'] ?? 0)).' (incl. HST)';
            }
        } elseif ($paintName = trim((string) ($c['paint_line_name'] ?? ''))) {
            $lines[] = 'Paint: '.$paintName;
        }

        $prep = trim((string) ($c['surface_prep_description'] ?? ''));
        if ($prep !== '') {
            $lines[] = 'Surface preparation scope (include in the client-facing description):';
            $lines[] = $prep;
        }

        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function fence(array $c): string
    {
        $lines = ['Quotation type: Fence installation'];

        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);
        $this->appendLine($lines, 'Fence height', $c['fence_height_ft'] ?? null);
        $this->appendLine($lines, 'Post size', $c['post_size'] ?? null);
        $this->appendLine($lines, 'Board style', $c['board_style'] ?? null);
        $this->appendLine($lines, 'Gates', $c['gates'] ?? null);

        if (($c['board_style'] ?? '') === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING) {
            $overlap = \App\Models\FenceFormulaSetting::current()->overlapping_in;
            if ($overlap > 0) {
                $this->appendLine($lines, 'Board overlap', $overlap.' in');
            }
        }

        $sides = is_array($c['sides'] ?? null) ? $c['sides'] : [];
        $sideParts = [];
        foreach ($sides as $i => $side) {
            if (! is_array($side)) {
                continue;
            }
            $len = $side['length_ft'] ?? null;
            if ($len !== null && $len !== '' && is_numeric($len) && (float) $len > 0) {
                $part = 'Side '.($i + 1).': '.rtrim(rtrim(number_format((float) $len, 1, '.', ''), '0'), '.').' linear ft';
                $existing = [];
                if (! empty($side['already_have_post_left'])) {
                    $existing[] = 'existing post on left';
                }
                if (! empty($side['already_have_post_right'])) {
                    $existing[] = 'existing post on right';
                }
                if (! empty($side['gate_goes_here'])) {
                    $existing[] = 'gate on this side';
                }
                if ($existing !== []) {
                    $part .= ' ('.implode(', ', $existing).')';
                }
                $sideParts[] = $part;
            }
        }
        if ($sideParts !== []) {
            $lines[] = 'Fence runs: '.implode('; ', $sideParts);
        }

        $this->appendBlock($lines, 'Layout / geometry', $c['geometry_summary'] ?? null);
        $this->appendBlock($lines, 'Materials list', $c['materials_summary'] ?? null);
        $this->appendBlock($lines, 'Pricing snapshot', $c['totals_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function lowDeck(array $c): string
    {
        $lines = ['Quotation type: Low floating deck'];

        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);
        $this->appendLine($lines, 'Width', isset($c['width_ft']) ? $c['width_ft'].' ft' : null);
        $this->appendLine($lines, 'Length', isset($c['length_ft']) ? $c['length_ft'].' ft' : null);
        $this->appendLine($lines, 'Joist spacing', isset($c['joist_spacing_in']) ? $c['joist_spacing_in'].' in (auto, max 15 in.)' : null);

        $this->appendBlock($lines, 'Layout / geometry', $c['geometry_summary'] ?? null);
        $this->appendBlock($lines, 'Materials list', $c['materials_summary'] ?? null);
        $this->appendBlock($lines, 'Pricing snapshot', $c['totals_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function paving(array $c): string
    {
        $lines = ['Quotation type: Paving stones / hardscape'];

        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $area = $c['total_area_sq_ft'] ?? null;
        if ($area !== null && $area !== '') {
            $areaStr = is_numeric($area) ? $area.' sq ft' : (string) $area;
            $this->appendLine($lines, 'Project area', $areaStr);
        }

        $stone = $c['stone_types'] ?? ($c['stone_type'] ?? null);
        $this->appendLine($lines, 'Stone type(s)', $stone);

        if (! empty($c['excavation_depth_in'])) {
            $depthStr = is_numeric($c['excavation_depth_in']) ? $c['excavation_depth_in'].' in' : (string) $c['excavation_depth_in'];
            $this->appendLine($lines, 'Excavation depth', $depthStr);
        }

        if (! empty($c['waste_factor'])) {
            $wasteStr = is_numeric($c['waste_factor']) ? $c['waste_factor'].'%' : (string) $c['waste_factor'];
            $this->appendLine($lines, 'Waste factor', $wasteStr);
        }

        if ((float) ($c['mini_skid_days'] ?? 0) > 0) {
            $this->appendLine($lines, 'Mini skid steer rental days', $c['mini_skid_days']);
        }
        if ((float) ($c['plate_compactor_days'] ?? 0) > 0) {
            $this->appendLine($lines, 'Plate compactor rental days', $c['plate_compactor_days']);
        }

        $this->appendBlock($lines, 'Stone details', $c['stone_meta'] ?? null);
        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function standard(array $c): string
    {
        $lines = ['Quotation type: Standard labour and materials'];

        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);
        $this->appendLine($lines, 'Project / quote title', $c['display_title'] ?? null);
        $this->appendLine($lines, 'Document type', $c['document_type'] ?? null);
        $this->appendLine($lines, 'PDF detail mode', $c['pdf_detail_mode'] ?? null);

        $includes = [];
        if (filter_var($c['includes_material'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $includes[] = 'material';
        }
        if (filter_var($c['includes_labour'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $includes[] = 'labour';
        }
        if ($includes !== []) {
            $lines[] = 'PDF includes: '.implode(' and ', $includes);
        }

        $materials = is_array($c['materials'] ?? null) ? $c['materials'] : [];
        if ($materials !== []) {
            $lines[] = 'Materials:';
            foreach ($materials as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['short_name'] ?? $row['description'] ?? ''));
                $desc = trim((string) ($row['description'] ?? ''));
                $qty = trim((string) ($row['quantity'] ?? ''));
                $unit = trim((string) ($row['unit_price'] ?? ''));
                if ($name === '' && $desc === '') {
                    continue;
                }
                $part = $name !== '' ? $name : $desc;
                if ($desc !== '' && $desc !== $name) {
                    $part .= ' — '.$desc;
                }
                if ($qty !== '' && is_numeric($qty)) {
                    $part .= ', qty '.$qty;
                }
                if ($unit !== '') {
                    $part .= ', unit '.$unit;
                }
                $lines[] = '  - '.$part;
            }
        }

        $jobs = is_array($c['jobs'] ?? null) ? $c['jobs'] : [];
        if (count($jobs) > 1) {
            $lines[] = 'Jobs:';
            foreach ($jobs as $i => $job) {
                if (! is_array($job)) {
                    continue;
                }
                $title = trim((string) ($job['title'] ?? ''));
                $lines[] = '  - '.($title !== '' ? $title : 'Job '.($i + 1));
            }
        }

        if (filter_var($c['contingency_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->appendLine($lines, 'Contingency allowance', ($c['contingency_percent'] ?? '').'%');
        }

        if (filter_var($c['labour_use_fixed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->appendLine($lines, 'Labour', 'Fixed amount '.($c['labour_fixed_amount'] ?? ''));
        } else {
            $labour = trim(implode(' ', array_filter([
                isset($c['labour_hourly_rate']) ? 'Rate '.$c['labour_hourly_rate'].'/hr' : '',
                isset($c['labour_quantity']) ? 'Qty '.$c['labour_quantity'].' hours' : '',
            ])));
            if ($labour !== '') {
                $lines[] = 'Labour: '.$labour;
            }
        }

        $this->appendBlock($lines, 'Totals snapshot', $c['totals_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function trimming(array $c): string
    {
        $lines = ['Quotation type: Interior trimming'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $enabled = is_array($c['enabled_families'] ?? null) ? $c['enabled_families'] : [];
        $allFamilies = TrimmingQuotationCalculator::FAMILIES;
        $includedFamilies = [];
        $excludedFamilies = [];
        foreach ($allFamilies as $key => $label) {
            if (in_array((string) $key, $enabled, true)) {
                $includedFamilies[] = $label;
            } else {
                $excludedFamilies[] = $label;
            }
        }

        if ($includedFamilies !== []) {
            $lines[] = 'Included trim families: '.implode(', ', $includedFamilies);
        }
        if ($excludedFamilies !== []) {
            $lines[] = 'Trim families NOT included in this quote: '.implode(', ', $excludedFamilies).'. Strictly DO NOT describe or assume any unquoted trim families.';
        }

        if (in_array('doors_windows', $enabled, true)) {
            $doorQty = (int) ($c['door_qty'] ?? 0);
            $windowQty = (int) ($c['window_qty'] ?? 0);
            if ($doorQty > 0) {
                $lines[] = 'Door openings: '.$doorQty;
            }
            if ($windowQty > 0) {
                $lines[] = 'Window openings: '.$windowQty;
            }
            if (! empty($c['include_casing'])) {
                $lines[] = 'Casing: included';
            }
            if (! empty($c['include_architrave'])) {
                $lines[] = 'Architrave: included';
            }
            if (! empty($c['include_plinth'])) {
                $lines[] = 'Plinth blocks: included';
            }
            if (! empty($c['include_rosettes'])) {
                $lines[] = 'Rosettes: included';
            }
            if (! empty($c['include_stool_apron'])) {
                $lines[] = 'Window stool & apron: included';
            }
            if (! empty($c['include_jamb_extensions'])) {
                $lines[] = 'Jamb extensions: included';
            }
            if (! empty($c['include_interior_doors'])) {
                $lines[] = 'Hang pre-hung interior doors: included';
            }
            if (! empty($c['include_slab_swap'])) {
                $lines[] = 'Door slab swap: included';
            }
            if (! empty($c['include_picture_frame_windows'])) {
                $lines[] = 'Picture-frame window casing: included';
            }
            $pocketQty = (int) ($c['pocket_door_qty'] ?? 0);
            if ($pocketQty > 0) {
                $lines[] = 'Pocket doors (retrofit): '.$pocketQty;
            }
            $frenchQty = (int) ($c['french_door_qty'] ?? 0);
            if ($frenchQty > 0) {
                $lines[] = 'French / double door pairs: '.$frenchQty;
            }
        }

        if (in_array('floor_wall', $enabled, true)) {
            $floorLf = (float) ($c['floor_lf'] ?? 0);
            if ($floorLf > 0) {
                $lines[] = 'Floor perimeter run: '.$floorLf.' lf';
            }
            if (! empty($c['include_baseboard'])) {
                $baseLf = (float) ($c['baseboard_lf'] ?? $floorLf);
                $lines[] = 'Baseboards: included'.($baseLf > 0 ? ' ('.$baseLf.' lf)' : '');
            }
            if (! empty($c['include_base_cap'])) {
                $capLf = (float) ($c['base_cap_lf'] ?? $floorLf);
                $lines[] = 'Base cap: included'.($capLf > 0 ? ' ('.$capLf.' lf)' : '');
            }
            if (! empty($c['include_shoe'])) {
                $shoeLf = (float) ($c['shoe_lf'] ?? $floorLf);
                $lines[] = 'Shoe moulding: included'.($shoeLf > 0 ? ' ('.$shoeLf.' lf)' : '');
            }
            if (! empty($c['include_quarter_round'])) {
                $qrLf = (float) ($c['quarter_round_lf'] ?? $floorLf);
                $lines[] = 'Quarter round: included'.($qrLf > 0 ? ' ('.$qrLf.' lf)' : '');
            }
            if (! empty($c['include_built_up_baseboard'])) {
                $buLf = (float) ($c['built_up_baseboard_lf'] ?? $floorLf);
                $lines[] = 'Built-up / hardwood baseboard: included'.($buLf > 0 ? ' ('.$buLf.' lf)' : '');
            }
        }

        if (in_array('ceiling', $enabled, true)) {
            if (! empty($c['include_crown'])) {
                $crownLf = (float) ($c['crown_lf'] ?? 0);
                $lines[] = 'Crown moulding: included'.($crownLf > 0 ? ' ('.$crownLf.' lf)' : '');
            }
            if (! empty($c['include_cove'])) {
                $coveLf = (float) ($c['cove_lf'] ?? 0);
                $lines[] = 'Cove moulding: included'.($coveLf > 0 ? ' ('.$coveLf.' lf)' : '');
            }
            if (! empty($c['include_built_up_crown'])) {
                $buCrown = (float) ($c['built_up_crown_lf'] ?? 0);
                $lines[] = 'Built-up / complex crown: included'.($buCrown > 0 ? ' ('.$buCrown.' lf)' : '');
            }
        }

        if (in_array('wall_treatments', $enabled, true)) {
            if (! empty($c['include_chair_rail'])) {
                $chairRailLf = (float) ($c['chair_rail_lf'] ?? 0);
                $lines[] = 'Chair rail: included'.($chairRailLf > 0 ? ' ('.$chairRailLf.' lf)' : '');
            }
            if (! empty($c['include_picture_rail'])) {
                $picLf = (float) ($c['picture_rail_lf'] ?? 0);
                $lines[] = 'Picture rail: included'.($picLf > 0 ? ' ('.$picLf.' lf)' : '');
            }
            if (! empty($c['include_box_moulding'])) {
                $boxLf = (float) ($c['box_moulding_lf'] ?? 0);
                $lines[] = 'Picture-frame / box moulding: included'.($boxLf > 0 ? ' ('.$boxLf.' lf)' : '');
            }
            if (! empty($c['include_wainscoting'])) {
                $wainscotLf = (float) ($c['wainscot_wall_lf'] ?? 0);
                $style = $c['wainscot_style'] ?? 'beadboard';
                $lines[] = 'Wainscoting ('.$style.'): included'.($wainscotLf > 0 ? ' ('.$wainscotLf.' lf)' : '');
            }
        }

        if (! empty($c['include_remove_existing'])) {
            $lines[] = 'Remove existing trim: included';
        }
        if (! empty($c['include_caulk_paint'])) {
            $lines[] = 'Caulk & paint: included';
        }
        if (! empty($c['include_stain'])) {
            $lines[] = 'Stain & clear coat: included';
        }

        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function shed(array $c): string
    {
        $lines = ['Quotation type: Custom shed'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $type = trim((string) ($c['shed_type'] ?? ''));
        if ($type !== '') {
            $this->appendLine($lines, 'Shed style', ShedQuotationCalculator::shedTypeLabel($type));
        }
        if (! empty($c['attached'])) {
            $lines[] = 'Attached to an existing building: yes';
        }

        $length = $c['length_ft'] ?? null;
        $width = $c['width_ft'] ?? null;
        if ($length !== null && $length !== '' && $width !== null && $width !== '') {
            $this->appendLine($lines, 'Floor size', $length.' ft × '.$width.' ft');
        }
        $this->appendLine($lines, 'Floor area', $c['area_label'] ?? null);
        $this->appendLine($lines, 'Wall height', isset($c['wall_height_ft']) && $c['wall_height_ft'] !== '' ? $c['wall_height_ft'].' ft' : null);
        $this->appendLine($lines, 'Roof pitch', isset($c['roof_pitch']) && $c['roof_pitch'] !== '' ? $c['roof_pitch'].'/12' : null);
        $this->appendLine($lines, 'Overhang', isset($c['overhang_in']) && $c['overhang_in'] !== '' ? $c['overhang_in'].' in' : null);

        $foundation = trim((string) ($c['foundation'] ?? ''));
        if ($foundation !== '') {
            $this->appendLine($lines, 'Foundation', ShedQuotationCalculator::foundationLabel($foundation));
        }
        $this->appendLine($lines, 'Joist size', $c['joist_size'] ?? null);
        $this->appendLine($lines, 'Joist spacing', isset($c['joist_spacing_in']) && $c['joist_spacing_in'] !== '' ? $c['joist_spacing_in'].' in' : null);
        $this->appendLine($lines, 'Stud size', $c['stud_size'] ?? null);
        $this->appendLine($lines, 'Stud spacing', isset($c['stud_spacing_in']) && $c['stud_spacing_in'] !== '' ? $c['stud_spacing_in'].' in' : null);

        $roof = trim((string) ($c['roof_type'] ?? ''));
        if ($roof !== '') {
            $this->appendLine($lines, 'Roof covering', ShedQuotationCalculator::roofLabel($roof));
        }
        $siding = trim((string) ($c['siding'] ?? ''));
        if ($siding !== '') {
            $this->appendLine($lines, 'Siding', ShedQuotationCalculator::sidingLabel($siding));
        }
        if (! empty($c['include_paint'])) {
            $lines[] = 'Exterior paint / stain: yes';
        }

        $doorQty = (int) ($c['door_qty'] ?? 0);
        $windowQty = (int) ($c['window_qty'] ?? 0);
        if ($doorQty > 0) {
            $door = ShedQuotationCalculator::DOOR_TYPES[(string) ($c['door_type'] ?? 'custom')] ?? 'Custom built';
            $lines[] = 'Doors: '.$doorQty.' × '.$door.(! empty($c['double_door']) ? ' (double)' : '');
        }
        if ($windowQty > 0) {
            $window = ShedQuotationCalculator::WINDOW_TYPES[(string) ($c['window_type'] ?? 'slider')] ?? 'Standard slider';
            $lines[] = 'Windows: '.$windowQty.' × '.$window;
        }

        $finish = (($c['interior_finish'] ?? 'rough') === 'finished') ? 'Fully finished (insulation and drywall)' : 'Rough / shell';
        $this->appendLine($lines, 'Interior', $finish);
        if (! empty($c['include_electrical'])) {
            $lines[] = 'Electrical: yes ('.($c['outlet_qty'] ?? 0).' outlets, '.($c['light_qty'] ?? 0).' lights'
                .(! empty($c['include_subpanel']) ? ', subpanel' : '').')';
        }
        if (! empty($c['include_loft'])) {
            $lines[] = 'Loft: yes';
        }
        if (! empty($c['include_ramp'])) {
            $lines[] = 'Ramp: yes';
        }
        if (! empty($c['include_gutters'])) {
            $lines[] = 'Gutters: yes';
        }
        if (! empty($c['include_plumbing'])) {
            $lines[] = 'Plumbing: yes';
        }
        if (! empty($c['client_provides_all'])) {
            $lines[] = 'Client provides all materials: yes';
        }

        $lines[] = 'Note: Only describe the shed components, sizes, and options explicitly listed above. Do not assume or invent unlisted features.';

        $this->appendBlock($lines, 'Labor scope', $c['labor_summary'] ?? null);
        $this->appendBlock($lines, 'Materials', $c['materials_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function basement(array $c): string
    {
        $lines = ['Quotation type: Basement renovation'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $enabled = BasementQuotationCalculator::enabledSections($c);
        $area = round(max(0, (float) ($c['area_sqft'] ?? 0)), 2);
        $height = isset($c['ceiling_height_ft']) ? round(max(0, (float) $c['ceiling_height_ft']), 2) : 0;

        if (isset($c['scope_preset'])) {
            $this->appendLine($lines, 'Scope', BasementQuotationCalculator::finishLabel($c));
        }
        if ($area > 0) {
            $this->appendLine($lines, 'Area', number_format($area, 2).' sq ft');
        }
        if ($height > 0) {
            $this->appendLine($lines, 'Ceiling height', $height.' ft');
        }

        // Explicitly contrast included trades vs excluded trades when custom sections or preset are set
        $hasCustomSections = (isset($c['enabled_sections']) && is_array($c['enabled_sections'])) || isset($c['scope_preset']);
        if ($hasCustomSections) {
            $allTradeKeys = array_keys(BasementQuotationCalculator::tradeSections());
            $includedTrades = [];
            $excludedTrades = [];
            foreach ($allTradeKeys as $tradeKey) {
                $label = BasementQuotationCalculator::SECTIONS[$tradeKey] ?? $tradeKey;
                if (in_array($tradeKey, $enabled, true)) {
                    $includedTrades[] = $label;
                } else {
                    $excludedTrades[] = $label;
                }
            }

            if ($includedTrades !== []) {
                $lines[] = 'Included trades in this quotation: '.implode(', ', $includedTrades);
            }
            if ($excludedTrades !== []) {
                $lines[] = 'Trades NOT included in this quotation: '.implode(', ', $excludedTrades).'. Strictly DO NOT describe or assume any of these excluded trades.';
            }
        }

        // Demolition: only if demolition trade is enabled and items are checked
        if (in_array('demolition', $enabled, true) && ! empty($c['include_demolition'])) {
            $demoParts = [];
            if (! empty($c['demo_include_studs'])) {
                $studLf = (float) ($c['demo_studs_lf'] ?? 0);
                $demoParts[] = 'studs'.($studLf > 0 ? ' ('.$studLf.' lf)' : '');
            }
            if (! empty($c['demo_include_drywall'])) {
                $dwSf = (float) ($c['demo_drywall_sf'] ?? 0);
                $demoParts[] = 'drywall'.($dwSf > 0 ? ' ('.$dwSf.' sq ft)' : '');
            }
            if (! empty($c['demo_include_flooring'])) {
                $flSf = (float) ($c['demo_flooring_sf'] ?? 0);
                $demoParts[] = 'flooring'.($flSf > 0 ? ' ('.$flSf.' sq ft)' : '');
            }
            if (! empty($c['demo_include_baseboard'])) {
                $bbLf = (float) ($c['demo_baseboard_lf'] ?? 0);
                $demoParts[] = 'baseboard'.($bbLf > 0 ? ' ('.$bbLf.' lf)' : '');
            }
            $demoSummary = [];
            if (! empty($c['demo_include_flooring'])) {
                $demoSummary[] = 'flooring';
            }
            if (! empty($c['demo_include_studs'])) {
                $demoSummary[] = 'studs';
            }
            if (! empty($c['demo_include_drywall'])) {
                $demoSummary[] = 'drywall';
            }
            if (! empty($c['demo_include_baseboard'])) {
                $demoSummary[] = 'baseboard';
            }
            $lines[] = 'Demolition: '.($demoSummary !== [] ? implode(', ', $demoSummary) : 'yes');
            if ($demoParts !== []) {
                $lines[] = 'Demolition details: '.implode(', ', $demoParts);
            }
        }

        // Framing: only if framing trade is enabled
        if (in_array('framing', $enabled, true)) {
            $roomCount = (int) ($c['room_count'] ?? 1);
            if ($roomCount > 1) {
                $this->appendLine($lines, 'Room count', $roomCount);
            }
            if ((float) ($c['partition_lf'] ?? 0) > 0) {
                $lines[] = 'Partition framing: '.(float) $c['partition_lf'].' lf';
            }
        }

        // Drywall: only if drywall trade is enabled
        if (in_array('drywall', $enabled, true) && ($area > 0 || isset($c['finish_level']))) {
            $finishLevel = (int) ($c['finish_level'] ?? 4);
            $levelLabel = BasementQuotationCalculator::FINISH_LEVELS[$finishLevel] ?? 'Level '.$finishLevel;
            $lines[] = 'Drywall finish level: '.$levelLabel;
        }

        // Flooring: only if flooring trade is enabled and specified
        if (in_array('flooring', $enabled, true) && ! empty($c['flooring_type'])) {
            $this->appendLine($lines, 'Flooring', $c['flooring_type']);
        }

        // Ceiling: only if ceiling trade is enabled and specified
        if (in_array('ceiling', $enabled, true) && ! empty($c['ceiling_type'])) {
            $this->appendLine($lines, 'Ceiling', $c['ceiling_type']);
        }

        // Doors & Trim: only if doors_trim trade is enabled
        if (in_array('doors_trim', $enabled, true)) {
            $doorQty = (int) ($c['door_qty'] ?? 0);
            if ($doorQty > 0) {
                $lines[] = 'Interior doors: '.$doorQty;
            }
            $casing = (int) ($c['casing_openings'] ?? 0);
            if ($casing > 0) {
                $lines[] = 'Door casing openings: '.$casing;
            }
            $baseLf = (float) ($c['baseboard_lf'] ?? 0);
            if ($baseLf > 0) {
                $lines[] = 'Baseboard: '.$baseLf.' lf';
            }
        }

        // Insulation: only if insulation trade is enabled
        if (in_array('insulation', $enabled, true)) {
            $insParts = [];
            if (! empty($c['insulate_walls'])) {
                $insParts[] = 'wall insulation + vapor barrier';
            }
            if (! empty($c['insulate_ceiling'])) {
                $insParts[] = 'ceiling insulation';
            }
            if (! empty($c['soundproof_interior'])) {
                $insParts[] = 'interior soundproofing';
            }
            if ($insParts !== []) {
                $lines[] = 'Insulation: '.implode(', ', $insParts);
            }
        }

        // Electrical: only if electrical trade is enabled
        if (in_array('electrical', $enabled, true)) {
            $elec = [];
            if ((int) ($c['outlet_qty'] ?? 0) > 0) {
                $elec[] = (int) $c['outlet_qty'].' outlets';
            }
            if ((int) ($c['switch_qty'] ?? 0) > 0) {
                $elec[] = (int) $c['switch_qty'].' switches';
            }
            if ((int) ($c['potlight_qty'] ?? 0) > 0) {
                $elec[] = (int) $c['potlight_qty'].' potlights';
            }
            if ((int) ($c['detector_qty'] ?? 0) > 0) {
                $elec[] = (int) $c['detector_qty'].' smoke/CO detectors';
            }
            if ($elec !== []) {
                $lines[] = 'Electrical scope: '.implode(', ', $elec);
            }
        }

        // Bathroom: only if bathroom trade is enabled
        if (in_array('bathroom', $enabled, true)) {
            $lines[] = 'Bathroom: yes';
        }

        // Painting: only if painting trade is enabled
        if (in_array('painting', $enabled, true)) {
            $paintParts = [];
            if (! empty($c['paint_walls'])) {
                $paintParts[] = 'walls';
            }
            if (! empty($c['paint_ceiling'])) {
                $paintParts[] = 'ceiling';
            }
            if (! empty($c['paint_doors'])) {
                $paintParts[] = 'doors & trim';
            }
            if ($paintParts !== []) {
                $lines[] = 'Painting scope: '.implode(', ', $paintParts);
            }
        }

        // Finishing: only if finishing trade is enabled
        if (in_array('finishing', $enabled, true)) {
            $finParts = [];
            if (! empty($c['include_cleaning'])) {
                $finParts[] = 'final cleaning';
            }
            if (! empty($c['include_touchups'])) {
                $finParts[] = 'touch-ups';
            }
            if ($finParts !== []) {
                $lines[] = 'Final cleanup / details: '.implode(', ', $finParts);
            }
        }

        // Calculate accurate labor tasks and materials from the calculator
        $calculatedLabor = false;
        $calculatedMats = false;
        if ($area > 0 || (float) ($c['partition_lf'] ?? 0) > 0) {
            try {
                $calc = (new BasementQuotationCalculator)->calculate($c);

                $laborLines = [];
                foreach ($calc['labor'] ?? [] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $lbl = trim((string) ($row['label'] ?? ''));
                    $det = trim((string) ($row['detail'] ?? ''));
                    if ($lbl !== '') {
                        $laborLines[] = '  - '.$lbl.($det !== '' ? ' ('.$det.')' : '');
                    }
                }
                if ($laborLines !== []) {
                    $lines[] = 'Quoted labor tasks:';
                    $lines = array_merge($lines, $laborLines);
                    $calculatedLabor = true;
                }

                $matLines = [];
                foreach ($calc['materials'] ?? [] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $lbl = trim((string) ($row['label'] ?? ''));
                    $qty = (float) ($row['qty'] ?? 0);
                    $unit = trim((string) ($row['unit'] ?? ''));
                    $provided = ! empty($row['client_provided']) ? ' [client provided]' : '';
                    if ($lbl !== '' && $qty > 0) {
                        $qtyStr = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
                        $matLines[] = '  - '.$lbl.' ('.$qtyStr.($unit !== '' ? ' '.$unit : '').')'.$provided;
                    }
                }
                foreach ($calc['extra_materials'] ?? [] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $lbl = trim((string) ($row['name'] ?? ($row['short_name'] ?? '')));
                    $qty = (float) ($row['quantity'] ?? ($row['qty'] ?? 0));
                    $unit = trim((string) ($row['unit'] ?? ''));
                    if ($lbl !== '' && $qty > 0) {
                        $qtyStr = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
                        $matLines[] = '  - '.$lbl.' ('.$qtyStr.($unit !== '' ? ' '.$unit : '').')';
                    }
                }
                if ($matLines !== []) {
                    $lines[] = 'Quoted materials:';
                    $lines = array_merge($lines, $matLines);
                    $calculatedMats = true;
                }
            } catch (\Throwable $e) {
                // Fall back to submitted summary blocks if calculation fails
            }
        }

        if (! $calculatedLabor) {
            $this->appendBlock($lines, 'Labor scope', $c['labor_summary'] ?? null);
        }
        if (! $calculatedMats) {
            $this->appendBlock($lines, 'Materials', $c['materials_summary'] ?? null);
        }

        $this->appendBlock($lines, 'Site notes', $c['existing_notes'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function millwork(array $c): string
    {
        $lines = ['Quotation type: Custom millwork'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        try {
            $calc = (new MillworkQuotationCalculator)->calculate($c);

            $pieces = is_array($c['pieces'] ?? null) ? $c['pieces'] : [];
            if ($pieces !== []) {
                $lines[] = 'Pieces:';
                foreach ($pieces as $idx => $p) {
                    if (! is_array($p)) {
                        continue;
                    }
                    $type = trim((string) ($p['piece_type'] ?? $p['type'] ?? 'shelf'));
                    $type = str_replace('_', ' ', $type);
                    $qty = max(1, (int) ($p['quantity'] ?? $p['qty'] ?? 1));
                    $unit = ($p['unit'] ?? 'in') === 'ft' ? 'ft' : 'in';
                    $dim = [];
                    $w = $p['width_in'] ?? $p['width'] ?? null;
                    if (! empty($w)) {
                        $dim[] = 'W: '.$w.$unit;
                    }
                    $l = $p['length_ft'] ?? $p['length'] ?? null;
                    if (! empty($l)) {
                        $dim[] = 'L: '.$l.$unit;
                    }
                    $h = $p['height_in'] ?? $p['height'] ?? null;
                    if (! empty($h)) {
                        $dim[] = 'H: '.$h.$unit;
                    }
                    $d = $p['depth_in'] ?? $p['depth'] ?? null;
                    if (! empty($d)) {
                        $dim[] = 'D: '.$d.$unit;
                    }
                    $dimStr = $dim !== [] ? ' ('.implode(', ', $dim).')' : '';
                    $lines[] = '  - Piece '.($idx + 1).': '.$qty.' × '.ucwords($type).$dimStr;
                }
            }

            $sheetName = $calc['sheet']['name'] ?? $c['sheet_material_type'] ?? null;
            if (! empty($sheetName)) {
                $lines[] = 'Sheet material: '.$sheetName;
            }
            $edgeName = $calc['edge_banding']['name'] ?? $c['edge_banding_type'] ?? null;
            if (! empty($edgeName)) {
                $lines[] = 'Edge banding: '.$edgeName;
            }
            $finishName = $calc['finish']['name'] ?? $c['finish_type'] ?? null;
            if (! empty($finishName)) {
                $lines[] = 'Finish: '.$finishName;
            }
            $hasInstall = ! empty($c['include_install']) || ! empty($c['include_installation']);
            $lines[] = 'Installation included: '.($hasInstall ? 'yes' : 'no');

            $laborLines = [];
            foreach ($calc['labor'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lbl = trim((string) ($row['label'] ?? ''));
                $det = trim((string) ($row['detail'] ?? ''));
                if ($lbl !== '') {
                    $laborLines[] = '  - '.$lbl.($det !== '' ? ' ('.$det.')' : '');
                }
            }
            if ($laborLines !== []) {
                $lines[] = 'Quoted labor tasks:';
                $lines = array_merge($lines, $laborLines);
            }

            $matLines = [];
            foreach ($calc['materials'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lbl = trim((string) ($row['label'] ?? ''));
                $qty = (float) ($row['qty'] ?? 0);
                $unit = trim((string) ($row['unit'] ?? ''));
                if ($lbl !== '' && $qty > 0) {
                    $qtyStr = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
                    $matLines[] = '  - '.$lbl.' ('.$qtyStr.($unit !== '' ? ' '.$unit : '').')';
                }
            }
            if ($matLines !== []) {
                $lines[] = 'Quoted materials:';
                $lines = array_merge($lines, $matLines);
            }
        } catch (\Throwable $e) {
            $this->appendLine($lines, 'Sheet goods', $c['sheet_id'] ?? null);
            $this->appendLine($lines, 'Finish', $c['finish_label'] ?? null);
            $this->appendBlock($lines, 'Pieces', $c['piece_summary'] ?? null);
            $this->appendBlock($lines, 'Labor scope', $c['labor_summary'] ?? null);
            $this->appendBlock($lines, 'Materials', $c['materials_summary'] ?? null);
        }

        $this->appendBlock($lines, 'Site notes', $c['notes'] ?? ($c['existing_notes'] ?? null));

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function stairs(array $c): string
    {
        $lines = ['Quotation type: Stair refinishing'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);
        $this->appendLine($lines, 'Total steps', $c['total_steps'] ?? null);
        $this->appendLine($lines, 'Material / wood type', $c['material_type'] ?? null);
        if (! empty($c['nosing_choice'])) {
            $this->appendLine($lines, 'Nosing option', $c['nosing_choice']);
        }
        if (! empty($c['carpet_removal'])) {
            $lines[] = 'Carpet removal: yes';
        }
        if (! empty($c['requires_stain'])) {
            $lines[] = 'Staining: yes';
        }
        if (! empty($c['requires_clear_coat'])) {
            $lines[] = 'Clear coat finish: yes';
        }
        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function flooring(array $c): string
    {
        $lines = ['Quotation type: Flooring installation'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $sqft = $c['square_footage'] ?? null;
        if ($sqft !== null && (float) $sqft > 0) {
            $this->appendLine($lines, 'Square footage', $sqft.' sq ft');
        }

        $mat = trim((string) ($c['material_name'] ?? ($c['flooring_type'] ?? '')));
        if ($mat !== '') {
            $this->appendLine($lines, 'Flooring material', $mat);
        }

        if (! empty($c['remove_carpet'])) {
            $lines[] = 'Carpet removal: yes';
        }
        if (! empty($c['rescrew_subfloor'])) {
            $lines[] = 'Subfloor re-screwing / preparation: yes';
        }
        if (! empty($c['install_shoe_moulding'])) {
            $shoeLf = (float) ($c['shoe_moulding_linear_ft'] ?? 0);
            $lines[] = 'Install shoe moulding: yes'.($shoeLf > 0 ? ' ('.$shoeLf.' lf)' : '');
        }
        if (! empty($c['caulk_paint_shoe_moulding'])) {
            $lines[] = 'Caulk and paint shoe moulding: yes';
        }
        if (! empty($c['install_baseboard'])) {
            $baseLf = (float) ($c['baseboard_linear_ft'] ?? 0);
            $lines[] = 'Install baseboard: yes'.($baseLf > 0 ? ' ('.$baseLf.' lf)' : '');
        }
        if (! empty($c['caulk_paint_baseboard'])) {
            $lines[] = 'Caulk and paint baseboard: yes';
        }
        if (! empty($c['closet_doors'])) {
            $doors = (int) ($c['closet_door_qty'] ?? 0);
            $lines[] = 'Closet doors: yes'.($doors > 0 ? ' ('.$doors.' door(s))' : '');
        }

        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function tile(array $c): string
    {
        $lines = ['Quotation type: Tile installation'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);

        $len = $c['length_ft'] ?? null;
        $wid = $c['width_ft'] ?? null;
        if ($len && $wid) {
            $this->appendLine($lines, 'Area dimensions', $len.' ft × '.$wid.' ft');
        }
        $sqft = $c['total_area_sqft'] ?? null;
        if ($sqft !== null && $sqft !== '' && (float) $sqft > 0) {
            $this->appendLine($lines, 'Total area', $sqft.' sq ft');
        }

        if (! empty($c['include_tile_material'])) {
            $tileName = $this->tileCatalogName($c['tile_product_name'] ?? null, $c['tile_product_id'] ?? null, TileProduct::class);
            $this->appendLine($lines, 'Tile product', $tileName ?? 'Contractor supplied');
        } else {
            $lines[] = 'Tile material: Client provided';
        }

        $mortarName = $this->tileCatalogName($c['mortar_type_name'] ?? null, $c['mortar_type_id'] ?? null, TileMortarType::class);
        $this->appendLine($lines, 'Mortar', $mortarName);
        if (! empty($c['trowel_size'])) {
            $this->appendLine($lines, 'Trowel size', $c['trowel_size']);
        }
        $groutName = $this->tileCatalogName($c['grout_type_name'] ?? null, $c['grout_type_id'] ?? null, TileGroutType::class);
        $this->appendLine($lines, 'Grout', $groutName);
        if (! empty($c['joint_width'])) {
            $this->appendLine($lines, 'Joint width', $c['joint_width']);
        }
        if (! empty($c['include_edge'])) {
            $edgeName = $this->tileCatalogName($c['tile_edge_name'] ?? null, $c['tile_edge_id'] ?? null, TileEdge::class);
            $edgeLf = $c['edge_lf'] ?? null;
            $edgeLine = $edgeName ?? 'yes';
            if ($edgeLf !== null && $edgeLf !== '' && (float) $edgeLf > 0) {
                $edgeLine .= ' ('.$edgeLf.' LF)';
            }
            $this->appendLine($lines, 'Tile edge', $edgeLine);
        }
        if (! empty($c['include_demolition'])) {
            $lines[] = 'Demolition: yes';
        }
        if (! empty($c['include_disposal_fee'])) {
            $lines[] = 'Disposal fee: yes';
        }

        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);

        return implode("\n", $lines);
    }

    /**
     * Use the catalog product name. Resolve a numeric id when possible so the
     * AI does not invent labels like "Mortar Type 1".
     */
    private function tileCatalogName(mixed $label, mixed $id, string $modelClass): ?string
    {
        $text = trim((string) ($label ?? ''));
        $rawId = $id !== null && $id !== '' ? $id : (ctype_digit($text) ? $text : null);
        if (is_numeric($rawId) && (int) $rawId > 0) {
            $row = $modelClass::query()->find((int) $rawId);
            $name = $row ? trim((string) ($row->name ?? '')) : '';
            if ($name !== '') {
                return $name;
            }
        }

        if ($text === '' || str_starts_with($text, '--') || ctype_digit($text)) {
            return null;
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function generic(string $type, array $c): string
    {
        $labels = [
            'painting' => 'Residential interior painting',
            'fence' => 'Fence installation',
            'paving' => 'Paving stones / hardscape',
            'low_deck' => 'Low floating deck',
            'trimming' => 'Interior trimming',
            'millwork' => 'Custom millwork',
            'stairs' => 'Stair refinishing',
            'flooring' => 'Flooring installation',
            'tile' => 'Tile installation',
        ];
        $lines = ['Quotation type: '.($labels[$type] ?? $type)];
        $this->flattenInto($lines, $c);

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, mixed>  $c
     * @param  list<string>  $skipKeys
     */
    private function flattenInto(array &$lines, array $c, string $prefix = '', array $skipKeys = []): void
    {
        foreach ($c as $key => $value) {
            $keyStr = (string) $key;
            if ($this->shouldSkipContextKey($keyStr) || in_array($keyStr, $skipKeys, true)) {
                continue;
            }
            $label = $prefix === '' ? $this->humanizeKey($keyStr) : $prefix.' / '.$this->humanizeKey($keyStr);
            if (is_bool($value)) {
                if ($value) {
                    $lines[] = $label.': yes';
                }
                continue;
            }
            if (is_array($value)) {
                $this->flattenInto($lines, $value, $label, $skipKeys);
                continue;
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $lines[] = $label.': '.$value;
            }
        }
    }

    private function shouldSkipContextKey(string $key): bool
    {
        if (in_array($key, [
            '_token', '_method', 'save_intent', 'work_description',
            'owner_hourly_rate', 'helper_hourly_rate', 'link_client_project_id',
            'tm_client_pick', 'include_commute', 'include_debris_removal',
        ], true)) {
            return true;
        }
        if (str_contains($key, 'override')) {
            return true;
        }
        if (str_starts_with($key, 'payment_') || str_starts_with($key, 'commute_') || str_starts_with($key, 'fp_')) {
            return true;
        }
        if (str_contains($key, 'unit_price') || str_contains($key, 'hourly_rate')) {
            return true;
        }

        return false;
    }

    private function humanizeKey(string $key): string
    {
        if (is_numeric($key)) {
            return '#'.((int) $key + 1);
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendLine(array &$lines, string $label, mixed $value): void
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text !== '') {
            $lines[] = $label.': '.$text;
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendBlock(array &$lines, string $label, mixed $value): void
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text !== '') {
            $lines[] = $label.':';
            $lines[] = $text;
        }
    }

    private function sqfLabel(float|int|string|null $sqf): string
    {
        $n = round((float) $sqf, 0);

        return number_format($n).' sq ft';
    }
}

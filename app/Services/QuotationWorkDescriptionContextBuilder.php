<?php

namespace App\Services;

use App\Models\ClientProject;

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
        $this->appendLine($lines, 'Width', isset($c['width_ft']) ? $c['width_ft']." ft" : null);
        $this->appendLine($lines, 'Length', isset($c['length_ft']) ? $c['length_ft']." ft" : null);
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
        $this->appendLine($lines, 'Project area', $c['total_area_sq_ft'] ?? null);
        $this->appendLine($lines, 'Stone type', $c['stone_type'] ?? null);
        $this->appendLine($lines, 'Waste factor', $c['waste_factor'] ?? null);
        $this->appendLine($lines, 'Excavation depth', $c['excavation_depth_in'] ?? null);
        $this->appendLine($lines, 'Profit margin', $c['profit_margin'] ?? null);
        $this->appendLine($lines, 'Mini skid steer rental days', $c['mini_skid_days'] ?? null);
        $this->appendLine($lines, 'Plate compactor rental days', $c['plate_compactor_days'] ?? null);
        $this->appendLine($lines, 'Helper hourly rate', $c['helper_hourly_rate'] ?? null);
        $this->appendLine($lines, 'Owner hourly rate', $c['owner_hourly_rate'] ?? null);

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

        $enabled = $c['enabled_families'] ?? [];
        if (is_array($enabled) && $enabled !== []) {
            $labels = [];
            foreach ($enabled as $key) {
                $label = TrimmingQuotationCalculator::FAMILIES[(string) $key] ?? null;
                if (is_string($label) && $label !== '') {
                    $labels[] = $label;
                }
            }
            if ($labels !== []) {
                $lines[] = 'Families: '.implode(', ', $labels);
            }
        }

        $this->appendLine($lines, 'Door openings', $c['door_qty'] ?? null);
        $this->appendLine($lines, 'Window openings', $c['window_qty'] ?? null);
        $this->appendLine($lines, 'Floor-to-wall linear feet', $c['floor_lf'] ?? null);
        $this->appendLine($lines, 'Crown linear feet', $c['crown_lf'] ?? null);
        $this->appendLine($lines, 'Cove linear feet', $c['cove_lf'] ?? null);
        $this->appendLine($lines, 'Chair rail linear feet', $c['chair_rail_lf'] ?? null);
        $this->appendLine($lines, 'Wainscot wall linear feet', $c['wainscot_wall_lf'] ?? null);
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
        $this->appendLine($lines, 'Scope', $c['scope_preset'] ?? null);
        $this->appendLine($lines, 'Area', isset($c['area_sqft']) && $c['area_sqft'] !== '' ? $c['area_sqft'].' sq ft' : null);
        $this->appendLine($lines, 'Ceiling height', isset($c['ceiling_height_ft']) && $c['ceiling_height_ft'] !== '' ? $c['ceiling_height_ft'].' ft' : null);
        $this->appendLine($lines, 'Room count', $c['room_count'] ?? null);
        $this->appendLine($lines, 'Flooring', $c['flooring_type'] ?? null);
        $this->appendLine($lines, 'Ceiling', $c['ceiling_type'] ?? null);
        if (! empty($c['include_bathroom'])) {
            $lines[] = 'Bathroom: yes';
        }
        if (! empty($c['include_demolition'])) {
            $lines[] = 'Demolition: yes';
        }
        $this->appendBlock($lines, 'Site notes', $c['existing_notes'] ?? null);
        $this->appendBlock($lines, 'Labor scope', $c['labor_summary'] ?? null);
        $this->appendBlock($lines, 'Materials', $c['materials_summary'] ?? null);
        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);
        $this->flattenInto($lines, $c, '', [
            'client_name', 'scope_preset', 'area_sqft', 'area_entry_mode', 'area_length_ft', 'area_width_ft',
            'ceiling_height_ft', 'room_count', 'flooring_type', 'ceiling_type', 'include_bathroom',
            'include_demolition', 'existing_notes', 'labor_summary', 'materials_summary', 'breakdown_summary',
        ]);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function millwork(array $c): string
    {
        $lines = ['Quotation type: Custom millwork'];
        $this->appendLine($lines, 'Client', $c['client_name'] ?? null);
        $this->appendLine($lines, 'Sheet goods', $c['sheet_id'] ?? null);
        $this->appendLine($lines, 'Finish', $c['finish_label'] ?? null);
        $this->appendBlock($lines, 'Pieces', $c['piece_summary'] ?? null);
        $this->appendBlock($lines, 'Labor scope', $c['labor_summary'] ?? null);
        $this->appendBlock($lines, 'Materials', $c['materials_summary'] ?? null);
        $this->appendBlock($lines, 'Live breakdown from form', $c['breakdown_summary'] ?? null);
        $this->flattenInto($lines, $c, '', [
            'client_name', 'sheet_id', 'finish_label', 'piece_summary',
            'labor_summary', 'materials_summary', 'breakdown_summary',
        ]);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function generic(string $type, array $c): string
    {
        $labels = [
            'stairs' => 'Stair refinishing',
            'flooring' => 'Flooring',
            'tile' => 'Tile',
            'painting' => 'Residential interior painting',
            'fence' => 'Fence installation',
            'paving' => 'Paving stones / hardscape',
            'low_deck' => 'Low floating deck',
            'trimming' => 'Interior trimming',
            'millwork' => 'Custom millwork',
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

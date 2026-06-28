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
            'paving' => $this->paving($context),
            'standard' => $this->standard($context),
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
                $sideParts[] = 'Side '.($i + 1).': '.rtrim(rtrim(number_format((float) $len, 1, '.', ''), '0'), '.').' linear ft';
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
    private function generic(string $type, array $c): string
    {
        $lines = ['Quotation type: '.$type];
        foreach ($c as $key => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $lines[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.$value;
            }
        }

        return implode("\n", $lines);
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

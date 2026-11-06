<?php

namespace App\Services;

/**
 * Client-facing basement scope copy and Base / Standard / Upgrade packages.
 *
 * Default Included / Not included / Assumptions / Allowances text is derived
 * from wizard toggles and optional extras so unchecked services are never promised.
 */
class BasementClientScope
{
    public const VALUE_TIERS = [
        'base' => 'Base',
        'standard' => 'Standard',
        'upgrade' => 'Upgrade',
    ];

    public const COPY_KEYS = [
        'included' => 'client_scope_included',
        'not_included' => 'client_scope_not_included',
        'assumptions' => 'client_scope_assumptions',
        'allowances' => 'client_scope_allowances',
    ];

    public static function normalizeTier(mixed $tier): string
    {
        $value = strtolower(trim((string) $tier));

        return array_key_exists($value, self::VALUE_TIERS) ? $value : 'standard';
    }

    public static function flooringTypeForTier(string $tier, string $current): string
    {
        $current = strtolower(trim($current));
        if ($current === '' || $current === 'none') {
            return 'none';
        }
        $tier = self::normalizeTier($tier);

        return match ($tier) {
            'base' => 'lvp',
            'upgrade' => 'hardwood',
            default => $current,
        };
    }

    public static function contractorFeeLabel(float $percent): string
    {
        return 'Contractor fee '.self::formatPercent($percent).'%';
    }

    public static function formatPercent(float $percent): string
    {
        $formatted = number_format(max(0.0, $percent), 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    public static function assumptionsFeeSentence(float $percent): string
    {
        $pct = self::formatPercent($percent);

        return 'A contractor fee of '.$pct.'% applies to materials and subcontracted services, not Zanini hourly labour. The same fee also applies to written change orders.';
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $calc
     * @return array{included: string, not_included: string, assumptions: string, allowances: string}
     */
    public static function generatedCopy(array $inputs, array $calc = []): array
    {
        $enabled = BasementQuotationCalculator::enabledSections($inputs);
        $included = [];
        $notIncluded = [];

        $tradeLabels = BasementQuotationCalculator::tradeSections();
        foreach ($tradeLabels as $key => $label) {
            if (in_array($key, $enabled, true)) {
                if ($key === 'electrical') {
                    $included[] = 'Electrical coordination — Jonas arranges certified electricians for quoted electrical work.';
                } elseif ($key === 'plumbing') {
                    $included[] = 'Plumbing coordination — Jonas arranges certified plumbers for quoted plumbing work.';
                } elseif ($key === 'flooring') {
                    $type = strtolower(trim((string) ($inputs['flooring_type'] ?? 'lvp')));
                    if ($type !== 'none') {
                        $included[] = 'Flooring: '.(BasementQuotationCalculator::FLOORING_TYPES[$type] ?? 'LVP').'. Carpet is not offered.';
                    }
                } elseif ($key === 'bathroom' && empty($inputs['include_bathroom'])) {
                    $notIncluded[] = $label;
                    continue;
                } else {
                    $included[] = $label;
                }
            } else {
                $notIncluded[] = $label;
            }
        }

        if (! empty($inputs['include_bathroom']) && in_array('bathroom', $enabled, true)) {
            $type = ($inputs['bath_type'] ?? '') === 'half' ? 'Half bathroom' : 'Full bathroom';
            $included[] = $type.' finishing as specified on this quotation.';
        }

        $permitOn = ! empty($inputs['include_building_permit']);
        $bcinOn = ! empty($inputs['include_bcin_drawings']);
        if ($permitOn) {
            $included[] = 'City building permit as an optional extra-charge service (priced separately).';
        } else {
            $notIncluded[] = 'City building permit — optional extra-charge service, not included by default.';
        }
        if ($bcinOn) {
            $included[] = 'BCIN drawings as an optional extra-charge service (priced separately).';
        } else {
            $notIncluded[] = 'BCIN drawings — optional extra-charge service, not included by default.';
        }

        $notIncluded[] = 'Carpet flooring.';
        if (! $permitOn || ! $bcinOn) {
            $notIncluded[] = 'Any optional extra-charge item that is not listed under Included.';
        }

        $feePercent = BasementQuotationCalculator::resolvedContractorFeePercent($inputs);
        $assumptions = [
            'Work is designed to meet or exceed the Ontario Building Code (O.Reg. 332/12) for Part 9 housing.',
            self::assumptionsFeeSentence($feePercent),
            'Zanini Renovations is fully insured.',
            'The client provides clear access to the work area during standard working hours, plus electricity and water at no cost to Zanini.',
            'Finish materials, colours, and fixture models are confirmed before ordering. Substitutions of equal quality may be used if a specified item is unavailable.',
        ];

        $allowances = [];
        $elec = AdditionalCostsService::normalizeRows($inputs['electrical_lines'] ?? [], 'Electrical work');
        $plumb = AdditionalCostsService::normalizeRows($inputs['plumbing_lines'] ?? [], 'Plumbing work');
        $elecTax = AdditionalCostsService::taxInclusiveTotal($elec);
        $plumbTax = AdditionalCostsService::taxInclusiveTotal($plumb);
        if ($elecTax > 0) {
            $allowances[] = 'Electrical work allowance CAD$'.number_format($elecTax, 2, '.', ',').' (HST included in the trade line).';
        }
        if ($plumbTax > 0) {
            $allowances[] = 'Plumbing work allowance CAD$'.number_format($plumbTax, 2, '.', ',').' (HST included in the trade line).';
        }
        $permitAmt = $permitOn ? round(max(0, (float) ($inputs['building_permit_amount'] ?? 0)), 2) : 0.0;
        $bcinAmt = $bcinOn ? round(max(0, (float) ($inputs['bcin_drawings_amount'] ?? 0)), 2) : 0.0;
        if ($permitAmt > 0) {
            $allowances[] = 'City building permit extra-charge CAD$'.number_format($permitAmt, 2, '.', ',').' before HST.';
        }
        if ($bcinAmt > 0) {
            $allowances[] = 'BCIN drawings extra-charge CAD$'.number_format($bcinAmt, 2, '.', ',').' before HST.';
        }
        if ($allowances === []) {
            $allowances[] = 'No separate cash allowances beyond the priced lines on this quotation.';
        }

        return [
            'included' => self::toBulletText($included),
            'not_included' => self::toBulletText($notIncluded),
            'assumptions' => self::toBulletText($assumptions),
            'allowances' => self::toBulletText($allowances),
        ];
    }

    /**
     * Stored copy when the admin edited it; otherwise generated defaults.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $calc
     * @return array{included: string, not_included: string, assumptions: string, allowances: string, generated: array{included: string, not_included: string, assumptions: string, allowances: string}}
     */
    public static function resolveCopy(array $inputs, array $calc = []): array
    {
        $generated = self::generatedCopy($inputs, $calc);
        $out = ['generated' => $generated];
        foreach (self::COPY_KEYS as $short => $inputKey) {
            $stored = trim((string) ($inputs[$inputKey] ?? ''));
            $out[$short] = $stored !== '' ? $stored : $generated[$short];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $originalInputs
     * @param  array<string, mixed>  $standardResult
     * @return array<string, array<string, mixed>>
     */
    public static function buildValuePackages(
        BasementQuotationCalculator $calculator,
        array $originalInputs,
        array $standardResult,
        ?\App\Models\BasementSettings $settings = null,
    ): array {
        $currentType = strtolower(trim((string) ($standardResult['scope']['flooring_type'] ?? $originalInputs['flooring_type'] ?? 'lvp')));
        if ($currentType === '') {
            $currentType = 'lvp';
        }
        $notes = is_array($originalInputs['value_package_notes'] ?? null)
            ? $originalInputs['value_package_notes']
            : [];
        $packages = [];
        foreach (array_keys(self::VALUE_TIERS) as $tier) {
            $type = self::flooringTypeForTier($tier, $currentType);
            if ($tier === 'standard' || $type === $currentType) {
                $packages[$tier] = self::packageFromResult($tier, $type, $standardResult, $notes);
                continue;
            }
            $pkgInputs = $originalInputs;
            $pkgInputs['flooring_type'] = $type;
            $pkgInputs['flooring_waste_percent'] = BasementQuotationCalculator::flooringWasteDefault($type);
            $pkgResult = $calculator->calculate($pkgInputs, $settings, false);
            $packages[$tier] = self::packageFromResult($tier, $type, $pkgResult, $notes);
        }

        return $packages;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $notes
     * @return array<string, mixed>
     */
    public static function packageFromResult(string $tier, string $flooringType, array $result, array $notes = []): array
    {
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $label = self::VALUE_TIERS[$tier] ?? ucfirst($tier);
        $floorLabel = BasementQuotationCalculator::FLOORING_TYPES[$flooringType] ?? ($flooringType === 'none' ? 'No flooring' : $flooringType);
        $storedNote = trim((string) ($notes[$tier] ?? ''));
        $defaultNote = match ($tier) {
            'base' => $flooringType === 'none' ? 'Same scope; no finish flooring.' : 'Vinyl plank (LVP) finish flooring.',
            'upgrade' => $flooringType === 'none' ? 'Same scope; no finish flooring.' : 'Hardwood finish flooring.',
            default => $flooringType === 'none' ? 'As specified; no finish flooring.' : 'As specified: '.$floorLabel.'.',
        };

        return [
            'tier' => $tier,
            'label' => $label,
            'flooring_type' => $flooringType,
            'flooring_label' => $floorLabel,
            'note' => $storedNote !== '' ? $storedNote : $defaultNote,
            'labor_pre_hst' => round((float) ($summary['labor_pre_hst'] ?? 0), 2),
            'materials_pre_hst' => round((float) ($summary['materials_pre_hst'] ?? 0), 2),
            'contractor_fee_percent' => round((float) ($summary['contractor_fee_percent'] ?? 0), 2),
            'contractor_fee_pre_hst' => round((float) ($summary['contractor_fee_pre_hst'] ?? 0), 2),
            'subtotal_pre_hst' => round((float) ($summary['subtotal_pre_hst'] ?? 0), 2),
            'hst' => round((float) ($summary['hst'] ?? 0), 2),
            'trade_lines_tax_in' => round((float) ($summary['trade_lines_tax_in'] ?? 0), 2),
            'grand_total' => round((float) ($summary['grand_total'] ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $packages
     */
    public static function package(array $packages, mixed $tier): ?array
    {
        $key = self::normalizeTier($tier);
        $row = $packages[$key] ?? null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param  list<string>  $lines
     */
    public static function toBulletText(array $lines): string
    {
        $clean = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $clean[] = $line;
        }

        return implode("\n", $clean);
    }

    public static function htmlSection(string $title, string $body): string
    {
        $title = trim($title);
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        $html = '<div class="br-pdf-client-scope__block" style="page-break-inside:avoid;break-inside:avoid;margin:0 0 8pt;">';
        $html .= '<p style="margin:0 0 3pt;font-weight:700;color:#024768;line-height:1.25;">'.e($title).'</p>';
        $html .= '<ul style="margin:0 0 0 12pt;padding:0;line-height:1.35;">';
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $html .= '<li style="margin:0 0 2pt;">'.e($line).'</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }
}

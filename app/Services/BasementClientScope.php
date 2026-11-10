<?php

namespace App\Services;

/**
 * Client-facing basement scope copy for the single configured quotation.
 *
 * Default Included / Not included / Assumptions / Allowances text is derived
 * from wizard toggles and optional extras so unchecked services are never promised.
 */
class BasementClientScope
{
    public const COPY_KEYS = [
        'included' => 'client_scope_included',
        'not_included' => 'client_scope_not_included',
        'assumptions' => 'client_scope_assumptions',
        'allowances' => 'client_scope_allowances',
    ];

    public static function contractorFeeLabel(float $percent, ?float $basePreHst = null): string
    {
        $label = 'Contractor fee '.self::formatPercent($percent).'%';
        if ($basePreHst !== null && $basePreHst > 0) {
            $label .= ' on CA$'.number_format($basePreHst, 2, '.', ',').' pre-HST';
        }

        return $label;
    }

    public static function formatPercent(float $percent): string
    {
        $formatted = number_format(max(0.0, $percent), 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    public static function assumptionsFeeSentence(float $percent): string
    {
        $pct = self::formatPercent($percent);

        return 'A contractor fee of '.$pct.'% applies to materials and subcontracted services, including BCIN drawings and professional engineer signing when that signing is required. It does not apply to Zanini hourly labour or the municipal building-permit fee. The same fee also applies to written change orders.';
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
                    $included[] = 'Electrical coordination, Jonas arranges certified electricians for quoted electrical work.';
                } elseif ($key === 'plumbing') {
                    $included[] = 'Plumbing coordination, Jonas arranges certified plumbers for quoted plumbing work.';
                } elseif ($key === 'flooring') {
                    $type = strtolower(trim((string) ($inputs['flooring_type'] ?? 'lvp')));
                    if ($type !== 'none') {
                        $included[] = 'Flooring: '.(BasementQuotationCalculator::FLOORING_TYPES[$type] ?? 'LVP').'. Carpet is not offered.';
                    }
                } elseif ($key === 'bathroom' && empty($inputs['include_bathroom'])) {
                    $notIncluded[] = $label;
                    continue;
                } elseif ($key === 'demolition' && empty($inputs['include_demolition'])) {
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
            $included[] = 'City building permit as an optional extra-charge municipal fee (priced separately; pass-through, no contractor fee).';
        } else {
            $notIncluded[] = 'City building permit, optional extra-charge municipal fee, not included by default.';
        }
        if ($bcinOn) {
            $included[] = 'BCIN drawings as an optional extra-charge service (priced separately). A BCIN qualified designer is not the same credential as a professional engineer; engineer signing or stamping is included only when required.';
        } else {
            $notIncluded[] = 'BCIN drawings, optional extra-charge service, not included by default.';
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
        $trades = is_array($calc['summary']['trades'] ?? null) ? $calc['summary']['trades'] : [];
        if (BasementQuotationCalculator::sectionEnabled($inputs, 'electrical')) {
            $elecTax = round((float) ($trades['electrical']['tax_in'] ?? 0), 2);
            if ($elecTax <= 0) {
                $elec = AdditionalCostsService::normalizeRows($inputs['electrical_lines'] ?? [], 'Electrical work');
                $elecTax = AdditionalCostsService::taxInclusiveTotal($elec);
            }
            if ($elecTax > 0) {
                $allowances[] = 'Electrical work allowance CA$'.number_format($elecTax, 2, '.', ',').' (HST included in the trade line).';
            }
        }
        if (BasementQuotationCalculator::sectionEnabled($inputs, 'plumbing')) {
            $plumbTax = round((float) ($trades['plumbing']['tax_in'] ?? 0), 2);
            if ($plumbTax <= 0) {
                $plumb = AdditionalCostsService::normalizeRows($inputs['plumbing_lines'] ?? [], 'Plumbing work');
                $plumbTax = AdditionalCostsService::taxInclusiveTotal($plumb);
            }
            if ($plumbTax > 0) {
                $allowances[] = 'Plumbing work allowance CA$'.number_format($plumbTax, 2, '.', ',').' (HST included in the trade line).';
            }
        }
        $permitAmt = $permitOn ? round(max(0, (float) ($inputs['building_permit_amount'] ?? 0)), 2) : 0.0;
        $bcinAmt = $bcinOn ? round(max(0, (float) ($inputs['bcin_drawings_amount'] ?? 0)), 2) : 0.0;
        if ($permitAmt > 0) {
            $allowances[] = 'City building permit extra-charge CA$'.number_format($permitAmt, 2, '.', ',').' before HST (municipal fee; no contractor fee).';
        }
        if ($bcinAmt > 0) {
            $allowances[] = 'BCIN drawings extra-charge CA$'.number_format($bcinAmt, 2, '.', ',').' before HST (subcontracted professional service, including engineer signing when required).';
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
     * Allowances amounts always sync to live trade / permit lines for PDF output.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $calc
     * @return array{included: string, not_included: string, assumptions: string, allowances: string, generated: array{included: string, not_included: string, assumptions: string, allowances: string}, allowances_stale?: bool, allowances_ui?: string}
     */
    public static function resolveCopy(array $inputs, array $calc = []): array
    {
        $generated = self::generatedCopy($inputs, $calc);
        $out = ['generated' => $generated];
        foreach (self::COPY_KEYS as $short => $inputKey) {
            $stored = trim((string) ($inputs[$inputKey] ?? ''));
            if ($short === 'allowances') {
                if ($stored === '') {
                    $out['allowances'] = $generated['allowances'];
                    $out['allowances_ui'] = $generated['allowances'];
                } else {
                    $synced = self::syncAllowanceAmounts($stored, $generated['allowances']);
                    $out['allowances'] = $synced;
                    $out['allowances_ui'] = $stored;
                    $out['allowances_stale'] = self::allowancesAmountsStale($stored, $generated['allowances']);
                }
                continue;
            }
            $out[$short] = $stored !== '' ? $stored : $generated[$short];
        }

        return $out;
    }

    public static function allowancesAmountsStale(string $stored, string $generated): bool
    {
        $stored = self::normalizeAllowanceText($stored);
        $generated = self::normalizeAllowanceText($generated);
        if ($stored === '' || $generated === '') {
            return false;
        }

        return self::normalizeAllowanceText(self::syncAllowanceAmounts($stored, $generated)) !== $stored;
    }

    public static function syncAllowanceAmounts(string $stored, string $generated): string
    {
        $stored = self::normalizeAllowanceText($stored);
        $generated = self::normalizeAllowanceText($generated);
        if ($stored === '') {
            return $generated;
        }
        if ($generated === '') {
            return $stored;
        }
        $freshByPrefix = [];
        foreach (explode("\n", $generated) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $prefix = self::allowanceLinePrefix($line);
            if ($prefix !== '') {
                $freshByPrefix[$prefix] = $line;
            }
        }
        if ($freshByPrefix === []) {
            return $stored;
        }

        $out = [];
        foreach (explode("\n", $stored) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $prefix = self::allowanceLinePrefix($line);
            if ($prefix !== '' && isset($freshByPrefix[$prefix])) {
                $out[] = $freshByPrefix[$prefix];
            } else {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    public static function normalizeAllowanceText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private static function allowanceLinePrefix(string $line): string
    {
        $line = trim($line);
        if (str_starts_with($line, 'Electrical work allowance')) {
            return 'electrical';
        }
        if (str_starts_with($line, 'Plumbing work allowance')) {
            return 'plumbing';
        }
        if (str_starts_with($line, 'City building permit extra-charge')) {
            return 'permit';
        }
        if (str_starts_with($line, 'BCIN drawings extra-charge')) {
            return 'bcin';
        }

        return '';
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

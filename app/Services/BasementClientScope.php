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
        $isFlood = BasementQuotationCalculator::normalizePreset($inputs['scope_preset'] ?? null) === 'flood';
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
                } elseif ($isFlood && $key === 'insulation') {
                    $included[] = ! empty($inputs['insulate_walls'])
                        ? 'Exterior wall batt insulation to the flood cut line plus 6 mil vapour barrier patch and seal (acoustic sealant or tape) meeting Ontario Building Code (O.Reg. 332/12) requirements.'
                        : $label;
                } elseif ($isFlood && $key === 'drywall') {
                    $cutHeight = rtrim(rtrim(number_format(BasementQuotationCalculator::normalizeFloodCutHeightIn($inputs['flood_cut_height_in'] ?? 24), 1, '.', ''), '0'), '.');
                    $included[] = 'Drywall rebuild to '.$cutHeight.' in flood cut height, horizontal cut joint taping, finishing, and blending into existing upper walls.';
                } elseif ($isFlood && $key === 'doors_trim') {
                    $casingPhrase = BasementQuotationCalculator::normalizeFloodCasingMode($inputs['flood_casing_mode'] ?? 'legs') === 'full'
                        ? 'full door casing (legs and head)'
                        : 'door casing side legs replaced (reusing existing head)';
                    $included[] = 'Baseboards, shoe moulding (where selected), and '.$casingPhrase.'.';
                } elseif ($isFlood && $key === 'painting') {
                    $included[] = 'Full-height wall repainting (primer on new drywall board only), plus baseboards, trim, and door casings.';
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

        if (in_array('framing', $enabled, true) && (float) ($inputs['framing_2x6_lf'] ?? 0) > 0) {
            $included[] = '2×6 plumbing / structural wall framing';
        }
        if (in_array('insulation', $enabled, true) && ! empty($inputs['include_acoustic_insulation'])) {
            $included[] = 'Acoustic Safe\'n\'Sound batt insulation for ceiling / interior sound separation.';
        }
        if (in_array('drywall', $enabled, true) && ! empty($inputs['drywall_type_x_ceiling'])) {
            $included[] = '5/8" Type X fire-rated ceiling drywall assembly (Ontario Building Code).';
        }
        if (in_array('drywall', $enabled, true) && ! empty($inputs['drywall_resilient_channel'])) {
            $included[] = 'Resilient sound channel (RC-1) ceiling decoupling.';
        }
        if (in_array('flooring', $enabled, true) && ! empty($inputs['dricore_subfloor'])) {
            $included[] = 'Insulated dimpled basement subfloor panel system.';
        }
        if (in_array('doors_trim', $enabled, true) && ! empty($inputs['include_stairs'])) {
            $included[] = 'Basement stairs, handrail/guardrail, and finish trim installation.';
        }
        if (in_array('doors_trim', $enabled, true) && ! empty($inputs['include_millwork'])) {
            $included[] = 'Wet bar / laundry cabinetry & countertop installation.';
        }
        if (in_array('finishing', $enabled, true) && ! empty($inputs['include_egress_window']) && (int) ($inputs['egress_window_qty'] ?? 0) > 0) {
            $included[] = 'Basement egress window cut, enlargement, and window well installation.';
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
        if ($isFlood) {
            array_unshift($assumptions, 'Water extraction, drying, moisture readings and mould remediation are completed by others before we start.');
        }

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
     * @return array{included: string, not_included: string, assumptions: string, allowances: string, generated: array{included: string, not_included: string, assumptions: string, allowances: string}, allowances_stale?: bool, allowances_ui?: string, allowances_reset?: string}
     */
    public static function resolveCopy(array $inputs, array $calc = []): array
    {
        $generated = self::generatedCopy($inputs, $calc);
        $out = ['generated' => $generated];
        $tradeLabels = array_values(BasementQuotationCalculator::tradeSections());
        $sectionLists = self::refreshSectionLists(
            trim((string) ($inputs['client_scope_included'] ?? '')),
            trim((string) ($inputs['client_scope_not_included'] ?? '')),
            $generated,
            $tradeLabels
        );
        foreach (self::COPY_KEYS as $short => $inputKey) {
            if ($short === 'included' || $short === 'not_included') {
                $out[$short] = $sectionLists[$short];
                continue;
            }
            $stored = trim((string) ($inputs[$inputKey] ?? ''));
            if ($short === 'allowances') {
                if ($stored === '') {
                    $out['allowances'] = $generated['allowances'];
                    $out['allowances_ui'] = $generated['allowances'];
                    $out['allowances_reset'] = $generated['allowances'];
                } else {
                    $synced = self::syncAllowanceAmounts($stored, $generated['allowances']);
                    $out['allowances'] = $synced;
                    $out['allowances_ui'] = $stored;
                    $out['allowances_reset'] = self::mergeAllowancesToCurrent($stored, $generated['allowances']);
                    $out['allowances_stale'] = self::allowancesAmountsStale($stored, $generated['allowances']);
                }
                continue;
            }
            $out[$short] = $stored !== '' ? $stored : $generated[$short];
        }

        return $out;
    }

    /**
     * Move trade names between Included and Not included when the saved copy
     * is still the generated list (or still lists those trade names) and the
     * enabled sections have changed. Hand-written scope text is left as-is.
     *
     * @param  array{included: string, not_included: string, assumptions: string, allowances: string}  $generated
     * @param  list<string>  $tradeLabels
     * @return array{included: string, not_included: string}
     */
    public static function refreshSectionLists(string $included, string $notIncluded, array $generated, array $tradeLabels): array
    {
        $generatedIncluded = (string) ($generated['included'] ?? '');
        $generatedNotIncluded = (string) ($generated['not_included'] ?? '');
        if ($included === '') {
            $included = $generatedIncluded;
        } elseif (self::looksLikeFullGeneratedList($included, $generatedIncluded, $generated, $tradeLabels)) {
            $included = $generatedIncluded;
        }
        if ($notIncluded === '') {
            $notIncluded = $generatedNotIncluded;
        } elseif (self::looksLikeFullGeneratedList($notIncluded, $generatedNotIncluded, $generated, $tradeLabels)) {
            $notIncluded = $generatedNotIncluded;
        }

        $generatedIncludedSet = array_fill_keys(self::scopeLines($generatedIncluded), true);
        $generatedNotSet = array_fill_keys(self::scopeLines($generatedNotIncluded), true);
        $includedAcceptsMovedLabel = self::containsTradeLabel($included, $tradeLabels);
        $notIncludedAcceptsMovedLabel = self::containsTradeLabel($notIncluded, $tradeLabels);

        foreach ($tradeLabels as $label) {
            $off = isset($generatedNotSet[$label]);
            if ($off) {
                $wasListed = in_array($label, self::scopeLines($included), true);
                $included = self::removeScopeLine($included, $label);
                if ($wasListed && $notIncludedAcceptsMovedLabel) {
                    $notIncluded = self::ensureScopeLine($notIncluded, $label);
                }
                continue;
            }
            $wasExcluded = in_array($label, self::scopeLines($notIncluded), true);
            $notIncluded = self::removeScopeLine($notIncluded, $label);
            if ($wasExcluded && $includedAcceptsMovedLabel && isset($generatedIncludedSet[$label])) {
                $included = self::ensureScopeLine($included, $label);
            }
        }

        return [
            'included' => $included,
            'not_included' => $notIncluded,
        ];
    }

    /**
     * @param  array{included?: string, not_included?: string}  $generated
     * @param  list<string>  $tradeLabels
     */
    private static function looksLikeFullGeneratedList(string $stored, string $generatedField, array $generated, array $tradeLabels): bool
    {
        $storedLines = self::scopeLines($stored);
        if ($storedLines === []) {
            return false;
        }
        $managed = self::managedScopeLines($generated, $tradeLabels);
        $labelSet = array_fill_keys($tradeLabels, true);
        $generatedLines = self::scopeLines($generatedField);
        $generatedSet = array_fill_keys($generatedLines, true);
        foreach ($storedLines as $line) {
            if (! isset($managed[$line])) {
                return false;
            }
            if (! isset($generatedSet[$line]) && ! isset($labelSet[$line])) {
                return false;
            }
        }
        $storedSet = array_fill_keys($storedLines, true);
        foreach ($generatedLines as $line) {
            if (! isset($storedSet[$line]) && ! isset($labelSet[$line])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{included?: string, not_included?: string}  $generated
     * @param  list<string>  $tradeLabels
     * @return array<string, true>
     */
    private static function managedScopeLines(array $generated, array $tradeLabels): array
    {
        $managed = [];
        foreach ($tradeLabels as $label) {
            $managed[$label] = true;
        }
        foreach (['included', 'not_included'] as $key) {
            foreach (self::scopeLines((string) ($generated[$key] ?? '')) as $line) {
                $managed[$line] = true;
            }
        }

        return $managed;
    }

    /**
     * @param  list<string>  $tradeLabels
     */
    private static function containsTradeLabel(string $text, array $tradeLabels): bool
    {
        $set = array_fill_keys(self::scopeLines($text), true);
        foreach ($tradeLabels as $label) {
            if (isset($set[$label])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function scopeLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function removeScopeLine(string $text, string $line): string
    {
        $keep = [];
        foreach (self::scopeLines($text) as $existing) {
            if ($existing !== $line) {
                $keep[] = $existing;
            }
        }

        return implode("\n", $keep);
    }

    private static function ensureScopeLine(string $text, string $line): string
    {
        $lines = self::scopeLines($text);
        if (in_array($line, $lines, true)) {
            return implode("\n", $lines);
        }
        $lines[] = $line;

        return implode("\n", $lines);
    }

    public static function allowancesAmountsStale(string $stored, string $generated): bool
    {
        $stored = self::normalizeAllowanceText($stored);
        $generated = self::normalizeAllowanceText($generated);
        if ($stored === '' || $generated === '') {
            return false;
        }

        $synced = self::normalizeAllowanceText(self::syncAllowanceAmounts($stored, $generated));
        if ($synced !== $stored) {
            return true;
        }

        return self::generatedAllowancePrefixesMissingFromStored($stored, $generated);
    }

    public static function syncAllowanceAmounts(string $stored, string $generated): string
    {
        return self::buildAllowanceMerge($stored, $generated, false);
    }

    /**
     * Full merge for admin reset: update amounts, add missing generated lines, drop removed trades, keep custom lines.
     */
    public static function mergeAllowancesToCurrent(string $stored, string $generated): string
    {
        return self::buildAllowanceMerge($stored, $generated, true);
    }

    private static function buildAllowanceMerge(string $stored, string $generated, bool $appendAllMissingGenerated): string
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
        $generatedOrder = [];
        foreach (explode("\n", $generated) as $line) {
            $line = trim($line);
            if ($line === '' || self::isPlaceholderAllowanceLine($line)) {
                continue;
            }
            $prefix = self::allowanceLinePrefix($line);
            if ($prefix !== '') {
                $freshByPrefix[$prefix] = $line;
                $generatedOrder[] = $prefix;
            }
        }

        $storedHadPlaceholder = false;
        $out = [];
        $presentPrefixes = [];
        foreach (explode("\n", $stored) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (self::isPlaceholderAllowanceLine($line)) {
                $storedHadPlaceholder = true;
                if ($freshByPrefix === []) {
                    $out[] = $line;
                }
                continue;
            }
            $prefix = self::allowanceLinePrefix($line);
            if ($prefix !== '') {
                if (isset($freshByPrefix[$prefix])) {
                    $out[] = $freshByPrefix[$prefix];
                    $presentPrefixes[$prefix] = true;
                }
                continue;
            }
            $out[] = $line;
        }

        if ($appendAllMissingGenerated || ($storedHadPlaceholder && $freshByPrefix !== [])) {
            foreach ($generatedOrder as $prefix) {
                if (! isset($presentPrefixes[$prefix]) && isset($freshByPrefix[$prefix])) {
                    $out[] = $freshByPrefix[$prefix];
                    $presentPrefixes[$prefix] = true;
                }
            }
        }

        if ($out === []) {
            return $generated;
        }

        return implode("\n", $out);
    }

    public static function isPlaceholderAllowanceLine(string $line): bool
    {
        return trim($line) === 'No separate cash allowances beyond the priced lines on this quotation.';
    }

    private static function generatedAllowancePrefixesMissingFromStored(string $stored, string $generated): bool
    {
        $storedPrefixes = [];
        foreach (explode("\n", $stored) as $line) {
            $prefix = self::allowanceLinePrefix(trim($line));
            if ($prefix !== '') {
                $storedPrefixes[$prefix] = true;
            }
        }

        foreach (explode("\n", $generated) as $line) {
            $line = trim($line);
            if ($line === '' || self::isPlaceholderAllowanceLine($line)) {
                continue;
            }
            $prefix = self::allowanceLinePrefix($line);
            if ($prefix !== '' && empty($storedPrefixes[$prefix])) {
                return true;
            }
        }

        return false;
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

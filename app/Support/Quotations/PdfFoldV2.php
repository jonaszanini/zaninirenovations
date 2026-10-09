<?php

namespace App\Support\Quotations;

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use App\Services\AdditionalCostsService;
use App\Services\DebrisRemovalExpenseService;

/**
 * Frozen client-PDF fold / HST from main #60 (f6ed7f91).
 * v3 code must never run on a quote whose pdf_fold_version === 2.
 */
final class PdfFoldV2
{
    public function __construct(private ClientProjectQuotation $quote)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pdfLineItems(): array
    {
        $quote = $this->quote;
        if ($quote->source_type === ClientProjectQuotation::SOURCE_STANDARD && $quote->source_id) {
            $sq = StandardQuotation::query()->find($quote->source_id);
            if ($sq) {
                if ($sq->usesDetailedPdf()) {
                    $detailed = $sq->detailedPdfLineItems();
                    $items = $detailed !== [] ? $detailed : $quote->cleanItems();
                } elseif ($sq->usesCombinedPdf()) {
                    $combined = $sq->combinedPdfLineItems();
                    $items = $combined !== [] ? $combined : $quote->cleanItems();
                } elseif ($sq->usesSummaryPdf()) {
                    $summary = $sq->summaryPdfLineItems();
                    $items = $summary !== [] ? $summary : $quote->cleanItems();
                } else {
                    $items = $quote->applyStandardSummaryLineWording($quote->cleanItems(), $sq);
                }
            } else {
                $items = $quote->cleanItems();
            }
        } else {
            $items = $quote->cleanItems();
            if ($quote->source_type === ClientProjectQuotation::SOURCE_PAVING) {
                $items = $this->normalizePavingPdfPreTaxLines($items);
            }
        }

        return $this->normalizeDebrisRemovalForClientFacing(
            $this->normalizeInternalExpensesForClientFacing($items)
        );
    }

    public function pdfSubtotal(): float
    {
        $sum = 0.0;
        foreach ($this->pdfLineItems() as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $sum += (float) ($row['amount'] ?? 0);
        }

        return round($sum, 2);
    }

    public function pdfHst(): float
    {
        $sum = 0.0;
        foreach ($this->pdfLineItems() as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $sum += ClientProjectQuotation::lineHstAmount($row);
        }

        return round($sum, 2);
    }

    public function pdfGrandTotal(): float
    {
        return round($this->pdfSubtotal() + $this->pdfHst(), 2);
    }

    public function hst(): float
    {
        if (! $this->quote->hasSubtotalOverrideActive()) {
            return $this->pdfHst();
        }

        $items = $this->pdfLineItems();
        $lineSub = $this->pdfSubtotal();
        $override = round((float) $this->quote->subtotal_override, 2);

        if ($lineSub > 0.00001) {
            $ratio = $override / $lineSub;
            $sum = 0.0;
            foreach ($items as $row) {
                if (empty($row['apply_hst'])) {
                    continue;
                }
                $sum += ClientProjectQuotation::lineHstAmount($row) * $ratio;
            }

            return round($sum, 2);
        }

        $anyTaxable = false;
        foreach ($items as $row) {
            if (! empty($row['apply_hst'])) {
                $anyTaxable = true;
                break;
            }
        }

        if ($anyTaxable) {
            return round($override * ClientProjectQuotation::HST_RATE, 2);
        }

        return 0.0;
    }

    public function grandTotal(): float
    {
        $subtotal = $this->quote->hasSubtotalOverrideActive()
            ? round((float) $this->quote->subtotal_override, 2)
            : $this->pdfSubtotal();

        return round($subtotal + $this->hst(), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeDebrisRemovalForClientFacing(array $items): array
    {
        $debrisGross = 0.0;
        $filtered = [];

        foreach ($items as $row) {
            $desc = (string) ($row['description'] ?? '');
            if (DebrisRemovalExpenseService::isLineDescription($desc)) {
                $debrisGross += (float) ($row['amount'] ?? 0);

                continue;
            }
            if ($this->quote->source_type === ClientProjectQuotation::SOURCE_TILE && $desc === 'Client-selected tile & materials') {
                $row['description'] = 'Materials';
            }
            $filtered[] = $row;
        }

        if ($debrisGross > 0) {
            $filtered = $this->mergeCommuteIntoLabourLines($filtered, $debrisGross);
        }

        return array_values($filtered);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeInternalExpensesForClientFacing(array $items): array
    {
        $labourHiddenGross = 0.0;
        $materialsHiddenGross = 0.0;
        $filtered = [];

        foreach ($items as $row) {
            $desc = (string) ($row['description'] ?? '');
            if (self::isMaterialsHiddenInternalExpenseLineDescription($desc)) {
                $materialsHiddenGross += (float) ($row['amount'] ?? 0);

                continue;
            }
            if (self::isLabourHiddenInternalExpenseLineDescription($desc)) {
                $labourHiddenGross += (float) ($row['amount'] ?? 0);

                continue;
            }
            $filtered[] = $row;
        }

        if ($filtered === [] && $labourHiddenGross <= 0 && $materialsHiddenGross <= 0) {
            return $items;
        }

        if ($materialsHiddenGross > 0) {
            $filtered = $this->mergeHiddenTaxInExpenseIntoTarget($filtered, $materialsHiddenGross, 'materials');
        }
        if ($labourHiddenGross > 0) {
            $filtered = $this->mergeCommuteIntoLabourLines($filtered, $labourHiddenGross);
        }

        return array_values($filtered);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function mergeCommuteIntoLabourLines(array $items, float $hiddenGross): array
    {
        if ($hiddenGross <= 0) {
            return $items;
        }

        $indexes = $this->commuteLabourLineIndexes($items);
        if ($indexes === []) {
            $taxNewLabour = $this->itemsHaveTaxedAmount($items);
            $items[] = [
                'description' => 'Labour',
                'amount' => 0.0,
                'apply_hst' => $taxNewLabour,
                'hst_included' => false,
            ];
            $indexes = $this->commuteLabourLineIndexes($items);
            if ($indexes === []) {
                return $items;
            }
        }

        $taxed = [];
        $untaxed = [];
        foreach ($indexes as $idx) {
            if (! empty($items[$idx]['apply_hst'])) {
                $taxed[] = $idx;
            } else {
                $untaxed[] = $idx;
            }
        }

        $leftoverHst = 0.0;
        if ($taxed !== []) {
            $total = ClientProjectQuotation::preTaxFromHstIncluded($hiddenGross);
            $leftoverHst = round($hiddenGross - $total, 2);
            $targets = $taxed;
        } elseif ($this->itemsHaveTaxedAmount($items)) {
            $hiddenPreTax = ClientProjectQuotation::preTaxFromHstIncluded($hiddenGross);
            $ownLeftover = round($hiddenGross - $hiddenPreTax, 2);
            if ($hiddenPreTax <= 0 && $ownLeftover <= 0) {
                return $items;
            }
            $items[] = [
                'description' => 'Labour',
                'amount' => $hiddenPreTax,
                'apply_hst' => true,
                'hst_amount' => $ownLeftover,
            ];

            return array_values($items);
        } else {
            $total = round($hiddenGross, 2);
            $targets = $untaxed;
        }

        if ($total <= 0 || $targets === []) {
            return $items;
        }

        $shares = ClientProjectQuotation::splitAmountEvenly($total, count($targets));
        $hstShares = ClientProjectQuotation::splitAmountEvenly($leftoverHst, count($targets));
        foreach ($targets as $i => $idx) {
            $row = &$items[$idx];
            $hstBefore = ClientProjectQuotation::lineHstAmount($row);
            $this->addAmountToPdfLine($row, $shares[$i]);
            if (! empty($row['apply_hst'])) {
                $row['hst_amount'] = round($hstBefore + $hstShares[$i], 2);
            }
            unset($row);
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<int>
     */
    private function commuteLabourLineIndexes(array $items): array
    {
        $indexes = [];
        foreach ($items as $i => $row) {
            if (self::isPdfLabourLine($row)) {
                $indexes[] = $i;
            }
        }

        if ($indexes !== []) {
            return $indexes;
        }

        foreach ($items as $i => $row) {
            if (self::isPdfCombinedSummaryLine($row)) {
                $indexes[] = $i;
            }
        }

        return $indexes;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function itemsHaveTaxedAmount(array $items): bool
    {
        foreach ($items as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            if (! empty($row['apply_hst'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isPdfLabourLine(array $row): bool
    {
        if (! empty($row['is_heading']) || self::isPdfExcludedFoldLine($row)) {
            return false;
        }

        $desc = trim((string) ($row['description'] ?? ''));
        if ($desc === '' || stripos($desc, 'HST exempt') !== false) {
            return false;
        }
        if (DebrisRemovalExpenseService::isLineDescription($desc)) {
            return false;
        }

        return (bool) preg_match('/\blabou?r\b/i', $desc);
    }

    private static function normalizePdfLineLabel(string $desc): string
    {
        $desc = strtr($desc, ['—' => '-', '–' => '-', '−' => '-']);
        $desc = (string) preg_replace('/\s*-\s*/u', ' - ', $desc);
        $desc = (string) preg_replace('/\s+/u', ' ', $desc);

        return strtolower(trim($desc));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isPdfCombinedSummaryLine(array $row): bool
    {
        if (! empty($row['is_heading']) || self::isPdfExcludedFoldLine($row) || self::isPdfMaterialOnlyLine($row)) {
            return false;
        }

        $desc = (string) ($row['description'] ?? '');
        if (stripos($desc, 'HST exempt') !== false) {
            return false;
        }
        if (DebrisRemovalExpenseService::isLineDescription($desc)) {
            return false;
        }
        if (empty($row['apply_hst']) && empty($row['hst_included'])) {
            return false;
        }

        if (preg_match('/\b(?:labour|labor)\s+and\s+material|\bmaterial\s+and\s+(?:labour|labor)\b/i', $desc)) {
            return true;
        }

        return (bool) preg_match('/\b(?:fence|deck|paving|interlock|unilock|patio)\b/i', $desc);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isPdfMaterialOnlyLine(array $row): bool
    {
        $normalized = self::normalizePdfLineLabel((string) ($row['description'] ?? ''));
        if ($normalized === 'material' || $normalized === 'materials') {
            return true;
        }
        if (str_ends_with($normalized, ' - material') || str_ends_with($normalized, ' - materials')) {
            return true;
        }
        if (preg_match('/\blabou?r\b/i', $normalized)) {
            return false;
        }

        return (bool) preg_match('/\bmaterials?\b/i', $normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isPdfExcludedFoldLine(array $row): bool
    {
        $desc = (string) ($row['description'] ?? '');

        return (bool) preg_match(
            '/\b(permits?|electrical|plumbing|demolition|demo|supply|custom|labourer)\b/i',
            $desc
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function addAmountToPdfLine(array &$row, float $add): void
    {
        if ($add == 0.0) {
            return;
        }

        $hstBefore = ! empty($row['apply_hst']) ? ClientProjectQuotation::lineHstAmount($row) : 0.0;
        $row['amount'] = round((float) ($row['amount'] ?? 0) + $add, 2);

        if (! empty($row['apply_hst'])) {
            $taxableBase = array_key_exists('hst_taxable_amount', $row)
                ? (float) $row['hst_taxable_amount']
                : (float) $row['amount'] - $add;
            $row['hst_taxable_amount'] = round($taxableBase + $add, 2);
            $row['hst_amount'] = round($hstBefore + round($add * ClientProjectQuotation::HST_RATE, 2), 2);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function mergeHiddenTaxInExpenseIntoTarget(array $items, float $hiddenGross, string $bucket): array
    {
        if ($hiddenGross <= 0) {
            return $items;
        }

        $targetIdx = $bucket === 'materials'
            ? $this->materialsMergeTargetIndex($items)
            : $this->commuteMergeTargetIndex($items);

        if ($targetIdx === null) {
            $items[] = [
                'description' => $bucket === 'materials' ? 'Material' : 'Labour',
                'amount' => 0.0,
                'apply_hst' => true,
            ];
            $targetIdx = count($items) - 1;
        }

        $row = &$items[$targetIdx];

        if (empty($row['apply_hst'])) {
            unset($row);
            $hiddenPreTax = ClientProjectQuotation::preTaxFromHstIncluded($hiddenGross);
            $leftoverHst = round($hiddenGross - $hiddenPreTax, 2);
            if ($hiddenPreTax <= 0 && $leftoverHst <= 0) {
                return $items;
            }
            $items[] = [
                'description' => $bucket === 'materials' ? 'Material' : 'Labour',
                'amount' => $hiddenPreTax,
                'apply_hst' => true,
                'hst_amount' => $leftoverHst,
            ];

            return array_values($items);
        }

        $hiddenPreTax = ClientProjectQuotation::preTaxFromHstIncluded($hiddenGross);
        $leftoverHst = round($hiddenGross - $hiddenPreTax, 2);
        if ($hiddenPreTax <= 0 && $leftoverHst <= 0) {
            unset($row);

            return $items;
        }

        $hstBefore = ! empty($row['apply_hst']) ? ClientProjectQuotation::lineHstAmount($row) : 0.0;
        $row['amount'] = round((float) ($row['amount'] ?? 0) + $hiddenPreTax, 2);

        if (! empty($row['apply_hst'])) {
            $taxableBase = array_key_exists('hst_taxable_amount', $row)
                ? (float) $row['hst_taxable_amount']
                : (float) ($row['amount'] ?? 0) - $hiddenPreTax;
            $row['hst_taxable_amount'] = round($taxableBase + $hiddenPreTax, 2);
            $row['hst_amount'] = round($hstBefore + $leftoverHst, 2);
        }

        unset($row);

        return $items;
    }

    private static function isLabourHiddenInternalExpenseLineDescription(string $description): bool
    {
        return str_starts_with($description, 'Commute expense')
            || str_starts_with($description, AdditionalCostsService::LINE_PREFIX_LABOUR);
    }

    private static function isMaterialsHiddenInternalExpenseLineDescription(string $description): bool
    {
        return str_starts_with($description, AdditionalCostsService::LINE_PREFIX);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function materialsMergeTargetIndex(array $items): ?int
    {
        $candidates = [];

        foreach ($items as $i => $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $desc = (string) ($row['description'] ?? '');
            if (stripos($desc, 'HST exempt') !== false) {
                continue;
            }
            if (DebrisRemovalExpenseService::isLineDescription($desc)) {
                continue;
            }
            if (self::isPdfExcludedFoldLine($row)) {
                continue;
            }

            $normalized = self::normalizePdfLineLabel($desc);
            $score = 0;
            if (str_ends_with($normalized, ' - material') || str_ends_with($normalized, ' - materials')) {
                $score = 100;
            } elseif (preg_match('/\bmaterials?\b/i', $normalized) && ! preg_match('/\blabou?r\b/i', $normalized)) {
                $score = 90;
            } elseif (preg_match('/\b(?:labour|labor)\s+and\s+material|\bmaterial\s+and\s+(?:labour|labor)\b/i', $normalized)) {
                $score = 70;
            } else {
                continue;
            }

            $candidates[] = ['index' => $i, 'score' => $score];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['index'];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function commuteMergeTargetIndex(array $items): ?int
    {
        $candidates = [];

        foreach ($items as $i => $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            if (empty($row['apply_hst'])) {
                continue;
            }

            $desc = (string) ($row['description'] ?? '');
            if (stripos($desc, 'HST exempt') !== false) {
                continue;
            }
            if (DebrisRemovalExpenseService::isLineDescription($desc)) {
                continue;
            }
            if (self::isPdfExcludedFoldLine($row)) {
                continue;
            }

            $score = 0;
            if (str_ends_with($desc, ' — Labour') || str_ends_with($desc, ' — Labor')) {
                $score = 100;
            } elseif (stripos($desc, 'HST applicable') !== false) {
                $score = 90;
            } elseif (preg_match('/\blabou?r\b/i', $desc) && stripos($desc, 'material') === false) {
                $score = 80;
            } elseif (stripos($desc, 'Labour and material') !== false || stripos($desc, 'Material and labour') !== false) {
                $score = 70;
            } else {
                $score = 10;
            }

            $candidates[] = ['index' => $i, 'score' => $score];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['index'];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizePavingPdfPreTaxLines(array $items): array
    {
        $normalized = [];

        foreach ($items as $row) {
            $description = (string) ($row['description'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);

            $isHiddenExtra = DebrisRemovalExpenseService::isLineDescription($description)
                || self::isLabourHiddenInternalExpenseLineDescription($description)
                || self::isMaterialsHiddenInternalExpenseLineDescription($description);
            if ($isHiddenExtra) {
                $normalized[] = $row;

                continue;
            }

            if (! empty($row['hst_included'])) {
                $preTax = ClientProjectQuotation::preTaxFromHstIncluded($amount);
                $normalized[] = [
                    'description' => $description,
                    'amount' => $preTax,
                    'apply_hst' => true,
                    'hst_amount' => round($amount - $preTax, 2),
                ];

                continue;
            }

            if (! empty($row['apply_hst'])) {
                $taxableBase = array_key_exists('hst_taxable_amount', $row)
                    ? (float) $row['hst_taxable_amount']
                    : $amount;
                $includedPortion = round($amount - $taxableBase, 2);
                $preTaxAmount = $taxableBase;

                if ($includedPortion > 0) {
                    $preTaxAmount = round($taxableBase + ClientProjectQuotation::preTaxFromHstIncluded($includedPortion), 2);
                }

                $line = [
                    'description' => $description,
                    'amount' => round($preTaxAmount, 2),
                    'apply_hst' => true,
                ];
                if (array_key_exists('hst_taxable_amount', $row)) {
                    $line['hst_taxable_amount'] = $taxableBase;
                }
                $editorLineTotal = round($amount + ClientProjectQuotation::lineHstAmount([
                    'apply_hst' => true,
                    'amount' => $amount,
                    'hst_taxable_amount' => $taxableBase,
                ]), 2);
                $line['hst_amount'] = round($editorLineTotal - $line['amount'], 2);
                $normalized[] = $line;

                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}

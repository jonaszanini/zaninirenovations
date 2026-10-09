<?php

namespace App\Support\Quotations;

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use App\Services\AdditionalCostsService;
use App\Services\DebrisRemovalExpenseService;

/**
 * Byte-for-byte client-PDF fold / HST from main (bc380f47).
 * v2 code must never run on a quote whose pdf_fold_version !== 2.
 */
final class LegacyPdfFoldV1
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

        $items = $this->stripHstAmount($items);

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
            $sum += self::lineHstAmount($row);
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
                $sum += (float) ($row['amount'] ?? 0) * $ratio * ClientProjectQuotation::HST_RATE;
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
        return round($this->quote->subtotal() + $this->hst(), 2);
    }

    /**
     * Main's line HST: never honours hst_amount.
     *
     * @param  array<string, mixed>  $row
     */
    public static function lineHstAmount(array $row): float
    {
        if (empty($row['apply_hst'])) {
            return 0.0;
        }

        $base = array_key_exists('hst_taxable_amount', $row)
            ? (float) $row['hst_taxable_amount']
            : (float) ($row['amount'] ?? 0);

        return round($base * ClientProjectQuotation::HST_RATE, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function stripHstAmount(array $items): array
    {
        foreach ($items as &$row) {
            unset($row['hst_amount']);
        }
        unset($row);

        return $items;
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
            $items[] = [
                'description' => 'Labour',
                'amount' => 0.0,
                'apply_hst' => false,
                'hst_included' => true,
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

        if ($taxed !== []) {
            $total = ClientProjectQuotation::preTaxFromHstIncluded($hiddenGross);
            $targets = $taxed;
        } else {
            $total = round($hiddenGross, 2);
            $targets = $untaxed;
        }

        if ($total <= 0 || $targets === []) {
            return $items;
        }

        $shares = ClientProjectQuotation::splitAmountEvenly($total, count($targets));
        foreach ($targets as $i => $idx) {
            $row = &$items[$idx];
            $this->addAmountToPdfLine($row, $shares[$i]);
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

        $one = $this->commuteMergeTargetIndex($items);

        return $one !== null ? [$one] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function isPdfLabourLine(array $row): bool
    {
        if (! empty($row['is_heading'])) {
            return false;
        }

        $desc = (string) ($row['description'] ?? '');
        if (stripos($desc, 'HST exempt') !== false) {
            return false;
        }
        if (DebrisRemovalExpenseService::isLineDescription($desc)) {
            return false;
        }

        return $desc === 'Labour'
            || $desc === 'Labor'
            || str_ends_with($desc, ' — Labour')
            || str_ends_with($desc, ' — Labor')
            || (bool) preg_match('/\blabou?r\b/i', $desc);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function addAmountToPdfLine(array &$row, float $add): void
    {
        if ($add == 0.0) {
            return;
        }

        $row['amount'] = round((float) ($row['amount'] ?? 0) + $add, 2);

        if (! empty($row['apply_hst'])) {
            $taxableBase = array_key_exists('hst_taxable_amount', $row)
                ? (float) $row['hst_taxable_amount']
                : (float) $row['amount'] - $add;
            $row['hst_taxable_amount'] = round($taxableBase + $add, 2);
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
            return $items;
        }

        $row = &$items[$targetIdx];

        if (! empty($row['hst_included']) && empty($row['apply_hst'])) {
            $row['amount'] = round((float) ($row['amount'] ?? 0) + $hiddenGross, 2);
            unset($row);

            return $items;
        }

        $hiddenPreTax = ClientProjectQuotation::preTaxFromHstIncluded($hiddenGross);
        if ($hiddenPreTax <= 0) {
            unset($row);

            return $items;
        }

        $row['amount'] = round((float) ($row['amount'] ?? 0) + $hiddenPreTax, 2);

        if (! empty($row['apply_hst'])) {
            $taxableBase = array_key_exists('hst_taxable_amount', $row)
                ? (float) $row['hst_taxable_amount']
                : (float) ($row['amount'] ?? 0) - $hiddenPreTax;
            $row['hst_taxable_amount'] = round($taxableBase + $hiddenPreTax, 2);
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

            $score = 0;
            if (str_ends_with($desc, ' — Material') || str_ends_with($desc, ' — materials')) {
                $score = 100;
            } elseif (preg_match('/\bMaterials?\b/i', $desc) && stripos($desc, 'labour') === false) {
                $score = 90;
            } elseif (stripos($desc, 'Labour and material') !== false || stripos($desc, 'Material and labour') !== false) {
                $score = 70;
            } elseif (! empty($row['apply_hst']) || ! empty($row['hst_included'])) {
                $score = 10;
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

            if (DebrisRemovalExpenseService::isLineDescription($description)) {
                $normalized[] = $row;

                continue;
            }

            if (! empty($row['hst_included'])) {
                $normalized[] = [
                    'description' => $description,
                    'amount' => ClientProjectQuotation::preTaxFromHstIncluded($amount),
                    'apply_hst' => true,
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

                $normalized[] = [
                    'description' => $description,
                    'amount' => round($preTaxAmount, 2),
                    'apply_hst' => true,
                ];

                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}

<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\AdditionalCostsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

trait HandlesQuotationAdditionalCosts
{
    /**
     * @return list<array{description: string, amount: float, hst_included: bool}>|array{}
     */
    protected function additionalCostsPersistenceFromRequest(
        Request $request,
        ?object $existingQuotation = null,
        ?string $table = null
    ): array {
        $table = $table ?? ($existingQuotation instanceof Model ? $existingQuotation->getTable() : null);
        if ($table === null || ! Schema::hasColumn($table, 'additional_costs')) {
            return [];
        }

        return app(AdditionalCostsService::class)->persistenceFromRequest($request);
    }

    /**
     * @param  array<string, mixed>  $persistence
     * @param  list<array{description: string, amount: float, hst_included: bool}>|array{}  $rows
     * @return array<string, mixed>
     */
    protected function mergeAdditionalCostsIntoPersistence(array $persistence, array $rows, string $totalKey): array
    {
        $normalized = AdditionalCostsService::normalizeRows($rows);
        $taxIn = AdditionalCostsService::taxInclusiveTotal($normalized);
        $base = (float) ($persistence[$totalKey] ?? 0);

        return array_merge($persistence, [
            'additional_costs' => $normalized === [] ? null : $normalized,
            $totalKey => round($base + $taxIn, 2),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function appendAdditionalCostsProjectLines(array &$items, object $quotation): void
    {
        $rows = is_array($quotation->additional_costs ?? null) ? $quotation->additional_costs : [];
        foreach (AdditionalCostsService::projectLineItems($rows) as $line) {
            $items[] = $line;
        }
    }

    /**
     * Fold additional costs into a tax-included materials remainder line (paving-style).
     * Prefer this when the final total already embeds the tax-in amount in the material bucket.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function foldAdditionalCostsIntoTaxIncludedMaterialLine(array &$items, float $taxInclusiveAmount): void
    {
        if ($taxInclusiveAmount <= 0) {
            return;
        }

        foreach ($items as &$row) {
            $desc = (string) ($row['description'] ?? '');
            if (str_ends_with($desc, ' — Material') || stripos($desc, 'material') !== false) {
                if (! empty($row['hst_included']) || empty($row['apply_hst'])) {
                    $row['amount'] = round((float) ($row['amount'] ?? 0) + $taxInclusiveAmount, 2);
                    unset($row);

                    return;
                }
            }
        }
        unset($row);
    }
}

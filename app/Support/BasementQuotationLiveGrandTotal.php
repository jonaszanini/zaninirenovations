<?php

namespace App\Support;

use App\Models\BasementQuotation;
use App\Services\AdditionalCostsService;

final class BasementQuotationLiveGrandTotal
{
    /**
     * Toolbar / first-paint grand total: calculator grand plus commute, debris, and additional costs.
     *
     * @param  array<string, mixed>|null  $previewSummary
     */
    public static function fromQuotation(?BasementQuotation $quotation, ?array $previewSummary = null): float
    {
        $base = (float) (($previewSummary ?? [])['grand_total'] ?? 0);
        if ($quotation === null) {
            return round($base, 2);
        }

        $commute = $quotation->include_commute ? (float) $quotation->commute_amount : 0.0;
        $debris = $quotation->include_debris_removal ? (float) $quotation->debris_removal_amount : 0.0;
        $rows = is_array($quotation->additional_costs) ? $quotation->additional_costs : [];
        $additional = AdditionalCostsService::taxInclusiveTotal($rows);

        return round($base + $commute + $debris + $additional, 2);
    }

    /**
     * @return array{commute: float, debris: float, additionalTaxIn: float}
     */
    public static function expensePayload(?BasementQuotation $quotation): array
    {
        if ($quotation === null) {
            return ['commute' => 0.0, 'debris' => 0.0, 'additionalTaxIn' => 0.0];
        }

        return [
            'commute' => $quotation->include_commute ? (float) $quotation->commute_amount : 0.0,
            'debris' => $quotation->include_debris_removal ? (float) $quotation->debris_removal_amount : 0.0,
            'additionalTaxIn' => AdditionalCostsService::taxInclusiveTotal(
                is_array($quotation->additional_costs) ? $quotation->additional_costs : []
            ),
        ];
    }
}

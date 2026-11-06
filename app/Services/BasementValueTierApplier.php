<?php

namespace App\Services;

use App\Http\Controllers\Admin\BasementQuotationController;
use App\Models\BasementQuotation;
use App\Models\ClientProjectQuotation;

/**
 * Apply a Base / Standard / Upgrade choice onto a basement quotation
 * at first acceptance only. Already-accepted contracts are left alone.
 */
class BasementValueTierApplier
{
    public function apply(ClientProjectQuotation $quotation, ?string $tier): void
    {
        if ($quotation->isAccepted()) {
            return;
        }
        if ($quotation->source_type !== ClientProjectQuotation::SOURCE_BASEMENT || ! $quotation->source_id) {
            return;
        }

        $normalized = BasementClientScope::normalizeTier($tier);
        $quotation->accepted_value_tier = $normalized;

        $basement = BasementQuotation::query()->find($quotation->source_id);
        if ($basement === null) {
            return;
        }

        $inputs = is_array($basement->inputs) ? $basement->inputs : [];
        $currentType = strtolower(trim((string) ($inputs['flooring_type'] ?? 'lvp')));
        $packageType = BasementClientScope::flooringTypeForTier($normalized, $currentType);
        $inputs['selected_value_tier'] = $normalized;

        if ($packageType !== $currentType) {
            $inputs['flooring_type'] = $packageType;
            $inputs['flooring_waste_percent'] = BasementQuotationCalculator::flooringWasteDefault($packageType);
            $calc = (new BasementQuotationCalculator)->calculate($inputs);
            $basement->inputs = $inputs;
            $basement->calculation = $calc;
            $basement->final_quote_total = round((float) ($calc['summary']['final_quote_total'] ?? 0), 2);
            $basement->save();

            $project = $quotation->project;
            if ($project) {
                app(BasementQuotationController::class)->syncLinkedQuote($project, $basement->fresh());
                $quotation->refresh();
            }
        } else {
            $basement->inputs = $inputs;
            $basement->save();
        }

        $quotation->accepted_value_tier = $normalized;
    }
}

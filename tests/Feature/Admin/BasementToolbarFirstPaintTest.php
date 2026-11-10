<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Services\AdditionalCostsService;
use App\Services\BasementQuotationCalculator;
use App\Support\BasementQuotationLiveGrandTotal;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementToolbarFirstPaintTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_edit_toolbar_grand_matches_live_total_with_commute_debris_and_additional_costs(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 600,
            'flooring_type' => 'lvp',
            'flooring_sf' => 600,
            'contractor_fee_percent' => 10,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
            'electrical_lines' => [
                ['description' => 'Panel work', 'amount' => 1000, 'hst_included' => false],
            ],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'First paint toolbar',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['grand_total'],
            'include_commute' => true,
            'commute_amount' => 113.00,
            'commute_trip_percent' => 100,
            'commute_estimated_trips' => 1,
            'commute_round_trip_cost' => 100,
            'include_debris_removal' => true,
            'debris_removal_amount' => 226.00,
            'additional_costs' => [
                ['description' => 'Bin rental', 'amount' => 200, 'hst_included' => false, 'category' => 'materials'],
            ],
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $expected = BasementQuotationLiveGrandTotal::fromQuotation($quote, $calc['summary']);
        $this->assertEqualsWithDelta(
            (float) $calc['summary']['grand_total'] + 113.00 + 226.00 + AdditionalCostsService::taxInclusiveTotal($quote->additional_costs),
            $expected,
            0.01
        );

        $html = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertStringContainsString(\App\Models\ClientProject::formatMoney($expected), $html);
        $addTax = round(AdditionalCostsService::taxInclusiveTotal($quote->additional_costs), 2);
        $this->assertMatchesRegularExpression(
            '/"additionalTaxIn":\s*'.preg_quote(number_format($addTax, 2, '.', ''), '/').'/',
            $html
        );
    }
}

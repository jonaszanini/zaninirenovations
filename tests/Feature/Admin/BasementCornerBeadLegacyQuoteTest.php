<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementCornerBeadLegacyQuoteTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_legacy_corner_bead_quote_reopen_keeps_grand_total(): void
    {
        $this->actingAsAdmin();

        $legacyInputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'Legacy Corner Bead Client',
            'area_sqft' => 500,
            'ceiling_height_ft' => 9,
            'drywall_wall_sf' => 500,
            'drywall_corners' => 2,
            'finish_level' => 4,
            'flooring_type' => 'lvp',
            'flooring_sf' => 500,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($legacyInputs['corner_bead_by_height'], $legacyInputs['drywall_boxing_v2']);

        $settings = BasementSettings::current();
        $calc = (new BasementQuotationCalculator)->calculate($legacyInputs, $settings);
        $grand = (float) $calc['summary']['grand_total'];

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy Corner Bead Client',
            'inputs' => $legacyInputs,
            'calculation' => $calc,
            'final_quote_total' => $grand,
            'is_draft' => true,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $reopened = (new BasementQuotationCalculator)->calculate($quote->inputs, $settings);
        $this->assertEqualsWithDelta($grand, (float) $reopened['summary']['grand_total'], 0.02);

        $wallBead = collect($reopened['materials'])->firstWhere('key', 'corner_bead');
        $this->assertNotNull($wallBead);
        $this->assertSame(2.0, (float) $wallBead['qty']);
    }
}

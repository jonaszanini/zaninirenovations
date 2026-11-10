<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementTrimMaterialLegacyQuoteTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_legacy_trim_material_quote_reopen_keeps_grand_total(): void
    {
        $this->actingAsAdmin();

        $legacyInputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'Legacy Trim Material Client',
            'area_sqft' => 500,
            'baseboard_lf' => 40,
            'include_shoe' => true,
            'shoe_lf' => 40,
            'door_qty' => 0,
            'drywall_wall_sf' => 0,
            'flooring_type' => 'none',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($legacyInputs['trim_material_lf_v2']);

        $settings = BasementSettings::current();
        $calc = (new BasementQuotationCalculator)->calculate($legacyInputs, $settings);
        $grand = (float) $calc['summary']['grand_total'];

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy Trim Material Client',
            'inputs' => $legacyInputs,
            'calculation' => $calc,
            'final_quote_total' => $grand,
            'is_draft' => true,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $reopened = (new BasementQuotationCalculator)->calculate($quote->inputs, $settings);
        $this->assertEqualsWithDelta($grand, (float) $reopened['summary']['grand_total'], 0.02);

        $base = collect($reopened['materials'])->firstWhere('key', 'baseboard');
        $this->assertNotNull($base);
        $this->assertSame('each', $base['unit']);
        $this->assertSame(6.0, (float) $base['qty']);
    }
}

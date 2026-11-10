<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementHeaderLegacyQuoteTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_legacy_header_qty_quote_reopen_keeps_grand_total_without_header_lf_key(): void
    {
        $this->actingAsAdmin();

        $legacyInputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'Legacy Header Qty Client',
            'area_sqft' => 600,
            'ceiling_height_ft' => 8,
            'partition_lf' => 20,
            'header_qty' => 2,
            'flooring_type' => 'lvp',
            'flooring_sf' => 600,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($legacyInputs['header_lf']);

        $settings = BasementSettings::current();
        $calc = (new BasementQuotationCalculator)->calculate($legacyInputs, $settings);
        $grand = (float) $calc['summary']['grand_total'];

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy Header Qty Client',
            'inputs' => $legacyInputs,
            'calculation' => $calc,
            'final_quote_total' => $grand,
            'is_draft' => true,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $quote->refresh();
        $this->assertFalse(array_key_exists('header_lf', $quote->inputs));

        $reopened = (new BasementQuotationCalculator)->calculate($quote->inputs, $settings);
        $this->assertEqualsWithDelta($grand, (float) $reopened['summary']['grand_total'], 0.02);

        $headers = collect($reopened['labor'])->firstWhere('key', 'framing_headers');
        $this->assertNotNull($headers);
        $this->assertEqualsWithDelta(4.0, (float) $headers['hours'], 0.02);
        $this->assertStringContainsString('opening', (string) $headers['detail']);

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertStringContainsString('Headers / beams (lin. ft)', $editHtml);
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bname="header_qty")(?=[^>]*\btype="hidden")(?=[^>]*\bvalue="2")/',
            $editHtml
        );
        $this->assertMatchesRegularExpression('/\bid="br-headers"/', $editHtml);
    }
}

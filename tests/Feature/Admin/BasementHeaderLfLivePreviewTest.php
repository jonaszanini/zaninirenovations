<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementHeaderLfLivePreviewTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_posted_zero_header_lf_drops_legacy_opening_hours_in_preview(): void
    {
        $this->actingAsAdmin();
        $settings = BasementSettings::current();
        $legacyInputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'Legacy header live preview',
            'area_sqft' => 600,
            'header_qty' => 8,
            'partition_lf' => 20,
            'owner_hourly_rate' => 80,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
        ]);
        unset($legacyInputs['header_lf']);
        $storedCalc = (new BasementQuotationCalculator)->calculate($legacyInputs, $settings);
        $storedGrand = (float) $storedCalc['summary']['grand_total'];

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy header live preview',
            'inputs' => $legacyInputs,
            'calculation' => $storedCalc,
            'final_quote_total' => $storedGrand,
            'is_draft' => false,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $payload = array_merge($legacyInputs, [
            'client_name' => $quote->client_name,
            'header_lf' => 0,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
        ]);
        $preview = $this->postJson(route('admin.quotations.basement.calculate-existing', $quote), $payload)
            ->assertOk()
            ->json();

        $headers = collect($preview['labor'] ?? [])->firstWhere('key', 'framing_headers');
        $this->assertNull($headers);
        $this->assertLessThan(
            $storedGrand,
            (float) ($preview['summary']['grand_total'] ?? 0),
            'Live preview with 0 lf should be below the stored legacy total until save'
        );

        $quote->refresh();
        $this->assertEqualsWithDelta($storedGrand, (float) $quote->final_quote_total, 0.01);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementAllowancesStaleTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_pdf_allowances_use_live_electrical_amount_after_line_changes(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 800,
            'flooring_sf' => 800,
            'flooring_type' => 'lvp',
            'client_scope_allowances' => "Electrical work allowance CA$9,040.00 (HST included in the trade line).\n",
            'electrical_lines' => [
                ['description' => 'Electrician', 'amount' => 7000, 'hst_included' => false],
            ],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $this->assertTrue(BasementClientScope::allowancesAmountsStale(
            (string) $inputs['client_scope_allowances'],
            $calc['client_scope']['generated']['allowances']
        ));

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Allowances stale',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['grand_total'],
            'version' => 1,
        ]);
        $pdfAllowances = $quote->clientScopeCopy()['allowances'];
        $this->assertStringContainsString('CA$7,910.00', $pdfAllowances);
        $this->assertStringNotContainsString('9,040.00', $pdfAllowances);
    }

    public function test_crlf_allowances_matching_generated_do_not_show_stale_banner(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 800,
            'flooring_sf' => 800,
            'electrical_lines' => [
                ['description' => 'Electrician', 'amount' => 7000, 'hst_included' => false],
            ],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $generated = $calc['client_scope']['generated']['allowances'];
        $inputs['client_scope_allowances'] = str_replace("\n", "\r\n", $generated);

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'CRLF allowances',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['grand_total'],
            'version' => 1,
        ]);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="br-scope-allowances-stale"', $html);
        $this->assertStringNotContainsString('Allowance amounts no longer match', $html);
    }
}

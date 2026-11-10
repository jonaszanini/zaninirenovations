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

    public function test_pdf_includes_electrical_after_placeholder_stored_when_trade_added(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 800,
            'flooring_sf' => 800,
            'client_scope_allowances' => 'No separate cash allowances beyond the priced lines on this quotation.',
            'electrical_lines' => [
                ['description' => 'Electrician', 'amount' => 7000, 'hst_included' => false],
            ],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $this->assertTrue($calc['client_scope']['allowances_stale'] ?? false);

        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Placeholder then electrical',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['grand_total'],
            'version' => 1,
        ]);

        $pdfAllowances = $quote->clientScopeCopy()['allowances'];
        $this->assertStringContainsString('CA$7,910.00', $pdfAllowances);
        $this->assertStringNotContainsString('No separate cash allowances', $pdfAllowances);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertStringContainsString('Allowance amounts no longer match', $html);
    }

    public function test_publish_stores_synced_allowances_matching_pdf_after_trade_added(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 800,
            'flooring_sf' => 800,
            'client_scope_allowances' => 'No separate cash allowances beyond the priced lines on this quotation.',
            'electrical_lines' => [
                ['description' => 'Electrician', 'amount' => 7000, 'hst_included' => false],
            ],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Publish synced allowances',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['grand_total'],
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $payload = array_merge(is_array($quote->inputs) ? $quote->inputs : [], [
            'client_name' => $quote->client_name,
            'save_intent' => 'publish',
            'ask_reply_to_accept' => '0',
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'electrical_lines' => [[
                'description' => 'Electrician',
                'amount' => '7000',
                'hst_included' => '0',
            ]],
        ]);
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $stored = (string) ($quote->inputs['client_scope_allowances'] ?? '');
        $pdf = $quote->clientScopeCopy()['allowances'];
        $this->assertStringContainsString('CA$7,910.00', $stored);
        $this->assertStringNotContainsString('No separate cash allowances', $stored);
        $this->assertSame(BasementClientScope::normalizeAllowanceText($pdf), BasementClientScope::normalizeAllowanceText($stored));
    }
}

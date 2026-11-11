<?php

namespace Tests\Unit\Services;

use App\Services\BasementClientScope;
use Tests\TestCase;

class BasementClientScopeAllowanceSyncTest extends TestCase
{
    public function test_sync_allowance_amounts_replaces_stale_electrical_figure(): void
    {
        $stored = "Electrical work allowance CA$9,040.00 (HST included in the trade line).\n";
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n";

        $this->assertTrue(BasementClientScope::allowancesAmountsStale($stored, $generated));
        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringContainsString('CA$7,910.00', $synced);
        $this->assertStringNotContainsString('9,040.00', $synced);
    }

    public function test_crlf_only_difference_is_not_stale(): void
    {
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n";
        $stored = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\r\n";

        $this->assertFalse(BasementClientScope::allowancesAmountsStale($stored, $generated));
    }

    public function test_sync_does_not_reappend_deleted_permit_line(): void
    {
        $stored = "Electrical work allowance CA$7,910.00 (HST included in the trade line).";
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n"
            ."City building permit extra-charge CA$200.00 before HST (municipal fee; no contractor fee).";

        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringNotContainsString('City building permit', $synced);
        $this->assertStringContainsString('CA$7,910.00', $synced);
    }

    public function test_sync_drops_stale_electrical_when_trade_removed_from_generated(): void
    {
        $stored = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n"
            .'Custom site allowance CA$500.00 before HST.';
        $generated = 'No separate cash allowances beyond the priced lines on this quotation.';

        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringNotContainsString('Electrical work allowance', $synced);
        $this->assertStringNotContainsString('7,910.00', $synced);
        $this->assertStringContainsString('Custom site allowance', $synced);
        $this->assertTrue(BasementClientScope::allowancesAmountsStale($stored, $generated));
    }

    public function test_resolve_copy_pdf_allowances_omits_removed_electrical(): void
    {
        $inputs = [
            'enabled_sections' => ['overview', 'demolition', 'framing', 'drywall', 'painting', 'finishing'],
            'electrical_lines' => [],
            'include_building_permit' => false,
            'include_bcin_drawings' => false,
            'client_scope_allowances' => 'Electrical work allowance CA$7,910.00 (HST included in the trade line).',
        ];
        $copy = BasementClientScope::resolveCopy($inputs, ['summary' => ['trades' => []]]);
        $this->assertStringNotContainsString('7,910.00', $copy['allowances']);
        $this->assertStringNotContainsString('Electrical work allowance', $copy['allowances']);
        $this->assertTrue($copy['allowances_stale'] ?? false);
    }

    public function test_sync_replaces_placeholder_when_electrical_trade_added_later(): void
    {
        $stored = 'No separate cash allowances beyond the priced lines on this quotation.';
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).";

        $this->assertTrue(BasementClientScope::allowancesAmountsStale($stored, $generated));
        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringContainsString('CA$7,910.00', $synced);
        $this->assertStringNotContainsString('No separate cash allowances', $synced);
    }

    public function test_deleted_permit_is_stale_but_not_reappended_to_pdf_sync(): void
    {
        $stored = 'Electrical work allowance CA$7,910.00 (HST included in the trade line).';
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n"
            .'City building permit extra-charge CA$200.00 before HST (municipal fee; no contractor fee).';

        $this->assertTrue(BasementClientScope::allowancesAmountsStale($stored, $generated));
        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringNotContainsString('City building permit', $synced);
    }

    public function test_reset_sync_keeps_custom_non_prefixed_line_and_updates_trade_amount(): void
    {
        $stored = "Electrical work allowance CA$9,040.00 (HST included in the trade line).\n"
            .'Client to choose fixtures from a CA$500.00 fixture budget.';
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).";

        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringContainsString('CA$7,910.00', $synced);
        $this->assertStringNotContainsString('9,040.00', $synced);
        $this->assertStringContainsString('Client to choose fixtures from a CA$500.00 fixture budget', $synced);
        $this->assertFalse(BasementClientScope::allowancesAmountsStale($synced, $generated));
    }

    public function test_merge_reset_restores_deleted_permit_and_custom_line(): void
    {
        $stored = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n"
            .'Client to choose fixtures from a CA$500.00 fixture budget.';
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n"
            .'City building permit extra-charge CA$600.00 before HST (municipal fee; no contractor fee).';

        $this->assertTrue(BasementClientScope::allowancesAmountsStale($stored, $generated));
        $merged = BasementClientScope::mergeAllowancesToCurrent($stored, $generated);
        $this->assertStringContainsString('City building permit extra-charge CA$600.00', $merged);
        $this->assertStringContainsString('Client to choose fixtures from a CA$500.00 fixture budget', $merged);
        $this->assertFalse(BasementClientScope::allowancesAmountsStale($merged, $generated));
        $synced = BasementClientScope::syncAllowanceAmounts($merged, $generated);
        $this->assertStringContainsString('CA$600.00', $synced);
    }

    public function test_sync_still_omits_deleted_permit_for_pdf_until_reset(): void
    {
        $stored = 'Electrical work allowance CA$7,910.00 (HST included in the trade line).';
        $generated = "Electrical work allowance CA$7,910.00 (HST included in the trade line).\n"
            .'City building permit extra-charge CA$600.00 before HST (municipal fee; no contractor fee).';

        $synced = BasementClientScope::syncAllowanceAmounts($stored, $generated);
        $this->assertStringNotContainsString('City building permit', $synced);
    }
}

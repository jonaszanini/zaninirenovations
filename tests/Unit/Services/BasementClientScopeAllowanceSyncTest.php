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
}

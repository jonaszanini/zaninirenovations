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
}

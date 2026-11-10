<?php

namespace Tests\Unit\Support;

use App\Support\MaterialPriceSyncedAtDisplay;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaterialPriceSyncedAtDisplayTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_relative_label_with_frozen_toronto_clock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15 14:00:00', 'America/Toronto'));

        $this->assertSame('never', MaterialPriceSyncedAtDisplay::relativeLabel(null));

        $today = Carbon::parse('2026-03-15 09:30:00', 'UTC');
        $this->assertSame('today', MaterialPriceSyncedAtDisplay::relativeLabel($today));

        $yesterday = Carbon::parse('2026-03-14 12:00:00', 'UTC');
        $this->assertSame('1 day ago', MaterialPriceSyncedAtDisplay::relativeLabel($yesterday));

        $weekAgo = Carbon::parse('2026-03-08 12:00:00', 'UTC');
        $this->assertSame('7 days ago', MaterialPriceSyncedAtDisplay::relativeLabel($weekAgo));
    }

    public function test_stale_after_thirty_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'America/Toronto'));

        $recent = Carbon::parse('2026-04-15 12:00:00', 'UTC');
        $this->assertFalse(MaterialPriceSyncedAtDisplay::isStale($recent));

        $old = Carbon::parse('2026-03-01 12:00:00', 'UTC');
        $this->assertTrue(MaterialPriceSyncedAtDisplay::isStale($old));
    }
}

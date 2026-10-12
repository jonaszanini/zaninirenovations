<?php

namespace Tests\Unit\Support;

use App\Support\AdminListFilters;
use Tests\TestCase;

class AdminListFiltersTest extends TestCase
{
    public function test_counts_only_non_empty_values_by_default(): void
    {
        $this->assertSame(0, AdminListFilters::activeCount([
            'q' => '',
            'status' => null,
            'from' => '  ',
        ]));

        $this->assertSame(2, AdminListFilters::activeCount([
            'q' => 'kitchen',
            'status' => 'quote_pending',
            'from' => '',
        ]));
    }

    public function test_date_range_counts_as_one_active_filter(): void
    {
        $this->assertSame(1, AdminListFilters::activeCount([
            'from' => '2026-01-01',
            'to' => '2026-02-01',
            'q' => '',
        ], [], [
            'dateRange' => ['from', 'to'],
        ]));

        $this->assertSame(1, AdminListFilters::activeCount([
            'from' => '2026-01-01',
            'to' => '',
            'q' => '',
        ], [], [
            'dateRange' => ['from', 'to'],
        ]));

        $this->assertSame(2, AdminListFilters::activeCount([
            'from' => '2026-01-01',
            'to' => '2026-02-01',
            'q' => 'kitchen',
        ], [], [
            'dateRange' => ['from', 'to'],
        ]));
    }

    public function test_ignores_search_shorter_than_controller_threshold(): void
    {
        $this->assertSame(0, AdminListFilters::activeCount([
            'search' => 'a',
            'status' => '',
        ], [], [
            'minLength' => ['search' => 2],
        ]));

        $this->assertSame(1, AdminListFilters::activeCount([
            'search' => 'ab',
            'status' => '',
        ], [], [
            'minLength' => ['search' => 2],
        ]));
    }

    public function test_ignores_values_the_controller_would_not_apply(): void
    {
        $this->assertSame(0, AdminListFilters::activeCount([
            'status' => 'nope',
            'has_receipt' => 'maybe',
        ], [], [
            'allowed' => [
                'status' => ['draft', 'sent', 'paid'],
                'has_receipt' => ['yes', 'no'],
            ],
        ]));

        $this->assertSame(2, AdminListFilters::activeCount([
            'status' => 'sent',
            'has_receipt' => 'yes',
        ], [], [
            'allowed' => [
                'status' => ['draft', 'sent', 'paid'],
                'has_receipt' => ['yes', 'no'],
            ],
        ]));
    }
}

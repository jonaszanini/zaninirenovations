<?php

namespace Tests\Unit\Support;

use App\Support\PurchaseListPrintMode;
use PHPUnit\Framework\TestCase;

class PurchaseListPrintModeTest extends TestCase
{
    /**
     * @dataProvider pricesQueryProvider
     */
    public function test_shows_prices_from_query(mixed $query, bool $expected): void
    {
        $this->assertSame($expected, PurchaseListPrintMode::showsPrices($query));
    }

    public static function pricesQueryProvider(): array
    {
        return [
            'omitted defaults to full' => [null, true],
            'empty defaults to full' => ['', true],
            '1' => ['1', true],
            'full' => ['full', true],
            '0' => ['0', false],
            'simple' => ['simple', false],
            'SIMPLE' => ['SIMPLE', false],
            'false' => ['false', false],
            'no' => ['no', false],
        ];
    }
}

<?php

namespace Tests\Unit\Support;

use App\Support\CadMoney;
use PHPUnit\Framework\TestCase;

class CadMoneyTest extends TestCase
{
    public function test_parse_accepts_thousands_separators(): void
    {
        $this->assertSame(3850.0, CadMoney::parse('3,850.00'));
        $this->assertSame(1200.5, CadMoney::parse('1,200.50'));
        $this->assertSame(3850.0, CadMoney::parse(3850));
    }

    public function test_parse_keeps_typed_dollars_and_does_not_divide_by_100(): void
    {
        $this->assertSame(4.75, CadMoney::parse('4.75'));
        $this->assertSame(4.75, CadMoney::parse('4.7500'));
        $this->assertNotSame(4.75 / 100, CadMoney::parse('4.75'));
        $this->assertSame(475.0, CadMoney::parse('475'));
        $this->assertNotSame(4.75, CadMoney::parse('475'));
    }

    public function test_parse_rejects_invalid_and_empty(): void
    {
        $this->assertNull(CadMoney::parse(''));
        $this->assertNull(CadMoney::parse(null));
        $this->assertNull(CadMoney::parse('abc'));
        $this->assertNull(CadMoney::parse('.'));
        $this->assertNull(CadMoney::parse(','));
    }

    public function test_parse_treats_lone_comma_plus_1_or_2_digits_as_decimal(): void
    {
        $this->assertSame(4.75, CadMoney::parse('4,75'));
        $this->assertSame(4.7, CadMoney::parse('4,7'));
        $this->assertSame(1234.56, CadMoney::parse('1234,56'));
        $this->assertSame('4.75', CadMoney::stripGrouping('4,75'));
    }

    public function test_parse_rejects_ambiguous_mixed_separators(): void
    {
        $this->assertNull(CadMoney::parse('1.234,56'));
        $this->assertSame('1.234,56', CadMoney::stripGrouping('1.234,56'));
        $this->assertNull(CadMoney::parse('1,234,56'));
    }

    public function test_strip_grouping_leaves_non_strings_alone(): void
    {
        $this->assertSame(12.5, CadMoney::stripGrouping(12.5));
        $this->assertSame('3850.00', CadMoney::stripGrouping('3,850.00'));
    }
}

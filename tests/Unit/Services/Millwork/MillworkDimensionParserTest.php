<?php

namespace Tests\Unit\Services\Millwork;

use App\Services\Millwork\MillworkDimensionParser;
use App\Services\Millwork\MillworkPartBuilder;
use PHPUnit\Framework\TestCase;

class MillworkDimensionParserTest extends TestCase
{
    /**
     * @dataProvider dimProvider
     */
    public function test_parses_plain_numbers_and_fractions(mixed $raw, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, MillworkDimensionParser::parse($raw), 0.0001);
    }

    /**
     * @return list<array{0: mixed, 1: float}>
     */
    public function dimProvider(): array
    {
        return [
            [23, 23.0],
            ['23.5', 23.5],
            ['23 1/2', 23.5],
            ['23-1/2', 23.5],
            ['23 1/2"', 23.5],
            ['1/2', 0.5],
            ['2 1/4', 2.25],
            ['', 0.0],
            [null, 0.0],
            ['abc', 0.0],
        ];
    }

    public function test_per_dimension_units_fall_back_to_piece_unit(): void
    {
        $piece = [
            'width' => '2',
            'width_unit' => 'ft',
            'height' => '84',
            'depth' => '14',
            'unit' => 'in',
        ];

        $this->assertSame('ft', MillworkPartBuilder::dimUnit($piece, 'width'));
        $this->assertSame('in', MillworkPartBuilder::dimUnit($piece, 'height'));
        $this->assertEqualsWithDelta(24.0, MillworkPartBuilder::dimInches($piece, ['width']), 0.001);
        $this->assertEqualsWithDelta(84.0, MillworkPartBuilder::dimInches($piece, ['height']), 0.001);
    }

    public function test_fraction_width_with_feet_unit(): void
    {
        $this->assertEqualsWithDelta(
            30.0,
            MillworkPartBuilder::dimInches(['width' => '2 1/2', 'width_unit' => 'ft'], ['width']),
            0.001
        );
    }
}

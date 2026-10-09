<?php

namespace Tests\Unit\Services\Millwork;

use App\Services\Millwork\MillworkDimensionParser;
use App\Services\Millwork\MillworkPartBuilder;
use PHPUnit\Framework\TestCase;

class MillworkDimensionParserTest extends TestCase
{
    /**
     * @dataProvider okProvider
     */
    public function test_parses_unambiguous_sizes_to_inches(mixed $raw, string $fieldUnit, float $inches): void
    {
        $parsed = MillworkDimensionParser::inspect($raw, $fieldUnit);
        $this->assertSame(MillworkDimensionParser::STATUS_OK, $parsed['status'], (string) json_encode($raw));
        $this->assertNull($parsed['message']);
        $this->assertEqualsWithDelta($inches, $parsed['inches'], 0.0008);
        $this->assertGreaterThan(0, $parsed['inches']);
        $this->assertTrue(is_finite($parsed['inches']));
    }

    /**
     * @return list<array{0: mixed, 1: string, 2: float}>
     */
    public function okProvider(): array
    {
        return [
            [23, 'in', 23.0],
            [23.0, 'in', 23.0],
            ['23.5', 'in', 23.5],
            ['23 1/2', 'in', 23.5],
            ['23-1/2', 'in', 23.5],
            ['23 1/2"', 'in', 23.5],
            ['1/2', 'in', 0.5],
            ['2 1/4', 'in', 2.25],
            ['2\' 6"', 'in', 30.0],
            ['2\'6"', 'in', 30.0],
            ['2 ft 6 in', 'in', 30.0],
            ['2 ft', 'in', 24.0],
            ['2ft', 'in', 24.0],
            ['2\'', 'in', 24.0],
            ['2\' 6 1/2"', 'in', 30.5],
            ['762mm', 'in', 762 / 25.4],
            ['76.2cm', 'in', 76.2 / 2.54],
            ['0.762m', 'in', 0.762 / 0.0254],
            ['1,5', 'in', 1.5],
            ['1,50', 'in', 1.5],
            ['½', 'in', 0.5],
            ['23½', 'in', 23.5],
            ['23 ½', 'in', 23.5],
            ['¼', 'in', 0.25],
            ['¾', 'in', 0.75],
            ['2 1/2', 'ft', 30.0],
            ['2', 'ft', 24.0],
            [2, 'ft', 24.0],
            ['36 in', 'ft', 36.0],
            ['3 ft', 'ft', 36.0],
        ];
    }

    /**
     * @dataProvider emptyProvider
     */
    public function test_blank_values_are_empty_not_invalid(mixed $raw): void
    {
        $parsed = MillworkDimensionParser::inspect($raw, 'in');
        $this->assertSame(MillworkDimensionParser::STATUS_EMPTY, $parsed['status']);
        $this->assertSame(0.0, $parsed['inches']);
        $this->assertNull($parsed['message']);
        $this->assertSame(0.0, MillworkDimensionParser::parse($raw));
    }

    /**
     * @return list<array{0: mixed}>
     */
    public function emptyProvider(): array
    {
        return [
            [null],
            [''],
            ['   '],
            [0],
            [0.0],
            ['0'],
            ['0.0'],
            ['00'],
        ];
    }

    /**
     * @dataProvider invalidProvider
     */
    public function test_rejects_unparseable_and_unsafe_values(mixed $raw, string $needle): void
    {
        $parsed = MillworkDimensionParser::inspect($raw, 'in');
        $this->assertSame(MillworkDimensionParser::STATUS_INVALID, $parsed['status'], (string) json_encode($raw));
        $this->assertSame(0.0, $parsed['inches']);
        $this->assertNotNull($parsed['message']);
        $this->assertStringContainsString($needle, (string) $parsed['message']);
        $this->assertSame(0.0, MillworkDimensionParser::parse($raw));
    }

    /**
     * @return list<array{0: mixed, 1: string}>
     */
    public function invalidProvider(): array
    {
        return [
            ['abc', 'valid size'],
            ['xyz"', 'valid size'],
            ['1/0', 'valid size'],
            ['23 1/0', 'valid size'],
            ['-24', 'greater than zero'],
            [-24, 'greater than zero'],
            ['-2\' 6"', 'greater than zero'],
            ['1E400', 'valid size'],
            ['1e3', 'valid size'],
            [INF, 'valid size'],
            [NAN, 'valid size'],
            ['--24', 'greater than zero'],
            ['12foo', 'valid size'],
            ['1,500', 'valid size'],
            ['1,234,567', 'valid size'],
        ];
    }

    public function test_parse_keeps_backward_compatible_zero_for_garbage(): void
    {
        $this->assertSame(0.0, MillworkDimensionParser::parse('abc'));
        $this->assertEqualsWithDelta(23.5, MillworkDimensionParser::parse('23 1/2'), 0.0001);
        $this->assertEqualsWithDelta(24.0, MillworkDimensionParser::parse('2 ft'), 0.0001);
    }

    public function test_typed_zero_string_is_empty_like_stored_zero(): void
    {
        $parsed = MillworkDimensionParser::inspect('0', 'in');
        $this->assertSame(MillworkDimensionParser::STATUS_EMPTY, $parsed['status']);
        $this->assertSame(0.0, $parsed['inches']);
        $this->assertNull($parsed['message']);
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

    public function test_two_ft_in_an_inch_field_is_twenty_four_inches(): void
    {
        $this->assertEqualsWithDelta(
            24.0,
            MillworkPartBuilder::dimInches(['width' => '2 ft', 'width_unit' => 'in'], ['width']),
            0.001
        );
    }

    public function test_legacy_conversion_rewrites_only_retyped_sizes(): void
    {
        $legacy = MillworkDimensionParser::toLegacyInchPiece([
            'type' => 'cabinet',
            'width' => '23 1/2',
            'width_unit' => 'in',
            'height' => '2',
            'height_unit' => 'ft',
            'depth' => '762mm',
            'unit' => 'ft',
            'sets' => [[
                'qty' => 1,
                'length' => '3 ft',
                'depth' => '1,5',
                'unit' => 'in',
            ]],
        ]);

        $this->assertSame('ft', $legacy['unit']);
        $this->assertEqualsWithDelta(23.5 / 12.0, (float) $legacy['width'], 0.001);
        $this->assertSame('2', $legacy['height']);
        $this->assertEqualsWithDelta((762 / 25.4) / 12.0, (float) $legacy['depth'], 0.01);
        $this->assertEqualsWithDelta(36.0, (float) $legacy['sets'][0]['length'], 0.001);
        $this->assertEqualsWithDelta(1.5, (float) $legacy['sets'][0]['depth'], 0.001);
    }

    public function test_legacy_conversion_leaves_trim_openings_and_plain_ft_length(): void
    {
        $legacy = MillworkDimensionParser::toLegacyInchPiece([
            'type' => 'trim',
            'profile' => 'casing',
            'unit' => 'ft',
            'length' => 20,
            'length_unit' => 'ft',
            'openings' => 3,
            'width' => 36,
            'width_unit' => 'ft',
            'height' => 80,
            'height_unit' => 'ft',
        ]);

        $this->assertSame('ft', $legacy['unit']);
        $this->assertSame(20, $legacy['length']);
        $this->assertSame(36, $legacy['width']);
        $this->assertSame(80, $legacy['height']);
        $this->assertSame(3, $legacy['openings']);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaMeasurementParser;
use InvalidArgumentException;
use Tests\TestCase;

class RoomAreaMeasurementParserTest extends TestCase
{
    private RoomAreaMeasurementParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RoomAreaMeasurementParser;
    }

    /**
     * @dataProvider feetInchProvider
     */
    public function test_parses_feet_inches_and_fractions(string $raw, float $expectedFeet): void
    {
        $this->assertEqualsWithDelta(
            $expectedFeet,
            $this->parser->parse($raw, RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC),
            0.0001
        );
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    public function feetInchProvider(): array
    {
        return [
            ["12' 6\"", 12.5],
            ["12'6", 12.5],
            ['12 6', 12.5],
            ['12.5', 12.5],
            ['150"', 12.5],
            ['150', 12.5],
            ["12' 6 1/2\"", 12 + 6.5 / 12],
            ['12 6 1/2', 12 + 6.5 / 12],
            ["12' ½\"", 12 + 0.5 / 12],
            ["12’ 6”", 12.5],
            ['3/4"', 0.75 / 12],
            ["10'", 10.0],
            ['12-6', 12.5],
        ];
    }

    public function test_parses_metric_meters_and_centimeters(): void
    {
        $meters = $this->parser->parse('3.048 m', RoomAreaMeasurementParser::UNIT_METER);
        $this->assertEqualsWithDelta(10.0, $meters, 0.0001);

        $cm = $this->parser->parse('304.8 cm', RoomAreaMeasurementParser::UNIT_METER);
        $this->assertEqualsWithDelta(10.0, $cm, 0.0001);
    }

    public function test_explicit_suffixes_win_over_display_mode(): void
    {
        $this->assertEqualsWithDelta(12.5, $this->parser->parse("12' 6\"", RoomAreaMeasurementParser::UNIT_METER), 0.0001);
        $this->assertEqualsWithDelta(3.81 / 0.3048, $this->parser->parse('3.81 m', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC), 0.0001);
        $this->assertEqualsWithDelta(3.81 / 0.3048, $this->parser->parse('3810 mm', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL), 0.0001);
        $this->assertEqualsWithDelta(12.5, $this->parser->parse('12.5', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL), 0.0001);
    }

    public function test_parses_inches_only_mode(): void
    {
        $this->assertEqualsWithDelta(
            12.5,
            $this->parser->parse('150', RoomAreaMeasurementParser::UNIT_INCH),
            0.0001
        );
    }

    public function test_parses_feet_decimal_mode(): void
    {
        $this->assertEqualsWithDelta(
            12.5,
            $this->parser->parse('12.5 ft', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL),
            0.0001
        );
    }

    public function test_rejects_zero_and_blank(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('0');
    }

    public function test_formats_closing_gap_with_vulgar_half(): void
    {
        $this->assertSame('1½"', $this->parser->formatClosingGap(1.5));
        $this->assertSame('6"', $this->parser->formatClosingGap(6));
        $this->assertSame('3/4"', $this->parser->formatClosingGap(0.75));
    }

    /**
     * @dataProvider qaParserProvider
     */
    public function test_qa_parser_cases(string $raw, string $unit, float $expectedFeet): void
    {
        $this->assertEqualsWithDelta(
            $expectedFeet,
            $this->parser->parse($raw, $unit),
            0.0001,
            $raw
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: float}>
     */
    public function qaParserProvider(): array
    {
        $ft = RoomAreaMeasurementParser::FEET_TO_METERS;

        return [
            ['3810mm', RoomAreaMeasurementParser::UNIT_METER, 3.81 / $ft],
            ['3810 mm', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL, 3.81 / $ft],
            ['12ft 6in', RoomAreaMeasurementParser::UNIT_METER, 12.5],
            ['12 ft 6 in', RoomAreaMeasurementParser::UNIT_METER, 12.5],
            ['5 m 20 cm', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC, 5.2 / $ft],
            ['5m 20cm', RoomAreaMeasurementParser::UNIT_INCH, 5.2 / $ft],
            ['3,81', RoomAreaMeasurementParser::UNIT_METER, 3.81 / $ft],
            ['3,81 m', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC, 3.81 / $ft],
            ['150″', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC, 12.5],
            ['12′ 6″', RoomAreaMeasurementParser::UNIT_METER, 12.5],
            ['6½', RoomAreaMeasurementParser::UNIT_INCH, 6.5 / 12],
            ['6½"', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC, 6.5 / 12],
            ['3,810 mm', RoomAreaMeasurementParser::UNIT_METER, 3.81 / $ft],
            ['3.810 mm', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL, 3.81 / $ft],
            ['12.700 mm', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL, 0.0127 / $ft],
            ['50.000 mm', RoomAreaMeasurementParser::UNIT_METER, 0.05 / $ft],
            ["12’ 6”", RoomAreaMeasurementParser::UNIT_METER, 12.5],
        ];
    }

    /**
     * @dataProvider qaRejectProvider
     */
    public function test_qa_parser_rejects_junk_and_out_of_range(string $raw, string $unit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($raw, $unit);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function qaRejectProvider(): array
    {
        return [
            ['12abc', RoomAreaMeasurementParser::UNIT_METER],
            ['12abc', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC],
            ['1e9', RoomAreaMeasurementParser::UNIT_METER],
            ['1e9', RoomAreaMeasurementParser::UNIT_FEET_DECIMAL],
            ['6 1/0"', RoomAreaMeasurementParser::UNIT_FEET_INCH_FRAC],
            ['0.500 mm', RoomAreaMeasurementParser::UNIT_METER],
        ];
    }
}

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
}

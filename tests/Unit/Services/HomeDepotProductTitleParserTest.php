<?php

namespace Tests\Unit\Services;

use App\Services\MaterialScraping\HomeDepotProductTitleParser;
use PHPUnit\Framework\TestCase;

class HomeDepotProductTitleParserTest extends TestCase
{
    public function test_parses_nail_pack_quantity(): void
    {
        $result = HomeDepotProductTitleParser::parse(
            '1000 Count 21 Degree Framing Nails Full Round Head (2 Inch X 0.113 Inch)'
        );

        $this->assertSame(1000, $result['quantity_per_package']);
        $this->assertSame('package', $result['unit']);
    }

    public function test_parses_plywood_as_sheet(): void
    {
        $result = HomeDepotProductTitleParser::parse(
            '1/2-inch x 4 ft. x 8 ft. Standard Spruce Plywood Board'
        );

        $this->assertNull($result['quantity_per_package']);
        $this->assertSame('each', $result['unit']);
    }

    public function test_returns_nulls_for_empty_title(): void
    {
        $result = HomeDepotProductTitleParser::parse(null);

        $this->assertNull($result['unit']);
        $this->assertNull($result['quantity_per_package']);
    }
}

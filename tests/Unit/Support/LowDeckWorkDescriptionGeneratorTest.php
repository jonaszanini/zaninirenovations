<?php

namespace Tests\Unit\Support;

use App\Support\LowDeckWorkDescriptionGenerator;
use Tests\TestCase;

class LowDeckWorkDescriptionGeneratorTest extends TestCase
{
    public function test_generates_description_with_placeholders_replaced(): void
    {
        $text = LowDeckWorkDescriptionGenerator::generateFromTemplate([
            'width_ft' => 12.0,
            'length_ft' => 14.0,
            'area_sq_ft' => 168.0,
            'joist_spacing_in' => 13.96,
            'joists' => ['field_spacing_in' => 13.96],
        ], LowDeckWorkDescriptionGenerator::defaultTemplate());

        $this->assertStringContainsString("12'x14'", $text);
        $this->assertStringContainsString('168 square feet', $text);
        $this->assertStringContainsString('13.96 inches on center', $text);
        $this->assertStringContainsString('No railing is included', $text);
        $this->assertStringNotContainsString('{{dimensions}}', $text);
        $this->assertStringNotContainsString('{{area}}', $text);
        $this->assertStringNotContainsString('{{joist_spacing}}', $text);
    }

    public function test_formats_decimal_dimensions_and_area(): void
    {
        $text = LowDeckWorkDescriptionGenerator::generateFromTemplate([
            'width_ft' => 18.0,
            'length_ft' => 19.6,
            'area_sq_ft' => 352.8,
            'joists' => ['field_spacing_in' => 14.98],
        ], LowDeckWorkDescriptionGenerator::defaultTemplate());

        $this->assertStringContainsString("18'x19.6'", $text);
        $this->assertStringContainsString('352.8 square feet', $text);
        $this->assertStringContainsString('14.98 inches on center', $text);
    }

    public function test_format_helpers_trim_trailing_zeros(): void
    {
        $this->assertSame("12'", LowDeckWorkDescriptionGenerator::formatFoot(12.0));
        $this->assertSame("19.6'", LowDeckWorkDescriptionGenerator::formatFoot(19.6));
        $this->assertSame('168', LowDeckWorkDescriptionGenerator::formatArea(168.0));
        $this->assertSame('352.8', LowDeckWorkDescriptionGenerator::formatArea(352.8));
        $this->assertSame('16 inches', LowDeckWorkDescriptionGenerator::formatJoistSpacingInches(16.0));
    }
}

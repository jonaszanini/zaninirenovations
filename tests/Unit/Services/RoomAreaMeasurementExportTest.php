<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaMeasurementExport;
use PHPUnit\Framework\TestCase;

class RoomAreaMeasurementExportTest extends TestCase
{
    public function test_csv_columns_are_stable(): void
    {
        $this->assertSame([
            'Phase',
            'Room',
            'Area (sq ft)',
            'Perimeter (lf)',
            'Net wall (sq ft)',
            'Flooring with waste (sq ft)',
            'Casing (lf)',
            'Ceiling height (ft)',
            'Flooring waste (%)',
            'Flooring boxes',
            'Project',
            'Client',
        ], RoomAreaMeasurementExport::COLUMNS);
    }

    public function test_sanitize_cell_prefixes_formula_injection_and_rounds_numbers(): void
    {
        $export = new RoomAreaMeasurementExport;
        $this->assertSame("'=1+1", $export->sanitizeCell('=1+1'));
        $this->assertSame("'+cmd", $export->sanitizeCell('+cmd'));
        $this->assertSame("'-2+3", $export->sanitizeCell('-2+3'));
        $this->assertSame("'@SUM(A1)", $export->sanitizeCell('@SUM(A1)'));
        $this->assertSame("'\tcmd", $export->sanitizeCell("\tcmd"));
        $this->assertSame("'\r=1", $export->sanitizeCell("\r=1"));
        $this->assertSame('Kitchen', $export->sanitizeCell('Kitchen'));
        $this->assertSame('José Ñoño', $export->sanitizeCell('José Ñoño'));
        $this->assertSame('120.00', $export->sanitizeCell(120));
        $this->assertSame('32.67', $export->sanitizeCell(32.666));
        $this->assertSame('70.40', $export->sanitizeCell(70.4));
        $this->assertSame(RoomAreaMeasurementExport::UTF8_BOM, "\xEF\xBB\xBF");
    }

    public function test_excel_writes_numbers_including_negatives_and_plain_text_strings(): void
    {
        $export = new RoomAreaMeasurementExport;
        $this->assertSame(-12.5, $export->excelRow(['=CMD', -12.5, 120, ''])[1]);
        $this->assertSame('=CMD', $export->excelRow(['=CMD', -12.5])[0]);
        $this->assertSame("'=CMD", $export->sanitizeCell('=CMD'));

        $xml = $export->sheetXml([
            ['Phase', -12.5, '=SUM(A1)', 120.00],
        ]);
        $this->assertStringContainsString('t="n"><v>-12.50</v>', $xml);
        $this->assertStringContainsString('t="n"><v>120.00</v>', $xml);
        $this->assertStringContainsString('t="inlineStr"><is><t>=SUM(A1)</t></is></c>', $xml);
        $this->assertStringNotContainsString("'=SUM", $xml);
        $this->assertStringNotContainsString("'=-12", $xml);
    }
}

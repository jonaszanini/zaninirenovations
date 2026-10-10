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
}

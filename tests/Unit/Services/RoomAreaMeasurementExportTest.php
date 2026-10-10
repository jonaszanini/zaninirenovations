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
}

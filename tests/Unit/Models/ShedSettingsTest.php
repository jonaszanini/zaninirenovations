<?php

namespace Tests\Unit\Models;

use App\Models\ShedSettings;
use Tests\TestCase;

class ShedSettingsTest extends TestCase
{
    public function test_asphalt_shingles_default_to_bundle_coverage(): void
    {
        $row = ShedSettings::defaultMaterials()['asphalt_shingle'];

        $this->assertSame('bundle', $row['unit']);
        $this->assertStringContainsString('33.3', $row['name']);
        $this->assertEqualsWithDelta(
            round(95.00 * ShedSettings::SHINGLE_SF_PER_BUNDLE / ShedSettings::SHINGLE_SF_PER_SQUARE, 4),
            $row['unit_price'],
            0.0001
        );
    }

    public function test_linked_catalog_keeps_bundle_price_when_stored_unit_was_square(): void
    {
        $settings = new ShedSettings([
            'materials' => [
                'asphalt_shingle' => [
                    'name' => 'Timberline HDZ Charcoal',
                    'unit' => 'sq',
                    'unit_price' => 44.13,
                    'catalog_material_id' => 12,
                ],
            ],
        ]);

        $row = $settings->resolvedMaterials()['asphalt_shingle'];

        $this->assertSame('bundle', $row['unit']);
        $this->assertSame(12, $row['catalog_material_id']);
        $this->assertEqualsWithDelta(44.13, $row['unit_price'], 0.0001);
        $this->assertSame('Timberline HDZ Charcoal', $row['name']);
    }

    public function test_manual_square_price_converts_to_bundle(): void
    {
        $settings = new ShedSettings([
            'materials' => [
                'asphalt_shingle' => [
                    'unit' => 'sq',
                    'unit_price' => 95.00,
                    'catalog_material_id' => null,
                ],
            ],
        ]);

        $row = $settings->resolvedMaterials()['asphalt_shingle'];

        $this->assertSame('bundle', $row['unit']);
        $this->assertEqualsWithDelta(
            round(95.00 * ShedSettings::SHINGLE_SF_PER_BUNDLE / ShedSettings::SHINGLE_SF_PER_SQUARE, 4),
            $row['unit_price'],
            0.0001
        );
    }
}

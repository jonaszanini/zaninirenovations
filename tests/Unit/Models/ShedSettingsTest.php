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

    public function test_metal_roof_defaults_are_black_and_silver_panels(): void
    {
        $mats = ShedSettings::defaultMaterials();

        $this->assertArrayNotHasKey('metal_roof', $mats);
        $this->assertSame('panel', $mats[ShedSettings::MATERIAL_METAL_BLACK]['unit']);
        $this->assertSame('panel', $mats[ShedSettings::MATERIAL_METAL_GALVANIZED]['unit']);
        $this->assertEqualsWithDelta(72.28, $mats[ShedSettings::MATERIAL_METAL_BLACK]['unit_price'], 0.001);
        $this->assertEqualsWithDelta(63.38, $mats[ShedSettings::MATERIAL_METAL_GALVANIZED]['unit_price'], 0.001);
    }

    public function test_legacy_metal_roof_row_maps_to_black_panel(): void
    {
        $settings = new ShedSettings([
            'materials' => [
                'metal_roof' => [
                    'name' => 'Old metal sheet',
                    'unit' => 'sf',
                    'unit_price' => 4.25,
                    'catalog_material_id' => 44,
                ],
            ],
        ]);

        $row = $settings->resolvedMaterials()[ShedSettings::MATERIAL_METAL_BLACK];
        $this->assertSame('panel', $row['unit']);
        $this->assertSame(44, $row['catalog_material_id']);
        $this->assertSame('Old metal sheet', $row['name']);
        $this->assertEqualsWithDelta(72.28, $row['unit_price'], 0.001);
    }

    public function test_every_default_material_has_an_info_tip(): void
    {
        $tips = ShedSettings::materialInfoTips();
        foreach (array_keys(ShedSettings::defaultMaterials()) as $key) {
            $this->assertArrayHasKey($key, $tips);
            $this->assertNotSame('', trim($tips[$key]));
        }
    }

    public function test_price_config_packs_two_field_groups_on_one_row(): void
    {
        $html = view('admin.partials.quotation_productivity_groups', [
            'productivityGroups' => ShedSettings::productivityGroups(),
            'productivity' => [],
        ])->render();

        $this->assertStringContainsString('br-prod-pair', $html);
        $this->assertStringContainsString('admin-form-grid-4', $html);

        $pairStart = strpos($html, 'br-prod-pair');
        $this->assertNotFalse($pairStart);
        $pairHtml = substr($html, $pairStart, 1800);
        $this->assertStringContainsString('Floor &amp; walls', $pairHtml);
        $this->assertStringContainsString('Openings', $pairHtml);
        $this->assertStringNotContainsString('Roof &amp; siding', $pairHtml);
    }
}

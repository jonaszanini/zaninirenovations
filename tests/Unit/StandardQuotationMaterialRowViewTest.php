<?php

namespace Tests\Unit;

use App\Models\StandardQuotation;
use Tests\TestCase;

class StandardQuotationMaterialRowViewTest extends TestCase
{
    public function test_electrical_mode_hides_short_name_and_prefills_description(): void
    {
        $html = view('admin.quotations.standard._material_row', [
            'i' => 0,
            'row' => [
                'entry_mode' => 'electrical',
                'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
                'short_name' => '',
                'description' => '',
                'url' => '',
                'unit_price' => 650,
                'quantity' => 1,
                'apply_hst' => true,
                'hst_includes_unit' => false,
                'catalog_material_id' => null,
                'delivery_cad' => 0,
            ],
        ])->render();

        $this->assertStringContainsString('value="electrical"', $html);
        $this->assertStringContainsString('sq-material-row--electrical', $html);
        $this->assertStringContainsString(StandardQuotation::ELECTRICAL_LINE_DESCRIPTION, $html);
        $this->assertMatchesRegularExpression('/sq-mat-field--short-name"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/sq-mat-row--url"[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('Unit price', $html);
        $this->assertStringContainsString('name="materials[0][quantity]"', $html);
    }

    public function test_manual_mode_includes_image_url_field_and_preview(): void
    {
        $html = view('admin.quotations.standard._material_row', [
            'i' => 0,
            'row' => [
                'entry_mode' => 'manual',
                'short_name' => 'Paint',
                'description' => 'Interior paint',
                'url' => 'https://supplier.com/product',
                'image_url' => 'https://supplier.com/image.jpg',
                'unit_price' => 20,
                'quantity' => 1,
                'apply_hst' => true,
                'hst_includes_unit' => false,
                'catalog_material_id' => null,
                'delivery_cad' => 0,
            ],
        ])->render();

        $this->assertStringContainsString('name="materials[0][image_url]"', $html);
        $this->assertStringContainsString('sq-mat-image-url', $html);
        $this->assertStringContainsString('Image URL', $html);
        $this->assertStringContainsString('https://supplier.com/image.jpg', $html);
        $this->assertStringContainsString('sq-mat-thumb', $html);
        $this->assertDoesNotMatchRegularExpression('/sq-mat-thumb"[^>]*\bhidden\b/', $html);
    }
}

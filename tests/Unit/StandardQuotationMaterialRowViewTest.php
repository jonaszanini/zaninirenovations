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
}

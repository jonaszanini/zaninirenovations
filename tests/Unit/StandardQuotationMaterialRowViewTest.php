<?php

namespace Tests\Unit;

use App\Models\StandardQuotation;
use Tests\TestCase;

class StandardQuotationMaterialRowViewTest extends TestCase
{
    public function test_electrical_mode_hides_short_name_and_prefills_description(): void
    {
        $html = view('admin.quotations.standard._material_row', [
            'workIndex' => 0,
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
        $this->assertStringContainsString('name="works[0][materials][0][quantity]"', $html);
    }

    public function test_manual_mode_includes_image_url_field_and_preview(): void
    {
        $html = view('admin.quotations.standard._material_row', [
            'workIndex' => 1,
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

        $this->assertStringContainsString('name="works[1][materials][0][image_url]"', $html);
        $this->assertStringContainsString('sq-mat-image-url', $html);
        $this->assertStringContainsString('Image URL', $html);
        $this->assertStringContainsString('https://supplier.com/image.jpg', $html);
        $this->assertStringContainsString('sq-mat-thumb', $html);
        $this->assertDoesNotMatchRegularExpression('/sq-mat-thumb"[^>]*\bhidden\b/', $html);
    }

    public function test_work_card_nests_material_and_labour_fields(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = view('admin.quotations.standard._work_card', [
            'workIndex' => 0,
            'workCount' => 1,
            'isOpen' => true,
            'work' => [
                'title' => '',
                'materials' => [[
                    'short_name' => '',
                    'description' => '',
                    'url' => '',
                    'image_url' => '',
                    'unit_price' => '',
                    'quantity' => '1',
                    'apply_hst' => true,
                    'hst_includes_unit' => false,
                    'catalog_material_id' => null,
                    'delivery_cad' => 0,
                ]],
                'labour_mode' => 'hours',
                'labour_quantity' => '',
                'labour_hourly_rate' => '65.00',
                'labour_use_fixed' => false,
                'labour_fixed_amount' => '',
                'labour_fixed_hst_included' => false,
            ],
        ])->render();

        $this->assertStringContainsString('name="works[0][title]"', $html);
        $this->assertStringContainsString('name="works[0][labour_hourly_rate]"', $html);
        $this->assertStringContainsString('name="works[0][materials][0][short_name]"', $html);
        $this->assertStringContainsString('Add material', $html);
        $this->assertStringContainsString('sq-remove-work', $html);
    }
}

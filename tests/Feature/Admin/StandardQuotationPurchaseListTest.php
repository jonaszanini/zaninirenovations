<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\StandardQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class StandardQuotationPurchaseListTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_full_material_list_includes_unit_price_and_total(): void
    {
        $this->actingAsAdmin();
        $quote = $this->makeQuote();

        $response = $this->get(route('admin.quotations.standard.purchase-list', [
            'standard_quotation' => $quote,
            'prices' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Black aluminium spindle', false);
        $response->assertSee('SKU HD-123', false);
        $response->assertSee('Unit price', false);
        $response->assertSee('Total', false);
        $response->assertSee('CA$12.50', false);
        $response->assertDontSee('Material list (no prices)', false);
    }

    public function test_simple_material_list_omits_unit_price_and_total(): void
    {
        $this->actingAsAdmin();
        $quote = $this->makeQuote();

        $response = $this->get(route('admin.quotations.standard.purchase-list', [
            'standard_quotation' => $quote,
            'prices' => 0,
        ]));

        $response->assertOk();
        $response->assertSee('Material list (no prices)', false);
        $response->assertSee('Black aluminium spindle', false);
        $response->assertDontSee('Unit price', false);
        $response->assertDontSee('Materials total', false);
    }

    public function test_edit_page_shows_print_material_list_button(): void
    {
        $this->actingAsAdmin();
        $quote = $this->makeQuote();

        $this->get(route('admin.quotations.standard.edit', $quote))
            ->assertOk()
            ->assertSee('Print material list', false)
            ->assertSee('data-purchase-list-choice', false)
            ->assertSee('Electrical work', false)
            ->assertSee('value="electrical"', false);
    }

    public function test_purchase_list_omits_electrical_work_rows(): void
    {
        $this->actingAsAdmin();
        $quote = $this->makeQuote();
        $materials = $quote->materials;
        $materials[] = [
            'line_kind' => 'electrical',
            'entry_mode' => 'electrical',
            'description' => 'Electrical work (Including material)',
            'unit_price' => 650,
            'quantity' => 1,
            'apply_hst' => true,
        ];
        $quote->materials = $materials;
        $quote->save();

        $response = $this->get(route('admin.quotations.standard.purchase-list', [
            'standard_quotation' => $quote,
            'prices' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Black aluminium spindle', false);
        $response->assertDontSee('Electrical work (Including material)', false);
    }

    private function makeQuote(): StandardQuotation
    {
        $catalog = Material::create([
            'name' => 'Black aluminium spindle',
            'store_sku' => 'HD-123',
            'price_cad' => 12.50,
            'is_active' => true,
            'unit' => 'each',
        ]);

        return StandardQuotation::query()->create([
            'quote_number' => 'SQ-2026-'.str_pad((string) random_int(8000, 9999), 4, '0', STR_PAD_LEFT),
            'client_name' => 'Luccas Curcio',
            'display_title' => '40LF - Railing for deck',
            'materials' => [
                [
                    'short_name' => 'Spindle',
                    'description' => 'Black aluminium spindle',
                    'url' => null,
                    'unit_price' => 12.50,
                    'quantity' => 40,
                    'apply_hst' => true,
                    'hst_includes_unit' => false,
                    'catalog_material_id' => $catalog->id,
                ],
            ],
        ]);
    }
}

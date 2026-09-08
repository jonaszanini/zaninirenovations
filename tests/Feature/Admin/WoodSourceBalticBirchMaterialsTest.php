<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\MaterialVendor;
use App\Models\MillworkSettings;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class WoodSourceBalticBirchMaterialsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_wood_source_materials_seeded_and_linked_to_millwork_price_configuration(): void
    {
        $this->actingAsAdmin();

        // 1. Verify vendor
        $vendor = MaterialVendor::query()->where('slug', 'the-wood-source')->first();
        $this->assertNotNull($vendor);
        $this->assertSame('The Wood Source', $vendor->name);

        // 2. Verify all 8 materials in Material DB
        $expected = [
            [
                'sku' => 'BB48-BB-6MM',
                'name' => 'BB/BB 6mm / 1/4" Baltic Birch Plywood – 4’x8′',
                'price' => 56.00,
                'thickness' => 0.25,
            ],
            [
                'sku' => 'BB48-BB-12MM',
                'name' => 'BB/BB 12mm / 1/2" Baltic Birch Plywood – 4’x8′',
                'price' => 95.67,
                'thickness' => 0.50,
            ],
            [
                'sku' => 'BB48-BB-15MM',
                'name' => 'BB/BB 15mm / 5/8" Baltic Birch Plywood – 4’x8′',
                'price' => 109.38,
                'thickness' => 0.625,
            ],
            [
                'sku' => 'BB48-BB-18MM',
                'name' => 'BB/BB 18mm / 3/4" Baltic Birch Plywood – 4’x8′',
                'price' => 129.00,
                'thickness' => 0.75,
            ],
            [
                'sku' => 'BB48-BB-24MM',
                'name' => 'BB/BB 24mm / 1" Baltic Birch Plywood – 4’x8′',
                'price' => 183.75,
                'thickness' => 1.00,
            ],
            [
                'sku' => 'BB48-B-6MM',
                'name' => 'B/BB 6mm / 1/4" Baltic Birch Plywood – 4’x8′',
                'price' => 61.60,
                'thickness' => 0.25,
            ],
            [
                'sku' => 'BB48-B-12MM',
                'name' => 'B/BB 12mm / 1/2" Baltic Birch Plywood – 4’x8′',
                'price' => 115.00,
                'thickness' => 0.50,
            ],
            [
                'sku' => 'BB48-B-18MM',
                'name' => 'B/BB 18mm / 3/4" Baltic Birch Plywood – 4’x8′',
                'price' => 159.95,
                'thickness' => 0.75,
            ],
        ];

        $materialsBySku = [];
        foreach ($expected as $item) {
            /** @var Material|null $material */
            $material = Material::query()->where('store_sku', $item['sku'])->first();
            $this->assertNotNull($material, "Material with SKU {$item['sku']} must exist.");
            $this->assertSame($item['name'], $material->name);
            $this->assertEquals($item['price'], (float) $material->price_cad);
            $this->assertSame('https://wood-source.com/product/baltic-birch-plywood-4x8/', $material->product_url);
            $this->assertSame('https://wood-source.com/wp-content/uploads/2023/02/B306018-900x900.jpg', $material->image_url);
            $this->assertSame('sheet', $material->unit);
            $this->assertTrue((bool) $material->is_active);
            $this->assertStringStartsWith('Baltic Birch Plywood – 4’x8′', $material->description);
            $this->assertSame($vendor->id, $material->vendor_id);

            $materialsBySku[$item['sku']] = $material;
        }

        // 3. Verify MillworkSettings wood_sheets catalog
        $settings = MillworkSettings::current();
        $catalog = $settings->resolvedCatalog();
        $woodSheets = $catalog[MillworkSettings::SLOT_WOOD_SHEETS] ?? [];

        $sheetsByMaterialId = [];
        foreach ($woodSheets as $sheet) {
            if (! empty($sheet['catalog_material_id'])) {
                $sheetsByMaterialId[(int) $sheet['catalog_material_id']] = $sheet;
            }
        }

        foreach ($expected as $item) {
            $material = $materialsBySku[$item['sku']];
            $this->assertArrayHasKey($material->id, $sheetsByMaterialId, "Sheet for {$material->name} must be linked in wood_sheets.");
            $sheet = $sheetsByMaterialId[$material->id];

            $this->assertEquals($item['price'], (float) $sheet['unit_price']);
            $this->assertEquals($item['thickness'], (float) $sheet['thickness_in']);
            $this->assertEquals(48.0, (float) $sheet['width_in']);
            $this->assertEquals(96.0, (float) $sheet['length_in']);
            $this->assertSame('plywood', $sheet['material_kind']);
            $this->assertSame('cabinet', $sheet['grade']);
        }

        // 4. Verify Millwork price-configuration page renders the linked rows
        $this->get(route('admin.quotations.millwork.price-configuration', ['tab' => 'catalog']))
            ->assertOk()
            ->assertSee('BB/BB 6mm / 1/4&quot; Baltic Birch Plywood', false)
            ->assertSee('BB/BB 18mm / 3/4&quot; Baltic Birch Plywood', false)
            ->assertSee('B/BB 18mm / 3/4&quot; Baltic Birch Plywood', false)
            ->assertSee('56.00')
            ->assertSee('159.95');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class MaterialCustomCatalogTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_custom_supplier_can_be_saved_without_sku_or_store_vendor(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.materials.store'), [
            'name' => 'TBD',
            'short_name' => 'Debris bin / dumpster',
            'price_cad' => '400.00',
            'delivery_cad' => '0.00',
            'unit' => 'each',
            'product_url' => 'https://binrentalsottawa.com/dumpster-rentals/',
            'image_url' => 'https://dumpsquad.ca/wp-content/uploads/2023/11/disposable.png',
            'is_active' => '1',
            'sort_order' => '0',
        ]);

        $material = Material::query()->where('short_name', 'Debris bin / dumpster')->first();
        $this->assertNotNull($material);
        $this->assertTrue((bool) $material->is_active);
        $this->assertNull($material->store_sku);
        $this->assertNull($material->vendor_id);
        $this->assertFalse($material->isSerpApiSyncable());
        $response->assertRedirect(route('admin.materials.edit', $material));
    }
}

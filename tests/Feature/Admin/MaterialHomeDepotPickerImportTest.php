<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\MaterialVendor;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Mockery;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class MaterialHomeDepotPickerImportTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_guest_cannot_import_from_home_depot(): void
    {
        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])->assertRedirect(route('login'));
    }

    public function test_rejects_invalid_sku_format(): void
    {
        $this->actingAsAdmin();

        $this->mockSyncService()->shouldNotReceive('syncOne');

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => 'not-a-sku',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Enter a Home Depot SKU or product URL.');
    }

    public function test_new_sku_creates_material_and_returns_picker_option(): void
    {
        $this->actingAsAdmin();

        $this->mockSyncService()
            ->shouldReceive('syncOne')
            ->once()
            ->andReturnUsing(function (Material $material) {
                $material->name = '6 in. x 6 in. Black Vinyl Fence Post Cap';
                $material->short_name = '6x6 Post Cap';
                $material->price_cad = 1.49;
                $material->unit = 'each';
                $material->image_url = 'https://images.homedepot.ca/productimages/p_1001898921.jpg';
                $material->product_url = 'https://www.homedepot.ca/product/1001898921';
                $material->store_sku = '1001898921';
                $material->last_scrape_status = Material::SCRAPE_OK;
                $material->save();

                return [
                    'status' => Material::SCRAPE_OK,
                    'error' => null,
                    'parsed' => null,
                    'old_price' => 0.0,
                ];
            });

        $response = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ]);

        $response->assertOk();
        $response->assertJsonPath('created', true);
        $response->assertJsonPath('material.sku', '1001898921');
        $response->assertJsonPath('material.name', '6x6 Post Cap');
        $this->assertSame('1.49', $response->json('material.price'));
        $this->assertNotEmpty($response->json('material.id'));

        $material = Material::query()->where('store_sku', '1001898921')->first();
        $this->assertNotNull($material);
        $this->assertSame('6x6 Post Cap', $material->displayName());
        $this->assertEqualsWithDelta(1.49, (float) $material->price_cad, 0.001);
        $this->assertSame('each', $material->unit);
        $this->assertSame('https://www.homedepot.ca/product/1001898921', $material->product_url);
        $this->assertTrue((bool) $material->is_active);
    }

    public function test_existing_sku_returns_existing_without_syncing(): void
    {
        $this->actingAsAdmin();

        $existing = Material::query()->create([
            'name' => '6 in. x 6 in. Black Vinyl Fence Post Cap',
            'short_name' => '6x6 Post Cap',
            'store_sku' => '1001898921',
            'price_cad' => 1.49,
            'unit' => 'each',
            'product_url' => 'https://www.homedepot.ca/product/1001898921',
            'is_active' => true,
            'vendor_id' => MaterialVendor::homeDepotId(),
        ]);

        $this->mockSyncService()->shouldNotReceive('syncOne');

        $response = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => 'https://www.homedepot.ca/product/example/1001898921',
        ]);

        $response->assertOk();
        $response->assertJsonPath('created', false);
        $response->assertJsonPath('material.id', $existing->id);
        $response->assertJsonPath('material.sku', '1001898921');
        $this->assertSame(1, Material::query()->where('store_sku', '1001898921')->count());
    }

    public function test_home_depot_failure_returns_clean_error_and_does_not_leave_stub(): void
    {
        $this->actingAsAdmin();

        $this->mockSyncService()
            ->shouldReceive('syncOne')
            ->once()
            ->andReturn([
                'status' => Material::SCRAPE_FAILED,
                'error' => 'Could not extract name and price from page.',
                'parsed' => null,
                'old_price' => 0.0,
            ]);

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', "Couldn't find that SKU on Home Depot");

        $this->assertNull(Material::query()->where('store_sku', '1001898921')->first());
    }

    public function test_fence_config_catalog_picker_exposes_home_depot_import(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.fence.config'))
            ->assertOk()
            ->assertSee('data-hd-import-url="'.route('admin.materials.import-from-home-depot').'"', false);
    }

    private function mockSyncService()
    {
        $syncService = Mockery::mock(MaterialPriceSyncService::class);
        $this->app->instance(MaterialPriceSyncService::class, $syncService);

        return $syncService;
    }
}

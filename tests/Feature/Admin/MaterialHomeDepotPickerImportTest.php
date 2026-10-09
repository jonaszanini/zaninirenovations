<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\MaterialVendor;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use App\Services\MaterialScraping\MaterialCatalogHomeDepotPullService;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\MaterialSkuImportService;
use App\Services\MaterialScraping\ParsedProductData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        ])->assertUnauthorized();
    }

    public function test_rejects_invalid_sku_format(): void
    {
        $this->actingAsAdmin();

        $this->mockSyncService()->shouldNotReceive('fetchHomeDepotSku');

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => 'not-a-sku',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', MaterialCatalogHomeDepotPullService::MSG_INVALID);
    }

    public function test_new_sku_creates_material_and_returns_picker_option(): void
    {
        $this->actingAsAdmin();

        $this->mockSuccessfulFetch('1001898921');

        $response = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ]);

        $response->assertOk();
        $response->assertJsonPath('created', true);
        $response->assertJsonPath('material.sku', '1001898921');
        $response->assertJsonPath('material.name', '6 in. x 6 in. Black Vinyl Fence Post Cap');
        $this->assertSame('1.49', $response->json('material.price'));
        $this->assertNotEmpty($response->json('material.id'));

        $material = Material::query()->where('store_sku', '1001898921')->first();
        $this->assertNotNull($material);
        $this->assertSame('6 in. x 6 in. Black Vinyl Fence Post Cap', $material->name);
        $this->assertEqualsWithDelta(1.49, (float) $material->price_cad, 0.001);
        $this->assertSame('each', $material->unit);
        $this->assertSame('https://www.homedepot.ca/product/1001898921', $material->product_url);
        $this->assertTrue((bool) $material->is_active);
    }

    public function test_existing_sku_returns_existing_without_fetching(): void
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

        $this->mockSyncService()->shouldNotReceive('fetchHomeDepotSku');

        $response = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => 'https://www.homedepot.ca/product/example/1001898921',
        ]);

        $response->assertOk();
        $response->assertJsonPath('created', false);
        $response->assertJsonPath('material.id', $existing->id);
        $response->assertJsonPath('material.sku', '1001898921');
        $this->assertSame(1, Material::query()->where('store_sku', '1001898921')->count());
    }

    public function test_same_sku_pulled_twice_creates_one_row(): void
    {
        $this->actingAsAdmin();

        $this->mockSuccessfulFetch('1001898921');

        $first = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ]);
        $first->assertOk();
        $first->assertJsonPath('created', true);

        $this->mockSyncService()->shouldNotReceive('fetchHomeDepotSku');

        $second = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ]);
        $second->assertOk();
        $second->assertJsonPath('created', false);
        $second->assertJsonPath('material.id', $first->json('material.id'));
        $this->assertSame(1, Material::query()->where('store_sku', '1001898921')->count());
    }

    public function test_home_depot_failure_returns_clean_error_and_does_not_leave_stub(): void
    {
        $this->actingAsAdmin();

        $this->mockSyncService()
            ->shouldReceive('fetchHomeDepotSku')
            ->once()
            ->andReturn([
                'status' => Material::SCRAPE_FAILED,
                'error' => 'Could not extract name and price from page.',
                'parsed' => null,
                'unreachable' => false,
            ]);

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', MaterialCatalogHomeDepotPullService::MSG_NOT_FOUND);

        $this->assertNull(Material::query()->where('store_sku', '1001898921')->first());
        $this->assertSame(0, Material::query()->where('name', 'HD 1001898921')->count());
    }

    public function test_timeout_returns_distinct_unreachable_message_and_leaves_no_stub(): void
    {
        $this->actingAsAdmin();

        $this->mockSyncService()
            ->shouldReceive('fetchHomeDepotSku')
            ->once()
            ->andReturn([
                'status' => Material::SCRAPE_FAILED,
                'error' => 'cURL error 28: Operation timed out after 30000 milliseconds',
                'parsed' => null,
                'unreachable' => true,
            ]);

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', MaterialCatalogHomeDepotPullService::MSG_UNREACHABLE);

        $this->assertNull(Material::query()->where('store_sku', '1001898921')->first());
    }

    public function test_failed_pull_leaves_existing_not_ready_material_untouched(): void
    {
        $this->actingAsAdmin();

        $existing = Material::query()->create([
            'name' => 'HD 1001898921',
            'store_sku' => '1001898921',
            'price_cad' => 0,
            'product_url' => 'https://www.homedepot.ca/product/old-path/1001898921',
            'image_url' => 'https://images.homedepot.ca/productimages/p_old.jpg',
            'is_active' => true,
            'vendor_id' => MaterialVendor::homeDepotId(),
            'last_scrape_status' => Material::SCRAPE_FAILED,
            'last_scrape_error' => 'previous',
        ]);

        $this->mockSyncService()
            ->shouldReceive('fetchHomeDepotSku')
            ->once()
            ->andReturn([
                'status' => Material::SCRAPE_FAILED,
                'error' => 'Could not extract name and price from page.',
                'parsed' => null,
                'unreachable' => false,
            ]);

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', MaterialCatalogHomeDepotPullService::MSG_NOT_FOUND);

        $existing->refresh();
        $this->assertSame('HD 1001898921', $existing->name);
        $this->assertEqualsWithDelta(0.0, (float) $existing->price_cad, 0.001);
        $this->assertSame('https://www.homedepot.ca/product/old-path/1001898921', $existing->product_url);
        $this->assertSame('https://images.homedepot.ca/productimages/p_old.jpg', $existing->image_url);
        $this->assertTrue((bool) $existing->is_active);
        $this->assertSame(Material::SCRAPE_FAILED, $existing->last_scrape_status);
        $this->assertSame('previous', $existing->last_scrape_error);
    }

    public function test_failed_pull_leaves_existing_inactive_material_untouched(): void
    {
        $this->actingAsAdmin();

        $existing = Material::query()->create([
            'name' => 'Retired post cap',
            'store_sku' => '1001898921',
            'price_cad' => 1.49,
            'product_url' => 'https://www.homedepot.ca/product/1001898921',
            'is_active' => false,
            'vendor_id' => MaterialVendor::homeDepotId(),
        ]);

        $this->mockSyncService()->shouldNotReceive('fetchHomeDepotSku');

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', MaterialCatalogHomeDepotPullService::MSG_INACTIVE)
            ->assertJsonPath('material.id', $existing->id);

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_active);
        $this->assertSame('Retired post cap', $existing->name);
        $this->assertEqualsWithDelta(1.49, (float) $existing->price_cad, 0.001);
        $this->assertSame(1, Material::query()->where('store_sku', '1001898921')->count());
    }

    public function test_store_sku_is_normalized_on_save_and_unique(): void
    {
        Material::query()->create([
            'name' => 'Cap A',
            'store_sku' => ' 100-189-8921 ',
            'price_cad' => 1.49,
            'is_active' => true,
        ]);

        $saved = Material::query()->where('name', 'Cap A')->first();
        $this->assertSame('1001898921', $saved?->store_sku);

        $this->expectException(QueryException::class);
        Material::query()->create([
            'name' => 'Cap B',
            'store_sku' => '1001898921',
            'price_cad' => 2.00,
            'is_active' => true,
        ]);
    }

    public function test_unique_index_exists_on_store_sku(): void
    {
        $rows = DB::select('SHOW INDEX FROM materials WHERE Key_name = ?', ['materials_store_sku_unique']);
        $this->assertNotEmpty($rows);
        $this->assertTrue((bool) ($rows[0]->Non_unique === 0 || $rows[0]->Non_unique === '0'));
    }

    public function test_bulk_import_sku_matches_product_url_sets_store_sku_and_does_not_reactivate(): void
    {
        $existing = Material::query()->create([
            'name' => 'Existing from URL',
            'store_sku' => null,
            'price_cad' => 4.25,
            'product_url' => 'https://www.homedepot.ca/product/example/1001898921',
            'image_url' => 'https://images.homedepot.ca/productimages/p_kept.jpg',
            'is_active' => false,
        ]);

        $result = app(MaterialSkuImportService::class)->importSku('1001898921');

        $existing->refresh();
        $this->assertSame($existing->id, $result['material_id']);
        $this->assertSame('updated', $result['action']);
        $this->assertSame('1001898921', $existing->store_sku);
        $this->assertFalse((bool) $existing->is_active);
        $this->assertSame('https://www.homedepot.ca/product/example/1001898921', $existing->product_url);
        $this->assertSame('https://images.homedepot.ca/productimages/p_kept.jpg', $existing->image_url);
        $this->assertSame(1, Material::query()->where('store_sku', '1001898921')->count());
    }

    public function test_fence_config_catalog_picker_exposes_home_depot_import(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.fence.config'))
            ->assertOk()
            ->assertSee('data-hd-import-url="'.route('admin.materials.import-from-home-depot').'"', false);
    }

    private function mockSuccessfulFetch(string $sku): void
    {
        $this->mockSyncService()
            ->shouldReceive('fetchHomeDepotSku')
            ->once()
            ->with(Mockery::on(fn ($value) => HomeDepotCaUrls::normalizeStoredSku($value) === $sku))
            ->andReturn([
                'status' => Material::SCRAPE_OK,
                'error' => null,
                'unreachable' => false,
                'parsed' => new ParsedProductData(
                    name: '6 in. x 6 in. Black Vinyl Fence Post Cap',
                    sku: $sku,
                    priceCad: 1.49,
                    imageUrl: 'https://images.homedepot.ca/productimages/p_'.$sku.'.jpg',
                    productUrl: HomeDepotCaUrls::productUrl($sku),
                    unit: 'each',
                    source: 'fbt-local',
                ),
            ]);
    }

    private function mockSyncService()
    {
        $syncService = Mockery::mock(MaterialPriceSyncService::class);
        $this->app->instance(MaterialPriceSyncService::class, $syncService);

        return $syncService;
    }
}

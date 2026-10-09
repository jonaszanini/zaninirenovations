<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\MaterialVendor;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use App\Services\MaterialScraping\MaterialCatalogHomeDepotPullService;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\MaterialSkuImportService;
use App\Services\MaterialScraping\HomeDepotCaProductParser;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\MaterialPageFetcher;
use App\Services\MaterialScraping\AmazonCaSerpApiProductFetcher;
use App\Services\MaterialMetadataPopulationService;
use App\Services\MaterialScraping\ParsedProductData;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
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

    public function test_store_sku_is_normalized_on_save(): void
    {
        Material::query()->create([
            'name' => 'Cap A',
            'store_sku' => 'SKU 100-189-8921',
            'price_cad' => 1.49,
            'is_active' => true,
        ]);

        $saved = Material::query()->where('name', 'Cap A')->first();
        $this->assertSame('1001898921', $saved?->store_sku);
    }

    public function test_admin_rejects_duplicate_normalised_hd_sku_variants(): void
    {
        $this->actingAsAdmin();

        Material::query()->create([
            'name' => 'Cap A',
            'store_sku' => '1001898921',
            'price_cad' => 1.49,
            'is_active' => true,
        ]);

        $this->from(route('admin.materials.create'))
            ->post(route('admin.materials.store'), [
                'name' => 'Cap B',
                'store_sku' => 'SKU 100-189-8921',
                'price_cad' => '2.00',
                'is_active' => '1',
                'sort_order' => '0',
            ])
            ->assertRedirect(route('admin.materials.create'))
            ->assertSessionHasErrors('store_sku');

        $this->assertSame(0, Material::query()->where('name', 'Cap B')->count());
    }

    public function test_admin_allows_editing_material_that_already_has_the_sku(): void
    {
        $this->actingAsAdmin();

        $existing = Material::query()->create([
            'name' => 'Cap A',
            'store_sku' => '1001898921',
            'price_cad' => 1.49,
            'is_active' => true,
        ]);

        $this->put(route('admin.materials.update', $existing), [
            'name' => 'Cap A updated',
            'store_sku' => '100-189-8921',
            'price_cad' => '1.49',
            'is_active' => '1',
            'sort_order' => '0',
        ])->assertRedirect(route('admin.materials.edit', $existing));

        $existing->refresh();
        $this->assertSame('Cap A updated', $existing->name);
        $this->assertSame('1001898921', $existing->store_sku);
    }

    public function test_lock_timeout_returns_busy_without_500(): void
    {
        $this->actingAsAdmin();

        $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
        Cache::shouldReceive('lock')->once()->andReturn($lock);

        $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', MaterialCatalogHomeDepotPullService::MSG_BUSY);

        $this->assertNull(Material::query()->where('store_sku', '1001898921')->first());
    }

    public function test_sync_does_not_steal_another_rows_store_sku(): void
    {
        $homeDepotId = MaterialVendor::homeDepotId();
        $owner = Material::query()->create([
            'name' => 'Owner cap',
            'store_sku' => '1001898921',
            'price_cad' => 1.49,
            'product_url' => 'https://www.homedepot.ca/product/1001898921',
            'is_active' => true,
            'vendor_id' => $homeDepotId,
        ]);
        $target = Material::query()->create([
            'name' => 'Other item',
            'store_sku' => '1000000001',
            'price_cad' => 2.00,
            'product_url' => 'https://www.homedepot.ca/product/1000000001',
            'is_active' => true,
            'vendor_id' => $homeDepotId,
        ]);

        $parsed = new ParsedProductData(
            name: 'Hijacked title',
            sku: '1001898921',
            priceCad: 8.88,
            productUrl: (string) $target->product_url,
            source: 'serpapi-hd',
        );

        $localized = Mockery::mock(HomeDepotCaLocalizedPriceFetcher::class);
        $localized->shouldReceive('configuredStoreId')->andReturn('7263');
        $localized->shouldReceive('tryFetch')->andReturn($parsed);

        $population = Mockery::mock(MaterialMetadataPopulationService::class);
        $population->shouldReceive('applyToMaterial')->andReturnFalse();

        $service = new MaterialPriceSyncService(
            Mockery::mock(MaterialPageFetcher::class),
            new HomeDepotCaProductParser($localized),
            $localized,
            Mockery::mock(AmazonCaSerpApiProductFetcher::class),
            $population,
        );

        $result = $service->syncOne($target);
        $this->assertSame(Material::SCRAPE_OK, $result['status']);

        $target->refresh();
        $owner->refresh();
        $this->assertSame('1000000001', $target->store_sku);
        $this->assertSame('1001898921', $owner->store_sku);
        $this->assertEqualsWithDelta(8.88, (float) $target->price_cad, 0.001);
        $this->assertSame('Hijacked title', $target->name);
    }

    public function test_bulk_import_skips_inactive_instead_of_updating(): void
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
        $this->assertSame('skipped', $result['action']);
        $this->assertSame('inactive', $result['reason']);
        $this->assertSame($existing->id, $result['material_id']);
        $this->assertNull($existing->store_sku);
        $this->assertFalse((bool) $existing->is_active);
        $this->assertSame('https://www.homedepot.ca/product/example/1001898921', $existing->product_url);
    }

    public function test_bulk_import_sku_matches_product_url_and_sets_store_sku_on_active_row(): void
    {
        $existing = Material::query()->create([
            'name' => 'Existing from URL',
            'store_sku' => null,
            'price_cad' => 4.25,
            'product_url' => 'https://www.homedepot.ca/product/example/1001898921',
            'is_active' => true,
        ]);

        $result = app(MaterialSkuImportService::class)->importSku('1001898921');

        $existing->refresh();
        $this->assertSame('updated', $result['action']);
        $this->assertSame('1001898921', $existing->store_sku);
        $this->assertTrue((bool) $existing->is_active);
    }

    public function test_pull_finds_existing_formatted_store_sku(): void
    {
        $this->actingAsAdmin();

        $existing = Material::query()->create([
            'name' => 'Legacy formatted SKU',
            'short_name' => 'Legacy cap',
            'store_sku' => '1001898921',
            'price_cad' => 1.49,
            'is_active' => true,
        ]);
        Material::query()->where('id', $existing->id)->update(['store_sku' => 'SKU 100-189-8921']);

        $this->mockSyncService()->shouldNotReceive('fetchHomeDepotSku');

        $response = $this->postJson(route('admin.materials.import-from-home-depot'), [
            'query' => '1001898921',
        ]);

        $response->assertOk();
        $response->assertJsonPath('created', false);
        $response->assertJsonPath('material.id', $existing->id);
        $this->assertSame(1, Material::query()->where('id', $existing->id)->count());
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

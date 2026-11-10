<?php

namespace Tests\Feature\Admin;

use App\Models\BasementSettings;
use App\Models\Material;
use App\Models\MaterialVendor;
use App\Models\SharedWork;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\SharedWorkCatalog;
use Mockery;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementMaterialPriceSyncTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_basement_price_configuration_exposes_update_button(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->assertSee('Update basement material prices', false)
            ->assertSee(route('admin.quotations.basement.materials.sync.start'), false);
    }

    public function test_shared_prices_exposes_basement_scoped_update_button(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('Update basement material prices', false)
            ->assertSee('data-bsm-material-sync', false);
    }

    public function test_sync_start_excludes_non_basement_linked_materials(): void
    {
        $this->actingAsAdmin();

        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId === null) {
            $this->markTestSkipped('Home Depot vendor is not seeded.');
        }

        $basementMat = Material::create([
            'name' => 'Basement sync HD',
            'price_cad' => 10,
            'is_active' => true,
            'vendor_id' => $homeDepotId,
            'store_sku' => '1000790081',
            'product_url' => 'https://www.homedepot.ca/product/1000790081',
        ]);
        $otherMat = Material::create([
            'name' => 'Trim only sync HD',
            'price_cad' => 11,
            'is_active' => true,
            'vendor_id' => $homeDepotId,
            'store_sku' => '1000790082',
            'product_url' => 'https://www.homedepot.ca/product/1000790082',
        ]);

        $basementWork = SharedWork::query()->where('key', 'underlayment')->firstOrFail();
        $basementWork->catalog_material_id = $basementMat->id;
        $basementWork->consumers = ['basement'];
        $basementWork->save();

        $trimWork = SharedWork::query()->where('key', 'casing_lf')->firstOrFail();
        $trimWork->catalog_material_id = $otherMat->id;
        $trimWork->consumers = ['trimming'];
        $trimWork->save();

        BasementSettings::current()->update(['materials' => BasementSettings::defaultMaterials()]);
        SharedWorkCatalog::forgetCache();

        $syncService = Mockery::mock(MaterialPriceSyncService::class);
        $syncService->shouldReceive('syncOne')
            ->once()
            ->with(Mockery::on(fn ($m) => (int) $m->id === (int) $basementMat->id))
            ->andReturn([
                'status' => Material::SCRAPE_OK,
                'error' => null,
                'old_price' => 10.0,
            ]);
        $this->app->instance(MaterialPriceSyncService::class, $syncService);

        $start = $this->postJson(route('admin.quotations.basement.materials.sync.start'));
        $start->assertOk();
        $start->assertJsonPath('phase_total', 1);

        $token = $start->json('token');
        $step = $this->postJson(route('admin.quotations.basement.materials.sync.step'), ['token' => $token]);
        $step->assertOk();
        $step->assertJsonPath('done', true);
        $step->assertJsonPath('stats.unchanged', 1);
        $step->assertJsonPath('stats.updated', 0);
    }

    public function test_success_step_updates_last_price_synced_timestamp(): void
    {
        $this->actingAsAdmin();

        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId === null) {
            $this->markTestSkipped('Home Depot vendor is not seeded.');
        }

        SharedWork::query()->update(['catalog_material_id' => null]);

        $catalog = Material::create([
            'name' => 'Basement timestamp mat',
            'price_cad' => 5,
            'is_active' => true,
            'vendor_id' => $homeDepotId,
            'store_sku' => '1000790083',
            'product_url' => 'https://www.homedepot.ca/product/1000790083',
            'last_price_synced_at' => null,
        ]);

        $work = SharedWork::query()->where('key', 'underlayment')->firstOrFail();
        $work->catalog_material_id = $catalog->id;
        $work->consumers = ['basement'];
        $work->save();
        SharedWorkCatalog::forgetCache();

        $syncService = Mockery::mock(MaterialPriceSyncService::class);
        $syncService->shouldReceive('syncOne')
            ->once()
            ->andReturnUsing(function (Material $material) {
                $material->price_cad = 6.5;
                $material->last_price_synced_at = now();
                $material->last_scrape_status = Material::SCRAPE_OK;
                $material->save();

                return [
                    'status' => Material::SCRAPE_OK,
                    'error' => null,
                    'old_price' => 5.0,
                ];
            });
        $this->app->instance(MaterialPriceSyncService::class, $syncService);

        $start = $this->postJson(route('admin.quotations.basement.materials.sync.start'));
        $token = $start->json('token');

        $this->postJson(route('admin.quotations.basement.materials.sync.step'), ['token' => $token])
            ->assertJsonPath('stats.updated', 1);

        $catalog->refresh();
        $this->assertNotNull($catalog->last_price_synced_at);
    }

    public function test_failed_step_does_not_set_last_price_synced_timestamp(): void
    {
        $this->actingAsAdmin();

        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId === null) {
            $this->markTestSkipped('Home Depot vendor is not seeded.');
        }

        SharedWork::query()->update(['catalog_material_id' => null]);

        $failMat = Material::create([
            'name' => 'Basement fail mat',
            'price_cad' => 7,
            'is_active' => true,
            'vendor_id' => $homeDepotId,
            'store_sku' => '1000790084',
            'product_url' => 'https://www.homedepot.ca/product/1000790084',
            'last_price_synced_at' => null,
        ]);

        $work = SharedWork::query()->where('key', 'subfloor')->firstOrFail();
        $work->catalog_material_id = $failMat->id;
        $work->consumers = ['basement'];
        $work->save();
        SharedWorkCatalog::forgetCache();

        $syncService = Mockery::mock(MaterialPriceSyncService::class);
        $syncService->shouldReceive('syncOne')
            ->once()
            ->andReturn([
                'status' => Material::SCRAPE_FAILED,
                'error' => 'network',
                'old_price' => 7.0,
            ]);
        $this->app->instance(MaterialPriceSyncService::class, $syncService);

        $start = $this->postJson(route('admin.quotations.basement.materials.sync.start'));
        $this->postJson(route('admin.quotations.basement.materials.sync.step'), [
            'token' => $start->json('token'),
        ])->assertJsonPath('stats.failed', 1);

        $failMat->refresh();
        $this->assertNull($failMat->last_price_synced_at);
    }
}

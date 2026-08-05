<?php

namespace Tests\Feature\Admin;

use App\Models\FenceMaterial;
use App\Models\Material;
use App\Models\MaterialVendor;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Mockery;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FenceConfigPriceSyncTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fence_config_page_exposes_sync_prices_button(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.quotations.fence.config'));

        $response->assertOk();
        $response->assertSee('Sync fence prices', false);
        $response->assertSee(route('admin.quotations.fence.config.materials.sync.start'), false);
        $response->assertSee(route('admin.quotations.fence.config.materials.sync.step'), false);
    }

    public function test_fence_sync_start_returns_422_when_no_syncable_links(): void
    {
        $this->actingAsAdmin();

        FenceMaterial::query()->update(['material_id' => null]);

        $response = $this->postJson(route('admin.quotations.fence.config.materials.sync.start'));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'No linked fence materials are eligible for price sync. Link each row to a Home Depot or Amazon catalog material, then save.',
        ]);
    }

    public function test_fence_sync_start_and_step_sync_linked_catalog_materials(): void
    {
        $this->actingAsAdmin();

        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId === null) {
            $this->markTestSkipped('Home Depot vendor is not seeded.');
        }

        FenceMaterial::query()->update(['material_id' => null]);

        $catalog = Material::create([
            'name' => 'Test Fence Post Sync',
            'price_cad' => 19.99,
            'is_active' => true,
            'vendor_id' => $homeDepotId,
            'store_sku' => '1000790080',
            'product_url' => 'https://www.homedepot.ca/product/1000790080',
        ]);

        $fenceRow = FenceMaterial::query()->where('key', FenceMaterial::KEY_POST_10)->firstOrFail();
        $fenceRow->material_id = $catalog->id;
        $fenceRow->save();

        $syncService = Mockery::mock(MaterialPriceSyncService::class);
        $syncService->shouldReceive('syncOne')
            ->once()
            ->with(Mockery::on(fn ($m) => (int) $m->id === (int) $catalog->id))
            ->andReturn([
                'status' => Material::SCRAPE_OK,
                'error' => null,
            ]);
        $this->app->instance(MaterialPriceSyncService::class, $syncService);

        $start = $this->postJson(route('admin.quotations.fence.config.materials.sync.start'));
        $start->assertOk();
        $start->assertJsonPath('done', false);
        $start->assertJsonPath('phase_total', 1);
        $token = $start->json('token');
        $this->assertNotEmpty($token);

        $step = $this->postJson(route('admin.quotations.fence.config.materials.sync.step'), [
            'token' => $token,
        ]);
        $step->assertOk();
        $step->assertJsonPath('done', true);
        $step->assertJsonPath('stats.ok', 1);
        $step->assertJsonPath('current', 1);
    }
}

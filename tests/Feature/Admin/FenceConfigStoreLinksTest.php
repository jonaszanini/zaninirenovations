<?php

namespace Tests\Feature\Admin;

use App\Models\FenceMaterial;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FenceConfigStoreLinksTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_fence_config_page_includes_store_links_for_linked_rows(): void
    {
        $this->actingAsAdmin();

        $linked = FenceMaterial::query()
            ->with('material')
            ->whereNotNull('material_id')
            ->orderBy('sort_order')
            ->get()
            ->first(fn (FenceMaterial $row) => filled($row->material?->storeProductUrl()));

        if ($linked === null || $linked->material === null) {
            $this->markTestSkipped('No linked fence material with a store URL in the database.');
        }

        $response = $this->get(route('admin.quotations.fence.config'));

        $response->assertOk();
        $response->assertSee('fq-catalog-picker__open', false);
        $response->assertSee('data-store-url="'.$linked->material->storeProductUrl().'"', false);
    }
}

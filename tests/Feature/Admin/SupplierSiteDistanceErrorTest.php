<?php

namespace Tests\Feature\Admin;

use App\Models\SupplierSite;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SupplierSiteDistanceErrorTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_refresh_distance_surfaces_configuration_error(): void
    {
        $this->actingAsAdmin();

        $site = SupplierSite::create([
            'name' => 'Test Supply',
            'url' => 'https://example.com',
            'address' => '123 Main St, Ottawa, ON',
        ]);

        config(['google_maps.server_api_key' => null]);

        $response = $this->postJson(route('admin.supplier-sites.refresh-distance', $site));

        $response->assertOk();
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('message', 'Travel estimates need GOOGLE_MAPS_SERVER_API_KEY in .env.');

        $site->refresh();
        $this->assertNull($site->distance_km);
        $this->assertStringContainsString('GOOGLE_MAPS_SERVER_API_KEY', (string) $site->distance_error);
        $this->assertStringContainsString('GOOGLE_MAPS_SERVER_API_KEY', $response->json('distance_label'));
    }
}

<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Http;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SupplierWebsiteImportTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_import_preview_blocks_private_url(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.supplier-sites.import-preview'), [
            'url' => 'http://127.0.0.1/admin',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => false]);
    }

    public function test_import_preview_fills_fields_from_faked_homepage(): void
    {
        $this->actingAsAdmin();

        $html = '<!doctype html><html><head>'
            .'<title>Raw Glass Ottawa</title>'
            .'<script type="application/ld+json">'
            .json_encode([
                '@type' => 'LocalBusiness',
                'name' => 'Raw Glass',
                'telephone' => '+1-613-555-0100',
                'email' => 'info@rawglass.test',
                'url' => 'https://httpbin.org/html',
            ], JSON_THROW_ON_ERROR)
            .'</script></head><body><p>Contact us</p></body></html>';

        Http::fake([
            'https://httpbin.org/*' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);

        $response = $this->postJson(route('admin.supplier-sites.import-preview'), [
            'url' => 'https://httpbin.org/html',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.name', 'Raw Glass');
        $response->assertJsonPath('data.phone', '(613) 555-0100');
        $response->assertJsonPath('data.email', 'info@rawglass.test');
    }
}

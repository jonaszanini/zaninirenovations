<?php

namespace Tests\Feature\Admin;

use App\Support\PinnedCurlAttemptResult;
use App\Support\SafeOutboundHttp;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SupplierWebsiteImportTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    private const FIXTURE_HOST = 'supplier-import-fixture.test';

    protected function tearDown(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(null);
        SafeOutboundHttp::setPinnedCurlTransportForTesting(null);
        parent::tearDown();
    }

    public function test_import_preview_blocks_private_url(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.supplier-sites.import-preview'), [
            'url' => 'http://127.0.0.1/admin',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => false]);
    }

    public function test_import_preview_fills_fields_from_stubbed_homepage(): void
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
                'url' => 'https://'.self::FIXTURE_HOST.'/',
            ], JSON_THROW_ON_ERROR)
            .'</script></head><body><p>Contact us</p></body></html>';

        SafeOutboundHttp::setHostIpResolverForTesting(fn (string $host) => $host === self::FIXTURE_HOST
            ? ['93.184.216.34']
            : []);

        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            fn () => new PinnedCurlAttemptResult(
                true,
                200,
                ['content-type' => ['text/html; charset=UTF-8']],
                $html
            )
        );

        $response = $this->postJson(route('admin.supplier-sites.import-preview'), [
            'url' => 'https://'.self::FIXTURE_HOST.'/',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.name', 'Raw Glass');
        $response->assertJsonPath('data.phone', '(613) 555-0100');
        $response->assertJsonPath('data.email', 'info@rawglass.test');
    }
}

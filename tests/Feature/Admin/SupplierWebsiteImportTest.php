<?php

namespace Tests\Feature\Admin;

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
}

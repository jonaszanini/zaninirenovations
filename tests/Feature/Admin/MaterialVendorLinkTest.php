<?php

namespace Tests\Feature\Admin;

use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class MaterialVendorLinkTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_amazon_vendor_link_redirects_to_amazon_ca(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.materials.vendor-link.amazon', ['asin' => 'B07VVMBFBV']));

        $response->assertRedirect('https://www.amazon.ca/dp/B07VVMBFBV');
    }

    public function test_amazon_vendor_link_rejects_invalid_asin(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.materials.vendor-link.amazon', ['asin' => '1000790080']))
            ->assertNotFound();
    }
}

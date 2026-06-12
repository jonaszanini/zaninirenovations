<?php

namespace Tests\Unit\Models;

use App\Models\Material;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use PHPUnit\Framework\TestCase;

class MaterialStoreProductUrlTest extends TestCase
{
    public function test_store_product_url_uses_asin_before_product_url(): void
    {
        $material = new Material([
            'store_sku' => 'B07VVMBFBV',
            'product_url' => 'https://www.homedepot.ca/product/example/1000790080',
        ]);

        $this->assertSame('https://www.amazon.ca/dp/B07VVMBFBV', $material->storeProductUrl());
        $this->assertSame('amazon', $material->storeLinkVendor());
    }

    public function test_store_product_url_uses_home_depot_sku_when_not_asin(): void
    {
        $material = new Material([
            'store_sku' => '1000790080',
            'product_url' => null,
        ]);

        $this->assertSame(HomeDepotCaUrls::productUrl('1000790080'), $material->storeProductUrl());
        $this->assertSame('home_depot', $material->storeLinkVendor());
    }
}

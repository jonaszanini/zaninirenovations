<?php

namespace Tests\Unit\Models;

use App\Models\FenceMaterial;
use App\Models\Material;
use Tests\TestCase;

class FenceMaterialTest extends TestCase
{
    public function test_effective_quantity_per_box_prefers_catalog_material(): void
    {
        $catalog = new Material([
            'name' => '2" Nails pack',
            'short_name' => '2" Nails',
            'quantity_per_package' => 1000,
            'price_cad' => 50.00,
        ]);

        $fenceMaterial = new FenceMaterial([
            'key' => FenceMaterial::KEY_NAILS,
            'quantity_per_box' => 3000,
            'price_cad' => 50.00,
        ]);
        $fenceMaterial->setRelation('material', $catalog);

        $this->assertSame(1000, $fenceMaterial->effectiveQuantityPerBox());
    }

    public function test_unit_price_divides_box_price_by_quantity(): void
    {
        $fenceMaterial = new FenceMaterial([
            'key' => FenceMaterial::KEY_NAILS,
            'quantity_per_box' => 1000,
            'price_cad' => 100.00,
        ]);

        $this->assertTrue($fenceMaterial->soldByBox());
        $this->assertEqualsWithDelta(0.1, $fenceMaterial->unitPrice(), 0.000001);
    }

    public function test_non_nails_materials_are_not_sold_by_box(): void
    {
        $fenceMaterial = new FenceMaterial([
            'key' => FenceMaterial::KEY_RAIL,
            'price_cad' => 7.25,
        ]);

        $this->assertFalse($fenceMaterial->soldByBox());
        $this->assertSame(7.25, $fenceMaterial->unitPrice());
    }

    public function test_effective_delivery_comes_from_linked_catalog_material(): void
    {
        $catalog = new Material([
            'name' => 'Quikrete 30kg',
            'price_cad' => 7.50,
            'delivery_cad' => 45.00,
        ]);
        $fenceMaterial = new FenceMaterial([
            'key' => FenceMaterial::KEY_CONCRETE,
            'price_cad' => 7.50,
        ]);
        $fenceMaterial->setRelation('material', $catalog);

        $this->assertEqualsWithDelta(45.0, $fenceMaterial->effectiveDeliveryCad(), 0.01);
        $this->assertSame(0.0, (new FenceMaterial(['key' => FenceMaterial::KEY_RAIL]))->effectiveDeliveryCad());
    }
}

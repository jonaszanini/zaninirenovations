<?php

namespace Tests\Unit\Services;

use App\Models\PavingMarbleType;
use App\Services\PavingMarbleCalculator;
use Tests\TestCase;

class PavingMarbleCalculatorTest extends TestCase
{
    public function test_bags_scale_with_depth_at_same_area(): void
    {
        $type = new PavingMarbleType([
            'name' => 'Test marble',
            'reference_depth_in' => 2,
            'sq_ft_per_bag_at_reference_depth' => 9,
            'price_per_bag' => 10,
        ]);

        $shallow = PavingMarbleCalculator::estimateBags($type, 50, 2, 0);
        $deep = PavingMarbleCalculator::estimateBags($type, 50, 4, 0);

        $this->assertGreaterThan($shallow, $deep);
    }

    public function test_bulk_discount_lowers_effective_price(): void
    {
        $type = new PavingMarbleType([
            'name' => 'Bulk marble',
            'reference_depth_in' => 2,
            'sq_ft_per_bag_at_reference_depth' => 1,
            'price_per_bag' => 10,
            'bulk_min_bags' => 5,
            'bulk_discount_pct' => 10,
        ]);

        $this->assertSame(10.0, $type->effectivePricePerBag(4));
        $this->assertSame(9.0, $type->effectivePricePerBag(5));
    }
}

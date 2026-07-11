<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PavingMarbleType extends Model
{
    protected $fillable = [
        'name',
        'product_url',
        'image_url',
        'stone_size_in',
        'reference_depth_in',
        'sq_ft_per_bag_at_reference_depth',
        'price_per_bag',
        'bulk_min_bags',
        'bulk_discount_pct',
        'delivery_cost',
        'additional_cost',
        'additional_cost_description',
        'sort_order',
    ];

    protected $casts = [
        'stone_size_in' => 'decimal:2',
        'reference_depth_in' => 'decimal:2',
        'sq_ft_per_bag_at_reference_depth' => 'decimal:4',
        'price_per_bag' => 'decimal:2',
        'bulk_min_bags' => 'integer',
        'bulk_discount_pct' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'additional_cost' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function cuFtPerBag(): float
    {
        $sqFt = (float) ($this->sq_ft_per_bag_at_reference_depth ?? 0);
        $refDepth = max(0.01, (float) ($this->reference_depth_in ?? 2));

        return round($sqFt * ($refDepth / 12), 4);
    }

    public function effectivePricePerBag(int $bags): float
    {
        $price = (float) ($this->price_per_bag ?? 0);
        $min = (int) ($this->bulk_min_bags ?? 0);
        $pct = (float) ($this->bulk_discount_pct ?? 0);

        if ($bags >= $min && $min > 0 && $pct > 0) {
            return round($price * (1 - ($pct / 100)), 4);
        }

        return $price;
    }
}

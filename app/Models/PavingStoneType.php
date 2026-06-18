<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PavingStoneType extends Model
{
    protected $fillable = [
        'name',
        'product_url',
        'length_in',
        'width_in',
        'height_in',
        'sq_ft_per_stone',
        'price_per_stone',
        'price_per_sq_ft',
        'delivery_cost',
        'additional_cost',
        'additional_cost_description',
        'sort_order',
    ];

    protected $casts = [
        'length_in' => 'decimal:2',
        'width_in' => 'decimal:2',
        'height_in' => 'decimal:2',
        'sq_ft_per_stone' => 'decimal:4',
        'price_per_stone' => 'decimal:2',
        'price_per_sq_ft' => 'decimal:4',
        'delivery_cost' => 'decimal:2',
        'additional_cost' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /** Sq ft per stone from length × width (inches), when both are set. */
    public function sqFtFromDimensions(): ?float
    {
        if ($this->length_in === null || $this->width_in === null) {
            return null;
        }
        $length = (float) $this->length_in;
        $width = (float) $this->width_in;
        if ($length <= 0 || $width <= 0) {
            return null;
        }

        return round($length * $width / 144, 4);
    }
}

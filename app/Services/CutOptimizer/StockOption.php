<?php

namespace App\Services\CutOptimizer;

class StockOption
{
    public function __construct(
        public string $id,
        public string $label,
        public float $width,
        public float $length,
        public float $unitPrice = 0.0,
        public ?int $catalogMaterialId = null,
        public ?int $offcutId = null,
        public int $quantity = 1
    ) {
    }

    public function area(): float
    {
        return $this->width * $this->length;
    }
}

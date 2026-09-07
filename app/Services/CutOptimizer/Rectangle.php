<?php

namespace App\Services\CutOptimizer;

class Rectangle
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height
    ) {
    }

    public function area(): float
    {
        return $this->width * $this->height;
    }
}

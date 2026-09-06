<?php

namespace App\Services\CutOptimizer;

class PlacedPart
{
    public function __construct(
        public Part $part,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public bool $rotated
    ) {
    }
}

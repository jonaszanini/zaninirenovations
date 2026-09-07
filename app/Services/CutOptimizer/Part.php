<?php

namespace App\Services\CutOptimizer;

class Part
{
    public float $cuttingWidth;

    public float $cuttingLength;

    public function __construct(
        public string $id,
        public string $label,
        public float $width,
        public float $length,
        public int $quantity = 1,
        public string $grain = GrainDirection::ANY,
        public float $edgeBandTop = 0.0,
        public float $edgeBandBottom = 0.0,
        public float $edgeBandLeft = 0.0,
        public float $edgeBandRight = 0.0,
        public string $materialKey = 'default',
        public ?string $groupLabel = null
    ) {
        $this->cuttingWidth = max(0.01, $this->width - ($this->edgeBandLeft + $this->edgeBandRight));
        $this->cuttingLength = max(0.01, $this->length - ($this->edgeBandTop + $this->edgeBandBottom));
    }
}

<?php

namespace App\Services\CutOptimizer;

class GuillotineSheet
{
    public const EPS = 0.001;

    public float $usableWidth;

    public float $usableLength;

    /** @var list<Rectangle> */
    public array $freeRectangles = [];

    /** @var list<PlacedPart> */
    public array $placedParts = [];

    /** @var list<array<string, mixed>> */
    public array $cutLog = [];

    /** Inventory offcut this sheet was packed onto, if any. */
    public ?int $offcutId = null;

    public function __construct(
        public string $id,
        public float $width,
        public float $length,
        public float $kerf = 0.125,
        public float $trimTop = 0.0,
        public float $trimBottom = 0.0,
        public float $trimLeft = 0.0,
        public float $trimRight = 0.0,
        public float $minOffcut = 0.0,
        public string $label = ''
    ) {
        $this->usableWidth = max(0.0, $this->width - ($this->trimLeft + $this->trimRight));
        $this->usableLength = max(0.0, $this->length - ($this->trimTop + $this->trimBottom));

        $this->freeRectangles[] = new Rectangle(
            $this->trimLeft,
            $this->trimBottom,
            $this->usableWidth,
            $this->usableLength
        );
    }

    /**
     * Free rectangles large enough to keep as leftover inventory.
     *
     * @return list<Rectangle>
     */
    public function leftoverRectangles(float $minSize): array
    {
        $out = [];
        foreach ($this->freeRectangles as $rect) {
            if ($rect->width + self::EPS >= $minSize && $rect->height + self::EPS >= $minSize) {
                $out[] = $rect;
            }
        }

        return $out;
    }

    public function insert(Part $part, string $splitStrategy): bool
    {
        $bestIndex = null;
        $bestRotated = false;
        $bestShortSideFit = INF;
        $bestLongSideFit = INF;

        $targetW = $part->cuttingWidth;
        $targetL = $part->cuttingLength;

        foreach ($this->freeRectangles as $index => $free) {
            $normalRect = $this->placementRect($free, $targetW, $targetL);
            $rotatedRect = $this->placementRect($free, $targetL, $targetW);

            if ($part->grain === GrainDirection::LENGTHWISE) {
                $rotatedRect = null;
            } elseif ($part->grain === GrainDirection::CROSSWISE) {
                $normalRect = null;
            }

            if ($normalRect !== null) {
                $leftoverW = abs($normalRect->width - $targetW);
                $leftoverL = abs($normalRect->height - $targetL);
                $shortSide = min($leftoverW, $leftoverL);
                $longSide = max($leftoverW, $leftoverL);
                if ($shortSide < $bestShortSideFit - self::EPS
                    || (abs($shortSide - $bestShortSideFit) < self::EPS && $longSide < $bestLongSideFit - self::EPS)) {
                    $bestIndex = $index;
                    $bestRotated = false;
                    $bestShortSideFit = $shortSide;
                    $bestLongSideFit = $longSide;
                }
            }

            if ($rotatedRect !== null) {
                $leftoverW = abs($rotatedRect->width - $targetL);
                $leftoverL = abs($rotatedRect->height - $targetW);
                $shortSide = min($leftoverW, $leftoverL);
                $longSide = max($leftoverW, $leftoverL);
                if ($shortSide < $bestShortSideFit - self::EPS
                    || (abs($shortSide - $bestShortSideFit) < self::EPS && $longSide < $bestLongSideFit - self::EPS)) {
                    $bestIndex = $index;
                    $bestRotated = true;
                    $bestShortSideFit = $shortSide;
                    $bestLongSideFit = $longSide;
                }
            }
        }

        if ($bestIndex === null) {
            return false;
        }

        $free = $this->freeRectangles[$bestIndex];
        $placedWidth = $bestRotated ? $targetL : $targetW;
        $placedLength = $bestRotated ? $targetW : $targetL;
        $chosenRect = $this->placementRect($free, $placedWidth, $placedLength);
        if ($chosenRect === null) {
            return false;
        }
        array_splice($this->freeRectangles, $bestIndex, 1);

        $this->placedParts[] = new PlacedPart(
            $part,
            $chosenRect->x,
            $chosenRect->y,
            $placedWidth,
            $placedLength,
            $bestRotated
        );

        $this->splitFreeRectangle($chosenRect, $placedWidth, $placedLength, $splitStrategy, $part->label);

        return true;
    }

    public function usedArea(): float
    {
        $used = 0.0;
        foreach ($this->placedParts as $p) {
            $used += $p->width * $p->height;
        }

        return $used;
    }

    public function wastePercentage(): float
    {
        $sheetArea = $this->width * $this->length;
        if ($sheetArea <= 0) {
            return 0.0;
        }

        return round(max(0, 100.0 - ($this->usedArea() / $sheetArea * 100.0)), 1);
    }

    public function totalCutLength(): float
    {
        $len = 0.0;
        foreach ($this->cutLog as $cut) {
            $len += (float) ($cut['length'] ?? 0);
        }

        return round($len, 2);
    }

    /**
     * Free space after factory-edge trim, expanded into the trim bands when a
     * part is as long/wide as the stock (full-length rips along the 8' edge).
     */
    private function placementRect(Rectangle $free, float $w, float $h): ?Rectangle
    {
        $x = $free->x;
        $y = $free->y;
        $availW = $free->width;
        $availH = $free->height;

        if ($availW + self::EPS < $w) {
            $growLeft = 0.0;
            $growRight = 0.0;
            if ($free->x <= $this->trimLeft + self::EPS) {
                $growLeft = $free->x;
            }
            $freeRight = $free->x + $free->width;
            if ($freeRight >= $this->width - $this->trimRight - self::EPS) {
                $growRight = $this->width - $freeRight;
            }
            $x = $free->x - $growLeft;
            $availW = $free->width + $growLeft + $growRight;
        }

        if ($availH + self::EPS < $h) {
            $growBottom = 0.0;
            $growTop = 0.0;
            if ($free->y <= $this->trimBottom + self::EPS) {
                $growBottom = $free->y;
            }
            $freeTop = $free->y + $free->height;
            if ($freeTop >= $this->length - $this->trimTop - self::EPS) {
                $growTop = $this->length - $freeTop;
            }
            $y = $free->y - $growBottom;
            $availH = $free->height + $growBottom + $growTop;
        }

        if (($availW - $w) < -self::EPS || ($availH - $h) < -self::EPS) {
            return null;
        }

        return new Rectangle($x, $y, $availW, $availH);
    }

    private function splitFreeRectangle(
        Rectangle $free,
        float $w,
        float $h,
        string $strategy,
        string $partLabel
    ): void {
        $kerf = $this->kerf;

        if ($strategy === SplitHeuristic::HORIZONTAL_FIRST) {
            $rightW = $free->width - $w - $kerf;
            $rightH = $h;
            $topW = $free->width;
            $topH = $free->height - $h - $kerf;

            if (($free->width - $w) > self::EPS) {
                $this->logCut('rip', $free->x + $w, $free->y, $free->x + $w, $free->y + $h, $h, $partLabel, [
                    'surplus' => [round(max(0, $rightW), 3), round($rightH, 3)],
                ]);
            }
            if ($rightW > $this->minOffcut) {
                $this->addOffcut($free->x + $w + $kerf, $free->y, $rightW, $rightH);
            }
            if (($free->height - $h) > self::EPS) {
                $this->logCut('crosscut', $free->x, $free->y + $h, $free->x + $w, $free->y + $h, $w, $partLabel, [
                    'surplus' => [round($topW, 3), round(max(0, $topH), 3)],
                ]);
            }
            if ($topW > $this->minOffcut && $topH > $this->minOffcut) {
                $this->addOffcut($free->x, $free->y + $h + $kerf, $topW, $topH);
            }
        } else {
            $rightW = $free->width - $w - $kerf;
            $rightH = $free->height;
            $topW = $w;
            $topH = $free->height - $h - $kerf;

            if (($free->width - $w) > self::EPS) {
                $this->logCut('rip', $free->x + $w, $free->y, $free->x + $w, $free->y + $free->height, $free->height, $partLabel, [
                    'surplus' => [round(max(0, $rightW), 3), round($rightH, 3)],
                ]);
            }
            if ($rightW > $this->minOffcut && $rightH > $this->minOffcut) {
                $this->addOffcut($free->x + $w + $kerf, $free->y, $rightW, $rightH);
            }
            if (($free->height - $h) > self::EPS) {
                $this->logCut('crosscut', $free->x, $free->y + $h, $free->x + $w, $free->y + $h, $w, $partLabel, [
                    'surplus' => [round($topW, 3), round(max(0, $topH), 3)],
                ]);
            }
            if ($topH > $this->minOffcut) {
                $this->addOffcut($free->x, $free->y + $h + $kerf, $topW, $topH);
            }
        }
    }

    private function addOffcut(float $x, float $y, float $w, float $h): void
    {
        if ($w > self::EPS && $h > self::EPS) {
            $this->freeRectangles[] = new Rectangle($x, $y, $w, $h);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logCut(
        string $type,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $length,
        string $partLabel,
        array $extra = []
    ): void {
        $this->cutLog[] = array_merge([
            'step' => count($this->cutLog) + 1,
            'type' => $type,
            'position' => round($type === 'rip' ? $x1 : $y1, 3),
            'start' => ['x' => round($x1, 3), 'y' => round($y1, 3)],
            'end' => ['x' => round($x2, 3), 'y' => round($y2, 3)],
            'length' => round($length, 3),
            'part_label' => $partLabel,
        ], $extra);
    }
}

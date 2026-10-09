<?php

namespace App\Services\Millwork;

use App\Services\CutOptimizer\GrainDirection;
use App\Services\CutOptimizer\GuillotineSheet;
use App\Services\CutOptimizer\Part;
use App\Services\CutOptimizer\StockOption;

/**
 * Split oversized back panels into full-size pieces that still sum to the
 * original finished size. Kerf is not taken from the finished dimension —
 * the shop joins the panels at a stated seam.
 */
class MillworkPanelSplitter
{
    /**
     * @param  list<Part>  $parts
     * @param  list<StockOption>  $stocks
     * @return array{parts: list<Part>, seams: list<array<string, mixed>>}
     */
    public function splitOversizedBacks(array $parts, array $stocks): array
    {
        $largest = $this->largestStock($stocks);
        if ($largest === null) {
            return ['parts' => $parts, 'seams' => []];
        }

        $out = [];
        $seams = [];
        foreach ($parts as $part) {
            if ($part->materialKey !== 'back' || $this->fitsStock($part->width, $part->length, $largest, $part->grain)) {
                $out[] = $part;
                continue;
            }
            $split = $this->splitPart($part, $largest);
            if ($split === null) {
                $out[] = $part;
                continue;
            }
            foreach ($split['parts'] as $panel) {
                $out[] = $panel;
            }
            $seams[] = $split['seam'];
        }

        return ['parts' => $out, 'seams' => $seams];
    }

    /**
     * @param  list<StockOption>  $stocks
     */
    private function largestStock(array $stocks): ?StockOption
    {
        if ($stocks === []) {
            return null;
        }
        $best = $stocks[0];
        foreach ($stocks as $opt) {
            if ($opt->area() > $best->area() + GuillotineSheet::EPS) {
                $best = $opt;
            }
        }

        return $best;
    }

    /**
     * @return array{parts: list<Part>, seam: array<string, mixed>}|null
     */
    private function splitPart(Part $part, StockOption $stock): ?array
    {
        $attempts = [
            ['split' => 'width', 'overflow' => $part->width, 'keep' => $part->length, 'axis' => 'height'],
            ['split' => 'length', 'overflow' => $part->length, 'keep' => $part->width, 'axis' => 'width'],
        ];

        $best = null;
        foreach ($attempts as $attempt) {
            $maxPiece = $this->maxSplitSize((float) $attempt['keep'], (string) $attempt['split'], $stock, $part->grain);
            if ($maxPiece <= GuillotineSheet::EPS) {
                continue;
            }
            if ($attempt['overflow'] <= $maxPiece + GuillotineSheet::EPS) {
                continue;
            }
            $sizes = $this->chunkEven((float) $attempt['overflow'], $maxPiece);
            if (count($sizes) < 2) {
                continue;
            }
            $ok = true;
            foreach ($sizes as $size) {
                $w = $attempt['split'] === 'width' ? $size : $attempt['keep'];
                $l = $attempt['split'] === 'length' ? $size : $attempt['keep'];
                if (! $this->fitsStock($w, $l, $stock, $part->grain)) {
                    $ok = false;
                    break;
                }
            }
            if (! $ok) {
                continue;
            }
            $score = [count($sizes), -min($sizes)];
            if ($best === null || $score < $best['score']) {
                $best = ['attempt' => $attempt, 'sizes' => $sizes, 'score' => $score];
            }
        }

        if ($best === null) {
            return null;
        }

        $attempt = $best['attempt'];
        $sizes = $best['sizes'];
        $letters = range('A', 'Z');
        $panels = [];
        foreach ($sizes as $i => $size) {
            $letter = $letters[$i] ?? (string) ($i + 1);
            $w = $attempt['split'] === 'width' ? $size : $attempt['keep'];
            $l = $attempt['split'] === 'length' ? $size : $attempt['keep'];
            $panels[] = new Part(
                $part->id.'-'.$letter,
                $part->label.' '.$letter,
                $w,
                $l,
                max(1, $part->quantity),
                $part->grain,
                $part->edgeBandTop,
                $part->edgeBandBottom,
                $part->edgeBandLeft,
                $part->edgeBandRight,
                $part->materialKey,
                $part->groupLabel,
                $part->sourcePieceIndex
            );
        }

        $overflow = (float) $attempt['overflow'];
        $seamAt = (float) $sizes[0];
        $axis = (string) $attempt['axis'];
        $originalW = max($part->width, $part->length);
        $originalH = min($part->width, $part->length);

        return [
            'parts' => $panels,
            'seam' => [
                'part_id' => $part->id,
                'label' => $part->label,
                'original' => $this->sizeLabel($part->width, $part->length),
                'finished_width' => $originalW,
                'finished_height' => $originalH,
                'seam_at' => $seamAt,
                'along' => $overflow,
                'axis' => $axis,
                'note' => 'seam at '.$this->fmt($seamAt).' along '.$this->fmt($overflow).' '.$axis,
                'panels' => array_map(fn (Part $p) => $p->label, $panels),
                'panel_sizes' => array_map(
                    fn (Part $p) => $this->sizeLabel($p->width, $p->length),
                    $panels
                ),
            ],
        ];
    }

    private function maxSplitSize(float $keep, string $split, StockOption $stock, string $grain): float
    {
        $best = 0.0;
        foreach ([$stock->width, $stock->length] as $size) {
            $w = $split === 'width' ? $size : $keep;
            $l = $split === 'length' ? $size : $keep;
            if ($this->fitsStock($w, $l, $stock, $grain)) {
                $best = max($best, $size);
            }
        }

        return $best;
    }

    /**
     * Split into the fewest equal panels that each fit on the stock.
     * 100" on a 96" sheet becomes 50+50, not 48+48+4.
     *
     * @return list<float>
     */
    private function chunkEven(float $total, float $maxPiece): array
    {
        if ($maxPiece <= GuillotineSheet::EPS || $total <= GuillotineSheet::EPS) {
            return [$total];
        }
        if ($total <= $maxPiece + GuillotineSheet::EPS) {
            return [$total];
        }
        $n = max(2, (int) ceil($total / $maxPiece - 1e-9));
        $each = $total / $n;
        while ($each > $maxPiece + GuillotineSheet::EPS) {
            $n++;
            $each = $total / $n;
        }
        $sizes = array_fill(0, $n, round($each, 4));
        $sizes[$n - 1] += $total - array_sum($sizes);

        return $sizes;
    }

    private function fitsStock(float $width, float $length, StockOption $stock, string $grain): bool
    {
        $normal = $width <= $stock->width + GuillotineSheet::EPS
            && $length <= $stock->length + GuillotineSheet::EPS;
        if ($normal) {
            return true;
        }
        if ($grain === GrainDirection::LENGTHWISE) {
            return false;
        }

        return $length <= $stock->width + GuillotineSheet::EPS
            && $width <= $stock->length + GuillotineSheet::EPS;
    }

    private function fmt(float $inches): string
    {
        return rtrim(rtrim(number_format($inches, 2, '.', ''), '0'), '.').'"';
    }

    private function sizeLabel(float $a, float $b): string
    {
        return $this->fmt(max($a, $b)).'×'.$this->fmt(min($a, $b));
    }
}

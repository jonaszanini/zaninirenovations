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
            ['split' => 'length', 'overflow' => $part->length, 'keep' => $part->width, 'chunk' => $stock->width],
            ['split' => 'length', 'overflow' => $part->length, 'keep' => $part->width, 'chunk' => $stock->length],
            ['split' => 'width', 'overflow' => $part->width, 'keep' => $part->length, 'chunk' => $stock->width],
            ['split' => 'width', 'overflow' => $part->width, 'keep' => $part->length, 'chunk' => $stock->length],
        ];

        foreach ($attempts as $attempt) {
            if ($attempt['overflow'] <= $attempt['chunk'] + GuillotineSheet::EPS) {
                continue;
            }
            $sizes = $this->chunkExact((float) $attempt['overflow'], (float) $attempt['chunk']);
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
                    $part->groupLabel
                );
            }

            $overflow = (float) $attempt['overflow'];
            $chunk = (float) $attempt['chunk'];
            $originalW = max($part->width, $part->length);
            $originalH = min($part->width, $part->length);

            return [
                'parts' => $panels,
                'seam' => [
                    'part_id' => $part->id,
                    'label' => $part->label,
                    'original' => $this->fmt($part->width).'×'.$this->fmt($part->length),
                    'finished_width' => $originalW,
                    'finished_height' => $originalH,
                    'seam_at' => $chunk,
                    'along' => $overflow,
                    'note' => 'seam at '.$this->fmt($chunk).' along '.$this->fmt($overflow).' width',
                    'panels' => array_map(fn (Part $p) => $p->label, $panels),
                    'panel_sizes' => array_map(
                        fn (Part $p) => $this->fmt($p->width).'×'.$this->fmt($p->length),
                        $panels
                    ),
                ],
            ];
        }

        return null;
    }

    /**
     * @return list<float>
     */
    private function chunkExact(float $total, float $chunk): array
    {
        if ($chunk <= GuillotineSheet::EPS || $total <= GuillotineSheet::EPS) {
            return [$total];
        }
        $sizes = [];
        $left = $total;
        while ($left > GuillotineSheet::EPS) {
            $take = min($chunk, $left);
            $sizes[] = $take;
            $left -= $take;
        }
        if ($sizes === []) {
            return [$total];
        }
        $drift = $total - array_sum($sizes);
        $sizes[count($sizes) - 1] += $drift;

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
}

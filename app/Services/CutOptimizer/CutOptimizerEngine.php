<?php

namespace App\Services\CutOptimizer;

class CutOptimizerEngine
{
    /**
     * @param  list<Part>  $parts
     * @param  list<StockOption>  $stockOptions  largest-first preferred; first is the default full sheet
     * @return list<GuillotineSheet>
     */
    public function optimize(
        array $parts,
        array $stockOptions,
        float $kerf = 0.125,
        float $trim = 0.125,
        float $minOffcut = 2.0
    ): array {
        $flat = [];
        foreach ($parts as $part) {
            $qty = max(1, (int) $part->quantity);
            for ($i = 0; $i < $qty; $i++) {
                $clone = new Part(
                    $part->id.'#'.($i + 1),
                    $qty > 1 ? $part->label.' '.($i + 1) : $part->label,
                    $part->width,
                    $part->length,
                    1,
                    $part->grain,
                    $part->edgeBandTop,
                    $part->edgeBandBottom,
                    $part->edgeBandLeft,
                    $part->edgeBandRight,
                    $part->materialKey,
                    $part->groupLabel
                );
                $flat[] = $clone;
            }
        }

        if ($flat === [] || $stockOptions === []) {
            return [];
        }

        $default = $this->largestStock($stockOptions);
        $packed = $this->bestPack($flat, $default, $kerf, $trim, $minOffcut);

        return $this->preferSmallerSheets($packed, $flat, $stockOptions, $kerf, $trim, $minOffcut);
    }

    /**
     * @param  list<Part>  $parts
     * @return list<GuillotineSheet>
     */
    public function packOnStock(
        array $parts,
        StockOption $stock,
        float $kerf = 0.125,
        float $trim = 0.125,
        float $minOffcut = 2.0
    ): array {
        return $this->bestPack($parts, $stock, $kerf, $trim, $minOffcut);
    }

    /**
     * @param  list<StockOption>  $options
     */
    private function largestStock(array $options): StockOption
    {
        $best = $options[0];
        foreach ($options as $opt) {
            if ($opt->area() > $best->area() + GuillotineSheet::EPS) {
                $best = $opt;
            }
        }

        return $best;
    }

    /**
     * @param  list<Part>  $parts
     * @return list<GuillotineSheet>
     */
    private function bestPack(
        array $parts,
        StockOption $stock,
        float $kerf,
        float $trim,
        float $minOffcut
    ): array {
        $strategies = [SplitHeuristic::HORIZONTAL_FIRST, SplitHeuristic::VERTICAL_FIRST];
        $sortRules = [
            function (Part $a, Part $b): int {
                return ($b->cuttingWidth * $b->cuttingLength) <=> ($a->cuttingWidth * $a->cuttingLength);
            },
            function (Part $a, Part $b): int {
                return max($b->cuttingWidth, $b->cuttingLength) <=> max($a->cuttingWidth, $a->cuttingLength);
            },
            function (Part $a, Part $b): int {
                return ($b->cuttingWidth + $b->cuttingLength) <=> ($a->cuttingWidth + $a->cuttingLength);
            },
        ];

        $bestSolution = [];
        $bestScore = INF;

        foreach ($strategies as $strategy) {
            foreach ($sortRules as $sortRule) {
                $candidate = $parts;
                usort($candidate, $sortRule);
                $sheets = $this->packCandidate($candidate, $stock, $kerf, $trim, $minOffcut, $strategy);
                $score = $this->fitness($sheets, $stock);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSolution = $sheets;
                }
            }
        }

        return $bestSolution;
    }

    /**
     * @param  list<Part>  $parts
     * @return list<GuillotineSheet>
     */
    private function packCandidate(
        array $parts,
        StockOption $stock,
        float $kerf,
        float $trim,
        float $minOffcut,
        string $strategy
    ): array {
        $sheets = [];
        $n = 0;
        foreach ($parts as $part) {
            $placed = false;
            foreach ($sheets as $sheet) {
                if ($sheet->insert($part, $strategy)) {
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $n++;
                $new = new GuillotineSheet(
                    $stock->id.'_'.$n,
                    $stock->width,
                    $stock->length,
                    $kerf,
                    $trim,
                    $trim,
                    $trim,
                    $trim,
                    $minOffcut,
                    $stock->label
                );
                if (! $new->insert($part, $strategy)) {
                    continue;
                }
                $sheets[] = $new;
            }
        }

        return $sheets;
    }

    /**
     * @param  list<GuillotineSheet>  $sheets
     */
    private function fitness(array $sheets, StockOption $stock): float
    {
        $sheetPenalty = count($sheets) * 100000.0;
        $unused = 0.0;
        $largestOffcut = 0.0;
        foreach ($sheets as $sheet) {
            foreach ($sheet->freeRectangles as $free) {
                $unused += $free->area();
                $largestOffcut = max($largestOffcut, $free->area());
            }
        }

        return $sheetPenalty + $unused - ($largestOffcut * 0.15) + (count($sheets) * ($stock->width + $stock->length) * 0.01);
    }

    /**
     * @param  list<GuillotineSheet>  $sheets
     * @param  list<Part>  $allParts
     * @param  list<StockOption>  $stockOptions
     * @return list<GuillotineSheet>
     */
    private function preferSmallerSheets(
        array $sheets,
        array $allParts,
        array $stockOptions,
        float $kerf,
        float $trim,
        float $minOffcut
    ): array {
        if ($sheets === []) {
            return [];
        }

        $smaller = $this->smallerThanDefault($stockOptions);
        if ($smaller === []) {
            return $sheets;
        }

        $out = [];
        foreach ($sheets as $sheet) {
            $sheetParts = [];
            foreach ($sheet->placedParts as $placed) {
                $sheetParts[] = $placed->part;
            }
            $replaced = false;
            usort($smaller, function (StockOption $a, StockOption $b): int {
                return $a->area() <=> $b->area();
            });
            foreach ($smaller as $opt) {
                if (! $this->allPartsFitStock($sheetParts, $opt, $kerf, $trim)) {
                    continue;
                }
                $repack = $this->bestPack($sheetParts, $opt, $kerf, $trim, $minOffcut);
                if (count($repack) === 1) {
                    $out[] = $repack[0];
                    $replaced = true;
                    break;
                }
            }
            if (! $replaced) {
                $out[] = $sheet;
            }
        }

        return $out;
    }

    /**
     * @param  list<StockOption>  $options
     * @return list<StockOption>
     */
    private function smallerThanDefault(array $options): array
    {
        $largest = $this->largestStock($options);
        $out = [];
        foreach ($options as $opt) {
            if ($opt->area() + GuillotineSheet::EPS < $largest->area()) {
                $out[] = $opt;
            }
        }

        return $out;
    }

    /**
     * @param  list<Part>  $parts
     */
    private function allPartsFitStock(array $parts, StockOption $opt, float $kerf, float $trim): bool
    {
        $usableW = $opt->width - (2 * $trim);
        $usableL = $opt->length - (2 * $trim);
        $area = 0.0;
        foreach ($parts as $part) {
            $w = $part->cuttingWidth;
            $h = $part->cuttingLength;
            $fits = ($w <= $usableW + GuillotineSheet::EPS && $h <= $usableL + GuillotineSheet::EPS)
                || ($part->grain === GrainDirection::ANY && $h <= $usableW + GuillotineSheet::EPS && $w <= $usableL + GuillotineSheet::EPS);
            if (! $fits) {
                return false;
            }
            $area += $w * $h;
        }

        return $area <= ($usableW * $usableL) + GuillotineSheet::EPS;
    }
}

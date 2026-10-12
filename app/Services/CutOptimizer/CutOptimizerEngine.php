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
                    $part->groupLabel,
                    $part->sourcePieceIndex
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
     * Pack onto matching inventory offcuts first, then fresh stock.
     * Offcut sheets skip factory-edge trim (they are already cut).
     *
     * @param  list<Part>  $parts
     * @param  list<StockOption>  $stockOptions
     * @param  list<StockOption>  $offcutStocks
     * @return array{sheets: list<GuillotineSheet>, used_offcut_ids: list<int>}
     */
    public function optimizeWithOffcuts(
        array $parts,
        array $stockOptions,
        array $offcutStocks,
        float $kerf = 0.125,
        float $trim = 0.125,
        float $minOffcut = 2.0
    ): array {
        $flat = $this->flattenParts($parts);
        $usedOffcutIds = [];
        $offcutSheets = [];

        $queue = [];
        foreach ($offcutStocks as $opt) {
            $copies = max(1, (int) $opt->quantity);
            for ($i = 0; $i < $copies; $i++) {
                $queue[] = $opt;
            }
        }
        usort($queue, function (StockOption $a, StockOption $b): int {
            return $a->area() <=> $b->area();
        });

        foreach ($queue as $offcut) {
            if ($flat === []) {
                break;
            }
            $packed = $this->bestPack($flat, $offcut, $kerf, 0.0, $minOffcut);
            if ($packed === [] || $packed[0]->placedParts === []) {
                continue;
            }
            $sheet = $packed[0];
            $sheet->offcutId = $offcut->offcutId;
            if ($sheet->label === '') {
                $sheet->label = $offcut->label;
            }
            $placedIds = [];
            foreach ($sheet->placedParts as $placed) {
                $placedIds[$placed->part->id] = true;
            }
            $flat = array_values(array_filter(
                $flat,
                static fn (Part $part): bool => ! isset($placedIds[$part->id])
            ));
            $offcutSheets[] = $sheet;
            if ($offcut->offcutId) {
                $usedOffcutIds[] = $offcut->offcutId;
            }
        }

        $fresh = [];
        if ($flat !== [] && $stockOptions !== []) {
            $fresh = $this->optimize($flat, $stockOptions, $kerf, $trim, $minOffcut);
        }

        return [
            'sheets' => array_merge($offcutSheets, $fresh),
            'used_offcut_ids' => array_values(array_unique($usedOffcutIds)),
        ];
    }

    /**
     * @param  list<Part>  $parts
     * @return list<Part>
     */
    public function flattenParts(array $parts): array
    {
        $flat = [];
        foreach ($parts as $part) {
            $qty = max(1, (int) $part->quantity);
            for ($i = 0; $i < $qty; $i++) {
                $flat[] = new Part(
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
                    $part->groupLabel,
                    $part->sourcePieceIndex
                );
            }
        }

        return $flat;
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
                $packed = $this->packCandidate($candidate, $stock, $kerf, $trim, $minOffcut, $strategy);
                $score = $this->fitness($packed['sheets'], $stock, count($packed['unplaced']));
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSolution = $packed['sheets'];
                }
            }
        }

        return $bestSolution;
    }

    /**
     * @param  list<Part>  $parts
     * @return array{sheets: list<GuillotineSheet>, unplaced: list<Part>}
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
        $unplaced = [];
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
                    $unplaced[] = $part;
                    continue;
                }
                $sheets[] = $new;
            }
        }

        return ['sheets' => $sheets, 'unplaced' => $unplaced];
    }

    /**
     * @param  list<GuillotineSheet>  $sheets
     */
    private function fitness(array $sheets, StockOption $stock, int $unplacedCount = 0): float
    {
        $sheetPenalty = ($unplacedCount * 10000000.0) + (count($sheets) * 100000.0);
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
            $currentPrice = $this->stockPriceForSheet($sheet, $stockOptions);
            foreach ($smaller as $opt) {
                if ($opt->unitPrice + GuillotineSheet::EPS >= $currentPrice) {
                    continue;
                }
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
        unset($kerf, $trim);

        $area = 0.0;
        foreach ($parts as $part) {
            $w = $part->cuttingWidth;
            $h = $part->cuttingLength;
            $fits = ($w <= $opt->width + GuillotineSheet::EPS && $h <= $opt->length + GuillotineSheet::EPS)
                || ($part->grain === GrainDirection::ANY && $h <= $opt->width + GuillotineSheet::EPS && $w <= $opt->length + GuillotineSheet::EPS);
            if (! $fits) {
                return false;
            }
            $area += $w * $h;
        }

        return $area <= ($opt->width * $opt->length) + GuillotineSheet::EPS;
    }

    /**
     * @param  list<StockOption>  $stockOptions
     */
    private function stockPriceForSheet(GuillotineSheet $sheet, array $stockOptions): float
    {
        foreach ($stockOptions as $opt) {
            if (abs($opt->width - $sheet->width) < 0.2 && abs($opt->length - $sheet->length) < 0.2) {
                return $opt->unitPrice;
            }
        }

        return INF;
    }
}

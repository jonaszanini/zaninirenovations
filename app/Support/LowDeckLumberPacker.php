<?php

namespace App\Support;

/**
 * LowDeckLumberPacker — a small one-dimensional cutting-stock optimiser for
 * the Low Deck calculator. Given a list of cut pieces (in feet) and the
 * commercial stock lengths available for a material (with prices), it
 * returns the lowest-cost mix of stock pieces that covers every cut.
 *
 * The packer evaluates two strategies and keeps the cheaper result:
 *   1. Single-stock: pack everything from one stock length (first-fit
 *      decreasing) for each candidate length.
 *   2. Mixed-stock: a cost-greedy first-fit-decreasing pass that may open
 *      bins of different lengths.
 *
 * All maths runs in integer sixteenths of an inch to avoid float drift.
 */
final class LowDeckLumberPacker
{
    private const SIXTEENTHS_PER_FOOT = 12 * 16;

    /**
     * @param  list<float>  $pieceLengthsFt   Cut pieces required, in feet.
     * @param  list<array{key: string, length_ft: float, price: float}>  $stocks
     * @param  float  $kerfFt                  Saw kerf consumed per interior cut.
     * @return array{
     *   by_key: array<string, int>,
     *   total_price: float,
     *   total_stock_ft: float,
     *   used_ft: float,
     *   waste_ft: float,
     *   bins: list<array{key: string, length_ft: float, price: float, pieces_ft: list<float>, waste_ft: float}>
     * }
     */
    public static function pack(array $pieceLengthsFt, array $stocks, float $kerfFt = 0.0): array
    {
        $pieces = [];
        foreach ($pieceLengthsFt as $len) {
            $six = (int) round(((float) $len) * self::SIXTEENTHS_PER_FOOT);
            if ($six > 0) {
                $pieces[] = $six;
            }
        }
        rsort($pieces);

        $kerf = max(0, (int) round($kerfFt * self::SIXTEENTHS_PER_FOOT));

        $stockOptions = [];
        foreach ($stocks as $stock) {
            $six = (int) round(((float) $stock['length_ft']) * self::SIXTEENTHS_PER_FOOT);
            if ($six <= 0) {
                continue;
            }
            $stockOptions[] = [
                'key' => $stock['key'],
                'length_six' => $six,
                'length_ft' => (float) $stock['length_ft'],
                'price' => round((float) $stock['price'], 4),
            ];
        }

        if ($pieces === [] || $stockOptions === []) {
            return self::emptyResult();
        }

        $longestPiece = $pieces[0];
        $usableStocks = array_values(array_filter(
            $stockOptions,
            static fn (array $s): bool => $s['length_six'] >= $longestPiece
        ));

        // No single stock can hold the longest piece: fall back to the
        // largest available stock so the caller still gets a result.
        if ($usableStocks === []) {
            usort($stockOptions, static fn ($a, $b) => $b['length_six'] <=> $a['length_six']);
            $usableStocks = [$stockOptions[0]];
        }

        $best = null;

        foreach ($usableStocks as $stock) {
            $candidate = self::packFirstFit($pieces, [$stock], $kerf);
            if ($best === null || $candidate['total_price'] < $best['total_price']) {
                $best = $candidate;
            }
        }

        $mixed = self::packFirstFit($pieces, $usableStocks, $kerf);
        if ($best === null || $mixed['total_price'] < $best['total_price']) {
            $best = $mixed;
        }

        return $best ?? self::emptyResult();
    }

    /**
     * First-fit-decreasing packing. When opening a new bin the smallest
     * stock that still fits the piece is chosen, preferring the lowest
     * price-per-foot among ties, which keeps scrap and cost down.
     *
     * @param  list<int>  $pieces  sixteenths, sorted descending
     * @param  list<array{key: string, length_six: int, length_ft: float, price: float}>  $stocks
     */
    private static function packFirstFit(array $pieces, array $stocks, int $kerf): array
    {
        $byEfficiency = $stocks;
        usort($byEfficiency, static function ($a, $b) {
            $ea = $a['price'] / max(1, $a['length_six']);
            $eb = $b['price'] / max(1, $b['length_six']);

            return $ea <=> $eb ?: $a['length_six'] <=> $b['length_six'];
        });

        /** @var list<array{stock: array, remaining: int, pieces: list<int>}> $bins */
        $bins = [];

        foreach ($pieces as $piece) {
            $placedAt = null;
            $tightest = null;

            foreach ($bins as $i => $bin) {
                $needed = $piece + ($bin['pieces'] === [] ? 0 : $kerf);
                if ($needed <= $bin['remaining']) {
                    $leftover = $bin['remaining'] - $needed;
                    if ($tightest === null || $leftover < $tightest) {
                        $tightest = $leftover;
                        $placedAt = $i;
                    }
                }
            }

            if ($placedAt !== null) {
                $needed = $piece + ($bins[$placedAt]['pieces'] === [] ? 0 : $kerf);
                $bins[$placedAt]['remaining'] -= $needed;
                $bins[$placedAt]['pieces'][] = $piece;

                continue;
            }

            $chosen = null;
            foreach ($byEfficiency as $stock) {
                if ($stock['length_six'] >= $piece) {
                    $chosen = $stock;
                    break;
                }
            }
            $chosen = $chosen ?? $byEfficiency[count($byEfficiency) - 1];

            $bins[] = [
                'stock' => $chosen,
                'remaining' => $chosen['length_six'] - $piece,
                'pieces' => [$piece],
            ];
        }

        return self::summariseBins($bins);
    }

    /**
     * @param  list<array{stock: array, remaining: int, pieces: list<int>}>  $bins
     */
    private static function summariseBins(array $bins): array
    {
        $byKey = [];
        $totalPrice = 0.0;
        $totalStockSix = 0;
        $usedSix = 0;
        $outBins = [];

        foreach ($bins as $bin) {
            $stock = $bin['stock'];
            $key = $stock['key'];
            $byKey[$key] = ($byKey[$key] ?? 0) + 1;
            $totalPrice += $stock['price'];
            $totalStockSix += $stock['length_six'];
            $binUsed = array_sum($bin['pieces']);
            $usedSix += $binUsed;

            $outBins[] = [
                'key' => $key,
                'length_ft' => $stock['length_ft'],
                'price' => round($stock['price'], 2),
                'pieces_ft' => array_map(
                    static fn (int $s): float => round($s / self::SIXTEENTHS_PER_FOOT, 3),
                    $bin['pieces']
                ),
                'waste_ft' => round(($stock['length_six'] - $binUsed) / self::SIXTEENTHS_PER_FOOT, 3),
            ];
        }

        return [
            'by_key' => $byKey,
            'total_price' => round($totalPrice, 2),
            'total_stock_ft' => round($totalStockSix / self::SIXTEENTHS_PER_FOOT, 3),
            'used_ft' => round($usedSix / self::SIXTEENTHS_PER_FOOT, 3),
            'waste_ft' => round(($totalStockSix - $usedSix) / self::SIXTEENTHS_PER_FOOT, 3),
            'bins' => $outBins,
        ];
    }

    private static function emptyResult(): array
    {
        return [
            'by_key' => [],
            'total_price' => 0.0,
            'total_stock_ft' => 0.0,
            'used_ft' => 0.0,
            'waste_ft' => 0.0,
            'bins' => [],
        ];
    }
}

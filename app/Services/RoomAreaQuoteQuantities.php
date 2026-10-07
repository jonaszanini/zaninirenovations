<?php

namespace App\Services;

/**
 * Maps Room Area layoutMetrics() to priced-quote quantities.
 *
 * Quantities are always derived from stored canvas metrics (never from the
 * browser). Waste is applied and rounded up to the nearest 0.01 so a cut
 * of material is never under-ordered.
 */
class RoomAreaQuoteQuantities
{
    public const TRADE_FLOORING = 'flooring';

    public const TRADE_BASEBOARD = 'baseboard';

    public const TRADE_PAINT_WALLS = 'paint_walls';

    public const TRADE_PAINT_CEILING = 'paint_ceiling';

    public const TRADE_DRYWALL_WALLS = 'drywall_walls';

    public const TRADE_DRYWALL_CEILING = 'drywall_ceiling';

    /**
     * @return array<string, array{label: string, unit: string, metric: string, default_waste: float}>
     */
    public static function trades(): array
    {
        return [
            self::TRADE_FLOORING => [
                'label' => 'Flooring',
                'unit' => 'sq ft',
                'metric' => 'area_sqft',
                'default_waste' => 10.0,
            ],
            self::TRADE_BASEBOARD => [
                'label' => 'Baseboard',
                'unit' => 'linear ft',
                'metric' => 'baseboard_lf',
                'default_waste' => 0.0,
            ],
            self::TRADE_PAINT_WALLS => [
                'label' => 'Paint walls',
                'unit' => 'sq ft',
                'metric' => 'wall_area_sqft',
                'default_waste' => 0.0,
            ],
            self::TRADE_PAINT_CEILING => [
                'label' => 'Paint ceiling',
                'unit' => 'sq ft',
                'metric' => 'area_sqft',
                'default_waste' => 0.0,
            ],
            self::TRADE_DRYWALL_WALLS => [
                'label' => 'Drywall walls',
                'unit' => 'sq ft',
                'metric' => 'wall_area_sqft',
                'default_waste' => 10.0,
            ],
            self::TRADE_DRYWALL_CEILING => [
                'label' => 'Drywall ceiling',
                'unit' => 'sq ft',
                'metric' => 'area_sqft',
                'default_waste' => 10.0,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function tradeKeys(): array
    {
        return array_keys(self::trades());
    }

    /**
     * @param  array<string, mixed>  $metrics  layoutMetrics() (or an aggregated project option)
     * @return array{trade: string, label: string, unit: string, base_qty: float, waste_percent: float, qty: float}
     */
    public function line(string $trade, array $metrics, float $wastePercent = 0.0): array
    {
        $catalog = self::trades();
        if (! isset($catalog[$trade])) {
            throw new \InvalidArgumentException('Unknown room-to-quote trade: '.$trade);
        }

        $meta = $catalog[$trade];
        $base = $this->baseQuantity($trade, $metrics);
        $waste = $this->normalizeWaste($wastePercent);
        $qty = $this->applyWaste($base, $waste);

        return [
            'trade' => $trade,
            'label' => $meta['label'],
            'unit' => $meta['unit'],
            'base_qty' => $base,
            'waste_percent' => $waste,
            'qty' => $qty,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, float>  $wasteByTrade  trade => waste percent
     * @return list<array{trade: string, label: string, unit: string, base_qty: float, waste_percent: float, qty: float}>
     */
    public function linesForTrades(array $metrics, array $trades, array $wasteByTrade = []): array
    {
        $out = [];
        foreach ($trades as $trade) {
            $trade = (string) $trade;
            if (! isset(self::trades()[$trade])) {
                continue;
            }
            $default = self::trades()[$trade]['default_waste'];
            $waste = array_key_exists($trade, $wasteByTrade)
                ? (float) $wasteByTrade[$trade]
                : $default;
            $out[] = $this->line($trade, $metrics, $waste);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function baseQuantity(string $trade, array $metrics): float
    {
        $catalog = self::trades();
        if (! isset($catalog[$trade])) {
            return 0.0;
        }

        $key = $catalog[$trade]['metric'];
        $value = (float) ($metrics[$key] ?? 0);

        if ($trade === self::TRADE_BASEBOARD && $value <= 0) {
            $value = (float) ($metrics['perimeter_lf'] ?? 0);
        }

        return round(max(0.0, $value), 2);
    }

    public function applyWaste(float $baseQty, float $wastePercent): float
    {
        $baseQty = max(0.0, $baseQty);
        $wastePercent = $this->normalizeWaste($wastePercent);
        if ($baseQty <= 0) {
            return 0.0;
        }

        $raw = $baseQty * (1.0 + ($wastePercent / 100.0));

        return round(ceil(($raw - 1e-9) * 100) / 100, 2);
    }

    public function normalizeWaste(float $wastePercent): float
    {
        return round(max(0.0, min(100.0, $wastePercent)), 2);
    }
}

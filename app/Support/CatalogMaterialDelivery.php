<?php

namespace App\Support;

use App\Models\Material;

/**
 * Flat catalog delivery fee: added once per linked product line, not × quantity.
 */
final class CatalogMaterialDelivery
{
    public static function cad(?Material $material): float
    {
        if ($material === null) {
            return 0.0;
        }

        return round(max(0.0, (float) ($material->delivery_cad ?? 0)), 2);
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return array<int, float> id => delivery CAD (includes 0 when the row exists)
     */
    public static function mapForIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $map = [];
        foreach (Material::query()->whereIn('id', $ids)->get(['id', 'delivery_cad']) as $mat) {
            $map[(int) $mat->id] = self::cad($mat);
        }

        return $map;
    }

    /**
     * Append a qty=1 delivery row after each priced material line that has a fee.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array{0: float, 1: string}  $deliveryAndName
     * @return list<array<string, mixed>>
     */
    public static function appendNamedRows(array $rows, callable $deliveryAndName): array
    {
        $extra = [];
        foreach ($rows as $row) {
            if ((float) ($row['quantity'] ?? 0) <= 0) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key !== '' && str_ends_with($key, '_delivery')) {
                continue;
            }
            [$delivery, $name] = $deliveryAndName($row);
            $delivery = round(max(0.0, (float) $delivery), 2);
            if ($delivery <= 0) {
                continue;
            }
            $name = trim((string) $name);
            if ($name === '') {
                $name = (string) ($row['name'] ?? $key);
            }
            $line = $row;
            $line['key'] = ($key !== '' ? $key : 'material').'_delivery';
            $line['name'] = $name.' delivery';
            if (array_key_exists('long_name', $row)) {
                $baseLong = trim((string) ($row['long_name'] ?? ''));
                $line['long_name'] = ($baseLong !== '' ? $baseLong : $name).' delivery';
            }
            $line['quantity'] = 1;
            $line['unit'] = 'flat';
            $line['unit_price'] = $delivery;
            $line['subtotal'] = $delivery;
            $line['notes'] = 'Catalog delivery';
            if (array_key_exists('quantity_of_individual_units', $row)) {
                $line['quantity_of_individual_units'] = null;
            }
            if (array_key_exists('quantity_per_box', $row)) {
                $line['quantity_per_box'] = null;
            }
            $extra[] = $line;
        }

        return array_merge($rows, $extra);
    }

    /**
     * One delivery line per vendor/order. Uses the highest fee in the group
     * (typical store delivery is charged once per drop, not per SKU).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array{0: float, 1: string, 2?: string}  $deliveryNameAndVendor
     * @return list<array<string, mixed>>
     */
    public static function appendOncePerVendor(array $rows, callable $deliveryNameAndVendor): array
    {
        $byVendor = [];
        foreach ($rows as $row) {
            if ((float) ($row['quantity'] ?? 0) <= 0) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key !== '' && str_ends_with($key, '_delivery')) {
                continue;
            }
            $meta = $deliveryNameAndVendor($row);
            $delivery = round(max(0.0, (float) ($meta[0] ?? 0)), 2);
            if ($delivery <= 0) {
                continue;
            }
            $name = trim((string) ($meta[1] ?? ''));
            $vendor = trim((string) ($meta[2] ?? ''));
            if ($vendor === '') {
                $vendor = $name !== '' ? $name : ($key !== '' ? $key : 'supplier');
            }
            if (! isset($byVendor[$vendor]) || $delivery > $byVendor[$vendor]['delivery']) {
                $byVendor[$vendor] = [
                    'row' => $row,
                    'key' => $key,
                    'name' => $name !== '' ? $name : (string) ($row['name'] ?? $key),
                    'delivery' => $delivery,
                ];
            }
        }

        $extra = [];
        foreach ($byVendor as $vendor => $item) {
            $row = $item['row'];
            $key = $item['key'];
            $name = $item['name'];
            $line = $row;
            $line['key'] = ($key !== '' ? $key : 'material').'_delivery';
            $line['name'] = $name.' delivery';
            if (array_key_exists('long_name', $row)) {
                $baseLong = trim((string) ($row['long_name'] ?? ''));
                $line['long_name'] = ($baseLong !== '' ? $baseLong : $name).' delivery';
            }
            $line['quantity'] = 1;
            $line['unit'] = 'flat';
            $line['unit_price'] = $item['delivery'];
            $line['subtotal'] = $item['delivery'];
            $line['notes'] = 'One delivery per vendor / order ('.$vendor.')';
            $line['delivery_vendor'] = $vendor;
            if (array_key_exists('quantity_of_individual_units', $row)) {
                $line['quantity_of_individual_units'] = null;
            }
            if (array_key_exists('quantity_per_box', $row)) {
                $line['quantity_per_box'] = null;
            }
            $extra[] = $line;
        }

        return array_merge($rows, $extra);
    }
}

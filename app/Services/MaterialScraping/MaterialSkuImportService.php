<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;

class MaterialSkuImportService
{
    public const DEFAULT_VENDOR = 'Home Depot';

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     material_ids: list<int>,
     *     skip_reasons: list<string>
     * }
     */
    public function import(string $rawInput, ?string $category = null, ?string $vendor = null): array
    {
        $category = filled($category) ? trim($category) : null;
        $vendor = filled($vendor) ? trim($vendor) : self::DEFAULT_VENDOR;

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $materialIds = [];
        $skipReasons = [];
        $seen = [];

        $lines = preg_split('/\r\n|\r|\n/', $rawInput) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $sku = HomeDepotCaUrls::normalizeSku($line);
            if ($sku === null) {
                $skipped++;
                $skipReasons[] = 'Invalid SKU or URL: '.$line;

                continue;
            }

            if (isset($seen[$sku])) {
                $skipped++;
                $skipReasons[] = 'Duplicate in paste: '.$sku;

                continue;
            }
            $seen[$sku] = true;

            $attributes = [
                'product_url' => HomeDepotCaUrls::productUrl($sku),
                'image_url' => HomeDepotCaUrls::imageUrl($sku),
                'vendor' => $vendor,
                'is_active' => true,
            ];

            if ($category !== null) {
                $attributes['category'] = $category;
            }

            $existing = Material::query()->where('store_sku', $sku)->first();

            if ($existing === null) {
                $attributes['name'] = 'HD '.$sku;
                $attributes['store_sku'] = $sku;
                $material = Material::create($attributes);
                $created++;
            } else {
                $existing->fill($attributes);
                $existing->save();
                $material = $existing;
                $updated++;
            }

            $materialIds[] = $material->id;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'material_ids' => $materialIds,
            'skip_reasons' => $skipReasons,
        ];
    }
}

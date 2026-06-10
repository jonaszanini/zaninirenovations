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
     *     blank_lines: int,
     *     duplicate_lines: int,
     *     invalid_lines: int,
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
        $blankLines = 0;
        $duplicateLines = 0;
        $invalidLines = 0;
        $materialIds = [];
        $skipReasons = [];
        $seen = [];

        $rawInput = preg_replace('/^\x{FEFF}/u', '', $rawInput) ?? $rawInput;
        $lines = preg_split('/\r\n|\r|\n/', $rawInput) ?: [];

        foreach ($lines as $line) {
            $line = $this->trimLine($line);

            if ($this->isBlankLine($line)) {
                $blankLines++;

                continue;
            }

            $sku = HomeDepotCaUrls::normalizeSku($line);
            if ($sku === null) {
                $invalidLines++;
                $skipReasons[] = 'Invalid SKU or URL: '.$line;

                continue;
            }

            if (isset($seen[$sku])) {
                $duplicateLines++;
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
            'skipped' => $duplicateLines + $invalidLines,
            'blank_lines' => $blankLines,
            'duplicate_lines' => $duplicateLines,
            'invalid_lines' => $invalidLines,
            'material_ids' => $materialIds,
            'skip_reasons' => $skipReasons,
        ];
    }

    public function hasImportableSku(string $rawInput): bool
    {
        $rawInput = preg_replace('/^\x{FEFF}/u', '', $rawInput) ?? $rawInput;
        $lines = preg_split('/\r\n|\r|\n/', $rawInput) ?: [];
        $seen = [];

        foreach ($lines as $line) {
            $line = $this->trimLine($line);
            if ($this->isBlankLine($line)) {
                continue;
            }

            $sku = HomeDepotCaUrls::normalizeSku($line);
            if ($sku === null || isset($seen[$sku])) {
                continue;
            }

            $seen[$sku] = true;

            return true;
        }

        return false;
    }

    private function trimLine(string $line): string
    {
        $line = preg_replace('/^\x{FEFF}/u', '', $line) ?? $line;
        $line = str_replace("\xc2\xa0", ' ', $line);

        return trim($line);
    }

    private function isBlankLine(string $line): bool
    {
        return $line === '' || preg_match('/^\s*$/u', $line) === 1;
    }
}

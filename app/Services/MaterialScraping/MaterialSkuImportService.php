<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;
use App\Models\MaterialVendor;

class MaterialSkuImportService
{
    public const DEFAULT_VENDOR = 'Home Depot';

    /**
     * Parse pasted input without writing to the database.
     *
     * @return array{
     *     skus: list<string>,
     *     blank_lines: int,
     *     duplicate_lines: int,
     *     invalid_lines: int,
     *     skip_reasons: list<string>
     * }
     */
    public function buildPlan(string $rawInput): array
    {
        $blankLines = 0;
        $duplicateLines = 0;
        $invalidLines = 0;
        $skipReasons = [];
        $seen = [];
        $skus = [];

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
            $skus[] = $sku;
        }

        return [
            'skus' => $skus,
            'blank_lines' => $blankLines,
            'duplicate_lines' => $duplicateLines,
            'invalid_lines' => $invalidLines,
            'skip_reasons' => $skipReasons,
        ];
    }

    /**
     * @return array{
     *     action: string,
     *     material_id: int,
     *     sku: string,
     *     name: string
     * }
     */
    public function importSku(string $sku, ?int $categoryId = null, ?int $vendorId = null): array
    {
        $vendorId = $vendorId ?? MaterialVendor::defaultForHomeDepotImport()?->id;

        $attributes = [
            'product_url' => HomeDepotCaUrls::productUrl($sku),
            'image_url' => HomeDepotCaUrls::imageUrl($sku),
            'vendor_id' => $vendorId,
            'is_active' => true,
        ];

        if ($categoryId !== null) {
            $attributes['category_id'] = $categoryId;
        }

        $existing = Material::query()->where('store_sku', $sku)->first();

        if ($existing === null) {
            $attributes['name'] = 'HD '.$sku;
            $attributes['store_sku'] = $sku;
            $material = Material::create($attributes);

            return [
                'action' => 'created',
                'material_id' => $material->id,
                'sku' => $sku,
                'name' => $material->name,
            ];
        }

        $existing->fill($attributes);
        $existing->save();

        return [
            'action' => 'updated',
            'material_id' => $existing->id,
            'sku' => $sku,
            'name' => $existing->name,
        ];
    }

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
    public function import(string $rawInput, ?int $categoryId = null, ?int $vendorId = null): array
    {
        $plan = $this->buildPlan($rawInput);
        $created = 0;
        $updated = 0;
        $materialIds = [];

        foreach ($plan['skus'] as $sku) {
            $result = $this->importSku($sku, $categoryId, $vendorId);
            $materialIds[] = $result['material_id'];

            if ($result['action'] === 'created') {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $plan['duplicate_lines'] + $plan['invalid_lines'],
            'blank_lines' => $plan['blank_lines'],
            'duplicate_lines' => $plan['duplicate_lines'],
            'invalid_lines' => $plan['invalid_lines'],
            'material_ids' => $materialIds,
            'skip_reasons' => $plan['skip_reasons'],
        ];
    }

    public function hasImportableSku(string $rawInput): bool
    {
        return $this->buildPlan($rawInput)['skus'] !== [];
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

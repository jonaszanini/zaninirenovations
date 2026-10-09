<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;
use App\Models\MaterialVendor;
use App\Support\Logging\AppLogger;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public function findBySku(string $sku, bool $forUpdate = false, ?int $ignoreId = null): ?Material
    {
        return Material::findByNormalizedHdSku($sku, $ignoreId, $forUpdate);
    }

    public function persistParsedSku(string $sku, ParsedProductData $parsed, ?Material $existing = null): Material
    {
        $sku = HomeDepotCaUrls::normalizeStoredSku($parsed->sku ?? $sku) ?? $sku;
        $vendorId = MaterialVendor::defaultForHomeDepotImport()?->id;

        if ($existing === null) {
            $owner = $this->findBySku($sku);
            if ($owner !== null) {
                if (! $owner->is_active) {
                    return $owner;
                }
                $existing = $owner;
            }
        }

        $material = $existing ?? new Material;

        $material->name = $parsed->name ?? $material->name ?? ('HD '.$sku);
        if (! $material->assignStoreSkuIfAvailable($sku)) {
            AppLogger::warning('materials.persist.sku_owned', [
                'material_id' => $material->id,
                'store_sku' => $sku,
            ]);
        }
        $material->product_url = filled($parsed->productUrl)
            ? $parsed->productUrl
            : HomeDepotCaUrls::productUrl($sku);

        if (filled($parsed->imageUrl)) {
            $material->image_url = $parsed->imageUrl;
        } elseif (! filled($material->image_url)) {
            $material->image_url = HomeDepotCaUrls::imageUrl($sku);
        }

        if (filled($parsed->model)) {
            $material->model = $parsed->model;
        }

        if (filled($parsed->unit)) {
            $material->unit = $parsed->unit;
        }

        if ($parsed->quantityPerPackage !== null && $parsed->quantityPerPackage >= 2) {
            $material->quantity_per_package = $parsed->quantityPerPackage;
        }

        if ($parsed->priceCad !== null) {
            $material->price_cad = $parsed->priceCad;
        }

        if ($vendorId !== null) {
            $material->vendor_id = $vendorId;
        }

        if ($existing === null) {
            $material->is_active = true;
        }

        $material->last_scraped_at = now();
        $material->last_scrape_status = Material::SCRAPE_OK;
        $material->last_scrape_error = null;
        $material->save();

        return $material;
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
        $sku = HomeDepotCaUrls::normalizeStoredSku($sku) ?? trim($sku);
        $vendorId = $vendorId ?? MaterialVendor::defaultForHomeDepotImport()?->id;

        try {
            return Cache::lock('materials.store-sku.'.$sku, 75)->block(10, function () use ($sku, $categoryId, $vendorId) {
                return DB::transaction(function () use ($sku, $categoryId, $vendorId) {
                    $existing = $this->findBySku($sku, forUpdate: true);

                    if ($existing !== null && ! $existing->is_active) {
                        return [
                            'action' => 'skipped',
                            'reason' => 'inactive',
                            'material_id' => $existing->id,
                            'sku' => $sku,
                            'name' => $existing->name,
                        ];
                    }

                    $attributes = [
                        'vendor_id' => $vendorId,
                    ];

                    if ($categoryId !== null) {
                        $attributes['category_id'] = $categoryId;
                    }

                    if ($existing === null) {
                        $attributes['name'] = 'HD '.$sku;
                        $attributes['product_url'] = HomeDepotCaUrls::productUrl($sku);
                        $attributes['image_url'] = HomeDepotCaUrls::imageUrl($sku);
                        $attributes['is_active'] = true;
                        $material = new Material($attributes);
                        $material->assignStoreSkuIfAvailable($sku);
                        $material->save();

                        return [
                            'action' => 'created',
                            'material_id' => $material->id,
                            'sku' => $sku,
                            'name' => $material->name,
                        ];
                    }

                    if (! $existing->assignStoreSkuIfAvailable($sku)) {
                        AppLogger::warning('materials.import.sku_owned', [
                            'material_id' => $existing->id,
                            'store_sku' => $sku,
                        ]);
                    }
                    if (! filled($existing->product_url)) {
                        $attributes['product_url'] = HomeDepotCaUrls::productUrl($sku);
                    }
                    if (! filled($existing->image_url)) {
                        $attributes['image_url'] = HomeDepotCaUrls::imageUrl($sku);
                    }

                    $existing->fill($attributes);
                    $existing->save();

                    return [
                        'action' => 'updated',
                        'material_id' => $existing->id,
                        'sku' => $sku,
                        'name' => $existing->name,
                    ];
                });
            });
        } catch (LockTimeoutException $e) {
            return [
                'action' => 'skipped',
                'reason' => 'busy',
                'material_id' => 0,
                'sku' => $sku,
                'name' => '',
            ];
        }
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
        $skippedInactive = 0;
        $materialIds = [];
        $skipReasons = $plan['skip_reasons'];

        foreach ($plan['skus'] as $sku) {
            $result = $this->importSku($sku, $categoryId, $vendorId);

            if ($result['action'] === 'created') {
                $created++;
                $materialIds[] = $result['material_id'];
            } elseif ($result['action'] === 'skipped') {
                $skippedInactive++;
                $skipReasons[] = 'skipped: '.($result['reason'] ?? 'inactive').' (SKU '.$sku.')';
            } else {
                $updated++;
                $materialIds[] = $result['material_id'];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $plan['duplicate_lines'] + $plan['invalid_lines'] + $skippedInactive,
            'blank_lines' => $plan['blank_lines'],
            'duplicate_lines' => $plan['duplicate_lines'],
            'invalid_lines' => $plan['invalid_lines'],
            'material_ids' => $materialIds,
            'skip_reasons' => $skipReasons,
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

<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;
use App\Models\MaterialVendor;
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

    public function findBySku(string $sku, bool $forUpdate = false): ?Material
    {
        $sku = HomeDepotCaUrls::normalizeStoredSku($sku) ?? trim($sku);
        if (! HomeDepotCaUrls::isValidSku($sku)) {
            return null;
        }

        $query = Material::query()
            ->where(function ($inner) use ($sku) {
                $inner->where('store_sku', $sku)
                    ->orWhere('product_url', 'like', '%/'.$sku)
                    ->orWhere('product_url', 'like', '%/'.$sku.'/%')
                    ->orWhere('product_url', 'like', '%/'.$sku.'?%');
            })
            ->orderByRaw('case when store_sku = ? then 0 else 1 end', [$sku]);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function persistParsedSku(string $sku, ParsedProductData $parsed, ?Material $existing = null): Material
    {
        $sku = HomeDepotCaUrls::normalizeStoredSku($parsed->sku ?? $sku) ?? $sku;
        $vendorId = MaterialVendor::defaultForHomeDepotImport()?->id;
        $material = $existing ?? new Material;

        $material->name = $parsed->name ?? $material->name ?? ('HD '.$sku);
        $material->store_sku = $sku;
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

        return Cache::lock('materials.store-sku.'.$sku, 20)->block(15, function () use ($sku, $categoryId, $vendorId) {
            return DB::transaction(function () use ($sku, $categoryId, $vendorId) {
                $existing = $this->findBySku($sku, forUpdate: true);

                $attributes = [
                    'store_sku' => $sku,
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
                    $material = Material::create($attributes);

                    return [
                        'action' => 'created',
                        'material_id' => $material->id,
                        'sku' => $sku,
                        'name' => $material->name,
                    ];
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

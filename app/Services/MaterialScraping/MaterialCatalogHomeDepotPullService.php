<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;
use App\Services\MaterialMetadataPopulationService;
use App\Support\Logging\AppLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MaterialCatalogHomeDepotPullService
{
    public const MSG_INVALID = 'Enter a Home Depot SKU or product URL.';

    public const MSG_NOT_FOUND = "Couldn't find that SKU on Home Depot";

    public const MSG_UNREACHABLE = "Home Depot couldn't be reached, try again";

    public const MSG_INACTIVE = 'This material exists but is inactive';

    public function __construct(
        private MaterialSkuImportService $importer,
        private MaterialPriceSyncService $sync,
        private MaterialMetadataPopulationService $metadata,
    ) {}

    /**
     * Fetch Home Depot data first, then persist inside a lock + transaction.
     * Never writes on failure and never reactivates an inactive material.
     *
     * @return array{
     *     ok: bool,
     *     status: int,
     *     created: bool,
     *     material: ?Material,
     *     message: ?string
     * }
     */
    public function pull(string $query): array
    {
        $sku = HomeDepotCaUrls::normalizeSku($query);
        if ($sku === null) {
            return $this->fail(422, self::MSG_INVALID);
        }

        $lock = Cache::lock('materials.store-sku.'.$sku, 20);

        return $lock->block(15, function () use ($sku) {
            $existing = $this->importer->findBySku($sku);

            if ($existing !== null && ! $existing->is_active) {
                return $this->fail(422, self::MSG_INACTIVE, $existing);
            }

            if ($existing !== null && $existing->isCatalogReady()) {
                return $this->ok($existing, created: false);
            }

            $fetched = $this->sync->fetchHomeDepotSku($sku);
            if (($fetched['status'] ?? null) !== Material::SCRAPE_OK || ! ($fetched['parsed'] instanceof ParsedProductData)) {
                $message = ! empty($fetched['unreachable'])
                    ? self::MSG_UNREACHABLE
                    : self::MSG_NOT_FOUND;

                return $this->fail(422, $message);
            }

            $material = null;
            $created = false;

            DB::transaction(function () use ($sku, $fetched, &$material, &$created) {
                $existing = $this->importer->findBySku($sku, forUpdate: true);

                if ($existing !== null && ! $existing->is_active) {
                    $material = $existing;

                    return;
                }

                $created = $existing === null;
                $material = $this->importer->persistParsedSku($sku, $fetched['parsed'], $existing);
            });

            if ($material !== null && ! $material->is_active) {
                return $this->fail(422, self::MSG_INACTIVE, $material);
            }

            if ($material === null) {
                return $this->fail(422, self::MSG_NOT_FOUND);
            }

            $this->populateMetadata($material);
            $material->refresh()->load('vendor');

            return $this->ok($material, $created);
        });
    }

    /**
     * @return array{ok: bool, status: int, created: bool, material: Material, message: null}
     */
    private function ok(Material $material, bool $created): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'created' => $created,
            'material' => $material,
            'message' => null,
        ];
    }

    /**
     * @return array{ok: bool, status: int, created: bool, material: ?Material, message: string}
     */
    private function fail(int $status, string $message, ?Material $material = null): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'created' => false,
            'material' => $material,
            'message' => $message,
        ];
    }

    private function populateMetadata(Material $material): void
    {
        try {
            $this->metadata->applyToMaterial($material);
        } catch (\Throwable $e) {
            AppLogger::warning('materials.metadata_populate.failed', [
                'material_id' => $material->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

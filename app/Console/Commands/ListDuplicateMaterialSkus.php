<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only report of catalog rows that share a normalised Home Depot store_sku
 * or a matching Home Depot product_url. Does not write to the database.
 */
class ListDuplicateMaterialSkus extends Command
{
    protected $signature = 'materials:list-duplicate-skus';

    protected $description = 'List materials that share the same normalised Home Depot store SKU (read-only)';

    public function handle(): int
    {
        $groups = [];

        $materials = Material::query()
            ->orderBy('id')
            ->get(['id', 'name', 'is_active', 'store_sku', 'product_url']);

        foreach ($materials as $material) {
            $keys = [];
            $fromSku = HomeDepotCaUrls::normalizeStoredSku($material->store_sku);
            if (HomeDepotCaUrls::isValidSku($fromSku)) {
                $keys[$fromSku] = true;
            }
            $fromUrl = HomeDepotCaUrls::normalizeSku((string) ($material->product_url ?? ''));
            if (HomeDepotCaUrls::isValidSku($fromUrl)) {
                $keys[$fromUrl] = true;
            }

            foreach (array_keys($keys) as $key) {
                $groups[$key][$material->id] = $material;
            }
        }

        $duplicates = array_filter($groups, fn (array $rows) => count($rows) > 1);

        if ($duplicates === []) {
            $this->info('No duplicate normalised Home Depot store_sku groups.');

            return self::SUCCESS;
        }

        foreach ($duplicates as $sku => $rows) {
            $this->newLine();
            $this->warn('SKU '.$sku.' ('.count($rows).' rows)');
            $this->table(
                ['id', 'name', 'active', 'store_sku', 'match', 'refs'],
                array_map(function (Material $row) use ($sku) {
                    $match = HomeDepotCaUrls::normalizeStoredSku($row->store_sku) === $sku
                        ? 'store_sku'
                        : 'product_url';

                    return [
                        $row->id,
                        $row->name,
                        $row->is_active ? 'yes' : 'no',
                        $row->store_sku ?? '',
                        $match,
                        $this->referenceCount((int) $row->id),
                    ];
                }, array_values($rows))
            );
        }

        $this->newLine();
        $this->comment('Read-only. Review before merging or deactivating any row.');

        return self::SUCCESS;
    }

    private function referenceCount(int $id): int
    {
        $count = 0;
        if (Schema::hasTable('fence_materials')) {
            $count += (int) DB::table('fence_materials')->where('material_id', $id)->count();
        }
        if (Schema::hasTable('low_deck_materials')) {
            $count += (int) DB::table('low_deck_materials')->where('material_id', $id)->count();
        }
        if (Schema::hasTable('shared_works')) {
            $count += (int) DB::table('shared_works')->where('catalog_material_id', $id)->count();
        }

        return $count;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use Illuminate\Console\Command;

/**
 * Read-only report of catalog rows that share a normalised Home Depot store_sku.
 * Does not write to the database.
 */
class ListDuplicateMaterialSkus extends Command
{
    protected $signature = 'materials:list-duplicate-skus';

    protected $description = 'List materials that share the same normalised Home Depot store SKU (read-only)';

    public function handle(): int
    {
        $groups = [];

        foreach (Material::query()->orderBy('id')->get(['id', 'name', 'is_active', 'store_sku']) as $material) {
            $normalized = HomeDepotCaUrls::normalizeStoredSku($material->store_sku);
            if (! HomeDepotCaUrls::isValidSku($normalized)) {
                continue;
            }
            $groups[$normalized][] = $material;
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
                ['id', 'name', 'active', 'store_sku'],
                array_map(fn (Material $row) => [
                    $row->id,
                    $row->name,
                    $row->is_active ? 'yes' : 'no',
                    $row->store_sku ?? '',
                ], $rows)
            );
        }

        $this->newLine();
        $this->comment('Read-only. Review before merging or deactivating any row.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsMaterialPriceSync;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Illuminate\Console\Command;

class SyncMaterialPrices extends Command
{
    use RunsMaterialPriceSync;

    protected $signature = 'materials:sync-prices
                            {--dry-run : Show changes without writing to the database}
                            {--id= : Sync a single material by ID}
                            {--vendor= : Limit to materials whose vendor contains this string}
                            {--unsynced-only : Skip materials that were synced successfully before}';

    protected $description = 'Fetch retailer product pages and update material prices (run locally on your PC)';

    public function handle(MaterialPriceSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $id = $this->option('id') !== null ? (int) $this->option('id') : null;
        $vendor = $this->option('vendor') !== null ? trim((string) $this->option('vendor')) : null;
        $unsyncedOnly = (bool) $this->option('unsynced-only');

        return $this->runMaterialPriceSync($syncService, $dryRun, $id, $vendor !== '' ? $vendor : null, $unsyncedOnly);
    }
}

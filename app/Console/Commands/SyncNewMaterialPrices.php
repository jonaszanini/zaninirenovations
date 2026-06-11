<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsMaterialPriceSync;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Illuminate\Console\Command;

class SyncNewMaterialPrices extends Command
{
    use RunsMaterialPriceSync;

    protected $signature = 'materials:sync-new-prices
                            {--dry-run : Show changes without writing to the database}
                            {--id= : Sync a single material by ID}
                            {--vendor= : Limit to materials whose vendor contains this string}';

    protected $description = 'Sync only materials never successfully synced before (run locally on your PC)';

    public function handle(MaterialPriceSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $id = $this->option('id') !== null ? (int) $this->option('id') : null;
        $vendor = $this->option('vendor') !== null ? trim((string) $this->option('vendor')) : null;

        return $this->runMaterialPriceSync($syncService, $dryRun, $id, $vendor !== '' ? $vendor : null, true);
    }
}

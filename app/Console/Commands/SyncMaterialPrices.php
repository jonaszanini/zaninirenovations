<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Illuminate\Console\Command;

class SyncMaterialPrices extends Command
{
    protected $signature = 'materials:sync-prices
                            {--dry-run : Show changes without writing to the database}
                            {--id= : Sync a single material by ID}
                            {--vendor= : Limit to materials whose vendor contains this string}';

    protected $description = 'Fetch retailer product pages and update material prices (run locally on your PC)';

    public function handle(MaterialPriceSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $id = $this->option('id') !== null ? (int) $this->option('id') : null;
        $vendor = $this->option('vendor') !== null ? trim((string) $this->option('vendor')) : null;

        $materials = $syncService->materialsToSync($id, $vendor !== '' ? $vendor : null);

        if ($materials->isEmpty()) {
            $this->warn('No active materials with a product URL found.');

            return self::SUCCESS;
        }

        $fetcher = (string) config('materials.scraper.fetcher', 'http');
        $hdStore = trim((string) config('materials.scraper.homedepot_store_id', ''));
        $this->info(sprintf(
            'Syncing %d material(s). Fetcher: %s. HD store: %s. %s',
            $materials->count(),
            $fetcher,
            $hdStore !== '' ? $hdStore : 'not set',
            $dryRun ? '[DRY RUN]' : '[LIVE]'
        ));

        if ($hdStore === '') {
            $this->line('Tip: set MATERIALS_SCRAPER_HD_STORE_ID=7263 (Carleton Place) for correct Home Depot shelf prices.');
        }

        $rows = [];
        $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));

        foreach ($materials as $index => $material) {
            if ($index > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $result = $syncService->syncOne($material, $dryRun);
            $parsed = $result['parsed'];
            $newPrice = $parsed?->priceCad;

            $rows[] = [
                $material->id,
                $material->name,
                number_format($result['old_price'], 2),
                $newPrice !== null ? number_format($newPrice, 2) : '—',
                $result['status'],
                $parsed?->source ?? '—',
                $result['error'] ?? '',
            ];
        }

        $this->table(
            ['ID', 'Name', 'Old CAD', 'New CAD', 'Status', 'Parser', 'Error'],
            $rows
        );

        $ok = collect($rows)->where(4, Material::SCRAPE_OK)->count();
        $failed = collect($rows)->where(4, Material::SCRAPE_FAILED)->count();
        $skipped = collect($rows)->where(4, Material::SCRAPE_SKIPPED)->count();

        $this->newLine();
        $this->info("Done: {$ok} ok, {$failed} failed, {$skipped} skipped.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

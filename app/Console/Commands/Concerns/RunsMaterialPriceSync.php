<?php

namespace App\Console\Commands\Concerns;

use App\Models\Material;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

trait RunsMaterialPriceSync
{
    protected function runMaterialPriceSync(
        MaterialPriceSyncService $syncService,
        bool $dryRun,
        ?int $id,
        ?string $vendor,
        bool $unsyncedOnly,
    ): int {
        $materials = $syncService->materialsToSync($id, $vendor, $unsyncedOnly);

        if ($materials->isEmpty()) {
            $this->warn($unsyncedOnly
                ? 'No active Home Depot materials need a first-time or retry sync.'
                : 'No active Home Depot materials with a product URL found.');

            return Command::SUCCESS;
        }

        $fetcher = (string) config('materials.scraper.fetcher', 'http');
        $hdStore = trim((string) config('materials.scraper.homedepot_store_id', ''));
        $via = filled(config('materials.scraper.serpapi_key'))
            ? 'SerpApi'
            : 'Home Depot FBT store API (local PC)';

        $scope = $unsyncedOnly ? ' (unsynced / failed only)' : '';
        $total = $materials->count();
        $this->info(sprintf(
            'Syncing %d material(s)%s. Backend: %s. Fetcher: %s. HD store: %s. %s',
            $total,
            $scope,
            $via,
            $fetcher,
            $hdStore !== '' ? $hdStore : 'not set',
            $dryRun ? '[DRY RUN]' : '[LIVE]'
        ));

        if ($hdStore === '') {
            $this->line('Tip: set MATERIALS_SCRAPER_HD_STORE_ID=7263 (Carleton Place) for correct Home Depot shelf prices.');
        }

        if (filled(config('materials.scraper.serpapi_key'))) {
            $this->warn('SerpApi key is set — each SKU uses one SerpApi search credit. Omit MATERIALS_SCRAPER_SERPAPI_KEY on your PC to use free local FBT sync.');
        }

        $this->newLine();

        $rows = [];
        $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));

        foreach ($materials as $index => $material) {
            if ($index > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $current = $index + 1;
            $this->output->write(sprintf(
                "\r[%d/%d] Syncing: %s",
                $current,
                $total,
                Str::limit((string) $material->name, 55)
            ));

            $result = $syncService->syncOne($material, $dryRun);
            $parsed = $result['parsed'];
            $newPrice = $parsed?->priceCad;

            $progress = sprintf(
                '[%d/%d] %s — %s',
                $current,
                $total,
                Str::limit((string) $material->name, 42),
                $result['status']
            );
            if ($newPrice !== null) {
                $progress .= ' ($'.number_format($newPrice, 2).')';
            }
            if (filled($result['error'] ?? null)) {
                $progress .= ' — '.Str::limit((string) $result['error'], 70);
            }
            $this->output->writeln($progress);

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

        $this->newLine();
        $this->table(
            ['ID', 'Name', 'Old CAD', 'New CAD', 'Status', 'Parser', 'Error'],
            $rows
        );

        $ok = collect($rows)->where(4, Material::SCRAPE_OK)->count();
        $failed = collect($rows)->where(4, Material::SCRAPE_FAILED)->count();
        $skipped = collect($rows)->where(4, Material::SCRAPE_SKIPPED)->count();

        $this->newLine();
        $this->info("Done: {$ok} ok, {$failed} failed, {$skipped} skipped ({$total} total).");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

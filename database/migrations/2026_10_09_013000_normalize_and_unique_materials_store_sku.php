<?php

use App\Services\MaterialScraping\HomeDepotCaUrls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $report = $this->normalizeStoreSkus();
        $report = array_merge($report, $this->resolveDuplicateStoreSkus());

        foreach ($report as $line) {
            Log::info('materials.store_sku.unique: '.$line);
            if (app()->runningInConsole()) {
                echo $line.PHP_EOL;
            }
        }

        if ($this->indexExists('materials', 'materials_store_sku_index')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropIndex('materials_store_sku_index');
            });
        }

        if (! $this->indexExists('materials', 'materials_store_sku_unique')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->unique('store_sku');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('materials', 'materials_store_sku_unique')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropUnique('materials_store_sku_unique');
            });
        }

        if (! $this->indexExists('materials', 'materials_store_sku_index')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->index('store_sku');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }

    /**
     * @return list<string>
     */
    private function normalizeStoreSkus(): array
    {
        $report = [];
        $rows = DB::table('materials')->select('id', 'store_sku')->get();

        foreach ($rows as $row) {
            $original = $row->store_sku;
            $normalized = HomeDepotCaUrls::normalizeStoredSku($original);

            if ($original === $normalized) {
                continue;
            }

            DB::table('materials')->where('id', $row->id)->update(['store_sku' => $normalized]);
            $report[] = sprintf(
                'Normalized material #%d store_sku %s → %s',
                $row->id,
                $this->skuLabel($original),
                $this->skuLabel($normalized)
            );
        }

        return $report;
    }

    /**
     * Keep one row per store_sku. Losers keep their row (quotes still reference them)
     * but lose the duplicate SKU so a unique index can be added.
     *
     * @return list<string>
     */
    private function resolveDuplicateStoreSkus(): array
    {
        $report = [];
        $groups = DB::table('materials')
            ->select('store_sku', DB::raw('COUNT(*) as total'))
            ->whereNotNull('store_sku')
            ->where('store_sku', '!=', '')
            ->groupBy('store_sku')
            ->having('total', '>', 1)
            ->pluck('total', 'store_sku');

        foreach ($groups as $sku => $total) {
            $rows = DB::table('materials')
                ->where('store_sku', $sku)
                ->orderByDesc('is_active')
                ->orderByDesc('updated_at')
                ->orderBy('id')
                ->get();

            $keeper = $this->chooseKeeper($rows);
            $report[] = sprintf(
                'Duplicate store_sku %s (%d rows); keeping material #%d',
                $sku,
                $rows->count(),
                $keeper->id
            );

            foreach ($rows as $loser) {
                if ((int) $loser->id === (int) $keeper->id) {
                    continue;
                }

                $refs = $this->repointReferences((int) $loser->id, (int) $keeper->id);
                DB::table('materials')->where('id', $loser->id)->update(['store_sku' => null]);
                $report[] = sprintf(
                    'Cleared store_sku on material #%d (referenced rows remounted: %s); row kept',
                    $loser->id,
                    $refs === [] ? 'none' : implode(', ', $refs)
                );
            }
        }

        return $report;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function chooseKeeper($rows): object
    {
        $scored = $rows->map(function ($row) {
            $name = trim((string) ($row->name ?? ''));
            $sku = trim((string) ($row->store_sku ?? ''));
            $isStub = $sku !== '' && strcasecmp($name, 'HD '.$sku) === 0;
            $ready = (int) ($row->is_active ?? 0) === 1
                && $name !== ''
                && ! $isStub
                && is_numeric($row->price_cad ?? null);
            $refs = $this->referenceCount((int) $row->id);

            return [
                'row' => $row,
                'refs' => $refs,
                'active' => (int) ($row->is_active ?? 0),
                'ready' => $ready ? 1 : 0,
            ];
        })->sort(function ($a, $b) {
            return [$b['refs'], $b['active'], $b['ready'], $b['row']->id]
                <=> [$a['refs'], $a['active'], $a['ready'], $a['row']->id];
        })->values();

        return $scored->first()['row'];
    }

    /**
     * @return list<string>
     */
    private function repointReferences(int $fromId, int $toId): array
    {
        $moved = [];

        if (Schema::hasTable('fence_materials')) {
            $n = DB::table('fence_materials')->where('material_id', $fromId)->update(['material_id' => $toId]);
            if ($n > 0) {
                $moved[] = 'fence_materials:'.$n;
            }
        }

        if (Schema::hasTable('low_deck_materials')) {
            $n = DB::table('low_deck_materials')->where('material_id', $fromId)->update(['material_id' => $toId]);
            if ($n > 0) {
                $moved[] = 'low_deck_materials:'.$n;
            }
        }

        if (Schema::hasTable('shared_works')) {
            $n = DB::table('shared_works')->where('catalog_material_id', $fromId)->update(['catalog_material_id' => $toId]);
            if ($n > 0) {
                $moved[] = 'shared_works:'.$n;
            }
        }

        return $moved;
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

    private function skuLabel(mixed $sku): string
    {
        return $sku === null || $sku === '' ? 'NULL' : (string) $sku;
    }
};

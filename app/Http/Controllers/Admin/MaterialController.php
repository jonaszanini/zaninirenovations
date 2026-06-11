<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\MaterialSkuImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MaterialController extends Controller
{
    private const BULK_IMPORT_SESSION_PREFIX = 'material_bulk_import.';

    private const SYNC_ALL_SESSION_PREFIX = 'material_sync_all.';

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $category = trim((string) $request->get('category', ''));

        $materials = Material::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%')
                        ->orWhere('store_sku', 'like', '%'.$q.'%')
                        ->orWhere('model', 'like', '%'.$q.'%')
                        ->orWhere('vendor', 'like', '%'.$q.'%');
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = Material::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $totalMaterials = Material::query()->count();
        $syncableCount = Material::query()
            ->where('is_active', true)
            ->whereNotNull('product_url')
            ->where('product_url', '!=', '')
            ->count();

        $usesSerpApi = (new HomeDepotCaLocalizedPriceFetcher)->usesSerpApi();
        $needsSyncCount = app(MaterialPriceSyncService::class)->countNeedingSync();

        return view('admin.materials.index', compact(
            'materials',
            'q',
            'category',
            'categories',
            'totalMaterials',
            'syncableCount',
            'needsSyncCount',
            'usesSerpApi',
        ));
    }

    public function config(): View
    {
        return view('admin.materials.config', $this->materialsConfigData());
    }

    public function destroyFailed()
    {
        $deleted = Material::query()
            ->where('last_scrape_status', Material::SCRAPE_FAILED)
            ->delete();

        if ($deleted === 0) {
            return redirect()
                ->route('admin.materials.config')
                ->with('error', 'No materials with a failed sync status to delete.');
        }

        return redirect()
            ->route('admin.materials.config')
            ->with('success', $deleted === 1
                ? '1 failed material deleted.'
                : $deleted.' failed materials deleted.');
    }

    public function create(): View
    {
        return view('admin.materials.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $material = Material::create($data);

        return redirect()
            ->route('admin.materials.edit', $material)
            ->with('success', 'Material created.');
    }

    public function edit(Material $material): View
    {
        return view('admin.materials.edit', compact('material'));
    }

    public function syncPrice(Material $material, MaterialPriceSyncService $syncService, HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher)
    {
        if (! $material->hasProductUrl()) {
            return redirect()
                ->back()
                ->with('error', 'Add a product URL before syncing price.');
        }

        $result = $syncService->syncOne($material);
        $material->refresh();

        if ($result['status'] === Material::SCRAPE_OK) {
            return redirect()
                ->back()
                ->with('success', 'Price synced — '.$material->name.' is now '.$material->formattedPrice().'.');
        }

        if ($result['status'] === Material::SCRAPE_SKIPPED) {
            return redirect()
                ->back()
                ->with('error', $result['error'] ?? 'Sync skipped.');
        }

        $error = $result['error'] ?? 'Could not sync price from the store.';
        if ($result['status'] !== Material::SCRAPE_OK && ! $localizedPriceFetcher->usesSerpApi() && str_contains($error, 'Akamai')) {
            $error .= ' From your PC: php artisan materials:sync-prices --id='.$material->id;
        }

        return redirect()
            ->back()
            ->with('error', $error);
    }

    public function update(Request $request, Material $material)
    {
        $material->update($this->validated($request));

        return redirect()
            ->route('admin.materials.edit', $material)
            ->with('success', 'Material saved.');
    }

    public function destroy(Material $material)
    {
        $material->delete();

        return redirect()
            ->route('admin.materials.index')
            ->with('success', 'Material deleted.');
    }

    public function syncAllStart(Request $request, MaterialPriceSyncService $syncService, HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher): JsonResponse
    {
        $materialIds = $syncService->materialsToSync()->pluck('id')->all();

        if ($materialIds === []) {
            return response()->json([
                'message' => 'No active materials with a product URL to sync.',
            ], 422);
        }

        $token = Str::random(40);
        $total = count($materialIds);

        session()->put(self::SYNC_ALL_SESSION_PREFIX.$token, [
            'user_id' => $request->user()->id,
            'material_ids' => $materialIds,
            'index' => 0,
            'ok' => 0,
            'failed' => 0,
            'skipped' => 0,
            'log' => [],
        ]);

        $via = $localizedPriceFetcher->usesSerpApi() ? 'SerpApi' : 'Home Depot store API';

        return response()->json([
            'token' => $token,
            'phase' => 'sync',
            'phase_label' => 'Syncing prices',
            'current' => 0,
            'phase_total' => $total,
            'remaining' => $total,
            'message' => 'Starting sync of '.$total.' material(s) via '.$via.'.',
            'stats' => $this->syncAllStats(['ok' => 0, 'failed' => 0, 'skipped' => 0]),
            'log' => [],
            'done' => false,
        ]);
    }

    public function syncAllStep(Request $request, MaterialPriceSyncService $syncService): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $sessionKey = self::SYNC_ALL_SESSION_PREFIX.$data['token'];
        $state = session($sessionKey);

        if (! is_array($state) || (int) ($state['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Sync session expired or invalid. Please start again.',
            ], 404);
        }

        $total = count($state['material_ids']);

        if ($state['index'] >= $total) {
            session()->forget($sessionKey);

            return $this->syncAllProgressResponse($state, 'Sync complete.', true);
        }

        if ($state['index'] > 0) {
            $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $materialId = $state['material_ids'][$state['index']];
        $material = Material::query()->find($materialId);

        if ($material === null) {
            $state['failed']++;
            $message = 'Material #'.$materialId.' not found.';
        } else {
            $result = $syncService->syncOne($material);
            $material->refresh();

            if ($result['status'] === Material::SCRAPE_OK) {
                $state['ok']++;
                $message = 'Synced '.$material->name.' — '.$material->formattedPrice();
            } elseif ($result['status'] === Material::SCRAPE_SKIPPED) {
                $state['skipped']++;
                $message = 'Skipped '.$material->name.': '.($result['error'] ?? 'no product URL');
            } else {
                $state['failed']++;
                $message = 'Failed '.$material->name.': '.($result['error'] ?? 'unknown error');
            }
        }

        $state['index']++;
        $state = $this->bulkImportAppendLog($state, $message);

        $done = $state['index'] >= $total;

        if ($done) {
            session()->forget($sessionKey);
        } else {
            session()->put($sessionKey, $state);
        }

        return $this->syncAllProgressResponse($state, $message, $done);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:materials,id'],
        ]);

        $deleted = Material::query()->whereIn('id', $data['ids'])->delete();

        return redirect()
            ->back()
            ->with('success', $deleted === 1 ? '1 material deleted.' : $deleted.' materials deleted.');
    }

    public function bulkImportStart(Request $request, MaterialSkuImportService $importer): JsonResponse
    {
        $data = $request->validate([
            'skus' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'sync_prices' => ['sometimes', 'boolean'],
        ]);

        $plan = $importer->buildPlan($data['skus']);

        if ($plan['skus'] === []) {
            return response()->json([
                'message' => 'Paste at least one valid Home Depot SKU or product URL. Blank lines are ignored; duplicate lines in the same paste are skipped.',
            ], 422);
        }

        $token = Str::random(40);
        $syncPrices = $request->boolean('sync_prices');
        $importTotal = count($plan['skus']);

        $state = [
            'user_id' => $request->user()->id,
            'skus' => $plan['skus'],
            'category' => $data['category'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'sync_prices' => $syncPrices,
            'import_index' => 0,
            'sync_index' => 0,
            'material_ids' => [],
            'created' => 0,
            'updated' => 0,
            'sync_ok' => 0,
            'sync_failed' => 0,
            'blank_lines' => $plan['blank_lines'],
            'duplicate_lines' => $plan['duplicate_lines'],
            'invalid_lines' => $plan['invalid_lines'],
            'log' => [],
        ];

        session()->put(self::BULK_IMPORT_SESSION_PREFIX.$token, $state);

        $overallTotal = $importTotal + ($syncPrices ? $importTotal : 0);
        $skipNote = $this->bulkImportSkipNote($state);

        return response()->json([
            'token' => $token,
            'phase' => 'import',
            'phase_label' => 'Importing SKUs',
            'current' => 0,
            'phase_total' => $importTotal,
            'overall_current' => 0,
            'overall_total' => $overallTotal,
            'total_import' => $importTotal,
            'total_sync' => $syncPrices ? $importTotal : 0,
            'message' => sprintf(
                'Starting import of %d SKU(s)%s.',
                $importTotal,
                $syncPrices ? ', then syncing prices' : ''
            ),
            'stats' => $this->bulkImportStats($state),
            'skip_note' => $skipNote,
            'log' => [],
            'done' => false,
        ]);
    }

    public function bulkImportStep(
        Request $request,
        MaterialSkuImportService $importer,
        MaterialPriceSyncService $syncService,
    ): JsonResponse {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $sessionKey = self::BULK_IMPORT_SESSION_PREFIX.$data['token'];
        $state = session($sessionKey);

        if (! is_array($state) || (int) ($state['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Import session expired or invalid. Please start again.',
            ], 404);
        }

        $importTotal = count($state['skus']);

        if ($state['import_index'] < $importTotal) {
            $sku = $state['skus'][$state['import_index']];
            $result = $importer->importSku($sku, $state['category'], $state['vendor']);

            $state['material_ids'][] = $result['material_id'];
            if ($result['action'] === 'created') {
                $state['created']++;
            } else {
                $state['updated']++;
            }
            $state['import_index']++;

            $message = $result['action'] === 'created'
                ? 'Created material for SKU '.$sku
                : 'Updated existing material for SKU '.$sku;

            $state = $this->bulkImportAppendLog($state, $message);

            $importDone = $state['import_index'] >= $importTotal;
            $done = $importDone && ! $state['sync_prices'];

            if ($done) {
                session()->forget($sessionKey);
            } else {
                session()->put($sessionKey, $state);
            }

            return $this->bulkImportProgressResponse($state, $message, $done);
        }

        if ($state['sync_prices'] && $state['sync_index'] < count($state['material_ids'])) {
            if ($state['sync_index'] > 0) {
                $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }

            $materialId = $state['material_ids'][$state['sync_index']];
            $material = Material::query()->find($materialId);

            if ($material !== null) {
                $syncResult = $syncService->syncOne($material);
                $material->refresh();

                if ($syncResult['status'] === Material::SCRAPE_OK) {
                    $state['sync_ok']++;
                    $message = 'Synced price for '.$material->name.' (SKU '.($material->store_sku ?: '—').')';
                } else {
                    $state['sync_failed']++;
                    $message = 'Sync failed for '.$material->name.': '.($syncResult['error'] ?? 'unknown error');
                }
            } else {
                $state['sync_failed']++;
                $message = 'Material #'.$materialId.' not found for sync.';
            }

            $state['sync_index']++;
            $state = $this->bulkImportAppendLog($state, $message);
            session()->put($sessionKey, $state);

            $done = $state['sync_index'] >= count($state['material_ids']);

            if ($done) {
                session()->forget($sessionKey);
            }

            return $this->bulkImportProgressResponse($state, $message, $done);
        }

        session()->forget($sessionKey);

        return $this->bulkImportProgressResponse($state, 'Import complete.', true);
    }

    public function bulkImport(
        Request $request,
        MaterialSkuImportService $importer,
        MaterialPriceSyncService $syncService,
    ) {
        $data = $request->validate([
            'skus' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'sync_prices' => ['sometimes', 'boolean'],
        ]);

        if (! $importer->hasImportableSku($data['skus'])) {
            return redirect()
                ->route('admin.materials.config')
                ->withErrors([
                    'skus' => 'Paste at least one valid Home Depot SKU or product URL. Blank lines are ignored; duplicate lines in the same paste are skipped.',
                ])
                ->withInput($request->only(['skus', 'category', 'vendor']));
        }

        $result = $importer->import(
            $data['skus'],
            $data['category'] ?? null,
            $data['vendor'] ?? null,
        );

        if ($result['created'] === 0 && $result['updated'] === 0) {
            $detail = collect([
                $result['duplicate_lines'] > 0 ? $result['duplicate_lines'].' duplicate line(s)' : null,
                $result['invalid_lines'] > 0 ? $result['invalid_lines'].' invalid line(s)' : null,
            ])->filter()->implode(', ');

            return redirect()
                ->route('admin.materials.config')
                ->withErrors([
                    'skus' => $detail !== ''
                        ? 'No materials imported — '.$detail.' skipped.'
                        : 'No materials imported.',
                ])
                ->withInput($request->only(['skus', 'category', 'vendor']));
        }

        $message = sprintf(
            '%d created, %d updated',
            $result['created'],
            $result['updated'],
        );

        $skipParts = [];
        if ($result['blank_lines'] > 0) {
            $skipParts[] = $result['blank_lines'].' blank line(s) ignored';
        }
        if ($result['duplicate_lines'] > 0) {
            $skipParts[] = $result['duplicate_lines'].' duplicate line(s) skipped';
        }
        if ($result['invalid_lines'] > 0) {
            $skipParts[] = $result['invalid_lines'].' invalid line(s) skipped';
        }
        if ($skipParts !== []) {
            $message .= '. '.ucfirst(implode(', ', $skipParts)).'.';
        } else {
            $message .= '.';
        }

        if ($request->boolean('sync_prices') && $result['material_ids'] !== []) {
            $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));
            $ok = 0;
            $failed = 0;

            foreach ($result['material_ids'] as $index => $materialId) {
                if ($index > 0 && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                $material = Material::query()->find($materialId);
                if ($material === null) {
                    continue;
                }

                $syncResult = $syncService->syncOne($material);
                if ($syncResult['status'] === Material::SCRAPE_OK) {
                    $ok++;
                } else {
                    $failed++;
                }
            }

            $message .= sprintf(' Sync: %d ok, %d failed.', $ok, $failed);
        }

        return redirect()
            ->route('admin.materials.config')
            ->with('success', $message)
            ->withInput($request->only(['skus', 'category', 'vendor']));
    }

    /**
     * @return array<string, mixed>
     */
    private function materialsConfigData(): array
    {
        $categories = Material::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return [
            'categories' => $categories,
            'usesSerpApi' => (new HomeDepotCaLocalizedPriceFetcher)->usesSerpApi(),
            'needsSyncCount' => app(MaterialPriceSyncService::class)->countNeedingSync(),
            'syncableCount' => Material::query()
                ->where('is_active', true)
                ->whereNotNull('product_url')
                ->where('product_url', '!=', '')
                ->count(),
            'totalMaterials' => Material::query()->count(),
            'failedCount' => Material::query()
                ->where('last_scrape_status', Material::SCRAPE_FAILED)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'store_sku' => ['nullable', 'string', 'max:160'],
            'model' => ['nullable', 'string', 'max:160'],
            'price_cad' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'product_url' => ['nullable', 'url', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'unit' => ['nullable', 'string', 'max:40'],
            'quantity_per_package' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        $data['price_cad'] = (float) ($data['price_cad'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        foreach (['description', 'category', 'vendor', 'store_sku', 'model', 'product_url', 'image_url', 'unit'] as $nullable) {
            if (array_key_exists($nullable, $data) && ! filled($data[$nullable])) {
                $data[$nullable] = null;
            }
        }

        if (! filled($data['quantity_per_package'] ?? null)) {
            $data['quantity_per_package'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int>
     */
    private function bulkImportStats(array $state): array
    {
        return [
            'created' => (int) ($state['created'] ?? 0),
            'updated' => (int) ($state['updated'] ?? 0),
            'sync_ok' => (int) ($state['sync_ok'] ?? 0),
            'sync_failed' => (int) ($state['sync_failed'] ?? 0),
            'blank_lines' => (int) ($state['blank_lines'] ?? 0),
            'duplicate_lines' => (int) ($state['duplicate_lines'] ?? 0),
            'invalid_lines' => (int) ($state['invalid_lines'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function bulkImportSkipNote(array $state): ?string
    {
        $parts = [];
        if (($state['blank_lines'] ?? 0) > 0) {
            $parts[] = $state['blank_lines'].' blank line(s) will be ignored';
        }
        if (($state['duplicate_lines'] ?? 0) > 0) {
            $parts[] = $state['duplicate_lines'].' duplicate line(s) will be skipped';
        }
        if (($state['invalid_lines'] ?? 0) > 0) {
            $parts[] = $state['invalid_lines'].' invalid line(s) will be skipped';
        }

        return $parts !== [] ? ucfirst(implode(', ', $parts)).'.' : null;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function bulkImportAppendLog(array $state, string $message): array
    {
        $log = is_array($state['log'] ?? null) ? $state['log'] : [];
        $log[] = $message;
        $state['log'] = array_slice($log, -30);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function bulkImportProgressResponse(array $state, string $message, bool $done): JsonResponse
    {
        $importTotal = count($state['skus']);
        $syncTotal = $state['sync_prices'] ? $importTotal : 0;
        $overallTotal = $importTotal + $syncTotal;

        if ($done) {
            $phase = 'done';
            $phaseLabel = 'Complete';
            $current = $state['sync_prices'] ? $syncTotal : $importTotal;
            $phaseTotal = $state['sync_prices'] ? $syncTotal : $importTotal;
            $overallCurrent = $overallTotal;
        } elseif ($state['import_index'] < $importTotal) {
            $phase = 'import';
            $phaseLabel = 'Importing SKUs';
            $current = $state['import_index'];
            $phaseTotal = $importTotal;
            $overallCurrent = $current;
        } else {
            $phase = 'sync';
            $phaseLabel = 'Syncing prices';
            $current = $state['sync_index'];
            $phaseTotal = $syncTotal;
            $overallCurrent = $importTotal + $current;
        }

        return response()->json([
            'done' => $done,
            'phase' => $phase,
            'phase_label' => $phaseLabel,
            'current' => $current,
            'phase_total' => $phaseTotal,
            'overall_current' => $overallCurrent,
            'overall_total' => $overallTotal,
            'message' => $message,
            'stats' => $this->bulkImportStats($state),
            'log' => $state['log'] ?? [],
            'summary' => $done ? $this->bulkImportSummary($state) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function bulkImportSummary(array $state): string
    {
        $message = sprintf(
            '%d created, %d updated',
            (int) $state['created'],
            (int) $state['updated'],
        );

        $skipParts = [];
        if (($state['blank_lines'] ?? 0) > 0) {
            $skipParts[] = $state['blank_lines'].' blank line(s) ignored';
        }
        if (($state['duplicate_lines'] ?? 0) > 0) {
            $skipParts[] = $state['duplicate_lines'].' duplicate line(s) skipped';
        }
        if (($state['invalid_lines'] ?? 0) > 0) {
            $skipParts[] = $state['invalid_lines'].' invalid line(s) skipped';
        }
        if ($skipParts !== []) {
            $message .= '. '.ucfirst(implode(', ', $skipParts)).'.';
        } else {
            $message .= '.';
        }

        if ($state['sync_prices']) {
            $message .= sprintf(
                ' Sync: %d ok, %d failed.',
                (int) $state['sync_ok'],
                (int) $state['sync_failed'],
            );
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int>
     */
    private function syncAllStats(array $state): array
    {
        return [
            'ok' => (int) ($state['ok'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
            'skipped' => (int) ($state['skipped'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function syncAllProgressResponse(array $state, string $message, bool $done): JsonResponse
    {
        $total = count($state['material_ids']);
        $current = (int) $state['index'];
        $remaining = max(0, $total - $current);

        return response()->json([
            'done' => $done,
            'phase' => $done ? 'done' : 'sync',
            'phase_label' => $done ? 'Complete' : 'Syncing prices',
            'current' => $current,
            'phase_total' => $total,
            'remaining' => $remaining,
            'message' => $message,
            'stats' => $this->syncAllStats($state),
            'log' => $state['log'] ?? [],
            'summary' => $done ? $this->syncAllSummary($state) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function syncAllSummary(array $state): string
    {
        return sprintf(
            '%d synced, %d failed, %d skipped. %d of %d complete.',
            (int) $state['ok'],
            (int) $state['failed'],
            (int) $state['skipped'],
            (int) $state['index'],
            count($state['material_ids']),
        );
    }
}

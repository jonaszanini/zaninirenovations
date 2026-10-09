<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialVendor;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use App\Services\MaterialScraping\MaterialCatalogHomeDepotPullService;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\MaterialSkuImportService;
use App\Services\MaterialShortNameAiService;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MaterialController extends Controller
{
    private const BULK_IMPORT_SESSION_PREFIX = 'material_bulk_import.';

    private const SYNC_ALL_SESSION_PREFIX = 'material_sync_all.';

    private const AI_SHORT_NAMES_SESSION_PREFIX = 'material_ai_short_names.';

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $categoryId = $request->filled('category') ? (int) $request->get('category') : null;

        $materials = Material::query()
            ->with(['vendor', 'category'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('short_name', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%')
                        ->orWhere('store_sku', 'like', '%'.$q.'%')
                        ->orWhere('model', 'like', '%'.$q.'%')
                        ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->when($categoryId !== null && $categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = $this->categoriesForSelect();

        $totalMaterials = Material::query()->count();
        $syncService = app(MaterialPriceSyncService::class);
        $syncableCount = $syncService->countSerpApiSyncable();
        $homeDepotSyncableCount = Material::query()->homeDepotSyncable()->count();
        $amazonSyncableCount = Material::query()->amazonSyncable()->count();

        $usesSerpApi = (new HomeDepotCaLocalizedPriceFetcher)->usesSerpApi();
        $needsSyncCount = $syncService->countSerpApiNeedingSync();

        return view('admin.materials.index', compact(
            'materials',
            'q',
            'categoryId',
            'categories',
            'totalMaterials',
            'syncableCount',
            'homeDepotSyncableCount',
            'amazonSyncableCount',
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
        return view('admin.materials.create', [
            'vendors' => $this->vendorsForSelect(),
            'categories' => $this->categoriesForSelect(),
        ]);
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
        [$previousMaterial, $nextMaterial, $position, $total] = $this->adjacentMaterials($material);

        return view('admin.materials.edit', [
            'material' => $material->load('category'),
            'vendors' => $this->vendorsForSelect(),
            'categories' => $this->categoriesForSelect(),
            'previousMaterial' => $previousMaterial,
            'nextMaterial' => $nextMaterial,
            'materialPosition' => $position,
            'materialTotal' => $total,
        ]);
    }

    /**
     * Previous / next materials in the same order as the materials list
     * (sort_order, name, id).
     *
     * @return array{0: ?Material, 1: ?Material, 2: int, 3: int}
     */
    private function adjacentMaterials(Material $material): array
    {
        $ids = Material::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id');

        $total = $ids->count();
        $index = $ids->search(fn ($id) => (int) $id === (int) $material->id);

        if ($index === false) {
            return [null, null, 0, $total];
        }

        $previousId = $index > 0 ? (int) $ids[$index - 1] : null;
        $nextId = $index < $total - 1 ? (int) $ids[$index + 1] : null;

        return [
            $previousId ? Material::query()->find($previousId) : null,
            $nextId ? Material::query()->find($nextId) : null,
            $index + 1,
            $total,
        ];
    }

    public function syncPrice(Material $material, MaterialPriceSyncService $syncService, HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher)
    {
        if (! $material->isSerpApiSyncable()) {
            return redirect()
                ->back()
                ->with('error', 'Sync requires Home Depot (vendor + homedepot.ca URL) or Amazon (vendor + ASIN in Store SKU).');
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
        $material->update($this->validated($request, $material));

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

    public function syncAllStart(Request $request, HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:materials,id'],
        ]);

        $selectedIds = array_values(array_unique(array_map('intval', $data['ids'] ?? [])));
        $selectedOnly = $selectedIds !== [];

        $query = Material::query()
            ->serpApiSyncable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');

        if ($selectedOnly) {
            $query->whereIn('id', $selectedIds);
        }

        $materialIds = $query->pluck('id')->all();

        if ($materialIds === []) {
            return response()->json([
                'message' => $selectedOnly
                    ? 'None of the selected materials are eligible for price sync (need Home Depot + URL, or Amazon + ASIN).'
                    : 'No active Home Depot or Amazon materials eligible for price sync.',
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
        $scope = $selectedOnly ? 'selected' : 'all eligible';

        return response()->json([
            'token' => $token,
            'phase' => 'sync',
            'phase_label' => 'Syncing prices',
            'current' => 0,
            'phase_total' => $total,
            'remaining' => $total,
            'message' => 'Starting sync of '.$total.' '.$scope.' material(s) via '.$via.'.',
            'stats' => $this->syncAllStats(['ok' => 0, 'failed' => 0, 'skipped' => 0]),
            'log' => [],
            'done' => false,
        ]);
    }

    public function syncAllStep(Request $request, MaterialPriceSyncService $syncService): JsonResponse
    {
        // Shared hosting (Hostinger) returns HTML 503 pages when PHP workers
        // stay busy too long. Keep each step short — pacing lives in the browser.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

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

    public function aiShortNamesStart(Request $request, MaterialShortNameAiService $shortNameAi): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:missing,all'],
        ]);

        $mode = $data['mode'];
        $materialIds = $shortNameAi->materialsQuery($mode)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($materialIds === []) {
            return response()->json([
                'message' => $mode === 'missing'
                    ? 'All materials already have a short name.'
                    : 'No materials in the catalog.',
            ], 422);
        }

        $batches = $shortNameAi->buildBatches($materialIds);
        $token = Str::random(40);

        session()->put(self::AI_SHORT_NAMES_SESSION_PREFIX.$token, [
            'user_id' => $request->user()->id,
            'mode' => $mode,
            'batches' => $batches,
            'batch_index' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'log' => [],
        ]);

        $totalMaterials = count($materialIds);
        $totalBatches = count($batches);
        $modeLabel = $mode === 'all' ? 'Regenerating all short names' : 'Generating missing short names';

        return response()->json([
            'token' => $token,
            'phase' => 'ai_short_names',
            'phase_label' => $modeLabel,
            'current' => 0,
            'phase_total' => $totalBatches,
            'overall_total' => $totalMaterials,
            'overall_current' => 0,
            'remaining' => $totalBatches,
            'message' => $modeLabel.' for '.$totalMaterials.' material(s) in '.$totalBatches.' batch(es).',
            'stats' => $this->aiShortNamesStats(['updated' => 0, 'skipped' => 0, 'failed' => 0]),
            'log' => [],
            'done' => false,
        ]);
    }

    public function aiShortNamesStep(Request $request, MaterialShortNameAiService $shortNameAi): JsonResponse
    {
        set_time_limit(max(120, (int) config('gemini.timeout_seconds', 120) + 30));

        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $sessionKey = self::AI_SHORT_NAMES_SESSION_PREFIX.$data['token'];
        $state = session($sessionKey);

        if (! is_array($state) || (int) ($state['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Short name session expired or invalid. Please start again.',
            ], 404);
        }

        $batches = $state['batches'] ?? [];
        $batchIndex = (int) ($state['batch_index'] ?? 0);
        $totalBatches = count($batches);

        if ($batchIndex >= $totalBatches) {
            session()->forget($sessionKey);

            return $this->aiShortNamesProgressResponse($state, 'Short name generation complete.', true);
        }

        $batchIds = $batches[$batchIndex];
        $materials = Material::query()->whereIn('id', $batchIds)->orderBy('id')->get()->all();
        $overwrite = ($state['mode'] ?? 'missing') === 'all';

        try {
            $result = $shortNameAi->generateAndApplyBatch($materials, $overwrite);
            $state['updated'] += $result['updated'];
            $state['skipped'] += $result['skipped'];

            foreach ($result['results'] as $row) {
                $state = $this->bulkImportAppendLog(
                    $state,
                    'Updated #'.$row['id'].' → '.$row['short_name'],
                );
            }

            if ($result['updated'] === 0 && $result['skipped'] > 0) {
                $state = $this->bulkImportAppendLog($state, 'Batch '.($batchIndex + 1).': no changes needed.');
            }

            $message = 'Batch '.($batchIndex + 1).' of '.$totalBatches.': '.$result['updated'].' updated, '.$result['skipped'].' skipped.';
        } catch (\Throwable $e) {
            $state['failed']++;
            $message = 'Batch '.($batchIndex + 1).' failed: '.$e->getMessage();
            $state = $this->bulkImportAppendLog($state, $message);
        }

        $state['batch_index'] = $batchIndex + 1;
        $done = $state['batch_index'] >= $totalBatches;

        if ($done) {
            session()->forget($sessionKey);
        } else {
            session()->put($sessionKey, $state);
        }

        return $this->aiShortNamesProgressResponse($state, $message, $done);
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

    public function importFromHomeDepot(
        Request $request,
        MaterialCatalogHomeDepotPullService $puller,
    ): JsonResponse {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
        ]);

        $result = $puller->pull($data['query']);
        $payload = ['message' => $result['message']];

        if ($result['material'] !== null) {
            $payload['created'] = $result['created'];
            $payload['material'] = $this->catalogPickerOption($result['material']);
        }

        return response()->json($payload, $result['status']);
    }

    public function bulkImportStart(Request $request, MaterialSkuImportService $importer): JsonResponse
    {
        $data = $request->validate([
            'skus' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
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
            'category_id' => filled($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
            'vendor_id' => MaterialVendor::defaultForHomeDepotImport()?->id,
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
            $result = $importer->importSku($sku, $state['category_id'] ?? null, $state['vendor_id'] ?? null);

            $state['import_index']++;

            if ($result['action'] === 'skipped') {
                $reason = $result['reason'] ?? 'inactive';
                $message = 'skipped: '.$reason.' (SKU '.$sku.')';
            } elseif ($result['action'] === 'created') {
                $state['material_ids'][] = $result['material_id'];
                $state['created']++;
                $message = 'Created material for SKU '.$sku;
            } else {
                $state['material_ids'][] = $result['material_id'];
                $state['updated']++;
                $message = 'Updated existing material for SKU '.$sku;
            }

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
            'category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'sync_prices' => ['sometimes', 'boolean'],
        ]);

        if (! $importer->hasImportableSku($data['skus'])) {
            return redirect()
                ->route('admin.materials.config')
                ->withErrors([
                    'skus' => 'Paste at least one valid Home Depot SKU or product URL. Blank lines are ignored; duplicate lines in the same paste are skipped.',
                ])
                ->withInput($request->only(['skus', 'category_id']));
        }

        $result = $importer->import(
            $data['skus'],
            filled($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
            MaterialVendor::defaultForHomeDepotImport()?->id,
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
                ->withInput($request->only(['skus', 'category_id']));
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
            ->withInput($request->only(['skus', 'category_id']));
    }

    /**
     * @return array<string, mixed>
     */
    private function materialsConfigData(): array
    {
        return [
            'materialCategories' => MaterialCategory::query()
                ->withCount('materials')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'categories' => $this->categoriesForSelect(),
            'vendors' => MaterialVendor::query()
                ->withCount('materials')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'homeDepotVendor' => MaterialVendor::homeDepot(),
            'usesSerpApi' => (new HomeDepotCaLocalizedPriceFetcher)->usesSerpApi(),
            'needsSyncCount' => app(MaterialPriceSyncService::class)->countNeedingSync(),
            'syncableCount' => app(MaterialPriceSyncService::class)->countSerpApiSyncable(),
            'totalMaterials' => Material::query()->count(),
            'missingShortNameCount' => Material::query()
                ->where(function ($q) {
                    $q->whereNull('short_name')->orWhere('short_name', '');
                })
                ->count(),
            'failedCount' => Material::query()
                ->where('last_scrape_status', Material::SCRAPE_FAILED)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Material $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:material_vendors,id'],
            'store_sku' => ['nullable', 'string', 'max:160'],
            'model' => ['nullable', 'string', 'max:160'],
            'price_cad' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'delivery_cad' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'product_url' => ['nullable', 'url', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'unit' => ['nullable', 'string', 'max:40'],
            'quantity_per_package' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        $data['price_cad'] = (float) ($data['price_cad'] ?? 0);
        $data['delivery_cad'] = (float) ($data['delivery_cad'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        foreach (['short_name', 'description', 'store_sku', 'model', 'product_url', 'image_url', 'unit'] as $nullable) {
            if (array_key_exists($nullable, $data) && ! filled($data[$nullable])) {
                $data[$nullable] = null;
            }
        }

        $normalizedSku = HomeDepotCaUrls::normalizeStoredSku($data['store_sku'] ?? null);
        $data['store_sku'] = $normalizedSku;
        if (HomeDepotCaUrls::isValidSku($normalizedSku)) {
            $owner = Material::findByNormalizedHdSku(
                $normalizedSku,
                $current !== null ? (int) $current->id : null
            );
            if ($owner !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'store_sku' => 'Another material already uses Home Depot SKU '.$normalizedSku.'.',
                ]);
            }
        }

        if (! filled($data['vendor_id'] ?? null)) {
            $data['vendor_id'] = null;
        }

        if (! filled($data['category_id'] ?? null)) {
            $data['category_id'] = null;
        }

        if (! filled($data['quantity_per_package'] ?? null)) {
            $data['quantity_per_package'] = null;
        }

        return $data;
    }

    /**
     * @return \Illuminate\Support\Collection<int, MaterialVendor>
     */
    private function vendorsForSelect()
    {
        return MaterialVendor::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MaterialCategory>
     */
    private function categoriesForSelect()
    {
        return MaterialCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
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

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int>
     */
    private function aiShortNamesStats(array $state): array
    {
        return [
            'updated' => (int) ($state['updated'] ?? 0),
            'skipped' => (int) ($state['skipped'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function aiShortNamesProgressResponse(array $state, string $message, bool $done): JsonResponse
    {
        $batches = $state['batches'] ?? [];
        $totalBatches = count($batches);
        $currentBatch = (int) ($state['batch_index'] ?? 0);
        $remaining = max(0, $totalBatches - $currentBatch);
        $totalMaterials = array_sum(array_map('count', $batches));
        $processedMaterials = 0;
        for ($i = 0; $i < min($currentBatch, $totalBatches); $i++) {
            $processedMaterials += count($batches[$i]);
        }

        $mode = $state['mode'] ?? 'missing';
        $phaseLabel = $done
            ? 'Complete'
            : ($mode === 'all' ? 'Regenerating short names' : 'Generating short names');

        return response()->json([
            'done' => $done,
            'phase' => $done ? 'done' : 'ai_short_names',
            'phase_label' => $phaseLabel,
            'current' => $currentBatch,
            'phase_total' => $totalBatches,
            'overall_total' => $totalMaterials,
            'overall_current' => $processedMaterials,
            'remaining' => $remaining,
            'message' => $message,
            'stats' => $this->aiShortNamesStats($state),
            'log' => $state['log'] ?? [],
            'summary' => $done ? $this->aiShortNamesSummary($state) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function aiShortNamesSummary(array $state): string
    {
        $batches = $state['batches'] ?? [];
        $totalMaterials = array_sum(array_map('count', $batches));

        return sprintf(
            '%d updated, %d skipped, %d batch(es) failed. Processed %d material(s).',
            (int) ($state['updated'] ?? 0),
            (int) ($state['skipped'] ?? 0),
            (int) ($state['failed'] ?? 0),
            $totalMaterials,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogPickerOption(Material $material): array
    {
        $material->loadMissing('vendor');
        $option = MaterialCatalogPickerOptions::fromMaterials([$material])[0] ?? null;

        return is_array($option) ? $option : [
            'id' => $material->id,
            'name' => $material->displayName(),
            'sku' => trim((string) ($material->store_sku ?? '')),
        ];
    }
}

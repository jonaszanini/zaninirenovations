<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\MaterialSkuImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
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

        return view('admin.materials.index', compact('materials', 'q', 'category', 'categories', 'totalMaterials'));
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
                ->route('admin.materials.index')
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
                ->route('admin.materials.index')
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
            ->route('admin.materials.index')
            ->with('success', $message)
            ->withInput($request->only(['skus', 'category', 'vendor']));
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
}

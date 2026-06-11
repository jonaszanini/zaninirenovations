<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\Material;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Illuminate\Http\Request;

/**
 * Handles the Fence module's configuration pages — linking fence catalogue
 * rows to the materials catalog, and formula settings (labour rates,
 * concrete-per-post, rails-per-section, etc).
 */
class FenceConfigController extends Controller
{
    public function index()
    {
        $materials = FenceMaterial::query()
            ->with('material')
            ->orderBy('sort_order')
            ->get();

        $catalogMaterials = Material::query()
            ->with('vendor')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'store_sku', 'model', 'price_cad', 'product_url', 'vendor_id']);

        $linkedSyncCount = $materials
            ->filter(fn (FenceMaterial $row) => $row->material_id !== null
                && $row->material !== null
                && $row->material->isSerpApiSyncable())
            ->count();

        return view('admin.quotations.fence.config.index', [
            'materials' => $materials,
            'catalogPickerOptions' => $this->catalogPickerOptions($catalogMaterials),
            'settings' => FenceFormulaSetting::current(),
            'usesSerpApi' => (new HomeDepotCaLocalizedPriceFetcher)->usesSerpApi(),
            'linkedSyncCount' => $linkedSyncCount,
        ]);
    }

    public function updateMaterials(Request $request)
    {
        $data = $request->validate([
            'materials' => ['required', 'array'],
            'materials.*.id' => ['required', 'integer', 'exists:fence_materials,id'],
            'materials.*.material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'materials.*.quantity_per_box' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ]);

        foreach ($data['materials'] as $row) {
            $material = FenceMaterial::find($row['id']);
            if ($material === null) {
                continue;
            }

            $catalogId = filled($row['material_id'] ?? null) ? (int) $row['material_id'] : null;
            $material->material_id = $catalogId;

            if ($material->key === FenceMaterial::KEY_NAILS) {
                $material->quantity_per_box = $row['quantity_per_box'] ?? null;
            }

            $material->save();
        }

        return redirect()
            ->route('admin.quotations.fence.config')
            ->with('success', 'Fence materials linked to the catalog.');
    }

    public function syncMaterials(
        MaterialPriceSyncService $syncService,
        HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher,
    ) {
        if (! $localizedPriceFetcher->usesSerpApi()) {
            return redirect()
                ->route('admin.quotations.fence.config')
                ->with('error', 'SerpApi is not configured. Set MATERIALS_SCRAPER_SERPAPI_KEY on the server to sync fence material prices online.');
        }

        $fenceRows = FenceMaterial::query()
            ->whereNotNull('material_id')
            ->with('material')
            ->orderBy('sort_order')
            ->get();

        if ($fenceRows->isEmpty()) {
            return redirect()
                ->route('admin.quotations.fence.config')
                ->with('error', 'No fence materials are linked to the catalog yet. Select a material for each row and save.');
        }

        $ok = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));

        foreach ($fenceRows as $index => $fenceRow) {
            if ($index > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $catalog = $fenceRow->material;
            if ($catalog === null) {
                $skipped++;
                $errors[] = $fenceRow->name.': linked catalog material not found.';

                continue;
            }

            if (! $catalog->isSerpApiSyncable()) {
                $skipped++;
                $errors[] = $catalog->name.': not syncable (Home Depot + homedepot.ca URL, or Amazon vendor + ASIN in SKU).';

                continue;
            }

            $result = $syncService->syncOne($catalog);
            if ($result['status'] === Material::SCRAPE_OK) {
                $ok++;
            } elseif ($result['status'] === Material::SCRAPE_SKIPPED) {
                $skipped++;
            } else {
                $failed++;
                $errors[] = $catalog->name.': '.($result['error'] ?? 'Sync failed.');
            }
        }

        $message = sprintf('Fence sync: %d ok, %d failed, %d skipped.', $ok, $failed, $skipped);
        if ($errors !== []) {
            $message .= ' '.implode(' ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' …';
            }
        }

        return redirect()
            ->route('admin.quotations.fence.config')
            ->with($failed > 0 ? 'error' : 'success', $message);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Material>  $catalogMaterials
     * @return list<array<string, mixed>>
     */
    private function catalogPickerOptions($catalogMaterials): array
    {
        return $catalogMaterials->map(function (Material $catalog) {
            $priceLabel = ClientProject::formatMoney((float) $catalog->price_cad);
            $sku = trim((string) ($catalog->store_sku ?? ''));
            $label = $catalog->displayName();
            if ($sku !== '') {
                $label .= ' · '.$sku;
            }
            $label .= ' · '.$priceLabel;

            return [
                'id' => $catalog->id,
                'label' => $label,
                'name' => $catalog->displayName(),
                'store_name' => $catalog->name,
                'sku' => $sku,
                'model' => trim((string) ($catalog->model ?? '')),
                'vendor' => trim((string) ($catalog->vendorName() ?? '')),
                'price' => number_format((float) $catalog->price_cad, 2, '.', ''),
                'price_label' => $priceLabel,
                'url' => (string) ($catalog->product_url ?? ''),
            ];
        })->values()->all();
    }

    public function updateFormula(Request $request)
    {
        $data = $request->validate([
            'gate_rails' => ['required', 'integer', 'min:0', 'max:50'],
            'concrete_per_post_6ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'concrete_per_post_8ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'labour_per_additional_gate' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'rails_per_section_6ft' => ['required', 'integer', 'min:1', 'max:10'],
            'rails_per_section_8ft' => ['required', 'integer', 'min:1', 'max:10'],
            'nails_per_picket' => ['required', 'integer', 'min:1', 'max:50'],
            'board_width_in' => ['required', 'numeric', 'min:1', 'max:20'],
            'standard_gap_in' => ['required', 'numeric', 'min:0', 'max:5'],
            'max_post_spacing_ft' => ['required', 'numeric', 'min:1', 'max:20'],
        ]);

        FenceFormulaSetting::current()->update($data);

        return redirect()
            ->route('admin.quotations.fence.config')
            ->with('success', 'Formula parameters updated.');
    }
}

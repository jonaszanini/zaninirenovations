<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LowDeckFormulaSetting;
use App\Models\LowDeckMaterial;
use App\Models\Material;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;

/**
 * Handles the Low Deck module's configuration page — linking deck catalogue
 * rows to the materials catalog, and formula settings (block grid, joist
 * span, board geometry, fasteners, gravel, labour rate, dump fee, etc.).
 */
class LowDeckConfigController extends Controller
{
    public function index()
    {
        $materials = LowDeckMaterial::query()
            ->with('material')
            ->orderBy('sort_order')
            ->get();

        $catalogMaterials = Material::query()
            ->with('vendor')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'store_sku', 'model', 'price_cad', 'product_url', 'vendor_id', 'quantity_per_package']);

        $linkedSyncCount = $materials
            ->filter(fn (LowDeckMaterial $row) => $row->material_id !== null
                && $row->material !== null
                && $row->material->isSerpApiSyncable())
            ->count();

        return view('admin.quotations.low_deck.config.index', [
            'materials' => $materials,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::fromMaterials($catalogMaterials),
            'settings' => LowDeckFormulaSetting::current(),
            'usesSerpApi' => (new HomeDepotCaLocalizedPriceFetcher)->usesSerpApi(),
            'linkedSyncCount' => $linkedSyncCount,
        ]);
    }

    public function updateMaterials(Request $request)
    {
        $data = $request->validate([
            'materials' => ['required', 'array'],
            'materials.*.id' => ['required', 'integer', 'exists:low_deck_materials,id'],
            'materials.*.material_id' => ['nullable', 'integer', 'exists:materials,id'],
        ]);

        foreach ($data['materials'] as $row) {
            $material = LowDeckMaterial::find($row['id']);
            if ($material === null) {
                continue;
            }

            $material->material_id = filled($row['material_id'] ?? null) ? (int) $row['material_id'] : null;
            $material->save();
        }

        return redirect()
            ->route('admin.quotations.low-deck.config')
            ->with('success', 'Low deck materials linked to the catalog.');
    }

    public function syncMaterials(
        MaterialPriceSyncService $syncService,
        HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher,
    ) {
        if (! $localizedPriceFetcher->usesSerpApi()) {
            return redirect()
                ->route('admin.quotations.low-deck.config')
                ->with('error', 'SerpApi is not configured. Set MATERIALS_SCRAPER_SERPAPI_KEY on the server to sync low deck material prices online.');
        }

        $deckRows = LowDeckMaterial::query()
            ->whereNotNull('material_id')
            ->with('material')
            ->orderBy('sort_order')
            ->get();

        if ($deckRows->isEmpty()) {
            return redirect()
                ->route('admin.quotations.low-deck.config')
                ->with('error', 'No low deck materials are linked to the catalog yet. Select a material for each row and save.');
        }

        $ok = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $delayMs = max(0, (int) config('materials.scraper.delay_ms', 1500));

        foreach ($deckRows as $index => $deckRow) {
            if ($index > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $catalog = $deckRow->material;
            if ($catalog === null) {
                $skipped++;
                $errors[] = $deckRow->name.': linked catalog material not found.';

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

        $message = sprintf('Low deck sync: %d ok, %d failed, %d skipped.', $ok, $failed, $skipped);
        if ($errors !== []) {
            $message .= ' '.implode(' ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' …';
            }
        }

        return redirect()
            ->route('admin.quotations.low-deck.config')
            ->with($failed > 0 ? 'error' : 'success', $message);
    }

    public function updateFormula(Request $request)
    {
        $data = $request->validate([
            'max_area_sq_ft' => ['required', 'numeric', 'min:50', 'max:5000'],
            'block_spacing_ft' => ['required', 'numeric', 'min:1', 'max:10'],
            'default_joist_spacing_in' => ['required', 'integer', 'in:12,16'],
            'max_joist_span_in' => ['required', 'numeric', 'min:24', 'max:240'],
            'board_width_in' => ['required', 'numeric', 'min:1', 'max:20'],
            'board_gap_in' => ['required', 'numeric', 'min:0', 'max:2'],
            'post_length_per_block_ft' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'gravel_depth_in' => ['required', 'numeric', 'min:0', 'max:24'],
            'gravel_footprint_in' => ['required', 'numeric', 'min:1', 'max:48'],
            'gravel_cubic_ft_per_bag' => ['required', 'numeric', 'min:0.05', 'max:5'],
            'structural_screws_per_post' => ['required', 'integer', 'min:0', 'max:50'],
            'structural_screws_per_corner' => ['required', 'integer', 'min:0', 'max:50'],
            'board_screws_per_inch' => ['required', 'numeric', 'min:0', 'max:5'],
            'frame_screws_per_t_joint' => ['required', 'integer', 'min:0', 'max:50'],
            'frame_screws_per_cross_joint' => ['required', 'integer', 'min:0', 'max:50'],
            'joist_tape_roll_length_ft' => ['required', 'numeric', 'min:1', 'max:500'],
            'joist_tape_overlap_in' => ['required', 'numeric', 'min:0', 'max:24'],
            'kerf_in' => ['required', 'numeric', 'min:0', 'max:1'],
            'labour_rate_per_sqft' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'waste_dump_fee_cad' => ['required', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        LowDeckFormulaSetting::current()->update($data);

        return redirect()
            ->route('admin.quotations.low-deck.config')
            ->with('success', 'Formula parameters updated.');
    }
}

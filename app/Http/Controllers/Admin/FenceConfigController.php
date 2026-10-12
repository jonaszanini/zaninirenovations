<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\Material;
use App\Support\MaterialCatalogPickerOptions;
use App\Support\QuotationAfterSaveRedirect;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\SteppedMaterialPriceSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles the Fence module's configuration pages — linking fence catalogue
 * rows to the materials catalog, and formula settings (labour rates,
 * concrete-per-post, rails-per-section, etc).
 */
class FenceConfigController extends Controller
{
    private const SYNC_SESSION_PREFIX = 'fence_config_sync.';

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
            ->get(['id', 'name', 'short_name', 'store_sku', 'model', 'price_cad', 'product_url', 'image_url', 'vendor_id', 'quantity_per_package']);

        $linkedSyncCount = count($this->linkedSyncableMaterialIds($materials));

        return view('admin.quotations.fence.config.index', [
            'materials' => $materials,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::fromMaterials($catalogMaterials),
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
        ]);

        foreach ($data['materials'] as $row) {
            $material = FenceMaterial::find($row['id']);
            if ($material === null) {
                continue;
            }

            $material->material_id = filled($row['material_id'] ?? null) ? (int) $row['material_id'] : null;
            $material->save();
        }

        return redirect()
            ->route('admin.quotations.fence.config', QuotationAfterSaveRedirect::withReturnQuery($request))
            ->with('success', 'Fence materials linked to the catalog.');
    }

    public function syncMaterialsStart(Request $request, SteppedMaterialPriceSync $steppedSync): JsonResponse
    {
        $materials = FenceMaterial::query()
            ->with('material')
            ->orderBy('sort_order')
            ->get();

        return $steppedSync->start(
            $request,
            $this->linkedSyncableMaterialIds($materials),
            self::SYNC_SESSION_PREFIX,
            'No linked fence materials are eligible for price sync. Link each row to a Home Depot or Amazon catalog material, then save.',
        );
    }

    public function syncMaterialsStep(Request $request, SteppedMaterialPriceSync $steppedSync): JsonResponse
    {
        return $steppedSync->step($request, self::SYNC_SESSION_PREFIX);
    }

    public function updateFormula(Request $request)
    {
        $data = $request->validate([
            'labour_rate_per_ft' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'labour_rate_8ft_per_ft' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'gate_rails' => ['required', 'integer', 'min:0', 'max:50'],
            'concrete_per_post_6ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'concrete_per_post_8ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'labour_per_additional_gate' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'existing_post_labour_credit' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'rails_per_section_6ft' => ['required', 'integer', 'min:1', 'max:10'],
            'rails_per_section_8ft' => ['required', 'integer', 'min:1', 'max:10'],
            'nails_per_picket' => ['required', 'integer', 'min:1', 'max:50'],
            'board_width_in' => ['required', 'numeric', 'min:1', 'max:20'],
            'overlapping_in' => ['required', 'numeric', 'min:0', 'max:12'],
            'max_post_spacing_ft' => ['required', 'numeric', 'min:1', 'max:20'],
            'concrete_per_post_6x6_6ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'concrete_per_post_6x6_8ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'waste_extra_posts' => ['required', 'integer', 'min:0', 'max:20'],
            'waste_pickets_per_section' => ['required', 'integer', 'min:0', 'max:20'],
            'waste_extra_rails' => ['required', 'integer', 'min:0', 'max:20'],
            'waste_extra_brackets' => ['required', 'integer', 'min:0', 'max:20'],
            'waste_extra_caps' => ['required', 'integer', 'min:0', 'max:20'],
            'waste_concrete_posts_per_extra_bag' => ['required', 'integer', 'min:1', 'max:20'],
            'work_description_template' => ['nullable', 'string', 'max:10000'],
        ]);

        FenceFormulaSetting::current()->update($data);

        return redirect()
            ->route('admin.quotations.fence.config', QuotationAfterSaveRedirect::withReturnQuery($request))
            ->with('success', 'Formula parameters updated.');
    }

    /**
     * Unique catalog material IDs linked from fence rows and eligible for price sync.
     *
     * @param  \Illuminate\Support\Collection<int, FenceMaterial>  $materials
     * @return list<int>
     */
    private function linkedSyncableMaterialIds($materials): array
    {
        $ids = [];

        foreach ($materials as $row) {
            $catalog = $row->material;
            if ($catalog === null || ! $catalog->isSerpApiSyncable()) {
                continue;
            }

            $ids[(int) $catalog->id] = (int) $catalog->id;
        }

        return array_values($ids);
    }
}

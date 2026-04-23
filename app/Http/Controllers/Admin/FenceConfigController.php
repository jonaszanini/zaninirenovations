<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use Illuminate\Http\Request;

/**
 * Handles the Fence module's configuration pages — the materials
 * catalogue (price + URL + nail box size) and the formula settings
 * (labour rate, concrete-per-post, rails-per-section, etc).
 *
 * Materials are never created or deleted through the UI; only the
 * three editable fields are writeable. This keeps the calculator's
 * lookups by `key` safe from accidental renames.
 */
class FenceConfigController extends Controller
{
    public function index()
    {
        return view('admin.quotations.fence.config.index', [
            'materials' => FenceMaterial::query()->orderBy('sort_order')->get(),
            'settings' => FenceFormulaSetting::current(),
        ]);
    }

    public function updateMaterials(Request $request)
    {
        $data = $request->validate([
            'materials' => ['required', 'array'],
            'materials.*.id' => ['required', 'integer', 'exists:fence_materials,id'],
            'materials.*.price_cad' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'materials.*.url' => ['nullable', 'url', 'max:500'],
            'materials.*.quantity_per_box' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ]);

        foreach ($data['materials'] as $row) {
            $material = FenceMaterial::find($row['id']);
            if (!$material) {
                continue;
            }

            $material->price_cad = (float) ($row['price_cad'] ?? 0);
            $material->url = $row['url'] ?? null;

            // Only the nails row exposes quantity_per_box — keep the
            // other rows' column at NULL so the calculator's soldByBox()
            // helper stays deterministic.
            if ($material->key === FenceMaterial::KEY_NAILS) {
                $material->quantity_per_box = $row['quantity_per_box'] ?? null;
            }

            $material->save();
        }

        return redirect()
            ->route('admin.quotations.fence.config')
            ->with('success', 'Materials catalogue updated.');
    }

    public function updateFormula(Request $request)
    {
        $data = $request->validate([
            'gate_rails' => ['required', 'integer', 'min:0', 'max:50'],
            'concrete_per_post_6ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'concrete_per_post_8ft' => ['required', 'numeric', 'min:0', 'max:20'],
            'labour_rate_per_ft' => ['required', 'numeric', 'min:0', 'max:9999.99'],
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

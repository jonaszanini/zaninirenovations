<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TileMortarType;
use App\Models\TileGroutType;
use App\Models\TileProduct;
use App\Models\TileQuotation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TileDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $mortarTypes = TileMortarType::query()->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->orderBy('sort_order')->orderBy('id')->get();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.tile.price_configuration', compact(
            'mortarTypes',
            'groutTypes',
            'products',
            'qpid'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mortars' => ['nullable', 'array'],
            'mortars.*.id' => ['nullable', 'integer', 'exists:tile_mortar_types,id'],
            'mortars.*.name' => ['required', 'string', 'max:255'],
            'mortars.*.bag_weight_lbs' => ['required', 'numeric', 'min:0'],
            'mortars.*.cost_price' => ['required', 'numeric', 'min:0'],
            'mortars.*.retail_price' => ['required', 'numeric', 'min:0'],
            'mortars.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'mortars.*.is_active' => ['nullable', 'boolean'],
            'new_mortars' => ['nullable', 'array'],
            'new_mortars.*.name' => ['nullable', 'string', 'max:255'],
            'new_mortars.*.bag_weight_lbs' => ['nullable', 'numeric', 'min:0'],
            'new_mortars.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'new_mortars.*.retail_price' => ['nullable', 'numeric', 'min:0'],
            'delete_mortar_ids' => ['nullable', 'array'],
            'delete_mortar_ids.*' => ['integer', 'exists:tile_mortar_types,id'],

            'grouts' => ['nullable', 'array'],
            'grouts.*.id' => ['nullable', 'integer', 'exists:tile_grout_types,id'],
            'grouts.*.name' => ['required', 'string', 'max:255'],
            'grouts.*.bag_weight_lbs' => ['required', 'numeric', 'min:0'],
            'grouts.*.density_factor' => ['required', 'numeric', 'min:0'],
            'grouts.*.cost_price' => ['required', 'numeric', 'min:0'],
            'grouts.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'grouts.*.is_active' => ['nullable', 'boolean'],
            'new_grouts' => ['nullable', 'array'],
            'new_grouts.*.name' => ['nullable', 'string', 'max:255'],
            'new_grouts.*.bag_weight_lbs' => ['nullable', 'numeric', 'min:0'],
            'new_grouts.*.density_factor' => ['nullable', 'numeric', 'min:0'],
            'new_grouts.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'delete_grout_ids' => ['nullable', 'array'],
            'delete_grout_ids.*' => ['integer', 'exists:tile_grout_types,id'],

            'products' => ['nullable', 'array'],
            'products.*.id' => ['nullable', 'integer', 'exists:tile_products,id'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.length_inches' => ['required', 'numeric', 'min:0'],
            'products.*.width_inches' => ['required', 'numeric', 'min:0'],
            'products.*.thickness_inches' => ['required', 'numeric', 'min:0'],
            'products.*.cost_per_sqft' => ['required', 'numeric', 'min:0'],
            'products.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'products.*.is_active' => ['nullable', 'boolean'],
            'new_products' => ['nullable', 'array'],
            'new_products.*.name' => ['nullable', 'string', 'max:255'],
            'new_products.*.length_inches' => ['nullable', 'numeric', 'min:0'],
            'new_products.*.width_inches' => ['nullable', 'numeric', 'min:0'],
            'new_products.*.thickness_inches' => ['nullable', 'numeric', 'min:0'],
            'new_products.*.cost_per_sqft' => ['nullable', 'numeric', 'min:0'],
            'delete_product_ids' => ['nullable', 'array'],
            'delete_product_ids.*' => ['integer', 'exists:tile_products,id'],
        ]);

        // 1. Process Mortars
        foreach ($data['mortars'] ?? [] as $row) {
            if (! is_array($row) || empty($row['id'])) continue;
            $m = TileMortarType::query()->find($row['id']);
            if ($m) {
                $m->update([
                    'name' => trim((string) $row['name']),
                    'bag_weight_lbs' => round((float) $row['bag_weight_lbs'], 2),
                    'cost_price' => round((float) $row['cost_price'], 4),
                    'retail_price' => round((float) ($row['retail_price'] ?? 0), 4),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? $m->sort_order)),
                    'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
        $maxSortMortar = (int) TileMortarType::query()->max('sort_order');
        foreach ($data['new_mortars'] ?? [] as $row) {
            if (! is_array($row) || empty(trim((string) ($row['name'] ?? '')))) continue;
            TileMortarType::query()->create([
                'name' => trim((string) $row['name']),
                'bag_weight_lbs' => round((float) ($row['bag_weight_lbs'] ?? 50), 2),
                'cost_price' => round((float) ($row['cost_price'] ?? 0), 4),
                'retail_price' => round((float) ($row['retail_price'] ?? 0), 4),
                'sort_order' => ++$maxSortMortar,
                'is_active' => true,
            ]);
        }
        foreach ($data['delete_mortar_ids'] ?? [] as $id) {
            if ($id > 0) TileMortarType::query()->whereKey($id)->delete();
        }

        // 2. Process Grouts
        foreach ($data['grouts'] ?? [] as $row) {
            if (! is_array($row) || empty($row['id'])) continue;
            $g = TileGroutType::query()->find($row['id']);
            if ($g) {
                $g->update([
                    'name' => trim((string) $row['name']),
                    'bag_weight_lbs' => round((float) $row['bag_weight_lbs'], 2),
                    'density_factor' => round((float) $row['density_factor'], 4),
                    'cost_price' => round((float) $row['cost_price'], 4),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? $g->sort_order)),
                    'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
        $maxSortGrout = (int) TileGroutType::query()->max('sort_order');
        foreach ($data['new_grouts'] ?? [] as $row) {
            if (! is_array($row) || empty(trim((string) ($row['name'] ?? '')))) continue;
            TileGroutType::query()->create([
                'name' => trim((string) $row['name']),
                'bag_weight_lbs' => round((float) ($row['bag_weight_lbs'] ?? 10), 2),
                'density_factor' => round((float) ($row['density_factor'] ?? 1.6), 4),
                'cost_price' => round((float) ($row['cost_price'] ?? 0), 4),
                'sort_order' => ++$maxSortGrout,
                'is_active' => true,
            ]);
        }
        foreach ($data['delete_grout_ids'] ?? [] as $id) {
            if ($id > 0) TileGroutType::query()->whereKey($id)->delete();
        }

        // 3. Process Products
        foreach ($data['products'] ?? [] as $row) {
            if (! is_array($row) || empty($row['id'])) continue;
            $p = TileProduct::query()->find($row['id']);
            if ($p) {
                $p->update([
                    'name' => trim((string) $row['name']),
                    'length_inches' => round((float) $row['length_inches'], 4),
                    'width_inches' => round((float) $row['width_inches'], 4),
                    'thickness_inches' => round((float) $row['thickness_inches'], 4),
                    'cost_per_sqft' => round((float) $row['cost_per_sqft'], 4),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? $p->sort_order)),
                    'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
        $maxSortProduct = (int) TileProduct::query()->max('sort_order');
        foreach ($data['new_products'] ?? [] as $row) {
            if (! is_array($row) || empty(trim((string) ($row['name'] ?? '')))) continue;
            TileProduct::query()->create([
                'name' => trim((string) $row['name']),
                'length_inches' => round((float) ($row['length_inches'] ?? 0), 4),
                'width_inches' => round((float) ($row['width_inches'] ?? 0), 4),
                'thickness_inches' => round((float) ($row['thickness_inches'] ?? 0), 4),
                'cost_per_sqft' => round((float) ($row['cost_per_sqft'] ?? 0), 4),
                'sort_order' => ++$maxSortProduct,
                'is_active' => true,
            ]);
        }
        foreach ($data['delete_product_ids'] ?? [] as $id) {
            if ($id > 0) TileProduct::query()->whereKey($id)->delete();
        }

        return redirect()
            ->route('admin.quotations.tile.price-configuration', $request->filled('project') ? ['project' => $request->query('project')] : [])
            ->with('success', 'Tile database updated.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
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

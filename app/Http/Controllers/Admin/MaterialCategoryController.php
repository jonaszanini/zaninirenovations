<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('material_categories', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        MaterialCategory::create([
            'name' => trim($data['name']),
            'slug' => MaterialCategory::uniqueSlug($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.materials.config')
            ->with('success', 'Category added.');
    }

    public function update(Request $request, MaterialCategory $materialCategory)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('material_categories', 'name')->ignore($materialCategory->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        $materialCategory->update([
            'name' => trim($data['name']),
            'slug' => MaterialCategory::uniqueSlug($data['name'], $materialCategory->id),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.materials.config')
            ->with('success', 'Category updated.');
    }

    public function destroy(MaterialCategory $materialCategory)
    {
        if ($materialCategory->materials()->exists()) {
            return redirect()
                ->route('admin.materials.config')
                ->with('error', 'Cannot delete a category that still has materials. Reassign those materials first.');
        }

        $materialCategory->delete();

        return redirect()
            ->route('admin.materials.config')
            ->with('success', 'Category deleted.');
    }
}

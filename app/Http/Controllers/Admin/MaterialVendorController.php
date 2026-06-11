<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialVendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialVendorController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('material_vendors', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        MaterialVendor::create([
            'name' => trim($data['name']),
            'slug' => MaterialVendor::uniqueSlug($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.materials.config')
            ->with('success', 'Vendor added.');
    }

    public function update(Request $request, MaterialVendor $materialVendor)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('material_vendors', 'name')->ignore($materialVendor->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('material_vendors', 'slug')->ignore($materialVendor->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_default_for_homedepot' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default_for_homedepot')) {
            MaterialVendor::query()
                ->where('id', '!=', $materialVendor->id)
                ->update(['is_default_for_homedepot' => false]);
        }

        $materialVendor->update([
            'name' => trim($data['name']),
            'slug' => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_default_for_homedepot' => $request->boolean('is_default_for_homedepot'),
        ]);

        return redirect()
            ->route('admin.materials.config')
            ->with('success', 'Vendor updated.');
    }

    public function destroy(MaterialVendor $materialVendor)
    {
        if ($materialVendor->materials()->exists()) {
            return redirect()
                ->route('admin.materials.config')
                ->with('error', 'Cannot delete a vendor that still has materials. Reassign those materials first.');
        }

        if ($materialVendor->slug === MaterialVendor::SLUG_HOME_DEPOT) {
            return redirect()
                ->route('admin.materials.config')
                ->with('error', 'The Home Depot vendor cannot be deleted.');
        }

        $materialVendor->delete();

        return redirect()
            ->route('admin.materials.config')
            ->with('success', 'Vendor deleted.');
    }
}

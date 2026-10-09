<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlooringQuotation;
use App\Models\FlooringType;
use App\Support\CadMoney;
use App\Support\QuotationAfterSaveRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FlooringDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $settings = \App\Models\FlooringSettings::current();
        $flooringTypes = FlooringType::query()->orderBy('sort_order')->orderBy('id')->get();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.flooring.price_configuration', compact(
            'settings',
            'flooringTypes',
            'qpid'
        ));
    }

    public function update(Request $request)
    {
        $this->normalizeMoneyFields($request);

        $data = $request->validate([
            'remove_carpet_dispose_rate' => ['required', 'numeric', 'min:0'],
            'rescrew_subfloor_rate' => ['required', 'numeric', 'min:0'],
            'install_shoe_moulding_rate' => ['required', 'numeric', 'min:0'],
            'caulk_paint_shoe_moulding_rate' => ['required', 'numeric', 'min:0'],
            'closet_door_reinstall_rate' => ['required', 'numeric', 'min:0'],
            'flooring_types' => ['nullable', 'array'],
            'flooring_types.*.id' => ['nullable', 'integer', 'exists:flooring_types,id'],
            'flooring_types.*.name' => ['required', 'string', 'max:160'],
            'flooring_types.*.installation_price_per_sqft' => ['required', 'numeric', 'min:0'],
            'flooring_types.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'flooring_types.*.is_active' => ['nullable', 'boolean'],
            'new_flooring_types' => ['nullable', 'array'],
            'new_flooring_types.*.name' => ['nullable', 'string', 'max:160'],
            'new_flooring_types.*.installation_price_per_sqft' => ['nullable', 'numeric', 'min:0'],
            'delete_flooring_type_ids' => ['nullable', 'array'],
            'delete_flooring_type_ids.*' => ['integer', 'exists:flooring_types,id'],
        ]);

        $settings = \App\Models\FlooringSettings::query()->find(1) ?? \App\Models\FlooringSettings::current();
        $settings->fill([
            'remove_carpet_dispose_rate' => round((float) $data['remove_carpet_dispose_rate'], 4),
            'rescrew_subfloor_rate' => round((float) $data['rescrew_subfloor_rate'], 2),
            'install_shoe_moulding_rate' => round((float) $data['install_shoe_moulding_rate'], 4),
            'caulk_paint_shoe_moulding_rate' => round((float) $data['caulk_paint_shoe_moulding_rate'], 4),
            'closet_door_reinstall_rate' => round((float) $data['closet_door_reinstall_rate'], 2),
        ]);
        $settings->save();

        foreach ($data['flooring_types'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $type = FlooringType::query()->find($id);
            if (! $type) {
                continue;
            }
            $type->name = trim((string) $row['name']);
            $type->installation_price_per_sqft = round((float) $row['installation_price_per_sqft'], 4);
            $type->sort_order = max(0, (int) ($row['sort_order'] ?? $type->sort_order));
            $type->is_active = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $type->save();
        }

        foreach ($data['new_flooring_types'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $baseKey = Str::slug($name) ?: 'flooring_type';
            $key = $baseKey;
            $n = 1;
            while (FlooringType::query()->where('key', $key)->exists()) {
                $key = $baseKey.'_'.$n;
                $n++;
            }
            $maxSort = (int) FlooringType::query()->max('sort_order');
            FlooringType::query()->create([
                'key' => $key,
                'name' => $name,
                'installation_price_per_sqft' => round((float) ($row['installation_price_per_sqft'] ?? 0), 4),
                'sort_order' => $maxSort + 1,
                'is_active' => true,
            ]);
        }

        foreach ($data['delete_flooring_type_ids'] ?? [] as $typeId) {
            $typeId = (int) $typeId;
            if ($typeId <= 0) {
                continue;
            }
            $inUse = FlooringQuotation::query()
                ->whereRaw("JSON_EXTRACT(inputs, '$.flooring_type_id') = ?", [$typeId])
                ->exists();
            if ($inUse) {
                throw ValidationException::withMessages([
                    'delete_flooring_type_ids' => 'Cannot delete a flooring type that is used on an existing quotation.',
                ]);
            }
            FlooringType::query()->whereKey($typeId)->delete();
        }

        return redirect()
            ->route(
                'admin.quotations.flooring.price-configuration',
                QuotationAfterSaveRedirect::withReturnQuery(
                    $request,
                    $request->filled('project') ? ['project' => $request->query('project')] : []
                )
            )
            ->with('success', 'Flooring price database updated.');
    }

    /**
     * Posted CAD fields are dollars (4.75 stays 4.75). Strip grouping
     * commas only — never divide by 100.
     */
    protected function normalizeMoneyFields(Request $request): void
    {
        foreach ([
            'remove_carpet_dispose_rate',
            'rescrew_subfloor_rate',
            'install_shoe_moulding_rate',
            'caulk_paint_shoe_moulding_rate',
            'closet_door_reinstall_rate',
        ] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => CadMoney::stripGrouping($request->input($field))]);
            }
        }

        foreach (['flooring_types', 'new_flooring_types'] as $key) {
            $rows = $request->input($key);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! array_key_exists('installation_price_per_sqft', $row)) {
                    continue;
                }
                $rows[$i]['installation_price_per_sqft'] = CadMoney::stripGrouping($row['installation_price_per_sqft']);
            }
            $request->merge([$key => $rows]);
        }
    }
}

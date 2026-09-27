<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SharedPriceLibraryController extends Controller
{
    public function edit()
    {
        return view('admin.quotations.shared_prices.edit', [
            'crew' => SharedCrewRate::current(),
            'works' => SharedWorkCatalog::allWorks(),
            'groups' => SharedWorkCatalog::GROUPS,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'owner_hourly_rate' => ['required', 'numeric', 'min:0'],
            'helper_hourly_rate' => ['required', 'numeric', 'min:0'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'works' => ['nullable', 'array'],
            'works.*.id' => ['required', 'integer', 'exists:shared_works,id'],
            'works.*.rate' => ['required', 'numeric', 'min:0'],
            'works.*.flat_rate' => ['nullable', 'numeric', 'min:0'],
            'works.*.catalog_material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'works.*.material_name' => ['nullable', 'string', 'max:160'],
            'works.*.material_unit' => ['nullable', 'string', 'max:40'],
            'works.*.material_unit_price' => ['nullable', 'numeric', 'min:0'],
            'works.*.labor_kind' => ['nullable', Rule::in([
                SharedWork::KIND_PER_HOUR,
                SharedWork::KIND_HOURS_EACH,
                SharedWork::KIND_FLAT,
            ])],
        ]);

        $crew = SharedCrewRate::current();
        $crew->fill([
            'owner_hourly_rate' => round((float) $data['owner_hourly_rate'], 2),
            'helper_hourly_rate' => round((float) $data['helper_hourly_rate'], 2),
            'hours_per_day' => round((float) $data['hours_per_day'], 2),
        ]);
        $crew->save();

        foreach ($data['works'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $work = SharedWork::query()->find((int) $row['id']);
            if (! $work) {
                continue;
            }
            $catalogId = (int) ($row['catalog_material_id'] ?? 0);
            $work->rate = round((float) $row['rate'], 4);
            $work->flat_rate = isset($row['flat_rate']) && $row['flat_rate'] !== '' && $row['flat_rate'] !== null
                ? round((float) $row['flat_rate'], 4)
                : $work->flat_rate;
            $work->catalog_material_id = $catalogId > 0 ? $catalogId : null;
            $name = trim((string) ($row['material_name'] ?? ''));
            if ($name !== '') {
                $work->material_name = $name;
            }
            $unit = trim((string) ($row['material_unit'] ?? ''));
            if ($unit !== '') {
                $work->material_unit = $unit;
            }
            if (isset($row['material_unit_price']) && is_numeric($row['material_unit_price'])) {
                $work->material_unit_price = round((float) $row['material_unit_price'], 4);
            }
            $work->save();
        }

        SharedWorkCatalog::forgetCache();

        return redirect()
            ->route('admin.quotations.shared-prices.edit')
            ->with('status', 'Shared prices saved. New quotation previews use these rates.');
    }
}
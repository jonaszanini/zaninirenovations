<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShedSettings;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;

class ShedDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $settings = ShedSettings::current();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.shed.price_configuration', [
            'settings' => $settings,
            'qpid' => $qpid,
            'productivity' => $settings->resolvedProductivity(),
            'productivityGroups' => ShedSettings::productivityGroups(),
            'materials' => $settings->resolvedMaterials(),
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'owner_hourly_rate' => ['required', 'numeric', 'min:0'],
            'helper_hourly_rate' => ['required', 'numeric', 'min:0'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'productivity' => ['nullable', 'array'],
            'productivity.*' => ['nullable', 'numeric', 'min:0'],
            'materials' => ['nullable', 'array'],
            'materials.*.name' => ['nullable', 'string', 'max:160'],
            'materials.*.unit' => ['nullable', 'string', 'max:40'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'materials.*.catalog_material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'tab' => ['nullable', 'in:rates,materials'],
        ]);

        $settings = ShedSettings::query()->find(1) ?? ShedSettings::current();
        $prod = $settings->resolvedProductivity();
        foreach ($data['productivity'] ?? [] as $key => $value) {
            if (! array_key_exists($key, $prod) || ! is_numeric($value)) {
                continue;
            }
            $prod[$key] = round((float) $value, 4);
        }

        $mats = $settings->resolvedMaterials();
        foreach ($data['materials'] ?? [] as $key => $row) {
            if (! isset($mats[$key]) || ! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $mats[$key]['name'] = $name;
            }
            $unit = trim((string) ($row['unit'] ?? ''));
            if ($unit !== '') {
                $mats[$key]['unit'] = $unit;
            }
            if (isset($row['unit_price']) && is_numeric($row['unit_price'])) {
                $mats[$key]['unit_price'] = round((float) $row['unit_price'], 4);
            }
            $catalogId = (int) ($row['catalog_material_id'] ?? 0);
            $mats[$key]['catalog_material_id'] = $catalogId > 0 ? $catalogId : null;
        }

        $mats = ShedSettings::applyDerivedMaterialPrices($mats);

        $settings->fill([
            'owner_hourly_rate' => round((float) $data['owner_hourly_rate'], 2),
            'helper_hourly_rate' => round((float) $data['helper_hourly_rate'], 2),
            'hours_per_day' => round((float) $data['hours_per_day'], 2),
            'productivity' => $prod,
            'materials' => $mats,
        ]);
        $settings->save();

        $redirect = [
            'tab' => $this->configTab($request),
        ];
        if ($request->filled('project')) {
            $redirect['project'] = (int) $request->input('project');
        }

        return redirect()
            ->route('admin.quotations.shed.price-configuration', $redirect)
            ->with('success', 'Shed price database updated.');
    }

    private function configTab(Request $request): string
    {
        $tab = (string) $request->input('tab', $request->query('tab', 'rates'));

        return in_array($tab, ['rates', 'materials'], true) ? $tab : 'rates';
    }
}

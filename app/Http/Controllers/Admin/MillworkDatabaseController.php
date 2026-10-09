<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkCatalog;
use App\Support\MaterialCatalogPickerOptions;
use App\Support\QuotationAfterSaveRedirect;
use Illuminate\Http\Request;

class MillworkDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $settings = MillworkSettings::current();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.millwork.price_configuration', [
            'settings' => $settings,
            'qpid' => $qpid,
            'productivity' => $settings->resolvedProductivity(),
            'productivityGroups' => MillworkSettings::productivityGroups(),
            'catalog' => $settings->resolvedCatalog(),
            'cutSettings' => $settings->resolvedCutSettings(),
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
            'cut_settings' => ['nullable', 'array'],
            'cut_settings.*' => ['nullable', 'numeric', 'min:0'],
            'catalog' => ['nullable', 'array'],
            'tab' => ['nullable', 'in:rates,catalog,cut'],
        ]);

        $settings = MillworkSettings::query()->find(1) ?? MillworkSettings::current();
        $prod = $settings->resolvedProductivity();
        foreach ($data['productivity'] ?? [] as $key => $value) {
            if (! array_key_exists($key, $prod) || ! is_numeric($value)) {
                continue;
            }
            $prod[$key] = round((float) $value, 4);
        }

        $cut = $settings->resolvedCutSettings();
        foreach ($data['cut_settings'] ?? [] as $key => $value) {
            if (! array_key_exists($key, $cut) || ! is_numeric($value)) {
                continue;
            }
            $cut[$key] = round((float) $value, 4);
        }

        foreach (MillworkSettings::catalogSlots() as $slot => $_label) {
            $rows = $request->input('catalog.'.$slot, []);
            MillworkCatalog::replaceSlot($settings, $slot, is_array($rows) ? $rows : []);
        }

        $settings->fill([
            'owner_hourly_rate' => round((float) $data['owner_hourly_rate'], 2),
            'helper_hourly_rate' => round((float) $data['helper_hourly_rate'], 2),
            'hours_per_day' => round((float) $data['hours_per_day'], 2),
            'productivity' => $prod,
            'cut_settings' => $cut,
        ]);
        $settings->save();

        $redirect = [
            'tab' => $this->configTab($request),
        ];
        if ($request->filled('project')) {
            $redirect['project'] = (int) $request->input('project');
        }
        $redirect = QuotationAfterSaveRedirect::withReturnQuery($request, $redirect);

        return redirect()
            ->route('admin.quotations.millwork.price-configuration', $redirect)
            ->with('success', 'Millwork price database updated.');
    }

    private function configTab(Request $request): string
    {
        $tab = (string) $request->input('tab', $request->query('tab', 'rates'));

        return in_array($tab, ['rates', 'catalog', 'cut'], true) ? $tab : 'rates';
    }
}

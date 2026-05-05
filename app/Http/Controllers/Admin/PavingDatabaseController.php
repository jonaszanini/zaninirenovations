<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PavingMaterialRate;
use App\Models\PavingPricingTier;
use App\Models\PavingStoneType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Editable price database for paving quotations (stones, fixed rates, pricing tiers).
 */
class PavingDatabaseController extends Controller
{
    public function storeStone(Request $request)
    {
        $this->mergeStrippedMoney($request, ['price_per_stone']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sq_ft_per_stone' => ['required', 'numeric', 'min:0.0001', 'max:100'],
            'price_per_stone' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        $max = (int) PavingStoneType::query()->max('sort_order');
        PavingStoneType::create([
            'name' => trim($data['name']),
            'sq_ft_per_stone' => $data['sq_ft_per_stone'],
            'price_per_stone' => $data['price_per_stone'],
            'sort_order' => $max + 1,
        ]);

        return back()->with('success', 'Stone type added.');
    }

    public function updateStone(Request $request, PavingStoneType $paving_stone_type)
    {
        $this->mergeStrippedMoney($request, ['price_per_stone']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sq_ft_per_stone' => ['required', 'numeric', 'min:0.0001', 'max:100'],
            'price_per_stone' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        $paving_stone_type->update([
            'name' => trim($data['name']),
            'sq_ft_per_stone' => $data['sq_ft_per_stone'],
            'price_per_stone' => $data['price_per_stone'],
        ]);

        return back()->with('success', 'Stone type updated.');
    }

    public function destroyStone(PavingStoneType $paving_stone_type)
    {
        if (PavingStoneType::query()->count() <= 1) {
            return back()->with('error', 'Keep at least one stone type.');
        }
        $paving_stone_type->delete();

        return back()->with('success', 'Stone type deleted.');
    }

    public function updateRate(Request $request, PavingMaterialRate $paving_material_rate)
    {
        $this->mergeStrippedMoney($request, ['price_before_tax']);

        $data = $request->validate([
            'price_before_tax' => ['required', 'numeric', 'min:0', 'max:999999'],
            'label' => ['nullable', 'string', 'max:160'],
            'unit' => ['nullable', 'string', 'max:40'],
        ]);

        $paving_material_rate->fill([
            'price_before_tax' => $data['price_before_tax'],
            'label' => isset($data['label']) ? trim((string) $data['label']) : $paving_material_rate->label,
            'unit' => array_key_exists('unit', $data) ? ($data['unit'] !== null && $data['unit'] !== '' ? trim((string) $data['unit']) : null) : $paving_material_rate->unit,
        ])->save();

        return back()->with('success', 'Rate updated.');
    }

    public function syncTiers(Request $request)
    {
        $data = $request->validate([
            'tiers' => ['required', 'array', 'min:2'],
            'tiers.*.area_sq_ft' => ['required', 'numeric', 'min:1', 'max:100000'],
            'tiers.*.price_per_sq_ft' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        $rows = $data['tiers'];
        usort($rows, static fn ($a, $b) => ((float) $a['area_sq_ft']) <=> ((float) $b['area_sq_ft']));

        DB::transaction(function () use ($rows) {
            PavingPricingTier::query()->delete();
            $i = 0;
            foreach ($rows as $row) {
                $i++;
                PavingPricingTier::create([
                    'area_sq_ft' => round((float) $row['area_sq_ft'], 2),
                    'price_per_sq_ft' => round((float) $row['price_per_sq_ft'], 4),
                    'sort_order' => $i,
                ]);
            }
        });

        return back()->with('success', 'Pricing tiers saved.');
    }

    /**
     * @param  list<string>  $keys
     */
    protected function mergeStrippedMoney(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $v = $request->input($key);
            if (! is_string($v)) {
                continue;
            }
            $request->merge([$key => str_replace(',', '', $v)]);
        }
    }
}

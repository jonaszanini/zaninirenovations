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
    public function edit(Request $request)
    {
        $stoneTypes = PavingStoneType::query()->orderBy('sort_order')->get();
        $materialRateGroups = PavingMaterialRate::groupedForAdmin();
        $pricingTiers = PavingPricingTier::query()->orderBy('area_sq_ft')->get();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.paving.price_configuration', compact(
            'stoneTypes',
            'materialRateGroups',
            'pricingTiers',
            'qpid',
        ));
    }

    public function storeStone(Request $request)
    {
        $this->mergeStrippedMoney($request, ['price_per_stone', 'price_per_sq_ft', 'delivery_cost', 'additional_cost']);
        $this->normalizeStoneUrlOnRequest($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_url' => ['nullable', 'string', 'max:500', 'url'],
            'image_url' => ['nullable', 'string', 'max:500', 'url'],
            'length_in' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'width_in' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'height_in' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'sq_ft_per_stone' => ['nullable', 'numeric', 'min:0.0001', 'max:100'],
            'price_per_stone' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'price_per_sq_ft' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'delivery_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost_description' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['price_per_sq_ft']) && (empty($data['sq_ft_per_stone']) || empty($data['price_per_stone']))) {
            return back()->withInput()->withErrors(['price_per_sq_ft' => 'You must provide either Price/sq ft or both Sq ft/stone and $/stone.']);
        }

        $max = (int) PavingStoneType::query()->max('sort_order');
        PavingStoneType::create($this->stonePayloadFromValidated($data) + [
            'sort_order' => $max + 1,
        ]);

        return back()->with('success', 'Stone type added.');
    }

    public function updateStone(Request $request, PavingStoneType $paving_stone_type)
    {
        $this->mergeStrippedMoney($request, ['price_per_stone', 'price_per_sq_ft', 'delivery_cost', 'additional_cost']);
        $this->normalizeStoneUrlOnRequest($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_url' => ['nullable', 'string', 'max:500', 'url'],
            'image_url' => ['nullable', 'string', 'max:500', 'url'],
            'length_in' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'width_in' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'height_in' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'sq_ft_per_stone' => ['nullable', 'numeric', 'min:0.0001', 'max:100'],
            'price_per_stone' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'price_per_sq_ft' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'delivery_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost_description' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['price_per_sq_ft']) && (empty($data['sq_ft_per_stone']) || empty($data['price_per_stone']))) {
            return back()->withInput()->withErrors(['price_per_sq_ft' => 'You must provide either Price/sq ft or both Sq ft/stone and $/stone.']);
        }

        $paving_stone_type->update($this->stonePayloadFromValidated($data));

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
        $tierInputs = $request->input('tiers', []);
        if (is_array($tierInputs)) {
            foreach ($tierInputs as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (isset($row['price_per_sq_ft']) && is_string($row['price_per_sq_ft'])) {
                    $tierInputs[$i]['price_per_sq_ft'] = str_replace(
                        [',', ' ', "\u{00A0}"],
                        '',
                        $row['price_per_sq_ft']
                    );
                }
            }
            $request->merge(['tiers' => $tierInputs]);
        }

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

    protected function normalizeStoneUrlOnRequest(Request $request): void
    {
        if (! $request->has('product_url')) {
            return;
        }
        $url = trim((string) $request->input('product_url'));
        if ($url === '') {
            $request->merge(['product_url' => null]);

            return;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $request->merge(['product_url' => $url]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stonePayloadFromValidated(array $data): array
    {
        $url = trim((string) ($data['product_url'] ?? ''));
        $length = isset($data['length_in']) && $data['length_in'] !== '' ? (float) $data['length_in'] : null;
        $width = isset($data['width_in']) && $data['width_in'] !== '' ? (float) $data['width_in'] : null;
        $height = isset($data['height_in']) && $data['height_in'] !== '' ? (float) $data['height_in'] : null;

        $sqFt = isset($data['sq_ft_per_stone']) && $data['sq_ft_per_stone'] !== '' ? (float) $data['sq_ft_per_stone'] : null;
        $price = isset($data['price_per_stone']) && $data['price_per_stone'] !== '' ? (float) $data['price_per_stone'] : null;
        
        $pricePerSqFt = isset($data['price_per_sq_ft']) && $data['price_per_sq_ft'] !== '' ? (float) $data['price_per_sq_ft'] : null;
        if ($pricePerSqFt === null && $sqFt > 0 && $price !== null) {
            $pricePerSqFt = round($price / $sqFt, 4);
        }

        return [
            'name' => trim($data['name']),
            'product_url' => $url !== '' ? $url : null,
            'length_in' => $length,
            'width_in' => $width,
            'height_in' => $height,
            'sq_ft_per_stone' => $sqFt,
            'price_per_stone' => $price,
            'price_per_sq_ft' => $pricePerSqFt,
        ];
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PavingMarbleType;
use App\Models\PavingMaterialRate;
use App\Models\PavingPricingTier;
use App\Models\PavingStoneType;
use App\Services\ProjectPhotoWebpStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Editable price database for paving quotations (stones, fixed rates, pricing tiers).
 */
class PavingDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $stoneTypes = PavingStoneType::query()->orderBy('sort_order')->get();
        $marbleTypes = PavingMarbleType::query()->orderBy('sort_order')->get();
        $materialRateGroups = PavingMaterialRate::groupedForAdmin();
        $pricingTiers = PavingPricingTier::query()->orderBy('area_sq_ft')->get();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.paving.price_configuration', compact(
            'stoneTypes',
            'marbleTypes',
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
            'image_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
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
        PavingStoneType::create($this->stonePayloadFromValidated($data, $this->resolveStoneImageUrl($request)) + [
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
            'image_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
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

        $paving_stone_type->update($this->stonePayloadFromValidated(
            $data,
            $this->resolveStoneImageUrl($request, $paving_stone_type->image_url)
        ));

        return back()->with('success', 'Stone type updated.');
    }

    public function destroyStone(PavingStoneType $paving_stone_type)
    {
        if (PavingStoneType::query()->count() <= 1) {
            return back()->with('error', 'Keep at least one stone type.');
        }
        $this->deleteLocalStoneImageByUrl($paving_stone_type->image_url);
        $paving_stone_type->delete();

        return back()->with('success', 'Stone type deleted.');
    }

    public function storeMarble(Request $request)
    {
        $this->mergeStrippedMoney($request, ['price_per_bag', 'delivery_cost', 'additional_cost', 'bulk_discount_pct']);
        $this->normalizeStoneUrlOnRequest($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_url' => ['nullable', 'string', 'max:500', 'url'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'stone_size_in' => ['nullable', 'numeric', 'min:0.01', 'max:99'],
            'reference_depth_in' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'sq_ft_per_bag_at_reference_depth' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'price_per_bag' => ['required', 'numeric', 'min:0', 'max:99999'],
            'bulk_min_bags' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'bulk_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost_description' => ['nullable', 'string', 'max:255'],
        ]);

        $max = (int) PavingMarbleType::query()->max('sort_order');
        PavingMarbleType::create($this->marblePayloadFromValidated($data, $this->resolveMarbleImageUrl($request)) + [
            'sort_order' => $max + 1,
        ]);

        return back()->with('success', 'Marble type added.');
    }

    public function updateMarble(Request $request, PavingMarbleType $paving_marble_type)
    {
        $this->mergeStrippedMoney($request, ['price_per_bag', 'delivery_cost', 'additional_cost', 'bulk_discount_pct']);
        $this->normalizeStoneUrlOnRequest($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_url' => ['nullable', 'string', 'max:500', 'url'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'stone_size_in' => ['nullable', 'numeric', 'min:0.01', 'max:99'],
            'reference_depth_in' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'sq_ft_per_bag_at_reference_depth' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'price_per_bag' => ['required', 'numeric', 'min:0', 'max:99999'],
            'bulk_min_bags' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'bulk_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'additional_cost_description' => ['nullable', 'string', 'max:255'],
        ]);

        $paving_marble_type->update($this->marblePayloadFromValidated(
            $data,
            $this->resolveMarbleImageUrl($request, $paving_marble_type->image_url)
        ));

        return back()->with('success', 'Marble type updated.');
    }

    public function destroyMarble(PavingMarbleType $paving_marble_type)
    {
        $this->deleteLocalMarbleImageByUrl($paving_marble_type->image_url);
        $paving_marble_type->delete();

        return back()->with('success', 'Marble type deleted.');
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
        foreach (['product_url', 'image_url'] as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $url = trim((string) $request->input($key));
            if ($url === '') {
                $request->merge([$key => null]);

                continue;
            }
            if (str_starts_with($url, '/')) {
                $request->merge([$key => $url]);

                continue;
            }
            if (! preg_match('#^https?://#i', $url)) {
                $request->merge([$key => 'https://'.$url]);
            } else {
                $request->merge([$key => $url]);
            }
        }
    }

    protected function resolveStoneImageUrl(Request $request, ?string $existingUrl = null): ?string
    {
        $file = $request->file('image');
        if ($file instanceof UploadedFile && $file->isValid()) {
            return $this->storeStoneImage($file, $existingUrl);
        }

        if (! $request->exists('image_url')) {
            return $existingUrl;
        }

        $url = trim((string) $request->input('image_url'));
        if ($url === '') {
            if ($existingUrl && $this->isLocalStoneImageUrl($existingUrl)) {
                $this->deleteLocalStoneImageByUrl($existingUrl);
            }

            return null;
        }

        if ($existingUrl && $existingUrl !== $url && $this->isLocalStoneImageUrl($existingUrl)) {
            $this->deleteLocalStoneImageByUrl($existingUrl);
        }

        return $url;
    }

    protected function storeStoneImage(UploadedFile $file, ?string $previousUrl = null): string
    {
        Storage::disk('public')->makeDirectory('paving-stones');

        $path = $file->store('paving-stones', 'public');
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'image' => 'Could not save the stone image. Check that storage/app/public is writable.',
            ]);
        }

        app(ProjectPhotoWebpStorage::class)->syncPublicMirror($path);

        if ($previousUrl && $this->isLocalStoneImageUrl($previousUrl)) {
            $this->deleteLocalStoneImageByUrl($previousUrl);
        }

        return Storage::disk('public')->url($path);
    }

    protected function isLocalStoneImageUrl(?string $url): bool
    {
        if (! filled($url)) {
            return false;
        }

        return str_contains($url, '/storage/paving-stones/');
    }

    protected function deleteLocalStoneImageByUrl(?string $url): void
    {
        if (! $this->isLocalStoneImageUrl($url)) {
            return;
        }

        $path = parse_url((string) $url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return;
        }

        $relative = ltrim(str_replace('/storage/', '', $path), '/');
        if ($relative === '') {
            return;
        }

        Storage::disk('public')->delete($relative);

        $mirror = public_path('storage/'.str_replace('\\', '/', $relative));
        if (is_file($mirror)) {
            @unlink($mirror);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stonePayloadFromValidated(array $data, ?string $imageUrlOverride = null): array
    {
        $url = trim((string) ($data['product_url'] ?? ''));
        $imgUrl = $imageUrlOverride ?? trim((string) ($data['image_url'] ?? ''));
        $length = isset($data['length_in']) && $data['length_in'] !== '' ? (float) $data['length_in'] : null;
        $width = isset($data['width_in']) && $data['width_in'] !== '' ? (float) $data['width_in'] : null;
        $height = isset($data['height_in']) && $data['height_in'] !== '' ? (float) $data['height_in'] : null;

        $sqFt = isset($data['sq_ft_per_stone']) && $data['sq_ft_per_stone'] !== '' ? (float) $data['sq_ft_per_stone'] : null;
        $price = isset($data['price_per_stone']) && $data['price_per_stone'] !== '' ? (float) $data['price_per_stone'] : null;

        $pricePerSqFt = isset($data['price_per_sq_ft']) && $data['price_per_sq_ft'] !== '' ? (float) $data['price_per_sq_ft'] : null;
        if ($sqFt > 0 && $price !== null) {
            $pricePerSqFt = round($price / $sqFt, 4);
        }

        $deliveryCost = isset($data['delivery_cost']) && $data['delivery_cost'] !== '' ? (float) $data['delivery_cost'] : null;
        $addCost = isset($data['additional_cost']) && $data['additional_cost'] !== '' ? (float) $data['additional_cost'] : null;
        $addCostDesc = trim((string) ($data['additional_cost_description'] ?? ''));

        return [
            'name' => trim($data['name']),
            'product_url' => $url !== '' ? $url : null,
            'image_url' => $imgUrl !== '' ? $imgUrl : null,
            'length_in' => $length,
            'width_in' => $width,
            'height_in' => $height,
            'sq_ft_per_stone' => $sqFt,
            'price_per_stone' => $price,
            'price_per_sq_ft' => $pricePerSqFt,
            'delivery_cost' => $deliveryCost,
            'additional_cost' => $addCost,
            'additional_cost_description' => $addCostDesc !== '' ? $addCostDesc : null,
        ];
    }

    protected function resolveMarbleImageUrl(Request $request, ?string $existingUrl = null): ?string
    {
        $file = $request->file('image');
        if ($file instanceof UploadedFile && $file->isValid()) {
            return $this->storeMarbleImage($file, $existingUrl);
        }

        if (! $request->exists('image_url')) {
            return $existingUrl;
        }

        $url = trim((string) $request->input('image_url'));
        if ($url === '') {
            if ($existingUrl && $this->isLocalMarbleImageUrl($existingUrl)) {
                $this->deleteLocalMarbleImageByUrl($existingUrl);
            }

            return null;
        }

        if ($existingUrl && $existingUrl !== $url && $this->isLocalMarbleImageUrl($existingUrl)) {
            $this->deleteLocalMarbleImageByUrl($existingUrl);
        }

        return $url;
    }

    protected function storeMarbleImage(UploadedFile $file, ?string $previousUrl = null): string
    {
        Storage::disk('public')->makeDirectory('paving-marble');

        $path = $file->store('paving-marble', 'public');
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'image' => 'Could not save the marble image. Check that storage/app/public is writable.',
            ]);
        }

        app(ProjectPhotoWebpStorage::class)->syncPublicMirror($path);

        if ($previousUrl && $this->isLocalMarbleImageUrl($previousUrl)) {
            $this->deleteLocalMarbleImageByUrl($previousUrl);
        }

        return Storage::disk('public')->url($path);
    }

    protected function isLocalMarbleImageUrl(?string $url): bool
    {
        if (! filled($url)) {
            return false;
        }

        return str_contains($url, '/storage/paving-marble/');
    }

    protected function deleteLocalMarbleImageByUrl(?string $url): void
    {
        if (! $this->isLocalMarbleImageUrl($url)) {
            return;
        }

        $path = parse_url((string) $url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return;
        }

        $relative = ltrim(str_replace('/storage/', '', $path), '/');
        if ($relative === '') {
            return;
        }

        Storage::disk('public')->delete($relative);

        $mirror = public_path('storage/'.str_replace('\\', '/', $relative));
        if (is_file($mirror)) {
            @unlink($mirror);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function marblePayloadFromValidated(array $data, ?string $imageUrlOverride = null): array
    {
        $url = trim((string) ($data['product_url'] ?? ''));
        $imgUrl = $imageUrlOverride ?? trim((string) ($data['image_url'] ?? ''));
        $stoneSize = isset($data['stone_size_in']) && $data['stone_size_in'] !== '' ? (float) $data['stone_size_in'] : null;
        $refDepth = (float) ($data['reference_depth_in'] ?? 2);
        $sqFtPerBag = (float) ($data['sq_ft_per_bag_at_reference_depth'] ?? 0);
        $pricePerBag = (float) ($data['price_per_bag'] ?? 0);
        $bulkMin = isset($data['bulk_min_bags']) && $data['bulk_min_bags'] !== '' ? (int) $data['bulk_min_bags'] : null;
        $bulkPct = isset($data['bulk_discount_pct']) && $data['bulk_discount_pct'] !== '' ? (float) $data['bulk_discount_pct'] : null;
        $deliveryCost = isset($data['delivery_cost']) && $data['delivery_cost'] !== '' ? (float) $data['delivery_cost'] : null;
        $addCost = isset($data['additional_cost']) && $data['additional_cost'] !== '' ? (float) $data['additional_cost'] : null;
        $addCostDesc = trim((string) ($data['additional_cost_description'] ?? ''));

        return [
            'name' => trim($data['name']),
            'product_url' => $url !== '' ? $url : null,
            'image_url' => $imgUrl !== '' ? $imgUrl : null,
            'stone_size_in' => $stoneSize,
            'reference_depth_in' => $refDepth,
            'sq_ft_per_bag_at_reference_depth' => $sqFtPerBag,
            'price_per_bag' => $pricePerBag,
            'bulk_min_bags' => $bulkMin,
            'bulk_discount_pct' => $bulkPct,
            'delivery_cost' => $deliveryCost,
            'additional_cost' => $addCost,
            'additional_cost_description' => $addCostDesc !== '' ? $addCostDesc : null,
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

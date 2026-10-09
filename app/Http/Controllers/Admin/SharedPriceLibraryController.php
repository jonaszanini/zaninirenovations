<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use App\Support\CadMoney;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $this->normalizeMoneyFields($request);

        $data = $request->validate([
            'owner_hourly_rate' => ['required', 'numeric', 'min:0'],
            'helper_hourly_rate' => ['required', 'numeric', 'min:0'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'works' => ['nullable', 'array'],
            'works.*.id' => ['required', 'integer', 'exists:shared_works,id'],
            'works.*.label' => ['nullable', 'string', 'max:160'],
            'works.*.rate' => ['nullable', 'numeric', 'min:0'],
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
            'new_works' => ['nullable', 'array'],
            'new_works.*' => ['nullable', 'array'],
            'new_works.*.*.label' => ['nullable', 'string', 'max:160'],
            'new_works.*.*.unit' => ['nullable', 'string', 'max:20'],
            'new_works.*.*.rate' => ['nullable', 'numeric', 'min:0'],
            'new_works.*.*.flat_rate' => ['nullable', 'numeric', 'min:0'],
            'new_works.*.*.consumers' => ['nullable', 'array'],
            'new_works.*.*.consumers.*' => ['nullable', 'string', Rule::in(array_keys(SharedWorkCatalog::CONSUMER_LABELS))],
        ]);

        $this->assertUniqueLabelsInGroup($data);

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
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $work->label = $label;
            }
            $catalogId = (int) ($row['catalog_material_id'] ?? 0);
            $owner = (float) $crew->owner_hourly_rate;
            $flat = isset($row['flat_rate']) && $row['flat_rate'] !== '' && $row['flat_rate'] !== null
                ? round((float) $row['flat_rate'], 4)
                : null;

            if ($work->isPricedPerArea()) {
                $work->labor_kind = SharedWork::KIND_FLAT;
                $work->flat_rate = $flat;
                if ($flat !== null && $flat > 0 && $owner > 0) {
                    $work->rate = round($owner / $flat, 4);
                } elseif (isset($row['rate']) && is_numeric($row['rate'])) {
                    $work->rate = round((float) $row['rate'], 4);
                }
            } else {
                if (isset($row['rate']) && is_numeric($row['rate'])) {
                    $work->rate = round((float) $row['rate'], 4);
                }
                $work->flat_rate = $flat !== null ? $flat : $work->flat_rate;
            }
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

        $this->createNewWorks($data);

        SharedWorkCatalog::forgetCache();

        return redirect()
            ->route('admin.quotations.shared-prices.edit')
            ->with('status', 'Shared prices saved. New quotation previews use these rates.');
    }

    /**
     * Strip thousands separators from masked CAD money fields before numeric validation.
     */
    protected function normalizeMoneyFields(Request $request): void
    {
        foreach (['owner_hourly_rate', 'helper_hourly_rate'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => CadMoney::stripGrouping($request->input($field))]);
            }
        }

        $works = $request->input('works');
        if (is_array($works)) {
            foreach ($works as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['rate', 'flat_rate', 'material_unit_price'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $works[$i][$field] = CadMoney::stripGrouping($row[$field]);
                    }
                }
            }
            $request->merge(['works' => $works]);
        }

        $newWorks = $request->input('new_works');
        if (! is_array($newWorks)) {
            return;
        }
        foreach ($newWorks as $groupKey => $rows) {
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['rate', 'flat_rate'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $newWorks[$groupKey][$i][$field] = CadMoney::stripGrouping($row[$field]);
                    }
                }
            }
        }
        $request->merge(['new_works' => $newWorks]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    protected function postedWorkIds(array $data): array
    {
        $ids = [];
        foreach ($data['works'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function assertUniqueLabelsInGroup(array $data): void
    {
        $seen = [];
        $errors = [];
        $postedIds = $this->postedWorkIds($data);

        foreach ($data['works'] ?? [] as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $work = SharedWork::query()->find((int) ($row['id'] ?? 0));
            if (! $work) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? $work->label));
            if ($label === '') {
                $errors['works.'.$idx.'.label'] = 'Enter a name for this item.';

                continue;
            }
            $norm = SharedWork::normalizeLabel($label);
            $bucket = $work->group_key.'|'.$norm;
            $taken = SharedWork::query()
                ->where('group_key', $work->group_key)
                ->where('id', '!=', $work->id)
                ->when($postedIds !== [], fn ($q) => $q->whereNotIn('id', $postedIds))
                ->whereRaw('LOWER(TRIM(label)) = ?', [$norm])
                ->exists();
            if (isset($seen[$bucket]) || $taken) {
                $errors['works.'.$idx.'.label'] = 'That name is already used in this group.';
            }
            $seen[$bucket] = true;
        }

        foreach ($data['new_works'] ?? [] as $groupKey => $rows) {
            if (! is_string($groupKey) || ! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $norm = SharedWork::normalizeLabel($label);
                $bucket = $groupKey.'|'.$norm;
                $taken = SharedWork::query()
                    ->where('group_key', $groupKey)
                    ->when($postedIds !== [], fn ($q) => $q->whereNotIn('id', $postedIds))
                    ->whereRaw('LOWER(TRIM(label)) = ?', [$norm])
                    ->exists();
                if (isset($seen[$bucket]) || $taken) {
                    $errors['new_works.'.$groupKey.'.'.$i.'.label'] = 'That name is already used in this group.';
                }
                $seen[$bucket] = true;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createNewWorks(array $data): void
    {
        $owner = (float) ($data['owner_hourly_rate'] ?? SharedWorkCatalog::ownerHourlyRate());
        $maxSort = (int) SharedWork::query()->max('sort_order');

        foreach ($data['new_works'] ?? [] as $groupKey => $rows) {
            if (! is_string($groupKey) || ! isset(SharedWorkCatalog::GROUPS[$groupKey]) || ! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $unit = strtolower(trim((string) ($row['unit'] ?? 'each')));
                if ($unit === '') {
                    $unit = 'each';
                }
                $rate = isset($row['rate']) && $row['rate'] !== '' && $row['rate'] !== null
                    ? round((float) $row['rate'], 4)
                    : 0.0;
                $flat = isset($row['flat_rate']) && $row['flat_rate'] !== '' && $row['flat_rate'] !== null
                    ? round((float) $row['flat_rate'], 4)
                    : null;
                $isArea = in_array($unit, ['lf', 'sf'], true);
                $kind = $isArea || ($flat !== null && $flat > 0 && $rate <= 0)
                    ? SharedWork::KIND_FLAT
                    : SharedWork::KIND_HOURS_EACH;
                if ($isArea && $flat !== null && $flat > 0 && $owner > 0) {
                    $rate = round($owner / $flat, 4);
                }
                $consumers = [];
                foreach ($row['consumers'] ?? SharedWorkCatalog::defaultConsumersForGroup($groupKey) as $consumer) {
                    $consumer = (string) $consumer;
                    if (isset(SharedWorkCatalog::CONSUMER_LABELS[$consumer])) {
                        $consumers[] = $consumer;
                    }
                }
                $consumers = array_values(array_unique($consumers));
                $maxSort++;
                SharedWork::query()->create([
                    'key' => SharedWork::allocateUniqueKey($label),
                    'group_key' => $groupKey,
                    'label' => $label,
                    'unit' => $unit,
                    'labor_kind' => $kind,
                    'rate' => $rate,
                    'flat_rate' => $flat,
                    'material_unit' => $unit,
                    'consumers' => $consumers,
                    'sort_order' => $maxSort,
                ]);
            }
        }
    }
}
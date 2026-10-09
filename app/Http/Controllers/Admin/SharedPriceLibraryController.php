<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use App\Support\CadMoney;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $money = ['nullable', 'numeric', 'min:0', 'max:'.SharedWork::MONEY_MAX];
        $requiredMoney = ['required', 'numeric', 'min:0', 'max:'.SharedWork::MONEY_MAX];

        $data = $request->validate([
            'owner_hourly_rate' => $requiredMoney,
            'helper_hourly_rate' => $requiredMoney,
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'works' => ['nullable', 'array'],
            'works.*.id' => ['required', 'integer', 'exists:shared_works,id'],
            'works.*.label' => ['nullable', 'string', 'max:'.SharedWork::LABEL_MAX],
            'works.*.rate' => $money,
            'works.*.flat_rate' => $money,
            'works.*.catalog_material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'works.*.material_name' => ['nullable', 'string', 'max:160'],
            'works.*.material_unit' => ['nullable', 'string', 'max:40'],
            'works.*.material_unit_price' => $money,
            'works.*.labor_kind' => ['nullable', Rule::in([
                SharedWork::KIND_PER_HOUR,
                SharedWork::KIND_HOURS_EACH,
                SharedWork::KIND_FLAT,
            ])],
            'new_works' => ['nullable', 'array'],
            'new_works.*' => ['nullable', 'array'],
            'new_works.*.*.label' => ['nullable', 'string', 'max:'.SharedWork::LABEL_MAX],
            'new_works.*.*.unit' => ['nullable', 'string', 'max:20'],
            'new_works.*.*.rate' => $money,
            'new_works.*.*.flat_rate' => $money,
            'new_works.*.*.consumers' => ['nullable', 'array'],
            'new_works.*.*.consumers.*' => ['nullable', 'string', Rule::in(array_keys(SharedWorkCatalog::CONSUMER_LABELS))],
        ], $this->validationMessages());

        $this->assertUniqueLabelsInGroup($data);

        try {
            DB::transaction(function () use ($data) {
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
                    $label = SharedWork::displayLabel((string) ($row['label'] ?? ''));
                    if ($label !== '') {
                        $work->label = $label;
                    }
                    $catalogId = (int) ($row['catalog_material_id'] ?? 0);
                    $owner = (float) $crew->owner_hourly_rate;
                    $ratePosted = array_key_exists('rate', $row) && $row['rate'] !== '' && $row['rate'] !== null;
                    $flat = isset($row['flat_rate']) && $row['flat_rate'] !== '' && $row['flat_rate'] !== null
                        ? round((float) $row['flat_rate'], 4)
                        : null;

                    if ($work->isPricedPerArea()) {
                        $work->labor_kind = SharedWork::KIND_FLAT;
                        $work->flat_rate = $flat;
                        if ($ratePosted) {
                            $work->rate = round((float) $row['rate'], 4);
                        } elseif ($flat !== null && $flat > 0 && $owner > 0) {
                            $work->rate = round($owner / $flat, 4);
                        }
                    } else {
                        if ($ratePosted) {
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
            });
        } catch (QueryException $e) {
            if ($this->isLabelUniqueFailure($e)) {
                throw ValidationException::withMessages([
                    'new_works' => 'That name is already used in this group.',
                ]);
            }
            throw $e;
        }

        SharedWorkCatalog::forgetCache();

        return redirect()
            ->route('admin.quotations.shared-prices.edit')
            ->with('success', 'Shared prices saved. New quotation previews use these rates.');
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        $nameMax = 'The name may not be longer than '.SharedWork::LABEL_MAX.' characters.';
        $priceMax = 'Enter a price of 999,999.99 or less.';
        $priceMin = 'Prices cannot be negative.';

        return [
            'works.*.label.max' => $nameMax,
            'new_works.*.*.label.max' => $nameMax,
            'owner_hourly_rate.max' => $priceMax,
            'helper_hourly_rate.max' => $priceMax,
            'owner_hourly_rate.min' => $priceMin,
            'helper_hourly_rate.min' => $priceMin,
            'works.*.rate.max' => $priceMax,
            'works.*.flat_rate.max' => $priceMax,
            'works.*.material_unit_price.max' => $priceMax,
            'works.*.rate.min' => $priceMin,
            'works.*.flat_rate.min' => $priceMin,
            'works.*.material_unit_price.min' => $priceMin,
            'new_works.*.*.rate.max' => $priceMax,
            'new_works.*.*.flat_rate.max' => $priceMax,
            'new_works.*.*.rate.min' => $priceMin,
            'new_works.*.*.flat_rate.min' => $priceMin,
        ];
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

    /**
     * @param  array<string, mixed>  $data
     */
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
            if (! array_key_exists('label', $row)) {
                $label = SharedWork::displayLabel((string) $work->label);
            } else {
                $label = SharedWork::displayLabel((string) $row['label']);
            }
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
                ->where('label_normalized', $norm)
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
                $label = SharedWork::displayLabel((string) ($row['label'] ?? ''));
                $hasPrices = $this->rowHasPrices($row);
                if ($label === '') {
                    if ($hasPrices) {
                        $errors['new_works.'.$groupKey.'.'.$i.'.label'] = 'Enter a name for this item.';
                    }

                    continue;
                }
                $norm = SharedWork::normalizeLabel($label);
                $bucket = $groupKey.'|'.$norm;
                $taken = SharedWork::query()
                    ->where('group_key', $groupKey)
                    ->when($postedIds !== [], fn ($q) => $q->whereNotIn('id', $postedIds))
                    ->where('label_normalized', $norm)
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
     * @param  array<string, mixed>  $row
     */
    protected function rowHasPrices(array $row): bool
    {
        foreach (['rate', 'flat_rate'] as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === '' || $row[$field] === null) {
                continue;
            }
            if (is_numeric($row[$field])) {
                return true;
            }
        }

        return false;
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
                $label = SharedWork::displayLabel((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $unit = strtolower(trim((string) ($row['unit'] ?? 'each')));
                if ($unit === '') {
                    $unit = 'each';
                }
                $ratePosted = array_key_exists('rate', $row) && $row['rate'] !== '' && $row['rate'] !== null;
                $rate = $ratePosted ? round((float) $row['rate'], 4) : 0.0;
                $flat = isset($row['flat_rate']) && $row['flat_rate'] !== '' && $row['flat_rate'] !== null
                    ? round((float) $row['flat_rate'], 4)
                    : null;
                $isArea = in_array($unit, ['lf', 'sf'], true);
                if ($ratePosted) {
                    $kind = $isArea ? SharedWork::KIND_PER_HOUR : SharedWork::KIND_HOURS_EACH;
                } elseif ($flat !== null && $flat > 0) {
                    $kind = SharedWork::KIND_FLAT;
                    if ($isArea && $owner > 0) {
                        $rate = round($owner / $flat, 4);
                    }
                } else {
                    $kind = $isArea ? SharedWork::KIND_FLAT : SharedWork::KIND_HOURS_EACH;
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
                $this->createWorkWithUniqueKey([
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function createWorkWithUniqueKey(array $attrs): SharedWork
    {
        $label = (string) $attrs['label'];
        $attempt = 0;
        while ($attempt < 8) {
            $attrs['key'] = SharedWork::allocateUniqueKey($label);
            try {
                return SharedWork::query()->create($attrs);
            } catch (QueryException $e) {
                if ($this->isLabelUniqueFailure($e) || ! $this->isKeyUniqueFailure($e)) {
                    throw $e;
                }
                $attempt++;
            }
        }

        throw ValidationException::withMessages([
            'new_works' => 'Could not allocate a unique item key. Try a different name.',
        ]);
    }

    protected function isUniqueConstraintFailure(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }

    protected function isLabelUniqueFailure(QueryException $e): bool
    {
        if (! $this->isUniqueConstraintFailure($e)) {
            return false;
        }
        $haystack = strtolower($this->uniqueConstraintHaystack($e));

        return str_contains($haystack, 'shared_works_group_label_unique')
            || str_contains($haystack, 'unique constraint failed: shared_works.label_normalized');
    }

    protected function isKeyUniqueFailure(QueryException $e): bool
    {
        if (! $this->isUniqueConstraintFailure($e)) {
            return false;
        }
        $haystack = strtolower($this->uniqueConstraintHaystack($e));

        return str_contains($haystack, 'shared_works_key_unique')
            || str_contains($haystack, 'unique constraint failed: shared_works.key');
    }

    protected function uniqueConstraintHaystack(QueryException $e): string
    {
        $info = $e->errorInfo ?? [];

        return (string) ($info[2] ?? $e->getMessage());
    }
}

<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Offcut;
use App\Services\CutOptimizer\GuillotineSheet;
use App\Services\CutOptimizer\StockOption;
use Illuminate\Support\Collection;

/**
 * Shared leftover inventory for the lumber/board calculator (1D) and
 * millwork sheet optimizer (2D). Sheet leftovers surface in the lumber
 * view as linear stock using the longer side, and vice versa when a
 * linear leftover has a usable second dimension.
 */
class OffcutInventoryService
{
    /**
     * @return Collection<int, Offcut>
     */
    public function available(?string $materialKey = null): Collection
    {
        $query = Offcut::query()->available()->with(['createdFromProject:id,name,client_name']);
        if ($materialKey !== null && $materialKey !== '') {
            $query->where('material_key', $materialKey);
        }

        return $query->orderByDesc('id')->get();
    }

    /**
     * Payload for the lumber calculator and millwork optimizer.
     *
     * @return array{linear: list<array<string, mixed>>, sheets: list<array<string, mixed>>}
     */
    public function sharedPayload(?string $materialKey = null): array
    {
        $rows = $this->available($materialKey);
        $prices = $this->catalogPrices($rows->pluck('material_key')->unique()->filter()->all());

        $linear = [];
        $sheets = [];

        foreach ($rows as $offcut) {
            $price = $prices[$this->priceLookupKey($offcut->material_key)] ?? null;
            $base = $this->rowPayload($offcut, $price);

            if ($offcut->isSheet()) {
                $sheets[] = $base;
                $linear[] = array_merge($base, [
                    'bridged' => true,
                    'bridge_from' => 'sheet',
                    'length_in' => $offcut->longerSideIn(),
                    'usable_width_in' => $offcut->shorterSideIn(),
                ]);
            } else {
                $linear[] = array_merge($base, ['bridged' => false]);
                $width = (float) ($offcut->width_in ?? 0);
                $thickness = (float) ($offcut->thickness_in ?? 0);
                $sheetWidth = $width > 0 ? $width : $thickness;
                if ($sheetWidth >= 6.0) {
                    $sheets[] = array_merge($base, [
                        'bridged' => true,
                        'bridge_from' => 'linear',
                        'width_in' => $sheetWidth,
                        'length_in' => (float) $offcut->length_in,
                    ]);
                }
            }
        }

        return [
            'linear' => $linear,
            'sheets' => $sheets,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function consume(array $ids, ?int $projectId = null, int $qtyEach = 1): array
    {
        $used = [];
        $qtyEach = max(1, $qtyEach);
        $rows = Offcut::query()->available()->whereIn('id', $ids)->get();
        foreach ($rows as $offcut) {
            if ($offcut->consume($qtyEach, $projectId) > 0) {
                $used[] = (int) $offcut->id;
            }
        }

        return $used;
    }

    /**
     * Persist leftovers that meet the minimum size. Sheet leftovers need
     * both width and length ≥ min; linear leftovers need length ≥ min.
     *
     * @param  list<array<string, mixed>>  $leftovers
     * @return list<Offcut>
     */
    public function saveLeftovers(array $leftovers, float $minSize, ?int $projectId = null): array
    {
        $created = [];
        foreach ($leftovers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $length = round((float) ($row['length_in'] ?? 0), 3);
            $width = isset($row['width_in']) && $row['width_in'] !== '' && $row['width_in'] !== null
                ? round((float) $row['width_in'], 3)
                : 0.0;
            $isSheet = $width > 0;
            $min = $minSize > 0 ? $minSize : ($isSheet ? Offcut::MIN_SHEET_IN : Offcut::MIN_LINEAR_IN);
            if ($length + 0.001 < $min) {
                continue;
            }
            if ($isSheet && $width + 0.001 < $min) {
                continue;
            }
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $key = $this->normaliseKey((string) ($row['material_key'] ?? 'offcut'));
            $created[] = Offcut::query()->create([
                'material_key' => $key,
                'thickness_in' => $this->nullableDecimal($row['thickness_in'] ?? null),
                'width_in' => $isSheet ? $width : null,
                'length_in' => $length,
                'qty' => $qty,
                'location' => $this->nullableString($row['location'] ?? null, 120),
                'notes' => $this->nullableString($row['notes'] ?? null, 255),
                'created_from_project_id' => $projectId,
            ]);
        }

        return $created;
    }

    /**
     * @param  list<GuillotineSheet>  $sheets
     * @return list<array<string, mixed>>
     */
    public function leftoversFromSheets(array $sheets, string $materialKey, float $minSize, ?float $thicknessIn = null): array
    {
        $out = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet->leftoverRectangles($minSize) as $rect) {
                $out[] = [
                    'material_key' => $materialKey,
                    'thickness_in' => $thicknessIn,
                    'width_in' => round($rect->width, 3),
                    'length_in' => round($rect->height, 3),
                    'qty' => 1,
                    'notes' => $sheet->offcutId
                        ? 'Remainder of inventory offcut #'.$sheet->offcutId
                        : 'Leftover from '.$sheet->label,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Offcut>|list<Offcut>  $offcuts
     * @return list<StockOption>
     */
    public function asSheetStock(iterable $offcuts): array
    {
        $stock = [];
        foreach ($offcuts as $offcut) {
            if (! $offcut instanceof Offcut || ! $offcut->isAvailable()) {
                continue;
            }
            $width = $offcut->isSheet()
                ? (float) $offcut->width_in
                : (float) ($offcut->width_in ?: $offcut->thickness_in ?: 0);
            $length = (float) $offcut->length_in;
            if ($width <= 0 || $length <= 0) {
                continue;
            }
            $stock[] = new StockOption(
                'offcut-'.$offcut->id,
                $offcut->displayName().' leftover '.$offcut->sizeLabel(),
                $width,
                $length,
                0.0,
                null,
                (int) $offcut->id,
                max(1, (int) $offcut->qty)
            );
        }

        return $stock;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, float>
     */
    public function catalogPrices(array $keys): array
    {
        $out = [];
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        if ($keys === []) {
            return $out;
        }

        $materials = Material::query()
            ->where('is_active', true)
            ->whereNotNull('price_cad')
            ->where('price_cad', '>', 0)
            ->get(['id', 'name', 'short_name', 'price_cad']);

        foreach ($keys as $key) {
            $needle = $this->priceLookupKey($key);
            if ($needle === '') {
                continue;
            }
            $match = $materials->first(function (Material $m) use ($needle): bool {
                $name = $this->priceLookupKey((string) $m->short_name);
                $full = $this->priceLookupKey((string) $m->name);

                return $name === $needle
                    || $full === $needle
                    || ($name !== '' && str_contains($full, $needle))
                    || ($needle !== '' && str_contains($name, $needle))
                    || ($needle !== '' && str_contains($full, $needle));
            });
            if ($match) {
                $price = round((float) $match->price_cad, 2);
                $out[$needle] = $price;
                $out[$key] = $price;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function rowPayload(Offcut $offcut, ?float $catalogPrice = null): array
    {
        return [
            'id' => (int) $offcut->id,
            'material_key' => $offcut->material_key,
            'thickness_in' => $offcut->thickness_in !== null ? (float) $offcut->thickness_in : null,
            'width_in' => $offcut->width_in !== null ? (float) $offcut->width_in : null,
            'length_in' => (float) $offcut->length_in,
            'qty' => (int) $offcut->qty,
            'location' => $offcut->location,
            'notes' => $offcut->notes,
            'size_label' => $offcut->sizeLabel(),
            'is_sheet' => $offcut->isSheet(),
            'catalog_price' => $catalogPrice,
            'created_from_project_id' => $offcut->created_from_project_id,
            'created_from_project_name' => $offcut->createdFromProject?->linkSelectLabel(),
        ];
    }

    public function normaliseKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/\s+/', '-', $key) ?? $key;
        $key = mb_substr($key, 0, 80);

        return $key !== '' ? $key : 'offcut';
    }

    private function priceLookupKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace(['×', 'x'], 'x', $key);
        $key = preg_replace('/[^a-z0-9]+/', '', $key) ?? $key;

        return $key;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (float) $value;

        return $n > 0 ? round($n, 3) : null;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $s = trim((string) $value);

        return $s !== '' ? mb_substr($s, 0, $max) : null;
    }
}

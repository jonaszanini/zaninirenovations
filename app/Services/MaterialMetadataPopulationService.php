<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Collection;

class MaterialMetadataPopulationService
{
    public function __construct(
        private MaterialMetadataEnricher $enricher,
    ) {}

    /**
     * @param  iterable<int, Material>  $materials
     * @return array{updated: int, skipped: int}
     */
    public function applyToMaterials(
        iterable $materials,
        bool $skipCategory = false,
        bool $onlyShortName = false,
    ): array {
        $updated = 0;
        $skipped = 0;

        foreach ($materials as $material) {
            if ($this->applyToMaterial($material, $skipCategory, $onlyShortName)) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function applyToMaterial(
        Material $material,
        bool $skipCategory = false,
        bool $onlyShortName = false,
    ): bool {
        $changes = $this->suggestedChanges($material, $skipCategory, $onlyShortName);

        if ($changes === []) {
            return false;
        }

        $material->fill($changes);
        $material->save();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestedChanges(
        Material $material,
        bool $skipCategory = false,
        bool $onlyShortName = false,
    ): array {
        if ($onlyShortName && filled($material->short_name)) {
            return [];
        }

        $suggestions = $this->enricher->suggest($material);
        $changes = [];

        if ($suggestions['short_name'] !== null && blank($material->short_name)) {
            $changes['short_name'] = $suggestions['short_name'];
        }

        if (! $onlyShortName && ! $skipCategory && $suggestions['category_id'] !== null) {
            if ((int) $material->category_id !== (int) $suggestions['category_id']) {
                $changes['category_id'] = $suggestions['category_id'];
            }
        }

        if (! $onlyShortName && $suggestions['unit'] !== null && blank($material->unit)) {
            $changes['unit'] = $suggestions['unit'];
        }

        return $changes;
    }

    /**
     * @return Collection<int, array<int, string|int|null>>
     */
    public function previewAll(bool $skipCategory = false, bool $onlyShortName = false): Collection
    {
        $rows = collect();

        $this->materialsQuery($onlyShortName)->orderBy('id')->each(function (Material $material) use ($skipCategory, $onlyShortName, $rows) {
            $changes = $this->suggestedChanges($material, $skipCategory, $onlyShortName);

            if ($changes === []) {
                return;
            }

            $rows->push([
                $material->id,
                $material->short_name ?: '—',
                $changes['short_name'] ?? '—',
                $material->category_id ?: '—',
                $changes['category_id'] ?? '—',
                $material->unit ?: '—',
                $changes['unit'] ?? '—',
                \Illuminate\Support\Str::limit($material->name, 50),
            ]);
        });

        return $rows;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Material>
     */
    public function materialsQuery(bool $onlyShortName = false): \Illuminate\Database\Eloquent\Builder
    {
        $query = Material::query();

        if ($onlyShortName) {
            $query->where(function ($q) {
                $q->whereNull('short_name')->orWhere('short_name', '');
            });
        }

        return $query;
    }
}

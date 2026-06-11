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
        bool $forceShortName = false,
        bool $skipCategory = false,
    ): array {
        $updated = 0;
        $skipped = 0;

        foreach ($materials as $material) {
            if ($this->applyToMaterial($material, $forceShortName, $skipCategory)) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function applyToMaterial(
        Material $material,
        bool $forceShortName = false,
        bool $skipCategory = false,
    ): bool {
        $changes = $this->suggestedChanges($material, $forceShortName, $skipCategory);

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
        bool $forceShortName = false,
        bool $skipCategory = false,
    ): array {
        $suggestions = $this->enricher->suggest($material);
        $changes = [];

        if ($suggestions['short_name'] !== null
            && ($forceShortName || blank($material->short_name))) {
            if ($forceShortName || $material->short_name !== $suggestions['short_name']) {
                $changes['short_name'] = $suggestions['short_name'];
            }
        }

        if (! $skipCategory && $suggestions['category_id'] !== null) {
            if ((int) $material->category_id !== (int) $suggestions['category_id']) {
                $changes['category_id'] = $suggestions['category_id'];
            }
        }

        if ($suggestions['unit'] !== null && blank($material->unit)) {
            $changes['unit'] = $suggestions['unit'];
        }

        return $changes;
    }

    /**
     * @return Collection<int, array<int, string|int|null>>
     */
    public function previewAll(bool $forceShortName = false, bool $skipCategory = false): Collection
    {
        $rows = collect();

        Material::query()->orderBy('id')->each(function (Material $material) use ($forceShortName, $skipCategory, $rows) {
            $changes = $this->suggestedChanges($material, $forceShortName, $skipCategory);

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
}

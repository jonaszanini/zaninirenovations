<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Collection;

class MaterialMetadataPopulationService
{
    public function __construct(
        private MaterialMetadataEnricher $enricher,
        private MaterialShortNameAiService $shortNameAi,
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
        $materials = collect($materials)->values();
        $updated = 0;
        $skipped = 0;

        $shortNameCandidates = $materials->filter(function (Material $material) use ($onlyShortName) {
            if ($onlyShortName && filled($material->short_name)) {
                return false;
            }

            return blank($material->short_name);
        });

        $shortNameUpdatedIds = [];
        foreach ($this->chunkMaterials($shortNameCandidates) as $batch) {
            $result = $this->shortNameAi->generateAndApplyBatch($batch, overwrite: false);
            foreach ($result['results'] as $row) {
                $shortNameUpdatedIds[(int) $row['id']] = true;
            }
        }

        foreach ($materials as $material) {
            if ($onlyShortName && filled($material->short_name)) {
                $skipped++;

                continue;
            }

            $material->refresh();
            $changed = $shortNameUpdatedIds[(int) $material->id] ?? false;

            if (! $onlyShortName && $this->applyEnricherFields($material, $skipCategory)) {
                $changed = true;
            }

            if ($changed) {
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

        $changes = [];

        if (blank($material->short_name)) {
            $shortNames = $this->shortNameAi->generateShortNames([[
                'index' => 0,
                'id' => (int) $material->id,
                'store_name' => trim((string) $material->name),
            ]]);

            if ($shortNames !== []) {
                $changes['short_name'] = $shortNames[0]['short_name'];
            }
        }

        if (! $onlyShortName) {
            $suggestions = $this->enricher->suggest($material);

            if (! $skipCategory && $suggestions['category_id'] !== null) {
                if ((int) $material->category_id !== (int) $suggestions['category_id']) {
                    $changes['category_id'] = $suggestions['category_id'];
                }
            }

            if ($suggestions['unit'] !== null && blank($material->unit)) {
                $changes['unit'] = $suggestions['unit'];
            }
        }

        return $changes;
    }

    /**
     * @return Collection<int, array<int, string|int|null>>
     */
    public function previewAll(bool $skipCategory = false, bool $onlyShortName = false): Collection
    {
        $rows = collect();
        $materials = $this->materialsQuery($onlyShortName)->orderBy('id')->get();

        foreach ($this->chunkMaterials($materials->filter(fn (Material $m) => blank($m->short_name))) as $batch) {
            try {
                $items = [];
                foreach ($batch as $index => $material) {
                    $items[] = [
                        'index' => $index,
                        'id' => (int) $material->id,
                        'store_name' => trim((string) $material->name),
                    ];
                }

                $shortNames = $this->shortNameAi->generateShortNames($items);
                $shortNameById = collect($shortNames)->keyBy('id');

                foreach ($batch as $material) {
                    $matched = $shortNameById->get((int) $material->id);
                    $newShort = is_array($matched) ? ($matched['short_name'] ?? null) : null;
                    $changes = [];

                    if ($newShort !== null) {
                        $changes['short_name'] = $newShort;
                    }

                    if (! $onlyShortName) {
                        $suggestions = $this->enricher->suggest($material);
                        if (! $skipCategory && $suggestions['category_id'] !== null
                            && (int) $material->category_id !== (int) $suggestions['category_id']) {
                            $changes['category_id'] = $suggestions['category_id'];
                        }
                        if ($suggestions['unit'] !== null && blank($material->unit)) {
                            $changes['unit'] = $suggestions['unit'];
                        }
                    }

                    if ($changes === []) {
                        continue;
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
                }
            } catch (\Throwable $e) {
                foreach ($batch as $material) {
                    $rows->push([
                        $material->id,
                        $material->short_name ?: '—',
                        'ERROR: '.$e->getMessage(),
                        $material->category_id ?: '—',
                        '—',
                        $material->unit ?: '—',
                        '—',
                        \Illuminate\Support\Str::limit($material->name, 50),
                    ]);
                }
            }
        }

        if (! $onlyShortName) {
            $materials->each(function (Material $material) use ($skipCategory, $rows) {
                if (blank($material->short_name)) {
                    return;
                }

                $changes = [];
                $suggestions = $this->enricher->suggest($material);

                if (! $skipCategory && $suggestions['category_id'] !== null
                    && (int) $material->category_id !== (int) $suggestions['category_id']) {
                    $changes['category_id'] = $suggestions['category_id'];
                }
                if ($suggestions['unit'] !== null && blank($material->unit)) {
                    $changes['unit'] = $suggestions['unit'];
                }

                if ($changes === []) {
                    return;
                }

                $rows->push([
                    $material->id,
                    $material->short_name ?: '—',
                    '—',
                    $material->category_id ?: '—',
                    $changes['category_id'] ?? '—',
                    $material->unit ?: '—',
                    $changes['unit'] ?? '—',
                    \Illuminate\Support\Str::limit($material->name, 50),
                ]);
            });
        }

        return $rows;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Material>
     */
    public function materialsQuery(bool $onlyShortName = false): \Illuminate\Database\Eloquent\Builder
    {
        return $this->shortNameAi->materialsQuery($onlyShortName ? 'missing' : 'all');
    }

    /**
     * @param  Collection<int, Material>|iterable<int, Material>  $materials
     * @return list<list<Material>>
     */
    private function chunkMaterials(iterable $materials): array
    {
        $ids = collect($materials)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $batches = $this->shortNameAi->buildBatches($ids);

        if ($batches === []) {
            return [];
        }

        $byId = collect($materials)->keyBy('id');

        return array_map(
            fn (array $batchIds) => array_values(array_filter(array_map(
                fn (int $id) => $byId->get($id),
                $batchIds,
            ))),
            $batches,
        );
    }

    private function applyEnricherFields(Material $material, bool $skipCategory): bool
    {
        $suggestions = $this->enricher->suggest($material);
        $changes = [];

        if (! $skipCategory && $suggestions['category_id'] !== null) {
            if ((int) $material->category_id !== (int) $suggestions['category_id']) {
                $changes['category_id'] = $suggestions['category_id'];
            }
        }

        if ($suggestions['unit'] !== null && blank($material->unit)) {
            $changes['unit'] = $suggestions['unit'];
        }

        if ($changes === []) {
            return false;
        }

        $material->fill($changes);
        $material->save();

        return true;
    }
}

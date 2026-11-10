<?php

namespace App\Services;

use App\Models\BasementSettings;
use App\Models\Material;
use App\Models\SharedWork;

/**
 * Catalog material IDs used by basement quotations (shared works + basement settings).
 */
class BasementMaterialPriceSyncScope
{
    /**
     * All linked catalog IDs (syncable or not; non-syncable rows count as skipped during sync).
     *
     * @return list<int>
     */
    public function linkedCatalogMaterialIds(): array
    {
        $ids = [];

        SharedWork::query()
            ->whereNotNull('catalog_material_id')
            ->where('catalog_material_id', '>', 0)
            ->get(['id', 'catalog_material_id', 'consumers'])
            ->each(function (SharedWork $work) use (&$ids) {
                $consumers = $work->consumers ?? [];
                if (! is_array($consumers) || ! in_array('basement', $consumers, true)) {
                    return;
                }
                $ids[(int) $work->catalog_material_id] = (int) $work->catalog_material_id;
            });

        foreach (BasementSettings::current()->resolvedMaterials() as $row) {
            if (! is_array($row)) {
                continue;
            }
            $catalogId = (int) ($row['catalog_material_id'] ?? 0);
            if ($catalogId > 0) {
                $ids[$catalogId] = $catalogId;
            }
        }

        $list = array_values($ids);
        sort($list, SORT_NUMERIC);

        return $list;
    }

    /**
     * Linked IDs that have SerpApi/Home Depot or Amazon sync credentials.
     *
     * @return list<int>
     */
    public function syncableLinkedCatalogMaterialIds(): array
    {
        $ids = $this->linkedCatalogMaterialIds();
        if ($ids === []) {
            return [];
        }

        return Material::query()
            ->whereIn('id', $ids)
            ->serpApiSyncable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function syncableLinkedCount(): int
    {
        return count($this->syncableLinkedCatalogMaterialIds());
    }
}

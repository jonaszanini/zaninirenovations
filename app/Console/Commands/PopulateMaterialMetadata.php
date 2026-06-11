<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Services\MaterialMetadataEnricher;
use Illuminate\Console\Command;

class PopulateMaterialMetadata extends Command
{
    protected $signature = 'materials:populate-metadata
                            {--dry-run : Preview changes without saving}
                            {--force-short-name : Overwrite existing short names}
                            {--skip-category : Do not update categories}';

    protected $description = 'One-time helper: suggest short names, categories, and units from material store names';

    public function handle(MaterialMetadataEnricher $enricher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $forceShortName = (bool) $this->option('force-short-name');
        $skipCategory = (bool) $this->option('skip-category');

        $updated = 0;
        $skipped = 0;

        $rows = [];

        Material::query()->orderBy('id')->each(function (Material $material) use (
            $enricher,
            $dryRun,
            $forceShortName,
            $skipCategory,
            &$updated,
            &$skipped,
            &$rows
        ) {
            $suggestions = $enricher->suggest($material);
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

            if ($changes === []) {
                $skipped++;

                return;
            }

            $rows[] = [
                $material->id,
                $material->short_name ?: '—',
                $changes['short_name'] ?? '—',
                $material->category_id ?: '—',
                $changes['category_id'] ?? '—',
                $material->unit ?: '—',
                $changes['unit'] ?? '—',
                \Illuminate\Support\Str::limit($material->name, 50),
            ];

            if (! $dryRun) {
                $material->fill($changes);
                $material->save();
            }

            $updated++;
        });

        if ($rows !== []) {
            $this->table(
                ['ID', 'Old short', 'New short', 'Old cat', 'New cat', 'Old unit', 'New unit', 'Name'],
                $rows
            );
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$updated} material(s); skipped {$skipped} with nothing to change.");

        if ($dryRun && $updated > 0) {
            $this->comment('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}

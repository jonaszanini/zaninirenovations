<?php

namespace App\Console\Commands;

use App\Services\MaterialMetadataPopulationService;
use Illuminate\Console\Command;

class PopulateMaterialMetadata extends Command
{
    protected $signature = 'materials:populate-metadata
                            {--dry-run : Preview changes without saving}
                            {--only-short-name : Create short names only for materials missing one}
                            {--skip-category : Do not update categories}';

    protected $description = 'One-time helper: suggest short names, categories, and units from material store names';

    public function handle(MaterialMetadataPopulationService $population): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyShortName = (bool) $this->option('only-short-name');
        $skipCategory = (bool) $this->option('skip-category');

        $rows = $population->previewAll($skipCategory, $onlyShortName);

        if ($rows->isNotEmpty()) {
            $this->table(
                ['ID', 'Old short', 'New short', 'Old cat', 'New cat', 'Old unit', 'New unit', 'Name'],
                $rows->all()
            );
        }

        if (! $dryRun) {
            $result = $population->applyToMaterials(
                $population->materialsQuery($onlyShortName)->orderBy('id')->get(),
                $skipCategory,
                $onlyShortName,
            );
            $updated = $result['updated'];
            $skipped = $result['skipped'];
        } else {
            $updated = $rows->count();
            $skipped = $population->materialsQuery($onlyShortName)->count() - $updated;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$updated} material(s); skipped {$skipped} with nothing to change.");

        if ($dryRun && $updated > 0) {
            $this->comment('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}

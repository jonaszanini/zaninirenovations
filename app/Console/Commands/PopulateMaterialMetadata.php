<?php

namespace App\Console\Commands;

use App\Services\MaterialMetadataPopulationService;
use Illuminate\Console\Command;

class PopulateMaterialMetadata extends Command
{
    protected $signature = 'materials:populate-metadata
                            {--dry-run : Preview changes without saving}
                            {--skip-category : Do not update categories}';

    protected $description = 'One-time helper: suggest categories and units from material store names (short names: use Materials → Config → Short names Gemini)';

    public function handle(MaterialMetadataPopulationService $population): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipCategory = (bool) $this->option('skip-category');

        $rows = $population->previewAll($skipCategory, onlyShortName: false, skipShortName: true);

        if ($rows->isNotEmpty()) {
            $this->table(
                ['ID', 'Old short', 'New short', 'Old cat', 'New cat', 'Old unit', 'New unit', 'Name'],
                $rows->all()
            );
        }

        if (! $dryRun) {
            $result = $population->applyToMaterials(
                $population->materialsQuery(onlyShortName: false)->orderBy('id')->get(),
                $skipCategory,
                onlyShortName: false,
                skipShortName: true,
            );
            $updated = $result['updated'];
            $skipped = $result['skipped'];
        } else {
            $updated = $rows->count();
            $skipped = $population->materialsQuery(onlyShortName: false)->count() - $updated;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$updated} material(s); skipped {$skipped} with nothing to change.");

        if ($dryRun && $updated > 0) {
            $this->comment('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\BlogImageStorage;
use Illuminate\Console\Command;

class CleanupBlogOrphans extends Command
{
    protected $signature = 'blog:cleanup-orphans {--hours=168 : Delete pending/temp files older than this many hours}';

    protected $description = 'Delete abandoned pending featured images and temp inline uploads.';

    public function handle(BlogImageStorage $images): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $deleted = $images->deleteOrphansOlderThan($hours);
        $this->info('Deleted '.$deleted.' orphan blog file'.($deleted === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}

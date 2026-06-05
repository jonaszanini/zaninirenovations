<?php

namespace App\Console\Commands;

use App\Models\ProjectPhoto;
use App\Services\ProjectPhotoWebpStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RecompressPortfolioWebp extends Command
{
    protected $signature = 'portfolio:recompress-webp
                            {--dry-run : List files over the byte budget without writing}
                            {--project= : Only process photos for this portfolio project ID}';

    protected $description = 'One-time recompression of existing Latest Projects Photos WebP files to the current size budget';

    public function handle(ProjectPhotoWebpStorage $webpStorage): int
    {
        $maxBytes = max(32_768, (int) config('webp.max_bytes', 102_400));
        $dryRun = (bool) $this->option('dry-run');
        $projectId = $this->option('project');
        $projectId = is_numeric($projectId) ? (int) $projectId : null;

        $query = ProjectPhoto::query()
            ->where('path', 'like', '%.webp')
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        $photos = $query->get();

        if ($photos->isEmpty()) {
            $this->warn('No portfolio WebP photos found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d portfolio WebP photo(s). Target budget: %s KB (%d bytes). Quality %d%%, max edge %dpx.',
            $photos->count(),
            number_format($maxBytes / 1024, 0),
            $maxBytes,
            (int) config('webp.quality', 75),
            (int) config('webp.max_edge_px', 1280)
        ));

        if ($dryRun) {
            $this->comment('Dry run — no files will be modified.');
        }

        $missing = 0;
        $alreadyOk = 0;
        $overBudget = 0;
        $compressed = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        foreach ($photos as $photo) {
            $relative = ltrim(str_replace('\\', '/', (string) $photo->path), '/');
            $absolute = Storage::disk('public')->path($relative);

            if (! is_file($absolute)) {
                $missing++;
                $this->warn("Missing file [photo #{$photo->id}]: {$relative}");

                continue;
            }

            $size = (int) filesize($absolute);

            if ($size <= $maxBytes) {
                $alreadyOk++;

                continue;
            }

            $overBudget++;
            $bytesBefore += $size;

            if ($dryRun) {
                $this->line(sprintf(
                    'Would recompress [photo #%d, project #%d]: %s (%s KB)',
                    $photo->id,
                    $photo->project_id,
                    $relative,
                    number_format($size / 1024, 1)
                ));

                continue;
            }

            $before = $size;
            $webpStorage->recompressWebpAtPath($absolute);
            clearstatcache(true, $absolute);
            $after = (int) filesize($absolute);

            if ($after < $before) {
                $compressed++;
                $bytesAfter += $after;
                $photo->touch();

                $this->line(sprintf(
                    'Compressed [photo #%d]: %s KB → %s KB',
                    $photo->id,
                    number_format($before / 1024, 1),
                    number_format($after / 1024, 1)
                ));
            } else {
                $alreadyOk++;
                $bytesAfter += $after;
                $this->warn(sprintf(
                    'No change [photo #%d]: still %s KB (encoder could not shrink further)',
                    $photo->id,
                    number_format($after / 1024, 1)
                ));
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total WebP photos', $photos->count()],
                ['Already at or under budget', $alreadyOk],
                ['Missing on disk', $missing],
                [$dryRun ? 'Would recompress' : 'Recompressed', $dryRun ? $overBudget : $compressed],
                ['Failed', $failed],
            ]
        );

        if (! $dryRun && $compressed > 0) {
            $saved = max(0, $bytesBefore - $bytesAfter);
            $this->info(sprintf(
                'Saved approximately %s KB across %d file(s).',
                number_format($saved / 1024, 1),
                $compressed
            ));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

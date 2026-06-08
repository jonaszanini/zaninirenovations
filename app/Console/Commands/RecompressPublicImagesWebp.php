<?php

namespace App\Console\Commands;

use App\Services\ProjectPhotoWebpStorage;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RecompressPublicImagesWebp extends Command
{
    protected $signature = 'public-images:recompress-webp
                            {--dry-run : List files over the byte budget without writing}
                            {--all : Re-encode every WebP under public/images, not only files over budget}
                            {--path= : Limit to a subdirectory of public/images (e.g. services)}';

    protected $description = 'Recompress static WebP files in public/images using the internal WebP size budget';

    public function handle(ProjectPhotoWebpStorage $webpStorage): int
    {
        $maxBytes = max(32_768, (int) config('webp.max_bytes', 102_400));
        $dryRun = (bool) $this->option('dry-run');
        $reencodeAll = (bool) $this->option('all');
        $subPath = trim(str_replace('\\', '/', (string) $this->option('path')), '/');

        $root = public_path('images');
        if ($subPath !== '') {
            $root = public_path('images/'.$subPath);
        }

        if (! is_dir($root)) {
            $this->error("Directory not found: {$root}");

            return self::FAILURE;
        }

        $files = $this->collectWebpFiles($root);
        if ($files === []) {
            $this->warn('No WebP files found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d WebP file(s) under public/images%s. Target budget: %s KB (%d bytes). Quality %d%%, max edge %dpx.',
            count($files),
            $subPath !== '' ? '/'.$subPath : '',
            number_format($maxBytes / 1024, 0),
            $maxBytes,
            (int) config('webp.quality', 75),
            (int) config('webp.max_edge_px', 1280)
        ));

        if ($dryRun) {
            $this->comment('Dry run — no files will be modified.');
        }

        $compressed = 0;
        $alreadyOk = 0;
        $overBudget = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        foreach ($files as $absolute) {
            $relative = 'images/'.ltrim(str_replace('\\', '/', substr($absolute, strlen(public_path('images')))), '/');
            $size = (int) filesize($absolute);

            if (! $reencodeAll && $size <= $maxBytes) {
                $alreadyOk++;

                continue;
            }

            $overBudget++;
            $bytesBefore += $size;

            if ($dryRun) {
                $this->line(sprintf(
                    'Would recompress: %s (%s KB)',
                    $relative,
                    number_format($size / 1024, 1)
                ));

                continue;
            }

            try {
                $webpStorage->recompressWebpAtPath($absolute);
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed [{$relative}]: {$e->getMessage()}");

                continue;
            }

            clearstatcache(true, $absolute);
            $after = (int) filesize($absolute);

            if (! is_file($absolute)) {
                $failed++;
                $this->error("Failed [{$relative}]: file missing after recompress");

                continue;
            }

            $bytesAfter += $after;

            if ($after < $size) {
                $compressed++;
                $this->line(sprintf(
                    'Compressed: %s — %s KB → %s KB',
                    $relative,
                    number_format($size / 1024, 1),
                    number_format($after / 1024, 1)
                ));
            } else {
                $alreadyOk++;
                $this->warn(sprintf(
                    'No change: %s — still %s KB',
                    $relative,
                    number_format($after / 1024, 1)
                ));
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total WebP files scanned', count($files)],
                ['Already at or under budget', $alreadyOk],
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

    /**
     * @return list<string> Absolute paths
     */
    private function collectWebpFiles(string $root): array
    {
        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'webp') {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        sort($paths);

        return $paths;
    }
}

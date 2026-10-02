<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Support\GooglePickerItems;
use App\Support\Logging\AppLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PortfolioAiImageCollector
{
    public const MAX_IMAGES = 8;

    public function __construct(
        protected GooglePhotosService $googlePhotos,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $googlePickerRawItems
     * @param  list<string>  $extraPublicPaths  Relative paths on the public disk (already authorized by the caller).
     * @return list<array{bytes: string, mime: string}>
     */
    public function collect(
        User $user,
        array $uploads,
        array $googlePickerRawItems,
        ?Project $project = null,
        array $extraPublicPaths = [],
    ): array {
        $images = [];

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile || count($images) >= self::MAX_IMAGES) {
                continue;
            }

            $this->appendImageFromPath($images, $upload->getRealPath(), $upload->getMimeType());
        }

        foreach (GooglePickerItems::parse($googlePickerRawItems) as $item) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }

            try {
                [$tmp, $mime] = $this->googlePhotos->downloadPickedMedia(
                    $user,
                    $item['baseUrl'],
                    $item['mimeType'],
                    preferFullResolution: false,
                );

                try {
                    $this->appendImageFromPath($images, $tmp, $mime);
                } finally {
                    if (is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            } catch (\Throwable $e) {
                AppLogger::debug('portfolio.ai_image.chunk_failed', [], $e);
            }
        }

        foreach ($extraPublicPaths as $relative) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }
            if (! is_string($relative)) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            if (! Storage::disk('public')->exists($relative)) {
                continue;
            }

            $this->appendImageFromPath($images, Storage::disk('public')->path($relative), null);
        }

        if ($project !== null && count($images) < self::MAX_IMAGES) {
            $project->loadMissing(['photos' => fn ($q) => $q->orderBy('sort_order')]);

            foreach ($project->photos as $photo) {
                if (count($images) >= self::MAX_IMAGES) {
                    break;
                }

                if (! filled($photo->path) || ! Storage::disk('public')->exists($photo->path)) {
                    continue;
                }

                $this->appendImageFromPath($images, Storage::disk('public')->path($photo->path), 'image/webp');
            }
        }

        return $images;
    }

    /**
     * @param  list<array{bytes: string, mime: string}>  $images
     */
    private function appendImageFromPath(array &$images, string|false|null $path, ?string $mime): bool
    {
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return false;
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return false;
        }

        $images[] = [
            'bytes' => $bytes,
            'mime' => $this->normalizeMime($mime, $path),
        ];

        return true;
    }

    private function normalizeMime(?string $mime, string $path): string
    {
        $mime = strtolower(trim((string) $mime));
        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            $mime = is_string($detected) ? strtolower($detected) : '';
        }
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            return 'image/jpeg';
        }

        return $mime;
    }
}

<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogImageStorage
{
    public function __construct(
        protected ProjectPhotoWebpStorage $webpStorage,
        protected ProjectPhotoCropService $cropService,
    ) {}

    public function storeFeatured(UploadedFile $file, int $postId, ?array $crop = null): string
    {
        $relative = $this->webpStorage->store($file, $postId, 'blog-featured', 'featured');

        if ($crop) {
            $this->applyCrop($relative, $crop);
        }

        return $relative;
    }

    public function storeInline(UploadedFile $file, int $postId): string
    {
        return $this->webpStorage->store($file, $postId, 'blog-inline');
    }

    public function storeTempInline(UploadedFile $file): string
    {
        return $this->webpStorage->store($file, 0, 'blog-inline-temp');
    }

    public function applyCrop(string $relativePath, array $crop): void
    {
        $absolute = Storage::disk('public')->path($relativePath);
        $this->cropService->cropWebp(
            $absolute,
            (int) $crop['x'],
            (int) $crop['y'],
            (int) $crop['width'],
            (int) $crop['height']
        );
        $this->webpStorage->recompressWebpAtPath($absolute);
    }

    public function delete(string $relativePath): void
    {
        if (filled($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    public function publicUrl(?string $relativePath): ?string
    {
        if (! filled($relativePath)) {
            return null;
        }

        return Storage::disk('public')->url($relativePath);
    }
}

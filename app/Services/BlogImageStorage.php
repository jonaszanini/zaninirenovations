<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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

    /**
     * Store AI-generated raster bytes as a compressed WebP featured image.
     */
    public function storeFeaturedFromBinary(string $bytes, string $mime, int $postId): string
    {
        if ($bytes === '') {
            throw new RuntimeException('Generated image is empty.');
        }

        $bytes = $this->normalizeAiFeaturedTo16x9($bytes);
        $mime = 'image/jpeg';

        $ext = 'jpg';
        if (str_contains(strtolower($mime), 'png')) {
            $ext = 'png';
        } elseif (str_contains(strtolower($mime), 'webp')) {
            $ext = 'webp';
        }

        $tmpBase = tempnam(sys_get_temp_dir(), 'blog-ai-');
        if ($tmpBase === false) {
            throw new RuntimeException('Could not create a temp file for the generated image.');
        }

        $tmpPath = $tmpBase.'.'.$ext;
        if (!@rename($tmpBase, $tmpPath)) {
            @unlink($tmpBase);
            throw new RuntimeException('Could not prepare temp file for the generated image.');
        }

        try {
            if (file_put_contents($tmpPath, $bytes) === false) {
                throw new RuntimeException('Could not write generated image to disk.');
            }

            $upload = new UploadedFile($tmpPath, 'featured.'.$ext, $mime, null, true);

            return $this->webpStorage->store($upload, $postId, 'blog-featured', 'featured');
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Move a pending featured image (blog-featured/0/…) onto a saved post.
     */
    public function adoptPendingFeatured(string $pendingRelativePath, int $postId): string
    {
        $pendingRelativePath = ltrim(str_replace('\\', '/', $pendingRelativePath), '/');
        if ($postId < 1 || !preg_match('#^blog-featured/0/[\w.-]+\.webp$#', $pendingRelativePath)) {
            throw new RuntimeException('Invalid pending featured image path.');
        }

        if (!Storage::disk('public')->exists($pendingRelativePath)) {
            throw new RuntimeException('Pending featured image no longer exists. Generate again.');
        }

        $absolute = Storage::disk('public')->path($pendingRelativePath);
        $bytes = file_get_contents($absolute);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Could not read pending featured image.');
        }

        Storage::disk('public')->delete($pendingRelativePath);

        return $this->storeFeaturedFromBinary($bytes, 'image/webp', $postId);
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

    /**
     * Gemini often returns a square canvas with 16:9 content letterboxed inside.
     * Center-crop to true 16:9 before WebP conversion (manual uploads use Cropper instead).
     */
    private function normalizeAiFeaturedTo16x9(string $bytes): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $bytes;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 2 || $height < 2) {
            imagedestroy($image);

            return $bytes;
        }

        $targetRatio = 16 / 9;
        $currentRatio = $width / $height;
        if (abs($currentRatio - $targetRatio) < 0.015) {
            imagedestroy($image);

            return $bytes;
        }

        if ($currentRatio > $targetRatio) {
            $cropWidth = (int) max(1, round($height * $targetRatio));
            $cropHeight = $height;
            $cropX = (int) max(0, floor(($width - $cropWidth) / 2));
            $cropY = 0;
        } else {
            $cropWidth = $width;
            $cropHeight = (int) max(1, round($width / $targetRatio));
            $cropX = 0;
            $cropY = (int) max(0, floor(($height - $cropHeight) / 2));
        }

        $cropped = imagecrop($image, [
            'x' => $cropX,
            'y' => $cropY,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);
        imagedestroy($image);

        if ($cropped === false) {
            return $bytes;
        }

        ob_start();
        $ok = imagejpeg($cropped, null, 92);
        imagedestroy($cropped);
        $jpeg = ob_get_clean();

        return ($ok && is_string($jpeg) && $jpeg !== '') ? $jpeg : $bytes;
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

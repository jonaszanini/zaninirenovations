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
        if ($crop !== null) {
            $file = $this->cropUploadedRaster($file, $crop);
        }

        return $this->webpStorage->store($file, $postId, 'blog-featured');
    }

    /**
     * Store AI generated raster bytes as a compressed WebP featured image.
     */
    public function storeFeaturedFromBinary(string $bytes, string $mime, int $postId): string
    {
        return $this->storeGeneratedBinary($bytes, $mime, 'blog-featured', $postId);
    }

    /**
     * Store the full-resolution AI tool image exactly as returned by Gemini.
     */
    public function storeAiToolOriginalFromBinary(string $bytes, string $mime): string
    {
        if ($bytes === '') {
            throw new RuntimeException('Generated image is empty.');
        }

        $ext = $this->extensionForMime($mime);
        $leaf = \Illuminate\Support\Str::uuid()->toString();
        $relativePath = 'ai-tool-images/originals/'.$leaf.'.'.$ext;

        Storage::disk('public')->put($relativePath, $bytes);
        $this->webpStorage->syncPublicMirror($relativePath);

        return $relativePath;
    }

    /**
     * Store a compressed WebP copy for gallery preview and optimized download.
     * Expects bytes already cropped to the requested aspect ratio.
     */
    public function storeAiToolWebpFromBinary(string $bytes, string $mime): string
    {
        return $this->storeGeneratedBinary($bytes, $mime, 'ai-tool-images', 0, normalizeTo16x9: false);
    }

    /** @see GeminiImageCropService */
    public function cropGeminiOutputToAspectRatio(string $bytes, string $aspectRatio): string
    {
        return app(GeminiImageCropService::class)->cropToAspectRatio($bytes, $aspectRatio);
    }

    /**
     * Shared Gemini image pipeline: optional 16:9 crop, convert to WebP, store on public disk.
     */
    private function storeGeneratedBinary(
        string $bytes,
        string $mime,
        string $storageFolder,
        int $folderId,
        bool $normalizeTo16x9 = true,
    ): string {
        if ($bytes === '') {
            throw new RuntimeException('Generated image is empty.');
        }

        if ($normalizeTo16x9) {
            $bytes = $this->normalizeAiFeaturedTo16x9($bytes);
        }
        $mime = 'image/jpeg';

        $ext = $this->extensionForMime($mime);

        $tmpBase = tempnam(sys_get_temp_dir(), 'blog-ai-');
        if ($tmpBase === false) {
            throw new RuntimeException('Could not create a temp file for the generated image.');
        }

        $tmpPath = $tmpBase.'.'.$ext;
        if (! @rename($tmpBase, $tmpPath)) {
            @unlink($tmpBase);
            throw new RuntimeException('Could not prepare temp file for the generated image.');
        }

        try {
            if (file_put_contents($tmpPath, $bytes) === false) {
                throw new RuntimeException('Could not write generated image to disk.');
            }

            $upload = new UploadedFile($tmpPath, 'generated.'.$ext, $mime, null, true);

            return $this->webpStorage->store($upload, $folderId, $storageFolder);
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
        $this->webpStorage->syncPublicMirror($relativePath);
    }

    /** Blog featured images are always requested at 16:9 from Gemini. */
    private function normalizeAiFeaturedTo16x9(string $bytes): string
    {
        return $this->cropGeminiOutputToAspectRatio($bytes, '16:9');
    }

    public function delete(?string $relativePath): void
    {
        if (! filled($relativePath)) {
            return;
        }

        Storage::disk('public')->delete($relativePath);

        $mirror = public_path('storage/'.ltrim(str_replace('\\', '/', $relativePath), '/'));
        if (is_file($mirror)) {
            @unlink($mirror);
        }
    }

    private function extensionForMime(string $mime): string
    {
        $mime = strtolower($mime);

        return match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Crop an uploaded raster (JPEG/PNG/WebP) before WebP conversion.
     * Coordinates match Cropper.js getData(true) — natural image pixels.
     */
    private function cropUploadedRaster(UploadedFile $file, array $crop): UploadedFile
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecrop')) {
            return $file;
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return $file;
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return $file;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $file;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $x = max(0, min($width - 1, (int) $crop['x']));
        $y = max(0, min($height - 1, (int) $crop['y']));
        $cropWidth = max(1, min($width - $x, (int) $crop['width']));
        $cropHeight = max(1, min($height - $y, (int) $crop['height']));

        $cropped = imagecrop($image, [
            'x' => $x,
            'y' => $y,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);
        imagedestroy($image);

        if ($cropped === false) {
            return $file;
        }

        $mime = strtolower((string) $file->getMimeType());
        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };

        $tmpBase = tempnam(sys_get_temp_dir(), 'blog-crop-');
        if ($tmpBase === false) {
            imagedestroy($cropped);

            return $file;
        }

        $tmpPath = $tmpBase.'.'.$ext;
        if (! @rename($tmpBase, $tmpPath)) {
            @unlink($tmpBase);
            imagedestroy($cropped);

            return $file;
        }

        $written = match ($ext) {
            'png' => function_exists('imagepng') && imagepng($cropped, $tmpPath, 6),
            'webp' => function_exists('imagewebp') && imagewebp($cropped, $tmpPath, 92),
            default => imagejpeg($cropped, $tmpPath, 92),
        };
        imagedestroy($cropped);

        if (! $written) {
            @unlink($tmpPath);

            return $file;
        }

        return new UploadedFile($tmpPath, 'featured-cropped.'.$ext, $mime, null, true);
    }

    public function publicUrl(?string $relativePath): ?string
    {
        if (! filled($relativePath)) {
            return null;
        }

        return Storage::disk('public')->url($relativePath);
    }
}

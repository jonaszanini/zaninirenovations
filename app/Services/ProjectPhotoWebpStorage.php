<?php

namespace App\Services;

use App\Support\Logging\AppLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * WebP storage for portfolio photos, blog images, client-project photos, receipts, etc.
 *
 * Downscaling always preserves the source aspect ratio (longest edge capped at max_edge_px).
 * Never use Imagick thumbnailImage with fill, or any center-crop, in this class — only
 * {@see scaleDownMaxEdgeImagick} / {@see scaleDownMaxEdgeGd} / cwebp -resize W 0|0 H.
 */
class ProjectPhotoWebpStorage
{
    /** When true, use config webp.portfolio_* instead of the global defaults. */
    private bool $portfolioProfile = false;

    /** Re-apply WebP budget after crop/rotate (in-place). */
    public function recompressWebpAtPath(string $absolutePath): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        $this->runWithPortfolioProfile($this->pathUsesPortfolioProfile($absolutePath), function () use ($absolutePath): void {
            $bytes = file_get_contents($absolutePath);
            if ($bytes === false || $bytes === '') {
                return;
            }

            try {
                $reencoded = $this->reencodeWebpFromPath($absolutePath);
            } catch (RuntimeException) {
                $reencoded = $bytes;
            }

            $optimized = $this->fitWebpBudget($reencoded);
            if ($optimized !== $bytes) {
                file_put_contents($absolutePath, $optimized);
            }
        });
    }

    /**
     * Recompress an existing WebP on the public disk (relative path under storage/app/public).
     *
     * @return array{changed: bool, before: int, after: int}|null null when the file is missing or not WebP
     */
    public function recompressRelativeWebp(string $relativePath): ?array
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || ! str_ends_with(strtolower($relativePath), '.webp')) {
            return null;
        }

        $absolute = \App\Support\PrivateAppFile::isPrivateRelative($relativePath)
            ? \App\Support\PrivateAppFile::absolutePath($relativePath)
            : Storage::disk('public')->path($relativePath);
        if ($absolute === null || ! is_file($absolute)) {
            return null;
        }

        $before = (int) filesize($absolute);
        $this->recompressWebpAtPath($absolute);
        clearstatcache(true, $absolute);
        $after = (int) filesize($absolute);
        $changed = $after !== $before;

        if ($changed && ! \App\Support\PrivateAppFile::isPrivateRelative($relativePath)) {
            $this->syncPublicStorageFallback($relativePath);
        }

        return [
            'changed' => $changed,
            'before' => $before,
            'after' => $after,
        ];
    }

    public function storeFromPath(string $absolutePath, int $projectId, ?string $mime = null): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Image file is missing.');
        }

        $mime = $mime ? strtolower($mime) : null;
        if (! $mime && function_exists('mime_content_type')) {
            $detected = @mime_content_type($absolutePath);
            $mime = is_string($detected) ? strtolower($detected) : null;
        }

        $originalName = basename($absolutePath);
        $upload = new UploadedFile($absolutePath, $originalName, $mime, null, true);

        return $this->store($upload, $projectId, 'project-photos');
    }

    /**
     * Convert raw image bytes to WebP using the portfolio profile when requested.
     */
    public function convertBinaryToWebp(
        string $bytes,
        string $mime,
        ?string $originalBasename = null,
        bool $portfolioProfile = true,
    ): string {
        return $this->runWithPortfolioProfile($portfolioProfile, function () use ($bytes, $mime, $originalBasename): string {
            if ($bytes === '') {
                throw new RuntimeException('Image is empty.');
            }

            $mime = strtolower(trim($mime));
            if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                $mime = 'image/jpeg';
            }

            $ext = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'jpg',
            };

            $tmp = tempnam(sys_get_temp_dir(), 'pwp_');
            if ($tmp === false) {
                throw new RuntimeException('Could not create temp file.');
            }
            $path = $tmp.'.'.$ext;
            @unlink($tmp);

            try {
                if (file_put_contents($path, $bytes) === false) {
                    throw new RuntimeException('Could not write temp image.');
                }

                if ($mime === 'image/webp') {
                    $webp = $this->reencodeWebpFromPath($path);
                } else {
                    $upload = new UploadedFile($path, $originalBasename ?? ('photo.'.$ext), $mime, null, true);
                    $webp = $this->convertRasterToWebp($upload, $mime, $ext, $path);
                }

                return $this->fitWebpBudget($webp);
            } finally {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        });
    }

    /**
     * Store a project expense receipt: images → WebP (same as {@see store()} with
     * a fixed basename); PDFs are stored without conversion as "{basename}.pdf".
     *
     * @param  string  $preferredBasenameSansExt  e.g. "Receipt-3" → Receipt-3.webp or Receipt-3.pdf
     */
    public function storeExpenseReceipt(UploadedFile $file, int $projectId, string $preferredBasenameSansExt): string
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            $leaf = trim(preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '-', $preferredBasenameSansExt)), '-');
            if ($leaf === '') {
                throw new RuntimeException('Invalid receipt name.');
            }
            $storageFolder = 'expense-receipts';
            $directory = "{$storageFolder}/{$projectId}";
            $relativePath = $directory.'/'.$leaf.'.pdf';
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                throw new RuntimeException('Could not read the uploaded file.');
            }
            $bytes = file_get_contents($realPath);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Could not read the PDF file.');
            }
            if (\App\Support\PrivateAppFile::isPrivateRelative($relativePath)) {
                \App\Support\PrivateAppFile::put($relativePath, $bytes);
            } else {
                Storage::disk('public')->put($relativePath, $bytes);
                $this->syncPublicStorageFallback($relativePath);
            }

            return $relativePath;
        }

        return $this->store($file, $projectId, 'expense-receipts', $preferredBasenameSansExt);
    }

    /**
     * @param  string  $storageFolder  Top-level folder under the public disk (e.g. project-photos, expense-receipts).
     * @param  string|null  $preferredBasenameSansExt  When set (e.g. "Receipt-3"), stored file is "{sanitized}.webp" instead of a UUID.
     */
    public function store(UploadedFile $file, int $projectId, string $storageFolder = 'project-photos', ?string $preferredBasenameSansExt = null): string
    {
        $storageFolder = trim(str_replace(['\\', '..'], '', $storageFolder), '/');

        return $this->runWithPortfolioProfile($storageFolder === 'project-photos', function () use ($file, $projectId, $storageFolder, $preferredBasenameSansExt): string {
            return $this->storeWithCurrentProfile($file, $projectId, $storageFolder, $preferredBasenameSansExt);
        });
    }

    private function storeWithCurrentProfile(
        UploadedFile $file,
        int $projectId,
        string $storageFolder,
        ?string $preferredBasenameSansExt,
    ): string {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $leaf = null;
        if (is_string($preferredBasenameSansExt) && trim($preferredBasenameSansExt) !== '') {
            $leaf = trim(preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '-', $preferredBasenameSansExt)), '-');
        }
        if ($leaf === null || $leaf === '') {
            $leaf = Str::uuid()->toString();
        }
        $basename = $leaf.'.webp';
        $directory = "{$storageFolder}/{$projectId}";
        $relativePath = $directory.'/'.$basename;

        if ($mime === 'image/webp' || $ext === 'webp') {
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                throw new RuntimeException('Could not read the uploaded file.');
            }

            $webp = $this->reencodeWebpFromPath($realPath);
            $webp = $this->fitWebpBudget($webp);
            if (\App\Support\PrivateAppFile::isPrivateRelative($relativePath)) {
                \App\Support\PrivateAppFile::put($relativePath, $webp);
            } else {
                Storage::disk('public')->put($relativePath, $webp);
                $this->syncPublicStorageFallback($relativePath);
            }

            return $relativePath;
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        $webp = $this->convertRasterToWebp($file, $mime, $ext, $path);
        $webp = $this->fitWebpBudget($webp);
        if (\App\Support\PrivateAppFile::isPrivateRelative($relativePath)) {
            \App\Support\PrivateAppFile::put($relativePath, $webp);
        } else {
            Storage::disk('public')->put($relativePath, $webp);
            $this->syncPublicStorageFallback($relativePath);
        }

        return $relativePath;
    }

    /**
     * @return array{quality: int, max_edge_px: int, max_bytes: int}
     */
    public static function portfolioEncodingSettings(): array
    {
        return [
            'quality' => max(1, min(100, (int) config('webp.portfolio_quality', 85))),
            'max_edge_px' => max(320, min(8192, (int) config('webp.portfolio_max_edge_px', 1920))),
            'max_bytes' => max(32_768, (int) config('webp.portfolio_max_bytes', 280_000)),
        ];
    }

    /**
     * Shared hosting fallback when symlink() is disabled:
     * keep a mirrored copy under public/storage so /storage/* URLs resolve.
     */
    public function syncPublicMirror(string $relativePath): void
    {
        $this->syncPublicStorageFallback($relativePath);
    }

    private function syncPublicStorageFallback(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $publicStorageRoot = public_path('storage');

        if (! is_dir($publicStorageRoot)) {
            return;
        }

        $source = Storage::disk('public')->path($relativePath);
        if (! is_file($source)) {
            return;
        }

        $destination = public_path('storage/'.$relativePath);
        $destinationDir = dirname($destination);
        if (! is_dir($destinationDir) && ! @mkdir($destinationDir, 0775, true) && ! is_dir($destinationDir)) {
            return;
        }

        $sourceReal = realpath($source);
        $destinationReal = is_file($destination) ? realpath($destination) : false;
        if ($sourceReal !== false && $destinationReal !== false && $sourceReal === $destinationReal) {
            return;
        }

        @copy($source, $destination);
    }

    private function quality(): int
    {
        if ($this->portfolioProfile) {
            return self::portfolioEncodingSettings()['quality'];
        }

        $q = (int) config('webp.quality', 75);

        return max(1, min(100, $q));
    }

    private function maxEdge(): int
    {
        if ($this->portfolioProfile) {
            return self::portfolioEncodingSettings()['max_edge_px'];
        }

        $m = (int) config('webp.max_edge_px', 1280);

        return max(320, min(8192, $m));
    }

    private function maxBytes(): int
    {
        if ($this->portfolioProfile) {
            return self::portfolioEncodingSettings()['max_bytes'];
        }

        return max(32_768, (int) config('webp.max_bytes', 102_400));
    }

    private function pathUsesPortfolioProfile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_contains($normalized, '/project-photos/')
            || str_ends_with($normalized, '/project-photos');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function runWithPortfolioProfile(bool $portfolioProfile, callable $callback)
    {
        $previous = $this->portfolioProfile;
        $this->portfolioProfile = $portfolioProfile;

        try {
            return $callback();
        } finally {
            $this->portfolioProfile = $previous;
        }
    }

    /**
     * Re-encode WebP until it fits the byte budget (default ~100 KB).
     */
    private function fitWebpBudget(string $webp): string
    {
        $maxBytes = $this->maxBytes();
        if (strlen($webp) <= $maxBytes) {
            return $webp;
        }

        $quality = $this->quality();
        $edge = $this->maxEdge();
        $current = $webp;

        for ($attempt = 0; $attempt < 12 && strlen($current) > $maxBytes; $attempt++) {
            $next = $this->reencodeWebpBlob($current, $quality, $edge);
            if ($next === $current || strlen($next) >= strlen($current)) {
                if ($quality > 45) {
                    $quality -= 5;
                } elseif ($edge > 640) {
                    $edge = (int) max(640, round($edge * 0.85));
                } else {
                    break;
                }

                continue;
            }
            $current = $next;

            if (strlen($current) > $maxBytes) {
                if ($quality > 45) {
                    $quality -= 5;
                } elseif ($edge > 640) {
                    $edge = (int) max(640, round($edge * 0.85));
                }
            }
        }

        return $current;
    }

    private function reencodeWebpBlob(string $webp, int $quality, int $maxEdge): string
    {
        if ($this->imagickSupportsWebp()) {
            try {
                $imagick = new \Imagick;
                $imagick->readImageBlob($webp);
                $this->scaleDownMaxEdgeImagick($imagick, $maxEdge);
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality(max(1, min(100, $quality)));
                $blob = $imagick->getImageBlob();
                $imagick->destroy();
                if (is_string($blob) && $blob !== '') {
                    return $blob;
                }
            } catch (Throwable $e) {
                AppLogger::debug('photo.webp.imagick_fallback_to_gd', [], $e);
            }
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
            $im = @imagecreatefromstring($webp);
            if ($im !== false) {
                imagesavealpha($im, true);
                $im = $this->scaleDownMaxEdgeGd($im, $maxEdge);
                ob_start();
                $ok = imagewebp($im, null, max(1, min(100, $quality)));
                imagedestroy($im);
                $out = ob_get_clean();
                if ($ok && is_string($out) && $out !== '') {
                    return $out;
                }
            }
        }

        return $webp;
    }

    private function cwebpMethod(): int
    {
        $m = (int) config('webp.cwebp_method', 6);

        return max(0, min(6, $m));
    }

    private function needsDownscale(string $absolutePath): bool
    {
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return false;
        }
        [$w, $h] = $info;
        $max = $this->maxEdge();

        return $w > $max || $h > $max;
    }

    /**
     * Re-encode an existing WebP (resize if needed) for smaller files.
     */
    private function reencodeWebpFromPath(string $absolutePath): string
    {
        if ($this->imagickSupportsWebp()) {
            return $this->encodeToWebpViaImagick($absolutePath);
        }

        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $im = @imagecreatefromwebp($absolutePath);
            if ($im !== false) {
                imagesavealpha($im, true);
                $im = $this->scaleDownMaxEdgeGd($im);
                ob_start();
                $ok = imagewebp($im, null, $this->quality());
                imagedestroy($im);
                $webp = ob_get_clean();
                if ($ok && $webp !== false && $webp !== '') {
                    return $webp;
                }
            }
        }

        $cwebp = $this->resolveCwebpBinary();
        if ($cwebp !== null) {
            return $this->encodeToWebpViaCwebp($absolutePath, $cwebp);
        }

        throw new RuntimeException('Could not resize or re-encode large WebP. Add cwebp in bin/ or enable Imagick / GD WebP.');
    }

    private function convertRasterToWebp(UploadedFile $file, string $mime, string $ext, string $absolutePath): string
    {
        if (function_exists('imagewebp')) {
            $image = $this->createImageFromUpload($file, $mime, $ext);
            if ($image !== false) {
                $image = $this->scaleDownMaxEdgeGd($image);
                ob_start();
                $ok = imagewebp($image, null, $this->quality());
                imagedestroy($image);
                $webp = ob_get_clean();
                if ($ok && $webp !== false && $webp !== '') {
                    return $webp;
                }
            }
        }

        if ($this->imagickSupportsWebp()) {
            return $this->encodeToWebpViaImagick($absolutePath);
        }

        $cwebp = $this->resolveCwebpBinary();
        if ($cwebp !== null) {
            return $this->encodeToWebpViaCwebp($absolutePath, $cwebp);
        }

        throw new RuntimeException(
            'Could not convert images to WebP. Options: (1) Enable GD with WebP in php.ini, (2) Install Imagick with WebP, '.
            '(3) Install Google\'s cwebp tool and set CWEBP_PATH in .env to the full path of cwebp.exe, or place cwebp.exe in the project bin/ folder. '.
            'See bin/WEBP-CONVERTER.txt. You can still upload .webp files directly without conversion.'
        );
    }

    /**
     * @return array{0: int, 1: int}|null [newWidth, newHeight], or null when already within max edge
     */
    private function scaledDimensionsWithinMaxEdge(int $width, int $height, int $maxEdge): ?array
    {
        if ($width <= $maxEdge && $height <= $maxEdge) {
            return null;
        }

        if ($width >= $height) {
            return [$maxEdge, (int) max(1, round($height * ($maxEdge / $width)))];
        }

        return [(int) max(1, round($width * ($maxEdge / $height))), $maxEdge];
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function scaleDownMaxEdgeGd($image, ?int $maxEdge = null)
    {
        $maxEdge = $maxEdge ?? $this->maxEdge();
        $w = imagesx($image);
        $h = imagesy($image);
        $scaledDims = $this->scaledDimensionsWithinMaxEdge($w, $h, $maxEdge);
        if ($scaledDims === null) {
            return $image;
        }

        [$newW, $newH] = $scaledDims;
        $scaled = imagescale($image, $newW, $newH, IMG_BICUBIC);
        if ($scaled === false) {
            return $image;
        }
        imagedestroy($image);

        return $scaled;
    }

    private function resolveCwebpBinary(): ?string
    {
        $configured = config('webp.cwebp_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            base_path('bin/cwebp.exe'),
            base_path('bin/cwebp'),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $finder = new ExecutableFinder;
        $found = $finder->find('cwebp', null, [dirname(PHP_BINARY)]);

        return $found;
    }

    private function encodeToWebpViaCwebp(string $absolutePath, string $cwebpBinary): string
    {
        $base = tempnam(sys_get_temp_dir(), 'zrw');
        if ($base === false) {
            throw new RuntimeException('Could not create a temp file.');
        }
        @unlink($base);
        $out = $base.'.webp';

        try {
            $args = [
                $cwebpBinary,
                '-q', (string) $this->quality(),
                '-m', (string) $this->cwebpMethod(),
            ];

            $info = @getimagesize($absolutePath);
            if ($info !== false) {
                [$w, $h] = $info;
                $scaledDims = $this->scaledDimensionsWithinMaxEdge($w, $h, $this->maxEdge());
                if ($scaledDims !== null) {
                    [$newW, $newH] = $scaledDims;
                    array_push($args, '-resize', (string) $newW, (string) $newH);
                }
            }

            $args[] = $absolutePath;
            $args[] = '-o';
            $args[] = $out;

            $process = new Process($args);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($out) || filesize($out) === 0) {
                $detail = trim($process->getErrorOutput().' '.$process->getOutput());
                throw new RuntimeException($detail !== '' ? $detail : 'cwebp exited with an error.');
            }

            $blob = file_get_contents($out);
            if ($blob === false || $blob === '') {
                throw new RuntimeException('Could not read WebP produced by cwebp.');
            }

            return $blob;
        } finally {
            if (is_file($out)) {
                @unlink($out);
            }
        }
    }

    private function scaleDownMaxEdgeImagick(\Imagick $imagick, int $maxEdge): void
    {
        $scaledDims = $this->scaledDimensionsWithinMaxEdge(
            $imagick->getImageWidth(),
            $imagick->getImageHeight(),
            $maxEdge,
        );
        if ($scaledDims === null) {
            return;
        }

        [$newW, $newH] = $scaledDims;
        $imagick->resizeImage($newW, $newH, \Imagick::FILTER_LANCZOS, 1);
    }

    private function imagickSupportsWebp(): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return in_array('WEBP', \Imagick::queryFormats(), true);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function encodeToWebpViaImagick(string $absolutePath): string
    {
        try {
            $imagick = new \Imagick($absolutePath);
        } catch (Throwable $e) {
            throw new RuntimeException('Imagick could not read the image.', 0, $e);
        }

        try {
            $this->scaleDownMaxEdgeImagick($imagick, $this->maxEdge());
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($this->quality());
            $blob = $imagick->getImageBlob();
            if ($blob === false || $blob === '') {
                throw new RuntimeException('Imagick produced empty WebP output.');
            }

            return $blob;
        } finally {
            $imagick->destroy();
        }
    }

    /**
     * @return \GdImage|resource|false
     */
    private function createImageFromUpload(UploadedFile $file, string $mime, string $ext)
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return false;
        }

        $isJpeg = in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)
            || in_array($ext, ['jpg', 'jpeg'], true);
        $isPng = $mime === 'image/png' || $ext === 'png';

        if ($isJpeg) {
            return @imagecreatefromjpeg($path);
        }

        if ($isPng) {
            $im = @imagecreatefrompng($path);
            if ($im !== false) {
                imagealphablending($im, false);
                imagesavealpha($im, true);
            }

            return $im;
        }

        return false;
    }
}

<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ProjectPhotoWebpStorage
{
    /**
     * Store a photo that already exists on local disk (e.g. downloaded from Google Photos).
     */
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

        return $this->store($upload, $projectId);
    }

    public function store(UploadedFile $file, int $projectId): string
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $basename = Str::uuid()->toString().'.webp';
        $directory = "project-photos/{$projectId}";
        $relativePath = $directory.'/'.$basename;

        if ($mime === 'image/webp' || $ext === 'webp') {
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                throw new RuntimeException('Could not read the uploaded file.');
            }

            if ($this->needsDownscale($realPath)) {
                $webp = $this->reencodeWebpFromPath($realPath);
                Storage::disk('public')->put($relativePath, $webp);
                $this->syncPublicStorageFallback($relativePath);
            } else {
                $bytes = file_get_contents($realPath);
                if ($bytes === false || $bytes === '') {
                    throw new RuntimeException('Could not read the uploaded WebP file.');
                }
                Storage::disk('public')->put($relativePath, $bytes);
                $this->syncPublicStorageFallback($relativePath);
            }

            return $relativePath;
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        $webp = $this->convertRasterToWebp($file, $mime, $ext, $path);
        Storage::disk('public')->put($relativePath, $webp);
        $this->syncPublicStorageFallback($relativePath);

        return $relativePath;
    }

    /**
     * Shared hosting fallback when symlink() is disabled:
     * keep a mirrored copy under public/storage so /storage/* URLs resolve.
     */
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
        $q = (int) config('webp.quality', 78);

        return max(1, min(100, $q));
    }

    private function maxEdge(): int
    {
        $m = (int) config('webp.max_edge_px', 1920);

        return max(320, min(8192, $m));
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
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function scaleDownMaxEdgeGd($image)
    {
        $maxEdge = $this->maxEdge();
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w <= $maxEdge && $h <= $maxEdge) {
            return $image;
        }

        if ($w >= $h) {
            $newW = $maxEdge;
            $newH = (int) max(1, round($h * ($maxEdge / $w)));
        } else {
            $newH = $maxEdge;
            $newW = (int) max(1, round($w * ($maxEdge / $h)));
        }

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
                $max = $this->maxEdge();
                if ($w > $max || $h > $max) {
                    if ($w >= $h) {
                        array_push($args, '-resize', (string) $max, '0');
                    } else {
                        array_push($args, '-resize', '0', (string) $max);
                    }
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
            $max = $this->maxEdge();
            $w = $imagick->getImageWidth();
            $h = $imagick->getImageHeight();
            if ($w > $max || $h > $max) {
                $imagick->thumbnailImage($max, $max, true, true);
            }
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

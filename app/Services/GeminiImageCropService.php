<?php

namespace App\Services;

use App\Support\Logging\AppLogger;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Remove Gemini letterboxing and crop to the requested aspect ratio.
 *
 * Gemini often returns a square canvas with the scene padded top/bottom or
 * side-to-side. PHP GD is not always enabled on shared hosting, so this tries
 * ImageMagick CLI first, then Imagick, then GD.
 */
class GeminiImageCropService
{
    public function cropToAspectRatio(string $bytes, string $aspectRatio): string
    {
        if ($bytes === '' || ! preg_match('/^(\d+):(\d+)$/', trim($aspectRatio))) {
            return $bytes;
        }

        foreach ([
            fn () => $this->cropViaMagickCli($bytes, $aspectRatio),
            fn () => $this->cropViaFfmpeg($bytes, $aspectRatio),
            fn () => $this->cropViaImagick($bytes, $aspectRatio),
            fn () => $this->cropViaGd($bytes, $aspectRatio),
        ] as $attempt) {
            $cropped = $attempt();
            if (is_string($cropped) && $cropped !== '' && $cropped !== $bytes) {
                return $cropped;
            }
        }

        return $bytes;
    }

    private function cropViaMagickCli(string $bytes, string $aspectRatio): ?string
    {
        $magick = $this->resolveMagickBinary();
        if ($magick === null) {
            return null;
        }

        $tmpIn = $this->writeTempImage($bytes, 'gemini-crop-in');
        $tmpOut = $this->tempPath('gemini-crop-out', '.jpg');

        try {
            $process = new Process([
                $magick,
                $tmpIn,
                '-auto-orient',
                '-fuzz', '14%',
                '-trim',
                '+repage',
                '-gravity', 'Center',
                '-crop', $aspectRatio,
                '+repage',
                '-quality', '92',
                $tmpOut,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($tmpOut)) {
                return null;
            }

            $out = file_get_contents($tmpOut);

            return ($out !== false && $out !== '') ? $out : null;
        } catch (Throwable $e) {
            AppLogger::debug('image.crop.imagick_cli_failed', ['aspect_ratio' => $aspectRatio], $e);

            return null;
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }

    private function cropViaFfmpeg(string $bytes, string $aspectRatio): ?string
    {
        if (! preg_match('/^(\d+):(\d+)$/', trim($aspectRatio), $matches)) {
            return null;
        }

        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            return null;
        }

        $ratioW = max(1, (int) $matches[1]);
        $ratioH = max(1, (int) $matches[2]);
        $targetRatio = $ratioW / $ratioH;

        $filter = $targetRatio >= 1
            ? sprintf('crop=iw:iw*%d/%d:0:(ih-iw*%d/%d)/2', $ratioH, $ratioW, $ratioH, $ratioW)
            : sprintf('crop=ih*%d/%d:ih:(iw-ih*%d/%d)/2:0', $ratioW, $ratioH, $ratioW, $ratioH);

        $tmpIn = $this->writeTempImage($bytes, 'gemini-ff-in');
        $tmpOut = $this->tempPath('gemini-ff-out', '.jpg');

        try {
            $process = new Process([
                $ffmpeg,
                '-y',
                '-i', $tmpIn,
                '-vf', $filter,
                '-q:v', '2',
                $tmpOut,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($tmpOut)) {
                return null;
            }

            $out = file_get_contents($tmpOut);

            return ($out !== false && $out !== '') ? $out : null;
        } catch (Throwable $e) {
            AppLogger::debug('image.crop.ffmpeg_failed', ['aspect_ratio' => $aspectRatio], $e);

            return null;
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }

    private function cropViaImagick(string $bytes, string $aspectRatio): ?string
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return null;
        }

        if (! preg_match('/^(\d+):(\d+)$/', trim($aspectRatio), $matches)) {
            return null;
        }

        $ratioW = max(1, (int) $matches[1]);
        $ratioH = max(1, (int) $matches[2]);

        try {
            $image = new \Imagick;
            $image->readImageBlob($bytes);
            $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            $image->setImageFuzz(0.12 * \Imagick::getQuantum());
            $image->trimImage();

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            if ($width < 2 || $height < 2) {
                $image->destroy();

                return null;
            }

            $targetRatio = $ratioW / $ratioH;
            $currentRatio = $width / $height;

            if (abs($currentRatio - $targetRatio) >= 0.015) {
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

                $image->cropImage($cropWidth, $cropHeight, $cropX, $cropY);
            }

            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(92);
            $out = $image->getImageBlob();
            $image->destroy();

            return ($out !== false && $out !== '') ? $out : null;
        } catch (Throwable $e) {
            AppLogger::debug('image.crop.imagick_ext_failed', ['aspect_ratio' => $aspectRatio], $e);

            return null;
        }
    }

    private function cropViaGd(string $bytes, string $aspectRatio): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        if (! preg_match('/^(\d+):(\d+)$/', trim($aspectRatio), $matches)) {
            return null;
        }

        $ratioW = max(1, (int) $matches[1]);
        $ratioH = max(1, (int) $matches[2]);
        $targetRatio = $ratioW / $ratioH;

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 2 || $height < 2) {
            imagedestroy($image);

            return null;
        }

        $currentRatio = $width / $height;
        if (abs($currentRatio - $targetRatio) < 0.015) {
            imagedestroy($image);

            return null;
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
            return null;
        }

        ob_start();
        $ok = imagejpeg($cropped, null, 92);
        imagedestroy($cropped);
        $jpeg = ob_get_clean();

        return ($ok && is_string($jpeg) && $jpeg !== '') ? $jpeg : null;
    }

    public function matchesAspectRatio(string $bytes, string $aspectRatio, float $tolerance = 0.04): bool
    {
        $actual = $this->readAspectRatio($bytes);
        if ($actual === null || ! preg_match('/^(\d+):(\d+)$/', trim($aspectRatio), $matches)) {
            return false;
        }

        $target = ((int) $matches[1]) / max(1, (int) $matches[2]);

        return abs($actual - $target) <= $tolerance;
    }

    public function readAspectRatio(string $bytes): ?float
    {
        if (! function_exists('getimagesizefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }

        return $info[0] / $info[1];
    }

    private function resolveFfmpegBinary(): ?string
    {
        $configured = trim((string) config('webp.ffmpeg_binary', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $finder = new ExecutableFinder;

        return $finder->find('ffmpeg') ?: $finder->find('ffmpeg.exe');
    }

    private function resolveMagickBinary(): ?string
    {
        $configured = trim((string) config('image_processing.magick_binary', ''));
        if ($configured !== '' && $this->isImageMagickBinary($configured)) {
            return $configured;
        }

        $finder = new ExecutableFinder;
        foreach (['magick', 'magick.exe', 'convert', 'convert.exe'] as $name) {
            $path = $finder->find($name);
            if ($this->isImageMagickBinary($path)) {
                return $path;
            }
        }

        foreach (glob('C:/Program Files/ImageMagick*/magick.exe') ?: [] as $path) {
            if ($this->isImageMagickBinary($path)) {
                return $path;
            }
        }

        return null;
    }

    private function isImageMagickBinary(?string $path): bool
    {
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return false;
        }

        // Windows ships convert.exe in System32 for disk volumes — not ImageMagick.
        if (preg_match('#\\System32\\\\convert\.exe$#i', $path)) {
            return false;
        }

        try {
            $process = new Process([$path, '-version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful()
                && str_contains(strtolower($process->getOutput()), 'imagemagick');
        } catch (Throwable $e) {
            AppLogger::debug('image.crop.imagemagick_probe_failed', [], $e);

            return false;
        }
    }

    private function writeTempImage(string $bytes, string $prefix): string
    {
        $mime = $this->detectMime($bytes);
        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };

        $path = $this->tempPath($prefix, '.'.$ext);
        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Could not write temp image for cropping.');
        }

        return $path;
    }

    private function detectMime(string $bytes): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    return strtolower($detected);
                }
            }
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')) {
            return 'image/webp';
        }

        return 'image/jpeg';
    }

    private function tempPath(string $prefix, string $suffix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new RuntimeException('Could not create temp file.');
        }

        $path = $base.$suffix;
        if (! @rename($base, $path)) {
            @unlink($base);
            throw new RuntimeException('Could not prepare temp file.');
        }

        return $path;
    }
}

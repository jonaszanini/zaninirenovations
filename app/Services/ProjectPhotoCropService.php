<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ProjectPhotoCropService
{
    /**
     * Crop a WebP file on disk to the given rectangle (pixel coordinates, origin top-left).
     */
    public function cropWebp(string $absolutePath, int $x, int $y, int $width, int $height): void
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Image file not found.');
        }

        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Invalid crop dimensions.');
        }

        if ($this->imagickAvailable()) {
            $this->cropViaImagick($absolutePath, $x, $y, $width, $height);

            return;
        }

        if (function_exists('imagecreatefromwebp') && function_exists('imagecrop') && function_exists('imagewebp')) {
            $this->cropViaGd($absolutePath, $x, $y, $width, $height);

            return;
        }

        if (
            $this->resolveDwebpBinary() !== null
            && $this->resolveCwebpBinary() !== null
            && function_exists('imagecreatefrompng')
            && function_exists('imagecrop')
        ) {
            $this->cropViaDwebpGdCwebp($absolutePath, $x, $y, $width, $height);

            return;
        }

        if ($this->resolveFfmpegBinary() !== null) {
            $this->cropViaFfmpeg($absolutePath, $x, $y, $width, $height);

            return;
        }

        throw new RuntimeException(
            'Cannot crop WebP on this server. Do one of the following: '.
            '(1) Copy dwebp.exe from the same Google libwebp zip as cwebp into your project bin/ folder next to cwebp.exe, or set DWEBP_PATH in .env. '.
            '(2) Install FFmpeg with WebP support (libwebp) and add ffmpeg to PATH or set FFMPEG_PATH in .env. '.
            '(3) Enable PHP Imagick, or PHP GD with WebP read/write plus imagecrop.'
        );
    }

    private function webpQuality(): int
    {
        $q = (int) config('webp.quality', 75);

        return max(1, min(100, $q));
    }

    private function cwebpMethod(): int
    {
        $m = (int) config('webp.cwebp_method', 6);

        return max(0, min(6, $m));
    }

    private function imagickAvailable(): bool
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

    private function cropViaImagick(string $absolutePath, int $x, int $y, int $width, int $height): void
    {
        try {
            $im = new \Imagick($absolutePath);
        } catch (Throwable $e) {
            throw new RuntimeException('Imagick could not open the WebP file.', 0, $e);
        }

        try {
            $im->cropImage($width, $height, $x, $y);
            $im->setImagePage(0, 0, 0, 0);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($this->webpQuality());
            $ok = $im->writeImage($absolutePath);
            if (! $ok) {
                throw new RuntimeException('Imagick could not write the cropped WebP.');
            }
        } finally {
            $im->destroy();
        }
    }

    private function cropViaGd(string $absolutePath, int $x, int $y, int $width, int $height): void
    {
        $src = @imagecreatefromwebp($absolutePath);
        if ($src === false) {
            throw new RuntimeException('GD could not read the WebP file.');
        }

        imagesavealpha($src, true);
        $cropped = imagecrop($src, [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ]);
        imagedestroy($src);

        if ($cropped === false) {
            throw new RuntimeException('GD could not crop the image.');
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $ok = imagewebp($cropped, $absolutePath, $this->webpQuality());
        imagedestroy($cropped);

        if (! $ok) {
            throw new RuntimeException('GD could not write the cropped WebP.');
        }
    }

    private function cropViaDwebpGdCwebp(string $absolutePath, int $x, int $y, int $width, int $height): void
    {
        $dwebp = $this->resolveDwebpBinary();
        $cwebp = $this->resolveCwebpBinary();
        if ($dwebp === null || $cwebp === null) {
            throw new RuntimeException('dwebp/cwebp not available.');
        }

        $base = tempnam(sys_get_temp_dir(), 'zrc');
        if ($base === false) {
            throw new RuntimeException('Could not create temp file.');
        }
        $pngPath = $base.'.png';
        @unlink($base);

        try {
            $p1 = new Process([$dwebp, $absolutePath, '-o', $pngPath]);
            $p1->setTimeout(120);
            $p1->run();

            if (! $p1->isSuccessful() || ! is_file($pngPath) || filesize($pngPath) === 0) {
                $p1b = new Process([$dwebp, '-o', $pngPath, $absolutePath]);
                $p1b->setTimeout(120);
                $p1b->run();
                if (! $p1b->isSuccessful() || ! is_file($pngPath) || filesize($pngPath) === 0) {
                    throw new RuntimeException(trim($p1->getErrorOutput().' '.$p1b->getErrorOutput()) ?: 'dwebp failed.');
                }
            }

            $src = @imagecreatefrompng($pngPath);
            if ($src === false) {
                throw new RuntimeException('Could not load PNG from dwebp output.');
            }
            imagesavealpha($src, true);
            $cropped = imagecrop($src, [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
            ]);
            imagedestroy($src);

            if ($cropped === false) {
                throw new RuntimeException('Could not crop PNG.');
            }

            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            if (! imagepng($cropped, $pngPath)) {
                imagedestroy($cropped);
                throw new RuntimeException('Could not save cropped PNG.');
            }
            imagedestroy($cropped);

            $p2 = new Process([
                $cwebp,
                '-q', (string) $this->webpQuality(),
                '-m', (string) $this->cwebpMethod(),
                $pngPath,
                '-o', $absolutePath,
            ]);
            $p2->setTimeout(120);
            $p2->run();
            if (! $p2->isSuccessful() || ! is_file($absolutePath) || filesize($absolutePath) === 0) {
                throw new RuntimeException(trim($p2->getErrorOutput().' '.$p2->getOutput()) ?: 'cwebp failed after crop.');
            }
        } finally {
            if (is_file($pngPath)) {
                @unlink($pngPath);
            }
        }
    }

    private function cropViaFfmpeg(string $absolutePath, int $x, int $y, int $width, int $height): void
    {
        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            throw new RuntimeException('ffmpeg not found.');
        }

        $base = tempnam(sys_get_temp_dir(), 'zrcf');
        if ($base === false) {
            throw new RuntimeException('Could not create temp file.');
        }
        @unlink($base);
        $out = $base.'.webp';

        try {
            $process = new Process([
                $ffmpeg,
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-i', $absolutePath,
                '-vf', 'crop='.$width.':'.$height.':'.$x.':'.$y,
                '-c:v', 'libwebp',
                '-quality', (string) $this->webpQuality(),
                $out,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($out) || filesize($out) === 0) {
                $detail = trim($process->getErrorOutput().' '.$process->getOutput());
                throw new RuntimeException(
                    $detail !== '' ? $detail : 'ffmpeg could not crop WebP (install a build with libwebp, or use dwebp.exe in bin/).'
                );
            }

            $bytes = file_get_contents($out);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Could not read ffmpeg crop output.');
            }

            if (file_put_contents($absolutePath, $bytes) === false) {
                throw new RuntimeException('Could not write cropped WebP to storage.');
            }
        } finally {
            if (is_file($out)) {
                @unlink($out);
            }
        }
    }

    private function resolveCwebpBinary(): ?string
    {
        $configured = config('webp.cwebp_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ($this->globBinExecutables('cwebp') as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach ([base_path('bin/cwebp.exe'), base_path('bin/cwebp')] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $finder = new ExecutableFinder;

        return $finder->find('cwebp', null, [dirname(PHP_BINARY), base_path('bin')]);
    }

    private function resolveDwebpBinary(): ?string
    {
        $configured = config('webp.dwebp_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ($this->globBinExecutables('dwebp') as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $cwebp = $this->resolveCwebpBinary();
        if (is_string($cwebp) && $cwebp !== '') {
            $dir = dirname($cwebp);
            foreach (['dwebp.exe', 'dwebp'] as $name) {
                $p = $dir.DIRECTORY_SEPARATOR.$name;
                if (is_file($p)) {
                    return $p;
                }
            }
        }

        foreach ([base_path('bin/dwebp.exe'), base_path('bin/dwebp')] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $finder = new ExecutableFinder;

        return $finder->find('dwebp', null, [dirname(PHP_BINARY), base_path('bin')]);
    }

    private function resolveFfmpegBinary(): ?string
    {
        $configured = config('webp.ffmpeg_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ($this->globBinExecutables('ffmpeg') as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach ([base_path('bin/ffmpeg.exe'), base_path('bin/ffmpeg')] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $finder = new ExecutableFinder;

        return $finder->find('ffmpeg', null, [dirname(PHP_BINARY), base_path('bin')]);
    }

    /**
     * @return list<string>
     */
    private function globBinExecutables(string $prefix): array
    {
        $bin = base_path('bin');
        if (! is_dir($bin)) {
            return [];
        }

        $out = [];
        foreach (glob($bin.DIRECTORY_SEPARATOR.$prefix.'*.exe') ?: [] as $p) {
            $out[] = $p;
        }
        foreach (glob($bin.DIRECTORY_SEPARATOR.$prefix) ?: [] as $p) {
            if (is_file($p)) {
                $out[] = $p;
            }
        }

        return $out;
    }
}

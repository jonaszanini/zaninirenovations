<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ProjectPhotoRotationService
{
    /**
     * Rotate a WebP file on disk 90° clockwise or counter-clockwise (re-encoded using config webp.quality).
     *
     * @param  string  $absolutePath  Full path on the public disk
     * @param  string  $direction  "cw" or "ccw"
     */
    public function rotateWebp(string $absolutePath, string $direction): void
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Image file not found.');
        }

        if ($direction !== 'cw' && $direction !== 'ccw') {
            throw new RuntimeException('Invalid rotation direction.');
        }

        $this->rotateWithFirstWorkingStrategy($absolutePath, $direction);
    }

    private function webpQuality(): int
    {
        $q = (int) config('webp.quality', 78);

        return max(1, min(100, $q));
    }

    private function rotateWithFirstWorkingStrategy(string $absolutePath, string $direction): void
    {
        $degreesImagick = $direction === 'cw' ? -90.0 : 90.0;
        $degreesGd = $direction === 'cw' ? -90 : 90;

        if ($this->imagickAvailable()) {
            $this->rotateViaImagick($absolutePath, $degreesImagick);

            return;
        }

        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp') && function_exists('imagerotate')) {
            $this->rotateViaGdWebp($absolutePath, $degreesGd);

            return;
        }

        if ($this->resolveFfmpegBinary() !== null) {
            $this->rotateViaFfmpeg($absolutePath, $direction);

            return;
        }

        if ($this->resolveDwebpBinary() !== null && $this->resolveCwebpBinary() !== null) {
            $this->rotateViaDwebpGdCwebp($absolutePath, $degreesGd);

            return;
        }

        throw new RuntimeException(
            'Cannot rotate WebP on this server. Do one of the following: '.
            '(1) Copy dwebp.exe from the same Google libwebp zip as cwebp into your project bin/ folder next to cwebp.exe, or set DWEBP_PATH in .env. '.
            '(2) Install FFmpeg with WebP support (libwebp) and add ffmpeg.exe to PATH or set FFMPEG_PATH in .env. '.
            '(3) Enable PHP Imagick, or PHP GD with WebP read/write plus imagerotate.'
        );
    }

    private function imagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(\Imagick::class);
    }

    private function rotateViaImagick(string $absolutePath, float $degrees): void
    {
        try {
            $im = new \Imagick($absolutePath);
        } catch (Throwable $e) {
            throw new RuntimeException('Imagick could not open the WebP file.', 0, $e);
        }

        try {
            $im->setImageBackgroundColor(new \ImagickPixel('transparent'));
            $im->rotateImage(new \ImagickPixel('transparent'), $degrees);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($this->webpQuality());
            $ok = $im->writeImage($absolutePath);
            if (! $ok) {
                throw new RuntimeException('Imagick could not write the rotated WebP.');
            }
        } finally {
            $im->destroy();
        }
    }

    private function rotateViaGdWebp(string $absolutePath, int $degreesGd): void
    {
        $src = @imagecreatefromwebp($absolutePath);
        if ($src === false) {
            throw new RuntimeException('GD could not read the WebP file.');
        }

        imagesavealpha($src, true);
        $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
        $rotated = imagerotate($src, $degreesGd, $transparent);
        imagedestroy($src);

        if ($rotated === false) {
            throw new RuntimeException('GD could not rotate the image.');
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        $ok = imagewebp($rotated, $absolutePath, $this->webpQuality());
        imagedestroy($rotated);

        if (! $ok) {
            throw new RuntimeException('GD could not write the rotated WebP.');
        }
    }

    /**
     * FFmpeg transpose: 1 = 90° clockwise, 2 = 90° counter-clockwise (no extra flip vs. our buttons).
     */
    private function rotateViaFfmpeg(string $absolutePath, string $direction): void
    {
        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            throw new RuntimeException('ffmpeg not found.');
        }

        $transpose = $direction === 'cw' ? '1' : '2';

        $base = tempnam(sys_get_temp_dir(), 'zrf');
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
                '-vf', 'transpose='.$transpose,
                '-c:v', 'libwebp',
                '-quality', (string) $this->webpQuality(),
                $out,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($out) || filesize($out) === 0) {
                $detail = trim($process->getErrorOutput().' '.$process->getOutput());
                throw new RuntimeException(
                    $detail !== '' ? $detail : 'ffmpeg could not produce WebP (install a build with libwebp, or use dwebp.exe in bin/).'
                );
            }

            $bytes = file_get_contents($out);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Could not read ffmpeg output.');
            }

            if (file_put_contents($absolutePath, $bytes) === false) {
                throw new RuntimeException('Could not write rotated WebP to storage.');
            }
        } finally {
            if (is_file($out)) {
                @unlink($out);
            }
        }
    }

    private function rotateViaDwebpGdCwebp(string $absolutePath, int $degreesGd): void
    {
        $dwebp = $this->resolveDwebpBinary();
        $cwebp = $this->resolveCwebpBinary();
        if ($dwebp === null || $cwebp === null) {
            throw new RuntimeException('dwebp/cwebp not available.');
        }

        $base = tempnam(sys_get_temp_dir(), 'zrw');
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
            $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
            $rotated = imagerotate($src, $degreesGd, $transparent);
            imagedestroy($src);
            if ($rotated === false) {
                throw new RuntimeException('Could not rotate PNG.');
            }
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
            if (! imagepng($rotated, $pngPath)) {
                imagedestroy($rotated);
                throw new RuntimeException('Could not save rotated PNG.');
            }
            imagedestroy($rotated);

            $method = max(0, min(6, (int) config('webp.cwebp_method', 6)));
            $p2 = new Process([$cwebp, '-q', (string) $this->webpQuality(), '-m', (string) $method, $pngPath, '-o', $absolutePath]);
            $p2->setTimeout(120);
            $p2->run();
            if (! $p2->isSuccessful() || ! is_file($absolutePath) || filesize($absolutePath) === 0) {
                throw new RuntimeException(trim($p2->getErrorOutput().' '.$p2->getOutput()) ?: 'cwebp failed after rotate.');
            }
        } finally {
            if (is_file($pngPath)) {
                @unlink($pngPath);
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
}

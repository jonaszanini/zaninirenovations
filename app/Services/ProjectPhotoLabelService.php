<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Burn a "Before" or "During" watermark into portfolio WebP photos (large centered text).
 */
class ProjectPhotoLabelService
{
    private const LABEL_BEFORE = 'Before';

    private const LABEL_DURING = 'During';

    private string $labelText = self::LABEL_BEFORE;

    /** 1 = solid white, 0 = invisible. */
    private const LABEL_OPACITY = 0.35;

    /** Dark drop shadow so the pale label stays readable on white. */
    private const LABEL_SHADOW_OPACITY = 0.45;

    /**
     * Word runs upward: the first letter sits lower than the last.
     * Imagick annotateImage is clockwise-positive, so a negative angle rises.
     * GD imagettftext is counter-clockwise-positive, so the same rise is positive.
     */
    private const LABEL_ANGLE_IMAGICK = -30;

    private const LABEL_ANGLE_GD = 30;

    public function applyBeforeLabel(string $absolutePath): void
    {
        $this->applyLabel($absolutePath, self::LABEL_BEFORE);
    }

    public function applyDuringLabel(string $absolutePath): void
    {
        $this->applyLabel($absolutePath, self::LABEL_DURING);
    }

    public function applyLabel(string $absolutePath, string $text): void
    {
        if (! in_array($text, [self::LABEL_BEFORE, self::LABEL_DURING], true)) {
            throw new RuntimeException('Unknown photo label.');
        }

        $this->labelText = $text;

        if (! is_file($absolutePath)) {
            throw new RuntimeException('Image file not found.');
        }

        if ($this->imagickAvailable()) {
            $this->labelViaImagick($absolutePath);

            return;
        }

        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $this->labelViaGdWebp($absolutePath);

            return;
        }

        if (
            $this->resolveDwebpBinary() !== null
            && $this->resolveCwebpBinary() !== null
            && function_exists('imagecreatefrompng')
        ) {
            $this->labelViaDwebpGdCwebp($absolutePath);

            return;
        }

        throw new RuntimeException(
            'Cannot add a label on this server. Enable PHP Imagick or GD with WebP support, or install dwebp/cwebp.'
        );
    }

    private function fontPath(): string
    {
        $path = public_path('fonts/roboto/Roboto-Bold.ttf');
        if (! is_file($path)) {
            throw new RuntimeException('Roboto Bold font is missing from public/fonts/roboto/.');
        }

        return $path;
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

    /**
     * @return array{fontSize: int, text: string}
     */
    private function labelMetrics(int $width, int $height): array
    {
        $shortEdge = max(1, min($width, $height));
        $fontSize = (int) max(48, min(240, round($shortEdge * 0.12)));

        return [
            'fontSize' => $fontSize,
            'text' => $this->labelText,
        ];
    }

    private function shadowOffsetPx(int $fontSize): int
    {
        return (int) max(2, min(8, round($fontSize * 0.03)));
    }

    private function imagickAvailable(): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return in_array('WEBP', \Imagick::queryFormats(), true);
        } catch (Throwable) {
            return false;
        }
    }

    private function labelViaImagick(string $absolutePath): void
    {
        try {
            $im = new \Imagick($absolutePath);
        } catch (Throwable $e) {
            throw new RuntimeException('Imagick could not open the WebP file.', 0, $e);
        }

        try {
            $width = $im->getImageWidth();
            $height = $im->getImageHeight();
            $metrics = $this->labelMetrics($width, $height);
            $font = $this->fontPath();

            $draw = new \ImagickDraw;
            $draw->setFont($font);
            $draw->setFontSize($metrics['fontSize']);
            $draw->setTextAntialias(true);
            $draw->setGravity(\Imagick::GRAVITY_CENTER);
            $offset = $this->shadowOffsetPx($metrics['fontSize']);
            $draw->setFillColor(new \ImagickPixel('rgba(0,0,0,'.self::LABEL_SHADOW_OPACITY.')'));
            $im->annotateImage($draw, $offset, $offset, self::LABEL_ANGLE_IMAGICK, $metrics['text']);
            $draw->setFillColor(new \ImagickPixel('rgba(255,255,255,'.self::LABEL_OPACITY.')'));
            $im->annotateImage($draw, 0, 0, self::LABEL_ANGLE_IMAGICK, $metrics['text']);

            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($this->webpQuality());
            if (! $im->writeImage($absolutePath)) {
                throw new RuntimeException('Imagick could not write the labelled WebP.');
            }
        } finally {
            $im->destroy();
        }
    }

    private function labelViaGdWebp(string $absolutePath): void
    {
        $img = @imagecreatefromwebp($absolutePath);
        if ($img === false) {
            throw new RuntimeException('GD could not read the WebP file.');
        }

        try {
            $this->drawLabelOnGdImage($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            if (! imagewebp($img, $absolutePath, $this->webpQuality())) {
                throw new RuntimeException('GD could not write the labelled WebP.');
            }
        } finally {
            imagedestroy($img);
        }
    }

    private function labelViaDwebpGdCwebp(string $absolutePath): void
    {
        $dwebp = $this->resolveDwebpBinary();
        $cwebp = $this->resolveCwebpBinary();
        if ($dwebp === null || $cwebp === null) {
            throw new RuntimeException('dwebp/cwebp not available.');
        }

        $base = tempnam(sys_get_temp_dir(), 'zrpl');
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
                throw new RuntimeException(trim($p1->getErrorOutput()) ?: 'dwebp failed.');
            }

            $img = @imagecreatefrompng($pngPath);
            if ($img === false) {
                throw new RuntimeException('Could not load PNG from dwebp output.');
            }
            imagesavealpha($img, true);
            $this->drawLabelOnGdImage($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            if (! imagepng($img, $pngPath)) {
                imagedestroy($img);
                throw new RuntimeException('Could not save labelled PNG.');
            }
            imagedestroy($img);

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
                throw new RuntimeException(trim($p2->getErrorOutput().' '.$p2->getOutput()) ?: 'cwebp failed after label.');
            }
        } finally {
            if (is_file($pngPath)) {
                @unlink($pngPath);
            }
        }
    }

    /**
     * @param  \GdImage|resource  $img
     */
    private function drawLabelOnGdImage($img): void
    {
        $width = imagesx($img);
        $height = imagesy($img);
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Invalid image dimensions.');
        }

        $metrics = $this->labelMetrics($width, $height);
        $font = $this->fontPath();
        $bbox = imagettfbbox($metrics['fontSize'], self::LABEL_ANGLE_GD, $font, $metrics['text']);
        if ($bbox === false) {
            throw new RuntimeException('Could not measure label text.');
        }

        $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
        $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        $textX = (int) round($width / 2 - ($minX + $maxX) / 2);
        $textY = (int) round($height / 2 - ($minY + $maxY) / 2);

        imagealphablending($img, true);
        imagesavealpha($img, true);

        $offset = $this->shadowOffsetPx($metrics['fontSize']);
        $shadowAlpha = (int) round((1 - self::LABEL_SHADOW_OPACITY) * 127);
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
        imagettftext($img, $metrics['fontSize'], self::LABEL_ANGLE_GD, $textX + $offset, $textY + $offset, $shadow, $font, $metrics['text']);

        $alpha = (int) round((1 - self::LABEL_OPACITY) * 127);
        $white = imagecolorallocatealpha($img, 255, 255, 255, $alpha);
        imagettftext($img, $metrics['fontSize'], self::LABEL_ANGLE_GD, $textX, $textY, $white, $font, $metrics['text']);
    }

    private function resolveCwebpBinary(): ?string
    {
        $configured = config('webp.cwebp_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
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

        foreach ([base_path('bin/dwebp.exe'), base_path('bin/dwebp')] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $finder = new ExecutableFinder;

        return $finder->find('dwebp', null, [dirname(PHP_BINARY), base_path('bin')]);
    }
}

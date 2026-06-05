<?php

namespace App\Services;

use RuntimeException;
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

        throw new RuntimeException(
            'Cannot crop WebP on this server. Enable PHP Imagick with WebP, or GD with WebP read/write and imagecrop.'
        );
    }

    private function webpQuality(): int
    {
        $q = (int) config('webp.quality', 75);

        return max(1, min(100, $q));
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
}

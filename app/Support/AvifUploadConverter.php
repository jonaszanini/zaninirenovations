<?php

namespace App\Support;

use App\Support\Logging\AppLogger;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Server-side fallback for .avif uploads the browser did not convert.
 * Decodes with GD (PHP 8.1+ built with libavif) or Imagick (AVIF/HEIF delegate)
 * and hands back a WebP UploadedFile so every existing image pipeline accepts it.
 */
class AvifUploadConverter
{
    public static function isAvif(UploadedFile $file): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'avif') {
            return true;
        }

        try {
            $mime = strtolower((string) $file->getMimeType());
        } catch (Throwable) {
            $mime = '';
        }

        return $mime === 'image/avif';
    }

    public static function supported(): bool
    {
        if (function_exists('imagecreatefromavif') && function_exists('imagewebp')) {
            return true;
        }

        return self::imagickSupportsAvif();
    }

    /**
     * Returns a WebP upload, or the original file when conversion is not possible.
     */
    public static function convert(UploadedFile $file): UploadedFile
    {
        if (! $file->isValid() || ! self::isAvif($file)) {
            return $file;
        }

        $source = $file->getRealPath();
        if ($source === false || ! is_file($source)) {
            return $file;
        }

        $webp = self::encodeWebp($source);
        if ($webp === null) {
            return $file;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'avif_');
        if ($tmp === false) {
            return $file;
        }
        $target = $tmp.'.webp';
        @unlink($tmp);
        if (file_put_contents($target, $webp) === false) {
            return $file;
        }

        $name = preg_replace('/\.avif$/i', '', (string) $file->getClientOriginalName());
        if ($name === '' || $name === null) {
            $name = 'image';
        }

        return new UploadedFile($target, $name.'.webp', 'image/webp', null, true);
    }

    private static function encodeWebp(string $source): ?string
    {
        if (function_exists('imagecreatefromavif') && function_exists('imagewebp')) {
            $im = @imagecreatefromavif($source);
            if ($im !== false) {
                imagesavealpha($im, true);
                ob_start();
                $ok = imagewebp($im, null, 90);
                imagedestroy($im);
                $out = ob_get_clean();
                if ($ok && is_string($out) && $out !== '') {
                    return $out;
                }
            }
        }

        if (self::imagickSupportsAvif()) {
            try {
                $imagick = new \Imagick($source);
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality(90);
                $blob = $imagick->getImageBlob();
                $imagick->destroy();
                if (is_string($blob) && $blob !== '') {
                    return $blob;
                }
            } catch (Throwable $e) {
                AppLogger::debug('upload.avif.imagick_failed', [], $e);
            }
        }

        return null;
    }

    private static function imagickSupportsAvif(): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            $formats = \Imagick::queryFormats();

            return in_array('WEBP', $formats, true)
                && (in_array('AVIF', $formats, true) || in_array('HEIC', $formats, true));
        } catch (Throwable) {
            return false;
        }
    }
}

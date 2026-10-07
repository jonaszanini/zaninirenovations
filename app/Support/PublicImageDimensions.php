<?php

namespace App\Support;

/**
 * Width/height for public <img> tags when the file is on disk.
 */
class PublicImageDimensions
{
    /**
     * @return array{width: int, height: int}|null
     */
    public static function of(string $absolutePath): ?array
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! is_array($info)) {
            return null;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 1 || $height < 1) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }
}

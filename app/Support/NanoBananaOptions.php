<?php

namespace App\Support;

use InvalidArgumentException;

class NanoBananaOptions
{
    /**
     * @return list<string>
     */
    public static function aspectRatios(): array
    {
        return config('nano_banana.aspect_ratios', ['16:9']);
    }

    /**
     * @return list<string>
     */
    public static function imageSizes(): array
    {
        return config('nano_banana.image_sizes', ['1K']);
    }

    public static function defaultAspectRatio(): string
    {
        return (string) config('nano_banana.default_aspect_ratio', '16:9');
    }

    public static function defaultImageSize(): string
    {
        return (string) config('nano_banana.default_image_size', '1K');
    }

    /**
     * Approximate width × height for UI labels (long edge matches API size tier).
     *
     * @return array{width: int, height: int, label: string}
     */
    public static function approximateDimensions(string $aspectRatio, string $imageSize): array
    {
        self::assertValid($aspectRatio, $imageSize);

        $longEdge = (int) (config('nano_banana.size_long_edge_px')[$imageSize] ?? 1024);
        [$ratioW, $ratioH] = array_map('intval', explode(':', $aspectRatio, 2));

        if ($ratioW >= $ratioH) {
            $width = $longEdge;
            $height = (int) max(1, round($longEdge * $ratioH / $ratioW));
        } else {
            $height = $longEdge;
            $width = (int) max(1, round($longEdge * $ratioW / $ratioH));
        }

        return [
            'width' => $width,
            'height' => $height,
            'label' => $imageSize.' (~'.$width.'×'.$height.')',
        ];
    }

    /**
     * Size options for one aspect ratio, each with a human-readable pixel hint.
     *
     * @return list<array{value: string, label: string, width: int, height: int}>
     */
    public static function sizesForAspectRatio(string $aspectRatio): array
    {
        if (! in_array($aspectRatio, self::aspectRatios(), true)) {
            throw new InvalidArgumentException('Unsupported aspect ratio.');
        }

        $options = [];
        foreach (self::imageSizes() as $size) {
            $dims = self::approximateDimensions($aspectRatio, $size);
            $options[] = [
                'value' => $size,
                'label' => $dims['label'],
                'width' => $dims['width'],
                'height' => $dims['height'],
            ];
        }

        return $options;
    }

    /**
     * Payload for the Nano Banana 2 admin UI (AR buttons + dynamic size dropdown).
     *
     * @return array{
     *     aspectRatios: list<string>,
     *     sizesByAspect: array<string, list<array{value: string, label: string, width: int, height: int}>>,
     *     defaults: array{aspectRatio: string, imageSize: string}
     * }
     */
    public static function clientConfig(): array
    {
        $sizesByAspect = [];
        foreach (self::aspectRatios() as $aspectRatio) {
            $sizesByAspect[$aspectRatio] = self::sizesForAspectRatio($aspectRatio);
        }

        return [
            'aspectRatios' => self::aspectRatios(),
            'sizesByAspect' => $sizesByAspect,
            'defaults' => [
                'aspectRatio' => self::defaultAspectRatio(),
                'imageSize' => self::defaultImageSize(),
            ],
        ];
    }

    public static function assertValid(string $aspectRatio, string $imageSize): void
    {
        if (! in_array($aspectRatio, self::aspectRatios(), true)) {
            throw new InvalidArgumentException('Unsupported aspect ratio.');
        }

        if (! in_array($imageSize, self::imageSizes(), true)) {
            throw new InvalidArgumentException('Unsupported image size.');
        }
    }

    /**
     * @return array{aspect_ratio: string, image_size: string}
     */
    public static function validated(string $aspectRatio, string $imageSize): array
    {
        $aspectRatio = trim($aspectRatio);
        $imageSize = trim($imageSize);

        self::assertValid($aspectRatio, $imageSize);

        return [
            'aspect_ratio' => $aspectRatio,
            'image_size' => $imageSize,
        ];
    }
}

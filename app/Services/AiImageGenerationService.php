<?php

namespace App\Services;

use App\Models\AiGeneratedImage;
use App\Support\NanoBananaOptions;
use RuntimeException;

class AiImageGenerationService
{
    public function __construct(
        protected GeminiService $gemini,
        protected BlogImageStorage $images,
    ) {}

    /**
     * Generate an image with Nano Banana 2, store original + WebP, and persist metadata.
     */
    public function generate(string $prompt, string $aspectRatio, string $imageSize): AiGeneratedImage
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Image prompt is empty.');
        }

        $params = NanoBananaOptions::validated($aspectRatio, $imageSize);

        $generated = $this->gemini->generateImage(
            $prompt,
            $params['aspect_ratio'],
            $params['image_size'],
        );

        // Gemini may return a square canvas with the scene letterboxed; crop to the requested AR.
        $cropper = app(GeminiImageCropService::class);
        $imageBytes = $cropper->cropToAspectRatio(
            $generated['bytes'],
            $params['aspect_ratio'],
        );
        $imageMime = $imageBytes !== $generated['bytes'] ? 'image/jpeg' : $generated['mime'];

        if (! $cropper->matchesAspectRatio($imageBytes, $params['aspect_ratio'])) {
            $cropChanged = $imageBytes !== $generated['bytes'];
            $verified = $cropper->readAspectRatio($imageBytes) !== null;

            if (! $cropChanged || $verified) {
                throw new RuntimeException(
                    'Gemini returned a square letterboxed image and this server could not crop it to '
                    .$params['aspect_ratio'].'. Enable PHP GD (php-gd) or Imagick, install ImageMagick '
                    .'(MAGICK_PATH in .env), or set FFMPEG_PATH in .env.'
                );
            }
        }

        $originalPath = $this->images->storeAiToolOriginalFromBinary(
            $imageBytes,
            $imageMime,
        );

        $webpPath = $this->images->storeAiToolWebpFromBinary(
            $imageBytes,
            $imageMime,
        );

        return AiGeneratedImage::query()->create([
            'prompt' => $prompt,
            'original_image_path' => $originalPath,
            'webp_image_path' => $webpPath,
            'aspect_ratio' => $params['aspect_ratio'],
            'image_size' => $params['image_size'],
        ]);
    }

    public function deleteRecord(AiGeneratedImage $image): void
    {
        $this->images->delete($image->original_image_path);
        $this->images->delete($image->webp_image_path);
        $image->delete();
    }
}

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

        $originalPath = $this->images->storeAiToolOriginalFromBinary(
            $generated['bytes'],
            $generated['mime'],
        );

        $webpPath = $this->images->storeAiToolWebpFromBinary(
            $generated['bytes'],
            $generated['mime'],
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

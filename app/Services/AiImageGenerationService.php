<?php

namespace App\Services;

use App\Models\AiGeneratedImage;
use RuntimeException;

class AiImageGenerationService
{
    public function __construct(
        protected GeminiService $gemini,
        protected BlogImageStorage $images,
    ) {}

    /**
     * Generate an image with Gemini using the same API path as blog featured images.
     */
    public function generate(string $prompt): AiGeneratedImage
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Image prompt is empty.');
        }

        $generated = $this->gemini->generateImage($prompt);
        $path = $this->images->storeAiToolImageFromBinary(
            $generated['bytes'],
            $generated['mime'],
        );

        return AiGeneratedImage::query()->create([
            'prompt' => $prompt,
            'image_path' => $path,
        ]);
    }

    public function deleteRecord(AiGeneratedImage $image): void
    {
        $this->images->delete($image->image_path);
        $image->delete();
    }
}

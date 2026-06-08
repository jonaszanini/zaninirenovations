<?php

namespace App\Services;

use App\Models\AiGeneratedImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class AiImageModificationService
{
    public function __construct(
        protected GeminiService $gemini,
        protected BlogImageStorage $images,
    ) {}

    /**
     * Edit an uploaded photo with Gemini and store the full-resolution result (no WebP).
     */
    public function modify(UploadedFile $source, string $prompt): AiGeneratedImage
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Edit prompt is empty.');
        }

        $path = $source->getRealPath();
        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('Could not read the uploaded image.');
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Uploaded image is empty.');
        }

        $mime = strtolower((string) ($source->getMimeType() ?: ''));
        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            $mime = is_string($detected) ? strtolower($detected) : '';
        }
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $edited = $this->gemini->modifyImage($prompt, $bytes, $mime);

        $sourcePath = $this->images->storeAiToolSourceFromBinary($bytes, $mime);
        $originalPath = $this->images->storeAiToolOriginalFromBinary(
            $edited['bytes'],
            $edited['mime'],
        );

        return AiGeneratedImage::query()->create([
            'mode' => AiGeneratedImage::MODE_MODIFY,
            'prompt' => $prompt,
            'source_image_path' => $sourcePath,
            'original_image_path' => $originalPath,
            'webp_image_path' => null,
            'aspect_ratio' => null,
            'image_size' => null,
        ]);
    }

    public function deleteRecord(AiGeneratedImage $image): void
    {
        $this->images->delete($image->source_image_path);
        $this->images->delete($image->original_image_path);
        $image->delete();
    }
}

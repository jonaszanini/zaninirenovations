<?php

namespace App\Services;

use App\Models\AiGeneratedImage;
use App\Models\User;
use App\Support\GooglePickerItems;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class AiImageModificationService
{
    public function __construct(
        protected GeminiService $gemini,
        protected BlogImageStorage $images,
        protected GooglePhotosService $googlePhotos,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $googlePickerRawItems
     * @return array{images: list<AiGeneratedImage>, failures: list<array{label: string, error: string}>, summary: string}
     */
    public function modifyBatch(User $user, string $prompt, array $uploads, array $googlePickerRawItems): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Edit prompt is empty.');
        }

        $images = [];
        $failures = [];

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $label = $upload->getClientOriginalName() ?: 'Upload';

            try {
                $images[] = $this->modifyUploadedFile($prompt, $upload);
            } catch (\Throwable $e) {
                $failures[] = [
                    'label' => $label,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $pickerItems = GooglePickerItems::parse($googlePickerRawItems);
        foreach ($pickerItems as $item) {
            $label = $item['filename'] ?: 'Google Photo';

            try {
                [$tmp, $mime] = $this->googlePhotos->downloadPickedMedia(
                    $user,
                    $item['baseUrl'],
                    $item['mimeType'],
                    preferFullResolution: true,
                );

                try {
                    $bytes = file_get_contents($tmp);
                    if ($bytes === false || $bytes === '') {
                        throw new RuntimeException('Downloaded Google Photo is empty.');
                    }

                    $mime = $this->normalizeMime($mime, $tmp);
                    $images[] = $this->modifyFromBytes($prompt, $bytes, $mime);
                } finally {
                    if (is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            } catch (\Throwable $e) {
                $failures[] = [
                    'label' => $label,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($images === [] && $failures !== []) {
            throw new RuntimeException($failures[0]['error']);
        }

        $ok = count($images);
        $failed = count($failures);
        $summary = $failed === 0
            ? ($ok === 1 ? '1 image edited and saved.' : $ok.' images edited and saved.')
            : $ok.' edited, '.$failed.' failed.';

        return [
            'images' => $images,
            'failures' => $failures,
            'summary' => $summary,
        ];
    }

    /**
     * Edit an uploaded photo with Gemini and store the full-resolution result (no WebP).
     */
    public function modify(UploadedFile $source, string $prompt): AiGeneratedImage
    {
        return $this->modifyUploadedFile($prompt, $source);
    }

    public function deleteRecord(AiGeneratedImage $image): void
    {
        $this->images->delete($image->source_image_path);
        $this->images->delete($image->original_image_path);
        $image->delete();
    }

    private function modifyUploadedFile(string $prompt, UploadedFile $source): AiGeneratedImage
    {
        $path = $source->getRealPath();
        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('Could not read the uploaded image.');
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Uploaded image is empty.');
        }

        $mime = $this->normalizeMime($source->getMimeType(), $path);

        return $this->modifyFromBytes($prompt, $bytes, $mime);
    }

    private function modifyFromBytes(string $prompt, string $bytes, string $mime): AiGeneratedImage
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Edit prompt is empty.');
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
        ]);
    }

    private function normalizeMime(?string $mime, string $path): string
    {
        $mime = strtolower(trim((string) $mime));
        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            $mime = is_string($detected) ? strtolower($detected) : '';
        }
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            return 'image/jpeg';
        }

        return $mime;
    }
}

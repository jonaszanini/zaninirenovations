<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\Project;
use App\Models\User;
use App\Support\GooglePickerItems;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PortfolioDescriptionAiService
{
    private const MAX_IMAGES = 8;

    public function __construct(
        protected GeminiService $gemini,
        protected GooglePhotosService $googlePhotos,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $googlePickerRawItems
     * @return array{option_1: string, option_2: string, image_count: int}
     */
    public function suggest(
        User $user,
        string $briefDescription,
        array $uploads,
        array $googlePickerRawItems,
        ?Project $project = null,
    ): array {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_PORTFOLIO_DESCRIPTION);
        if (! $instruction) {
            throw new RuntimeException('Portfolio description AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $briefDescription = trim($briefDescription);
        if ($briefDescription === '') {
            throw new RuntimeException('Enter a brief description first.');
        }

        $images = $this->collectImages($user, $uploads, $googlePickerRawItems, $project);
        $imageCount = count($images);

        $userText = "### Brief Description ###\n{$briefDescription}\n\n";
        if ($imageCount > 0) {
            $userText .= "### Attached Images ###\n{$imageCount} project photo(s) are attached to this message for visual analysis.\n";
        } else {
            $userText .= "### Attached Images ###\nNo photos were attached. Base the description on the brief text only.\n";
        }

        $raw = $this->gemini->generateStructuredJsonWithImages(
            $instruction->instruction,
            $userText,
            $this->responseSchema(),
            $images,
        );

        $option1 = $this->stringField($raw, 'option_1');
        $option2 = $this->stringField($raw, 'option_2');

        if ($option1 === '' && $option2 === '') {
            throw new RuntimeException('Gemini did not return description options.');
        }

        if ($option1 === '') {
            $option1 = $option2;
        }
        if ($option2 === '') {
            $option2 = $option1;
        }

        return [
            'option_1' => $option1,
            'option_2' => $option2,
            'image_count' => $imageCount,
        ];
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $googlePickerRawItems
     * @return list<array{bytes: string, mime: string}>
     */
    private function collectImages(
        User $user,
        array $uploads,
        array $googlePickerRawItems,
        ?Project $project,
    ): array {
        $images = [];

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile || count($images) >= self::MAX_IMAGES) {
                continue;
            }

            $added = $this->appendImageFromPath($images, $upload->getRealPath(), $upload->getMimeType());
            if (! $added) {
                continue;
            }
        }

        foreach (GooglePickerItems::parse($googlePickerRawItems) as $item) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }

            try {
                [$tmp, $mime] = $this->googlePhotos->downloadPickedMedia(
                    $user,
                    $item['baseUrl'],
                    $item['mimeType'],
                    preferFullResolution: false,
                );

                try {
                    $this->appendImageFromPath($images, $tmp, $mime);
                } finally {
                    if (is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($project !== null && count($images) < self::MAX_IMAGES) {
            $project->loadMissing(['photos' => fn ($q) => $q->orderBy('sort_order')]);

            foreach ($project->photos as $photo) {
                if (count($images) >= self::MAX_IMAGES) {
                    break;
                }

                if (! filled($photo->path) || ! Storage::disk('public')->exists($photo->path)) {
                    continue;
                }

                $this->appendImageFromPath($images, Storage::disk('public')->path($photo->path), 'image/webp');
            }
        }

        return $images;
    }

    /**
     * @param  list<array{bytes: string, mime: string}>  $images
     */
    private function appendImageFromPath(array &$images, string|false|null $path, ?string $mime): bool
    {
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return false;
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return false;
        }

        $mime = $this->normalizeMime($mime, $path);
        $images[] = [
            'bytes' => $bytes,
            'mime' => $mime,
        ];

        return true;
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

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'option_1' => [
                    'type' => 'STRING',
                    'description' => 'Option 1: craftsmanship, aesthetic appeal, and material details.',
                ],
                'option_2' => [
                    'type' => 'STRING',
                    'description' => 'Option 2: technical best practices, durability, and Ontario Building Code standards.',
                ],
            ],
            'required' => ['option_1', 'option_2'],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function stringField(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}

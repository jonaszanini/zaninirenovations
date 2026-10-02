<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class PortfolioDescriptionAiService
{
    public function __construct(
        protected GeminiService $gemini,
        protected PortfolioAiImageCollector $images,
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

        $images = $this->images->collect($user, $uploads, $googlePickerRawItems, $project);
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

<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\User;
use App\Support\GooglePickerItems;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class PortfolioPhotoAiImprovementService
{
    public function __construct(
        protected GeminiService $gemini,
        protected GooglePhotosService $googlePhotos,
        protected ProjectPhotoWebpStorage $webpStorage,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $googlePickerRawItems
     * @return array{
     *   files: list<array{filename: string, content_base64: string, size_bytes: int, label: string}>,
     *   failures: list<array{label: string, error: string}>,
     *   summary: string
     * }
     */
    public function improveBatch(User $user, array $uploads, array $googlePickerRawItems): array
    {
        $prompt = $this->resolvePrompt();

        $files = [];
        $failures = [];

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $label = $upload->getClientOriginalName() ?: 'Upload';

            try {
                $files[] = $this->improveUploadedFile($prompt, $upload, $label);
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
                    $files[] = $this->improveFromBytes($prompt, $bytes, $mime, $label);
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

        if ($files === [] && $failures !== []) {
            throw new RuntimeException($failures[0]['error']);
        }

        $ok = count($files);
        $failed = count($failures);
        $summary = $failed === 0
            ? ($ok === 1 ? '1 AI-improved WebP photo ready to save.' : $ok.' AI-improved WebP photos ready to save.')
            : $ok.' improved, '.$failed.' failed.';

        return [
            'files' => $files,
            'failures' => $failures,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{filename: string, content_base64: string, size_bytes: int, label: string}
     */
    private function improveUploadedFile(string $prompt, UploadedFile $source, string $label): array
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

        return $this->improveFromBytes($prompt, $bytes, $mime, $label);
    }

    /**
     * @return array{filename: string, content_base64: string, size_bytes: int, label: string}
     */
    private function improveFromBytes(string $prompt, string $bytes, string $mime, string $label): array
    {
        $edited = $this->gemini->modifyImage($prompt, $bytes, $mime);
        $webp = $this->webpStorage->convertBinaryToWebp(
            $edited['bytes'],
            $edited['mime'],
            $label,
        );

        return [
            'filename' => $this->webpFilename($label),
            'content_base64' => base64_encode($webp),
            'size_bytes' => strlen($webp),
            'label' => $label,
        ];
    }

    private function webpFilename(string $label): string
    {
        $base = pathinfo($label, PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-');
        if ($base === '') {
            $base = 'photo';
        }

        return substr($base, 0, 80).'-ai.webp';
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

    private function resolvePrompt(): string
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_PORTFOLIO_PHOTO_PROMPT);
        if (! $instruction) {
            throw new RuntimeException('Latest projects photo prompt improvement is not configured. Add it under Tools → AI Instructions.');
        }

        $prompt = trim($instruction->instruction);
        if ($prompt === '') {
            throw new RuntimeException('Latest projects photo prompt improvement is empty. Edit it under Tools → AI Instructions.');
        }

        return $prompt;
    }
}

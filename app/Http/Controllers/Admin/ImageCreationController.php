<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedImage;
use App\Services\AiImageGenerationService;
use App\Services\AiImageModificationService;
use App\Services\AiImagePromptImprovementService;
use App\Support\GooglePickerItems;
use App\Support\NanoBananaOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ImageCreationController extends Controller
{
    private const MAX_MODIFY_SOURCES = 15;

    public function index()
    {
        $workflows = [
            [
                'name' => 'Create image',
                'description' => 'Generate a new image from a text prompt with aspect ratio and resolution controls.',
                'route' => route('admin.image-creation.create'),
                'icon' => 'fa-magic',
                'available' => true,
            ],
            [
                'name' => 'Modify image',
                'description' => 'Upload a photo and describe what to change. The edited result is saved at full resolution (no WebP).',
                'route' => route('admin.image-creation.modify'),
                'icon' => 'fa-pencil-square-o',
                'available' => true,
            ],
        ];

        return view('admin.image_creation.index', compact('workflows'));
    }

    public function create()
    {
        $images = AiGeneratedImage::query()
            ->where(function ($query) {
                $query->where('mode', AiGeneratedImage::MODE_CREATE)
                    ->orWhereNull('mode');
            })
            ->orderByDesc('created_at')
            ->paginate(24);

        return view('admin.image_creation.create', [
            'images' => $images,
            'nb2Options' => NanoBananaOptions::clientConfig(),
        ]);
    }

    /** @deprecated Use create() — kept for existing bookmarks. */
    public function nanoBanana2()
    {
        return $this->create();
    }

    public function modify()
    {
        $images = AiGeneratedImage::query()
            ->where('mode', AiGeneratedImage::MODE_MODIFY)
            ->orderByDesc('created_at')
            ->paginate(24);

        $inlineMaxMb = round((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024 / 1024, 1);

        return view('admin.image_creation.modify', [
            'images' => $images,
            'inlineMaxMb' => $inlineMaxMb,
            'maxSources' => self::MAX_MODIFY_SOURCES,
        ]);
    }

    public function generate(Request $request, AiImageGenerationService $generator): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'aspect_ratio' => ['required', 'string', Rule::in(NanoBananaOptions::aspectRatios())],
            'image_size' => ['required', 'string', Rule::in(NanoBananaOptions::imageSizes())],
        ]);

        try {
            $image = $generator->generate(
                $data['prompt'],
                $data['aspect_ratio'],
                $data['image_size'],
            );

            return response()->json($this->imagePayload($image));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function modifyImage(Request $request, AiImageModificationService $modifier): JsonResponse
    {
        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'source_images' => ['nullable', 'array', 'max:'.self::MAX_MODIFY_SOURCES],
            'source_images.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'google_picker_items' => ['nullable', 'array', 'max:'.self::MAX_MODIFY_SOURCES],
            'google_picker_items.*' => ['string'],
        ]);

        $uploads = array_values(array_filter(
            (array) $request->file('source_images', []),
            fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));
        $googleItems = array_values(array_filter(
            (array) ($data['google_picker_items'] ?? []),
            fn ($item) => is_string($item) && $item !== '',
        ));

        $sourceCount = count($uploads) + count(GooglePickerItems::parse($googleItems));
        if ($sourceCount < 1) {
            return response()->json(['error' => 'Choose at least one source photo (upload or Google Photos).'], 422);
        }
        if ($sourceCount > self::MAX_MODIFY_SOURCES) {
            return response()->json(['error' => 'Too many photos selected (max '.self::MAX_MODIFY_SOURCES.').'], 422);
        }

        try {
            $result = $modifier->modifyBatch(
                $request->user(),
                $data['prompt'],
                $uploads,
                $googleItems,
            );

            return response()->json([
                'images' => array_map(fn (AiGeneratedImage $image) => $this->imagePayload($image), $result['images']),
                'failures' => $result['failures'],
                'summary' => $result['summary'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function improvePrompt(Request $request, AiImagePromptImprovementService $improver): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $improver->improve($data['prompt']);

            return response()->json([
                'prompt' => $result['optimized_prompt'],
                'critique' => $result['critique'],
                'why_better' => $result['why_better'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function download(AiGeneratedImage $ai_generated_image, string $format)
    {
        if (! in_array($format, ['original', 'webp', 'source'], true)) {
            abort(404);
        }

        $path = match ($format) {
            'original' => $ai_generated_image->original_image_path,
            'webp' => $ai_generated_image->webp_image_path,
            'source' => $ai_generated_image->source_image_path,
        };

        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: match ($format) {
            'webp' => 'webp',
            'source' => 'jpg',
            default => 'jpg',
        };
        $filename = 'ai-image-'.$ai_generated_image->id.'-'.$format.'.'.$extension;

        return Storage::disk('public')->download($path, $filename);
    }

    public function destroy(
        AiGeneratedImage $ai_generated_image,
        AiImageGenerationService $generator,
        AiImageModificationService $modifier,
    ): JsonResponse {
        if ($ai_generated_image->isModify()) {
            $modifier->deleteRecord($ai_generated_image);
        } else {
            $generator->deleteRecord($ai_generated_image);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function imagePayload(AiGeneratedImage $image): array
    {
        return [
            'id' => $image->id,
            'mode' => $image->mode,
            'prompt' => $image->prompt,
            'aspect_ratio' => $image->aspect_ratio,
            'image_size' => $image->image_size,
            'image_url' => $image->imageUrl(),
            'source_image_url' => $image->sourceImageUrl(),
            'created_at' => $image->displayCreatedAt(),
            'meta' => $image->displayMeta(),
            'download_original_url' => $image->hasOriginalDownload()
                ? route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'original'])
                : null,
            'download_webp_url' => $image->hasWebpDownload()
                ? route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'webp'])
                : null,
            'download_source_url' => filled($image->source_image_path) && Storage::disk('public')->exists($image->source_image_path)
                ? route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'source'])
                : null,
            'delete_url' => route('admin.image-creation.destroy', $image),
        ];
    }
}

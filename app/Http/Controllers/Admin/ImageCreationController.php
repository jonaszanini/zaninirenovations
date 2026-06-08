<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedImage;
use App\Services\AiImageGenerationService;
use App\Support\NanoBananaOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ImageCreationController extends Controller
{
    /**
     * Landing page listing available image-generation models. New models can be
     * added to the $models array (and given their own route + page) without
     * touching the layout.
     */
    public function index()
    {
        $models = [
            [
                'name' => 'Nano Banana 2',
                'route' => route('admin.image-creation.nano-banana-2'),
                'available' => true,
            ],
        ];

        return view('admin.image_creation.index', compact('models'));
    }

    public function nanoBanana2()
    {
        $images = AiGeneratedImage::query()
            ->orderByDesc('created_at')
            ->paginate(24);

        return view('admin.image_creation.nano_banana_2', [
            'images' => $images,
            'nb2Options' => NanoBananaOptions::clientConfig(),
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

    public function download(AiGeneratedImage $ai_generated_image, string $format)
    {
        if (! in_array($format, ['original', 'webp'], true)) {
            abort(404);
        }

        $path = $format === 'original'
            ? $ai_generated_image->original_image_path
            : $ai_generated_image->webp_image_path;

        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: ($format === 'webp' ? 'webp' : 'jpg');
        $filename = 'nano-banana-'.$ai_generated_image->id.'-'.$format.'.'.$extension;

        return Storage::disk('public')->download($path, $filename);
    }

    public function destroy(AiGeneratedImage $ai_generated_image, AiImageGenerationService $generator): JsonResponse
    {
        $generator->deleteRecord($ai_generated_image);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function imagePayload(AiGeneratedImage $image): array
    {
        return [
            'id' => $image->id,
            'prompt' => $image->prompt,
            'aspect_ratio' => $image->aspect_ratio,
            'image_size' => $image->image_size,
            'image_url' => $image->imageUrl(),
            'created_at' => $image->displayCreatedAt(),
            'meta' => $image->displayMeta(),
            'download_original_url' => $image->hasOriginalDownload()
                ? route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'original'])
                : null,
            'download_webp_url' => $image->hasWebpDownload()
                ? route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'webp'])
                : null,
            'delete_url' => route('admin.image-creation.destroy', $image),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedImage;
use App\Services\AiImageGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageCreationController extends Controller
{
    public function index()
    {
        $images = AiGeneratedImage::query()
            ->orderByDesc('created_at')
            ->paginate(24);

        return view('admin.image_creation.index', compact('images'));
    }

    public function generate(Request $request, AiImageGenerationService $generator): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $image = $generator->generate($data['prompt']);

            return response()->json([
                'id' => $image->id,
                'prompt' => $image->prompt,
                'image_path' => $image->image_path,
                'image_url' => $image->imageUrl(),
                'created_at' => $image->displayCreatedAt(),
                'download_url' => route('admin.image-creation.download', $image),
                'delete_url' => route('admin.image-creation.destroy', $image),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function download(AiGeneratedImage $ai_generated_image)
    {
        $path = $ai_generated_image->image_path;
        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $filename = 'nano-banana-'.$ai_generated_image->id.'.webp';

        return Storage::disk('public')->download($path, $filename);
    }

    public function destroy(AiGeneratedImage $ai_generated_image, AiImageGenerationService $generator): JsonResponse
    {
        $generator->deleteRecord($ai_generated_image);

        return response()->json(['ok' => true]);
    }
}

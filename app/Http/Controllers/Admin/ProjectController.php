<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPhoto;
use App\Models\GooglePhotosToken;
use App\Services\GooglePhotosService;
use App\Services\PortfolioPhotoAiImprovementService;
use App\Services\ProjectPhotoCropService;
use App\Services\ProjectPhotoRotationService;
use App\Services\ProjectPhotoWebpStorage;
use App\Support\GooglePickerItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const MAX_PHOTOS_PER_PROJECT = 15;

    private const AI_PROMPT_MAX_LENGTH = 8000;

    public function index(Request $request)
    {
        $categories = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
        ];

        $categoryId = null;
        if ($filters['category'] !== '') {
            $matched = $categories->first(
                fn (ProjectCategory $cat) => $cat->slug === $filters['category']
                    || (string) $cat->id === $filters['category']
            );
            if ($matched) {
                $categoryId = $matched->id;
                $filters['category'] = $matched->slug;
            } else {
                $filters['category'] = '';
            }
        }

        $query = Project::query()
            ->with('categories')
            ->with('coverPhoto')
            ->withCount('photos')
            ->orderByCompletionLatest();

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId !== null) {
            $query->whereHas(
                'categories',
                fn ($sub) => $sub->where('project_categories.id', $categoryId)
            );
        }

        $projects = $query->paginate(15)->withQueryString();

        return view('admin.portfolio.index', compact('projects', 'filters', 'categories'));
    }

    public function create()
    {
        $categories = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.portfolio.create', compact('categories'));
    }

    public function aiImprovePhotos(Request $request, PortfolioPhotoAiImprovementService $improver): JsonResponse
    {
        $imageTimeout = max(60, (int) config('gemini.image_timeout_seconds', 180));
        set_time_limit($imageTimeout + 120);

        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:'.self::AI_PROMPT_MAX_LENGTH],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'google_picker_items' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'google_picker_items.*' => ['string', 'max:4000'],
        ]);

        $uploads = array_values(array_filter(
            (array) $request->file('photos', []),
            fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));
        $googleItems = array_values(array_filter(
            (array) ($data['google_picker_items'] ?? []),
            fn ($item) => is_string($item) && $item !== '',
        ));

        $sourceCount = count($uploads) + count(GooglePickerItems::parse($googleItems));
        if ($sourceCount < 1) {
            return response()->json(['error' => 'Choose at least one photo (upload or checked Google Photos).'], 422);
        }
        if ($sourceCount > self::MAX_PHOTOS_PER_PROJECT) {
            return response()->json(['error' => 'Too many photos selected (max '.self::MAX_PHOTOS_PER_PROJECT.').'], 422);
        }

        try {
            $result = $improver->improveBatch(
                $request->user(),
                $data['prompt'],
                $uploads,
                $googleItems,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'project_category_ids' => ['required', 'array', 'min:1'],
            'project_category_ids.*' => ['integer', 'distinct', 'exists:project_categories,id'],
            'completed_on' => ['required', 'date'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photos.*' => ['file', 'mimes:webp,jpeg,jpg,png', 'max:10240'],
            'google_picker_items' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'google_picker_items.*' => ['string', 'max:4000', 'distinct'],
        ]);

        $fileCount = $request->hasFile('photos') ? count((array) $request->file('photos')) : 0;
        $googleItems = array_values(array_filter((array) $request->input('google_picker_items', []), fn ($v) => is_string($v) && $v !== ''));
        if ($fileCount + count($googleItems) > self::MAX_PHOTOS_PER_PROJECT) {
            return back()
                ->withErrors(['photos' => 'Too many images selected (max '.self::MAX_PHOTOS_PER_PROJECT.' total).'])
                ->withInput();
        }

        $converter = new ProjectPhotoWebpStorage;
        $savedPaths = [];

        try {
            DB::beginTransaction();

            $project = Project::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'completed_on' => $validated['completed_on'],
            ]);

            $project->categories()->sync($validated['project_category_ids']);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $file) {
                    $path = $converter->store($file, (int) $project->id);
                    $savedPaths[] = $path;
                    $project->photos()->create([
                        'path' => $path,
                        'sort_order' => $index,
                    ]);
                }
            }

            if ($googleItems !== []) {
                $this->importGooglePickerMedia($request, $project, $converter, $savedPaths, (int) $project->photos()->count());
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            foreach ($savedPaths as $p) {
                Storage::disk('public')->delete($p);
            }
            report($e);

            return back()
                ->withErrors(['photos' => 'Could not save images. '.$e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.portfolio.index')
            ->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        $project->load([
            'photos' => fn ($q) => $q->orderBy('sort_order'),
            'categories',
        ]);

        $categories = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.portfolio.edit', compact('project', 'categories'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'project_category_ids' => ['required', 'array', 'min:1'],
            'project_category_ids.*' => ['integer', 'distinct', 'exists:project_categories,id'],
            'completed_on' => ['required', 'date'],
            'photo_sequence' => ['nullable', 'array'],
            'photo_sequence.*' => [
                'integer',
                'distinct',
                Rule::exists('project_photos', 'id')->where(fn ($q) => $q->where('project_id', $project->id)),
            ],
            'delete_photos' => ['nullable', 'array'],
            'delete_photos.*' => [
                'integer',
                Rule::exists('project_photos', 'id')->where(fn ($q) => $q->where('project_id', $project->id)),
            ],
        ]);

        $project->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'completed_on' => $validated['completed_on'],
        ]);

        $project->categories()->sync($validated['project_category_ids']);

        if (! empty($validated['delete_photos'])) {
            $photos = ProjectPhoto::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $validated['delete_photos'])
                ->get();

            foreach ($photos as $photo) {
                Storage::disk('public')->delete($photo->path);
                $photo->delete();
            }
        }

        if (! empty($validated['photo_sequence'])) {
            $remainingIds = $project->photos()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $sequenceIds = array_values(array_map('intval', $validated['photo_sequence']));
            $remSorted = $remainingIds;
            $seqSorted = $sequenceIds;
            sort($remSorted);
            sort($seqSorted);
            if ($remSorted !== $seqSorted) {
                return back()
                    ->withErrors(['photo_sequence' => 'Could not save photo order. Refresh the page and try again.'])
                    ->withInput();
            }
            foreach ($sequenceIds as $index => $photoId) {
                ProjectPhoto::query()
                    ->where('project_id', $project->id)
                    ->where('id', $photoId)
                    ->update(['sort_order' => $index]);
            }
        }

        $slots = self::MAX_PHOTOS_PER_PROJECT - $project->photos()->count();

        $request->validate([
            'photos' => ['nullable', 'array', 'max:'.max(0, $slots)],
            'photos.*' => ['file', 'mimes:webp,jpeg,jpg,png', 'max:10240'],
            'google_picker_items' => ['nullable', 'array', 'max:'.max(0, $slots)],
            'google_picker_items.*' => ['string', 'max:4000', 'distinct'],
        ]);

        $fileCount = $request->hasFile('photos') ? count((array) $request->file('photos')) : 0;
        $googleItems = array_values(array_filter((array) $request->input('google_picker_items', []), fn ($v) => is_string($v) && $v !== ''));
        if ($fileCount + count($googleItems) > max(0, $slots)) {
            return back()
                ->withErrors(['photos' => 'Too many images selected for remaining slots (max '.$slots.' more).'])
                ->withInput();
        }

        if ($slots > 0 && $request->hasFile('photos')) {
            $converter = new ProjectPhotoWebpStorage;
            $files = array_slice($request->file('photos'), 0, $slots);
            $maxOrder = (int) $project->photos()->max('sort_order');
            $savedPaths = [];

            try {
                foreach ($files as $file) {
                    $path = $converter->store($file, (int) $project->id);
                    $savedPaths[] = $path;
                    $maxOrder++;
                    $project->photos()->create([
                        'path' => $path,
                        'sort_order' => $maxOrder,
                    ]);
                }
            } catch (\Throwable $e) {
                foreach ($savedPaths as $p) {
                    Storage::disk('public')->delete($p);
                }
                report($e);

                return back()
                    ->withErrors(['photos' => 'Could not add images. '.$e->getMessage()])
                    ->withInput();
            }
        }

        if ($googleItems !== [] && $slots > 0) {
            $converter = new ProjectPhotoWebpStorage;
            $savedPaths = [];

            try {
                $this->importGooglePickerMedia($request, $project, $converter, $savedPaths, (int) $project->photos()->count());
            } catch (\Throwable $e) {
                foreach ($savedPaths as $p) {
                    Storage::disk('public')->delete($p);
                }
                report($e);

                return back()
                    ->withErrors(['photos' => 'Could not import Google Photos. '.$e->getMessage()])
                    ->withInput();
            }
        }

        return redirect()
            ->route('admin.portfolio.edit', $project)
            ->with('success', 'Project updated.');
    }

    public function rotatePhoto(Request $request, Project $project, ProjectPhoto $photo)
    {
        abort_unless((int) $photo->project_id === (int) $project->id, 404);

        $request->validate([
            'direction' => ['required', Rule::in(['cw', 'ccw'])],
        ]);

        $disk = Storage::disk('public');
        if (! $disk->exists($photo->path)) {
            return back()->with('error', 'Photo file is missing on disk.');
        }

        $fullPath = $disk->path($photo->path);

        try {
            app(ProjectPhotoRotationService::class)->rotateWebp($fullPath, $request->input('direction'));
            app(ProjectPhotoWebpStorage::class)->recompressWebpAtPath($fullPath);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not rotate the photo: '.$e->getMessage());
        }

        $photo->touch();
        $this->syncPublicStorageFallback($photo->path);

        return redirect()
            ->route('admin.portfolio.edit', $project)
            ->with('success', 'Photo rotated.');
    }

    public function cropPhoto(Request $request, Project $project, ProjectPhoto $photo)
    {
        abort_unless((int) $photo->project_id === (int) $project->id, 404);

        $validated = $request->validate([
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'width' => ['required', 'numeric', 'min:1'],
            'height' => ['required', 'numeric', 'min:1'],
        ]);

        $disk = Storage::disk('public');
        if (! $disk->exists($photo->path)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Photo file is missing on disk.'], 404);
            }

            return back()->with('error', 'Photo file is missing on disk.');
        }

        $fullPath = $disk->path($photo->path);
        $info = @getimagesize($fullPath);
        if ($info === false) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Could not read image dimensions.'], 422);
            }

            return back()->with('error', 'Could not read image dimensions.');
        }

        $iw = (int) $info[0];
        $ih = (int) $info[1];
        $x = (float) $validated['x'];
        $y = (float) $validated['y'];
        $w = (float) $validated['width'];
        $h = (float) $validated['height'];

        $tol = 0.02;
        if ($x < -$tol || $y < -$tol || $x + $w > $iw + $tol || $y + $h > $ih + $tol) {
            $msg = 'Crop rectangle must be inside the image.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $ix = (int) max(0, min($iw - 1, floor($x)));
        $iy = (int) max(0, min($ih - 1, floor($y)));
        $cropW = (int) max(1, min($iw - $ix, (int) round($w)));
        $cropH = (int) max(1, min($ih - $iy, (int) round($h)));

        if ($cropW < 8 || $cropH < 8) {
            $msg = 'Crop area is too small (minimum 8×8 pixels).';

            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        try {
            app(ProjectPhotoCropService::class)->cropWebp($fullPath, $ix, $iy, $cropW, $cropH);
            app(ProjectPhotoWebpStorage::class)->recompressWebpAtPath($fullPath);
            clearstatcache(true, $fullPath);
        } catch (\Throwable $e) {
            report($e);
            $msg = 'Could not crop the photo: '.$e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 500);
            }

            return back()->with('error', $msg);
        }

        $photo->touch();
        app(ProjectPhotoWebpStorage::class)->syncPublicMirror($photo->path);

        if ($request->expectsJson()) {
            $info = @getimagesize($fullPath);

            return response()->json([
                'ok' => true,
                'width' => $info !== false ? (int) $info[0] : null,
                'height' => $info !== false ? (int) $info[1] : null,
            ]);
        }

        return redirect()
            ->route('admin.portfolio.edit', $project)
            ->with('success', 'Photo cropped.');
    }

    public function destroy(Project $project)
    {
        foreach ($project->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $project->delete();

        return redirect()
            ->route('admin.portfolio.index')
            ->with('success', 'Project deleted.');
    }

    /**
     * Shared hosting fallback when symlink() is unavailable.
     */
    private function syncPublicStorageFallback(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $publicStorageRoot = public_path('storage');
        if (! is_dir($publicStorageRoot)) {
            return;
        }

        $source = Storage::disk('public')->path($relativePath);
        if (! is_file($source)) {
            return;
        }

        $destination = public_path('storage/'.$relativePath);
        $destinationDir = dirname($destination);
        if (! is_dir($destinationDir) && ! @mkdir($destinationDir, 0775, true) && ! is_dir($destinationDir)) {
            return;
        }

        $sourceReal = realpath($source);
        $destinationReal = is_file($destination) ? realpath($destination) : false;
        if ($sourceReal !== false && $destinationReal !== false && $sourceReal === $destinationReal) {
            return;
        }

        @copy($source, $destination);
    }

    /**
     * @param  list<string>  $savedPaths
     */
    private function importGooglePickerMedia(Request $request, Project $project, ProjectPhotoWebpStorage $converter, array &$savedPaths, int $startSortOrder): void
    {
        if (! GooglePhotosToken::query()->where('user_id', $request->user()->id)->exists()) {
            throw new \RuntimeException('Google Photos is not connected.');
        }

        $rawItems = array_values(array_filter((array) $request->input('google_picker_items', []), fn ($v) => is_string($v) && $v !== ''));
        if ($rawItems === []) {
            return;
        }

        $items = GooglePickerItems::parse($rawItems);
        if ($items === []) {
            return;
        }

        $remaining = self::MAX_PHOTOS_PER_PROJECT - (int) $project->photos()->count();
        if ($remaining < 0) {
            $remaining = 0;
        }

        if (count($items) > $remaining) {
            throw new \RuntimeException('Too many Google Photos items selected for remaining slots.');
        }

        $photosApi = app(GooglePhotosService::class);

        $order = $startSortOrder;
        foreach ($items as $item) {
            [$tmp, $mime] = $photosApi->downloadPickedMedia(
                $request->user(),
                (string) $item['baseUrl'],
                isset($item['mimeType']) ? (string) $item['mimeType'] : null,
            );

            try {
                $path = $converter->storeFromPath($tmp, (int) $project->id, $mime);
                $savedPaths[] = $path;
                $project->photos()->create([
                    'path' => $path,
                    'sort_order' => $order,
                ]);
                $order++;
            } finally {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }
}

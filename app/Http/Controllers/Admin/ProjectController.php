<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPhoto;
use App\Models\ProjectSlugRedirect;
use App\Models\GooglePhotosToken;
use App\Services\GooglePhotosService;
use App\Services\PortfolioCategoryAiService;
use App\Services\PortfolioDescriptionAiService;
use App\Services\PortfolioDraftPhotoService;
use App\Services\PortfolioPhotoAiImprovementService;
use App\Services\ProjectPhotoCropService;
use App\Services\ProjectPhotoLabelService;
use App\Services\ProjectPhotoRotationService;
use App\Services\ProjectPhotoWebpStorage;
use App\Support\GooglePickerItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('town', 'like', "%{$search}%");
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

    public function create(PortfolioDraftPhotoService $draftPhotos)
    {
        $categories = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $draftPhotos = $draftPhotos->resolveForForm(old('draft_photos'));

        return view('admin.portfolio.create', compact('categories', 'draftPhotos'));
    }

    public function aiImprovePhotos(Request $request, PortfolioPhotoAiImprovementService $improver): JsonResponse
    {
        $imageTimeout = max(60, (int) config('gemini.image_timeout_seconds', 180));
        set_time_limit($imageTimeout + 120);

        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'google_picker_items' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'google_picker_items.*' => ['string', 'max:4000'],
            'existing_photo_id' => ['nullable', 'integer', 'exists:project_photos,id'],
        ]);

        $existingPhotoId = (int) ($data['existing_photo_id'] ?? 0);
        if ($existingPhotoId > 0) {
            return $this->improveExistingPhoto($existingPhotoId, $improver);
        }

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

        if ($request->boolean('stash_drafts')) {
            $existingDrafts = count(app(PortfolioDraftPhotoService::class)->listPaths());
            if ($existingDrafts + $sourceCount > self::MAX_PHOTOS_PER_PROJECT) {
                return response()->json([
                    'error' => 'Too many photos (max '.self::MAX_PHOTOS_PER_PROJECT.' including saved photos).',
                ], 422);
            }
        }

        try {
            $result = $improver->improveBatch(
                $request->user(),
                $uploads,
                $googleItems,
            );

            if ($request->boolean('stash_drafts')) {
                $drafts = app(PortfolioDraftPhotoService::class);
                $stashed = [];
                foreach ($result['files'] as $file) {
                    if (empty($file['content_base64'])) {
                        continue;
                    }
                    $webp = base64_decode((string) $file['content_base64'], true);
                    if ($webp === false || $webp === '') {
                        continue;
                    }
                    $path = $drafts->stashWebp($webp);
                    $stashed[] = [
                        'draft_path' => $path,
                        'url' => '/storage/'.$path,
                        'filename' => $file['filename'] ?? basename($path),
                        'label' => $file['label'] ?? basename($path),
                        'size_bytes' => strlen($webp),
                    ];
                }
                $result['files'] = $stashed;
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function stashDraftPhotos(Request $request, PortfolioDraftPhotoService $drafts): JsonResponse
    {
        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'photos' => ['required', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
        ]);

        $uploads = array_values(array_filter(
            (array) $request->file('photos', []),
            fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));

        if ($uploads === []) {
            return response()->json(['error' => 'No photos to save.'], 422);
        }

        $existing = count($drafts->listPaths());
        if ($existing + count($uploads) > self::MAX_PHOTOS_PER_PROJECT) {
            return response()->json([
                'error' => 'Too many draft photos (max '.self::MAX_PHOTOS_PER_PROJECT.' total).',
            ], 422);
        }

        $saved = [];
        foreach ($uploads as $file) {
            try {
                $path = $drafts->stashUpload($file);
                $saved[] = [
                    'draft_path' => $path,
                    'url' => '/storage/'.$path,
                    'filename' => basename($path),
                ];
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 502);
            }
        }

        return response()->json(['photos' => $saved]);
    }

    private function improveExistingPhoto(int $photoId, PortfolioPhotoAiImprovementService $improver): JsonResponse
    {
        $photo = ProjectPhoto::query()->find($photoId);
        if (! $photo) {
            return response()->json(['error' => 'That saved photo could not be found.'], 422);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($photo->path)) {
            return response()->json(['error' => 'Photo file is missing on disk.'], 404);
        }

        $fullPath = $disk->path($photo->path);

        try {
            $file = $improver->improveLocalFile($fullPath, basename($photo->path));
            $webp = base64_decode((string) ($file['content_base64'] ?? ''), true);
            if ($webp === false || $webp === '') {
                throw new \RuntimeException('AI improve returned an empty image.');
            }
            if (file_put_contents($fullPath, $webp) === false) {
                throw new \RuntimeException('Could not save the improved photo.');
            }

            clearstatcache(true, $fullPath);
            $photo->touch();
            app(ProjectPhotoWebpStorage::class)->syncPublicMirror($photo->path);
            $photo->refresh();

            return response()->json([
                'files' => [[
                    'replaced' => true,
                    'photo_id' => $photo->id,
                    'url' => $photo->url(),
                    'filename' => $file['filename'] ?? basename($photo->path),
                    'label' => $file['label'] ?? basename($photo->path),
                    'size_bytes' => strlen($webp),
                ]],
                'failures' => [],
                'summary' => '1 saved photo replaced with the AI-improved version.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function removeDraftPhoto(Request $request, PortfolioDraftPhotoService $drafts): JsonResponse
    {
        $data = $request->validate([
            'draft_path' => ['required', 'string', 'max:500'],
        ]);

        if (! $drafts->remove($data['draft_path'])) {
            return response()->json(['error' => 'Draft photo not found.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function aiImproveDescription(Request $request, PortfolioDescriptionAiService $describer): JsonResponse
    {
        return $this->respondWithImprovedDescription($request, $describer, null);
    }

    public function aiImproveDescriptionForProject(
        Request $request,
        Project $project,
        PortfolioDescriptionAiService $describer,
    ): JsonResponse {
        return $this->respondWithImprovedDescription($request, $describer, $project);
    }

    public function aiCategorize(Request $request, PortfolioCategoryAiService $categorizer, PortfolioDraftPhotoService $draftPhotos): JsonResponse
    {
        return $this->respondWithCategories($request, $categorizer, $draftPhotos, null);
    }

    public function aiCategorizeForProject(
        Request $request,
        Project $project,
        PortfolioCategoryAiService $categorizer,
        PortfolioDraftPhotoService $draftPhotos,
    ): JsonResponse {
        return $this->respondWithCategories($request, $categorizer, $draftPhotos, $project);
    }

    public function store(Request $request, PortfolioDraftPhotoService $draftPhotos)
    {
        $this->preparePortfolioSeoRequest($request);

        $validated = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'project_category_ids' => ['required', 'array', 'min:1'],
            'project_category_ids.*' => ['integer', 'distinct', 'exists:project_categories,id'],
            'completed_on' => ['required', 'date'],
            'town' => ['nullable', 'string', 'max:80'],
            'draft_photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'draft_photos.*' => ['string', 'max:500'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photos.*' => ['file', 'mimes:webp,jpeg,jpg,png', 'max:10240'],
            'google_picker_items' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'google_picker_items.*' => ['string', 'max:4000', 'distinct'],
        ], $this->portfolioSeoRules()));

        $draftPaths = $draftPhotos->validatePaths($validated['draft_photos'] ?? []);
        $fileCount = $request->hasFile('photos') ? count((array) $request->file('photos')) : 0;
        $googleItems = array_values(array_filter((array) $request->input('google_picker_items', []), fn ($v) => is_string($v) && $v !== ''));
        if (count($draftPaths) + $fileCount + count($googleItems) > self::MAX_PHOTOS_PER_PROJECT) {
            return back()
                ->withErrors(['photos' => 'Too many images selected (max '.self::MAX_PHOTOS_PER_PROJECT.' total).'])
                ->withInput();
        }

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $converter = new ProjectPhotoWebpStorage;
        $savedPaths = [];

        try {
            DB::beginTransaction();

            $project = Project::create([
                'title' => $validated['title'],
                'slug' => $validated['slug'] ?? Project::uniqueSlug($validated['title']),
                'description' => $validated['description'],
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'completed_on' => $validated['completed_on'],
                'town' => $validated['town'] ?? null,
            ]);

            $project->categories()->sync($validated['project_category_ids']);

            $sortOrder = 0;
            foreach ($draftPhotos->adoptPathsForProject((int) $project->id, $draftPaths) as $path) {
                $savedPaths[] = $path;
                $project->photos()->create([
                    'path' => $path,
                    'sort_order' => $sortOrder,
                ]);
                $sortOrder++;
            }

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $file) {
                    $path = $converter->store($file, (int) $project->id);
                    $savedPaths[] = $path;
                    $project->photos()->create([
                        'path' => $path,
                        'sort_order' => $sortOrder,
                    ]);
                    $sortOrder++;
                }
            }

            if ($googleItems !== []) {
                $this->importGooglePickerMedia($request, $project, $converter, $savedPaths, $sortOrder);
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

        $draftPhotos->clear();
        $this->rememberSubmitSucceeded($request);

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
        $this->preparePortfolioSeoRequest($request);

        $validated = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'project_category_ids' => ['required', 'array', 'min:1'],
            'project_category_ids.*' => ['integer', 'distinct', 'exists:project_categories,id'],
            'completed_on' => ['required', 'date'],
            'town' => ['nullable', 'string', 'max:80'],
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
        ], $this->portfolioSeoRules($project)));

        $previousSlug = (string) $project->slug;
        $slug = $validated['slug'] ?? ($previousSlug !== '' ? $previousSlug : Project::uniqueSlug($validated['title'], $project->id));

        DB::transaction(function () use ($project, $validated, $previousSlug, $slug) {
            if ($previousSlug !== $slug) {
                ProjectSlugRedirect::query()
                    ->where('project_id', $project->id)
                    ->where('slug', $slug)
                    ->delete();
            }

            $project->update([
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'],
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'completed_on' => $validated['completed_on'],
                'town' => $validated['town'] ?? null,
            ]);

            if ($previousSlug !== '' && $previousSlug !== $slug) {
                ProjectSlugRedirect::query()->create([
                    'project_id' => $project->id,
                    'slug' => $previousSlug,
                ]);
            }
        });

        $project->categories()->sync($validated['project_category_ids']);

        if (! empty($validated['delete_photos'])) {
            $this->deleteOwnedPhotos($project, $validated['delete_photos']);
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

    public function destroyPhotos(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photo_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('project_photos', 'id')->where(fn ($q) => $q->where('project_id', $project->id)),
            ],
        ]);

        $this->deleteOwnedPhotos($project, $validated['photo_ids']);

        $remaining = $project->photos()->count();

        return response()->json([
            'deleted' => array_map('intval', $validated['photo_ids']),
            'remaining' => $remaining,
            'slots_free' => max(0, self::MAX_PHOTOS_PER_PROJECT - $remaining),
        ]);
    }

    public function labelPhotos(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photo_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('project_photos', 'id')->where(fn ($q) => $q->where('project_id', $project->id)),
            ],
            'label' => ['required', Rule::in(['Before', 'During'])],
        ]);

        $photos = ProjectPhoto::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $validated['photo_ids'])
            ->get();

        $disk = Storage::disk('public');
        foreach ($photos as $photo) {
            if (! $disk->exists($photo->path)) {
                return response()->json(['message' => 'A selected photo file is missing on disk.'], 404);
            }
        }

        $converter = app(ProjectPhotoWebpStorage::class);
        $label = $validated['label'];
        $updated = [];

        try {
            foreach ($photos as $photo) {
                $fullPath = $disk->path($photo->path);
                $this->applyPhotoLabel($fullPath, $label);
                $converter->recompressWebpAtPath($fullPath);
                clearstatcache(true, $fullPath);
                $photo->touch();
                $converter->syncPublicMirror($photo->path);
                $photo->refresh();
                $updated[] = [
                    'id' => $photo->id,
                    'url' => $photo->url(),
                ];
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not add the '.$label.' label: '.$e->getMessage(),
                'photos' => $updated,
            ], 500);
        }

        return response()->json([
            'label' => $label,
            'photos' => $updated,
        ]);
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

        $disk = Storage::disk('public');
        if (! $disk->exists($photo->path)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Photo file is missing on disk.'], 404);
            }

            return back()->with('error', 'Photo file is missing on disk.');
        }

        $fullPath = $disk->path($photo->path);
        $label = $this->photoLabelChoice($request);
        if ($label === false) {
            $msg = 'Choose either a Before label or a During label.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'],
                'label_before' => ['sometimes', 'boolean'],
                'label_during' => ['sometimes', 'boolean'],
            ]);

            try {
                $upload = $request->file('image');
                $realPath = $upload->getRealPath();
                if ($realPath === false || ! is_file($realPath)) {
                    throw new \RuntimeException('Could not read the edited image.');
                }

                $bytes = file_get_contents($realPath);
                if ($bytes === false || $bytes === '') {
                    throw new \RuntimeException('Edited image is empty.');
                }

                $mime = strtolower((string) $upload->getMimeType());
                if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                    $mime = 'image/png';
                }

                $converter = app(ProjectPhotoWebpStorage::class);
                $webp = $converter->convertBinaryToWebp($bytes, $mime, basename($photo->path));
                if (file_put_contents($fullPath, $webp) === false) {
                    throw new \RuntimeException('Could not save the edited photo.');
                }

                $this->applyPhotoLabel($fullPath, $label);

                app(ProjectPhotoWebpStorage::class)->recompressWebpAtPath($fullPath);
                clearstatcache(true, $fullPath);
            } catch (\Throwable $e) {
                report($e);
                $msg = 'Could not save the edited photo: '.$e->getMessage();

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
                ->with('success', $this->photoLabelSuccess('Photo updated', $label));
        }

        $validated = $request->validate([
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'width' => ['required', 'numeric', 'min:1'],
            'height' => ['required', 'numeric', 'min:1'],
            'label_before' => ['sometimes', 'boolean'],
            'label_during' => ['sometimes', 'boolean'],
        ]);

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
            $this->applyPhotoLabel($fullPath, $label);
            app(ProjectPhotoWebpStorage::class)->recompressWebpAtPath($fullPath);
            clearstatcache(true, $fullPath);
        } catch (\Throwable $e) {
            report($e);
            $msg = $label !== null
                ? 'Could not crop or label the photo: '.$e->getMessage()
                : 'Could not crop the photo: '.$e->getMessage();

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

        $success = $this->photoLabelSuccess('Photo cropped', $label);

        return redirect()
            ->route('admin.portfolio.edit', $project)
            ->with('success', $success);
    }

    public function replacePhoto(Request $request, Project $project, ProjectPhoto $photo): JsonResponse
    {
        abort_unless((int) $photo->project_id === (int) $project->id, 404);

        set_time_limit(max(120, (int) config('gemini.timeout_seconds', 120) + 60));

        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'google_picker_items' => ['nullable', 'array', 'max:1'],
            'google_picker_items.*' => ['string', 'max:4000'],
        ]);

        $hasFile = $request->hasFile('image');
        $googleItems = array_values(array_filter(
            (array) ($data['google_picker_items'] ?? []),
            fn ($item) => is_string($item) && $item !== '',
        ));

        if ($hasFile && $googleItems !== []) {
            return response()->json(['error' => 'Choose either an upload or one Google Photo.'], 422);
        }
        if (! $hasFile && $googleItems === []) {
            return response()->json(['error' => 'Upload a photo or pick one from Google Photos.'], 422);
        }
        if (count(GooglePickerItems::parse($googleItems)) > 1) {
            return response()->json(['error' => 'Pick only one Google Photo.'], 422);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($photo->path)) {
            return response()->json(['error' => 'Photo file is missing on disk.'], 404);
        }

        $fullPath = $disk->path($photo->path);
        $tmp = null;

        try {
            if ($hasFile) {
                $upload = $request->file('image');
                $realPath = $upload->getRealPath();
                if ($realPath === false || ! is_file($realPath)) {
                    throw new \RuntimeException('Could not read the uploaded photo.');
                }
                $bytes = file_get_contents($realPath);
                $mime = strtolower((string) $upload->getMimeType());
            } else {
                if (! GooglePhotosToken::query()->where('user_id', $request->user()->id)->exists()) {
                    throw new \RuntimeException('Google Photos is not connected.');
                }
                $item = GooglePickerItems::parse($googleItems)[0] ?? null;
                if (! is_array($item) || empty($item['baseUrl'])) {
                    throw new \RuntimeException('That Google Photo could not be read.');
                }
                [$tmp, $mime] = app(GooglePhotosService::class)->downloadPickedMedia(
                    $request->user(),
                    (string) $item['baseUrl'],
                    isset($item['mimeType']) ? (string) $item['mimeType'] : null,
                );
                $bytes = is_file($tmp) ? file_get_contents($tmp) : false;
            }

            if ($bytes === false || $bytes === '') {
                throw new \RuntimeException('Replacement photo is empty.');
            }

            $mime = strtolower(trim((string) $mime));
            if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                $mime = 'image/jpeg';
            }

            $converter = app(ProjectPhotoWebpStorage::class);
            $webp = $converter->convertBinaryToWebp($bytes, $mime, basename($photo->path));
            if (file_put_contents($fullPath, $webp) === false) {
                throw new \RuntimeException('Could not save the replacement photo.');
            }
            $converter->recompressWebpAtPath($fullPath);
            clearstatcache(true, $fullPath);
            $photo->touch();
            $converter->syncPublicMirror($photo->path);
            $photo->refresh();

            return response()->json([
                'ok' => true,
                'photo_id' => $photo->id,
                'url' => $photo->url(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        } finally {
            if (is_string($tmp) && is_file($tmp)) {
                @unlink($tmp);
            }
        }
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
     * @param  array<int, mixed>  $photoIds
     */
    /**
     * @return 'Before'|'During'|null|false false when both labels are requested
     */
    private function photoLabelChoice(Request $request): string|false|null
    {
        $before = $request->boolean('label_before');
        $during = $request->boolean('label_during');
        if ($before && $during) {
            return false;
        }
        if ($during) {
            return 'During';
        }
        if ($before) {
            return 'Before';
        }

        return null;
    }

    private function applyPhotoLabel(string $fullPath, string|false|null $label): void
    {
        if ($label === 'During') {
            app(ProjectPhotoLabelService::class)->applyDuringLabel($fullPath);

            return;
        }
        if ($label === 'Before') {
            app(ProjectPhotoLabelService::class)->applyBeforeLabel($fullPath);
        }
    }

    private function photoLabelSuccess(string $base, string|false|null $label): string
    {
        if ($label === 'Before' || $label === 'During') {
            return $base.' with '.$label.' label.';
        }

        return $base.'.';
    }

    private function deleteOwnedPhotos(Project $project, array $photoIds): void
    {
        $photos = ProjectPhoto::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $photoIds)
            ->get();

        foreach ($photos as $photo) {
            Storage::disk('public')->delete($photo->path);
            $photo->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function portfolioSeoRules(?Project $project = null): array
    {
        return [
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug')->ignore($project?->id),
                Rule::unique('project_slug_redirects', 'slug')->where(function ($query) use ($project) {
                    if ($project) {
                        $query->where('project_id', '!=', $project->id);
                    }
                }),
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    private function preparePortfolioSeoRequest(Request $request): void
    {
        $slug = trim((string) $request->input('slug', ''));
        $normalized = '';
        if ($slug !== '') {
            $normalized = rtrim(substr(Str::slug($slug), 0, 180), '-');
        }

        $request->merge([
            'slug' => $normalized === '' ? null : $normalized,
            'meta_title' => $this->blankToNull($request->input('meta_title')),
            'meta_description' => $this->blankToNull($request->input('meta_description')),
            'town' => $this->blankToNull($request->input('town')),
        ]);
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
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

    private function respondWithCategories(
        Request $request,
        PortfolioCategoryAiService $categorizer,
        PortfolioDraftPhotoService $draftPhotos,
        ?Project $project,
    ): JsonResponse {
        set_time_limit(max(120, (int) config('gemini.timeout_seconds', 120) + 60));

        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'google_picker_items' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'google_picker_items.*' => ['string', 'max:4000'],
            'draft_photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS_PER_PROJECT],
            'draft_photos.*' => ['string', 'max:500'],
        ]);

        $uploads = array_values(array_filter(
            (array) $request->file('photos', []),
            fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));
        $googleItems = array_values(array_filter(
            (array) ($data['google_picker_items'] ?? []),
            fn ($item) => is_string($item) && $item !== '',
        ));
        $draftPaths = $draftPhotos->validatePaths($data['draft_photos'] ?? []);

        try {
            $result = $categorizer->suggest(
                $request->user(),
                $data['title'],
                (string) ($data['description'] ?? ''),
                $uploads,
                $googleItems,
                $draftPaths,
                $project,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    private function respondWithImprovedDescription(
        Request $request,
        PortfolioDescriptionAiService $describer,
        ?Project $project,
    ): JsonResponse {
        set_time_limit(max(120, (int) config('gemini.timeout_seconds', 120) + 60));

        $maxKb = max(1024, (int) ceil((int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024) / 1024) * 4);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:8000'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'google_picker_items' => ['nullable', 'array', 'max:8'],
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

        try {
            $result = $describer->suggest(
                $request->user(),
                $data['description'],
                $uploads,
                $googleItems,
                $project,
            );

            return response()->json([
                'description' => $result['option_1'],
                'option_1' => $result['option_1'],
                'option_2' => $result['option_2'],
                'image_count' => $result['image_count'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}

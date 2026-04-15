<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPhoto;
use App\Services\ProjectPhotoRotationService;
use App\Services\ProjectPhotoWebpStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const MAX_PHOTOS_PER_PROJECT = 15;

    public function index()
    {
        $projects = Project::query()
            ->with('categories')
            ->withCount('photos')
            ->orderByCompletionLatest()
            ->paginate(15);

        return view('admin.projects.index', compact('projects'));
    }

    public function create()
    {
        $categories = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.projects.create', compact('categories'));
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
        ]);

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
            ->route('admin.projects.index')
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

        return view('admin.projects.edit', compact('project', 'categories'));
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
        ]);

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

        return redirect()
            ->route('admin.projects.edit', $project)
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
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not rotate the photo: '.$e->getMessage());
        }

        $photo->touch();

        return redirect()
            ->route('admin.projects.edit', $project)
            ->with('success', 'Photo rotated.');
    }

    public function destroy(Project $project)
    {
        foreach ($project->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $project->delete();

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project deleted.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::query()
            ->with('category')
            ->withCount('photos')
            ->latest()
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
            'project_category_id' => ['required', 'integer', 'exists:project_categories,id'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:webp', 'max:10240'],
        ]);

        $project = Project::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'project_category_id' => $validated['project_category_id'],
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $file) {
                $path = $file->store("project-photos/{$project->id}", 'public');
                $project->photos()->create([
                    'path' => $path,
                    'sort_order' => $index,
                ]);
            }
        }

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        $project->load(['photos' => fn ($q) => $q->orderBy('sort_order')]);

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
            'project_category_id' => ['required', 'integer', 'exists:project_categories,id'],
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
            'project_category_id' => $validated['project_category_id'],
        ]);

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

        $slots = 10 - $project->photos()->count();

        $request->validate([
            'photos' => ['nullable', 'array', 'max:'.max(0, $slots)],
            'photos.*' => ['file', 'mimes:webp', 'max:10240'],
        ]);

        if ($slots > 0 && $request->hasFile('photos')) {
            $files = array_slice($request->file('photos'), 0, $slots);
            $maxOrder = (int) $project->photos()->max('sort_order');
            foreach ($files as $file) {
                $path = $file->store("project-photos/{$project->id}", 'public');
                $maxOrder++;
                $project->photos()->create([
                    'path' => $path,
                    'sort_order' => $maxOrder,
                ]);
            }
        }

        return redirect()
            ->route('admin.projects.edit', $project)
            ->with('success', 'Project updated.');
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

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSlugRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->with([
                'photos' => fn ($q) => $q->orderBy('sort_order'),
                'categories',
            ])
            ->orderByCompletionLatest()
            ->get();

        return view('site.portfolio.index', compact('projects'));
    }

    public function show(string $slug): View|RedirectResponse
    {
        $project = Project::query()
            ->where('slug', $slug)
            ->with([
                'photos' => fn ($q) => $q->orderBy('sort_order'),
                'categories',
            ])
            ->first();

        if (! $project) {
            $redirect = ProjectSlugRedirect::query()
                ->where('slug', $slug)
                ->with('project')
                ->first();

            if ($redirect?->project && $redirect->project->slug) {
                return redirect()->to($redirect->project->publicUrl(), 301);
            }

            abort(404);
        }

        $categoryIds = $project->categories->pluck('id');
        $related = Project::query()
            ->where('id', '!=', $project->id)
            ->when($project->isInServiceArea(), fn ($query) => $query->inServiceArea())
            ->when($categoryIds->isNotEmpty(), function ($query) use ($categoryIds) {
                $query->whereHas(
                    'categories',
                    fn ($categories) => $categories->whereIn('project_categories.id', $categoryIds)
                );
            })
            ->with([
                'photos' => fn ($q) => $q->orderBy('sort_order'),
                'categories',
            ])
            ->orderByCompletionLatest()
            ->limit(4)
            ->get();

        if ($related->isEmpty()) {
            $related = Project::query()
                ->where('id', '!=', $project->id)
                ->when($project->isInServiceArea(), fn ($query) => $query->inServiceArea())
                ->with([
                    'photos' => fn ($q) => $q->orderBy('sort_order'),
                    'categories',
                ])
                ->orderByCompletionLatest()
                ->limit(4)
                ->get();
        }

        return view('site.portfolio.show', compact('project', 'related'));
    }
}

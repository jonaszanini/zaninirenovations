<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Support\AdminPaginatedList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogCategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = AdminPaginatedList::perPage($request);

        $query = BlogCategory::query()
            ->withCount('posts')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name');

        $categories = AdminPaginatedList::paginateOrAll($query, $perPage);

        return view('admin.blog.categories.index', compact('categories', 'q', 'perPage'));
    }

    public function create()
    {
        return view('admin.blog.categories.form', ['category' => new BlogCategory]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:blog_categories,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $slug = filled($data['slug'] ?? null)
            ? $data['slug']
            : BlogCategory::uniqueSlug($data['name']);

        BlogCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('admin.blog.categories.index')->with('success', 'Category created.');
    }

    public function edit(BlogCategory $category)
    {
        return view('admin.blog.categories.form', compact('category'));
    }

    public function update(Request $request, BlogCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $category->update($data);

        return redirect()->route('admin.blog.categories.index')->with('success', 'Category saved.');
    }

    public function destroy(BlogCategory $category)
    {
        $category->delete();

        return redirect()->route('admin.blog.categories.index')->with('success', 'Category deleted.');
    }
}

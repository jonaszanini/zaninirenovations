<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogCategoryController extends Controller
{
    public function index()
    {
        $categories = BlogCategory::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return view('admin.blog.categories.index', compact('categories'));
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlogTagController extends Controller
{
    public function index()
    {
        $tags = BlogTag::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return view('admin.blog.tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.blog.tags.form', ['tag' => new BlogTag]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:blog_tags,slug'],
        ]);

        $slug = filled($data['slug'] ?? null)
            ? $data['slug']
            : BlogTag::uniqueSlug($data['name']);

        BlogTag::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        return redirect()->route('admin.blog.tags.index')->with('success', 'Tag created.');
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'names' => ['required', 'string'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', $data['names']) ?: [];

        $created = 0;
        $skipped = 0;
        $seen = [];

        foreach ($lines as $line) {
            $name = trim($line);
            if ($name === '' || mb_strlen($name) > 80) {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                $skipped++;
                continue;
            }
            $seen[$key] = true;

            $exists = BlogTag::query()
                ->whereRaw('LOWER(name) = ?', [$key])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            BlogTag::create([
                'name' => $name,
                'slug' => BlogTag::uniqueSlug($name),
            ]);
            $created++;
        }

        return redirect()->route('admin.blog.tags.index')
            ->with('success', "{$created} tag(s) added, {$skipped} skipped (already exist or duplicate).");
    }

    public function edit(BlogTag $tag)
    {
        return view('admin.blog.tags.form', compact('tag'));
    }

    public function update(Request $request, BlogTag $tag)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_tags', 'slug')->ignore($tag->id),
            ],
        ]);

        $tag->update($data);

        return redirect()->route('admin.blog.tags.index')->with('success', 'Tag saved.');
    }

    public function destroy(BlogTag $tag)
    {
        $tag->delete();

        return redirect()->route('admin.blog.tags.index')->with('success', 'Tag deleted.');
    }
}

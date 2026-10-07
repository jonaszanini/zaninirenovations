<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\BlogHtmlContentProcessor;
use App\Services\BlogImageStorage;
use App\Services\BlogPostMetadataAiService;
use App\Support\Logging\AppLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogPostController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $posts = BlogPost::query()
            ->with(['categories:id,name', 'tags:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%')
                        ->orWhere('excerpt', 'like', '%'.$q.'%')
                        ->orWhere('html_content', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog.posts.index', compact('posts', 'q'));
    }

    public function create()
    {
        return view('admin.blog.posts.form', [
            'post' => new BlogPost([
                'status' => BlogPost::STATUS_DRAFT,
                'author_name' => config('blog.author_name'),
            ]),
            'categories' => BlogCategory::query()->orderBy('name')->get(),
            'tags' => BlogTag::query()->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'tagNames' => '',
        ]);
    }

    public function store(Request $request, BlogHtmlContentProcessor $htmlProcessor, BlogImageStorage $images)
    {
        $data = $this->validatedPost($request);
        $categoryIds = $data['category_ids'];
        $tagNames = $data['tag_names'];
        unset($data['category_ids'], $data['tag_names'], $data['featured_image'], $data['remove_featured_image']);
        unset($data['crop_x'], $data['crop_y'], $data['crop_width'], $data['crop_height']);

        $post = BlogPost::create($data);

        $post->categories()->sync($categoryIds);
        $post->tags()->sync(BlogTag::syncFromNames($tagNames));

        $this->adoptPendingFeaturedIfNeeded($request, $post, $images);
        $this->handleFeaturedUpload($request, $post, $images);
        $post->update([
            'html_content' => $htmlProcessor->persistInlineImages((string) $request->input('html_content', ''), $post->id),
        ]);

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', 'Post created.');
    }

    public function edit(BlogPost $post)
    {
        $post->load(['categories', 'tags']);

        return view('admin.blog.posts.form', [
            'post' => $post,
            'categories' => BlogCategory::query()->orderBy('name')->get(),
            'tags' => BlogTag::query()->orderBy('name')->get(),
            'selectedCategoryIds' => $post->categories->pluck('id')->all(),
            'tagNames' => $post->tags->pluck('name')->implode(', '),
        ]);
    }

    public function update(Request $request, BlogPost $post, BlogHtmlContentProcessor $htmlProcessor, BlogImageStorage $images)
    {
        $data = $this->validatedPost($request, $post);
        $categoryIds = $data['category_ids'];
        $tagNames = $data['tag_names'];
        unset($data['category_ids'], $data['tag_names'], $data['featured_image'], $data['remove_featured_image']);
        unset($data['crop_x'], $data['crop_y'], $data['crop_width'], $data['crop_height']);

        $data['html_content'] = $htmlProcessor->persistInlineImages((string) $request->input('html_content', ''), $post->id);

        $post->update($data);
        $post->categories()->sync($categoryIds);
        $post->tags()->sync(BlogTag::syncFromNames($tagNames));

        $this->adoptPendingFeaturedIfNeeded($request, $post, $images);
        $this->handleFeaturedUpload($request, $post, $images);

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', 'Post saved.');
    }

    public function destroy(BlogPost $post, BlogImageStorage $images)
    {
        if ($post->featured_image_path) {
            $images->delete($post->featured_image_path);
        }
        $post->delete();

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'Post deleted.');
    }

    public function generateAiMetadata(Request $request, BlogPostMetadataAiService $metadataAi): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'html_content' => ['required', 'string', 'max:500000'],
            'post_id' => ['nullable', 'integer', 'exists:blog_posts,id'],
        ]);

        try {
            $postId = isset($data['post_id']) ? (int) $data['post_id'] : null;
            $result = $metadataAi->suggestWithFeaturedImage($data['title'], $data['html_content'], $postId);

            return response()->json($result);
        } catch (\Throwable $e) {
            AppLogger::error('blog.ai_metadata.failed', [
                'post_id' => $data['post_id'] ?? null,
            ], $e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function uploadImage(Request $request, BlogImageStorage $images): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'post_id' => ['nullable', 'integer', 'exists:blog_posts,id'],
        ]);

        $postId = (int) ($request->input('post_id') ?: 0);
        $relative = $postId > 0
            ? $images->storeInline($request->file('file'), $postId)
            : $images->storeTempInline($request->file('file'));

        return response()->json([
            'location' => $images->publicUrl($relative),
        ]);
    }

    protected function validatedPost(Request $request, ?BlogPost $post = null): array
    {
        $postId = $post?->id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($postId),
            ],
            'excerpt' => ['nullable', 'string', 'max:'.BlogPost::EXCERPT_MAX],
            'html_content' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'ai_image_prompt' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'author_name' => ['required', 'string', 'max:120'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:blog_categories,id'],
            'tag_names' => ['nullable', 'string', 'max:2000'],
            'featured_image' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'crop_x' => ['nullable', 'integer', 'min:0'],
            'crop_y' => ['nullable', 'integer', 'min:0'],
            'crop_width' => ['nullable', 'integer', 'min:1'],
            'crop_height' => ['nullable', 'integer', 'min:1'],
        ]);

        if (blank($data['slug'] ?? null)) {
            $data['slug'] = BlogPost::uniqueSlug($data['title'], $postId);
        }

        if (blank($data['excerpt'] ?? null)) {
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($data['html_content'] ?? ''))));
            $data['excerpt'] = Str::limit($plain, BlogPost::EXCERPT_MAX);
        }

        // datetime-local submits "" when empty; MySQL rejects that for timestamp columns.
        if (! filled($data['published_at'] ?? null)) {
            $data['published_at'] = $data['status'] === BlogPost::STATUS_PUBLISHED ? now() : null;
        }

        $data['category_ids'] = array_values(array_unique(array_map('intval', $data['category_ids'] ?? [])));
        $data['tag_names'] = $this->parseTagNames($data['tag_names'] ?? '');

        return $data;
    }

    /** @return list<string> */
    protected function parseTagNames(string $raw): array
    {
        $parts = preg_split('/[,]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($t) => $t !== ''));
    }

    protected function adoptPendingFeaturedIfNeeded(Request $request, BlogPost $post, BlogImageStorage $images): void
    {
        $pending = trim((string) $request->input('pending_featured_image_path', ''));
        if ($pending === '' || $request->hasFile('featured_image')) {
            return;
        }

        if ($post->featured_image_path && $post->featured_image_path !== $pending) {
            $images->delete($post->featured_image_path);
        }

        try {
            $path = $images->adoptPendingFeatured($pending, $post->id);
            $post->update(['featured_image_path' => $path]);
        } catch (\Throwable $e) {
            AppLogger::warning('blog.featured_image.adopt_pending_failed', [
                'post_id' => $post->id,
                'pending_path' => $pending,
            ], $e);
        }
    }

    protected function handleFeaturedUpload(Request $request, BlogPost $post, BlogImageStorage $images): void
    {
        if ($request->boolean('remove_featured_image') && $post->featured_image_path) {
            $images->delete($post->featured_image_path);
            $post->update(['featured_image_path' => null]);
        }

        if (! $request->hasFile('featured_image')) {
            if (
                $request->boolean('featured_crop_applies')
                && $request->filled(['crop_x', 'crop_y', 'crop_width', 'crop_height'])
                && $post->featured_image_path
            ) {
                $images->applyCrop($post->featured_image_path, [
                    'x' => (int) $request->input('crop_x'),
                    'y' => (int) $request->input('crop_y'),
                    'width' => (int) $request->input('crop_width'),
                    'height' => (int) $request->input('crop_height'),
                ]);
            }

            return;
        }

        if ($post->featured_image_path) {
            $images->delete($post->featured_image_path);
        }

        $crop = null;
        if ($request->filled(['crop_x', 'crop_y', 'crop_width', 'crop_height'])) {
            $crop = [
                'x' => (int) $request->input('crop_x'),
                'y' => (int) $request->input('crop_y'),
                'width' => (int) $request->input('crop_width'),
                'height' => (int) $request->input('crop_height'),
            ];
        }

        $path = $images->storeFeatured($request->file('featured_image'), $post->id, $crop);
        $post->update(['featured_image_path' => $path]);
    }
}

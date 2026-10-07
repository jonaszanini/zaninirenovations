<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostSlugRedirect;
use App\Models\BlogTag;
use App\Services\BlogCommentCaptcha;
use App\Services\BlogHtmlContentProcessor;
use App\Services\BlogImageStorage;
use App\Services\BlogPostMetadataAiService;
use App\Services\BlogPostQuality;
use App\Support\Logging\AppLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', 'all');
        if (! in_array($status, ['all', 'draft', 'published', 'scheduled'], true)) {
            $status = 'all';
        }
        $categoryId = (int) $request->get('category', 0);
        $sort = (string) $request->get('sort', 'updated');
        $sortMap = [
            'updated' => ['updated_at', 'desc'],
            'published' => ['published_at', 'desc'],
            'title' => ['title', 'asc'],
            'views' => ['views_count', 'desc'],
        ];
        if (! isset($sortMap[$sort])) {
            $sort = 'updated';
        }

        $applyFilters = function ($query) use ($q, $categoryId) {
            $query->when($q !== '', function ($inner) use ($q) {
                $inner->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%')
                        ->orWhere('excerpt', 'like', '%'.$q.'%')
                        ->orWhere('html_content', 'like', '%'.$q.'%');
                });
            })->when($categoryId > 0, function ($inner) use ($categoryId) {
                $inner->whereHas('categories', fn ($c) => $c->where('blog_categories.id', $categoryId));
            });
        };

        $countQuery = BlogPost::query();
        $applyFilters($countQuery);

        $counts = [
            'all' => (clone $countQuery)->count(),
            'draft' => (clone $countQuery)->where('status', BlogPost::STATUS_DRAFT)->count(),
            'published' => (clone $countQuery)->published()->count(),
            'scheduled' => (clone $countQuery)->scheduled()->count(),
        ];

        [$sortCol, $sortDir] = $sortMap[$sort];

        $posts = BlogPost::query()
            ->with(['categories:id,name', 'tags:id,name'])
            ->withCount([
                'comments',
                'comments as pending_comments_count' => fn ($c) => $c->where('is_approved', false),
            ])
            ->tap($applyFilters)
            ->when($status === 'draft', fn ($q) => $q->where('status', BlogPost::STATUS_DRAFT))
            ->when($status === 'published', fn ($q) => $q->published())
            ->when($status === 'scheduled', fn ($q) => $q->scheduled())
            ->orderBy($sortCol, $sortDir)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $categories = BlogCategory::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.blog.posts.index', compact(
            'posts',
            'q',
            'status',
            'categoryId',
            'sort',
            'counts',
            'categories'
        ));
    }

    public function create(): View
    {
        return view('admin.blog.posts.form', $this->formData(new BlogPost([
            'status' => BlogPost::STATUS_DRAFT,
            'author_name' => config('blog.author_name'),
        ])));
    }

    public function store(Request $request, BlogHtmlContentProcessor $htmlProcessor, BlogImageStorage $images): RedirectResponse|JsonResponse
    {
        $data = $this->validatedPost($request);
        $post = $this->persistPost($request, new BlogPost, $data, $htmlProcessor, $images);

        if ($request->expectsJson() || $request->boolean('autosave')) {
            return $this->autosaveResponse($post, 'Post created.');
        }

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', $this->savedMessage($post, $request));
    }

    public function edit(BlogPost $post): View
    {
        $post->load(['categories', 'tags']);

        return view('admin.blog.posts.form', $this->formData($post));
    }

    public function update(Request $request, BlogPost $post, BlogHtmlContentProcessor $htmlProcessor, BlogImageStorage $images): RedirectResponse|JsonResponse
    {
        $previousSlug = (string) $post->slug;
        $wasPublished = $post->status === BlogPost::STATUS_PUBLISHED;
        $data = $this->validatedPost($request, $post);
        $this->persistPost($request, $post, $data, $htmlProcessor, $images, $previousSlug, $wasPublished);

        if ($request->expectsJson() || $request->boolean('autosave')) {
            return $this->autosaveResponse($post->fresh(), 'Saved.');
        }

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', $this->savedMessage($post, $request));
    }

    public function destroy(BlogPost $post, BlogImageStorage $images): RedirectResponse
    {
        if ($post->featured_image_path) {
            $images->delete($post->featured_image_path);
        }
        $post->delete();

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'Post deleted.');
    }

    public function duplicate(BlogPost $post, BlogImageStorage $images): RedirectResponse
    {
        $copy = DB::transaction(function () use ($post, $images) {
            $copy = $post->replicate([
                'slug',
                'views_count',
                'published_at',
                'status',
            ]);
            $copy->title = trim($post->title.' (copy)');
            $copy->slug = BlogPost::uniqueSlug($copy->title);
            $copy->status = BlogPost::STATUS_DRAFT;
            $copy->published_at = null;
            $copy->views_count = 0;
            $copy->featured_image_path = null;
            $copy->save();

            $copy->categories()->sync($post->categories()->pluck('blog_categories.id')->all());
            $copy->tags()->sync($post->tags()->pluck('blog_tags.id')->all());

            if (filled($post->featured_image_path) && $images->publicUrl($post->featured_image_path)) {
                $absolute = \Illuminate\Support\Facades\Storage::disk('public')->path($post->featured_image_path);
                if (is_file($absolute)) {
                    $bytes = file_get_contents($absolute);
                    if (is_string($bytes) && $bytes !== '') {
                        $copy->update([
                            'featured_image_path' => $images->storeFeaturedFromBinary($bytes, 'image/webp', $copy->id),
                        ]);
                    }
                }
            }

            return $copy;
        });

        return redirect()
            ->route('admin.blog.posts.edit', $copy)
            ->with('success', 'Draft copy created.');
    }

    public function preview(BlogPost $post, BlogHtmlContentProcessor $htmlProcessor, BlogCommentCaptcha $captcha): View
    {
        $post->load(['categories', 'tags']);

        $approvedComments = $post->approvedComments()
            ->whereNull('parent_id')
            ->with(['approvedReplies'])
            ->orderBy('created_at')
            ->get();

        $relatedPosts = collect();
        $relatedProjects = \App\Support\BlogPublicContent::relatedProjects($post);
        $cta = \App\Support\BlogPublicContent::cta($post);
        $prevPost = null;
        $nextPost = null;
        $recentPosts = collect();
        $captchaMode = $captcha->mode();
        $captchaChallenge = $captchaMode === 'math' ? $captcha->generateMathChallenge() : null;
        $isPreview = true;

        return view('site.blog.show', compact(
            'post',
            'approvedComments',
            'relatedPosts',
            'relatedProjects',
            'cta',
            'prevPost',
            'nextPost',
            'recentPosts',
            'htmlProcessor',
            'captchaMode',
            'captchaChallenge',
            'isPreview'
        ));
    }

    public function autosave(Request $request, BlogHtmlContentProcessor $htmlProcessor, BlogImageStorage $images, ?BlogPost $post = null): JsonResponse
    {
        $request->merge(['autosave' => true]);

        if ($post && $post->exists) {
            $previousSlug = (string) $post->slug;
            $wasPublished = $post->status === BlogPost::STATUS_PUBLISHED;
            $data = $this->validatedPost($request, $post, autosave: true);
            $this->persistPost($request, $post, $data, $htmlProcessor, $images, $previousSlug, $wasPublished);

            return $this->autosaveResponse($post->fresh());
        }

        $data = $this->validatedPost($request, null, autosave: true);
        $created = $this->persistPost($request, new BlogPost, $data, $htmlProcessor, $images);

        return $this->autosaveResponse($created);
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

    public function suggestAiMetadata(Request $request, BlogPostMetadataAiService $metadataAi): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'html_content' => ['required', 'string', 'max:500000'],
        ]);

        try {
            return response()->json($metadataAi->suggest($data['title'], $data['html_content']));
        } catch (\Throwable $e) {
            AppLogger::error('blog.ai_suggest.failed', [], $e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function generateAiImage(Request $request, BlogPostMetadataAiService $metadataAi): JsonResponse
    {
        $data = $request->validate([
            'image_prompt' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $metadataAi->generateFeaturedImage($data['image_prompt']);
            if (! empty($result['image_generation_error']) && empty($result['featured_image_url'])) {
                return response()->json([
                    'error' => $result['image_generation_error'],
                ], 502);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            AppLogger::error('blog.ai_image.failed', [], $e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function checkContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'html_content' => ['nullable', 'string', 'max:500000'],
            'excerpt' => ['nullable', 'string', 'max:'.BlogPost::EXCERPT_MAX],
            'seo_title' => ['nullable', 'string', 'max:'.BlogPost::SEO_TITLE_MAX],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'featured_image' => ['nullable', 'boolean'],
            'featured_image_alt' => ['nullable', 'string', 'max:'.BlogPost::FEATURED_ALT_MAX],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['nullable'],
        ]);

        $html = (string) ($data['html_content'] ?? '');

        return response()->json(BlogPostQuality::checklist([
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'html_content' => $html,
            'excerpt' => $data['excerpt'] ?? '',
            'seo_title' => $data['seo_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'featured_image' => (bool) ($data['featured_image'] ?? false),
            'featured_image_alt' => $data['featured_image_alt'] ?? '',
            'category_ids' => $data['category_ids'] ?? [],
        ]));
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

    /**
     * @return array<string, mixed>
     */
    protected function formData(BlogPost $post): array
    {
        return [
            'post' => $post,
            'categories' => BlogCategory::query()->orderBy('name')->get(),
            'tags' => BlogTag::query()->orderBy('name')->get(['id', 'name']),
            'selectedCategoryIds' => $post->exists
                ? $post->categories->pluck('id')->all()
                : [],
            'tagNames' => $post->exists
                ? $post->tags->pluck('name')->implode(', ')
                : '',
            'authorOptions' => BlogPost::authorOptions(),
            'ctaServices' => config('blog.cta_services', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistPost(
        Request $request,
        BlogPost $post,
        array $data,
        BlogHtmlContentProcessor $htmlProcessor,
        BlogImageStorage $images,
        ?string $previousSlug = null,
        bool $wasPublished = false,
    ): BlogPost {
        $categoryIds = $data['category_ids'];
        $tagNames = $data['tag_names'];
        unset($data['category_ids'], $data['tag_names'], $data['featured_image'], $data['remove_featured_image']);
        unset($data['crop_x'], $data['crop_y'], $data['crop_width'], $data['crop_height']);

        $newSlug = (string) ($data['slug'] ?? '');

        if ($post->exists) {
            $data['html_content'] = $htmlProcessor->persistInlineImages((string) $request->input('html_content', ''), $post->id);
            $post->fill($data);
            $post->save();
            if ($wasPublished && $previousSlug && $previousSlug !== $newSlug) {
                BlogPostSlugRedirect::recordChange($post, $previousSlug, $newSlug);
            }
        } else {
            $created = BlogPost::create($data);
            $created->update([
                'html_content' => $htmlProcessor->persistInlineImages((string) $request->input('html_content', ''), $created->id),
            ]);
            $post = $created;
        }

        $post->categories()->sync($categoryIds);
        $post->tags()->sync(BlogTag::syncFromNames($tagNames));

        $this->adoptPendingFeaturedIfNeeded($request, $post, $images);
        $this->handleFeaturedUpload($request, $post, $images);

        return $post->fresh() ?? $post;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPost(Request $request, ?BlogPost $post = null, bool $autosave = false): array
    {
        $postId = $post?->id;
        $autosave = $autosave || $request->boolean('autosave');

        $this->applyIntent($request, $post, $autosave);

        $data = $request->validate([
            'title' => [$autosave ? 'nullable' : 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($postId),
                Rule::unique('blog_post_slug_redirects', 'slug')->where(
                    fn ($q) => $postId ? $q->where('blog_post_id', '!=', $postId) : $q
                ),
            ],
            'excerpt' => ['nullable', 'string', 'max:'.BlogPost::EXCERPT_MAX],
            'html_content' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'seo_title' => ['nullable', 'string', 'max:'.BlogPost::SEO_TITLE_MAX],
            'featured_image_alt' => ['nullable', 'string', 'max:'.BlogPost::FEATURED_ALT_MAX],
            'cta_service' => ['nullable', 'string', 'max:80', Rule::in(array_merge([''], array_keys(config('blog.cta_services', []))))],
            'noindex' => ['nullable', 'boolean'],
            'ai_image_prompt' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'author_name' => [$autosave ? 'nullable' : 'required', 'string', 'max:120'],
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

        if (blank($data['title'] ?? null)) {
            $data['title'] = $autosave ? 'Untitled draft' : $data['title'];
        }

        if (blank($data['author_name'] ?? null)) {
            $data['author_name'] = (string) config('blog.author_name');
        }

        if (blank($data['slug'] ?? null)) {
            $data['slug'] = BlogPost::uniqueSlug((string) $data['title'], $postId);
        } else {
            $data['slug'] = BlogPost::slugify((string) $data['slug']);
            if (BlogPost::slugIsTaken($data['slug'], $postId)) {
                $data['slug'] = BlogPost::uniqueSlug($data['slug'], $postId);
            }
        }

        if (blank($data['excerpt'] ?? null) && ! $autosave) {
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($data['html_content'] ?? ''))));
            $data['excerpt'] = Str::limit($plain, BlogPost::EXCERPT_MAX);
        }

        if (! filled($data['published_at'] ?? null)) {
            $data['published_at'] = $data['status'] === BlogPost::STATUS_PUBLISHED ? now() : null;
        }

        $data['noindex'] = $request->boolean('noindex');
        $data['cta_service'] = filled($data['cta_service'] ?? null) ? $data['cta_service'] : null;
        $data['seo_title'] = filled($data['seo_title'] ?? null) ? trim((string) $data['seo_title']) : null;
        $data['featured_image_alt'] = filled($data['featured_image_alt'] ?? null) ? trim((string) $data['featured_image_alt']) : null;
        $data['category_ids'] = array_values(array_unique(array_map('intval', $data['category_ids'] ?? [])));
        $data['tag_names'] = $this->parseTagNames($data['tag_names'] ?? '');

        return $data;
    }

    protected function applyIntent(Request $request, ?BlogPost $post, bool $autosave): void
    {
        $intent = (string) $request->input('intent', 'save');

        if ($autosave) {
            if (! $request->filled('status')) {
                $request->merge([
                    'status' => $post?->status ?: BlogPost::STATUS_DRAFT,
                ]);
            }
            if (! $request->filled('published_at') && $post?->published_at) {
                $request->merge([
                    'published_at' => $post->published_at->format('Y-m-d H:i:s'),
                ]);
            }

            return;
        }

        if ($intent === 'publish') {
            $request->merge([
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => now()->format('Y-m-d\TH:i'),
            ]);
        } elseif ($intent === 'schedule') {
            $request->merge(['status' => BlogPost::STATUS_PUBLISHED]);
        } elseif ($intent === 'draft') {
            $request->merge(['status' => BlogPost::STATUS_DRAFT]);
        }
    }

    /** @return list<string> */
    protected function parseTagNames(string $raw): array
    {
        $parts = preg_split('/[,]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($t) => $t !== ''));
    }

    protected function savedMessage(BlogPost $post, Request $request): string
    {
        $intent = (string) $request->input('intent', 'save');
        if ($intent === 'publish') {
            return 'Post published.';
        }
        if ($intent === 'schedule' || $post->isScheduled()) {
            return 'Post scheduled.';
        }
        if ($intent === 'draft') {
            return 'Saved as draft.';
        }

        return $post->exists ? 'Post saved.' : 'Post created.';
    }

    protected function autosaveResponse(BlogPost $post, string $message = 'Saved.'): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'id' => $post->id,
            'slug' => $post->slug,
            'status' => $post->status,
            'saved_at' => optional($post->updated_at)?->toIso8601String(),
            'edit_url' => route('admin.blog.posts.edit', $post),
            'preview_url' => route('admin.blog.posts.preview', $post),
            'autosave_url' => route('admin.blog.posts.autosave.update', $post),
            'message' => $message,
        ]);
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

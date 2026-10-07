<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\BlogCommentCaptcha;
use App\Services\BlogHtmlContentProcessor;
use App\Support\BlogPublicContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request, BlogHtmlContentProcessor $htmlProcessor): View
    {
        $q = trim((string) $request->get('q', ''));
        $categorySlug = trim((string) $request->get('category', ''));
        $tagSlug = trim((string) $request->get('tag', ''));

        $postsQuery = BlogPost::query()
            ->published()
            ->with(['categories:id,name,slug', 'tags:id,name,slug'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', '%'.$q.'%')
                        ->orWhere('excerpt', 'like', '%'.$q.'%')
                        ->orWhere('html_content', 'like', '%'.$q.'%')
                        ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->when($categorySlug !== '', function ($query) use ($categorySlug) {
                $query->whereHas('categories', fn ($c) => $c->where('slug', $categorySlug));
            })
            ->when($tagSlug !== '', function ($query) use ($tagSlug) {
                $query->whereHas('tags', fn ($t) => $t->where('slug', $tagSlug));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $posts = $postsQuery->paginate(9)->withQueryString();

        if ($posts->currentPage() > max(1, $posts->lastPage())) {
            abort(404);
        }
        if (($categorySlug !== '' || $tagSlug !== '') && $posts->total() === 0) {
            abort(404);
        }

        $featuredPost = BlogPost::query()
            ->published()
            ->with(['categories', 'tags'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        $recentPosts = BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'published_at', 'featured_image_path']);

        $popularPosts = BlogPost::query()
            ->published()
            ->orderByDesc('views_count')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'views_count', 'featured_image_path']);

        $categories = BlogCategory::query()
            ->whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts as published_posts_count' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        $tagCloud = BlogTag::query()
            ->whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts as published_posts_count' => fn ($q) => $q->published()])
            ->orderByDesc('published_posts_count')
            ->limit(20)
            ->get();

        $publishedCount = BlogPost::query()->published()->count();
        $minPosts = max(1, (int) config('seo.blog_filter_index_min_posts', 8));
        $isSearch = $q !== '';
        $isTaxonomyFilter = $categorySlug !== '' || $tagSlug !== '';
        $blogRobots = 'index,follow';
        if ($isSearch || ($isTaxonomyFilter && $publishedCount < $minPosts)) {
            $blogRobots = 'noindex, follow';
        }

        return view('site.blog.index', compact(
            'posts',
            'featuredPost',
            'recentPosts',
            'popularPosts',
            'categories',
            'tagCloud',
            'q',
            'categorySlug',
            'tagSlug',
            'htmlProcessor',
            'blogRobots'
        ));
    }

    public function show(BlogPost $post, BlogHtmlContentProcessor $htmlProcessor, BlogCommentCaptcha $captcha): View
    {
        if (! $post->isPublishedNow()) {
            abort(404);
        }

        $post->load(['categories', 'tags']);
        $post->incrementViews();

        $approvedComments = $post->approvedComments()
            ->whereNull('parent_id')
            ->with(['approvedReplies'])
            ->orderBy('created_at')
            ->get();

        $relatedPosts = BlogPost::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->when($post->categories->isNotEmpty(), function ($q) use ($post) {
                $q->whereHas('categories', fn ($c) => $c->whereIn('blog_categories.id', $post->categories->pluck('id')));
            })
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $prevPost = BlogPost::query()
            ->published()
            ->where('published_at', '<', $post->published_at ?? $post->created_at)
            ->orderByDesc('published_at')
            ->first();

        $nextPost = BlogPost::query()
            ->published()
            ->where('published_at', '>', $post->published_at ?? $post->created_at)
            ->orderBy('published_at')
            ->first();

        $recentPosts = BlogPost::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        $captchaMode = $captcha->mode();
        $captchaChallenge = $captchaMode === 'math' ? $captcha->generateMathChallenge() : null;

        $isPreview = false;
        $relatedProjects = BlogPublicContent::relatedProjects($post);
        $cta = BlogPublicContent::cta($post);

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
}

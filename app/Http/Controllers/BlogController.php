<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\BlogCommentCaptcha;
use App\Services\BlogHtmlContentProcessor;
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
            'htmlProcessor'
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

        return view('site.blog.show', compact(
            'post',
            'approvedComments',
            'relatedPosts',
            'prevPost',
            'nextPost',
            'recentPosts',
            'htmlProcessor',
            'captchaMode',
            'captchaChallenge'
        ));
    }
}

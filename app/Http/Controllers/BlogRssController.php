<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;

class BlogRssController extends Controller
{
    public function index(): Response
    {
        $posts = BlogPost::query()
            ->indexable()
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('site.blog.rss', compact('posts'), 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}

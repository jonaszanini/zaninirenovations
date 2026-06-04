<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Services\BlogCommentCaptcha;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    public function store(Request $request, BlogPost $post, BlogCommentCaptcha $captcha)
    {
        if (! $post->isPublishedNow()) {
            abort(404);
        }

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:120'],
            'author_email' => ['required', 'email', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:blog_comments,id'],
            'captcha_answer' => ['nullable', 'integer'],
            'captcha_token' => ['nullable', 'string', 'max:64'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ]);

        if (! $captcha->validate($request)) {
            return back()
                ->withInput()
                ->with('error', 'Anti-spam verification failed. Please try again.');
        }

        if (! empty($data['parent_id'])) {
            $parent = BlogComment::query()->find($data['parent_id']);
            if (! $parent || (int) $parent->blog_post_id !== (int) $post->id || ! $parent->is_approved) {
                return back()->withInput()->with('error', 'Invalid reply target.');
            }
        }

        BlogComment::create([
            'blog_post_id' => $post->id,
            'parent_id' => $data['parent_id'] ?? null,
            'author_name' => $data['author_name'],
            'author_email' => $data['author_email'],
            'body' => $data['body'],
            'is_approved' => false,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Thank you! Your comment was submitted and will appear after approval.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $q = trim((string) $request->get('q', ''));

        $comments = BlogComment::query()
            ->with(['post:id,title,slug', 'parent:id,author_name'])
            ->when($status === 'pending', fn ($query) => $query->where('is_approved', false))
            ->when($status === 'approved', fn ($query) => $query->where('is_approved', true))
            ->when(! in_array($status, ['pending', 'approved', 'all'], true), fn ($query) => $query->where('is_approved', false))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('author_name', 'like', '%'.$q.'%')
                        ->orWhere('author_email', 'like', '%'.$q.'%')
                        ->orWhere('body', 'like', '%'.$q.'%')
                        ->orWhereHas('post', fn ($p) => $p->where('title', 'like', '%'.$q.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'pending' => BlogComment::query()->where('is_approved', false)->count(),
            'approved' => BlogComment::query()->where('is_approved', true)->count(),
            'all' => BlogComment::query()->count(),
        ];

        return view('admin.blog.comments.index', compact('comments', 'status', 'q', 'counts'));
    }

    public function approve(BlogComment $comment)
    {
        $comment->update(['is_approved' => true]);

        return back()->with('success', 'Comment approved.');
    }

    public function destroy(BlogComment $comment)
    {
        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}

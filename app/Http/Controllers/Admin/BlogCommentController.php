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

        $comments = BlogComment::query()
            ->with(['post:id,title,slug', 'parent:id,author_name'])
            ->when($status === 'pending', fn ($q) => $q->where('is_approved', false))
            ->when($status === 'approved', fn ($q) => $q->where('is_approved', true))
            ->when(! in_array($status, ['pending', 'approved', 'all'], true), fn ($q) => $q->where('is_approved', false))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.blog.comments.index', compact('comments', 'status'));
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

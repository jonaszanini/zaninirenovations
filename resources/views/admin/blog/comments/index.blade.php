@extends('admin.layouts.app')

@section('heading', 'Blog comments')

@section('content')
  <div class="admin-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.blog.comments.index', ['status' => 'pending']) }}" class="admin-btn admin-btn--sm {{ $status === 'pending' ? 'admin-btn--primary' : 'admin-btn--ghost' }}">Pending</a>
      <a href="{{ route('admin.blog.comments.index', ['status' => 'approved']) }}" class="admin-btn admin-btn--sm {{ $status === 'approved' ? 'admin-btn--primary' : 'admin-btn--ghost' }}">Approved</a>
      <a href="{{ route('admin.blog.comments.index', ['status' => 'all']) }}" class="admin-btn admin-btn--sm {{ $status === 'all' ? 'admin-btn--primary' : 'admin-btn--ghost' }}">All</a>
    </div>
  </div>
  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr><th>Post</th><th>Author</th><th>Comment</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($comments as $comment)
          <tr>
            <td><a href="{{ route('admin.blog.posts.edit', $comment->post) }}">{{ $comment->post->title }}</a></td>
            <td>{{ $comment->author_name }}<br><small>{{ $comment->author_email }}</small></td>
            <td>{{ Str::limit($comment->body, 120) }}</td>
            <td>{{ $comment->created_at->format('M j, Y') }}</td>
            <td>
              @if (! $comment->is_approved)
                <form action="{{ route('admin.blog.comments.approve', $comment) }}" method="post" style="display:inline;">@csrf
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Approve</button>
                </form>
              @endif
              <form action="{{ route('admin.blog.comments.destroy', $comment) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete comment?');">@csrf @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="admin-form-help">No comments.</td></tr>
        @endforelse
      </tbody>
    </table>
    @if ($comments->hasPages())
      <div style="margin-top:16px;">{{ $comments->links('pagination.admin') }}</div>
    @endif
  </div>
@endsection

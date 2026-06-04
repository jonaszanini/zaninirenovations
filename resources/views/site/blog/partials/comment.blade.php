<li>
  <article class="clearfix">
    <header>
      <h5>{{ $comment->author_name }}</h5>
      <p><span>{{ $comment->created_at->format('M j, Y') }}</span></p>
    </header>
    <div class="comment_text">{!! nl2br(e($comment->body)) !!}</div>
  </article>
  @if ($comment->approvedReplies->isNotEmpty())
    <ol>
      @foreach ($comment->approvedReplies as $reply)
        @include('site.blog.partials.comment', ['comment' => $reply, 'depth' => $depth + 1, 'post' => $post])
      @endforeach
    </ol>
  @endif
  <p style="margin-top:8px;">
    <a href="#comment-reply-{{ $comment->id }}">Reply</a>
  </p>
  <form id="comment-reply-{{ $comment->id }}" action="{{ route('blog.comments.store', $post) }}" method="post" class="blog-comment-form blog-comment-reply" style="margin:12px 0 24px 24px;">
    @csrf
    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
    <input type="text" name="author_name" class="form-control" placeholder="Your name" required maxlength="120">
    <input type="email" name="author_email" class="form-control" placeholder="Your email" required maxlength="255">
    <textarea name="body" class="form-control" rows="3" required maxlength="5000"></textarea>
    <button type="submit" class="btn btn-theme btn-sm">Reply</button>
  </form>
</li>

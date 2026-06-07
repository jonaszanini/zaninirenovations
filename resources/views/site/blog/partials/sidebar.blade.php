<div class="widget site-sidebar">
  <h2 class="widget-title">Search</h2>
  <form action="{{ route('blog.index') }}" method="get" class="search-form" role="search">
    <div class="input-group">
      <input type="search" name="q" class="form-control" placeholder="Search…" value="{{ $q ?? '' }}">
      <span class="input-group-addon"><i class="fa fa-search"></i></span>
    </div>
  </form>
</div>

@if (isset($categories) && $categories->isNotEmpty())
  <div class="widget site-sidebar">
    <h2 class="widget-title">Categories</h2>
    <ul class="widget-post-category clearfix">
      @foreach ($categories as $category)
        <li>
          <a href="{{ route('blog.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>
          <span class="pull-right badge">{{ $category->published_posts_count ?? $category->posts_count ?? 0 }}</span>
        </li>
      @endforeach
    </ul>
  </div>
@endif

@if (isset($recentPosts) && $recentPosts->isNotEmpty())
  <div class="widget site-sidebar">
    <h2 class="widget-title">Recent posts</h2>
    <ul class="widget-post-category clearfix">
      @foreach ($recentPosts as $rp)
        <li><a href="{{ route('blog.show', $rp) }}">{{ $rp->title }}</a></li>
      @endforeach
    </ul>
  </div>
@endif

@if (isset($popularPosts) && $popularPosts->isNotEmpty())
  <div class="widget site-sidebar">
    <h2 class="widget-title">Popular posts</h2>
    <ul class="widget-post-category clearfix">
      @foreach ($popularPosts as $pp)
        <li><a href="{{ route('blog.show', $pp) }}">{{ $pp->title }}</a></li>
      @endforeach
    </ul>
  </div>
@endif

@if (isset($tagCloud) && $tagCloud->isNotEmpty())
  <div class="widget site-sidebar">
    <h2 class="widget-title">Tags</h2>
    <div class="tag-list">
      @foreach ($tagCloud as $tag)
        <a href="{{ route('blog.index', ['tag' => $tag->slug]) }}">{{ $tag->name }}</a>
      @endforeach
    </div>
  </div>
@endif

<div class="widget site-sidebar">
  <h2 class="widget-title">RSS</h2>
  <p><a href="{{ route('blog.rss') }}"><i class="fa fa-rss"></i> Subscribe to feed</a></p>
</div>

@extends('site.layouts.public')

@section('page_title', $post->title.' — Zanini Renovations')
@section('meta_description', $post->meta_description ?: $post->displayExcerpt(300))
@section('canonical', $post->publicUrl())
@section('og_type', 'article')
@if ($post->featuredImageUrl())
  @section('og_image', $post->featuredImageUrl())
@endif

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/blog.css') }}?v={{ @filemtime(public_path('css/blog.css')) ?: time() }}">
@endpush

@section('content')
  <section class="page-header">
    <div class="container">
      <div class="row">
        <div class="col-sm-12 text-center">
          <h3>{{ $post->title }}</h3>
          <p class="page-breadcrumb">
            <a href="{{ url('/') }}">Home</a> /
            <a href="{{ route('blog.index') }}">Blog</a> /
            {{ Str::limit($post->title, 40) }}
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-content-block">
    <div class="container">
      <div class="row">
        <div class="col-md-8 col-sm-12">
          <article class="post single-post-inner">
            @if ($post->featuredImageUrl())
              <div class="post-inner-featured-content">
                <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->title }}" loading="eager">
              </div>
            @endif
            <div class="single-post-inner-title">
              <h2>{{ $post->title }}</h2>
              <p class="single-post-meta">
                <i class="fa fa-user"></i> {{ $post->author_name }}
                &nbsp;<i class="fa fa-calendar"></i> {{ $post->displayDate() }}
                &nbsp;<i class="fa fa-clock-o"></i> {{ $post->readTimeMinutes() }} min read
                &nbsp;<i class="fa fa-comments"></i> {{ $post->approvedCommentsCount() }} comments
              </p>
            </div>
            <div class="single-post-inner-content blog-post-body">
              {!! $htmlProcessor->addLazyLoadToImages($post->html_content ?? '') !!}
            </div>
            @if ($post->tags->isNotEmpty())
              <div class="single-post-inner-meta">
                <div class="tag-list">
                  @foreach ($post->tags as $tag)
                    <a href="{{ route('blog.index', ['tag' => $tag->slug]) }}">{{ $tag->name }}</a>
                  @endforeach
                </div>
              </div>
            @endif
          </article>

          <div class="blog-share clearfix">
            <span>Share:</span>
            @php $shareUrl = urlencode($post->publicUrl()); $shareTitle = urlencode($post->title); @endphp
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener noreferrer" class="fa fa-facebook"></a>
            <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}" target="_blank" rel="noopener noreferrer" class="fa fa-twitter"></a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" target="_blank" rel="noopener noreferrer" class="fa fa-linkedin"></a>
          </div>

          @if ($prevPost || $nextPost)
            <div class="post-nav-section clearfix">
              @if ($nextPost)
                <a class="btn btn-primary fr" href="{{ route('blog.show', $nextPost) }}">Next post <i class="fa fa-angle-double-right"></i></a>
              @endif
              @if ($prevPost)
                <a class="btn btn-primary" href="{{ route('blog.show', $prevPost) }}"><i class="fa fa-angle-double-left"></i> Previous post</a>
              @endif
            </div>
          @endif

          @if ($relatedPosts->isNotEmpty())
            <div class="related-post">
              <div class="topic-bold-header clearfix"><h4>Related posts</h4></div>
              <ul>
                @foreach ($relatedPosts as $rel)
                  <li><a href="{{ route('blog.show', $rel) }}">{{ $rel->title }}</a></li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="comments-area" id="comments">
            <div class="topic-bold-header clearfix">
              <h4>{{ $post->approvedCommentsCount() }} comment(s)</h4>
            </div>

            @if (session('success'))
              <p class="alert alert-success">{{ session('success') }}</p>
            @endif
            @if (session('error'))
              <p class="alert alert-danger">{{ session('error') }}</p>
            @endif

            @if ($approvedComments->isNotEmpty())
              <ol class="commentslist">
                @foreach ($approvedComments as $comment)
                  @include('site.blog.partials.comment', ['comment' => $comment, 'depth' => 0, 'post' => $post])
                @endforeach
              </ol>
            @endif

            <div class="topic-bold-header clearfix" style="margin-top:24px;"><h4>Leave a comment</h4></div>
            <form action="{{ route('blog.comments.store', $post) }}" method="post" class="blog-comment-form">
              @csrf
              <div class="row">
                <div class="col-sm-6">
                  <input type="text" name="author_name" class="form-control" placeholder="Your name" required maxlength="120" value="{{ old('author_name') }}">
                </div>
                <div class="col-sm-6">
                  <input type="email" name="author_email" class="form-control" placeholder="Your email" required maxlength="255" value="{{ old('author_email') }}">
                </div>
              </div>
              <textarea name="body" class="form-control" rows="5" placeholder="Your comment" required maxlength="5000">{{ old('body') }}</textarea>
              @if ($captchaMode === 'math' && $captchaChallenge)
                <p class="blog-captcha">
                  <label>What is {{ $captchaChallenge['a'] }} + {{ $captchaChallenge['b'] }}?</label>
                  <input type="number" name="captcha_answer" class="form-control" required style="max-width:120px;display:inline-block;">
                  <input type="hidden" name="captcha_token" value="{{ $captchaChallenge['token'] }}">
                </p>
              @elseif ($captchaMode === 'recaptcha_v2' && filled(config('blog.recaptcha_site_key')))
                <div class="g-recaptcha" data-sitekey="{{ config('blog.recaptcha_site_key') }}"></div>
                @push('scripts')
                  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                @endpush
              @endif
              <button type="submit" class="btn btn-theme">Post comment</button>
            </form>
          </div>
        </div>
        <div class="col-md-4 col-sm-12">
          @php
            $categories = \App\Models\BlogCategory::query()->whereHas('posts', fn ($q) => $q->published())->withCount(['posts as published_posts_count' => fn ($q) => $q->published()])->orderBy('name')->get();
          @endphp
          @include('site.blog.partials.sidebar', [
            'q' => '',
            'categories' => $categories,
            'recentPosts' => $recentPosts,
            'popularPosts' => null,
            'tagCloud' => $post->tags,
          ])
        </div>
      </div>
    </div>
  </section>
@endsection

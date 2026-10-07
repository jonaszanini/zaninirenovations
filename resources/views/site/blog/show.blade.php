@extends('site.layouts.public')

@php
  $isPreview = ! empty($isPreview);
  $cta = $cta ?? \App\Support\BlogPublicContent::cta($post);
  $relatedProjects = $relatedProjects ?? collect();
  $featuredDims = $post->featuredImageDimensions();
  $bodyHtml = $htmlProcessor->addLazyLoadToImages($post->html_content ?? '');
  $ctaHtml = view('site.blog.partials.cta', compact('post', 'cta'))->render();
  $bodyHtml = \App\Support\BlogPublicContent::insertAfterIntro($bodyHtml, $ctaHtml);
@endphp

@section('page_title', $post->documentTitle())
@section('meta_description', $post->meta_description ?: $post->displayExcerpt(300))
@section('canonical', $post->publicUrl())
@section('og_type', 'article')
@section('meta_robots', $isPreview ? 'noindex, nofollow' : ($post->noindex ? 'noindex, follow' : 'index,follow'))
@section('og_image_alt', $post->featuredImageAlt())
@section('og_image', $post->featuredImageUrl() ?: '')

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::blogPosting($post)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/blog.css') }}?v={{ @filemtime(public_path('css/blog.css')) ?: time() }}">
@endpush

@section('content')
  @if ($isPreview)
    <div class="blog-preview-banner">
      <div class="container">
        <strong>Draft preview</strong>
        — only signed-in staff can see this page. Search engines are told not to index it.
        <a href="{{ route('admin.blog.posts.edit', $post) }}">Back to editor</a>
      </div>
    </div>
  @endif
  <section class="page-header page-header--blog page-header--slim">
    <div class="container">
      <p class="page-breadcrumb">
        <a href="{{ url('/') }}">Home</a> /
        <a href="{{ route('blog.index') }}">Blog</a> /
        {{ Str::limit($post->title, 40) }}
      </p>
    </div>
  </section>

  <section class="section-content-block blog-article-wrap">
    <div class="container">
      <div class="row">
        <div class="col-md-8 col-sm-12">
          <article class="post single-post-inner">
            @if ($post->featuredImageUrl())
              <div class="post-inner-featured-content">
                <img src="{{ $post->featuredImageUrl() }}"
                     alt="{{ $post->featuredImageAlt() }}"
                     @if ($featuredDims)
                       width="{{ $featuredDims['width'] }}"
                       height="{{ $featuredDims['height'] }}"
                     @endif
                     loading="eager"
                     decoding="async"
                     sizes="(max-width: 767px) 100vw, 720px">
              </div>
            @endif
            <div class="single-post-inner-title">
              <h1 class="single-post-inner-title__heading">{{ $post->title }}</h1>
              <p class="single-post-meta">
                <i class="fa fa-user"></i> {{ $post->author_name }}
                &nbsp;<i class="fa fa-calendar"></i> {{ $post->displayDateLong() }}
                &nbsp;<i class="fa fa-clock-o"></i> {{ $post->readTimeMinutes() }} min read
                &nbsp;<i class="fa fa-comments"></i> {{ $post->approvedCommentsCount() }} comments
              </p>
            </div>
            <div class="single-post-inner-content blog-post-body">
              {!! $bodyHtml !!}
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

          @include('site.blog.partials.author', ['post' => $post])

          <div class="blog-share clearfix">
            <span>Share:</span>
            @php $shareUrl = urlencode($post->publicUrl()); $shareTitle = urlencode($post->title); @endphp
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener noreferrer" class="fa fa-facebook" aria-label="Share on Facebook"></a>
            <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}" target="_blank" rel="noopener noreferrer" class="fa fa-twitter" aria-label="Share on X (Twitter)"></a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" target="_blank" rel="noopener noreferrer" class="fa fa-linkedin" aria-label="Share on LinkedIn"></a>
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

          @if ($relatedPosts->isNotEmpty() || $relatedProjects->isNotEmpty())
            <div class="related-post blog-related">
              @if ($relatedPosts->isNotEmpty())
                <div class="topic-bold-header clearfix"><h2>Related posts</h2></div>
                <ul>
                  @foreach ($relatedPosts as $rel)
                    <li><a href="{{ route('blog.show', $rel) }}">{{ $rel->title }}</a></li>
                  @endforeach
                </ul>
              @endif
              @if ($relatedProjects->isNotEmpty())
                <div class="topic-bold-header clearfix"><h2>Related projects</h2></div>
                <ul>
                  @foreach ($relatedProjects as $project)
                    <li><a href="{{ $project->publicUrl() }}">{{ $project->title }}</a></li>
                  @endforeach
                </ul>
              @endif
              <p class="blog-related__service">
                <a href="{{ $cta['service_url'] }}">See {{ $cta['service_label'] }}</a>
              </p>
            </div>
          @else
            <p class="blog-related__service">
              <a href="{{ $cta['service_url'] }}">See {{ $cta['service_label'] }}</a>
            </p>
          @endif

          <div class="comments-area" id="comments">
            <div class="topic-bold-header clearfix">
              <h2>{{ $post->approvedCommentsCount() }} comment(s)</h2>
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

            <div class="topic-bold-header clearfix" style="margin-top:24px;"><h2>Leave a comment</h2></div>
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
              @include('site.blog.partials.captcha', ['captchaAnswerId' => 'captcha-answer'])
              @if ($captchaMode === 'recaptcha_v2' && filled(config('blog.recaptcha_site_key')))
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

@extends('site.layouts.public')

@section('page_title', 'Blog — Zanini Renovations')
@section('meta_description', 'Renovation tips, project insights, and news from Zanini Renovations in Carleton Place and the Ottawa region.')
@section('canonical', route('blog.index'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::blogIndex()) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/blog.css') }}?v={{ @filemtime(public_path('css/blog.css')) ?: time() }}">
@endpush

@section('content')
  <section class="page-header page-header--blog">
    <div class="container">
      <div class="row">
        <div class="col-sm-12 text-center">
          <h3>Blog</h3>
          <p class="page-breadcrumb">
            <a href="{{ url('/') }}">Home</a> / Blog
          </p>
        </div>
      </div>
    </div>
  </section>

  @if ($featuredPost && ! request()->has('q') && ! request()->has('category') && ! request()->has('tag') && $posts->currentPage() === 1)
    <section class="blog-hero">
      <div class="container">
        <div class="row blog-hero__row">
          @if ($featuredPost->featuredImageUrl())
            <div class="col-md-5">
              <a href="{{ route('blog.show', $featuredPost) }}">
                <img src="{{ $featuredPost->featuredImageUrl() }}" alt="{{ $featuredPost->title }}" loading="eager" class="blog-hero__img">
              </a>
            </div>
          @endif
          <div class="{{ $featuredPost->featuredImageUrl() ? 'col-md-7' : 'col-md-12' }}">
            <span class="blog-hero__label">Featured</span>
            <h2><a href="{{ route('blog.show', $featuredPost) }}">{{ $featuredPost->title }}</a></h2>
            <p class="single-post-meta">
              <i class="fa fa-user"></i> {{ $featuredPost->author_name }}
              &nbsp;<i class="fa fa-calendar"></i> {{ $featuredPost->displayDate() }}
              &nbsp;<i class="fa fa-clock-o"></i> {{ $featuredPost->readTimeMinutes() }} min read
            </p>
            <p>{{ $featuredPost->displayExcerpt(280) }}</p>
            <a href="{{ route('blog.show', $featuredPost) }}" class="btn btn-theme">Read article</a>
          </div>
        </div>
      </div>
    </section>
  @endif

  <section class="main-content">
    <div class="container">
      <div class="row">
        <div class="col-md-8 col-sm-12">
          @forelse ($posts as $post)
            @if ($featuredPost && $post->id === $featuredPost->id && ! request()->has('q') && ! request()->has('category') && ! request()->has('tag') && $posts->currentPage() === 1)
              @continue
            @endif
            <article class="post single-post">
              @if ($post->featuredImageUrl())
                <div class="single-post-content">
                  <a href="{{ route('blog.show', $post) }}">
                    <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->title }}" loading="lazy">
                  </a>
                </div>
              @endif
              <div class="single-post-title">
                <h2><a href="{{ route('blog.show', $post) }}">{{ $post->title }}</a></h2>
                <p class="single-post-meta">
                  <i class="fa fa-user"></i> {{ $post->author_name }}
                  @if ($post->categories->isNotEmpty())
                    &nbsp;<i class="fa fa-book"></i>
                    @foreach ($post->categories as $cat)
                      <a href="{{ route('blog.index', ['category' => $cat->slug]) }}">{{ $cat->name }}</a>@if (! $loop->last), @endif
                    @endforeach
                  @endif
                  &nbsp;<i class="fa fa-calendar"></i> {{ $post->displayDate() }}
                  &nbsp;<i class="fa fa-clock-o"></i> {{ $post->readTimeMinutes() }} min
                </p>
                <p>{{ $post->displayExcerpt() }}</p>
                <a href="{{ route('blog.show', $post) }}" class="btn btn-theme btn-sm">Read more</a>
              </div>
            </article>
          @empty
            <p class="text-center">No posts found.</p>
          @endforelse

          @if ($posts->hasPages())
            <div class="blog-pagination text-center clearfix">
              {{ $posts->links('pagination.site') }}
            </div>
          @endif
        </div>
        <div class="col-md-4 col-sm-12">
          @include('site.blog.partials.sidebar', compact('categories', 'recentPosts', 'popularPosts', 'tagCloud', 'q'))
        </div>
      </div>
    </div>
  </section>
@endsection

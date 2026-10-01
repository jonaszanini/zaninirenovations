@extends('site.layouts.public')

@section('page_title', $project->seoTitle())
@section('meta_description', $project->seoDescription())
@section('canonical', $project->publicUrl())
@section('og_type', 'article')
@if ($project->categories->isNotEmpty())
  @section('meta_keywords', $project->categories->pluck('name')->implode(', '))
@endif
@if ($project->coverImageUrl())
  @section('og_image', $project->coverImageUrl())
@endif

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::portfolioProject($project)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/portfolio.css') }}?v={{ @filemtime(public_path('css/portfolio.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $coverBackground = $project->coverImageUrl();
    $galleryUrls = $project->photos->map(fn ($photo) => $photo->url())->values()->all();
  @endphp
  <section
    class="page-header {{ $coverBackground ? 'page-header--project' : '' }}"
    @if ($coverBackground) style="background-image: url('{{ $coverBackground }}')" @endif
  >
    <div class="container">
      <div class="row">
        <div class="col-sm-12 text-center">
          <h1>{{ $project->title }}</h1>
          <p class="page-breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            /
            <a href="{{ route('portfolio.index') }}">Projects</a>
            /
            {{ \Illuminate\Support\Str::limit($project->title, 48) }}
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
            @if ($project->photos->isNotEmpty())
              <div class="portfolio-page-gallery">
                @foreach ($project->photos as $photo)
                  <button
                    type="button"
                    class="portfolio-page-gallery__item portfolio-page-gallery__open js-project-gallery {{ $loop->first ? 'portfolio-page-gallery__item--cover' : '' }}"
                    data-project-title="{{ $project->title }}"
                    data-gallery-start="{{ $loop->index }}"
                    data-gallery='@json($galleryUrls)'
                    aria-label="Open photo {{ $loop->iteration }} of {{ $project->photos->count() }}"
                  >
                    <img
                      src="{{ $photo->url() }}"
                      alt="{{ $project->title }} — photo {{ $loop->iteration }}"
                      @if ($loop->first) loading="eager" @else loading="lazy" decoding="async" @endif
                    >
                  </button>
                @endforeach
              </div>
            @endif

            <div class="single-post-inner-title">
              <p class="single-post-inner-title__heading">{{ $project->title }}</p>
              <p class="single-post-meta">
                @if ($project->categoryLabel() !== '—')
                  <i class="fa fa-tag"></i> {{ $project->categoryLabel() }}
                @endif
                @if ($project->completed_on)
                  &nbsp;<i class="fa fa-calendar"></i> {{ $project->completed_on->format('F j, Y') }}
                @endif
              </p>
            </div>

            <div class="single-post-inner-content portfolio-page-description">
              @foreach (preg_split("/\r\n|\n|\r/", trim((string) $project->description)) ?: [] as $paragraph)
                @if (trim($paragraph) !== '')
                  <p>{{ $paragraph }}</p>
                @endif
              @endforeach
            </div>
          </article>
        </div>

        <div class="col-md-4 col-sm-12">
          <div class="widget site-sidebar portfolio-page-sidebar">
            <h2 class="widget-title">Project details</h2>
            <ul class="widget-post-category clearfix">
              <li>
                Category
                <span class="pull-right">{{ $project->categoryLabel() }}</span>
              </li>
              @if ($project->completed_on)
                <li>
                  Completed
                  <span class="pull-right">{{ $project->completed_on->format('M j, Y') }}</span>
                </li>
              @endif
              <li>
                By
                <span class="pull-right">Zanini Renovations</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      @if ($related->isNotEmpty())
        <div class="portfolio-page-related">
          <div class="row section-heading-wrapper">
            <div class="col-md-12 col-sm-12 text-center">
              <h2 class="subheading-alt-style">Related projects</h2>
            </div>
          </div>
          <div class="row latest-projects-grid">
            @include('site.partials.latest-projects', ['projects' => $related])
          </div>
        </div>
      @endif
    </div>
  </section>

  <section class="cta-layout-02 section-primary-bg">
    <div class="container">
      <div class="row">
        <div class="col-lg-9 col-md-9 col-sm-12 col-xs-12">
          <h2 class="text-light-color">Planning a similar project?</h2>
          <p class="text-light-color">
            Tell us about the work and we will follow up with a quote for your home in Carleton Place, Ottawa, and nearby communities.
          </p>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 text-center">
          <a class="btn btn-theme btn-square btn-theme-invert" href="{{ url('/#contact-location') }}">Contact us</a>
        </div>
      </div>
    </div>
  </section>

  @if ($project->photos->isNotEmpty())
    <div class="modal fade project-gallery-modal" id="latestProjectGalleryModal" tabindex="-1" role="dialog" aria-labelledby="latestProjectGalleryModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header project-gallery-modal-header">
            <button type="button" class="close project-gallery-close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title" id="latestProjectGalleryModalLabel"></h4>
          </div>
          <div class="modal-body project-gallery-modal-body">
            <div class="project-gallery-viewport">
              <div class="project-gallery-track" id="projectGalleryTrack"></div>
            </div>
            <button type="button" class="project-gallery-nav project-gallery-nav-prev" id="projectGalleryPrev" aria-label="Previous photo">
              <svg class="project-gallery-nav-svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
            </button>
            <button type="button" class="project-gallery-nav project-gallery-nav-next" id="projectGalleryNext" aria-label="Next photo">
              <svg class="project-gallery-nav-svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
            </button>
            <ul class="project-gallery-dots" id="projectGalleryDots"></ul>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

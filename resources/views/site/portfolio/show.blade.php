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
  <section class="page-header">
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
                  <figure class="portfolio-page-gallery__item {{ $loop->first ? 'portfolio-page-gallery__item--cover' : '' }}">
                    <img
                      src="{{ $photo->url() }}"
                      alt="{{ $project->title }} — photo {{ $loop->iteration }}"
                      @if ($loop->first) loading="eager" @else loading="lazy" decoding="async" @endif
                    >
                  </figure>
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
@endsection

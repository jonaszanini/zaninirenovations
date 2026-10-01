@extends('site.layouts.public')

@section('page_title', 'Carpentry & Renovation Projects in Ottawa | Zanini Renovations')
@section('meta_description', 'Photos and details of decks, fences, basements, flooring, and other completed projects by Zanini Renovations in Carleton Place and the Ottawa region.')
@section('canonical', route('portfolio.index'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::portfolioIndex()) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/portfolio.css') }}?v={{ @filemtime(public_path('css/portfolio.css')) ?: time() }}">
@endpush

@section('content')
  <section class="page-header">
    <div class="container">
      <div class="row">
        <div class="col-sm-12 text-center">
          <h1>Latest projects</h1>
          <p class="page-breadcrumb">
            <a href="{{ url('/') }}">Home</a> / Projects
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-content-block section-portfolio-bg">
    <div class="container">
      <div class="row section-heading-wrapper">
        <div class="col-md-12 col-sm-12 text-center">
          <h2 class="subheading-alt-style">
            Decks, fences, basements, and other carpentry completed by Zanini Renovations across the Ottawa region.
          </h2>
        </div>
      </div>

      <div class="row latest-projects-grid">
        @include('site.partials.latest-projects', ['projects' => $projects])
      </div>
    </div>
  </section>
@endsection

@extends('site.layouts.public')

@section('page_title', $hub['title'])
@section('meta_description', $hub['meta_description'])
@section('canonical', url('/services'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::servicesHub($hub)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('css/public-pages.css') }}?v={{ @filemtime(public_path('css/public-pages.css')) ?: time() }}">
@endpush

@section('content')
  <main class="sa-page pp-page" id="services-hub">
    <header class="sa-hero pp-hero">
      <div class="container pp-wrap">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <span>Services</span>
        </nav>
        <h1>{{ $hub['h1'] }}</h1>
        @foreach ((array) ($hub['intro_paragraphs'] ?? []) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
        <p class="pp-cta-row">
          <a class="btn btn-theme sa-btn" href="{{ url('/contact') }}">Get a free estimate</a>
          <a class="btn btn-theme sa-btn sa-btn--ghost" href="tel:+16478347570">Call (647) 834-7570</a>
        </p>
      </div>
    </header>

    <section class="sa-section pp-section">
      <div class="container pp-wrap">
        <ul class="pp-hub-grid">
          @foreach ($pages as $service)
            <li>
              <a class="pp-hub-card" href="{{ url('/services/'.$service['slug']) }}">
                <h2>{{ $service['hub_label'] ?? $service['nav_label'] }}</h2>
                <p>{{ $service['meta_description'] }}</p>
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    </section>

    <div class="container pp-wrap pp-cta-bottom">
      @include('site.partials.public-page-cta', ['cta' => $cta])
    </div>
  </main>
@endsection

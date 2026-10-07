@extends('site.layouts.public')

@section('page_title', $hub['title'])
@section('meta_description', $hub['meta_description'])
@section('canonical', url('/service-areas'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::serviceAreaHub($hub)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $mapsUrl = \App\Support\SeoStructuredData::googleBusinessUrl();
  @endphp
  <main class="sa-page sa-page--hub" id="service-areas">
    <header class="sa-hero">
      <div class="container">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <span>Service areas</span>
        </nav>
        <h1>{{ $hub['h1'] }}</h1>
        @foreach ((array) ($hub['intro_paragraphs'] ?? []) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
        @include('site.partials.service-area-cta', ['cta' => $cta, 'compact' => true])
      </div>
    </header>

    <section class="sa-group">
      <div class="container">
        <h2>Town pages</h2>
        <ul class="sa-town-grid sa-town-grid--featured">
          @foreach ($published as $page)
            <li class="sa-town sa-town--featured">
              <a class="sa-town__name" href="{{ url('/service-areas/'.$page['slug']) }}">{{ $page['hub_label'] ?? $page['name'] }}</a>
              @if (! empty($page['featured_note']))
                <span class="sa-town__note">{{ $page['featured_note'] }}</span>
              @endif
              <a class="btn btn-theme sa-btn" href="{{ url('/service-areas/'.$page['slug']) }}">View page</a>
            </li>
          @endforeach
        </ul>

        @foreach ($groups as $group)
          <h2>{{ $group['heading'] }}</h2>
          <ul class="sa-town-grid">
            @foreach ($group['towns'] as $town)
              @if (! empty($town['href']))
                @continue
              @endif
              <li class="sa-town">
                <span class="sa-town__name sa-town__name--plain">{{ $town['name'] }}</span>
                @if ($town['note'] !== '')
                  <span class="sa-town__note">{{ $town['note'] }}</span>
                @endif
                @if ($town['projects']->isNotEmpty())
                  <ul class="sa-town__projects">
                    @foreach ($town['projects'] as $project)
                      <li>
                        <a href="{{ $project->publicUrl() }}">{{ $project->title }}</a>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </li>
            @endforeach
          </ul>
        @endforeach

        <p class="sa-map-actions">
          <a class="btn btn-theme sa-btn sa-btn--ghost" href="{{ $mapsUrl }}" rel="noopener noreferrer" target="_blank">Open in Google Maps</a>
        </p>
      </div>
    </section>
  </main>
@endsection

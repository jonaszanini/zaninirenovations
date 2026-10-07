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
  <main class="sa-page" id="service-areas">
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
        @include('site.partials.service-area-cta', ['cta' => $cta])
      </div>
    </header>

    @foreach ($groups as $group)
      <section class="sa-group">
        <div class="container">
          <h2>{{ $group['heading'] }}</h2>
          @if ($group['intro'] !== '')
            <p class="sa-muted">{{ $group['intro'] }}</p>
          @endif
          <ul class="sa-town-list">
            @foreach ($group['towns'] as $town)
              <li class="sa-town">
                @if (! empty($town['href']))
                  <a class="sa-town__name" href="{{ $town['href'] }}">{{ $town['name'] }}</a>
                @else
                  <span class="sa-town__name sa-town__name--plain">{{ $town['name'] }}</span>
                @endif
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
        </div>
      </section>
    @endforeach

    @if ($mapEmbedSrc !== '')
      <section class="sa-map">
        <div class="container">
          <h2>Find us on Google</h2>
          <p class="sa-muted">Google Business listing for Zanini Renovations. No street address is published.</p>
          <div class="sa-map__frame">
            <iframe
              src="{{ $mapEmbedSrc }}"
              title="Zanini Renovations on Google Maps"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
            ></iframe>
          </div>
        </div>
      </section>
    @endif

    <div class="container sa-cta-bottom">
      @include('site.partials.service-area-cta', ['cta' => $cta])
    </div>
  </main>
@endsection

@extends('site.layouts.public')

@section('page_title', $page['title'])
@section('meta_description', $page['meta_description'])
@section('canonical', url('/service-areas/'.$page['slug']))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::serviceAreaTown($page)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $mapsUrl = \App\Support\SeoStructuredData::googleBusinessUrl();
  @endphp
  <main class="sa-page" id="service-area-{{ $page['slug'] }}">
    <header class="sa-hero">
      <div class="container">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <a href="{{ route('service-areas.index') }}">Service areas</a>
          <span aria-hidden="true">/</span>
          <span>{{ $page['breadcrumb_name'] ?? $page['name'] }}</span>
        </nav>
        <h1>{{ $page['h1'] }}</h1>
        @foreach ((array) ($page['intro_paragraphs'] ?? []) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
        @include('site.partials.service-area-cta', ['cta' => $cta])
      </div>
    </header>

    <section class="sa-section">
      <div class="container">
        <h2>{{ $page['services_heading'] }}</h2>
        @if (! empty($page['services_intro']))
          <p>{{ $page['services_intro'] }}</p>
        @endif
        <div class="sa-services">
          @foreach ((array) ($page['services'] ?? []) as $service)
            <article class="sa-card">
              <h3>{{ $service['title'] }}</h3>
              <p>{!! \App\Support\ServiceAreas::richText((string) ($service['text'] ?? '')) !!}</p>
            </article>
          @endforeach
        </div>
      </div>
    </section>

    <section class="sa-section">
      <div class="container">
        <h2>{{ $page['projects_heading'] ?? ('Recent projects in '.$page['name']) }}</h2>
        @if (! empty($page['projects_intro']))
          <p>{!! \App\Support\ServiceAreas::richText((string) $page['projects_intro']) !!}</p>
        @endif
        @include('site.partials.service-area-projects', ['projects' => $projects])
        @if (! empty($page['projects_note']))
          <p>{!! \App\Support\ServiceAreas::richText((string) $page['projects_note']) !!}</p>
        @endif
      </div>
    </section>

    <section class="sa-section">
      <div class="container">
        <h2>{{ $page['permits_heading'] }}</h2>
        @foreach ((array) ($page['permits_paragraphs'] ?? []) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
        @if (! empty($page['permit_links']))
          <ul class="sa-permits">
            @foreach ($page['permit_links'] as $link)
              <li>
                <a href="{{ $link['url'] }}" rel="noopener noreferrer" target="_blank">{{ $link['label'] }}</a>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </section>

    <section class="sa-section">
      <div class="container">
        <h2>{{ $page['map_heading'] }}</h2>
        @foreach ((array) ($page['map_paragraphs'] ?? []) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
        @if ($nearby !== [])
          <p>
            Nearby:
            @foreach ($nearby as $item)
              <a href="{{ $item['url'] }}">{{ $item['name'] }}</a>@if (! $loop->last), @endif
            @endforeach
            · <a href="{{ route('service-areas.index') }}">All service areas</a>
          </p>
        @else
          <p><a href="{{ route('service-areas.index') }}">All service areas</a></p>
        @endif
        <p>
          <a class="btn btn-theme sa-btn sa-btn--ghost" href="{{ $mapsUrl }}" rel="noopener noreferrer" target="_blank">Open in Google Maps</a>
        </p>
      </div>
    </section>

    @if (! empty($page['faqs']))
      <section class="sa-section">
        <div class="container">
          <h2>{{ $page['faqs_heading'] ?? ('Questions about work in '.$page['name']) }}</h2>
          <div class="sa-faq">
            @foreach ($page['faqs'] as $faq)
              <details class="sa-faq__item">
                <summary>{{ $faq['question'] }}</summary>
                <p>{{ $faq['answer'] }}</p>
              </details>
            @endforeach
          </div>
        </div>
      </section>
    @endif

    <div class="container sa-cta-bottom">
      @include('site.partials.service-area-cta', ['cta' => $cta])
    </div>
  </main>
@endsection

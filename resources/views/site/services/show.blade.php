@extends('site.layouts.public')

@section('page_title', $page['title'])
@section('meta_description', $page['meta_description'])
@section('canonical', url('/services/'.$page['slug']))
@section('og_image', $hero['src'] ?? '')
@section('og_image_alt', $hero['alt'] ?? '')

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::servicePage($page)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('css/public-pages.css') }}?v={{ @filemtime(public_path('css/public-pages.css')) ?: time() }}">
@endpush

@section('content')
  <main class="sa-page pp-page" id="service-{{ $page['slug'] }}">
    <header class="sa-hero pp-hero">
      <div class="container pp-wrap">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <a href="{{ route('services.index') }}">Services</a>
          <span aria-hidden="true">/</span>
          <span>{{ $page['breadcrumb_name'] ?? $page['h1'] }}</span>
        </nav>
        <h1>{{ $page['h1'] }}</h1>
        @if (! empty($hero['src']))
          <figure class="pp-hero__media">
            <img src="{{ $hero['src'] }}" alt="{{ $hero['alt'] }}" width="960" height="540" decoding="async">
          </figure>
        @endif
      </div>
    </header>

    <section class="sa-section pp-section">
      <div class="container pp-wrap pp-prose">
        @include('site.services.bodies.'.$page['slug'])
      </div>
    </section>

    @if (! empty($page['projects']))
      <section class="sa-section pp-section">
        <div class="container pp-wrap">
          <h2>Recent projects</h2>
          <ul class="pp-projects">
            @foreach ($page['projects'] as $item)
              <li>
                <a href="{{ url('/portfolio/'.$item['slug']) }}">{{ $item['label'] }}</a>@if (! empty($item['text'])): {{ $item['text'] }}@endif
              </li>
            @endforeach
          </ul>
          @if (! empty($page['more_projects']))
            <p>
              More projects:
              @foreach ($page['more_projects'] as $item)
                <a href="{{ url('/portfolio/'.$item['slug']) }}">{{ $item['label'] }}</a>
                @if (! empty($item['text']))
                  ({{ $item['text'] }})
                @endif
                @if (! $loop->last), @endif
              @endforeach
            </p>
          @endif
        </div>
      </section>
    @endif

    @if (! empty($page['reviews']))
      <section class="sa-section pp-section">
        <div class="container pp-wrap">
          <h2>What clients say</h2>
          @foreach ($page['reviews'] as $review)
            <blockquote class="pp-quote">
              <p>“{{ $review['quote'] }}”</p>
              <p class="pp-quote-cite">
                {{ $review['author'] }},
                <a href="{{ \App\Support\GoogleReviews::profileUrl() }}" rel="noopener noreferrer" target="_blank">Google</a>
              </p>
            </blockquote>
          @endforeach
        </div>
      </section>
    @endif

    @if (! empty($page['faqs']))
      <section class="sa-section pp-section">
        <div class="container pp-wrap">
          <h2>{{ $page['faqs_heading'] ?? 'FAQ' }}</h2>
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

    <div class="container pp-wrap pp-cta-bottom">
      @include('site.partials.public-page-cta', ['cta' => $cta])
    </div>
  </main>
@endsection

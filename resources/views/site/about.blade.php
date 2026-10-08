@extends('site.layouts.public')

@section('page_title', $page['title'])
@section('meta_description', $page['meta_description'])
@section('canonical', url('/about'))
@section('og_image', $page['photo'] ?? 'images/about-us/jonas.webp')
@section('og_image_alt', $page['photo_alt'] ?? 'Jonas Zanini')

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::aboutPage($page)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('css/public-pages.css') }}?v={{ @filemtime(public_path('css/public-pages.css')) ?: time() }}">
@endpush

@section('content')
  <main class="sa-page pp-page" id="about">
    <header class="sa-hero pp-hero">
      <div class="container pp-wrap">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <span>About</span>
        </nav>
        <h1>{{ $page['h1'] }}</h1>
        <figure class="pp-photo">
          <img src="{{ url($page['photo'] ?? 'images/about-us/jonas.webp') }}" alt="{{ $page['photo_alt'] ?? 'Jonas Zanini' }}" width="800" height="860" decoding="async">
        </figure>
      </div>
    </header>

    <section class="sa-section pp-section">
      <div class="container pp-wrap pp-prose">
        <h2>The story behind Zanini Renovations</h2>
        <p>Zanini Renovations is owned and run by Jonas Zanini. Before carpentry, Jonas spent 20 years in IT, working with global companies like IBM and Infosys. In 2019 he moved from Brazil to Ontario, and that's where he found a real passion for building. He started Zanini Renovations in 2020, and today it's a carpentry and renovation business based in Carleton Place.</p>
        <p>Software taught Jonas that small details decide whether something works, and carpentry is the same: a mitre that's slightly off or a gate that isn't braced will show sooner or later. We bring that same precision to every project. The goal is simple: quality craftsmanship that makes you fall in love with your home again.</p>
        <p>In Jonas's own words:</p>
        <blockquote class="pp-quote-block">
          <p>“I love the blend of practical engineering and meticulous craftsmanship that challenges my mind on every build. More than that, I truly enjoy collaborating with homeowners to create smart solutions that make daily life easier and turn their house into a true home.”</p>
          <footer>Jonas Zanini, owner</footer>
        </blockquote>

        <h2>How we work</h2>
        <ul>
          <li><strong>You deal directly with the owner.</strong> Jonas is your point of contact from the first call and the estimate through to the final walkthrough.</li>
          <li><strong>Free estimates.</strong> We talk about your project, visit the site when needed, and give you a written estimate in Canadian dollars.</li>
          <li><strong>Labour-only or with materials.</strong> We can quote either, depending on your project. Scope, schedule and pricing are confirmed before work begins.</li>
          <li><strong>Clear communication.</strong> You always know who to call and what's happening next.</li>
          <li><strong>A clean, organized site.</strong> We clean up as we go and at the end of the job.</li>
        </ul>
        <p><strong>Mayara Zanini, Office &amp; Marketing</strong>, looks after the office and marketing side of the business.</p>

        <h2>Insurance and warranty</h2>
        <p>Zanini Renovations is fully insured, with $3M in coverage, and we're happy to share our certificate of insurance before your project starts. Our work in Ontario is done to the Ontario Building Code (O.Reg. 332/12). Our workmanship is covered by a one-year limited warranty, and materials such as flooring or decking boards carry their manufacturer's warranty. The details are on our <a href="{{ url('/warranty') }}">warranty page</a>.</p>

        <h2>What we build</h2>
        <p>We work in homes, condos and commercial spaces. Most of the carpentry and renovation work we do in houses, we also do for condo owners and businesses, from a custom office desk built at a client's workplace to our most recent kitchen, which was a condo project. Our services:</p>
        <ul>
          <li><a href="{{ url('/services/decks') }}">Decks</a>, landings and outdoor stairs</li>
          <li><a href="{{ url('/services/fences-gates') }}">Custom wood fences and gates</a></li>
          <li><a href="{{ url('/services/basement-finishing') }}">Basement finishing</a></li>
          <li><a href="{{ url('/services/finish-carpentry-trim') }}">Finish carpentry and trim</a>, including fireplace surrounds and mantels</li>
          <li><a href="{{ url('/services/custom-built-ins-closets') }}">Custom built-ins and closets</a>, including bathroom vanities</li>
          <li><a href="{{ url('/services/staircases') }}">Staircases</a></li>
          <li><a href="{{ url('/services/flooring-installation') }}">Flooring installation</a></li>
          <li><a href="{{ url('/services/drywall') }}">Drywall</a></li>
          <li><a href="{{ url('/services/kitchen-remodel') }}">Kitchen remodels</a></li>
        </ul>
        <p>You can see real examples in our <a href="{{ url('/portfolio') }}">project portfolio</a>.</p>

        <h2>Where we work</h2>
        <p>We're based in Carleton Place and work across Lanark County, including Beckwith, Franktown, Almonte and the rest of Mississippi Mills, Pakenham, Perth, Smiths Falls and Lanark Highlands, as well as Arnprior. In Ottawa we work in Kanata, Stittsville, Carp, Ashton, Richmond, Barrhaven, Nepean, Manotick, Riverside South and other parts of the city. We also take on projects in Gatineau, Quebec.</p>

        <h2>What clients say</h2>
        <p>Zanini Renovations has a 5.0 rating on Google from 26 reviews (as of October 2026). <a href="{{ url('/reviews') }}">Read the reviews</a>.</p>
      </div>
    </section>

    <div class="container pp-wrap pp-cta-bottom">
      @include('site.partials.public-page-cta', ['cta' => $cta])
    </div>
  </main>
@endsection

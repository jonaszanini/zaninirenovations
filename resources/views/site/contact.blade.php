@extends('site.layouts.public')

@section('page_title', $page['title'])
@section('meta_description', $page['meta_description'])
@section('canonical', url('/contact'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::contactPage($page)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('css/public-pages.css') }}?v={{ @filemtime(public_path('css/public-pages.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $socialWhatsappUrl = (string) config('social.whatsapp_url');
  @endphp
  <main class="sa-page pp-page" id="contact">
    <header class="sa-hero pp-hero">
      <div class="container pp-wrap">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <span>Contact</span>
        </nav>
        <h1>{{ $page['h1'] }}</h1>
        <p>Tell us about your project and we'll get back to you with next steps. Estimates are free, and you'll deal directly with the owner, Jonas Zanini.</p>
      </div>
    </header>

    <section class="sa-section pp-section">
      <div class="container pp-wrap pp-prose">
        <h2>Reach us directly</h2>
        <ul>
          <li><strong>Phone:</strong> <a href="tel:+16478347570">(647) 834-7570</a></li>
          <li><strong>WhatsApp:</strong> <a href="{{ $socialWhatsappUrl }}" @if (str_starts_with($socialWhatsappUrl, 'http')) target="_blank" rel="noopener noreferrer" @endif>Message us on WhatsApp</a></li>
          <li><strong>Email:</strong> <a href="mailto:zaninirenovations@gmail.com">zaninirenovations@gmail.com</a></li>
          <li><strong>Hours:</strong> Monday to Friday, 9 AM to 6 PM. Closed Saturday and Sunday.</li>
          <li><strong>Based in:</strong> Carleton Place, Ontario</li>
        </ul>

        <h2>Where we work</h2>
        <p>We're based in Carleton Place and take on projects across the area:</p>
        <ul>
          <li><strong>Lanark County:</strong> Carleton Place, Beckwith, Franktown, Almonte and Mississippi Mills, Pakenham, Perth, Smiths Falls, Lanark Highlands and the rest of Lanark County</li>
          <li><strong>Ottawa:</strong> Kanata, Stittsville, Carp, Ashton, Richmond, Barrhaven, Nepean, Manotick, Riverside South and other parts of the city</li>
          <li><strong>Nearby:</strong> Arnprior, and Gatineau, Quebec</li>
        </ul>
        <p>Not sure if you're in our area? Ask, and we'll tell you honestly whether we can take it on.</p>

        @if ($mapEmbedSrc)
          <div class="pp-map">
            <iframe
              src="{{ $mapEmbedSrc }}"
              title="Zanini Renovations service area on Google Maps"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
            ></iframe>
          </div>
        @endif

        <h2>Request a free estimate</h2>
        <p>Fill in the form below and we'll get back to you to talk through the project and, if needed, set up a site visit. After that you'll receive a written estimate in Canadian dollars. We can quote labour-only or include materials.</p>
        <div class="pp-form">
          @include('site.partials.contact-form')
        </div>

        <h2>What to include in your request</h2>
        <p>The more we know up front, the more useful our first reply will be:</p>
        <ul>
          <li><strong>What you want done:</strong> a deck, fence, basement, trim, built-in, stairs, flooring, drywall or kitchen, for example</li>
          <li><strong>Your town or neighbourhood</strong> (no need for your full address yet)</li>
          <li><strong>Rough size:</strong> square footage, length of fence, number of rooms or stairs</li>
          <li><strong>Photos</strong> of the space as it is now, and pictures of anything you'd like it to look like. You can text photos to <a href="sms:+16478347570">(647) 834-7570</a> or email them to <a href="mailto:zaninirenovations@gmail.com">zaninirenovations@gmail.com</a>.</li>
          <li><strong>Materials</strong> you have in mind, or whether you'd like recommendations</li>
          <li><strong>Timing:</strong> when you'd like to start, and any deadlines</li>
          <li><strong>Budget range</strong>, if you have one. It helps us suggest options that fit</li>
          <li><strong>Home, condo or commercial space</strong>, and for condos, any building rules we should know about</li>
          <li><strong>Anything else</strong> that matters: pets, access to the yard, a past flood, work by other trades</li>
        </ul>

        <h2>What happens next</h2>
        <ol>
          <li><strong>We reply</strong> to your message or call, usually with a few questions about the project.</li>
          <li><strong>Site visit</strong>, when the job needs one, to measure and look at the space.</li>
          <li><strong>Written estimate</strong> with the scope, materials and what's included, before any work is booked.</li>
        </ol>
        <p>Zanini Renovations is fully insured ($3M), and our workmanship is covered by a one-year limited warranty. See the <a href="{{ url('/warranty') }}">warranty page</a> for details.</p>
      </div>
    </section>
  </main>
@endsection

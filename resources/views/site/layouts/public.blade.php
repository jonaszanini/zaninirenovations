<!DOCTYPE html>
<html lang="en-CA">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="contact-post-url" content="{{ url('/contact-email') }}">
  <title>@yield('page_title', 'Zanini Renovations')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="@yield('meta_description', 'Zanini Renovations — carpentry and renovations in Carleton Place and the Ottawa region.')">
  @hasSection('meta_keywords')
  <meta name="keywords" content="@yield('meta_keywords')">
  @endif
  <meta name="theme-color" content="#024768">
  <meta name="robots" content="@yield('meta_robots', 'index,follow')">
  <link rel="canonical" href="@yield('canonical', url()->current())">
  <link rel="shortcut icon" href="{{ url('images/favicon.png') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,700;1,400;1,700&family=Open+Sans:wght@300;400;500;700;800&display=swap">

  @stack('head-assets')

  @php
    $seoTitle = trim($__env->yieldContent('page_title')) ?: 'Zanini Renovations';
    $seoDesc = trim($__env->yieldContent('meta_description')) ?: 'Zanini Renovations — licensed carpenter Ottawa, Carleton Place, finish & rough carpentry, O.Reg. 332/12 compliant work.';
    $seoDescOg = \Illuminate\Support\Str::limit($seoDesc, 300, '');
    $ogImageOverride = trim($__env->yieldContent('og_image'));
    $ogImagePath = $ogImageOverride !== '' ? $ogImageOverride : ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
    $ogImageAbs = str_starts_with($ogImagePath, 'http') ? $ogImagePath : url($ogImagePath);
  @endphp
  <meta property="og:locale" content="en_CA">
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:site_name" content="Zanini Renovations">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $seoDescOg }}">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:image" content="{{ $ogImageAbs }}">
  <meta property="og:image:alt" content="{{ $seoTitle }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $seoDescOg }}">
  <meta name="twitter:image" content="{{ $ogImageAbs }}">

  @if (filled(config('seo.google_site_verification')))
  <meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">
  @endif

  @stack('jsonld')

  <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/font-awesome.min.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/owl.carousel.css') }}" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="{{ url('css/owl.theme.css') }}" media="print" onload="this.media='all'">
  <noscript><link href="{{ url('css/owl.theme.css') }}" rel="stylesheet" type="text/css"></noscript>
  <link rel="stylesheet" href="{{ url('css/animate.css') }}" media="print" onload="this.media='all'">
  <noscript><link href="{{ url('css/animate.css') }}" rel="stylesheet" type="text/css"></noscript>
  {{-- Cache-bust styles.css so every push to prod is picked up by phones,
       browsers and CDNs that would otherwise serve a stale copy. The query
       string changes whenever the file on disk is modified. --}}
  <link rel="stylesheet" href="{{ url('css/styles.css') }}?v={{ @filemtime(public_path('css/styles.css')) ?: time() }}" type="text/css">
  @stack('styles')
</head>

<body>

<noscript>
  <div class="container" style="padding:12px 15px;background:#f0f7fc;border-bottom:1px solid #c5d9e8;font-size:15px;line-height:1.5;">
    <strong>Zanini Renovations</strong> — licensed carpenter and renovation contractor in Carleton Place, Ontario (Ottawa region).
    Services: decks, fencing, basement framing, interior trim, custom built-ins, flooring, drywall, accent walls, and staircases.
    Phone: <a href="tel:+16478347570">(647) 834-7570</a>.
    <a href="{{ url('/') }}">Home</a> · <a href="{{ url('/') }}#contact-location">Contact</a>
  </div>
</noscript>

@include('site.partials.public-header')

@yield('content')

@include('site.partials.public-footer')

<a id="backTop" role="button" aria-label="Back to top">Back To Top</a>

<script defer src="{{ url('js/jquery.min.js') }}"></script>
<script defer src="{{ url('js/bootstrap.min.js') }}"></script>
<script defer src="{{ url('js/wow.min.js') }}"></script>
<script defer src="{{ url('js/jquery.backTop.min.js') }}"></script>
<script defer src="{{ url('js/waypoints.min.js') }}"></script>
<script defer src="{{ url('js/waypoints-sticky.min.js') }}"></script>
<script defer src="{{ url('js/owl.carousel.min.js') }}"></script>
<script defer src="{{ url('js/custom-scripts.js') }}?v={{ @filemtime(public_path('js/custom-scripts.js')) ?: time() }}"></script>
@if (filled(config('seo.ga4_measurement_id')))
<script defer src="https://www.googletagmanager.com/gtag/js?id={{ config('seo.ga4_measurement_id') }}"></script>
<script defer>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ config('seo.ga4_measurement_id') }}');
</script>
@endif
@stack('scripts')
</body>
</html>

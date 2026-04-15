<!DOCTYPE html>
<html lang="en-CA">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="contact-post-url" content="{{ url('/contact-email') }}">
  <title>@yield('title', 'Zanini Renovations')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="@yield('meta_description', 'Zanini Renovations — carpentry and renovations in Carleton Place and the Ottawa region.')">
  @hasSection('meta_keywords')
  <meta name="keywords" content="@yield('meta_keywords')">
  @endif
  <meta name="theme-color" content="#024768">
  <meta name="robots" content="@yield('meta_robots', 'index,follow')">
  <link rel="canonical" href="@yield('canonical', url()->current())">
  <link rel="shortcut icon" href="{{ url('images/favicon.png') }}">

  @php
    $seoTitle = trim($__env->yieldContent('title')) ?: 'Zanini Renovations';
    $seoDesc = trim($__env->yieldContent('meta_description')) ?: 'Zanini Renovations — licensed carpenter Ottawa, Carleton Place, finish & rough carpentry, O.Reg. 332/12 compliant work.';
    $seoDescOg = \Illuminate\Support\Str::limit($seoDesc, 300, '');
    $ogImagePath = ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
    $ogImageAbs = url($ogImagePath);
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

  @if (filled(config('seo.ga4_measurement_id')))
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('seo.ga4_measurement_id') }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('seo.ga4_measurement_id') }}');
  </script>
  @endif

  @stack('jsonld')

  <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/font-awesome.min.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/owl.carousel.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/owl.theme.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/animate.css') }}" rel="stylesheet" type="text/css">
  <link href="{{ url('css/venobox.css') }}" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="{{ url('css/styles.css') }}" type="text/css">
  @stack('styles')
</head>

<body>

<div id="preloader">
  <span class="margin-bottom"><img src="{{ url('images/loader.gif') }}" alt="Loading......"></span>
</div>

@include('site.partials.public-header')

@yield('content')

@include('site.partials.public-footer')

<a id="backTop" role="button" aria-label="Back to top">Back To Top</a>

<script src="{{ url('js/jquery.min.js') }}"></script>
<script src="{{ url('js/bootstrap.min.js') }}"></script>
<script src="{{ url('js/wow.min.js') }}"></script>
<script src="{{ url('js/jquery.backTop.min.js') }}"></script>
<script src="{{ url('js/waypoints.min.js') }}"></script>
<script src="{{ url('js/waypoints-sticky.min.js') }}"></script>
<script src="{{ url('js/owl.carousel.min.js') }}"></script>
<script src="{{ url('js/jquery.stellar.min.js') }}"></script>
<script src="{{ url('js/jquery.counterup.min.js') }}"></script>
<script src="{{ url('js/venobox.min.js') }}"></script>
<script src="{{ url('js/custom-scripts.js') }}"></script>
@stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="contact-post-url" content="{{ url('/contact-email') }}">
  <title>@yield('title', 'Zanini Renovations')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="@yield('meta_description', 'Zanini Renovations — carpentry and renovations in Carleton Place and the Ottawa region.')">
  <meta name="theme-color" content="#024768">
  <link rel="shortcut icon" href="{{ url('images/favicon.png') }}">

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

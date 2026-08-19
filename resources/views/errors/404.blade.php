@extends('site.layouts.public')

@section('page_title', 'Page not found — Zanini Renovations')
@section('meta_description', 'That page could not be found. Return to Zanini Renovations for carpentry and renovation services in Carleton Place and Ottawa.')
@section('meta_robots', 'noindex, follow')

@section('content')
  <section class="page-header" aria-labelledby="not-found-heading">
    <div class="container">
      <div class="row">
        <div class="col-sm-12 text-center">
          <h3 id="not-found-heading">Page not found</h3>
          <p class="page-breadcrumb">
            <a href="{{ url('/') }}">Home</a> / 404
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-content-block section-404">
    <div class="container">
      <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
          <div class="message-container-404 text-center">
            <p class="text-404" aria-hidden="true">404</p>
            <p class="message-text-404">
              <strong>We could not find that page.</strong>
              The link may be outdated, or the address may be typed incorrectly.
            </p>
            <a href="{{ url('/') }}" class="btn btn-theme">Back to home</a>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

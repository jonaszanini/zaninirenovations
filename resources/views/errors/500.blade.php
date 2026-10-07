@extends('site.layouts.public')

@section('page_title', 'Something went wrong — Zanini Renovations')
@section('meta_description', 'The page could not be loaded. Please try again or contact Zanini Renovations.')
@section('meta_robots', 'noindex, follow')
@section('omit_canonical', '1')

@section('content')
  <section class="page-header" aria-labelledby="server-error-heading">
    <div class="container">
      <div class="row">
        <div class="col-sm-12 text-center">
          <h3 id="server-error-heading">Something went wrong</h3>
          <p class="page-breadcrumb">
            <a href="{{ url('/') }}">Home</a> / 500
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-content-block">
    <div class="container">
      <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
          <div class="message-container-404 text-center">
            <p class="message-text-404">
              <strong>We could not load that page.</strong>
              Please try again in a moment, or return home.
            </p>
            <a href="{{ url('/') }}" class="btn btn-theme">Back to home</a>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

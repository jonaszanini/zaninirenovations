@extends('site.layouts.public')

@section('page_title', 'This quote is being revised – Zanini Renovations')
@section('meta_robots', 'noindex,nofollow')

@push('styles')
<link rel="stylesheet" href="{{ url('css/quote-accept.css') }}?v={{ @filemtime(public_path('css/quote-accept.css')) ?: time() }}">
@endpush

@section('content')
  <main class="qa-page">
    <div class="container">
      <div class="row">
        <div class="col-md-8 col-md-offset-2">
          <div class="qa-card">
            <p class="qa-kicker">Zanini Renovations</p>
            <h1 class="qa-title">This quote is being revised</h1>
            <p class="qa-message">{{ $message ?? \App\Models\ClientProjectQuotation::SOURCE_REVISING_MESSAGE }}</p>
            @include('site.partials.business_contact')
          </div>
        </div>
      </div>
    </div>
  </main>
@endsection

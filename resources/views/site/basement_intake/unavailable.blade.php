@extends('site.layouts.public')

@section('page_title', 'Form not available – Zanini Renovations')
@section('meta_robots', 'noindex, nofollow')

@push('styles')
<link rel="stylesheet" href="{{ url('css/basement-intake.css') }}?v={{ @filemtime(public_path('css/basement-intake.css')) ?: time() }}">
@endpush

@section('content')
  <main class="page-warranty">
    <section class="section-primary-bg page-warranty-hero">
      <div class="container text-center">
        <h1 class="page-warranty-h1">Form not available</h1>
      </div>
    </section>
    <section class="section-secondary-bg page-warranty-body">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 warranty-prose text-center">
            @if (($reason ?? '') === 'preset')
              <p>This quotation does not use the client questionnaire.</p>
            @else
              <p>This link is invalid or has expired. Please ask us to send a new one.</p>
            @endif
            <p class="margin-top-24">{{ config('mail.from.name', 'Zanini Renovations') }} · (647) 834-7570</p>
          </div>
        </div>
      </div>
    </section>
  </main>
@endsection

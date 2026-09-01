@extends('site.layouts.public')

@section('page_title', 'Thank you – Zanini Renovations')
@section('meta_robots', 'noindex, nofollow')

@push('styles')
<link rel="stylesheet" href="{{ url('css/basement-intake.css') }}?v={{ @filemtime(public_path('css/basement-intake.css')) ?: time() }}">
@endpush

@section('content')
  <main class="page-warranty">
    <section class="section-primary-bg page-warranty-hero">
      <div class="container text-center">
        <h1 class="page-warranty-h1">Thank you</h1>
      </div>
    </section>
    <section class="section-secondary-bg page-warranty-body">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 warranty-prose text-center">
            @if (! empty($already))
              <p>We already received your answers for this quotation. If you need to change something, please call or email us.</p>
            @else
              <p>Your answers have been saved. We will use them to finish the quotation and will be in touch shortly.</p>
            @endif
            <p class="margin-top-24">{{ config('mail.from.name', 'Zanini Renovations') }} · (647) 834-7570</p>
          </div>
        </div>
      </div>
    </section>
  </main>
@endsection

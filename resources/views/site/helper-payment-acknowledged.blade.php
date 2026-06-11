@extends('site.layouts.public')

@section('page_title', 'Payment confirmed')

@section('content')
  <main class="page-warranty">
    <section class="section-primary-bg page-warranty-hero">
      <div class="container text-center">
        <h1 class="page-warranty-h1">Thank you</h1>
      </div>
    </section>

    <section class="section-secondary-bg home-contact-palette page-warranty-body">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 warranty-prose text-center">
            @if ($payment->isAcknowledged())
              <p>
                Your confirmation for
                <strong>{{ \App\Models\ClientProject::formatMoney((float) $payment->total_amount) }}</strong>
                on <strong>{{ $payment->project->name }}</strong>
                has been recorded.
              </p>
            @else
              <p>We could not record your confirmation. Please reply to the payment email instead.</p>
            @endif
            <p class="margin-top-24">{{ config('mail.from.name', 'Zanini Renovations') }}</p>
          </div>
        </div>
      </div>
    </section>
  </main>
@endsection

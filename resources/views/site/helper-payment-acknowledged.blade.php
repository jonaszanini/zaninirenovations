@extends('site.layouts.public')

@section('title', 'Payment confirmed')

@section('content')
  <section class="section-padding">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="admin-card" style="max-width:560px;margin:40px auto;padding:32px;">
            <h1 style="font-size:1.5rem;margin-bottom:12px;">Thank you</h1>
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
            <p class="admin-form-help" style="margin-top:16px;">
              {{ config('mail.from.name', 'Zanini Renovations') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

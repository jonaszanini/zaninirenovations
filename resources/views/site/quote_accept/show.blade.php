@extends('site.layouts.public')

@php
  use App\Services\QuoteAcceptanceService;
  $quoteNumber = $quotation->displayQuoteNumber();
  $projectNumber = $project?->referenceCode() ?? '';
  $projectName = $project?->name ?? 'Project';
  $total = $project ? \App\Models\ClientProject::formatMoney((float) $quotation->grandTotal()) : '';
  $pageTitle = 'Accept quote '.$quoteNumber.' – Zanini Renovations';
@endphp

@section('page_title', $pageTitle)
@section('meta_robots', 'noindex,nofollow')
@section('meta_description', 'Review and accept your Zanini Renovations quotation.')

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
            <h1 class="qa-title">
              @if ($status === QuoteAcceptanceService::STATUS_ACCEPTED)
                Quote accepted
              @elseif ($status === QuoteAcceptanceService::STATUS_ALREADY_ACCEPTED)
                Already accepted
              @elseif ($status === QuoteAcceptanceService::STATUS_INVALID_STATE)
                This quote cannot be accepted
              @else
                Accept this quote
              @endif
            </h1>

            <dl class="qa-summary">
              <div class="qa-summary__row">
                <dt>Project</dt>
                <dd>{{ $projectName }}@if ($projectNumber !== '') <span class="qa-id">{{ $projectNumber }}</span>@endif</dd>
              </div>
              <div class="qa-summary__row">
                <dt>Quote number</dt>
                <dd><span class="qa-id">{{ $quoteNumber }}</span></dd>
              </div>
              <div class="qa-summary__row">
                <dt>Total incl. HST</dt>
                <dd>{{ $total }}</dd>
              </div>
            </dl>

            @if ($status === QuoteAcceptanceService::STATUS_ACCEPTED)
              <p class="qa-message">Thank you. This quote is now accepted. We will be in touch with next steps.</p>
            @elseif ($status === QuoteAcceptanceService::STATUS_ALREADY_ACCEPTED)
              <p class="qa-message">This quote is already accepted. No further action is needed.</p>
            @elseif ($status === QuoteAcceptanceService::STATUS_INVALID_STATE)
              <p class="qa-message">This quote is no longer available to accept. Please contact us if you have questions.</p>
            @else
              <form method="post" action="{{ $formAction }}" class="qa-form">
                @csrf
                <button type="submit" class="qa-btn">Accept quote</button>
              </form>
            @endif

            <p class="qa-contact">{{ config('mail.from.name', 'Zanini Renovations') }} · (647) 834-7570</p>
          </div>
        </div>
      </div>
    </div>
  </main>
@endsection

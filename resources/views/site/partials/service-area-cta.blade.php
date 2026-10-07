@php
  $cta = $cta ?? \App\Support\ServiceAreas::cta();
  $ctaUrl = url((string) ($cta['button_url'] ?? '/#contact'));
  $compact = (bool) ($compact ?? false);
@endphp
<aside class="sa-cta{{ $compact ? ' sa-cta--compact' : '' }}" aria-label="Request a quote">
  @unless ($compact)
    <h2 class="sa-cta__heading">{{ $cta['heading'] ?? 'Request a free estimate' }}</h2>
    <p class="sa-cta__text">{{ $cta['text'] ?? '' }}</p>
  @endunless
  <div class="sa-cta__actions">
    <a class="btn btn-theme sa-btn" href="{{ $ctaUrl }}">{{ $cta['button_label'] ?? 'Get a free estimate' }}</a>
    <a class="btn btn-theme sa-btn sa-btn--ghost" href="{{ $cta['phone_href'] ?? 'tel:+16478347570' }}">{{ $cta['phone_label'] ?? 'Call (647) 834-7570' }}</a>
  </div>
</aside>

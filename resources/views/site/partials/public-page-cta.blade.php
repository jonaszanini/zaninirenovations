@php
  $cta = $cta ?? \App\Support\PublicServices::cta();
  $ctaUrl = url((string) ($cta['button_url'] ?? '/contact'));
@endphp
<aside class="sa-cta" aria-label="Request a quote">
  <h2 class="sa-cta__heading">{{ $cta['heading'] ?? 'Get a free estimate' }}</h2>
  @if (! empty($cta['text']))
    <p class="sa-cta__text">{{ $cta['text'] }}</p>
  @endif
  <div class="sa-cta__actions pp-cta-row">
    <a class="btn btn-theme sa-btn" href="{{ $ctaUrl }}">{{ $cta['button_label'] ?? 'Get a free estimate' }}</a>
    <a class="btn btn-theme sa-btn sa-btn--ghost" href="{{ $cta['phone_href'] ?? 'tel:+16478347570' }}">{{ $cta['phone_label'] ?? 'Call (647) 834-7570' }}</a>
    @if (! empty($cta['email_href']))
      <a class="btn btn-theme sa-btn sa-btn--ghost" href="{{ $cta['email_href'] }}">{{ $cta['email_label'] ?? 'Email us' }}</a>
    @endif
  </div>
</aside>

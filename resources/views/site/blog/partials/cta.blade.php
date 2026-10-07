@php
  $cta = $cta ?? \App\Support\BlogPublicContent::cta($post);
@endphp
<aside class="blog-cta" aria-label="Get a free estimate">
  <h2 class="blog-cta__title">{{ $cta['title'] }}</h2>
  <p class="blog-cta__text">{{ $cta['text'] }}</p>
  <div class="blog-cta__actions">
    <a class="btn btn-theme blog-cta__estimate" href="{{ url(config('blog.estimate_url', '/#contact-location')) }}">Free estimate</a>
    <a class="blog-cta__call" href="tel:{{ config('blog.phone_tel', '+16478347570') }}">Call {{ config('blog.phone_display', '(647) 834-7570') }}</a>
  </div>
</aside>

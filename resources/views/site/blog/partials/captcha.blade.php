@if (($captchaMode ?? 'math') === 'math' && ! empty($captchaChallenge))
  <p class="blog-captcha">
    <label for="{{ $captchaAnswerId }}">What is {{ $captchaChallenge['a'] }} + {{ $captchaChallenge['b'] }}?</label>
    <input type="number" id="{{ $captchaAnswerId }}" name="captcha_answer" class="form-control" required style="max-width:120px;display:inline-block;">
    <input type="hidden" name="captcha_token" value="{{ $captchaChallenge['token'] }}">
  </p>
@elseif (($captchaMode ?? '') === 'recaptcha_v2' && filled(config('blog.recaptcha_site_key')))
  <div class="g-recaptcha" data-sitekey="{{ config('blog.recaptcha_site_key') }}"></div>
@endif

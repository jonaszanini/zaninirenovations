@php
  $form = $form ?? [];
  $raw = old($name, array_key_exists($name, $form) ? $form[$name] : null);
  if ($raw === 'client') {
      $current = 'client';
  } elseif ($raw === 'we') {
      $current = 'we';
  } else {
      $current = null;
  }
@endphp
<div class="bi-field" @if (! empty($reveal)) data-bi-reveal="{{ $reveal }}" @endif @if (! empty($hideWhen)) data-bi-hide-when="{{ $hideWhen }}" @endif>
  <span class="bi-legend">{{ $label }}</span>
  <div class="bi-choices" role="radiogroup" aria-required="true" aria-label="{{ $label }}">
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="we" @checked($current === 'we') required>
      <span>Zanini supplies it</span>
    </label>
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="client" @checked($current === 'client')>
      <span>I will provide it</span>
    </label>
  </div>
</div>

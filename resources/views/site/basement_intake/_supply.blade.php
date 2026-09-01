@php
  $current = old($name, $form[$name] ?? 'we');
  $current = $current === 'client' ? 'client' : 'we';
@endphp
<div class="bi-field" @if (! empty($reveal)) data-bi-reveal="{{ $reveal }}" @endif @if (! empty($hideWhen)) data-bi-hide-when="{{ $hideWhen }}" @endif>
  <span class="bi-legend">{{ $label }}</span>
  <div class="bi-choices" role="radiogroup" aria-label="{{ $label }}">
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="we" @checked($current === 'we')>
      <span>Zanini supplies it</span>
    </label>
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="client" @checked($current === 'client')>
      <span>I will provide it</span>
    </label>
  </div>
</div>

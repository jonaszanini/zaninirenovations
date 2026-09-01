@php
  $yes = (string) ($yes ?? '1');
  $no = (string) ($no ?? '0');
  $current = old($name, $form[$name] ?? $default ?? '0');
  $current = ($current === true || $current === 1 || $current === '1' || $current === 'yes') ? $yes : $no;
@endphp
<div class="bi-field">
  <span class="bi-legend">{{ $label }}</span>
  <div class="bi-choices" role="radiogroup" aria-label="{{ $label }}">
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="{{ $yes }}" @checked($current === $yes)>
      <span>{{ $yesLabel ?? 'Yes' }}</span>
    </label>
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="{{ $no }}" @checked($current === $no)>
      <span>{{ $noLabel ?? 'No' }}</span>
    </label>
  </div>
</div>

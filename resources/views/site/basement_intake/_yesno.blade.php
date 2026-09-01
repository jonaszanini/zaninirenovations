@php
  $yes = (string) ($yes ?? '1');
  $no = (string) ($no ?? '0');
  $form = $form ?? [];
  $raw = old($name, array_key_exists($name, $form) ? $form[$name] : null);
  if ($raw === null || $raw === '') {
      $current = null;
  } elseif ($raw === true || $raw === 1 || $raw === '1' || $raw === 'yes') {
      $current = $yes;
  } else {
      $current = $no;
  }
@endphp
<div class="bi-field">
  <span class="bi-legend">{{ $label }}</span>
  <div class="bi-choices" role="radiogroup" aria-required="true" aria-label="{{ $label }}">
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="{{ $yes }}" @checked($current === $yes) required>
      <span>{{ $yesLabel ?? 'Yes' }}</span>
    </label>
    <label class="bi-choice">
      <input type="radio" name="{{ $name }}" value="{{ $no }}" @checked($current === $no)>
      <span>{{ $noLabel ?? 'No' }}</span>
    </label>
  </div>
</div>

@php
  $form = $form ?? [];
  $modeName = $modeName ?? ($prefix.'_mode');
  $rawMode = old($modeName, array_key_exists($modeName, $form) ? $form[$modeName] : null);
  $mode = $rawMode === 'dimensions' ? 'dimensions' : ($rawMode === 'sqft' ? 'sqft' : null);
  $sqftName = $sqftName ?? ($prefix.'_sqft');
  $lenName = $lenName ?? ($prefix.'_length_ft');
  $widName = $widName ?? ($prefix.'_width_ft');
  $areaMin = $areaMin ?? '0';
@endphp
<div class="bi-field" data-bi-area="{{ $prefix }}">
  <span class="bi-legend">{{ $label }}</span>
  <div class="bi-choices" role="radiogroup" aria-required="true" aria-label="How to enter {{ $label }}">
    <label class="bi-choice">
      <input type="radio" name="{{ $modeName }}" value="sqft" @checked($mode === 'sqft') required>
      <span>Total sq ft</span>
    </label>
    <label class="bi-choice">
      <input type="radio" name="{{ $modeName }}" value="dimensions" @checked($mode === 'dimensions')">
      <span>Length × width</span>
    </label>
  </div>
  <div class="bi-grid bi-grid--2 bi-area-inputs">
    <div data-bi-sqft @if($mode !== 'sqft') hidden @endif>
      <label for="{{ $sqftName }}">Square feet</label>
      <input type="number" step="0.01" min="{{ $areaMin }}" class="form-control" id="{{ $sqftName }}" name="{{ $sqftName }}"
             value="{{ old($sqftName, $form[$sqftName] ?? '') }}" placeholder="0" required>
    </div>
    <div class="bi-grid bi-grid--2" data-bi-dims @if($mode !== 'dimensions') hidden @endif>
      <div>
        <label for="{{ $lenName }}">Length (ft)</label>
        <input type="number" step="0.01" min="{{ $areaMin }}" class="form-control" id="{{ $lenName }}" name="{{ $lenName }}"
               value="{{ old($lenName, $form[$lenName] ?? '') }}" required>
      </div>
      <div>
        <label for="{{ $widName }}">Width (ft)</label>
        <input type="number" step="0.01" min="{{ $areaMin }}" class="form-control" id="{{ $widName }}" name="{{ $widName }}"
               value="{{ old($widName, $form[$widName] ?? '') }}" required>
      </div>
    </div>
  </div>
</div>

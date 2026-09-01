@php
  $modeName = $modeName ?? ($prefix.'_mode');
  $mode = old($modeName, $form[$modeName] ?? 'sqft');
  $mode = $mode === 'dimensions' ? 'dimensions' : 'sqft';
  $sqftName = $sqftName ?? ($prefix.'_sqft');
  $lenName = $lenName ?? ($prefix.'_length_ft');
  $widName = $widName ?? ($prefix.'_width_ft');
@endphp
<div class="bi-field" data-bi-area="{{ $prefix }}">
  <span class="bi-legend">{{ $label }}</span>
  <div class="bi-choices" role="radiogroup" aria-label="How to enter {{ $label }}">
    <label class="bi-choice">
      <input type="radio" name="{{ $modeName }}" value="sqft" @checked($mode === 'sqft')>
      <span>Total sq ft</span>
    </label>
    <label class="bi-choice">
      <input type="radio" name="{{ $modeName }}" value="dimensions" @checked($mode === 'dimensions')>
      <span>Length × width</span>
    </label>
  </div>
  <div class="bi-grid bi-grid--2 bi-area-inputs">
    <div data-bi-sqft @if($mode === 'dimensions') hidden @endif>
      <label for="{{ $sqftName }}">Square feet</label>
      <input type="number" step="0.01" min="0" class="form-control" id="{{ $sqftName }}" name="{{ $sqftName }}"
             value="{{ old($sqftName, $form[$sqftName] ?? '') }}" placeholder="0">
    </div>
    <div class="bi-grid bi-grid--2" data-bi-dims @if($mode !== 'dimensions') hidden @endif>
      <div>
        <label for="{{ $lenName }}">Length (ft)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="{{ $lenName }}" name="{{ $lenName }}"
               value="{{ old($lenName, $form[$lenName] ?? '') }}">
      </div>
      <div>
        <label for="{{ $widName }}">Width (ft)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="{{ $widName }}" name="{{ $widName }}"
               value="{{ old($widName, $form[$widName] ?? '') }}">
      </div>
    </div>
  </div>
</div>

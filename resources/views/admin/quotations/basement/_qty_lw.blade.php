{{--
  Square-footage field with a compact Total sq ft / Length × width toggle.

  @param string      $name
  @param string      $id
  @param string      $label
  @param mixed       $value
  @param string      $modeName
  @param string      $lengthName
  @param string      $widthName
  @param string      $mode
  @param mixed       $lengthValue
  @param mixed       $widthValue
  @param string|null $placeholder
  @param string|null $help
  @param string|null $labelId
  @param string|null $title
  @param bool        $zeroIfIncomplete  Work area: incomplete L×W zeros the qty
--}}
@php
  $mode = \App\Services\BasementQuotationCalculator::normalizeAreaMode($mode ?? 'sqft');
  $lengthValue = $lengthValue ?? '';
  $widthValue = $widthValue ?? '';
  $placeholder = $placeholder ?? '';
  $zeroIfIncomplete = ! empty($zeroIfIncomplete);
  $computed = ($mode === 'dimensions' && $lengthValue !== '' && $widthValue !== '' && (float) $lengthValue > 0 && (float) $widthValue > 0)
      ? rtrim(rtrim(number_format((float) $lengthValue * (float) $widthValue, 2, '.', ''), '0'), '.').' sq ft'
      : 'Enter length and width';
@endphp
<div class="admin-form-group br-lw" data-br-lw @if($zeroIfIncomplete) data-br-lw-zero="1" @endif>
  <div class="br-lw__head">
    <label for="{{ $id }}" @if(! empty($labelId)) id="{{ $labelId }}" @endif>{{ $label }}</label>
    <div class="br-lw-segment" role="radiogroup" aria-label="{{ $label }} entry">
      <label class="br-lw-segment__item">
        <input type="radio" class="br-preview" name="{{ $modeName }}" value="sqft"
               data-br-lw-mode @checked($mode === 'sqft')>
        <span>Total sq ft</span>
      </label>
      <label class="br-lw-segment__item">
        <input type="radio" class="br-preview" name="{{ $modeName }}" value="dimensions"
               data-br-lw-mode @checked($mode === 'dimensions')>
        <span>Length × width</span>
      </label>
    </div>
  </div>
  <div data-br-lw-sqft @if($mode === 'dimensions') hidden @endif>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="{{ $id }}" name="{{ $name }}"
           data-br-lw-qty
           value="{{ $value }}"
           @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif
           @if(! empty($title)) title="{{ $title }}" @endif>
    @if (! empty($help))
      <p class="admin-form-help">{{ $help }}</p>
    @endif
  </div>
  <div class="br-lw__dims" data-br-lw-dims @unless($mode === 'dimensions') hidden @endunless>
    <div class="admin-form-group">
      <label for="{{ $id }}-length">Length (ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="{{ $id }}-length"
             name="{{ $lengthName }}" data-br-lw-length
             value="{{ $lengthValue }}" placeholder="e.g. 12">
    </div>
    <div class="admin-form-group">
      <label for="{{ $id }}-width">Width (ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="{{ $id }}-width"
             name="{{ $widthName }}" data-br-lw-width
             value="{{ $widthValue }}" placeholder="e.g. 10">
      <p class="admin-form-help" data-br-lw-computed>{{ $computed }}</p>
    </div>
  </div>
</div>

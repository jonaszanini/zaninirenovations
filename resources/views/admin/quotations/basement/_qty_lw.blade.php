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
  @param string|null $info            Explanation for the information icon
  @param string|null $labelId
  @param string|null $title
  @param bool        $zeroIfIncomplete  Work area: incomplete L×W zeros the qty
  @param string|null $companion         Optional HTML for a third field (e.g. ceiling height)
  @param bool        $clientFilled
--}}
@php
  $mode = \App\Services\BasementQuotationCalculator::normalizeAreaMode($mode ?? 'sqft');
  $lengthValue = $lengthValue ?? '';
  $widthValue = $widthValue ?? '';
  $placeholder = $placeholder ?? '';
  $zeroIfIncomplete = ! empty($zeroIfIncomplete);
  $companion = $companion ?? null;
  $clientFilled = $clientFilled ?? false;
  $computed = ($mode === 'dimensions' && $lengthValue !== '' && $widthValue !== '' && (float) $lengthValue > 0 && (float) $widthValue > 0)
      ? rtrim(rtrim(number_format((float) $lengthValue * (float) $widthValue, 2, '.', ''), '0'), '.').' sq ft'
      : 'Enter length and width';
@endphp
<div class="admin-form-group br-lw{{ $companion ? ' br-lw--with-companion' : '' }}" data-br-lw @if($zeroIfIncomplete) data-br-lw-zero="1" @endif>
  <div class="br-lw__head">
    <label for="{{ $id }}" @if(! empty($labelId)) id="{{ $labelId }}" @endif>
      @include('admin.partials.info_tip', ['text' => $info ?? null])
      @if (! empty($clientFilled))
        @include('admin.quotations.basement._client_filled', ['force' => true])
      @endif
      {{ $label }}
    </label>
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

  <div class="br-lw__fields">
    <div class="br-lw__sqft" data-br-lw-sqft @if($mode === 'dimensions') hidden @endif>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="{{ $id }}" name="{{ $name }}"
             data-br-lw-qty
             value="{{ $value }}"
             @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif
             @if(! empty($title)) title="{{ $title }}" @endif>
    </div>
    <div class="br-lw__dims" data-br-lw-dims @unless($mode === 'dimensions') hidden @endunless>
      <div class="admin-form-group br-lw__dim">
        <label for="{{ $id }}-length">Length (ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="{{ $id }}-length"
               name="{{ $lengthName }}" data-br-lw-length
               value="{{ $lengthValue }}" placeholder="e.g. 12">
      </div>
      <div class="admin-form-group br-lw__dim">
        <label for="{{ $id }}-width">Width (ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="{{ $id }}-width"
               name="{{ $widthName }}" data-br-lw-width
               value="{{ $widthValue }}" placeholder="e.g. 10">
      </div>
    </div>
    @if (filled($companion))
      <div class="br-lw__companion">
        {!! $companion !!}
      </div>
    @endif
  </div>

  <p class="admin-form-help br-lw__computed" data-br-lw-computed @if($mode !== 'dimensions') hidden @endif>{{ $computed }}</p>
  @if (! empty($help))
    <p class="admin-form-help br-lw__help">{{ $help }}</p>
  @endif
</div>

{{--
  Editable unit rate for this quotation only. Prefills with the shared rate.
  Shared price records are not changed from here.

  @param list<string> $rateKeys
--}}
@php
  use App\Models\BasementSettings;
  use App\Services\BasementQuoteRateOverrides;

  $rateKeys = array_values(array_filter($rateKeys ?? []));
  $settingsOwner = isset($settings) && $settings
      ? (float) $settings->owner_hourly_rate
      : (float) BasementSettings::current()->owner_hourly_rate;
  $storedOverrides = is_array(($inputs['rate_overrides'] ?? null)) ? $inputs['rate_overrides'] : [];
@endphp
@foreach ($rateKeys as $rateKey)
  @php
    $shared = BasementQuoteRateOverrides::sharedUnitRate($rateKey, $settingsOwner);
    $sharedDisplay = $shared === null ? '' : number_format($shared, 2, '.', '');
    $posted = old('rate_overrides.'.$rateKey);
    $stored = $storedOverrides[$rateKey] ?? null;
    $valueRaw = $posted !== null ? $posted : ($stored !== null ? $stored : $sharedDisplay);
    $parsed = BasementQuoteRateOverrides::parseMoney($valueRaw);
    $valueNum = $parsed === null ? null : round(max(0, $parsed), 2);
    $isCustom = $valueNum !== null && $shared !== null && abs($valueNum - round((float) $shared, 2)) >= 0.001;
    $unit = BasementQuoteRateOverrides::unitLabel($rateKey);
    $short = BasementQuoteRateOverrides::shortLabel($rateKey);
    $rateLabel = ($short !== '' ? $short.' ' : '').'CA$ / '.$unit;
    $tipLabel = ($short !== '' ? $short.' ' : '').'unit rate for this quotation';
  @endphp
  <div class="br-rate{{ $isCustom ? ' is-custom' : '' }}" data-br-rate="{{ $rateKey }}">
    <span class="admin-label-with-tip br-rate__label">
      <label for="br-rate-{{ $rateKey }}">{{ $rateLabel }}</label>
      @include('admin.partials.info_tip', [
        'tipText' => 'Unit rate for this quotation only. Shared prices stay unchanged.',
        'tipLabel' => $tipLabel,
      ])
    </span>
    <input
      type="text"
      inputmode="decimal"
      autocomplete="off"
      class="admin-input cp-money-input br-preview br-rate__input"
      id="br-rate-{{ $rateKey }}"
      name="rate_overrides[{{ $rateKey }}]"
      value="{{ $valueNum !== null ? number_format($valueNum, 2, '.', '') : '' }}"
      data-br-rate-input="{{ $rateKey }}"
      data-br-rate-shared="{{ $sharedDisplay }}"
      data-cp-money-entry="direct"
    >
    <span class="br-rate__custom" data-br-rate-custom @unless($isCustom) hidden @endunless>
      <span class="br-rate__dot" aria-hidden="true"></span>
      Custom
    </span>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm br-rate__reset" data-br-rate-reset @unless($isCustom) hidden @endunless>
      Reset
    </button>
  </div>
@endforeach

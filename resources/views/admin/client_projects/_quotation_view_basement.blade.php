{{-- Basement renovation quotation — read-only detail for the View drawer.
     Expects: $sourceModel (BasementQuotation) --}}
@php
  $inp     = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $scope   = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
  $eta     = is_array($calc['eta']['scenarios'] ?? null) ? $calc['eta']['scenarios'] : [];
  $area    = (float) ($scope['area_sqft'] ?? $inp['area_sqft'] ?? 0);
  $labourPre = (float) ($summary['labor_pre_hst'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $matPre    = (float) ($summary['materials_pre_hst'] ?? $sourceModel->materialsPreTaxAmountForProjectQuotation());
  $laborRows = array_filter($calc['labor'] ?? [], fn ($r) => is_array($r) && (float) ($r['hours'] ?? 0) > 0);
@endphp

<div class="cp-vd-section">
  <div class="cp-vd-section__title">Project inputs</div>
  <div class="cp-vd-grid">
    @if ($sourceModel->client_name)
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Client name</span>
        <span class="cp-vd-field__value">{{ $sourceModel->client_name }}</span>
      </div>
    @endif
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Area</span>
      <span class="cp-vd-field__value">{{ $area > 0 ? number_format($area, 0).' sq ft' : '—' }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Ceiling height</span>
      <span class="cp-vd-field__value">{{ number_format((float) ($scope['ceiling_height_ft'] ?? $inp['ceiling_height_ft'] ?? 0), 2) }} ft</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Finished rooms / zones</span>
      <span class="cp-vd-field__value">{{ (int) ($scope['room_count'] ?? $inp['room_count'] ?? 0) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Finish</span>
      <span class="cp-vd-field__value">{{ $scope['finish_label'] ?? $sourceModel->summaryDescription() }}</span>
    </div>
  </div>
</div>

@if (! empty($laborRows))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Labor</div>
    <ul class="cp-vd-sides-list">
      @foreach ($laborRows as $row)
        <li class="cp-vd-side-chip">{{ $row['label'] }} — {{ number_format((float) $row['hours'], 2) }} hr</li>
      @endforeach
    </ul>
    <p class="admin-form-help" style="margin-top:8px;">Labor (pre-HST): {{ \App\Models\ClientProject::formatMoney($labourPre) }}</p>
  </div>
@endif

<div class="cp-vd-section">
  <div class="cp-vd-section__title">Totals</div>
  <div class="cp-vd-grid">
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Materials (pre-HST)</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matPre) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Materials + HST</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($summary['materials_after_hst'] ?? 0) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Grand total</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($sourceModel->final_quote_total) }}</span>
    </div>
  </div>
  @if ($eta)
    <div style="margin-top:12px;">
      <span class="cp-vd-field__label" style="display:block;margin-bottom:6px;">Duration</span>
      <ul class="cp-vd-sides-list">
        @foreach ($eta as $sc)
          <li class="cp-vd-side-chip">
            {{ $sc['label'] }} — {{ number_format((float) ($sc['calendar_days'] ?? 0), 1) }} days
            ({{ \App\Models\ClientProject::formatMoney($sc['labor_cost'] ?? 0) }})
          </li>
        @endforeach
      </ul>
    </div>
  @endif
</div>

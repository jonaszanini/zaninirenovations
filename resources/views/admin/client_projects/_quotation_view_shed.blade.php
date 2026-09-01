{{-- Shed quotation — read-only detail for the View drawer.
     Expects: $sourceModel (ShedQuotation) --}}
@php
  $inp     = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $scope   = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
  $area    = (float) ($scope['area_sqft'] ?? $sourceModel->areaSqft());
  $labourPre = (float) ($summary['labor_pre_hst'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $matPre    = (float) ($summary['materials_pre_hst'] ?? $sourceModel->materialsPreTaxAmountForProjectQuotation());
  $laborRows = array_filter($calc['labor'] ?? [], fn ($r) => is_array($r) && (float) ($r['hours'] ?? 0) > 0);
  $len = (float) ($scope['length_ft'] ?? $inp['length_ft'] ?? 0);
  $wid = (float) ($scope['width_ft'] ?? $inp['width_ft'] ?? 0);
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
      <span class="cp-vd-field__label">Type</span>
      <span class="cp-vd-field__value">{{ $scope['shed_type_label'] ?? \App\Services\ShedQuotationCalculator::shedTypeLabel((string) ($inp['shed_type'] ?? 'gable')) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Size</span>
      <span class="cp-vd-field__value">
        @if ($len > 0 && $wid > 0)
          {{ rtrim(rtrim(number_format($len, 2, '.', ''), '0'), '.') }} × {{ rtrim(rtrim(number_format($wid, 2, '.', ''), '0'), '.') }} ft
          ({{ $area > 0 ? number_format($area, 0).' sq ft' : '—' }})
        @else
          {{ $area > 0 ? number_format($area, 0).' sq ft' : '—' }}
        @endif
      </span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Foundation</span>
      <span class="cp-vd-field__value">{{ \App\Services\ShedQuotationCalculator::foundationLabel((string) ($inp['foundation'] ?? 'skids')) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Finish</span>
      <span class="cp-vd-field__value">{{ $scope['finish_label'] ?? $sourceModel->summaryDescription() }}</span>
    </div>
  </div>
</div>

@if (! empty($scope['permit_warnings']))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Permit notes</div>
    <ul class="cp-vd-sides-list">
      @foreach ($scope['permit_warnings'] as $warning)
        <li class="cp-vd-side-chip">{{ $warning }}</li>
      @endforeach
    </ul>
  </div>
@endif

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
      <span class="cp-vd-field__label">Grand total</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($sourceModel->final_quote_total) }}</span>
    </div>
  </div>
</div>

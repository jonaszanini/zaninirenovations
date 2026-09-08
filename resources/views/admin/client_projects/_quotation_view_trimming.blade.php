{{-- Trimming quotation — read-only detail for the View drawer.
     Expects: $sourceModel (TrimmingQuotation) --}}
@php
  $inp     = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $scope   = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
  $labourPre = (float) ($summary['labor_pre_hst'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $matPre    = (float) ($summary['materials_pre_hst'] ?? $sourceModel->materialsPreTaxAmountForProjectQuotation());
  $laborRows = array_filter($calc['labor'] ?? [], fn ($r) => is_array($r) && (float) ($r['hours'] ?? 0) > 0);
  $families = is_array($scope['families'] ?? null) ? $scope['families'] : [];
  $familyLabels = [];
  foreach ($families as $key) {
      $label = \App\Services\TrimmingQuotationCalculator::FAMILIES[$key] ?? null;
      if (is_string($label) && $label !== '') {
          $familyLabels[] = $label;
      }
  }
@endphp

<div class="cp-vd-section">
  <div class="cp-vd-section__title">Project inputs</div>
  <div class="cp-vd-grid">
    @if ($sourceModel->client_name)
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Client name</span>
        <span class="cp-vd-field__value">@include('admin.partials.client_directory_link', ['project' => $project ?? null, 'label' => $sourceModel->client_name])</span>
      </div>
    @endif
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Families</span>
      <span class="cp-vd-field__value">{{ $familyLabels !== [] ? implode(', ', $familyLabels) : '—' }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Measured trim</span>
      <span class="cp-vd-field__value">{{ number_format((float) ($scope['total_lf'] ?? 0), 0) }} lf</span>
    </div>
    @if ((int) ($scope['door_qty'] ?? 0) > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Door openings</span>
        <span class="cp-vd-field__value">{{ (int) $scope['door_qty'] }}</span>
      </div>
    @endif
    @if ((int) ($scope['window_qty'] ?? 0) > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Window openings</span>
        <span class="cp-vd-field__value">{{ (int) $scope['window_qty'] }}</span>
      </div>
    @endif
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
      <span class="cp-vd-field__label">Grand total</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($sourceModel->final_quote_total) }}</span>
    </div>
  </div>
</div>

{{-- Flooring quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (FlooringQuotation) --}}
@php
  $inp     = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $scope   = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

  // Flooring type
  $ftType = $sourceModel->flooringType();

  // Areas
  $sqft    = (float) ($scope['square_footage'] ?? $inp['square_footage'] ?? 0);

  // Material selection (client-supplied)
  $matSel  = $sourceModel->materialSelectionForPdf();

  // Labour breakdown
  $labourRows = array_filter($calc['breakdown'] ?? [], fn ($r) => is_array($r) && ($r['section'] ?? '') === 'labor');

  // Totals
  $labourPre = (float) ($summary['labor_pre_hst'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $matPre    = (float) ($summary['materials_pre_hst'] ?? $sourceModel->materialsPreTaxAmountForProjectQuotation());
  $finalTotal= (float) $sourceModel->final_quote_total;
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
      <span class="cp-vd-field__label">Flooring type</span>
      <span class="cp-vd-field__value">{{ $ftType?->name ?? '—' }}</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Total area</span>
      <span class="cp-vd-field__value">{{ number_format($sqft, 0) }} sq ft</span>
    </div>
  </div>

  @if (!empty($labourRows))
    <div style="margin-top:12px;">
      <span class="cp-vd-field__label" style="display:block;margin-bottom:6px;">Labour breakdown</span>
      <ul class="cp-vd-sides-list">
        @foreach ($labourRows as $row)
          @php $lbl = trim((string) ($row['label'] ?? '')); $amt = (float) ($row['amount'] ?? 0); @endphp
          @if ($lbl)
            <li class="cp-vd-side-chip">{{ $lbl }} — {{ \App\Models\ClientProject::formatMoney($amt) }}</li>
          @endif
        @endforeach
      </ul>
    </div>
  @endif
</div>

{{-- Client-supplied material --}}
@if ($matSel)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Client-supplied material</div>
    <div class="cp-vd-grid">
      @if ($matSel['name'])
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Product name</span>
          <span class="cp-vd-field__value">{{ $matSel['name'] }}</span>
        </div>
      @endif
      @if ($matSel['color'])
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Color</span>
          <span class="cp-vd-field__value">{{ $matSel['color'] }}</span>
        </div>
      @endif
      @if ($matSel['cost_per_sqft'] > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Cost/sq ft</span>
          <span class="cp-vd-field__value">${{ number_format($matSel['cost_per_sqft'], 4) }}</span>
        </div>
      @endif
      @if ($matSel['waste_percent'] > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Waste factor</span>
          <span class="cp-vd-field__value">{{ $matSel['waste_percent'] }}%</span>
        </div>
      @endif
      @if ($matSel['delivery_fee'] > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Delivery fee</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matSel['delivery_fee']) }}</span>
        </div>
      @endif
      @if ($matSel['total_pre_tax'] > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Material total (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matSel['total_pre_tax']) }}</span>
        </div>
      @endif
      @if ($matSel['url'])
        <div class="cp-vd-field cp-vd-field--full">
          <span class="cp-vd-field__label">Product link</span>
          <span class="cp-vd-field__value">
            <a href="{{ $matSel['url'] }}" target="_blank" rel="noopener" style="color:#024768;">
              <i class="fa fa-external-link" aria-hidden="true"></i> Open product page
            </a>
          </span>
        </div>
      @endif
    </div>
  </div>
@endif

{{-- Summary --}}
@if ($labourPre > 0 || $matPre > 0 || $finalTotal > 0)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Calculator summary</div>
    <div class="cp-vd-grid">
      @if ($matPre > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Materials (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matPre) }}</span>
        </div>
      @endif
      @if ($labourPre > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Labour (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($labourPre) }}</span>
        </div>
      @endif
      <div class="cp-vd-field cp-vd-field--highlight">
        <span class="cp-vd-field__label">Final quote total (incl. HST)</span>
        <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($finalTotal) }}</span>
      </div>
    </div>
  </div>
@endif

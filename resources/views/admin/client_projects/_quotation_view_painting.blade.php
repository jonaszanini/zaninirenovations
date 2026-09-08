{{-- Painting quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (PaintingQuotation) --}}
@php
  $inp     = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $scope   = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

  // Paint line
  $paint = $sourceModel->paintLine();

  // Areas
  $totalArea    = (float) ($scope['total_property_sqf'] ?? $inp['total_property_sqf'] ?? 0);
  $netArea      = (float) ($scope['net_painting_area_sqf'] ?? 0);
  $excludedArea = (float) ($scope['excluded_total_sqf'] ?? 0);
  $excludedAreas = $scope['excluded_areas'] ?? [];
  $coats        = (int) ($scope['coats'] ?? 2);

  // Surface prep
  $surfacePrep  = trim((string) ($inp['surface_prep_description'] ?? ''));

  // Totals
  $finalTotal  = (float) ($summary['subtotal_pre_hst'] ?? 0) * (1 + \App\Models\PaintingQuotation::HST_RATE);
  if ($finalTotal <= 0) $finalTotal = (float) $sourceModel->final_quote_total;
  $labourPre   = (float) ($summary['labor_pre_hst'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $matPre      = (float) ($summary['materials_pre_hst'] ?? $sourceModel->materialsPreTaxAmountForProjectQuotation());
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
      <span class="cp-vd-field__label">Paint line</span>
      <span class="cp-vd-field__value">{{ $paint?->name ?? '—' }}</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Total property area</span>
      <span class="cp-vd-field__value">{{ number_format($totalArea, 0) }} sq ft</span>
    </div>

    @if ($excludedArea > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Excluded area</span>
        <span class="cp-vd-field__value">{{ number_format($excludedArea, 0) }} sq ft</span>
      </div>
    @endif

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Net painting area</span>
      <span class="cp-vd-field__value">{{ number_format($netArea, 0) }} sq ft</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Top coats</span>
      <span class="cp-vd-field__value">{{ $coats }}</span>
    </div>
  </div>

  @if (!empty($excludedAreas) && $excludedArea > 0)
    <div style="margin-top:10px;">
      <span class="cp-vd-field__label" style="display:block;margin-bottom:6px;">Excluded areas</span>
      <ul class="cp-vd-sides-list">
        @foreach ($excludedAreas as $ea)
          @if (!is_array($ea)) @continue @endif
          @php
            $eaname = trim((string) ($ea['name'] ?? ''));
            $eadet  = trim((string) ($ea['detail'] ?? ''));
          @endphp
          @if ($eaname)
            <li class="cp-vd-side-chip">{{ $eaname }}{{ $eadet ? ' ('.$eadet.')' : '' }}</li>
          @endif
        @endforeach
      </ul>
    </div>
  @endif

  @if ($surfacePrep)
    <div style="margin-top:12px;">
      <span class="cp-vd-field__label" style="display:block;margin-bottom:4px;">Surface preparation</span>
      <div class="cp-vd-field-value--multiline">{{ $surfacePrep }}</div>
    </div>
  @endif
</div>

{{-- Calculator summary --}}
@if ($labourPre > 0 || $matPre > 0)
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

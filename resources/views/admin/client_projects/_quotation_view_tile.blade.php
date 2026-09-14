{{-- Tile quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (TileQuotation) --}}
@php
  $inp  = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];

  // Key inputs
  $sqft          = (float) ($calc['total_area'] ?? $inp['square_footage'] ?? 0);
  $wastePct      = (float) ($inp['waste_factor'] ?? 10);
  $labourRate    = (float) ($inp['labour_rate_per_sqft'] ?? 0);
  $tileProduct   = trim((string) ($inp['tile_product_name'] ?? ''));
  $tileColor     = trim((string) ($inp['tile_color'] ?? ''));
  $tileCostSqft  = (float) ($inp['tile_cost_per_sqft'] ?? 0);
  $mortarType    = trim((string) ($inp['mortar_type_name'] ?? ($calc['mortar_type'] ?? '')));
  $groutType     = trim((string) ($inp['grout_type_name'] ?? ($calc['grout_type'] ?? '')));
  $groutColor    = trim((string) ($inp['grout_color'] ?? ''));

  // Calculated totals
  $tileTotal   = (float) ($calc['tile_total_price'] ?? 0);
  $mortarTotal = (float) ($calc['mortar_total_price'] ?? 0);
  $groutTotal  = (float) ($calc['grout_total_price'] ?? 0);
  $labourTotal = (float) ($calc['labor_total'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $commuteAmount = (float) ($sourceModel->commute_amount ?? 0);
  $includeCommute = (bool) ($sourceModel->include_commute ?? false);
  $finalTotal  = (float) $sourceModel->final_quote_total;
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
      <span class="cp-vd-field__label">Total area</span>
      <span class="cp-vd-field__value">{{ number_format($sqft, 0) }} sq ft</span>
    </div>

    @if ($wastePct > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Waste factor</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($wastePct, 2), '0'), '.') }}%</span>
      </div>
    @endif

    @if ($labourRate > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Labour rate</span>
        <span class="cp-vd-field__value">${{ number_format($labourRate, 2) }}/sq ft</span>
      </div>
    @endif
  </div>
</div>

{{-- Tile product --}}
@if ($tileProduct || $tileCostSqft > 0)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Tile</div>
    <div class="cp-vd-grid">
      @if ($tileProduct)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Product</span>
          <span class="cp-vd-field__value">{{ $tileProduct }}</span>
        </div>
      @endif
      @if ($tileColor)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Color</span>
          <span class="cp-vd-field__value">{{ $tileColor }}</span>
        </div>
      @endif
      @if ($tileCostSqft > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Cost/sq ft</span>
          <span class="cp-vd-field__value">${{ number_format($tileCostSqft, 4) }}</span>
        </div>
      @endif
      @if ($tileTotal > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Tile total (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($tileTotal) }}</span>
        </div>
      @endif
    </div>
  </div>
@endif

{{-- Mortar & grout --}}
@if ($mortarType || $groutType)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Mortar &amp; grout</div>
    <div class="cp-vd-grid">
      @if ($mortarType)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Mortar type</span>
          <span class="cp-vd-field__value">{{ $mortarType }}</span>
        </div>
      @endif
      @if ($mortarTotal > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Mortar cost</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($mortarTotal) }}</span>
        </div>
      @endif
      @if ($groutType)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Grout type</span>
          <span class="cp-vd-field__value">{{ $groutType }}</span>
        </div>
      @endif
      @if ($groutColor)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Grout color</span>
          <span class="cp-vd-field__value">{{ $groutColor }}</span>
        </div>
      @endif
      @if ($groutTotal > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Grout cost</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($groutTotal) }}</span>
        </div>
      @endif
    </div>
  </div>
@endif

{{-- Summary --}}
@if ($finalTotal > 0)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Calculator summary</div>
    <div class="cp-vd-grid">
      @if ($labourTotal > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Labour (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($labourTotal) }}</span>
        </div>
      @endif
      @if ($includeCommute && $commuteAmount > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Commute (incl. HST)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($commuteAmount) }}</span>
        </div>
      @endif
      <div class="cp-vd-field cp-vd-field--highlight">
        <span class="cp-vd-field__label">Final quote total (incl. HST)</span>
        <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($finalTotal) }}</span>
      </div>
    </div>
  </div>
@endif

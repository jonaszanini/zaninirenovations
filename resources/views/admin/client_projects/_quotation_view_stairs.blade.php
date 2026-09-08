{{-- Stairs quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (StairsQuotation) --}}
@php
  use App\Models\StairsMaterialType;
  use App\Services\StairsQuotationCalculator;

  $inp      = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc     = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $summary  = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

  // Material
  $matTypeId = (int) ($inp['material_type_id'] ?? 0);
  $matType   = $matTypeId > 0 ? StairsMaterialType::query()->find($matTypeId) : null;
  $matName   = $matType ? $matType->name : '—';

  // Steps / segments
  $totalSteps = (int) ($inp['total_steps'] ?? 0);
  $segments   = is_array($inp['segments'] ?? null) ? $inp['segments'] : [];
  $legacyOpen = $inp['open_sides'] ?? null;

  // Labour & material pricing
  $matPrice    = (float) ($inp['material_price'] ?? 0);
  $nosing      = $inp['nosing_option'] ?? null;

  // Calculated totals
  $finalTotal = (float) ($summary['final_quote_total'] ?? (float) $sourceModel->final_quote_total);
  $labourPre  = round((float) ($summary['subtotal_pre_hst'] ?? 0) - $matPrice, 2);
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
      <span class="cp-vd-field__label">Material type</span>
      <span class="cp-vd-field__value">{{ $matName }}</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Total steps</span>
      <span class="cp-vd-field__value">{{ $totalSteps }}</span>
    </div>

    @if ($matPrice > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Material cost</span>
        <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matPrice) }}</span>
      </div>
    @endif

    @if ($nosing)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Nosing option</span>
        <span class="cp-vd-field__value">{{ ucfirst(str_replace('_', ' ', $nosing)) }}</span>
      </div>
    @endif
  </div>

  {{-- Segments --}}
  @if (!empty($segments))
    <div style="margin-top:12px;">
      <span class="cp-vd-field__label" style="display:block;margin-bottom:6px;">Segments ({{ count($segments) }})</span>
      <ul class="cp-vd-sides-list">
        @foreach ($segments as $i => $seg)
          @if (!is_array($seg)) @continue @endif
          @php
            $qty     = (int) ($seg['qty'] ?? 0);
            $open    = StairsQuotationCalculator::normalizeSegmentOpenSidesForMaterial($seg, $matType, $legacyOpen);
            $openLbl = StairsQuotationCalculator::openSidesLabel($open);
          @endphp
          @if ($qty > 0)
            <li class="cp-vd-side-chip">{{ $qty }} step{{ $qty !== 1 ? 's' : '' }} · {{ $openLbl }}</li>
          @endif
        @endforeach
      </ul>
    </div>
  @endif
</div>

{{-- Calculator summary --}}
@if (!empty($summary))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Calculator summary</div>
    <div class="cp-vd-grid">
      @if ($matPrice > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Material cost</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matPrice) }}</span>
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

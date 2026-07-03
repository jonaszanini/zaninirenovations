{{-- Standard quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (StandardQuotation) --}}
@php
  $materials = is_array($sourceModel->materials) ? $sourceModel->materials : [];
  $labourMode = $sourceModel->labour_mode ?? 'hours';
  $labourQty  = (float) $sourceModel->labour_quantity;
  $labourRate = (float) $sourceModel->labour_hourly_rate;
  $useFixed   = (bool) $sourceModel->labour_use_fixed;
  $fixedAmt   = (float) $sourceModel->labour_fixed_amount;
  $fixedHstIn = (bool) $sourceModel->labour_fixed_hst_included;
  $contingOn  = (bool) $sourceModel->contingency_enabled;
  $contingPct = (float) $sourceModel->contingency_percent;
  $contingAmt = (float) $sourceModel->contingency_amount;
@endphp

<div class="cp-vd-section">
  <div class="cp-vd-section__title">Project details</div>
  <div class="cp-vd-grid">
    @if ($sourceModel->client_name)
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Client name</span>
        <span class="cp-vd-field__value">{{ $sourceModel->client_name }}</span>
      </div>
    @endif
    @if ($sourceModel->display_title)
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Document title</span>
        <span class="cp-vd-field__value">{{ $sourceModel->display_title }}</span>
      </div>
    @endif
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Includes</span>
      <span class="cp-vd-field__value">{{ $sourceModel->pdfSummaryLineDescription() }}</span>
    </div>
  </div>
</div>

{{-- Materials --}}
@if (!empty($materials))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Materials ({{ count($materials) }})</div>
    <table class="cp-vd-materials-table">
      <thead>
        <tr>
          <th>Description</th>
          <th class="num">Qty</th>
          <th class="num">Unit price</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($materials as $mat)
          @php
            $matName  = trim((string) ($mat['short_name'] ?? $mat['description'] ?? 'Material'));
            $matDesc  = trim((string) ($mat['description'] ?? ''));
            $qty      = (float) ($mat['quantity'] ?? 0);
            $unit     = (float) ($mat['unit_price'] ?? 0);
            $lineTotal= (float) ($mat['line_subtotal'] ?? ($qty * $unit));
            $taxed    = (bool) ($mat['apply_hst'] ?? true);
          @endphp
          <tr>
            <td>
              {{ $matName }}
              @if ($matDesc && $matDesc !== $matName)
                <span style="color:#9ca3af;font-size:12px;display:block;">{{ $matDesc }}</span>
              @endif
              @if (!$taxed)
                <span style="color:#b45309;font-size:11px;">No HST</span>
              @endif
            </td>
            <td class="num">{{ rtrim(rtrim(number_format($qty,2),'0'),'.') }}</td>
            <td class="num">${{ number_format($unit,2) }}</td>
            <td class="num">{{ \App\Models\ClientProject::formatMoney($lineTotal) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

{{-- Labour --}}
<div class="cp-vd-section">
  <div class="cp-vd-section__title">Labour</div>
  <div class="cp-vd-grid">
    @if ($useFixed)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Labour type</span>
        <span class="cp-vd-field__value">Fixed fee</span>
      </div>
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Fixed amount</span>
        <span class="cp-vd-field__value">${{ number_format($fixedAmt, 2) }} {{ $fixedHstIn ? '(HST included)' : '' }}</span>
      </div>
    @else
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Labour type</span>
        <span class="cp-vd-field__value">{{ $labourMode === 'days' ? 'Days' : 'Hours' }}</span>
      </div>
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Quantity</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($labourQty,2),'0'),'.') }} {{ $labourMode === 'days' ? 'days' : 'hrs' }}</span>
      </div>
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Hourly rate</span>
        <span class="cp-vd-field__value">${{ number_format($labourRate,2) }}/hr</span>
      </div>
    @endif
  </div>
</div>

{{-- Contingency --}}
@if ($contingOn && $contingAmt > 0)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Contingency</div>
    <div class="cp-vd-grid">
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Percentage</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($contingPct,2),'0'),'.') }}%</span>
      </div>
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Amount</span>
        <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($contingAmt) }}</span>
      </div>
    </div>
  </div>
@endif

{{-- Grand total --}}
@if ($sourceModel->grand_total > 0)
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Summary</div>
    <div class="cp-vd-grid">
      @if ($sourceModel->materials_subtotal_before_hst > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Materials (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney((float)$sourceModel->materials_subtotal_before_hst) }}</span>
        </div>
      @endif
      @if ($sourceModel->labour_total > 0)
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Labour</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney((float)$sourceModel->labour_total) }}</span>
        </div>
      @endif
      <div class="cp-vd-field cp-vd-field--highlight">
        <span class="cp-vd-field__label">Grand total (incl. HST)</span>
        <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney((float)$sourceModel->grand_total) }}</span>
      </div>
    </div>
  </div>
@endif

{{-- Custom millwork quotation — read-only detail for the View drawer. --}}
@php
  $inp     = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $scope   = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
  $cut     = is_array($calc['cut_plan'] ?? null) ? $calc['cut_plan'] : [];
  $labourPre = (float) ($summary['labor_pre_hst'] ?? $sourceModel->labourPreTaxAmountForProjectQuotation());
  $matPre    = (float) ($summary['materials_pre_hst'] ?? $sourceModel->materialsPreTaxAmountForProjectQuotation());
  $layouts = is_array($cut['layouts'] ?? null) ? $cut['layouts'] : [];
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
    <div class="cp-vd-field cp-vd-field--full">
      <span class="cp-vd-field__label">Pieces</span>
      <span class="cp-vd-field__value">{{ $scope['piece_summary'] ?? $sourceModel->summaryDescription() }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Sheets</span>
      <span class="cp-vd-field__value">{{ (int) ($cut['sheet_count'] ?? $sourceModel->sheetCount()) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Finish</span>
      <span class="cp-vd-field__value">{{ $scope['finish_label'] ?? '—' }}</span>
    </div>
  </div>
</div>

@if ($layouts !== [])
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Cut plan</div>
    <div class="mw-cut-plan mw-cut-plan--drawer">
      @foreach ($layouts as $layout)
        <div class="mw-cut-sheet">
          <div class="mw-cut-sheet__stats">
            <strong>{{ $layout['stats']['label'] ?? 'Sheet' }}</strong>
            <span>Used {{ $layout['stats']['used_pct'] ?? 0 }}% · waste {{ $layout['stats']['waste_pct'] ?? 0 }}%</span>
          </div>
          <div class="mw-cut-svg-wrap">{!! $layout['svg'] ?? '' !!}</div>
          @if (! empty($layout['legend']))
            <ul class="mw-cut-legend">
              @foreach ($layout['legend'] as $row)
                <li>
                  <span class="mw-cut-legend__n">{{ $row['n'] ?? '' }}</span>
                  <span>{{ $row['label'] ?? 'Part' }} · {{ $row['size'] ?? '' }}@if (! empty($row['rotated'])) · rotated @endif</span>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      @endforeach
    </div>
  </div>
@endif

<div class="cp-vd-section">
  <div class="cp-vd-section__title">Totals</div>
  <div class="cp-vd-grid">
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Labor (pre-HST)</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($labourPre) }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Materials (pre-HST)</span>
      <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($matPre) }}</span>
    </div>
  </div>
</div>

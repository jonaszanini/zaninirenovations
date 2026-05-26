@extends('admin.layouts.app')

@php
  use App\Services\PavingQuotationCalculator;
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New paving stone quotation';
  $formAction = $isEdit ? route('admin.quotations.paving.update', $quotation) : route('admin.quotations.paving.store');
  $inputs = $isEdit ? ($quotation->inputs ?? []) : PavingQuotationCalculator::defaultInputs();
  foreach (PavingQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;

  $linkProject = $linkProject ?? null;
@endphp

@section('heading', $title)

@push('head')
  <style>
    /* Toolbar (match fence quotation form — paving omitted these originally) */
    .fq-form-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px 20px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }
    .fq-form-toolbar-right {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: flex-end;
      flex: 1 1 auto;
      min-width: 0;
    }
    @media (max-width: 720px) {
      .fq-form-toolbar {
        flex-direction: column;
        align-items: stretch;
      }
      .fq-form-toolbar > a:first-child {
        width: fit-content;
      }
      .fq-form-toolbar-right {
        justify-content: flex-start;
      }
    }
    .fq-linked-banner {
      display: flex;
      gap: 8px;
      align-items: center;
      background: #e8f4ea;
      color: #0f5132;
      border: 1px solid #bfddc6;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 16px;
    }
    .fq-linked-banner a { color: #0f5132; font-weight: 600; }

    .pq-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.95fr);
      gap: 20px;
      align-items: start;
    }
    .pq-layout > div:first-child { min-width: 0; }
    .pq-nav-tabs {
      list-style: none;
      margin: 0 0 1.1rem;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      border-bottom: 1px solid var(--admin-border);
      gap: 0;
    }
    .pq-nav-tabs .nav-item {
      margin: 0 0 -1px 0;
    }
    .pq-nav-tabs .nav-link {
      display: block;
      margin: 0;
      padding: 0.5rem 1rem;
      border: 1px solid transparent;
      border-top-left-radius: 0.25rem;
      border-top-right-radius: 0.25rem;
      background: transparent;
      color: var(--admin-primary);
      font-weight: 600;
      font-size: 0.9rem;
      font-family: inherit;
      line-height: 1.4;
      cursor: pointer;
      text-align: left;
      text-decoration: none;
      box-shadow: none;
      -webkit-appearance: none;
      appearance: none;
    }
    .pq-nav-tabs .nav-link:hover:not(.active):not(:disabled) {
      color: var(--admin-primary-hover);
      border-color: var(--admin-surface-hover) var(--admin-surface-hover) var(--admin-border);
      background: var(--admin-surface-hover);
    }
    .pq-nav-tabs .nav-link:focus {
      outline: none;
    }
    .pq-nav-tabs .nav-link:focus-visible {
      box-shadow: 0 0 0 2px var(--admin-surface), 0 0 0 4px var(--admin-primary-ring);
    }
    /* Active tab: “connected” strip; bottom edge matches main area background */
    .pq-nav-tabs .nav-link.active {
      color: var(--admin-text);
      background: var(--admin-surface);
      border-color: var(--admin-border) var(--admin-border) var(--admin-bg);
    }
    .pq-nav-tabs--secondary {
      margin-bottom: 1rem;
    }
    .pq-nav-tabs--secondary .nav-link {
      font-size: 0.875rem;
      padding: 0.42rem 0.9rem;
    }
    .pq-panel[hidden] { display: none !important; }
    /* Admin topbar is not sticky on desktop — keep offset small when scrolling. */
    .pq-sticky { position: sticky; top: 12px; align-self: start; }
    @media (max-width: 1100px) {
      .pq-layout { grid-template-columns: 1fr; }
      /* Actions + summary first; static positioning so nothing covers inputs while scrolling. */
      .pq-layout > aside { order: -1; }
      .pq-sticky {
        position: static;
        top: auto;
        align-self: stretch;
      }
    }
    .pq-sidebar-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 12px;
      padding: 12px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
    }
    .pq-sidebar-actions .admin-btn {
      width: 100%;
      justify-content: center;
    }
    .pq-sidebar-actions-row {
      display: flex;
      gap: 8px;
    }
    .pq-sidebar-actions-row .admin-btn {
      flex: 1 1 0;
      min-width: 0;
    }
    .pq-summary-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #fafbfc; }
    .pq-summary-section { margin-bottom: 14px; }
    .pq-summary-section:last-child { margin-bottom: 0; }
    .pq-summary-section__title {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #64748b;
      margin: 0 0 6px;
      padding-bottom: 4px;
      border-bottom: 1px solid #e8edf2;
    }
    .pq-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 5px 0; font-size: 13px; }
    .pq-row span:first-child { color: #374151; flex: 1 1 auto; min-width: 0; }
    .pq-row strong { flex-shrink: 0; text-align: right; font-weight: 600; color: #111827; }
    .pq-row--sub { font-size: 12px; }
    .pq-row--sub span:first-child { color: #6b7280; padding-left: 8px; }
    .pq-row--divider { border-top: 1px solid #d8dee4; margin-top: 6px; padding-top: 8px; }
    .pq-row--hl { font-weight: 700; color: #024768; font-size: 15px; }
    .pq-row--hl span:first-child { color: #024768; font-weight: 700; }
    .pq-row--hl strong { color: #024768; font-size: 16px; }
    .pq-row--total {
      border-top: 2px solid #024768;
      margin-top: 8px;
      padding-top: 10px;
      font-size: 15px;
    }
    .pq-row--total span:first-child { color: #024768; font-weight: 700; }
    .pq-row--total strong { color: #024768; font-size: 18px; font-weight: 700; }
    .pq-row--muted { font-size: 12px; }
    .pq-row--muted span:first-child { color: #9ca3af; }
    .pq-row--muted strong { color: #6b7280; font-weight: 500; }
    .pq-row--note { font-size: 11px; padding: 0; margin-top: -2px; }
    .pq-row--note span { color: #9ca3af; font-style: italic; }
    .pq-profit-pos strong { color: #15803d; }
    .pq-profit-neg strong { color: #b91c1c; }
    .pq-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 700px) { .pq-form-grid { grid-template-columns: 1fr; } }
    .pq-form-sections { display: flex; flex-direction: column; gap: 18px; }
    .pq-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; align-items: start; }
    @media (max-width: 700px) { .pq-form-row { grid-template-columns: 1fr; } }
    .pq-field-box {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 14px 16px;
      background: #fafbfc;
    }
    .pq-field-box .admin-form-group { margin-bottom: 0; }
    .pq-field-box .admin-form-group + .admin-form-group { margin-top: 0; }
    .pq-field-box-title {
      display: block;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #64748b;
      margin: 0 0 12px;
    }
    .pq-field-box .pq-hint { margin-top: 8px; margin-bottom: 0; }
    .pq-labour-rates-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px 16px;
    }
    @media (max-width: 520px) { .pq-labour-rates-row { grid-template-columns: 1fr; } }
    .pq-stone-meta {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0 6px;
      font-size: 11px;
      color: #64748b;
      margin: 5px 0 0;
      line-height: 1.35;
    }
    .pq-stone-meta-sep { color: #94a3b8; user-select: none; }
    .pq-stone-meta-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 22px;
      height: 22px;
      border-radius: 4px;
      color: #024768;
      background: #e8f4f8;
      text-decoration: none;
      flex-shrink: 0;
    }
    .pq-stone-meta-link:hover { background: #d1e9f0; color: #013a52; }
    .pq-stone-group .admin-input { max-width: none; }
    .pq-options-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 20px;
      padding: 12px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: #fff;
    }
    .pq-options-row label.admin-checkbox {
      font-size: 13px;
      font-weight: 500;
    }
    .pq-override-block {
      padding-top: 4px;
      border-top: 1px solid #e8edf2;
    }
    .pq-marble-price-field { max-width: 280px; }
    @media (max-width: 700px) { .pq-marble-price-field { max-width: none; } }
    .pq-db-wrap { margin-top: 0; }
    .pq-db-subpanel[hidden] { display: none !important; }
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .pq-area-block { grid-column: 1 / -1; }
    .pq-area-mode { display: flex; flex-wrap: wrap; gap: 12px 20px; margin-bottom: 10px; }
    .pq-area-mode label { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; }
    .pq-area-dimensions { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
    .pq-area-dimensions .admin-form-group { flex: 1 1 120px; min-width: 100px; margin: 0; }
    .pq-area-dimensions-sep { padding-bottom: 10px; font-weight: 600; color: #64748b; }
    .pq-area-computed { margin-top: 8px; font-size: 13px; color: #024768; }
    .pq-marble-box { border: 1px dashed #94a3b8; border-radius: 8px; padding: 14px; margin-top: 12px; background: #f8fafc; }

    /* Price database — stone rows share one grid with the header row */
    .pq-db-stone-list { min-width: 1000px; border: 1px solid var(--admin-border); border-radius: 8px; overflow: hidden; }
    .pq-db-stone-grid {
      display: grid;
      grid-template-columns:
        minmax(120px, 1.15fr)
        minmax(140px, 1.35fr)
        minmax(70px, 0.52fr)
        minmax(70px, 0.52fr)
        minmax(70px, 0.52fr)
        minmax(108px, 0.92fr)
        minmax(78px, 0.62fr)
        auto;
      gap: 8px 10px;
      align-items: center;
      padding: 0.65rem 1.1rem;
    }
    .pq-db-stone-head {
      background: var(--admin-surface-hover);
      border-bottom: 1px solid var(--admin-border);
      align-items: end;
      padding-top: 0.55rem;
      padding-bottom: 0.5rem;
    }
    .pq-db-stone-head-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--admin-muted);
      font-weight: 600;
      line-height: 1.2;
    }
    .pq-db-stone-row {
      border-bottom: 1px solid var(--admin-border);
      background: #f8fafc;
    }
    .pq-db-stone-row:last-child { border-bottom: none; }
    .pq-db-stone-row .pq-db-stone-grid { padding-top: 0.55rem; padding-bottom: 0.7rem; }
    .pq-db-stone-link-wrap {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 0;
    }
    .pq-db-stone-link-wrap .admin-input { flex: 1 1 auto; min-width: 0; }
    .pq-db-stone-open-link {
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 6px;
      color: #024768;
      background: #e8f4f8;
      text-decoration: none;
    }
    .pq-db-stone-open-link:hover { background: #d1e9f0; color: #013a52; }
    .pq-db-stone-sq-wrap {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 0;
    }
    .pq-db-stone-sq-wrap .pq-db-stone-sq { flex: 1 1 auto; min-width: 0; }
    .pq-db-stone-grid--add { margin-top: 4px; }
    .pq-db-stone-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: flex-end;
      padding-top: 1px; /* optically match admin-input top padding / border */
    }
    .pq-db-hidden-form { display: none; }
    @media (max-width: 720px) {
      .pq-db-stone-grid {
        grid-template-columns: 1fr;
      }
      .pq-db-stone-actions {
        justify-content: flex-start;
      }
    }
    .pq-db-rates-groups { display: flex; flex-direction: column; gap: 20px; }
    .pq-db-rates-group-title {
      margin: 0 0 8px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #64748b;
    }
    .pq-db-rates-list {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      overflow: hidden;
      background: #fff;
    }
    .pq-db-rate-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px 20px;
      align-items: center;
      padding: 12px 16px;
      border-bottom: 1px solid #e8edf2;
      background: #fafbfc;
    }
    .pq-db-rate-row:last-child { border-bottom: none; }
    .pq-db-rate-name {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #1e293b;
      line-height: 1.3;
    }
    .pq-db-rate-unit {
      display: block;
      margin-top: 2px;
      font-size: 12px;
      color: #64748b;
    }
    .pq-db-rate-inline {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: flex-end;
    }
    .pq-db-rate-price-label {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin: 0;
    }
    .pq-db-rate-price-label-text {
      font-size: 11px;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .pq-db-rate-input { width: 108px; min-width: 88px; }
    @media (max-width: 640px) {
      .pq-db-rate-row { grid-template-columns: 1fr; }
      .pq-db-rate-inline { justify-content: flex-start; }
    }
  </style>
@endpush

@section('content')
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.paving.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    @if ($isEdit)
      <div class="fq-form-toolbar-right">
        <a href="{{ route('admin.quotations.paving.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-print"></i> Print Material List
        </a>
      </div>
    @endif
  </div>

  @if ($isEdit && $quotation->project)
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      <span>
        Linked to
        <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation">{{ $quotation->project->name }}</a>
      </span>
    </div>
  @endif

  <ul class="nav nav-tabs pq-nav-tabs pq-nav-tabs--primary" role="tablist" aria-label="Quotation mode">
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link active" data-pq-tab="calc" role="tab" aria-selected="true" id="pq-tab-calc" aria-controls="pq-panel-calc">Calculator</button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" data-pq-tab="db" role="tab" aria-selected="false" id="pq-tab-db" aria-controls="pq-panel-db">Price database</button>
    </li>
  </ul>

  <div id="pq-panel-calc" class="pq-panel">
    <div class="pq-layout">
      <div>
        <form id="pq-main-form" method="post" action="{{ $formAction }}">
          @csrf
          @if ($isEdit)
            @method('PUT')
          @endif
          <input type="hidden" name="save_intent" id="pq-save-intent" value="publish">
          @if ($linkProject)
            <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
          @endif

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Section A — Project inputs</h3>
            <div class="admin-form-group">
              <label for="pq-client">Client name @if(!$isEdit || !$quotation->is_draft)<span class="cp-required">*</span>@endif</label>
              <input type="text" id="pq-client" name="client_name" class="admin-input"
                     value="{{ old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? '')) }}"
                     maxlength="160" @if(!old('client_name', $isEdit ? ($quotation->client_name ?? '') : '') && (!$isEdit || !($quotation->is_draft ?? false))) required @endif>
              <p class="pq-hint">Required for a published quotation; optional for drafts.</p>
            </div>
            <div class="admin-form-group">
              <label for="pq-work-description">Description of the work</label>
              <textarea id="pq-work-description" name="work_description" rows="5" maxlength="5000"
                        class="admin-input @error('work_description') is-invalid @enderror"
                        placeholder="Briefly describe the service to be performed (visible to the client on the quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
              <p class="pq-hint">Shown on the client PDF beneath the priced line item. Plain text — line breaks are preserved.</p>
              @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            @php
              $pqAreaMode = old('area_input_mode', $inputs['area_input_mode'] ?? 'total');
              $pqAreaLength = old('area_length_ft', $inputs['area_length_ft'] ?? '');
              $pqAreaWidth = old('area_width_ft', $inputs['area_width_ft'] ?? '');
              $pqAreaDimensions = $pqAreaMode === 'dimensions';
            @endphp
            <div class="pq-form-sections">
              <div class="admin-form-group pq-area-block">
                <span class="admin-form-group-label">Total project area (sq ft) <span class="cp-required">*</span></span>
                <div class="pq-area-mode" role="radiogroup" aria-label="How to enter project area">
                  <label>
                    <input type="radio" name="area_input_mode" value="total" data-pq-area-mode
                      @checked(! $pqAreaDimensions)>
                    Enter total sq ft
                  </label>
                  <label>
                    <input type="radio" name="area_input_mode" value="dimensions" data-pq-area-mode
                      @checked($pqAreaDimensions)>
                    Length × width (ft)
                  </label>
                </div>
                <div id="pq-area-total-wrap" @if ($pqAreaDimensions) hidden @endif>
                  <input type="number" id="pq-area" name="total_area_sq_ft" class="admin-input pq-calc-in pq-area-total-in"
                         min="1" step="0.01" @unless($pqAreaDimensions) required @endunless
                         value="{{ old('total_area_sq_ft', $inputs['total_area_sq_ft']) }}">
                </div>
                <div id="pq-area-dimensions-wrap" @unless($pqAreaDimensions) hidden @endunless>
                  <div class="pq-area-dimensions">
                    <div class="admin-form-group">
                      <label for="pq-area-length">Length (ft)</label>
                      <input type="number" id="pq-area-length" name="area_length_ft" class="admin-input pq-area-dim-in"
                             min="0.01" step="0.01" @if($pqAreaDimensions) required @endif
                             value="{{ $pqAreaLength !== '' && $pqAreaLength !== null ? $pqAreaLength : '' }}"
                             placeholder="e.g. 10">
                    </div>
                    <span class="pq-area-dimensions-sep" aria-hidden="true">×</span>
                    <div class="admin-form-group">
                      <label for="pq-area-width">Width (ft)</label>
                      <input type="number" id="pq-area-width" name="area_width_ft" class="admin-input pq-area-dim-in"
                             min="0.01" step="0.01" @if($pqAreaDimensions) required @endif
                             value="{{ $pqAreaWidth !== '' && $pqAreaWidth !== null ? $pqAreaWidth : '' }}"
                             placeholder="e.g. 10">
                    </div>
                  </div>
                  <p class="pq-area-computed" id="pq-area-computed" aria-live="polite"></p>
                  <input type="hidden" id="pq-area-from-dimensions" class="pq-calc-in"
                         @unless($pqAreaDimensions) disabled @endunless
                         value="{{ old('total_area_sq_ft', $inputs['total_area_sq_ft']) }}">
                </div>
              </div>

              <div class="admin-form-group pq-stone-group">
                <label for="pq-stone">Stone type <span class="cp-required">*</span></label>
                <select id="pq-stone" name="paving_stone_type_id" class="admin-input pq-calc-in" required>
                  @foreach ($stoneTypes as $st)
                    <option value="{{ $st->id }}"
                      data-sq="{{ $st->sq_ft_per_stone }}"
                      data-pr="{{ $st->price_per_stone }}"
                      data-len="{{ $st->length_in }}"
                      data-wid="{{ $st->width_in }}"
                      data-hgt="{{ $st->height_in }}"
                      data-url="{{ $st->product_url }}"
                      @selected((int) old('paving_stone_type_id', $inputs['paving_stone_type_id'] ?? 0) === (int) $st->id)>
                      {{ $st->name }}
                    </option>
                  @endforeach
                </select>
                <div class="pq-stone-meta" id="pq-stone-meta" aria-live="polite"></div>
                <input type="hidden" id="pq-sqft" name="stone_sq_ft_per_stone" class="pq-calc-in" required
                       value="{{ old('stone_sq_ft_per_stone', $inputs['stone_sq_ft_per_stone']) }}">
                <input type="hidden" id="pq-stprice" name="stone_price_per_stone" class="pq-calc-in" required
                       value="{{ old('stone_price_per_stone', number_format((float) ($inputs['stone_price_per_stone'] ?? 0), 2, '.', '')) }}">
              </div>

              <div class="pq-field-box">
                <span class="pq-field-box-title">Labour rates</span>
                <div class="pq-labour-rates-row">
                  <div class="admin-form-group">
                    <label for="pq-helper">Helper hourly rate ($/hr)</label>
                    <input type="text" id="pq-helper" name="helper_hourly_rate" class="admin-input pq-calc-in cp-money-input"
                           data-cp-money-entry="direct" inputmode="decimal" autocomplete="off" placeholder="0.00"
                           value="{{ old('helper_hourly_rate', number_format((float) ($inputs['helper_hourly_rate'] ?? 0), 2, '.', '')) }}">
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-owner">Owner hourly rate ($/hr)</label>
                    <input type="text" id="pq-owner" name="owner_hourly_rate" class="admin-input pq-calc-in cp-money-input"
                           data-cp-money-entry="direct" inputmode="decimal" autocomplete="off" placeholder="0.00"
                           value="{{ old('owner_hourly_rate', number_format((float) ($inputs['owner_hourly_rate'] ?? 0), 2, '.', '')) }}">
                  </div>
                </div>
                <p class="pq-hint">Set helper to 0 to disable helper hours on labour tasks.</p>
              </div>

              <div class="pq-field-box">
                <span class="pq-field-box-title">Equipment rental (days)</span>
                <div class="pq-form-row">
                  <div class="admin-form-group">
                    <label for="pq-mini">Mini skid steer</label>
                    <input type="number" id="pq-mini" name="mini_skid_days" class="admin-input pq-calc-in pq-equip-days-in" step="1" min="0" inputmode="numeric"
                           value="{{ old('mini_skid_days', (int) ($inputs['mini_skid_days'] ?? 0)) }}">
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-plate">Plate compactor</label>
                    <input type="number" id="pq-plate" name="plate_compactor_days" class="admin-input pq-calc-in pq-equip-days-in" step="1" min="0" inputmode="numeric"
                           value="{{ old('plate_compactor_days', (int) ($inputs['plate_compactor_days'] ?? 0)) }}">
                  </div>
                </div>
                <p class="pq-hint">Whole days only (0, 1, 2, …).</p>
              </div>

              <div class="pq-field-box">
                <span class="pq-field-box-title">Estimate parameters</span>
                <div class="pq-form-grid">
                  <div class="admin-form-group">
                    <label for="pq-waste">Waste factor (%)</label>
                    <input type="number" id="pq-waste" name="waste_factor" class="admin-input pq-calc-in" step="0.1" min="0" max="100"
                           value="{{ old('waste_factor', (($inputs['waste_factor'] ?? 0.05) <= 1 ? ($inputs['waste_factor'] ?? 0.05) * 100 : ($inputs['waste_factor'] ?? 5))) }}">
                    <p class="pq-hint">Extra stone quantity (e.g. 5 = 5%).</p>
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-depth">Excavation depth (in)</label>
                    <input type="number" id="pq-depth" name="excavation_depth_in" class="admin-input pq-calc-in" step="0.5" min="1" max="48"
                           value="{{ old('excavation_depth_in', $inputs['excavation_depth_in']) }}">
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-profit">Profit margin (%)</label>
                    <input type="number" id="pq-profit" name="profit_margin" class="admin-input pq-calc-in" step="0.01" min="0" max="100"
                           value="{{ old('profit_margin', (($inputs['profit_margin'] ?? 0.05) <= 1 ? ($inputs['profit_margin'] ?? 0.05) * 100 : ($inputs['profit_margin'] ?? 5))) }}">
                    <p class="pq-hint">Applied to tax-inclusive subtotal before profit.</p>
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-hst">HST rate (%)</label>
                    <input type="number" id="pq-hst" name="hst_rate" class="admin-input pq-calc-in" step="0.01" min="0" max="30"
                           value="{{ old('hst_rate', (($inputs['hst_rate'] ?? 0.13) <= 0.5 ? ($inputs['hst_rate'] ?? 0.13) * 100 : ($inputs['hst_rate'] ?? 13))) }}">
                    <p class="pq-hint">Ontario HST on materials &amp; equipment.</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="pq-marble-box">
              <label class="admin-checkbox admin-checkbox--block">
                <input type="checkbox" name="marble_enabled" value="1" class="admin-checkbox__input pq-calc-in" id="pq-marble-on"
                  @checked(old('marble_enabled', $inputs['marble_enabled'] ?? false))>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Part of this area will use marble / accent stones?</span>
              </label>
              <div id="pq-marble-fields" style="margin-top:12px;{{ old('marble_enabled', $inputs['marble_enabled'] ?? false) ? '' : 'display:none;' }}">
                <div class="pq-form-row">
                  <div class="admin-form-group">
                    <label for="pq-marble-stone">Marble stone type</label>
                    <select id="pq-marble-stone" name="marble_stone_type_id" class="admin-input pq-calc-in">
                      <option value="">—</option>
                      @foreach ($stoneTypes as $st)
                        <option value="{{ $st->id }}" data-sq="{{ $st->sq_ft_per_stone }}" data-pr="{{ $st->price_per_stone }}"
                          @selected((int) old('marble_stone_type_id', $inputs['marble_stone_type_id'] ?? 0) === (int) $st->id)>{{ $st->name }}</option>
                      @endforeach
                    </select>
                    <p class="pq-stone-meta" id="pq-marble-stone-meta" aria-live="polite"></p>
                    <input type="hidden" id="pq-marble-sqft" name="marble_sq_ft_per_stone" class="pq-calc-in"
                           value="{{ old('marble_sq_ft_per_stone', $inputs['marble_sq_ft_per_stone'] ?? '') }}">
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-marble-area">Marble area (sq ft)</label>
                    <input type="number" id="pq-marble-area" name="marble_area_sq_ft" class="admin-input pq-calc-in" min="0" step="0.01"
                           value="{{ old('marble_area_sq_ft', $inputs['marble_area_sq_ft'] ?? 0) }}">
                  </div>
                </div>
                <div class="admin-form-group pq-marble-price-field" style="margin-top:12px;">
                  <label for="pq-marble-price">Marble $/stone (before tax)</label>
                  <input type="text" id="pq-marble-price" name="marble_price_per_stone" class="admin-input pq-calc-in cp-money-input"
                         inputmode="decimal" autocomplete="off" placeholder="Auto from type"
                         value="{{ old('marble_price_per_stone', isset($inputs['marble_price_per_stone']) && $inputs['marble_price_per_stone'] !== null && $inputs['marble_price_per_stone'] !== '' ? number_format((float) $inputs['marble_price_per_stone'], 2, '.', '') : '') }}">
                </div>
              </div>
            </div>

            <div class="pq-options-row" style="margin-top:16px;">
              <label class="admin-checkbox">
                <input type="checkbox" name="add_polymeric_sand" value="1" class="admin-checkbox__input pq-calc-in" @checked(old('add_polymeric_sand', $inputs['add_polymeric_sand'] ?? true))>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Polymeric sand</span>
              </label>
              <label class="admin-checkbox">
                <input type="checkbox" name="include_geotextile" value="1" class="admin-checkbox__input pq-calc-in" @checked(old('include_geotextile', $inputs['include_geotextile'] ?? true))>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Geotextile fabric</span>
              </label>
              <label class="admin-checkbox">
                <input type="checkbox" name="include_old_surface_removal" value="1" class="admin-checkbox__input pq-calc-in" @checked(old('include_old_surface_removal', $inputs['include_old_surface_removal'] ?? false))>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Old surface removal</span>
              </label>
            </div>

            <div class="admin-form-group pq-override-block" style="margin-top:16px;">
              <label for="pq-manual">Manual price override (CAD)</label>
              <input type="text" id="pq-manual" name="manual_price_override" class="admin-input pq-calc-in cp-money-input"
                     inputmode="decimal" autocomplete="off" placeholder="Blank = use built-up suggested total"
                     value="{{ old('manual_price_override', isset($inputs['manual_price_override']) && $inputs['manual_price_override'] !== null && $inputs['manual_price_override'] !== '' ? number_format((float) $inputs['manual_price_override'], 2, '.', '') : '') }}">
              <p class="pq-hint">Leave blank to use the formula suggested total. Enter a dollar amount to override the final quote.</p>
            </div>
          </div>

          @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])
        </form>

        @if ($isEdit && !$quotation->isLinked())
          <div class="fq-link-project-card">
            <h4 style="margin:0 0 8px;">Link to project</h4>
            <div class="fq-link-grid">
              <div>
                <h5>Existing project</h5>
                <form method="post" action="{{ route('admin.quotations.paving.link', $quotation) }}">
                  @csrf
                  <div class="fq-link-existing-row">
                    <select name="client_project_id" class="admin-input" required>
                      <option value="">Select project…</option>
                      @foreach ($linkableProjects as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} — {{ $p->client_name }}</option>
                      @endforeach
                    </select>
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Link</button>
                  </div>
                  <div class="admin-form-group" style="margin-top:10px;">
                    <label>Line description (optional)</label>
                    <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="{{ $quotation->summaryDescription() }}">
                  </div>
                </form>
              </div>
              <div class="fq-link-col fq-link-col--stack">
                <h5>Create project &amp; link</h5>
                <form method="post" action="{{ route('admin.quotations.paving.create-project', $quotation) }}" class="fq-create-project-form">
                  @csrf
                  <input type="hidden" name="link_line_description" value="">
                  <div class="admin-form-group">
                    <label>Project name</label>
                    <input type="text" name="fp_project_name" class="admin-input" maxlength="255" placeholder="Defaults to client + Paving">
                  </div>
                  <div class="admin-form-group">
                    <label>Client name <span class="cp-required">*</span></label>
                    <input type="text" name="fp_client_name" class="admin-input" required maxlength="255" value="{{ $quotation->client_name }}">
                  </div>
                  <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="fp_client_email" class="admin-input" maxlength="255">
                  </div>
                  <div class="admin-form-group">
                    <label>Phone (10 digits)</label>
                    <input type="text" name="fp_client_phone" class="admin-input" maxlength="14" placeholder="(613) 555-0100">
                  </div>
                  <div class="admin-form-group">
                    <label>Address</label>
                    <input type="text" name="fp_client_address" class="admin-input" maxlength="500">
                  </div>
                  <button type="submit" class="admin-btn admin-btn--primary">Create project &amp; link</button>
                </form>
              </div>
            </div>
          </div>
        @endif
      </div>

      <aside class="pq-sticky">
        <div class="pq-sidebar-actions">
          <button type="submit" form="pq-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="pq-btn-publish">
            <i class="fa fa-save"></i> Save quotation
          </button>
          <div class="pq-sidebar-actions-row">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pq-btn-draft">Save as draft</button>
            <a href="{{ route('admin.quotations.paving.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
          </div>
        </div>
        <div class="pq-summary-card">
          <h4 style="margin:0 0 12px;">Live summary</h4>
          <div id="pq-live-root">
            <p class="admin-form-help">Adjust inputs to refresh totals…</p>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <div id="pq-panel-db" class="pq-panel" hidden>
    @include('admin.quotations.paving._database_tab')
  </div>

  <script>
    (function () {
      var previewUrl = @json(route('admin.quotations.paving.preview'));
      var token = document.querySelector('meta[name="csrf-token"]');
      var tokenV = token ? token.getAttribute('content') : '';

      document.querySelectorAll('[data-pq-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var key = btn.getAttribute('data-pq-tab');
          document.querySelectorAll('[data-pq-tab]').forEach(function (b) {
            var on = b === btn;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          document.getElementById('pq-panel-calc').hidden = key !== 'calc';
          document.getElementById('pq-panel-db').hidden = key !== 'db';
        });
      });

      var form = document.getElementById('pq-main-form');
      var intent = document.getElementById('pq-save-intent');

      function normalizeLabourRateField(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var raw = (el.value || '').trim().replace(/,/g, '');
        var n = raw === '' ? 0 : (window.cpMoneyMask ? window.cpMoneyMask.parse(raw) : parseFloat(raw));
        if (!isFinite(n) || n < 0) n = 0;
        el.value = n.toFixed(2);
      }

      function normalizeLabourRateFields() {
        normalizeLabourRateField('pq-helper');
        normalizeLabourRateField('pq-owner');
      }

      form.addEventListener('submit', normalizeLabourRateFields);
      document.getElementById('pq-btn-draft').addEventListener('click', function () {
        intent.value = 'draft';
        form.querySelector('#pq-client').removeAttribute('required');
        form.requestSubmit();
      });
      document.getElementById('pq-btn-publish').addEventListener('click', function () {
        intent.value = 'publish';
        form.querySelector('#pq-client').setAttribute('required', 'required');
      });

      function formatMoney(n) {
        return '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function formatDimInches(val) {
        var n = parseFloat(val);
        if (!isFinite(n) || n <= 0) return null;
        if (Math.abs(n - Math.round(n)) < 0.001) return String(Math.round(n));
        return n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
      }

      function formatStoneDims(len, wid, hgt) {
        var L = formatDimInches(len);
        var W = formatDimInches(wid);
        var H = formatDimInches(hgt);
        if (!L || !W || !H) return null;
        return L + ' x ' + W + ' x ' + H;
      }

      function escapeHtml(str) {
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function renderMainStoneMeta(metaEl, opt) {
        if (!metaEl) return;
        if (!opt || !opt.value) {
          metaEl.innerHTML = '';
          return;
        }
        var parts = [];
        var sqN = parseFloat(opt.getAttribute('data-sq'));
        var prN = parseFloat(opt.getAttribute('data-pr'));
        if (isFinite(sqN) && sqN > 0) {
          parts.push(sqN.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' sq ft per stone');
        }
        if (isFinite(prN) && prN >= 0) {
          parts.push(formatMoney(prN) + ' per stone (before tax)');
        }
        var dims = formatStoneDims(
          opt.getAttribute('data-len'),
          opt.getAttribute('data-wid'),
          opt.getAttribute('data-hgt')
        );
        if (dims) parts.push(dims);

        var url = (opt.getAttribute('data-url') || '').trim();
        var html = '';
        if (parts.length) {
          html += '<span>' + escapeHtml(parts.join(' / ')) + '</span>';
        }
        if (url) {
          if (html) html += '<span class="pq-stone-meta-sep" aria-hidden="true">/</span>';
          html += '<a href="' + escapeHtml(url) + '" class="pq-stone-meta-link" target="_blank" rel="noopener noreferrer" title="Open product page">';
          html += '<i class="fa fa-external-link" aria-hidden="true"></i></a>';
        }
        metaEl.innerHTML = html;
      }

      function formatSqFtMeta(sq) {
        var n = parseFloat(sq);
        if (!isFinite(n) || n <= 0) return '';
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' sq ft per stone';
      }

      function syncStoneMeta(sel, metaEl, sqEl, prEl, includePrice) {
        if (!metaEl) return;
        if (!sel || !sqEl) {
          metaEl.innerHTML = '';
          return;
        }
        var opt = sel.options[sel.selectedIndex];
        if (includePrice && prEl) {
          renderMainStoneMeta(metaEl, opt);
        } else if (opt && opt.value) {
          metaEl.textContent = formatSqFtMeta(opt.getAttribute('data-sq'));
        } else {
          metaEl.textContent = formatSqFtMeta(sqEl.value);
        }
      }

      function wireStoneSelect(sel, sqEl, prEl, metaEl, includePrice) {
        if (!sel || !sqEl || !prEl) return;
        function applyFromOption() {
          var opt = sel.options[sel.selectedIndex];
          if (!opt || !opt.value) {
            syncStoneMeta(sel, metaEl, sqEl, prEl, includePrice);
            return;
          }
          var sq = opt.getAttribute('data-sq');
          var pr = opt.getAttribute('data-pr');
          if (sq) {
            sqEl.value = sq;
            sqEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
          if (pr) {
            prEl.value = pr;
            prEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
          syncStoneMeta(sel, metaEl, sqEl, prEl, includePrice);
        }
        sel.addEventListener('change', applyFromOption);
        syncStoneMeta(sel, metaEl, sqEl, prEl, includePrice);
      }
      wireStoneSelect(
        document.getElementById('pq-stone'),
        document.getElementById('pq-sqft'),
        document.getElementById('pq-stprice'),
        document.getElementById('pq-stone-meta'),
        true
      );
      wireStoneSelect(
        document.getElementById('pq-marble-stone'),
        document.getElementById('pq-marble-sqft'),
        document.getElementById('pq-marble-price'),
        document.getElementById('pq-marble-stone-meta'),
        false
      );

      var marbleChk = document.getElementById('pq-marble-on');
      var marbleFields = document.getElementById('pq-marble-fields');
      function syncMarbleFields() {
        if (marbleFields && marbleChk) marbleFields.style.display = marbleChk.checked ? '' : 'none';
      }
      if (marbleChk) marbleChk.addEventListener('change', syncMarbleFields);
      syncMarbleFields();

      var root = document.getElementById('pq-live-root');
      var t = null;
      var lastCommuteAmount = 0;
      var lastPreviewCalc = null;
      function schedule() {
        clearTimeout(t);
        t = setTimeout(runPreview, 220);
      }
      function fmt(n) {
        return 'CA$' + Number(n).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function fmtPct(rate) {
        return (Number(rate || 0) * 100).toFixed(1) + '%';
      }
      function section(title, rows) {
        return '<div class="pq-summary-section">' +
          '<p class="pq-summary-section__title">' + title + '</p>' +
          rows +
          '</div>';
      }
      function row(label, value, cls, valueCls) {
        var valueClass = valueCls ? ' class="' + valueCls + '"' : '';
        return '<div class="pq-row' + (cls ? ' ' + cls : '') + '"><span>' + label + '</span><strong' + valueClass + '>' + value + '</strong></div>';
      }
      function render(c) {
        if (!c || !c.summary) return;
        lastPreviewCalc = c;
        var s = c.summary;
        var m = c.materials;
        var f = c.fixed_costs;
        var l = c.labour;
        var quoteBeforeCommute = Number(s.final_quote_total || 0);
        var clientTotal = quoteBeforeCommute + lastCommuteAmount;
        var calculatedTotal = Number(s.built_up_grand_total || 0);
        var hasManual = s.manual_price_override != null && Number(s.manual_price_override) > 0;
        var subtotal = Number(s.total_with_tax_mats_fixed_plus_labour || 0);
        var profitAmt = Number(s.profit_margin_amount || 0);
        var profitRate = fmtPct(s.profit_margin_rate);
        var html = '';

        html += section('Costs', [
          row('Materials <span class="admin-form-help">(incl. HST)</span>', fmt(m.with_tax)),
          row('Equipment &amp; rentals <span class="admin-form-help">(incl. HST)</span>', fmt(f.with_tax)),
          row('Labour <span class="admin-form-help">(before HST)</span>', fmt(l.total)),
        ].join(''));

        var quoteRows = [
          row('Subtotal', fmt(subtotal), 'pq-row--divider'),
          row('Profit (' + profitRate + ')', fmt(profitAmt)),
          row('Price per sq ft', fmt(s.reference_price_per_sq_ft) + ' / sq ft'),
        ];
        if (hasManual) {
          quoteRows.push(row('Calculated total', fmt(calculatedTotal), 'pq-row--sub'));
          quoteRows.push(row('Manual override', fmt(s.manual_price_override), 'pq-row--hl'));
        } else {
          quoteRows.push(row('Calculated total', fmt(calculatedTotal), 'pq-row--hl'));
        }
        if (lastCommuteAmount > 0) {
          quoteRows.push(row('Commute expense <span class="admin-form-help">(incl. HST)</span>', fmt(lastCommuteAmount)));
        }
        quoteRows.push(row('Total quote', fmt(clientTotal), 'pq-row--total'));
        html += section('Quote', quoteRows.join(''));

        if (s.market_reference_total && Math.abs(Number(s.market_reference_total) - calculatedTotal) > 0.01) {
          html += section('Reference', [
            row('Market tier estimate', fmt(s.market_reference_total), 'pq-row--muted'),
            '<div class="pq-row pq-row--note"><span>From pricing table — not added to the quote.</span></div>',
          ].join(''));
        }

        var gp = Number(s.gross_profit || 0);
        html += section('Profitability', [
          row('Gross profit', fmt(gp), '', gp >= 0 ? 'pq-profit-pos' : 'pq-profit-neg'),
          row('Margin', fmtPct(s.margin_percent)),
          row('Est. working days', String(s.working_days) + ' days'),
        ].join(''));

        root.innerHTML = html;
      }
      function runPreview() {
        normalizeLabourRateFields();
        var fd = new FormData(form);
        fd.delete('_method');
        fetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': tokenV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(render)
          .catch(function () { root.innerHTML = '<p class="admin-form-help">Could not refresh preview.</p>'; });
      }
      form.addEventListener('input', schedule);
      form.addEventListener('change', schedule);

      document.querySelectorAll('.pq-equip-days-in').forEach(function (el) {
        function snapWholeDays() {
          if (el.value === '' || el.value === null) return;
          var n = Math.max(0, Math.round(parseFloat(el.value) || 0));
          if (String(n) !== el.value) {
            el.value = String(n);
            schedule();
          }
        }
        el.addEventListener('change', snapWholeDays);
        el.addEventListener('blur', snapWholeDays);
      });

      (function initPavingAreaInput() {
        var modeRadios = document.querySelectorAll('[data-pq-area-mode]');
        var totalWrap = document.getElementById('pq-area-total-wrap');
        var dimWrap = document.getElementById('pq-area-dimensions-wrap');
        var totalInput = document.getElementById('pq-area');
        var lenInput = document.getElementById('pq-area-length');
        var widInput = document.getElementById('pq-area-width');
        var hiddenArea = document.getElementById('pq-area-from-dimensions');
        var computedEl = document.getElementById('pq-area-computed');
        if (!modeRadios.length || !totalWrap || !dimWrap || !totalInput) return;

        function isDimensionsMode() {
          var checked = document.querySelector('[data-pq-area-mode]:checked');
          return checked && checked.value === 'dimensions';
        }

        function syncComputedArea() {
          if (!isDimensionsMode() || !lenInput || !widInput || !hiddenArea) return;
          var len = parseFloat(lenInput.value);
          var wid = parseFloat(widInput.value);
          if (!isFinite(len) || !isFinite(wid) || len <= 0 || wid <= 0) {
            hiddenArea.value = '';
            if (computedEl) computedEl.textContent = '';
            return;
          }
          var area = Math.round(len * wid * 100) / 100;
          hiddenArea.value = String(area);
          if (computedEl) {
            computedEl.textContent = '= ' + area.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' sq ft';
          }
        }

        function syncAreaMode() {
          var dimMode = isDimensionsMode();
          totalWrap.hidden = dimMode;
          dimWrap.hidden = !dimMode;
          totalInput.required = !dimMode;
          totalInput.disabled = dimMode;
          if (lenInput) lenInput.required = dimMode;
          if (widInput) widInput.required = dimMode;
          if (hiddenArea) {
            hiddenArea.disabled = !dimMode;
            if (dimMode) {
              hiddenArea.setAttribute('name', 'total_area_sq_ft');
              totalInput.removeAttribute('name');
            } else {
              totalInput.setAttribute('name', 'total_area_sq_ft');
              hiddenArea.removeAttribute('name');
            }
          }
          if (dimMode) {
            syncComputedArea();
          } else {
            if (hiddenArea && hiddenArea.value) {
              totalInput.value = hiddenArea.value;
            }
            if (computedEl) computedEl.textContent = '';
          }
        }

        modeRadios.forEach(function (r) {
          r.addEventListener('change', function () {
            syncAreaMode();
            schedule();
          });
        });
        if (lenInput) lenInput.addEventListener('input', function () { syncComputedArea(); schedule(); });
        if (widInput) widInput.addEventListener('input', function () { syncComputedArea(); schedule(); });
        syncAreaMode();
      })();

      var initial = @json($initialCalc);
      if (initial) { render(initial); } else { runPreview(); }

      document.addEventListener('commute-expense:change', function (e) {
        var d = e.detail || {};
        lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
    })();
  </script>
@endsection

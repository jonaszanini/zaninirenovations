@extends('admin.layouts.app')

@php
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit fence quotation '.$quotation->quote_number : 'New fence quotation';
  $formAction = $isEdit
    ? route('admin.quotations.fence.update', $quotation)
    : route('admin.quotations.fence.store');

  $blankRun = [
    'length_ft' => null,
    'already_have_post_left' => false,
    'already_have_post_right' => false,
    'gate_goes_here' => false,
    'connected_to_previous' => true,
    'gate_preset' => \App\Support\FenceGateSpec::DEFAULT_KEY,
  ];
  $initialSides = old('sides', $isEdit ? ($quotation->sides ?? []) : [$blankRun]);
  if (! is_array($initialSides) || $initialSides === []) {
    $initialSides = [$blankRun];
  }
  $initialSides = \App\Support\FenceSideFlags::normaliseSides($initialSides);
  $initialReprice = \App\Support\FenceSideFlags::isOn(old('reprice', 0));
  $initialTickedGates = count(array_filter(
      $initialSides,
      static fn (array $side): bool => ! empty($side['gate_goes_here'])
  ));

  $initialHeight = (int) old('fence_height_ft', $isEdit ? (int) round((float) $quotation->fence_height_ft) : 6);
  $initialPostSize = old(
      'post_size',
      $isEdit
          ? ($quotation->resolvedPostSize())
          : \App\Models\FenceQuotation::POST_SIZE_4X4
  );
  $initialStyle = old('board_style', $isEdit ? $quotation->board_style : \App\Models\FenceQuotation::BOARD_STYLE_STANDARD);
  $initialGates = (int) old('gate_count', $isEdit ? (int) $quotation->gate_count : 0);
  $initialExtraGatePreset = old(
      'extra_gate_preset',
      $isEdit
          ? (($quotation->calculation['inputs']['extra_gate_preset'] ?? \App\Support\FenceGateSpec::DEFAULT_KEY))
          : \App\Support\FenceGateSpec::DEFAULT_KEY
  );
  $gatePresets = \App\Support\FenceGateSpec::options();
  $priceDelta = null;
  if ($isEdit && is_array($quotation->calculation)) {
      $liveForDelta = (new \App\Services\FenceQuotationCalculator())->calculate([
          'sides' => $quotation->sides ?? [],
          'fence_height_ft' => $quotation->fence_height_ft,
          'post_size' => $quotation->resolvedPostSize(),
          'board_style' => $quotation->board_style,
          'gate_count' => $quotation->gate_count,
          'labour_rate_per_ft' => $quotation->labour_rate_per_ft,
          'labour_rate_8ft_per_ft' => $quotation->labour_rate_8ft_per_ft,
          'include_labour_hst' => $quotation->include_labour_hst,
          'extra_gate_preset' => $initialExtraGatePreset,
      ]);
      $priceDelta = \App\Services\FenceQuotationCalculator::cataloguePriceDelta(
          $liveForDelta,
          is_array($quotation->calculation) ? $quotation->calculation : null
      );
  }
  $priceDeltaAmountLabel = is_array($priceDelta)
      ? \App\Services\FenceQuotationCalculator::formatCatalogueDeltaLabel((float) ($priceDelta['delta'] ?? 0))
      : '';
  $frozenUnitPrices = $isEdit
      ? \App\Services\FenceQuotationCalculator::frozenUnitPricesFromCalculation(
          is_array($quotation->calculation) ? $quotation->calculation : null
      )
      : [];

  $defaultLabour6 = (float) $settings->labour_rate_per_ft;
  $defaultLabour8 = (float) ($settings->labour_rate_8ft_per_ft ?? $settings->labour_rate_per_ft);
  $initialLabour6 = old(
      'labour_rate_per_ft',
      $isEdit ? (float) ($quotation->labour_rate_per_ft ?? $defaultLabour6) : $defaultLabour6
  );
  $initialLabour8 = old(
      'labour_rate_8ft_per_ft',
      $isEdit ? (float) ($quotation->labour_rate_8ft_per_ft ?? $defaultLabour8) : $defaultLabour8
  );
  $initialIncludeLabourHst = old('include_labour_hst') !== null
      ? filter_var(old('include_labour_hst'), FILTER_VALIDATE_BOOLEAN)
      : ($isEdit ? (bool) ($quotation->include_labour_hst ?? true) : true);

  // Initial breakdown (if editing, use the stored snapshot so the page
  // renders with real data before the first preview XHR returns).
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;
  if (is_array($initialCalc) && empty($initialCalc['suggested_work_description'])) {
      $initialCalc['suggested_work_description'] = \App\Support\FenceWorkDescriptionGenerator::generate($initialCalc);
  }

  $linkProject = $linkProject ?? null;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : $linkProject;
@endphp

@section('heading', $title)

@push('head')
  <style>
    {{-- Local styles specific to the fence calculator. General admin
         styles still come from admin.css / admin-client-projects.css. --}}
    .fq-calc-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 1100px) {
      .fq-calc-layout { grid-template-columns: 1fr; }
    }
    .fq-left-stack {
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 0;
    }
    .fq-sides-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 640px) {
      .fq-sides-grid { grid-template-columns: 1fr; }
    }
    .fq-side-card {
      border: 1px solid var(--admin-border);
      border-radius: 8px;
      padding: 12px;
      background: var(--admin-input-bg);
      position: relative;
    }
    .fq-side-card h5 {
      margin: 0 0 8px;
      font-size: 13px;
      font-weight: 600;
      color: var(--admin-heading);
    }
    .fq-side-card__head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
    }
    .fq-side-card__head h5 { margin: 0; }
    .fq-side-remove {
      min-width: 44px;
      min-height: 44px;
    }
    .fq-total-lf {
      font-size: 13px;
      font-weight: 600;
      color: var(--admin-heading);
    }
    .fq-gate-warn,
    .fq-price-banner {
      margin: 0 0 12px;
      padding: 10px 12px;
      border-radius: 8px;
      border: 1px solid var(--admin-border);
      background: var(--admin-surface);
      color: var(--admin-text);
      font-size: 13px;
    }
    .fq-gate-warn.is-hidden,
    .fq-price-banner.is-hidden { display: none; }
    .fq-breakdown-note { display: none; }
    .fq-materials-preview.show-math .fq-breakdown-note { display: block; }
    .fq-side-meta {
      margin-top: 8px;
      font-size: 12px;
      color: #6b7280;
      min-height: 16px;
      line-height: 1.45;
    }
    .fq-geometry-summary {
      margin: 0 0 10px;
      font-size: 13px;
      color: #374151;
    }
    .fq-geometry-per-ft-note {
      font-size: 11px;
      color: #6b7280;
      font-weight: 400;
    }
    .fq-geometry-sides {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 4px;
    }
    .fq-geometry-side {
      padding: 10px 12px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fafbfc;
      font-size: 12px;
      color: #4b5563;
      line-height: 1.5;
    }
    .fq-geometry-side__title {
      font-weight: 600;
      color: #111827;
      margin-bottom: 4px;
    }
    .fq-geometry-side__sections {
      margin-top: 4px;
      color: #6b7280;
    }
    .fq-side-existing-posts {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 6px;
      margin-top: 10px;
      font-size: 12px;
      color: #475569;
    }
    .fq-side-existing-posts__label {
      font-weight: 500;
      color: #64748b;
    }
    .fq-side-existing-posts__checks {
      display: inline-flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 12px;
    }
    .fq-side-existing-posts label.admin-checkbox {
      flex-shrink: 0;
      font-size: 12px;
      font-weight: 500;
      margin: 0;
      gap: 6px;
    }
    .fq-side-existing-posts label.admin-checkbox .admin-checkbox__text {
      flex: 0 0 auto;
    }
    /* Right end: label before checkbox (already have post on [ ] left  right [ ]) */
    .fq-side-post-check--right .admin-checkbox__input:checked ~ .admin-checkbox__mark {
      background: var(--admin-primary, #024768);
      border-color: var(--admin-primary, #024768);
    }
    .fq-side-post-check--right .admin-checkbox__input:checked ~ .admin-checkbox__mark::after {
      content: '';
      position: absolute;
      left: 7px;
      top: 3px;
      width: 6px;
      height: 11px;
      border: solid #fff;
      border-width: 0 2.5px 2.5px 0;
      transform: rotate(45deg);
    }
    .fq-side-post-check--right .admin-checkbox__input:focus-visible ~ .admin-checkbox__mark {
      box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(2, 71, 104, 0.22));
    }
    .fq-side-gate {
      margin-top: 8px;
    }
    .fq-side-gate label.admin-checkbox {
      margin: 0;
      font-size: 12px;
      font-weight: 500;
      gap: 6px;
    }
    .fq-side-gate label.admin-checkbox.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }
    .fq-side-gate-size { margin-top: 8px; }
    .fq-side-gate-size.is-hidden,
    #fq-extra-gate-wrap.is-hidden { display: none; }
    .fq-breakdown-card {
      position: sticky;
      top: 88px;
    }
    .fq-totals {
      margin-top: 14px;
      border-top: 1px solid #e0e4ea;
      padding-top: 12px;
    }
    .fq-totals-row {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
      font-size: 13px;
    }
    .fq-totals-row--total {
      border-top: 1px solid #d8dee4;
      margin-top: 6px;
      padding-top: 10px;
      font-weight: 600;
    }
    .fq-totals-row--grand {
      border-top: 1px solid #d8dee4;
      margin-top: 6px;
      padding-top: 10px;
      font-size: 17px;
      font-weight: 700;
      color: #024768;
    }
    .fq-labour-hst-toggle {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      cursor: pointer;
    }
    .fq-totals-row--labour-hst-excluded .fq-labour-hst-toggle .admin-checkbox__text,
    .fq-totals-row--labour-hst-excluded #fq-t-labour-hst {
      text-decoration: line-through;
      color: #9ca3af;
    }
    .fq-materials-preview {
      width: 100%;
      border-collapse: collapse;
      font-size: 12.5px;
    }
    .fq-materials-preview th,
    .fq-materials-preview td {
      padding: 6px 8px;
      border-bottom: 1px solid #eef0f3;
      text-align: left;
      vertical-align: top;
    }
    .fq-materials-preview th { background: #f5f7f9; font-weight: 600; }
    .fq-materials-preview .num { text-align: right; font-variant-numeric: tabular-nums; }
    .fq-breakdown-note {
      color: var(--admin-muted);
      font-size: 11px;
      margin-top: 2px;
    }
    .fq-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      background: #eef5fb;
      color: #024768;
      font-size: 11px;
      font-weight: 600;
    }
    .fq-badge.is-calculating { background: #fff4d4; color: #8a5d00; }
    .fq-badge.is-error { background: #fde1e1; color: #8a1a1a; }
    .fq-badge.is-idle { background: #eef1f5; color: #556; }
    .fq-badge.is-current { background: #e8f4ea; color: #0f5132; }
    .fq-inline-controls {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      align-items: start;
    }
    .fq-inline-controls .admin-form-group {
      margin: 0;
      min-width: 0;
    }
    .fq-inline-controls .admin-input {
      width: 100%;
    }
    @media (max-width: 1100px) {
      .fq-inline-controls {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 640px) {
      .fq-inline-controls {
        grid-template-columns: 1fr;
      }
    }
    .fq-overlap-wrap { display: none; }
    .fq-overlap-wrap.is-active { display: block; }
    .fq-sides-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .fq-sides-actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    @media (max-width: 640px) {
      .fq-sides-actions {
        width: 100%;
      }
      .fq-sides-actions .admin-btn {
        flex: 1 1 calc(50% - 3px);
      }
    }
    .fq-form-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    .fq-form-toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
    .fq-link-existing-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
    }
    @media (max-width: 640px) {
      .fq-link-existing-row {
        grid-template-columns: 1fr;
      }
      .fq-link-existing-row .admin-btn {
        width: 100%;
        min-height: 46px;
      }
    }
    .fq-create-project-form .admin-form-group {
      margin-bottom: 12px;
    }
    .fq-create-project-form .admin-btn--primary {
      width: 100%;
      margin-top: 4px;
      min-height: 46px;
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
    .fq-quote-labour-section {
      margin-top: 4px;
      padding-top: 16px;
      border-top: 1px dashed #e0e4ea;
    }
    .fq-quote-labour-grid {
      grid-template-columns: 1fr 1fr;
    }
    @media (max-width: 640px) {
      .fq-quote-labour-grid { grid-template-columns: 1fr; }
    }
  </style>
@endpush

@section('content')
  {{-- Toolbar: Back | Save actions -------------------------------- --}}
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.fence.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to fence quotations
    </a>
    <div class="fq-form-toolbar-right">
      <a href="{{ route('admin.quotations.fence.config') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
      @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
      <a href="{{ route('admin.quotations.fence.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      @if ($isEdit)
        <a href="{{ route('admin.quotations.fence.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm"
           data-purchase-list-choice
           data-purchase-list-title="Print material list">
          <i class="fa fa-print"></i> Print Material List
        </a>
        @include('admin.partials.quotation_view_pdf')
      @endif
      @if (! $isEdit)
        <button type="submit" form="fq-form" name="after_save" value="create_project"
                class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-folder-plus"></i> Save &amp; create project
        </button>
      @endif
      <button type="submit" form="fq-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> {{ $isEdit ? 'Save changes' : 'Save quotation' }}
      </button>
    </div>
  </div>

  @if ($isEdit && $quotation->project)
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      <span>
        Linked to project
        <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation">{{ $quotation->project->name }}</a>
        @if ($quotation->linked_at)
          · {{ \App\Models\ClientProject::formatDateTime($quotation->linked_at) }}
        @endif
      </span>
    </div>
  @endif

  @if (!empty($zeroPricedMaterials))
    <div class="admin-flash admin-flash--warning">
      <strong>Heads up:</strong> these materials have no price set yet — the calculator
      will treat them as $0.00:
      {{ implode(', ', $zeroPricedMaterials) }}.
      <a href="{{ route('admin.quotations.fence.config') }}" class="admin-text-link">
        Open configuration
      </a>.
    </div>
  @endif

  @if ($errors->any())
    <div class="admin-flash admin-flash--error">
      <strong>Please fix the following:</strong>
      <ul class="cp-list-indented">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="fq-calc-layout">
    {{-- ====================== LEFT: FORM =========================== --}}
    <div class="fq-left-stack">
      <form id="fq-form" method="post" action="{{ $formAction }}" class="admin-card" data-quotation-editor>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        @if ($linkProject)
          <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
        @endif

        <input type="hidden" name="reprice" id="fq-reprice" value="{{ $initialReprice ? '1' : '0' }}">

        {{-- 1. Client ------------------------------------------------ --}}
        <div class="admin-form-group">
          <label for="fq-client-name">Client name <span class="admin-required">*</span></label>
          <input id="fq-client-name" type="text" name="client_name"
                 @class(['admin-input', 'is-invalid' => $errors->has('client_name')])
                 value="{{ old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? '')) }}"
                 maxlength="160" required>
        </div>

        {{-- 2. Runs -------------------------------------------------- --}}
        <div class="admin-form-group cp-form-group-spaced">
          <div class="fq-sides-header">
            <div>
              <label class="cp-label-flush">Runs (up to {{ \App\Models\FenceQuotation::MAX_RUNS }})</label>
              <p class="admin-form-help cp-form-help-tight-top">
                Length in feet (quarter-foot is fine, e.g. 20.25). Blank extra runs are ignored.
              </p>
              <p class="fq-total-lf" id="fq-total-lf">Total LF: 0</p>
            </div>
            <div class="fq-sides-actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fq-add-side">
                <i class="fa fa-plus"></i> Add run
              </button>
            </div>
          </div>
          <p class="fq-gate-warn is-hidden" id="fq-gate-warn" role="status"></p>
        </div>

        <div id="fq-sides" class="fq-sides-grid"></div>

        {{-- 3. Height / post / style / gates ------------------------- --}}
        <div class="fq-inline-controls">
          <div class="admin-form-group">
            <label for="fq-height">Fence height</label>
            <select name="fence_height_ft" id="fq-height" class="admin-input">
              <option value="6" @selected((int) $initialHeight === 6)>6 feet</option>
              <option value="8" @selected((int) $initialHeight === 8)>8 feet</option>
            </select>
          </div>

          <div class="admin-form-group">
            <label for="fq-post-size">Post size</label>
            <select name="post_size" id="fq-post-size" class="admin-input">
              <option value="{{ \App\Models\FenceQuotation::POST_SIZE_4X4 }}"
                      @selected($initialPostSize === \App\Models\FenceQuotation::POST_SIZE_4X4)>
                4×4
              </option>
              <option value="{{ \App\Models\FenceQuotation::POST_SIZE_6X6 }}"
                      @selected($initialPostSize === \App\Models\FenceQuotation::POST_SIZE_6X6)>
                6×6
              </option>
            </select>
          </div>

          <div class="admin-form-group">
            <label for="fq-style">Board style</label>
            <select name="board_style" id="fq-style" class="admin-input">
              <option value="{{ \App\Models\FenceQuotation::BOARD_STYLE_STANDARD }}"
                      @selected($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_STANDARD)>
                Standard
              </option>
              <option value="{{ \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING }}"
                      @selected($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING)>
                Overlapping
              </option>
            </select>
            <p class="admin-form-help">Clear span between posts is at most the max spacing. An 8 ft top board covers that clear span; it may not reach post centres at near-max spacing.</p>
          </div>

          <div class="admin-form-group">
            <label for="fq-gates">Gates</label>
            <input id="fq-gates" type="number" min="0" max="16" step="1"
                   name="gate_count" class="admin-input"
                   value="{{ $initialGates }}">
            <p class="admin-form-help">Tick “Gate goes here” on a run. Extra gates (count higher than ticked runs) use Extra gate size.</p>
          </div>

          <div class="admin-form-group{{ $initialGates > $initialTickedGates ? '' : ' is-hidden' }}" id="fq-extra-gate-wrap">
            <label for="fq-extra-gate-preset">Extra gate size</label>
            <select name="extra_gate_preset" id="fq-extra-gate-preset" class="admin-input">
              @foreach ($gatePresets as $preset)
                <option value="{{ $preset['key'] }}" @selected($initialExtraGatePreset === $preset['key'])>
                  {{ $preset['label'] }}
                </option>
              @endforeach
            </select>
            <p class="admin-form-help">Size for gates not ticked on a run.</p>
          </div>
        </div>

        {{-- 4. Labour (rate for the chosen height) ------------------- --}}
        <div class="admin-form-section fq-quote-labour-section">
          <h4 class="cp-heading-sm">Labour rate for this quotation</h4>
          <p class="admin-form-help cp-form-help-spaced">
            Stored with this quote{{ $isEdit ? '' : ' (prefilled from formula defaults)' }}.
            First gate is included in the per-foot rate; extra gates add the configured extra-gate labour if any.
          </p>
          <div class="fq-formula-grid fq-quote-labour-grid">
            <div class="admin-form-group" id="fq-labour-6-wrap">
              <label for="fq-labour-rate-6">Labour rate 6&prime; fence (CAD$ / linear ft)</label>
              <input id="fq-labour-rate-6" type="text" name="labour_rate_per_ft"
                     @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('labour_rate_per_ft')])
                     inputmode="decimal" autocomplete="off" required
                     value="{{ number_format((float) $initialLabour6, 2, '.', '') }}" data-cp-money-entry="direct">
              @error('labour_rate_per_ft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group" id="fq-labour-8-wrap">
              <label for="fq-labour-rate-8">Labour rate 8&prime; fence (CAD$ / linear ft)</label>
              <input id="fq-labour-rate-8" type="text" name="labour_rate_8ft_per_ft"
                     @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('labour_rate_8ft_per_ft')])
                     inputmode="decimal" autocomplete="off" required
                     value="{{ number_format((float) $initialLabour8, 2, '.', '') }}" data-cp-money-entry="direct">
              @error('labour_rate_8ft_per_ft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>
        </div>

        {{-- 5. Commute / debris / extras ----------------------------- --}}
        @include('admin.quotations._commute_debris_row')
        @include('admin.quotations._additional_costs', ['quotation' => $quotation])

        {{-- 6. Description (needs runs first for auto-fill) ---------- --}}
        <div class="admin-form-group quotation-work-desc">
          <label for="fq-work-description">Description of the work</label>
          <textarea id="fq-work-description" name="work_description" rows="8"
                    @class(['admin-input', 'is-invalid' => $errors->has('work_description')])
                    placeholder="Describe the service to be performed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'fq-work-description',
              'aiContextFormId' => 'fq-form',
              'aiQuotationType' => 'fence',
              'trailingActionPartial' => 'admin.quotations.fence._work_description_trailing_actions',
          ])
          <p class="admin-form-help">Shown on the client PDF beneath the priced line item. Use Fill from template for a count-accurate draft, then polish with AI if you want.</p>
          @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        {{-- 7. Payment ---------------------------------------------- --}}
        @include('admin.quotations._payment_schedule', ['quotation' => $quotation])

        @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'fq-form'])
        @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'fq-form'])
      </form>

      {{-- Project settings: link existing OR create new (edit, not linked) --}}
      @if ($isEdit && !$quotation->project)
        @php
          $linkable = $linkableProjects ?? collect();
        @endphp
        <div id="fq-create-project">
        @include('admin.quotations._link_to_project_card', [
            'quotation' => $quotation,
            'linkUrl' => route('admin.quotations.fence.link', $quotation),
            'createProjectUrl' => route('admin.quotations.fence.create-project', $quotation),
            'idPrefix' => 'fq',
            'projectNamePlaceholder' => 'Defaults to: Client name — Fence',
            'linkableProjects' => $linkable,
            'wrapAdminCard' => true,
            'title' => 'Connect to a project',
            'helpText' => 'Add this fence estimate as a line on a project <strong>Quotation</strong> (pre-tax materials + labour — the project quotation adds HST).',
            'showExistingLineDescription' => false,
            'createLineDescription' => 'hidden',
            'emptyProjectsMessage' => 'No active projects to link. Create a new one below, or finish a rejected/completed project first.',
            'projectPickerId' => 'fq-link-project',
        ])
        </div>
      @endif
    </div>

    {{-- ====================== RIGHT: BREAKDOWN ==================== --}}
    <aside class="admin-card fq-breakdown-card">
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])

      <h3 class="cp-heading-md">Live breakdown</h3>
      <p class="admin-form-help cp-breakdown-intro">
        Preview updates as you type. <span class="fq-badge" id="fq-status-badge">Calculating…</span>
      </p>
      @if ($isEdit && ! empty($priceDelta['changed']) && ! $initialReprice)
        <div class="fq-price-banner" id="fq-price-banner">
          Catalogue unit prices have changed since this quote was saved{{ $priceDeltaAmountLabel }}.
          @include('admin.partials.info_tip', [
            'tipText' => 'Save keeps the unit prices from the last save. Quantities still follow the current runs. Re-price updates unit prices from the catalogue.',
            'tipLabel' => 'About saved unit prices',
          ])
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fq-reprice-btn">
            Re-price from catalogue
          </button>
        </div>
      @endif
      <p class="admin-form-help">
        <label class="admin-checkbox admin-checkbox--sm">
          <input type="checkbox" class="admin-checkbox__input" id="fq-show-math">
          <span class="admin-checkbox__mark" aria-hidden="true"></span>
          <span class="admin-checkbox__text">Show math</span>
        </label>
      </p>

      <div id="fq-geometry" class="admin-form-help cp-geometry-hint"></div>

      <div class="admin-table-scroll">
        <table class="fq-materials-preview" id="fq-materials">
          <thead>
            <tr>
              <th>Material</th>
              <th class="num">Qty</th>
              <th class="num">Unit</th>
              <th class="num">Subtotal</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="fq-totals">
        <div class="fq-totals-row">
          <span>Materials subtotal</span>
          <span id="fq-t-materials">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>HST on materials (13%)</span>
          <span id="fq-t-hst">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>Labour <span class="admin-form-help" id="fq-t-labour-rate"></span></span>
          <span id="fq-t-labour">CA$0.00</span>
        </div>
        <div class="fq-totals-row fq-totals-row--total">
          <span>Total</span>
          <span id="fq-t-total">CA$0.00</span>
        </div>
        <div class="fq-totals-row{{ $initialIncludeLabourHst ? '' : ' fq-totals-row--labour-hst-excluded' }}" id="fq-t-labour-hst-row">
          <label class="admin-checkbox admin-checkbox--sm fq-labour-hst-toggle" for="fq-include-labour-hst">
            <input type="hidden" name="include_labour_hst" value="0" form="fq-form">
            <input type="checkbox"
                   name="include_labour_hst"
                   value="1"
                   id="fq-include-labour-hst"
                   class="admin-checkbox__input"
                   form="fq-form"
                   title="Uncheck to exclude HST on labour."
                   @checked($initialIncludeLabourHst)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">HST on labour (13%)</span>
          </label>
          <span id="fq-t-labour-hst">CA$0.00</span>
        </div>
        <div class="fq-totals-row is-hidden" id="fq-t-commute-row">
          <span>Commute expense <span class="admin-form-help">(incl. HST)</span></span>
          <span id="fq-t-commute">CA$0.00</span>
        </div>
        <div class="fq-totals-row is-hidden" id="fq-t-debris-row">
          <span>Debris removal <span class="admin-form-help">(incl. HST)</span></span>
          <span id="fq-t-debris">CA$0.00</span>
        </div>
        <div class="fq-totals-row fq-totals-row--grand">
          <span>Grand total</span>
          <span id="fq-t-grand">CA$0.00</span>
        </div>
      </div>
    </aside>
  </div>

@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var previewUrl = @json(route('admin.quotations.fence.preview'));
  var initialSides = @json($initialSides);
  var initialCalc = @json($initialCalc);
  var gatePresets = @json($gatePresets);
  var frozenUnitPrices = @json($frozenUnitPrices);
  var useFrozenPrices = @json($isEdit && !empty($frozenUnitPrices) && !$initialReprice);
  var generateWorkDescriptionBtn = document.getElementById('fq-generate-work-description');
  var workDescriptionInput = document.getElementById('fq-work-description');

  var sidesEl = document.getElementById('fq-sides');
  var addBtn = document.getElementById('fq-add-side');
  var styleSelect = document.getElementById('fq-style');
  var heightSelect = document.getElementById('fq-height');
  var postSizeSelect = document.getElementById('fq-post-size');
  var gatesInput = document.getElementById('fq-gates');
  var labourRate6Input = document.getElementById('fq-labour-rate-6');
  var labourRate8Input = document.getElementById('fq-labour-rate-8');

  var statusBadge = document.getElementById('fq-status-badge');
  var geometryEl = document.getElementById('fq-geometry');
  var materialsBody = document.querySelector('#fq-materials tbody');
  var tMaterials = document.getElementById('fq-t-materials');
  var tHst = document.getElementById('fq-t-hst');
  var tLabour = document.getElementById('fq-t-labour');
  var tLabourRate = document.getElementById('fq-t-labour-rate');
  var tTotal = document.getElementById('fq-t-total');
  var tLabourHst = document.getElementById('fq-t-labour-hst');
  var tLabourHstRow = document.getElementById('fq-t-labour-hst-row');
  var includeLabourHstInput = document.getElementById('fq-include-labour-hst');
  var tGrand = document.getElementById('fq-t-grand');
  var tCommuteRow = document.getElementById('fq-t-commute-row');
  var tCommute = document.getElementById('fq-t-commute');
  var tDebrisRow = document.getElementById('fq-t-debris-row');
  var tDebris = document.getElementById('fq-t-debris');
  var lastCommuteAmount = 0;
  var lastDebrisAmount = 0;
  var lastAdditionalMaterialsPreTax = 0;
  var lastAdditionalMaterialsHst = 0;
  var lastAdditionalLaborPreTax = 0;
  var lastAdditionalLaborHst = 0;
  var lastAdditionalTaxIn = 0;
  var lastCalc = null;
  var repriceInput = document.getElementById('fq-reprice');
  var repriceBtn = document.getElementById('fq-reprice-btn');
  var gateWarnEl = document.getElementById('fq-gate-warn');
  var totalLfEl = document.getElementById('fq-total-lf');
  var labour6Wrap = document.getElementById('fq-labour-6-wrap');
  var labour8Wrap = document.getElementById('fq-labour-8-wrap');
  var showMathInput = document.getElementById('fq-show-math');
  var extraGatePresetInput = document.getElementById('fq-extra-gate-preset');

  var MAX_SIDES = {{ (int) \App\Models\FenceQuotation::MAX_RUNS }};

  // ---------- Side rows -------------------------------------------------
  function gatePresetOptionsHtml(selected) {
    return (gatePresets || []).map(function (preset) {
      var sel = preset.key === selected ? ' selected' : '';
      return '<option value="' + escapeHtml(preset.key) + '"' + sel + '>' + escapeHtml(preset.label) + '</option>';
    }).join('');
  }

  function flagOn(value) {
    return value === true || value === 1 || value === '1';
  }

  // Saved null is off. Missing/undefined (pre-#50) is on. Matches PHP array_key_exists.
  function connectedOn(value, index) {
    if (index <= 0) return false;
    if (value === undefined) return true;
    if (value === null) return false;
    return flagOn(value);
  }

  function renderSides(sides) {
    sidesEl.innerHTML = '';
    sides.forEach(function (side, i) {
      var leftChecked = !!(side && flagOn(side.already_have_post_left));
      var rightChecked = !!(side && flagOn(side.already_have_post_right));
      var gateChecked = !!(side && flagOn(side.gate_goes_here));
      var connectedChecked = connectedOn(side ? side.connected_to_previous : null, i);
      var preset = (side && side.gate_preset) ? side.gate_preset : '4_single';
      var card = document.createElement('div');
      card.className = 'fq-side-card';
      card.innerHTML = [
        '<div class="fq-side-card__head">',
        '  <h5>Run ' + (i + 1) + '</h5>',
        '  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm fq-side-remove" data-remove-run="' + i + '" aria-label="Remove run ' + (i + 1) + '"' + (sides.length <= 1 ? ' disabled' : '') + '>',
        '    <i class="fa fa-times" aria-hidden="true"></i>',
        '  </button>',
        '</div>',
        '<div class="admin-form-group cp-form-group-flush">',
        '  <label for="fq-side-' + i + '">Length (feet)</label>',
        '  <input id="fq-side-' + i + '" type="number" min="0.25" step="0.01"',
        '         name="sides[' + i + '][length_ft]"',
        '         class="admin-input fq-side-length"',
        '         value="' + (side && side.length_ft != null ? side.length_ft : '') + '"',
        '         data-side-index="' + i + '"',
        '         autocomplete="off">',
        '</div>',
        '<div class="fq-side-existing-posts">',
        '  <span class="fq-side-existing-posts__label">already have post on</span>',
        '  <span class="fq-side-existing-posts__checks">',
        '    <label class="admin-checkbox fq-side-post-check--left">',
        '      <input type="hidden" name="sides[' + i + '][already_have_post_left]" value="0">',
        '      <input type="checkbox" class="admin-checkbox__input fq-side-post-left"',
        '             name="sides[' + i + '][already_have_post_left]" value="1"',
        (leftChecked ? ' checked' : '') + '>',
        '      <span class="admin-checkbox__mark" aria-hidden="true"></span>',
        '      <span class="admin-checkbox__text">left</span>',
        '    </label>',
        '    <label class="admin-checkbox fq-side-post-check--right">',
        '      <input type="hidden" name="sides[' + i + '][already_have_post_right]" value="0">',
        '      <input type="checkbox" class="admin-checkbox__input fq-side-post-right"',
        '             name="sides[' + i + '][already_have_post_right]" value="1"',
        (rightChecked ? ' checked' : '') + '>',
        '      <span class="admin-checkbox__text">right</span>',
        '      <span class="admin-checkbox__mark" aria-hidden="true"></span>',
        '    </label>',
        '  </span>',
        '</div>',
        '<div class="fq-side-gate">',
        '  <label class="admin-checkbox fq-side-gate-check">',
        '    <input type="hidden" name="sides[' + i + '][gate_goes_here]" value="0">',
        '    <input type="checkbox" class="admin-checkbox__input fq-side-gate-here"',
        '           name="sides[' + i + '][gate_goes_here]" value="1"',
        (gateChecked ? ' checked' : '') + '>',
        '    <span class="admin-checkbox__mark" aria-hidden="true"></span>',
        '    <span class="admin-checkbox__text">Gate goes here</span>',
        '  </label>',
        '</div>',
        '<div class="admin-form-group fq-side-gate-size' + (gateChecked ? '' : ' is-hidden') + '">',
        '  <label for="fq-gate-preset-' + i + '">Gate size</label>',
        '  <select id="fq-gate-preset-' + i + '" name="sides[' + i + '][gate_preset]" class="admin-input fq-side-gate-preset">',
        gatePresetOptionsHtml(preset),
        '  </select>',
        '</div>',
        (i === 0 ? '' : (
          '<div class="fq-side-gate">' +
          '  <label class="admin-checkbox">' +
          '    <input type="hidden" name="sides[' + i + '][connected_to_previous]" value="0">' +
          '    <input type="checkbox" class="admin-checkbox__input fq-side-connected"' +
          '           name="sides[' + i + '][connected_to_previous]" value="1"' +
          (connectedChecked ? ' checked' : '') + '>' +
          '    <span class="admin-checkbox__mark" aria-hidden="true"></span>' +
          '    <span class="admin-checkbox__text">Connected to previous run</span>' +
          '  </label>' +
          '</div>'
        )),
        '<div class="fq-side-meta" data-meta="' + i + '"></div>'
      ].join('');
      sidesEl.appendChild(card);
    });
    updateGateCheckboxState();
    updateAddRemoveState();
    updateTotalLf();
  }

  function gateCountLimit() {
    var n = parseInt(gatesInput && gatesInput.value, 10);
    return isFinite(n) && n > 0 ? n : 0;
  }

  function updateGateCheckboxState() {
    var limit = gateCountLimit();
    var gateInputs = sidesEl.querySelectorAll('.fq-side-gate-here');
    var checkedCount = 0;

    gateInputs.forEach(function (input) {
      if (input.checked) checkedCount++;
    });

    if (checkedCount > limit) {
      var excess = checkedCount - limit;
      Array.prototype.slice.call(gateInputs).reverse().forEach(function (input) {
        if (excess <= 0) return;
        if (input.checked) {
          input.checked = false;
          excess--;
        }
      });
      checkedCount = limit;
    }

    // Keep every box clickable so checking another run moves the gate.
    gateInputs.forEach(function (input) {
      var label = input.closest('label.admin-checkbox');
      input.disabled = false;
      if (label) label.classList.remove('is-disabled');
      var sizeWrap = input.closest('.fq-side-card');
      if (sizeWrap) {
        var size = sizeWrap.querySelector('.fq-side-gate-size');
        if (size) size.classList.toggle('is-hidden', !input.checked);
      }
    });
    updateGateCountWarning();
  }

  function onGateCheckboxChange(changedInput) {
    if (changedInput.checked && gatesInput) {
      var checkedCount = sidesEl.querySelectorAll('.fq-side-gate-here:checked').length;
      var gateCount = parseInt(gatesInput.value, 10) || 0;
      if (gateCount === 0) {
        gatesInput.value = '1';
      } else if (checkedCount > gateCount) {
        var others = sidesEl.querySelectorAll('.fq-side-gate-here:checked');
        Array.prototype.forEach.call(others, function (input) {
          if (input !== changedInput && checkedCount > gateCount) {
            input.checked = false;
            checkedCount--;
          }
        });
      }
    }

    updateGateCheckboxState();
  }

  function gateWidthForPreset(key) {
    var found = (gatePresets || []).find(function (preset) { return preset.key === key; });
    return found ? Number(found.width_ft) : 4;
  }

  function extraGateLabel() {
    if (!extraGatePresetInput || extraGatePresetInput.selectedIndex < 0) return '4 ft single';
    var opt = extraGatePresetInput.options[extraGatePresetInput.selectedIndex];
    return opt ? opt.text : '4 ft single';
  }

  function toggleExtraGateSize() {
    var wrap = document.getElementById('fq-extra-gate-wrap');
    if (!wrap || !gatesInput) return;
    var ticked = sidesEl.querySelectorAll('.fq-side-gate-here:checked').length;
    var count = parseInt(gatesInput.value, 10) || 0;
    wrap.classList.toggle('is-hidden', count <= ticked);
  }

  function updateGateCountWarning() {
    if (!gateWarnEl || !gatesInput) return;
    var messages = [];
    var ticked = sidesEl.querySelectorAll('.fq-side-gate-here:checked').length;
    var count = parseInt(gatesInput.value, 10) || 0;
    if (count > ticked) {
      messages.push('Gates (' + count + ') is higher than ticked runs (' + ticked + '). Extra gates use Extra gate size (' + extraGateLabel() + ').');
    }
    collectSides().forEach(function (s, i) {
      if (!s.gate_goes_here || !s.length_ft) return;
      var width = gateWidthForPreset(s.gate_preset);
      if (width > s.length_ft) {
        messages.push('Run ' + (i + 1) + ' is ' + formatFt(s.length_ft) + ' ft, shorter than the ' + width + ' ft gate. Opening is clamped to the run.');
      }
    });
    if (messages.length) {
      gateWarnEl.innerHTML = messages.map(escapeHtml).join('<br>');
      gateWarnEl.classList.remove('is-hidden');
    } else {
      gateWarnEl.textContent = '';
      gateWarnEl.classList.add('is-hidden');
    }
    toggleExtraGateSize();
  }

  function updateAddRemoveState() {
    var n = sidesEl.querySelectorAll('.fq-side-card').length;
    addBtn.disabled = n >= MAX_SIDES;
  }

  function updateTotalLf() {
    if (!totalLfEl) return;
    var total = 0;
    collectSides().forEach(function (s) {
      if (s.length_ft) total += Number(s.length_ft);
    });
    totalLfEl.textContent = 'Total LF: ' + (total ? formatFt(total) : '0');
  }

  function updateLabourRateVisibility() {
    var h = parseInt(heightSelect && heightSelect.value, 10) || 6;
    if (labour6Wrap) labour6Wrap.hidden = h !== 6;
    if (labour8Wrap) labour8Wrap.hidden = h !== 8;
  }

  addBtn.addEventListener('click', function () {
    var current = collectSides();
    if (current.length >= MAX_SIDES) return;
    current.push({
      length_ft: null,
      already_have_post_left: false,
      already_have_post_right: false,
      gate_goes_here: false,
      connected_to_previous: true,
      gate_preset: '4_single'
    });
    renderSides(current);
    schedulePreview();
  });

  sidesEl.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-remove-run]') : null;
    if (!btn) return;
    var idx = parseInt(btn.getAttribute('data-remove-run'), 10);
    var current = collectSides();
    if (current.length <= 1 || !isFinite(idx)) return;
    current.splice(idx, 1);
    renderSides(current);
    schedulePreview();
  });

  function collectSides() {
    return Array.prototype.map.call(
      sidesEl.querySelectorAll('.fq-side-card'),
      function (card) {
        var lengthInput = card.querySelector('.fq-side-length');
        var v = parseFloat(lengthInput && lengthInput.value);
        var leftInput = card.querySelector('.fq-side-post-left');
        var rightInput = card.querySelector('.fq-side-post-right');
        var gateInput = card.querySelector('.fq-side-gate-here');
        var connectedInput = card.querySelector('.fq-side-connected');
        var presetInput = card.querySelector('.fq-side-gate-preset');
        var gateChecked = !!(gateInput && gateInput.checked);
        return {
          length_ft: isFinite(v) ? v : null,
          already_have_post_left: !!(leftInput && leftInput.checked),
          already_have_post_right: !!(rightInput && rightInput.checked),
          gate_goes_here: gateChecked,
          connected_to_previous: connectedInput ? !!connectedInput.checked : false,
          gate_preset: presetInput ? presetInput.value : '4_single',
        };
      }
    );
  }

  // ---------- Live preview ---------------------------------------------
  var previewTimer = null;
  function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 220);
  }

  function buildPayload() {
    var sides = collectSides();
    var gateSides = sides.filter(function (s) { return s.gate_goes_here; }).length;
    var gateCount = parseInt(gatesInput.value, 10) || 0;
    if (gateSides > gateCount) {
      gateCount = gateSides;
      gatesInput.value = String(gateCount);
    }
    var payload = {
      sides: sides,
      fence_height_ft: parseInt(heightSelect.value, 10) || 6,
      post_size: postSizeSelect ? postSizeSelect.value : '4x4',
      board_style: styleSelect.value,
      gate_count: gateCount,
      extra_gate_preset: extraGatePresetInput ? extraGatePresetInput.value : '4_single',
      labour_rate_per_ft: parseLabourRate(labourRate6Input),
      labour_rate_8ft_per_ft: parseLabourRate(labourRate8Input),
      include_labour_hst: includeLabourHstInput ? !!includeLabourHstInput.checked : true
    };
    if (useFrozenPrices && frozenUnitPrices && Object.keys(frozenUnitPrices).length) {
      payload.use_frozen_prices = true;
      payload.frozen_unit_prices = frozenUnitPrices;
    }
    return payload;
  }

  function parseLabourRate(input) {
    if (!input) return 0;
    var raw = String(input.value || '').replace(/,/g, '');
    if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
      return window.cpMoneyMask.parse(raw);
    }
    var v = parseFloat(raw);
    return isFinite(v) ? v : 0;
  }

  function runPreview() {
    var payload = buildPayload();
    // If no valid sides yet, show a hint but don't hit the server.
    var hasValidSide = payload.sides.some(function (s) { return s.length_ft && s.length_ft> 0; });
    if (!hasValidSide) {
      renderIdle('Enter at least one run length to see the breakdown.');
      return;
    }

    statusBadge.textContent = 'Calculating…';
    statusBadge.className = 'fq-badge is-calculating';
    statusBadge.style.background = '';
    statusBadge.style.color = '';

    fetch(previewUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json().then(function (body) {
          if (!res.ok) {
            var msg = (body && body.message) ? body.message : '';
            if (!msg && body && body.errors) {
              var firstKey = Object.keys(body.errors)[0];
              msg = firstKey && body.errors[firstKey] && body.errors[firstKey][0]
                ? body.errors[firstKey][0]
                : '';
            }
            throw new Error(msg || ('Preview failed (' + res.status + ')'));
          }
          return body;
        });
      })
      .then(renderCalc)
      .catch(function (err) {
        statusBadge.textContent = (err && err.message) ? err.message : 'Preview failed';
        statusBadge.className = 'fq-badge is-error';
        statusBadge.style.background = '';
        statusBadge.style.color = '';
        console.warn('[fence preview]', err);
      });
  }

  function renderIdle(msg) {
    statusBadge.textContent = 'Idle';
    statusBadge.className = 'fq-badge is-idle';
    statusBadge.style.background = '';
    statusBadge.style.color = '';
    geometryEl.textContent = msg || '';
    materialsBody.innerHTML = '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';
    tMaterials.textContent = 'CA$0.00';
    tHst.textContent = 'CA$0.00';
    tLabour.textContent = 'CA$0.00';
    tGrand.textContent = 'CA$0.00';
    window.__quoteGrandTotal = 0;
    document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: 0 } }));
    tLabourRate.textContent = '';
  }

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatFt(n) {
    var v = Number(n);
    if (!isFinite(v)) return '0.00';
    return v.toFixed(2);
  }

  function formatSectionLengths(lengths) {
    if (!lengths || !lengths.length) return '';
    return lengths.map(formatFt).join(', ') + ' ft';
  }

  function sideMetaHtml(s) {
    var posts = Number(s.posts || 0);
    var parts = [
      s.sections + ' panel' + (s.sections === 1 ? '' : 's'),
      posts + ' post' + (posts === 1 ? '' : 's'),
    ];
    if (s.gate_goes_here) {
      parts.push(formatFt(s.gate_opening_ft || 4) + ' ft gate');
    }
    if (s.section_lengths_ft && s.section_lengths_ft.length) {
      parts.push('spans ' + formatSectionLengths(s.section_lengths_ft));
    } else if (Number(s.max_section_length_ft || 0) > 0) {
      parts.push('max ' + formatFt(s.max_section_length_ft) + ' ft span');
    }
    return parts.join(' · ');
  }

  function sideBreakdownHtml(s) {
    var posts = Number(s.posts || 0);
    var title = 'Run ' + s.index + ': ' + formatFt(s.length_ft) + ' ft';
    var stats = s.sections + ' panel' + (s.sections === 1 ? '' : 's')
      + ' · ' + posts + ' post' + (posts === 1 ? '' : 's');
    if (s.connected_to_previous === false) {
      stats += ' · not connected';
    }
    if (s.gate_goes_here) {
      stats += ' · ' + formatFt(s.gate_opening_ft || 4) + ' ft gate';
      if (Number(s.fence_length_ft || 0) > 0) {
        stats += ' · ' + formatFt(s.fence_length_ft) + ' ft fence run';
      }
    }
    var html = '<div class="fq-geometry-side">'
      + '<div class="fq-geometry-side__title">' + escapeHtml(title) + '</div>'
      + '<div>' + escapeHtml(stats) + '</div>';
    if (s.section_lengths_ft && s.section_lengths_ft.length) {
      html += '<div class="fq-geometry-side__sections">Panel clear spans (excl. posts): '
        + escapeHtml(formatSectionLengths(s.section_lengths_ft)) + '</div>';
    }
    html += '</div>';
    return html;
  }

  function renderCalc(calc) {
    lastCalc = calc;
    statusBadge.textContent = 'Up to date';
    statusBadge.className = 'fq-badge is-current';
    statusBadge.style.background = '';
    statusBadge.style.color = '';

    var g = calc.geometry || {};
    var t = calc.totals || {};
    var sidesHtml = (g.sides_breakdown || []).map(sideBreakdownHtml).join('');
    var linearFt = Number(g.total_linear_ft || 0);
    var withTaxTotal = Number(t.grand_total || 0);
    var perFt = linearFt > 0 ? withTaxTotal / linearFt : 0;
    var perFtHtml = linearFt > 0
      ? ' · <strong>' + money(perFt) + '/ft</strong> <span class="fq-geometry-per-ft-note">(materials + labour, incl. tax)</span>'
      : '';
    var runCount = g.run_count || (g.sides_breakdown || []).length || 0;
    var panelCount = g.panels_total || g.sections_total || 0;
    geometryEl.innerHTML =
      '<p class="fq-geometry-summary"><strong>' + (g.total_linear_ft || 0) + ' linear ft</strong> · '
      + runCount + ' run' + (runCount === 1 ? '' : 's') + ' · '
      + panelCount + ' panel' + (panelCount === 1 ? '' : 's') + ' · '
      + (g.posts_before_waste || 0) + ' posts'
      + perFtHtml + '</p>'
      + (sidesHtml ? '<div class="fq-geometry-sides">' + sidesHtml + '</div>' : '');
    if (generateWorkDescriptionBtn) {
      generateWorkDescriptionBtn.disabled = !calc.suggested_work_description;
    }

    // Per-side section meta on input cards.
    (g.sides_breakdown || []).forEach(function (s) {
      var meta = document.querySelector('[data-meta="' + (s.index - 1) + '"]');
      if (meta) {
        meta.innerHTML = sideMetaHtml(s);
      }
    });

    // Materials table.
    var rows = (calc.materials || []).map(function (m) {
      var unit = (m.unit || '');
      var notesHtml = m.notes
        ? '<span class="fq-breakdown-note">' + escapeHtml(m.notes) + '</span>'
        : '';
      return '<tr>' +
        '<td>' + escapeHtml(m.name) + notesHtml + '</td>' +
        '<td class="num">' + formatQty(m.quantity) + ' ' + escapeHtml(unit) + '</td>' +
        '<td class="num">' + money(m.unit_price) + '</td>' +
        '<td class="num">' + money(m.subtotal) + '</td>' +
        '</tr>';
    }).join('');
    materialsBody.innerHTML = rows || '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';

    tLabour.textContent = money(Number(t.labour_subtotal || 0) + lastAdditionalLaborPreTax);
    tLabourHst.textContent = money(Number(t.labour_hst || 0) + lastAdditionalLaborHst);
    if (tLabourHstRow) {
      tLabourHstRow.classList.toggle(
        'fq-totals-row--labour-hst-excluded',
        t.include_labour_hst === false || (includeLabourHstInput && !includeLabourHstInput.checked)
      );
    }
    if (tCommuteRow) tCommuteRow.classList.toggle('is-hidden', !(lastCommuteAmount> 0));
    if (tCommute) tCommute.textContent = money(lastCommuteAmount);
    if (tDebrisRow) tDebrisRow.classList.toggle('is-hidden', !(lastDebrisAmount > 0));
    if (tDebris) tDebris.textContent = money(lastDebrisAmount);
    tMaterials.textContent = money(Number(t.materials_subtotal || 0) + lastAdditionalMaterialsPreTax);
    tHst.textContent = money(Number(t.hst || 0) + lastAdditionalMaterialsHst);
    var additionalInTotal = lastAdditionalMaterialsPreTax + lastAdditionalMaterialsHst + lastAdditionalLaborPreTax;
    tTotal.textContent = money(Number(t.total || 0) + additionalInTotal);
    tGrand.textContent = money(Number(t.grand_total || 0) + lastCommuteAmount + lastDebrisAmount + lastAdditionalTaxIn);
    window.__quoteGrandTotal = Number(t.grand_total || 0) + lastCommuteAmount + lastDebrisAmount + lastAdditionalTaxIn;
    document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: window.__quoteGrandTotal } }));
    if (t.labour_rate_per_ft) {
      tLabourRate.textContent = '(' + money(t.labour_rate_per_ft) + '/ft)';
    }
  }

  function formatQty(q) {
    if (typeof q !== 'number') q = Number(q);
    if (!isFinite(q)) return '0';
    return Math.abs(q - Math.round(q)) < 1e-6
      ? String(Math.round(q))
      : q.toFixed(2);
  }

  function escapeHtml(s) {
    s = String(s == null ? '' : s);
    return s.replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c];
    });
  }

  // ---------- Wire up everything ---------------------------------------
  renderSides(initialSides);
  updateLabourRateVisibility();

  if (showMathInput) {
    showMathInput.addEventListener('change', function () {
      var table = document.getElementById('fq-materials');
      if (table) table.classList.toggle('show-math', !!showMathInput.checked);
    });
  }

  if (repriceBtn && repriceInput) {
    repriceBtn.addEventListener('click', function () {
      useFrozenPrices = false;
      repriceInput.value = '1';
      var banner = document.getElementById('fq-price-banner');
      if (banner) banner.classList.add('is-hidden');
      schedulePreview();
    });
  }

  if (generateWorkDescriptionBtn && workDescriptionInput) {
    generateWorkDescriptionBtn.addEventListener('click', function () {
      if (!lastCalc || !lastCalc.suggested_work_description) {
        window.alert('Enter at least one run length first so the template can fill counts.');
        return;
      }
      var current = '';
      if (typeof tinymce !== 'undefined') {
        var ed = tinymce.get('fq-work-description');
        if (ed) current = String(ed.getContent({ format: 'text' }) || '').trim();
      }
      if (current === '') current = (workDescriptionInput.value || '').trim();
      if (current !== '' && !window.confirm('Replace the current description with a new one from the template?')) {
        return;
      }
      var next = lastCalc.suggested_work_description;
      if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('fq-work-description');
        if (editor) {
          editor.setContent(String(next || '').replace(/\r\n/g, '\n').split(/\n{2,}/).map(function (block) {
            return '<p>' + String(block).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p>';
          }).join(''));
          editor.save();
          return;
        }
      }
      workDescriptionInput.value = next;
      workDescriptionInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  // Any field change re-runs the preview.
  ['input', 'change'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      if (!e.target || !e.target.closest) return;
      if (e.target.matches('.fq-side-gate-here')) {
        onGateCheckboxChange(e.target);
      }
      if (e.target.matches('#fq-gates')) {
        updateGateCheckboxState();
      }
      if (e.target.matches('#fq-height')) {
        updateLabourRateVisibility();
      }
      if (e.target.matches('.fq-side-length')) {
        updateTotalLf();
        updateGateCountWarning();
      }
      if (e.target.matches('#fq-extra-gate-preset, .fq-side-gate-preset')) {
        updateGateCountWarning();
      }
      if (e.target.matches('#fq-labour-rate-6, #fq-labour-rate-8, #fq-include-labour-hst, #fq-form input, #fq-form select')) {
        schedulePreview();
      }
    });
  });

  if (initialCalc) {
    renderCalc(initialCalc);
  }
  schedulePreview();

  document.addEventListener('commute-expense:change', function (e) {
    var d = e.detail || {};
    lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
    if (lastCalc) {
      renderCalc(lastCalc);
    }
  });
  document.addEventListener('debris-removal:change', function (e) {
    var d = e.detail || {};
    lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
    if (lastCalc) {
      renderCalc(lastCalc);
    }
  });
  document.addEventListener('additional-costs:change', function (e) {
    var d = e.detail || {};
    lastAdditionalMaterialsPreTax = Number(d.materialsPreTax || 0);
    lastAdditionalMaterialsHst = Number(d.materialsHst || 0);
    lastAdditionalLaborPreTax = Number(d.laborPreTax || 0);
    lastAdditionalLaborHst = Number(d.laborHst || 0);
    lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
    if (lastCalc) {
      renderCalc(lastCalc);
    }
  });
})();
</script>
@endpush

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'fq-work-description',
  'formId' => 'fq-form',
])

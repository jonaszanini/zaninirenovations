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
  $preservePid = request('project');
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
      .fq-form-toolbar> a:first-child {
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
    .pq-layout> div:first-child { min-width: 0; }
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .pq-sticky { position: sticky; top: 12px; align-self: start; }
    @media (max-width: 1100px) {
      .pq-layout { grid-template-columns: 1fr; }
      /* Actions + summary first; static positioning so nothing covers inputs while scrolling. */
      .pq-layout> aside { order: -1; }
      .pq-sticky {
        position: static;
        top: auto;
        align-self: stretch;
      }
    }
    .pq-sidebar-actions {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 12px;
      padding: 12px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
    }
    .pq-sidebar-actions .admin-btn {
      flex: 1 1 auto;
      min-width: 0;
      width: auto;
      justify-content: center;
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
    .pq-row span:first-child .admin-form-help { display: inline; margin: 0; font-size: 0.92em; }
    .pq-row strong { flex-shrink: 0; text-align: right; font-weight: 600; color: #111827; white-space: nowrap; font-variant-numeric: tabular-nums; }
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
    .pq-marble-section { margin-top: 16px; padding-top: 14px; border-top: 1px dashed #e2e8f0; }
    .pq-marble-section__head { margin-bottom: 12px; }
    .pq-marble-section__fields { display: flex; flex-direction: column; gap: 12px; }
    .pq-marble-preview { margin: 0; font-size: 15px; font-weight: 600; color: #024768; padding-top: 6px; }
    .pq-marble-preview-wrap .admin-form-group-label { margin-bottom: 0; }
    .pq-area-block { grid-column: 1 / -1; }
    .pq-area-mode { display: flex; flex-wrap: wrap; gap: 12px 20px; margin-bottom: 10px; }
    .pq-area-mode label { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; }
    .pq-area-dimensions { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
    .pq-area-dimensions .admin-form-group { flex: 1 1 120px; min-width: 100px; margin: 0; }
    .pq-area-dimensions-sep { padding-bottom: 10px; font-weight: 600; color: #64748b; }
    .pq-area-computed { margin-top: 8px; font-size: 13px; color: #024768; }
    .pq-stone-entries-block { grid-column: 1 / -1; }
    .pq-stone-entries-head { margin-bottom: 10px; }
    .pq-hint--inline { margin: 4px 0 0; }
    .pq-stone-entries { display: flex; flex-direction: column; gap: 14px; }
    .pq-stone-entry {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 14px;
      background: #fff;
    }
    .pq-stone-entry__toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }
    .pq-stone-entry__title { font-weight: 600; font-size: 13px; color: #334155; }
    .pq-stone-entry__stone {
      display: flex;
      gap: 16px;
      align-items: flex-start;
      margin-bottom: 12px;
    }
    .pq-stone-entry-thumb {
      width: 72px;
      height: 72px;
      flex-shrink: 0;
      border: 2px solid #1e293b;
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .pq-stone-entry-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .pq-stone-entry-thumb .fa { font-size: 28px; color: #cbd5e1; }
    .pq-stone-entry__select-wrap { flex: 1; min-width: 0; }
    .pq-stone-entry__label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569; }
    .pq-stone-entry__mode {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 18px;
      margin-bottom: 10px;
      font-size: 13px;
    }
    .pq-stone-entry__mode label {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 500;
      cursor: pointer;
    }
    .pq-stone-entry__fields .pq-stone-field label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 4px;
      color: #475569;
    }
    .pq-stone-entries-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px 20px;
      margin-top: 12px;
    }
    .pq-total-area-line { margin: 0; font-weight: 600; }
    .pq-preview-status { margin: 0; }
    .pq-preview-status__title {
      margin: 0 0 8px;
      font-size: 13px;
      font-weight: 600;
      color: #92400e;
    }
    .pq-preview-status__list {
      margin: 0;
      padding-left: 1.15rem;
      font-size: 13px;
      color: #475569;
    }
    .pq-preview-status__list li + li { margin-top: 4px; }
  </style>
@endpush

@section('content')
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.paving.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    <div class="fq-form-toolbar-right">
      <a href="{{ route('admin.quotations.paving.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
      @if ($isEdit)
        <a href="{{ route('admin.quotations.paving.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-print"></i> Print Material List
        </a>
      @endif
    </div>
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
              @include('admin.partials.quotation_work_description_ai', [
                  'workDescriptionTextareaId' => 'pq-work-description',
                  'aiContextFormId' => 'pq-main-form',
                  'aiQuotationType' => 'paving',
              ])
              <p class="pq-hint">Shown on the client PDF beneath the priced line item. Plain text — line breaks are preserved.</p>
              @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="pq-form-sections">
              @include('admin.quotations.paving._stone_entries', ['inputs' => $inputs, 'stoneTypes' => $stoneTypes])
              @include('admin.quotations.paving._marble_section', ['inputs' => $inputs, 'marbleTypes' => $marbleTypes])

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
                <p class="pq-hint">Set helper to 0 to disable helper hours on labour tasks. Defaults come from <a href="{{ route('admin.quotations.paving.price-configuration', ['db' => 'rates']) }}">Price configuration → Fixed costs &amp; equipment → Labour</a>.</p>
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
                    <label for="pq-base-depth-display">Crushed base depth (in)</label>
                    <output id="pq-base-depth-display" class="admin-input" style="background:#f8fafc; display:block; min-height:38px; line-height:1.4; padding:8px 12px;">—</output>
                    <p class="pq-hint">Excavation depth − paver height − bedding sand.</p>
                  </div>
                  <div class="admin-form-group">
                    <label for="pq-sand-depth">Bedding sand depth (in)</label>
                    <input type="number" id="pq-sand-depth" name="bedding_sand_depth_in" class="admin-input pq-calc-in" step="0.25" min="0.25" max="6" readonly
                           value="{{ old('bedding_sand_depth_in', $inputs['bedding_sand_depth_in'] ?? 1) }}">
                    <p class="pq-hint">Standard 1 in. screeded layer.</p>
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
          @include('admin.quotations._debris_removal', ['quotation' => $quotation])
          @include('admin.quotations._additional_costs', ['quotation' => $quotation])
          @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
        </form>

        @if ($isEdit && !$quotation->isLinked())
          @include('admin.quotations._link_to_project_card', [
              'quotation' => $quotation,
              'linkUrl' => route('admin.quotations.paving.link', $quotation),
              'createProjectUrl' => route('admin.quotations.paving.create-project', $quotation),
              'idPrefix' => 'pv',
              'projectNamePlaceholder' => 'Defaults to client + Paving',
              'linkableProjects' => $linkableProjects,
          ])
        @endif
      </div>

      <aside class="pq-sticky">
        <div class="pq-sidebar-actions">
          <button type="submit" form="pq-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="pq-btn-publish">
            <i class="fa fa-save"></i> Save quotation
          </button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pq-btn-draft">Save as draft</button>
          <a href="{{ route('admin.quotations.paving.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
        </div>
        @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
        <div class="pq-summary-card">
          <h4 class="cp-form-help-spaced">Live summary</h4>
          <div id="pq-live-root">
            <p class="admin-form-help">Adjust inputs to refresh totals…</p>
          </div>
        </div>
      </aside>
    </div>

  <script>
    (function () {
      var previewUrl = @json(route('admin.quotations.paving.preview'));
      var token = document.querySelector('meta[name="csrf-token"]');
      var tokenV = token ? token.getAttribute('content') : '';

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
        var psqN = parseFloat(opt.getAttribute('data-psq'));
        if (isFinite(sqN) && sqN > 0) {
          parts.push(sqN.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' sq ft per stone');
          if (isFinite(prN) && prN >= 0) {
            parts.push(formatMoney(prN) + ' per stone (before tax)');
          }
        } else if (isFinite(psqN) && psqN > 0) {
          parts.push(formatMoney(psqN) + ' per sq ft (before tax)');
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

      function formatSqFtMeta(sq, psq) {
        var n = parseFloat(sq);
        var pn = parseFloat(psq);
        if (isFinite(n) && n > 0) {
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' sq ft per stone';
        }
        if (isFinite(pn) && pn > 0) {
            return formatMoney(pn) + ' per sq ft';
        }
        return '';
      }

      function syncStoneMeta(sel, metaEl, sqEl, prEl, psqEl, includePrice) {
        if (!metaEl) return;
        if (!sel || (!sqEl && !psqEl)) {
          metaEl.innerHTML = '';
          return;
        }
        var opt = sel.options[sel.selectedIndex];
        if (includePrice && prEl) {
          renderMainStoneMeta(metaEl, opt);
        } else if (opt && opt.value) {
          metaEl.textContent = formatSqFtMeta(opt.getAttribute('data-sq'), opt.getAttribute('data-psq'));
        } else {
          metaEl.textContent = formatSqFtMeta(sqEl.value, psqEl ? psqEl.value : null);
        }
      }

      function wireStoneEntryRow(row) {
        if (!row) return;
        var sel = row.querySelector('.pq-stone-entry-select');
        var metaEl = row.querySelector('.pq-stone-entry-meta');
        var thumbEl = row.querySelector('.pq-stone-entry-img');
        var placeholderEl = row.querySelector('.pq-stone-entry-placeholder');

        function applyStoneVisual() {
          if (!sel) return;
          var opt = sel.options[sel.selectedIndex];
          renderMainStoneMeta(metaEl, opt);
          if (thumbEl && placeholderEl) {
            var img = opt ? (opt.getAttribute('data-img') || '').trim() : '';
            if (img) {
              thumbEl.src = img;
              thumbEl.style.display = 'block';
              placeholderEl.style.display = 'none';
            } else {
              thumbEl.style.display = 'none';
              placeholderEl.style.display = 'block';
            }
          }
          syncBaseDepthDisplay();
          syncTotalAreaLine();
        }

        if (sel) {
          sel.addEventListener('change', function () {
            applyStoneVisual();
            schedule();
          });
          applyStoneVisual();
        }

        row.querySelectorAll('.pq-stone-mode-radio').forEach(function (radio) {
          radio.addEventListener('change', function () {
            syncStoneEntryMode(row);
            syncTotalAreaLine();
            schedule();
          });
        });

        var qtyIn = row.querySelector('.pq-stone-qty-in');
        var areaIn = row.querySelector('.pq-stone-area-in');
        var lenIn = row.querySelector('.pq-stone-len-in');
        var widIn = row.querySelector('.pq-stone-wid-in');
        [qtyIn, areaIn, lenIn, widIn].forEach(function (el) {
          if (el) el.addEventListener('input', function () {
            syncStoneEntryDimComputed(row);
            syncTotalAreaLine();
            schedule();
          });
        });

        syncStoneEntryMode(row);
        syncStoneEntryDimComputed(row);
      }

      function entryMode(row) {
        var checked = row.querySelector('.pq-stone-mode-radio:checked');
        return checked ? checked.value : 'sqft';
      }

      function syncStoneEntryMode(row) {
        var mode = entryMode(row);
        var qtyWrap = row.querySelector('.pq-stone-field--qty');
        var sqftWrap = row.querySelector('.pq-stone-field--sqft');
        var dimWrap = row.querySelector('.pq-stone-field--dimensions');
        var qtyIn = row.querySelector('.pq-stone-qty-in');
        var areaIn = row.querySelector('.pq-stone-area-in');
        var lenIn = row.querySelector('.pq-stone-len-in');
        var widIn = row.querySelector('.pq-stone-wid-in');

        if (qtyWrap) qtyWrap.hidden = mode !== 'qty';
        if (sqftWrap) sqftWrap.hidden = mode !== 'sqft';
        if (dimWrap) dimWrap.hidden = mode !== 'dimensions';
        if (qtyIn) qtyIn.required = mode === 'qty';
        if (areaIn) areaIn.required = mode === 'sqft';
        if (lenIn) lenIn.required = mode === 'dimensions';
        if (widIn) widIn.required = mode === 'dimensions';
        syncStoneEntryDimComputed(row);
      }

      function syncStoneEntryDimComputed(row) {
        if (entryMode(row) !== 'dimensions') return;
        var lenIn = row.querySelector('.pq-stone-len-in');
        var widIn = row.querySelector('.pq-stone-wid-in');
        var computedEl = row.querySelector('.pq-stone-dim-computed');
        if (!lenIn || !widIn || !computedEl) return;
        var len = parseFloat(lenIn.value);
        var wid = parseFloat(widIn.value);
        if (!isFinite(len) || !isFinite(wid) || len <= 0 || wid <= 0) {
          computedEl.textContent = '';
          return;
        }
        var area = Math.round(len * wid * 100) / 100;
        computedEl.textContent = '= ' + area.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' sq ft';
      }

      function entryResolvedArea(row) {
        var mode = entryMode(row);
        if (mode === 'qty') {
          var sel = row.querySelector('.pq-stone-entry-select');
          var qtyIn = row.querySelector('.pq-stone-qty-in');
          if (!sel || !qtyIn) return 0;
          var opt = sel.options[sel.selectedIndex];
          var sq = opt ? parseFloat(opt.getAttribute('data-sq')) : NaN;
          var qty = parseInt(qtyIn.value, 10);
          if (!isFinite(sq) || sq <= 0 || !isFinite(qty) || qty <= 0) return 0;
          return Math.round(qty * sq * 100) / 100;
        }
        if (mode === 'dimensions') {
          var lenIn = row.querySelector('.pq-stone-len-in');
          var widIn = row.querySelector('.pq-stone-wid-in');
          if (!lenIn || !widIn) return 0;
          var len = parseFloat(lenIn.value);
          var wid = parseFloat(widIn.value);
          if (!isFinite(len) || !isFinite(wid) || len <= 0 || wid <= 0) return 0;
          return Math.round(len * wid * 100) / 100;
        }
        var areaIn = row.querySelector('.pq-stone-area-in');
        if (!areaIn) return 0;
        var area = parseFloat(areaIn.value);
        return isFinite(area) && area > 0 ? area : 0;
      }

      function syncTotalAreaLine() {
        var line = document.getElementById('pq-total-area-line');
        if (!line) return;
        var sum = 0;
        document.querySelectorAll('.pq-stone-entry').forEach(function (row) {
          sum += entryResolvedArea(row);
        });
        sum = Math.round(sum * 100) / 100;
        line.textContent = sum > 0
          ? 'Total paving stone area: ' + sum.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' sq ft'
          : '';
      }

      function reindexStoneEntries() {
        document.querySelectorAll('.pq-stone-entry').forEach(function (row, idx) {
          row.dataset.index = String(idx);
          var title = row.querySelector('.pq-stone-entry__title');
          if (title) title.textContent = 'Stone ' + (idx + 1);
          row.querySelectorAll('[name]').forEach(function (el) {
            if (!el.name) return;
            el.name = el.name.replace(/stone_entries\[\d+\]/, 'stone_entries[' + idx + ']');
          });
        });
      }

      function initStoneEntries() {
        var container = document.getElementById('pq-stone-entries');
        var addBtn = document.getElementById('pq-add-stone-entry');
        var template = document.getElementById('pq-stone-entry-template');
        var maxEntries = 3;
        if (!container) return;

        container.querySelectorAll('.pq-stone-entry').forEach(wireStoneEntryRow);

        container.addEventListener('click', function (e) {
          var btn = e.target.closest('.pq-stone-entry-remove');
          if (!btn) return;
          var row = btn.closest('.pq-stone-entry');
          if (!row || container.querySelectorAll('.pq-stone-entry').length <= 1) return;
          row.remove();
          reindexStoneEntries();
          if (addBtn) addBtn.hidden = container.querySelectorAll('.pq-stone-entry').length >= maxEntries;
          syncBaseDepthDisplay();
          syncTotalAreaLine();
          schedule();
        });

        if (addBtn && template) {
          addBtn.addEventListener('click', function () {
            var count = container.querySelectorAll('.pq-stone-entry').length;
            if (count >= maxEntries) return;
            var html = template.innerHTML.replace(/__INDEX__/g, String(count)).replace(/__NUM__/g, String(count + 1));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var row = wrap.firstElementChild;
            container.appendChild(row);
            wireStoneEntryRow(row);
            reindexStoneEntries();
            addBtn.hidden = container.querySelectorAll('.pq-stone-entry').length >= maxEntries;
            syncTotalAreaLine();
            schedule();
          });
        }

        syncTotalAreaLine();
      }
      initStoneEntries();

      (function initMarbleSection() {
        var enabled = document.getElementById('pq-marble-enabled');
        var fields = document.getElementById('pq-marble-fields');
        var typeSel = document.getElementById('pq-marble-type');
        var areaIn = document.getElementById('pq-marble-area');
        var depthIn = document.getElementById('pq-marble-depth');
        var preview = document.getElementById('pq-marble-bags-preview');
        if (!enabled || !fields) return;

        function wasteFactor() {
          var wf = form.querySelector('[name="waste_factor"]');
          var w = wf ? parseFloat(wf.value) : 0.05;
          if (!isFinite(w)) w = 0.05;
          if (w > 1) w /= 100;
          return w;
        }

        function syncMarbleRequired(on) {
          [typeSel, areaIn, depthIn].forEach(function (el) {
            if (el) el.required = !!on;
          });
        }

        function updateMarbleBagPreview() {
          if (!preview || !enabled.checked) {
            if (preview) preview.textContent = '—';
            return;
          }
          if (!typeSel || typeSel.selectedIndex < 0) {
            preview.textContent = '—';
            return;
          }
          var opt = typeSel.options[typeSel.selectedIndex];
          var cuFt = parseFloat(opt.getAttribute('data-cuft-bag'));
          var area = areaIn ? parseFloat(areaIn.value) : 0;
          var depth = depthIn ? parseFloat(depthIn.value) : 2;
          if (!isFinite(cuFt) || cuFt <= 0 || !isFinite(area) || area <= 0 || !isFinite(depth) || depth <= 0) {
            preview.textContent = '—';
            return;
          }
          var volume = area * (depth / 12) * (1 + wasteFactor());
          var bags = Math.ceil(volume / cuFt - 1e-9);
          preview.textContent = bags + ' bag' + (bags === 1 ? '' : 's');
        }

        function syncMarbleVisibility() {
          var on = enabled.checked;
          fields.hidden = !on;
          syncMarbleRequired(on);
          updateMarbleBagPreview();
          schedule();
        }

        enabled.addEventListener('change', syncMarbleVisibility);
        [typeSel, areaIn, depthIn].forEach(function (el) {
          if (el) el.addEventListener('input', updateMarbleBagPreview);
        });
        syncMarbleVisibility();
      })();

      function syncBaseDepthDisplay() {
        var display = document.getElementById('pq-base-depth-display');
        var depthInput = document.getElementById('pq-depth');
        var sandInput = document.getElementById('pq-sand-depth');
        if (!display || !depthInput) return;

        var excav = parseFloat(depthInput.value) || 0;
        var sand = sandInput ? (parseFloat(sandInput.value) || 1) : 1;
        var hgt = 0;
        document.querySelectorAll('.pq-stone-entry-select').forEach(function (sel) {
          if (sel.selectedIndex < 0) return;
          var opt = sel.options[sel.selectedIndex];
          var parsed = parseFloat(opt.getAttribute('data-hgt'));
          if (isFinite(parsed) && parsed > hgt) hgt = parsed;
        });
        if (hgt <= 0) hgt = 2;
        var base = Math.max(0, excav - hgt - sand);
        if (excav <= 0) {
          display.textContent = '—';
          return;
        }
        display.textContent = (Math.abs(base - Math.round(base)) < 1e-6 ? String(Math.round(base)) : base.toFixed(2)) + ' in.';
      }

      ['pq-depth', 'pq-sand-depth'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', syncBaseDepthDisplay);
      });
      syncBaseDepthDisplay();

      var root = document.getElementById('pq-live-root');
      var t = null;
      var lastCommuteAmount = 0;
      var lastDebrisAmount = 0;
      var lastAdditionalTaxIn = 0;
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
      function row(label, value, cls, extraCls) {
        var divClass = 'pq-row';
        if (cls) {
          divClass += ' ' + cls;
        }
        if (extraCls) {
          divClass += ' ' + extraCls;
        }
        return '<div class="' + divClass + '"><span>' + label + '</span><strong>' + value + '</strong></div>';
      }
      function render(c) {
        if (!c || !c.summary) return;
        lastPreviewCalc = c;
        var s = c.summary;
        var m = c.materials;
        var f = c.fixed_costs;
        var l = c.labour;
        var quoteBeforeCommute = Number(s.final_quote_total || 0);
        var clientTotal = quoteBeforeCommute + lastCommuteAmount + lastDebrisAmount + lastAdditionalTaxIn;
        var calculatedTotal = Number(s.built_up_grand_total || 0);
        var hasManual = s.manual_price_override != null && Number(s.manual_price_override)> 0;
        var subtotal = Number(s.total_with_tax_mats_fixed_plus_labour || 0);
        var profitAmt = Number(s.profit_margin_amount || 0);
        var profitRate = fmtPct(s.profit_margin_rate);
        var html = '';

        html += section('Costs', [
          row('Materials <span class="admin-form-help">(incl. HST)</span>', fmt(Number(m.with_tax || 0) + lastAdditionalTaxIn)),
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
        if (lastCommuteAmount> 0) {
          quoteRows.push(row('Commute expense <span class="admin-form-help">(incl. HST)</span>', fmt(lastCommuteAmount)));
        }
        if (lastDebrisAmount > 0) {
          quoteRows.push(row('Debris removal <span class="admin-form-help">(incl. HST)</span>', fmt(lastDebrisAmount)));
        }
        quoteRows.push(row('Total quote', fmt(clientTotal), 'pq-row--total'));
        html += section('Quote', quoteRows.join(''));

        if (s.market_reference_total && Math.abs(Number(s.market_reference_total) - calculatedTotal)> 0.01) {
          html += section('Reference', [
            row('Market tier estimate', fmt(s.market_reference_total), 'pq-row--muted'),
            '<div class="pq-row pq-row--note"><span>From pricing table — not added to the quote.</span></div>',
          ].join(''));
        }

        if (c.marble && c.marble.enabled) {
          var mb = c.marble;
          html += section('Decorative marble', [
            row(mb.marble_name || 'Marble bed', mb.area_sq_ft + ' sq ft @ ' + mb.depth_in + ' in.'),
            row('Bags', String(mb.bags || 0)),
            row('Marble subtotal <span class="admin-form-help">(incl. HST + labour)</span>', fmt(mb.subtotal_with_tax_and_labour || 0)),
          ].join(''));
        }

        var gp = Number(s.gross_profit || 0);
        html += section('Profitability', [
          row('Gross profit', fmt(gp), '', gp>= 0 ? 'pq-profit-pos' : 'pq-profit-neg'),
          row('Margin', fmtPct(s.margin_percent)),
          row('Est. working days', String(s.working_days) + ' days'),
        ].join(''));

        root.innerHTML = html;
      }

      function collectMissingPreviewInputs() {
        var missing = [];
        var stoneRows = document.querySelectorAll('.pq-stone-entry');
        if (!stoneRows.length) {
          missing.push('Stone types — add at least one stone type');
        }
        stoneRows.forEach(function (row, idx) {
          var num = idx + 1;
          var sel = row.querySelector('.pq-stone-entry-select');
          if (!sel || !sel.value) {
            missing.push('Stone ' + num + ' — select a stone type');
            return;
          }
          var modeEl = row.querySelector('.pq-stone-mode-radio:checked');
          var modeVal = modeEl ? modeEl.value : 'sqft';
          if (modeVal === 'qty') {
            var qty = parseFloat((row.querySelector('.pq-stone-qty-in') || {}).value);
            if (!isFinite(qty) || qty < 1) missing.push('Stone ' + num + ' — enter number of stones');
          } else if (modeVal === 'dimensions') {
            var len = parseFloat((row.querySelector('.pq-stone-len-in') || {}).value);
            var wid = parseFloat((row.querySelector('.pq-stone-wid-in') || {}).value);
            if (!isFinite(len) || len <= 0 || !isFinite(wid) || wid <= 0) {
              missing.push('Stone ' + num + ' — enter length and width (ft)');
            }
          } else {
            var area = parseFloat((row.querySelector('.pq-stone-area-in') || {}).value);
            if (!isFinite(area) || area <= 0) missing.push('Stone ' + num + ' — enter area (sq ft)');
          }
        });

        var marbleEnabled = document.getElementById('pq-marble-enabled');
        if (marbleEnabled && marbleEnabled.checked) {
          var marbleType = document.getElementById('pq-marble-type');
          if (!marbleType || !marbleType.value) missing.push('Marble — select a marble type');
          var marbleArea = parseFloat((document.getElementById('pq-marble-area') || {}).value);
          if (!isFinite(marbleArea) || marbleArea < 0.1) missing.push('Marble — enter area (sq ft)');
          var marbleDepth = parseFloat((document.getElementById('pq-marble-depth') || {}).value);
          if (!isFinite(marbleDepth) || marbleDepth < 0.5) missing.push('Marble — enter bed depth (in)');
        }

        var excav = parseFloat((document.getElementById('pq-depth') || {}).value);
        if (!isFinite(excav) || excav < 1) missing.push('Excavation depth (in)');

        return missing;
      }

      function flattenValidationErrors(payload) {
        var list = [];
        if (!payload || !payload.errors) return list;
        Object.keys(payload.errors).forEach(function (key) {
          var msgs = payload.errors[key];
          if (!Array.isArray(msgs)) return;
          msgs.forEach(function (msg) {
            if (msg && list.indexOf(msg) === -1) list.push(msg);
          });
        });
        return list;
      }

      function renderMissingSummary(items) {
        if (!items.length) {
          root.innerHTML = '<p class="admin-form-help">Adjust inputs to refresh totals…</p>';
          return;
        }
        root.innerHTML =
          '<div class="pq-preview-status">' +
            '<p class="pq-preview-status__title">Complete these Project inputs to see totals:</p>' +
            '<ul class="pq-preview-status__list">' +
              items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') +
            '</ul>' +
          '</div>';
      }

      function appendPreviewFormData(fd) {
        if (!fd.get('marble_enabled')) {
          fd.delete('marble_type_id');
          fd.delete('marble_area_sq_ft');
          fd.delete('marble_depth_in');
          fd.delete('marble_include_geotextile');
        }
      }

      function runPreview() {
        normalizeLabourRateFields();
        var missing = collectMissingPreviewInputs();
        if (missing.length) {
          renderMissingSummary(missing);
          lastPreviewCalc = null;
          return;
        }
        var fd = new FormData(form);
        fd.delete('_method');
        appendPreviewFormData(fd);
        fetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': tokenV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) {
            return r.json().then(function (data) {
              return { ok: r.ok, data: data };
            });
          })
          .then(function (result) {
            if (!result.ok) {
              var errItems = flattenValidationErrors(result.data);
              if (!errItems.length && result.data && result.data.message) {
                errItems.push(result.data.message);
              }
              renderMissingSummary(errItems.length ? errItems : ['Could not calculate — check your inputs.']);
              lastPreviewCalc = null;
              return;
            }
            if (!result.data || !result.data.summary) {
              renderMissingSummary(['Calculator did not return a summary.']);
              lastPreviewCalc = null;
              return;
            }
            render(result.data);
          })
          .catch(function () {
            root.innerHTML = '<p class="admin-form-help">Could not refresh preview.</p>';
            lastPreviewCalc = null;
          });
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

      var initial = @json($initialCalc);
      if (initial) { render(initial); } else { runPreview(); }

      document.addEventListener('commute-expense:change', function (e) {
        var d = e.detail || {};
        lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
      document.addEventListener('debris-removal:change', function (e) {
        var d = e.detail || {};
        lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
      document.addEventListener('additional-costs:change', function (e) {
        var d = e.detail || {};
        lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
    })();
  </script>
@endsection

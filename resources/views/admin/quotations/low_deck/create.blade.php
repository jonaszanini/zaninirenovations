@extends('admin.layouts.app')

@php
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit low deck quotation '.$quotation->quote_number : 'New low deck quotation';
  $formAction = $isEdit
    ? route('admin.quotations.low-deck.update', $quotation)
    : route('admin.quotations.low-deck.store');

  $initialWidth = old('width_ft', $isEdit ? rtrim(rtrim(number_format((float) $quotation->width_ft, 2, '.', ''), '0'), '.') : '');
  $initialLength = old('length_ft', $isEdit ? rtrim(rtrim(number_format((float) $quotation->length_ft, 2, '.', ''), '0'), '.') : '');

  $defaultLabour = (float) $settings->labour_rate_per_sqft;
  $defaultWasteFee = (float) $settings->waste_dump_fee_cad;
  $initialLabour = old('labour_rate_per_sqft', $isEdit ? (float) ($quotation->labour_rate_per_sqft ?? $defaultLabour) : $defaultLabour);
  $initialWasteFee = old('waste_dump_fee_cad', $isEdit ? (float) ($quotation->waste_dump_fee_cad ?? $defaultWasteFee) : $defaultWasteFee);
  $initialMaterialWaste = old(
      'material_waste_percent',
      $isEdit ? (float) ($quotation->calculation['inputs']['material_waste_percent'] ?? 10) : 10
  );
  $initialIncludeUnderDeckGravel = (bool) old(
      'include_under_deck_gravel',
      $isEdit ? (bool) ($quotation->calculation['inputs']['include_under_deck_gravel'] ?? true) : true
  );
  $initialIncludeLabourHst = old('include_labour_hst') !== null
      ? filter_var(old('include_labour_hst'), FILTER_VALIDATE_BOOLEAN)
      : ($isEdit ? (bool) ($quotation->include_labour_hst ?? true) : true);
  $initialIncludeDiagrams = (bool) old('include_diagrams_on_pdf', $isEdit ? $quotation->include_diagrams_on_pdf : false);

  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;
  $linkProject = $linkProject ?? null;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : $linkProject;
@endphp

@section('heading', $title)

@push('head')
  <style>
    .ld-calc-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 1100px) {
      .ld-calc-layout { grid-template-columns: 1fr; }
    }
    .ld-left-stack { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .ld-dims-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      align-items: start;
    }
    @media (max-width: 640px) { .ld-dims-grid { grid-template-columns: 1fr; } }
    .ld-rates-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }
    @media (max-width: 640px) { .ld-rates-grid { grid-template-columns: 1fr; } }
    .ld-breakdown-card { position: sticky; top: 88px; }
    .ld-totals { margin-top: 14px; border-top: 1px solid #e0e4ea; padding-top: 12px; }
    .ld-totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
    .ld-totals-row--total { border-top: 1px solid #d8dee4; margin-top: 6px; padding-top: 10px; font-weight: 600; }
    .ld-labour-hst-toggle {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      cursor: pointer;
    }
    .ld-totals-row--labour-hst-excluded .ld-labour-hst-toggle .admin-checkbox__text,
    .ld-totals-row--labour-hst-excluded #ld-t-labour-hst {
      text-decoration: line-through;
      color: #9ca3af;
    }
    .ld-totals-row--grand {
      border-top: 1px solid #d8dee4; margin-top: 6px; padding-top: 10px;
      font-size: 17px; font-weight: 700; color: #024768;
    }
    .ld-materials-preview { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .ld-materials-preview th, .ld-materials-preview td {
      padding: 6px 8px; border-bottom: 1px solid #eef0f3; text-align: left; vertical-align: top;
    }
    .ld-materials-preview th { background: #f5f7f9; font-weight: 600; }
    .ld-materials-preview .num { text-align: right; font-variant-numeric: tabular-nums; }
    .ld-breakdown-note { color: #6b7280; font-size: 11px; display: block; margin-top: 2px; }
    .ld-badge {
      display: inline-block; padding: 2px 8px; border-radius: 999px;
      background: #eef5fb; color: #024768; font-size: 11px; font-weight: 600;
    }
    .ld-form-toolbar {
      display: flex; justify-content: space-between; align-items: center;
      gap: 12px; flex-wrap: wrap; margin-bottom: 16px;
    }
    .ld-form-toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
    .ld-linked-banner {
      display: flex; gap: 8px; align-items: center; background: #e8f4ea; color: #0f5132;
      border: 1px solid #bfddc6; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px;
    }
    .ld-linked-banner a { color: #0f5132; font-weight: 600; }
    .ld-diagrams-card { margin-top: 20px; }
    .ld-diagrams-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
      margin-top: 12px;
    }
    @media (max-width: 980px) { .ld-diagrams-grid { grid-template-columns: 1fr; } }
    .ld-diagram-panel { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #fff; }
    .ld-diagram-panel h5 { margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #374151; }
    .ld-diagram-svg { width: 100%; height: auto; display: block; }
    .ld-diagrams-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 8px;
    }
    .ld-diagrams-toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .ld-diagram-print-header { display: none; margin: 0 0 12px; font-size: 14px; color: #374151; }
    @media print {
      .admin-sidebar,
      .admin-nav-backdrop,
      .admin-topbar,
      .ld-form-toolbar,
      .ld-calc-layout,
      .ld-linked-banner,
      .admin-flash,
      .no-print { display: none !important; }
      .admin-main,
      .admin-content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
      .admin-body { background: #fff !important; }
      .ld-diagrams-card {
        margin: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
      }
      .ld-diagrams-grid {
        grid-template-columns: 1fr;
        gap: 22px;
      }
      .ld-diagram-panel {
        break-inside: avoid;
        page-break-inside: avoid;
      }
      .ld-diagram-print-header { display: block !important; }
      .ld-diagram-svg,
      .ld-diagram-panel {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
    .ld-section { margin-top: 4px; padding-top: 16px; border-top: 1px dashed #e0e4ea; }
  </style>
@endpush

@section('content')
  <div class="ld-form-toolbar">
    <a href="{{ route('admin.quotations.low-deck.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to low deck quotations
    </a>
    <div class="ld-form-toolbar-right">
      <a href="{{ route('admin.quotations.low-deck.config') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
      @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
      <a href="{{ route('admin.quotations.low-deck.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      @if ($isEdit)
        <a href="{{ route('admin.quotations.low-deck.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm"
           data-purchase-list-choice
           data-purchase-list-title="Print shopping list">
          <i class="fa fa-print"></i> Print Shopping List
        </a>
        <a href="{{ route('admin.quotations.low-deck.layout-diagrams', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-print"></i> Print Layout Diagrams
        </a>
        @include('admin.partials.quotation_view_pdf')
      @endif
      <button type="submit" form="ld-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> {{ $isEdit ? 'Save changes' : 'Save quotation' }}
      </button>
    </div>
  </div>

  @if ($isEdit && $quotation->project)
    <div class="ld-linked-banner">
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
      <a href="{{ route('admin.quotations.low-deck.config') }}" class="admin-text-link">
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

  <div class="ld-calc-layout">
    {{-- ====================== LEFT: FORM =========================== --}}
    <div class="ld-left-stack">
      <form id="ld-form" method="post" action="{{ $formAction }}" class="admin-card" data-quotation-editor>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        @if ($linkProject)
          <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
        @endif

        <div class="admin-form-group">
          <label for="ld-client-name">Client name <span class="admin-required">*</span></label>
          <input id="ld-client-name" type="text" name="client_name"
                 @class(['admin-input', 'is-invalid' => $errors->has('client_name')])
                 value="{{ old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? '')) }}"
                 maxlength="160" required>
        </div>

        <div class="admin-form-group quotation-work-desc">
          <label for="ld-work-description">Description of the work</label>
          <textarea id="ld-work-description" name="work_description" rows="8"
                    @class(['admin-input', 'is-invalid' => $errors->has('work_description')])
                    placeholder="Describe the service to be performed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'ld-work-description',
              'aiContextFormId' => 'ld-form',
              'aiQuotationType' => 'low_deck',
              'trailingActionPartial' => 'admin.quotations.low_deck._work_description_trailing_actions',
          ])
          <p class="admin-form-help">Shown on the client PDF beneath the priced line item. Formatting (bold, lists, headings) is preserved.</p>
          @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])
        @include('admin.quotations._debris_removal', ['quotation' => $quotation])
        @include('admin.quotations._additional_costs', ['quotation' => $quotation])
        @include('admin.quotations._payment_schedule', ['quotation' => $quotation])

        {{-- Dimensions ------------------------------------------------ --}}
        <div class="admin-form-section ld-section">
          <h4 class="cp-heading-sm">Deck dimensions</h4>
          <p class="admin-form-help cp-form-help-spaced">
            Maximum {{ rtrim(rtrim(number_format((float) $settings->max_area_sq_ft, 2), '0'), '.') }} sq ft footprint (Ontario Building Code floating deck guardrail).
          </p>
          <div class="ld-dims-grid">
            <div class="admin-form-group">
              <label for="ld-width">Width (feet) <span class="admin-required">*</span></label>
              <input id="ld-width" type="number" min="1" max="500" step="0.1" name="width_ft"
                     @class(['admin-input', 'is-invalid' => $errors->has('width_ft')])
                     value="{{ $initialWidth }}" autocomplete="off" required>
              @error('width_ft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="ld-length">Length (feet) <span class="admin-required">*</span></label>
              <input id="ld-length" type="number" min="1" max="500" step="0.1" name="length_ft"
                     @class(['admin-input', 'is-invalid' => $errors->has('length_ft')])
                     value="{{ $initialLength }}" autocomplete="off" required>
              @error('length_ft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>
          <p id="ld-area-display" class="admin-form-help cp-form-help-spaced" style="display: none; margin-top: 10px;">
            Total area: <strong id="ld-area-value">0</strong> sq ft
          </p>
          <p class="admin-form-help cp-form-help-spaced">
            Field joist spacing is calculated automatically (uniform bays, max 15 in. between joists). Four edge joists sit 2¼ in. inside the perimeter rim.
          </p>
          <div class="admin-form-group cp-form-group-spaced">
            <label class="admin-checkbox">
              <input type="hidden" name="include_under_deck_gravel" value="0">
              <input type="checkbox" class="admin-checkbox__input" id="ld-under-deck-gravel"
                     name="include_under_deck_gravel" value="1"
                     @checked($initialIncludeUnderDeckGravel)>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Gravel under the deck</span>
            </label>
            <p class="admin-form-help">Adds under-deck gravel bags (1 per {{ rtrim(rtrim(number_format((float) ($settings->under_deck_gravel_sq_ft_per_bag ?? 8), 2), '0'), '.') }} sq ft, same 30kg 1/2in gravel as under the blocks), weed-control fabric, and fabric spikes.</p>
          </div>
        </div>

        {{-- Rates ----------------------------------------------------- --}}
        <div class="admin-form-section ld-section">
          <h4 class="cp-heading-sm">Rates for this quotation</h4>
          <p class="admin-form-help cp-form-help-spaced">
            Prefilled from configuration defaults. Change them here to adjust this project only.
          </p>
          <div class="ld-rates-grid">
            <div class="admin-form-group">
              <label for="ld-labour-rate">Labour rate (CA$ / sq ft)</label>
              <input id="ld-labour-rate" type="text" name="labour_rate_per_sqft"
                     @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('labour_rate_per_sqft')])
                     inputmode="decimal" autocomplete="off"
                     value="{{ number_format((float) $initialLabour, 2, '.', '') }}">
              @error('labour_rate_per_sqft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="ld-material-waste">Material waste (%)</label>
              <input id="ld-material-waste" type="number" min="0" max="100" step="0.1" name="material_waste_percent"
                     @class(['admin-input', 'is-invalid' => $errors->has('material_waste_percent')])
                     value="{{ rtrim(rtrim(number_format((float) $initialMaterialWaste, 2, '.', ''), '0'), '.') }}"
                     autocomplete="off">
              @error('material_waste_percent') <p class="admin-form-error">{{ $message }}</p> @enderror
              <p class="admin-form-help">Extra material for cuts and breakage. Internal ordering buffer — not shown on the client quotation.</p>
            </div>
            <div class="admin-form-group">
              <label for="ld-waste-fee">Material dump fee</label>
              <input id="ld-waste-fee" type="text" name="waste_dump_fee_cad"
                     @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('waste_dump_fee_cad')])
                     inputmode="decimal" autocomplete="off"
                     value="{{ number_format((float) $initialWasteFee, 2, '.', '') }}">
              @error('waste_dump_fee_cad') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="admin-form-group cp-form-group-spaced">
            <label class="admin-checkbox">
              <input type="hidden" name="include_diagrams_on_pdf" value="0">
              <input type="checkbox" class="admin-checkbox__input" name="include_diagrams_on_pdf" value="1"
                     @checked($initialIncludeDiagrams)>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Include layout diagrams on the client PDF</span>
            </label>
          </div>
        </div>
      </form>

      @if ($isEdit && !$quotation->project)
        @php $linkable = $linkableProjects ?? collect(); @endphp
        @include('admin.quotations._link_to_project_card', [
            'quotation' => $quotation,
            'linkUrl' => route('admin.quotations.low-deck.link', $quotation),
            'createProjectUrl' => route('admin.quotations.low-deck.create-project', $quotation),
            'idPrefix' => 'ld',
            'projectNamePlaceholder' => 'Defaults to: Client name — Low deck',
            'linkableProjects' => $linkable,
            'wrapAdminCard' => true,
            'title' => 'Connect to a project',
            'helpText' => 'Add this deck estimate as a line on a project <strong>Quotation</strong> (pre-tax materials + labour — the project quotation adds HST).',
            'showExistingLineDescription' => false,
            'createLineDescription' => 'hidden',
            'emptyProjectsMessage' => 'No active projects to link. Create a new one below, or finish a rejected/completed project first.',
            'projectPickerId' => 'ld-link-project',
        ])
      @endif
    </div>

    {{-- ====================== RIGHT: BREAKDOWN ==================== --}}
    <aside class="admin-card ld-breakdown-card">
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
      @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'ld-form'])

      <h3 class="cp-heading-md">Live breakdown</h3>
      <p class="admin-form-help cp-breakdown-intro">
        Preview updates as you type. <span class="ld-badge" id="ld-status-badge">Calculating…</span>
      </p>

      <div id="ld-geometry" class="admin-form-help cp-geometry-hint"></div>

      <div class="admin-table-scroll">
        <table class="ld-materials-preview" id="ld-materials">
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

      <div class="ld-totals">
        <div class="ld-totals-row is-hidden" id="ld-t-material-waste-row">
          <span>Material waste allowance <span class="admin-form-help" id="ld-t-material-waste-label">(internal)</span></span>
          <span id="ld-t-material-waste">CA$0.00</span>
        </div>
        <div class="ld-totals-row">
          <span>Materials subtotal <span class="admin-form-help">(incl. dump fee)</span></span>
          <span id="ld-t-materials">CA$0.00</span>
        </div>
        <div class="ld-totals-row">
          <span>HST on materials (13%)</span>
          <span id="ld-t-hst">CA$0.00</span>
        </div>
        <div class="ld-totals-row">
          <span>Labour <span class="admin-form-help" id="ld-t-labour-rate"></span></span>
          <span id="ld-t-labour">CA$0.00</span>
        </div>
        <div class="ld-totals-row ld-totals-row--total">
          <span>Total</span>
          <span id="ld-t-total">CA$0.00</span>
        </div>
        <div class="ld-totals-row{{ $initialIncludeLabourHst ? '' : ' ld-totals-row--labour-hst-excluded' }}" id="ld-t-labour-hst-row">
          <label class="admin-checkbox admin-checkbox--sm ld-labour-hst-toggle" for="ld-include-labour-hst">
            <input type="hidden" name="include_labour_hst" value="0" form="ld-form">
            <input type="checkbox"
                   name="include_labour_hst"
                   value="1"
                   id="ld-include-labour-hst"
                   class="admin-checkbox__input"
                   form="ld-form"
                   title="Uncheck to exclude HST on labour."
                   @checked($initialIncludeLabourHst)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">HST on labour (13%)</span>
          </label>
          <span id="ld-t-labour-hst">CA$0.00</span>
        </div>
        <div class="ld-totals-row is-hidden" id="ld-t-commute-row">
          <span>Commute expense <span class="admin-form-help">(incl. HST)</span></span>
          <span id="ld-t-commute">CA$0.00</span>
        </div>
        <div class="ld-totals-row is-hidden" id="ld-t-debris-row">
          <span>Debris removal <span class="admin-form-help">(incl. HST)</span></span>
          <span id="ld-t-debris">CA$0.00</span>
        </div>
        <div class="ld-totals-row ld-totals-row--grand">
          <span>Grand total</span>
          <span id="ld-t-grand">CA$0.00</span>
        </div>
      </div>
    </aside>
  </div>

  {{-- ====================== DIAGRAMS ============================= --}}
  <div class="admin-card ld-diagrams-card">
    <div class="ld-diagrams-toolbar no-print">
      <h3 class="cp-heading-md" style="margin:0;">Layout diagrams</h3>
      <div class="ld-diagrams-toolbar-actions">
        @if ($isEdit)
          <a href="{{ route('admin.quotations.low-deck.layout-diagrams', $quotation) }}"
             target="_blank" rel="noopener noreferrer"
             class="admin-btn admin-btn--ghost admin-btn--sm">
            <i class="fa fa-external-link"></i> Open print view
          </a>
        @endif
        <button type="button" id="ld-print-diagrams" class="admin-btn admin-btn--ghost admin-btn--sm" disabled>
          <i class="fa fa-print"></i> Print layout diagrams
        </button>
      </div>
    </div>
    <div class="ld-diagram-print-header" id="ld-diagram-print-header">
      <strong>{{ $isEdit ? e($quotation->quote_number) : 'Low deck quotation' }}</strong>
      @if ($isEdit && filled($quotation->client_name))
        · {{ e($quotation->client_name) }}
      @endif
      <span id="ld-diagram-print-dims"></span>
    </div>
    <p class="admin-form-help cp-form-help-flush no-print" id="ld-diagrams-hint">Enter dimensions to generate the foundations, framing, and decking layouts.</p>
    <div class="ld-diagrams-grid">
      <div class="ld-diagram-panel">
        <h5>1. Foundations</h5>
        <div id="ld-diagram-foundations"></div>
      </div>
      <div class="ld-diagram-panel">
        <h5>2. Framing layout</h5>
        <div id="ld-diagram-framing"></div>
      </div>
      <div class="ld-diagram-panel">
        <h5>3. Surface decking</h5>
        <div id="ld-diagram-decking"></div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var previewUrl = @json(route('admin.quotations.low-deck.preview'));
  var initialCalc = @json($initialCalc);

  var widthInput = document.getElementById('ld-width');
  var lengthInput = document.getElementById('ld-length');
  var areaDisplay = document.getElementById('ld-area-display');
  var areaValue = document.getElementById('ld-area-value');
  var labourInput = document.getElementById('ld-labour-rate');
  var wasteInput = document.getElementById('ld-waste-fee');
  var materialWasteInput = document.getElementById('ld-material-waste');
  var underDeckGravelInput = document.getElementById('ld-under-deck-gravel');

  var statusBadge = document.getElementById('ld-status-badge');
  var geometryEl = document.getElementById('ld-geometry');
  var materialsBody = document.querySelector('#ld-materials tbody');
  var tMaterials = document.getElementById('ld-t-materials');
  var tMaterialWasteRow = document.getElementById('ld-t-material-waste-row');
  var tMaterialWaste = document.getElementById('ld-t-material-waste');
  var tMaterialWasteLabel = document.getElementById('ld-t-material-waste-label');
  var tHst = document.getElementById('ld-t-hst');
  var tLabour = document.getElementById('ld-t-labour');
  var tLabourRate = document.getElementById('ld-t-labour-rate');
  var tTotal = document.getElementById('ld-t-total');
  var tLabourHst = document.getElementById('ld-t-labour-hst');
  var tLabourHstRow = document.getElementById('ld-t-labour-hst-row');
  var includeLabourHstInput = document.getElementById('ld-include-labour-hst');
  var tGrand = document.getElementById('ld-t-grand');
  var tCommuteRow = document.getElementById('ld-t-commute-row');
  var tCommute = document.getElementById('ld-t-commute');
  var tDebrisRow = document.getElementById('ld-t-debris-row');
  var tDebris = document.getElementById('ld-t-debris');
  var diagramsHint = document.getElementById('ld-diagrams-hint');
  var printDiagramsBtn = document.getElementById('ld-print-diagrams');
  var diagramPrintDims = document.getElementById('ld-diagram-print-dims');
  var workDescriptionInput = document.getElementById('ld-work-description');
  var generateWorkDescriptionBtn = document.getElementById('ld-generate-work-description');

  var lastCommuteAmount = 0;
  var lastDebrisAmount = 0;
  var lastAdditionalMaterialsPreTax = 0;
  var lastAdditionalMaterialsHst = 0;
  var lastAdditionalLaborPreTax = 0;
  var lastAdditionalLaborHst = 0;
  var lastAdditionalTaxIn = 0;
  var lastCalc = null;

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function parseMoney(input) {
    if (!input) return null;
    var raw = String(input.value || '').replace(/,/g, '').trim();
    if (raw === '') return null;
    if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
      return window.cpMoneyMask.parse(raw);
    }
    var v = parseFloat(raw);
    return isFinite(v) ? v : null;
  }

  function formatQty(q) {
    if (typeof q !== 'number') q = Number(q);
    if (!isFinite(q)) return '0';
    return Math.abs(q - Math.round(q)) < 1e-6 ? String(Math.round(q)) : q.toFixed(2);
  }

  function escapeHtml(s) {
    s = String(s == null ? '' : s);
    return s.replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c];
    });
  }

  function ftLabel(ft) {
    var v = Number(ft || 0);
    return (Math.abs(v - Math.round(v)) < 1e-6 ? String(Math.round(v)) : v.toFixed(2)) + "'";
  }

  var previewTimer = null;
  function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 220);
  }

  function buildPayload() {
    return {
      width_ft: parseFloat(widthInput.value) || 0,
      length_ft: parseFloat(lengthInput.value) || 0,
      labour_rate_per_sqft: parseMoney(labourInput),
      waste_dump_fee_cad: parseMoney(wasteInput),
      material_waste_percent: materialWasteInput ? (parseFloat(materialWasteInput.value) || 0) : 10,
      include_under_deck_gravel: underDeckGravelInput ? !!underDeckGravelInput.checked : true,
      include_labour_hst: includeLabourHstInput ? !!includeLabourHstInput.checked : true
    };
  }

  function runPreview() {
    var payload = buildPayload();
    if (!(payload.width_ft > 0 && payload.length_ft > 0)) {
      renderIdle('Enter width and length to see the breakdown.');
      return;
    }

    statusBadge.textContent = 'Calculating…';
    statusBadge.style.background = '#fff4d4';
    statusBadge.style.color = '#8a5d00';

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
          return { ok: res.ok, status: res.status, body: body };
        });
      })
      .then(function (r) {
        if (!r.ok) {
          renderError(r.body && r.body.error ? r.body.error : 'Calculation error (' + r.status + ')');
          return;
        }
        renderCalc(r.body);
      })
      .catch(function (err) {
        renderError('Could not reach the calculator.');
        console.warn('[low deck preview]', err);
      });
  }

  function renderError(msg) {
    statusBadge.textContent = 'Error';
    statusBadge.style.background = '#fde1e1';
    statusBadge.style.color = '#8a1a1a';
    geometryEl.innerHTML = '<p style="color:#8a1a1a;font-weight:600;">' + escapeHtml(msg) + '</p>';
    materialsBody.innerHTML = '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';
    ['ld-t-materials','ld-t-hst','ld-t-labour','ld-t-total','ld-t-labour-hst','ld-t-grand'].forEach(function (id) {
      document.getElementById(id).textContent = 'CA$0.00';
    });
    clearDiagrams();
    if (generateWorkDescriptionBtn) generateWorkDescriptionBtn.disabled = true;
    if (tMaterialWasteRow) tMaterialWasteRow.classList.add('is-hidden');
  }

  function renderIdle(msg) {
    statusBadge.textContent = 'Idle';
    statusBadge.style.background = '#eef1f5';
    statusBadge.style.color = '#556';
    geometryEl.textContent = msg || '';
    materialsBody.innerHTML = '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';
    tMaterials.textContent = 'CA$0.00';
    tHst.textContent = 'CA$0.00';
    tLabour.textContent = 'CA$0.00';
    tTotal.textContent = 'CA$0.00';
    tLabourHst.textContent = 'CA$0.00';
    tGrand.textContent = 'CA$0.00';
    window.__quoteGrandTotal = 0;
    document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: 0 } }));
    tLabourRate.textContent = '';
    clearDiagrams();
    if (generateWorkDescriptionBtn) generateWorkDescriptionBtn.disabled = true;
    if (tMaterialWasteRow) tMaterialWasteRow.classList.add('is-hidden');
  }

  function clearDiagrams() {
    document.getElementById('ld-diagram-foundations').innerHTML = '';
    document.getElementById('ld-diagram-framing').innerHTML = '';
    document.getElementById('ld-diagram-decking').innerHTML = '';
    if (diagramsHint) diagramsHint.style.display = '';
    if (printDiagramsBtn) printDiagramsBtn.disabled = true;
    if (diagramPrintDims) diagramPrintDims.textContent = '';
  }

  function renderCalc(calc) {
    lastCalc = calc;
    if (generateWorkDescriptionBtn) {
      generateWorkDescriptionBtn.disabled = !(calc.suggested_work_description);
    }
    statusBadge.textContent = 'Up to date';
    statusBadge.style.background = '#e8f4ea';
    statusBadge.style.color = '#0f5132';

    var g = calc.geometry || {};
    var t = calc.totals || {};
    var blocks = g.blocks || {};
    var joists = g.joists || {};
    var beams = g.beams || {};
    var boards = g.boards || {};
    var tape = g.joist_tape || {};

    geometryEl.innerHTML =
      '<p style="margin:0 0 8px;"><strong>' + (g.area_sq_ft || 0) + ' sq ft</strong> · '
      + ftLabel(g.width_ft) + ' × ' + ftLabel(g.length_ft) + '</p>'
      + '<div style="font-size:12px;color:#4b5563;line-height:1.6;">'
      + (blocks.count || 0) + ' blocks (' + (blocks.grid_lines_run || 0) + ' × ' + (blocks.grid_lines_span || 0) + ' grid)<br>'
      + (joists.total_lines || 0) + ' joist lines · ' + formatJoistSpacing(joists.field_spacing_in) + ' spacing (max 15 in.) · '
      + (beams.lines_total || 0) + ' beam lines (' + (beams.interior_count || 0) + ' interior)<br>'
      + (boards.total_boards || boards.rows || 0) + ' deck boards (4 border + ' + (boards.infill_rows || boards.rows || 0) + ' infill) · '
      + (tape.rolls_required || 0) + ' joist tape roll' + ((tape.rolls_required === 1) ? '' : 's')
      + ((g.joist_hangers && (g.joist_hangers.quote_count || g.joist_hangers.count) > 0)
        ? '<br>' + (g.joist_hangers.quote_count || g.joist_hangers.count) + ' joist hangers ('
          + g.joist_hangers.count + ' field + ' + (g.joist_hangers.extra_count || 0) + ' extra)'
        : '')
      + ((calc.inputs && calc.inputs.material_waste_percent > 0)
        ? '<br>Material waste: ' + calc.inputs.material_waste_percent + '% (internal)'
        : '')
      + '</div>';

    var rows = (calc.materials || []).map(function (m) {
      var notesHtml = m.notes ? '<span class="ld-breakdown-note">' + escapeHtml(m.notes) + '</span>' : '';
      return '<tr>' +
        '<td>' + escapeHtml(m.name) + notesHtml + '</td>' +
        '<td class="num">' + formatQty(m.quantity) + ' ' + escapeHtml(m.unit || '') + '</td>' +
        '<td class="num">' + money(m.unit_price) + '</td>' +
        '<td class="num">' + money(m.subtotal) + '</td>' +
        '</tr>';
    }).join('');
    materialsBody.innerHTML = rows || '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';

    var wasteAmount = Number(t.material_waste_amount || 0);
    var wastePercent = Number(t.material_waste_percent || (calc.inputs && calc.inputs.material_waste_percent) || 0);
    if (tMaterialWasteRow && tMaterialWaste) {
      var showWaste = wasteAmount > 0 && wastePercent > 0;
      tMaterialWasteRow.classList.toggle('is-hidden', !showWaste);
      if (showWaste) {
        tMaterialWaste.textContent = money(wasteAmount);
        if (tMaterialWasteLabel) {
          tMaterialWasteLabel.textContent = '(' + wastePercent + '%)';
        }
      }
    }

    tLabour.textContent = money(Number(t.labour_subtotal || 0) + lastAdditionalLaborPreTax);
    tLabourHst.textContent = money(Number(t.labour_hst || 0) + lastAdditionalLaborHst);
    if (tLabourHstRow) {
      tLabourHstRow.classList.toggle(
        'ld-totals-row--labour-hst-excluded',
        t.include_labour_hst === false || (includeLabourHstInput && !includeLabourHstInput.checked)
      );
    }
    if (tCommuteRow) tCommuteRow.classList.toggle('is-hidden', !(lastCommuteAmount > 0));
    if (tCommute) tCommute.textContent = money(lastCommuteAmount);
    if (tDebrisRow) tDebrisRow.classList.toggle('is-hidden', !(lastDebrisAmount > 0));
    if (tDebris) tDebris.textContent = money(lastDebrisAmount);
    tMaterials.textContent = money(Number(t.materials_subtotal || 0) + lastAdditionalMaterialsPreTax);
    tHst.textContent = money(Number(t.hst || 0) + lastAdditionalMaterialsHst);
    tTotal.textContent = money(Number(t.total || 0) + lastAdditionalTaxIn);
    tGrand.textContent = money(Number(t.grand_total || 0) + lastCommuteAmount + lastDebrisAmount + lastAdditionalTaxIn);
    window.__quoteGrandTotal = Number(t.grand_total || 0) + lastCommuteAmount + lastDebrisAmount + lastAdditionalTaxIn;
    document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: window.__quoteGrandTotal } }));
    if (t.labour_rate_per_sqft) {
      tLabourRate.textContent = '(' + money(t.labour_rate_per_sqft) + '/sq ft)';
    }

    drawDiagrams(g);
  }

  // ---------- Diagrams (mirrors LowDeckDiagramRenderer) ----------------
  var CANVAS_W = 640, CANVAS_H = 380, PAD = 46;
  var HOLE_FT = 1;
  var RIM_JOIST_FT = 1.5 / 12;
  var JOIST_FILL = '#a7f3d0';
  var JOIST_STROKE = '#10b981';
  var BEAM_FILL = '#fed7aa';
  var BEAM_STROKE = '#f97316';
  var POST_FT = 3.5 / 12;
  var DIM_SEGMENT_OFFSET = 18;
  var DIM_TOTAL_OFFSET_RUN = 40;
  var DIM_TOTAL_OFFSET_SPAN = 44;

  function svgEl(tag, attrs) {
    var el = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (var k in attrs) { if (attrs.hasOwnProperty(k)) el.setAttribute(k, attrs[k]); }
    return el;
  }

  function diagramContext(g) {
    var runFt = Number(g.run_ft || 0);
    var spanFt = Number(g.span_ft || 0);
    if (runFt <= 0 || spanFt <= 0) return null;
    var scale = Math.min((CANVAS_W - 2 * PAD) / runFt, (CANVAS_H - 2 * PAD) / spanFt);
    var w = runFt * scale, h = spanFt * scale;
    return { scale: scale, x0: (CANVAS_W - w) / 2, y0: (CANVAS_H - h) / 2, w: w, h: h, runFt: runFt, spanFt: spanFt };
  }

  function newSvg() {
    return svgEl('svg', { viewBox: '0 0 ' + CANVAS_W + ' ' + CANVAS_H, class: 'ld-diagram-svg', preserveAspectRatio: 'xMidYMid meet' });
  }

  function deckRect(svg, c) {
    svg.appendChild(svgEl('rect', { x: c.x0, y: c.y0, width: c.w, height: c.h, fill: '#f8fafc', stroke: '#1e293b', 'stroke-width': 1.5 }));
  }

  function rimJoistBands(svg, c) {
    var rim = RIM_JOIST_FT * c.scale;
    svg.appendChild(svgEl('rect', { x: c.x0, y: c.y0, width: c.w, height: rim, fill: '#e2e8f0', opacity: 0.9 }));
    svg.appendChild(svgEl('rect', { x: c.x0, y: c.y0 + c.h - rim, width: c.w, height: rim, fill: '#e2e8f0', opacity: 0.9 }));
    svg.appendChild(svgEl('rect', { x: c.x0, y: c.y0, width: rim, height: c.h, fill: '#e2e8f0', opacity: 0.9 }));
    svg.appendChild(svgEl('rect', { x: c.x0 + c.w - rim, y: c.y0, width: rim, height: c.h, fill: '#e2e8f0', opacity: 0.9 }));
  }

  function postCenterFt(fx, fy, runFt, spanFt) {
    var rim = RIM_JOIST_FT, postHalf = POST_FT / 2, eps = 0.001;
    var cx = Math.abs(fx) < eps ? rim + postHalf : (Math.abs(fx - runFt) < eps ? runFt - rim - postHalf : fx);
    var cy = Math.abs(fy) < eps ? rim + postHalf : (Math.abs(fy - spanFt) < eps ? spanFt - rim - postHalf : fy);
    return { cx: cx, cy: cy };
  }

  function foundationBlock(svg, fx, fy, c) {
    var post = postCenterFt(fx, fy, c.runFt, c.spanFt);
    var holePx = HOLE_FT * c.scale;
    var holeX = c.x0 + post.cx * c.scale - holePx / 2;
    var holeY = c.y0 + post.cy * c.scale - holePx / 2;
    var postPx = POST_FT * c.scale;
    var postX = c.x0 + post.cx * c.scale - postPx / 2;
    var postY = c.y0 + post.cy * c.scale - postPx / 2;
    svg.appendChild(svgEl('rect', { x: holeX, y: holeY, width: holePx, height: holePx, fill: '#f0f9ff', stroke: '#0284c7', 'stroke-width': 1.25 }));
    svg.appendChild(svgEl('rect', { x: postX, y: postY, width: postPx, height: postPx, fill: '#024768', stroke: '#013a52', 'stroke-width': 0.75, rx: 0.5 }));
  }

  function formatJoistSpacing(inches) {
    var v = Number(inches || 0);
    if (!(v > 0)) return 'auto';
    if (Math.abs(v - Math.round(v)) < 0.01) return Math.round(v) + '"';
    return v.toFixed(2).replace(/\.?0+$/, '') + '"';
  }

  function fieldJoistSpacingDimension(svg, joists, c) {
    var fieldPositions = joists.field_positions_x_ft || [];
    var spacingIn = Number(joists.field_spacing_in || 0);
    if (!(spacingIn > 0)) return;
    var label = formatJoistSpacing(spacingIn);
    var dimY = c.y0 + c.h / 2;
    var x1, x2;
    if (fieldPositions.length >= 2) {
      x1 = c.x0 + fieldPositions[0] * c.scale;
      x2 = c.x0 + fieldPositions[1] * c.scale;
    } else if (fieldPositions.length === 1) {
      var edgeLeft = (joists.edge_inset_positions_x_ft || [])[0] || 0;
      x1 = c.x0 + edgeLeft * c.scale;
      x2 = c.x0 + fieldPositions[0] * c.scale;
    } else {
      var edge = joists.edge_inset_positions_x_ft || [];
      if (edge.length < 2) return;
      x1 = c.x0 + edge[0] * c.scale;
      x2 = c.x0 + edge[1] * c.scale;
    }
    dimensionLineH(svg, x1, x2, dimY, label);
  }

  function ftInLabel(ft) {
    var whole = Math.floor(ft + 1e-9);
    var inches = Math.round((ft - whole) * 12);
    if (inches >= 12) { whole++; inches = 0; }
    if (inches === 0) return whole + "'";
    return whole + "'" + inches + '"';
  }

  function dimensionLineH(svg, x1, x2, y, label) {
    var offset = DIM_SEGMENT_OFFSET, color = '#475569', lineY = y - offset, midX = (x1 + x2) / 2;
    svg.appendChild(svgEl('line', { x1: x1, y1: lineY, x2: x2, y2: lineY, stroke: color, 'stroke-width': 1 }));
    svg.appendChild(svgEl('line', { x1: x1, y1: lineY - 4, x2: x1, y2: lineY + 4, stroke: color, 'stroke-width': 1 }));
    svg.appendChild(svgEl('line', { x1: x2, y1: lineY - 4, x2: x2, y2: lineY + 4, stroke: color, 'stroke-width': 1 }));
    var t = svgEl('text', { x: midX, y: lineY - 5, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 600, fill: color });
    t.textContent = label;
    svg.appendChild(t);
  }

  function dimensionLineV(svg, x, y1, y2, label) {
    var offset = DIM_SEGMENT_OFFSET, color = '#475569', lineX = x - offset, midY = (y1 + y2) / 2;
    svg.appendChild(svgEl('line', { x1: lineX, y1: y1, x2: lineX, y2: y2, stroke: color, 'stroke-width': 1 }));
    svg.appendChild(svgEl('line', { x1: lineX - 4, y1: y1, x2: lineX + 4, y2: y1, stroke: color, 'stroke-width': 1 }));
    svg.appendChild(svgEl('line', { x1: lineX - 4, y1: y2, x2: lineX + 4, y2: y2, stroke: color, 'stroke-width': 1 }));
    var t = svgEl('text', { x: lineX - 6, y: midY, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 600, fill: color, transform: 'rotate(-90 ' + (lineX - 6) + ' ' + midY + ')' });
    t.textContent = label;
    svg.appendChild(t);
  }

  function holeSpacingDimensions(svg, xs, ys, c) {
    if (xs.length < 2 && ys.length < 2) return;
    var dimY = c.y0 + (ys[0] || 0) * c.scale;
    var dimX = c.x0 + (xs[0] || 0) * c.scale;
    for (var i = 0; i < xs.length - 1; i++) {
      var x1 = c.x0 + xs[i] * c.scale;
      var x2 = c.x0 + xs[i + 1] * c.scale;
      dimensionLineH(svg, x1, x2, dimY, ftInLabel(xs[i + 1] - xs[i]));
    }
    for (var j = 0; j < ys.length - 1; j++) {
      var y1 = c.y0 + ys[j] * c.scale;
      var y2 = c.y0 + ys[j + 1] * c.scale;
      dimensionLineV(svg, dimX, y1, y2, ftInLabel(ys[j + 1] - ys[j]));
    }
  }

  function dimensions(svg, c, outerTier) {
    var midX = c.x0 + c.w / 2, midY = c.y0 + c.h / 2;
    var runY = c.y0 - (outerTier ? DIM_TOTAL_OFFSET_RUN : 14);
    var spanX = c.x0 - (outerTier ? DIM_TOTAL_OFFSET_SPAN : 16);
    var t1 = svgEl('text', { x: midX, y: runY, 'text-anchor': 'middle', 'font-size': 13, 'font-weight': 600, fill: '#334155' });
    t1.textContent = ftLabel(c.runFt);
    svg.appendChild(t1);
    var t2 = svgEl('text', { x: spanX, y: midY, 'text-anchor': 'middle', 'font-size': 13, 'font-weight': 600, fill: '#334155', transform: 'rotate(-90 ' + spanX + ' ' + midY + ')' });
    t2.textContent = ftLabel(c.spanFt);
    svg.appendChild(t2);
  }

  function deckingCaption(boards) {
    if (boards.layout === 'picture_frame') {
      var ripNote = (boards.infill_rip_rows || 0) > 0 ? ' incl. 1 ripped' : '';
      return (boards.total_boards || 0) + ' boards: 4 border (45° miters) + ' + (boards.infill_rows || 0)
        + ' infill' + ripNote + ' · 5.5 in. wide · ' + (boards.board_gap_in || 0.125) + ' in. gap';
    }
    return (boards.rows || 0) + ' deck boards, 5.5 in. wide, 1/8 in. gap';
  }

  function drawPictureFrameBorder(svg, b, x0, y0, w, h, fill, stroke) {
    function poly(points) {
      svg.appendChild(svgEl('polygon', { points: points, fill: fill, stroke: stroke, 'stroke-width': 0.5 }));
    }

    // Top / bottom: 4-point quads; SVG close edge = 45° miter (inner corner → outer vertex).
    poly([
      x0, y0,
      x0 + w, y0,
      x0 + w - b, y0 + b,
      x0 + b, y0 + b
    ].map(function (n) { return n.toFixed(1); }).join(' '));

    poly([
      x0, y0 + h,
      x0 + w, y0 + h,
      x0 + w - b, y0 + h - b,
      x0 + b, y0 + h - b
    ].map(function (n) { return n.toFixed(1); }).join(' '));

    poly([
      x0, y0 + h,
      x0, y0,
      x0 + b, y0 + b,
      x0 + b, y0 + h - b
    ].map(function (n) { return n.toFixed(1); }).join(' '));

    poly([
      x0 + w, y0 + h,
      x0 + w, y0,
      x0 + w - b, y0 + b,
      x0 + w - b, y0 + h - b
    ].map(function (n) { return n.toFixed(1); }).join(' '));
  }

  function drawPictureFrameInfill(svg, boards, c, b, x0, y0, w, h, fill, stroke) {
    var rowWidths = boards.infill_row_widths_in || [];
    if (!rowWidths.length) return;

    var gapPx = ((boards.board_gap_in || 0) / 12) * c.scale;
    var innerX = x0 + b, innerY = y0 + b, innerW = w - 2 * b;
    var cursorY = innerY;

    rowWidths.forEach(function (widthIn, index) {
      if (index > 0) cursorY += gapPx;
      var rowH = (widthIn / 12) * c.scale;
      svg.appendChild(svgEl('rect', {
        x: innerX, y: cursorY, width: innerW, height: rowH,
        rx: 0.5, fill: fill, stroke: stroke, 'stroke-width': 0.5
      }));
      cursorY += rowH;
    });
  }

  function drawPictureFrameDecking(svg, boards, c) {
    var boardWidthFt = Number(boards.board_width_ft || 0);
    if (!(boardWidthFt > 0)) return;

    var b = boardWidthFt * c.scale;
    var x0 = c.x0, y0 = c.y0, w = c.w, h = c.h;
    var fill = '#c8a36b', stroke = '#a9824a';

    drawPictureFrameInfill(svg, boards, c, b, x0, y0, w, h, fill, stroke);
    drawPictureFrameBorder(svg, b, x0, y0, w, h, fill, stroke);
  }

  function drawLegacyDecking(svg, boards, c) {
    var rows = boards.rows || 0;
    if (rows <= 0) return;
    var pitch = c.h / rows;
    var boardH = Math.max(1.5, pitch * 0.82);
    for (var i = 0; i < rows; i++) {
      var y = c.y0 + i * pitch;
      svg.appendChild(svgEl('rect', { x: c.x0, y: y, width: c.w, height: boardH, rx: 1, fill: '#c8a36b', stroke: '#a9824a', 'stroke-width': 0.5 }));
    }
  }

  function normalizedJoistHangerPositions(g) {
    var hangers = g.joist_hangers || {};
    var positions = hangers.positions_ft || [];
    var fieldXs = (g.joists && g.joists.field_positions_x_ft) ? g.joists.field_positions_x_ft : [];
    var edgeYs = (g.beams && g.beams.edge_inset_positions_y_ft) ? g.beams.edge_inset_positions_y_ft : [];
    var spanFt = g.span_ft || 0;
    if (!fieldXs.length || !edgeYs.length) return positions;

    var topY = edgeYs[0];
    var bottomY = edgeYs[1] != null ? edgeYs[1] : (spanFt - topY);
    var uniqueY = [];
    positions.forEach(function (pos) {
      var y = Math.round((pos.y_ft || 0) * 1000) / 1000;
      if (uniqueY.indexOf(y) === -1) uniqueY.push(y);
    });

    var legacyInteriorBeam = positions.length === fieldXs.length
      && uniqueY.length === 1
      && uniqueY[0] > topY + 0.25
      && uniqueY[0] < bottomY - 0.25;

    if (legacyInteriorBeam) {
      var regenerated = [];
      fieldXs.forEach(function (x) {
        regenerated.push({ x_ft: x, y_ft: topY, end: 'top' });
        regenerated.push({ x_ft: x, y_ft: bottomY, end: 'bottom' });
      });
      return regenerated;
    }

    var normalized = [];
    positions.forEach(function (pos) {
      var end = pos.end || '';
      var y = pos.y_ft || 0;
      if (!end) {
        if (Math.abs(y - topY) < 0.06 || Math.abs(y) < 0.01) {
          end = 'top';
          y = topY;
        } else if (Math.abs(y - bottomY) < 0.06 || Math.abs(y - spanFt) < 0.01) {
          end = 'bottom';
          y = bottomY;
        } else {
          return;
        }
      } else if (end === 'top') {
        y = topY;
      } else if (end === 'bottom') {
        y = bottomY;
      } else {
        return;
      }
      normalized.push({ x_ft: pos.x_ft || 0, y_ft: y, end: end });
    });

    return normalized.length ? normalized : positions;
  }

  function joistHangerMarkers(svg, g, c) {
    var positions = normalizedJoistHangerPositions(g);
    if (!positions.length) return;
    var thick = RIM_JOIST_FT * c.scale;
    var half = thick / 2;
    var outerHalf = half * 1.15;
    var depth = Math.max(4, thick * 2.2);
    var seat = Math.max(1.5, thick * 0.45);
    var fill = '#94a3b8';
    var stroke = '#64748b';
    positions.forEach(function (pos) {
      var cx = c.x0 + (pos.x_ft || 0) * c.scale;
      var end = pos.end || '';
      var y = c.y0 + (pos.y_ft || 0) * c.scale;
      var points;
      if (end === 'bottom') {
        points = [
          [cx - outerHalf, y],
          [cx + outerHalf, y],
          [cx + outerHalf, y - depth],
          [cx + half, y - depth],
          [cx + half, y - seat],
          [cx - half, y - seat],
          [cx - half, y - depth],
          [cx - outerHalf, y - depth]
        ];
      } else {
        points = [
          [cx - outerHalf, y],
          [cx + outerHalf, y],
          [cx + outerHalf, y + depth],
          [cx + half, y + depth],
          [cx + half, y + seat],
          [cx - half, y + seat],
          [cx - half, y + depth],
          [cx - outerHalf, y + depth]
        ];
      }
      svg.appendChild(svgEl('polygon', {
        points: points.map(function (p) { return p[0] + ',' + p[1]; }).join(' '),
        fill: fill, stroke: stroke, 'stroke-width': 0.75
      }));
    });
  }

  function caption(svg, text) {
    var t = svgEl('text', { x: CANVAS_W / 2, y: CANVAS_H - 10, 'text-anchor': 'middle', 'font-size': 11, fill: '#64748b' });
    t.textContent = text;
    svg.appendChild(t);
  }

  function drawDiagrams(g) {
    var c = diagramContext(g);
    var fEl = document.getElementById('ld-diagram-foundations');
    var frEl = document.getElementById('ld-diagram-framing');
    var dEl = document.getElementById('ld-diagram-decking');
    fEl.innerHTML = ''; frEl.innerHTML = ''; dEl.innerHTML = '';
    if (!c) { if (diagramsHint) diagramsHint.style.display = ''; if (printDiagramsBtn) printDiagramsBtn.disabled = true; return; }
    if (diagramsHint) diagramsHint.style.display = 'none';
    if (printDiagramsBtn) printDiagramsBtn.disabled = false;
    if (diagramPrintDims) {
      diagramPrintDims.textContent = ' · ' + ftLabel(g.width_ft) + ' × ' + ftLabel(g.length_ft)
        + ' (' + (g.area_sq_ft || 0) + ' sq ft)';
    }

    var blocks = g.blocks || {}, joists = g.joists || {}, beams = g.beams || {}, boards = g.boards || {};
    var xs = blocks.positions_x_ft || [], ys = blocks.positions_y_ft || [];

    // 1. Foundations
    var s1 = newSvg();
    deckRect(s1, c);
    rimJoistBands(s1, c);
    xs.forEach(function (fx) {
      ys.forEach(function (fy) { foundationBlock(s1, fx, fy, c); });
    });
    holeSpacingDimensions(s1, xs, ys, c);
    dimensions(s1, c, true);
    caption(s1, (blocks.count || 0) + ' concrete deck blocks, ' + (blocks.grid_lines_run || 0) + ' x ' + (blocks.grid_lines_span || 0) + ' grid · 12 in. pier holes · posts inset 1.5 in. for rim joist');
    fEl.appendChild(s1);

    // 2. Framing (members drawn 1.5 in. wide in plan)
    var s2 = newSvg();
    deckRect(s2, c);
    var thick = RIM_JOIST_FT * c.scale, half = thick / 2;
    (beams.positions_y_ft || []).forEach(function (fy) {
      var y = c.y0 + fy * c.scale - half;
      s2.appendChild(svgEl('rect', { x: c.x0, y: y, width: c.w, height: thick, fill: BEAM_FILL, stroke: BEAM_STROKE, 'stroke-width': 0.75, rx: 0.5 }));
    });
    (joists.positions_x_ft || []).forEach(function (fx) {
      var x = c.x0 + fx * c.scale - half;
      s2.appendChild(svgEl('rect', { x: x, y: c.y0, width: thick, height: c.h, fill: JOIST_FILL, stroke: JOIST_STROKE, 'stroke-width': 0.75, rx: 0.5 }));
    });
    fieldJoistSpacingDimension(s2, joists, c);
    joistHangerMarkers(s2, g, c);
    dimensions(s2, c);
    caption(s2, (joists.total_lines || 0) + ' joist lines · ' + formatJoistSpacing(joists.field_spacing_in) + ' spacing (max 15 in.), ' + (beams.lines_total || 0) + ' beam lines · members shown 1.5 in. wide');
    frEl.appendChild(s2);

    // 3. Decking
    var s3 = newSvg();
    deckRect(s3, c);
    if (boards.layout === 'picture_frame') {
      drawPictureFrameDecking(s3, boards, c);
    } else {
      drawLegacyDecking(s3, boards, c);
    }
    dimensions(s3, c);
    caption(s3, deckingCaption(boards));
    dEl.appendChild(s3);
  }

  // ---------- Wire up --------------------------------------------------
  if (generateWorkDescriptionBtn && workDescriptionInput) {
    generateWorkDescriptionBtn.addEventListener('click', function () {
      if (!lastCalc || !lastCalc.suggested_work_description) {
        window.alert('Enter deck width and length first so the calculator can fill in dimensions.');
        return;
      }
      var current = '';
      if (typeof tinymce !== 'undefined') {
        var ed = tinymce.get('ld-work-description');
        if (ed) {
          current = String(ed.getContent({ format: 'text' }) || '').trim();
        }
      }
      if (current === '') {
        current = (workDescriptionInput.value || '').trim();
      }
      if (current !== '' && !window.confirm('Replace the current description with a new one from the template?')) {
        return;
      }
      var next = lastCalc.suggested_work_description;
      if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('ld-work-description');
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

  function updateArea() {
    var w = parseFloat(widthInput.value) || 0;
    var l = parseFloat(lengthInput.value) || 0;
    if (w > 0 && l > 0 && areaDisplay && areaValue) {
      var sqft = w * l;
      areaValue.textContent = (Math.round(sqft * 100) / 100).toLocaleString();
      areaDisplay.style.display = 'block';
    } else if (areaDisplay) {
      areaDisplay.style.display = 'none';
    }
  }

  ['input', 'change'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      if (!e.target || !e.target.closest) return;
      if (e.target === widthInput || e.target === lengthInput) {
        updateArea();
      }
      if (e.target.matches('#ld-include-labour-hst, #ld-form input, #ld-form select')) {
        schedulePreview();
      }
    });
  });

  if (initialCalc) {
    renderCalc(initialCalc);
  }
  schedulePreview();
  updateArea();

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

  if (printDiagramsBtn) {
    printDiagramsBtn.addEventListener('click', function () {
      window.print();
    });
  }
})();
</script>
@endpush

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'ld-work-description',
  'formId' => 'ld-form',
])

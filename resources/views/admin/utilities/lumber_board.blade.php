@extends('admin.layouts.app')

@section('heading', 'Lumber / Board Material Calculator')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Plan how many boards to buy for a job. Enter the lengths you need, pick available stock sizes,
      and see the best cutting layout with waste and cost estimates.
    </p>
    <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All utilities
    </a>
  </div>

  <div
    class="util-lb"
    data-lumber-board
    data-save-url="{{ route('admin.utilities.calculations.store') }}"
    data-pdf-url="{{ route('admin.utilities.lumber-board.pdf') }}"
    data-destroy-template="{{ route('admin.utilities.calculations.destroy', ['utility_calculation' => '__ID__']) }}"
    data-saved="{{ json_encode($savedCalculations->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'payload' => $c->payload, 'created_label' => optional($c->created_at)->format('M j, Y g:i A')])) }}"
  >
    <input type="hidden" id="util-lb-csrf" value="{{ csrf_token() }}">

    <div class="util-lb-grid">
      <section class="admin-card util-lb-inputs">
        <h3 class="util-lb-card-title">Inputs</h3>

        <div class="admin-form-group">
          <span class="admin-form-group-label">Unit system</span>
          <div class="util-segmented" role="group" aria-label="Unit system" data-lb-unit-toggle>
            <button type="button" class="util-segmented__btn is-active" data-unit="ft">Feet</button>
            <button type="button" class="util-segmented__btn" data-unit="in">Inches</button>
          </div>
        </div>

        <div class="admin-form-group">
          <span class="admin-form-group-label">
            Calculation mode
            <span class="util-tip" tabindex="0" data-tip="Minimize waste compares every stock length and a mixed-length plan. Single board type uses only the length you select below.">
              <i class="fa fa-info-circle"></i>
            </span>
          </span>
          <div class="util-segmented" role="group" aria-label="Calculation mode" data-lb-mode-toggle>
            <button type="button" class="util-segmented__btn is-active" data-mode="minimize">Minimize waste</button>
            <button type="button" class="util-segmented__btn" data-mode="single">Single board type</button>
          </div>
        </div>

        <div class="admin-form-group" data-lb-mode-field="single" hidden>
          <label for="lb-single-stock">Board length to use</label>
          <select id="lb-single-stock" data-lb-single-stock>
            <option value="">— Select after adding stock lengths —</option>
          </select>
        </div>

        <label class="admin-checkbox admin-checkbox--sm util-lb-check">
          <input type="checkbox" class="admin-checkbox__input" id="lb-round-up" data-lb-round-up>
          <span class="admin-checkbox__mark" aria-hidden="true"></span>
          <span class="admin-checkbox__text">Round piece quantities up</span>
        </label>

        <label class="admin-checkbox admin-checkbox--sm util-lb-check">
          <input type="checkbox" class="admin-checkbox__input" id="lb-kerf" data-lb-kerf checked>
          <span class="admin-checkbox__mark" aria-hidden="true"></span>
          <span class="admin-checkbox__text">Include 1/8″ kerf between cuts</span>
        </label>

        <div class="util-lb-section-block">
          <span class="util-lb-section-label">Available board lengths</span>
          <div class="util-lb-quick-btns" data-lb-quick-lengths>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="8">8′</button>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="10">10′</button>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="12">12′</button>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="16">16′</button>
          </div>
        </div>
        <p class="util-lb-hint">Optional price per board helps estimate total cost.</p>
        <div class="util-lb-stock-wrap">
          <table class="util-lb-stock-table">
            <thead>
              <tr>
                <th>Length</th>
                <th>Price ($)</th>
                <th class="util-lb-col-action" aria-hidden="true"></th>
              </tr>
            </thead>
            <tbody data-lb-stock-list></tbody>
          </table>
        </div>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm util-lb-add-row" data-lb-add-stock>
          <i class="fa fa-plus"></i> Add board length
        </button>

        <div class="util-lb-section-head util-lb-section-head--spaced">
          <span class="util-lb-section-label">Required pieces</span>
        </div>
        <div class="util-lb-pieces-wrap">
          <table class="util-lb-pieces-table">
            <thead>
              <tr>
                <th>Description</th>
                <th>Length</th>
                <th>Qty</th>
                <th class="util-lb-col-action" aria-hidden="true"></th>
              </tr>
            </thead>
            <tbody data-lb-pieces-body></tbody>
          </table>
        </div>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm util-lb-add-row" data-lb-add-piece>
          <i class="fa fa-plus"></i> Add piece
        </button>

        <button type="button" class="admin-btn admin-btn--ghost util-lb-reset" data-lb-reset>
          <i class="fa fa-undo"></i> Reset
        </button>
      </section>

      <section class="util-lb-output">
        <div class="admin-card util-lb-results">
          <div class="util-lb-results-head">
            <h3 class="util-lb-card-title">Results</h3>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-copy hidden>
              <i class="fa fa-copy"></i> Copy
            </button>
          </div>

          <div class="util-lb-empty" data-lb-empty>
            <span class="util-lb-empty__icon"><i class="fa fa-th-large"></i></span>
            <p class="util-lb-empty__title">Add stock lengths and pieces</p>
            <p class="util-lb-empty__hint">Enter at least one <strong>board length</strong> and one <strong>required piece</strong> to see the cutting plan.</p>
          </div>

          <div class="util-lb-error" data-lb-error hidden>
            <i class="fa fa-exclamation-triangle"></i>
            <span data-lb-error-text></span>
          </div>

          <div class="util-lb-live" data-lb-live hidden>
            <p class="util-lb-strategy" data-lb-strategy></p>
            <div class="util-lb-stats">
              <div class="util-lb-stat">
                <span class="util-lb-stat__value" data-lb-stat-linear>—</span>
                <span class="util-lb-stat__label">Linear length required</span>
              </div>
              <div class="util-lb-stat">
                <span class="util-lb-stat__value" data-lb-stat-boards>—</span>
                <span class="util-lb-stat__label">Total boards</span>
              </div>
              <div class="util-lb-stat">
                <span class="util-lb-stat__value" data-lb-stat-cost>—</span>
                <span class="util-lb-stat__label">Estimated cost</span>
              </div>
              <div class="util-lb-stat util-lb-stat--waste" data-lb-stat-waste-wrap>
                <span class="util-lb-stat__value" data-lb-stat-waste>—</span>
                <span class="util-lb-stat__label">Waste</span>
              </div>
            </div>

            <div class="util-lb-breakdown" data-lb-breakdown></div>
          </div>
        </div>

        <div class="admin-card util-lb-diagram-card" data-lb-diagram-card hidden>
          <h3 class="util-lb-card-title">Cutting diagrams</h3>
          <p class="util-lb-hint">Coloured segments are cuts; grey is leftover waste on each board.</p>
          <div class="util-lb-diagrams" data-lb-diagrams></div>
          <div class="util-lb-export">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-export-pdf>
              <i class="fa fa-file-pdf-o"></i> Export PDF
            </button>
          </div>
        </div>

        <div class="admin-card util-lb-save-card" data-lb-save-card hidden>
          <h3 class="util-lb-card-title">Save this calculation</h3>
          <div class="util-lb-save-row">
            <div class="admin-form-group">
              <label for="lb-save-name" class="util-sr-only">Calculation name</label>
              <input type="text" id="lb-save-name" maxlength="120" placeholder="e.g. Back deck boards">
            </div>
            <button type="button" class="admin-btn admin-btn--primary" data-lb-save>
              <i class="fa fa-save"></i> Save
            </button>
          </div>
          <p class="util-lb-save-feedback" data-lb-save-feedback hidden></p>
        </div>
      </section>
    </div>

    <section class="admin-card util-lb-saved" data-lb-saved-section>
      <h3 class="util-lb-card-title">Saved calculations</h3>
      <p class="util-lb-saved-empty" data-lb-saved-empty>No saved calculations yet. Build a plan above and save it for later.</p>
      <ul class="util-lb-saved-list" data-lb-saved-list></ul>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-utilities-lumber-board.js') }}?v={{ @filemtime(public_path('js/admin-utilities-lumber-board.js')) ?: time() }}"></script>
@endpush

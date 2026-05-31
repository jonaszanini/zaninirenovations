@extends('admin.layouts.app')

@section('heading', 'Pillar & Spindle Calculator')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Plan an even run of pillars, posts, or spindles. Enter the total length and either a target spacing
      or a fixed count &mdash; the layout, spacing, and a scaled diagram update as you type.
    </p>
    <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All utilities
    </a>
  </div>

  <div
    class="util-ps"
    data-pillar-spindle
    data-save-url="{{ route('admin.utilities.calculations.store') }}"
    data-pdf-url="{{ route('admin.utilities.pillar-spindle.pdf') }}"
    data-destroy-template="{{ route('admin.utilities.calculations.destroy', ['utility_calculation' => '__ID__']) }}"
    data-saved="{{ json_encode($savedCalculations->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'payload' => $c->payload, 'created_label' => optional($c->created_at)->format('M j, Y g:i A')])) }}"
  >
    <input type="hidden" id="util-ps-csrf" value="{{ csrf_token() }}">

    <div class="util-ps-grid">
      {{-- ───────────────── Inputs ───────────────── --}}
      <section class="admin-card util-ps-inputs">
        <h3 class="util-ps-card-title">Inputs</h3>

        <div class="admin-form-group">
          <span class="admin-form-group-label">Calculation mode</span>
          <div class="util-segmented" role="group" aria-label="Calculation mode" data-ps-mode-toggle>
            <button type="button" class="util-segmented__btn is-active" data-mode="spacing">By spacing</button>
            <button type="button" class="util-segmented__btn" data-mode="count">By number</button>
          </div>
        </div>

        <div class="util-ps-field-row">
          <div class="admin-form-group">
            <label for="ps-length">Total length</label>
            <input type="number" id="ps-length" min="0" step="any" inputmode="decimal" placeholder="e.g. 12">
          </div>
          <div class="admin-form-group util-ps-unit">
            <label for="ps-unit">Unit</label>
            <select id="ps-unit">
              <option value="ft">Feet (ft)</option>
              <option value="m">Meters (m)</option>
              <option value="in">Inches (in)</option>
            </select>
          </div>
        </div>

        <div class="admin-form-group">
          <label for="ps-thickness">
            Pillar thickness
            <span class="util-tip" tabindex="0" data-tip="Width of a single pillar measured along the run. Choose its own unit below.">
              <i class="fa fa-info-circle"></i>
            </span>
          </label>
          <div class="util-ps-input-unit">
            <input type="number" id="ps-thickness" min="0" step="any" inputmode="decimal" placeholder="e.g. 1.5">
            <select id="ps-thickness-unit" aria-label="Thickness unit">
              <option value="ft">ft</option>
              <option value="m">m</option>
              <option value="in" selected>in</option>
            </select>
          </div>
        </div>

        <div class="admin-form-group" data-ps-mode-field="spacing">
          <label for="ps-spacing">
            Desired gap between pillars
            <span class="util-tip" tabindex="0" data-tip="The clear space between two adjacent pillars. We use the fewest pillars that keep every gap at or below this value.">
              <i class="fa fa-info-circle"></i>
            </span>
          </label>
          <div class="util-ps-input-unit">
            <input type="number" id="ps-spacing" min="0" step="any" inputmode="decimal" placeholder="e.g. 4">
            <select id="ps-gap-unit" aria-label="Gap unit">
              <option value="ft">ft</option>
              <option value="m">m</option>
              <option value="in" selected>in</option>
            </select>
          </div>
        </div>

        <div class="admin-form-group" data-ps-mode-field="count" hidden>
          <label for="ps-count">
            Number of pillars
          </label>
          <input type="number" id="ps-count" min="1" step="1" inputmode="numeric" placeholder="e.g. 9">
        </div>

        <div class="admin-form-group">
          <label for="ps-placement">
            End placement
            <span class="util-tip" tabindex="0" data-tip="Whether a pillar sits flush at the start and/or end of the run. This changes how many gaps there are.">
              <i class="fa fa-info-circle"></i>
            </span>
          </label>
          <select id="ps-placement">
            <option value="both">At both ends</option>
            <option value="start">Only at start</option>
            <option value="end">Only at end</option>
            <option value="none">No pillars at ends</option>
          </select>
        </div>

        <button type="button" class="admin-btn admin-btn--ghost util-ps-reset" data-ps-reset>
          <i class="fa fa-undo"></i> Reset
        </button>
      </section>

      {{-- ───────────────── Results + diagram ───────────────── --}}
      <section class="util-ps-output">
        <div class="admin-card util-ps-results">
          <div class="util-ps-results-head">
            <h3 class="util-ps-card-title">Results</h3>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ps-copy hidden>
              <i class="fa fa-copy"></i> Copy results
            </button>
          </div>

          {{-- Empty state --}}
          <div class="util-ps-empty" data-ps-empty>
            <span class="util-ps-empty__icon"><i class="fa fa-arrows-h"></i></span>
            <p class="util-ps-empty__title">Enter your measurements</p>
            <p class="util-ps-empty__hint">Add a <strong>total length</strong>, a pillar <strong>thickness</strong>,
              and a <strong>gap</strong> (or number) to see the layout.</p>
          </div>

          {{-- Error state --}}
          <div class="util-ps-error" data-ps-error hidden>
            <i class="fa fa-exclamation-triangle"></i>
            <span data-ps-error-text></span>
          </div>

          {{-- Live results --}}
          <div class="util-ps-live" data-ps-live hidden>
            <p class="util-ps-breakdown" data-ps-breakdown></p>

            <div class="util-ps-stats">
              <div class="util-ps-stat">
                <span class="util-ps-stat__value" data-ps-stat-count>—</span>
                <span class="util-ps-stat__label" data-ps-stat-count-label>Pillars</span>
              </div>
              <div class="util-ps-stat">
                <span class="util-ps-stat__value" data-ps-stat-gaps>—</span>
                <span class="util-ps-stat__label">Gaps / spaces</span>
              </div>
              <div class="util-ps-stat">
                <span class="util-ps-stat__value" data-ps-stat-clear>—</span>
                <span class="util-ps-stat__fraction" data-ps-stat-clear-fraction hidden></span>
                <span class="util-ps-stat__label">
                  Clear gap
                  <span class="util-tip" tabindex="0" data-tip="Visible space between two adjacent pillars (does not include thickness). The ≈ value is the nearest 1/16 inch.">
                    <i class="fa fa-info-circle"></i>
                  </span>
                </span>
              </div>
              <div class="util-ps-stat">
                <span class="util-ps-stat__value" data-ps-stat-c2c>—</span>
                <span class="util-ps-stat__fraction" data-ps-stat-c2c-fraction hidden></span>
                <span class="util-ps-stat__label">
                  Center&#8209;to&#8209;center
                  <span class="util-tip" tabindex="0" data-tip="Distance from the centre of one pillar to the centre of the next. Includes one thickness. The ≈ value is the nearest 1/16 inch.">
                    <i class="fa fa-info-circle"></i>
                  </span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="admin-card util-ps-diagram-card" data-ps-diagram-card hidden>
          <h3 class="util-ps-card-title">Layout diagram</h3>
          <div class="util-ps-diagram" data-ps-diagram></div>
          <div class="util-ps-export">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ps-export-image>
              <i class="fa fa-image"></i> Save image
            </button>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ps-export-pdf>
              <i class="fa fa-file-pdf-o"></i> Export PDF
            </button>
          </div>
        </div>

        {{-- Tape-measure marking guide --}}
        <div class="admin-card util-ps-marks-card" data-ps-marks-card hidden>
          <div class="util-ps-marks-head">
            <h3 class="util-ps-card-title">Tape-measure marks</h3>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ps-copy-marks>
              <i class="fa fa-copy"></i> Copy marks
            </button>
          </div>
          <p class="util-ps-marks-hint">
            Measured from the <strong>start</strong> of the run. Mark each pillar's near and far edge before you begin.
          </p>
          <div class="util-ps-marks-scroll">
            <table class="util-ps-marks-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Near edge</th>
                  <th>Far edge</th>
                </tr>
              </thead>
              <tbody data-ps-marks-body></tbody>
            </table>
          </div>
        </div>

        {{-- Save calculation --}}
        <div class="admin-card util-ps-save-card" data-ps-save-card hidden>
          <h3 class="util-ps-card-title">Save this calculation</h3>
          <div class="util-ps-save-row">
            <div class="admin-form-group">
              <label for="ps-save-name" class="util-sr-only">Calculation name</label>
              <input type="text" id="ps-save-name" maxlength="120" placeholder="e.g. Front porch railing">
            </div>
            <button type="button" class="admin-btn admin-btn--primary" data-ps-save>
              <i class="fa fa-save"></i> Save
            </button>
          </div>
          <p class="util-ps-save-feedback" data-ps-save-feedback hidden></p>
        </div>
      </section>
    </div>

    {{-- ───────────────── Saved calculations ───────────────── --}}
    <section class="admin-card util-ps-saved" data-ps-saved-section>
      <h3 class="util-ps-card-title">Saved calculations</h3>
      <p class="util-ps-saved-empty" data-ps-saved-empty>No saved calculations yet. Build a layout above and save it for later.</p>
      <ul class="util-ps-saved-list" data-ps-saved-list></ul>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-utilities-pillar-spindle.js') }}?v={{ @filemtime(public_path('js/admin-utilities-pillar-spindle.js')) ?: time() }}"></script>
@endpush

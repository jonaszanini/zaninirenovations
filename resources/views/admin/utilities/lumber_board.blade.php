@extends('admin.layouts.app')

@section('heading', 'Lumber / Board Material Calculator')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Plan how many boards to buy for a job. Add one or more board types (2×4, 2×6, decking, etc.),
      enter required pieces for each, and see cutting layouts with waste and cost estimates.
    </p>
    <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All utilities
    </a>
  </div>

  <div
    class="util-lb"
    data-lumber-board
    data-save-url="{{ route('admin.utilities.calculations.store') }}"
    data-update-template="{{ route('admin.utilities.calculations.update', ['utility_calculation' => '__ID__']) }}"
    data-pdf-url="{{ route('admin.utilities.lumber-board.pdf') }}"
    data-destroy-template="{{ route('admin.utilities.calculations.destroy', ['utility_calculation' => '__ID__']) }}"
    data-project-url-template="{{ route('admin.client-projects.show', ['client_project' => '__ID__']) }}"
    data-link-project-id="{{ $linkProject?->id }}"
    data-saved="{{ json_encode($savedCalculations->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'client_project_id' => $c->client_project_id, 'client_project_name' => $c->clientProject?->name, 'payload' => $c->payload, 'created_label' => optional($c->created_at)->format('M j, Y g:i A'), 'saved_label' => optional($c->updated_at)->format('M j, Y g:i A')])) }}"
  >
    <input type="hidden" id="util-lb-csrf" value="{{ csrf_token() }}">

    <div class="util-lb-grid">
      <section class="admin-card util-lb-inputs">
        <h3 class="util-lb-card-title">Board types</h3>

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

        <div class="util-lb-board-types" data-lb-board-types></div>

        <button type="button" class="admin-btn admin-btn--ghost util-lb-add-bt" data-lb-add-board-type>
          <i class="fa fa-plus"></i> Add new board type
        </button>

        <button type="button" class="admin-btn admin-btn--ghost util-lb-reset" data-lb-reset>
          <i class="fa fa-undo"></i> Reset all
        </button>
      </section>

      <section class="util-lb-output">
        <div class="admin-card util-lb-results util-lb-results--shell">
          <div class="util-lb-results-head">
            <h3 class="util-lb-card-title">Results</h3>
            <div class="util-lb-results-actions" data-lb-results-actions hidden>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-save-quick>
                <i class="fa fa-save"></i> Save
              </button>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-print>
                <i class="fa fa-print"></i> Print list
              </button>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-copy>
                <i class="fa fa-copy"></i> Copy
              </button>
            </div>
          </div>

          <div class="util-lb-empty" data-lb-empty>
            <span class="util-lb-empty__icon"><i class="fa fa-th-large"></i></span>
            <p class="util-lb-empty__title">Add board types and pieces</p>
            <p class="util-lb-empty__hint">Create at least one <strong>board type</strong> with stock lengths and required pieces to see the cutting plan.</p>
          </div>

          <div class="util-lb-type-results" data-lb-type-results hidden></div>

          <div class="admin-card util-lb-global-summary" data-lb-global-summary hidden>
            <h3 class="util-lb-card-title">Grand total</h3>
            <div class="util-lb-stats util-lb-stats--global">
              <div class="util-lb-stat">
                <span class="util-lb-stat__value" data-lb-global-boards>—</span>
                <span class="util-lb-stat__label">Total boards</span>
              </div>
              <div class="util-lb-stat">
                <span class="util-lb-stat__value" data-lb-global-cost>—</span>
                <span class="util-lb-stat__label">Estimated cost</span>
              </div>
              <div class="util-lb-stat util-lb-stat--waste" data-lb-global-waste-wrap>
                <span class="util-lb-stat__value" data-lb-global-waste>—</span>
                <span class="util-lb-stat__label">Total waste</span>
              </div>
            </div>
            <div class="util-lb-breakdown" data-lb-global-breakdown></div>
          </div>
        </div>

        <div class="admin-card util-lb-save-card" data-lb-save-card hidden>
          <h3 class="util-lb-card-title">Save this calculation</h3>
          <div class="util-lb-save-form">
            <div class="admin-form-group">
              <label for="lb-link-project">Link to client project <span class="util-lb-optional">(optional)</span></label>
              <select id="lb-link-project" data-lb-link-project>
                <option value="">— None —</option>
                @foreach ($clientProjects as $cp)
                  <option value="{{ $cp->id }}" @selected($linkProject && (int) $linkProject->id === (int) $cp->id)>{{ $cp->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="util-lb-save-row">
              <div class="admin-form-group">
                <label for="lb-save-name" class="util-sr-only">Calculation name</label>
                <input type="text" id="lb-save-name" maxlength="120" placeholder="e.g. Scott deck — all lumber">
              </div>
              <button type="button" class="admin-btn admin-btn--primary" data-lb-save>
                <i class="fa fa-save"></i> Save
              </button>
            </div>
          </div>
          <p class="util-lb-save-feedback" data-lb-save-feedback hidden></p>
        </div>

        <div class="admin-card util-lb-diagram-card" data-lb-diagram-card hidden>
          <h3 class="util-lb-card-title">Cutting diagrams</h3>
          <p class="util-lb-hint">Coloured segments are cuts; grey is leftover waste on each board.</p>
          <div class="util-lb-diagrams" data-lb-diagrams></div>
          <div class="util-lb-export">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-print>
              <i class="fa fa-print"></i> Print list
            </button>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-export-pdf>
              <i class="fa fa-file-pdf-o"></i> Export PDF
            </button>
          </div>
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

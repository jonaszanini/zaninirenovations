@extends('admin.layouts.app')

@section('heading', 'Measurement Conversion')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Convert a single measurement into every common construction format. Organise rows by job-site project
      and optionally link a project to a real client job.
    </p>
    <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All utilities
    </a>
  </div>

  <div
    class="util-mc"
    data-measurement-conversion
    data-projects="{{ json_encode($projectsJson) }}"
    data-client-projects="{{ json_encode($clientProjects->map(fn ($p) => ['id' => $p->id, 'name' => $p->linkSelectLabel()])) }}"
    data-preview-url="{{ route('admin.utilities.measurement-conversion.preview') }}"
    data-project-store-url="{{ route('admin.utilities.measurement-conversion.projects.store') }}"
    data-project-update-template="{{ route('admin.utilities.measurement-conversion.projects.update', ['measurement_project' => '__ID__']) }}"
    data-project-destroy-template="{{ route('admin.utilities.measurement-conversion.projects.destroy', ['measurement_project' => '__ID__']) }}"
    data-row-store-template="{{ route('admin.utilities.measurement-conversion.rows.store', ['measurement_project' => '__ID__']) }}"
    data-row-destroy-template="{{ route('admin.utilities.measurement-conversion.rows.destroy', ['measurement_row' => '__ID__']) }}"
    data-unit-labels="{{ json_encode($unitLabels) }}">
    <input type="hidden" id="util-mc-csrf" value="{{ csrf_token() }}">

    {{-- Project picker --}}
    <section class="admin-card util-mc-project-bar">
      <div class="util-mc-project-bar__row">
        <div class="admin-form-group util-mc-project-select">
          <label for="mc-project-select">Project</label>
          <select class="admin-input" id="mc-project-select" data-mc-project-select>
            <option value="">— Select a project —</option>
          </select>
        </div>
        <div class="util-mc-project-bar__actions">
          <button type="button" class="admin-btn admin-btn--ghost util-mc-action-btn" data-mc-new-project-toggle>
            <i class="fa fa-plus"></i> New project
          </button>
          <button type="button" class="admin-btn admin-btn--danger util-mc-action-btn" data-mc-delete-project hidden>
            <i class="fa fa-trash"></i> Delete project
          </button>
        </div>
      </div>

      <div class="util-mc-new-project" data-mc-new-project hidden>
        <div class="util-mc-new-project__grid">
          <div class="admin-form-group">
            <label for="mc-new-project-name">Project name</label>
            <input class="admin-input" type="text" id="mc-new-project-name" maxlength="120" placeholder="e.g. Scott Bullen Deck">
          </div>
          <div class="admin-form-group">
            <label for="mc-new-client-project">Link to client project <span class="util-mc-optional">(optional)</span></label>
            <select class="admin-input" id="mc-new-client-project" data-mc-new-client-project>
              <option value="">— None —</option>
              @foreach ($clientProjects as $cp)
                <option value="{{ $cp->id }}">{{ $cp->linkSelectLabel() }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <button type="button" class="admin-btn admin-btn--primary" data-mc-create-project>
          <i class="fa fa-check"></i> Create project
        </button>
      </div>

      <div class="util-mc-linked" data-mc-linked hidden>
        <span class="util-mc-linked__label">Linked to:</span>
        <a href="#" data-mc-linked-link class="util-mc-linked__name"></a>
        <div class="admin-form-group util-mc-link-edit">
          <label for="mc-link-client-project" class="util-sr-only">Link to client project</label>
          <select class="admin-input" id="mc-link-client-project" data-mc-link-client-project>
            <option value="">— None —</option>
            @foreach ($clientProjects as $cp)
              <option value="{{ $cp->id }}">{{ $cp->linkSelectLabel() }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </section>

    {{-- Empty state when no project selected --}}
    <section class="admin-card util-mc-empty" data-mc-empty>
      <span class="util-mc-empty__icon"><i class="fa fa-exchange"></i></span>
      <p class="util-mc-empty__title">Select or create a project</p>
      <p class="util-mc-empty__hint">Choose an existing measurement project above, or click <strong>New project</strong> to start logging conversions.</p>
    </section>

    {{-- Main workspace --}}
    <div class="util-mc-workspace" data-mc-workspace hidden>
      {{-- Input row --}}
      <section class="admin-card util-mc-input-card">
        <h3 class="util-mc-card-title">Add measurement</h3>
        <div class="util-mc-input-grid">
          <div class="admin-form-group">
            <label for="mc-value">Value</label>
            <input class="admin-input" type="text" id="mc-value" inputmode="decimal" placeholder="e.g. 47.25 or 3' 11 1/4&quot;" data-mc-value>
          </div>
          <div class="admin-form-group">
            <label for="mc-unit">Source unit</label>
            <select class="admin-input" id="mc-unit" data-mc-unit>
              @foreach ($unitLabels as $key => $label)
                <option value="{{ $key }}" @selected($key === 'in_dec')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group">
            <label for="mc-label">Label <span class="util-mc-optional">(optional)</span></label>
            <input class="admin-input" type="text" id="mc-label" maxlength="120" placeholder="e.g. Post height" data-mc-label>
          </div>
          <div class="admin-form-group util-mc-add-cell">
            <label class="util-mc-label-spacer" aria-hidden="true">Add</label>
            <button type="button" class="admin-btn admin-btn--primary util-mc-action-btn" data-mc-add>
              <i class="fa fa-plus"></i> Add to project
            </button>
          </div>
        </div>
        <p class="util-mc-input-hint" data-mc-unit-hint>
          Enter a decimal number. For fractions use forms like <strong>1 1/4</strong> or <strong>3' 11 1/4"</strong>.
        </p>
        <p class="util-mc-feedback" data-mc-feedback hidden></p>

        {{-- Live preview --}}
        <div class="util-mc-preview" data-mc-preview hidden>
          <span class="util-mc-preview__label">Preview</span>
          <div class="util-mc-preview__chips" data-mc-preview-chips></div>
        </div>
      </section>

      {{-- Data table --}}
      <section class="admin-card util-mc-table-card">
        <h3 class="util-mc-card-title">Measurements</h3>
        <p class="util-mc-table-empty" data-mc-table-empty>No measurements yet. Add one above.</p>
        <div class="util-mc-table-scroll" data-mc-table-wrap hidden>
          <table class="util-mc-table">
            <thead>
              <tr>
                <th class="util-mc-col-label">Label</th>
                <th>mm</th>
                <th>cm</th>
                <th>m</th>
                <th>in (dec)</th>
                <th>in (frac)</th>
                <th>ft (dec)</th>
                <th>ft/in (dec)</th>
                <th>ft/in (frac)</th>
                <th class="util-mc-col-actions"><span class="util-sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody data-mc-table-body></tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-utilities-measurement-conversion.js') }}?v={{ @filemtime(public_path('js/admin-utilities-measurement-conversion.js')) ?: time() }}"></script>
@endpush

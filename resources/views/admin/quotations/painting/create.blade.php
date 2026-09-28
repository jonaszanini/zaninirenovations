@extends('admin.layouts.app')

@php
  use App\Services\PaintingQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project'
    && $isEdit
    && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
    ? $quotation->project
    : ($linkProject ?? null);

  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter([
    'return' => $returnToProject ? 'project' : null,
    'project' => $preservePid,
  ]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';

  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New painting services quotation';
  $formAction = ($isEdit
    ? route('admin.quotations.painting.update', $quotation)
    : route('admin.quotations.painting.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : PaintingQuotationCalculator::defaultInputs();
  foreach (PaintingQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  $excludedRows = old('excluded_areas');
  if ($excludedRows === null) {
      $excludedRows = is_array($inputs['excluded_areas'] ?? null) ? $inputs['excluded_areas'] : [];
  }
  if (! is_array($excludedRows) || count($excludedRows) === 0) {
      $excludedRows = [['name' => '', 'mode' => PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS, 'length_ft' => '', 'width_ft' => '', 'sqf' => '']];
  }
  $selPaintLine = (int) old('paint_line_id', $inputs['paint_line_id'] ?? 0);
  if (! $isEdit && $selPaintLine === 0 && $paintLines->isNotEmpty()) {
      $selPaintLine = (int) $paintLines->first()->id;
  }
@endphp

@section('heading', $title)

@push('head')
  <style>
    .fq-form-toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px 20px; flex-wrap: wrap; margin-bottom: 18px; }
    .fq-form-toolbar-right { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; flex: 1 1 auto; min-width: 0; }
    .fq-linked-banner { display: flex; gap: 8px; align-items: center; background: #e8f4ea; color: #0f5132; border: 1px solid #bfddc6; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
    .fq-linked-banner a { color: #0f5132; font-weight: 600; }
    .pq-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.95fr); gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .pq-layout { grid-template-columns: 1fr; } }
    .pq-sticky { position: sticky; top: 88px; }
    .pq-summary-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #fafbfc; }
    .pq-row { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 13px; }
    .pq-row--hl { font-weight: 700; color: #024768; font-size: 16px; border-top: 1px solid #d8dee4; margin-top: 8px; padding-top: 10px; }
    .pq-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 700px) { .pq-form-grid { grid-template-columns: 1fr; } }
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .pt-step-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; background: #fff; }
    .pt-step-card h4 { margin: 0 0 12px; color: #024768; }
    .pt-excluded-row {
      display: grid;
      grid-template-columns: minmax(120px, 1fr) 110px minmax(70px, 90px) minmax(70px, 90px) minmax(70px, 90px) 2.25rem;
      gap: 8px;
      align-items: end;
      margin-bottom: 10px;
    }
    .pt-excluded-row--sqf { grid-template-columns: minmax(120px, 1fr) 110px minmax(90px, 120px) 2.25rem; }
    .pt-excluded-row__actions {
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .pt-remove-excluded {
      width: 2.25rem;
      height: 2.25rem;
      min-width: 2.25rem;
      max-width: 2.25rem;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 1;
    }
    @media (max-width: 800px) { .pt-excluded-row, .pt-excluded-row--sqf { grid-template-columns: 1fr; } }
    .pt-net-banner {
      margin-top: 12px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      font-size: 13px;
      color: #1e3a5f;
    }
    .pt-net-banner strong { color: #024768; }
    .pq-sidebar-actions { display: flex; flex-direction: row; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .pq-sidebar-actions .admin-btn { flex: 1 1 auto; min-width: 0; width: auto; justify-content: center; }
    .pq-sidebar-send { margin-bottom: 12px; }
    .pt-readiness {
      margin-bottom: 12px;
      padding: 10px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: #fff;
    }
    .pt-readiness-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 6px 10px;
      margin-bottom: 8px;
    }
    .pt-readiness-title {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #64748b;
    }
    .pt-readiness-summary { font-size: 12px; font-weight: 600; color: #15803d; }
    .pt-readiness-summary.is-incomplete { color: #b45309; }
    .pt-readiness-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .pt-readiness-item {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 12px;
      line-height: 1.4;
      color: #475569;
    }
    .pt-readiness-item.is-done { color: #166534; }
    .pt-readiness-item__icon {
      flex-shrink: 0;
      width: 14px;
      margin-top: 1px;
      text-align: center;
      color: #94a3b8;
    }
    .pt-readiness-item.is-done .pt-readiness-item__icon { color: #16a34a; }
    .pt-readiness-item.is-pending .pt-readiness-item__icon { color: #d97706; }
    .pt-readiness-item.is-optional { color: #64748b; }
    .pt-readiness-item.is-optional .pt-readiness-item__icon { color: #94a3b8; }
    .pt-breakdown { border-top: 1px solid #e2e8f0; padding-top: 10px; margin-top: 4px; }
    .pt-breakdown.is-empty { border-top: 0; padding-top: 0; margin-top: 0; }
  </style>
@endpush

@section('content')
  @if ($isEdit && $quotation->project)
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      <span>
        Linked to
        <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation">{{ $quotation->project->name }}</a>
      </span>
    </div>
  @endif

  @error('calculation')
    <div class="admin-flash admin-flash--danger">{{ $message }}</div>
  @enderror

  <div class="pq-layout">
    <div>
      <form id="pt-main-form" method="post" action="{{ $formAction }}" data-quotation-editor data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}">
        @csrf
        @if ($isEdit)
          @method('PUT')
        @endif
        <input type="hidden" name="save_intent" id="pt-save-intent" value="publish">

        <div class="pt-step-card">
          <h4>Step 1 — Project scope</h4>
          <div class="admin-form-group">
            <label for="client_name">Client name <span class="cp-required">*</span></label>
            <input type="text" class="admin-input" id="client_name" name="client_name" maxlength="160"
                   value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}"
                   @if(!old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) && (!$isEdit || !($quotation->is_draft ?? false))) required @endif>
          </div>
          <div class="admin-form-group">
            <label for="total_property_sqf">Total property size (sq ft)</label>
            <input type="number" step="0.01" min="0" class="admin-input pt-recalc" id="total_property_sqf" name="total_property_sqf"
                   value="{{ old('total_property_sqf', $inputs['total_property_sqf'] ?? '') }}">
          </div>

          <h5 style="margin:16px 0 8px;">Excluded rooms / areas</h5>
          <p class="pq-hint" style="margin:0 0 10px;">Subtract rooms you are not painting. Use dimensions (L × W) or enter total sq ft.</p>
          <div id="pt-excluded-root">
            @foreach ($excludedRows as $i => $row)
              @php
                $mode = old('excluded_areas.'.$i.'.mode', $row['mode'] ?? PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS);
              @endphp
              <div class="pt-excluded-row {{ $mode === PaintingQuotationCalculator::EXCLUDED_MODE_SQF ? 'pt-excluded-row--sqf' : '' }}" data-excluded-row>
                <div class="admin-form-group" style="margin:0;">
                  <label>Label</label>
                  <input type="text" class="admin-input pt-recalc" name="excluded_areas[{{ $i }}][name]" maxlength="160"
                         value="{{ old('excluded_areas.'.$i.'.name', $row['name'] ?? '') }}" placeholder="Bedroom 1">
                </div>
                <div class="admin-form-group" style="margin:0;">
                  <label>Mode</label>
                  <select class="admin-input pt-excluded-mode pt-recalc" name="excluded_areas[{{ $i }}][mode]">
                    <option value="{{ PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS }}" @selected($mode === PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS)>L × W</option>
                    <option value="{{ PaintingQuotationCalculator::EXCLUDED_MODE_SQF }}" @selected($mode === PaintingQuotationCalculator::EXCLUDED_MODE_SQF)>Sq ft</option>
                  </select>
                </div>
                <div class="admin-form-group pt-excluded-dim" style="margin:0;">
                  <label>Length (ft)</label>
                  <input type="number" step="0.1" min="0" class="admin-input pt-recalc" name="excluded_areas[{{ $i }}][length_ft]"
                         value="{{ old('excluded_areas.'.$i.'.length_ft', $row['length_ft'] ?? '') }}">
                </div>
                <div class="admin-form-group pt-excluded-dim" style="margin:0;">
                  <label>Width (ft)</label>
                  <input type="number" step="0.1" min="0" class="admin-input pt-recalc" name="excluded_areas[{{ $i }}][width_ft]"
                         value="{{ old('excluded_areas.'.$i.'.width_ft', $row['width_ft'] ?? '') }}">
                </div>
                <div class="admin-form-group pt-excluded-sqf" style="margin:0;{{ $mode === PaintingQuotationCalculator::EXCLUDED_MODE_SQF ? '' : 'display:none;' }}">
                  <label>Sq ft</label>
                  <input type="number" step="0.01" min="0" class="admin-input pt-recalc" name="excluded_areas[{{ $i }}][sqf]"
                         value="{{ old('excluded_areas.'.$i.'.sqf', $row['sqf'] ?? '') }}">
                </div>
                <div class="pt-excluded-row__actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm pt-remove-excluded" title="Remove row" aria-label="Remove excluded area">
                    <i class="fa fa-times" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
            @endforeach
          </div>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pt-add-excluded">
            <i class="fa fa-plus"></i> Add excluded area
          </button>
          <div class="pt-net-banner" id="pt-net-banner" aria-live="polite">
            Net painting area: <strong id="pt-net-value">—</strong>
          </div>
        </div>

        <div class="pt-step-card">
          <h4>Step 2 — Specifications</h4>
          <div class="pq-form-grid">
            <div class="admin-form-group">
              <label for="paint_line_id">Paint brand / line</label>
              <select class="admin-input pt-recalc" id="paint_line_id" name="paint_line_id">
                <option value="">Select…</option>
                @foreach ($paintLines as $line)
                  <option value="{{ $line->id }}" @selected($selPaintLine === (int) $line->id)>{{ $line->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="admin-form-group">
              <label>Number of top coats</label>
              <input type="text" class="admin-input" value="{{ PaintingQuotationCalculator::DEFAULT_COATS }} (company standard)" readonly>
              <p class="pq-hint">Fixed at {{ PaintingQuotationCalculator::DEFAULT_COATS }} top coats per company standards.</p>
            </div>
          </div>
          <div class="admin-form-group">
            <label for="surface_prep_description">Surface preparation</label>
            <textarea class="admin-input" id="surface_prep_description" name="surface_prep_description" rows="4" maxlength="5000">{{ old('surface_prep_description', $inputs['surface_prep_description'] ?? PaintingQuotationCalculator::defaultSurfacePrepDescription()) }}</textarea>
          </div>
          <div class="admin-form-group quotation-work-desc">
            <label for="work-description">Project description <span class="admin-form-help">(optional — appears on client PDF)</span></label>
            <textarea class="admin-input" id="work-description" name="work_description" rows="8"
                      placeholder="Describe the service to be performed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
            @include('admin.partials.quotation_work_description_ai', [
                'workDescriptionTextareaId' => 'work-description',
                'aiContextFormId' => 'pt-main-form',
                'aiQuotationType' => 'painting',
            ])
            <p class="admin-form-help">Formatting (bold, lists, headings) is preserved on the client PDF.</p>
          </div>
        </div>

        @include('admin.quotations._building', ['quotation' => $quotation, 'inputs' => $inputs ?? [], 'previewClass' => 'pt-recalc'])
        @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])
        @include('admin.quotations._debris_removal', ['quotation' => $quotation])
        @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
      </form>

      @if (! $isEdit)
        @include('admin.quotations._link_to_project_save_first', [
            'linkProject' => $linkProject ?? null,
            'wrapAdminCard' => true,
        ])
      @elseif (! $quotation->isLinked())
        @include('admin.quotations._link_to_project_card', [
            'quotation' => $quotation,
            'linkUrl' => route('admin.quotations.painting.link', $quotation),
            'createProjectUrl' => route('admin.quotations.painting.create-project', $quotation),
            'idPrefix' => 'pt',
            'projectNamePlaceholder' => 'Defaults to client + Painting',
            'linkableProjects' => $linkableProjects,
            'wrapAdminCard' => true,
            'helpText' => 'Add this painting estimate as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).',
        ])
      @endif
    </div>

    <aside class="pq-sticky">
      <div class="pq-sidebar-actions">
        <button type="submit" form="pt-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="pt-btn-publish">
          <i class="fa fa-save"></i> Save quotation
        </button>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pt-btn-draft">Save as draft</button>
        <a href="{{ route('admin.quotations.painting.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-cog"></i> Price configuration
        </a>
        @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
        <a href="{{ route('admin.quotations.painting.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to list
        </a>
        <a href="{{ route('admin.quotations.painting.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
        @include('admin.partials.quotation_view_pdf')
      </div>
      @if ($isEdit && $linkedCpq && $quotation->project && ! $quotation->is_draft)
        @php
          $paintingSendConfirm = 'This will generate a new PDF and email it to '.$quotation->project->clientEmailRecipientsLabel().'. Continue?';
        @endphp
        <form method="post" action="{{ route('admin.client-projects.quotation.send', [$quotation->project, $linkedCpq]) }}"
              class="pq-sidebar-send"
              onsubmit="return confirm(@json($paintingSendConfirm));">
          @csrf
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" style="width:100%;">
            <i class="fa fa-paper-plane"></i> Send to client
          </button>
        </form>
      @endif
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
      @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'pt-main-form'])
      <div class="pq-summary-card">
        <h4 class="cp-form-help-spaced">Live breakdown</h4>
        <div id="pt-live-root">
          <div id="pt-readiness-root" aria-live="polite"></div>
          <div id="pt-breakdown-root" class="pt-breakdown is-empty"></div>
        </div>
      </div>
      <p class="pq-hint" style="margin-top:10px;">
        Labor: net sq ft × ${{ number_format(\App\Services\SharedWorkCatalog::flatRate('paint_walls', (float) $settings->labor_per_sqf), 2) }}/sq ft ·
        Paint: gallons × ${{ number_format((float) $settings->paint_per_gallon, 2) }} + ${{ number_format((float) $settings->material_addon_fee, 2) }} expendables ·
        HST 13%
      </p>
    </aside>
  </div>

  <script>
    (function () {
      var previewUrl = @json(route('admin.quotations.painting.preview'));
      var tokenEl = document.querySelector('meta[name="csrf-token"]');
      var tokenV = tokenEl ? tokenEl.getAttribute('content') : '';
      var form = document.getElementById('pt-main-form');
      var intent = document.getElementById('pt-save-intent');
      var excludedRoot = document.getElementById('pt-excluded-root');
      var excludedIndex = excludedRoot ? excludedRoot.querySelectorAll('[data-excluded-row]').length : 0;
      var MODE_DIM = @json(PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS);
      var MODE_SQF = @json(PaintingQuotationCalculator::EXCLUDED_MODE_SQF);
      var debounceTimer = null;

      function money(n) {
        var v = parseFloat(n);
        if (!isFinite(v)) return 'CAD$ 0.00';
        return 'CAD$ ' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function syncExcludedRowMode(row) {
        if (!row) return;
        var sel = row.querySelector('.pt-excluded-mode');
        var isSqf = sel && sel.value === MODE_SQF;
        row.classList.toggle('pt-excluded-row--sqf', isSqf);
        row.querySelectorAll('.pt-excluded-dim').forEach(function (el) {
          el.style.display = isSqf ? 'none' : '';
        });
        var sqfWrap = row.querySelector('.pt-excluded-sqf');
        if (sqfWrap) sqfWrap.style.display = isSqf ? '' : 'none';
      }

      function bindExcludedRow(row) {
        syncExcludedRowMode(row);
        var modeSel = row.querySelector('.pt-excluded-mode');
        if (modeSel) {
          modeSel.addEventListener('change', function () {
            syncExcludedRowMode(row);
            schedulePreview();
          });
        }
        var removeBtn = row.querySelector('.pt-remove-excluded');
        if (removeBtn) {
          removeBtn.addEventListener('click', function () {
            var rows = excludedRoot.querySelectorAll('[data-excluded-row]');
            if (rows.length <= 1) {
              row.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
              schedulePreview();
              return;
            }
            row.remove();
            schedulePreview();
          });
        }
      }

      function addExcludedRow() {
        var idx = excludedIndex++;
        var row = document.createElement('div');
        row.className = 'pt-excluded-row';
        row.setAttribute('data-excluded-row', '');
        row.innerHTML =
          '<div class="admin-form-group" style="margin:0;"><label>Label</label>'
          + '<input type="text" class="admin-input pt-recalc" name="excluded_areas[' + idx + '][name]" maxlength="160" placeholder="Room name"></div>'
          + '<div class="admin-form-group" style="margin:0;"><label>Mode</label>'
          + '<select class="admin-input pt-excluded-mode pt-recalc" name="excluded_areas[' + idx + '][mode]">'
          + '<option value="' + MODE_DIM + '">L × W</option><option value="' + MODE_SQF + '">Sq ft</option></select></div>'
          + '<div class="admin-form-group pt-excluded-dim" style="margin:0;"><label>Length (ft)</label>'
          + '<input type="number" step="0.1" min="0" class="admin-input pt-recalc" name="excluded_areas[' + idx + '][length_ft]"></div>'
          + '<div class="admin-form-group pt-excluded-dim" style="margin:0;"><label>Width (ft)</label>'
          + '<input type="number" step="0.1" min="0" class="admin-input pt-recalc" name="excluded_areas[' + idx + '][width_ft]"></div>'
          + '<div class="admin-form-group pt-excluded-sqf" style="margin:0;display:none;"><label>Sq ft</label>'
          + '<input type="number" step="0.01" min="0" class="admin-input pt-recalc" name="excluded_areas[' + idx + '][sqf]"></div>'
          + '<div class="pt-excluded-row__actions"><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm pt-remove-excluded" title="Remove row" aria-label="Remove excluded area"><i class="fa fa-times" aria-hidden="true"></i></button></div>';
        excludedRoot.appendChild(row);
        bindExcludedRow(row);
        row.querySelectorAll('.pt-recalc').forEach(function (el) {
          el.addEventListener('input', schedulePreview);
          el.addEventListener('change', schedulePreview);
        });
        schedulePreview();
      }

      if (excludedRoot) {
        excludedRoot.querySelectorAll('[data-excluded-row]').forEach(bindExcludedRow);
      }
      var addBtn = document.getElementById('pt-add-excluded');
      if (addBtn) addBtn.addEventListener('click', addExcludedRow);

      function escHtml(s) {
        return String(s)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      var lastCommuteAmount = 0;
      var lastDebrisAmount = 0;
      var lastBreakdownData = null;

      function collectReadiness() {
        var items = [];
        var totalEl = form ? form.querySelector('[name="total_property_sqf"]') : null;
        var total = totalEl ? parseFloat(totalEl.value) : NaN;
        if (!isFinite(total) || total <= 0) {
          items.push({ key: 'total', label: 'Enter total property size (sq ft)', done: false });
        } else {
          items.push({ key: 'total', label: 'Total property size (sq ft)', done: true });
        }

        var paintEl = form ? form.querySelector('[name="paint_line_id"]') : null;
        var paintId = paintEl ? parseInt(paintEl.value, 10) : 0;
        items.push({
          key: 'paint',
          label: paintId > 0 ? 'Paint brand / line' : 'Select paint brand / line',
          done: paintId > 0
        });

        var excludedSum = 0;
        if (excludedRoot) {
          excludedRoot.querySelectorAll('[data-excluded-row]').forEach(function (row, idx) {
            var nameInp = row.querySelector('[name*="[name]"]');
            var name = nameInp ? String(nameInp.value || '').trim() : '';
            if (name === '') return;
            var modeSel = row.querySelector('.pt-excluded-mode');
            var mode = modeSel ? modeSel.value : MODE_DIM;
            if (mode === MODE_SQF) {
              var sqfInp = row.querySelector('[name*="[sqf]"]');
              var sqf = sqfInp ? parseFloat(sqfInp.value) : NaN;
              if (isFinite(sqf) && sqf > 0) {
                excludedSum += sqf;
              } else {
                items.push({
                  key: 'excluded-sqf-' + idx,
                  label: 'Finish sq ft for “' + name + '” or clear the label',
                  done: false,
                  optional: true
                });
              }
            } else {
              var lenInp = row.querySelector('[name*="[length_ft]"]');
              var widInp = row.querySelector('[name*="[width_ft]"]');
              var len = lenInp ? parseFloat(lenInp.value) : NaN;
              var wid = widInp ? parseFloat(widInp.value) : NaN;
              if (isFinite(len) && len > 0 && isFinite(wid) && wid > 0) {
                excludedSum += len * wid;
              } else {
                items.push({
                  key: 'excluded-dim-' + idx,
                  label: 'Finish length × width for “' + name + '” or clear the label',
                  done: false,
                  optional: true
                });
              }
            }
          });
        }

        var net = isFinite(total) && total > 0 ? Math.max(0, total - excludedSum) : 0;
        if (isFinite(total) && total > 0) {
          if (net <= 0) {
            items.push({
              key: 'net',
              label: 'Net painting area must be greater than zero',
              done: false,
              hint: 'Excluded area exceeds total property size'
            });
          } else {
            items.push({ key: 'net', label: 'Net painting area greater than zero', done: true });
          }
        }

        var required = items.filter(function (it) { return !it.optional; });
        var doneCount = required.filter(function (it) { return it.done; }).length;
        return {
          items: items,
          doneCount: doneCount,
          totalCount: required.length,
          ready: required.length > 0 && doneCount === required.length,
          net: net
        };
      }

      function renderReadiness(state) {
        var root = document.getElementById('pt-readiness-root');
        var netEl = document.getElementById('pt-net-value');
        if (!root) return;
        if (netEl) {
          netEl.textContent = state.net > 0
            ? state.net.toLocaleString('en-CA') + ' sq ft'
            : '—';
        }
        var summaryClass = state.ready ? '' : ' is-incomplete';
        var html = '<div class="pt-readiness">'
          + '<div class="pt-readiness-head">'
          + '<span class="pt-readiness-title">To show prices</span>'
          + '<span class="pt-readiness-summary' + summaryClass + '">'
          + state.doneCount + ' / ' + state.totalCount + ' complete'
          + '</span></div><ul class="pt-readiness-list" aria-label="Fields required for price breakdown">';
        state.items.forEach(function (item) {
          var cls = item.done ? 'is-done' : (item.optional ? 'is-optional' : 'is-pending');
          var icon = item.done ? 'fa-check' : (item.optional ? 'fa-minus' : 'fa-circle-o');
          html += '<li class="pt-readiness-item ' + cls + '">'
            + '<span class="pt-readiness-item__icon" aria-hidden="true"><i class="fa ' + icon + '"></i></span>'
            + '<span>' + escHtml(item.label)
            + (item.optional && !item.done ? '<br><span class="pq-hint">Optional — does not block totals until filled</span>' : '')
            + (item.hint ? '<br><span class="pq-hint">' + escHtml(item.hint) + '</span>' : '')
            + '</span></li>';
        });
        html += '</ul></div>';
        root.innerHTML = html;
      }

      function renderBreakdown(data) {
        var root = document.getElementById('pt-breakdown-root');
        if (!root || !data || !data.summary) return;
        lastBreakdownData = data;
        var scope = data.scope || {};
        var grand = Number(data.summary.grand_total || 0) + lastCommuteAmount + lastDebrisAmount;
        window.__quoteGrandTotal = grand;
        document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: grand } }));
        var html = '';
        (data.breakdown || []).forEach(function (row) {
          html += '<div class="pq-row"><span>' + escHtml(row.label || '')
            + '<br><span class="pq-hint">' + escHtml(row.detail || '') + '</span></span><span>'
            + money(row.amount) + '</span></div>';
        });
        html += '<div class="pq-row"><span>Subtotal (before HST)</span><span>' + money(data.summary.subtotal_pre_hst) + '</span></div>';
        html += '<div class="pq-row"><span>HST (13%)</span><span>' + money(data.summary.hst) + '</span></div>';
        if (lastCommuteAmount > 0) {
          html += '<div class="pq-row"><span>Commute expense (incl. HST)</span><span>' + money(lastCommuteAmount) + '</span></div>';
        }
        if (lastDebrisAmount > 0) {
          html += '<div class="pq-row"><span>Debris removal (incl. HST)</span><span>' + money(lastDebrisAmount) + '</span></div>';
        }
        html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + money(grand) + '</span></div>';
        if (scope.gallons_needed > 0) {
          html += '<p class="pq-hint" style="margin-top:8px;">' + scope.gallons_needed
            + ' gallon(s) @ ' + (scope.sqf_per_gallon || 70) + ' sq ft/gal (2 coats)</p>';
        }
        root.innerHTML = html;
        root.classList.remove('is-empty');
      }

      function clearBreakdown(message) {
        var root = document.getElementById('pt-breakdown-root');
        if (!root) return;
        root.innerHTML = message
          ? '<p class="admin-form-help pq-hint" style="margin:0;">' + escHtml(message) + '</p>'
          : '';
        root.classList.toggle('is-empty', !message);
      }

      function refreshSidebar() {
        var state = collectReadiness();
        renderReadiness(state);
        if (!state.ready) {
          clearBreakdown('Complete the checklist above to see labor, materials, and totals.');
          return;
        }
        clearBreakdown('Calculating…');
        if (!form) return;
        var body = new FormData(form);
        body.delete('_method');
        fetch(previewUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': tokenV,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body
        })
          .then(function (r) {
            return r.json().then(function (data) {
              if (!r.ok) {
                var msg = (data && data.message) ? data.message : 'Could not refresh preview (HTTP ' + r.status + ').';
                throw new Error(msg);
              }
              return data;
            });
          })
          .then(function (data) {
            var latest = collectReadiness();
            if (!latest.ready) {
              refreshSidebar();
              return;
            }
            if (!data || !data.summary) {
              clearBreakdown('Unexpected preview response. Try saving the quotation.');
              return;
            }
            renderBreakdown(data);
            var netEl = document.getElementById('pt-net-value');
            var scope = data.scope || {};
            var net = parseFloat(scope.net_painting_area_sqf || 0);
            if (netEl && isFinite(net) && net > 0) {
              netEl.textContent = net.toLocaleString('en-CA') + ' sq ft';
            }
          })
          .catch(function (err) {
            clearBreakdown(err && err.message ? err.message : 'Could not refresh preview. Try again.');
          });
      }

      function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshSidebar, 280);
      }

      document.querySelectorAll('.pt-recalc').forEach(function (el) {
        el.addEventListener('input', schedulePreview);
        el.addEventListener('change', schedulePreview);
      });

      var savingAsDraft = false;
      var draftBtn = document.getElementById('pt-btn-draft');
      if (draftBtn && form) {
        draftBtn.addEventListener('click', function () {
          savingAsDraft = true;
          if (intent) intent.value = 'draft';
          form.submit();
        });
      }
      var pubBtn = document.getElementById('pt-btn-publish');
      if (pubBtn && intent) {
        pubBtn.addEventListener('click', function () {
          intent.value = 'publish';
        });
      }
      if (form && intent) {
        form.addEventListener('submit', function () {
          if (!savingAsDraft) {
            intent.value = 'publish';
          }
          savingAsDraft = false;
        });
      }

      refreshSidebar();

      document.addEventListener('commute-expense:change', function (e) {
        var d = e.detail || {};
        lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastBreakdownData) renderBreakdown(lastBreakdownData);
      });
      document.addEventListener('debris-removal:change', function (e) {
        var d = e.detail || {};
        lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastBreakdownData) renderBreakdown(lastBreakdownData);
      });
    })();
  </script>
@endsection

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'work-description',
  'formId' => 'pt-main-form',
])

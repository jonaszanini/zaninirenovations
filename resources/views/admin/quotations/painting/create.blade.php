@extends('admin.layouts.app')

@php
  use App\Services\PaintingQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project'
    && $isEdit
    && $quotation->project;
  $projectForReturn = $returnToProject ? $quotation->project : null;

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
    .fq-link-project-card {
      border: 1px dashed #c9d3e0;
      background: #fbfcfd;
      border-radius: 10px;
      padding: 18px 20px;
      margin-top: 20px;
    }
    .fq-link-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
      margin-top: 14px;
      align-items: start;
    }
    @media (min-width: 900px) {
      .fq-link-grid { grid-template-columns: 1fr 1fr; }
    }
    .fq-link-col h5 { margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #024768; }
    .fq-link-existing-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
    }
    .fq-create-project-form .admin-form-group { margin-bottom: 12px; }
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
    .pq-sidebar-actions { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
    .pq-sidebar-actions-row { display: flex; flex-wrap: wrap; gap: 8px; }
  </style>
@endpush

@section('content')
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.painting.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    <div class="fq-form-toolbar-right">
      <a href="{{ route('admin.quotations.painting.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
      @if ($returnToProject && $projectForReturn)
        <a href="{{ route('admin.client-projects.show', $projectForReturn) }}#quotation" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
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

  @error('calculation')
    <div class="admin-flash admin-flash--danger">{{ $message }}</div>
  @enderror

  <div class="pq-layout">
    <div>
      <form id="pt-main-form" method="post" action="{{ $formAction }}">
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
                   value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : '') }}">
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
          <div class="admin-form-group">
            <label for="work-description">Project description <span class="admin-form-help">(optional — appears on client PDF)</span></label>
            @include('admin.partials.quotation_work_description_ai', [
                'workDescriptionTextareaId' => 'work-description',
                'aiContextFormId' => 'pt-main-form',
                'aiQuotationType' => 'painting',
            ])
            <textarea class="admin-input" id="work-description" name="work_description" rows="5" maxlength="5000">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          </div>
        </div>

        @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])
      </form>

      @if (! $isEdit)
        <div class="admin-card fq-link-project-card">
          <h4 style="margin:0 0 8px;">Link to project</h4>
          <p class="admin-form-help" style="margin:0;">
            <strong>Save the quotation first</strong> (use <em>Save quotation</em>, not draft) with a total greater than zero.
            Then this page will show options to link an existing project or create a new one.
            @if ($linkProject)
              <span class="cp-form-help-flush" style="display:block;margin-top:8px;">
                You opened this from <strong>{{ $linkProject->name }}</strong> — a published save will attach automatically when possible.
              </span>
            @endif
          </p>
        </div>
      @elseif (! $quotation->isLinked())
        <div class="admin-card fq-link-project-card">
          <h4 style="margin:0 0 8px;">Link to project</h4>
          <p class="admin-form-help cp-form-help-flush" style="margin:0 0 12px;">
            Add this painting estimate as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).
          </p>
          <div class="fq-link-grid">
            <div>
              <h5>Existing project</h5>
              <form method="post" action="{{ route('admin.quotations.painting.link', $quotation) }}">
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
                <div class="admin-form-group cp-form-group-spaced">
                  <label>Line description (optional)</label>
                  <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="{{ $quotation->summaryDescription() }}">
                </div>
              </form>
            </div>
            <div class="fq-link-col fq-link-col--stack">
              <h5>Create project &amp; link</h5>
              <form method="post" action="{{ route('admin.quotations.painting.create-project', $quotation) }}" class="fq-create-project-form">
                @csrf
                <input type="hidden" name="link_line_description" value="">
                <div class="admin-form-group">
                  <label>Project name</label>
                  <input type="text" name="fp_project_name" class="admin-input" maxlength="255" placeholder="Defaults to client + Painting">
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
        <button type="submit" form="pt-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="pt-btn-publish">
          <i class="fa fa-save"></i> Save quotation
        </button>
        @if ($isEdit && $linkedCpq && $quotation->project && ! $quotation->is_draft)
          <form method="post" action="{{ route('admin.client-projects.quotation.send', [$quotation->project, $linkedCpq]) }}"
                onsubmit="return confirm('Email this quotation PDF to the client?');">
            @csrf
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" style="width:100%;">
              <i class="fa fa-paper-plane"></i> Send to client
            </button>
          </form>
        @endif
        <div class="pq-sidebar-actions-row">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pt-btn-draft">Save as draft</button>
          <a href="{{ route('admin.quotations.painting.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
        </div>
      </div>
      <div class="pq-summary-card">
        <h4 class="cp-form-help-spaced">Live breakdown</h4>
        <div id="pt-live-root">
          <p class="admin-form-help">Adjust inputs to refresh totals…</p>
        </div>
      </div>
      <p class="pq-hint" style="margin-top:10px;">
        Labor: net sq ft × ${{ number_format((float) $settings->labor_per_sqf, 2) }}/sq ft ·
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

      function renderPreview(data) {
        var root = document.getElementById('pt-live-root');
        var netEl = document.getElementById('pt-net-value');
        if (!root || !data || !data.summary) return;
        var scope = data.scope || {};
        var net = parseFloat(scope.net_painting_area_sqf || 0);
        if (netEl) {
          netEl.textContent = isFinite(net) ? net.toLocaleString('en-CA') + ' sq ft' : '—';
        }
        var html = '';
        (data.breakdown || []).forEach(function (row) {
          html += '<div class="pq-row"><span>' + (row.label || '') + '<br><span class="pq-hint">' + (row.detail || '') + '</span></span><span>' + money(row.amount) + '</span></div>';
        });
        html += '<div class="pq-row"><span>Subtotal (before HST)</span><span>' + money(data.summary.subtotal_pre_hst) + '</span></div>';
        html += '<div class="pq-row"><span>HST (13%)</span><span>' + money(data.summary.hst) + '</span></div>';
        html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + money(data.summary.grand_total) + '</span></div>';
        if (scope.gallons_needed > 0) {
          html += '<p class="pq-hint" style="margin-top:8px;">' + scope.gallons_needed + ' gallon(s) @ ' + (scope.sqf_per_gallon || 70) + ' sq ft/gal (2 coats)</p>';
        }
        root.innerHTML = html;
      }

      function runPreview() {
        if (!form) return;
        var body = new FormData(form);
        fetch(previewUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': tokenV, 'Accept': 'application/json' },
          body: body
        }).then(function (r) { return r.json(); }).then(renderPreview).catch(function () {});
      }

      function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runPreview, 280);
      }

      document.querySelectorAll('.pt-recalc').forEach(function (el) {
        el.addEventListener('input', schedulePreview);
        el.addEventListener('change', schedulePreview);
      });

      var draftBtn = document.getElementById('pt-btn-draft');
      if (draftBtn) {
        draftBtn.addEventListener('click', function () {
          if (intent) intent.value = 'draft';
          form.submit();
        });
      }
      var pubBtn = document.getElementById('pt-btn-publish');
      if (pubBtn) {
        pubBtn.addEventListener('click', function () {
          if (intent) intent.value = 'publish';
        });
      }

      runPreview();
    })();
  </script>
@endsection

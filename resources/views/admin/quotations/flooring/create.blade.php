@extends('admin.layouts.app')

@php
  use App\Services\FlooringQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = $returnToProject ? $quotation->project : null;
  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New flooring quotation';
  $formAction = ($isEdit ? route('admin.quotations.flooring.update', $quotation) : route('admin.quotations.flooring.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : FlooringQuotationCalculator::defaultInputs();
  foreach (FlooringQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) { $inputs[$k] = $v; }
  }
  $selType = (int) old('flooring_type_id', $inputs['flooring_type_id'] ?? 0);
  if (! $isEdit && $selType === 0 && $flooringTypes->isNotEmpty()) {
      $selType = (int) $flooringTypes->first()->id;
  }
@endphp

@section('heading', $title)

@push('head')
  <style>
    .pq-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.95fr); gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .pq-layout { grid-template-columns: 1fr; } }
    .pq-sticky { position: sticky; top: 88px; }
    .pq-summary-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #fafbfc; }
    .pq-row { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 13px; }
    .pq-row--hl { font-weight: 700; color: #024768; font-size: 16px; border-top: 1px solid #d8dee4; margin-top: 8px; padding-top: 10px; }
    .fl-step-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; background: #fff; }
    .fl-step-card h4 { margin: 0 0 12px; color: #024768; }
    .fl-addon-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #e8edf2; }
    .fl-addon-row:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
    .fl-addon-fields { flex: 1; min-width: 140px; max-width: 200px; }
    .fl-addon-fields[hidden] { display: none !important; }
    .fl-section-toggle[hidden] { display: none !important; }
    .pq-sidebar-actions { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
    .pq-sidebar-actions-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .fl-breakdown-section { margin-top: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
  </style>
@endpush

@section('content')
  <div class="admin-toolbar" style="margin-bottom:16px;">
    <a href="{{ route('admin.quotations.flooring.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    <a href="{{ route('admin.quotations.flooring.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm" style="margin-left:8px;">
      <i class="fa fa-cog"></i> Price configuration
    </a>
  </div>

  @if ($isEdit && $quotation->project)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation"><strong>{{ $quotation->project->name }}</strong></a>
    </div>
  @endif

  @if ($errors->has('calculation'))
    <div class="admin-flash admin-flash--danger">{{ $errors->first('calculation') }}</div>
  @endif

  <form method="post" action="{{ $formAction }}" id="fl-main-form" class="pq-layout">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="save_intent" id="fl-save-intent" value="publish">

    <div>
      <div class="admin-card fl-step-card">
        <h4>Client &amp; scope</h4>
        <div class="admin-form-group">
          <label for="fl-client-name">Client name</label>
          <input type="text" class="admin-input" id="fl-client-name" name="client_name" maxlength="160"
                 value="{{ old('client_name', $quotation->client_name ?? optional($linkProject)->client_name) }}" required>
        </div>
        @include('admin.partials.quotation_work_description_ai', ['quotationType' => 'flooring', 'workDescription' => old('work_description', $quotation->work_description ?? '')])
      </div>

      <div class="admin-card fl-step-card">
        <h4>Core measurements</h4>
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="fl-flooring-type">Flooring type</label>
            <select class="admin-input" id="fl-flooring-type" name="flooring_type_id" required>
              <option value="">— Select —</option>
              @foreach ($flooringTypes as $type)
                <option value="{{ $type->id }}" @selected($selType === (int) $type->id)>{{ $type->name }} ({{ \App\Models\ClientProject::formatMoney($type->installation_price_per_sqft) }}/sq ft)</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group">
            <label for="fl-sqft">Total square footage</label>
            <input type="number" step="0.01" min="0" class="admin-input" id="fl-sqft" name="square_footage"
                   value="{{ old('square_footage', $inputs['square_footage'] ?? 0) }}" required>
          </div>
        </div>
      </div>

      <div class="admin-card fl-step-card">
        <h4>Optional add-ons</h4>
        <p class="admin-form-help" style="margin:0 0 14px;">Rates from <a href="{{ route('admin.quotations.flooring.price-configuration') }}">price configuration</a>.</p>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="remove_carpet" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger" name="remove_carpet" value="1" @checked(old('remove_carpet', $inputs['remove_carpet'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Remove carpet &amp; dispose off site</span>
          </label>
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->remove_carpet_dispose_rate) }}/sq ft</span>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="rescrew_subfloor" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger" name="rescrew_subfloor" value="1" @checked(old('rescrew_subfloor', $inputs['rescrew_subfloor'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Re-screw subfloor to joists</span>
          </label>
          <span class="admin-form-help">Flat {{ \App\Models\ClientProject::formatMoney($settings->rescrew_subfloor_rate) }}</span>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="install_shoe_moulding" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-toggle" data-target="fl-shoe-ft" name="install_shoe_moulding" value="1" @checked(old('install_shoe_moulding', $inputs['install_shoe_moulding'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Install primed white shoe moulding</span>
          </label>
          <div class="fl-addon-fields" id="fl-shoe-ft" @unless(old('install_shoe_moulding', $inputs['install_shoe_moulding'] ?? false)) hidden @endunless>
            <label class="admin-form-help">Linear feet</label>
            <input type="number" step="0.01" min="0" class="admin-input fl-preview-trigger" name="shoe_moulding_linear_ft"
                   value="{{ old('shoe_moulding_linear_ft', $inputs['shoe_moulding_linear_ft'] ?? 0) }}">
          </div>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="caulk_paint_shoe_moulding" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-toggle" data-target="fl-caulk-ft" name="caulk_paint_shoe_moulding" value="1" @checked(old('caulk_paint_shoe_moulding', $inputs['caulk_paint_shoe_moulding'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Caulking and painting shoe moulding</span>
          </label>
          <div class="fl-addon-fields" id="fl-caulk-ft" @unless(old('caulk_paint_shoe_moulding', $inputs['caulk_paint_shoe_moulding'] ?? false)) hidden @endunless>
            <label class="admin-form-help">Linear feet</label>
            <input type="number" step="0.01" min="0" class="admin-input fl-preview-trigger" name="caulk_paint_linear_ft"
                   value="{{ old('caulk_paint_linear_ft', $inputs['caulk_paint_linear_ft'] ?? 0) }}">
          </div>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="closet_doors" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-toggle" data-target="fl-closet-qty" name="closet_doors" value="1" @checked(old('closet_doors', $inputs['closet_doors'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Remove and reinstall closet doors</span>
          </label>
          <div class="fl-addon-fields" id="fl-closet-qty" @unless(old('closet_doors', $inputs['closet_doors'] ?? false)) hidden @endunless>
            <label class="admin-form-help">Quantity</label>
            <input type="number" step="1" min="0" class="admin-input fl-preview-trigger" name="closet_door_qty"
                   value="{{ old('closet_door_qty', $inputs['closet_door_qty'] ?? 0) }}">
          </div>
        </div>
      </div>

      <div class="admin-card fl-step-card">
        <label class="admin-checkbox" style="margin-bottom:12px;">
          <input type="hidden" name="include_material_details" value="0">
          <input type="checkbox" class="admin-checkbox__input fl-toggle" data-target="fl-material-section" name="include_material_details" value="1" @checked(old('include_material_details', $inputs['include_material_details'] ?? false))>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text"><strong>Include client material selection details</strong></span>
        </label>
        <div id="fl-material-section" class="fl-section-toggle" @unless(old('include_material_details', $inputs['include_material_details'] ?? false)) hidden @endunless>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="fl-material-cost">Material cost per sq ft (CAD$)</label>
              <input type="number" step="0.0001" min="0" class="admin-input fl-preview-trigger" id="fl-material-cost" name="material_cost_per_sqft"
                     value="{{ old('material_cost_per_sqft', $inputs['material_cost_per_sqft'] ?? 0) }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-name">Material name / brand</label>
              <input type="text" class="admin-input" id="fl-material-name" name="material_name" maxlength="160"
                     value="{{ old('material_name', $inputs['material_name'] ?? '') }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-color">Colour</label>
              <input type="text" class="admin-input" id="fl-material-color" name="material_color" maxlength="120"
                     value="{{ old('material_color', $inputs['material_color'] ?? '') }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-url">Store URL</label>
              <input type="url" class="admin-input" id="fl-material-url" name="material_url" maxlength="500"
                     value="{{ old('material_url', $inputs['material_url'] ?? '') }}" placeholder="https://">
            </div>
          </div>
        </div>
      </div>

      @unless ($isEdit && $quotation->isLinked())
        @include('admin.quotations._link_project_picker', [
          'linkableProjects' => $linkableProjects,
          'linkProject' => $linkProject,
          'linkRoute' => $isEdit ? route('admin.quotations.flooring.link', $quotation) : null,
          'createProjectRoute' => $isEdit ? route('admin.quotations.flooring.create-project', $quotation) : null,
        ])
      @endunless
    </div>

    <aside class="pq-sticky">
      <div class="pq-sidebar-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="fl-btn-publish">
          <i class="fa fa-save"></i> Save quotation
        </button>
        <div class="pq-sidebar-actions-row">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fl-btn-draft">Save as draft</button>
          <a href="{{ route('admin.quotations.flooring.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
        </div>
      </div>
      <div class="pq-summary-card">
        <h4 style="margin:0 0 10px;">Live breakdown</h4>
        <div id="fl-live-root">
          <p class="admin-form-help" id="fl-live-empty">Enter measurements to see totals.</p>
          <div id="fl-live-body" hidden></div>
        </div>
      </div>
    </aside>
  </form>

  <script>
    (function () {
      var form = document.getElementById('fl-main-form');
      var previewUrl = @json(route('admin.quotations.flooring.preview'));
      var tokenEl = document.querySelector('meta[name="csrf-token"]');
      var token = tokenEl ? tokenEl.getAttribute('content') : '';
      var debounceTimer = null;
      var fmt = function (n) { return 'CA$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };

      document.getElementById('fl-btn-draft').addEventListener('click', function () {
        document.getElementById('fl-save-intent').value = 'draft';
        form.requestSubmit();
      });
      document.getElementById('fl-btn-publish').addEventListener('click', function () {
        document.getElementById('fl-save-intent').value = 'publish';
      });

      document.querySelectorAll('.fl-toggle').forEach(function (cb) {
        var target = document.getElementById(cb.getAttribute('data-target'));
        if (!target) return;
        var sync = function () { target.hidden = !cb.checked; schedulePreview(); };
        cb.addEventListener('change', sync);
        sync();
      });

      function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runPreview, 280);
      }

      function runPreview() {
        var fd = new FormData(form);
        fetch(previewUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
          body: fd
        }).then(function (r) { return r.json(); }).then(renderPreview).catch(function () {});
      }

      function renderPreview(data) {
        var empty = document.getElementById('fl-live-empty');
        var body = document.getElementById('fl-live-body');
        var summary = data.summary || {};
        var breakdown = data.breakdown || [];
        if (!summary.grand_total || summary.grand_total <= 0) {
          empty.hidden = false;
          body.hidden = true;
          return;
        }
        empty.hidden = true;
        body.hidden = false;
        var html = '';
        var lastSection = '';
        breakdown.forEach(function (row) {
          var sec = row.section === 'materials' ? 'Materials' : 'Labor';
          if (sec !== lastSection) {
            html += '<div class="fl-breakdown-section">' + sec + '</div>';
            lastSection = sec;
          }
          html += '<div class="pq-row"><span>' + (row.label || '') + '<br><span class="admin-form-help">' + (row.detail || '') + '</span></span><span>' + fmt(row.amount) + '</span></div>';
        });
        html += '<div class="pq-row"><span>Subtotal (pre-HST)</span><span>' + fmt(summary.subtotal_pre_hst) + '</span></div>';
        html += '<div class="pq-row"><span>HST (13%)</span><span>' + fmt(summary.hst) + '</span></div>';
        html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + fmt(summary.grand_total) + '</span></div>';
        body.innerHTML = html;
      }

      form.querySelectorAll('.fl-preview-trigger, #fl-flooring-type, #fl-sqft, #fl-material-cost').forEach(function (el) {
        el.addEventListener('input', schedulePreview);
        el.addEventListener('change', schedulePreview);
      });
      schedulePreview();
    })();
  </script>
@endsection

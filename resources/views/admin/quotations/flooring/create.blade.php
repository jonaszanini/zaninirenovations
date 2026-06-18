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
  $shoeLinearFt = old('shoe_moulding_linear_ft');
  if ($shoeLinearFt === null) {
      $shoeLinearFt = max(
          (float) ($inputs['shoe_moulding_linear_ft'] ?? 0),
          (float) ($inputs['caulk_paint_linear_ft'] ?? 0)
      );
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
    .fq-link-project-card {
      border: 1px dashed #c9d3e0;
      background: #fbfcfd;
      border-radius: 10px;
      padding: 18px 20px;
    }
    .fq-link-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      align-items: start;
    }
    @media (min-width: 900px) {
      .fq-link-grid { grid-template-columns: 1fr 1fr; }
    }
    .fq-link-grid h5 { margin: 0 0 10px; font-size: 14px; font-weight: 600; color: #024768; }
    .fq-create-project-form .admin-form-group { margin-bottom: 12px; }
    .fq-create-project-form .admin-form-grid-2 { margin-bottom: 12px; }
    .fq-create-project-form .admin-form-grid-2 .admin-form-group { margin-bottom: 0; }
    .fq-create-project-contact {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.35fr);
      gap: 16px;
      margin-bottom: 12px;
      align-items: start;
    }
    .fq-create-project-contact .admin-form-group { margin-bottom: 0; min-width: 0; }
    .fl-material-summary {
      margin-top: 14px;
      padding: 12px 14px;
      border-radius: 8px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      font-size: 13px;
    }
    .fl-material-summary[hidden] { display: none !important; }
    .fl-material-summary__title {
      margin: 0 0 8px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #64748b;
    }
    .fl-material-summary__row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 3px 0;
      color: #334155;
    }
    .fl-material-summary__row--total {
      margin-top: 6px;
      padding-top: 8px;
      border-top: 1px solid #dbe3ec;
      font-weight: 700;
      color: #024768;
    }
    .fl-material-tax-note {
      margin: 0 0 12px;
      padding: 8px 10px;
      border-radius: 6px;
      background: #fffbeb;
      border: 1px solid #fde68a;
      color: #92400e;
      font-size: 12px;
      line-height: 1.45;
    }
    .fl-field-warning {
      margin: 6px 0 0;
      font-size: 12px;
      color: #b45309;
      line-height: 1.4;
    }
    .fl-field-warning[hidden] { display: none !important; }
    .fq-create-project-contact .admin-places-field { margin-bottom: 0; }
    @media (max-width: 900px) {
      .fq-create-project-contact { grid-template-columns: 1fr; }
    }
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
                 value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}" required>
        </div>
        <div class="admin-form-group">
          <label for="fl-work-description">Project description <span class="admin-form-help">(optional — appears on client PDF)</span></label>
          <textarea class="admin-input" id="fl-work-description" name="work_description" rows="5" maxlength="5000">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'fl-work-description',
              'aiContextFormId' => 'fl-main-form',
              'aiQuotationType' => 'flooring',
          ])
        </div>
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
          <div class="admin-form-group">
            <label for="fl-shoe-linear-ft">Shoe moulding linear feet</label>
            <input type="number" step="0.01" min="0" class="admin-input fl-preview-trigger" id="fl-shoe-linear-ft" name="shoe_moulding_linear_ft"
                   value="{{ $shoeLinearFt }}">
            <p class="admin-form-help">Used for install and/or caulk &amp; paint shoe moulding add-ons.</p>
            <p class="fl-field-warning" id="fl-shoe-linear-ft-warning" hidden>
              <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
              Enter linear feet when a shoe moulding add-on is selected.
            </p>
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
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-shoe-addon" name="install_shoe_moulding" value="1" @checked(old('install_shoe_moulding', $inputs['install_shoe_moulding'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Install primed white shoe moulding</span>
          </label>
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->install_shoe_moulding_rate) }}/lin ft</span>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="caulk_paint_shoe_moulding" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-shoe-addon" name="caulk_paint_shoe_moulding" value="1" @checked(old('caulk_paint_shoe_moulding', $inputs['caulk_paint_shoe_moulding'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Caulking and painting shoe moulding</span>
          </label>
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->caulk_paint_shoe_moulding_rate) }}/lin ft</span>
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
          <p class="fl-material-tax-note">
            <strong>Important:</strong> Material cost per sq ft (CAD$) is <strong>before tax</strong>. Waste and delivery are added below to estimate the total material price.
          </p>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="fl-material-cost">Material cost per sq ft (CAD$)</label>
              <input type="number" step="0.0001" min="0" class="admin-input fl-preview-trigger fl-material-calc" id="fl-material-cost" name="material_cost_per_sqft"
                     value="{{ old('material_cost_per_sqft', $inputs['material_cost_per_sqft'] ?? 0) }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-waste">Waste (%)</label>
              <input type="number" step="0.1" min="0" max="100" class="admin-input fl-preview-trigger fl-material-calc" id="fl-material-waste" name="material_waste_percent"
                     value="{{ old('material_waste_percent', $inputs['material_waste_percent'] ?? 5) }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-delivery">Delivery (CAD$)</label>
              <input type="number" step="0.01" min="0" class="admin-input fl-preview-trigger fl-material-calc" id="fl-material-delivery" name="material_delivery_fee"
                     value="{{ old('material_delivery_fee', $inputs['material_delivery_fee'] ?? 60) }}">
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
          <div class="fl-material-summary" id="fl-material-summary" hidden>
            <p class="fl-material-summary__title">Material price summary (before tax)</p>
            <div class="fl-material-summary__row"><span id="fl-material-summary-base-label">Material</span><span id="fl-material-summary-base">—</span></div>
            <div class="fl-material-summary__row" id="fl-material-summary-waste-row" hidden><span id="fl-material-summary-waste-label">Waste</span><span id="fl-material-summary-waste">—</span></div>
            <div class="fl-material-summary__row" id="fl-material-summary-delivery-row" hidden><span>Delivery</span><span id="fl-material-summary-delivery">—</span></div>
            <div class="fl-material-summary__row fl-material-summary__row--total"><span>Total material price</span><span id="fl-material-summary-total">—</span></div>
          </div>
        </div>
      </div>

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
      @endif
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

  @if ($isEdit && ! $quotation->isLinked())
    <div class="admin-card fq-link-project-card" style="margin-top:16px;">
      <h4 style="margin:0 0 8px;">Link to project</h4>
      <p class="admin-form-help cp-form-help-flush" style="margin:0 0 12px;">
        Add this flooring estimate as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).
      </p>
      <div class="fq-link-grid">
        <div>
          <h5>Existing project</h5>
          <form method="post" action="{{ route('admin.quotations.flooring.link', $quotation) }}">
            @csrf
            @if ($linkableProjects->isNotEmpty())
              <div class="fq-link-existing-row">
                @include('admin.quotations._link_project_picker', ['projects' => $linkableProjects])
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Link</button>
              </div>
            @else
              <p class="admin-form-help cp-form-help-flush">No active projects to link. Create a new one below.</p>
            @endif
            <div class="admin-form-group cp-form-group-spaced">
              <label>Line description (optional)</label>
              <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="{{ $quotation->summaryDescription() }}">
            </div>
          </form>
        </div>
        <div class="fq-link-col fq-link-col--stack">
          <h5>Create project &amp; link</h5>
          <form method="post" action="{{ route('admin.quotations.flooring.create-project', $quotation) }}" class="fq-create-project-form">
            @csrf
            <div class="admin-form-grid-2">
              <div class="admin-form-group">
                <label for="fl-new-project-name">Project name</label>
                <input type="text" id="fl-new-project-name" name="fp_project_name" class="admin-input" maxlength="255"
                       value="{{ old('fp_project_name') }}" placeholder="Defaults to client + Flooring">
              </div>
              <div class="admin-form-group">
                <label for="fl-new-client-name">Client name <span class="cp-required">*</span></label>
                <input type="text" id="fl-new-client-name" name="fp_client_name"
                       class="admin-input @error('fp_client_name') is-invalid @enderror"
                       required maxlength="255"
                       value="{{ old('fp_client_name', $quotation->client_name) }}">
                @error('fp_client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
            </div>
            <div class="fq-create-project-contact">
              <div class="admin-form-group">
                <label for="fl-new-client-email">Email</label>
                <input type="email" id="fl-new-client-email" name="fp_client_email"
                       class="admin-input @error('fp_client_email') is-invalid @enderror"
                       maxlength="255" value="{{ old('fp_client_email') }}">
                @error('fp_client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
              <div class="admin-form-group">
                <label for="fl-new-client-phone">Phone</label>
                <input type="text" id="fl-new-client-phone" name="fp_client_phone"
                       class="admin-input cp-phone-input @error('fp_client_phone') is-invalid @enderror"
                       inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx"
                       value="{{ old('fp_client_phone') ? \App\Models\ClientProject::formatPhone(old('fp_client_phone')) : '' }}">
                @error('fp_client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
              @include('admin.partials.places_address_field', [
                'inputId' => 'fl-new-client-address',
                'addressName' => 'fp_client_address',
                'label' => 'Address',
                'value' => old('fp_client_address'),
                'placeIdName' => 'fp_client_address_place_id',
                'placeIdValue' => old('fp_client_address_place_id'),
                'latName' => 'fp_client_address_latitude',
                'latValue' => old('fp_client_address_latitude'),
                'lngName' => 'fp_client_address_longitude',
                'lngValue' => old('fp_client_address_longitude'),
              ])
            </div>
            <div class="admin-form-group">
              <label for="fl-new-line-desc">Line description (optional)</label>
              <input type="text" id="fl-new-line-desc" name="link_line_description" class="admin-input" maxlength="500"
                     value="{{ old('link_line_description') }}" placeholder="{{ $quotation->summaryDescription() }}">
            </div>
            <button type="submit" class="admin-btn admin-btn--primary">Create project &amp; link</button>
          </form>
        </div>
      </div>
    </div>
  @endif

  @if ($isEdit && ! $quotation->isLinked())
    @push('scripts')
      @include('admin.partials.google_maps_scripts')
    @endpush
  @endif

  <script>
    (function () {
      var form = document.getElementById('fl-main-form');
      var previewUrl = @json(route('admin.quotations.flooring.preview'));
      var initialPreview = @json($initialPreview ?? null);
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

      function syncShoeMouldingWarning() {
        var install = form.querySelector('input[name="install_shoe_moulding"]').checked;
        var caulk = form.querySelector('input[name="caulk_paint_shoe_moulding"]').checked;
        var input = document.getElementById('fl-shoe-linear-ft');
        var warn = document.getElementById('fl-shoe-linear-ft-warning');
        if (!input || !warn) return;
        var lf = parseFloat(input.value || '0');
        var needsValue = (install || caulk) && !(lf > 0);
        warn.hidden = !needsValue;
        input.classList.toggle('is-invalid', needsValue);
      }

      form.querySelectorAll('.fl-shoe-addon').forEach(function (el) {
        el.addEventListener('change', function () {
          syncShoeMouldingWarning();
          schedulePreview();
        });
      });
      var shoeLinearInput = document.getElementById('fl-shoe-linear-ft');
      if (shoeLinearInput) {
        shoeLinearInput.addEventListener('input', syncShoeMouldingWarning);
        shoeLinearInput.addEventListener('change', syncShoeMouldingWarning);
      }
      syncShoeMouldingWarning();

      function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runPreview, 280);
      }

      function runPreview() {
        var fd = new FormData(form);
        fetch(previewUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
          body: fd,
          credentials: 'same-origin'
        }).then(function (r) {
          if (!r.ok) {
            return null;
          }
          return r.json();
        }).then(function (data) {
          if (data) {
            renderPreview(data);
          }
        }).catch(function () {});
      }

      function renderPreview(data) {
        var empty = document.getElementById('fl-live-empty');
        var body = document.getElementById('fl-live-body');
        var summary = data.summary || {};
        var breakdown = data.breakdown || [];
        renderMaterialSummary(data.material_selection || null);
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

      function renderMaterialSummary(selection) {
        var panel = document.getElementById('fl-material-summary');
        var includeCb = form.querySelector('input[name="include_material_details"]');
        if (!panel || !includeCb || !includeCb.checked || !selection || !selection.total_pre_tax) {
          if (panel) panel.hidden = true;
          return;
        }

        var sqft = Number(selection.square_footage || 0);
        var rate = Number(selection.cost_per_sqft || 0);
        var wastePercent = Number(selection.waste_percent || 0);
        var baseAmount = Number(selection.base_amount || 0);
        var wasteAmount = Number(selection.waste_amount || 0);
        var deliveryFee = Number(selection.delivery_fee || 0);
        var total = Number(selection.total_pre_tax || 0);

        document.getElementById('fl-material-summary-base-label').textContent =
          sqft > 0 && rate > 0
            ? ('Material (' + sqft.toLocaleString() + ' sq ft × ' + fmt(rate) + '/sq ft)')
            : 'Material';
        document.getElementById('fl-material-summary-base').textContent = fmt(baseAmount);

        var wasteRow = document.getElementById('fl-material-summary-waste-row');
        if (wasteAmount > 0) {
          wasteRow.hidden = false;
          document.getElementById('fl-material-summary-waste-label').textContent = 'Waste (' + wastePercent + '%)';
          document.getElementById('fl-material-summary-waste').textContent = fmt(wasteAmount);
        } else {
          wasteRow.hidden = true;
        }

        var deliveryRow = document.getElementById('fl-material-summary-delivery-row');
        if (deliveryFee > 0) {
          deliveryRow.hidden = false;
          document.getElementById('fl-material-summary-delivery').textContent = fmt(deliveryFee);
        } else {
          deliveryRow.hidden = true;
        }

        document.getElementById('fl-material-summary-total').textContent = fmt(total);
        panel.hidden = false;
      }

      form.querySelectorAll('.fl-preview-trigger, #fl-flooring-type, #fl-sqft, .fl-material-calc').forEach(function (el) {
        el.addEventListener('input', schedulePreview);
        el.addEventListener('change', schedulePreview);
      });
      var includeMaterialCb = form.querySelector('input[name="include_material_details"]');
      if (includeMaterialCb) {
        includeMaterialCb.addEventListener('change', schedulePreview);
      }
      if (initialPreview) {
        renderPreview(initialPreview);
      }
      schedulePreview();
    })();
  </script>
@endsection

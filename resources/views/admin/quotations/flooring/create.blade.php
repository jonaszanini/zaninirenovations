@extends('admin.layouts.app')

@php
  use App\Services\FlooringQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : ($linkProject ?? null);
  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New flooring quotation';
  $formAction = ($isEdit ? route('admin.quotations.flooring.update', $quotation) : route('admin.quotations.flooring.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : FlooringQuotationCalculator::defaultInputs();
  foreach (FlooringQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) { $inputs[$k] = $v; }
  }
  if (! $isEdit && ! empty($roomAreaImport['inputs']) && is_array($roomAreaImport['inputs'])) {
      $inputs = array_merge($inputs, $roomAreaImport['inputs']);
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
  $baseboardLinearFt = old('baseboard_linear_ft', $inputs['baseboard_linear_ft'] ?? 0);
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
    .pq-sidebar-actions { display: flex; flex-direction: row; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .pq-sidebar-actions .admin-btn { flex: 1 1 auto; min-width: 0; width: auto; justify-content: center; }
    .fl-breakdown-section { margin-top: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
    .fl-bd-group { display: flex; flex-direction: column; gap: 2px; }
    .fl-bd-group__title {
      margin: 4px 0 6px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--admin-muted, #64748b);
    }
    .fl-bd-item {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      padding: 5px 0;
      font-size: 13px;
    }
    .fl-bd-item__text { display: flex; flex-direction: column; min-width: 0; }
    .fl-bd-item__label { color: var(--admin-text, #1e293b); line-height: 1.35; }
    .fl-bd-item__detail { font-size: 11.5px; color: var(--admin-muted, #64748b); line-height: 1.35; margin-top: 1px; }
    .fl-bd-amount { white-space: nowrap; font-variant-numeric: tabular-nums; text-align: right; }
    .fl-bd-item .fl-bd-amount { color: var(--admin-text, #1e293b); }
    .fl-bd-subtotal {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-top: 6px;
      padding: 8px 10px;
      border-radius: 6px;
      background: var(--admin-surface-hover, #f1f5f9);
      font-size: 13px;
      font-weight: 700;
      color: var(--admin-heading, #024768);
    }
    .fl-bd-divider {
      height: 1px;
      margin: 16px 0 12px;
      background: linear-gradient(90deg, transparent, var(--admin-border, #d8dee4) 15%, var(--admin-border, #d8dee4) 85%, transparent);
    }
    .fl-bd-totals {
      margin-top: 16px;
      padding-top: 12px;
      border-top: 1px solid var(--admin-border, #d8dee4);
    }
    .fl-bd-total-row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 3px 0;
      font-size: 13px;
      color: var(--admin-text, #334155);
    }
    .fl-bd-grand {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 12px;
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid var(--admin-border, #d8dee4);
      font-size: 16px;
      font-weight: 700;
      color: #024768;
    }
    html.admin-dark .fl-bd-grand { color: var(--admin-heading); }
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
  @include('admin.partials.quotation_editor_header', [
    'title' => $title,
    'typeLabel' => 'Flooring',
    'project' => $projectForReturn,
    'clientName' => old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name),
    'listUrl' => route('admin.quotations.flooring.index', $preservePid ? ['project' => $preservePid] : []),
    'cancelUrl' => route('admin.quotations.flooring.index', $preservePid ? ['project' => $preservePid] : []),
    'priceConfigUrl' => route('admin.quotations.flooring.price-configuration', $preservePid ? ['project' => $preservePid] : []),
    'formId' => 'fl-main-form',
    'saveId' => 'fl-btn-publish',
    'draftId' => 'fl-btn-draft',
    'draftHotkey' => true,
  ])
  @include('admin.partials.quotation_editor_more_actions', [
    'isEdit' => $isEdit,
    'quotation' => $quotation ?? null,
    'linkedCpq' => $linkedCpq ?? null,
  ])
  @include('admin.partials.room_area_prefill_banner')
  @if ($isEdit && $quotation->project)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation"><strong>{{ $quotation->project->name }}</strong></a>
    </div>
  @endif

  @if ($errors->has('calculation'))
    <div class="admin-flash admin-flash--danger">{{ $errors->first('calculation') }}</div>
  @endif

  <form method="post" action="{{ $formAction }}" id="fl-main-form" class="pq-layout" data-quotation-editor data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}">
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
        <div class="admin-form-group quotation-work-desc">
          <label for="fl-work-description">Project description <span class="admin-form-help">(optional — appears on client PDF)</span></label>
          <textarea class="admin-input" id="fl-work-description" name="work_description" rows="8"
                    placeholder="Describe the service to be performed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'fl-work-description',
              'aiContextFormId' => 'fl-main-form',
              'aiQuotationType' => 'flooring',
          ])
          <p class="admin-form-help">Formatting (bold, lists, headings) is preserved on the client PDF.</p>
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
          @include('admin.quotations._shared_qty_field', [
            'id' => 'fl-shoe-linear-ft',
            'name' => 'shoe_moulding_linear_ft',
            'label' => 'Shoe moulding linear feet',
            'value' => $shoeLinearFt,
            'info' => 'Used for install and/or caulk & paint shoe moulding add-ons.',
            'workKey' => 'shoe_install',
            'gearLabel' => 'Edit shoe moulding rates',
            'rate' => \App\Models\ClientProject::formatMoney($settings->shoeInstallRate()).' / lf install · '.\App\Models\ClientProject::formatMoney($settings->shoeCaulkPaintRate()).' / lf caulk & paint',
            'inputClass' => 'fl-preview-trigger',
            'warningId' => 'fl-shoe-linear-ft-warning',
            'warning' => 'Enter linear feet when a shoe moulding add-on is selected.',
          ])
          @include('admin.quotations._shared_qty_field', [
            'id' => 'fl-baseboard-linear-ft',
            'name' => 'baseboard_linear_ft',
            'label' => 'Baseboard linear feet',
            'value' => $baseboardLinearFt,
            'info' => 'Used for install and/or caulk & paint baseboard add-ons.',
            'workKey' => 'baseboard',
            'gearLabel' => 'Edit baseboard rates',
            'rate' => \App\Models\ClientProject::formatMoney($settings->baseboardInstallRate()).' / lf install · '.\App\Models\ClientProject::formatMoney($settings->baseboardCaulkPaintRate()).' / lf caulk & paint',
            'inputClass' => 'fl-preview-trigger',
            'warningId' => 'fl-baseboard-linear-ft-warning',
            'warning' => 'Enter linear feet when a baseboard add-on is selected.',
          ])
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
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->removeCarpetRate()) }}/sq ft</span>
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
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->shoeInstallRate()) }}/lin ft</span>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="caulk_paint_shoe_moulding" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-shoe-addon" name="caulk_paint_shoe_moulding" value="1" @checked(old('caulk_paint_shoe_moulding', $inputs['caulk_paint_shoe_moulding'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Caulking and painting shoe moulding</span>
          </label>
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->shoeCaulkPaintRate()) }}/lin ft</span>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="install_baseboard" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-baseboard-addon" name="install_baseboard" value="1" @checked(old('install_baseboard', $inputs['install_baseboard'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Install baseboard</span>
          </label>
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->baseboardInstallRate()) }}/lin ft</span>
        </div>

        <div class="fl-addon-row">
          <label class="admin-checkbox">
            <input type="hidden" name="caulk_paint_baseboard" value="0">
            <input type="checkbox" class="admin-checkbox__input fl-preview-trigger fl-baseboard-addon" name="caulk_paint_baseboard" value="1" @checked(old('caulk_paint_baseboard', $inputs['caulk_paint_baseboard'] ?? false))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Caulking and painting baseboard</span>
          </label>
          <span class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($settings->baseboardCaulkPaintRate()) }}/lin ft</span>
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
              <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                     class="admin-input cp-money-input fl-preview-trigger fl-material-calc" id="fl-material-cost" name="material_cost_per_sqft"
                     value="{{ old('material_cost_per_sqft', number_format((float) ($inputs['material_cost_per_sqft'] ?? 0), 4, '.', '')) }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-waste">Waste (%)</label>
              <input type="number" step="0.1" min="0" max="100" class="admin-input fl-preview-trigger fl-material-calc" id="fl-material-waste" name="material_waste_percent"
                     value="{{ old('material_waste_percent', $inputs['material_waste_percent'] ?? 5) }}">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-delivery">Delivery (CAD$)</label>
              <input type="text" inputmode="decimal" autocomplete="off"
                     class="admin-input cp-money-input fl-preview-trigger fl-material-calc" id="fl-material-delivery" name="material_delivery_fee"
                     placeholder="0.00" value="{{ old('material_delivery_fee', number_format((float) ($inputs['material_delivery_fee'] ?? 60), 2, '.', '')) }}">
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
            <div class="admin-form-group">
              <label for="fl-material-image-url-1">Material Image URL 1</label>
              <input type="url" class="admin-input" id="fl-material-image-url-1" name="material_image_url_1" maxlength="500"
                     value="{{ old('material_image_url_1', $inputs['material_image_url_1'] ?? '') }}" placeholder="https://">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-image-url-2">Material Image URL 2</label>
              <input type="url" class="admin-input" id="fl-material-image-url-2" name="material_image_url_2" maxlength="500"
                     value="{{ old('material_image_url_2', $inputs['material_image_url_2'] ?? '') }}" placeholder="https://">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-image-url-3">Material Image URL 3</label>
              <input type="url" class="admin-input" id="fl-material-image-url-3" name="material_image_url_3" maxlength="500"
                     value="{{ old('material_image_url_3', $inputs['material_image_url_3'] ?? '') }}" placeholder="https://">
            </div>
            <div class="admin-form-group">
              <label for="fl-material-image-url-4">Material Image URL 4</label>
              <input type="url" class="admin-input" id="fl-material-image-url-4" name="material_image_url_4" maxlength="500"
                     value="{{ old('material_image_url_4', $inputs['material_image_url_4'] ?? '') }}" placeholder="https://">
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

      @include('admin.quotations._payment_schedule', ['quotation' => $quotation])

      @include('admin.quotations._building', ['quotation' => $quotation, 'inputs' => $inputs ?? [], 'previewClass' => 'fl-preview-trigger'])
      @include('admin.quotations._debris_removal', ['quotation' => $quotation])
      @include('admin.quotations._additional_costs', ['quotation' => $quotation])

      @if (! $isEdit)
        @include('admin.quotations._link_to_project_save_first', [
            'linkProject' => $linkProject ?? null,
            'wrapAdminCard' => true,
        ])
      @endif
    </div>

    <aside class="pq-sticky">
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
      @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'fl-main-form'])
      @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'fl-main-form'])
      <div class="pq-summary-card">
        <div class="qh-priced-head">
          <h4>Live breakdown</h4>
          @include('admin.partials.quotation_price_config_gear', [
            'url' => route('admin.quotations.flooring.price-configuration', $preservePid ? ['project' => $preservePid] : []),
          ])
        </div>
        <div id="fl-live-root">
          <p class="admin-form-help" id="fl-live-empty">Enter measurements to see totals.</p>
          <div id="fl-live-body" hidden></div>
        </div>
      </div>
    </aside>
  </form>

  @if ($isEdit && ! $quotation->isLinked())
    @include('admin.quotations._link_to_project_card', [
        'quotation' => $quotation,
        'linkUrl' => route('admin.quotations.flooring.link', $quotation),
        'createProjectUrl' => route('admin.quotations.flooring.create-project', $quotation),
        'idPrefix' => 'fl',
        'projectNamePlaceholder' => 'Defaults to client + Flooring',
        'linkableProjects' => $linkableProjects,
        'wrapAdminCard' => true,
        'cardStyle' => 'margin-top:16px;',
        'helpText' => 'Add this flooring estimate as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).',
        'createLineDescription' => 'field',
        'usePlacesAddress' => true,
    ])
  @endif

  <script>
    (function () {
      var form = document.getElementById('fl-main-form');
      var previewUrl = @json(
        $isEdit && $quotation
          ? route('admin.quotations.flooring.calculate-existing', $quotation)
          : route('admin.quotations.flooring.calculate')
      );
      var initialPreview = @json($initialPreview ?? null);
      var tokenEl = document.querySelector('meta[name="csrf-token"]');
      var token = tokenEl ? tokenEl.getAttribute('content') : '';
      var debounceTimer = null;
      var lastDebrisAmount = 0;
      var lastAdditionalMaterialsPreTax = 0;
      var lastAdditionalMaterialsHst = 0;
      var lastAdditionalLaborPreTax = 0;
      var lastAdditionalLaborHst = 0;
      var lastAdditionalTaxIn = 0;
      var lastPreviewData = null;
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

      function syncBaseboardWarning() {
        var install = form.querySelector('input[name="install_baseboard"]');
        var caulk = form.querySelector('input[name="caulk_paint_baseboard"]');
        var input = document.getElementById('fl-baseboard-linear-ft');
        var warn = document.getElementById('fl-baseboard-linear-ft-warning');
        if (!install || !caulk || !input || !warn) return;
        var lf = parseFloat(input.value || '0');
        var needsValue = (install.checked || caulk.checked) && !(lf > 0);
        warn.hidden = !needsValue;
        input.classList.toggle('is-invalid', needsValue);
      }

      form.querySelectorAll('.fl-baseboard-addon').forEach(function (el) {
        el.addEventListener('change', function () {
          syncBaseboardWarning();
          schedulePreview();
        });
      });
      var baseboardLinearInput = document.getElementById('fl-baseboard-linear-ft');
      if (baseboardLinearInput) {
        baseboardLinearInput.addEventListener('input', syncBaseboardWarning);
        baseboardLinearInput.addEventListener('change', syncBaseboardWarning);
      }
      syncBaseboardWarning();

      function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runPreview, 280);
      }

      function runPreview() {
        var fd = new FormData(form);
        form.querySelectorAll('.cp-money-input[name]').forEach(function (input) {
          fd.set(input.name, (input.value || '').replace(/,/g, ''));
        });
        fd.delete('_method');
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
        lastPreviewData = data;
        if (window.QuotationBuildingLaborUi) window.QuotationBuildingLaborUi.sync(data);
        var empty = document.getElementById('fl-live-empty');
        var body = document.getElementById('fl-live-body');
        var summary = data.summary || {};
        var breakdown = data.breakdown || [];
        renderMaterialSummary(data.material_selection || null);
        var liveGrand = Number(summary.grand_total || 0) > 0
          ? Number(summary.grand_total) + lastDebrisAmount + lastAdditionalTaxIn
          : 0;
        window.__quoteGrandTotal = liveGrand;
        document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: liveGrand } }));
        if (!summary.grand_total || summary.grand_total <= 0) {
          empty.hidden = false;
          body.hidden = true;
          return;
        }
        empty.hidden = true;
        body.hidden = false;
        function esc(s) {
          return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
          });
        }
        function itemRow(label, detail, amount) {
          return '<div class="fl-bd-item">'
            + '<div class="fl-bd-item__text"><span class="fl-bd-item__label">' + esc(label) + '</span>'
            + (detail ? '<span class="fl-bd-item__detail">' + esc(detail) + '</span>' : '')
            + '</div><span class="fl-bd-amount">' + fmt(amount) + '</span></div>';
        }
        function group(title, rows, subtotalLabel, subtotal) {
          return '<section class="fl-bd-group">'
            + '<h5 class="fl-bd-group__title">' + title + '</h5>'
            + rows.join('')
            + '<div class="fl-bd-subtotal"><span>' + subtotalLabel + '</span><span class="fl-bd-amount">' + fmt(subtotal) + '</span></div>'
            + '</section>';
        }

        var laborRows = [];
        var materialRows = [];
        breakdown.forEach(function (row) {
          var html = itemRow(row.label, row.detail, row.amount);
          if (row.section === 'materials') materialRows.push(html); else laborRows.push(html);
        });
        if (lastAdditionalLaborPreTax > 0) {
          laborRows.push(itemRow('Additional costs', 'Admin only — folded into labour on client PDF', lastAdditionalLaborPreTax));
        }
        if (lastAdditionalMaterialsPreTax > 0) {
          materialRows.push(itemRow('Additional costs', 'Admin only — folded into materials on client PDF', lastAdditionalMaterialsPreTax));
        }

        var laborSub = Number(summary.labor_pre_hst || 0) + lastAdditionalLaborPreTax;
        var materialSub = Number(summary.materials_pre_hst || 0) + lastAdditionalMaterialsPreTax;

        var html = '';
        if (laborRows.length) {
          html += group('Labour', laborRows, 'Labour subtotal', laborSub);
        }
        if (laborRows.length && materialRows.length) {
          html += '<div class="fl-bd-divider" role="presentation"></div>';
        }
        if (materialRows.length) {
          html += group('Materials', materialRows, 'Materials subtotal', materialSub);
        }

        html += '<div class="fl-bd-totals">';
        html += '<div class="fl-bd-total-row"><span>Subtotal (pre-HST)</span><span class="fl-bd-amount">' + fmt(Number(summary.subtotal_pre_hst || 0) + lastAdditionalLaborPreTax + lastAdditionalMaterialsPreTax) + '</span></div>';
        html += '<div class="fl-bd-total-row"><span>HST (13%)</span><span class="fl-bd-amount">' + fmt(Number(summary.hst || 0) + lastAdditionalLaborHst + lastAdditionalMaterialsHst) + '</span></div>';
        if (lastDebrisAmount > 0) {
          html += '<div class="fl-bd-total-row"><span>Debris removal (incl. HST)</span><span class="fl-bd-amount">' + fmt(lastDebrisAmount) + '</span></div>';
        }
        var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn;
        html += '<div class="fl-bd-grand"><span>Grand total</span><span class="fl-bd-amount">' + fmt(grand) + '</span></div>';
        html += '</div>';
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

      document.addEventListener('debris-removal:change', function (e) {
        var d = e.detail || {};
        lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastPreviewData) {
          renderPreview(lastPreviewData);
        }
      });
      document.addEventListener('additional-costs:change', function (e) {
        var d = e.detail || {};
        lastAdditionalMaterialsPreTax = Number(d.materialsPreTax || 0);
        lastAdditionalMaterialsHst = Number(d.materialsHst || 0);
        lastAdditionalLaborPreTax = Number(d.laborPreTax || 0);
        lastAdditionalLaborHst = Number(d.laborHst || 0);
        lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
        if (lastPreviewData) {
          renderPreview(lastPreviewData);
        }
      });
    })();
  </script>
@endsection

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'fl-work-description',
  'formId' => 'fl-main-form',
])

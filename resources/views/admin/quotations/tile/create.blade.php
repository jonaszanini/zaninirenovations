@extends('admin.layouts.app')

@php
  $isEdit = isset($quotation) && filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = $returnToProject ? $quotation->project : null;
  $preservePid = optional($linkProject ?? null)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New tile quotation';
  $formAction = ($isEdit ? route('admin.quotations.tile.update', $quotation) : route('admin.quotations.tile.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : [];

  $total_area_sqft = old('total_area_sqft', $inputs['total_area_sqft'] ?? '');
  $length_ft = old('length_ft', $inputs['length_ft'] ?? '');
  $width_ft = old('width_ft', $inputs['width_ft'] ?? '');
  $waste_margin_percent = old('waste_margin_percent', $inputs['waste_margin_percent'] ?? 10);
  $tile_product_id = old('tile_product_id', $inputs['tile_product_id'] ?? '');
  $include_tile_material = old('include_tile_material', $inputs['include_tile_material'] ?? false);
  $trowel_size = old('trowel_size', $inputs['trowel_size'] ?? '1/4x1/4');
  $mortar_type_id = old('mortar_type_id', $inputs['mortar_type_id'] ?? '');
  $joint_width_decimal = old('joint_width_decimal', $inputs['joint_width_decimal'] ?? 0.125); // 1/8" default
  $grout_type_id = old('grout_type_id', $inputs['grout_type_id'] ?? '');
  $labor_rate = old('labor_rate', $inputs['labor_rate'] ?? 12);
  $work_description = old('work_description', $isEdit ? $quotation->work_description : '');
  $client_name = old('client_name', $isEdit ? $quotation->client_name : '');
  
  $include_demolition = old('include_demolition', $inputs['include_demolition'] ?? false);
  $demolition_complexity = old('demolition_complexity', $inputs['demolition_complexity'] ?? 'standard');
  $custom_demolition_rate = old('custom_demolition_rate', $inputs['custom_demolition_rate'] ?? '');
  $include_disposal_fee = old('include_disposal_fee', $inputs['include_disposal_fee'] ?? false);
@endphp

@section('heading', $title)

@push('head')
  <style>
    .pq-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.95fr); gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .pq-layout { grid-template-columns: 1fr; } }
    .pq-sticky { position: sticky; top: 88px; }
    .pq-summary-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #fafbfc; }
    .fl-step-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; background: #fff; }
    .fl-step-card h4 { margin: 0 0 12px; color: #024768; }
    .pq-sidebar-actions { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
    .pq-sidebar-actions-row { display: flex; flex-wrap: wrap; gap: 8px; }
    
    /* Toolbar */
    .fq-form-toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px 20px; flex-wrap: wrap; margin-bottom: 18px; }
    .fq-form-toolbar-right { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; flex: 1 1 auto; min-width: 0; }
    @media (max-width: 720px) {
      .fq-form-toolbar { flex-direction: column; align-items: stretch; }
      .fq-form-toolbar> a:first-child { width: fit-content; }
      .fq-form-toolbar-right { justify-content: flex-start; }
    }
    .fq-linked-banner { display: flex; gap: 8px; align-items: center; background: #e8f4ea; color: #0f5132; border: 1px solid #bfddc6; border-radius: 8px; padding: 10px 14px; margin-bottom: 0; }
    .fq-linked-banner a { color: #0f5132; font-weight: 600; }
  </style>
  <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
  @if ($isEdit && $quotation->isLinked())
    <div class="admin-banner admin-banner--info" style="margin-bottom:16px;">
      <i class="fa fa-info-circle"></i>
      This quotation is linked to project <strong>{{ $quotation->project->name }}</strong>. 
      Saving changes will update the project tab.
    </div>
  @endif

  <form action="{{ $formAction }}" method="POST" id="tl-form">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="is_draft" id="tl-is-draft" value="{{ old('is_draft', $isEdit && $quotation->is_draft ? '1' : '0') }}">
    
    <div class="fq-form-toolbar">
      <a href="{{ $returnToProject ? route('admin.projects.show', ['project' => $quotation->client_project_id, 'tab' => 'quotation']) : route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back
      </a>
      <div class="fq-form-toolbar-right">
        @if ($linkProject)
          <div class="fq-linked-banner">
            <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.projects.show', $linkProject) }}">{{ $linkProject->name }}</a>
          </div>
          <input type="hidden" name="client_project_id" value="{{ $linkProject->id }}">
        @elseif ($isEdit && $quotation->project)
          <div class="fq-linked-banner">
            <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.projects.show', $quotation->project) }}">{{ $quotation->project->name }}</a>
          </div>
          <input type="hidden" name="client_project_id" value="{{ $quotation->client_project_id }}">
        @else
          <!-- Dropdown for unlinked quotes -->
          <div style="display: flex; gap: 8px; align-items: center; background: #f8fafc; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <i class="fa fa-link" style="color: #64748b;"></i>
            <span style="font-size: 13px; font-weight: 500; color: #475569;">Link project:</span>
            <select name="client_project_id" class="admin-input admin-input--sm" style="min-width: 180px;">
              <option value="">-- None (Standalone quote) --</option>
              @foreach (\App\Models\ClientProject::query()->orderByDesc('created_at')->get() as $p)
                <option value="{{ $p->id }}" {{ strval($preservePid) === strval($p->id) ? 'selected' : '' }}>{{ $p->name }}</option>
              @endforeach
            </select>
          </div>
        @endif
      </div>
    </div>

    <div class="pq-layout">
      <div>
      <div class="fl-step-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h4 style="margin:0;">1. General Details</h4>
            <a href="{{ route('admin.quotations.tile.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                <i class="fa fa-cog"></i> Config
            </a>
        </div>
        
        @if (! $isEdit || ! $quotation->isLinked())
          <div class="admin-form-group">
            <label class="admin-form-label">Client Name (Optional)</label>
            <input type="text" name="client_name" class="admin-input" value="{{ $client_name }}">
          </div>
        @endif

        <div class="admin-form-group">
          <label class="admin-form-label">Work Description (Appears on PDF)</label>
          <textarea id="tl-work-description" name="work_description" class="admin-input" rows="3">{{ $work_description }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'tl-work-description',
              'aiContextFormId' => 'tl-form',
              'aiQuotationType' => 'tile',
          ])
        </div>
      </div>

      <div class="fl-step-card">
        <h4>2. Measurements</h4>
        <div style="margin-bottom:12px; font-size:14px; color:#475569;">
          Enter the <strong>Total Area</strong> directly, OR enter both <strong>Length and Width</strong>.
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
          <div class="admin-form-group" style="margin-bottom:0;">
            <label class="admin-form-label">Total Area (sqft)</label>
            <input type="number" step="0.01" name="total_area_sqft" id="tl-total-area" class="admin-input tl-live-trigger" value="{{ !empty($total_area_sqft) && $total_area_sqft > 0 ? $total_area_sqft : '' }}">
          </div>
          <div class="admin-form-group" style="margin-bottom:0;">
            <label class="admin-form-label">Length (ft)</label>
            <input type="number" step="0.01" name="length_ft" id="tl-length" class="admin-input tl-live-trigger" value="{{ !empty($length_ft) && $length_ft > 0 ? $length_ft : '' }}">
          </div>
          <div class="admin-form-group" style="margin-bottom:0;">
            <label class="admin-form-label">Width (ft)</label>
            <input type="number" step="0.01" name="width_ft" id="tl-width" class="admin-input tl-live-trigger" value="{{ !empty($width_ft) && $width_ft > 0 ? $width_ft : '' }}">
          </div>
        </div>
        <div class="admin-form-grid-2">
            <div class="admin-form-group">
                <label class="admin-form-label">Waste Margin (%) <span class="admin-text-danger">*</span></label>
                <input type="number" step="0.1" name="waste_margin_percent" class="admin-input tl-live-trigger" value="{{ $waste_margin_percent }}" required>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Labor Rate per sqft ($) <span class="admin-text-danger">*</span></label>
                <input type="number" step="0.01" name="labor_rate" class="admin-input tl-live-trigger" value="{{ $labor_rate }}" required>
            </div>
        </div>
      </div>

      <div class="fl-step-card">
        <h4>3. Demolition & Prep Work</h4>
        <div class="admin-form-group">
          <label class="admin-checkbox">
            <input type="checkbox" name="include_demolition" id="tl-include-demolition" value="1" class="admin-checkbox__input tl-live-trigger" {{ $include_demolition ? 'checked' : '' }}>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Include Tile Removal</span>
          </label>
        </div>
        
        <div id="tl-demolition-details" style="{{ $include_demolition ? '' : 'display:none;' }} background:#f8fafc; padding:16px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:16px;">
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label">Removal Complexity</label>
              <select name="demolition_complexity" id="tl-demolition-complexity" class="admin-input tl-live-trigger">
                <option value="standard" {{ $demolition_complexity === 'standard' ? 'selected' : '' }}>Standard Removal (CAD$ 4.00 / sq ft)</option>
                <option value="heavy" {{ $demolition_complexity === 'heavy' ? 'selected' : '' }}>Heavy-Duty Removal (CAD$ 6.50 / sq ft)</option>
                <option value="custom" {{ $demolition_complexity === 'custom' ? 'selected' : '' }}>Custom Rate</option>
              </select>
            </div>
            <div class="admin-form-group" id="tl-custom-demo-rate-group" style="{{ $demolition_complexity === 'custom' ? '' : 'display:none;' }}">
              <label class="admin-form-label">Custom Rate ($ / sq ft)</label>
              <input type="number" step="0.01" name="custom_demolition_rate" id="tl-custom-demo-rate" class="admin-input tl-live-trigger" value="{{ $custom_demolition_rate }}">
            </div>
          </div>
          
          <div class="admin-form-group" style="margin-bottom:0;">
            <label class="admin-checkbox">
              <input type="checkbox" name="include_disposal_fee" value="1" class="admin-checkbox__input tl-live-trigger" {{ $include_disposal_fee ? 'checked' : '' }}>
              <span class="admin-checkbox__mark"></span>
              <span class="admin-checkbox__text">Include Disposal Fee (Flat CAD$ 60.00)</span>
            </label>
          </div>
        </div>
      </div>

      <div class="fl-step-card">
        <h4>4. Tile Selection</h4>
        <div class="admin-form-group">
          <label class="admin-form-label">Tile Product</label>
          <select name="tile_product_id" id="tile_product_id" class="admin-input tl-live-trigger">
            <option value="" data-url="" data-image="">-- No Tile Selected --</option>
            @foreach ($products as $p)
              <option value="{{ $p->id }}" data-url="{{ $p->url }}" data-image="{{ $p->image_path ? Storage::url($p->image_path) : '' }}" {{ $tile_product_id == $p->id ? 'selected' : '' }}>
                {{ $p->name }} (${{ number_format($p->cost_per_sqft, 2) }}/sqft, {{ (float)$p->length_inches }}x{{ (float)$p->width_inches }}x{{ (float)$p->thickness_inches }}")
              </option>
            @endforeach
          </select>
          <div id="tile-preview" style="margin-top:12px;"></div>
        </div>
        <label class="admin-form-check">
            <input type="hidden" name="include_tile_material" value="0">
            <input type="checkbox" name="include_tile_material" value="1" class="tl-live-trigger" {{ $include_tile_material ? 'checked' : '' }}>
            <span>Include Tile Material Cost in Quote Total</span>
        </label>
      </div>

      <div class="fl-step-card">
        <h4>5. Mortar & Grout</h4>
        <div class="admin-form-grid-2">
            <div class="admin-form-group">
                <label class="admin-form-label">Mortar Type</label>
                <select name="mortar_type_id" class="admin-input tl-live-trigger">
                    <option value="">-- None --</option>
                    @foreach ($mortarTypes as $m)
                        <option value="{{ $m->id }}" {{ $mortar_type_id == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Trowel Size <span class="admin-text-danger">*</span></label>
                <select name="trowel_size" class="admin-input tl-live-trigger" required>
                    <option value="1/4x1/4" {{ $trowel_size === '1/4x1/4' ? 'selected' : '' }}>1/4" x 1/4" x 1/4"</option>
                    <option value="1/4x3/8" {{ $trowel_size === '1/4x3/8' ? 'selected' : '' }}>1/4" x 3/8" x 1/4"</option>
                    <option value="1/2x1/2" {{ $trowel_size === '1/2x1/2' ? 'selected' : '' }}>1/2" x 1/2" x 1/2"</option>
                </select>
            </div>
        </div>
        <div class="admin-form-grid-2">
            <div class="admin-form-group">
                <label class="admin-form-label">Grout Type</label>
                <select name="grout_type_id" class="admin-input tl-live-trigger">
                    <option value="">-- None --</option>
                    @foreach ($groutTypes as $g)
                        <option value="{{ $g->id }}" {{ $grout_type_id == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Joint Width <span class="admin-text-danger">*</span></label>
                <select name="joint_width_decimal" class="admin-input tl-live-trigger" required>
                    <option value="0.0625" {{ $joint_width_decimal == 0.0625 ? 'selected' : '' }}>1/16"</option>
                    <option value="0.125" {{ $joint_width_decimal == 0.125 ? 'selected' : '' }}>1/8"</option>
                    <option value="0.1875" {{ $joint_width_decimal == 0.1875 ? 'selected' : '' }}>3/16"</option>
                    <option value="0.25" {{ $joint_width_decimal == 0.25 ? 'selected' : '' }}>1/4"</option>
                </select>
            </div>
        </div>
      </div>
    </div>

    <aside class="pq-sticky">
      <div class="pq-sidebar-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="tl-btn-publish">
          <i class="fa fa-save"></i> Save quotation
        </button>
        <div class="pq-sidebar-actions-row">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="tl-btn-draft">Save as draft</button>
          <a href="{{ $returnToProject ? route('admin.projects.show', ['project' => $quotation->client_project_id, 'tab' => 'quotation']) : route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
        </div>
      </div>
      
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
      
      <div class="pq-summary-card">
        <h4 style="margin:0 0 10px;">Live breakdown</h4>
        <div id="tl-live-root">
          <p class="admin-form-help" id="tl-live-empty">Enter measurements to see totals.</p>
          <div id="tl-live-body" hidden></div>
        </div>
      </div>
    </aside>
    </div>
  </form>

  @if ($isEdit && ! $quotation->isLinked())
    <div class="admin-card fq-link-project-card" style="margin-top:16px;">
      <h4 style="margin:0 0 8px;">Link to project</h4>
      <p class="admin-form-help cp-form-help-flush" style="margin:0 0 12px;">
        Add this tile estimate as a line on the project <strong>Quotation</strong> tab.
      </p>
      <div class="fq-link-grid">
        <div>
          <h5>Existing project</h5>
          <form method="post" action="{{ route('admin.quotations.tile.link', $quotation) }}">
            @csrf
            @if (isset($linkableProjects) && $linkableProjects->isNotEmpty())
              <div class="fq-link-existing-row">
                @include('admin.quotations._link_project_picker', ['projects' => $linkableProjects])
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Link</button>
              </div>
            @else
              <p class="admin-form-help cp-form-help-flush">No active projects to link. Create a new one below.</p>
            @endif
          </form>
        </div>
        <div class="fq-link-col fq-link-col--stack">
          <h5>Create project &amp; link</h5>
          <form method="post" action="{{ route('admin.quotations.tile.create-project', $quotation) }}" class="fq-create-project-form">
            @csrf
            <div class="admin-form-grid-2">
              <div class="admin-form-group">
                <label for="tl-new-project-name">Project name</label>
                <input type="text" id="tl-new-project-name" name="fp_project_name" class="admin-input" maxlength="255"
                       value="{{ old('fp_project_name') }}" placeholder="Defaults to client + Tile">
              </div>
              <div class="admin-form-group">
                <label for="tl-new-client-name">Client name <span class="cp-required">*</span></label>
                <input type="text" id="tl-new-client-name" name="fp_client_name"
                       class="admin-input @error('fp_client_name') is-invalid @enderror"
                       required maxlength="255"
                       value="{{ old('fp_client_name', $quotation->client_name) }}">
                @error('fp_client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
            </div>
            <div class="fq-create-project-contact">
              <div class="admin-form-group">
                <label for="tl-new-client-email">Email</label>
                <input type="email" id="tl-new-client-email" name="fp_client_email"
                       class="admin-input @error('fp_client_email') is-invalid @enderror"
                       maxlength="255" value="{{ old('fp_client_email') }}">
                @error('fp_client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
              <div class="admin-form-group">
                <label for="tl-new-client-phone">Phone</label>
                <input type="text" id="tl-new-client-phone" name="fp_client_phone"
                       class="admin-input cp-phone-input @error('fp_client_phone') is-invalid @enderror"
                       inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx"
                       value="{{ old('fp_client_phone') ? \App\Models\ClientProject::formatPhone(old('fp_client_phone')) : '' }}">
                @error('fp_client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
              @include('admin.partials.places_address_field', [
                'inputId' => 'tl-new-client-address',
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
            <button type="submit" class="admin-btn admin-btn--primary">Create project &amp; link</button>
          </form>
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('tl-form');
    var isDraftInput = document.getElementById('tl-is-draft');
    var btnDraft = document.getElementById('tl-btn-draft');
    var liveEmpty = document.getElementById('tl-live-empty');
    var liveBody = document.getElementById('tl-live-body');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var liveTimer = null;

    if (btnDraft) {
        btnDraft.addEventListener('click', function () {
            isDraftInput.value = '1';
            form.submit();
        });
    }

    function doLiveUpdate() {
        var fd = new FormData(form);
        fd.delete('_method'); // Edit forms include @method('PUT'); must not spoof preview POST as PUT.
        var payload = new URLSearchParams(fd);

        fetch('{{ route('admin.quotations.tile.live') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.valid) {
                liveEmpty.hidden = true;
                liveBody.innerHTML = data.html;
                liveBody.hidden = false;
            } else {
                liveEmpty.hidden = false;
                liveBody.hidden = true;
            }
        })
        .catch(err => console.error('Live calc error:', err));
    }

    var triggers = form.querySelectorAll('.tl-live-trigger');
    for (var i=0; i<triggers.length; i++) {
        triggers[i].addEventListener('input', function () {
            clearTimeout(liveTimer);
            liveTimer = setTimeout(doLiveUpdate, 400);
        });
        triggers[i].addEventListener('change', function () {
            clearTimeout(liveTimer);
            doLiveUpdate();
        });
    }

    // Measurements mutual exclusion
    var totalAreaInput = document.getElementById('tl-total-area');
    var lengthInput = document.getElementById('tl-length');
    var widthInput = document.getElementById('tl-width');
    
    function toggleMeasurements() {
        if (totalAreaInput && lengthInput && widthInput) {
            if (totalAreaInput.value.trim() !== '') {
                lengthInput.disabled = true;
                widthInput.disabled = true;
                lengthInput.value = '';
                widthInput.value = '';
            } else if (lengthInput.value.trim() !== '' || widthInput.value.trim() !== '') {
                totalAreaInput.disabled = true;
                totalAreaInput.value = '';
            } else {
                totalAreaInput.disabled = false;
                lengthInput.disabled = false;
                widthInput.disabled = false;
            }
        }
    }

    if (totalAreaInput && lengthInput && widthInput) {
        totalAreaInput.addEventListener('input', toggleMeasurements);
        lengthInput.addEventListener('input', toggleMeasurements);
        widthInput.addEventListener('input', toggleMeasurements);
        toggleMeasurements();
    }
    
    // Demolition UI toggles
    var includeDemolition = document.getElementById('tl-include-demolition');
    var demolitionDetails = document.getElementById('tl-demolition-details');
    var demoComplexity = document.getElementById('tl-demolition-complexity');
    var customDemoGroup = document.getElementById('tl-custom-demo-rate-group');

    if (includeDemolition && demolitionDetails) {
        includeDemolition.addEventListener('change', function() {
            demolitionDetails.style.display = this.checked ? 'block' : 'none';
        });
    }

    if (demoComplexity && customDemoGroup) {
        demoComplexity.addEventListener('change', function() {
            customDemoGroup.style.display = this.value === 'custom' ? 'block' : 'none';
        });
    }

    // Run on load if filled
    doLiveUpdate();

    // Tile Product Preview Logic
    var tileProductSelect = document.getElementById('tile_product_id');
    var tilePreview = document.getElementById('tile-preview');

    function updateTilePreview() {
        if (!tileProductSelect) return;
        var selectedOpt = tileProductSelect.options[tileProductSelect.selectedIndex];
        if (!selectedOpt) return;

        var url = selectedOpt.getAttribute('data-url');
        var img = selectedOpt.getAttribute('data-image');

        var html = '';
        if (img) {
            html += `<div style="margin-bottom:8px;"><img src="${img}" style="max-width:150px; max-height:150px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>`;
        }
        if (url) {
            html += `<div><a href="${url}" target="_blank" class="admin-text-primary" style="font-size:14px; text-decoration:none;"><i class="fa fa-external-link"></i> View Supplier Link</a></div>`;
        }

        tilePreview.innerHTML = html;
    }

    if (tileProductSelect) {
        tileProductSelect.addEventListener('change', updateTilePreview);
        updateTilePreview();
    }
});
</script>
@endpush

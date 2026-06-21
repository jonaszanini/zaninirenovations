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

  <form action="{{ $formAction }}" method="POST" id="tl-form" class="pq-layout">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="is_draft" id="tl-is-draft" value="{{ old('is_draft', $isEdit && $quotation->is_draft ? '1' : '0') }}">
    
    @if (! $isEdit && $preservePid)
      <input type="hidden" name="client_project_id" value="{{ $preservePid }}">
    @endif

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
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label class="admin-form-label">Length (ft) <span class="admin-text-danger">*</span></label>
            <input type="number" step="0.01" name="length_ft" class="admin-input tl-live-trigger" value="{{ $length_ft }}" required>
          </div>
          <div class="admin-form-group">
            <label class="admin-form-label">Width (ft) <span class="admin-text-danger">*</span></label>
            <input type="number" step="0.01" name="width_ft" class="admin-input tl-live-trigger" value="{{ $width_ft }}" required>
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
        <h4>3. Tile Selection</h4>
        <div class="admin-form-group">
          <label class="admin-form-label">Tile Product</label>
          <select name="tile_product_id" class="admin-input tl-live-trigger">
            <option value="">-- No Tile Selected --</option>
            @foreach ($products as $p)
              <option value="{{ $p->id }}" {{ $tile_product_id == $p->id ? 'selected' : '' }}>
                {{ $p->name }} (${{ number_format($p->cost_per_sqft, 2) }}/sqft, {{ (float)$p->length_inches }}x{{ (float)$p->width_inches }}x{{ (float)$p->thickness_inches }}")
              </option>
            @endforeach
          </select>
        </div>
        <label class="admin-form-check">
            <input type="hidden" name="include_tile_material" value="0">
            <input type="checkbox" name="include_tile_material" value="1" class="tl-live-trigger" {{ $include_tile_material ? 'checked' : '' }}>
            <span>Include Tile Material Cost in Quote Total</span>
        </label>
      </div>

      <div class="fl-step-card">
        <h4>4. Mortar & Grout</h4>
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
  </form>
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

    // Run on load if filled
    doLiveUpdate();
});
</script>
@endpush

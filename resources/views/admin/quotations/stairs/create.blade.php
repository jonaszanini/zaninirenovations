@extends('admin.layouts.app')

@php
  use App\Models\StairsMaterialType;
  use App\Services\StairsQuotationCalculator;

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

  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New stairs renovation quotation';
  $formAction = ($isEdit
    ? route('admin.quotations.stairs.update', $quotation)
    : route('admin.quotations.stairs.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : StairsQuotationCalculator::defaultInputs();
  foreach (StairsQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;
  $linkProject = $linkProject ?? null;

  $segRows = old('segments');
  if ($segRows === null) {
      $segRows = is_array($inputs['segments'] ?? null) ? $inputs['segments'] : [];
  }
  if (! is_array($segRows) || count($segRows) === 0) {
      $ts = max(1, (int) old('total_steps', $inputs['total_steps'] ?? 1));
      $segRows = [['stair_type' => 'standard', 'qty' => $ts]];
  }
  $selMaterial = (int) old('material_type_id', $inputs['material_type_id'] ?? 0);
  if (! $isEdit && $selMaterial === 0 && $materialTypes->isNotEmpty()) {
      $selMaterial = (int) $materialTypes->first()->id;
  }
  $openSidesVal = old('open_sides', $inputs['open_sides'] ?? StairsQuotationCalculator::OPEN_NONE);
@endphp

@section('heading', $title)

@push('head')
  <style>
    .fq-form-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px 20px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }
    .fq-form-toolbar-right {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: flex-end;
      flex: 1 1 auto;
      min-width: 0;
    }
    @media (max-width: 720px) {
      .fq-form-toolbar { flex-direction: column; align-items: stretch; }
      .fq-form-toolbar > a:first-child { width: fit-content; }
      .fq-form-toolbar-right { justify-content: flex-start; }
    }
    .fq-linked-banner {
      display: flex;
      gap: 8px;
      align-items: center;
      background: #e8f4ea;
      color: #0f5132;
      border: 1px solid #bfddc6;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 16px;
    }
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
    .sq-step-hint { font-size: 12px; margin-top: 8px; padding: 8px 10px; border-radius: 6px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .sq-step-hint.ok { background: #ecfdf5; color: #166534; border-color: #bbf7d0; }
    .sq-material-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      margin-top: 10px;
    }
    .sq-material-card {
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      padding: 10px;
      cursor: pointer;
      transition: border-color .15s, box-shadow .15s;
      background: #fff;
    }
    .sq-material-card:has(input:checked) {
      border-color: var(--admin-primary);
      box-shadow: 0 0 0 2px var(--admin-primary-ring);
    }
    .sq-material-card img { width: 100%; height: 100px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; background: #f1f5f9; }
    .sq-material-card__name { font-weight: 600; font-size: 13px; line-height: 1.3; display: flex; flex-direction: column; gap: 2px; }
    .sq-material-card__name-line { display: block; }
    .sq-seg-row { display: grid; grid-template-columns: 1fr 110px auto; gap: 10px; align-items: end; margin-bottom: 10px; }
    @media (max-width: 600px) { .sq-seg-row { grid-template-columns: 1fr; } }
  </style>
@endpush

@section('content')
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.stairs.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    <div class="fq-form-toolbar-right">
      <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        Price configuration
      </a>
      @if ($returnToProject && $projectForReturn)
        <a href="{{ route('admin.client-projects.show', $projectForReturn) }}#quotation" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
      @endif
      <a href="{{ route('admin.quotations.stairs.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="sq-btn-draft">Save as draft</button>
      <button type="submit" form="sq-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="sq-btn-publish">
        <i class="fa fa-save"></i> Save quotation
      </button>
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
    <div class="admin-flash admin-flash--danger" style="margin-bottom:14px;">{{ $message }}</div>
  @enderror

  <div class="pq-layout">
      <div>
        <form id="sq-main-form" method="post" action="{{ $formAction }}">
          @csrf
          @if ($isEdit)
            @method('PUT')
          @endif
          <input type="hidden" name="save_intent" id="sq-save-intent" value="publish">
          @if ($linkProject)
            <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
          @endif

          <div class="admin-card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">Step 1 — Basic information</h3>
            <div class="admin-form-group">
              <label for="sq-client">Client name @if(!$isEdit || !($quotation->is_draft ?? false))<span class="cp-required">*</span>@endif</label>
              <input type="text" id="sq-client" name="client_name" class="admin-input sq-calc-in"
                     value="{{ old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? '')) }}"
                     maxlength="160" @if(!old('client_name', $isEdit ? ($quotation->client_name ?? '') : '') && (!$isEdit || !($quotation->is_draft ?? false))) required @endif>
              <p class="pq-hint">Required for a published quotation; optional for drafts.</p>
            </div>
            <div class="admin-form-group">
              <label for="sq-total-steps">Total number of steps <span class="cp-required">*</span></label>
              <input type="number" id="sq-total-steps" name="total_steps" class="admin-input sq-calc-in" required min="1" max="500" step="1"
                     value="{{ old('total_steps', $inputs['total_steps'] ?? 1) }}">
            </div>
          </div>

          <div class="admin-card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">Step 2 — Carpet removal</h3>
            <label class="admin-form-group" style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" name="carpet_removal" value="1" class="sq-calc-in" id="sq-carpet"
                @checked(old('carpet_removal', $inputs['carpet_removal'] ?? false))>
              <span>Carpet removal required (priced per step from <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>)</span>
            </label>
          </div>

          <div class="admin-card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">Step 3 — Stair configuration</h3>
            <div class="admin-form-group">
              <span class="admin-label">Open sides</span>
              <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:6px;">
                @foreach ([
                  StairsQuotationCalculator::OPEN_NONE => 'No open sides',
                  StairsQuotationCalculator::OPEN_ONE => 'One open side',
                  StairsQuotationCalculator::OPEN_TWO => 'Two open sides',
                ] as $ov => $olbl)
                  <label style="display:flex;align-items:center;gap:6px;font-weight:500;">
                    <input type="radio" name="open_sides" value="{{ $ov }}" class="sq-open-sides sq-calc-in" @checked($openSidesVal === $ov)>
                    {{ $olbl }}
                  </label>
                @endforeach
              </div>
            </div>

            <p class="pq-hint" style="margin-top:12px;">Add one row per stair section. The sum of quantities must match total steps for published quotes.</p>
            <div id="sq-segments"></div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="sq-add-seg" style="margin-top:6px;">
              <i class="fa fa-plus"></i> Add section
            </button>
            <div id="sq-step-sum" class="sq-step-hint" role="status"></div>
          </div>

          <div class="admin-card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">Step 4 — Material</h3>
            <div class="sq-material-grid">
              @foreach ($materialTypes as $mat)
                <label class="sq-material-card">
                  <input type="radio" name="material_type_id" value="{{ $mat->id }}" class="sq-material-radio sq-calc-in" style="position:absolute;opacity:0;width:0;height:0;"
                    data-nosing-mode="{{ $mat->nosing_mode }}"
                    @checked($selMaterial === (int) $mat->id)>
                  @if ($mat->photoUrl())
                    <img src="{{ $mat->photoUrl() }}" alt="">
                  @else
                    <div style="height:100px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;">No photo</div>
                  @endif
                  <div class="sq-material-card__name">
                    @foreach ($mat->adminDisplayLines() as $line)
                      <span class="sq-material-card__name-line">{{ $line }}</span>
                    @endforeach
                  </div>
                </label>
              @endforeach
            </div>

            <div id="sq-nosing-wrap" class="admin-form-group" style="margin-top:16px;display:none;">
              <label for="sq-nosing">Nosing option <span class="cp-required">*</span></label>
              <select id="sq-nosing" name="nosing_choice" class="admin-input sq-calc-in">
                <option value="">— Select —</option>
                @foreach (StairsMaterialType::nosingChoiceLabels() as $nk => $nLabel)
                  <option value="{{ $nk }}" @selected(old('nosing_choice', $inputs['nosing_choice'] ?? '') === $nk)>{{ $nLabel }}</option>
                @endforeach
              </select>
            </div>
            <p id="sq-nosing-included" class="pq-hint" style="margin-top:10px;display:none;">Nosing is included in the material rate for this option.</p>
          </div>

          <div class="admin-card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">Step 5 — Additional options</h3>
            <label class="admin-form-group" style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" name="requires_stain" value="1" class="sq-calc-in" @checked(old('requires_stain', $inputs['requires_stain'] ?? false))>
              <span>Requires stain ($/step — <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>)</span>
            </label>
            <label class="admin-form-group" style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" name="requires_clear_coat" value="1" class="sq-calc-in" @checked(old('requires_clear_coat', $inputs['requires_clear_coat'] ?? false))>
              <span>Requires clear coat protection ($/step — <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>)</span>
            </label>
          </div>
        </form>

        @if ($isEdit && ! $quotation->isLinked())
          <div class="fq-link-project-card">
            <h4 style="margin:0 0 8px;">Link to project</h4>
            <div class="fq-link-grid">
              <div>
                <h5>Existing project</h5>
                <form method="post" action="{{ route('admin.quotations.stairs.link', $quotation) }}">
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
                  <div class="admin-form-group" style="margin-top:10px;">
                    <label>Line description (optional)</label>
                    <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="{{ $quotation->summaryDescription() }}">
                  </div>
                </form>
              </div>
              <div class="fq-link-col fq-link-col--stack">
                <h5>Create project &amp; link</h5>
                <form method="post" action="{{ route('admin.quotations.stairs.create-project', $quotation) }}" class="fq-create-project-form">
                  @csrf
                  <div class="admin-form-group">
                    <label>Project name</label>
                    <input type="text" name="fp_project_name" class="admin-input" maxlength="255" placeholder="Defaults to client + Stairs">
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
                    <input type="text" name="fp_client_phone" class="admin-input" maxlength="14">
                  </div>
                  <div class="admin-form-group">
                    <label>Address</label>
                    <input type="text" name="fp_client_address" class="admin-input" maxlength="500">
                  </div>
                  <div class="admin-form-group">
                    <label>Line description (optional)</label>
                    <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="{{ $quotation->summaryDescription() }}">
                  </div>
                  <button type="submit" class="admin-btn admin-btn--primary">Create project &amp; link</button>
                </form>
              </div>
            </div>
          </div>
        @endif
      </div>

      <aside class="pq-sticky">
        <div class="pq-summary-card">
          <h4 style="margin:0 0 12px;">Live summary</h4>
          <div id="sq-live-root">
            <p class="admin-form-help">Adjust inputs to refresh totals…</p>
          </div>
          <p class="pq-hint" style="margin-top:10px;">Prices valid for quoting; HST 13% applied to subtotal (Ontario).</p>
        </div>
      </aside>
    </div>

  <script>
    (function () {
      var previewUrl = @json(route('admin.quotations.stairs.preview'));
      var token = document.querySelector('meta[name="csrf-token"]');
      var tokenV = token ? token.getAttribute('content') : '';

      var OPEN_OPTS = {
        '{{ StairsQuotationCalculator::OPEN_NONE }}': ['standard', 'winder', 'double_landing', 'single_landing'],
        '{{ StairsQuotationCalculator::OPEN_ONE }}': ['standard', 'winder', 'double_landing', 'single_landing'],
        '{{ StairsQuotationCalculator::OPEN_TWO }}': ['standard']
      };
      var TYPE_LABEL = {
        standard: 'Standard stairs',
        winder: 'Winder step',
        double_landing: 'Double landing',
        single_landing: 'Single landing'
      };

      var form = document.getElementById('sq-main-form');
      var intent = document.getElementById('sq-save-intent');
      var clientInput = document.getElementById('sq-client');
      document.getElementById('sq-btn-draft').addEventListener('click', function () {
        intent.value = 'draft';
        if (clientInput) clientInput.removeAttribute('required');
        form.requestSubmit();
      });
      document.getElementById('sq-btn-publish').addEventListener('click', function () {
        intent.value = 'publish';
        if (clientInput) clientInput.setAttribute('required', 'required');
      });

      function currentOpenSides() {
        var r = form.querySelector('.sq-open-sides:checked');
        return r ? r.value : '{{ StairsQuotationCalculator::OPEN_NONE }}';
      }

      function allowedTypes() {
        return OPEN_OPTS[currentOpenSides()] || OPEN_OPTS['{{ StairsQuotationCalculator::OPEN_NONE }}'];
      }

      var segHost = document.getElementById('sq-segments');
      var segIdx = 0;

      function buildStairTypeSelect(selected) {
        var sel = document.createElement('select');
        sel.name = 'segments[' + segIdx + '][stair_type]';
        sel.className = 'admin-input sq-seg-type sq-calc-in';
        var allowed = allowedTypes();
        allowed.forEach(function (t) {
          var o = document.createElement('option');
          o.value = t;
          o.textContent = TYPE_LABEL[t] || t;
          if (t === selected) o.selected = true;
          sel.appendChild(o);
        });
        return sel;
      }

      function addSegmentRow(stairType, qty) {
        var row = document.createElement('div');
        row.className = 'sq-seg-row';
        var st = stairType && allowedTypes().indexOf(stairType) >= 0 ? stairType : allowedTypes()[0];
        var typeSel = buildStairTypeSelect(st);
        var qtyIn = document.createElement('input');
        qtyIn.type = 'number';
        qtyIn.name = 'segments[' + segIdx + '][qty]';
        qtyIn.className = 'admin-input sq-seg-qty sq-calc-in';
        qtyIn.min = '0';
        qtyIn.max = '500';
        qtyIn.step = '1';
        qtyIn.value = String(qty != null ? qty : 0);
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'admin-btn admin-btn--ghost admin-btn--sm sq-remove-seg';
        rm.innerHTML = '<i class="fa fa-times"></i>';
        rm.addEventListener('click', function () {
          row.remove();
          schedule();
          syncStepHint();
        });
        typeSel.addEventListener('change', schedule);
        qtyIn.addEventListener('input', function () { schedule(); syncStepHint(); });
        row.appendChild(typeSel);
        row.appendChild(qtyIn);
        row.appendChild(rm);
        segHost.appendChild(row);
        segIdx += 1;
      }

      var initialSegs = @json($segRows);
      if (Array.isArray(initialSegs) && initialSegs.length) {
        initialSegs.forEach(function (s) {
          addSegmentRow(s.stair_type || 'standard', s.qty);
        });
      } else {
        addSegmentRow('standard', 1);
      }

      document.getElementById('sq-add-seg').addEventListener('click', function () {
        addSegmentRow(allowedTypes()[0], 0);
        schedule();
        syncStepHint();
      });

      form.querySelectorAll('.sq-open-sides').forEach(function (r) {
        r.addEventListener('change', function () {
          segHost.querySelectorAll('.sq-seg-type').forEach(function (sel) {
            var v = sel.value;
            var al = allowedTypes();
            sel.innerHTML = '';
            al.forEach(function (t) {
              var o = document.createElement('option');
              o.value = t;
              o.textContent = TYPE_LABEL[t] || t;
              sel.appendChild(o);
            });
            sel.value = al.indexOf(v) >= 0 ? v : al[0];
          });
          schedule();
          syncStepHint();
        });
      });

      function syncStepHint() {
        var total = parseInt(document.getElementById('sq-total-steps').value, 10) || 0;
        var sum = 0;
        segHost.querySelectorAll('.sq-seg-qty').forEach(function (inp) {
          sum += parseInt(inp.value, 10) || 0;
        });
        var el = document.getElementById('sq-step-sum');
        if (!el) return;
        if (sum === total && total > 0) {
          el.textContent = 'Step quantities match total (' + sum + ').';
          el.className = 'sq-step-hint ok';
        } else {
          el.textContent = 'Segments total ' + sum + ' — must match total steps ' + total + ' when you publish.';
          el.className = 'sq-step-hint';
        }
      }
      document.getElementById('sq-total-steps').addEventListener('input', function () { schedule(); syncStepHint(); });
      syncStepHint();

      var nosingWrap = document.getElementById('sq-nosing-wrap');
      var nosingSelect = document.getElementById('sq-nosing');
      var nosingIncluded = document.getElementById('sq-nosing-included');
      function syncNosingUi() {
        var r = form.querySelector('.sq-material-radio:checked');
        var mode = r ? r.getAttribute('data-nosing-mode') : '';
        if (mode === '{{ StairsMaterialType::NOSING_CHOICE }}') {
          nosingWrap.style.display = '';
          nosingIncluded.style.display = 'none';
          nosingSelect.setAttribute('required', 'required');
        } else {
          nosingWrap.style.display = 'none';
          nosingIncluded.style.display = r ? 'block' : 'none';
          nosingSelect.removeAttribute('required');
        }
      }
      form.querySelectorAll('.sq-material-radio').forEach(function (radio) {
        radio.addEventListener('change', function () { syncNosingUi(); schedule(); });
      });
      syncNosingUi();

      var root = document.getElementById('sq-live-root');
      var t = null;
      function fmt(n) {
        return '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function render(c) {
        if (!c || !c.summary) return;
        var s = c.summary;
        var html = '';
        (c.breakdown || []).forEach(function (row) {
          html += '<div class="pq-row"><span>' + row.label + '<br><small style="color:#64748b;font-weight:400">' + (row.detail || '') + '</small></span><strong>' + fmt(row.amount) + '</strong></div>';
        });
        html += '<div class="pq-row"><span>Subtotal (pre-tax)</span><strong>' + fmt(s.subtotal_pre_hst) + '</strong></div>';
        html += '<div class="pq-row"><span>HST (' + Math.round((s.hst_rate || 0.13) * 100) + '%)</span><strong>' + fmt(s.hst) + '</strong></div>';
        html += '<div class="pq-row pq-row--hl"><span>Grand total</span><strong>' + fmt(s.grand_total) + '</strong></div>';
        root.innerHTML = html;
      }
      function runPreview() {
        var fd = new FormData(form);
        fetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': tokenV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(render)
          .catch(function () { root.innerHTML = '<p class="admin-form-help">Could not refresh preview.</p>'; });
      }
      function schedule() {
        clearTimeout(t);
        t = setTimeout(runPreview, 220);
      }
      form.addEventListener('input', schedule);
      form.addEventListener('change', schedule);

      var initial = @json($initialCalc);
      if (initial) { render(initial); } else { runPreview(); }
    })();
  </script>
@endsection

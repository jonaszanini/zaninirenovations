@extends('admin.layouts.app')

@php
  use App\Models\StairsMaterialType;
  use App\Services\StairsQuotationCalculator;

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

  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New stairs renovation quotation';
  $formAction = ($isEdit
    ? route('admin.quotations.stairs.update', $quotation)
    : route('admin.quotations.stairs.store')).$formQuery;
  $roomAreaImport = $roomAreaImport ?? null;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : StairsQuotationCalculator::defaultInputs();
  foreach (StairsQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  if (! $isEdit && ! empty($roomAreaImport['inputs']) && is_array($roomAreaImport['inputs'])) {
      $inputs = array_merge($inputs, $roomAreaImport['inputs']);
  }
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;
  $linkProject = $linkProject ?? null;

  $segRows = old('segments');
  if ($segRows === null) {
      $segRows = is_array($inputs['segments'] ?? null) ? $inputs['segments'] : [];
  }
  if (! is_array($segRows) || count($segRows) === 0) {
      $ts = max(1, (int) old('total_steps', $inputs['total_steps'] ?? 1));
      $segRows = [['stair_type' => 'standard', 'qty' => $ts, 'open_sides' => StairsQuotationCalculator::OPEN_NONE]];
  }
  $legacyOpenForSeg = $inputs['open_sides'] ?? StairsQuotationCalculator::OPEN_NONE;
  if (! in_array($legacyOpenForSeg, [
      StairsQuotationCalculator::OPEN_NONE,
      StairsQuotationCalculator::OPEN_ONE,
      StairsQuotationCalculator::OPEN_TWO,
  ], true)) {
      $legacyOpenForSeg = StairsQuotationCalculator::OPEN_NONE;
  }
  if (is_array($segRows)) {
      foreach ($segRows as $i => $row) {
          if (! is_array($row)) {
              continue;
          }
          $os = $row['open_sides'] ?? null;
          if (! is_string($os) || ! in_array($os, [
              StairsQuotationCalculator::OPEN_NONE,
              StairsQuotationCalculator::OPEN_ONE,
              StairsQuotationCalculator::OPEN_TWO,
          ], true)) {
              $segRows[$i]['open_sides'] = $legacyOpenForSeg;
          }
      }
  }
  $selMaterial = (int) old('material_type_id', $inputs['material_type_id'] ?? 0);
  if (! $isEdit && $selMaterial === 0 && $materialTypes->isNotEmpty()) {
      $selMaterial = (int) $materialTypes->first()->id;
  }
  $vinylWithoutNosingId = (int) ($materialTypes->firstWhere('key', StairsMaterialType::KEY_VINYL_WITHOUT_NOSING)?->id ?? 0);
  if ($vinylWithoutNosingId> 0 && $selMaterial === $vinylWithoutNosingId && is_array($segRows)) {
      foreach ($segRows as $i => $row) {
          if (is_array($segRows[$i])) {
              $segRows[$i]['open_sides'] = StairsQuotationCalculator::OPEN_NONE;
          }
      }
  }
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
      .fq-form-toolbar> a:first-child { width: fit-content; }
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
    .sq-material-card img {
      width: 100%;
      height: 100px;
      object-fit: cover;
      border-radius: 6px;
      margin-bottom: 8px;
      background: #f1f5f9;
    }
    .sq-material-card:has(.sq-picker-badge) { position: relative; padding-top: 28px; }
    .sq-picker-badge {
      position: absolute;
      top: 8px;
      left: 8px;
      z-index: 1;
      min-width: 1.6em;
      height: 1.6em;
      padding: 0 6px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      background: #024768;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      line-height: 1;
    }
    .sq-material-card__name { font-weight: 600; font-size: 13px; line-height: 1.3; display: flex; flex-direction: column; gap: 2px; }
    .sq-material-card__name-line { display: block; }
    .sq-seg-row { display: grid; grid-template-columns: minmax(130px, 1fr) minmax(150px, 1.35fr) 100px auto; gap: 10px; align-items: end; margin-bottom: 10px; }
    .sq-seg-row.sq-seg-row--no-open-variants { grid-template-columns: minmax(150px, 1.35fr) 100px auto; }
    .sq-seg-row.sq-seg-row--no-open-variants .sq-seg-open-wrap { display: none; }
    @media (max-width: 600px) { .sq-seg-row { grid-template-columns: 1fr; } }
  </style>
@endpush

@section('content')
  @include('admin.partials.room_area_prefill_banner')
  @include('admin.partials.quotation_editor_header', [
    'title' => $title,
    'typeLabel' => 'Stairs',
    'project' => $projectForReturn,
    'clientName' => old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name),
    'listUrl' => route('admin.quotations.stairs.index', $preservePid ? ['project' => $preservePid] : []),
    'cancelUrl' => route('admin.quotations.stairs.index', $preservePid ? ['project' => $preservePid] : []),
    'priceConfigUrl' => route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []),
    'formId' => 'sq-main-form',
    'saveId' => 'sq-btn-publish',
    'draftId' => 'sq-btn-draft',
    'draftHotkey' => true,
  ])
  @include('admin.partials.quotation_editor_more_actions', [
    'isEdit' => $isEdit,
    'quotation' => $quotation ?? null,
    'linkedCpq' => $linkedCpq ?? null,
  ])

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
        <form id="sq-main-form" method="post" action="{{ $formAction }}" data-quotation-editor data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}">
          @csrf
          @if ($isEdit)
            @method('PUT')
          @endif
          <input type="hidden" name="save_intent" id="sq-save-intent" value="publish">
          @if ($linkProject)
            <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
          @endif

          <div class="admin-card">
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

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Step 2 — Carpet removal</h3>
            <label class="admin-checkbox admin-checkbox--block">
              <input type="checkbox" name="carpet_removal" value="1" class="admin-checkbox__input sq-calc-in" id="sq-carpet"
                @checked(old('carpet_removal', $inputs['carpet_removal'] ?? false))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Carpet removal required (priced per step from <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>)</span>
            </label>
          </div>

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Step 3 — Stair configuration</h3>
            <p class="pq-hint" style="margin-top:0;" id="sq-seg-hint">Add one row per stair section. Choose open sides and stair type for each section. The sum of quantities must match total steps for published quotes.</p>
            <div id="sq-segments"></div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="sq-add-seg" style="margin-top:6px;">
              <i class="fa fa-plus"></i> Add section
            </button>
            <div id="sq-step-sum" class="sq-step-hint" role="status"></div>
          </div>

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Step 4 — Material</h3>
            <div class="sq-material-grid">
              @foreach ($materialTypes as $mat)
                <label class="sq-material-card">
                  <input type="radio" name="material_type_id" value="{{ $mat->id }}" class="sq-material-radio sq-calc-in" style="position:absolute;opacity:0;width:0;height:0;"
                    data-nosing-mode="{{ $mat->nosing_mode }}"
                    data-material-key="{{ $mat->key }}"
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

            <div id="sq-nosing-wrap" style="margin-top:16px;display:none;">
              <span class="admin-label">Nosing option <span class="cp-required">*</span></span>
              <div class="sq-material-grid">
                @foreach (StairsMaterialType::nosingChoiceLabels() as $nk => $nLabel)
                  <label class="sq-material-card">
                    <span class="sq-picker-badge">#{{ $loop->iteration }}</span>
                    <input type="radio" name="nosing_choice" value="{{ $nk }}" class="sq-nosing-radio sq-calc-in" style="position:absolute;opacity:0;width:0;height:0;"
                      @checked(old('nosing_choice', $inputs['nosing_choice'] ?? '') === $nk)>
                    @if ($settings->nosingOptionPhotoUrl($nk))
                      <img src="{{ $settings->nosingOptionPhotoUrl($nk) }}" alt="">
                    @else
                      <div style="height:100px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;">No photo</div>
                    @endif
                    <div class="sq-material-card__name">
                      <span class="sq-material-card__name-line">{{ $nLabel }}</span>
                    </div>
                  </label>
                @endforeach
              </div>
              <p class="pq-hint" style="margin-top:8px;">Configure photos under <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>.</p>
            </div>
            <p id="sq-nosing-included" class="pq-hint" style="margin-top:10px;display:none;">Nosing is included in the material rate for this option.</p>
          </div>

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Step 5 — Materials (optional)</h3>
            <div class="admin-form-group">
              <label for="sq-material-price">Materials price (pre-tax)</label>
              <input type="text" id="sq-material-price" name="material_price" class="admin-input sq-calc-in cp-money-input" inputmode="decimal" autocomplete="off"
                     value="{{ old('material_price', number_format((float) ($inputs['material_price'] ?? 0), 2, '.', '')) }}" data-cp-money-entry="direct">
              <p class="pq-hint">Leave at 0.00 when quoting <strong>labour only</strong>. Enter an amount when you are also supplying materials — the client PDF will say “material and labour”.</p>
            </div>
          </div>

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Step 6 — Additional options</h3>
            <label class="admin-checkbox admin-checkbox--block">
              <input type="checkbox" name="requires_stain" value="1" class="admin-checkbox__input sq-calc-in" @checked(old('requires_stain', $inputs['requires_stain'] ?? false))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Requires stain ($/step — <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>)</span>
            </label>
            <label class="admin-checkbox admin-checkbox--block">
              <input type="checkbox" name="requires_clear_coat" value="1" class="admin-checkbox__input sq-calc-in" @checked(old('requires_clear_coat', $inputs['requires_clear_coat'] ?? false))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Requires clear coat protection ($/step — <a href="{{ route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}">Price configuration</a>)</span>
            </label>
          </div>

          @include('admin.quotations._commute_debris_row')
          @include('admin.quotations._additional_costs', ['quotation' => $quotation])
          @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
        </form>

        @if ($isEdit && ! $quotation->isLinked())
          @include('admin.quotations._link_to_project_card', [
              'quotation' => $quotation,
              'linkUrl' => route('admin.quotations.stairs.link', $quotation),
              'createProjectUrl' => route('admin.quotations.stairs.create-project', $quotation),
              'idPrefix' => 'st',
              'projectNamePlaceholder' => 'Defaults to client + Stairs',
              'linkableProjects' => $linkableProjects,
              'createLineDescription' => 'field',
              'validateCreateProject' => false,
          ])
        @endif
      </div>

      <aside class="pq-sticky">
        @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
        @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'sq-main-form'])
        @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'sq-main-form'])
        
        <div class="pq-summary-card">
          <div class="qh-priced-head">
            <h4>Live summary</h4>
            @include('admin.partials.quotation_price_config_gear', [
              'url' => route('admin.quotations.stairs.price-configuration', $preservePid ? ['project' => $preservePid] : []),
            ])
          </div>
          <div id="sq-live-root">
            <p class="admin-form-help">Adjust inputs to refresh totals…</p>
          </div>
          <p class="pq-hint cp-form-group-spaced">Prices valid for quoting; HST 13% applied to subtotal (Ontario).</p>
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
      var OPEN_NONE = '{{ StairsQuotationCalculator::OPEN_NONE }}';
      var KEY_VINYL_WITHOUT_NOSING = '{{ StairsMaterialType::KEY_VINYL_WITHOUT_NOSING }}';
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

      var segHost = document.getElementById('sq-segments');
      var segHint = document.getElementById('sq-seg-hint');
      var segIdx = 0;

      function selectedMaterialKey() {
        var r = form.querySelector('.sq-material-radio:checked');
        return r ? r.getAttribute('data-material-key') : '';
      }

      function materialAllowsOpenSideVariants() {
        return selectedMaterialKey() !== KEY_VINYL_WITHOUT_NOSING;
      }

      function openSideSelectOptions() {
        var opts = [[OPEN_NONE, 'No open sides']];
        if (materialAllowsOpenSideVariants()) {
          opts.push(['{{ StairsQuotationCalculator::OPEN_ONE }}', 'One open side']);
          opts.push(['{{ StairsQuotationCalculator::OPEN_TWO }}', 'Two open sides']);
        }
        return opts;
      }

      function allowedTypesForOpen(openVal) {
        return OPEN_OPTS[openVal] || OPEN_OPTS[OPEN_NONE];
      }

      function rebuildStairTypeOptions(typeSel, openVal, preferredType) {
        var al = allowedTypesForOpen(openVal);
        var v = preferredType != null ? preferredType : typeSel.value;
        typeSel.innerHTML = '';
        al.forEach(function (t) {
          var o = document.createElement('option');
          o.value = t;
          o.textContent = TYPE_LABEL[t] || t;
          typeSel.appendChild(o);
        });
        typeSel.value = al.indexOf(v)>= 0 ? v : al[0];
      }

      function fillOpenSidesSelect(sel, selected) {
        var allowed = openSideSelectOptions().map(function (o) { return o[0]; });
        var val = allowed.indexOf(selected)>= 0 ? selected : OPEN_NONE;
        sel.innerHTML = '';
        openSideSelectOptions().forEach(function (opt) {
          var o = document.createElement('option');
          o.value = opt[0];
          o.textContent = opt[1];
          if (opt[0] === val) o.selected = true;
          sel.appendChild(o);
        });
        return val;
      }

      function buildOpenSidesSelect(selected) {
        var sel = document.createElement('select');
        sel.name = 'segments[' + segIdx + '][open_sides]';
        sel.className = 'admin-input sq-seg-open sq-calc-in';
        fillOpenSidesSelect(sel, selected || OPEN_NONE);
        return sel;
      }

      function buildStairTypeSelect(selectedType, openVal) {
        var sel = document.createElement('select');
        sel.name = 'segments[' + segIdx + '][stair_type]';
        sel.className = 'admin-input sq-seg-type sq-calc-in';
        rebuildStairTypeOptions(sel, openVal, selectedType);
        return sel;
      }

      function syncSegmentRowsForMaterial() {
        var allowOpen = materialAllowsOpenSideVariants();
        if (segHint) {
          segHint.textContent = allowOpen
            ? 'Add one row per stair section. Choose open sides and stair type for each section. The sum of quantities must match total steps for published quotes.'
            : 'Add one row per stair section. Choose stair type for each section (Vinyl without nosing uses no-open-side rates only). The sum of quantities must match total steps for published quotes.';
        }
        segHost.querySelectorAll('.sq-seg-row').forEach(function (row) {
          row.classList.toggle('sq-seg-row--no-open-variants', !allowOpen);
          var openSel = row.querySelector('.sq-seg-open');
          var typeSel = row.querySelector('.sq-seg-type');
          if (!openSel || !typeSel) return;
          var openVal = fillOpenSidesSelect(openSel, allowOpen ? openSel.value : OPEN_NONE);
          rebuildStairTypeOptions(typeSel, openVal, typeSel.value);
        });
        schedule();
        syncStepHint();
      }

      function addSegmentRow(stairType, qty, openSides) {
        var openVal = openSides || OPEN_NONE;
        if (!OPEN_OPTS[openVal]) openVal = OPEN_NONE;
        if (!materialAllowsOpenSideVariants()) openVal = OPEN_NONE;
        var row = document.createElement('div');
        row.className = 'sq-seg-row' + (materialAllowsOpenSideVariants() ? '' : ' sq-seg-row--no-open-variants');
        var openWrap = document.createElement('div');
        openWrap.className = 'sq-seg-open-wrap';
        var openSel = buildOpenSidesSelect(openVal);
        openWrap.appendChild(openSel);
        var al = allowedTypesForOpen(openVal);
        var st = stairType && al.indexOf(stairType)>= 0 ? stairType : al[0];
        var typeSel = buildStairTypeSelect(st, openVal);
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
        openSel.addEventListener('change', function () {
          rebuildStairTypeOptions(typeSel, openSel.value, typeSel.value);
          schedule();
          syncStepHint();
        });
        typeSel.addEventListener('change', schedule);
        qtyIn.addEventListener('input', function () { schedule(); syncStepHint(); });
        row.appendChild(openWrap);
        row.appendChild(typeSel);
        row.appendChild(qtyIn);
        row.appendChild(rm);
        segHost.appendChild(row);
        segIdx += 1;
      }

      var initialSegs = @json($segRows);
      if (Array.isArray(initialSegs) && initialSegs.length) {
        initialSegs.forEach(function (s) {
          addSegmentRow(s.stair_type || 'standard', s.qty, s.open_sides || OPEN_NONE);
        });
      } else {
        addSegmentRow('standard', 1, OPEN_NONE);
      }

      document.getElementById('sq-add-seg').addEventListener('click', function () {
        addSegmentRow(allowedTypesForOpen(OPEN_NONE)[0], 0, OPEN_NONE);
        schedule();
        syncStepHint();
      });

      function syncStepHint() {
        var total = parseInt(document.getElementById('sq-total-steps').value, 10) || 0;
        var sum = 0;
        segHost.querySelectorAll('.sq-seg-qty').forEach(function (inp) {
          sum += parseInt(inp.value, 10) || 0;
        });
        var el = document.getElementById('sq-step-sum');
        if (!el) return;
        if (sum === total && total> 0) {
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
      var nosingIncluded = document.getElementById('sq-nosing-included');
      function syncNosingUi() {
        var r = form.querySelector('.sq-material-radio:checked');
        var mode = r ? r.getAttribute('data-nosing-mode') : '';
        if (mode === '{{ StairsMaterialType::NOSING_CHOICE }}') {
          nosingWrap.style.display = '';
          nosingIncluded.style.display = 'none';
        } else {
          nosingWrap.style.display = 'none';
          nosingIncluded.style.display = r ? 'block' : 'none';
        }
      }
      form.querySelectorAll('.sq-material-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
          syncNosingUi();
          syncSegmentRowsForMaterial();
          schedule();
        });
      });
      syncNosingUi();
      syncSegmentRowsForMaterial();

      var root = document.getElementById('sq-live-root');
      var t = null;
      var lastCommuteAmount = 0;
      var lastDebrisAmount = 0;
      var lastAdditionalMaterialsPreTax = 0;
      var lastAdditionalMaterialsHst = 0;
      var lastAdditionalLaborPreTax = 0;
      var lastAdditionalLaborHst = 0;
      var lastAdditionalTaxIn = 0;
      var lastPreviewCalc = null;
      function fmt(n) {
        return '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function render(c) {
        if (!c || !c.summary) return;
        lastPreviewCalc = c;
        var s = c.summary;
        var grandTotal = Number(s.grand_total || 0) + lastCommuteAmount + lastDebrisAmount + lastAdditionalTaxIn;
        window.__quoteGrandTotal = grandTotal;
        document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: grandTotal } }));
        var html = '';
        (c.breakdown || []).forEach(function (row) {
          html += '<div class="pq-row"><span>' + row.label + '<br><small style="color:#64748b;font-weight:400">' + (row.detail || '') + '</small></span><strong>' + fmt(row.amount) + '</strong></div>';
        });
        if (lastAdditionalLaborPreTax > 0 || lastAdditionalMaterialsPreTax > 0) {
          if (lastAdditionalLaborPreTax > 0) {
            html += '<div class="pq-row"><span>Additional costs (labour)<br><small style="color:#64748b;font-weight:400">Admin only — folded into labour on client PDF</small></span><strong>' + fmt(lastAdditionalLaborPreTax) + '</strong></div>';
          }
          if (lastAdditionalMaterialsPreTax > 0) {
            html += '<div class="pq-row"><span>Additional costs (materials)<br><small style="color:#64748b;font-weight:400">Admin only — folded into materials on client PDF</small></span><strong>' + fmt(lastAdditionalMaterialsPreTax) + '</strong></div>';
          }
        }
        html += '<div class="pq-row"><span>Subtotal (pre-tax)</span><strong>' + fmt(Number(s.subtotal_pre_hst || 0) + lastAdditionalLaborPreTax + lastAdditionalMaterialsPreTax) + '</strong></div>';
        html += '<div class="pq-row"><span>HST (' + Math.round((s.hst_rate || 0.13) * 100) + '%)</span><strong>' + fmt(Number(s.hst || 0) + lastAdditionalLaborHst + lastAdditionalMaterialsHst) + '</strong></div>';
        if (lastCommuteAmount> 0) {
          html += '<div class="pq-row"><span>Commute expense (incl. HST)</span><strong>' + fmt(lastCommuteAmount) + '</strong></div>';
        }
        if (lastDebrisAmount > 0) {
          html += '<div class="pq-row"><span>Debris removal (incl. HST)</span><strong>' + fmt(lastDebrisAmount) + '</strong></div>';
        }
        html += '<div class="pq-row pq-row--hl"><span>Grand total</span><strong>' + fmt(grandTotal) + '</strong></div>';
        root.innerHTML = html;
      }
      function runPreview() {
        var fd = new FormData(form);
        // Edit forms include @method('PUT'); must not spoof preview POST as PUT.
        fd.delete('_method');
        fetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': tokenV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) {
            if (!r.ok) throw new Error('preview ' + r.status);
            return r.json();
          })
          .then(render)
          .catch(function () { root.innerHTML = '<p class="admin-form-help">Could not refresh preview.</p>'; });
      }
      function schedule() {
        clearTimeout(t);
        t = setTimeout(runPreview, 220);
      }
      form.addEventListener('input', schedule);
      form.addEventListener('change', schedule);
      var sqCarpet = document.getElementById('sq-carpet');
      if (sqCarpet) sqCarpet.addEventListener('change', schedule);

      var initial = @json($initialCalc);
      if (initial) { render(initial); } else { runPreview(); }

      document.addEventListener('commute-expense:change', function (e) {
        var d = e.detail || {};
        lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
      document.addEventListener('debris-removal:change', function (e) {
        var d = e.detail || {};
        lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
      document.addEventListener('additional-costs:change', function (e) {
        var d = e.detail || {};
        lastAdditionalMaterialsPreTax = Number(d.materialsPreTax || 0);
        lastAdditionalMaterialsHst = Number(d.materialsHst || 0);
        lastAdditionalLaborPreTax = Number(d.laborPreTax || 0);
        lastAdditionalLaborHst = Number(d.laborHst || 0);
        lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
        if (lastPreviewCalc) render(lastPreviewCalc);
      });
    })();
  </script>
@endsection

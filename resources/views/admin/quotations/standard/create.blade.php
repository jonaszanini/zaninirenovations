@extends('admin.layouts.app')

@php
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit standard quotation '.$quotation->quote_number : 'New standard quotation';
  $formAction = $isEdit
    ? route('admin.quotations.standard.update', $quotation)
    : route('admin.quotations.standard.store');
  $formMethod = $isEdit ? 'PUT' : 'POST';

  $matRows = old('materials');
  if ($matRows === null && $isEdit) {
      $matRows = is_array($quotation->materials) ? $quotation->materials : [];
  }
  if (!is_array($matRows) || count($matRows) === 0) {
      $matRows = [['short_name' => '', 'description' => '', 'unit_price' => '', 'quantity' => '1', 'apply_hst' => true]];
  }

  $labourMode = old('labour_mode', $isEdit ? $quotation->labour_mode : 'hours');
  $labourQty = old('labour_quantity', $isEdit ? (string) $quotation->labour_quantity : '');
  $labourRate = old('labour_hourly_rate', $isEdit ? number_format((float) $quotation->labour_hourly_rate, 2, '.', '') : '65.00');

  // Contingency allowance defaults: enabled with 10% on new quotes; for
  // existing quotes use whatever was saved (column was added with default
  // true / 10.00 so older rows still render sensibly).
  if (old('contingency_enabled') !== null) {
      $contingencyEnabled = filter_var(old('contingency_enabled'), FILTER_VALIDATE_BOOLEAN);
  } elseif ($isEdit) {
      $contingencyEnabled = (bool) $quotation->contingency_enabled;
  } else {
      $contingencyEnabled = true;
  }
  $contingencyPercent = old('contingency_percent');
  if ($contingencyPercent === null || $contingencyPercent === '') {
      $contingencyPercent = $isEdit
          ? rtrim(rtrim(number_format((float) $quotation->contingency_percent, 2, '.', ''), '0'), '.')
          : '10';
      if ($contingencyPercent === '') {
          $contingencyPercent = '10';
      }
  }
@endphp

@section('heading', $title)

@section('content')
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.standard.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All standard quotations
      </a>
      <a href="{{ route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-th"></i> Quotation types
      </a>
    </div>
  </div>

  @if (session('success'))
    <div class="admin-flash admin-flash--success" style="margin-bottom:16px;">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="admin-flash admin-flash--danger" style="margin-bottom:16px;">{{ session('error') }}</div>
  @endif

  @if (session('standard_flash') === 'saved' || session('standard_flash') === 'linked')
    <div class="admin-card sq-success-card">
      <h4 style="margin:0 0 8px;">Next steps</h4>
      <div class="sq-success-actions">
        <a href="{{ route('admin.quotations.standard.create') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-plus"></i> Create another standard quotation
        </a>
        <a href="{{ route('admin.quotations.standard.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-list"></i> View all saved quotations
        </a>
        @if (session('standard_linked_project_id'))
          <a href="{{ route('admin.client-projects.show', session('standard_linked_project_id')) }}#quotation"
             class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-briefcase"></i> Open linked project
          </a>
        @endif
      </div>
    </div>
  @endif

  <form action="{{ $formAction }}" method="post" id="sq-standard-form">
    @csrf
    @if ($isEdit)
      @method('PUT')
    @endif

    <div class="admin-card sq-intro-card">
      <h3 style="margin:0 0 8px;">Client</h3>
      <div class="admin-form-group" style="max-width:480px;">
        <label for="sq-client-name">Client name <span class="cp-required">*</span></label>
        <input type="text" id="sq-client-name" name="client_name" class="admin-input @error('client_name') is-invalid @enderror"
               maxlength="160" required
               value="{{ old('client_name', $isEdit ? $quotation->client_name : '') }}"
               placeholder="e.g. Jane Smith">
        @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      {{-- Free-form description of the work/service. Surfaced on the
           project quotation PDF (folded into the priced line item) so
           the client sees what's covered by this standard quotation. --}}
      <div class="admin-form-group" style="margin-top:18px;">
        <label for="sq-work-description">Description of the work</label>
        <textarea id="sq-work-description" name="work_description" rows="5" maxlength="5000"
                  class="admin-input @error('work_description') is-invalid @enderror"
                  placeholder="Briefly describe the service to be performed (visible to the client on the quotation PDF).">{{ old('work_description', $isEdit ? $quotation->work_description : '') }}</textarea>
        <p class="admin-form-help">Shown on the client's quotation PDF, beneath the priced line item for this standard quotation. Plain text — line breaks are preserved.</p>
        @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="sq-layout">
      {{-- Materials ------------------------------------------------ --}}
      <div class="admin-card sq-panel">
        <div class="sq-panel-head">
          <h3 style="margin:0;">Materials</h3>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="sq-add-material">
            <i class="fa fa-plus"></i> Add material
          </button>
        </div>
        <p class="admin-form-help">Unit prices are <strong>before HST</strong>. Per-row HST (13%) is optional (on by default).</p>

        {{-- Each material is rendered as a small two-line "form card" so
             the inputs stop getting squeezed on narrow viewports. Line 1
             holds the editable fields (short name, description, unit
             price, qty); line 2 holds the live-computed values (subtotal,
             HST, total) plus the per-row HST toggle. The delete button
             sits at the far right and is vertically centred across both
             lines via grid alignment. --}}
        <div class="sq-materials-list" id="sq-materials-list">
          @foreach ($matRows as $i => $row)
            @php
              $apply = array_key_exists('apply_hst', $row) ? filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN) : true;
            @endphp
            <div class="sq-material-card sq-material-row">
              <div class="sq-mat-fields">
                <div class="sq-mat-row sq-mat-row--inputs">
                  <div class="sq-mat-field sq-mat-field--short-name">
                    <label>Short name</label>
                    <input type="text" name="materials[{{ $i }}][short_name]" class="admin-input sq-mat-short-name"
                           maxlength="60" placeholder="e.g. Paint, Primer"
                           value="{{ $row['short_name'] ?? '' }}">
                  </div>
                  <div class="sq-mat-field sq-mat-field--description">
                    <label>Description</label>
                    <input type="text" name="materials[{{ $i }}][description]" class="admin-input sq-mat-desc"
                           maxlength="500" placeholder="Material or item"
                           value="{{ $row['description'] ?? '' }}">
                  </div>
                  <div class="sq-mat-field sq-mat-field--unit">
                    <label>Unit price ($)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" name="materials[{{ $i }}][unit_price]"
                           class="admin-input cp-money-input sq-mat-unit" placeholder="0.00"
                           value="{{ isset($row['unit_price']) && $row['unit_price'] !== '' ? number_format((float) $row['unit_price'], 2, '.', '') : '' }}">
                  </div>
                  <div class="sq-mat-field sq-mat-field--qty">
                    <label>Qty</label>
                    {{-- type=text with inputmode=decimal: numeric keyboard
                         on mobile while letting JS strip non-numeric input.
                         Allows decimals (e.g. 1.5). --}}
                    <input type="text" inputmode="decimal" autocomplete="off"
                           name="materials[{{ $i }}][quantity]"
                           class="admin-input sq-mat-qty" placeholder="1"
                           value="{{ $row['quantity'] ?? '' }}">
                  </div>
                </div>

                <div class="sq-mat-row sq-mat-row--computed">
                  <div class="sq-mat-field sq-mat-field--readonly">
                    <label>Subtotal</label>
                    <div class="sq-mat-readout"><span class="sq-mat-sub">—</span></div>
                  </div>
                  <div class="sq-mat-field sq-mat-field--readonly">
                    <label>HST (13%)</label>
                    <div class="sq-mat-readout"><span class="sq-mat-hst">—</span></div>
                  </div>
                  <div class="sq-mat-field sq-mat-field--readonly">
                    <label>Total w/ HST</label>
                    <div class="sq-mat-readout"><span class="sq-mat-total">—</span></div>
                  </div>
                  <div class="sq-mat-field sq-mat-field--apply">
                    <label>HST</label>
                    <input type="hidden" name="materials[{{ $i }}][apply_hst]" value="0">
                    <label class="sq-checkbox-label sq-mat-apply-label">
                      <input type="checkbox" name="materials[{{ $i }}][apply_hst]" value="1" class="sq-mat-hst-cb" @checked($apply)>
                      <span>Apply</span>
                    </label>
                  </div>
                </div>
              </div>

              <div class="sq-mat-actions">
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm sq-remove-material" aria-label="Remove row" title="Remove material">
                  <i class="fa fa-trash"></i>
                </button>
              </div>
            </div>
          @endforeach
        </div>

        {{-- Contingency Allowance ---------------------------------- --}}
        {{-- Sits between the material rows and the totals box. When
             enabled, applies the entered percentage on top of the
             materials pre-HST subtotal. The resulting dollars (and a
             prorated share of HST, mirroring the materials' effective
             rate) are folded into the materials totals below. --}}
        <div class="sq-contingency-block" id="sq-contingency-block">
          <div class="sq-contingency-row">
            <input type="hidden" name="contingency_enabled" value="0">
            <label class="sq-checkbox-label sq-contingency-toggle">
              <input type="checkbox" id="sq-contingency-enabled" name="contingency_enabled" value="1" @checked($contingencyEnabled)>
              <span>Contingency allowance</span>
            </label>
            <div class="sq-contingency-pct-field">
              <input type="text" inputmode="decimal" autocomplete="off" id="sq-contingency-percent"
                     name="contingency_percent" class="admin-input sq-contingency-percent"
                     maxlength="6" placeholder="10" value="{{ $contingencyPercent }}"
                     @disabled(!$contingencyEnabled)>
              <span class="sq-contingency-pct-suffix">%</span>
            </div>
            <p class="admin-form-help sq-contingency-help">
              Buffer applied on top of <em>Total materials (before HST)</em>. HST follows the materials' effective rate.
            </p>
          </div>
          @error('contingency_percent') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="cp-quotation-totals sq-materials-totals">
          <div class="cp-quotation-totals-row">
            <span>Total materials (before HST)</span>
            <span id="sq-mat-subtotal">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row sq-mat-contingency-row" id="sq-mat-contingency-row" style="{{ $contingencyEnabled ? '' : 'display:none;' }}">
            <span>Contingency (<span id="sq-mat-contingency-pct">{{ $contingencyPercent }}</span>%)</span>
            <span id="sq-mat-contingency">+CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span>Total materials with HST</span>
            <span id="sq-mat-with-hst">CA$0.00</span>
          </div>
        </div>

        {{-- Convenience save: same submit as the bottom action so the
             user can persist after entering only materials without
             scrolling all the way down. --}}
        <div class="sq-panel-save">
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-save"></i> Save only
          </button>
        </div>
      </div>

      {{-- Labour --------------------------------------------------- --}}
      <div class="admin-card sq-panel">
        <h3 style="margin:0 0 8px;">Labour</h3>
        <p class="admin-form-help">Labour is <strong>not taxed</strong> (no HST on labour). <span title="1 day = 8 hours">8 hours = 1 day</span>.</p>

        <div class="admin-form-group">
          <label for="sq-labour-rate">Hourly rate (CAD) <span class="cp-required">*</span></label>
          <input type="text" inputmode="decimal" id="sq-labour-rate" name="labour_hourly_rate" class="admin-input cp-money-input"
                 required placeholder="65.00" value="{{ $labourRate }}">
          @error('labour_hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="sq-labour-mode-fieldset">
          <legend class="admin-form-label">Estimate by</legend>
          <label class="cp-checkbox-row">
            <input type="radio" name="labour_mode" value="hours" class="sq-labour-mode" @checked($labourMode === 'hours')>
            <span>Hours</span>
          </label>
          <label class="cp-checkbox-row">
            <input type="radio" name="labour_mode" value="days" class="sq-labour-mode" @checked($labourMode === 'days')>
            <span>Days</span> <span class="admin-form-help">(× 8 h)</span>
          </label>
        </fieldset>

        <div class="admin-form-group">
          <label for="sq-labour-qty" id="sq-labour-qty-label">Estimated hours</label>
          <input type="number" id="sq-labour-qty" name="labour_quantity" class="admin-input" min="0" step="0.01"
                 value="{{ $labourQty }}" placeholder="0">
          @error('labour_quantity') <p class="admin-form-error">{{ $message }}</p> @enderror
          @error('labour_mode') <p class="admin-form-error">{{ $message }}</p> @enderror
          <p class="admin-form-help" id="sq-labour-equiv" style="margin-top:6px;"></p>
        </div>

        <div class="sq-labour-total-box">
          <span>Total labour cost</span>
          <strong id="sq-labour-total">CA$0.00</strong>
        </div>
      </div>
    </div>

    {{-- Grand summary -------------------------------------------- --}}
    <div class="admin-card sq-grand-card">
      <h3 style="margin:0 0 12px;">Totals</h3>
      <div class="sq-grand-rows">
        <div class="sq-grand-row"><span>Subtotal materials (before HST)</span><span id="sq-g-mat-sub">CA$0.00</span></div>
        <div class="sq-grand-row sq-grand-row--contingency" id="sq-g-contingency-row" style="{{ $contingencyEnabled ? '' : 'display:none;' }}">
          <span>Contingency (<span id="sq-g-contingency-pct">{{ $contingencyPercent }}</span>%)</span>
          <span id="sq-g-contingency">+CA$0.00</span>
        </div>
        <div class="sq-grand-row"><span>HST on materials (13%)</span><span id="sq-g-mat-hst">CA$0.00</span></div>
        <div class="sq-grand-row"><span>Total materials with HST</span><span id="sq-g-mat-total">CA$0.00</span></div>
        <div class="sq-grand-row"><span>Total labour</span><span id="sq-g-labour">CA$0.00</span></div>
        <div class="sq-grand-row sq-grand-row--final"><span>Grand total</span><span id="sq-g-grand">CA$0.00</span></div>
      </div>
    </div>

    <div class="sq-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save only
      </button>
    </div>
  </form>

  @if ($isEdit && !$quotation->project)
    @php $linkable = $linkableProjects ?? collect(); @endphp
    <div class="admin-card fq-link-project-card">
      <h4 style="margin:0 0 6px;">Connect to a project</h4>
      <p class="admin-form-help" style="margin:0 0 12px;">
        Adds one line to the project <strong>Quotation</strong> using the <strong>grand total</strong> below (materials already include HST where applied; labour is untaxed — the line is stored without extra HST).
      </p>

      <div class="admin-form-group" style="max-width:520px;">
        <label for="sq-link-line-desc">Line description on project quotation</label>
        <input type="text" id="sq-link-line-desc" name="link_line_description" class="admin-input" maxlength="500"
               form="sq-link-existing-form"
               value="{{ old('link_line_description', 'Standard quotation') }}"
               placeholder="Standard quotation">
        <p class="admin-form-help">Shown as the line item text (quote number is appended automatically).</p>
      </div>

      <div class="fq-link-grid">
        <div class="fq-link-col">
          <h5><i class="fa fa-link"></i> Existing project</h5>
          @if ($linkable->isNotEmpty())
            <form method="post" action="{{ route('admin.quotations.standard.link', $quotation) }}" class="fq-link-existing-form" id="sq-link-existing-form">
              @csrf
              <div class="admin-form-group" style="margin-bottom:10px;">
                <label for="sq-project-filter">Search projects</label>
                <input type="search" id="sq-project-filter" class="admin-input" placeholder="Type client or project name…" autocomplete="off">
              </div>
              <div class="fq-link-existing-row">
                <div class="admin-form-group" style="margin:0;flex:1;">
                  <label for="sq-link-project">Project</label>
                  <select id="sq-link-project" name="client_project_id" class="admin-input" required>
                    <option value="" disabled selected>Choose a project…</option>
                    @foreach ($linkable as $p)
                      <option value="{{ $p->id }}" data-label="{{ e($p->client_name.' — '.$p->name) }}">
                        {{ $p->client_name }} — {{ $p->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <button type="submit" class="admin-btn admin-btn--primary">
                  <i class="fa fa-link"></i> Link to project
                </button>
              </div>
            </form>
          @else
            <p class="admin-form-help" style="margin:0;">No active projects to link.</p>
          @endif
        </div>

        <hr class="sq-link-divider" aria-hidden="true">

        <div class="fq-link-col fq-link-col--stack">
          <h5><i class="fa fa-plus-circle"></i> New project</h5>
          <p class="admin-form-help" style="margin:0 0 12px;">
            Creates a <strong>fixed price</strong> project (status: Visit scheduled). Client name required. No client email is sent from here.
          </p>
          <form method="post" action="{{ route('admin.quotations.standard.create-project', $quotation) }}" class="fq-create-project-form"
                onsubmit="document.getElementById('sq-create-project-line-desc').value=(document.getElementById('sq-link-line-desc')&&document.getElementById('sq-link-line-desc').value)||'Standard quotation';">
            @csrf
            <div class="admin-form-group">
              <label for="sq-new-project-name">Project name <span class="admin-form-help">(optional)</span></label>
              <input type="text" id="sq-new-project-name" name="fp_project_name" class="admin-input" maxlength="255"
                     value="{{ old('fp_project_name') }}" placeholder="Defaults to: Client name — Standard">
            </div>
            <div class="admin-form-group">
              <label for="sq-new-client-name">Client name <span class="admin-required">*</span></label>
              <input type="text" id="sq-new-client-name" name="fp_client_name" class="admin-input @error('fp_client_name') is-invalid @enderror"
                     maxlength="255" required value="{{ old('fp_client_name', $quotation->client_name) }}">
              @error('fp_client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="sq-new-client-email">Email</label>
              <input type="email" id="sq-new-client-email" name="fp_client_email" class="admin-input" maxlength="255" value="{{ old('fp_client_email') }}">
            </div>
            <div class="admin-form-group">
              <label for="sq-new-client-phone">Phone</label>
              <input type="text" id="sq-new-client-phone" name="fp_client_phone" class="admin-input cp-phone-input"
                     inputmode="tel" maxlength="14" placeholder="(xxx) xxx-xxxx"
                     value="{{ old('fp_client_phone') ? \App\Models\ClientProject::formatPhone(old('fp_client_phone')) : '' }}">
              @error('fp_client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="sq-new-client-address">Address</label>
              <input type="text" id="sq-new-client-address" name="fp_client_address" class="admin-input" maxlength="500" value="{{ old('fp_client_address') }}">
            </div>
            <input type="hidden" name="link_line_description" value="" id="sq-create-project-line-desc">
            <button type="submit" class="admin-btn admin-btn--primary">
              <i class="fa fa-briefcase"></i> Create new project &amp; link
            </button>
          </form>
        </div>
      </div>
    </div>
  @endif

  <template id="sq-material-template">
    <div class="sq-material-card sq-material-row">
      <div class="sq-mat-fields">
        <div class="sq-mat-row sq-mat-row--inputs">
          <div class="sq-mat-field sq-mat-field--short-name">
            <label>Short name</label>
            <input type="text" name="materials[__I__][short_name]" class="admin-input sq-mat-short-name"
                   maxlength="60" placeholder="e.g. Paint, Primer">
          </div>
          <div class="sq-mat-field sq-mat-field--description">
            <label>Description</label>
            <input type="text" name="materials[__I__][description]" class="admin-input sq-mat-desc"
                   maxlength="500" placeholder="Material or item">
          </div>
          <div class="sq-mat-field sq-mat-field--unit">
            <label>Unit price ($)</label>
            <input type="text" inputmode="decimal" autocomplete="off" name="materials[__I__][unit_price]"
                   class="admin-input cp-money-input sq-mat-unit" placeholder="0.00">
          </div>
          <div class="sq-mat-field sq-mat-field--qty">
            <label>Qty</label>
            <input type="text" inputmode="decimal" autocomplete="off" name="materials[__I__][quantity]"
                   class="admin-input sq-mat-qty" placeholder="1">
          </div>
        </div>

        <div class="sq-mat-row sq-mat-row--computed">
          <div class="sq-mat-field sq-mat-field--readonly">
            <label>Subtotal</label>
            <div class="sq-mat-readout"><span class="sq-mat-sub">—</span></div>
          </div>
          <div class="sq-mat-field sq-mat-field--readonly">
            <label>HST (13%)</label>
            <div class="sq-mat-readout"><span class="sq-mat-hst">—</span></div>
          </div>
          <div class="sq-mat-field sq-mat-field--readonly">
            <label>Total w/ HST</label>
            <div class="sq-mat-readout"><span class="sq-mat-total">—</span></div>
          </div>
          <div class="sq-mat-field sq-mat-field--apply">
            <label>HST</label>
            <input type="hidden" name="materials[__I__][apply_hst]" value="0">
            <label class="sq-checkbox-label sq-mat-apply-label">
              <input type="checkbox" name="materials[__I__][apply_hst]" value="1" class="sq-mat-hst-cb" checked>
              <span>Apply</span>
            </label>
          </div>
        </div>
      </div>

      <div class="sq-mat-actions">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm sq-remove-material" aria-label="Remove row" title="Remove material">
          <i class="fa fa-trash"></i>
        </button>
      </div>
    </div>
  </template>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var HST = 0.13;
  var HPD = 8;

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function parseMoney(raw) {
    if (raw == null) return 0;
    var s = String(raw).replace(/[^\d.-]/g, '');
    if (s === '' || s === '-') return 0;
    var v = parseFloat(s);
    return isFinite(v) ? v : 0;
  }

  function parseQty(raw) {
    var v = parseFloat(raw);
    return isFinite(v) && v >= 0 ? v : 0;
  }

  var list = document.getElementById('sq-materials-list');
  var addBtn = document.getElementById('sq-add-material');
  var template = document.getElementById('sq-material-template');
  var rateInput = document.getElementById('sq-labour-rate');
  var qtyInput = document.getElementById('sq-labour-qty');
  var modeRadios = document.querySelectorAll('.sq-labour-mode');
  var qtyLabel = document.getElementById('sq-labour-qty-label');
  var equivEl = document.getElementById('sq-labour-equiv');

  var elMatSub = document.getElementById('sq-mat-subtotal');
  var elMatHst = document.getElementById('sq-mat-with-hst');
  var elLabour = document.getElementById('sq-labour-total');
  var gMatSub = document.getElementById('sq-g-mat-sub');
  var gMatHst = document.getElementById('sq-g-mat-hst');
  var gMatTot = document.getElementById('sq-g-mat-total');
  var gLab = document.getElementById('sq-g-labour');
  var gGrand = document.getElementById('sq-g-grand');

  // Contingency controls + readouts
  var contEnabled = document.getElementById('sq-contingency-enabled');
  var contPercent = document.getElementById('sq-contingency-percent');
  var matContRow = document.getElementById('sq-mat-contingency-row');
  var matContPct = document.getElementById('sq-mat-contingency-pct');
  var matContVal = document.getElementById('sq-mat-contingency');
  var gContRow = document.getElementById('sq-g-contingency-row');
  var gContPct = document.getElementById('sq-g-contingency-pct');
  var gContVal = document.getElementById('sq-g-contingency');

  function contingencyPctValue() {
    var raw = contPercent ? contPercent.value : '';
    var v = parseFloat(String(raw).replace(/[^0-9.]/g, ''));
    if (!isFinite(v) || v < 0) return 0;
    if (v > 100) return 100;
    return v;
  }

  function labourMode() {
    var m = document.querySelector('.sq-labour-mode:checked');
    return m ? m.value : 'hours';
  }

  function labourHoursTotal() {
    var q = parseQty(qtyInput.value);
    return labourMode() === 'days' ? q * HPD : q;
  }

  function recalcMaterials() {
    var rows = list.querySelectorAll('.sq-material-row');
    var subSum = 0;
    var hstSum = 0;
    var withSum = 0;

    rows.forEach(function (row) {
      var unit = parseMoney(row.querySelector('.sq-mat-unit').value);
      var qty = parseQty(row.querySelector('.sq-mat-qty').value);
      var cb = row.querySelector('.sq-mat-hst-cb');
      var apply = cb && cb.checked;
      var lineSub = Math.round(unit * qty * 100) / 100;
      var lineHst = apply ? Math.round(lineSub * HST * 100) / 100 : 0;
      var lineTot = Math.round((lineSub + lineHst) * 100) / 100;

      row.querySelector('.sq-mat-sub').textContent = money(lineSub);
      row.querySelector('.sq-mat-hst').textContent = apply ? money(lineHst) : '—';
      row.querySelector('.sq-mat-total').textContent = money(lineTot);

      subSum += lineSub;
      hstSum += lineHst;
      withSum += lineTot;
    });

    subSum = Math.round(subSum * 100) / 100;
    hstSum = Math.round(hstSum * 100) / 100;
    withSum = Math.round(withSum * 100) / 100;

    // Contingency: % of pre-HST materials, with HST prorated to match
    // the materials' effective HST rate. Mirrors backend calculator.
    var contOn = !!(contEnabled && contEnabled.checked);
    var contPct = contingencyPctValue();
    var contAmt = 0;
    var contHst = 0;
    if (contOn && subSum > 0 && contPct > 0) {
      contAmt = Math.round(subSum * (contPct / 100) * 100) / 100;
      var effRate = subSum > 0 ? (hstSum / subSum) : 0;
      contHst = Math.round(contAmt * effRate * 100) / 100;
    }

    var hstWithCont = Math.round((hstSum + contHst) * 100) / 100;
    var withSumWithCont = Math.round((withSum + contAmt + contHst) * 100) / 100;

    if (contPercent) {
      contPercent.disabled = !contOn;
    }

    var pctLabel = (Math.round(contPct * 100) / 100).toString();
    if (matContPct) matContPct.textContent = pctLabel;
    if (gContPct) gContPct.textContent = pctLabel;
    if (matContRow) matContRow.style.display = contOn ? '' : 'none';
    if (gContRow) gContRow.style.display = contOn ? '' : 'none';
    if (matContVal) matContVal.textContent = '+' + money(contAmt);
    if (gContVal) gContVal.textContent = '+' + money(contAmt);

    elMatSub.textContent = money(subSum);
    elMatHst.textContent = money(withSumWithCont);

    var rate = parseMoney(rateInput.value);
    var hrs = labourHoursTotal();
    var lab = Math.round(rate * hrs * 100) / 100;
    elLabour.textContent = money(lab);

    gMatSub.textContent = money(subSum);
    gMatHst.textContent = money(hstWithCont);
    gMatTot.textContent = money(withSumWithCont);
    gLab.textContent = money(lab);
    gGrand.textContent = money(withSumWithCont + lab);

    var lq = parseQty(qtyInput.value);
    if (labourMode() === 'days') {
      qtyLabel.textContent = 'Estimated days';
      var hrsFromDays = lq * HPD;
      equivEl.textContent = lq > 0 ? ('≈ ' + (Math.round(hrsFromDays * 100) / 100) + ' hours') : '';
    } else {
      qtyLabel.textContent = 'Estimated hours';
      var daysFromHrs = lq / HPD;
      equivEl.textContent = lq > 0 ? ('≈ ' + (Math.round(daysFromHrs * 1000) / 1000) + ' days (8 h/day)') : '';
    }
  }

  function reindexMaterialNames() {
    var rows = list.querySelectorAll('.sq-material-row');
    rows.forEach(function (row, i) {
      row.querySelectorAll('[name]').forEach(function (el) {
        el.name = el.name.replace(/materials\[\d+]/, 'materials[' + i + ']');
      });
    });
  }

  function addRow() {
    var html = template.innerHTML.replace(/__I__/g, String(list.querySelectorAll('.sq-material-row').length));
    var wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    list.appendChild(wrap.firstElementChild);
    reindexMaterialNames();
    recalcMaterials();
  }

  list.addEventListener('click', function (e) {
    var rm = e.target.closest('.sq-remove-material');
    if (!rm) return;
    var row = rm.closest('.sq-material-row');
    if (!row) return;
    if (list.querySelectorAll('.sq-material-row').length <= 1) return;
    row.remove();
    reindexMaterialNames();
    recalcMaterials();
  });

  if (addBtn) addBtn.addEventListener('click', addRow);

  // Strip anything that isn't a digit or a single decimal point from the
  // material-row qty inputs. We use type=text so we can fully control what
  // gets accepted, including supporting decimals like "1.5". Mobile keyboards
  // still come up numeric thanks to inputmode="decimal".
  function sanitizeDecimalInput(input) {
    var raw = input.value;
    // Remove any character that isn't 0-9 or ".".
    var cleaned = raw.replace(/[^0-9.]/g, '');
    // Collapse repeated dots: keep only the first one.
    var firstDot = cleaned.indexOf('.');
    if (firstDot !== -1) {
      cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
    }
    if (cleaned !== raw) {
      var pos = input.selectionStart;
      input.value = cleaned;
      // Best-effort caret restore (may be off by 1 if chars were stripped).
      try { input.setSelectionRange(pos - (raw.length - cleaned.length), pos - (raw.length - cleaned.length)); } catch (_) {}
    }
  }

  document.getElementById('sq-standard-form').addEventListener('input', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('sq-mat-qty')) {
      sanitizeDecimalInput(e.target);
    }
    if (e.target && e.target.id === 'sq-contingency-percent') {
      sanitizeDecimalInput(e.target);
    }
    if (e.target && e.target.closest('#sq-materials-list, #sq-labour-rate, #sq-labour-qty, #sq-contingency-block')) {
      recalcMaterials();
    }
  });
  document.getElementById('sq-standard-form').addEventListener('change', function (e) {
    if (e.target && (
        e.target.classList.contains('sq-mat-hst-cb') ||
        e.target.classList.contains('sq-labour-mode') ||
        e.target.id === 'sq-contingency-enabled'
    )) {
      recalcMaterials();
    }
  });

  modeRadios.forEach(function (r) {
    r.addEventListener('change', recalcMaterials);
  });

  var projFilter = document.getElementById('sq-project-filter');
  var projSelect = document.getElementById('sq-link-project');
  if (projFilter && projSelect) {
    projFilter.addEventListener('input', function () {
      var q = projFilter.value.toLowerCase().trim();
      projSelect.querySelectorAll('option').forEach(function (opt) {
        if (!opt.value) return;
        var lab = (opt.getAttribute('data-label') || opt.textContent || '').toLowerCase();
        opt.hidden = q !== '' && lab.indexOf(q) === -1;
      });
    });
  }

  recalcMaterials();
})();
</script>
@endpush

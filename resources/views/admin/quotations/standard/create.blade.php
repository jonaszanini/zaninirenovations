@extends('admin.layouts.app')

@php
  use App\Models\ClientProjectQuotation;

  $isEdit = filled($quotation);

  // Project-context mode: this editor was opened from a project's
  // "Edit quotation" entry point. The form posts with ?return=project
  // so the controller redirects the user back to the project's
  // Quotation tab on save (instead of staying on the standalone
  // editor). The "Connect to a project" panel is hidden in this mode
  // since the SQ is already linked.
  $returnToProject = request('return') === 'project'
    && $isEdit
    && $quotation->project;
  $projectForReturn = $returnToProject ? $quotation->project : null;

  $formAction = $isEdit
    ? route('admin.quotations.standard.update', $quotation).($returnToProject ? '?return=project' : '')
    : route('admin.quotations.standard.store');
  $formMethod = $isEdit ? 'PUT' : 'POST';

  $matRows = old('materials');
  if ($matRows === null && $isEdit) {
      $matRows = is_array($quotation->materials) ? $quotation->materials : [];
  }
  if (!is_array($matRows) || count($matRows) === 0) {
      $matRows = [['short_name' => '', 'description' => '', 'url' => '', 'unit_price' => '', 'quantity' => '1', 'apply_hst' => true, 'hst_includes_unit' => false, 'catalog_material_id' => null]];
  }

  $labourMode = old('labour_mode', $isEdit ? $quotation->labour_mode : 'hours');
  $labourQty = old('labour_quantity', $isEdit ? (string) $quotation->labour_quantity : '');
  $labourRate = old('labour_hourly_rate', $isEdit ? number_format((float) $quotation->labour_hourly_rate, 2, '.', '') : '65.00');

  $labourUseFixed = old('labour_use_fixed', $isEdit ? (bool) ($quotation->labour_use_fixed ?? false) : false);
  $labourFixedAmount = old('labour_fixed_amount');
  if ($labourFixedAmount === null && $isEdit && $quotation->labour_fixed_amount !== null) {
      $labourFixedAmount = number_format((float) $quotation->labour_fixed_amount, 2, '.', '');
  }
  if ($labourFixedAmount === null) {
      $labourFixedAmount = '';
  }
  $labourFixedHstInc = old('labour_fixed_hst_included', $isEdit ? (bool) ($quotation->labour_fixed_hst_included ?? false) : false);

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

  $linkProject = $linkProject ?? null;

  $defaultDocType = $isEdit
      ? (($quotation->document_type ?? null) ?: ClientProjectQuotation::TYPE_QUOTATION)
      : ClientProjectQuotation::TYPE_QUOTATION;

  $pdfDetailMode = old(
      'pdf_detail_mode',
      $isEdit
          ? ($quotation->pdf_detail_mode ?? \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY)
          : \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY
  );

  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
@endphp

@section('heading')
  @php
    $headDisplay = old('display_title', $isEdit ? ($quotation->display_title ?? '') : '');
  @endphp
  @if (filled($headDisplay))
    <span class="admin-sq-heading-display" style="display:block;font-weight:700;margin-bottom:8px;">{{ $headDisplay }}</span>
  @endif
  <span @if (filled($headDisplay)) style="font-weight:400;" @endif>{{ $isEdit ? 'Edit standard quotation '.$quotation->quote_number : 'New standard quotation' }}</span>
@endsection

@section('content')
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      @if ($returnToProject && $projectForReturn)
        <a href="{{ route('admin.client-projects.show', $projectForReturn) }}#quotation"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
      @else
        <a href="{{ route('admin.quotations.standard.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> All standard quotations
        </a>
        <a href="{{ route('admin.quotations.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-th"></i> Quotation types
        </a>
      @endif
    </div>
    <div class="admin-toolbar-group">
      @if ($isEdit && $quotation->isLinked() && !$returnToProject)
        <a href="{{ route('admin.client-projects.show', $quotation->client_project_id) }}#quotation"
           class="admin-btn admin-btn--primary admin-btn--sm">
          <i class="fa fa-briefcase"></i> Open linked project
        </a>
      @endif
      <button type="submit"
              form="sq-standard-form"
              class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> Save
      </button>
    </div>
  </div>

  @if ($returnToProject && $projectForReturn)
    <div class="admin-flash admin-flash--info">
      Editing this standard quotation in the context of project
      <a href="{{ route('admin.client-projects.show', $projectForReturn) }}#quotation">
        {{ $projectForReturn->name }}
      </a>. Saving will update the project's Quotation tab automatically.
    </div>
  @endif

  @if (session('standard_flash') === 'saved' || session('standard_flash') === 'linked')
    <div class="admin-card sq-success-card">
      <h4 style="margin:0 0 8px;">Next steps</h4>
      <div class="sq-success-actions">
        @if (session('standard_linked_project_id'))
          <a href="{{ route('admin.client-projects.show', session('standard_linked_project_id')) }}#quotation"
             class="admin-btn admin-btn--primary sq-success-project-btn">
            <i class="fa fa-briefcase"></i> Open linked project (Quotation tab)
          </a>
        @endif
        <a href="{{ route('admin.quotations.standard.create', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-plus"></i> Create another standard quotation
        </a>
        <a href="{{ route('admin.quotations.standard.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-list"></i> View all saved quotations
        </a>
      </div>
    </div>
  @endif

  <form action="{{ $formAction }}" method="post" id="sq-standard-form">
    @csrf
    @if ($isEdit)
      @method('PUT')
    @endif
    @if ($linkProject)
      <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
    @endif

    @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])

    <div class="admin-card sq-intro-card">
      <div class="sq-intro-sections">
        <section class="sq-field-box">
          <h4 class="sq-field-box-title">Client</h4>
          <div class="sq-intro-grid sq-intro-grid--2">
            <div class="admin-form-group">
              <label for="sq-client-name">Client name <span class="cp-required">*</span></label>
              <input type="text" id="sq-client-name" name="client_name" class="admin-input @error('client_name') is-invalid @enderror"
                     maxlength="160" required
                     value="{{ old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? '')) }}"
                     placeholder="e.g. Jane Smith">
              @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="sq-display-title">Title <span class="cp-required">*</span></label>
              <input type="text" id="sq-display-title" name="display_title" class="admin-input @error('display_title') is-invalid @enderror"
                     maxlength="255" required
                     value="{{ old('display_title', $isEdit ? $quotation->display_title : '') }}"
                     placeholder="Shown at the top of the client quotation PDF">
              @error('display_title') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>
        </section>

        <section class="sq-field-box">
          <h4 class="sq-field-box-title">PDF &amp; document</h4>
          <div class="sq-intro-pdf-options">
            <div class="sq-intro-option-block">
              <h5 class="sq-intro-option-title">Document type</h5>
              @php $docSel = old('document_type', $defaultDocType); @endphp
              <div class="sq-intro-choice-row">
                <label class="cp-checkbox-row">
                  <input type="radio" name="document_type" value="{{ ClientProjectQuotation::TYPE_QUOTATION }}" @checked($docSel === ClientProjectQuotation::TYPE_QUOTATION)>
                  <span>Quotation (firm price)</span>
                </label>
                <label class="cp-checkbox-row">
                  <input type="radio" name="document_type" value="{{ ClientProjectQuotation::TYPE_ESTIMATE }}" @checked($docSel === ClientProjectQuotation::TYPE_ESTIMATE)>
                  <span>Estimate</span>
                </label>
              </div>
              <p class="admin-form-help sq-intro-option-help">You can switch between quotation and estimate at any time; the document number prefix updates when you save.</p>
              @error('document_type') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sq-intro-option-block">
              <h5 class="sq-intro-option-title">Client PDF line items</h5>
              <div class="sq-intro-choice-col">
                <label class="cp-checkbox-row">
                  <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY }}"
                    @checked($pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY)>
                  <span><strong>Summary</strong> — one line with subtotal, HST, and total</span>
                </label>
                <label class="cp-checkbox-row">
                  <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_DETAILED }}"
                    @checked($pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_DETAILED)>
                  <span><strong>Detailed</strong> — list each material, price, and labour with HST columns</span>
                </label>
              </div>
              <p class="admin-form-help sq-intro-option-help">Controls what the client sees when you download or email the PDF from the project.</p>
              @error('pdf_detail_mode') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sq-intro-option-block">
              <h5 class="sq-intro-option-title">Includes (PDF wording)</h5>
              @php
                $matDef = $isEdit ? (($quotation->includes_material ?? true) ? '1' : '0') : '1';
                $labDef = $isEdit ? (($quotation->includes_labour ?? true) ? '1' : '0') : '1';
              @endphp
              <div class="sq-intro-choice-row">
                <label class="admin-checkbox cp-checkbox-row">
                  <input type="hidden" name="includes_material" value="0">
                  <input type="checkbox" class="admin-checkbox__input" name="includes_material" value="1" @checked((string) old('includes_material', $matDef) === '1')>
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                  <span class="admin-checkbox__text">Material</span>
                </label>
                <label class="admin-checkbox cp-checkbox-row">
                  <input type="hidden" name="includes_labour" value="0">
                  <input type="checkbox" class="admin-checkbox__input" name="includes_labour" value="1" @checked((string) old('includes_labour', $labDef) === '1')>
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                  <span class="admin-checkbox__text">Labour</span>
                </label>
              </div>
              <p class="admin-form-help sq-intro-option-help">Controls the summary line on the client PDF (e.g. Labour and material or Labour).</p>
              @error('includes_material') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>
        </section>

        <section class="sq-field-box">
          <h4 class="sq-field-box-title">Description of the work</h4>
          <div class="admin-form-group sq-intro-work-desc">
            <label for="sq-work-description" class="visually-hidden">Description of the work</label>
            <textarea id="sq-work-description" name="work_description" rows="5" maxlength="5000"
                      class="admin-input @error('work_description') is-invalid @enderror"
                      placeholder="Briefly describe the service to be performed (visible to the client on the quotation PDF).">{{ old('work_description', $isEdit ? $quotation->work_description : '') }}</textarea>
            @include('admin.partials.quotation_work_description_ai', [
                'workDescriptionTextareaId' => 'sq-work-description',
                'aiContextFormId' => 'sq-standard-form',
                'aiQuotationType' => 'standard',
            ])
            <p class="admin-form-help sq-intro-option-help">Shown on the client PDF beneath the priced line item. Plain text — line breaks are preserved.</p>
            @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </section>
      </div>
    </div>

    <div class="sq-layout">
      {{-- Materials ------------------------------------------------ --}}
      <div class="admin-card sq-panel">
        <div class="sq-panel-head">
          <h3 class="cp-form-help-flush">Materials</h3>
        </div>
        <p class="admin-form-help sq-materials-intro">
          Each row: <strong>Type manually</strong> or <strong>From Materials DB</strong> (<a href="{{ route('admin.materials.index') }}" class="admin-text-link">catalog</a>).
          Unit price is before tax unless you check <strong>Price includes HST</strong>.
        </p>

        {{-- Each material is rendered as a small two-line "form card" so
             the inputs stop getting squeezed on narrow viewports. Line 1
             holds the editable fields (short name, description, unit
             price, qty); line 2 holds the live-computed values (subtotal,
             HST, total) plus the per-row HST toggle. The delete button
             sits at the far right and is vertically centred across both
             lines via grid alignment. --}}
        <div class="sq-materials-list" id="sq-materials-list">
          @foreach ($matRows as $i => $row)
            @include('admin.quotations.standard._material_row', ['i' => $i, 'row' => $row])
          @endforeach
        </div>

        <div class="sq-add-material-wrap">
          <button type="button" class="admin-btn admin-btn--ghost sq-add-material-wide" id="sq-add-material">
            <i class="fa fa-plus"></i> Add material
          </button>
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
            <label class="admin-checkbox sq-checkbox-label sq-contingency-toggle">
              <input type="checkbox" id="sq-contingency-enabled" name="contingency_enabled" value="1" class="admin-checkbox__input" @checked($contingencyEnabled)>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Contingency allowance</span>
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
            <i class="fa fa-save"></i> Save
          </button>
        </div>
      </div>

      {{-- Labour --------------------------------------------------- --}}
      <div class="admin-card sq-panel">
        <h3 style="margin:0 0 8px;">Labour</h3>
        <p class="admin-form-help">Labour is <strong>HST-taxable</strong> at 13%. Use a <strong>flat labour fee</strong> for a fixed dollar amount, or estimate by <strong>rate × hours/days</strong> (<span title="1 day = 8 hours">8 h = 1 day</span>).</p>

        <div class="admin-form-group sq-labour-fixed-block">
          <input type="hidden" name="labour_use_fixed" value="0">
          <label class="admin-checkbox sq-checkbox-label cp-geometry-hint">
            <input type="checkbox" name="labour_use_fixed" value="1" id="sq-labour-use-fixed" class="admin-checkbox__input sq-labour-use-fixed-cb" @checked($labourUseFixed)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text"><strong>Flat labour fee</strong> (skip rate &amp; hours)</span>
          </label>
          <div class="sq-labour-fixed-fields" id="sq-labour-fixed-fields">
            <label for="sq-labour-fixed-amount">Amount (CAD)</label>
            <input type="text" inputmode="decimal" autocomplete="off" id="sq-labour-fixed-amount" name="labour_fixed_amount"
                   class="admin-input cp-money-input" placeholder="0.00" value="{{ $labourFixedAmount }}">
            <div class="cp-form-group-spaced">
              <input type="hidden" name="labour_fixed_hst_included" value="0">
              <label class="admin-checkbox sq-checkbox-label sq-mat-apply-label" style="display:inline-flex;">
                <input type="checkbox" name="labour_fixed_hst_included" value="1" id="sq-labour-fixed-hst-inc" class="admin-checkbox__input sq-labour-fixed-hst-inc-cb" @checked($labourFixedHstInc)>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">HST included</span>
              </label>
            </div>
            <p class="admin-form-help" style="margin-top:8px;">When <strong>HST included</strong> is checked, the amount is the total with tax; before-tax labour is calculated the same way as materials with <strong>HST-incl. price</strong>.</p>
          </div>
          @error('labour_fixed_amount') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="sq-labour-rate-fields" id="sq-labour-rate-fields">
        <div class="admin-form-group">
          <label for="sq-labour-rate">Hourly rate (CAD) @unless($labourUseFixed)<span class="cp-required">*</span>@endunless</label>
          <input type="text" inputmode="decimal" id="sq-labour-rate" name="labour_hourly_rate" class="admin-input cp-money-input"
                 @unless($labourUseFixed) required @endunless placeholder="65.00" value="{{ $labourRate }}">
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
        </div>

        <div class="cp-quotation-totals sq-labour-totals">
          <div class="cp-quotation-totals-row">
            <span>Total labour (before HST)</span>
            <span id="sq-labour-total">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span>HST on labour (13%)</span>
            <span id="sq-labour-hst">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span>Total labour with HST</span>
            <span id="sq-labour-with-hst">CA$0.00</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Grand summary -------------------------------------------- --}}
    @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])

    <div class="admin-card sq-grand-card">
      <h3 class="sq-grand-card__title">Totals</h3>
      <div class="sq-grand-rows">
        <section class="sq-grand-section">
          <h4 class="sq-grand-section__title">Materials</h4>
          <div class="sq-grand-section__body">
            <div class="sq-grand-row sq-grand-row--sub">
              <span>Subtotal (before HST)</span>
              <span id="sq-g-mat-sub">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--sub sq-grand-row--contingency" id="sq-g-contingency-row" style="{{ $contingencyEnabled ? '' : 'display:none;' }}">
              <span>Contingency (<span id="sq-g-contingency-pct">{{ $contingencyPercent }}</span>%)</span>
              <span id="sq-g-contingency">+CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--sub">
              <span>HST (13%)</span>
              <span id="sq-g-mat-hst">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--section-total">
              <span>Total materials with HST</span>
              <span id="sq-g-mat-total">CA$0.00</span>
            </div>
          </div>
        </section>

        <section class="sq-grand-section">
          <h4 class="sq-grand-section__title">Labour</h4>
          <div class="sq-grand-section__body">
            <div class="sq-grand-row sq-grand-row--sub">
              <span>Subtotal (before HST)</span>
              <span id="sq-g-labour">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--sub">
              <span>HST (13%)</span>
              <span id="sq-g-labour-hst">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--section-total">
              <span>Total labour with HST</span>
              <span id="sq-g-labour-total">CA$0.00</span>
            </div>
          </div>
        </section>

        <div class="sq-grand-row sq-grand-row--commute is-hidden" id="sq-g-commute-row">
          <span>Commute expense <span class="admin-form-help">(incl. HST)</span></span>
          <span id="sq-g-commute">CA$0.00</span>
        </div>

        <div class="sq-grand-row sq-grand-row--final">
          <span>Grand total</span>
          <span id="sq-g-grand">CA$0.00</span>
        </div>
      </div>
    </div>

    <div class="sq-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save
      </button>
    </div>
  </form>

  @if ($isEdit && !$quotation->project)
    @php $linkable = $linkableProjects ?? collect(); @endphp
    <div class="admin-card fq-link-project-card">
      <h4 class="cp-heading-md">Connect to a project</h4>
      <p class="admin-form-help cp-form-help-spaced">
        Adds the <strong>pre-HST</strong> amounts to the project's <strong>Quotation</strong>. The project quotation's "Apply HST" toggle will then add HST to the taxable portion (no double taxation). If some materials are HST-exempt, two lines are added — one taxable, one exempt.
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
              <div class="fq-link-existing-row">
                @include('admin.quotations._link_project_picker', [
                    'projects' => $linkable,
                    'id' => 'sq-link-project',
                ])
                <button type="submit" class="admin-btn admin-btn--primary">
                  <i class="fa fa-link"></i> Link to project
                </button>
              </div>
            </form>
          @else
            <p class="admin-form-help cp-form-help-flush">No active projects to link.</p>
          @endif
        </div>

        <hr class="sq-link-divider" aria-hidden="true">

        <div class="fq-link-col fq-link-col--stack">
          <h5><i class="fa fa-plus-circle"></i> New project</h5>
          <p class="admin-form-help cp-form-help-spaced">
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
    @include('admin.quotations.standard._material_row', [
      'i' => '__I__',
      'row' => [
        'short_name' => '',
        'description' => '',
        'url' => '',
        'unit_price' => '',
        'quantity' => '1',
        'apply_hst' => true,
        'hst_includes_unit' => false,
        'catalog_material_id' => null,
      ],
    ])
  </template>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
  'use strict';
  var HST = 0.13;
  var HPD = 8;
  var sqCatalogOptions = @json($catalogPickerOptions ?? []);
  var sqCatalogOptionMap = {};
  sqCatalogOptions.forEach(function (item) {
    sqCatalogOptionMap[String(item.id)] = item;
  });

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function parseMoney(raw) {
    if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
      return window.cpMoneyMask.parse(raw);
    }
    if (raw == null) return 0;
    var s = String(raw).replace(/[^\d.-]/g, '');
    if (s === '' || s === '-') return 0;
    var v = parseFloat(s);
    return isFinite(v) ? v : 0;
  }

  function parseQty(raw) {
    var v = parseFloat(raw);
    return isFinite(v) && v>= 0 ? v : 0;
  }

  var list = document.getElementById('sq-materials-list');
  var addBtn = document.getElementById('sq-add-material');
  var template = document.getElementById('sq-material-template');
  var rateInput = document.getElementById('sq-labour-rate');
  var qtyInput = document.getElementById('sq-labour-qty');
  var modeRadios = document.querySelectorAll('.sq-labour-mode');
  var qtyLabel = document.getElementById('sq-labour-qty-label');
  var equivEl = document.getElementById('sq-labour-equiv');
  var useFixedCb = document.getElementById('sq-labour-use-fixed');
  var fixedAmtInput = document.getElementById('sq-labour-fixed-amount');
  var fixedHstInc = document.getElementById('sq-labour-fixed-hst-inc');
  var rateFields = document.getElementById('sq-labour-rate-fields');

  function syncFixedLabourUI() {
    var on = useFixedCb && useFixedCb.checked;
    if (rateFields) {
      rateFields.style.opacity = on ? '0.55' : '';
      rateFields.style.pointerEvents = on ? 'none' : '';
    }
    if (rateInput) {
      rateInput.disabled = !!on;
      if (on) {
        rateInput.removeAttribute('required');
      } else {
        rateInput.setAttribute('required', 'required');
      }
    }
    if (qtyInput) qtyInput.disabled = !!on;
    modeRadios.forEach(function (r) { r.disabled = !!on; });
    if (fixedAmtInput) fixedAmtInput.disabled = !on;
    if (fixedHstInc) fixedHstInc.disabled = !on;
  }

  var elMatSub = document.getElementById('sq-mat-subtotal');
  var elMatHst = document.getElementById('sq-mat-with-hst');
  var elLabour = document.getElementById('sq-labour-total');
  var elLabourHst = document.getElementById('sq-labour-hst');
  var elLabourWithHst = document.getElementById('sq-labour-with-hst');
  var gMatSub = document.getElementById('sq-g-mat-sub');
  var gMatHst = document.getElementById('sq-g-mat-hst');
  var gMatTot = document.getElementById('sq-g-mat-total');
  var gLab = document.getElementById('sq-g-labour');
  var gLabHst = document.getElementById('sq-g-labour-hst');
  var gLabTot = document.getElementById('sq-g-labour-total');
  var gGrand = document.getElementById('sq-g-grand');
  var gCommuteRow = document.getElementById('sq-g-commute-row');
  var gCommute = document.getElementById('sq-g-commute');
  var lastCommuteAmount = 0;

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
    if (v> 100) return 100;
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

  function syncHstToggles(row) {
    var inc = row.querySelector('.sq-mat-hst-inclusive-cb');
    var apply = row.querySelector('.sq-mat-hst-cb');
    if (!inc || !apply) return;
    if (inc.checked) {
      apply.checked = true;
      apply.disabled = true;
    } else {
      apply.disabled = false;
    }
  }

  function recalcMaterials() {
    var rows = list.querySelectorAll('.sq-material-row');
    var subSum = 0;
    var hstSum = 0;
    var withSum = 0;

    rows.forEach(function (row) {
      syncHstToggles(row);
      var unit = parseMoney(row.querySelector('.sq-mat-unit').value);
      var qty = parseQty(row.querySelector('.sq-mat-qty').value);
      var inclusive = row.querySelector('.sq-mat-hst-inclusive-cb') && row.querySelector('.sq-mat-hst-inclusive-cb').checked;
      var applyCb = row.querySelector('.sq-mat-hst-cb');
      var chargePreTax = applyCb && applyCb.checked;
      var lineGross = Math.round(unit * qty * 100) / 100;
      var lineSub;
      var lineHst;
      var lineTot;
      if (inclusive) {
        lineSub = Math.round(lineGross / (1 + HST) * 100) / 100;
        lineHst = Math.round((lineGross - lineSub) * 100) / 100;
        lineTot = lineGross;
      } else if (chargePreTax) {
        lineSub = lineGross;
        lineHst = Math.round(lineSub * HST * 100) / 100;
        lineTot = Math.round((lineSub + lineHst) * 100) / 100;
      } else {
        lineSub = lineGross;
        lineHst = 0;
        lineTot = lineSub;
      }

      row.querySelector('.sq-mat-sub').textContent = money(lineSub);
      row.querySelector('.sq-mat-hst').textContent = (inclusive || chargePreTax) ? money(lineHst) : '—';
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
    if (contOn && subSum> 0 && contPct> 0) {
      contAmt = Math.round(subSum * (contPct / 100) * 100) / 100;
      var effRate = subSum> 0 ? (hstSum / subSum) : 0;
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

    var useFixed = useFixedCb && useFixedCb.checked;
    var lab;
    var labHst;
    var labWithHst;
    if (useFixed) {
      var fa = parseMoney(fixedAmtInput ? fixedAmtInput.value : '');
      if (fixedHstInc && fixedHstInc.checked) {
        lab = Math.round(fa / (1 + HST) * 100) / 100;
        labHst = Math.round((fa - lab) * 100) / 100;
        labWithHst = fa;
      } else {
        lab = Math.round(fa * 100) / 100;
        labHst = Math.round(lab * HST * 100) / 100;
        labWithHst = Math.round((lab + labHst) * 100) / 100;
      }
    } else {
      var rate = parseMoney(rateInput.value);
      var hrs = labourHoursTotal();
      lab = Math.round(rate * hrs * 100) / 100;
      labHst = Math.round(lab * HST * 100) / 100;
      labWithHst = Math.round((lab + labHst) * 100) / 100;
    }
    elLabour.textContent = money(lab);
    if (elLabourHst) elLabourHst.textContent = money(labHst);
    if (elLabourWithHst) elLabourWithHst.textContent = money(labWithHst);

    gMatSub.textContent = money(subSum);
    gMatHst.textContent = money(hstWithCont);
    gMatTot.textContent = money(withSumWithCont);
    gLab.textContent = money(lab);
    if (gLabHst) gLabHst.textContent = money(labHst);
    if (gLabTot) gLabTot.textContent = money(labWithHst);
    if (gCommuteRow) gCommuteRow.classList.toggle('is-hidden', !(lastCommuteAmount> 0));
    if (gCommute) gCommute.textContent = money(lastCommuteAmount);
    gGrand.textContent = money(withSumWithCont + labWithHst + lastCommuteAmount);

    if (useFixed) {
      if (equivEl) equivEl.textContent = '';
    } else {
    var lq = parseQty(qtyInput.value);
    if (labourMode() === 'days') {
      qtyLabel.textContent = 'Estimated days';
      var hrsFromDays = lq * HPD;
      equivEl.textContent = lq> 0 ? ('≈ ' + (Math.round(hrsFromDays * 100) / 100) + ' hours') : '';
    } else {
      qtyLabel.textContent = 'Estimated hours';
      var daysFromHrs = lq / HPD;
      equivEl.textContent = lq> 0 ? ('≈ ' + (Math.round(daysFromHrs * 1000) / 1000) + ' days (8 h/day)') : '';
    }
    }
  }

  function destroyCatalogPicker(row) {
    var selectEl = row.querySelector('.sq-mat-catalog-select');
    if (!selectEl) return;
    if (selectEl.tomselect) {
      selectEl.tomselect.destroy();
    }
  }

  function rowHasMaterialContent(row) {
    var shortEl = row.querySelector('.sq-mat-short-name');
    var descEl = row.querySelector('.sq-mat-desc');
    var selectEl = row.querySelector('.sq-mat-catalog-select');
    var catalogVal = selectEl && selectEl.tomselect ? selectEl.tomselect.getValue() : (selectEl ? selectEl.value : '');
    return !!(catalogVal && String(catalogVal) !== '0')
      || (shortEl && String(shortEl.value || '').trim() !== '')
      || (descEl && String(descEl.value || '').trim() !== '');
  }

  function syncMatDetailsVisibility(row) {
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    var isCatalog = checked && checked.value === 'catalog';
    var details = row.querySelector('.sq-mat-details-panel');
    var emptyHint = row.querySelector('.sq-mat-catalog-empty');
    var showDetails = !isCatalog || rowHasMaterialContent(row);
    if (details) details.hidden = !showDetails;
    if (emptyHint) emptyHint.hidden = !isCatalog || showDetails;
  }

  function syncMatSourceUI(row) {
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    var catalogWrap = row.querySelector('.sq-mat-catalog-wrap');
    var isCatalog = checked && checked.value === 'catalog';
    if (catalogWrap) catalogWrap.hidden = !isCatalog;
    if (!isCatalog) {
      destroyCatalogPicker(row);
    } else {
      initCatalogPicker(row);
    }
    syncMatDetailsVisibility(row);
  }

  function applyCatalogToRow(row, item) {
    if (!item) {
      syncMatDetailsVisibility(row);
      recalcMaterials();
      return;
    }
    var shortEl = row.querySelector('.sq-mat-short-name');
    var descEl = row.querySelector('.sq-mat-desc');
    var unitEl = row.querySelector('.sq-mat-unit');
    var urlEl = row.querySelector('.sq-mat-url');
    if (shortEl) shortEl.value = item.name || '';
    if (descEl) {
      descEl.value = item.description || item.store_name || item.name || '';
    }
    if (unitEl) unitEl.value = item.price || '';
    if (urlEl) {
      urlEl.value = item.url || '';
      syncUrlOpenLink(urlEl);
    }
    if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function' && unitEl) {
      window.cpMoneyMask.wire([unitEl]);
    }
    syncMatDetailsVisibility(row);
    recalcMaterials();
  }

  function initCatalogPicker(row) {
    if (typeof TomSelect === 'undefined') return;
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    if (!checked || checked.value !== 'catalog') return;
    var selectEl = row.querySelector('.sq-mat-catalog-select');
    if (!selectEl || selectEl.tomselect) return;

    var initial = selectEl.getAttribute('data-initial') || '';
    var ts = new TomSelect(selectEl, {
      plugins: ['clear_button'],
      options: sqCatalogOptions,
      valueField: 'id',
      labelField: 'label',
      searchField: ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
      maxOptions: 80,
      maxItems: 1,
      placeholder: 'Type to search name or SKU…',
      allowEmptyOption: true,
      closeAfterSelect: true,
      render: {
        option: function (data, escape) {
          return '<div class="fq-catalog-picker__option">' +
            '<span class="fq-catalog-picker__option-name">' + escape(data.name) + '</span>' +
            (data.sku ? '<span class="fq-catalog-picker__option-meta">SKU ' + escape(data.sku) + '</span>' : '') +
            '<span class="fq-catalog-picker__option-price">' + escape(data.price_label || '') + '</span>' +
            '</div>';
        },
        item: function (data, escape) {
          return '<span class="fq-catalog-picker__item">' + escape(data.label) + '</span>';
        }
      },
      onChange: function (value) {
        applyCatalogToRow(row, value ? sqCatalogOptionMap[String(value)] : null);
      }
    });

    if (initial && initial !== '0') {
      ts.setValue(initial, true);
    }
  }

  function initMaterialRow(row) {
    syncMatSourceUI(row);
    row.querySelectorAll('.sq-mat-source-radio').forEach(function (radio) {
      if (radio.dataset.sqSourceBound === '1') return;
      radio.dataset.sqSourceBound = '1';
      radio.addEventListener('change', function () {
        syncMatSourceUI(row);
      });
    });
  }

  function reindexMaterialNames() {
    var rows = list.querySelectorAll('.sq-material-row');
    rows.forEach(function (row, i) {
      destroyCatalogPicker(row);
      row.querySelectorAll('[name]').forEach(function (el) {
        el.name = el.name.replace(/materials\[\d+]/, 'materials[' + i + ']');
        el.name = el.name.replace(/materials\[__I__]/, 'materials[' + i + ']');
      });
      initMaterialRow(row);
    });
  }

  function addRow() {
    var html = template.innerHTML.replace(/__I__/g, String(list.querySelectorAll('.sq-material-row').length));
    var wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    var newRow = wrap.firstElementChild;
    list.appendChild(newRow);
    reindexMaterialNames();
    if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
      window.cpMoneyMask.wire(newRow.querySelectorAll('.cp-money-input'));
    }
    recalcMaterials();
  }

  list.addEventListener('click', function (e) {
    var rm = e.target.closest('.sq-remove-material');
    if (!rm) return;
    var row = rm.closest('.sq-material-row');
    if (!row) return;
    if (list.querySelectorAll('.sq-material-row').length <= 1) return;
    destroyCatalogPicker(row);
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

  function syncUrlOpenLink(input) {
    var control = input.closest('.sq-mat-url-control');
    if (!control) return;
    var open = control.querySelector('.sq-mat-url-open');
    if (!open) return;
    var val = String(input.value || '').trim();
    if (val === '') {
      open.hidden = true;
      open.setAttribute('href', '#');
    } else {
      open.hidden = false;
      open.setAttribute('href', val);
    }
  }

  document.getElementById('sq-standard-form').addEventListener('input', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('sq-mat-qty')) {
      sanitizeDecimalInput(e.target);
    }
    if (e.target && e.target.classList && e.target.classList.contains('sq-mat-url')) {
      syncUrlOpenLink(e.target);
    }
    if (e.target && e.target.id === 'sq-contingency-percent') {
      sanitizeDecimalInput(e.target);
    }
    if (e.target && e.target.closest('#sq-materials-list, #sq-labour-rate, #sq-labour-qty, #sq-contingency-block, #sq-labour-fixed-fields')) {
      recalcMaterials();
    }
  });
  document.getElementById('sq-standard-form').addEventListener('change', function (e) {
    if (e.target && (
        e.target.classList.contains('sq-mat-hst-cb') ||
        e.target.classList.contains('sq-mat-hst-inclusive-cb') ||
        e.target.classList.contains('sq-labour-mode') ||
        e.target.id === 'sq-contingency-enabled' ||
        e.target.id === 'sq-labour-use-fixed' ||
        e.target.classList.contains('sq-labour-fixed-hst-inc-cb')
    )) {
      if (e.target.id === 'sq-labour-use-fixed') syncFixedLabourUI();
      recalcMaterials();
    }
  });

  modeRadios.forEach(function (r) {
    r.addEventListener('change', recalcMaterials);
  });

  list.querySelectorAll('.sq-material-row').forEach(initMaterialRow);

  syncFixedLabourUI();
  recalcMaterials();

  document.addEventListener('commute-expense:change', function (e) {
    var d = e.detail || {};
    lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
    recalcMaterials();
  });
})();
</script>
@endpush

@extends('admin.layouts.app')

@php
  use App\Models\ClientProjectQuotation;

  $isEdit = filled($quotation);

  // Project-context mode: this editor was opened from a project's
  // "Edit quotation" entry point (?return=project). Save stays on this
  // page; use Back to project to open the project's Quotation tab.
  $returnToProject = request('return') === 'project'
    && $isEdit
    && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
    ? $quotation->project
    : ($linkProject ?? null);

  $formAction = $isEdit
    ? route('admin.quotations.standard.update', $quotation).($returnToProject ? '?return=project' : '')
    : route('admin.quotations.standard.store');
  $formMethod = $isEdit ? 'PUT' : 'POST';

  $emptyMaterialRow = ['short_name' => '', 'description' => '', 'url' => '', 'image_url' => '', 'unit_price' => '', 'quantity' => '1', 'apply_hst' => true, 'hst_includes_unit' => false, 'catalog_material_id' => null, 'delivery_cad' => 0];
  $emptyWork = [
      'title' => '',
      'materials' => [$emptyMaterialRow],
      'labour_mode' => 'hours',
      'labour_quantity' => '',
      'labour_hourly_rate' => '65.00',
      'labour_use_fixed' => false,
      'labour_fixed_amount' => '',
      'labour_fixed_hst_included' => false,
  ];
  $workRows = old('works');
  if ($workRows === null && $isEdit) {
      $workRows = $quotation->resolvedWorks();
  }
  if (!is_array($workRows) || count($workRows) === 0) {
      $workRows = [$emptyWork];
  }

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
  }
  if ($contingencyPercent === '') {
      $contingencyPercent = '10';
  }

  if (old('include_labour_hst') !== null) {
      $includeLabourHst = filter_var(old('include_labour_hst'), FILTER_VALIDATE_BOOLEAN);
  } elseif ($isEdit) {
      $includeLabourHst = (bool) ($quotation->include_labour_hst ?? true);
  } else {
      $includeLabourHst = true;
  }

  $linkProject = $linkProject ?? null;

  $defaultDocType = $isEdit
      ? (($quotation->document_type ?? null) ?: ClientProjectQuotation::TYPE_QUOTATION)
      : ClientProjectQuotation::TYPE_QUOTATION;

  $pdfDetailMode = old(
      'pdf_detail_mode',
      $isEdit
          ? ($quotation->pdf_detail_mode ?? \App\Models\StandardQuotation::PDF_DETAIL_COMBINED)
          : \App\Models\StandardQuotation::PDF_DETAIL_COMBINED
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
  @php
    $toolbarGrand = $isEdit ? (float) ($quotation->grand_total ?? 0) : 0.0;
    $linkedCpq = $linkedCpq ?? null;
    $sendProject = $isEdit ? ($quotation->project ?? null) : null;
    $hasPdfContent = $linkedCpq && $linkedCpq->hasContent();
    $canDeliverByEmail = $sendProject && filled(trim((string) $sendProject->client_email));
    $canDeliverBySms = $sendProject && \App\Models\ClientProject::phoneDigitsForSms($sendProject->client_phone) !== null;
    $moceanReady = app(\App\Services\MoceanSmsService::class)->isConfigured();
    $canSendEmail = (bool) ($sendProject && $linkedCpq && $hasPdfContent && $canDeliverByEmail);
    $canSendSms = (bool) ($sendProject && $linkedCpq && $hasPdfContent && $canDeliverBySms && $moceanReady);
    $sendEmailConfirm = $canSendEmail
        ? 'This will generate a new PDF and email it to '.$sendProject->clientEmailRecipientsLabel().'. Continue?'
        : '';
    $sendSmsConfirm = $canSendSms
        ? 'Send an SMS to '.$sendProject->clientSmsRecipientsLabel().' with a secure link to view or download this quotation PDF?'
        : '';
    $sendEmailDisabledTitle = ! $isEdit
        ? 'Save the quotation first.'
        : (! $sendProject || ! $linkedCpq
            ? 'Link this quotation to a project and save before sending.'
            : (! $hasPdfContent
                ? 'Save with at least one line item before sending.'
                : (! $canDeliverByEmail
                    ? "Add the client's email address to email this quotation."
                    : 'Cannot send email yet.')));
    $sendSmsDisabledTitle = ! $isEdit
        ? 'Save the quotation first.'
        : (! $sendProject || ! $linkedCpq
            ? 'Link this quotation to a project and save before sending.'
            : (! $hasPdfContent
                ? 'Save with at least one line item before sending.'
                : (! $moceanReady
                    ? 'SMS gateway is not configured on the server (MOCEAN_API_TOKEN).'
                    : (! $canDeliverBySms
                        ? "Add the client's mobile phone number."
                        : 'Cannot send SMS yet.'))));
  @endphp
  <div class="br-toolbar sq-toolbar" aria-label="Quotation actions">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.standard.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All standard quotations
      </a>
      <a href="{{ route('admin.quotations.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-th"></i> Quotation types
      </a>
      @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
    </div>
    <div class="pq-sidebar-actions">
      @if ($isEdit)
        <a href="{{ route('admin.quotations.standard.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
           data-purchase-list-choice
           data-purchase-list-title="Print material list"
           title="Print material list"
           aria-label="Print material list">
          <i class="fa fa-print" aria-hidden="true"></i>
        </a>
        @include('admin.partials.quotation_view_pdf', ['iconOnly' => true])

        @if ($canSendEmail)
          <form action="{{ route('admin.client-projects.quotation.send', [$sendProject, $linkedCpq]) }}"
                method="post" class="cp-inline-form"
                onsubmit='return confirm(@json($sendEmailConfirm));'>
            @csrf
            <button type="submit"
                    class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                    title="Email quotation to client"
                    aria-label="Email quotation to client">
              <i class="fa fa-paper-plane" aria-hidden="true"></i>
            </button>
          </form>
        @else
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                  disabled
                  title="{{ $sendEmailDisabledTitle }}"
                  aria-label="Email quotation to client">
            <i class="fa fa-paper-plane" aria-hidden="true"></i>
          </button>
        @endif

        @if ($canSendSms)
          <form action="{{ route('admin.client-projects.quotation.sms', [$sendProject, $linkedCpq]) }}"
                method="post" class="cp-inline-form"
                onsubmit='return confirm(@json($sendSmsConfirm));'>
            @csrf
            <button type="submit"
                    class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                    title="SMS quotation link to client"
                    aria-label="SMS quotation link to client">
              <i class="fa fa-comment" aria-hidden="true"></i>
            </button>
          </form>
        @else
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                  disabled
                  title="{{ $sendSmsDisabledTitle }}"
                  aria-label="SMS quotation link to client">
            <i class="fa fa-comment" aria-hidden="true"></i>
          </button>
        @endif
      @endif
      <button type="submit"
              form="sq-standard-form"
              class="admin-btn admin-btn--primary admin-btn--sm admin-btn--icon"
              title="Save"
              aria-label="Save"
              data-hotkey-save>
        <i class="fa fa-save" aria-hidden="true"></i>
      </button>
      <div class="br-toolbar-grand" id="sq-toolbar-grand" title="Live grand total (incl. HST)">
        <span class="br-toolbar-grand__label">Grand total</span>
        <strong class="br-toolbar-grand__amount" id="sq-toolbar-grand-amount">{{ \App\Models\ClientProject::formatMoney($toolbarGrand) }}</strong>
      </div>
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

  <form action="{{ $formAction }}" method="post" id="sq-standard-form" data-quotation-editor
        data-quote-id="{{ $isEdit ? $quotation->id : 'new' }}"
        data-electrical-desc="{{ \App\Models\StandardQuotation::ELECTRICAL_LINE_DESCRIPTION }}">
    @csrf
    @if ($isEdit)
      @method('PUT')
    @endif
    @if ($linkProject)
      <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
    @endif

    @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
    @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'sq-standard-form'])
    @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'sq-standard-form'])

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
              <h5 class="sq-intro-option-title">
                Document type
                @include('admin.partials.info_tip', ['text' => 'You can switch between quotation and estimate at any time; the document number prefix updates when you save.'])
              </h5>
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
              @error('document_type') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sq-intro-option-block" id="sq-pdf-detail-block">
              <h5 class="sq-intro-option-title">
                Client PDF line items
                @include('admin.partials.info_tip', ['text' => 'Controls how material and labour appear on the client PDF.'])
              </h5>
              <div class="sq-intro-choice-row sq-intro-choice-row--pdf-detail" id="sq-pdf-detail-choices">
                <div class="sq-intro-choice-with-tip">
                  <label class="cp-checkbox-row">
                    <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_COMBINED }}"
                      @checked($pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_COMBINED)>
                    <span>Combined</span>
                  </label>
                  @include('admin.partials.info_tip', ['text' => 'Material and labour as one line (one line per job when there are several).'])
                </div>
                <div class="sq-intro-choice-with-tip">
                  <label class="cp-checkbox-row">
                    <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY }}"
                      @checked($pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY)>
                    <span>Summary</span>
                  </label>
                  @include('admin.partials.info_tip', ['text' => 'Two lines for the whole quotation: Material and Labour.'])
                </div>
                <div class="sq-intro-choice-with-tip">
                  <label class="cp-checkbox-row">
                    <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_DETAILED }}"
                      @checked($pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_DETAILED)>
                    <span>Detailed</span>
                  </label>
                  @include('admin.partials.info_tip', ['text' => 'Separate Material and Labour lines per job (not each SKU).'])
                </div>
              </div>
              @error('pdf_detail_mode') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sq-intro-option-block">
              <h5 class="sq-intro-option-title">
                Includes (PDF wording)
                @include('admin.partials.info_tip', ['text' => 'Controls the summary line on the client PDF (e.g. Labour and material or Labour).'])
              </h5>
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
              @error('includes_material') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>
        </section>

        <section class="sq-field-box">
          <h4 class="sq-field-box-title">Description of the work</h4>
          <div class="admin-form-group sq-intro-work-desc quotation-work-desc">
            <label for="sq-work-description" class="visually-hidden">Description of the work</label>
            <textarea id="sq-work-description" name="work_description" rows="8"
                      class="admin-input @error('work_description') is-invalid @enderror"
                      placeholder="Describe the service to be performed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? $quotation->work_description : '') }}</textarea>
            @include('admin.partials.quotation_work_description_ai', [
                'workDescriptionTextareaId' => 'sq-work-description',
                'aiContextFormId' => 'sq-standard-form',
                'aiQuotationType' => 'standard',
            ])
            <p class="admin-form-help sq-intro-option-help">Shown on the client PDF beneath the priced line items. Formatting (bold, lists, headings) is preserved.</p>
            @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </section>
      </div>
    </div>

    @php
      $workCount = count($workRows);
    @endphp
    <div class="sq-works" id="sq-works" data-count="{{ $workCount }}">
      @foreach ($workRows as $w => $work)
        @include('admin.quotations.standard._work_card', [
          'workIndex' => $w,
          'work' => $work,
          'workCount' => $workCount,
          'isOpen' => $w === $workCount - 1 || $errors->has('works.'.$w.'.*'),
          'includeContingency' => $w === $workCount - 1,
        ])
      @endforeach
    </div>

    <div class="sq-add-work-wrap">
      <button type="button" class="admin-btn admin-btn--ghost sq-add-work" id="sq-add-work">
        <i class="fa fa-plus"></i> Add another job
      </button>
    </div>

    {{-- Grand summary -------------------------------------------- --}}
    @include('admin.quotations._building', ['quotation' => $quotation])
    @include('admin.quotations._commute_debris_row')
    @include('admin.quotations._payment_schedule', ['quotation' => $quotation])

    <div class="admin-card sq-grand-card">
      <h3 class="sq-grand-card__title">Totals</h3>
      <div class="sq-grand-rows">
        <section class="sq-grand-section" id="sq-g-jobs-wrap" hidden>
          <h4 class="sq-grand-section__title">Jobs</h4>
          <div class="sq-grand-section__body" id="sq-g-jobs"></div>
        </section>
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
            <div class="sq-grand-row sq-grand-row--sub" id="sq-g-labour-before-building-row" hidden>
              <span>Labour (before building access)</span>
              <span id="sq-g-labour-before-building">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--sub" id="sq-g-building-row" hidden>
              <span>Building access (<span id="sq-g-building-pct">30</span>%)</span>
              <span id="sq-g-building">+CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--sub">
              <span>Subtotal (before HST)</span>
              <span id="sq-g-labour">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--sub sq-grand-row--labour-hst{{ $includeLabourHst ? '' : ' sq-grand-row--labour-hst-excluded' }}" id="sq-g-labour-hst-row">
              <label class="admin-checkbox sq-labour-hst-toggle" for="sq-include-labour-hst">
                <input type="hidden" name="include_labour_hst" value="0">
                <input type="checkbox"
                       name="include_labour_hst"
                       value="1"
                       id="sq-include-labour-hst"
                       class="admin-checkbox__input"
                       title="Uncheck to leave HST off labour."
                       @checked($includeLabourHst)>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">HST (13%)</span>
              </label>
              <span id="sq-g-labour-hst">CA$0.00</span>
            </div>
            <div class="sq-grand-row sq-grand-row--section-total">
              <span id="sq-g-labour-total-label">{{ $includeLabourHst ? 'Total labour with HST' : 'Total labour' }}</span>
              <span id="sq-g-labour-total">CA$0.00</span>
            </div>
          </div>
        </section>

        <div class="sq-grand-row sq-grand-row--commute is-hidden" id="sq-g-commute-row">
          <span>Commute expense <span class="admin-form-help">(incl. HST)</span></span>
          <span id="sq-g-commute">CA$0.00</span>
        </div>
        <div class="sq-grand-row sq-grand-row--commute is-hidden" id="sq-g-debris-row">
          <span>Debris removal <span class="admin-form-help">(incl. HST)</span></span>
          <span id="sq-g-debris">CA$0.00</span>
        </div>

        <div class="sq-grand-row sq-grand-row--final">
          <span>Grand total</span>
          <span id="sq-g-grand">CA$0.00</span>
        </div>
      </div>
    </div>

    <div class="sq-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>
        <i class="fa fa-save"></i> Save
      </button>
    </div>
  </form>

  @if ($isEdit && !$quotation->project)
    @php $linkable = $linkableProjects ?? collect(); @endphp
    @include('admin.quotations._link_to_project_card', [
        'quotation' => $quotation,
        'linkUrl' => route('admin.quotations.standard.link', $quotation),
        'createProjectUrl' => route('admin.quotations.standard.create-project', $quotation),
        'idPrefix' => 'sq',
        'projectNamePlaceholder' => 'Defaults to: Client name — Standard',
        'linkableProjects' => $linkable,
        'wrapAdminCard' => true,
        'title' => 'Connect to a project',
        'helpText' => 'Adds the <strong>pre-HST</strong> amounts to the project\'s <strong>Quotation</strong>. The project quotation\'s "Apply HST" toggle will then add HST to the taxable portion (no double taxation). If some materials are HST-exempt, two lines are added — one taxable, one exempt.',
        'emptyProjectsMessage' => 'No active projects to link.',
        'projectPickerId' => 'sq-link-project',
        'existingFormId' => 'sq-link-existing-form',
        'createHiddenLineDescriptionId' => 'sq-create-project-line-desc',
        'createFormOnsubmit' => "document.getElementById('sq-create-project-line-desc').value=(document.getElementById('sq-link-line-desc')&&document.getElementById('sq-link-line-desc').value)||'Standard quotation';",
        'sharedLineDescription' => [
            'id' => 'sq-link-line-desc',
            'form' => 'sq-link-existing-form',
            'label' => 'Line description on project quotation',
            'value' => old('link_line_description', 'Standard quotation'),
            'placeholder' => 'Standard quotation',
            'help' => 'Shown as the line item text on the project quotation. Saved as you type it.',
        ],
    ])
  @endif

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>
  <template id="sq-material-template">
    @include('admin.quotations.standard._material_row', [
      'workIndex' => '__W__',
      'i' => '__I__',
      'row' => [
        'short_name' => '',
        'description' => '',
        'url' => '',
        'image_url' => '',
        'unit_price' => '',
        'quantity' => '1',
        'apply_hst' => true,
        'hst_includes_unit' => false,
        'catalog_material_id' => null,
        'delivery_cad' => 0,
      ],
    ])
  </template>
  <template id="sq-work-template">
    @include('admin.quotations.standard._work_card', [
      'workIndex' => '__W__',
      'work' => [
        'title' => '',
        'materials' => [[
          'short_name' => '',
          'description' => '',
          'url' => '',
          'image_url' => '',
          'unit_price' => '',
          'quantity' => '1',
          'apply_hst' => true,
          'hst_includes_unit' => false,
          'catalog_material_id' => null,
          'delivery_cad' => 0,
        ]],
        'labour_mode' => 'hours',
        'labour_quantity' => '',
        'labour_hourly_rate' => '65.00',
        'labour_use_fixed' => false,
        'labour_fixed_amount' => '',
        'labour_fixed_hst_included' => false,
      ],
      'workCount' => 2,
      'isOpen' => true,
    ])
  </template>
@endsection

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'sq-work-description',
  'formId' => 'sq-standard-form',
])

@push('scripts')
<script src="{{ asset('js/admin-standard-quotation.js') }}?v={{ @filemtime(public_path('js/admin-standard-quotation.js')) ?: time() }}"></script>
@endpush

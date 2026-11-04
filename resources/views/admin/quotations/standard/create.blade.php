@extends('admin.layouts.app')

@php
  use App\Models\ClientProjectQuotation;
  use App\Support\QuotationSharedLabourRates;

  $isEdit = filled($quotation);
  $defaultLabourHourly = QuotationSharedLabourRates::ownerHourlyFormattedForNewQuotation();

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
      'labour_hourly_rate' => $defaultLabourHourly,
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
      : null;

  $pdfDetailMode = old('pdf_detail_mode');
  if ($pdfDetailMode === null && $isEdit) {
      $pdfDetailMode = $quotation->pdf_detail_mode ?? \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY;
  }

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
  @include('admin.partials.quotation_editor_header', [
    'title' => $isEdit ? 'Edit standard quotation '.$quotation->quote_number : 'New standard quotation',
    'typeLabel' => 'Standard',
    'project' => $projectForReturn,
    'clientName' => old('client_name', $isEdit ? ($quotation->client_name ?? '') : ($linkProject?->client_name ?? '')),
    'listUrl' => route('admin.quotations.standard.index', $preservePid ? ['project' => $preservePid] : []),
    'cancelUrl' => route('admin.quotations.standard.index', $preservePid ? ['project' => $preservePid] : []),
    'formId' => 'sq-standard-form',
    'saveId' => 'sq-btn-save',
    'saveHotkey' => true,
    'draftId' => 'st-btn-draft',
  ])
  @include('admin.partials.quotation_editor_more_actions', [
    'isEdit' => $isEdit,
    'quotation' => $quotation ?? null,
    'linkedCpq' => $linkedCpq ?? null,
    'purchaseListUrl' => $isEdit ? route('admin.quotations.standard.purchase-list', $quotation) : null,
    'purchaseListTitle' => 'Print material list',
    'showHubLink' => ! $projectForReturn,
    'hubUrl' => route('admin.quotations.index', $preservePid ? ['project' => $preservePid] : []),
    'grandTotalId' => 'sq-toolbar-grand',
    'grandTotalValue' => $toolbarGrand,
    'email' => $isEdit ? [
      'can' => $canSendEmail,
      'action' => $sendProject && $linkedCpq
          ? route('admin.client-projects.quotation.send', [$sendProject, $linkedCpq])
          : null,
      'confirm' => $sendEmailConfirm,
      'disabledTitle' => $sendEmailDisabledTitle,
    ] : null,
    'sms' => $isEdit ? [
      'can' => $canSendSms,
      'action' => $sendProject && $linkedCpq
          ? route('admin.client-projects.quotation.sms', [$sendProject, $linkedCpq])
          : null,
      'confirm' => $sendSmsConfirm,
      'disabledTitle' => $sendSmsDisabledTitle,
    ] : null,
  ])

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
        data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}"
        data-qh-client-share="{{ $isEdit && !($quotation->is_draft ?? false) && ! empty($linkedCpq) ? '1' : '0' }}"
        data-quote-id="{{ $isEdit ? $quotation->id : 'new' }}"
        data-electrical-desc="{{ \App\Models\StandardQuotation::ELECTRICAL_LINE_DESCRIPTION }}">
    @csrf
    @include('admin.partials.once_submit')
    @if ($isEdit)
      @method('PUT')
    @endif
    <input type="hidden" name="save_intent" id="st-save-intent" value="publish">
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

        <section class="sq-field-box" id="sq-pdf-document-section">
          <h4 class="sq-field-box-title">PDF &amp; document</h4>
          @if (! $isEdit)
            <div class="admin-flash admin-flash--warning sq-pdf-setup-banner" id="sq-pdf-setup-banner" role="status">
              Choose <strong>document type</strong>, <strong>client PDF line items</strong>, and at least one <strong>includes</strong> option (material or labour) before you save or generate a client PDF.
            </div>
          @endif
          <div class="sq-intro-pdf-options">
            <div class="sq-intro-option-block">
              <h5 class="sq-intro-option-title">
                Document type
                @include('admin.partials.info_tip', [
                  'tipText' => 'You can switch between quotation and estimate at any time; the document number prefix updates when you save.',
                  'tipLabel' => 'Document type',
                ])
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
              <p class="admin-form-error sq-pdf-field-error" id="sq-error-document_type" @if (! $errors->has('document_type')) hidden @endif>{{ $errors->first('document_type') }}</p>
            </div>

            <div class="sq-intro-option-block" id="sq-pdf-detail-block">
              <h5 class="sq-intro-option-title">
                Client PDF line items
                @include('admin.partials.info_tip', [
                  'tipHtml' => 'Controls how material and labour appear on the client PDF.<br><strong>Combined:</strong> Material and labour as one line (one line per job when there are several).<br><strong>Summary:</strong> Two lines for the whole quotation: Material and Labour.<br><strong>Detailed:</strong> Separate Material and Labour lines per job (not each SKU).',
                  'tipLabel' => 'Client PDF line items',
                ])
              </h5>
              <div class="sq-intro-choice-row sq-intro-choice-row--pdf-detail" id="sq-pdf-detail-choices">
                <label class="cp-checkbox-row">
                  <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_COMBINED }}"
                    @checked($pdfDetailMode !== null && $pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_COMBINED)>
                  <span>Combined</span>
                </label>
                <label class="cp-checkbox-row">
                  <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY }}"
                    @checked($pdfDetailMode !== null && $pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_SUMMARY)>
                  <span>Summary</span>
                </label>
                <label class="cp-checkbox-row">
                  <input type="radio" name="pdf_detail_mode" value="{{ \App\Models\StandardQuotation::PDF_DETAIL_DETAILED }}"
                    @checked($pdfDetailMode !== null && $pdfDetailMode === \App\Models\StandardQuotation::PDF_DETAIL_DETAILED)>
                  <span>Detailed</span>
                </label>
              </div>
              <p class="admin-form-error sq-pdf-field-error" id="sq-error-pdf_detail_mode" @if (! $errors->has('pdf_detail_mode')) hidden @endif>{{ $errors->first('pdf_detail_mode') }}</p>
            </div>

            <div class="sq-intro-option-block">
              <h5 class="sq-intro-option-title">
                Includes (PDF wording)
                @include('admin.partials.info_tip', [
                  'tipText' => 'Controls the bold PDF headline (Material / Labour / both). The priced summary line always names what is on the quote (e.g. Labour and material when both are priced), even if a checkbox is off.',
                  'tipLabel' => 'Includes (PDF wording)',
                ])
              </h5>
              @php
                $matDef = $isEdit ? (($quotation->includes_material ?? true) ? '1' : '0') : '0';
                $labDef = $isEdit ? (($quotation->includes_labour ?? true) ? '1' : '0') : '0';
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
              <p class="admin-form-error sq-pdf-field-error" id="sq-error-includes_pdf" @if (! $errors->has('includes_material') && ! $errors->has('includes_labour')) hidden @endif>{{ $errors->first('includes_material') ?: $errors->first('includes_labour') }}</p>
            </div>
          </div>
        </section>

        <section class="sq-field-box">
          <h4 class="sq-field-box-title">
            Description of the work
            <x-admin.info-tip label="Description of the work">Shown on the client PDF beneath the priced line items. Formatting (bold, lists, headings) is preserved.</x-admin.info-tip>
          </h4>
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
      <button type="submit" class="admin-btn admin-btn--primary">
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
        'labour_hourly_rate' => $defaultLabourHourly,
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
<script>
(function () {
  var form = document.getElementById('sq-standard-form');
  var intent = document.getElementById('st-save-intent');
  var draftBtn = document.getElementById('st-btn-draft');
  if (!form || !intent || !draftBtn) return;
  draftBtn.addEventListener('click', function () {
    intent.value = 'draft';
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.submit();
  });
})();
</script>
@endpush

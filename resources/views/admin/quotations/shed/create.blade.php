@extends('admin.layouts.app')

@php
  use App\Services\ShedQuotationCalculator;
  use App\Support\QuotationSharedLabourRates;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : ($linkProject ?? null);
  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New shed quotation';
  $formAction = ($isEdit ? route('admin.quotations.shed.update', $quotation) : route('admin.quotations.shed.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : ShedQuotationCalculator::defaultInputs();
  foreach (ShedQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  if (! $isEdit) {
      $inputs['owner_hourly_rate'] = QuotationSharedLabourRates::ownerHourlyForNewQuotation();
  }
  $inputs['roof_type'] = ShedQuotationCalculator::normalizeRoofType($inputs['roof_type'] ?? 'asphalt');
  $inputs['siding'] = ShedQuotationCalculator::normalizeSiding($inputs['siding'] ?? 'vinyl');
  $sectionList = $sections ?? ShedQuotationCalculator::SECTIONS;
  $previewLabor = is_array($initialPreview['labor'] ?? null) ? $initialPreview['labor'] : [];
  $previewMats = array_merge(
      is_array($initialPreview['materials'] ?? null) ? $initialPreview['materials'] : [],
      is_array($initialPreview['extra_materials'] ?? null) ? $initialPreview['extra_materials'] : []
  );
  $provideSections = old('client_provides_sections', $inputs['client_provides_sections'] ?? []);
  if (! is_array($provideSections)) {
      $provideSections = [];
  }
  $extraRows = old('extra_materials', $inputs['extra_materials'] ?? []);
  if (! is_array($extraRows)) {
      $extraRows = [];
  }
  $enabledCount = count($sectionList);
@endphp

@section('heading', $title)

@section('content')
  @include('admin.partials.quotation_editor_header', [
    'title' => $title,
    'typeLabel' => 'Shed',
    'project' => $projectForReturn,
    'clientName' => old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name),
    'listUrl' => route('admin.quotations.shed.index', $preservePid ? ['project' => $preservePid] : []),
    'cancelUrl' => $projectForReturn
      ? route('admin.client-projects.show', $projectForReturn).'#quotation'
      : route('admin.quotations.shed.index', $preservePid ? ['project' => $preservePid] : []),
    'priceConfigUrl' => route('admin.quotations.shed.price-configuration', $preservePid ? ['project' => $preservePid] : []),
    'formId' => 'sh-main-form',
    'saveId' => 'sh-btn-publish',
    'draftId' => 'sh-btn-draft',
    'draftHotkey' => true,
    'saveLabel' => 'Publish',
    'wizard' => [
      'navId' => 'sh-step-nav',
      'prevId' => 'sh-prev',
      'nextId' => 'sh-next',
      'statusId' => 'sh-step-status',
      'count' => $enabledCount,
      'hidden' => $isEdit,
    ],
  ])
  @include('admin.partials.quotation_editor_more_actions', [
    'isEdit' => $isEdit,
    'quotation' => $quotation ?? null,
    'linkedCpq' => $linkedCpq ?? null,
  ])
  <div class="br-project-context-slot">
    @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
  </div>
  @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'sh-main-form'])
  @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'sh-main-form'])

  @if ($isEdit && $quotation->project)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation"><strong>{{ $quotation->project->name }}</strong></a>
    </div>
  @endif

  @if ($errors->has('calculation'))
    <div class="admin-flash admin-flash--danger">{{ $errors->first('calculation') }}</div>
  @endif

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ $formAction }}" id="sh-main-form" class="pq-layout br-layout {{ $isEdit ? 'br-layout--edit' : 'br-layout--create' }}"
        data-br-mode="{{ $isEdit ? 'edit' : 'create' }}"
        data-quotation-editor
        data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}"
        data-qh-client-share="{{ $isEdit && !($quotation->is_draft ?? false) && ! empty($linkedCpq) ? '1' : '0' }}">
    @csrf
    @include('admin.partials.once_submit')
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="save_intent" id="sh-save-intent" value="publish">
    <input type="hidden" name="owner_hourly_rate" value="{{ old('owner_hourly_rate', number_format((float) ($inputs['owner_hourly_rate'] ?? QuotationSharedLabourRates::ownerHourlyForNewQuotation()), 2, '.', '')) }}">
    <input type="hidden" name="helper_hourly_rate" value="{{ old('helper_hourly_rate', number_format((float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate), 2, '.', '')) }}">

    <div class="br-shell">
      <nav class="br-progress" aria-label="Quotation sections">
        @php $n = 0; @endphp
        @foreach ($sectionList as $key => $label)
          @php $n++; @endphp
          <button type="button" class="br-progress__item{{ $n === 1 ? ' is-current' : '' }}" data-sh-goto="{{ $key }}">
            <span class="br-progress__num">{{ $n }}</span>
            <span class="br-progress__label">{{ $label }}</span>
          </button>
        @endforeach
        <button type="button" class="br-progress__item" data-sh-goto="review">
          <span class="br-progress__num"><i class="fa fa-list-alt" aria-hidden="true"></i></span>
          <span class="br-progress__label">Review &amp; totals</span>
        </button>
      </nav>

      <div class="br-main">
        <div class="admin-card fl-step-card">
          <h4>Client</h4>
          <div class="admin-form-group">
            <span class="admin-label-with-tip">
              <label for="sh-client-name">Client name</label>
              <x-admin.info-tip label="Client name">Name printed on the quotation PDF and used when linking this quote to a project.</x-admin.info-tip>
            </span>
            <input type="text" class="admin-input" id="sh-client-name" name="client_name" maxlength="160"
                   value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}" required>
          </div>
          <div class="admin-form-group quotation-work-desc">
            <span class="admin-label-with-tip">
              <label for="sh-work-description">Description</label>
              <x-admin.info-tip label="Description">Optional scope summary on the client PDF. Improve with AI after you fill the wizard. Formatting (bold, lists, headings) is preserved on the client PDF.</x-admin.info-tip>
            </span>
            <textarea class="admin-input" id="sh-work-description" name="work_description" rows="8"
                      placeholder="Describe the service to be performed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
            @include('admin.partials.quotation_work_description_ai', [
                'workDescriptionTextareaId' => 'sh-work-description',
                'aiContextFormId' => 'sh-main-form',
                'aiQuotationType' => 'shed',
            ])
          </div>
        </div>

        @include('admin.quotations.shed._wizard_sections', [
            'inputs' => $inputs,
            'settings' => $settings,
            'isEdit' => $isEdit,
        ])

        <details class="br-acc admin-card" data-br-step="review" @if ($isEdit) open @endif>
          <summary class="br-acc__summary">Review &amp; totals</summary>
          <div class="br-acc__body">
            @include('admin.partials.checkbox', [
              'name' => 'client_provides_all',
              'hiddenFallback' => true,
              'checked' => (bool) old('client_provides_all', $inputs['client_provides_all'] ?? false),
              'label' => '<strong>Client provides all materials</strong>',
              'info' => 'Labor only — material cost drops out of the total.',
              'inputClass' => 'admin-checkbox__input sh-preview',
              'id' => 'sh-client-all',
            ])
            <div id="sh-provide-wrap">
            <fieldset class="br-provide-grid">
              <legend>Or client provides materials for</legend>
              @foreach ($sectionList as $key => $label)
                @if ($key === 'type' || $key === 'size')
                  @continue
                @endif
                @include('admin.partials.checkbox', [
                  'name' => 'client_provides_sections[]',
                  'value' => $key,
                  'checked' => in_array($key, $provideSections, true),
                  'label' => $label,
                  'info' => 'Client buys materials for '.$label.'; labor stays.',
                  'inputClass' => 'admin-checkbox__input sh-preview',
                ])
              @endforeach
            </fieldset>
            </div>

            <h4 class="br-review-heading">Labor hours</h4>
            <div class="admin-table-scroll">
              <table class="admin-table br-review-table">
                <thead>
                  <tr>
                    <th>Trade</th>
                    <th>Hours</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody id="sh-labor-rows">
                  @forelse ($previewLabor as $row)
                    <tr data-sh-labor-key="{{ $row['key'] }}">
                      <td>{{ $row['label'] }}@if (filled(trim((string) ($row['detail'] ?? '')))) <span class="admin-form-help">- {{ $row['detail'] }}</span>@endif</td>
                      <td data-sh-labor-hrs>{{ number_format((float) ($row['hours'] ?? 0), 2) }}</td>
                      <td class="cp-col-amount" data-sh-labor-amt>{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="3" class="admin-form-help">Enter length and width to generate labor lines.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Materials</h4>
            <div class="admin-table-scroll">
              <table class="admin-table br-review-table">
                <thead>
                  <tr>
                    <th>Material</th>
                    <th>Qty</th>
                    <th>Unit price</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody id="sh-mat-rows">
                  @forelse ($previewMats as $row)
                    @if (str_starts_with((string) ($row['key'] ?? ''), 'extra_'))
                      @continue
                    @endif
                    <tr data-sh-mat-key="{{ $row['key'] }}">
                      <td>{{ $row['label'] }}@if (! empty($row['client_provided'])) <span class="admin-form-help"> · client provided</span>@endif</td>
                      <td data-sh-mat-qty>{{ number_format((float) ($row['qty'] ?? 0), 2) }}</td>
                      <td data-sh-mat-price>{{ \App\Models\ClientProject::formatMoney($row['unit_price'] ?? 0) }}</td>
                      <td class="cp-col-amount" data-sh-mat-amt>{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after size is entered.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Extra materials</h4>
            <div id="sh-extra-list">
              @foreach ($extraRows as $ei => $erow)
                @include('admin.quotations.shed._extra_material_row', ['i' => $ei, 'row' => is_array($erow) ? $erow : []])
              @endforeach
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="sh-extra-add" style="margin-top:8px;">
              <i class="fa fa-plus"></i> Add extra material
            </button>
            <template id="sh-extra-template">
              @include('admin.quotations.shed._extra_material_row', ['i' => '__I__', 'row' => []])
            </template>

            @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
            @include('admin.quotations._commute_debris_row')
            @include('admin.quotations._additional_costs', ['quotation' => $quotation])

            @if (! $isEdit)
              @include('admin.quotations._link_to_project_save_first', [
                  'linkProject' => $linkProject ?? null,
                  'wrapAdminCard' => false,
              ])
            @endif
          </div>
        </details>
      </div>
    </div>

    <aside class="br-aside">
      <div class="pq-summary-card">
        <div class="qh-priced-head">
          <h4>Live breakdown</h4>
        </div>
        <div id="sh-live-root">
          <p class="admin-form-help" id="sh-live-empty">Enter length and width to see totals.</p>
          <div id="sh-live-body" hidden></div>
        </div>
      </div>
    </aside>
    @include('admin.partials.quotation_editor_footer', [
      'formId' => 'sh-main-form',
      'amountId' => 'sh-footer-grand-amount',
    ])
  </form>

  @if ($isEdit && ! $quotation->isLinked())
    @include('admin.quotations._link_to_project_card', [
        'quotation' => $quotation,
        'linkUrl' => route('admin.quotations.shed.link', $quotation),
        'createProjectUrl' => route('admin.quotations.shed.create-project', $quotation),
        'idPrefix' => 'sh',
        'projectNamePlaceholder' => $quotation->summaryDescription(),
        'linkableProjects' => $linkableProjects,
        'wrapAdminCard' => true,
        'cardStyle' => 'margin-top:16px;',
        'helpText' => 'Add this shed as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).',
        'createLineDescription' => 'field',
        'usePlacesAddress' => true,
    ])
  @endif
@endsection

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'sh-work-description',
  'formId' => 'sh-main-form',
])

@push('scripts')
  <script>
    window.SH_QUOTATION = {
      previewUrl: @json(
        $isEdit && $quotation
          ? route('admin.quotations.shed.calculate-existing', $quotation)
          : route('admin.quotations.shed.calculate')
      ),
      initialPreview: @json($initialPreview ?? null),
      isEdit: @json($isEdit),
      sectionKeys: @json(array_keys($sectionList)),
    };
  </script>
  <script src="{{ asset('js/admin-shed-quotation.js') }}?v={{ @filemtime(public_path('js/admin-shed-quotation.js')) ?: time() }}"></script>
@endpush

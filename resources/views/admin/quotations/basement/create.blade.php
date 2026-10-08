@extends('admin.layouts.app')

@php
  use App\Services\BasementQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : ($linkProject ?? null);
  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New basement renovation quotation';
  $formAction = ($isEdit ? route('admin.quotations.basement.update', $quotation) : route('admin.quotations.basement.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : BasementQuotationCalculator::defaultInputs();
  foreach (BasementQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  $sectionList = $sections ?? BasementQuotationCalculator::SECTIONS;
  $scopePreset = BasementQuotationCalculator::normalizePreset(old('scope_preset', $inputs['scope_preset'] ?? 'full'));
  if (! $isEdit) {
      foreach (BasementQuotationCalculator::electricalDefaultsForPreset($scopePreset) as $elecKey => $elecVal) {
          if (old($elecKey) === null) {
              $inputs[$elecKey] = $elecVal;
          }
      }
      if ($scopePreset === 'wall') {
          if (old('ceiling_type') === null) {
              $inputs['ceiling_type'] = 'none';
          }
          if (old('include_touchups') === null) {
              $inputs['include_touchups'] = false;
          }
          if (old('include_cleaning') === null) {
              $inputs['include_cleaning'] = false;
          }
      }
  }
  $enabledNow = BasementQuotationCalculator::enabledSections([
      'scope_preset' => $scopePreset,
      'enabled_sections' => old('enabled_sections', $inputs['enabled_sections'] ?? null),
  ]);
  $enabledCount = max(1, count($enabledNow));
  $presetSections = [
      'full' => BasementQuotationCalculator::presetEnabledSections('full'),
      'bathroom' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
      'wall' => BasementQuotationCalculator::presetEnabledSections('wall'),
      'custom' => BasementQuotationCalculator::presetEnabledSections('custom'),
  ];
  $electricalByPreset = [];
  foreach (array_keys(BasementQuotationCalculator::SCOPE_PRESETS) as $presetKey) {
      $electricalByPreset[$presetKey] = BasementQuotationCalculator::electricalDefaultsForPreset($presetKey);
  }
  $previewLabor = is_array($initialPreview['labor'] ?? null) ? $initialPreview['labor'] : [];
  $previewMats = array_merge(
      is_array($initialPreview['materials'] ?? null) ? $initialPreview['materials'] : [],
      is_array($initialPreview['extra_materials'] ?? null) ? $initialPreview['extra_materials'] : []
  );
  $provideSections = old('client_provides_sections', $inputs['client_provides_sections'] ?? []);
  if (! is_array($provideSections)) {
      $provideSections = [];
  }
  $provideBathItems = old('client_provides_bath_items', $inputs['client_provides_bath_items'] ?? []);
  if (! is_array($provideBathItems)) {
      $provideBathItems = [];
  }
  $filledKeys = ($isEdit && $quotation) ? $quotation->clientFilledKeys() : [];
  $extraRows = old('extra_materials', $inputs['extra_materials'] ?? []);
  if (! is_array($extraRows)) {
      $extraRows = [];
  }
@endphp

@section('heading', $title)

@section('content')
  <div class="br-project-context-slot">
    @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
  </div>
  @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'br-main-form'])
  @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'br-main-form'])

  @if ($isEdit && $quotation->project)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation"><strong>{{ $quotation->project->name }}</strong></a>
    </div>
  @endif

  @include('admin.quotations.basement._intake_bar', ['quotation' => $isEdit ? $quotation : null])

  @if ($errors->has('calculation'))
    <div class="admin-flash admin-flash--danger">{{ $errors->first('calculation') }}</div>
  @endif

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ $formAction }}" id="br-main-form" class="pq-layout br-layout {{ $isEdit ? 'br-layout--edit' : 'br-layout--create' }}"
        data-br-mode="{{ $isEdit ? 'edit' : 'create' }}"
        data-quotation-editor
        data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="save_intent" id="br-save-intent" value="publish">

    @php
      $toolbarGrand = (float) (($initialPreview['summary']['grand_total'] ?? 0));
      $linkedCpq = $linkedCpq ?? null;
      $sendProject = $isEdit ? ($quotation->project ?? null) : null;
      $hasPdfContent = $linkedCpq && $linkedCpq->hasContent();
      $canDeliverByEmail = $sendProject && filled(trim((string) $sendProject->client_email));
      $canSendEmail = (bool) ($sendProject && $linkedCpq && $hasPdfContent && $canDeliverByEmail);
      $sendEmailConfirm = $canSendEmail
          ? 'This will generate a new PDF and email it to '.$sendProject->clientEmailRecipientsLabel().'. Continue?'
          : '';
      $sendEmailDisabledTitle = ! $isEdit
          ? 'Save the quotation first.'
          : (! $sendProject || ! $linkedCpq
              ? 'Link this quotation to a project and save before sending.'
              : (! $hasPdfContent
                  ? 'Save the quotation before sending.'
                  : (! $canDeliverByEmail
                      ? "Add the client's email address to email this quotation."
                      : 'Cannot send email yet.')));
    @endphp
    <div class="br-toolbar" aria-label="Quotation actions">
      <div class="admin-toolbar-group">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-btn-draft">Save as draft</button>
        <a href="{{ route('admin.quotations.basement.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-cog"></i> Price configuration
        </a>
        @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
        <a href="{{ route('admin.quotations.basement.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to list
        </a>
        <a href="{{ route('admin.quotations.basement.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      </div>
      <div class="pq-sidebar-actions br-toolbar-actions">
        @if ($isEdit)
          @include('admin.partials.quotation_view_pdf', ['iconOnly' => true])
          @if ($canSendEmail)
            <button type="submit"
                    form="br-email-quote-form"
                    class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                    title="Email quotation to client"
                    aria-label="Email quotation to client"
                    onclick='return confirm(@json($sendEmailConfirm));'>
              <i class="fa fa-paper-plane" aria-hidden="true"></i>
            </button>
          @else
            <span class="br-toolbar-disabled-tip" title="{{ $sendEmailDisabledTitle }}">
              <button type="button"
                      class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                      disabled
                      aria-label="Email quotation to client. {{ $sendEmailDisabledTitle }}">
                <i class="fa fa-paper-plane" aria-hidden="true"></i>
              </button>
            </span>
          @endif
        @endif
        <button type="submit"
                class="admin-btn admin-btn--primary admin-btn--sm admin-btn--icon"
                id="br-btn-publish"
                title="Save quotation"
                aria-label="Save quotation">
          <i class="fa fa-save" aria-hidden="true"></i>
        </button>
      </div>
    </div>
    <div class="br-shell">
      <nav class="br-progress" aria-label="Quotation sections">
        @php $n = 0; @endphp
        @foreach ($sectionList as $key => $label)
          @php $n++; @endphp
          <button type="button" class="br-progress__item{{ $n === 1 ? ' is-current' : '' }}" data-br-goto="{{ $key }}"
                  @if ($key !== 'overview' && ! in_array($key, $enabledNow, true)) hidden
                  @elseif ($scopePreset === 'bathroom' && in_array($key, ['electrical', 'plumbing', 'framing'], true)) hidden
                  @endif>
            <span class="br-progress__num">{{ $n }}</span>
            <span class="br-progress__label">{{ $label }}</span>
          </button>
        @endforeach
        <button type="button" class="br-progress__item" data-br-goto="review">
          <span class="br-progress__num"><i class="fa fa-list-alt" aria-hidden="true"></i></span>
          <span class="br-progress__label">Review &amp; totals</span>
        </button>
      </nav>

      <div class="br-main">
        <div class="admin-card fl-step-card br-client-card">
          <div class="br-client-card__row">
            <div class="admin-form-group">
              <label for="br-client-name">@include('admin.partials.info_tip', ['text' => 'Name printed on the quotation PDF and used when linking this quote to a project.']) Client name</label>
              <input type="text" class="admin-input" id="br-client-name" name="client_name" maxlength="160"
                     value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}" required>
            </div>
          </div>
          <details class="br-desc-acc" @if ($isEdit && filled($quotation->work_description)) open @endif>
            <summary class="br-desc-acc__summary">Project description <span class="admin-form-help">(optional — client PDF)</span></summary>
            <div class="admin-form-group quotation-work-desc">
              <label for="br-work-description" class="visually-hidden">Project description</label>
              <textarea class="admin-input" id="br-work-description" name="work_description" rows="4"
                        placeholder="Optional scope summary for the client PDF.">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
              @include('admin.partials.quotation_work_description_ai', [
                  'workDescriptionTextareaId' => 'br-work-description',
                  'aiContextFormId' => 'br-main-form',
                  'aiQuotationType' => 'basement',
              ])
            </div>
          </details>
        </div>

        @include('admin.quotations.basement._wizard_sections', [
            'inputs' => $inputs,
            'settings' => $settings,
            'isEdit' => $isEdit,
            'scopePreset' => $scopePreset,
            'enabledNow' => $enabledNow,
            'enabledCount' => $enabledCount,
            'filledKeys' => $filledKeys,
            'layoutClientProjectId' => $isEdit
                ? ($quotation->client_project_id ?? null)
                : optional($linkProject)->id,
        ])

        <details class="br-acc admin-card" data-br-step="review" @if ($isEdit) open @endif>
          <summary class="br-acc__summary">Review, materials &amp; live edits</summary>
          <div class="br-acc__body">
            <p class="admin-form-help">Edit hours, quantities, or unit prices below. Totals recalculate as you type. Enter 0 on an auto-filled quantity in a previous section to skip that trade.</p>

            @include('admin.partials.checkbox', [
              'name' => 'client_provides_all',
              'hiddenFallback' => true,
              'checked' => (bool) old('client_provides_all', $inputs['client_provides_all'] ?? false),
              'label' => '<strong>Client provides all materials</strong> for the entire project',
              'info' => 'Labour only — you do not supply materials. Material cost drops out of the total.',
              'inputClass' => 'admin-checkbox__input br-preview',
              'id' => 'br-client-all',
            ])
            <div id="br-provide-wrap">
            <fieldset class="br-provide-grid" id="br-provide-sections">
              <legend>Or client provides materials for specific sections</legend>
              @foreach ($sectionList as $key => $label)
                @if ($key === 'overview' || $key === 'bathroom')
                  @continue
                @endif
                @include('admin.partials.checkbox', [
                  'name' => 'client_provides_sections[]',
                  'value' => $key,
                  'checked' => in_array($key, $provideSections, true),
                  'label' => $label,
                  'info' => 'Client buys the materials for '.$label.'; you still carry the labour for that trade.',
                  'inputClass' => 'admin-checkbox__input br-preview',
                  'clientFilled' => $key === 'flooring' && in_array('client_provides_sections', $filledKeys, true),
                ])
              @endforeach
            </fieldset>
            <fieldset class="br-bath-provide" id="br-provide-bath-items">
              <legend class="br-bath-provide__title">
                @include('admin.partials.info_tip', ['text' => 'Client buys these fixtures; you still install them. Tile and fan stay on the Bathroom step. Bathroom and living-area lights are electrician scope (priced on Electrical lines).'])
                @include('admin.quotations.basement._client_filled', ['key' => 'client_provides_bath_items', 'filledKeys' => $filledKeys])
                Bathroom items
              </legend>
              <div class="br-bath-provide__grid">
                @foreach (\App\Services\BasementQuotationCalculator::clientProvidesBathItemLabels() as $itemKey => $itemLabel)
                  @include('admin.partials.checkbox', [
                    'name' => 'client_provides_bath_items[]',
                    'value' => $itemKey,
                    'checked' => in_array($itemKey, $provideBathItems, true),
                    'label' => $itemLabel,
                    'info' => 'Client supplies the '.$itemLabel.'; material cost drops out. Labour stays.',
                    'inputClass' => 'admin-checkbox__input br-preview',
                    'clientFilled' => in_array('client_provides_bath_items', $filledKeys, true) && in_array($itemKey, $provideBathItems, true),
                  ])
                @endforeach
              </div>
            </fieldset>
            </div>

            <h4 class="br-review-heading">Labour hours</h4>
            <div class="admin-table-scroll">
              <table class="admin-table br-review-table">
                <thead>
                  <tr>
                    <th>Trade</th>
                    <th>Hours</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody id="br-labor-rows">
                  @forelse ($previewLabor as $row)
                    <tr data-br-labor-key="{{ $row['key'] }}">
                      <td>{{ $row['label'] }}@if (filled(trim((string) ($row['detail'] ?? '')))) <span class="admin-form-help">- {{ $row['detail'] }}</span>@endif</td>
                      <td data-br-labor-hrs>{{ number_format((float) ($row['hours'] ?? 0), 2) }}</td>
                      <td class="cp-col-amount" data-br-labor-amt>{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="3" class="admin-form-help">Enter the basement area to generate labour lines.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Materials</h4>
            <p class="admin-form-help">Waste % is on Overview (default 10%). Bulk quantities below already include it.</p>
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
                <tbody id="br-mat-rows">
                  @forelse ($previewMats as $row)
                    @if (str_starts_with((string) ($row['key'] ?? ''), 'extra_'))
                      @continue
                    @endif
                    <tr data-br-mat-key="{{ $row['key'] }}">
                      <td>{{ $row['label'] }}@if (! empty($row['client_provided'])) <span class="admin-form-help"> · client provided</span>@endif</td>
                      <td data-br-mat-qty>{{ number_format((float) ($row['qty'] ?? 0), 2) }}</td>
                      <td data-br-mat-price>{{ \App\Models\ClientProject::formatMoney($row['unit_price'] ?? 0) }}</td>
                      <td class="cp-col-amount" data-br-mat-amt>{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after quantities are entered.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Extra materials</h4>
            <p class="admin-form-help">Add items from the Materials DB or type them manually. Catalog rows are marked in blue; typed rows stay gray.</p>
            <div id="br-extra-list">
              @foreach ($extraRows as $ei => $erow)
                @include('admin.quotations.basement._extra_material_row', ['i' => $ei, 'row' => is_array($erow) ? $erow : []])
              @endforeach
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-extra-add" style="margin-top:8px;">
              <i class="fa fa-plus"></i> Add extra material
            </button>
            <template id="br-extra-template">
              @include('admin.quotations.basement._extra_material_row', ['i' => '__I__', 'row' => []])
            </template>

            @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
            @include('admin.quotations._commute_debris_row')
            @include('admin.quotations._additional_costs', [
                'quotation' => $quotation,
                'intro' => 'Optional extras (rentals, extra labour, etc.). Choose whether each line is <strong>materials</strong> or <strong>labour</strong>. Hidden from the client PDF — folded into that subtotal.',
                'placeholder' => 'e.g. Dumpster',
            ])

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
        <h4 style="margin:0 0 10px;">Live breakdown</h4>
        <div id="br-live-root">
          <p class="admin-form-help" id="br-live-empty">Enter the basement area to see totals.</p>
          <div id="br-live-body" hidden></div>
        </div>
      </div>
    </aside>

    <div class="br-sticky-spacer" aria-hidden="true"></div>

    <div class="br-sticky-total" id="br-sticky-total" aria-label="Step and running total">
      <div class="br-step-nav" id="br-step-nav">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-prev" disabled>Back</button>
        <span class="br-step-nav__status" id="br-step-status">1 of {{ $enabledCount }}</span>
        <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="br-next">Next</button>
      </div>
      <div class="br-toolbar-grand" id="br-toolbar-grand" title="Live grand total (incl. HST)">
        <span class="br-toolbar-grand__label">Grand total</span>
        <strong class="br-toolbar-grand__amount" id="br-toolbar-grand-amount">{{ \App\Models\ClientProject::formatMoney($toolbarGrand) }}</strong>
      </div>
    </div>
  </form>

  @if ($isEdit && $canSendEmail)
    <form id="br-email-quote-form"
          action="{{ route('admin.client-projects.quotation.send', [$sendProject, $linkedCpq]) }}"
          method="post"
          class="cp-inline-form"
          hidden>
      @csrf
    </form>
  @endif

  @if ($isEdit && ! $quotation->isLinked())
    @include('admin.quotations._link_to_project_card', [
        'quotation' => $quotation,
        'linkUrl' => route('admin.quotations.basement.link', $quotation),
        'createProjectUrl' => route('admin.quotations.basement.create-project', $quotation),
        'idPrefix' => 'br',
        'projectNamePlaceholder' => $quotation->summaryDescription(),
        'linkableProjects' => $linkableProjects,
        'wrapAdminCard' => true,
        'cardStyle' => 'margin-top:16px;',
        'helpText' => 'Add this basement renovation as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).',
        'createLineDescription' => 'field',
        'usePlacesAddress' => true,
    ])
  @endif
@endsection

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'br-work-description',
  'formId' => 'br-main-form',
])

@push('scripts')
  <script>
    window.BR_QUOTATION = {
      previewUrl: @json(
        $isEdit && $quotation
          ? route('admin.quotations.basement.calculate-existing', $quotation)
          : route('admin.quotations.basement.calculate')
      ),
      initialPreview: @json($initialPreview ?? null),
      isEdit: @json($isEdit),
      sectionKeys: @json(array_keys($sectionList)),
      presetSections: @json($presetSections),
      electricalByPreset: @json($electricalByPreset),
    };
  </script>
  <script src="{{ asset('js/admin-basement-quotation.js') }}?v={{ @filemtime(public_path('js/admin-basement-quotation.js')) ?: time() }}"></script>
@endpush

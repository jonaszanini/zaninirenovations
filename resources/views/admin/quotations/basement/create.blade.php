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
  <div class="admin-toolbar" style="margin-bottom:16px;">
    <a href="{{ route('admin.quotations.basement.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
    <a href="{{ route('admin.quotations.basement.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm" style="margin-left:8px;">
      <i class="fa fa-cog"></i> Price configuration
    </a>
  </div>

  <div class="br-project-context-slot">
    @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
  </div>

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
        data-br-mode="{{ $isEdit ? 'edit' : 'create' }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="save_intent" id="br-save-intent" value="publish">

    <div class="br-toolbar" aria-label="Quotation actions">
      <div class="pq-sidebar-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="br-btn-publish">
          <i class="fa fa-save"></i> Save quotation
        </button>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-btn-draft">Save as draft</button>
        @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
        <a href="{{ route('admin.quotations.basement.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      </div>
      <div class="br-step-nav" id="br-step-nav">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-prev" disabled>Back</button>
        <span class="br-step-nav__status" id="br-step-status">1 of {{ $enabledCount }}</span>
        <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="br-next">Next</button>
      </div>
    </div>

    <div class="br-shell">
      <nav class="br-progress" aria-label="Quotation sections">
        @php $n = 0; @endphp
        @foreach ($sectionList as $key => $label)
          @php $n++; @endphp
          <button type="button" class="br-progress__item{{ $n === 1 ? ' is-current' : '' }}" data-br-goto="{{ $key }}"
                  @if ($key !== 'overview' && ! in_array($key, $enabledNow, true)) hidden
                  @elseif ($scopePreset === 'bathroom' && in_array($key, ['electrical', 'plumbing'], true)) hidden
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
        <div class="admin-card fl-step-card">
          <h4>Client &amp; scope</h4>
          <div class="admin-form-group">
            <label for="br-client-name">@include('admin.partials.info_tip', ['text' => 'Name printed on the quotation PDF and used when linking this quote to a project.']) Client name</label>
            <input type="text" class="admin-input" id="br-client-name" name="client_name" maxlength="160"
                   value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}" required>
          </div>
          <div class="admin-form-group">
            <label for="br-work-description">@include('admin.partials.info_tip', ['text' => 'Optional scope summary that appears on the client PDF. Improve with AI after you fill the wizard.']) Project description <span class="admin-form-help">(optional — appears on client PDF)</span></label>
            <textarea class="admin-input" id="br-work-description" name="work_description" rows="4" maxlength="5000">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
            @include('admin.partials.quotation_work_description_ai', [
                'workDescriptionTextareaId' => 'br-work-description',
                'aiContextFormId' => 'br-main-form',
                'aiQuotationType' => 'basement',
            ])
          </div>
        </div>

        @include('admin.quotations.basement._wizard_sections', [
            'inputs' => $inputs,
            'settings' => $settings,
            'isEdit' => $isEdit,
            'scopePreset' => $scopePreset,
            'enabledNow' => $enabledNow,
            'enabledCount' => $enabledCount,
            'filledKeys' => $filledKeys,
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
              'info' => 'Labor only — you do not supply materials. Material cost drops out of the total.',
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
                  'info' => 'Client buys the materials for '.$label.'; you still carry the labor for that trade.',
                  'inputClass' => 'admin-checkbox__input br-preview',
                  'clientFilled' => $key === 'flooring' && in_array('client_provides_sections', $filledKeys, true),
                ])
              @endforeach
            </fieldset>
            <fieldset class="br-bath-provide" id="br-provide-bath-items">
              <legend class="br-bath-provide__title">
                @include('admin.partials.info_tip', ['text' => 'Client buys these fixtures; you still install them. Tile, fan, and lights stay on the Bathroom step (or Electrical when quoting living-area lights).'])
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
                    'info' => 'Client supplies the '.$itemLabel.'; material cost drops out. Labor stays.',
                    'inputClass' => 'admin-checkbox__input br-preview',
                    'clientFilled' => in_array('client_provides_bath_items', $filledKeys, true) && in_array($itemKey, $provideBathItems, true),
                  ])
                @endforeach
              </div>
            </fieldset>
            </div>

            <h4 class="br-review-heading">Labor hours</h4>
            <div class="admin-table-scroll">
              <table class="admin-table br-edit-table">
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
                      <td>{{ $row['label'] }}<br><span class="admin-form-help">{{ $row['detail'] ?? '' }}</span></td>
                      <td>
                        <input type="number" step="0.01" min="0" class="admin-input br-preview"
                               name="labor_override[{{ $row['key'] }}][hours]" value="{{ $row['hours'] }}">
                      </td>
                      <td class="cp-col-amount" data-br-labor-amt>{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="3" class="admin-form-help">Enter the basement area to generate labor lines.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Materials</h4>
            <div class="admin-table-scroll">
              <table class="admin-table br-edit-table">
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
                    @php $derivedPlate = ($row['key'] ?? '') === \App\Models\BasementSettings::MATERIAL_PLATE_2X4; @endphp
                    <tr data-br-mat-key="{{ $row['key'] }}">
                      <td>{{ $row['label'] }}@if (! empty($row['client_provided'])) <span class="admin-form-help"> · client provided</span>@endif</td>
                      <td>
                        <input type="number" step="0.01" min="0" class="admin-input br-preview"
                               name="materials_override[{{ $row['key'] }}][qty]" value="{{ $row['qty'] }}">
                      </td>
                      <td>
                        <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-cp-money-places="4"
                               class="admin-input cp-money-input br-preview{{ $derivedPlate ? ' is-locked' : '' }}"
                               name="materials_override[{{ $row['key'] }}][unit_price]"
                               value="{{ number_format((float) ($row['unit_price'] ?? 0), 4, '.', '') }}"
                               @if ($derivedPlate) readonly aria-readonly="true" title="Price is the 2x4x8 stud unit price ÷ 8." @endif>
                      </td>
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
            @include('admin.quotations._debris_removal', ['quotation' => $quotation])
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
        <h4 style="margin:0 0 10px;">Live breakdown</h4>
        <div id="br-live-root">
          <p class="admin-form-help" id="br-live-empty">Enter the basement area to see totals.</p>
          <div id="br-live-body" hidden></div>
        </div>
      </div>
    </aside>
  </form>

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

@extends('admin.layouts.app')

@php
  use App\Services\MillworkQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : ($linkProject ?? null);
  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New millwork quotation';
  $formAction = ($isEdit ? route('admin.quotations.millwork.update', $quotation) : route('admin.quotations.millwork.store')).$formQuery;
  $roomAreaImport = $roomAreaImport ?? null;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : MillworkQuotationCalculator::defaultInputs();
  foreach (MillworkQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  if (! $isEdit && ! empty($roomAreaImport['inputs']) && is_array($roomAreaImport['inputs'])) {
      $inputs = array_merge($inputs, $roomAreaImport['inputs']);
  }
  $sectionList = $sections ?? MillworkQuotationCalculator::SECTIONS;
  $previewLabor = is_array($initialPreview['labor'] ?? null) ? $initialPreview['labor'] : [];
  $previewMats = array_merge(
      is_array($initialPreview['materials'] ?? null) ? $initialPreview['materials'] : [],
      is_array($initialPreview['extra_materials'] ?? null) ? $initialPreview['extra_materials'] : []
  );
  $extraRows = old('extra_materials', $inputs['extra_materials'] ?? []);
  if (! is_array($extraRows)) {
      $extraRows = [];
  }
  $enabledCount = count($sectionList);
  $cutLayouts = is_array($initialPreview['cut_plan']['layouts'] ?? null) ? $initialPreview['cut_plan']['layouts'] : [];
@endphp

@section('heading', $title)

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-millwork.css') }}?v={{ @filemtime(public_path('css/admin-millwork.css')) ?: time() }}">
@endpush

@section('content')
  @include('admin.partials.quotation_editor_header', [
    'title' => $title,
    'typeLabel' => 'Millwork',
    'project' => $projectForReturn,
    'clientName' => old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name),
    'listUrl' => route('admin.quotations.millwork.index', $preservePid ? ['project' => $preservePid] : []),
    'cancelUrl' => route('admin.quotations.millwork.index', $preservePid ? ['project' => $preservePid] : []),
    'priceConfigUrl' => route('admin.quotations.millwork.price-configuration', $preservePid ? ['project' => $preservePid] : []),
    'formId' => 'mw-main-form',
    'saveId' => 'mw-btn-publish',
    'draftId' => 'mw-btn-draft',
    'draftHotkey' => true,
    'wizard' => [
      'navId' => 'mw-step-nav',
      'prevId' => 'mw-prev',
      'nextId' => 'mw-next',
      'statusId' => 'mw-step-status',
      'count' => $enabledCount,
    ],
  ])
  <div class="br-project-context-slot">
    @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
  </div>
  @include('admin.partials.room_area_prefill_banner')
  @include('admin.partials.quotation_client_email_message', ['quotation' => $quotation ?? null, 'formId' => 'mw-main-form'])
  @include('admin.partials.quotation_reply_to_accept', ['quotation' => $quotation ?? null, 'formId' => 'mw-main-form'])

  @if ($isEdit && $quotation->project)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      <i class="fa fa-link"></i> Linked to <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation"><strong>{{ $quotation->project->name }}</strong></a>
    </div>
  @endif

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ $formAction }}" id="mw-main-form" class="pq-layout br-layout {{ $isEdit ? 'br-layout--edit' : 'br-layout--create' }}"
        data-br-mode="{{ $isEdit ? 'edit' : 'create' }}"
        data-quotation-editor
        data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="save_intent" id="mw-save-intent" value="publish">
    <input type="hidden" name="_cut_plan_version" id="mw-cut-plan-version" value="{{ (int) ($cutPlanVersion ?? ($isEdit && $quotation ? $quotation->cutPlanVersion() : 2)) }}">
    <input type="hidden" name="upgrade_cut_plan" id="mw-upgrade-cut-plan" value="0">

    <div class="br-shell">
      <nav class="br-progress" aria-label="Quotation sections">
        @php $n = 0; @endphp
        @foreach ($sectionList as $key => $label)
          @php $n++; @endphp
          <button type="button" class="br-progress__item{{ $n === 1 ? ' is-current' : '' }}" data-mw-goto="{{ $key }}">
            <span class="br-progress__num">{{ $n }}</span>
            <span class="br-progress__label">{{ $label }}</span>
          </button>
        @endforeach
      </nav>

      <div class="br-main">
        <div class="admin-card fl-step-card mw-client-card">
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="mw-client-name">@include('admin.partials.info_tip', ['text' => 'Name printed on the quotation PDF and used when linking this quote to a project.']) Client name</label>
              <input type="text" class="admin-input" id="mw-client-name" name="client_name" maxlength="160"
                     value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}" required>
            </div>
          </div>
        </div>

        @include('admin.quotations.millwork._wizard_sections', [
            'inputs' => $inputs,
            'settings' => $settings,
            'catalog' => $catalog ?? $settings->resolvedCatalog(),
        ])

        <details class="br-acc admin-card" data-br-step="review" @if ($isEdit) open @endif>
          <summary class="br-acc__summary">Review &amp; cut plan</summary>
          <div class="br-acc__body">
            @include('admin.partials.checkbox', [
              'name' => 'client_provides_all',
              'hiddenFallback' => true,
              'checked' => (bool) old('client_provides_all', $inputs['client_provides_all'] ?? false),
              'label' => '<strong>Client provides all materials</strong>',
              'info' => 'Labour only — material cost drops out of the total.',
              'inputClass' => 'admin-checkbox__input mw-preview',
              'id' => 'mw-client-all',
            ])

            <div class="mw-cut-plan-head">
              <h4 class="br-review-heading">Cut plan</h4>
              <div class="mw-cut-plan-head__actions">
                @if ($isEdit && $quotation && $quotation->usesLegacyCutBuilder())
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="mw-upgrade-cut-plan-btn">
                    Recalculate with new cut plan
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="mw-undo-cut-plan-btn" hidden>
                    Undo recalculate
                  </button>
                @endif
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="mw-print-cut-list" @disabled($cutLayouts === [])>
                  <i class="fa fa-print"></i> Print cut list
                </button>
              </div>
            </div>
            @php
              $unplacedParts = is_array($initialPreview['cut_plan']['unplaced'] ?? null) ? $initialPreview['cut_plan']['unplaced'] : [];
              $cutSeams = is_array($initialPreview['cut_plan']['seams'] ?? null) ? $initialPreview['cut_plan']['seams'] : [];
            @endphp
            @php
              $recalcNotice = is_array($initialPreview['recalc_notice'] ?? null) ? $initialPreview['recalc_notice'] : null;
            @endphp
            <div id="mw-recalc-banner" class="admin-flash admin-flash--warning mw-recalc-banner" role="status" @if (! $recalcNotice) hidden @endif>
              <strong>Recalculated with the new cut plan</strong>
              <p id="mw-recalc-text">
                @if ($recalcNotice)
                  was {{ \App\Models\ClientProject::formatMoney((float) $recalcNotice['was']) }},
                  now {{ \App\Models\ClientProject::formatMoney((float) $recalcNotice['now']) }}.
                  Review the cut plan before you save.
                @endif
              </p>
              @if ($isEdit && $quotation && $quotation->usesLegacyCutBuilder())
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="mw-undo-cut-plan-banner-btn">
                  Undo
                </button>
              @endif
            </div>
            <div id="mw-unplaced-banner" class="admin-flash admin-flash--error mw-unplaced-banner" role="alert" @if ($unplacedParts === []) hidden @endif>
              <strong>Parts do not fit on any sheet</strong>
              <p>These parts are not in the cut plan and are not in the price. Change the size or pick a larger sheet. The client PDF cannot be downloaded until every part fits.</p>
              <ul id="mw-unplaced-list">
                @foreach ($unplacedParts as $missing)
                  <li>{{ $missing['label'] ?? 'Part' }}</li>
                @endforeach
              </ul>
            </div>
            <div id="mw-cut-seams" class="mw-cut-seams" @if ($cutSeams === []) hidden @endif>
              @foreach ($cutSeams as $seam)
                <p>
                  {{ $seam['label'] ?? 'Back' }}: {{ $seam['note'] ?? '' }}
                  @if (! empty($seam['panels']))
                    ({{ implode(' + ', $seam['panels']) }})
                  @endif
                  @include('admin.partials.info_tip', [
                    'tipText' => 'Finished sizes still add up to '.($seam['original'] ?? '').'.',
                    'tipLabel' => 'Finished size',
                  ])
                </p>
              @endforeach
            </div>
            <p class="admin-form-help" id="mw-cut-meta">
              {{ (int) ($initialPreview['cut_plan']['sheet_count'] ?? 0) }} sheet(s)
              @if (! empty($initialPreview['cut_plan']['yield_pct']))
                · {{ $initialPreview['cut_plan']['yield_pct'] }}% yield
              @endif
              @php
                $kerfLabel = \App\Services\CutOptimizer\SvgVisualizer::formatKerfLabel((float) ($initialPreview['cut_plan']['kerf_in'] ?? 0.125));
              @endphp
              @if ($kerfLabel !== '')
                · Kerf {{ $kerfLabel }}
              @endif
            </p>
            <div id="mw-cut-plan" class="mw-cut-plan">
              @forelse ($cutLayouts as $layout)
                @include('admin.quotations.millwork._cut_sheet', ['layout' => $layout])
              @empty
                <p class="admin-form-help">Enter piece dimensions to generate a guillotine cut plan.</p>
              @endforelse
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
                <tbody id="mw-labor-rows">
                  @php
                    $previewLaborHours = array_values(array_filter($previewLabor, fn ($row) => ($row['key'] ?? '') !== 'markup'));
                  @endphp
                  @forelse ($previewLaborHours as $row)
                    <tr>
                      <td>{{ $row['label'] }}</td>
                      <td>{{ number_format((float) ($row['hours'] ?? 0), 2) }}</td>
                      <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="3" class="admin-form-help">Labour appears after pieces are sized.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Shopping list</h4>
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
                <tbody id="mw-mat-rows">
                  @forelse ($previewMats as $row)
                    @if (str_starts_with((string) ($row['key'] ?? ''), 'extra_'))
                      @continue
                    @endif
                    <tr>
                      <td>{{ $row['label'] }}@if (! empty($row['client_provided'])) <span class="admin-form-help"> · client provided</span>@endif</td>
                      <td>{{ number_format((float) ($row['qty'] ?? 0), 2) }} {{ $row['unit'] ?? '' }}</td>
                      <td>{{ \App\Models\ClientProject::formatMoney($row['unit_price'] ?? 0) }}</td>
                      <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($row['amount'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after size is entered.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <h4 class="br-review-heading">Extra materials</h4>
            <div id="mw-extra-list">
              @foreach ($extraRows as $ei => $erow)
                @include('admin.quotations.millwork._extra_material_row', ['i' => $ei, 'row' => is_array($erow) ? $erow : []])
              @endforeach
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="mw-extra-add" style="margin-top:8px;">
              <i class="fa fa-plus"></i> Add extra material
            </button>
            <template id="mw-extra-template">
              @include('admin.quotations.millwork._extra_material_row', ['i' => '__I__', 'row' => []])
            </template>

            <div class="admin-form-group quotation-work-desc">
              <label for="mw-work-description">@include('admin.partials.info_tip', ['text' => 'Optional scope summary on the client PDF. Improve with AI after you fill the wizard.']) Description</label>
              <textarea class="admin-input" id="mw-work-description" name="work_description" rows="5"
                        placeholder="Describe the millwork to be built and installed (visible on the client quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
              @include('admin.partials.quotation_work_description_ai', [
                  'workDescriptionTextareaId' => 'mw-work-description',
                  'aiContextFormId' => 'mw-main-form',
                  'aiQuotationType' => 'millwork',
              ])
              <p class="admin-form-help">Formatting (bold, lists, headings) is preserved on the client PDF.</p>
            </div>

            @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
            @include('admin.quotations._commute_debris_row')
            @include('admin.quotations._building', ['quotation' => $quotation, 'inputs' => $inputs ?? [], 'previewClass' => 'mw-preview'])
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
          @include('admin.partials.quotation_price_config_gear', [
            'url' => route('admin.quotations.millwork.price-configuration', $preservePid ? ['project' => $preservePid] : []),
          ])
        </div>
        <div id="mw-live-root">
          <p class="admin-form-help" id="mw-live-empty">Add piece dimensions to see the cut plan.</p>
          <div id="mw-live-body"></div>
        </div>
      </div>
    </aside>

    <div class="mw-totals-bar" id="mw-totals-bar" aria-live="polite">
      <button type="button" class="mw-totals-bar__expand" id="mw-bar-expand" aria-expanded="false" aria-controls="mw-totals-bar" aria-label="Show totals details">
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
      </button>
      <span class="mw-totals-bar__detail"><em>Mat</em> <strong id="mw-bar-mat">CA$0.00</strong></span>
      <span class="mw-totals-bar__detail"><em>Labour</em> <strong id="mw-bar-labor">CA$0.00</strong></span>
      <span class="mw-totals-bar__detail" id="mw-bar-delivery-wrap" hidden><em>Deliv.</em> <strong id="mw-bar-delivery">CA$0.00</strong></span>
      <span class="mw-totals-bar__detail" id="mw-bar-markup-wrap" hidden><em>Markup</em> <strong id="mw-bar-markup">CA$0.00</strong></span>
      <span class="mw-totals-bar__core"><em>Subtotal</em> <strong id="mw-bar-sub">CA$0.00</strong></span>
      <span class="mw-totals-bar__detail"><em>HST 13%</em> <strong id="mw-bar-hst">CA$0.00</strong></span>
      <span class="mw-totals-bar__core mw-totals-bar__total"><em>Total</em> <strong id="mw-bar-total">CA$0.00</strong></span>
    </div>
  </form>

  @if ($isEdit && ! $quotation->isLinked())
    @include('admin.quotations._link_to_project_card', [
        'quotation' => $quotation,
        'linkUrl' => route('admin.quotations.millwork.link', $quotation),
        'createProjectUrl' => route('admin.quotations.millwork.create-project', $quotation),
        'idPrefix' => 'mw',
        'projectNamePlaceholder' => $quotation->summaryDescription(),
        'linkableProjects' => $linkableProjects,
        'wrapAdminCard' => true,
        'cardStyle' => 'margin-top:16px;',
        'helpText' => 'Add this millwork as a line on the project <strong>Quotation</strong> tab (pre-tax labour + materials — HST is added on the client PDF).',
        'createLineDescription' => 'field',
        'usePlacesAddress' => true,
    ])
  @endif
@endsection

@include('admin.partials.quotation_work_description_tinymce', [
  'textareaId' => 'mw-work-description',
  'formId' => 'mw-main-form',
])

@push('scripts')
  <script>
    window.MW_QUOTATION = {
      previewUrl: @json(
        $isEdit && $quotation
          ? route('admin.quotations.millwork.calculate-existing', $quotation)
          : route('admin.quotations.millwork.calculate')
      ),
      initialPreview: @json($initialPreview ?? null),
      isEdit: @json($isEdit),
      cutPlanVersion: {{ (int) ($cutPlanVersion ?? ($isEdit && $quotation ? $quotation->cutPlanVersion() : 2)) }},
      storedTotal: {{ $isEdit && $quotation ? json_encode((float) $quotation->final_quote_total) : '0' }},
      sectionKeys: @json(array_keys($sectionList)),
      quoteNumber: @json($isEdit && $quotation ? ($quotation->quote_number ?? '') : ''),
      printCssUrl: @json(asset('css/millwork-cut-list-print.css').'?v='.(@filemtime(public_path('css/millwork-cut-list-print.css')) ?: time())),
    };
  </script>
  <script src="{{ asset('js/admin-millwork-quotation.js') }}?v={{ @filemtime(public_path('js/admin-millwork-quotation.js')) ?: time() }}"></script>
@endpush

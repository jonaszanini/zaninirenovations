@extends('admin.layouts.app')

@php
  use App\Services\TrimmingQuotationCalculator;

  $isEdit = filled($quotation);
  $returnToProject = request('return') === 'project' && $isEdit && $quotation->project;
  $projectForReturn = ($isEdit && $quotation->project)
      ? $quotation->project
      : ($linkProject ?? null);
  $preservePid = optional($linkProject)->id ?: (request()->filled('project') ? (int) request('project') : null);
  $qs = array_filter(['return' => $returnToProject ? 'project' : null, 'project' => $preservePid]);
  $formQuery = $qs !== [] ? '?'.http_build_query($qs) : '';
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New trimming quotation';
  $formAction = ($isEdit ? route('admin.quotations.trimming.update', $quotation) : route('admin.quotations.trimming.store')).$formQuery;
  $inputs = $isEdit ? ($quotation->inputs ?? []) : TrimmingQuotationCalculator::defaultInputs();
  foreach (TrimmingQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  $enabled = old('enabled_families', $inputs['enabled_families'] ?? []);
  if (! is_array($enabled)) {
      $enabled = [];
  }
  $familyOn = function (string $key) use ($enabled): bool {
      return in_array($key, $enabled, true);
  };
  $tmOn = function (string $key) use ($inputs): bool {
      return (bool) old($key, $inputs[$key] ?? false);
  };
  $tmVal = function (string $key, $fallback = '') use ($inputs) {
      $v = old($key, $inputs[$key] ?? $fallback);
      return $v === null ? '' : $v;
  };
  $tips = [
      'doors' => 'Openings to case. Casing LF auto-fills at 17 lf per door.',
      'windows' => 'Openings to wrap. Casing auto-fills at 16 lf per window.',
      'floor_lf' => 'Shared length for base, cap, shoe, and quarter round unless you override a profile.',
      'crown' => 'Linear feet of crown. Enter separately from cove when rooms differ.',
      'wainscot' => 'Wall length × height becomes panel square footage. Battens add for board and batten.',
      'remove' => 'Blank uses the installed trim length from the families you turned on.',
      'paint' => 'Blank uses installed trim length. Rates include caulk, fill, and paint.',
      'client_all' => 'Labour only — material lines drop to $0.',
  ];
@endphp

@section('heading', $title)

@section('content')
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.trimming.index', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    <div class="fq-form-toolbar-right">
      <a href="{{ route('admin.quotations.trimming.price-configuration', $preservePid ? ['project' => $preservePid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
      @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="tm-btn-draft">Save as draft</button>
      @include('admin.partials.quotation_view_pdf')
      <button type="submit" form="tm-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="tm-btn-publish">
        <i class="fa fa-save"></i> Save quotation
      </button>
    </div>
  </div>

  @if ($isEdit && $quotation->project)
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      Linked to <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation">{{ $quotation->project->name }}</a>
    </div>
  @endif

  @if ($errors->has('calculation'))
    <div class="admin-flash admin-flash--danger">{{ $errors->first('calculation') }}</div>
  @endif

  <form method="post" action="{{ $formAction }}" id="tm-main-form" class="tm-layout" data-quotation-editor data-quotation-is-draft="{{ $isEdit && $quotation->is_draft ? '1' : '0' }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="save_intent" id="tm-save-intent" value="publish">
    @if ($linkProject)
      <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
    @endif
    <input type="hidden" name="owner_hourly_rate" value="{{ old('owner_hourly_rate', $inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate) }}">
    <input type="hidden" name="helper_hourly_rate" value="{{ old('helper_hourly_rate', $inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate) }}">

    <div>
      <div class="admin-card tm-card">
        <h4>Client</h4>
        <div class="pq-form-grid">
          <div class="admin-form-group">
            <label for="tm-client">Client name @if(!$isEdit || !($quotation->is_draft ?? false))<span class="cp-required">*</span>@endif</label>
            <input type="text" class="admin-input tm-preview" id="tm-client" name="client_name" maxlength="160"
                   value="{{ old('client_name', $isEdit ? ($quotation->client_name ?? '') : optional($linkProject)->client_name) }}"
                   @if(!old('client_name', $isEdit ? ($quotation->client_name ?? '') : '') && (!$isEdit || !($quotation->is_draft ?? false))) required @endif>
          </div>
          <div class="admin-form-group">
            @include('admin.partials.search_select', [
                'id' => 'tm-client-pick',
                'name' => 'tm_client_pick',
                'label' => 'Pick from directory',
                'placeholder' => 'Search name, email, or phone',
                'ariaLabel' => 'Search clients from directory',
                'searchUrl' => route('admin.clients.search'),
                'metaKeys' => ['email', 'phone_display'],
                'emptyMessage' => 'No matching clients',
                'emptyQueryMessage' => 'No clients in the directory yet',
                'fill' => ['name' => 'tm-client'],
            ])
          </div>
        </div>
        <div class="admin-form-group">
          <label for="tm-work-description">Work description</label>
          <textarea class="admin-input" id="tm-work-description" name="work_description" rows="3" maxlength="5000">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'tm-work-description',
              'aiContextFormId' => 'tm-main-form',
              'aiQuotationType' => 'trimming',
          ])
        </div>
      </div>

      <div class="admin-card tm-card">
        <h4>Doors &amp; windows</h4>
        <label class="admin-checkbox tm-family-toggle">
          <input type="checkbox" class="admin-checkbox__input tm-preview" name="enabled_families[]" value="doors_windows"
                 data-tm-family="doors_windows" @checked($familyOn('doors_windows'))>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text">Include this family</span>
        </label>
        <div class="tm-family-body" data-tm-family-body="doors_windows" @unless($familyOn('doors_windows')) hidden @endunless>
          <div class="pq-form-grid">
            <div class="admin-form-group">
              <label for="tm-doors">@include('admin.partials.info_tip', ['text' => $tips['doors']]) Door openings</label>
              <input type="number" min="0" step="1" class="admin-input tm-preview" id="tm-doors" name="door_qty" value="{{ $tmVal('door_qty', 0) }}">
            </div>
            <div class="admin-form-group">
              <label for="tm-windows">@include('admin.partials.info_tip', ['text' => $tips['windows']]) Window openings</label>
              <input type="number" min="0" step="1" class="admin-input tm-preview" id="tm-windows" name="window_qty" value="{{ $tmVal('window_qty', 0) }}">
            </div>
          </div>
          <div class="tm-chip-row">
            @foreach ([
              'include_casing' => 'Casing',
              'include_architrave' => 'Architrave',
              'include_plinth' => 'Plinth blocks',
              'include_rosettes' => 'Rosettes',
              'include_stool_apron' => 'Stool & apron',
              'include_jamb_extensions' => 'Jamb extensions',
              'include_interior_doors' => 'Install doors',
            ] as $flag => $label)
              <label class="admin-checkbox">
                <input type="hidden" name="{{ $flag }}" value="0">
                <input type="checkbox" class="admin-checkbox__input tm-preview" name="{{ $flag }}" value="1" @checked($tmOn($flag))>
                <span class="admin-checkbox__mark"></span>
                <span class="admin-checkbox__text">{{ $label }}</span>
              </label>
            @endforeach
          </div>
          <div class="pq-form-grid tm-override-grid">
            <div class="admin-form-group" data-tm-show-when="include_casing">
              <label for="tm-casing-lf">Casing override (door lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-casing-lf" name="casing_lf" placeholder="Auto 17 lf/door" value="{{ $tmVal('casing_lf') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_casing">
              <label for="tm-win-casing-lf">Casing override (window lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-win-casing-lf" name="window_casing_lf" placeholder="Auto 16 lf/window" value="{{ $tmVal('window_casing_lf') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_architrave">
              <label for="tm-arch-lf">Architrave override (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-arch-lf" name="architrave_lf" placeholder="Auto 4 lf/opening" value="{{ $tmVal('architrave_lf') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_plinth">
              <label for="tm-plinth">Plinth qty override</label>
              <input type="number" min="0" step="1" class="admin-input tm-preview" id="tm-plinth" name="plinth_qty" placeholder="Auto 2/door" value="{{ $tmVal('plinth_qty') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_rosettes">
              <label for="tm-rosette">Rosette qty override</label>
              <input type="number" min="0" step="1" class="admin-input tm-preview" id="tm-rosette" name="rosette_qty" placeholder="Auto 2/opening" value="{{ $tmVal('rosette_qty') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_stool_apron">
              <label for="tm-stool">Stool/apron qty override</label>
              <input type="number" min="0" step="1" class="admin-input tm-preview" id="tm-stool" name="stool_apron_qty" placeholder="Auto 1/window" value="{{ $tmVal('stool_apron_qty') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_jamb_extensions">
              <label for="tm-jamb">Jamb qty override</label>
              <input type="number" min="0" step="1" class="admin-input tm-preview" id="tm-jamb" name="jamb_qty" placeholder="Auto 1/opening" value="{{ $tmVal('jamb_qty') }}">
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card tm-card">
        <h4>Floor to wall</h4>
        <label class="admin-checkbox tm-family-toggle">
          <input type="checkbox" class="admin-checkbox__input tm-preview" name="enabled_families[]" value="floor_wall"
                 data-tm-family="floor_wall" @checked($familyOn('floor_wall'))>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text">Include this family</span>
        </label>
        <div class="tm-family-body" data-tm-family-body="floor_wall" @unless($familyOn('floor_wall')) hidden @endunless>
          <div class="pq-form-grid">
            <div class="admin-form-group">
              <label for="tm-floor-lf">@include('admin.partials.info_tip', ['text' => $tips['floor_lf']]) Linear feet</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-floor-lf" name="floor_lf" value="{{ $tmVal('floor_lf', 0) }}">
            </div>
          </div>
          <div class="tm-chip-row">
            @foreach ([
              'include_baseboard' => 'Baseboards',
              'include_base_cap' => 'Base cap',
              'include_shoe' => 'Shoe moulding',
              'include_quarter_round' => 'Quarter round',
            ] as $flag => $label)
              <label class="admin-checkbox">
                <input type="hidden" name="{{ $flag }}" value="0">
                <input type="checkbox" class="admin-checkbox__input tm-preview" name="{{ $flag }}" value="1" @checked($tmOn($flag))>
                <span class="admin-checkbox__mark"></span>
                <span class="admin-checkbox__text">{{ $label }}</span>
              </label>
            @endforeach
          </div>
          <div class="pq-form-grid tm-override-grid">
            <div class="admin-form-group" data-tm-show-when="include_baseboard">
              <label for="tm-bb-lf">Baseboard override (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-bb-lf" name="baseboard_lf" placeholder="Same as linear feet" value="{{ $tmVal('baseboard_lf') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_base_cap">
              <label for="tm-cap-lf">Base cap override (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-cap-lf" name="base_cap_lf" placeholder="Same as linear feet" value="{{ $tmVal('base_cap_lf') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_shoe">
              <label for="tm-shoe-lf">Shoe override (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-shoe-lf" name="shoe_lf" placeholder="Same as linear feet" value="{{ $tmVal('shoe_lf') }}">
            </div>
            <div class="admin-form-group" data-tm-show-when="include_quarter_round">
              <label for="tm-qr-lf">Quarter round override (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-qr-lf" name="quarter_round_lf" placeholder="Same as linear feet" value="{{ $tmVal('quarter_round_lf') }}">
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card tm-card">
        <h4>Ceiling to wall</h4>
        <label class="admin-checkbox tm-family-toggle">
          <input type="checkbox" class="admin-checkbox__input tm-preview" name="enabled_families[]" value="ceiling"
                 data-tm-family="ceiling" @checked($familyOn('ceiling'))>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text">Include this family</span>
        </label>
        <div class="tm-family-body" data-tm-family-body="ceiling" @unless($familyOn('ceiling')) hidden @endunless>
          <div class="tm-chip-row">
            <label class="admin-checkbox">
              <input type="hidden" name="include_crown" value="0">
              <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_crown" value="1" @checked($tmOn('include_crown'))>
              <span class="admin-checkbox__mark"></span>
              <span class="admin-checkbox__text">Crown moulding</span>
            </label>
            <label class="admin-checkbox">
              <input type="hidden" name="include_cove" value="0">
              <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_cove" value="1" @checked($tmOn('include_cove'))>
              <span class="admin-checkbox__mark"></span>
              <span class="admin-checkbox__text">Cove moulding</span>
            </label>
          </div>
          <div class="pq-form-grid">
            <div class="admin-form-group">
              <label for="tm-crown-lf">@include('admin.partials.info_tip', ['text' => $tips['crown']]) Crown (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-crown-lf" name="crown_lf" value="{{ $tmVal('crown_lf', 0) }}">
            </div>
            <div class="admin-form-group">
              <label for="tm-cove-lf">Cove (lf)</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-cove-lf" name="cove_lf" value="{{ $tmVal('cove_lf', 0) }}">
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card tm-card">
        <h4>Wall treatments</h4>
        <label class="admin-checkbox tm-family-toggle">
          <input type="checkbox" class="admin-checkbox__input tm-preview" name="enabled_families[]" value="wall_treatments"
                 data-tm-family="wall_treatments" @checked($familyOn('wall_treatments'))>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text">Include this family</span>
        </label>
        <div class="tm-family-body" data-tm-family-body="wall_treatments" @unless($familyOn('wall_treatments')) hidden @endunless>
          <div class="pq-form-grid">
            <div class="admin-form-group">
              <label class="admin-checkbox">
                <input type="hidden" name="include_chair_rail" value="0">
                <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_chair_rail" value="1" @checked($tmOn('include_chair_rail'))>
                <span class="admin-checkbox__mark"></span>
                <span class="admin-checkbox__text">Chair rail</span>
              </label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" name="chair_rail_lf" aria-label="Chair rail linear feet" value="{{ $tmVal('chair_rail_lf', 0) }}">
            </div>
            <div class="admin-form-group">
              <label class="admin-checkbox">
                <input type="hidden" name="include_picture_rail" value="0">
                <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_picture_rail" value="1" @checked($tmOn('include_picture_rail'))>
                <span class="admin-checkbox__mark"></span>
                <span class="admin-checkbox__text">Picture rail</span>
              </label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" name="picture_rail_lf" aria-label="Picture rail linear feet" value="{{ $tmVal('picture_rail_lf', 0) }}">
            </div>
            <div class="admin-form-group">
              <label class="admin-checkbox">
                <input type="hidden" name="include_box_moulding" value="0">
                <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_box_moulding" value="1" @checked($tmOn('include_box_moulding'))>
                <span class="admin-checkbox__mark"></span>
                <span class="admin-checkbox__text">Picture-frame moulding</span>
              </label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" name="box_moulding_lf" aria-label="Box moulding linear feet" value="{{ $tmVal('box_moulding_lf', 0) }}">
            </div>
          </div>
          <label class="admin-checkbox">
            <input type="hidden" name="include_wainscoting" value="0">
            <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_wainscoting" value="1" @checked($tmOn('include_wainscoting'))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">Wainscoting</span>
          </label>
          <div class="pq-form-grid tm-wainscot-grid">
            <div class="admin-form-group">
              <label for="tm-wain-style">Style</label>
              <select class="admin-input tm-preview" id="tm-wain-style" name="wainscot_style">
                @foreach (TrimmingQuotationCalculator::WAINSCOT_STYLES as $key => $label)
                  <option value="{{ $key }}" @selected($tmVal('wainscot_style', 'beadboard') === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="admin-form-group">
              <label for="tm-wain-lf">@include('admin.partials.info_tip', ['text' => $tips['wainscot']]) Wall lf</label>
              <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-wain-lf" name="wainscot_wall_lf" value="{{ $tmVal('wainscot_wall_lf', 0) }}">
            </div>
            <div class="admin-form-group">
              <label for="tm-wain-h">Height (in)</label>
              <input type="number" min="1" step="0.25" class="admin-input tm-preview" id="tm-wain-h" name="wainscot_height_in" value="{{ $tmVal('wainscot_height_in', 36) }}">
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card tm-card">
        <h4>Prep &amp; finish</h4>
        <div class="tm-chip-row">
          <label class="admin-checkbox">
            <input type="hidden" name="include_remove_existing" value="0">
            <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_remove_existing" value="1" @checked($tmOn('include_remove_existing'))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">@include('admin.partials.info_tip', ['text' => $tips['remove']]) Remove existing</span>
          </label>
          <label class="admin-checkbox">
            <input type="hidden" name="include_caulk_paint" value="0">
            <input type="checkbox" class="admin-checkbox__input tm-preview" name="include_caulk_paint" value="1" @checked($tmOn('include_caulk_paint'))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">@include('admin.partials.info_tip', ['text' => $tips['paint']]) Caulk &amp; paint</span>
          </label>
          <label class="admin-checkbox">
            <input type="hidden" name="client_provides_all" value="0">
            <input type="checkbox" class="admin-checkbox__input tm-preview" id="tm-client-all" name="client_provides_all" value="1" @checked($tmOn('client_provides_all'))>
            <span class="admin-checkbox__mark"></span>
            <span class="admin-checkbox__text">@include('admin.partials.info_tip', ['text' => $tips['client_all']]) Client supplies materials</span>
          </label>
        </div>
        <div class="pq-form-grid tm-override-grid">
          <div class="admin-form-group" data-tm-show-when="include_remove_existing">
            <label for="tm-remove-lf">Remove (lf)</label>
            <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-remove-lf" name="remove_lf" placeholder="Auto from selected profiles" value="{{ $tmVal('remove_lf') }}">
          </div>
          <div class="admin-form-group" data-tm-show-when="include_caulk_paint">
            <label for="tm-paint-lf">Caulk &amp; paint (lf)</label>
            <input type="number" min="0" step="0.01" class="admin-input tm-preview" id="tm-paint-lf" name="paint_lf" placeholder="Auto from selected profiles" value="{{ $tmVal('paint_lf') }}">
          </div>
          <div class="admin-form-group" data-tm-show-when="include_caulk_paint">
            <label for="tm-paint-coats">Paint coats</label>
            <input type="number" min="1" max="4" step="1" class="admin-input tm-preview" id="tm-paint-coats" name="paint_coats" value="{{ $tmVal('paint_coats', 2) }}">
          </div>
        </div>
      </div>

      @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])
      @include('admin.quotations._debris_removal', ['quotation' => $quotation])
      @include('admin.quotations._additional_costs', ['quotation' => $quotation])
      @include('admin.quotations._payment_schedule', ['quotation' => $quotation])
    </div>

    <aside class="pq-sticky">
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
      <div class="pq-summary-card">
        <h4 class="cp-form-help-spaced">Live breakdown</h4>
        <div id="tm-live-root">
          <p class="admin-form-help" id="tm-live-empty">Turn on a family and enter quantities.</p>
          <div id="tm-live-body" hidden></div>
        </div>
        <p class="pq-hint cp-form-group-spaced">HST 13% on labour and materials (Ontario).</p>
      </div>
    </aside>
  </form>

  @if ($isEdit && ! $quotation->isLinked())
    @include('admin.quotations._link_to_project_card', [
        'quotation' => $quotation,
        'linkUrl' => route('admin.quotations.trimming.link', $quotation),
        'createProjectUrl' => route('admin.quotations.trimming.create-project', $quotation),
        'idPrefix' => 'tm',
        'projectNamePlaceholder' => 'Defaults to client + Trimming',
        'linkableProjects' => $linkableProjects,
        'wrapAdminCard' => true,
        'cardStyle' => 'margin-top:16px;',
        'createLineDescription' => 'field',
        'usePlacesAddress' => true,
    ])
  @endif
@endsection

@push('scripts')
  <script>
    window.TM_QUOTATION = {
      previewUrl: @json(route('admin.quotations.trimming.preview')),
      initialPreview: @json($initialPreview ?? null)
    };
  </script>
  <script src="{{ asset('js/admin-trimming-quotation.js') }}?v={{ @filemtime(public_path('js/admin-trimming-quotation.js')) ?: time() }}"></script>
@endpush

@extends('admin.layouts.app')

@php
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit fence quotation '.$quotation->quote_number : 'New fence quotation';
  $formAction = $isEdit
    ? route('admin.quotations.fence.update', $quotation)
    : route('admin.quotations.fence.store');

  $initialSides = $isEdit
    ? ($quotation->sides ?? [])
    : [['length_ft' => null, 'already_have_post_left' => false, 'already_have_post_right' => false, 'gate_goes_here' => false]];
  if (empty($initialSides)) {
    $initialSides = [['length_ft' => null, 'already_have_post_left' => false, 'already_have_post_right' => false, 'gate_goes_here' => false]];
  }

  $initialHeight = $isEdit ? (int) round((float) $quotation->fence_height_ft) : 6;
  $initialPostSize = old(
      'post_size',
      $isEdit
          ? ($quotation->resolvedPostSize())
          : \App\Models\FenceQuotation::POST_SIZE_4X4
  );
  $initialStyle = $isEdit ? $quotation->board_style : \App\Models\FenceQuotation::BOARD_STYLE_STANDARD;
  $initialGates = $isEdit ? (int) $quotation->gate_count : 1;

  $defaultLabour6 = (float) $settings->labour_rate_per_ft;
  $defaultLabour8 = (float) ($settings->labour_rate_8ft_per_ft ?? $settings->labour_rate_per_ft);
  $initialLabour6 = old(
      'labour_rate_per_ft',
      $isEdit ? (float) ($quotation->labour_rate_per_ft ?? $defaultLabour6) : $defaultLabour6
  );
  $initialLabour8 = old(
      'labour_rate_8ft_per_ft',
      $isEdit ? (float) ($quotation->labour_rate_8ft_per_ft ?? $defaultLabour8) : $defaultLabour8
  );

  // Initial breakdown (if editing, use the stored snapshot so the page
  // renders with real data before the first preview XHR returns).
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;

  $linkProject = $linkProject ?? null;
@endphp

@section('heading', $title)

@push('head')
  <style>
    {{-- Local styles specific to the fence calculator. General admin
         styles still come from admin.css / admin-client-projects.css. --}}
    .fq-calc-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 1100px) {
      .fq-calc-layout { grid-template-columns: 1fr; }
    }
    .fq-left-stack {
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 0;
    }
    .fq-sides-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 640px) {
      .fq-sides-grid { grid-template-columns: 1fr; }
    }
    .fq-side-card {
      border: 1px solid #e0e4ea;
      border-radius: 8px;
      padding: 12px;
      background: #fafbfc;
    }
    .fq-side-card h5 {
      margin: 0 0 8px;
      font-size: 13px;
      font-weight: 600;
      color: #374151;
    }
    .fq-side-meta {
      margin-top: 8px;
      font-size: 12px;
      color: #6b7280;
      min-height: 16px;
      line-height: 1.45;
    }
    .fq-geometry-summary {
      margin: 0 0 10px;
      font-size: 13px;
      color: #374151;
    }
    .fq-geometry-per-ft-note {
      font-size: 11px;
      color: #6b7280;
      font-weight: 400;
    }
    .fq-geometry-sides {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 4px;
    }
    .fq-geometry-side {
      padding: 10px 12px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fafbfc;
      font-size: 12px;
      color: #4b5563;
      line-height: 1.5;
    }
    .fq-geometry-side__title {
      font-weight: 600;
      color: #111827;
      margin-bottom: 4px;
    }
    .fq-geometry-side__sections {
      margin-top: 4px;
      color: #6b7280;
    }
    .fq-side-existing-posts {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 6px;
      margin-top: 10px;
      font-size: 12px;
      color: #475569;
    }
    .fq-side-existing-posts__label {
      font-weight: 500;
      color: #64748b;
    }
    .fq-side-existing-posts__checks {
      display: inline-flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 12px;
    }
    .fq-side-existing-posts label.admin-checkbox {
      flex-shrink: 0;
      font-size: 12px;
      font-weight: 500;
      margin: 0;
      gap: 6px;
    }
    .fq-side-existing-posts label.admin-checkbox .admin-checkbox__text {
      flex: 0 0 auto;
    }
    /* Right end: label before checkbox (already have post on [ ] left  right [ ]) */
    .fq-side-post-check--right .admin-checkbox__input:checked ~ .admin-checkbox__mark {
      background: var(--admin-primary, #024768);
      border-color: var(--admin-primary, #024768);
    }
    .fq-side-post-check--right .admin-checkbox__input:checked ~ .admin-checkbox__mark::after {
      content: '';
      position: absolute;
      left: 7px;
      top: 3px;
      width: 6px;
      height: 11px;
      border: solid #fff;
      border-width: 0 2.5px 2.5px 0;
      transform: rotate(45deg);
    }
    .fq-side-post-check--right .admin-checkbox__input:focus-visible ~ .admin-checkbox__mark {
      box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(2, 71, 104, 0.22));
    }
    .fq-side-gate {
      margin-top: 8px;
    }
    .fq-side-gate label.admin-checkbox {
      margin: 0;
      font-size: 12px;
      font-weight: 500;
      gap: 6px;
    }
    .fq-side-gate label.admin-checkbox.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }
    .fq-breakdown-card {
      position: sticky;
      top: 88px;
    }
    .fq-totals {
      margin-top: 14px;
      border-top: 1px solid #e0e4ea;
      padding-top: 12px;
    }
    .fq-totals-row {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
      font-size: 13px;
    }
    .fq-totals-row--total {
      border-top: 1px solid #d8dee4;
      margin-top: 6px;
      padding-top: 10px;
      font-weight: 600;
    }
    .fq-totals-row--grand {
      border-top: 1px solid #d8dee4;
      margin-top: 6px;
      padding-top: 10px;
      font-size: 17px;
      font-weight: 700;
      color: #024768;
    }
    .fq-materials-preview {
      width: 100%;
      border-collapse: collapse;
      font-size: 12.5px;
    }
    .fq-materials-preview th,
    .fq-materials-preview td {
      padding: 6px 8px;
      border-bottom: 1px solid #eef0f3;
      text-align: left;
      vertical-align: top;
    }
    .fq-materials-preview th { background: #f5f7f9; font-weight: 600; }
    .fq-materials-preview .num { text-align: right; font-variant-numeric: tabular-nums; }
    .fq-breakdown-note {
      color: #6b7280;
      font-size: 11px;
      display: block;
      margin-top: 2px;
    }
    .fq-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      background: #eef5fb;
      color: #024768;
      font-size: 11px;
      font-weight: 600;
    }
    .fq-inline-controls {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      align-items: start;
    }
    .fq-inline-controls .admin-form-group {
      margin: 0;
      min-width: 0;
    }
    .fq-inline-controls .admin-input {
      width: 100%;
    }
    @media (max-width: 1100px) {
      .fq-inline-controls {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 640px) {
      .fq-inline-controls {
        grid-template-columns: 1fr;
      }
    }
    .fq-overlap-wrap { display: none; }
    .fq-overlap-wrap.is-active { display: block; }
    .fq-sides-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .fq-sides-actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    @media (max-width: 640px) {
      .fq-sides-actions {
        width: 100%;
      }
      .fq-sides-actions .admin-btn {
        flex: 1 1 calc(50% - 3px);
      }
    }
    .fq-form-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    .fq-form-toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
    .fq-link-project-card {
      border: 1px dashed #c9d3e0;
      background: #fbfcfd;
      border-radius: 10px;
      padding: 18px 20px;
      margin-top: 20px;
    }
    .fq-link-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0;
      margin-top: 14px;
      align-items: start;
    }
    .fq-link-divider {
      border: 0;
      border-top: 1px solid #d7e4ef;
      margin: 16px 0;
    }
    .fq-link-col h5 {
      margin: 0 0 8px;
      font-size: 14px;
      font-weight: 600;
      color: #024768;
    }
    .fq-link-existing-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
    }
    @media (max-width: 640px) {
      .fq-link-existing-row {
        grid-template-columns: 1fr;
      }
      .fq-link-existing-row .admin-btn {
        width: 100%;
        min-height: 46px;
      }
    }
    .fq-create-project-form .admin-form-group {
      margin-bottom: 12px;
    }
    .fq-create-project-form .admin-btn--primary {
      width: 100%;
      margin-top: 4px;
      min-height: 46px;
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
    .fq-quote-labour-section {
      margin-top: 4px;
      padding-top: 16px;
      border-top: 1px dashed #e0e4ea;
    }
    .fq-quote-labour-grid {
      grid-template-columns: 1fr 1fr;
    }
    @media (max-width: 640px) {
      .fq-quote-labour-grid { grid-template-columns: 1fr; }
    }
  </style>
@endpush

@section('content')
  {{-- Toolbar: Back | Save actions -------------------------------- --}}
  <div class="fq-form-toolbar">
    <a href="{{ route('admin.quotations.fence.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to fence quotations
    </a>
    <div class="fq-form-toolbar-right">
      <a href="{{ route('admin.quotations.fence.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      @if ($isEdit)
        <a href="{{ route('admin.quotations.fence.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-print"></i> Print Material List
        </a>
      @endif
      <button type="submit" form="fq-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> {{ $isEdit ? 'Save changes' : 'Save quotation' }}
      </button>
    </div>
  </div>

  @if ($isEdit && $quotation->project)
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      <span>
        Linked to project
        <a href="{{ route('admin.client-projects.show', $quotation->project) }}#quotation">{{ $quotation->project->name }}</a>
        @if ($quotation->linked_at)
          · {{ \App\Models\ClientProject::formatDateTime($quotation->linked_at) }}
        @endif
      </span>
    </div>
  @endif

  @if (!empty($zeroPricedMaterials))
    <div class="admin-flash admin-flash--warning">
      <strong>Heads up:</strong> these materials have no price set yet — the calculator
      will treat them as $0.00:
      {{ implode(', ', $zeroPricedMaterials) }}.
      <a href="{{ route('admin.quotations.fence.config') }}" class="admin-text-link">
        Open configuration
      </a>.
    </div>
  @endif

  @if ($errors->any())
    <div class="admin-flash admin-flash--error">
      <strong>Please fix the following:</strong>
      <ul class="cp-list-indented">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="fq-calc-layout">
    {{-- ====================== LEFT: FORM =========================== --}}
    <div class="fq-left-stack">
      <form id="fq-form" method="post" action="{{ $formAction }}" class="admin-card">
        @csrf
        @if ($isEdit) @method('PUT') @endif
        @if ($linkProject)
          <input type="hidden" name="link_client_project_id" value="{{ $linkProject->id }}">
        @endif

        {{-- Client name -------------------------------------------- --}}
        <div class="admin-form-group">
          <label for="fq-client-name">Client name <span class="admin-required">*</span></label>
          <input id="fq-client-name" type="text" name="client_name"
                 @class(['admin-input', 'is-invalid' => $errors->has('client_name')])
                 value="{{ old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? '')) }}"
                 maxlength="160" required>
        </div>

        <div class="admin-form-group">
          <label for="fq-work-description">Description of the work</label>
          <textarea id="fq-work-description" name="work_description" rows="5" maxlength="5000"
                    @class(['admin-input', 'is-invalid' => $errors->has('work_description')])
                    placeholder="Briefly describe the service to be performed (visible to the client on the quotation PDF).">{{ old('work_description', $isEdit ? ($quotation->work_description ?? '') : '') }}</textarea>
          @include('admin.partials.quotation_work_description_ai', [
              'workDescriptionTextareaId' => 'fq-work-description',
              'aiContextFormId' => 'fq-form',
              'aiQuotationType' => 'fence',
          ])
          <p class="admin-form-help">Shown on the client PDF beneath the priced line item. Plain text — line breaks are preserved.</p>
          @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        @include('admin.quotations._commute_expense', ['quotation' => $quotation, 'linkProject' => $linkProject])

        {{-- Labour rates (saved per quotation) ----------------------- --}}
        <div class="admin-form-section fq-quote-labour-section">
          <h4 class="cp-heading-sm">Labour rates for this quotation</h4>
          <p class="admin-form-help cp-form-help-spaced">
            Stored with this quote{{ $isEdit ? '' : ' (prefilled from your last global defaults)' }}.
            Change them here to adjust labour for this project only.
          </p>
          <div class="fq-formula-grid fq-quote-labour-grid">
            <div class="admin-form-group">
              <label for="fq-labour-rate-6">Labour rate 6&prime; fence (CAD$ / linear ft)</label>
              <input id="fq-labour-rate-6" type="text" name="labour_rate_per_ft"
                     @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('labour_rate_per_ft')])
                     inputmode="decimal" autocomplete="off" required
                     value="{{ number_format((float) $initialLabour6, 2, '.', '') }}">
              @error('labour_rate_per_ft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="fq-labour-rate-8">Labour rate 8&prime; fence (CAD$ / linear ft)</label>
              <input id="fq-labour-rate-8" type="text" name="labour_rate_8ft_per_ft"
                     @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('labour_rate_8ft_per_ft')])
                     inputmode="decimal" autocomplete="off" required
                     value="{{ number_format((float) $initialLabour8, 2, '.', '') }}">
              @error('labour_rate_8ft_per_ft') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>
        </div>

        {{-- Fence height + gates + style --------------------------- --}}
        <div class="fq-inline-controls">
          <div class="admin-form-group">
            <label>Fence height</label>
            <select name="fence_height_ft" id="fq-height" class="admin-input">
              <option value="6" @selected((int) $initialHeight === 6)>6 feet</option>
              <option value="8" @selected((int) $initialHeight === 8)>8 feet</option>
            </select>
            <p class="admin-form-help">6' uses 10' posts + 6' boards. 8' uses 12' + 8'.</p>
          </div>

          <div class="admin-form-group">
            <label for="fq-post-size">Post size</label>
            <select name="post_size" id="fq-post-size" class="admin-input">
              <option value="{{ \App\Models\FenceQuotation::POST_SIZE_4X4 }}"
                      @selected($initialPostSize === \App\Models\FenceQuotation::POST_SIZE_4X4)>
                4×4
              </option>
              <option value="{{ \App\Models\FenceQuotation::POST_SIZE_6X6 }}"
                      @selected($initialPostSize === \App\Models\FenceQuotation::POST_SIZE_6X6)>
                6×6
              </option>
            </select>
            <p class="admin-form-help">Cross-section of the fence posts for layout and PDF description.</p>
          </div>

          <div class="admin-form-group">
            <label for="fq-gates">Gates</label>
            <input id="fq-gates" type="number" min="0" max="10" step="1"
                   name="gate_count" class="admin-input"
                   value="{{ old('gate_count', $initialGates) }}">
            <p class="admin-form-help">Each gate adds 1 post and {{ $settings->gate_rails }} rails.</p>
          </div>

          <div class="admin-form-group">
            <label>Board style</label>
            <select name="board_style" id="fq-style" class="admin-input">
              <option value="{{ \App\Models\FenceQuotation::BOARD_STYLE_STANDARD }}"
                      @selected($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_STANDARD)>
                Standard
              </option>
              <option value="{{ \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING }}"
                      @selected($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING)>
                Overlapping
              </option>
            </select>
          </div>
        </div>

        {{-- Sides ---------------------------------------------------- --}}
        <div class="admin-form-group cp-form-group-spaced">
          <div class="fq-sides-header">
            <div>
              <label class="cp-label-flush">Sides (up to 4)</label>
              <p class="admin-form-help cp-form-help-tight-top">Enter each side's length in feet.</p>
            </div>
            <div class="fq-sides-actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fq-add-side">
                <i class="fa fa-plus"></i> Add side
              </button>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fq-remove-side">
                <i class="fa fa-minus"></i> Remove last
              </button>
            </div>
          </div>
        </div>

        <div id="fq-sides" class="fq-sides-grid"></div>

        <input type="hidden" name="_serialised" id="fq-serialised" value="">
      </form>

      {{-- Project settings: link existing OR create new (edit, not linked) --}}
      @if ($isEdit && !$quotation->project)
        @php
          $linkable = $linkableProjects ?? collect();
        @endphp
        <div class="admin-card fq-link-project-card">
          <h4 class="cp-heading-md">Connect to a project</h4>
          <p class="admin-form-help cp-form-help-flush">
            Add this fence estimate as a line on a project <strong>Quotation</strong> (pre-tax materials + labour — the project quotation adds HST).
          </p>

          <div class="fq-link-grid">
            <div class="fq-link-col">
              <h5><i class="fa fa-link"></i> Existing project</h5>
              @if ($linkable->isNotEmpty())
                <form method="post" action="{{ route('admin.quotations.fence.link', $quotation) }}" class="fq-link-existing-form">
                  @csrf
                  <div class="fq-link-existing-row">
                    @include('admin.quotations._link_project_picker', [
                        'projects' => $linkable,
                        'id' => 'fq-link-project',
                    ])
                    <button type="submit" class="admin-btn admin-btn--primary">
                      <i class="fa fa-link"></i> Link
                    </button>
                  </div>
                </form>
              @else
                <p class="admin-form-help cp-form-help-flush">No active projects to link. Create a new one below, or finish a rejected/completed project first.</p>
              @endif
            </div>

            <hr class="fq-link-divider">

            <div class="fq-link-col">
              <h5><i class="fa fa-plus-circle"></i> New project</h5>
              <p class="admin-form-help cp-form-help-spaced">
                Always <strong>fixed price</strong>. Only client name is required. No automated client email is sent from here.
              </p>
              <form method="post"
                    action="{{ route('admin.quotations.fence.create-project', $quotation) }}"
                    class="fq-create-project-form">
                @csrf
                <div class="admin-form-group">
                  <label for="fq-new-project-name">Project name <span class="admin-form-help">(optional)</span></label>
                  <input type="text" id="fq-new-project-name" name="fp_project_name" class="admin-input" maxlength="255"
                         value="{{ old('fp_project_name') }}"
                         placeholder="Defaults to: Client name — Fence">
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-name">Client name <span class="admin-required">*</span></label>
                  @include('admin.quotations._fp_client_picker', [
                      'clientsSearchUrl' => route('admin.clients.search'),
                      'pickerId'         => 'fq-fp-client-picker',
                      'nameInputId'      => 'fq-new-client-name',
                      'emailInputId'     => 'fq-new-client-email',
                      'phoneInputId'     => 'fq-new-client-phone',
                      'addressInputId'   => 'fq-new-client-address',
                  ])
                  <input type="text" id="fq-new-client-name" name="fp_client_name" class="admin-input @error('fp_client_name') is-invalid @enderror"
                         maxlength="255" required value="{{ old('fp_client_name') }}">
                  @error('fp_client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-email">Email</label>
                  <input type="email" id="fq-new-client-email" name="fp_client_email" class="admin-input @error('fp_client_email') is-invalid @enderror"
                         maxlength="255" value="{{ old('fp_client_email') }}">
                  @error('fp_client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-phone">Phone</label>
                  <input type="text" id="fq-new-client-phone" name="fp_client_phone"
                         class="admin-input cp-phone-input @error('fp_client_phone') is-invalid @enderror"
                         inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx"
                         value="{{ old('fp_client_phone') ? \App\Models\ClientProject::formatPhone(old('fp_client_phone')) : '' }}">
                  @error('fp_client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-address">Address</label>
                  <input type="text" id="fq-new-client-address" name="fp_client_address" class="admin-input" maxlength="500"
                         value="{{ old('fp_client_address') }}">
                </div>
                <button type="submit" class="admin-btn admin-btn--primary">
                  <i class="fa fa-briefcase"></i> Create project &amp; add to quotation
                </button>
              </form>
            </div>
          </div>
        </div>
      @endif
    </div>

    {{-- ====================== RIGHT: BREAKDOWN ==================== --}}
    <aside class="admin-card fq-breakdown-card">
      @include('admin.partials.quotation_project_context', ['project' => $linkProject ?? ($isEdit ? $quotation->project : null)])
      
      <h3 class="cp-heading-md">Live breakdown</h3>
      <p class="admin-form-help cp-breakdown-intro">
        Preview updates as you type. <span class="fq-badge" id="fq-status-badge">Calculating…</span>
      </p>

      <div id="fq-geometry" class="admin-form-help cp-geometry-hint"></div>

      <div class="admin-table-scroll">
        <table class="fq-materials-preview" id="fq-materials">
          <thead>
            <tr>
              <th>Material</th>
              <th class="num">Qty</th>
              <th class="num">Unit</th>
              <th class="num">Subtotal</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="fq-totals">
        <div class="fq-totals-row">
          <span>Materials subtotal</span>
          <span id="fq-t-materials">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>HST on materials (13%)</span>
          <span id="fq-t-hst">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>Labour <span class="admin-form-help" id="fq-t-labour-rate"></span></span>
          <span id="fq-t-labour">CA$0.00</span>
        </div>
        <div class="fq-totals-row fq-totals-row--total">
          <span>Total</span>
          <span id="fq-t-total">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>HST on labour (13%)</span>
          <span id="fq-t-labour-hst">CA$0.00</span>
        </div>
        <div class="fq-totals-row is-hidden" id="fq-t-commute-row">
          <span>Commute expense <span class="admin-form-help">(incl. HST)</span></span>
          <span id="fq-t-commute">CA$0.00</span>
        </div>
        <div class="fq-totals-row fq-totals-row--grand">
          <span>Grand total</span>
          <span id="fq-t-grand">CA$0.00</span>
        </div>
      </div>
    </aside>
  </div>

@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var previewUrl = @json(route('admin.quotations.fence.preview'));
  var initialSides = @json($initialSides);
  var initialCalc = @json($initialCalc);

  var sidesEl = document.getElementById('fq-sides');
  var addBtn = document.getElementById('fq-add-side');
  var removeBtn = document.getElementById('fq-remove-side');
  var styleSelect = document.getElementById('fq-style');
  var heightSelect = document.getElementById('fq-height');
  var postSizeSelect = document.getElementById('fq-post-size');
  var gatesInput = document.getElementById('fq-gates');
  var labourRate6Input = document.getElementById('fq-labour-rate-6');
  var labourRate8Input = document.getElementById('fq-labour-rate-8');

  var statusBadge = document.getElementById('fq-status-badge');
  var geometryEl = document.getElementById('fq-geometry');
  var materialsBody = document.querySelector('#fq-materials tbody');
  var tMaterials = document.getElementById('fq-t-materials');
  var tHst = document.getElementById('fq-t-hst');
  var tLabour = document.getElementById('fq-t-labour');
  var tLabourRate = document.getElementById('fq-t-labour-rate');
  var tTotal = document.getElementById('fq-t-total');
  var tLabourHst = document.getElementById('fq-t-labour-hst');
  var tGrand = document.getElementById('fq-t-grand');
  var tCommuteRow = document.getElementById('fq-t-commute-row');
  var tCommute = document.getElementById('fq-t-commute');
  var lastCommuteAmount = 0;
  var lastCalc = null;

  var MAX_SIDES = 4;

  // ---------- Side rows -------------------------------------------------
  function renderSides(sides) {
    sidesEl.innerHTML = '';
    sides.forEach(function (side, i) {
      var leftChecked = !!(side && side.already_have_post_left);
      var rightChecked = !!(side && side.already_have_post_right);
      var gateChecked = !!(side && side.gate_goes_here);
      var card = document.createElement('div');
      card.className = 'fq-side-card';
      card.innerHTML = [
        '<h5>Side ' + (i + 1) + '</h5>',
        '<div class="admin-form-group cp-form-group-flush">',
        '  <label for="fq-side-' + i + '">Length (feet)</label>',
        '  <input id="fq-side-' + i + '" type="number" min="0.5" step="0.1"',
        '         name="sides[' + i + '][length_ft]"',
        '         class="admin-input fq-side-length"',
        '         value="' + (side && side.length_ft != null ? side.length_ft : '') + '"',
        '         data-side-index="' + i + '"',
        '         autocomplete="off">',
        '</div>',
        '<div class="fq-side-existing-posts">',
        '  <span class="fq-side-existing-posts__label">already have post on</span>',
        '  <span class="fq-side-existing-posts__checks">',
        '    <label class="admin-checkbox fq-side-post-check--left">',
        '      <input type="hidden" name="sides[' + i + '][already_have_post_left]" value="0">',
        '      <input type="checkbox" class="admin-checkbox__input fq-side-post-left"',
        '             name="sides[' + i + '][already_have_post_left]" value="1"',
        (leftChecked ? ' checked' : '') + '>',
        '      <span class="admin-checkbox__mark" aria-hidden="true"></span>',
        '      <span class="admin-checkbox__text">left</span>',
        '    </label>',
        '    <label class="admin-checkbox fq-side-post-check--right">',
        '      <input type="hidden" name="sides[' + i + '][already_have_post_right]" value="0">',
        '      <input type="checkbox" class="admin-checkbox__input fq-side-post-right"',
        '             name="sides[' + i + '][already_have_post_right]" value="1"',
        (rightChecked ? ' checked' : '') + '>',
        '      <span class="admin-checkbox__text">right</span>',
        '      <span class="admin-checkbox__mark" aria-hidden="true"></span>',
        '    </label>',
        '  </span>',
        '</div>',
        '<div class="fq-side-gate">',
        '  <label class="admin-checkbox fq-side-gate-check">',
        '    <input type="hidden" name="sides[' + i + '][gate_goes_here]" value="0">',
        '    <input type="checkbox" class="admin-checkbox__input fq-side-gate-here"',
        '           name="sides[' + i + '][gate_goes_here]" value="1"',
        (gateChecked ? ' checked' : '') + '>',
        '    <span class="admin-checkbox__mark" aria-hidden="true"></span>',
        '    <span class="admin-checkbox__text">Gate goes here</span>',
        '  </label>',
        '</div>',
        '<div class="fq-side-meta" data-meta="' + i + '"></div>'
      ].join('');
      sidesEl.appendChild(card);
    });
    updateGateCheckboxState();
    updateAddRemoveState();
  }

  function gateCountLimit() {
    var n = parseInt(gatesInput && gatesInput.value, 10);
    return isFinite(n) && n > 0 ? n : 0;
  }

  function updateGateCheckboxState() {
    var limit = gateCountLimit();
    var gateInputs = sidesEl.querySelectorAll('.fq-side-gate-here');
    var checkedCount = 0;

    gateInputs.forEach(function (input) {
      if (input.checked) checkedCount++;
    });

    if (checkedCount > limit) {
      var excess = checkedCount - limit;
      Array.prototype.slice.call(gateInputs).reverse().forEach(function (input) {
        if (excess <= 0) return;
        if (input.checked) {
          input.checked = false;
          excess--;
        }
      });
      checkedCount = limit;
    }

    gateInputs.forEach(function (input) {
      var label = input.closest('label.admin-checkbox');
      var disabled = limit > 0 && !input.checked && checkedCount >= limit;
      input.disabled = disabled;
      if (label) label.classList.toggle('is-disabled', disabled);
    });
  }

  function onGateCheckboxChange(changedInput) {
    if (changedInput.checked && gatesInput) {
      var checkedCount = sidesEl.querySelectorAll('.fq-side-gate-here:checked').length;
      var gateCount = parseInt(gatesInput.value, 10) || 0;
      if (checkedCount > gateCount) {
        gatesInput.value = String(checkedCount);
      }
    }

    var limit = gateCountLimit();
    if (limit === 0) {
      changedInput.checked = false;
      updateGateCheckboxState();
      return;
    }

    if (changedInput.checked) {
      var checked = sidesEl.querySelectorAll('.fq-side-gate-here:checked');
      if (checked.length > limit) {
        changedInput.checked = false;
      }
    }

    updateGateCheckboxState();
  }

  function updateAddRemoveState() {
    var n = sidesEl.querySelectorAll('.fq-side-card').length;
    addBtn.disabled = n>= MAX_SIDES;
    removeBtn.disabled = n <= 1;
  }

  addBtn.addEventListener('click', function () {
    var current = collectSides();
    if (current.length>= MAX_SIDES) return;
    current.push({ length_ft: null, already_have_post_left: false, already_have_post_right: false, gate_goes_here: false });
    renderSides(current);
    schedulePreview();
  });

  removeBtn.addEventListener('click', function () {
    var current = collectSides();
    if (current.length <= 1) return;
    current.pop();
    renderSides(current);
    schedulePreview();
  });

  function collectSides() {
    return Array.prototype.map.call(
      sidesEl.querySelectorAll('.fq-side-card'),
      function (card) {
        var lengthInput = card.querySelector('.fq-side-length');
        var v = parseFloat(lengthInput && lengthInput.value);
        var leftInput = card.querySelector('.fq-side-post-left');
        var rightInput = card.querySelector('.fq-side-post-right');
        var gateInput = card.querySelector('.fq-side-gate-here');
        var gateChecked = !!(gateInput && gateInput.checked);
        return {
          length_ft: isFinite(v) ? v : null,
          already_have_post_left: !!(leftInput && leftInput.checked),
          already_have_post_right: !!(rightInput && rightInput.checked),
          gate_goes_here: gateChecked,
        };
      }
    );
  }

  // ---------- Live preview ---------------------------------------------
  var previewTimer = null;
  function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 220);
  }

  function buildPayload() {
    var sides = collectSides();
    var gateSides = sides.filter(function (s) { return s.gate_goes_here; }).length;
    var gateCount = parseInt(gatesInput.value, 10) || 0;
    if (gateSides > gateCount) {
      gateCount = gateSides;
      gatesInput.value = String(gateCount);
    }
    return {
      sides: sides,
      fence_height_ft: parseInt(heightSelect.value, 10) || 6,
      post_size: postSizeSelect ? postSizeSelect.value : '4x4',
      board_style: styleSelect.value,
      gate_count: gateCount,
      labour_rate_per_ft: parseLabourRate(labourRate6Input),
      labour_rate_8ft_per_ft: parseLabourRate(labourRate8Input)
    };
  }

  function parseLabourRate(input) {
    if (!input) return 0;
    var raw = String(input.value || '').replace(/,/g, '');
    if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
      return window.cpMoneyMask.parse(raw);
    }
    var v = parseFloat(raw);
    return isFinite(v) ? v : 0;
  }

  function runPreview() {
    var payload = buildPayload();
    // If no valid sides yet, show a hint but don't hit the server.
    var hasValidSide = payload.sides.some(function (s) { return s.length_ft && s.length_ft> 0; });
    if (!hasValidSide) {
      renderIdle('Enter at least one side length to see the breakdown.');
      return;
    }

    statusBadge.textContent = 'Calculating…';
    statusBadge.style.background = '#fff4d4';
    statusBadge.style.color = '#8a5d00';

    fetch(previewUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        if (!res.ok) throw new Error('preview failed (' + res.status + ')');
        return res.json();
      })
      .then(renderCalc)
      .catch(function (err) {
        statusBadge.textContent = 'Error';
        statusBadge.style.background = '#fde1e1';
        statusBadge.style.color = '#8a1a1a';
        console.warn('[fence preview]', err);
      });
  }

  function renderIdle(msg) {
    statusBadge.textContent = 'Idle';
    statusBadge.style.background = '#eef1f5';
    statusBadge.style.color = '#556';
    geometryEl.textContent = msg || '';
    materialsBody.innerHTML = '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';
    tMaterials.textContent = 'CA$0.00';
    tHst.textContent = 'CA$0.00';
    tLabour.textContent = 'CA$0.00';
    tGrand.textContent = 'CA$0.00';
    tLabourRate.textContent = '';
  }

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatFt(n) {
    var v = Number(n);
    if (!isFinite(v)) return '0.00';
    return v.toFixed(2);
  }

  function formatSectionLengths(lengths) {
    if (!lengths || !lengths.length) return '';
    return lengths.map(formatFt).join(', ') + ' ft';
  }

  function sideMetaHtml(s) {
    var posts = Number(s.posts || 0);
    var parts = [
      s.sections + ' section' + (s.sections === 1 ? '' : 's'),
      posts + ' post' + (posts === 1 ? '' : 's'),
    ];
    if (s.gate_goes_here) {
      parts.push(formatFt(s.gate_opening_ft || 4) + ' ft gate');
    }
    if (s.section_lengths_ft && s.section_lengths_ft.length) {
      parts.push('spans ' + formatSectionLengths(s.section_lengths_ft));
    } else if (Number(s.max_section_length_ft || 0) > 0) {
      parts.push('max ' + formatFt(s.max_section_length_ft) + ' ft span');
    }
    return parts.join(' · ');
  }

  function sideBreakdownHtml(s) {
    var posts = Number(s.posts || 0);
    var title = 'Side ' + s.index + ' — ' + formatFt(s.length_ft) + ' ft';
    var stats = s.sections + ' section' + (s.sections === 1 ? '' : 's')
      + ' · ' + posts + ' post' + (posts === 1 ? '' : 's');
    if (s.gate_goes_here) {
      stats += ' · ' + formatFt(s.gate_opening_ft || 4) + ' ft gate';
      if (Number(s.fence_length_ft || 0) > 0) {
        stats += ' · ' + formatFt(s.fence_length_ft) + ' ft fence run';
      }
    }
    var html = '<div class="fq-geometry-side">'
      + '<div class="fq-geometry-side__title">' + escapeHtml(title) + '</div>'
      + '<div>' + escapeHtml(stats) + '</div>';
    if (s.section_lengths_ft && s.section_lengths_ft.length) {
      html += '<div class="fq-geometry-side__sections">Section spans (excl. posts): '
        + escapeHtml(formatSectionLengths(s.section_lengths_ft)) + '</div>';
    }
    html += '</div>';
    return html;
  }

  function renderCalc(calc) {
    lastCalc = calc;
    statusBadge.textContent = 'Up to date';
    statusBadge.style.background = '#e8f4ea';
    statusBadge.style.color = '#0f5132';

    var g = calc.geometry || {};
    var t = calc.totals || {};
    var sidesHtml = (g.sides_breakdown || []).map(sideBreakdownHtml).join('');
    var linearFt = Number(g.total_linear_ft || 0);
    var withTaxTotal = Number(t.grand_total || 0);
    var perFt = linearFt > 0 ? withTaxTotal / linearFt : 0;
    var perFtHtml = linearFt > 0
      ? ' · <strong>' + money(perFt) + '/ft</strong> <span class="fq-geometry-per-ft-note">(materials + labour, incl. tax)</span>'
      : '';
    geometryEl.innerHTML =
      '<p class="fq-geometry-summary"><strong>' + (g.total_linear_ft || 0) + ' linear ft</strong> · '
      + (g.sections_total || 0) + ' section' + ((g.sections_total === 1) ? '' : 's') + ' · '
      + (g.posts_before_waste || 0) + ' posts (pre-waste)'
      + perFtHtml + '</p>'
      + (sidesHtml ? '<div class="fq-geometry-sides">' + sidesHtml + '</div>' : '');

    // Per-side section meta on input cards.
    (g.sides_breakdown || []).forEach(function (s) {
      var meta = document.querySelector('[data-meta="' + (s.index - 1) + '"]');
      if (meta) {
        meta.innerHTML = sideMetaHtml(s);
      }
    });

    // Materials table.
    var rows = (calc.materials || []).map(function (m) {
      var unit = (m.unit || '');
      var notesHtml = m.notes
        ? '<span class="fq-breakdown-note">' + escapeHtml(m.notes) + '</span>'
        : '';
      return '<tr>' +
        '<td>' + escapeHtml(m.name) + notesHtml + '</td>' +
        '<td class="num">' + formatQty(m.quantity) + ' ' + escapeHtml(unit) + '</td>' +
        '<td class="num">' + money(m.unit_price) + '</td>' +
        '<td class="num">' + money(m.subtotal) + '</td>' +
        '</tr>';
    }).join('');
    materialsBody.innerHTML = rows || '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';

    tMaterials.textContent = money(t.materials_subtotal);
    tHst.textContent = money(t.hst);
    tLabour.textContent = money(t.labour_subtotal);
    tTotal.textContent = money(t.total);
    tLabourHst.textContent = money(t.labour_hst);
    if (tCommuteRow) tCommuteRow.classList.toggle('is-hidden', !(lastCommuteAmount> 0));
    if (tCommute) tCommute.textContent = money(lastCommuteAmount);
    tGrand.textContent = money(Number(t.grand_total || 0) + lastCommuteAmount);
    if (t.labour_rate_per_ft) {
      tLabourRate.textContent = '(' + money(t.labour_rate_per_ft) + '/ft)';
    }
  }

  function formatQty(q) {
    if (typeof q !== 'number') q = Number(q);
    if (!isFinite(q)) return '0';
    return Math.abs(q - Math.round(q)) < 1e-6
      ? String(Math.round(q))
      : q.toFixed(2);
  }

  function escapeHtml(s) {
    s = String(s == null ? '' : s);
    return s.replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c];
    });
  }

  // ---------- Wire up everything ---------------------------------------
  renderSides(initialSides);

  // Any field change re-runs the preview.
  ['input', 'change'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      if (!e.target || !e.target.closest) return;
      if (e.target.matches('.fq-side-gate-here')) {
        onGateCheckboxChange(e.target);
      }
      if (e.target.matches('#fq-gates')) {
        updateGateCheckboxState();
      }
      if (e.target.matches('#fq-labour-rate-6, #fq-labour-rate-8, #fq-form input, #fq-form select')) {
        schedulePreview();
      }
    });
  });

  if (initialCalc) {
    renderCalc(initialCalc);
  } else {
    schedulePreview();
  }

  document.addEventListener('commute-expense:change', function (e) {
    var d = e.detail || {};
    lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
    if (lastCalc) {
      renderCalc(lastCalc);
    }
  });
})();
</script>
@endpush

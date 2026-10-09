@once
  @push('scripts')
    <script src="{{ asset('js/admin-commute-expense.js') }}?v={{ @filemtime(public_path('js/admin-commute-expense.js')) ?: time() }}"></script>
  @endpush
@endonce

@php
  $quotation = $quotation ?? null;
  $linkProject = $linkProject ?? null;
  $isEdit = filled($quotation);

  $travelProjectId = $linkProject?->id
    ?? ($isEdit ? $quotation->client_project_id : null)
    ?? (request()->filled('project') ? (int) request('project') : null);

  // Forced Yes/No — no default on create. Edit keeps the saved choice.
  $includeCommuteChoice = null;
  if (old('include_commute') !== null && old('include_commute') !== '') {
      $includeCommuteChoice = old('include_commute') === '1' || old('include_commute') === 1 || old('include_commute') === true;
  } elseif ($isEdit) {
      $includeCommuteChoice = (bool) ($quotation->include_commute ?? false);
  }

  $commutePercent = old('commute_trip_percent');
  if ($commutePercent === null || $commutePercent === '') {
      $commutePercent = $isEdit
          ? rtrim(rtrim(number_format((float) ($quotation->commute_trip_percent ?? 100), 2, '.', ''), '0'), '.')
          : '100';
      if ($commutePercent === '') {
          $commutePercent = '100';
      }
  }

  $commuteTrips = old('commute_estimated_trips');
  if ($commuteTrips === null || $commuteTrips === '') {
      $commuteTrips = (string) ($isEdit ? (int) ($quotation->commute_estimated_trips ?? 1) : 1);
  }

  $savedRoundTrip = old('commute_round_trip_cost');
  if (($savedRoundTrip === null || $savedRoundTrip === '') && $isEdit && $quotation->commute_round_trip_cost !== null) {
      $savedRoundTrip = number_format((float) $quotation->commute_round_trip_cost, 2, '.', '');
  }
@endphp

<div class="admin-card q-commute-card" id="q-commute-expense"
     data-cp-commute-expense
     data-travel-url-template="{{ url('admin/client-projects/__ID__/travel-estimate') }}"
     data-initial-project-id="{{ $travelProjectId ?: '' }}"
     data-saved-round-trip="{{ $savedRoundTrip ?? '' }}">
  <h3 class="q-commute-card__title"><i class="fa fa-road" aria-hidden="true"></i> Charge Commute expense? <span class="cp-required">*</span></h3>


  <fieldset class="q-commute-choice" role="radiogroup" aria-label="Include commute fuel">
    <legend class="util-sr-only">Include commute fuel in this quotation?</legend>
    <label class="q-commute-choice__option{{ $includeCommuteChoice === true ? ' is-selected' : '' }}">
      <input type="radio" name="include_commute" value="1" class="q-commute-choice__input" data-cp-commute-include-yes
             @checked($includeCommuteChoice === true) required>
      <span class="q-commute-choice__text">Yes</span>
    </label>
    <label class="q-commute-choice__option{{ $includeCommuteChoice === false ? ' is-selected' : '' }}">
      <input type="radio" name="include_commute" value="0" class="q-commute-choice__input" data-cp-commute-include-no
             @checked($includeCommuteChoice === false) required>
      <span class="q-commute-choice__text">No</span>
    </label>
  </fieldset>
  @error('include_commute')
    <p class="admin-form-error" style="margin-top:8px;">{{ $message }}</p>
  @enderror

  <div class="q-commute-card__body" data-cp-commute-body @if ($includeCommuteChoice !== true) hidden @endif>
    <div class="q-commute-card__metrics">
      <div class="q-commute-metric">
        <span class="q-commute-metric__label">Round trip (fuel)</span>
        <span class="q-commute-metric__value" data-cp-commute-round-trip>—</span>
        <span class="admin-form-help" data-cp-commute-round-trip-help hidden></span>
      </div>
      <div class="q-commute-metric">
        <span class="q-commute-metric__label">Added to quotation</span>
        <span class="q-commute-metric__value q-commute-metric__value--hl" data-cp-commute-added>CA$0.00</span>
      </div>
    </div>
    <p class="admin-form-help" data-cp-commute-status hidden></p>

    <div class="q-commute-card__fields">
      <div class="admin-form-group q-commute-field q-commute-field--pct">
        <label for="q-commute-percent">
          % of round trip
          <x-admin.info-tip>100% = full round-trip fuel cost per trip.</x-admin.info-tip>
        </label>
        <div class="q-commute-field__inline">
          <input type="text" inputmode="decimal" id="q-commute-percent" name="commute_trip_percent"
                 class="admin-input" maxlength="6" autocomplete="off"
                 value="{{ $commutePercent }}" data-cp-commute-percent>
          <span class="q-commute-field__suffix">%</span>
        </div>
      </div>
      <div class="admin-form-group q-commute-field">
        <label for="q-commute-trips">Estimated trips</label>
        <input type="number" id="q-commute-trips" name="commute_estimated_trips" class="admin-input"
               min="1" max="999" step="1" value="{{ $commuteTrips }}" data-cp-commute-trips>
      </div>
    </div>
  </div>

  <input type="hidden" name="commute_round_trip_cost" value="{{ $savedRoundTrip ?? '' }}" data-cp-commute-round-trip-hidden>
</div>

<style>
  .q-commute-choice {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 10px;
    padding: 0;
    border: 0;
  }
  .q-commute-choice__option {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    margin: 0;
    padding: 8px 10px;
    border: 1px solid var(--admin-border, #cbd5e1);
    border-radius: 10px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #0f172a);
    cursor: pointer;
  }
  .q-commute-choice__option.is-selected,
  .q-commute-choice__option:has(:checked) {
    border-color: var(--admin-primary, #024768);
    background: var(--admin-primary-soft, #f0f9ff);
    box-shadow: 0 0 0 1px var(--admin-primary, #024768);
  }
  .q-commute-choice__input { margin: 0; }
  .q-commute-choice__text { font-size: 0.9rem; color: var(--admin-heading, #0f172a); }
  [data-cp-commute-status] { margin: 8px 0 0; }
  .util-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }
</style>

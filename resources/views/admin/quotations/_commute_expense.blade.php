@php
  $quotation = $quotation ?? null;
  $linkProject = $linkProject ?? null;
  $isEdit = filled($quotation);

  $travelProjectId = $linkProject?->id
    ?? ($isEdit ? $quotation->client_project_id : null)
    ?? (request()->filled('project') ? (int) request('project') : null);

  if (old('include_commute') !== null) {
      $includeCommute = filter_var(old('include_commute'), FILTER_VALIDATE_BOOLEAN);
  } elseif ($isEdit) {
      $includeCommute = (bool) ($quotation->include_commute ?? true);
  } else {
      $includeCommute = true;
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
  <h3 class="q-commute-card__title"><i class="fa fa-road" aria-hidden="true"></i> Commute expense</h3>
  <p class="admin-form-help q-commute-card__intro">
    Add fuel cost for trips to the job site. Uses the same round-trip estimate as the project overview (home → site → home). The amount added includes 13% HST.
  </p>

  <label class="admin-checkbox admin-checkbox--block q-commute-card__toggle">
    <input type="hidden" name="include_commute" value="0">
    <input type="checkbox" name="include_commute" value="1" class="admin-checkbox__input" data-cp-commute-include @checked($includeCommute)>
    <span class="admin-checkbox__mark" aria-hidden="true"></span>
    <span class="admin-checkbox__text">Include commute fuel in this quotation</span>
  </label>

  <div class="q-commute-card__body" data-cp-commute-body @if (!$includeCommute) hidden @endif>
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

    <div class="q-commute-card__fields">
      <div class="admin-form-group q-commute-field q-commute-field--pct">
        <label for="q-commute-percent">Charge % of round trip</label>
        <div class="q-commute-field__inline">
          <input type="text" inputmode="decimal" id="q-commute-percent" name="commute_trip_percent"
                 class="admin-input" maxlength="6" autocomplete="off"
                 value="{{ $commutePercent }}" data-cp-commute-percent>
          <span class="q-commute-field__suffix">%</span>
        </div>
        <p class="admin-form-help">100% = full round-trip fuel cost per trip.</p>
      </div>
      <div class="admin-form-group q-commute-field">
        <label for="q-commute-trips">Estimated trips</label>
        <input type="text" inputmode="numeric" id="q-commute-trips" name="commute_estimated_trips"
               class="admin-input" maxlength="3" autocomplete="off"
               value="{{ $commuteTrips }}" data-cp-commute-trips>
        <p class="admin-form-help">How many round trips you expect for this job.</p>
      </div>
    </div>

    <input type="hidden" name="commute_round_trip_cost" value="{{ $savedRoundTrip ?? '' }}" data-cp-commute-round-trip-hidden>
    <p class="admin-form-help q-commute-card__status" data-cp-commute-status aria-live="polite"></p>
  </div>
</div>

@once
  @push('head')
    <link rel="stylesheet" href="{{ asset('css/admin-vehicles.css') }}?v={{ @filemtime(public_path('css/admin-vehicles.css')) ?: time() }}">
  @endpush
  @push('scripts')
    <script src="{{ asset('js/admin-commute-expense.js') }}?v={{ @filemtime(public_path('js/admin-commute-expense.js')) ?: time() }}"></script>
  @endpush
@endonce

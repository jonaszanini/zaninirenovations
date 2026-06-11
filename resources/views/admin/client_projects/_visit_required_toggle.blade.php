{{--
  Visit required toggle + expandable visit details.
  @param string $visitRequiredId
  @param string $expandableId
  @param string $visitDateId
  @param bool $expandOpen
  @param string|null $visitDateValue
  @param bool $showVisitDone
  @param bool $visitDoneChecked
  @param string|null $visitDoneId
  @param bool $slowExpand — 1s open/close animation (create project)
--}}
@php
  $expandOpen = $expandOpen ?? false;
  $visitDateValue = $visitDateValue ?? null;
  $showVisitDone = $showVisitDone ?? false;
  $visitDoneChecked = $visitDoneChecked ?? false;
  $slowExpand = $slowExpand ?? false;
@endphp
<input type="hidden" name="visit_required" value="0">
<label class="cp-visit-toggle" for="{{ $visitRequiredId }}">
  <input type="checkbox"
    name="visit_required"
    value="1"
    id="{{ $visitRequiredId }}"
    @checked($visitRequiredChecked ?? false)>
  <span class="cp-visit-toggle__control" aria-hidden="true"></span>
  <span class="cp-visit-toggle__label">Visit required</span>
</label>
<div id="{{ $expandableId }}"
  class="cp-visit-expandable @if ($slowExpand) cp-visit-expandable--slow @endif @if ($expandOpen) is-open @endif"
  aria-hidden="{{ $expandOpen ? 'false' : 'true' }}">
  <div class="cp-visit-expandable__inner">
    <p class="admin-form-help cp-visit-expandable__help">
      On-site visit before quoting. Set a visit date when you know it; leave the date empty if you only need to flag that a visit is required.
    </p>
    <div class="admin-form-group cp-visit-date">
      <label for="{{ $visitDateId }}">Visit date</label>
      <input class="admin-input" type="date" id="{{ $visitDateId }}" name="visit_date" value="{{ $visitDateValue }}">
      @error('visit_date') <p class="admin-form-error">{{ $message }}</p> @enderror
    </div>
    @if ($showVisitDone)
      <input type="hidden" name="visit_done" value="0">
      <div class="admin-form-group cp-visit-done-row">
        <label class="cp-visit-toggle cp-visit-toggle--compact" for="{{ $visitDoneId }}">
          <input type="checkbox" name="visit_done" value="1" id="{{ $visitDoneId }}" @checked($visitDoneChecked)>
          <span class="cp-visit-toggle__control" aria-hidden="true"></span>
          <span class="cp-visit-toggle__label">Visit done</span>
        </label>
      </div>
    @endif
  </div>
</div>

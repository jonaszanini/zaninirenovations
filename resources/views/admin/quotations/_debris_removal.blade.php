@once
  @push('scripts')
    <script src="{{ asset('js/admin-debris-removal.js') }}?v={{ @filemtime(public_path('js/admin-debris-removal.js')) ?: time() }}"></script>
  @endpush
@endonce

@php
  $quotation = $quotation ?? null;
  $isEdit = filled($quotation);

  $includeDebrisChoice = null;
  if (old('include_debris_removal') !== null && old('include_debris_removal') !== '') {
      $includeDebrisChoice = old('include_debris_removal') === '1'
          || old('include_debris_removal') === 1
          || old('include_debris_removal') === true;
  } elseif ($isEdit) {
      $includeDebrisChoice = (bool) ($quotation->include_debris_removal ?? false);
  }

  $debrisAmount = old('debris_removal_amount');
  if ($debrisAmount === null || $debrisAmount === '') {
      $debrisAmount = $isEdit && (float) ($quotation->debris_removal_amount ?? 0) > 0
          ? number_format((float) $quotation->debris_removal_amount, 2, '.', '')
          : '';
  }
@endphp

<div class="admin-card q-debris-card" id="q-debris-removal" data-cp-debris-removal>
  <h3 class="q-debris-card__title"><i class="fa fa-trash" aria-hidden="true"></i> Debris removal <span class="cp-required">*</span></h3>

  <fieldset class="q-commute-choice" role="radiogroup" aria-label="Include debris removal">
    <legend class="util-sr-only">Include debris removal in this quotation?</legend>
    <label class="q-commute-choice__option{{ $includeDebrisChoice === true ? ' is-selected' : '' }}">
      <input type="radio" name="include_debris_removal" value="1" class="q-commute-choice__input" data-cp-debris-include-yes
             @checked($includeDebrisChoice === true) required>
      <span class="q-commute-choice__text"><strong>Yes</strong> — charge debris removal</span>
    </label>
    <label class="q-commute-choice__option{{ $includeDebrisChoice === false ? ' is-selected' : '' }}">
      <input type="radio" name="include_debris_removal" value="0" class="q-commute-choice__input" data-cp-debris-include-no
             @checked($includeDebrisChoice === false) required>
      <span class="q-commute-choice__text"><strong>No</strong> — do not charge</span>
    </label>
  </fieldset>
  @error('include_debris_removal')
    <p class="admin-form-error" style="margin-top:8px;">{{ $message }}</p>
  @enderror

  <div class="q-debris-card__body" data-cp-debris-body @if ($includeDebrisChoice !== true) hidden @endif>
    <div class="admin-form-group q-debris-amount-group">
      <label for="q-debris-amount">Amount to charge <span class="cp-required">*</span></label>
      <div class="q-debris-amount-wrap">
        <span class="q-debris-amount-prefix">CA$</span>
        <input type="number"
               id="q-debris-amount"
               name="debris_removal_amount"
               class="admin-input q-debris-amount-input"
               inputmode="decimal"
               step="0.01"
               min="0"
               max="999999"
               placeholder="0.00"
               value="{{ $debrisAmount }}"
               data-cp-debris-amount
               @if ($includeDebrisChoice === true) required @endif>
      </div>
      <p class="admin-form-help">Incl. HST — added to the grand total; hidden from the client PDF.</p>
      @error('debris_removal_amount')
        <p class="admin-form-error">{{ $message }}</p>
      @enderror
    </div>
  </div>
</div>

<style>
  .q-debris-card {
    margin-bottom: 16px;
  }
  .q-debris-card__title {
    margin: 0 0 6px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
  }
  .q-debris-card__intro { margin: 0 0 14px; }
  .q-debris-card__body {
    margin-top: 4px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
  }
  .q-debris-amount-group { margin-bottom: 0; }
  .q-debris-amount-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 240px;
  }
  .q-debris-amount-prefix {
    flex: 0 0 auto;
    font-size: 0.9rem;
    font-weight: 600;
    color: #64748b;
  }
  /* Beat `.admin-input { padding: … }` so the value/placeholder is not cramped. */
  .q-debris-amount-wrap .admin-input.q-debris-amount-input {
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 600;
  }
</style>

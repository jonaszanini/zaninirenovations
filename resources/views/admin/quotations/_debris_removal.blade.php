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
  <h3 class="q-debris-card__title"><i class="fa fa-trash" aria-hidden="true"></i> Charge Debris removal? <span class="cp-required">*</span></h3>

  <fieldset class="q-commute-choice" role="radiogroup" aria-label="Include debris removal">
    <legend class="util-sr-only">Include debris removal in this quotation?</legend>
    <label class="q-commute-choice__option{{ $includeDebrisChoice === true ? ' is-selected' : '' }}">
      <input type="radio" name="include_debris_removal" value="1" class="q-commute-choice__input" data-cp-debris-include-yes
             @checked($includeDebrisChoice === true) required>
      <span class="q-commute-choice__text">Yes</span>
    </label>
    <label class="q-commute-choice__option{{ $includeDebrisChoice === false ? ' is-selected' : '' }}">
      <input type="radio" name="include_debris_removal" value="0" class="q-commute-choice__input" data-cp-debris-include-no
             @checked($includeDebrisChoice === false) required>
      <span class="q-commute-choice__text">No</span>
    </label>
  </fieldset>
  @error('include_debris_removal')
    <p class="admin-form-error" style="margin-top:8px;">{{ $message }}</p>
  @enderror

  <div class="q-debris-card__body" data-cp-debris-body @if ($includeDebrisChoice !== true) hidden @endif>
    <div class="admin-form-group q-debris-amount-group">
      <span class="admin-label-with-tip">
        <label for="q-debris-amount">Amount to charge <span class="cp-required">*</span></label>
        <x-admin.info-tip label="Amount to charge">Incl. HST — folded into Labour on the client PDF (not shown as its own line).</x-admin.info-tip>
      </span>
      <input type="text"
             id="q-debris-amount"
             name="debris_removal_amount"
             @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('debris_removal_amount')])
             inputmode="decimal"
             autocomplete="off"
            
             placeholder="0.00"
             value="{{ $debrisAmount }}"
             data-cp-debris-amount
             @if ($includeDebrisChoice === true) required @endif>
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
    color: var(--admin-heading, #0f172a);
  }
  .q-debris-card__intro { margin: 0 0 14px; }
  .q-debris-card__body {
    margin-top: 0;
    padding: 10px 12px;
    background: var(--admin-surface-hover, #f8fafc);
    border: 1px dashed var(--admin-border, #cbd5e1);
    border-radius: 8px;
  }
  .q-debris-amount-group { margin-bottom: 0; }
  .q-debris-amount-group .cp-money-input {
    max-width: 200px;
  }
</style>

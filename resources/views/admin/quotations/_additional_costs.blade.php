@once
  @push('scripts')
    <script src="{{ asset('js/admin-additional-costs.js') }}?v={{ @filemtime(public_path('js/admin-additional-costs.js')) ?: time() }}"></script>
  @endpush
@endonce

@php
  $quotation = $quotation ?? null;
  $isEdit = filled($quotation);

  $additionalCostsRows = old('additional_costs');
  if (! is_array($additionalCostsRows)) {
      $additionalCostsRows = $isEdit && is_array($quotation->additional_costs ?? null)
          ? $quotation->additional_costs
          : [];
  }

  $normalizedRows = [];
  foreach ($additionalCostsRows as $row) {
      if (! is_array($row)) {
          continue;
      }
      $desc = trim((string) ($row['description'] ?? ''));
      $amt = $row['amount'] ?? '';
      $hst = $row['hst_included'] ?? '1';
      $hstIncluded = $hst === true || $hst === 1 || $hst === '1';
      if ($desc === '' && ($amt === '' || $amt === null || (is_numeric($amt) && (float) $amt <= 0))) {
          continue;
      }
      $normalizedRows[] = [
          'description' => $desc,
          'amount' => is_numeric($amt) ? number_format((float) $amt, 2, '.', '') : (string) $amt,
          'hst_included' => $hstIncluded,
      ];
  }

  if ($normalizedRows === []) {
      $normalizedRows[] = ['description' => '', 'amount' => '', 'hst_included' => true];
  }
@endphp

<div class="admin-card q-additional-costs-card" id="q-additional-costs" data-cp-additional-costs>
  <div class="q-additional-costs-card__head">
    <h3 class="q-additional-costs-card__title">
      <i class="fa fa-plus-circle" aria-hidden="true"></i> Additional costs
    </h3>
    <p class="admin-form-help q-additional-costs-card__intro">
      Optional extras (permits, rentals, etc.). Added into the <strong>materials</strong> subtotal and grand total.
      Hidden from the client PDF.
    </p>
  </div>

  <div class="q-ac-rows" data-ac-rows>
    @foreach ($normalizedRows as $idx => $row)
      <div class="q-ac-row" data-ac-row data-ac-index="{{ $idx }}">
        <div class="admin-form-group q-ac-row__desc">
          <label for="q-ac-desc-{{ $idx }}">Description</label>
          <input type="text"
                 id="q-ac-desc-{{ $idx }}"
                 name="additional_costs[{{ $idx }}][description]"
                 class="admin-input"
                 maxlength="200"
                 placeholder="e.g. Dumpster rental, permits"
                 value="{{ $row['description'] }}"
                 data-ac-description>
        </div>
        <div class="admin-form-group q-ac-row__amount">
          <label for="q-ac-amt-{{ $idx }}">Amount (CA$)</label>
          <input type="number"
                 id="q-ac-amt-{{ $idx }}"
                 name="additional_costs[{{ $idx }}][amount]"
                 class="admin-input"
                 inputmode="decimal"
                 step="0.01"
                 min="0"
                 max="999999"
                 placeholder="0.00"
                 value="{{ $row['amount'] }}"
                 data-ac-amount>
        </div>
        <div class="admin-form-group q-ac-row__hst">
          <span class="q-ac-row__hst-label">Tax</span>
          <div class="q-ac-hst-choice" role="radiogroup" aria-label="Tax on additional cost {{ $idx + 1 }}">
            <label class="q-ac-hst-option{{ $row['hst_included'] ? ' is-selected' : '' }}">
              <input type="radio"
                     name="additional_costs[{{ $idx }}][hst_included]"
                     value="1"
                     class="q-ac-hst-option__input"
                     data-ac-hst-yes
                     @checked($row['hst_included'])>
              <span>Tax included</span>
            </label>
            <label class="q-ac-hst-option{{ ! $row['hst_included'] ? ' is-selected' : '' }}">
              <input type="radio"
                     name="additional_costs[{{ $idx }}][hst_included]"
                     value="0"
                     class="q-ac-hst-option__input"
                     data-ac-hst-no
                     @checked(! $row['hst_included'])>
              <span>Before tax</span>
            </label>
          </div>
        </div>
        <div class="q-ac-row__actions">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm"
                  data-ac-remove
                  title="Remove cost"
                  aria-label="Remove additional cost">
            <i class="fa fa-times" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    @endforeach
  </div>

  <div class="q-ac-footer">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ac-add>
      <i class="fa fa-plus" aria-hidden="true"></i> Add cost
    </button>
    <p class="admin-form-help q-ac-summary" data-ac-summary hidden></p>
  </div>

  @error('additional_costs')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  @error('additional_costs.*.description')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  @error('additional_costs.*.amount')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
</div>

<style>
  .q-additional-costs-card { margin-bottom: 16px; }
  .q-additional-costs-card__title {
    margin: 0 0 6px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
  }
  .q-additional-costs-card__intro { margin: 0 0 14px; }
  .q-ac-rows { display: flex; flex-direction: column; gap: 12px; }
  .q-ac-row {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(110px, 0.7fr) minmax(200px, 1fr) auto;
    gap: 12px;
    align-items: end;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
  }
  .q-ac-row .admin-form-group { margin-bottom: 0; }
  .q-ac-row__hst-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .q-ac-hst-choice {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .q-ac-hst-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 8px 10px;
    min-height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 0.85rem;
  }
  .q-ac-hst-option.is-selected,
  .q-ac-hst-option:has(:checked) {
    border-color: var(--admin-primary, #024768);
    background: #f0f9ff;
    box-shadow: 0 0 0 1px var(--admin-primary, #024768);
  }
  .q-ac-hst-option__input { margin: 0; }
  .q-ac-row__actions {
    display: flex;
    align-items: flex-end;
    padding-bottom: 2px;
  }
  .q-ac-row__actions .admin-btn {
    min-width: 44px;
    min-height: 44px;
  }
  .q-ac-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
  }
  .q-ac-summary { margin: 0; }
  @media (max-width: 768px) {
    .q-ac-row {
      grid-template-columns: 1fr;
      align-items: stretch;
    }
    .q-ac-row__actions {
      justify-content: flex-end;
      padding-bottom: 0;
    }
  }
</style>

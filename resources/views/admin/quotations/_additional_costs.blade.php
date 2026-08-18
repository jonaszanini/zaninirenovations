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
          <input type="text"
                 id="q-ac-amt-{{ $idx }}"
                 name="additional_costs[{{ $idx }}][amount]"
                 class="admin-input cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 data-cp-money-entry="direct"
                 placeholder="0.00"
                 value="{{ $row['amount'] }}"
                 data-ac-amount>
        </div>
        <div class="admin-form-group q-ac-row__hst">
          <span class="q-ac-row__hst-label" id="q-ac-hst-label-{{ $idx }}">Tax</span>
          <label class="cp-visit-toggle cp-visit-toggle--compact q-ac-hst-toggle"
                 for="q-ac-hst-{{ $idx }}">
            <input type="hidden" name="additional_costs[{{ $idx }}][hst_included]" value="0">
            <input type="checkbox"
                   id="q-ac-hst-{{ $idx }}"
                   name="additional_costs[{{ $idx }}][hst_included]"
                   value="1"
                   data-ac-hst-toggle
                   aria-describedby="q-ac-hst-label-{{ $idx }}"
                   @checked($row['hst_included'])>
            <span class="cp-visit-toggle__control" aria-hidden="true"></span>
            <span class="cp-visit-toggle__label" data-ac-hst-text>
              {{ $row['hst_included'] ? 'Tax included' : 'Before tax' }}
            </span>
          </label>
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
    grid-template-columns: minmax(0, 1.6fr) minmax(120px, 0.55fr) minmax(148px, auto) auto;
    gap: 12px;
    align-items: end;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
  }
  .q-ac-row .admin-form-group { margin-bottom: 0; min-width: 0; }
  .q-ac-row__desc label,
  .q-ac-row__amount label,
  .q-ac-row__hst-label {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .q-ac-row__hst-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .q-ac-hst-toggle {
    min-height: 44px;
  }
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

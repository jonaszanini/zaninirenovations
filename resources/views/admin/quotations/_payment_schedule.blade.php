@php
  use App\Services\QuotationPaymentSchedule;

  $quotation = $quotation ?? null;
  $isEdit = filled($quotation);
  $paymentOptions = QuotationPaymentSchedule::formOptions();

  $selectedPlan = old('payment_installments');
  if ($selectedPlan === null || $selectedPlan === '') {
      $selectedPlan = $isEdit ? ($quotation->payment_installments ?? null) : null;
  }
  $selectedPlan = $selectedPlan !== null && $selectedPlan !== '' ? (int) $selectedPlan : null;
@endphp

<div class="admin-card q-payment-schedule-card" id="q-payment-schedule">
  <h3 class="q-payment-schedule-card__title"><i class="fa fa-calendar-check-o" aria-hidden="true"></i> Payment schedule <span class="cp-required">*</span></h3>
  <p class="admin-form-help q-payment-schedule-card__intro">
    Choose how many installments appear on the client PDF. Required before saving.
  </p>

  <div class="q-payment-schedule-options" role="radiogroup" aria-label="Payment installments">
    @foreach ($paymentOptions as $opt)
      <label class="q-payment-schedule-option{{ $selectedPlan === (int) $opt['value'] ? ' is-selected' : '' }}">
        <input type="radio"
               name="payment_installments"
               value="{{ $opt['value'] }}"
               class="q-payment-schedule-option__input"
               @checked($selectedPlan === (int) $opt['value'])
               required>
        <span class="q-payment-schedule-option__body">
          <span class="q-payment-schedule-option__label">{{ $opt['label'] }}</span>
          <span class="q-payment-schedule-option__summary">{{ $opt['summary'] }}</span>
        </span>
      </label>
    @endforeach
  </div>

  @error('payment_installments')
    <p class="admin-form-error" style="margin-top:10px;">{{ $message }}</p>
  @enderror
</div>

<style>
  .q-payment-schedule-card__title {
    margin: 0 0 6px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
  }
  .q-payment-schedule-card__intro { margin: 0 0 14px; }
  .q-payment-schedule-options {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }
  @media (max-width: 900px) {
    .q-payment-schedule-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 520px) {
    .q-payment-schedule-options { grid-template-columns: 1fr; }
  }
  .q-payment-schedule-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 0;
    padding: 12px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s, background .15s;
  }
  .q-payment-schedule-option:hover {
    border-color: #94a3b8;
  }
  .q-payment-schedule-option.is-selected,
  .q-payment-schedule-option:has(:checked) {
    border-color: var(--admin-primary, #024768);
    background: #f0f9ff;
    box-shadow: 0 0 0 1px var(--admin-primary, #024768);
  }
  .q-payment-schedule-option__input {
    margin-top: 3px;
    flex-shrink: 0;
  }
  .q-payment-schedule-option__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }
  .q-payment-schedule-option__label {
    font-weight: 700;
    font-size: 0.92rem;
    color: #0f172a;
  }
  .q-payment-schedule-option__summary {
    font-size: 0.8rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
  }
</style>

@once
  @push('scripts')
    <script>
      (function () {
        document.querySelectorAll('.q-payment-schedule-options').forEach(function (group) {
          group.addEventListener('change', function (e) {
            if (!e.target || e.target.name !== 'payment_installments') return;
            group.querySelectorAll('.q-payment-schedule-option').forEach(function (opt) {
              opt.classList.toggle('is-selected', !!(opt.querySelector('input') && opt.querySelector('input').checked));
            });
          });
        });
      })();
    </script>
  @endpush
@endonce

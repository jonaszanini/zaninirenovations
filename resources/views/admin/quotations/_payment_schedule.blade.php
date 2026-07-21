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

  $savedPercentages = old('payment_percentages');
  if ($savedPercentages === null && $isEdit && is_array($quotation->payment_percentages ?? null)) {
      $planKey = (int) ($quotation->payment_installments ?? 0);
      if ($planKey > 0) {
          $savedPercentages = [
              $planKey => $quotation->payment_percentages
          ];
      }
  }

  $installmentLabels = [
      2 => ['Deposit', 'Completion'],
      3 => ['Deposit', 'Commencement', 'Completion'],
      4 => ['Deposit', 'Commencement', 'Mid-project', 'Completion'],
  ];
@endphp

<div class="admin-card q-payment-schedule-card" id="q-payment-schedule">
  <h3 class="q-payment-schedule-card__title"><i class="fa fa-calendar-check-o" aria-hidden="true"></i> Payment schedule <span class="cp-required">*</span></h3>
  <p class="admin-form-help q-payment-schedule-card__intro">
    Choose how many installments appear on the client PDF. You can also customize the percentage split for each installment. Required before saving.
  </p>

  <div class="q-payment-schedule-options" role="radiogroup" aria-label="Payment installments">
    @foreach ($paymentOptions as $opt)
      @php
        $n = (int) $opt['value'];
        $optPcts = QuotationPaymentSchedule::percentages($n);
        if (isset($savedPercentages[$n]) && is_array($savedPercentages[$n]) && count($savedPercentages[$n]) === $n) {
            $optPcts = array_map('floatval', $savedPercentages[$n]);
        }
        $summaryText = implode(' / ', array_map(fn ($p) => rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.').'%', $optPcts));
      @endphp
      <label class="q-payment-schedule-option{{ $selectedPlan === $n ? ' is-selected' : '' }}" data-plan-card="{{ $n }}">
        <input type="radio"
               name="payment_installments"
               value="{{ $n }}"
               class="q-payment-schedule-option__input"
               @checked($selectedPlan === $n)
               required>
        <span class="q-payment-schedule-option__body">
          <span class="q-payment-schedule-option__label">{{ $opt['label'] }}</span>
          <span class="q-payment-schedule-option__summary" data-plan-summary="{{ $n }}">{{ $summaryText }}</span>
        </span>
      </label>
    @endforeach
  </div>

  <div class="q-payment-schedule-panels" style="margin-top: 14px;">
    @foreach ([2, 3, 4] as $n)
      @php
        $defaults = QuotationPaymentSchedule::percentages($n);
        $currentPcts = $defaults;
        if (isset($savedPercentages[$n]) && is_array($savedPercentages[$n]) && count($savedPercentages[$n]) === $n) {
            $currentPcts = array_map('floatval', $savedPercentages[$n]);
        }
        $labels = $installmentLabels[$n];
      @endphp
      <div class="q-payment-schedule-panel" data-plan-panel="{{ $n }}" @if($selectedPlan !== $n) hidden @endif>
        <div class="q-payment-schedule-panel__title">Customize {{ $n }} Installments Percentage Split</div>
        <div class="q-payment-schedule-pct-grid q-pct-grid-{{ $n }}">
          @foreach ($labels as $idx => $lbl)
            <div class="admin-form-group q-pct-group">
              <label for="q-pct-{{ $n }}-{{ $idx }}">{{ $lbl }} (%)</label>
              <div class="q-pct-input-wrap">
                <input type="number"
                       id="q-pct-{{ $n }}-{{ $idx }}"
                       name="payment_percentages[{{ $n }}][{{ $idx }}]"
                       class="admin-input q-pct-input"
                       step="0.01"
                       min="0"
                       max="100"
                       value="{{ number_format((float) ($currentPcts[$idx] ?? $defaults[$idx]), 2, '.', '') }}"
                       data-plan="{{ $n }}"
                       data-index="{{ $idx }}">
                <span class="q-pct-suffix">%</span>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>

  @error('payment_installments')
    <p class="admin-form-error" style="margin-top:10px;">{{ $message }}</p>
  @enderror
  @error('payment_percentages')
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

  .q-payment-schedule-panel {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 12px 14px;
  }
  .q-payment-schedule-panel__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .q-payment-schedule-pct-grid {
    display: grid;
    gap: 12px;
  }
  .q-pct-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .q-pct-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .q-pct-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  @media (max-width: 640px) {
    .q-pct-grid-2, .q-pct-grid-3, .q-pct-grid-4 { grid-template-columns: 1fr; }
  }

  .q-pct-group { margin-bottom: 0; }
  .q-pct-group label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
    display: block;
  }
  .q-pct-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }
  .q-pct-input {
    padding-right: 24px;
    font-weight: 600;
  }
  .q-pct-suffix {
    position: absolute;
    right: 10px;
    font-size: 0.85rem;
    color: #64748b;
    pointer-events: none;
  }
</style>

@once
  @push('scripts')
    <script>
      (function () {
        document.querySelectorAll('.q-payment-schedule-card').forEach(function (card) {
          var optionsGroup = card.querySelector('.q-payment-schedule-options');
          if (!optionsGroup) return;

          function updatePanels() {
            var checked = optionsGroup.querySelector('input[name="payment_installments"]:checked');
            var selectedVal = checked ? parseInt(checked.value, 10) : null;

            optionsGroup.querySelectorAll('.q-payment-schedule-option').forEach(function (opt) {
              var inp = opt.querySelector('input');
              opt.classList.toggle('is-selected', !!(inp && inp.checked));
            });

            card.querySelectorAll('[data-plan-panel]').forEach(function (panel) {
              var plan = parseInt(panel.getAttribute('data-plan-panel'), 10);
              panel.hidden = (plan !== selectedVal);
            });
          }

          optionsGroup.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'payment_installments') {
              updatePanels();
            }
          });

          // Auto-updating percentages logic
          card.querySelectorAll('.q-payment-schedule-panel').forEach(function (panel) {
            var plan = parseInt(panel.getAttribute('data-plan-panel'), 10);
            var inputs = Array.from(panel.querySelectorAll('.q-pct-input'));
            var summaryEl = card.querySelector('[data-plan-summary="' + plan + '"]');

            function syncSummary() {
              if (!summaryEl) return;
              var parts = inputs.map(function (inp) {
                var v = parseFloat(inp.value) || 0;
                return (Math.round(v * 100) / 100).toString().replace(/\.?0+$/, '') + '%';
              });
              summaryEl.textContent = parts.join(' / ');
            }

            inputs.forEach(function (inp, idx) {
              inp.addEventListener('input', function () {
                var val = parseFloat(inp.value);
                if (isNaN(val)) return;

                var totalCount = inputs.length;
                var targetIdx = (idx === totalCount - 1) ? 0 : totalCount - 1;

                var otherSum = 0;
                inputs.forEach(function (otherInp, oIdx) {
                  if (oIdx !== targetIdx) {
                    otherSum += parseFloat(otherInp.value) || 0;
                  }
                });

                var remainder = Math.max(0, Math.round((100 - otherSum) * 100) / 100);
                inputs[targetIdx].value = remainder.toFixed(2).replace(/\.?0+$/, function(m) {
                  // keeps format clean if whole or 2 decimals
                  return m.indexOf('.') !== -1 && m.length > 2 ? m : m;
                });

                syncSummary();
              });

              inp.addEventListener('change', function () {
                var v = parseFloat(inp.value) || 0;
                inp.value = v.toFixed(2).replace(/\.00$/, '');
                syncSummary();
              });
            });
          });

          updatePanels();
        });
      })();
    </script>
  @endpush
@endonce

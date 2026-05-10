@extends('admin.layouts.app')

@section('heading', 'Edit Invoice — '.$project->name)

@section('content')
  @php
    /*
     * Build the items array rendered on initial load. Need at least one
     * row visible so the user has something to type into. Saved items
     * are shown first; if none, three empty rows.
     */
    $existing = old('items') ?? array_map(
        fn ($i) => [
            'description' => $i['description'],
            'amount' => $i['amount'],
            'line_kind' => $i['line_kind'] ?? 'labour',
            'expense_id' => $i['expense_id'] ?? null,
        ],
        $invoice->cleanItems()
    );
    if (empty($existing)) {
        $existing = [['description' => '', 'amount' => '', 'line_kind' => 'labour', 'expense_id' => null]];
    }

    $isManual = $invoice->isManual();
    $isHourlyManual = $project->isHourly() && $isManual;
    $isFromTimeEntries = $isManual && $invoice->isFromTimeEntries();
    $manualDescription = old('manual_description', $invoice->installment_description);
    $manualTotal = old('manual_total', $invoice->subtotal > 0 ? number_format((float) $invoice->subtotal, 2, '.', '') : '');
    $dueOn = old('due_on', $invoice->due_on ? $invoice->due_on->toDateString() : null);
    $hstExempt = (bool) old('hst_exempt', $invoice->isHstExempt());

    $discountPercent = old(
        'discount_percent',
        $invoice->discount_percent !== null
            ? number_format((float) $invoice->discount_percent, 4, '.', '')
            : ''
    );
    $discountAmount = old(
        'discount_amount',
        $invoice->discount_amount !== null
            ? number_format((float) $invoice->discount_amount, 2, '.', '')
            : ''
    );

    // $lockedSubtotal + $lockedHst + $lockedTotalWithHst are provided by
    // the controller. For installment-linked invoices, $lockedHst is the
    // proportional share of the quotation's HST (NOT a flat 13% of the
    // subtotal), so HST-exempt quotation lines aren't re-taxed.
  @endphp

  <div class="admin-card cp-contract-editor-card">
    <div class="cp-contract-editor-toolbar">
      <div class="cp-contract-editor-toolbar-left">
        <a href="{{ route('admin.client-projects.show', $project) }}#invoice"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
      </div>
      <div class="cp-contract-editor-toolbar-right">
        <a href="{{ route('admin.client-projects.show', $project) }}#invoice"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          Cancel
        </a>
        <button type="submit"
                form="cp-invoice-form"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-cp-invoice-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                form="cp-invoice-form"
                class="admin-btn admin-btn--primary admin-btn--sm"
                data-cp-invoice-intent="close">
          Save &amp; Close
        </button>
      </div>
    </div>

    <div class="cp-contract-editor-heading">
      <h3 style="margin:0;">
        Invoice editor — <span class="cp-quotation-number">{{ $invoice->invoice_number }}</span>
        @if ($invoice->isSent())
          <span class="cp-badge" style="background:#3b82f620;color:#1e40af;margin-left:8px;">Sent</span>
        @elseif ($invoice->isPaid())
          <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:8px;">Paid</span>
        @else
          <span class="cp-badge" style="background:#f59e0b20;color:#b45309;margin-left:8px;">Draft</span>
        @endif
      </h3>
      <p class="admin-form-help" style="margin:4px 0 0;">
        @if ($isFromTimeEntries)
          Hourly invoice — prefilled from {{ $invoice->timeEntries->count() }} unbilled time entr{{ $invoice->timeEntries->count() === 1 ? 'y' : 'ies' }}.
          You can edit the descriptions and amounts freely before sending. Deleting this draft will release the entries back to "unbilled".
          Material lines are tax-included (no extra HST on materials); HST applies to labour only.
        @elseif ($isManual)
          Manual invoice — no installment link. Enter the pre-HST total and optionally itemise the work below.
        @else
          <strong>{{ $invoice->installmentTitle() }}</strong>.
          Amounts below follow the Financials installment as a reference; you may apply a pre-tax discount before HST.
        @endif
      </p>
    </div>

    <form action="{{ route('admin.client-projects.invoices.update', [$project, $invoice]) }}"
          method="post"
          id="cp-invoice-form">
      @csrf
      @method('PUT')

      <input type="hidden" name="intent" value="draft" id="cp-invoice-intent">

      {{-- Installment / header info ---------------------------------- --}}
      <div class="cp-quotation-form-grid">
        <div class="admin-form-group">
          <label>
            @if ($isFromTimeEntries) Source @else Linked installment @endif
          </label>
          @if ($isFromTimeEntries)
            <div class="cp-invoice-locked-field">
              <span class="cp-badge" style="background:#ecfdf5;color:#15803d;">
                <i class="fa fa-clock-o"></i>
                Hourly — {{ $invoice->timeEntries->count() }} time entr{{ $invoice->timeEntries->count() === 1 ? 'y' : 'ies' }}
              </span>
            </div>
          @elseif ($isManual)
            <div class="cp-invoice-locked-field">
              <span class="cp-badge" style="background:#e5e7eb;color:#4b5563;">Manual — no installment</span>
            </div>
          @else
            <div class="cp-invoice-locked-field">
              <strong>{{ $invoice->installmentTitle() }}</strong>
            </div>
          @endif
        </div>

        <div class="admin-form-group">
          <label for="cp-invoice-due">Due date <span style="color:#ef4444;">*</span></label>
          <input type="date"
                 id="cp-invoice-due"
                 name="due_on"
                 class="admin-input"
                 value="{{ $dueOn }}">
          <p class="admin-form-help" style="margin:4px 0 0;">Required before the invoice can be sent to the client.</p>
        </div>
      </div>

      {{-- The HST toggle now lives inline in the totals breakdown below
           (look for the checkbox next to the HST row). We still need a
           hidden input so the form posts the current value — JS keeps it
           in sync with the inline checkbox. --}}
      <input type="hidden" name="hst_exempt" id="cp-invoice-hst-exempt-hidden" value="{{ $hstExempt ? '1' : '0' }}">

      @if ($isManual)
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-invoice-manual-desc">Invoice description</label>
            <input type="text"
                   id="cp-invoice-manual-desc"
                   name="manual_description"
                   class="admin-input"
                   maxlength="255"
                   value="{{ $manualDescription }}"
                   placeholder="e.g. Extra work — additional drywall">
          </div>
          @unless ($isHourlyManual)
            <div class="admin-form-group">
              <label for="cp-invoice-manual-total">Pre-HST total (CAD)</label>
              <input type="text"
                     id="cp-invoice-manual-total"
                     name="manual_total"
                     class="admin-input cp-money-input"
                     inputmode="decimal"
                     autocomplete="off"
                     placeholder="0.00"
                     value="{{ $manualTotal }}">
              <p class="admin-form-help" style="margin:4px 0 0;">
                If you itemise the work below, the sum of the line items will be used instead.
              </p>
            </div>
          @else
            <div class="admin-form-group">
              <span class="admin-form-help" style="display:block;margin-top:22px;line-height:1.5;color:#374151;">
                Mark each line as Labour (pre-tax) or Materials (<strong>amount includes HST</strong>). Invoice HST applies to labour lines only — materials pass through tax-included.
              </span>
            </div>
          @endunless
        </div>
      @else
        @php
          $lockedHstDisplay = $hstExempt ? 0.0 : (float) $lockedHst;
          $lockedTotalDisplay = $hstExempt ? (float) $lockedSubtotal : (float) $lockedTotalWithHst;
        @endphp
        <div class="cp-invoice-locked-total">
          <div>
            <span class="admin-form-help">Locked total (from Financials — reference):</span>
          </div>
          <div class="cp-invoice-locked-total-value">
            <div class="cp-invoice-locked-total-subtotal">
              Subtotal: <strong>{{ \App\Models\ClientProject::formatMoney($lockedSubtotal) }}</strong>
            </div>
            <div class="cp-invoice-locked-total-subtotal" id="cp-invoice-locked-hst-row" @if ($hstExempt) style="display:none;" @endif>
              HST (from quotation share): <strong id="cp-invoice-locked-hst">{{ \App\Models\ClientProject::formatMoney($lockedHstDisplay) }}</strong>
            </div>
            <div class="cp-invoice-locked-total-grand">
              <span id="cp-invoice-locked-total-label">{{ $hstExempt ? 'Total (no HST):' : 'Total (incl. HST):' }}</span>
              <strong id="cp-invoice-locked-total">{{ \App\Models\ClientProject::formatMoney($lockedTotalDisplay) }}</strong>
            </div>
          </div>
        </div>
      @endif

      {{-- Line items ------------------------------------------------- --}}
      <div class="cp-quotation-items-card" style="margin-top:18px;">
        <div class="cp-quotation-items-header">
          <h4>Line items</h4>
        </div>

        <table class="cp-quotation-items-table" id="cp-invoice-items">
          <thead>
            <tr>
              <th class="col-description">Description</th>
              @if ($isHourlyManual)
                <th class="col-line-kind">Type</th>
              @endif
              <th class="col-amount">Amount ($)</th>
              <th class="col-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($existing as $i => $row)
              @php
                $lk = strtolower((string) ($row['line_kind'] ?? 'labour'));
                $lk = $lk === 'materials' ? 'materials' : 'labour';
                $expenseId = $row['expense_id'] ?? null;
              @endphp
              <tr class="cp-quotation-row">
                <td class="col-description">
                  @if ($isManual)
                    <input type="text"
                           name="items[{{ $i }}][description]"
                           class="admin-input cp-quotation-row-desc"
                           maxlength="2000"
                           value="{{ $row['description'] }}"
                           placeholder="Describe this line item">
                  @else
                    <textarea name="items[{{ $i }}][description]"
                              class="admin-input cp-quotation-row-desc"
                              rows="2"
                              placeholder="Describe this line item">{{ $row['description'] }}</textarea>
                  @endif
                </td>
                @if ($isHourlyManual)
                  <td class="col-line-kind">
                    @if (!empty($expenseId))
                      <span class="cp-badge" style="background:#ecfdf5;color:#047857;white-space:nowrap;">Materials (expense)</span>
                      <input type="hidden" name="items[{{ $i }}][line_kind]" value="materials">
                      <input type="hidden" name="items[{{ $i }}][expense_id]" value="{{ (int) $expenseId }}">
                    @else
                      <select name="items[{{ $i }}][line_kind]" class="admin-input cp-invoice-line-kind" aria-label="Line type">
                        <option value="labour" @selected($lk === 'labour')>Labour</option>
                        <option value="materials" @selected($lk === 'materials')>Materials</option>
                      </select>
                    @endif
                  </td>
                @endif
                <td class="col-amount">
                  <input type="text"
                         inputmode="decimal"
                         autocomplete="off"
                         name="items[{{ $i }}][amount]"
                         value="{{ $row['amount'] !== '' ? number_format((float) $row['amount'], 2, '.', '') : '' }}"
                         class="admin-input cp-money-input cp-quotation-row-amount"
                         placeholder="0.00">
                </td>
                <td class="col-actions">
                  <button type="button"
                          class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-remove-row"
                          aria-label="Remove row">
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>

        <div class="cp-quotation-add-row-slot">
          <button type="button"
                  id="cp-invoice-add-row"
                  class="cp-quotation-add-row-btn">
            <i class="fa fa-plus"></i> Add row
          </button>
        </div>

        @if ($isHourlyManual)
          <div class="cp-quotation-totals" id="cp-invoice-hourly-totals">
            <div class="cp-quotation-totals-row">
              <span>Labour subtotal (pre-HST)</span>
              <span id="cp-invoice-labour-sub">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row">
              <span>Materials (tax-included)</span>
              <span id="cp-invoice-materials-sub">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row cp-invoice-hst-row">
              <span class="cp-invoice-hst-toggle">
                <input type="checkbox"
                       id="cp-invoice-include-hst"
                       class="cp-invoice-include-hst"
                       title="Uncheck to exclude HST (e.g. cash payment)."
                       @checked(!$hstExempt)>
                <label for="cp-invoice-include-hst" id="cp-invoice-hst-label">HST (13% on labour)</label>
              </span>
              <span id="cp-invoice-hst">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row cp-quotation-totals-grand">
              <span id="cp-invoice-grand-label">Total due</span>
              <span id="cp-invoice-grand">CA$0.00</span>
            </div>
          </div>
        @else
          <div class="cp-quotation-totals" id="cp-invoice-standard-totals">
            @unless ($isManual || $isHourlyManual)
              <div class="cp-quotation-totals-row">
                <span>Line items subtotal</span>
                <span id="cp-invoice-line-subtotal">CA$0.00</span>
              </div>
              <div class="cp-quotation-totals-row cp-invoice-discount-field-row">
                <span><label for="cp-invoice-discount-pct">Discount (%)</label></span>
                <span style="text-align:right;">
                  <input type="text"
                         inputmode="decimal"
                         name="discount_percent"
                         id="cp-invoice-discount-pct"
                         class="admin-input"
                         style="max-width:110px;text-align:right;"
                         value="{{ $discountPercent }}"
                         placeholder="0"
                         autocomplete="off">
                </span>
              </div>
              <div class="cp-quotation-totals-row cp-invoice-discount-field-row">
                <span><label for="cp-invoice-discount-amt">Discount (CAD)</label></span>
                <span style="text-align:right;">
                  <input type="text"
                         inputmode="decimal"
                         name="discount_amount"
                         id="cp-invoice-discount-amt"
                         class="admin-input cp-money-input"
                         data-cp-money-entry="direct"
                         data-cp-money-hybrid="1"
                         style="max-width:110px;text-align:right;"
                         value="{{ $discountAmount }}"
                         placeholder="0.00"
                         autocomplete="off">
                </span>
              </div>
              <p class="admin-form-help" style="margin:0 0 8px;text-align:right;font-size:12px;color:#4b5563;">
                <strong>Discount (CAD):</strong> same as line items — <strong>digits only</strong> while typing (e.g. <strong>13000</strong> → <strong>$130.00</strong>). Paste works for values like <strong>130.50</strong>. Totals update as you type.
              </p>
              <div class="cp-quotation-totals-row">
                <span>Net subtotal (pre-tax)</span>
                <span id="cp-invoice-net-subtotal">CA$0.00</span>
              </div>
            @else
              <div class="cp-quotation-totals-row">
                <span>Subtotal</span>
                <span id="cp-invoice-subtotal">CA$0.00</span>
              </div>
            @endunless
            <div class="cp-quotation-totals-row cp-invoice-hst-row">
              <span class="cp-invoice-hst-toggle">
                <input type="checkbox"
                       id="cp-invoice-include-hst"
                       class="cp-invoice-include-hst"
                       title="Uncheck to exclude HST (e.g. cash payment)."
                       @checked(!$hstExempt)>
                <label for="cp-invoice-include-hst" id="cp-invoice-hst-label">{{ $isManual ? 'HST (13%)' : 'HST (from quotation share)' }}</label>
              </span>
              <span id="cp-invoice-hst">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row cp-quotation-totals-grand">
              <span id="cp-invoice-grand-label">Total with HST</span>
              <span id="cp-invoice-grand">CA$0.00</span>
            </div>
            @unless ($isManual)
              <div class="cp-quotation-totals-row cp-invoice-plan-diff" id="cp-invoice-plan-diff" style="display:none;">
                <span>
                  <i class="fa fa-info-circle"></i>
                  Net subtotal differs from the Financials installment ({{ \App\Models\ClientProject::formatMoney($lockedSubtotal) }}) by <span id="cp-invoice-diff">—</span>.
                </span>
              </div>
            @endunless
          </div>
        @endif
      </div>

      <div class="cp-contract-editor-actions">
        <a href="{{ route('admin.client-projects.show', $project) }}#invoice"
           class="admin-btn admin-btn--ghost">
          Cancel
        </a>
        <button type="submit"
                class="admin-btn admin-btn--ghost"
                data-cp-invoice-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                class="admin-btn admin-btn--primary"
                data-cp-invoice-intent="close">
          Save &amp; Close
        </button>
      </div>
    </form>
  </div>

  {{-- Templates for dynamically-added rows. Hourly/manual includes Labour vs Materials column. --}}
  @if ($isHourlyManual)
    <template id="cp-invoice-row-template-hourly">
      <tr class="cp-quotation-row">
        <td class="col-description">
          <input type="text"
                 name="items[__INDEX__][description]"
                 class="admin-input cp-quotation-row-desc"
                 maxlength="2000"
                 placeholder="Describe this line item">
        </td>
        <td class="col-line-kind">
          <select name="items[__INDEX__][line_kind]" class="admin-input cp-invoice-line-kind" aria-label="Line type">
            <option value="labour" selected>Labour</option>
            <option value="materials">Materials</option>
          </select>
        </td>
        <td class="col-amount">
          <input type="text"
                 inputmode="decimal"
                 autocomplete="off"
                 name="items[__INDEX__][amount]"
                 class="admin-input cp-money-input cp-quotation-row-amount"
                 placeholder="0.00">
        </td>
        <td class="col-actions">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-remove-row"
                  aria-label="Remove row">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>
    </template>
  @else
    <template id="cp-invoice-row-template">
      <tr class="cp-quotation-row">
        <td class="col-description">
          @if ($isManual)
            <input type="text"
                   name="items[__INDEX__][description]"
                   class="admin-input cp-quotation-row-desc"
                   maxlength="2000"
                   placeholder="Describe this line item">
          @else
            <textarea name="items[__INDEX__][description]"
                      class="admin-input cp-quotation-row-desc"
                      rows="2"
                      placeholder="Describe this line item"></textarea>
          @endif
        </td>
        <td class="col-amount">
          <input type="text"
                 inputmode="decimal"
                 autocomplete="off"
                 name="items[__INDEX__][amount]"
                 class="admin-input cp-money-input cp-quotation-row-amount"
                 placeholder="0.00">
        </td>
        <td class="col-actions">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-remove-row"
                  aria-label="Remove row">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>
    </template>
  @endif

  <script>
    (function () {
      // Manual invoices use a flat 13% on the full line sum, except hourly manual:
      // labour lines are pre-tax; materials are tax-included; HST = 13% × labour only.
      // Installment-linked invoices scale quotation-share HST when the net subtotal changes.
      var HST_RATE = 0.13;
      var HOURLY_MANUAL = {{ $isHourlyManual ? 'true' : 'false' }};
      var INSTALLMENT_EDITOR = {{ (!$isManual && !$isHourlyManual) ? 'true' : 'false' }};
      var LOCKED_SUBTOTAL = {{ $isManual ? 'null' : json_encode(round((float) $lockedSubtotal, 2)) }};
      var LOCKED_HST = {{ $isManual ? 'null' : json_encode(round((float) $lockedHst, 2)) }};

      var tbody = document.querySelector('#cp-invoice-items tbody');
      var addBtn = document.getElementById('cp-invoice-add-row');
      var template = document.getElementById(HOURLY_MANUAL ? 'cp-invoice-row-template-hourly' : 'cp-invoice-row-template');
      var subtotalEl = document.getElementById('cp-invoice-subtotal');
      var lineSubtotalEl = document.getElementById('cp-invoice-line-subtotal');
      var netSubtotalEl = document.getElementById('cp-invoice-net-subtotal');
      var labourEl = document.getElementById('cp-invoice-labour-sub');
      var materialsEl = document.getElementById('cp-invoice-materials-sub');
      var hstEl = document.getElementById('cp-invoice-hst');
      var hstLabelEl = document.getElementById('cp-invoice-hst-label');
      var hstRowEl = document.querySelector('.cp-invoice-hst-row');
      var grandEl = document.getElementById('cp-invoice-grand');
      var grandLabelEl = document.getElementById('cp-invoice-grand-label');
      var planDiffEl = document.getElementById('cp-invoice-plan-diff');
      var diffEl = document.getElementById('cp-invoice-diff');
      // Inline "include HST" checkbox: checked = HST applies, unchecked = exempt.
      var includeHstCb = document.getElementById('cp-invoice-include-hst');
      var hstExemptHidden = document.getElementById('cp-invoice-hst-exempt-hidden');
      var ORIGINAL_GRAND_LABEL = grandLabelEl ? grandLabelEl.textContent : '';
      var EXEMPT_GRAND_LABEL = HOURLY_MANUAL ? 'Total due (no HST)' : 'Total (no HST)';

      // Locked-total panel (installment-linked invoices only).
      var lockedHstRow = document.getElementById('cp-invoice-locked-hst-row');
      var lockedHstEl = document.getElementById('cp-invoice-locked-hst');
      var lockedTotalEl = document.getElementById('cp-invoice-locked-total');
      var lockedTotalLabelEl = document.getElementById('cp-invoice-locked-total-label');
      var LOCKED_HST_BASE = {{ $isManual ? 'null' : json_encode(round((float) $lockedHst, 2)) }};
      var LOCKED_TOTAL_BASE = {{ $isManual ? 'null' : json_encode(round((float) ($lockedTotalWithHst ?? 0), 2)) }};

      function isExempt() {
        // No checkbox = treat as not exempt (HST applies).
        return !!(includeHstCb && !includeHstCb.checked);
      }
      function syncHstExemptHidden() {
        if (hstExemptHidden) hstExemptHidden.value = isExempt() ? '1' : '0';
      }

      function syncLockedPanel() {
        if (!lockedTotalEl) return;
        var exempt = isExempt();
        if (lockedHstRow) lockedHstRow.style.display = exempt ? 'none' : '';
        if (lockedHstEl && LOCKED_HST_BASE !== null) {
          lockedHstEl.textContent = formatMoney(exempt ? 0 : LOCKED_HST_BASE);
        }
        if (lockedTotalEl && LOCKED_TOTAL_BASE !== null) {
          var sub = LOCKED_SUBTOTAL || 0;
          lockedTotalEl.textContent = formatMoney(exempt ? sub : LOCKED_TOTAL_BASE);
        }
        if (lockedTotalLabelEl) {
          lockedTotalLabelEl.textContent = exempt ? 'Total (no HST):' : 'Total (incl. HST):';
        }
      }

      function formatMoney(n) {
        var v = (typeof n === 'number' && !isNaN(n)) ? n : 0;
        return 'CA$' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      }

      function parseAmount(input) {
        if (!input) return 0;
        if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
          return window.cpMoneyMask.parse(input.value);
        }
        var n = parseFloat(String(input.value).replace(/,/g, ''));
        return isFinite(n) ? n : 0;
      }

      /** Discount (%) only — not calculator-masked. */
      function parsePlainDecimal(input) {
        if (!input) return 0;
        var n = parseFloat(String(input.value || '').replace(/,/g, '').trim());
        return isFinite(n) ? n : 0;
      }

      function rowLineKind(tr) {
        var hidden = tr.querySelector('input[type="hidden"][name*="[line_kind]"]');
        if (hidden) return hidden.value || 'labour';
        var sel = tr.querySelector('select.cp-invoice-line-kind');
        return sel ? sel.value : 'labour';
      }

      function recalc() {
        var exempt = isExempt();
        // Visual cue: grey out the HST row when excluded, swap the grand
        // label so users know HST isn't part of the total.
        if (hstRowEl) hstRowEl.classList.toggle('cp-invoice-hst-row--excluded', exempt);
        if (grandLabelEl) grandLabelEl.textContent = exempt ? EXEMPT_GRAND_LABEL : ORIGINAL_GRAND_LABEL;

        if (HOURLY_MANUAL) {
          var labour = 0;
          var materials = 0;
          tbody.querySelectorAll('tr.cp-quotation-row').forEach(function (tr) {
            var amt = parseAmount(tr.querySelector('.cp-quotation-row-amount'));
            var kind = rowLineKind(tr);
            if (kind === 'materials') materials += amt;
            else labour += amt;
          });
          labour = Math.round(labour * 100) / 100;
          materials = Math.round(materials * 100) / 100;
          var hst = exempt ? 0 : Math.round(labour * HST_RATE * 100) / 100;
          if (labourEl) labourEl.textContent = formatMoney(labour);
          if (materialsEl) materialsEl.textContent = formatMoney(materials);
          if (hstEl) hstEl.textContent = formatMoney(hst);
          if (grandEl) grandEl.textContent = formatMoney(Math.round((labour + hst + materials) * 100) / 100);
          return;
        }

        var inputs = tbody.querySelectorAll('.cp-quotation-row-amount');
        var gross = 0;
        inputs.forEach(function (input) {
          gross += parseAmount(input);
        });
        gross = Math.round(gross * 100) / 100;

        if (INSTALLMENT_EDITOR) {
          var pctInput = document.getElementById('cp-invoice-discount-pct');
          var amtInput = document.getElementById('cp-invoice-discount-amt');
          var pct = pctInput ? parsePlainDecimal(pctInput) : 0;
          var amtRaw = 0;
          if (amtInput) {
            if (window.cpMoneyMask && typeof window.cpMoneyMask.parseHybridCadMoney === 'function') {
              amtRaw = window.cpMoneyMask.parseHybridCadMoney(amtInput.value, 2);
            } else {
              amtRaw = parseAmount(amtInput);
            }
          }
          var discount = 0;
          if (pct > 0.00001) {
            discount = Math.round(Math.min(gross * pct / 100, gross) * 100) / 100;
          } else if (amtRaw > 0.00001) {
            discount = Math.round(Math.min(amtRaw, gross) * 100) / 100;
          }
          var net = Math.round(Math.max(0, gross - discount) * 100) / 100;

          var hstInst;
          if (exempt) {
            hstInst = 0;
          } else if (LOCKED_SUBTOTAL !== null && LOCKED_HST !== null && LOCKED_SUBTOTAL > 0.00001) {
            hstInst = Math.round(LOCKED_HST * (net / LOCKED_SUBTOTAL) * 100) / 100;
          } else if (LOCKED_HST !== null) {
            hstInst = LOCKED_HST;
          } else {
            hstInst = Math.round(net * HST_RATE * 100) / 100;
          }

          if (lineSubtotalEl) lineSubtotalEl.textContent = formatMoney(gross);
          if (netSubtotalEl) netSubtotalEl.textContent = formatMoney(net);
          if (hstEl) hstEl.textContent = formatMoney(hstInst);
          if (grandEl) grandEl.textContent = formatMoney(Math.round((net + hstInst) * 100) / 100);

          if (LOCKED_SUBTOTAL !== null && planDiffEl && diffEl) {
            var diff = Math.round((net - LOCKED_SUBTOTAL) * 100) / 100;
            if (Math.abs(diff) > 0.01) {
              planDiffEl.style.display = '';
              diffEl.textContent = (diff > 0 ? '+' : '') + formatMoney(diff);
            } else {
              planDiffEl.style.display = 'none';
            }
          }
          return;
        }

        var hst;
        if (exempt) {
          hst = 0;
        } else if (LOCKED_HST !== null) {
          hst = LOCKED_HST;
        } else {
          hst = Math.round(gross * HST_RATE * 100) / 100;
        }

        if (subtotalEl) subtotalEl.textContent = formatMoney(gross);
        if (hstEl) hstEl.textContent = formatMoney(hst);
        if (grandEl) grandEl.textContent = formatMoney(Math.round((gross + hst) * 100) / 100);
      }

      function reindex() {
        tbody.querySelectorAll('tr.cp-quotation-row').forEach(function (tr, idx) {
          tr.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/items\[(\d+|__INDEX__)\]/, 'items[' + idx + ']');
          });
        });
      }

      addBtn.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, tbody.querySelectorAll('tr').length);
        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        var newRow = wrapper.firstChild;
        tbody.appendChild(newRow);
        reindex();
        if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
          window.cpMoneyMask.wire(newRow.querySelectorAll('.cp-money-input'));
        }
        var desc = newRow.querySelector('.cp-quotation-row-desc');
        if (desc) desc.focus();
      });

      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.cp-quotation-remove-row');
        if (!btn) return;
        var rows = tbody.querySelectorAll('tr.cp-quotation-row');
        if (rows.length <= 1) {
          var tr = btn.closest('tr');
          var d = tr.querySelector('.cp-quotation-row-desc');
          var a = tr.querySelector('.cp-quotation-row-amount');
          if (d) d.value = '';
          if (a) a.value = '';
          var kindSel = tr.querySelector('select.cp-invoice-line-kind');
          if (kindSel) kindSel.value = 'labour';
        } else {
          btn.closest('tr').remove();
        }
        reindex();
        recalc();
      });

      tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('cp-quotation-row-amount')) recalc();
      });

      tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('cp-invoice-line-kind')) recalc();
      });

      var discountPctEl = document.getElementById('cp-invoice-discount-pct');
      var discountAmtEl = document.getElementById('cp-invoice-discount-amt');
      if (discountAmtEl && window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
        window.cpMoneyMask.wire(discountAmtEl);
      }
      if (discountPctEl) discountPctEl.addEventListener('input', recalc);
      if (discountAmtEl) discountAmtEl.addEventListener('input', recalc);
      if (discountAmtEl) discountAmtEl.addEventListener('blur', recalc);

      if (includeHstCb) includeHstCb.addEventListener('change', function () {
        syncHstExemptHidden();
        recalc();
        syncLockedPanel();
      });

      syncHstExemptHidden();
      syncLockedPanel();

      var intentField = document.getElementById('cp-invoice-intent');
      document.querySelectorAll('[data-cp-invoice-intent]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          intentField.value = btn.getAttribute('data-cp-invoice-intent') || 'draft';
        });
      });

      recalc();

      document.addEventListener('DOMContentLoaded', function () {
        if (INSTALLMENT_EDITOR) recalc();
      });
    })();
  </script>
@endsection

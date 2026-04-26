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
        fn ($i) => ['description' => $i['description'], 'amount' => $i['amount']],
        $invoice->cleanItems()
    );
    if (empty($existing)) {
        $existing = [['description' => '', 'amount' => '']];
    }

    $isManual = $invoice->isManual();
    $isFromTimeEntries = $isManual && $invoice->isFromTimeEntries();
    $manualDescription = old('manual_description', $invoice->installment_description);
    $manualTotal = old('manual_total', $invoice->subtotal > 0 ? number_format((float) $invoice->subtotal, 2, '.', '') : '');
    $dueOn = old('due_on', $invoice->due_on ? $invoice->due_on->toDateString() : null);

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
        @elseif ($isManual)
          Manual invoice — no installment link. Enter the pre-HST total and optionally itemise the work below.
        @else
          <strong>{{ $invoice->installmentTitle() }}</strong>.
          The invoice total is locked to the installment amount from the Financials payment plan.
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
        </div>
      @else
        <div class="cp-invoice-locked-total">
          <div>
            <span class="admin-form-help">Locked total (from Financials — must match):</span>
          </div>
          <div class="cp-invoice-locked-total-value">
            <div class="cp-invoice-locked-total-subtotal">
              Subtotal: <strong>{{ \App\Models\ClientProject::formatMoney($lockedSubtotal) }}</strong>
            </div>
            <div class="cp-invoice-locked-total-subtotal">
              HST (from quotation share): <strong>{{ \App\Models\ClientProject::formatMoney($lockedHst) }}</strong>
            </div>
            <div class="cp-invoice-locked-total-grand">
              Total (incl. HST): <strong>{{ \App\Models\ClientProject::formatMoney($lockedTotalWithHst) }}</strong>
            </div>
          </div>
        </div>
      @endif

      {{-- Line items ------------------------------------------------- --}}
      <div class="cp-quotation-items-card" style="margin-top:18px;">
        <div class="cp-quotation-items-header">
          <h4>Line items</h4>
          <button type="button"
                  id="cp-invoice-add-row"
                  class="admin-btn admin-btn--ghost admin-btn--sm">
            <i class="fa fa-plus"></i> Add row
          </button>
        </div>

        <table class="cp-quotation-items-table" id="cp-invoice-items">
          <thead>
            <tr>
              <th class="col-description">Description</th>
              <th class="col-amount">Amount ($)</th>
              <th class="col-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($existing as $i => $row)
              <tr class="cp-quotation-row">
                <td class="col-description">
                  <textarea name="items[{{ $i }}][description]"
                            class="admin-input cp-quotation-row-desc"
                            rows="2"
                            placeholder="Describe this line item">{{ $row['description'] }}</textarea>
                </td>
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

        <div class="cp-quotation-totals">
          <div class="cp-quotation-totals-row">
            <span>Subtotal</span>
            <span id="cp-invoice-subtotal">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span id="cp-invoice-hst-label">{{ $isManual ? 'HST (13%)' : 'HST (from quotation share)' }}</span>
            <span id="cp-invoice-hst">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row cp-quotation-totals-grand">
            <span>Total with HST</span>
            <span id="cp-invoice-grand">CA$0.00</span>
          </div>
          @unless ($isManual)
            <div class="cp-quotation-totals-row cp-invoice-mismatch" id="cp-invoice-mismatch" style="display:none;">
              <span>
                <i class="fa fa-exclamation-triangle"></i>
                Line-item subtotal must equal
                <strong>{{ \App\Models\ClientProject::formatMoney($lockedSubtotal) }}</strong>
                to match the locked installment. Current difference: <span id="cp-invoice-diff">—</span>
              </span>
            </div>
          @endunless
        </div>
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

  {{-- Template for dynamically-added rows. --}}
  <template id="cp-invoice-row-template">
    <tr class="cp-quotation-row">
      <td class="col-description">
        <textarea name="items[__INDEX__][description]"
                  class="admin-input cp-quotation-row-desc"
                  rows="2"
                  placeholder="Describe this line item"></textarea>
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

  <script>
    (function () {
      // Manual invoices use a flat 13%. Installment-linked invoices
      // freeze the HST to its proportional share of the quotation
      // (LOCKED_HST), since the items must sum to LOCKED_SUBTOTAL anyway.
      var HST_RATE = 0.13;
      var LOCKED_SUBTOTAL = {{ $isManual ? 'null' : json_encode(round((float) $lockedSubtotal, 2)) }};
      var LOCKED_HST = {{ $isManual ? 'null' : json_encode(round((float) $lockedHst, 2)) }};

      var tbody = document.querySelector('#cp-invoice-items tbody');
      var addBtn = document.getElementById('cp-invoice-add-row');
      var template = document.getElementById('cp-invoice-row-template');
      var subtotalEl = document.getElementById('cp-invoice-subtotal');
      var hstEl = document.getElementById('cp-invoice-hst');
      var grandEl = document.getElementById('cp-invoice-grand');
      var mismatchEl = document.getElementById('cp-invoice-mismatch');
      var diffEl = document.getElementById('cp-invoice-diff');

      function formatMoney(n) {
        var v = (typeof n === 'number' && !isNaN(n)) ? n : 0;
        return 'CA$' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      }

      function parseAmount(input) {
        if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
          return window.cpMoneyMask.parse(input.value);
        }
        var n = parseFloat(String(input.value).replace(/,/g, ''));
        return isFinite(n) ? n : 0;
      }

      function recalc() {
        var inputs = tbody.querySelectorAll('.cp-quotation-row-amount');
        var subtotal = 0;
        inputs.forEach(function (input) {
          subtotal += parseAmount(input);
        });
        subtotal = Math.round(subtotal * 100) / 100;

        // Installment-linked invoices: HST is whatever the quotation
        // share resolved to server-side — never recompute as 13% × items.
        // Manual invoices: 13% × items.
        var hst;
        if (LOCKED_HST !== null) {
          hst = LOCKED_HST;
        } else {
          hst = Math.round(subtotal * HST_RATE * 100) / 100;
        }

        subtotalEl.textContent = formatMoney(subtotal);
        hstEl.textContent = formatMoney(hst);
        grandEl.textContent = formatMoney(Math.round((subtotal + hst) * 100) / 100);

        if (LOCKED_SUBTOTAL !== null && mismatchEl) {
          var diff = Math.round((subtotal - LOCKED_SUBTOTAL) * 100) / 100;
          if (Math.abs(diff) > 0.01) {
            mismatchEl.style.display = '';
            diffEl.textContent = (diff > 0 ? '+' : '') + formatMoney(diff);
          } else {
            mismatchEl.style.display = 'none';
          }
        }
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
        newRow.querySelector('.cp-quotation-row-desc').focus();
      });

      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.cp-quotation-remove-row');
        if (!btn) return;
        var rows = tbody.querySelectorAll('tr.cp-quotation-row');
        if (rows.length <= 1) {
          var tr = btn.closest('tr');
          tr.querySelector('.cp-quotation-row-desc').value = '';
          tr.querySelector('.cp-quotation-row-amount').value = '';
        } else {
          btn.closest('tr').remove();
        }
        reindex();
        recalc();
      });

      tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('cp-quotation-row-amount')) recalc();
      });

      var intentField = document.getElementById('cp-invoice-intent');
      document.querySelectorAll('[data-cp-invoice-intent]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          intentField.value = btn.getAttribute('data-cp-invoice-intent') || 'draft';
        });
      });

      recalc();
    })();
  </script>
@endsection

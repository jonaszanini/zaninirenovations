@extends('admin.layouts.app')

@section('heading', 'Edit '.$quotation->typeLabel().' — '.$project->name)

@section('content')
  @php
    /*
     * Build the items array we render on initial load. We need at least one
     * row visible so the user has something to type into. Saved items are
     * shown first; if none exist we show three empty rows by default.
     */
    $existing = old('items') ?? array_map(
        fn ($i) => [
            'description' => $i['description'],
            'amount' => $i['amount'],
            'apply_hst' => array_key_exists('apply_hst', $i) ? (bool) $i['apply_hst'] : true,
        ],
        $quotation->cleanItems()
    );
    if (empty($existing)) {
        $existing = array_fill(0, 3, ['description' => '', 'amount' => '', 'apply_hst' => true]);
    }
    $type = old('type', $quotation->type);
    $labour = old('labour', $quotation->labour) ? true : false;
    $material = old('material', $quotation->material) ? true : false;
    if (request()->isMethod('post')) {
        // After validation failure (POST roundtrip), respect whatever the
        // user actually checked rather than the saved row.
        $labour = request()->boolean('labour');
        $material = request()->boolean('material');
    }
  @endphp

  <div class="admin-card cp-contract-editor-card">
    {{-- Top action bar: Back to project on the LEFT, the form action
         buttons (Cancel / Save Draft / Save & Close) on the RIGHT.
         The action buttons live outside the <form> below and are wired
         to it via the HTML `form="cp-quotation-form"` attribute. --}}
    <div class="cp-contract-editor-toolbar">
      <div class="cp-contract-editor-toolbar-left">
        <a href="{{ route('admin.client-projects.show', $project) }}#quotation"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
      </div>
      <div class="cp-contract-editor-toolbar-right">
        <a href="{{ route('admin.client-projects.show', $project) }}#quotation"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          Cancel
        </a>
        <button type="submit"
                form="cp-quotation-form"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-cp-quotation-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                form="cp-quotation-form"
                class="admin-btn admin-btn--primary admin-btn--sm"
                data-cp-quotation-intent="close">
          Save &amp; Close
        </button>
      </div>
    </div>

    <div class="cp-contract-editor-heading">
      <h3 style="margin:0;">{{ $quotation->typeLabel() }} editor — <span class="cp-quotation-number">{{ $quotation->quotation_number }}</span></h3>
      <p class="admin-form-help" style="margin:4px 0 0;">
        Add line items with their pre-HST amounts. Check <strong>13% HST</strong> per row
        (on by default); uncheck for tax-exempt lines. Totals update automatically. Changing between
        <em>Quotation</em> and <em>Estimate</em> updates the document
        prefix until the first send.
      </p>
    </div>

    <form action="{{ route('admin.client-projects.quotation.update', [$project, $quotation]) }}"
          method="post"
          id="cp-quotation-form">
      @csrf
      @method('PUT')

      <input type="hidden" name="intent" value="draft" id="cp-quotation-intent">

      <div class="cp-quotation-form-grid">
        <div class="admin-form-group">
          <label for="cp-quotation-type">Document type</label>
          <select id="cp-quotation-type" name="type" class="admin-input">
            <option value="quotation" @selected($type === 'quotation')>Quotation (firm price)</option>
            <option value="estimate"  @selected($type === 'estimate')>Estimate (variable price)</option>
          </select>
        </div>

        <div class="admin-form-group">
          <span class="admin-form-label">Includes</span>
          <label class="cp-checkbox-row">
            <input type="checkbox" name="material" value="1" @checked($material) id="cp-quotation-material">
            <span>Material</span>
          </label>
          <label class="cp-checkbox-row">
            <input type="checkbox" name="labour" value="1" @checked($labour) id="cp-quotation-labour">
            <span>Labour</span>
          </label>
          <p id="cp-quotation-includes-warning" class="cp-quotation-warning-inline" style="display:none;">
            <i class="fa fa-exclamation-triangle"></i>
            Select at least one of <em>Material</em> or <em>Labour</em> so the intro phrase reads naturally.
          </p>
        </div>
      </div>

      {{-- Description of the work — free-form notes about the service.
           Appears above the line items in the PDF / preview the client
           sees. When a Standard Quotation is linked to this project,
           its description is copied into this field. --}}
      <div class="admin-form-group cp-quotation-work-description">
        <label for="cp-quotation-work-description">Description of the work</label>
        <textarea id="cp-quotation-work-description"
                  name="work_description"
                  rows="5"
                  maxlength="5000"
                  class="admin-input @error('work_description') is-invalid @enderror"
                  placeholder="Briefly describe the service to be performed (visible to the client on the quotation PDF).">{{ old('work_description', $quotation->work_description) }}</textarea>
        <p class="admin-form-help">Shown above the line items on the client's quotation PDF. Plain text — line breaks are preserved.</p>
        @error('work_description') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="cp-quotation-items-card">
        <div class="cp-quotation-items-header">
          <h4>Line items</h4>
        </div>

        <table class="cp-quotation-items-table" id="cp-quotation-items">
          <thead>
            <tr>
              <th class="col-description">Description</th>
              <th class="col-hst">13% HST</th>
              <th class="col-amount">Amount ($)</th>
              <th class="col-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($existing as $i => $row)
              @php $applyHst = $row['apply_hst'] ?? true; @endphp
              <tr class="cp-quotation-row">
                <td class="col-description">
                  <textarea name="items[{{ $i }}][description]"
                            class="admin-input cp-quotation-row-desc"
                            rows="2"
                            placeholder="Describe this line item">{{ $row['description'] }}</textarea>
                </td>
                <td class="col-hst">
                  <input type="hidden" name="items[{{ $i }}][apply_hst]" value="0">
                  <label>
                    <input type="checkbox" name="items[{{ $i }}][apply_hst]" value="1" class="cp-quotation-row-hst" @checked($applyHst)>
                    Apply
                  </label>
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

        <div class="cp-quotation-add-row-slot">
          <button type="button"
                  id="cp-quotation-add-row"
                  class="cp-quotation-add-row-btn">
            <i class="fa fa-plus"></i> Add row
          </button>
        </div>

        <div class="cp-quotation-totals">
          <div class="cp-quotation-totals-row">
            <span>Subtotal</span>
            <span id="cp-quotation-subtotal">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span>HST (13% on checked lines)</span>
            <span id="cp-quotation-hst">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row cp-quotation-totals-grand">
            <span>Total with HST</span>
            <span id="cp-quotation-grand">CA$0.00</span>
          </div>
        </div>
      </div>

      <div class="cp-contract-editor-actions">
        <a href="{{ route('admin.client-projects.show', $project) }}#quotation"
           class="admin-btn admin-btn--ghost">
          Cancel
        </a>
        <button type="submit"
                class="admin-btn admin-btn--ghost"
                data-cp-quotation-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                class="admin-btn admin-btn--primary"
                data-cp-quotation-intent="close">
          Save &amp; Close
        </button>
      </div>
    </form>
  </div>

  {{-- Hidden HTML template used by the JS to clone a fresh empty row. --}}
  <template id="cp-quotation-row-template">
    <tr class="cp-quotation-row">
      <td class="col-description">
        <textarea name="items[__INDEX__][description]"
                  class="admin-input cp-quotation-row-desc"
                  rows="2"
                  placeholder="Describe this line item"></textarea>
      </td>
      <td class="col-hst">
        <input type="hidden" name="items[__INDEX__][apply_hst]" value="0">
        <label>
          <input type="checkbox" name="items[__INDEX__][apply_hst]" value="1" class="cp-quotation-row-hst" checked>
          Apply
        </label>
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
      var HST_RATE = 0.13;

      var tbody = document.querySelector('#cp-quotation-items tbody');
      var addBtn = document.getElementById('cp-quotation-add-row');
      var template = document.getElementById('cp-quotation-row-template');
      var subtotalEl = document.getElementById('cp-quotation-subtotal');
      var hstEl = document.getElementById('cp-quotation-hst');
      var grandEl = document.getElementById('cp-quotation-grand');
      var labourCb = document.getElementById('cp-quotation-labour');
      var materialCb = document.getElementById('cp-quotation-material');
      var includesWarning = document.getElementById('cp-quotation-includes-warning');

      // -- Money formatting helper -----------------------------------------
      function formatMoney(n) {
        var v = (typeof n === 'number' && !isNaN(n)) ? n : 0;
        return 'CA$' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      }

      // -- Live totals -----------------------------------------------------
      // The amount inputs go through the global .cp-money-input mask
      // (calculator-style entry, comma-formatted display). We can't just
      // parseFloat the visible value because "1,234.56" parses to 1.
      // window.cpMoneyMask.parse() strips the commas first.
      function parseAmount(input) {
        if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
          return window.cpMoneyMask.parse(input.value);
        }
        var n = parseFloat(String(input.value).replace(/,/g, ''));
        return isFinite(n) ? n : 0;
      }

      function recalc() {
        var rows = tbody.querySelectorAll('tr.cp-quotation-row');
        var subtotal = 0;
        var hst = 0;
        rows.forEach(function (tr) {
          var amtInput = tr.querySelector('.cp-quotation-row-amount');
          var hstCb = tr.querySelector('.cp-quotation-row-hst');
          var v = parseAmount(amtInput);
          subtotal += v;
          if (hstCb && hstCb.checked) {
            hst += v * HST_RATE;
          }
        });
        hst = Math.round(hst * 100) / 100;
        subtotalEl.textContent = formatMoney(subtotal);
        hstEl.textContent = formatMoney(hst);
        grandEl.textContent = formatMoney(subtotal + hst);
      }

      // Re-index all items[N][...] field names whenever rows are added or
      // removed so the server-side payload stays a contiguous array.
      function reindex() {
        tbody.querySelectorAll('tr.cp-quotation-row').forEach(function (tr, idx) {
          tr.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/items\[(\d+|__INDEX__)\]/, 'items[' + idx + ']');
          });
        });
      }

      // -- Add row ---------------------------------------------------------
      addBtn.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, tbody.querySelectorAll('tr').length);
        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        var newRow = wrapper.firstChild;
        tbody.appendChild(newRow);
        reindex();
        // Wire the calculator-style money mask onto the brand-new amount input.
        if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
          window.cpMoneyMask.wire(newRow.querySelectorAll('.cp-money-input'));
        }
        newRow.querySelector('.cp-quotation-row-desc').focus();
      });

      // -- Remove row (event delegation) -----------------------------------
      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.cp-quotation-remove-row');
        if (!btn) return;
        var rows = tbody.querySelectorAll('tr.cp-quotation-row');
        if (rows.length <= 1) {
          // Always keep at least one row in the table for usability — clear
          // its contents instead of removing the row entirely.
          var tr = btn.closest('tr');
          tr.querySelector('.cp-quotation-row-desc').value = '';
          tr.querySelector('.cp-quotation-row-amount').value = '';
          var hstCb = tr.querySelector('.cp-quotation-row-hst');
          if (hstCb) hstCb.checked = true;
        } else {
          btn.closest('tr').remove();
        }
        reindex();
        recalc();
      });

      // -- Recalc on any amount change -------------------------------------
      tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('cp-quotation-row-amount')) recalc();
      });
      tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('cp-quotation-row-hst')) recalc();
      });

      // -- Includes warning ------------------------------------------------
      function refreshIncludesWarning() {
        var bad = !labourCb.checked && !materialCb.checked;
        includesWarning.style.display = bad ? '' : 'none';
      }
      labourCb.addEventListener('change', refreshIncludesWarning);
      materialCb.addEventListener('change', refreshIncludesWarning);
      refreshIncludesWarning();

      // -- Intent stamping (top + bottom Save buttons) ---------------------
      var intentField = document.getElementById('cp-quotation-intent');
      document.querySelectorAll('[data-cp-quotation-intent]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          intentField.value = btn.getAttribute('data-cp-quotation-intent') || 'draft';
        });
      });

      recalc();
    })();
  </script>
@endsection

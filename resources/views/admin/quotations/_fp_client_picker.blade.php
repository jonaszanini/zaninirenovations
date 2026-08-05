{{--
  Shared client-from-DB picker for the "Connect to a project → New project" form.

  Parameters (all required):
    $clientsSearchUrl  — URL of admin.clients.search (JSON endpoint)
    $pickerId          — unique ID for the TomSelect <select>  (e.g. 'fq-fp-client-picker')
    $nameInputId       — id= of the fp_client_name <input>
    $emailInputId      — id= of the fp_client_email <input>
    $phoneInputId      — id= of the fp_client_phone <input>
    $addressInputId    — id= of the fp_client_address <input>

  Optional:
    $hideDivider       — when true, omit the "or fill manually" divider (render it after a sibling grid row)
--}}

@push('head')
  @once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <style>
      .fp-cp-wrap { margin-bottom: 14px; }
      .fp-cp-wrap--inline { margin-bottom: 0; min-width: 0; }
      .fp-cp-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 5px;
        letter-spacing: .01em;
      }
      .fp-cp-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 10px 0;
        font-size: 11px;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .05em;
      }
      .fp-cp-divider::before,
      .fp-cp-divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
    </style>
  @endonce
@endpush

<div class="fp-cp-wrap{{ ! empty($hideDivider) ? ' fp-cp-wrap--inline' : '' }}">
  <label class="fp-cp-label" for="{{ $pickerId }}">
    Autofill from client directory
  </label>
  <div class="fq-catalog-picker">
    <select id="{{ $pickerId }}"
            name="fp_client_id"
            class="fq-catalog-picker__select"
            aria-label="Search clients from directory"
            data-fp-client-picker
            data-search-url="{{ $clientsSearchUrl }}"
            data-name-id="{{ $nameInputId }}"
            data-email-id="{{ $emailInputId }}"
            data-phone-id="{{ $phoneInputId }}"
            data-address-id="{{ $addressInputId }}">
      <option value=""></option>
    </select>
  </div>
</div>
@if (empty($hideDivider))
<div class="fp-cp-divider">or fill manually</div>
@endif

@push('scripts')
  @once
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
      (function () {
        function formatPhone(raw) {
          if (!raw) return '';
          var d = String(raw).replace(/\D/g, '');
          if (d.length === 10) {
            return '(' + d.slice(0,3) + ') ' + d.slice(3,6) + '-' + d.slice(6);
          }
          return raw;
        }

        function initFpClientPicker(sel) {
          if (!sel || typeof TomSelect === 'undefined') return;
          var searchUrl = sel.dataset.searchUrl || '';
          var nameEl    = sel.dataset.nameId    ? document.getElementById(sel.dataset.nameId)    : null;
          var emailEl   = sel.dataset.emailId   ? document.getElementById(sel.dataset.emailId)   : null;
          var phoneEl   = sel.dataset.phoneId   ? document.getElementById(sel.dataset.phoneId)   : null;
          var addrEl    = sel.dataset.addressId ? document.getElementById(sel.dataset.addressId) : null;

          function applyClient(o) {
            if (!o) return;
            if (nameEl)  nameEl.value  = o.name  || '';
            if (emailEl) emailEl.value = o.email || '';
            if (addrEl)  addrEl.value  = o.address || '';
            if (phoneEl) {
              phoneEl.value = o.phone_display || formatPhone(o.phone) || '';
              phoneEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
          }

          function fetchAndApply(val) {
            var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(val);
            fetch(url, {
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
              }
            })
              .then(function (r) { return r.json(); })
              .then(function (rows) { if (rows && rows[0]) applyClient(rows[0]); })
              .catch(function () {});
          }

          new TomSelect(sel, {
            plugins: ['clear_button'],
            valueField: 'id',
            labelField: 'label',
            searchField: ['label', 'name', 'email', 'phone', 'address'],
            maxOptions: 40,
            placeholder: 'Type name or email…',
            allowEmptyOption: true,
            closeAfterSelect: true,
            loadThrottle: 250,
            render: {
              option: function (data, escape) {
                return '<div style="padding:5px 8px;">' +
                  '<span style="font-weight:600;display:block;">' + escape(data.name || data.label || '') + '</span>' +
                  (data.email ? '<span style="font-size:12px;color:#6b7280;">' + escape(data.email) + '</span>' : '') +
                  (data.phone_display ? '<span style="font-size:12px;color:#6b7280;margin-left:8px;">' + escape(data.phone_display) + '</span>' : '') +
                  '</div>';
              },
              item: function (data, escape) {
                if (!data.id) return '';
                return '<div class="fq-catalog-picker__item">' + escape(data.label || data.name || '') + '</div>';
              }
            },
            load: function (query, callback) {
              var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query || '');
              fetch(url, {
                headers: {
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
              })
                .then(function (r) { return r.json(); })
                .then(function (json) { callback(json); })
                .catch(function () { callback(); });
            },
            onChange: function (val) {
              if (!val) return;
              var option = this.options[val];
              if (option && option.name !== undefined) { applyClient(option); return; }
              fetchAndApply(val);
            }
          });
        }

        // Initialise all pickers on page load
        document.querySelectorAll('[data-fp-client-picker]').forEach(function (sel) {
          initFpClientPicker(sel);
        });
      })();
    </script>
  @endonce
@endpush

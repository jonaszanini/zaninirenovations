@php
  $initialClientForPicker = $initialClientForPicker ?? null;
  $initialClientId = (int) old('client_id', ($initialClientForPicker['id'] ?? 0));
@endphp

@once
  @push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
  @endpush
@endonce

<div class="admin-form-group cp-client-directory-picker">
  <label for="cp-client-directory" class="cp-label-with-hint">
    Client from directory
    @include('admin.client_projects._cp_field_hint', [
      'text' => 'Type a name, email, or phone. Choosing a client fills the fields below; you can edit them before saving. If no client is selected, a new directory entry is created automatically.',
    ])
  </label>

  {{-- Source of truth for submit. The Tom Select <select> is unnamed so its
       blank option cannot overwrite a chosen client_id in the POST body. --}}
  <input type="hidden"
         name="client_id"
         id="cp-client-id"
         value="{{ $initialClientId > 0 ? $initialClientId : '' }}">

  <div class="cp-client-assign">
    <div class="fq-catalog-picker cp-client-directory-picker__field">
      <select id="cp-client-directory"
              class="fq-catalog-picker__select"
              data-search-url="{{ $clientsSearchUrl }}"
              data-initial="{{ $initialClientId }}"
              aria-label="Client from directory">
        {{-- Blank option: Tom Select placeholder handles empty state (option text would stick while typing). --}}
        <option value=""></option>
      </select>
    </div>
    <a href="{{ route('admin.clients.index') }}"
       class="admin-btn admin-btn--ghost admin-btn--sm cp-client-directory-picker__manage"
       title="Manage clients"
       aria-label="Manage clients">
      <i class="fa fa-users" aria-hidden="true"></i>
    </a>
  </div>
</div>

@once
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
      (function () {
        var searchUrl = @json($clientsSearchUrl);
        var initial = @json($initialClientForPicker ?? null);

        var sel = document.getElementById('cp-client-directory');
        var hidden = document.getElementById('cp-client-id');
        if (!sel || typeof TomSelect === 'undefined') return;

        var ts = null;
        var hintEl = document.getElementById('cp-client-address-directory-hint');
        var addressInputId = 'cp-client-address';

        function field(id) {
          return document.getElementById(id);
        }

        function updateDirectoryHint(o) {
          if (!hintEl) return;
          var addr = o && o.address ? String(o.address).trim() : '';
          if (!addr) {
            hintEl.hidden = true;
            hintEl.textContent = '';
            return;
          }
          hintEl.textContent = 'Address on file (saved with this project): ' + addr;
          hintEl.hidden = false;
        }

        function syncPlacesAddress() {
          if (window.ZR_GOOGLE_MAPS && typeof window.ZR_GOOGLE_MAPS.syncAddressFromHidden === 'function') {
            window.ZR_GOOGLE_MAPS.syncAddressFromHidden(addressInputId);
          }
        }

        function setPostedClientId(id) {
          var value = id ? String(id) : '';
          if (hidden) hidden.value = value;
        }

        function nameFromOption(o) {
          if (!o) return '';
          if (o.name) return String(o.name).trim();
          if (o.label) return String(o.label).split(' — ')[0].trim();
          if (o.display) return String(o.display).split(' — ')[0].trim();
          return '';
        }

        function applyClient(o) {
          if (!o) return;
          var nameInput = field('cp-client-name');
          var emailInput = field('cp-client-email');
          var email2Input = field('cp-client-email-secondary');
          var phoneInput = field('cp-client-phone');
          var addrInput = field('cp-client-address');
          var placeIdInput = field('cp-client-address-place-id');
          var latInput = field('cp-client-address-lat');
          var lngInput = field('cp-client-address-lng');

          var name = nameFromOption(o);
          if (nameInput && name) nameInput.value = name;
          if (emailInput) emailInput.value = o.email || '';
          if (email2Input) email2Input.value = o.email_secondary || '';
          if (addrInput) addrInput.value = o.address || '';
          if (placeIdInput) placeIdInput.value = o.address_place_id || '';
          if (latInput) latInput.value = o.address_latitude != null && o.address_latitude !== '' ? String(o.address_latitude) : '';
          if (lngInput) lngInput.value = o.address_longitude != null && o.address_longitude !== '' ? String(o.address_longitude) : '';
          if (phoneInput) {
            phoneInput.value = o.phone_display || o.phone || '';
            phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
          }
          updateDirectoryHint(o);
          syncPlacesAddress();
        }

        function csrfToken() {
          var meta = document.querySelector('meta[name="csrf-token"]');
          return meta ? (meta.getAttribute('content') || '') : '';
        }

        function fetchClientById(id, callback) {
          var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(id);
          fetch(url, {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken()
            }
          })
            .then(function (r) {
              if (!r.ok) throw new Error('client search failed');
              return r.json();
            })
            .then(function (rows) {
              var row = rows && rows[0] ? rows[0] : null;
              if (row && row.id != null) row.id = String(row.id);
              callback(row);
            })
            .catch(function () { callback(null); });
        }

        function optionHasClientData(option) {
          return !!(option && (option.name || option.email || option.phone || option.phone_display || option.label));
        }

        function resolveOption(instance, id) {
          if (!instance || id === null || id === undefined || id === '') return null;
          var key = String(id);
          return instance.options[key] || instance.options[id] || null;
        }

        function matchOptionByQuery(instance, query) {
          if (!instance || !query) return null;
          var q = String(query).trim().toLowerCase();
          if (!q) return null;
          var keys = Object.keys(instance.options || {});
          var found = null;
          for (var i = 0; i < keys.length; i++) {
            var o = instance.options[keys[i]];
            if (!o || !o.id) continue;
            var label = String(o.label || o.display || '').trim().toLowerCase();
            var name = String(o.name || '').trim().toLowerCase();
            var email = String(o.email || '').trim().toLowerCase();
            if (label === q || name === q || (name && email && (name + ' — ' + email) === q)) {
              return o;
            }
            if (!found && (label.indexOf(q) === 0 || name.indexOf(q) === 0)) {
              found = o;
            }
          }
          return found;
        }

        function currentPickerValue() {
          if (ts && typeof ts.getValue === 'function') {
            var v = ts.getValue();
            if (v) return String(v);
          }
          if (hidden && hidden.value) return String(hidden.value);
          return '';
        }

        function syncPickerToForm() {
          var id = currentPickerValue();
          if (!id && ts && ts.control_input) {
            var typed = String(ts.control_input.value || '').trim();
            var matched = matchOptionByQuery(ts, typed);
            if (matched && matched.id) {
              id = String(matched.id);
              ts.setValue(id, true);
            }
          }
          setPostedClientId(id);
          if (!id) return;

          var nameInput = field('cp-client-name');
          if (nameInput && String(nameInput.value || '').trim() !== '') return;

          var opt = resolveOption(ts, id);
          if (opt) applyClient(opt);
        }

        function onClientSelected(id, option, instance) {
          id = id ? String(id) : '';
          setPostedClientId(id);
          if (!id) {
            updateDirectoryHint(null);
            return;
          }

          var cached = optionHasClientData(option) ? option : resolveOption(instance, id);
          if (cached) {
            applyClient(cached);
          }

          fetchClientById(id, function (row) {
            if (row) {
              applyClient(row);
            }
          });
        }

        document.addEventListener('zr-places-ready', function (e) {
          if (e.detail && e.detail.inputId === addressInputId) {
            syncPlacesAddress();
          }
        });

        document.addEventListener('zr-places-address-changed', function (e) {
          if (e.detail && e.detail.inputId === addressInputId) {
            updateDirectoryHint(null);
          }
        });

        var opts = {
          plugins: ['clear_button'],
          valueField: 'id',
          labelField: 'label',
          searchField: ['label', 'display', 'name', 'email', 'phone', 'address'],
          maxOptions: 50,
          placeholder: 'Type to search name or email…',
          allowEmptyOption: true,
          closeAfterSelect: true,
          selectOnTab: true,
          loadThrottle: 250,
          render: {
            option: function (data, escape) {
              return '<div class="fq-catalog-picker__option">' +
                '<span class="fq-catalog-picker__option-name">' + escape(data.name || data.label || '') + '</span>' +
                (data.email ? '<span class="fq-catalog-picker__option-meta">' + escape(data.email) + '</span>' : '') +
                (data.phone_display ? '<span class="fq-catalog-picker__option-meta">' + escape(data.phone_display) + '</span>' : '') +
                '</div>';
            },
            item: function (data, escape) {
              if (!data.id) return '';
              return '<div class="fq-catalog-picker__item">' + escape(data.label || data.display || data.name || '') + '</div>';
            }
          },
          load: function (query, callback) {
            var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query || '');
            fetch(url, {
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
              }
            })
              .then(function (r) { return r.json(); })
              .then(function (json) {
                var rows = Array.isArray(json) ? json : [];
                rows.forEach(function (row) {
                  if (row && row.id != null) row.id = String(row.id);
                });
                callback(rows);
              })
              .catch(function () { callback(); });
          },
          onChange: function (val) {
            onClientSelected(val ? String(val) : '', val ? resolveOption(this, val) : null, this);
          },
          onItemAdd: function (val) {
            onClientSelected(val ? String(val) : '', resolveOption(this, val), this);
          },
          onBlur: function () {
            syncPickerToForm();
          }
        };

        if (initial && initial.id) {
          initial.id = String(initial.id);
          opts.options = [initial];
          opts.items = [initial.id];
        }

        ts = new TomSelect(sel, opts);

        function bindForm(form) {
          if (!form || form.dataset.cpClientPickerBound === '1') return;
          form.dataset.cpClientPickerBound = '1';
          form.addEventListener('submit', function () {
            syncPickerToForm();
          }, true);
          form.addEventListener('formdata', function (e) {
            syncPickerToForm();
            var id = hidden ? String(hidden.value || '') : '';
            if (id) e.formData.set('client_id', id);
            var nameInput = field('cp-client-name');
            if (nameInput && String(nameInput.value || '').trim() !== '') {
              e.formData.set('client_name', nameInput.value);
            }
          });
        }

        bindForm(document.getElementById('cp-create-project-form'));
        bindForm(document.getElementById('cp-edit-project-form'));

        document.addEventListener('click', function (e) {
          var btn = e.target && e.target.closest
            ? e.target.closest(
                'button[type="submit"][form="cp-create-project-form"],' +
                'button[type="submit"][form="cp-edit-project-form"],' +
                '#cp-create-project-form button[type="submit"],' +
                '#cp-edit-project-form button[type="submit"]'
              )
            : null;
          if (btn) syncPickerToForm();
        }, true);

        if (initial && initial.id) {
          onClientSelected(String(initial.id), initial, ts);
        } else {
          setPostedClientId(currentPickerValue());
        }
      })();
    </script>
  @endpush
@endonce

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
@endpush

<div class="admin-form-group cp-client-directory-picker">
  <label for="cp-client-directory" class="cp-label-with-hint">
    Client from directory
    @include('admin.client_projects._cp_field_hint', [
      'text' => 'Type a name, email, or phone. Choosing a client fills the fields below; you can edit them before saving. If no client is selected, a new directory entry is created automatically.',
    ])
  </label>

  <div class="cp-client-assign">
    <div class="fq-catalog-picker cp-client-directory-picker__field">
      <select id="cp-client-directory"
              name="client_id"
              class="fq-catalog-picker__select"
              data-search-url="{{ $clientsSearchUrl }}"
              data-initial="{{ (int) old('client_id', ($initialClientForPicker ?? [])['id'] ?? 0) }}"
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

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <script>
    (function () {
      var searchUrl = @json($clientsSearchUrl);
      var initial = @json($initialClientForPicker ?? null);

      var sel = document.getElementById('cp-client-directory');
      if (!sel || typeof TomSelect === 'undefined') return;

      var ts = null;
      var hintEl = document.getElementById('cp-client-address-directory-hint');
      var addressInputId = 'cp-client-address';

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

      function field(id) {
        return document.getElementById(id);
      }

      function applyClient(o) {
        if (!o) return;
        // Re-query fields on each apply — do not rely on nodes cached at init.
        var nameInput = field('cp-client-name');
        var emailInput = field('cp-client-email');
        var email2Input = field('cp-client-email-secondary');
        var phoneInput = field('cp-client-phone');
        var addrInput = field('cp-client-address');
        var placeIdInput = field('cp-client-address-place-id');
        var latInput = field('cp-client-address-lat');
        var lngInput = field('cp-client-address-lng');

        var name = o.name || '';
        if (!name && o.label) {
          name = String(o.label).split(' — ')[0].trim();
        }

        if (nameInput) nameInput.value = name;
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
        syncClientNameRequired();
      }

      function syncClientNameRequired() {
        var nameInput = field('cp-client-name');
        if (!nameInput) return;
        // Directory client selected → server hydrates blank contact fields.
        // Do not block submit with HTML5 "required" on Client name.
        var directoryId = '';
        if (typeof ts !== 'undefined' && ts && typeof ts.getValue === 'function') {
          directoryId = ts.getValue();
        } else if (sel) {
          directoryId = sel.value;
        }
        nameInput.required = !directoryId;
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

      function normalizeCachedOption(option) {
        if (!option) return null;
        if (option.name) return option;
        if (option.label) {
          return Object.assign({}, option, {
            name: String(option.label).split(' — ')[0].trim()
          });
        }
        return option;
      }

      function onClientSelected(id, option, instance) {
        if (!id) {
          updateDirectoryHint(null);
          syncClientNameRequired();
          return;
        }

        var cached = normalizeCachedOption(
          optionHasClientData(option) ? option : resolveOption(instance, id)
        );
        if (cached) {
          applyClient(cached);
        } else {
          // Still waive HTML5 required — server will hydrate from client_id.
          syncClientNameRequired();
        }

        // Refresh from the API so email/phone/address are complete even when
        // Tom Select only retained label/value on the selected option.
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
              // String ids avoid Tom Select option-lookup mismatches (number vs string keys).
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
        }
      };

      if (initial && initial.id) {
        opts.options = [initial];
        opts.items = [String(initial.id)];
      }

      ts = new TomSelect(sel, opts);

      function ensureClientFieldsFilled() {
        var nameInput = field('cp-client-name');
        var clientId = ts.getValue();
        syncClientNameRequired();
        if (!clientId || !nameInput || String(nameInput.value || '').trim() !== '') {
          return;
        }
        var opt = normalizeCachedOption(resolveOption(ts, clientId));
        if (!opt) return;
        applyClient(opt);
      }

      // Fill empty contact fields / waive required before HTML5 blocks submit
      // (primary action buttons often live outside the <form> via form="…").
      document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest
          ? e.target.closest(
              'button[type="submit"][form="cp-create-project-form"],' +
              'button[type="submit"][form="cp-edit-project-form"],' +
              '#cp-create-project-form button[type="submit"],' +
              '#cp-edit-project-form button[type="submit"]'
            )
          : null;
        if (btn) {
          ensureClientFieldsFilled();
        }
      }, true);

      // Tom Select syncs the underlying <select>; listen as a reliable backup.
      sel.addEventListener('change', function () {
        onClientSelected(sel.value, resolveOption(ts, sel.value), ts);
      });

      syncClientNameRequired();

      if (initial && initial.id) {
        onClientSelected(String(initial.id), initial, ts);
      }
    })();
  </script>
@endpush

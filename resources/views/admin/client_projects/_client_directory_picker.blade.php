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
  <div class="cp-client-directory-picker__row">
    <div class="cp-client-directory-picker__field">
      <select id="cp-client-directory" name="client_id">
        <option value="">— Search to select —</option>
      </select>
    </div>
    <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm cp-client-directory-picker__manage">
      <i class="fa fa-users" aria-hidden="true"></i> Manage clients
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

      var nameEl = document.getElementById('cp-client-name');
      var emailEl = document.getElementById('cp-client-email');
      var email2El = document.getElementById('cp-client-email-secondary');
      var phoneEl = document.getElementById('cp-client-phone');
      var addrEl = document.getElementById('cp-client-address');
      var placeIdEl = document.getElementById('cp-client-address-place-id');
      var latEl = document.getElementById('cp-client-address-lat');
      var lngEl = document.getElementById('cp-client-address-lng');
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

      function applyClient(o) {
        if (!o) return;
        if (nameEl) nameEl.value = o.name || '';
        if (emailEl) emailEl.value = o.email || '';
        if (email2El) email2El.value = o.email_secondary || '';
        if (addrEl) addrEl.value = o.address || '';
        if (placeIdEl) placeIdEl.value = o.address_place_id || '';
        if (latEl) latEl.value = o.address_latitude != null && o.address_latitude !== '' ? String(o.address_latitude) : '';
        if (lngEl) lngEl.value = o.address_longitude != null && o.address_longitude !== '' ? String(o.address_longitude) : '';
        if (phoneEl) {
          phoneEl.value = o.phone_display || o.phone || '';
          phoneEl.dispatchEvent(new Event('input', { bubbles: true }));
        }
        updateDirectoryHint(o);
        syncPlacesAddress();
      }

      function fetchClientById(id, callback) {
        var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(id);
        fetch(url, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          }
        })
          .then(function (r) { return r.json(); })
          .then(function (rows) { callback(rows && rows[0] ? rows[0] : null); })
          .catch(function () { callback(null); });
      }

      function onClientSelected(id, option) {
        if (!id) {
          updateDirectoryHint(null);
          return;
        }
        if (option && option.address !== undefined) {
          applyClient(option);
          return;
        }
        fetchClientById(id, function (row) {
          if (row) applyClient(row);
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
        labelField: 'display',
        searchField: ['display', 'name', 'email', 'phone', 'address'],
        maxOptions: 50,
        loadThrottle: 250,
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
          onClientSelected(val, val ? this.options[val] : null);
        }
      };

      if (initial && initial.id) {
        opts.options = [initial];
        opts.items = [String(initial.id)];
      }

      var ts = new TomSelect(sel, opts);

      if (initial && initial.id) {
        onClientSelected(String(initial.id), initial);
      }
    })();
  </script>
@endpush

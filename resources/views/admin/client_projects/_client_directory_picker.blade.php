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
    <div class="cp-client-assign__picker">
      <div class="fq-catalog-picker cp-client-directory-picker__field">
        <select id="cp-client-directory"
                name="client_id"
                class="fq-catalog-picker__select"
                data-preview-target="cp-client-preview"
                data-search-url="{{ $clientsSearchUrl }}"
                data-initial="{{ (int) old('client_id', ($initialClientForPicker ?? [])['id'] ?? 0) }}"
                aria-label="Client from directory">
          {{-- Blank option: Tom Select placeholder handles empty state (option text would stick while typing). --}}
          <option value=""></option>
        </select>
      </div>
      <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm cp-client-directory-picker__manage">
        <i class="fa fa-users" aria-hidden="true"></i> Manage clients
      </a>
    </div>
    <div class="cp-client-assign__preview">
      <div id="cp-client-preview" class="fq-material-preview cp-client-preview">
        @if (! empty($initialClientForPicker))
          <span class="fq-material-preview__name">{{ $initialClientForPicker['name'] }}</span>
          @if (! empty($initialClientForPicker['email']))
            <span class="admin-form-help">{{ $initialClientForPicker['email'] }}</span>
          @endif
          @if (! empty($initialClientForPicker['phone_display']))
            <span class="admin-form-help">{{ $initialClientForPicker['phone_display'] }}</span>
          @endif
          @if (! empty($initialClientForPicker['address']))
            <span class="admin-form-help">{{ $initialClientForPicker['address'] }}</span>
          @endif
          <a href="{{ route('admin.clients.edit', $initialClientForPicker['id']) }}" class="admin-text-link fq-material-preview__link">Client record</a>
        @else
          <span class="admin-form-help">Select a client from the directory.</span>
        @endif
      </div>
    </div>
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
      var clientEditBase = @json(url('admin/clients'));

      function escapeHtml(text) {
        return String(text)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
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

      function updatePreview(option) {
        var previewEl = document.getElementById('cp-client-preview');
        if (!previewEl) return;

        if (!option) {
          previewEl.innerHTML = '<span class="admin-form-help">Select a client from the directory.</span>';
          return;
        }

        var html = '<span class="fq-material-preview__name">' + escapeHtml(option.name || '') + '</span>';
        if (option.email) {
          html += '<span class="admin-form-help">' + escapeHtml(option.email) + '</span>';
        }
        if (option.phone_display || option.phone) {
          html += '<span class="admin-form-help">' + escapeHtml(option.phone_display || option.phone) + '</span>';
        }
        if (option.address) {
          html += '<span class="admin-form-help">' + escapeHtml(option.address) + '</span>';
        }
        if (option.id) {
          html += '<a href="' + escapeHtml(clientEditBase + '/' + option.id + '/edit') + '" class="admin-text-link fq-material-preview__link">Client record</a>';
        }
        previewEl.innerHTML = html;
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
        var url = searchUrl + (searchUrl.indexOf('?')>= 0 ? '&' : '?') + 'id=' + encodeURIComponent(id);
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
          updatePreview(null);
          updateDirectoryHint(null);
          return;
        }

        function handle(row) {
          if (!row) return;
          updatePreview(row);
          applyClient(row);
        }

        if (option && option.name !== undefined) {
          handle(option);
          return;
        }

        fetchClientById(id, handle);
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
          var url = searchUrl + (searchUrl.indexOf('?')>= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query || '');
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

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
@endpush

<div class="admin-form-group cp-client-directory-picker">
  <div class="cp-client-directory-picker__head">
    <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-users" aria-hidden="true"></i> Manage clients
    </a>
    <label for="cp-client-directory" class="cp-label-with-hint">
      Client from directory
      @include('admin.client_projects._cp_field_hint', [
        'text' => 'Type a name, email, or phone. Choosing a client fills the fields below; you can edit them before saving. If no client is selected, a new directory entry is created automatically.',
      ])
    </label>
  </div>
  <select id="cp-client-directory" name="client_id">
    <option value="">— Search to select —</option>
  </select>
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

      function applyClient(o) {
        if (!o) return;
        if (nameEl) nameEl.value = o.name || '';
        if (emailEl) emailEl.value = o.email || '';
        if (email2El) email2El.value = o.email_secondary || '';
        if (addrEl) addrEl.value = o.address || '';
        if (phoneEl) {
          phoneEl.value = o.phone_display || o.phone || '';
          phoneEl.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }

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
          if (!val) return;
          var o = this.options[val];
          if (o) applyClient(o);
        }
      };

      if (initial && initial.id) {
        opts.options = [initial];
        opts.items = [String(initial.id)];
      }

      new TomSelect(sel, opts);
    })();
  </script>
@endpush

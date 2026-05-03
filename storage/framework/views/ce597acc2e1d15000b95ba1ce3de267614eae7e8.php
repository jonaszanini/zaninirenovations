<?php $__env->startPush('head'); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<?php $__env->stopPush(); ?>

<div class="admin-form-group cp-client-directory-picker">
  <label for="cp-client-directory">Client from directory</label>
  <select id="cp-client-directory" name="client_id">
    <option value="">— Search to select —</option>
  </select>
  <p class="admin-form-help">
    Type a name, email, or phone. Choosing a client fills the fields below (you can still edit them for this project only).
    <a href="<?php echo e(route('admin.clients.index')); ?>">Manage clients</a>
  </p>
</div>

<?php $__env->startPush('scripts'); ?>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <script>
    (function () {
      var searchUrl = <?php echo json_encode($clientsSearchUrl, 15, 512) ?>;
      var initial = <?php echo json_encode($initialClientForPicker ?? null, 15, 512) ?>;

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
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_client_directory_picker.blade.php ENDPATH**/ ?>
(function () {
  var root = document.getElementById('ss-import-panel');
  if (!root) return;

  var importUrl = document.getElementById('ss-import-url');
  var importBtn = document.getElementById('ss-import-btn');
  var statusEl = document.getElementById('ss-import-status');
  var noteEl = document.getElementById('ss-import-address-note');
  var previewUrl = root.getAttribute('data-import-url');

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.hidden = !msg;
    statusEl.classList.toggle('admin-form-error', !!isError);
    statusEl.classList.toggle('admin-form-help', !isError);
  }

  function fillField(id, value) {
    var el = document.getElementById(id);
    if (!el || value === null || value === undefined || value === '') return;
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
  }

  importBtn.addEventListener('click', function () {
    var url = (importUrl && importUrl.value ? importUrl.value : '').trim();
    if (!url) {
      setStatus('Enter a website URL to import.', true);
      return;
    }
    importBtn.disabled = true;
    setStatus('Fetching website…', false);

    fetch(previewUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
      body: JSON.stringify({ url: url }),
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (payload) {
        if (!payload.ok) {
          setStatus(payload.message || 'Import failed.', true);
          return;
        }
        var data = payload.data || {};
        fillField('ss-name', data.name);
        fillField('ss-url', data.url || url);
        fillField('ss-phone', data.phone);
        fillField('ss-email', data.email);
        fillField('ss-address', data.address);
        if (data.logo_url) {
          var logoEl = document.getElementById('ss-logo-url');
          if (logoEl && !logoEl.value.trim()) {
            logoEl.value = data.logo_url;
            logoEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
        }
        if (data.tags) {
          var tagsEl = document.getElementById('ss-tags');
          if (tagsEl && !tagsEl.value.trim()) {
            tagsEl.value = data.tags;
          }
        }
        if (data.notes) {
          var notes = document.getElementById('ss-notes');
          if (notes && !notes.value.trim()) {
            notes.value = data.notes;
          }
        }
        if (noteEl) {
          var parts = [];
          if (data.distance_note) parts.push(data.distance_note);
          if (data.other_addresses && data.other_addresses.length) {
            parts.push('Other addresses found: ' + data.other_addresses.join('; '));
          }
          noteEl.textContent = parts.join(' ');
          noteEl.hidden = parts.length === 0;
        }
        setStatus('Fields updated — review and save when ready.', false);
      })
      .catch(function () {
        setStatus('Could not reach the server. Try again.', true);
      })
      .finally(function () {
        importBtn.disabled = false;
      });
  });
})();

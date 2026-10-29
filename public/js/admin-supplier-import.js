(function () {
  var root = document.getElementById('ss-import-panel');
  if (!root) return;

  var importUrl = document.getElementById('ss-import-url');
  var importBtn = document.getElementById('ss-import-btn');
  var importBtnIcon = document.getElementById('ss-import-btn-icon');
  var statusEl = document.getElementById('ss-import-status');
  var statusSpinner = document.getElementById('ss-import-status-spinner');
  var noteEl = document.getElementById('ss-import-address-note');
  var supplierForm = document.getElementById('ss-supplier-form');
  var supplierFieldset = document.getElementById('ss-supplier-form-body');
  var previewUrl = root.getAttribute('data-import-url');
  var importAbort = null;
  var importTimeoutId = null;
  var IMPORT_TIMEOUT_MS = 30000;

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.hidden = !msg;
    statusEl.classList.toggle('admin-form-error', !!isError);
    statusEl.classList.toggle('admin-form-help', !isError);
  }

  function setImportBusy(busy) {
    root.classList.toggle('is-busy', busy);
    if (supplierForm) supplierForm.classList.toggle('is-import-locked', busy);
    if (supplierFieldset) supplierFieldset.disabled = busy;
    if (importUrl) importUrl.disabled = busy;
    if (importBtn) importBtn.disabled = busy;
    if (statusSpinner) statusSpinner.hidden = !busy;
    if (importBtnIcon) {
      importBtnIcon.classList.toggle('fa-download', !busy);
      importBtnIcon.classList.toggle('fa-spinner', busy);
      importBtnIcon.classList.toggle('fa-spin', busy);
    }
  }

  function clearImportRequest() {
    if (importTimeoutId !== null) {
      clearTimeout(importTimeoutId);
      importTimeoutId = null;
    }
    importAbort = null;
  }

  function parseJsonResponse(res) {
    return res.text().then(function (text) {
      if (!text || !text.trim()) {
        throw new Error('empty_body');
      }
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error('invalid_json');
      }
    });
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

    if (importAbort) {
      importAbort.abort();
    }
    importAbort = new AbortController();
    importTimeoutId = setTimeout(function () {
      if (importAbort) {
        importAbort.abort();
      }
    }, IMPORT_TIMEOUT_MS);

    setImportBusy(true);
    setStatus('Fetching website…', false);

    fetch(previewUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
      body: JSON.stringify({ url: url }),
      signal: importAbort.signal,
    })
      .then(function (res) {
        return parseJsonResponse(res).then(function (payload) {
          if (!res.ok && payload && typeof payload === 'object' && payload.message) {
            return payload;
          }
          if (!res.ok) {
            throw new Error('http_' + res.status);
          }
          return payload;
        });
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
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          setStatus('Import timed out. Try again.', true);
        } else if (err && err.message === 'invalid_json') {
          setStatus('Server returned an unexpected response. Try again.', true);
        } else if (err && err.message === 'empty_body') {
          setStatus('Server returned an empty response. Try again.', true);
        } else {
          setStatus('Could not reach the server. Try again.', true);
        }
      })
      .finally(function () {
        clearImportRequest();
        setImportBusy(false);
        if (importBtn) {
          importBtn.focus();
        }
      });
  });
})();

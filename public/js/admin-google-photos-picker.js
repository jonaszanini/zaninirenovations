(function () {
  'use strict';

  function parseSecondsDuration(value, fallbackMs) {
    if (!value || typeof value !== 'string' || !value.endsWith('s')) return fallbackMs;
    var n = parseFloat(value.slice(0, -1));
    if (!isFinite(n) || n <= 0) return fallbackMs;
    return Math.round(n * 1000);
  }

  function PickerReconnectError(message) {
    this.name = 'PickerReconnectError';
    this.message = message || 'Your Google Photos connection expired. Reload the page, then connect again.';
  }
  PickerReconnectError.prototype = Object.create(Error.prototype);
  PickerReconnectError.prototype.constructor = PickerReconnectError;

  function throwPickerApiError(res, data, fallback) {
    if (data && data.reconnect_required) {
      var err = new PickerReconnectError(data.error);
      err.reconnectRequired = true;
      throw err;
    }
    throw new Error((data && data.error) ? data.error : (fallback + (res && res.status ? ' (HTTP ' + res.status + ').' : '.')));
  }

  function handlePickerFailure(e, popup) {
    if (popup && !popup.closed) {
      try { popup.close(); } catch (ignore) {}
    }
    if (e && e.reconnectRequired) {
      alert(e.message);
      window.location.reload();
      return;
    }
    alert(e && e.message ? e.message : 'Could not open Google Photos Picker.');
  }

  function pickerThumbUrl(baseUrl) {
    if (!baseUrl || typeof baseUrl !== 'string') return '';
    var t = baseUrl.trim();
    if (t.indexOf('=') !== -1) return t;
    return t + '=w240-h240-c';
  }

  function pickerThumbnailSrc(baseUrl, proxyUrl) {
    var thumb = pickerThumbUrl(baseUrl);
    if (!thumb) return '';
    if (proxyUrl) {
      return proxyUrl + (proxyUrl.indexOf('?') === -1 ? '?' : '&') + 'url=' + encodeURIComponent(thumb);
    }
    return thumb;
  }

  function getFileInput(root) {
    var id = root.getAttribute('data-file-input-id') || 'photos';
    return document.getElementById(id);
  }

  function initRoot(root) {
    if (!root || root.getAttribute('data-google-photos-init') === '1') return;
    root.setAttribute('data-google-photos-init', '1');

    var createSessionUrl = root.getAttribute('data-picker-create-url');
    var mediaUrlTemplate = root.getAttribute('data-picker-media-url-template');
    var thumbnailProxyUrl = root.getAttribute('data-picker-thumbnail-url') || '';
    var maxTotal = parseInt(root.getAttribute('data-max-total') || '15', 10);
    var statusMode = root.getAttribute('data-status-mode') || 'total';
    var formSync = root.getAttribute('data-form-sync') !== 'false';
    var btnOpen = root.querySelector('[data-google-open-picker]');
    var grid = root.querySelector('[data-google-grid]');
    var status = root.querySelector('[data-google-status]');
    var hintEl = root.querySelector('[data-google-hint]');
    var pickedFields = root.querySelector('[data-google-picked-fields]');

    if (!btnOpen || !grid || !status) return;

    function selectedCount() {
      return grid.querySelectorAll('input[type="checkbox"][data-picker-checkbox]:checked').length;
    }

    function pickedTileCount() {
      return grid.querySelectorAll('.admin-google-photo-tile').length;
    }

    function uploadCount() {
      var fileInput = getFileInput(root);
      return fileInput && fileInput.files ? fileInput.files.length : 0;
    }

    function maxSuffix() {
      return statusMode === 'remaining' ? 'more' : 'total';
    }

    function refreshStatus() {
      var files = uploadCount();
      var picked = pickedTileCount();
      var checked = selectedCount();
      status.textContent =
        picked + ' from Google (' + checked + ' checked) · ' + files + ' uploads · max ' + maxTotal + ' ' + maxSuffix();
      root.dispatchEvent(new CustomEvent('admin:google-photos-changed', { bubbles: true }));
    }

    function applyDefaultChecks() {
      var files = uploadCount();
      var maxGoogle = Math.max(0, maxTotal - files);
      grid.querySelectorAll('input[data-picker-checkbox]').forEach(function (c) {
        c.checked = true;
      });
      while (selectedCount() > maxGoogle) {
        var list = grid.querySelectorAll('input[data-picker-checkbox]:checked');
        if (!list.length) break;
        list[list.length - 1].checked = false;
      }
      refreshStatus();
    }

    function enforceMax(checkbox) {
      var files = uploadCount();
      var maxGoogle = Math.max(0, maxTotal - files);
      if (!checkbox.checked) return;
      if (selectedCount() > maxGoogle) {
        checkbox.checked = false;
        alert('Too many Google Photos selected for current upload count (max ' + maxTotal + ' ' + maxSuffix() + ').');
      }
    }

    function renderItems(items) {
      grid.innerHTML = '';
      items.forEach(function (it) {
        if (!it || !it.baseUrl) return;
        var url = pickerThumbnailSrc(it.baseUrl, thumbnailProxyUrl);
        var label = it.filename || 'Photo';

        var tile = document.createElement('label');
        tile.className = 'admin-google-photo-tile';
        tile.setAttribute('data-picker-payload', JSON.stringify({
          id: it.id || '',
          baseUrl: it.baseUrl || '',
          mimeType: it.mimeType || '',
          filename: it.filename || ''
        }));

        var thumb = document.createElement('div');
        thumb.className = 'admin-google-photo-thumb';

        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'admin-checkbox__input admin-checkbox__input--standalone admin-google-photo-tile-check';
        cb.setAttribute('data-picker-checkbox', '1');
        cb.addEventListener('change', function () { enforceMax(cb); refreshStatus(); });
        thumb.appendChild(cb);

        var img = document.createElement('img');
        img.src = url;
        img.alt = '';
        img.loading = 'lazy';
        img.decoding = 'async';
        img.referrerPolicy = 'no-referrer';
        img.addEventListener('error', function () {
          thumb.classList.add('is-fallback');
        });
        thumb.appendChild(img);

        var fallback = document.createElement('div');
        fallback.className = 'admin-google-photo-thumb-fallback';
        fallback.textContent = 'Preview unavailable (still imports if checked)';
        thumb.appendChild(fallback);

        var cap = document.createElement('div');
        cap.className = 'admin-google-photo-caption';
        cap.textContent = label;

        tile.appendChild(thumb);
        tile.appendChild(cap);
        grid.appendChild(tile);
      });
      applyDefaultChecks();
    }

    async function fetchPickedMedia(sessionId, maxItems) {
      var mediaUrl = (mediaUrlTemplate || '').replace('__SESSION__', encodeURIComponent(sessionId));
      if (maxItems && maxItems > 0) {
        var sep = mediaUrl.indexOf('?') === -1 ? '?' : '&';
        mediaUrl += sep + 'max_items=' + encodeURIComponent(String(maxItems));
      }
      if (!mediaUrl || mediaUrl === mediaUrlTemplate) throw new Error('Picker media URL is not configured.');
      var res = await fetch(mediaUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      var data = await res.json().catch(function () { return null; });
      if (!res.ok || !data || data.error) {
        throwPickerApiError(res, data, 'Could not read picked media');
      }
      return data;
    }

    async function openPicker(popup, allowedSlots) {
      status.textContent = 'Starting picker…';
      if (hintEl) {
        hintEl.hidden = true;
        hintEl.textContent = '';
      }
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
      if (!csrfToken) throw new Error('Missing CSRF token. Reload the page and try again.');
      var res = await fetch(createSessionUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ max_item_count: allowedSlots }),
      });
      var data = await res.json().catch(function () { return null; });
      if (!res.ok || !data || data.error) {
        throwPickerApiError(res, data, 'Could not open Google Photos picker');
      }
      var pickerUrl = data.pickerUriAutoclose || data.pickerUri;
      var sessionId = data.id;
      if (!pickerUrl || !sessionId) throw new Error('Picker session response was incomplete.');
      if (!popup || popup.closed) throw new Error('Popup blocked. Allow popups for this site and try again.');
      popup.location.href = pickerUrl;

      status.textContent = 'Waiting for selection…';
      var pollEvery = parseSecondsDuration(data.pollingConfig && data.pollingConfig.pollInterval, 2000);
      var serverTimeoutMs = parseSecondsDuration(data.pollingConfig && data.pollingConfig.timeoutIn, 120000);
      var started = Date.now();
      var deadline = started + serverTimeoutMs;
      var pickerClosedAt = null;
      var postCloseBudgetMs = 240000;

      while (Date.now() < deadline) {
        await new Promise(function (resolve) { setTimeout(resolve, pollEvery); });
        if (popup && popup.closed && pickerClosedAt === null) {
          pickerClosedAt = Date.now();
          deadline = Math.max(deadline, pickerClosedAt + postCloseBudgetMs);
          status.textContent = 'Finishing up with Google Photos… (albums can take a bit)';
        }
        var picked;
        try {
          picked = await fetchPickedMedia(sessionId, allowedSlots);
        } catch (ignore) {
          continue;
        }
        if (picked.ready) {
          renderItems(picked.items || []);
          status.textContent = '';
          if (hintEl && (picked.items || []).length === 0) {
            hintEl.textContent = 'No items were selected in Google Photos Picker.';
            hintEl.hidden = false;
          }
          refreshStatus();
          return;
        }
      }
      status.textContent = '';
      if (hintEl) {
        hintEl.textContent = 'Google Photos did not finish returning your picks in time. Try again, confirm your selection in the picker, or pick fewer photos at once.';
        hintEl.hidden = false;
      }
      refreshStatus();
    }

    function getCheckedPayloads() {
      var payloads = [];
      grid.querySelectorAll('.admin-google-photo-tile').forEach(function (tile) {
        var cb = tile.querySelector('input[type="checkbox"][data-picker-checkbox]');
        if (!cb || !cb.checked) return;
        var payload = tile.getAttribute('data-picker-payload') || '';
        if (payload) payloads.push(payload);
      });
      return payloads;
    }

    btnOpen.addEventListener('click', async function () {
      btnOpen.disabled = true;
      try {
        var files = uploadCount();
        var allowedSlots = Math.max(0, maxTotal - files);
        if (allowedSlots < 1) throw new Error('No free slots left for Google photos. Remove selected uploads first.');
        var popup = window.open('', '_blank');
        if (!popup) throw new Error('Popup blocked. Allow popups for this site and try again.');
        popup.document.write('Loading Google Photos Picker...');
        await openPicker(popup, allowedSlots);
      } catch (e) {
        status.textContent = '';
        handlePickerFailure(e, popup);
      } finally {
        btnOpen.disabled = false;
        refreshStatus();
      }
    });

    var fileInput = getFileInput(root);
    if (fileInput) {
      fileInput.addEventListener('change', function () {
        grid.querySelectorAll('input[type="checkbox"][data-picker-checkbox]:checked').forEach(function (cb) {
          enforceMax(cb);
        });
        refreshStatus();
      });
    }

    if (formSync && pickedFields) {
      var form = root.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          pickedFields.innerHTML = '';
          getCheckedPayloads().forEach(function (payload) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'google_picker_items[]';
            input.value = payload;
            pickedFields.appendChild(input);
          });
        });
      }
    }

    root.googlePhotosPicker = {
      getCheckedPayloads: getCheckedPayloads,
      getCheckedCount: selectedCount,
      getUploadCount: uploadCount,
      getMaxTotal: function () { return maxTotal; },
      refreshStatus: refreshStatus,
      clearPickedItems: function () {
        grid.innerHTML = '';
        if (pickedFields) pickedFields.innerHTML = '';
        refreshStatus();
      },
    };

    refreshStatus();
  }

  function initLocalPreviewGrids() {
    document.querySelectorAll('[data-admin-local-preview-target]').forEach(function (input) {
      if (input.getAttribute('data-local-preview-init') === '1') return;
      input.setAttribute('data-local-preview-init', '1');
      var gridId = input.getAttribute('data-admin-local-preview-target');
      var grid = gridId ? document.getElementById(gridId) : null;
      if (!grid) return;

      function render() {
        grid.innerHTML = '';
        if (!input.files || !input.files.length) {
          grid.hidden = true;
          return;
        }
        grid.hidden = false;
        Array.prototype.forEach.call(input.files, function (file) {
          var tile = document.createElement('div');
          tile.className = 'admin-local-photo-preview-tile';
          var img = document.createElement('img');
          img.alt = file.name || '';
          var cap = document.createElement('span');
          cap.className = 'admin-local-photo-preview-caption';
          cap.textContent = file.name || 'Photo';
          tile.appendChild(img);
          tile.appendChild(cap);
          grid.appendChild(tile);
          var reader = new FileReader();
          reader.onload = function (e) {
            img.src = e.target.result;
          };
          reader.readAsDataURL(file);
        });
      }

      input.addEventListener('change', render);
    });
  }

  function boot() {
    document.querySelectorAll('.admin-google-photos').forEach(initRoot);
    initLocalPreviewGrids();
  }

  window.AdminGooglePhotosPicker = {
    init: initRoot,
    collectPayloads: function (root) {
      return root && root.googlePhotosPicker ? root.googlePhotosPicker.getCheckedPayloads() : [];
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();

(function () {
  'use strict';

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }

  function draftRoot() {
    return document.getElementById('portfolio-draft-photos-root');
  }

  function draftGrid() {
    return document.getElementById('portfolio-draft-photo-grid');
  }

  function showDraftSection() {
    var root = draftRoot();
    if (root) root.hidden = false;
  }

  function appendDraftTile(photo) {
    var grid = draftGrid();
    if (!grid || !photo || !photo.draft_path) return;

    var existing = grid.querySelector('[data-draft-path="' + CSS.escape(photo.draft_path) + '"]');
    if (existing) return;

    showDraftSection();

    var tile = document.createElement('div');
    tile.className = 'admin-portfolio-draft-tile';
    tile.setAttribute('data-draft-path', photo.draft_path);

    var img = document.createElement('img');
    img.src = photo.url || '';
    img.alt = '';
    img.loading = 'lazy';
    img.decoding = 'async';

    var cap = document.createElement('span');
    cap.className = 'admin-local-photo-preview-caption';
    cap.textContent = photo.filename || photo.label || 'Photo';

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'admin-portfolio-draft-remove js-portfolio-draft-remove';
    removeBtn.title = 'Remove saved photo';
    removeBtn.setAttribute('aria-label', 'Remove saved photo');
    removeBtn.innerHTML = '<i class="fa fa-times" aria-hidden="true"></i>';

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'draft_photos[]';
    hidden.value = photo.draft_path;

    tile.appendChild(img);
    tile.appendChild(cap);
    tile.appendChild(removeBtn);
    tile.appendChild(hidden);
    grid.appendChild(tile);
  }

  function draftCount() {
    var grid = draftGrid();
    return grid ? grid.querySelectorAll('.admin-portfolio-draft-tile').length : 0;
  }

  function clearFileInput(fileInput) {
    if (!fileInput) return;
    try {
      fileInput.value = '';
      fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (ignore) {
      /* ignore */
    }
  }

  async function stashFilesBeforeSubmit(fileInput, stashUrl, maxTotal) {
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
      return true;
    }

    var remaining = maxTotal - draftCount();
    if (remaining < 1) {
      alert('Too many photos (max ' + maxTotal + '). Remove some saved photos first.');
      return false;
    }

    var files = Array.prototype.slice.call(fileInput.files, 0, remaining);
    if (files.length < fileInput.files.length) {
      alert('Only ' + remaining + ' more photo slot(s) left. Extra files were not saved.');
    }

    var fd = new FormData();
    files.forEach(function (file) {
      fd.append('photos[]', file);
    });

    var res = await fetch(stashUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: fd,
    });

    var body = null;
    try {
      body = await res.json();
    } catch (ignore) {
      body = null;
    }

    if (!res.ok) {
      var msg = (body && body.error) ? body.error : ('Could not save photos (HTTP ' + res.status + ').');
      alert(msg);
      return false;
    }

    (body.photos || []).forEach(appendDraftTile);
    clearFileInput(fileInput);

    return true;
  }

  async function removeDraft(path, removeUrl) {
    var res = await fetch(removeUrl, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ draft_path: path }),
    });

    if (!res.ok) {
      var body = null;
      try {
        body = await res.json();
      } catch (ignore) {
        body = null;
      }
      alert((body && body.error) ? body.error : 'Could not remove saved photo.');
      return false;
    }

    return true;
  }

  function init(config) {
    var form = document.getElementById(config.formId || 'portfolio-create-form');
    if (!form || form.getAttribute('data-portfolio-create-init') === '1') return;
    form.setAttribute('data-portfolio-create-init', '1');

    var fileInput = document.getElementById(config.fileInputId || 'photos');
    var stashUrl = config.stashDraftUrl || '';
    var removeUrl = config.removeDraftUrl || '';
    var maxTotal = parseInt(config.maxTotal || '15', 10);
    var submitting = false;

    form.addEventListener('submit', async function (e) {
      if (submitting || !stashUrl) return;
      if (!fileInput || !fileInput.files || !fileInput.files.length) return;

      e.preventDefault();
      submitting = true;

      try {
        var ok = await stashFilesBeforeSubmit(fileInput, stashUrl, maxTotal);
        if (ok) {
          form.submit();
        }
      } finally {
        submitting = false;
      }
    });

    document.addEventListener('click', async function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.js-portfolio-draft-remove') : null;
      if (!btn || !removeUrl) return;

      var tile = btn.closest('.admin-portfolio-draft-tile');
      if (!tile) return;

      var path = tile.getAttribute('data-draft-path') || '';
      if (!path) return;

      btn.disabled = true;
      var removed = await removeDraft(path, removeUrl);
      if (removed) {
        tile.remove();
        var root = draftRoot();
        var grid = draftGrid();
        if (root && grid && !grid.querySelector('.admin-portfolio-draft-tile')) {
          root.hidden = true;
        }
      } else {
        btn.disabled = false;
      }
    });

    window.portfolioDraftPhotos = {
      append: appendDraftTile,
      count: draftCount,
      clearFileInput: function () {
        clearFileInput(fileInput);
      },
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    var boot = window.portfolioCreateConfig;
    if (boot) init(boot);
  });
})();

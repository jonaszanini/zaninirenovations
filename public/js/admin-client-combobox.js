/**
 * Simple client-directory combobox: type in the field, pick from the list.
 * Posts client_id via #cp-client-id and fills contact fields on choose.
 */
(function () {
  var root = document.getElementById('cp-client-combobox');
  if (!root) return;

  var searchUrl = root.getAttribute('data-search-url') || '';
  var hidden = document.getElementById('cp-client-id');
  var input = document.getElementById('cp-client-combobox-input');
  var list = document.getElementById('cp-client-combobox-list');
  var clearBtn = document.getElementById('cp-client-combobox-clear');
  var hintEl = document.getElementById('cp-client-address-directory-hint');
  var addressInputId = 'cp-client-address';

  if (!searchUrl || !hidden || !input || !list) return;

  var rows = [];
  var activeIndex = -1;
  var open = false;
  var selected = null;
  var debounceTimer = null;
  var lastQuery = null;
  var abortCtrl = null;

  function field(id) {
    return document.getElementById(id);
  }

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }

  function nameFrom(o) {
    if (!o) return '';
    if (o.name) return String(o.name).trim();
    if (o.label) return String(o.label).split(' — ')[0].trim();
    return '';
  }

  function labelFrom(o) {
    if (!o) return '';
    return String(o.label || o.display || nameFrom(o) || '').trim();
  }

  function setPostedClientId(id) {
    hidden.value = id ? String(id) : '';
  }

  function updateClear() {
    if (!clearBtn) return;
    var show = !!(hidden.value || String(input.value || '').trim());
    clearBtn.hidden = !show;
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

    var name = nameFrom(o);
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

  function closeList() {
    open = false;
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    activeIndex = -1;
  }

  function openList() {
    open = true;
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function setActive(index) {
    var opts = list.querySelectorAll('[role="option"]');
    if (!opts.length) {
      activeIndex = -1;
      input.removeAttribute('aria-activedescendant');
      return;
    }
    if (index < 0) index = opts.length - 1;
    if (index >= opts.length) index = 0;
    activeIndex = index;
    opts.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
    });
    opts[index].scrollIntoView({ block: 'nearest' });
    input.setAttribute('aria-activedescendant', opts[index].id);
  }

  function render(items, query) {
    rows = items || [];
    list.innerHTML = '';
    if (!rows.length) {
      var empty = document.createElement('li');
      empty.className = 'cp-client-combobox__empty';
      empty.textContent = query
        ? 'No matching clients'
        : 'No clients in the directory yet';
      list.appendChild(empty);
      openList();
      return;
    }

    rows.forEach(function (row, i) {
      var li = document.createElement('li');
      li.id = 'cp-client-combobox-opt-' + i;
      li.setAttribute('role', 'option');
      li.className = 'cp-client-combobox__option';
      li.setAttribute('data-index', String(i));
      if (hidden.value && String(row.id) === String(hidden.value)) {
        li.classList.add('is-selected');
      }

      var name = document.createElement('span');
      name.className = 'cp-client-combobox__option-name';
      name.textContent = row.name || row.label || 'Client';
      li.appendChild(name);

      var metaBits = [];
      if (row.email) metaBits.push(row.email);
      if (row.phone_display || row.phone) metaBits.push(row.phone_display || row.phone);
      if (metaBits.length) {
        var meta = document.createElement('span');
        meta.className = 'cp-client-combobox__option-meta';
        meta.textContent = metaBits.join(' · ');
        li.appendChild(meta);
      }

      list.appendChild(li);
    });

    openList();
    setActive(0);
  }

  function fetchRows(query, callback) {
    if (abortCtrl && typeof abortCtrl.abort === 'function') {
      abortCtrl.abort();
    }
    abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query || '');
    fetch(url, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken()
      },
      signal: abortCtrl ? abortCtrl.signal : undefined
    })
      .then(function (r) {
        if (!r.ok) throw new Error('search failed');
        return r.json();
      })
      .then(function (json) {
        var data = Array.isArray(json) ? json : [];
        data.forEach(function (row) {
          if (row && row.id != null) row.id = String(row.id);
        });
        callback(data);
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        callback([]);
      });
  }

  function search(query, immediate) {
    var q = String(query || '').trim();
    if (!immediate && q === lastQuery && rows.length) {
      openList();
      return;
    }
    window.clearTimeout(debounceTimer);
    var run = function () {
      lastQuery = q;
      fetchRows(q, function (data) {
        render(data, q);
      });
    };
    if (immediate) {
      run();
    } else {
      debounceTimer = window.setTimeout(run, 180);
    }
  }

  function choose(row) {
    if (!row || !row.id) return;
    selected = row;
    setPostedClientId(row.id);
    input.value = nameFrom(row) || labelFrom(row);
    applyClient(row);
    root.classList.add('is-selected');
    updateClear();
    closeList();
  }

  function clearSelection(keepText) {
    selected = null;
    setPostedClientId('');
    root.classList.remove('is-selected');
    if (!keepText) input.value = '';
    updateDirectoryHint(null);
    updateClear();
  }

  list.addEventListener('mousedown', function (e) {
    e.preventDefault();
  });

  list.addEventListener('click', function (e) {
    var opt = e.target && e.target.closest ? e.target.closest('[data-index]') : null;
    if (!opt) return;
    var idx = parseInt(opt.getAttribute('data-index'), 10);
    if (!isNaN(idx) && rows[idx]) choose(rows[idx]);
  });

  input.addEventListener('focus', function () {
    search(input.value, lastQuery === null);
  });

  input.addEventListener('input', function () {
    var typed = String(input.value || '').trim();
    if (selected && typed !== nameFrom(selected) && typed !== labelFrom(selected)) {
      clearSelection(true);
    }
    updateClear();
    search(input.value, false);
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!open) {
        search(input.value, true);
        return;
      }
      setActive(activeIndex + 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (!open) return;
      setActive(activeIndex - 1);
    } else if (e.key === 'Enter') {
      if (open && activeIndex >= 0 && rows[activeIndex]) {
        e.preventDefault();
        choose(rows[activeIndex]);
      }
    } else if (e.key === 'Escape') {
      closeList();
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      clearSelection(false);
      input.focus();
      search('', true);
    });
  }

  document.addEventListener('click', function (e) {
    if (!root.contains(e.target)) closeList();
  });

  function syncPickerToForm() {
    if (hidden.value) return;
    var typed = String(input.value || '').trim().toLowerCase();
    if (!typed || !rows.length) return;
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var name = nameFrom(row).toLowerCase();
      var label = labelFrom(row).toLowerCase();
      if (typed === name || typed === label) {
        choose(row);
        return;
      }
    }
  }

  function bindForm(form) {
    if (!form || form.dataset.cpClientPickerBound === '1') return;
    form.dataset.cpClientPickerBound = '1';
    form.addEventListener('submit', function () {
      syncPickerToForm();
    }, true);
    form.addEventListener('formdata', function (e) {
      syncPickerToForm();
      if (hidden.value) e.formData.set('client_id', hidden.value);
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

  document.addEventListener('zr-places-ready', function (e) {
    if (e.detail && e.detail.inputId === addressInputId) syncPlacesAddress();
  });
  document.addEventListener('zr-places-address-changed', function (e) {
    if (e.detail && e.detail.inputId === addressInputId) updateDirectoryHint(null);
  });

  var initialJson = root.getAttribute('data-initial');
  if (initialJson) {
    try {
      var initial = JSON.parse(initialJson);
      if (initial && initial.id) {
        initial.id = String(initial.id);
        selected = initial;
        setPostedClientId(initial.id);
        input.value = nameFrom(initial) || labelFrom(initial);
        root.classList.add('is-selected');
        var nameInput = field('cp-client-name');
        if (!nameInput || String(nameInput.value || '').trim() === '') {
          applyClient(initial);
        } else {
          updateDirectoryHint(initial);
        }
      }
    } catch (err) {
      /* ignore bad initial payload */
    }
  }
  updateClear();
})();

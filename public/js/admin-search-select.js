/**
 * Admin search-select (combobox): type in the field, pick from the list.
 *
 * Markup: [data-admin-search-select]
 * Optional: data-search-url, data-options-source, data-search-fields,
 * data-meta-keys, data-fill, data-fill-skip-if, data-address-input-id,
 * data-address-hint-id, data-empty-message, data-empty-query-message,
 * data-hd-import-url (catalog pickers: pull a Home Depot SKU into the DB).
 *
 * Events: admin-search-select:change  detail { id, option }
 */
(function (window, document) {
  var instances = new WeakMap();

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }

  function parseJson(raw, fallback) {
    if (!raw) return fallback;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function nameFrom(row) {
    if (!row) return '';
    if (row.name) return String(row.name).trim();
    if (row.label) return String(row.label).split(' — ')[0].trim();
    return '';
  }

  function labelFrom(row) {
    if (!row) return '';
    return String(row.label || row.display || nameFrom(row) || '').trim();
  }

  function metaFrom(row, keys) {
    if (!row) return '';
    if (row.meta) return String(row.meta);
    var bits = [];
    (keys || []).forEach(function (key) {
      var val = row[key];
      if (val == null || val === '') return;
      if (key === 'sku') bits.push('SKU ' + val);
      else if (key === 'phone' && row.phone_display) return;
      else bits.push(String(val));
    });
    if (!bits.length && (row.phone_display || row.phone)) {
      bits.push(row.phone_display || row.phone);
    }
    return bits.join(' · ');
  }

  function loadLocalOptions(root) {
    var sourceId = root.getAttribute('data-options-source');
    if (sourceId) {
      var el = document.getElementById(sourceId);
      if (el) {
        if (!el._adminSsOptions) {
          el._adminSsOptions = parseJson(el.textContent, []);
          (el._adminSsOptions || []).forEach(function (row) {
            if (row && row.id != null) row.id = String(row.id);
          });
        }
        return el._adminSsOptions || [];
      }
    }
    var embedded = parseJson(root.getAttribute('data-options'), []);
    (embedded || []).forEach(function (row) {
      if (row && row.id != null) row.id = String(row.id);
    });
    return embedded || [];
  }

  function optionById(options, id) {
    if (!id) return null;
    for (var i = 0; i < options.length; i++) {
      if (String(options[i].id) === String(id)) return options[i];
    }
    return null;
  }

  function normalizeHdSku(raw) {
    var line = String(raw || '').trim();
    if (!line) return null;
    if (/homedepot\.ca/i.test(line)) {
      var fromUrl = line.match(/\/product\/(?:[^/?#]+\/)?(\d{6,})/i);
      return fromUrl ? fromUrl[1] : null;
    }
    var digits = line.match(/^(\d{6,})$/);
    return digits ? digits[1] : null;
  }

  function optionSku(row) {
    return row && row.sku != null ? String(row.sku).trim() : '';
  }

  function findExactSku(options, sku) {
    if (!sku) return null;
    for (var i = 0; i < (options || []).length; i++) {
      if (optionSku(options[i]) === sku) return options[i];
    }
    return null;
  }

  function rememberImportedOption(root, option) {
    if (!option || option.id == null) return;
    option.id = String(option.id);
    var sourceId = root.getAttribute('data-options-source');
    var el = sourceId ? document.getElementById(sourceId) : null;
    var pool = el ? (el._adminSsOptions || parseJson(el.textContent, [])) : loadLocalOptions(root);
    if (optionById(pool, option.id)) {
      var existing = optionById(pool, option.id);
      Object.keys(option).forEach(function (key) { existing[key] = option[key]; });
    } else {
      pool.push(option);
    }
    if (el) el._adminSsOptions = pool;
  }

  function matches(row, query, fields) {
    if (!query) return true;
    var q = query.toLowerCase();
    for (var i = 0; i < fields.length; i++) {
      if (String(row[fields[i]] || '').toLowerCase().indexOf(q) !== -1) return true;
    }
    return false;
  }

  function dispatchChange(root, id, option) {
    root.dispatchEvent(new CustomEvent('admin-search-select:change', {
      bubbles: true,
      detail: { id: id || '', option: option || null }
    }));
  }

  function applyFill(root, option, isInit) {
    var skipSel = root.getAttribute('data-fill-skip-if');
    if (isInit && skipSel) {
      var skipEl = document.querySelector(skipSel);
      if (skipEl && String(skipEl.value || '').trim() !== '') {
        updateAddressHint(root, option);
        return;
      }
    }

    var map = parseJson(root.getAttribute('data-fill'), null);
    if (map && typeof map === 'object') {
      Object.keys(map).forEach(function (key) {
        var el = document.getElementById(map[key]);
        if (!el) return;
        var val = '';
        if (option) {
          if (key === 'name') val = nameFrom(option);
          else if (key === 'phone_display') val = option.phone_display || option.phone || '';
          else val = option[key] != null ? String(option[key]) : '';
        }
        el.value = val;
        if (el.classList.contains('cp-phone-input')) {
          el.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    }

    var addrId = root.getAttribute('data-address-input-id');
    if (addrId && window.ZR_GOOGLE_MAPS && typeof window.ZR_GOOGLE_MAPS.syncAddressFromHidden === 'function') {
      window.ZR_GOOGLE_MAPS.syncAddressFromHidden(addrId);
    }
    updateAddressHint(root, option);
  }

  function updateAddressHint(root, option) {
    var hintId = root.getAttribute('data-address-hint-id');
    if (!hintId) return;
    var hintEl = document.getElementById(hintId);
    if (!hintEl) return;
    var addr = option && option.address ? String(option.address).trim() : '';
    if (!addr) {
      hintEl.hidden = true;
      hintEl.textContent = '';
      return;
    }
    hintEl.textContent = 'Address on file (saved with this project): ' + addr;
    hintEl.hidden = false;
  }

  function init(root) {
    if (!root || root.getAttribute('data-admin-search-select-init') === '1') return;
    var input = root.querySelector('.admin-search-select__input');
    var list = root.querySelector('.admin-search-select__list');
    var hidden = root.querySelector('.admin-search-select__value');
    var clearBtn = root.querySelector('.admin-search-select__clear');
    if (!input || !list || !hidden) return;

    root.setAttribute('data-admin-search-select-init', '1');

    var searchUrl = root.getAttribute('data-search-url') || '';
    var searchFields = parseJson(root.getAttribute('data-search-fields'), ['label', 'name', 'email', 'phone', 'sku', 'vendor']);
    var metaKeys = parseJson(root.getAttribute('data-meta-keys'), ['email', 'phone_display', 'sku', 'price_label']);
    var emptyMessage = root.getAttribute('data-empty-message') || 'No matches';
    var emptyQueryMessage = root.getAttribute('data-empty-query-message') || emptyMessage;
    var localOptions = loadLocalOptions(root);
    var maxOptions = parseInt(root.getAttribute('data-max-options') || '80', 10) || 80;
    var listParent = root.getAttribute('data-list-parent') || '';
    var listHome = list.parentNode;
    var hdImportUrl = root.getAttribute('data-hd-import-url') || '';

    var rows = [];
    var activeIndex = -1;
    var open = false;
    var selected = null;
    var debounceTimer = null;
    var lastQuery = null;
    var abortCtrl = null;
    var hdAbortCtrl = null;
    var hdPulling = false;
    var hdError = null;

    function setPostedId(id) {
      hidden.value = id ? String(id) : '';
    }

    function updateClear() {
      if (!clearBtn) return;
      clearBtn.hidden = !(hidden.value || String(input.value || '').trim());
    }

    function placeList() {
      if (listParent !== 'body') return;
      var rect = input.getBoundingClientRect();
      var pad = 8;
      var vw = window.innerWidth || document.documentElement.clientWidth || 0;
      var maxW = Math.max(80, vw - pad * 2);
      var width = Math.min(Math.max(rect.width, Math.min(280, maxW)), maxW);
      var left = rect.left;
      if (left + width > vw - pad) {
        left = vw - pad - width;
      }
      if (left < pad) {
        left = pad;
      }
      list.style.position = 'fixed';
      list.style.left = Math.round(left) + 'px';
      list.style.right = 'auto';
      list.style.top = (rect.bottom + 4) + 'px';
      list.style.width = Math.round(width) + 'px';
      list.style.maxWidth = maxW + 'px';
      list.style.zIndex = '200';
    }

    function restoreList() {
      if (listParent !== 'body') return;
      if (listHome && list.parentNode !== listHome) listHome.appendChild(list);
      list.style.position = '';
      list.style.left = '';
      list.style.right = '';
      list.style.top = '';
      list.style.width = '';
      list.style.maxWidth = '';
      list.style.zIndex = '';
    }

    function closeList() {
      open = false;
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
      restoreList();
    }

    function openList() {
      open = true;
      if (listParent === 'body' && list.parentNode !== document.body) {
        document.body.appendChild(list);
      }
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      placeList();
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

    function renderHdAction(sku, query) {
      if (hdError && hdError.query === query) {
        var err = document.createElement('li');
        err.className = 'admin-search-select__error';
        err.setAttribute('role', 'status');
        err.textContent = hdError.message;
        list.appendChild(err);
      }

      var li = document.createElement('li');
      li.id = (root.id || 'ss') + '-hd-import';
      li.setAttribute('role', 'option');
      li.className = 'admin-search-select__option admin-search-select__option--action';
      li.setAttribute('data-hd-import-action', sku);
      if (hdPulling) {
        li.classList.add('is-loading');
        li.setAttribute('aria-disabled', 'true');
      }

      var body = document.createElement('span');
      body.className = 'admin-search-select__option-body';

      var nameEl = document.createElement('span');
      nameEl.className = 'admin-search-select__option-name';
      nameEl.textContent = hdPulling
        ? ('Pulling SKU ' + sku + ' from Home Depot…')
        : ('Pull SKU ' + sku + ' from Home Depot');
      body.appendChild(nameEl);

      var metaEl = document.createElement('span');
      metaEl.className = 'admin-search-select__option-meta';
      metaEl.textContent = hdPulling ? 'Creating catalog material' : 'Add this item to the materials catalog';
      body.appendChild(metaEl);
      li.appendChild(body);
      list.appendChild(li);
    }

    function render(items, query) {
      rows = items || [];
      list.innerHTML = '';
      var sku = hdImportUrl ? normalizeHdSku(query) : null;
      var exact = sku ? (findExactSku(rows, sku) || findExactSku(localOptions, sku)) : null;
      if (exact && sku) {
        var already = rows.some(function (row) { return String(row.id) === String(exact.id); });
        if (!already) rows = [exact].concat(rows);
      }
      var showHd = !!(sku && hdImportUrl && !exact);

      if (!rows.length && !showHd) {
        var empty = document.createElement('li');
        empty.className = 'admin-search-select__empty';
        empty.textContent = query ? emptyMessage : emptyQueryMessage;
        list.appendChild(empty);
        openList();
        return;
      }

      rows.forEach(function (row, i) {
        var li = document.createElement('li');
        li.id = (root.id || 'ss') + '-opt-' + i;
        li.setAttribute('role', 'option');
        li.className = 'admin-search-select__option';
        li.setAttribute('data-index', String(i));
        if (hidden.value && String(row.id) === String(hidden.value)) {
          li.classList.add('is-selected');
        }

        var imageUrl = String(row.image || row.image_url || '').trim();
        if (imageUrl) {
          var img = document.createElement('img');
          img.className = 'admin-search-select__option-thumb';
          img.src = imageUrl;
          img.alt = '';
          img.loading = 'lazy';
          img.decoding = 'async';
          img.referrerPolicy = 'no-referrer';
          img.addEventListener('error', function () {
            if (img.parentNode) img.parentNode.removeChild(img);
          });
          li.appendChild(img);
        }

        var body = document.createElement('span');
        body.className = 'admin-search-select__option-body';

        var nameEl = document.createElement('span');
        nameEl.className = 'admin-search-select__option-name';
        nameEl.textContent = row.name || row.label || 'Item';
        body.appendChild(nameEl);

        var meta = metaFrom(row, metaKeys);
        if (meta) {
          var metaEl = document.createElement('span');
          metaEl.className = 'admin-search-select__option-meta';
          metaEl.textContent = meta;
          body.appendChild(metaEl);
        }
        li.appendChild(body);
        list.appendChild(li);
      });

      if (showHd) renderHdAction(sku, query);

      openList();
      setActive(0);
    }

    function finishHdPull() {
      hdPulling = false;
      if (hdAbortCtrl && typeof hdAbortCtrl.abort === 'function') {
        hdAbortCtrl.abort();
      }
      hdAbortCtrl = null;
    }

    function markHdLoading(sku) {
      var action = list.querySelector('[data-hd-import-action]');
      if (!action) {
        render(rows, lastQuery || sku);
        return;
      }
      action.classList.add('is-loading');
      action.setAttribute('aria-disabled', 'true');
      var nameEl = action.querySelector('.admin-search-select__option-name');
      var metaEl = action.querySelector('.admin-search-select__option-meta');
      if (nameEl) nameEl.textContent = 'Pulling SKU ' + sku + ' from Home Depot…';
      if (metaEl) metaEl.textContent = 'Creating catalog material';
    }

    function pullFromHomeDepot(sku) {
      if (!hdImportUrl || !sku || hdPulling) return;
      hdPulling = true;
      hdError = null;
      markHdLoading(sku);

      if (hdAbortCtrl && typeof hdAbortCtrl.abort === 'function') hdAbortCtrl.abort();
      hdAbortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;

      fetch(hdImportUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ query: sku }),
        signal: hdAbortCtrl ? hdAbortCtrl.signal : undefined
      })
        .then(function (r) {
          return r.json().then(function (json) {
            return { ok: r.ok, status: r.status, json: json || {} };
          }).catch(function () {
            return { ok: r.ok, status: r.status, json: {} };
          });
        })
        .then(function (res) {
          finishHdPull();
          var material = res.json && res.json.material;
          if (res.ok && material && material.id) {
            hdError = null;
            rememberImportedOption(root, material);
            if (!optionById(localOptions, material.id)) localOptions.push(material);
            choose(material);
            return;
          }
          hdError = {
            query: lastQuery || sku,
            message: (res.json && res.json.message) || "Couldn't find that SKU on Home Depot"
          };
          render(rows, lastQuery || sku);
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          finishHdPull();
          hdError = {
            query: lastQuery || sku,
            message: "Home Depot couldn't be reached, try again"
          };
          render(rows, lastQuery || sku);
        });
    }

    function fetchRemote(query, callback) {
      if (abortCtrl && typeof abortCtrl.abort === 'function') abortCtrl.abort();
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
          callback(data.slice(0, maxOptions));
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          callback([]);
        });
    }

    function search(query, immediate) {
      var q = String(query || '').trim();
      if (!immediate && q === lastQuery && (rows.length || list.querySelector('[data-hd-import-action]'))) {
        openList();
        return;
      }
      window.clearTimeout(debounceTimer);
      var run = function () {
        if (q !== lastQuery) {
          hdError = null;
          if (hdPulling) finishHdPull();
        }
        lastQuery = q;
        if (searchUrl) {
          fetchRemote(q, function (data) { render(data, q); });
          return;
        }
        var filtered = localOptions.filter(function (row) {
          return matches(row, q, searchFields);
        }).slice(0, maxOptions);
        render(filtered, q);
      };
      if (immediate) run();
      else debounceTimer = window.setTimeout(run, 180);
    }

    function choose(row, opts) {
      opts = opts || {};
      if (!row || !row.id) return;
      selected = row;
      setPostedId(row.id);
      input.value = nameFrom(row) || labelFrom(row);
      root.classList.add('is-selected');
      updateClear();
      closeList();
      if (!opts.silentFill) applyFill(root, row, !!opts.isInit);
      if (!opts.silent) dispatchChange(root, row.id, row);
    }

    function clearSelection(keepText, opts) {
      opts = opts || {};
      selected = null;
      setPostedId('');
      root.classList.remove('is-selected');
      if (!keepText) input.value = '';
      updateClear();
      if (!opts.silentFill) applyFill(root, null, false);
      if (!opts.silent) dispatchChange(root, '', null);
    }

    function onListMouseDown(e) { e.preventDefault(); }

    function onListClick(e) {
      var action = e.target && e.target.closest ? e.target.closest('[data-hd-import-action]') : null;
      if (action) {
        if (action.getAttribute('aria-disabled') === 'true') return;
        pullFromHomeDepot(action.getAttribute('data-hd-import-action'));
        return;
      }
      var opt = e.target && e.target.closest ? e.target.closest('[data-index]') : null;
      if (!opt) return;
      var idx = parseInt(opt.getAttribute('data-index'), 10);
      if (!isNaN(idx) && rows[idx]) choose(rows[idx]);
    }

    function onFocus() {
      search(input.value, lastQuery === null);
    }

    function onInput() {
      var typed = String(input.value || '').trim();
      if (selected && typed !== nameFrom(selected) && typed !== labelFrom(selected)) {
        clearSelection(true);
      }
      updateClear();
      search(input.value, false);
    }

    function onKeyDown(e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!open) { search(input.value, true); return; }
        setActive(activeIndex + 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!open) return;
        setActive(activeIndex - 1);
      } else if (e.key === 'Enter') {
        if (open && activeIndex >= 0) {
          var activeEl = list.querySelectorAll('[role="option"]')[activeIndex];
          if (activeEl && activeEl.getAttribute('data-hd-import-action')) {
            e.preventDefault();
            if (activeEl.getAttribute('aria-disabled') !== 'true') {
              pullFromHomeDepot(activeEl.getAttribute('data-hd-import-action'));
            }
            return;
          }
          if (rows[activeIndex]) {
            e.preventDefault();
            choose(rows[activeIndex]);
          }
        }
      } else if (e.key === 'Escape') {
        closeList();
      }
    }

    function onClearClick() {
      clearSelection(false);
      input.focus();
      search('', true);
    }

    function onDocClick(e) {
      if (hdPulling) return;
      if (root.contains(e.target) || list.contains(e.target)) return;
      closeList();
    }

    function onWinPlace() {
      if (open) placeList();
    }

    function syncTypedMatch() {
      if (hidden.value) return;
      var typed = String(input.value || '').trim().toLowerCase();
      if (!typed) return;
      var pool = rows.length ? rows : localOptions;
      for (var i = 0; i < pool.length; i++) {
        var row = pool[i];
        if (typed === nameFrom(row).toLowerCase() || typed === labelFrom(row).toLowerCase()) {
          choose(row);
          return;
        }
      }
    }

    function onFormSubmit() { syncTypedMatch(); }

    list.addEventListener('mousedown', onListMouseDown);
    list.addEventListener('click', onListClick);
    input.addEventListener('focus', onFocus);
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKeyDown);
    if (clearBtn) clearBtn.addEventListener('click', onClearClick);
    document.addEventListener('click', onDocClick);
    if (listParent === 'body') {
      window.addEventListener('resize', onWinPlace);
      window.addEventListener('scroll', onWinPlace, true);
    }

    var form = root.closest('form');
    if (form) form.addEventListener('submit', onFormSubmit, true);

    var initial = parseJson(root.getAttribute('data-initial'), null);
    if ((!initial || !initial.id) && hidden.value) {
      initial = optionById(localOptions, hidden.value);
    }
    if (initial && initial.id) {
      initial.id = String(initial.id);
      choose(initial, { silent: true, isInit: true });
    }
    updateClear();

    instances.set(root, {
      choose: choose,
      clear: function () { clearSelection(false); },
      getValue: function () { return hidden.value; },
      getOption: function () { return selected; },
      destroy: function () {
        finishHdPull();
        list.removeEventListener('mousedown', onListMouseDown);
        list.removeEventListener('click', onListClick);
        input.removeEventListener('focus', onFocus);
        input.removeEventListener('input', onInput);
        input.removeEventListener('keydown', onKeyDown);
        if (clearBtn) clearBtn.removeEventListener('click', onClearClick);
        document.removeEventListener('click', onDocClick);
        if (listParent === 'body') {
          window.removeEventListener('resize', onWinPlace);
          window.removeEventListener('scroll', onWinPlace, true);
        }
        restoreList();
        if (form) form.removeEventListener('submit', onFormSubmit, true);
        root.removeAttribute('data-admin-search-select-init');
        instances.delete(root);
      }
    });
  }

  function initAll(scope) {
    (scope || document).querySelectorAll('[data-admin-search-select]').forEach(init);
  }

  function destroy(root) {
    var inst = root && instances.get(root);
    if (inst) inst.destroy();
  }

  function getValue(root) {
    var inst = root && instances.get(root);
    if (inst) return inst.getValue();
    var hidden = root ? root.querySelector('.admin-search-select__value') : null;
    return hidden ? hidden.value : '';
  }

  window.AdminSearchSelect = {
    init: init,
    initAll: initAll,
    destroy: destroy,
    getValue: getValue,
    getOption: function (root) {
      var inst = root && instances.get(root);
      return inst ? inst.getOption() : null;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }
})(window, document);

/**
 * Shared admin toast. Used by Ctrl+S and normal Save clicks.
 *
 * Types:
 *   success — green "Saved" after the server confirms. Auto-hides ~2.5s.
 *   error   — red, sticky until closed.
 *   warning — yellow, sticky until closed (validation that is not blocking).
 *   validation — red (or warning), sticky; lists fields with jump links.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminToast = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var SUCCESS_MS = 2500;
  var AVOID_PAD = 14;
  var PLACEMENTS = [
    'bottom-center',
    'top-center',
    'bottom-left',
    'bottom-right',
    'top-left',
    'top-right',
  ];
  var AVOID_SEL = [
    '[data-hotkey-save]',
    '[data-ra-save-room]',
    '[data-ra-zoom-in]',
    '[data-ra-zoom-out]',
    '[data-ra-zoom-100]',
    '[data-ra-zoom-reset]',
    '.util-ra-tool-group',
    '.util-ra-full-header__save',
    '.br-toolbar-actions',
    '.admin-toolbar-actions',
  ].join(',');

  var hideTimer = null;
  var host = null;
  var card = null;
  var lastOptions = null;

  function envDoc(env) {
    return (env && env.document) || (typeof document !== 'undefined' ? document : null);
  }

  function envWin(env) {
    return (env && env.window) || (typeof window !== 'undefined' ? window : null);
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function ensureHost(env) {
    var doc = envDoc(env);
    if (!doc) return null;
    host = doc.querySelector('[data-admin-toast-host]');
    if (!host) {
      host = doc.createElement('div');
      host.className = 'admin-toast';
      host.setAttribute('data-admin-toast-host', '');
      host.hidden = true;
      (doc.body || doc.documentElement).appendChild(host);
    }
    return host;
  }

  function clearTimer() {
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
  }

  function rectsOverlap(a, b, pad) {
    pad = pad == null ? AVOID_PAD : pad;
    if (!a || !b) return false;
    return !(
      a.right < b.left - pad ||
      a.left > b.right + pad ||
      a.bottom < b.top - pad ||
      a.top > b.bottom + pad
    );
  }

  function visibleRect(el, win) {
    if (!el || !el.getBoundingClientRect) return null;
    if (el.hidden || el.getAttribute('aria-hidden') === 'true') return null;
    var r = el.getBoundingClientRect();
    if (!r || r.width < 2 || r.height < 2) return null;
    var vw = (win && win.innerWidth) || 0;
    var vh = (win && win.innerHeight) || 0;
    if (r.bottom < 0 || r.right < 0 || r.top > vh || r.left > vw) return null;
    return r;
  }

  function avoidRects(doc, win) {
    var rects = [];
    var nodes = doc.querySelectorAll(AVOID_SEL);
    for (var i = 0; i < nodes.length; i++) {
      var r = visibleRect(nodes[i], win);
      if (r) rects.push(r);
    }
    var active = doc.activeElement;
    if (active && active !== doc.body && active !== doc.documentElement) {
      var ar = visibleRect(active, win);
      if (ar) rects.push(ar);
    }
    return rects;
  }

  function applyPlacement(node, name) {
    node.setAttribute('data-placement', name);
    node.className = 'admin-toast admin-toast--' + name;
  }

  function placementOverlaps(node, name, rects) {
    applyPlacement(node, name);
    if (!node.getBoundingClientRect) return false;
    var toastRect = node.getBoundingClientRect();
    for (var i = 0; i < rects.length; i++) {
      if (rectsOverlap(toastRect, rects[i])) return true;
    }
    return false;
  }

  function pickPlacement(env) {
    var doc = envDoc(env);
    var win = envWin(env);
    var node = ensureHost(env);
    if (!node || !doc) return 'bottom-center';
    var rects = avoidRects(doc, win);
    for (var i = 0; i < PLACEMENTS.length; i++) {
      if (!placementOverlaps(node, PLACEMENTS[i], rects)) {
        return PLACEMENTS[i];
      }
    }
    applyPlacement(node, 'bottom-center');
    return 'bottom-center';
  }

  function iconFor(type) {
    if (type === 'success') return 'fa-check-circle';
    if (type === 'warning') return 'fa-exclamation-triangle';
    return 'fa-exclamation-circle';
  }

  function renderFields(fields) {
    if (!fields || !fields.length) return '';
    var html = '<ul class="admin-toast__fields">';
    fields.forEach(function (field, idx) {
      var label = escapeHtml(field.label || field.name || 'Field');
      var msg = escapeHtml(field.message || 'is invalid');
      html += '<li class="admin-toast__field">';
      html += '<a href="#" class="admin-toast__field-link" data-admin-toast-field="' + idx + '">' + label + '</a>';
      html += ' <span class="admin-toast__field-msg">' + msg + '</span>';
      html += '</li>';
    });
    html += '</ul>';
    return html;
  }

  function highlightFields(fields, on) {
    if (!fields) return;
    fields.forEach(function (field) {
      var el = field.el;
      if (!el || !el.classList) return;
      if (on) {
        el.classList.add('admin-field-invalid');
        el.setAttribute('aria-invalid', 'true');
      } else {
        el.classList.remove('admin-field-invalid');
        el.removeAttribute('aria-invalid');
      }
    });
  }

  function focusField(field, env) {
    var el = field && field.el;
    if (!el) return;
    try {
      if (el.scrollIntoView) {
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
      if (el.focus) el.focus();
    } catch (err) { /* ignore */ }
    place(env);
  }

  function bindFieldLinks(env) {
    if (!host) return;
    var links = host.querySelectorAll('[data-admin-toast-field]');
    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('click', function (e) {
        e.preventDefault();
        var idx = parseInt(this.getAttribute('data-admin-toast-field'), 10);
        var field = lastOptions && lastOptions.fields ? lastOptions.fields[idx] : null;
        if (field) focusField(field, env);
      });
    }
  }

  function close(env) {
    clearTimer();
    var node = host || (envDoc(env) && envDoc(env).querySelector('[data-admin-toast-host]'));
    if (node) {
      node.hidden = true;
      node.innerHTML = '';
    }
    if (lastOptions && lastOptions.fields) {
      highlightFields(lastOptions.fields, false);
    }
    lastOptions = null;
  }

  function show(options, env) {
    options = options || {};
    env = env || {};
    var doc = envDoc(env);
    if (!doc) return null;
    ensureHost(env);
    clearTimer();

    if (lastOptions && lastOptions.fields) {
      highlightFields(lastOptions.fields, false);
    }

    var type = options.type || 'success';
    if (type === 'validation') {
      type = options.warning ? 'warning' : 'error';
    }
    var sticky = options.sticky;
    if (sticky == null) {
      sticky = type !== 'success';
    }
    var title = options.title || (type === 'success' ? 'Saved' : 'Could not save');
    var live = type === 'success' ? 'status' : 'alert';

    lastOptions = options;
    host.hidden = false;
    host.setAttribute('role', live);
    host.setAttribute('aria-live', type === 'success' ? 'polite' : 'assertive');
    host.innerHTML =
      '<div class="admin-toast__card admin-toast--' + type + '">' +
        '<i class="fa ' + iconFor(type) + ' admin-toast__icon" aria-hidden="true"></i>' +
        '<div class="admin-toast__body">' +
          '<div class="admin-toast__title">' + escapeHtml(title) + '</div>' +
          (options.message ? '<div class="admin-toast__message">' + escapeHtml(options.message) + '</div>' : '') +
          renderFields(options.fields) +
        '</div>' +
        '<button type="button" class="admin-toast__close" data-admin-toast-close aria-label="Dismiss">' +
          '<i class="fa fa-times" aria-hidden="true"></i>' +
        '</button>' +
      '</div>';

    var closeBtn = host.querySelector('[data-admin-toast-close]');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        close(env);
      });
    }
    bindFieldLinks(env);
    if (options.fields) highlightFields(options.fields, true);
    pickPlacement(env);

    if (!sticky) {
      hideTimer = setTimeout(function () {
        close(env);
      }, options.duration || SUCCESS_MS);
    }
    return host;
  }

  function saved(env) {
    return show({ type: 'success', title: 'Saved', sticky: false, duration: SUCCESS_MS }, env);
  }

  function error(message, env) {
    return show({
      type: 'error',
      title: 'Could not save',
      message: message || 'The server could not save your changes.',
      sticky: true,
    }, env);
  }

  function validation(fields, opts, env) {
    opts = opts || {};
    var warning = !!opts.warning;
    return show({
      type: warning ? 'warning' : 'error',
      title: warning ? 'Please review' : 'Please fix these fields',
      fields: fields || [],
      warning: warning,
      sticky: true,
    }, env);
  }

  function place(env) {
    if (!host || host.hidden) return null;
    return pickPlacement(env);
  }

  function overlapsAvoided(env) {
    var doc = envDoc(env);
    var win = envWin(env);
    var node = ensureHost(env);
    if (!node || node.hidden || !doc) return false;
    var toastRect = node.getBoundingClientRect();
    var rects = avoidRects(doc, win);
    for (var i = 0; i < rects.length; i++) {
      if (rectsOverlap(toastRect, rects[i])) return true;
    }
    return false;
  }

  return {
    SUCCESS_MS: SUCCESS_MS,
    PLACEMENTS: PLACEMENTS,
    show: show,
    saved: saved,
    error: error,
    validation: validation,
    close: close,
    place: place,
    overlapsAvoided: overlapsAvoided,
    rectsOverlap: rectsOverlap,
    escapeHtml: escapeHtml,
  };
});

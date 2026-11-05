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
  var PRIORITY_AVOID_SEL = [
    '.br-sticky-total',
    '.br-step-nav',
    '#br-next',
    '#mw-next',
    '#sh-next',
    '#tm-next',
    '[data-hotkey-save]',
    '.br-toolbar-actions',
    '.admin-toolbar-actions',
  ].join(',');
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
    PRIORITY_AVOID_SEL,
  ].join(',');

  var hideTimer = null;
  var host = null;
  var lastOptions = null;
  var viewportBound = false;
  var fieldWatchBound = false;
  var keyBound = false;
  var lastEnv = null;

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

  function isPriorityAvoid(el) {
    if (!el || !el.matches) {
      var id = el && el.id;
      var cls = el && el.className && String(el.className);
      return id === 'br-next' || id === 'mw-next' || id === 'sh-next' || id === 'tm-next'
        || (el.getAttribute && el.getAttribute('data-hotkey-save') != null)
        || (cls && (
          cls.indexOf('br-sticky-total') !== -1
          || cls.indexOf('br-step-nav') !== -1
          || cls.indexOf('br-toolbar-actions') !== -1
          || cls.indexOf('admin-toolbar-actions') !== -1
        ));
    }
    try { return el.matches(PRIORITY_AVOID_SEL); } catch (err) { return false; }
  }

  function avoidRects(doc, win) {
    var rects = [];
    var nodes = doc.querySelectorAll(AVOID_SEL);
    for (var i = 0; i < nodes.length; i++) {
      var r = visibleRect(nodes[i], win);
      if (r) rects.push({ rect: r, priority: isPriorityAvoid(nodes[i]) });
    }
    var active = doc.activeElement;
    if (active && active !== doc.body && active !== doc.documentElement) {
      var ar = visibleRect(active, win);
      if (ar) rects.push({ rect: ar, priority: false });
    }
    return rects;
  }

  function overlapArea(a, b, pad) {
    pad = pad == null ? AVOID_PAD : pad;
    var left = Math.max(a.left, b.left - pad);
    var right = Math.min(a.right, b.right + pad);
    var top = Math.max(a.top, b.top - pad);
    var bottom = Math.min(a.bottom, b.bottom + pad);
    if (right <= left || bottom <= top) return 0;
    return (right - left) * (bottom - top);
  }

  function applyPlacement(node, name) {
    node.setAttribute('data-placement', name);
    node.className = 'admin-toast admin-toast--' + name;
    if (node.style) {
      node.style.top = '';
      node.style.bottom = '';
      node.style.left = '';
      node.style.right = '';
      node.style.transform = '';
    }
  }

  function scorePlacement(node, name, rects) {
    applyPlacement(node, name);
    if (!node.getBoundingClientRect) return { score: 0, priority: 0 };
    var toastRect = node.getBoundingClientRect();
    var priority = 0;
    var other = 0;
    for (var i = 0; i < rects.length; i++) {
      var item = rects[i];
      var rect = item.rect || item;
      var area = overlapArea(toastRect, rect);
      if (!area) continue;
      if (item.priority) priority += area;
      else other += area;
    }
    return { score: priority * 1000 + other, priority: priority };
  }

  function pinAboveSticky(node, doc, win) {
    var sticky = doc.querySelector('.br-sticky-total, .br-step-nav');
    var next = doc.getElementById('br-next') || doc.getElementById('mw-next') || doc.getElementById('sh-next');
    var bar = sticky && visibleRect(sticky, win);
    if (!bar && next) bar = visibleRect(next, win);
    if (!bar || !node.style) return false;
    applyPlacement(node, 'bottom-center');
    var gap = AVOID_PAD + 8;
    var vh = (win && win.innerHeight) || 0;
    var vw = (win && win.innerWidth) || 0;
    var bottom = Math.max(8, vh - bar.top + gap);
    node.style.bottom = bottom + 'px';
    node.style.top = 'auto';
    if (vw && vw <= 700) {
      node.style.left = '0.75rem';
      node.style.right = '0.75rem';
      node.style.transform = 'none';
    } else {
      node.style.transform = 'translateX(-50%)';
    }
    return true;
  }

  function pickPlacement(env) {
    var doc = envDoc(env);
    var win = envWin(env);
    var node = ensureHost(env);
    if (!node || !doc) return 'bottom-center';
    var rects = avoidRects(doc, win);
    var best = null;
    for (var i = 0; i < PLACEMENTS.length; i++) {
      var result = scorePlacement(node, PLACEMENTS[i], rects);
      if (result.priority === 0 && result.score === 0) {
        applyPlacement(node, PLACEMENTS[i]);
        return PLACEMENTS[i];
      }
      if (!best || result.score < best.score) {
        best = { name: PLACEMENTS[i], score: result.score, priority: result.priority };
      }
    }
    if (best && best.priority > 0) {
      if (pinAboveSticky(node, doc, win)) return 'bottom-center';
    }
    applyPlacement(node, best ? best.name : 'bottom-center');
    return best ? best.name : 'bottom-center';
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
      var target = el;
      if ((el.tagName || '').toLowerCase() === 'input') {
        var t = (el.type || '').toLowerCase();
        if (t === 'radio' || t === 'checkbox') {
          var fieldset = el.closest ? el.closest('fieldset') : null;
          if (fieldset && fieldset.classList) target = fieldset;
        }
      }
      if (on) {
        target.classList.add('admin-field-invalid');
        target.setAttribute('aria-invalid', 'true');
        if (target !== el) {
          el.classList.add('admin-field-invalid');
          el.setAttribute('aria-invalid', 'true');
        }
      } else {
        target.classList.remove('admin-field-invalid');
        target.removeAttribute('aria-invalid');
        if (target !== el) {
          el.classList.remove('admin-field-invalid');
          el.removeAttribute('aria-invalid');
        }
      }
    });
  }

  function revealWizardFor(el) {
    if (!el || !el.closest) return;
    var step = el.closest('[data-br-step], [data-mw-step], [data-sh-step], [data-tm-step], .br-acc');
    if (step) {
      step.hidden = false;
      if (step.classList) step.classList.add('is-active');
      try { step.setAttribute('open', ''); } catch (err) { /* ignore */ }
      var key = step.getAttribute('data-br-step')
        || step.getAttribute('data-mw-step')
        || step.getAttribute('data-sh-step')
        || step.getAttribute('data-tm-step');
      if (key) {
        var doc = el.ownerDocument || (typeof document !== 'undefined' ? document : null);
        var nav = doc && doc.querySelector
          ? doc.querySelector('[data-br-goto="' + key + '"], [data-mw-goto="' + key + '"], [data-sh-goto="' + key + '"]')
          : null;
        if (nav && nav.click) nav.click();
      }
    }
    var hiddenParent = el.closest('[hidden]');
    if (hiddenParent) hiddenParent.hidden = false;
  }

  function focusField(field, env) {
    var el = field && field.el;
    if (!el) return;
    revealWizardFor(el);
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

  function onViewportChange() {
    if (!host || host.hidden) return;
    pickPlacement(lastEnv || {});
  }

  function bindViewport(env) {
    var win = envWin(env);
    if (!win || viewportBound || !win.addEventListener) return;
    viewportBound = true;
    win.addEventListener('scroll', onViewportChange, true);
    win.addEventListener('resize', onViewportChange);
  }

  function onFieldEdit(e) {
    if (!lastOptions || !lastOptions.fields || !lastOptions.fields.length) return;
    var target = e && e.target;
    if (!target) return;
    var hit = false;
    lastOptions.fields.forEach(function (field) {
      if (!field.el) return;
      if (field.el === target || (field.el.contains && field.el.contains(target))) {
        field.edited = true;
        hit = true;
      }
    });
    if (!hit) return;
    var remaining = lastOptions.fields.filter(function (field) {
      if (!field.el) return false;
      if (field.el.validity && !field.el.validity.valid) return true;
      if (typeof field.el.checkValidity === 'function' && !field.el.checkValidity()) return true;
      return !field.edited;
    });
    if (remaining.length === 0) close(lastEnv || {});
  }

  function bindFieldWatch(env) {
    var doc = envDoc(env);
    if (!doc || fieldWatchBound || !doc.addEventListener) return;
    fieldWatchBound = true;
    doc.addEventListener('input', onFieldEdit, true);
    doc.addEventListener('change', onFieldEdit, true);
  }

  function onDocKeydown(e) {
    if (!e) return;
    if (e.key !== 'Escape' && e.code !== 'Escape') return;
    if (!host || host.hidden) return;
    close(lastEnv || {});
  }

  function bindEsc(env) {
    var doc = envDoc(env);
    if (!doc || keyBound || !doc.addEventListener) return;
    keyBound = true;
    doc.addEventListener('keydown', onDocKeydown, true);
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
    lastEnv = env;
    ensureHost(env);
    bindViewport(env);
    bindFieldWatch(env);
    bindEsc(env);
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
    if (options.fields && options.fields[0] && options.fields[0].el) {
      focusField(options.fields[0], env);
    }

    if (!sticky) {
      hideTimer = setTimeout(function () {
        close(env);
      }, options.duration || SUCCESS_MS);
    }
    return host;
  }

  function saved(env, message) {
    var text = message && String(message).trim();
    var plain = !text || /^(saved\.?|changes saved\.?)$/i.test(text);
    return show({
      type: 'success',
      title: plain ? 'Saved' : text,
      sticky: false,
      duration: SUCCESS_MS,
    }, env);
  }

  function error(message, env, title) {
    return show({
      type: 'error',
      title: title || 'Could not save',
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
      var rect = rects[i].rect || rects[i];
      if (rectsOverlap(toastRect, rect)) return true;
    }
    return false;
  }

  return {
    SUCCESS_MS: SUCCESS_MS,
    PLACEMENTS: PLACEMENTS,
    AVOID_SEL: AVOID_SEL,
    PRIORITY_AVOID_SEL: PRIORITY_AVOID_SEL,
    AVOID_PAD: AVOID_PAD,
    show: show,
    saved: saved,
    error: error,
    validation: validation,
    close: close,
    place: place,
    overlapsAvoided: overlapsAvoided,
    rectsOverlap: rectsOverlap,
    escapeHtml: escapeHtml,
    revealWizardFor: revealWizardFor,
    bindFieldWatch: bindFieldWatch,
  };
});

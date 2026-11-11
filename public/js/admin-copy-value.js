/**
 * Copy a plain value from [data-copy-value] buttons.
 * Uses navigator.clipboard in secure contexts, with execCommand fallback.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminCopyValue = api;
  if (typeof document !== 'undefined') {
    api.bind(document);
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var COPIED_MS = 1600;
  var timers = new WeakMap();

  function envDoc(env) {
    return (env && env.document) || (typeof document !== 'undefined' ? document : null);
  }

  function envWin(env) {
    return (env && env.window) || (typeof window !== 'undefined' ? window : null);
  }

  function envNav(env) {
    if (env && env.navigator) return env.navigator;
    var win = envWin(env);
    if (win && win.navigator) return win.navigator;
    return typeof navigator !== 'undefined' ? navigator : null;
  }

  function fallbackCopy(text, env) {
    var doc = envDoc(env);
    return new Promise(function (resolve, reject) {
      if (!doc || !doc.createElement) {
        reject(new Error('copy unavailable'));
        return;
      }
      var ta = doc.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.setAttribute('aria-hidden', 'true');
      ta.style.position = 'fixed';
      ta.style.top = '0';
      ta.style.left = '0';
      ta.style.opacity = '0';
      (doc.body || doc.documentElement).appendChild(ta);
      ta.focus();
      ta.select();
      try {
        var ok = typeof doc.execCommand === 'function' && doc.execCommand('copy');
        if (ta.parentNode) ta.parentNode.removeChild(ta);
        if (ok) resolve();
        else reject(new Error('copy failed'));
      } catch (err) {
        if (ta.parentNode) ta.parentNode.removeChild(ta);
        reject(err);
      }
    });
  }

  function copyText(text, env) {
    var nav = envNav(env);
    var win = envWin(env);
    if (nav && nav.clipboard && typeof nav.clipboard.writeText === 'function' && win && win.isSecureContext) {
      return nav.clipboard.writeText(text).catch(function () {
        return fallbackCopy(text, env);
      });
    }
    return fallbackCopy(text, env);
  }

  function iconEl(btn) {
    return btn.querySelector ? btn.querySelector('i') : null;
  }

  function markCopied(btn) {
    if (!btn) return;
    var icon = iconEl(btn);
    var prevLabel = btn.getAttribute('data-copy-prev-label');
    if (prevLabel == null) {
      btn.setAttribute('data-copy-prev-label', btn.getAttribute('aria-label') || 'Copy');
    }
    if (icon && !icon.getAttribute('data-copy-prev-class')) {
      icon.setAttribute('data-copy-prev-class', icon.className || 'fa fa-copy');
    }
    btn.classList.add('is-copied');
    btn.setAttribute('aria-label', 'Copied');
    btn.setAttribute('title', 'Copied');
    if (icon) icon.className = 'fa fa-check';

    var prev = timers.get(btn);
    if (prev) clearTimeout(prev);
    var t = setTimeout(function () {
      btn.classList.remove('is-copied');
      var restore = btn.getAttribute('data-copy-prev-label') || 'Copy';
      btn.setAttribute('aria-label', restore);
      btn.setAttribute('title', restore);
      if (icon) {
        icon.className = icon.getAttribute('data-copy-prev-class') || 'fa fa-copy';
      }
      timers.delete(btn);
    }, COPIED_MS);
    timers.set(btn, t);
  }

  function showCopiedToast() {
    if (typeof window === 'undefined' || !window.AdminToast || typeof window.AdminToast.show !== 'function') {
      return;
    }
    window.AdminToast.show({
      type: 'success',
      title: 'Copied',
      sticky: false,
      duration: COPIED_MS,
    });
  }

  function onClick(ev, env) {
    var target = ev.target;
    if (!target || typeof target.closest !== 'function') return false;
    var btn = target.closest('[data-copy-value]');
    if (!btn) return false;
    var text = btn.getAttribute('data-copy-value') || '';
    if (!text) return false;
    if (ev.preventDefault) ev.preventDefault();
    copyText(text, env).then(function () {
      markCopied(btn);
      showCopiedToast();
    }).catch(function () {
      // Leave the control as-is when the browser blocks clipboard access.
    });
    return true;
  }

  function bind(doc) {
    if (!doc || typeof doc.addEventListener !== 'function') return;
    if (doc.documentElement && doc.documentElement.getAttribute('data-admin-copy-bound') === '1') {
      return;
    }
    if (doc.documentElement) {
      doc.documentElement.setAttribute('data-admin-copy-bound', '1');
    }
    doc.addEventListener('click', function (ev) {
      onClick(ev);
    });
  }

  return {
    COPIED_MS: COPIED_MS,
    copyText: copyText,
    fallbackCopy: fallbackCopy,
    markCopied: markCopied,
    onClick: onClick,
    bind: bind,
  };
});

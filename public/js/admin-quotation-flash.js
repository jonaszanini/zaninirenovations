/**
 * Quotation editor save flash: compact breadcrumb status + toast auto-hide.
 * Toast markup lives in admin.partials.quotation_flash_toast; AdminToast
 * renders into that host on AJAX and session flash.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminQuotationFlash = api;
  if (root.document) {
    if (root.document.readyState === 'loading') {
      root.document.addEventListener('DOMContentLoaded', function () { api.bind(root); });
    } else {
      api.bind(root);
    }
  }
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
  function compact(message, type) {
    var text = String(message == null ? '' : message).trim();
    type = type || 'success';
    if (!text) return type === 'success' ? 'Saved' : 'Could not save';
    if (type !== 'success') return text;
    var lower = text.toLowerCase();
    if (lower.indexOf('saved as a draft') !== -1 || lower.indexOf('saved as draft') !== -1) {
      return 'Saved as a draft';
    }
    if (
      lower.indexOf('linked to this project') !== -1
      || lower.indexOf('saved and linked') !== -1
      || lower.indexOf('published and linked') !== -1
      || lower.indexOf('updated and linked') !== -1
    ) {
      return 'Saved and linked to this project';
    }
    if (/\b(updated|saved)\b/i.test(text)) return 'Saved';
    return text.replace(/\.+$/, '');
  }

  function iconFor(type) {
    if (type === 'warning') return 'fa-exclamation-triangle';
    if (type === 'error') return 'fa-exclamation-circle';
    return 'fa-check-circle';
  }

  function envDoc(env) {
    return (env && env.document) || (root.document || null);
  }

  function sync(type, message, env) {
    var doc = envDoc(env);
    if (!doc || !doc.querySelector) return null;
    var status = doc.querySelector('[data-qh-flash-status]');
    if (!status) return null;
    type = type || 'success';
    var label = compact(message, type);
    var text = status.querySelector('[data-qh-flash-status-text]');
    var icon = status.querySelector('[data-qh-flash-status-icon]');
    status.hidden = !label;
    status.className = 'qh-flash-status qh-flash-status--' + type;
    status.setAttribute('data-qh-flash-status-type', type);
    if (text) text.textContent = label;
    if (icon) icon.className = 'fa ' + iconFor(type);
    return status;
  }

  function cardType(host) {
    if (!host || !host.querySelector) return 'success';
    var card = host.querySelector('.admin-toast__card');
    var cls = card && card.className ? String(card.className) : '';
    if (cls.indexOf('admin-toast--error') !== -1) return 'error';
    if (cls.indexOf('admin-toast--warning') !== -1) return 'warning';
    return 'success';
  }

  function adoptVisibleToast(env) {
    var doc = envDoc(env);
    if (!doc) return;
    var host = doc.querySelector('[data-qh-flash-toast]');
    if (!host || host.hidden) return;
    var titleEl = host.querySelector('.admin-toast__title');
    var title = titleEl ? String(titleEl.textContent || '').trim() : '';
    if (!title) return;
    var type = cardType(host);
    sync(type, title, env);
    var toast = env && env.toast ? env.toast : root.AdminToast;
    if (toast && typeof toast.show === 'function') {
      toast.show({ type: type, title: title, sticky: false }, env || {});
    }
  }

  function hookToast() {
    var toast = root.AdminToast;
    if (!toast || toast.__qhFlashHooked) return;
    toast.__qhFlashHooked = true;
    var origShow = toast.show;
    if (typeof origShow !== 'function') return;
    toast.show = function (options, env) {
      options = options || {};
      var host = origShow.call(toast, options, env);
      var type = options.type || 'success';
      if (type === 'validation') type = options.warning ? 'warning' : 'error';
      var message = options.title || options.message || '';
      if (envDoc(env) && envDoc(env).querySelector('[data-qh-flash-status]')) {
        sync(type, message, env);
      }
      return host;
    };
  }

  function bind(env) {
    env = env || {};
    hookToast();
    adoptVisibleToast(env);
    return true;
  }

  return {
    compact: compact,
    iconFor: iconFor,
    sync: sync,
    bind: bind,
    hookToast: hookToast,
    adoptVisibleToast: adoptVisibleToast,
  };
});

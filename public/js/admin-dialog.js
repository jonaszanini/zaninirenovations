/**
 * Admin dialog — styled replacements for window.alert / window.confirm.
 * Forms: use data-confirm="Message" (optional data-confirm-title, data-confirm-danger).
 * Legacy onsubmit="return confirm('…')" is auto-migrated on DOMContentLoaded.
 */
(function (global) {
  'use strict';

  var OPEN_CLASS = 'admin-dialog-open';
  var root;
  var titleEl;
  var messageEl;
  var iconEl;
  var actionsEl;
  var panelEl;
  var resolveCb;
  var lastFocus;

  function qs(sel) {
    return root ? root.querySelector(sel) : null;
  }

  function ensureDom() {
    if (root) return !!root;
    root = document.getElementById('admin-dialog');
    if (!root) return false;
    titleEl = qs('#admin-dialog-title');
    messageEl = qs('#admin-dialog-message');
    iconEl = qs('.admin-dialog-icon');
    actionsEl = qs('.admin-dialog-actions');
    panelEl = qs('.admin-dialog-panel');

    root.querySelectorAll('[data-admin-dialog-dismiss]').forEach(function (el) {
      el.addEventListener('click', function () {
        close(false);
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root && !root.hidden) {
        e.preventDefault();
        close(false);
      }
    });

    return true;
  }

  function copyFallback(text, options) {
    options = options || {};
    return new Promise(function (resolve) {
      if (!ensureDom()) {
        resolve(false);
        return;
      }

      if (resolveCb) close(false);

      var title = options.title || 'Copy text';
      var message = options.message || 'Select the text below and copy it (Ctrl+C or Cmd+C).';

      if (titleEl) titleEl.textContent = title;
      if (messageEl) {
        messageEl.innerHTML = '';
        var hint = document.createElement('p');
        hint.className = 'admin-dialog-message';
        hint.textContent = message;
        messageEl.appendChild(hint);

        var field = document.createElement('textarea');
        field.className = 'admin-input admin-dialog-copy-field';
        field.readOnly = true;
        field.rows = 6;
        field.value = text;
        messageEl.appendChild(field);

        setTimeout(function () {
          field.focus();
          field.select();
        }, 0);
      }

      if (iconEl) {
        iconEl.className = 'fa admin-dialog-icon fa-clipboard';
        iconEl.parentElement.className = 'admin-dialog-icon-wrap admin-dialog-icon-wrap--info';
      }

      if (panelEl) panelEl.classList.remove('admin-dialog-panel--danger');

      if (actionsEl) {
        actionsEl.className = 'admin-dialog-actions';
        actionsEl.innerHTML = '';
        actionsEl.appendChild(makeBtn('Close', 'admin-btn admin-btn--primary admin-btn--sm', function () {
          close(true);
        }));
        resolveCb = resolve;
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add(OPEN_CLASS);
      }
    });
  }

  function close(result) {
    if (!root || root.hidden) return;
    root.hidden = true;
    root.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove(OPEN_CLASS);
    if (messageEl) messageEl.textContent = '';
    var cb = resolveCb;
    resolveCb = null;
    if (lastFocus && typeof lastFocus.focus === 'function') {
      try { lastFocus.focus(); } catch (err) { /* ignore */ }
    }
    if (cb) cb(result);
  }

  function makeBtn(label, className, onClick) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = className;
    btn.textContent = label;
    btn.addEventListener('click', onClick);
    return btn;
  }

  function show(options) {
    return new Promise(function (resolve) {
      if (!ensureDom()) {
        resolve(options.type === 'alert' ? undefined : false);
        return;
      }

      if (resolveCb) {
        close(false);
      }

      var type = options.type || 'confirm';
      var title = options.title || (type === 'alert' ? 'Notice' : (type === 'choose' ? 'Choose' : 'Confirm'));
      var message = options.message != null ? String(options.message) : '';
      var confirmLabel = options.confirmLabel || (type === 'alert' ? 'OK' : 'Continue');
      var cancelLabel = options.cancelLabel || 'Cancel';
      var danger = !!options.danger;
      var choices = options.choices || [];

      lastFocus = document.activeElement;

      if (titleEl) titleEl.textContent = title;
      if (messageEl) messageEl.textContent = message;

      if (iconEl) {
        iconEl.className = 'fa admin-dialog-icon ' + (
          type === 'alert'
            ? 'fa-info-circle'
            : (type === 'choose'
              ? 'fa-print'
              : (danger ? 'fa-exclamation-triangle' : 'fa-question-circle'))
        );
        iconEl.parentElement.className = 'admin-dialog-icon-wrap admin-dialog-icon-wrap--' + (
          type === 'alert' ? 'info' : (danger && type !== 'choose' ? 'danger' : 'confirm')
        );
      }

      if (panelEl) {
        panelEl.classList.toggle('admin-dialog-panel--danger', danger && type !== 'alert' && type !== 'choose');
      }

      if (actionsEl) {
        actionsEl.innerHTML = '';
        actionsEl.className = 'admin-dialog-actions' + (type === 'choose' ? ' admin-dialog-actions--stack' : '');

        if (type === 'choose') {
          var firstChoiceBtn = null;
          choices.forEach(function (choice, i) {
            var value = choice && choice.value != null ? choice.value : '';
            var label = (choice && choice.label) ? String(choice.label) : String(value);
            var cls = i === 0
              ? 'admin-btn admin-btn--primary'
              : 'admin-btn admin-btn--ghost';
            var btn = makeBtn(label, cls, function () {
              close(value);
            });
            if (!firstChoiceBtn) firstChoiceBtn = btn;
            actionsEl.appendChild(btn);
          });
          actionsEl.appendChild(makeBtn(cancelLabel, 'admin-btn admin-btn--ghost', function () {
            close(null);
          }));
          resolveCb = resolve;
          root.hidden = false;
          root.setAttribute('aria-hidden', 'false');
          document.documentElement.classList.add(OPEN_CLASS);
          setTimeout(function () {
            if (firstChoiceBtn) firstChoiceBtn.focus();
          }, 0);
          return;
        }

        if (type !== 'alert') {
          actionsEl.appendChild(makeBtn(cancelLabel, 'admin-btn admin-btn--ghost admin-btn--sm', function () {
            close(false);
          }));
        }
        var primaryClass = danger && type !== 'alert'
          ? 'admin-btn admin-btn--danger admin-btn--sm'
          : 'admin-btn admin-btn--primary admin-btn--sm';
        var primaryBtn = makeBtn(confirmLabel, primaryClass, function () {
          close(true);
        });
        actionsEl.appendChild(primaryBtn);
        resolveCb = resolve;

        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add(OPEN_CLASS);
        setTimeout(function () { primaryBtn.focus(); }, 0);
      }
    });
  }

  function alert(message, options) {
    options = options || {};
    return show({
      type: 'alert',
      message: message,
      title: options.title || 'Notice',
      confirmLabel: options.confirmLabel || 'OK',
    });
  }

  function confirm(message, options) {
    options = options || {};
    return show({
      type: 'confirm',
      message: message,
      title: options.title || 'Confirm',
      confirmLabel: options.confirmLabel || 'Continue',
      cancelLabel: options.cancelLabel || 'Cancel',
      danger: !!options.danger,
    });
  }

  /**
   * Several equally valid actions. Resolves to the chosen `value`, or null if cancelled.
   * options.choices: [{ value, label }, ...]
   */
  function choose(message, options) {
    options = options || {};
    return show({
      type: 'choose',
      message: message,
      title: options.title || 'Choose',
      choices: options.choices || [],
      cancelLabel: options.cancelLabel || 'Cancel',
    });
  }

  function migrateLegacyConfirmForms() {
    document.querySelectorAll('form[onsubmit]').forEach(function (form) {
      var attr = form.getAttribute('onsubmit');
      if (!attr || !/confirm\s*\(/.test(attr)) return;
      if (form.hasAttribute('data-confirm')) {
        form.removeAttribute('onsubmit');
        return;
      }
      var m = attr.match(/confirm\s*\(\s*([\s\S]+?)\s*\)\s*;?\s*$/);
      if (!m) return;
      var msg;
      try {
        msg = (new Function('return (' + m[1] + ')'))();
      } catch (err) {
        return;
      }
      if (typeof msg !== 'string' || msg === '') return;
      form.setAttribute('data-confirm', msg);
      form.removeAttribute('onsubmit');
      if (/delete|remove|permanently/i.test(msg)) {
        form.setAttribute('data-confirm-danger', '1');
      }
    });
  }

  function bindFormConfirm() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM') return;
      if (form.dataset.confirmBypass === '1') return;

      var msg = form.getAttribute('data-confirm');
      if (!msg) return;

      e.preventDefault();
      e.stopPropagation();

      var title = form.getAttribute('data-confirm-title') || 'Confirm';
      var danger = form.hasAttribute('data-confirm-danger');

      confirm(msg, { title: title, danger: danger }).then(function (ok) {
        if (!ok) return;
        form.dataset.confirmBypass = '1';
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          HTMLFormElement.prototype.submit.call(form);
        }
        delete form.dataset.confirmBypass;
      });
    }, true);
  }

  function bindClickConfirm() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]:not(form)');
      if (!el || el.tagName === 'FORM') return;
      if (el.dataset.confirmBypass === '1') return;

      var msg = el.getAttribute('data-confirm');
      if (!msg) return;

      e.preventDefault();
      e.stopPropagation();

      var title = el.getAttribute('data-confirm-title') || 'Confirm';
      var danger = el.hasAttribute('data-confirm-danger');

      confirm(msg, { title: title, danger: danger }).then(function (ok) {
        if (!ok) return;
        el.dataset.confirmBypass = '1';
        el.click();
        delete el.dataset.confirmBypass;
      });
    }, true);
  }

  function init() {
    migrateLegacyConfirmForms();
    bindFormConfirm();
    bindClickConfirm();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.AdminDialog = {
    alert: alert,
    confirm: confirm,
    choose: choose,
    show: show,
    copyFallback: copyFallback,
  };

  global.alert = function (message) {
    alert(message);
  };

})(window);

(function (global) {
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  global.AdminInfoTip = global.AdminInfoTip || {
    html: function (text, label) {
      var tip = String(text == null ? '' : text);
      var raw = String(label == null || label === '' ? '' : label).replace(/^\s*About\s+/i, '').trim();
      var aria = raw ? ('About ' + raw) : 'More info';
      return '<button type="button" class="admin-info-tip" aria-expanded="false" aria-label="'
        + escapeHtml(aria) + '">'
        + '<i class="fa fa-info-circle" aria-hidden="true"></i>'
        + '<span class="admin-info-tip__bubble" role="tooltip">' + escapeHtml(tip) + '</span></button>';
    }
  };
})(window);

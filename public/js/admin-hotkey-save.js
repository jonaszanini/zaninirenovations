/**
 * Admin-only Ctrl+S / Cmd+S. Clicks the page's opted-in [data-hotkey-save]
 * button so the existing submit / AJAX / validation path runs unchanged.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminHotkeySave = api;
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
  var DEBOUNCE_MS = 700;
  var WATCH_MS = 8000;
  var EXPECT_KEY = 'admin-save-expect';
  var DIALOG_SEL = [
    '[role="dialog"]:not([hidden])',
    '[role="alertdialog"]:not([hidden])',
    '.admin-dialog:not([hidden])',
    '.admin-modal:not([hidden])',
    '.cp-modal-backdrop:not([hidden])',
    '.cp-modal:not([hidden])',
  ].join(',');

  function defaultEnv(env) {
    env = env || {};
    return {
      document: env.document || (typeof document !== 'undefined' ? document : null),
      window: env.window || (typeof window !== 'undefined' ? window : null),
      now: env.now || function () { return Date.now(); },
      toast: env.toast || root.AdminToast || null,
      storage: env.storage || null,
    };
  }

  function storage(env) {
    if (env.storage) return env.storage;
    try {
      return env.window && env.window.sessionStorage;
    } catch (err) {
      return null;
    }
  }

  function isSaveChord(e) {
    if (!e) return false;
    var key = e.key || e.code || '';
    var isS = key === 's' || key === 'S' || key === 'KeyS';
    if (!isS) return false;
    if (e.altKey) return false;
    return !!(e.ctrlKey || e.metaKey);
  }

  function shortcutLabel(win) {
    win = win || (typeof window !== 'undefined' ? window : null);
    var platform = '';
    try {
      platform = (win && win.navigator && win.navigator.platform) || '';
    } catch (err) { /* ignore */ }
    if (/Mac|iPhone|iPad|iPod/i.test(platform)) return '\u2318S';
    return 'Ctrl+S';
  }

  function annotateTitle(btn, win) {
    if (!btn || !btn.getAttribute) return;
    var label = shortcutLabel(win);
    var current = btn.getAttribute('title') || '';
    if (current.indexOf(label) !== -1 || current.indexOf('Ctrl+S') !== -1 || current.indexOf('\u2318S') !== -1) {
      return;
    }
    btn.setAttribute('title', current ? current + ' (' + label + ')' : label);
  }

  function computedHidden(el, win) {
    if (!el) return true;
    if (el.hidden) return true;
    if (el.getAttribute && el.getAttribute('aria-hidden') === 'true') return true;
    if (el.closest && el.closest('[hidden]')) return true;
    var styleFn = win && win.getComputedStyle;
    if (styleFn) {
      try {
        var style = styleFn(el);
        if (style && (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0')) {
          return true;
        }
      } catch (err) { /* ignore */ }
    }
    return false;
  }

  function isButtonAvailable(btn, env) {
    env = defaultEnv(env);
    if (!btn) return false;
    if (btn.disabled) return false;
    if (btn.getAttribute && btn.getAttribute('aria-disabled') === 'true') return false;
    if (computedHidden(btn, env.window)) return false;
    return true;
  }

  function isBusy(btn) {
    if (!btn) return false;
    if (btn.disabled) return true;
    if (btn.getAttribute && btn.getAttribute('aria-busy') === 'true') return true;
    if (btn.getAttribute && btn.getAttribute('data-submitting') === '1') return true;
    if (btn.getAttribute && btn.getAttribute('data-submitting') === 'true') return true;
    if (btn.classList && btn.classList.contains('is-submitting')) return true;
    return false;
  }

  function openDialog(env) {
    env = defaultEnv(env);
    var doc = env.document;
    if (!doc || !doc.querySelectorAll) return null;
    var nodes = doc.querySelectorAll(DIALOG_SEL);
    var found = null;
    for (var i = 0; i < nodes.length; i++) {
      var node = nodes[i];
      if (computedHidden(node, env.window)) continue;
      if (node.getAttribute && node.getAttribute('aria-hidden') === 'true') continue;
      if (node.querySelector && node.querySelector('[data-hotkey-save]')) {
        found = node;
      }
    }
    return found;
  }

  function findTargetButton(env) {
    env = defaultEnv(env);
    var doc = env.document;
    if (!doc) return null;
    var scope = openDialog(env) || doc;
    var nodes = scope.querySelectorAll('[data-hotkey-save]');
    for (var i = 0; i < nodes.length; i++) {
      if (isButtonAvailable(nodes[i], env)) return nodes[i];
    }
    return null;
  }

  function buttonForm(btn, env) {
    env = defaultEnv(env);
    if (!btn) return null;
    if (btn.form) return btn.form;
    var formId = btn.getAttribute && btn.getAttribute('form');
    if (formId && env.document) {
      var byId = env.document.getElementById(formId);
      if (byId) return byId;
    }
    return btn.closest ? btn.closest('form') : null;
  }

  function commitPendingValue(el) {
    if (!el) return;
    var tag = (el.tagName || '').toLowerCase();
    var isField = tag === 'input' || tag === 'textarea' || tag === 'select' ||
      (el.isContentEditable) || (el.getAttribute && el.getAttribute('contenteditable') === 'true');
    if (!isField) return;
    try {
      if (typeof Event === 'function') {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (el.dispatchEvent) {
        el.dispatchEvent({ type: 'input', bubbles: true });
        el.dispatchEvent({ type: 'change', bubbles: true });
      }
    } catch (err) { /* ignore */ }
    try {
      if (el.blur) el.blur();
    } catch (err2) { /* ignore */ }
  }

  function fieldLabel(el, env) {
    env = defaultEnv(env);
    var doc = env.document;
    if (!el) return 'This field';
    if (el.id && doc) {
      var lab = doc.querySelector('label[for="' + el.id + '"]');
      if (lab) return cleanLabel(lab.textContent);
    }
    var wrap = el.closest ? el.closest('label') : null;
    if (wrap) return cleanLabel(wrap.textContent);
    return el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.name || 'This field';
  }

  function cleanLabel(text) {
    return String(text || '').replace(/\s+/g, ' ').trim().replace(/[*:]+$/, '').trim() || 'This field';
  }

  function resolveNamedField(form, name) {
    if (!form || !name) return null;
    var el = form.querySelector('[name="' + name + '"]');
    if (el) return el;
    el = form.querySelector('[name="' + name + '[]"]');
    if (el) return el;
    var dotted = name.replace(/\.(\d+)\./g, '[$1][').replace(/\.(\d+)$/g, '[$1]').replace(/\.([^.]+)/g, '[$1]');
    if (dotted !== name) {
      el = form.querySelector('[name="' + dotted + '"]');
      if (el) return el;
    }
    if (el && el.id) return el;
    var byId = form.querySelector('#' + cssEscape(name));
    return byId;
  }

  function cssEscape(name) {
    return String(name).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function collectInvalidFields(form, env) {
    if (!form) return [];
    var nodes;
    try {
      nodes = form.querySelectorAll(':invalid');
    } catch (err) {
      nodes = [];
    }
    var fields = [];
    var seen = {};
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if ((el.tagName || '').toLowerCase() === 'fieldset') continue;
      var key = el.name || el.id || i;
      if (seen[key]) continue;
      seen[key] = true;
      var msg = '';
      try { msg = el.validationMessage || 'is required'; } catch (e2) { msg = 'is required'; }
      fields.push({
        el: el,
        name: el.name || el.id || '',
        label: fieldLabel(el, env),
        message: msg,
      });
    }
    return fields;
  }

  function fieldsFromPayload(list, env) {
    env = defaultEnv(env);
    var doc = env.document;
    var form = doc ? doc.querySelector('form') : null;
    return (list || []).map(function (item) {
      var el = item.el || null;
      if (!el && doc) {
        if (item.id) el = doc.getElementById(item.id);
        if (!el && item.name && form) el = resolveNamedField(form, item.name);
        if (!el && item.name) el = doc.querySelector('[name="' + item.name + '"]');
      }
      return {
        el: el,
        name: item.name || '',
        label: item.label || (el ? fieldLabel(el, env) : item.name) || 'This field',
        message: item.message || 'is invalid',
      };
    });
  }

  function setExpect(env) {
    var store = storage(env);
    if (store && store.setItem) {
      try { store.setItem(EXPECT_KEY, '1'); } catch (err) { /* ignore */ }
    }
  }

  function consumeExpect(env) {
    var store = storage(env);
    if (!store || !store.getItem) return false;
    try {
      var yes = store.getItem(EXPECT_KEY) === '1';
      store.removeItem(EXPECT_KEY);
      return yes;
    } catch (err) {
      return false;
    }
  }

  function create(userEnv) {
    var env = defaultEnv(userEnv);
    var lastFire = 0;
    var inflight = false;
    var bound = false;
    var unwrap = null;

    function toast() {
      return env.toast || root.AdminToast;
    }

    function showSaved() {
      var t = toast();
      if (t && t.saved) t.saved(env);
    }

    function showError(message) {
      var t = toast();
      if (t && t.error) t.error(message, env);
    }

    function showValidation(fields, opts) {
      var t = toast();
      if (t && t.validation) t.validation(fields, opts, env);
    }

    function finishWatch() {
      inflight = false;
      if (unwrap) {
        try { unwrap(); } catch (err) { /* ignore */ }
        unwrap = null;
      }
    }

    function handleResponse(res) {
      if (!res) {
        finishWatch();
        showError('Could not save.');
        return;
      }
      var status = res.status;
      var clone = res.clone ? res.clone() : res;
      var parse = clone && clone.json ? clone.json() : Promise.resolve(null);
      parse.catch(function () { return null; }).then(function (body) {
        finishWatch();
        if (res.ok) {
          if (body && body.errors) {
            showValidation(fieldsFromPayload(laravelErrors(body.errors), env));
            return;
          }
          showSaved();
          return;
        }
        if (status === 422 && body && body.errors) {
          showValidation(fieldsFromPayload(laravelErrors(body.errors), env));
          return;
        }
        var msg = (body && (body.message || body.error)) || 'The server could not save your changes.';
        showError(msg);
      });
    }

    function laravelErrors(errors) {
      var list = [];
      Object.keys(errors || {}).forEach(function (name) {
        var messages = errors[name];
        list.push({
          name: name,
          message: Array.isArray(messages) ? messages[0] : String(messages || 'is invalid'),
        });
      });
      return list;
    }

    function isMutating(method) {
      return /^(POST|PUT|PATCH)$/i.test(method || '');
    }

    function requestMethod(input, init) {
      if (init && init.method) return String(init.method);
      if (input && input.method) return String(input.method);
      return 'GET';
    }

    function armWatch() {
      var win = env.window;
      if (!win || inflight) return;
      inflight = true;
      var handled = false;
      var origFetch = win.fetch;
      var origOpen = null;
      var XHR = win.XMLHttpRequest;
      var proto = XHR && XHR.prototype;

      function take(res) {
        if (handled) return;
        handled = true;
        handleResponse(res);
      }

      if (typeof origFetch === 'function') {
        win.fetch = function (input, init) {
          var method = requestMethod(input, init);
          var p = origFetch.apply(this, arguments);
          if (isMutating(method)) {
            p.then(function (res) { take(res); }).catch(function () {
              if (!handled) {
                handled = true;
                finishWatch();
                showError('Could not save.');
              }
            });
          }
          return p;
        };
      }

      var origSend = null;
      if (proto && proto.open && proto.send) {
        origOpen = proto.open;
        origSend = proto.send;
        proto.open = function (method) {
          this.__adminSaveMethod = method;
          return origOpen.apply(this, arguments);
        };
        proto.send = function () {
          if (isMutating(this.__adminSaveMethod)) {
            this.addEventListener('load', function () {
              take({
                ok: this.status >= 200 && this.status < 300,
                status: this.status,
                json: function () {
                  try { return Promise.resolve(JSON.parse(this.responseText || 'null')); }
                  catch (err) { return Promise.resolve(null); }
                }.bind(this),
                clone: function () {
                  return this;
                }.bind(this),
              });
            });
            this.addEventListener('error', function () {
              if (!handled) {
                handled = true;
                finishWatch();
                showError('Could not save.');
              }
            });
          }
          return origSend.apply(this, arguments);
        };
      }

      var jqOff = null;
      if (win.jQuery && win.jQuery(win.document)) {
        var $doc = win.jQuery(win.document);
        var onDone = function (event, xhr) {
          if (!xhr) return;
          take({
            ok: xhr.status >= 200 && xhr.status < 300,
            status: xhr.status,
            clone: function () {
              return {
                json: function () {
                  try { return Promise.resolve(typeof xhr.responseJSON !== 'undefined' ? xhr.responseJSON : JSON.parse(xhr.responseText || 'null')); }
                  catch (err) { return Promise.resolve(null); }
                },
              };
            },
          });
        };
        $doc.on('ajaxSuccess.adminHotkeySave ajaxError.adminHotkeySave', onDone);
        jqOff = function () {
          $doc.off('ajaxSuccess.adminHotkeySave ajaxError.adminHotkeySave');
        };
      }

      var timeoutFn = (win && win.setTimeout) || setTimeout;
      var clearFn = (win && win.clearTimeout) || clearTimeout;
      var timer = timeoutFn(function () {
        if (!handled) finishWatch();
      }, WATCH_MS);
      if (timer && typeof timer.unref === 'function') {
        timer.unref();
      }

      unwrap = function () {
        clearFn(timer);
        if (typeof origFetch === 'function') win.fetch = origFetch;
        if (proto && origOpen) proto.open = origOpen;
        if (proto && origSend) proto.send = origSend;
        if (jqOff) jqOff();
      };
    }

    function prepareSave(btn) {
      var doc = env.document;
      var active = doc && doc.activeElement;
      commitPendingValue(active);
      var form = buttonForm(btn, env);
      if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) {
        var fields = collectInvalidFields(form, env);
        showValidation(fields);
        return false;
      }
      return true;
    }

    function looksLikeAjaxButton(btn) {
      if (!btn) return false;
      if (btn.hasAttribute && btn.hasAttribute('data-ajax-save')) return true;
      var type = (btn.getAttribute && btn.getAttribute('type')) || '';
      type = String(type || 'submit').toLowerCase();
      return type === 'button';
    }

    function afterValidSave(btn) {
      setExpect(env);
      if (looksLikeAjaxButton(btn)) {
        armWatch();
      }
    }

    function onMarkedClick(e) {
      var btn = e && e.target && e.target.closest ? e.target.closest('[data-hotkey-save]') : null;
      if (!btn) return;
      if (isBusy(btn) || !isButtonAvailable(btn, env)) return;
      if (!prepareSave(btn)) {
        if (e && e.preventDefault) e.preventDefault();
        if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
        return;
      }
      afterValidSave(btn);
    }

    function handleKeydown(e) {
      if (!isSaveChord(e)) return false;
      if (e.preventDefault) e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();
      var t = env.now();
      if (t - lastFire < DEBOUNCE_MS) return true;
      if (inflight) return true;
      var btn = findTargetButton(env);
      if (!btn) return true;
      if (isBusy(btn) || !isButtonAvailable(btn, env)) return true;
      lastFire = t;
      if (!prepareSave(btn)) return true;
      afterValidSave(btn);
      try {
        btn.click();
      } catch (err) { /* ignore */ }
      return true;
    }

    function annotateAll() {
      var doc = env.document;
      if (!doc) return;
      var nodes = doc.querySelectorAll('[data-hotkey-save]');
      for (var i = 0; i < nodes.length; i++) {
        annotateTitle(nodes[i], env.window);
      }
    }

    function consumeFlash(payload) {
      payload = payload || {};
      var expected = consumeExpect(env);
      if (payload.fields && payload.fields.length) {
        showValidation(fieldsFromPayload(payload.fields, env), { warning: !!payload.warning });
        return;
      }
      if (payload.error) {
        showError(payload.error);
        return;
      }
      if (payload.warning && expected) {
        var tw = toast();
        if (tw && tw.show) {
          tw.show({ type: 'warning', title: payload.warning, sticky: true }, env);
        }
        return;
      }
      if (expected && payload.success) {
        showSaved();
      }
    }

    function bind() {
      if (bound) return;
      var doc = env.document;
      if (!doc || !doc.addEventListener) return;
      bound = true;
      doc.addEventListener('keydown', handleKeydown, true);
      doc.addEventListener('click', onMarkedClick, true);
      annotateAll();
      var flashEl = doc.getElementById('admin-toast-flash');
      if (flashEl) {
        try {
          consumeFlash(JSON.parse(flashEl.textContent || '{}'));
        } catch (err) { /* ignore */ }
      }
    }

    function reset() {
      lastFire = 0;
      finishWatch();
    }

    return {
      DEBOUNCE_MS: DEBOUNCE_MS,
      EXPECT_KEY: EXPECT_KEY,
      isSaveChord: isSaveChord,
      shortcutLabel: function () { return shortcutLabel(env.window); },
      annotateTitle: function (btn) { return annotateTitle(btn, env.window); },
      isButtonAvailable: function (btn) { return isButtonAvailable(btn, env); },
      isBusy: isBusy,
      findTargetButton: function () { return findTargetButton(env); },
      openDialog: function () { return openDialog(env); },
      buttonForm: function (btn) { return buttonForm(btn, env); },
      commitPendingValue: commitPendingValue,
      collectInvalidFields: function (form) { return collectInvalidFields(form, env); },
      handleKeydown: handleKeydown,
      onMarkedClick: onMarkedClick,
      consumeFlash: consumeFlash,
      setExpect: function () { setExpect(env); },
      consumeExpect: function () { return consumeExpect(env); },
      bind: bind,
      reset: reset,
      isInflight: function () { return inflight; },
    };
  }

  var singleton = null;
  function instance() {
    if (!singleton) singleton = create();
    return singleton;
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        instance().bind();
      });
    } else {
      instance().bind();
    }
  }

  return {
    DEBOUNCE_MS: DEBOUNCE_MS,
    EXPECT_KEY: EXPECT_KEY,
    create: create,
    isSaveChord: isSaveChord,
    shortcutLabel: shortcutLabel,
    annotateTitle: annotateTitle,
    isButtonAvailable: isButtonAvailable,
    isBusy: isBusy,
    findTargetButton: findTargetButton,
    commitPendingValue: commitPendingValue,
    collectInvalidFields: collectInvalidFields,
    instance: instance,
  };
});

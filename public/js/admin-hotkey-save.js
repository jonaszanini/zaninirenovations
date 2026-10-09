/**
 * Admin-only Ctrl+S / Cmd+S. Clicks the opted-in [data-hotkey-save] button
 * so the page's existing save path runs unchanged.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminHotkeySave = api;
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
  var DEBOUNCE_MS = 700;
  var WATCH_MS = 12000;
  var EXPECT_KEY = 'admin-save-expect';
  var HOTKEY_FIELD = '_hotkey_save';
  var EMAIL_FIELDS = [
    'send_client_email',
    'send_schedule_email',
    'send_dates_to_client',
    'notify_client',
    'email_client',
  ];
  var DIALOG_SEL = [
    '[role="dialog"]:not([hidden])',
    '[role="alertdialog"]:not([hidden])',
    '.admin-dialog:not([hidden])',
    '.admin-modal:not([hidden])',
    '.cp-modal-backdrop:not([hidden])',
    '.cp-modal:not([hidden])',
  ].join(',');
  var IGNORE_URL = /\/preview(?:\/|$|\?)|\/search(?:\/|$|\?)|\/autosave(?:\/|$|\?)|live-preview|live-breakdown|-calculate(?:\/|$|\?)|\/calculate(?:\/|$|\?)/i;

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
    if (e.shiftKey) return false;
    if (e.altKey && e.ctrlKey) return false;
    var code = e.code || '';
    var key = e.key || '';
    if (code !== 'KeyS' && key !== 's' && key !== 'S') return false;
    return !!(e.ctrlKey || e.metaKey);
  }

  function shouldTriggerSave(e) {
    if (!isSaveChord(e)) return false;
    if (e.repeat) return false;
    if (e.altKey && e.ctrlKey) return false;
    return true;
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
    if (btn.getAttribute && (btn.getAttribute('data-submitting') === '1' || btn.getAttribute('data-submitting') === 'true')) return true;
    if (btn.classList && btn.classList.contains('is-submitting')) return true;
    return false;
  }

  function isEmailButton(btn) {
    if (!btn) return false;
    var id = ((btn.id || '') + ' ' + (btn.getAttribute && btn.getAttribute('name') || '')).toLowerCase();
    var intent = ((btn.getAttribute && (btn.getAttribute('data-intent') || btn.getAttribute('data-cp-invoice-intent') || '')) || '').toLowerCase();
    var text = ((btn.textContent || '') + ' ' + (btn.getAttribute && btn.getAttribute('aria-label') || '')).toLowerCase();
    if (/publish|email|sms|send/.test(id) && !/draft|save/.test(id)) return true;
    if (intent === 'publish' || intent === 'send' || intent === 'close') return true;
    if (/email quotation|email client|send.*client|save & send|save &amp; send/.test(text)) return true;
    return false;
  }

  function isConfirmOpen(env) {
    env = defaultEnv(env);
    var doc = env.document;
    if (!doc) return false;
    var dlg = doc.getElementById('admin-dialog');
    if (dlg && !dlg.hidden && dlg.getAttribute('aria-hidden') !== 'true') return true;
    if (doc.documentElement && doc.documentElement.classList && doc.documentElement.classList.contains('admin-dialog-open')) {
      return true;
    }
    return false;
  }

  function openDialog(env) {
    env = defaultEnv(env);
    var doc = env.document;
    if (!doc || !doc.querySelectorAll) return null;
    if (isConfirmOpen(env)) return null;
    var nodes = doc.querySelectorAll(DIALOG_SEL);
    var found = null;
    for (var i = 0; i < nodes.length; i++) {
      var node = nodes[i];
      if (node.id === 'admin-dialog') continue;
      if (computedHidden(node, env.window)) continue;
      if (node.getAttribute && node.getAttribute('aria-hidden') === 'true') continue;
      if (node.querySelector && node.querySelector('[data-hotkey-save]')) {
        found = node;
      }
    }
    return found;
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

  function tinymceApi(env) {
    var win = env && env.window;
    return (win && win.tinymce) || (typeof tinymce !== 'undefined' ? tinymce : null);
  }

  function editorHasFocus(editor, env) {
    if (!editor) return false;
    if (typeof editor.hasFocus === 'function') {
      try { return !!editor.hasFocus(); } catch (err) { /* ignore */ }
    }
    var el = typeof editor.getElement === 'function' ? editor.getElement() : null;
    var active = env.document && env.document.activeElement;
    if (el && active && (active === el || (el.contains && el.contains(active)))) return true;
    return false;
  }

  function activeEditable(env) {
    env = defaultEnv(env);
    var tm = tinymceApi(env);
    if (tm && tm.activeEditor && editorHasFocus(tm.activeEditor, env) && typeof tm.activeEditor.getElement === 'function') {
      return tm.activeEditor.getElement();
    }
    var doc = env.document;
    var el = doc && doc.activeElement;
    if (!el || el === doc.body || el === doc.documentElement) return null;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable) return el;
    if (el.closest && el.closest('form')) return el;
    return el;
  }

  function formForFocus(env) {
    var el = activeEditable(env);
    if (!el) return null;
    if (el.form) return el.form;
    return el.closest ? el.closest('form') : null;
  }

  function firstAvailable(nodes, env) {
    for (var i = 0; i < nodes.length; i++) {
      if (isButtonAvailable(nodes[i], env) && !isEmailButton(nodes[i])) return nodes[i];
    }
    return null;
  }

  function findTargetButton(env) {
    env = defaultEnv(env);
    var doc = env.document;
    if (!doc) return null;
    if (isConfirmOpen(env)) return null;
    var dialog = openDialog(env);
    if (dialog) {
      return firstAvailable(dialog.querySelectorAll('[data-hotkey-save]'), env);
    }
    var focusedForm = formForFocus(env);
    if (focusedForm) {
      var inForm = [];
      var all = doc.querySelectorAll('[data-hotkey-save]');
      for (var i = 0; i < all.length; i++) {
        if (buttonForm(all[i], env) === focusedForm) inForm.push(all[i]);
      }
      var match = firstAvailable(inForm, env);
      if (match) return match;
    }
    return firstAvailable(doc.querySelectorAll('[data-hotkey-save]'), env);
  }

  function commitPendingValue(el, env) {
    env = defaultEnv(env);
    var tm = tinymceApi(env);
    if (tm && typeof tm.triggerSave === 'function') {
      try { tm.triggerSave(); } catch (err) { /* ignore */ }
    }
    if (!el) return;
    var tag = (el.tagName || '').toLowerCase();
    var isField = tag === 'input' || tag === 'textarea' || tag === 'select' ||
      el.isContentEditable || (el.getAttribute && el.getAttribute('contenteditable') === 'true');
    if (!isField) return;
    try {
      if (typeof Event === 'function') {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (el.dispatchEvent) {
        el.dispatchEvent({ type: 'input', bubbles: true });
        el.dispatchEvent({ type: 'change', bubbles: true });
      }
    } catch (err2) { /* ignore */ }
  }

  function restoreFocus(el) {
    if (!el || !el.focus) return;
    try {
      el.focus({ preventScroll: true });
    } catch (err) {
      try { el.focus(); } catch (err2) { /* ignore */ }
    }
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

  function laravelToName(name) {
    return String(name || '').replace(/\.(\d+)/g, '[$1]').replace(/\.([^.]+)/g, '[$1]');
  }

  function resolveNamedField(form, name, env) {
    env = defaultEnv(env);
    var doc = env.document;
    var scopes = [form, doc].filter(Boolean);
    var names = [name, laravelToName(name), name + '[]'];
    for (var s = 0; s < scopes.length; s++) {
      for (var n = 0; n < names.length; n++) {
        if (!names[n]) continue;
        var el = scopes[s].querySelector('[name="' + names[n] + '"]');
        if (el) return el;
      }
    }
    return null;
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
        if (!el && item.name) el = resolveNamedField(form, item.name, env);
      }
      return {
        el: el,
        name: item.name || '',
        label: item.label || (el ? fieldLabel(el, env) : laravelToName(item.name || '')) || 'This field',
        message: item.message || 'is invalid',
      };
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

  function isIgnoredUrl(url) {
    return IGNORE_URL.test(String(url || ''));
  }

  function requestUrl(input) {
    if (!input) return '';
    if (typeof input === 'string') return input;
    if (input.url) return String(input.url);
    try { return String(input); } catch (err) { return ''; }
  }

  function requestMethod(input, init) {
    if (init && init.method) return String(init.method);
    if (input && input.method) return String(input.method);
    return 'GET';
  }

  function isMutating(method) {
    return /^(POST|PUT|PATCH)$/i.test(method || '');
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

  function ensureHidden(form, name, value, env) {
    if (!form || !form.querySelector) return null;
    var el = form.querySelector('input[name="' + name + '"][data-admin-hotkey-field]');
    if (!el) {
      var doc = (form.ownerDocument)
        || (env && env.document)
        || (typeof document !== 'undefined' ? document : null);
      if (!doc || !doc.createElement) return null;
      el = doc.createElement('input');
      el.type = 'hidden';
      el.setAttribute('type', 'hidden');
      el.name = name;
      el.setAttribute('name', name);
      el.setAttribute('data-admin-hotkey-field', '1');
      form.appendChild(el);
    }
    el.value = value;
    return el;
  }

  function neutralizeEmail(form, env) {
    env = defaultEnv(env);
    var doc = env.document || form;
    EMAIL_FIELDS.forEach(function (name) {
      var nodes = (form || doc).querySelectorAll('[name="' + name + '"]');
      for (var i = 0; i < nodes.length; i++) {
        var el = nodes[i];
        if (el.type === 'checkbox' || el.type === 'radio') {
          el.checked = false;
        } else {
          el.value = '0';
        }
      }
    });
    if (form) ensureHidden(form, HOTKEY_FIELD, '1', env);
  }

  function hideDuplicateFlash(kind, env) {
    var doc = defaultEnv(env).document;
    if (!doc) return;
    var nodes = doc.querySelectorAll('[data-admin-flash="' + kind + '"]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].hidden = true;
    }
  }

  function revealWizardFor(el) {
    if (!el || !el.closest) return;
    var step = el.closest('[data-br-step], [data-mw-step], [data-sh-step], .br-acc');
    if (step) {
      step.hidden = false;
      if (step.classList) step.classList.add('is-active');
      try { step.setAttribute('open', ''); } catch (err) { /* ignore */ }
      var key = step.getAttribute('data-br-step') || step.getAttribute('data-mw-step') || step.getAttribute('data-sh-step');
      if (key) {
        var nav = (el.ownerDocument || document).querySelector('[data-br-goto="' + key + '"], [data-mw-goto="' + key + '"]');
        if (nav && nav.click) nav.click();
      }
    }
    var hiddenParent = el.closest('[hidden]');
    if (hiddenParent) hiddenParent.hidden = false;
  }

  function create(userEnv) {
    var env = defaultEnv(userEnv);
    var lastFire = 0;
    var session = null;
    var bound = false;
    var unwrap = null;

    function toast() {
      return env.toast || root.AdminToast;
    }

    function showSaved() {
      var t = toast();
      if (t && t.saved) t.saved(env);
    }

    function showError(message, title) {
      var t = toast();
      if (t && t.error) t.error(message, env, title);
    }

    function showValidation(fields, opts) {
      var t = toast();
      if (t && t.validation) t.validation(fields, opts || {}, env);
    }

    function finishSession() {
      if (session) {
        var focus = session.focus;
        session.inflight = false;
        session = null;
        restoreFocus(focus);
      }
      if (unwrap) {
        try { unwrap(); } catch (err) { /* ignore */ }
        unwrap = null;
      }
    }

    function parseAjaxResult(res, body) {
      var ctype = '';
      try {
        ctype = (res.headers && res.headers.get && res.headers.get('content-type')) || '';
      } catch (err) { /* ignore */ }
      var isJson = /application\/json/i.test(ctype) || (body && typeof body === 'object');
      if (!isJson) {
        var text = typeof body === 'string' ? body : '';
        if (/<html|login|sign in/i.test(text)) {
          return { kind: 'error', title: 'Sign in required', message: 'Your session expired. Sign in and save again.' };
        }
        return { kind: 'error', title: 'Could not save', message: 'The server did not confirm the save.' };
      }
      if (body && body.errors) {
        return { kind: 'validation', fields: fieldsFromPayload(laravelErrors(body.errors), env) };
      }
      if (res.ok && body && body.ok === true) {
        return { kind: 'saved' };
      }
      if (body && body.ok === false) {
        return { kind: 'error', title: 'Could not save', message: body.message || body.error || 'The save was not confirmed.' };
      }
      return { kind: 'error', title: 'Could not save', message: (body && (body.message || body.error)) || 'The save was not confirmed.' };
    }

    function applyAjaxResult(res, body) {
      var result = parseAjaxResult(res, body);
      finishSession();
      if (result.kind === 'saved') {
        showSaved();
        return;
      }
      if (result.kind === 'validation') {
        showValidation(result.fields);
        return;
      }
      showError(result.message, result.title);
    }

    function readBody(res) {
      if (!res) return Promise.resolve(null);
      var clone = res.clone ? res.clone() : res;
      if (clone.json) {
        return clone.json().catch(function () {
          return clone.text ? clone.text() : Promise.resolve(null);
        });
      }
      return Promise.resolve(null);
    }

    function takeAjax(res) {
      if (!session || !session.inflight || session.mode === 'form') return;
      session.mode = 'ajax';
      readBody(res).then(function (body) {
        applyAjaxResult(res, body);
      }).catch(function () {
        finishSession();
        showError('Could not save.');
      });
    }

    function armWatch() {
      var win = env.window;
      if (!win) return;
      var origFetch = win.fetch;
      var origOpen = null;
      var origSend = null;
      var origFormSubmit = null;
      var HTMLForm = win.HTMLFormElement;
      var XHR = win.XMLHttpRequest;
      var proto = XHR && XHR.prototype;
      var timeoutFn = (win && win.setTimeout) || setTimeout;
      var clearFn = (win && win.clearTimeout) || clearTimeout;

      if (typeof origFetch === 'function') {
        win.fetch = function (input, init) {
          var method = requestMethod(input, init);
          var url = requestUrl(input);
          var p = origFetch.apply(this, arguments);
          if (session && session.inflight && session.mode !== 'form' && isMutating(method) && !isIgnoredUrl(url)) {
            p.then(function (res) { takeAjax(res); }).catch(function () {
              if (session && session.inflight && session.mode !== 'form') {
                finishSession();
                showError('Could not save.');
              }
            });
          }
          return p;
        };
      }

      if (proto && proto.open && proto.send) {
        origOpen = proto.open;
        origSend = proto.send;
        proto.open = function (method, url) {
          this.__adminSaveMethod = method;
          this.__adminSaveUrl = url;
          return origOpen.apply(this, arguments);
        };
        proto.send = function () {
          if (session && session.inflight && session.mode !== 'form' && isMutating(this.__adminSaveMethod) && !isIgnoredUrl(this.__adminSaveUrl)) {
            this.addEventListener('load', function () {
              takeAjax({
                ok: this.status >= 200 && this.status < 300,
                status: this.status,
                headers: { get: function () { return this.getResponseHeader && this.getResponseHeader('content-type'); }.bind(this) },
                json: function () {
                  try { return Promise.resolve(JSON.parse(this.responseText || 'null')); }
                  catch (err) { return Promise.resolve(this.responseText || null); }
                }.bind(this),
                clone: function () { return this; }.bind(this),
              });
            });
            this.addEventListener('error', function () {
              if (session && session.inflight && session.mode !== 'form') {
                finishSession();
                showError('Could not save.');
              }
            });
          }
          return origSend.apply(this, arguments);
        };
      }

      if (HTMLForm && HTMLForm.prototype && HTMLForm.prototype.submit) {
        origFormSubmit = HTMLForm.prototype.submit;
        HTMLForm.prototype.submit = function () {
          if (session && session.inflight) {
            session.mode = 'form';
            setExpect(env);
          }
          return origFormSubmit.apply(this, arguments);
        };
      }

      var timer = timeoutFn(function () {
        if (session && session.inflight && session.mode !== 'form') {
          finishSession();
        }
      }, WATCH_MS);
      if (timer && typeof timer.unref === 'function') timer.unref();

      unwrap = function () {
        clearFn(timer);
        if (typeof origFetch === 'function') win.fetch = origFetch;
        if (proto && origOpen) proto.open = origOpen;
        if (proto && origSend) proto.send = origSend;
        if (HTMLForm && origFormSubmit) HTMLForm.prototype.submit = origFormSubmit;
      };
    }

    function onFormSubmit(e) {
      if (!session || !session.inflight) return;
      var form = e.target;
      if (session.form && form !== session.form) return;
      session.mode = 'form';
      setExpect(env);
    }

    function beginSession(btn, fromHotkey) {
      if (session && session.inflight) return false;
      var focus = activeEditable(env);
      commitPendingValue(focus, env);
      var form = buttonForm(btn, env);
      if (fromHotkey && form) neutralizeEmail(form, env);
      if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) {
        showValidation(collectInvalidFields(form, env));
        restoreFocus(focus);
        return false;
      }
      session = {
        inflight: true,
        btn: btn,
        form: form,
        mode: null,
        focus: focus,
        hotkey: !!fromHotkey,
      };
      armWatch();
      return true;
    }

    function onMarkedClick(e) {
      var btn = e && e.target && e.target.closest ? e.target.closest('[data-hotkey-save]') : null;
      if (!btn) return;
      if (session && session.inflight) {
        if (session.fromHotkey) return;
        if (e.preventDefault) e.preventDefault();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        return;
      }
      if (isBusy(btn) || !isButtonAvailable(btn, env) || isEmailButton(btn)) return;
      beginSession(btn, false);
    }

    function handleKeydown(e) {
      if (!isSaveChord(e)) return false;
      if (e.preventDefault) e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();
      if (e.repeat) return true;
      if (isConfirmOpen(env)) return true;
      var t = env.now();
      if (t - lastFire < DEBOUNCE_MS) return true;
      if (session && session.inflight) return true;
      var btn = findTargetButton(env);
      if (!btn || isBusy(btn) || !isButtonAvailable(btn, env) || isEmailButton(btn)) return true;
      lastFire = t;
      var focus = activeEditable(env);
      if (!beginSession(btn, true)) return true;
      session.fromHotkey = true;
      try { btn.click(); } catch (err) { /* ignore */ }
      if (session) session.fromHotkey = false;
      restoreFocus(focus);
      return true;
    }

    function annotateAll() {
      var doc = env.document;
      if (!doc) return;
      var nodes = doc.querySelectorAll('[data-hotkey-save]');
      for (var i = 0; i < nodes.length; i++) annotateTitle(nodes[i], env.window);
    }

    function bindTiny() {
      var tm = tinymceApi(env);
      if (!tm) return;
      var hook = function (editor) {
        if (!editor || editor.__adminHotkeyBound) return;
        editor.__adminHotkeyBound = true;
        editor.on('keydown', function (evt) {
          if (handleKeydown(evt)) {
            if (evt && evt.preventDefault) evt.preventDefault();
            return false;
          }
          return true;
        });
      };
      if (typeof tm.on === 'function') {
        tm.on('AddEditor', function (e) { hook(e.editor); });
      }
      if (tm.editors && tm.editors.forEach) {
        tm.editors.forEach(hook);
      }
    }

    function consumeFlash(payload) {
      payload = payload || {};
      var expected = consumeExpect(env);
      if (payload.fields && payload.fields.length) {
        showValidation(fieldsFromPayload(payload.fields, env), { warning: !!payload.warning });
        hideDuplicateFlash('error', env);
        return;
      }
      if (payload.error) {
        if (expected) {
          showError(payload.error, 'Could not save');
          hideDuplicateFlash('error', env);
        }
        return;
      }
      if (expected && payload.warning) {
        var tw = toast();
        if (tw && tw.show) tw.show({ type: 'warning', title: payload.warning, sticky: true }, env);
        hideDuplicateFlash('warning', env);
        return;
      }
      if (expected && payload.success) {
        showSaved();
        hideDuplicateFlash('success', env);
      }
    }

    function onPageHide() {
      if (session && session.mode === 'form') {
        session.inflight = false;
      }
    }

    function bind() {
      if (bound) return;
      var doc = env.document;
      if (!doc || !doc.addEventListener) return;
      bound = true;
      doc.addEventListener('keydown', handleKeydown, true);
      doc.addEventListener('click', onMarkedClick, true);
      doc.addEventListener('submit', onFormSubmit, true);
      if (env.window && env.window.addEventListener) {
        env.window.addEventListener('pagehide', onPageHide);
      }
      annotateAll();
      bindTiny();
      var flashEl = doc.getElementById('admin-toast-flash');
      if (flashEl) {
        try {
          consumeFlash(JSON.parse(flashEl.textContent || '{}'));
        } catch (err) { /* ignore */ }
      }
      if (toast() && toast().bindFieldWatch) toast().bindFieldWatch(env);
    }

    function reset() {
      lastFire = 0;
      finishSession();
    }

    return {
      DEBOUNCE_MS: DEBOUNCE_MS,
      EXPECT_KEY: EXPECT_KEY,
      HOTKEY_FIELD: HOTKEY_FIELD,
      isSaveChord: isSaveChord,
      shouldTriggerSave: shouldTriggerSave,
      shortcutLabel: function () { return shortcutLabel(env.window); },
      annotateTitle: function (btn) { return annotateTitle(btn, env.window); },
      isButtonAvailable: function (btn) { return isButtonAvailable(btn, env); },
      isBusy: isBusy,
      isEmailButton: isEmailButton,
      isConfirmOpen: function () { return isConfirmOpen(env); },
      findTargetButton: function () { return findTargetButton(env); },
      buttonForm: function (btn) { return buttonForm(btn, env); },
      commitPendingValue: function (el) { return commitPendingValue(el, env); },
      neutralizeEmail: function (form) { return neutralizeEmail(form, env); },
      laravelToName: laravelToName,
      isIgnoredUrl: isIgnoredUrl,
      parseAjaxResult: parseAjaxResult,
      collectInvalidFields: function (form) { return collectInvalidFields(form, env); },
      handleKeydown: handleKeydown,
      onMarkedClick: onMarkedClick,
      consumeFlash: consumeFlash,
      setExpect: function () { setExpect(env); },
      consumeExpect: function () { return consumeExpect(env); },
      bind: bind,
      reset: reset,
      isInflight: function () { return !!(session && session.inflight); },
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
    shouldTriggerSave: shouldTriggerSave,
    shortcutLabel: shortcutLabel,
    annotateTitle: annotateTitle,
    isButtonAvailable: isButtonAvailable,
    isBusy: isBusy,
    isEmailButton: isEmailButton,
    findTargetButton: findTargetButton,
    commitPendingValue: commitPendingValue,
    neutralizeEmail: neutralizeEmail,
    laravelToName: laravelToName,
    isIgnoredUrl: isIgnoredUrl,
    instance: instance,
  };
});

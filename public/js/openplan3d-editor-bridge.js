/**
 * Hosted openPlan3D iframe bridge.
 * Queues LOAD_PLAN until the editor has mounted, ignores boot-time store
 * writes until LOAD_ACK, namespaces/clears vendor storage, and hides the
 * vendor save/export chrome so Zanini Save Plan is the only save path.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.OpenPlan3DEditorBridge = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var IDB_NAME = 'openplan3d-local';
  var IDB_NAMESPACE = 'openplan3d-local-zanini-disabled';
  var PROJECTS_KEY = 'floorplan_projects';
  var THUMB_PREFIX = 'floorplan_thumb_';
  var VH_PREFIX = 'vh_';
  var HIDE_LABELS = {
    'Projects': true,
    'Save': true,
    'Saved ✓': true,
    'Saving…': true,
    'Saving...': true,
    'Export': true,
    'Back to Projects': true
  };

  function isBlockedStorageKey(key) {
    if (key == null) return false;
    var k = String(key);
    if (k === PROJECTS_KEY) return true;
    if (k.indexOf(THUMB_PREFIX) === 0) return true;
    if (k.indexOf(VH_PREFIX) === 0) return true;
    return false;
  }

  function serializePlan(raw) {
    if (!raw || typeof raw !== 'object') return null;
    try {
      return JSON.parse(JSON.stringify(raw));
    } catch (e) {
      try {
        var ancestors = [];
        var json = JSON.stringify(raw, function (key, value) {
          if (typeof value === 'function' || typeof value === 'symbol') return undefined;
          if (typeof value === 'object' && value !== null) {
            while (ancestors.length > 0 && ancestors[ancestors.length - 1] !== this) {
              ancestors.pop();
            }
            if (ancestors.indexOf(value) !== -1) return undefined;
            ancestors.push(value);
          }
          return value;
        });
        return JSON.parse(json);
      } catch (e2) {
        return null;
      }
    }
  }

  function floorSnapshot(floor) {
    if (!floor || typeof floor !== 'object') return null;
    var walls = Array.isArray(floor.walls) ? floor.walls : [];
    var rooms = Array.isArray(floor.rooms) ? floor.rooms : [];
    var first = walls[0] || null;
    return {
      id: floor.id || null,
      walls: walls.length,
      rooms: rooms.length,
      firstStart: first && first.start ? [first.start.x, first.start.y] : null,
      firstEnd: first && first.end ? [first.end.x, first.end.y] : null,
      roomIds: rooms.map(function (r) { return r && r.id; })
    };
  }

  function planFingerprint(plan) {
    if (!plan || typeof plan !== 'object') return null;
    var floors = [];
    if (Array.isArray(plan.floors)) {
      floors = plan.floors;
    } else if (plan.project && Array.isArray(plan.project.floors)) {
      floors = plan.project.floors;
    } else if (plan.floor && typeof plan.floor === 'object') {
      floors = [plan.floor];
    } else if (Array.isArray(plan.walls)) {
      floors = [plan];
    }
    return {
      id: plan.id || (plan.project && plan.project.id) || null,
      name: plan.name || (plan.project && plan.project.name) || null,
      floors: floors.map(floorSnapshot)
    };
  }

  function plansMatch(a, b) {
    if (!a || !b) return false;
    try {
      return JSON.stringify(planFingerprint(a)) === JSON.stringify(planFingerprint(b));
    } catch (e) {
      return false;
    }
  }

  var FLOOR_ARRAY_KEYS = [
    'walls',
    'rooms',
    'doors',
    'windows',
    'furniture',
    'stairs',
    'guides',
    'measurements',
    'annotations',
    'textAnnotations',
    'groups'
  ];

  function normalizeFloor(floor) {
    if (!floor || typeof floor !== 'object') return { walls: [], rooms: [], doors: [], windows: [] };
    var next = {};
    for (var key in floor) {
      if (Object.prototype.hasOwnProperty.call(floor, key)) next[key] = floor[key];
    }
    for (var i = 0; i < FLOOR_ARRAY_KEYS.length; i++) {
      var k = FLOOR_ARRAY_KEYS[i];
      if (!Array.isArray(next[k])) next[k] = [];
    }
    return next;
  }

  function toEditorProject(plan) {
    if (!plan || typeof plan !== 'object') return plan;
    var p = serializePlan(plan);
    if (!p) return plan;
    var floors = [];
    if (Array.isArray(p.floors)) {
      floors = p.floors;
    } else if (p.project && Array.isArray(p.project.floors)) {
      floors = p.project.floors;
    } else if (p.floor && typeof p.floor === 'object') {
      floors = [p.floor];
    } else if (Array.isArray(p.walls) || Array.isArray(p.rooms)) {
      floors = [p];
    }
    p.floors = floors.map(function (floor) { return normalizeFloor(floor); });
    if (!p.activeFloorId && p.floors[0] && p.floors[0].id) {
      p.activeFloorId = p.floors[0].id;
    }
    return p;
  }

  function inspectStoreModule(mod) {
    if (!mod || typeof mod !== 'object') {
      return { ok: false, setter: null, store: null, reason: 'missing-module' };
    }
    var store = (mod.c && typeof mod.c.subscribe === 'function') ? mod.c : null;
    var setter = null;
    if (typeof mod.H === 'function') {
      setter = function (project) { mod.H(project); };
    } else if (store && typeof store.set === 'function') {
      setter = function (project) { store.set(project); };
    }
    if (!store || !setter) {
      return { ok: false, setter: setter, store: store, reason: 'missing-H-or-c' };
    }
    return { ok: true, setter: setter, store: store, reason: null };
  }

  function storeExportsOk(mod) {
    return inspectStoreModule(mod).ok;
  }

  function defaultShowError(message) {
    if (typeof document === 'undefined') return;
    var el = document.getElementById('zanini-openplan3d-bridge-error');
    if (!el) {
      el = document.createElement('div');
      el.id = 'zanini-openplan3d-bridge-error';
      el.setAttribute('role', 'alert');
      el.style.cssText = 'position:fixed;z-index:99999;left:0;right:0;top:0;padding:12px 16px;background:#b91c1c;color:#fff;font:14px/1.4 sans-serif;';
      (document.body || document.documentElement).appendChild(el);
    }
    el.hidden = false;
    el.textContent = message;
  }

  function clearLocalStorageKeys(storage) {
    if (!storage) return [];
    var removed = [];
    var keys = [];
    for (var i = 0; i < storage.length; i++) {
      keys.push(storage.key(i));
    }
    for (var k = 0; k < keys.length; k++) {
      if (isBlockedStorageKey(keys[k])) {
        storage.removeItem(keys[k]);
        removed.push(keys[k]);
      }
    }
    return removed;
  }

  function snapshotBlockedStorage(storage) {
    var out = {};
    if (!storage) return out;
    for (var i = 0; i < storage.length; i++) {
      var key = storage.key(i);
      if (isBlockedStorageKey(key)) {
        out[key] = storage.getItem(key);
      }
    }
    return out;
  }

  function installStorageLock(options) {
    options = options || {};
    var g = options.global || (typeof window !== 'undefined' ? window : root);
    var ls = options.localStorage || (g && g.localStorage) || (typeof localStorage !== 'undefined' ? localStorage : null);
    var idb = options.indexedDB || (g && g.indexedDB) || (typeof indexedDB !== 'undefined' ? indexedDB : null);

    if (ls) {
      try { clearLocalStorageKeys(ls); } catch (e) {}
    }

    if (idb && typeof idb.deleteDatabase === 'function') {
      try { idb.deleteDatabase(IDB_NAME); } catch (e2) {}
      try { idb.deleteDatabase(IDB_NAMESPACE); } catch (e3) {}
    }

    var proto = (typeof Storage !== 'undefined') ? Storage.prototype : (ls ? Object.getPrototypeOf(ls) : null);
    if (proto && typeof proto.setItem === 'function' && !proto.__zaniniOpenPlan3DLocked) {
      var origSet = proto.setItem;
      proto.setItem = function (key, value) {
        if (isBlockedStorageKey(key)) {
          return;
        }
        return origSet.call(this, key, value);
      };
      proto.__zaniniOpenPlan3DLocked = true;
    }

    if (idb && typeof idb.open === 'function' && !idb.__zaniniOpenPlan3DLocked) {
      var origOpen = idb.open.bind(idb);
      idb.open = function (name, version) {
        if (name === IDB_NAME) {
          return origOpen(IDB_NAMESPACE, version);
        }
        return origOpen(name, version);
      };
      idb.__zaniniOpenPlan3DLocked = true;
    }

    return {
      idbName: IDB_NAME,
      namespacedIdb: IDB_NAMESPACE,
      localKeys: snapshotBlockedStorage(ls)
    };
  }

  function isEditorPanel(node) {
    if (!node || node.nodeType !== 1) return false;
    var tag = (node.tagName || '').toLowerCase();
    if (tag === 'aside' || tag === 'main' || tag === 'section' || tag === 'form' || tag === 'canvas') return true;
    var cls = typeof node.className === 'string' ? node.className : '';
    if (/\b(panel|sidebar|toolbar|catalog|drawer|viewport|canvas)\b/i.test(cls)) return true;
    if (node.hasAttribute('data-editor-panel')) return true;
    return false;
  }

  function isInsideEditorPanel(node) {
    if (!node || node.nodeType !== 1) return false;
    if (isEditorPanel(node)) return true;
    if (node.closest && node.closest('aside, main, section, form, [data-editor-panel], .panel, .sidebar, .toolbar')) {
      return true;
    }
    return false;
  }

  function shouldHideNativeControl(el) {
    if (!el || el.nodeType !== 1) return false;
    if (isInsideEditorPanel(el)) return false;
    if (el.hasAttribute('data-save-label')) return true;
    var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
    var aria = (el.getAttribute('aria-label') || '').trim();
    var title = (el.getAttribute('title') || '').trim();
    return !!(HIDE_LABELS[text] || HIDE_LABELS[aria] || HIDE_LABELS[title]);
  }

  function hideNativeSaveUi(root) {
    if (!root || typeof root.querySelectorAll !== 'function') return 0;
    var scope = root.body || root.documentElement || root;
    var header = null;
    var primarySelector = 'div.bg-gradient-to-r.from-slate-800';
    if (scope.matches && scope.matches(primarySelector)) {
      header = scope;
    } else if (typeof scope.querySelector === 'function') {
      header = scope.querySelector(primarySelector);
    }
    if (!header) {
      var fallbackSelector = '.vendor-top-bar, [data-vendor-header], header, nav, [class*="top-bar"], [class*="topbar"]';
      if (scope.matches && scope.matches(fallbackSelector)) {
        header = scope;
      } else if (typeof scope.querySelector === 'function') {
        header = scope.querySelector(fallbackSelector);
      }
    }
    if (!header) return 0;
    var nodes = header.querySelectorAll('a, button, span, [data-save-label], [aria-label]');
    var hidden = 0;
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (!shouldHideNativeControl(el)) continue;
      if (isInsideEditorPanel(el)) continue;
      var target = el;
      var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
      var aria = (el.getAttribute('aria-label') || '').trim();
      if ((text === 'Export' || aria === 'Export') && el.closest) {
        var wrap = el.closest('[data-zanini-hide-native-save], span.inline-grid');
        if (!wrap && el.parentElement && el.parentElement.classList && el.parentElement.classList.contains('relative')) {
          var p = el.parentElement;
          if (p !== header && !isInsideEditorPanel(p) && p.querySelectorAll('button, a').length <= 2) {
            wrap = p;
          }
        }
        if (wrap && wrap !== el && wrap !== header && !isInsideEditorPanel(wrap)) target = wrap;
      } else if (el.closest) {
        var wrap2 = el.closest('[data-zanini-hide-native-save], span.inline-grid');
        if (wrap2 && wrap2 !== el && wrap2 !== header && !isInsideEditorPanel(wrap2)) target = wrap2;
      }
      if (isInsideEditorPanel(target)) continue;
      if (target.getAttribute('data-zanini-hide-native-save') === '1' && target.hidden) {
        hidden++;
        continue;
      }
      target.setAttribute('data-zanini-hide-native-save', '1');
      target.hidden = true;
      target.setAttribute('aria-hidden', 'true');
      target.style.setProperty('display', 'none', 'important');
      target.style.setProperty('pointer-events', 'none', 'important');
      if (target.tagName === 'BUTTON') {
        target.disabled = true;
        target.setAttribute('tabindex', '-1');
      } else if (target.tagName === 'A') {
        target.setAttribute('tabindex', '-1');
      }
      hidden++;
    }
    return hidden;
  }

  function setEditorDarkClass(wantDark) {
    if (typeof document === 'undefined' || !document.documentElement) return;
    var hasDark = document.documentElement.classList.contains('dark');
    if (hasDark === wantDark) return;
    document.documentElement.classList.toggle('dark', wantDark);
  }

  function applyEditorTheme(theme) {
    setEditorDarkClass(theme === 'dark');
  }

  function syncHostedThemeFromParent() {
    if (typeof window === 'undefined' || !window.parent || window.parent === window) return;
    try {
      if (!window.parent.document || !window.parent.document.documentElement) return;
      var parentDark = window.parent.document.documentElement.classList.contains('admin-dark');
      var theme = parentDark ? 'dark' : 'light';
      setEditorDarkClass(parentDark);
      try {
        var existing = localStorage.getItem('o3d_theme');
        if (existing !== 'dark' && existing !== 'light') {
          localStorage.setItem('o3d_theme', theme);
        }
      } catch (e) {}
    } catch (e) {}
  }

  function keepHostedClass(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc || !doc.documentElement) return null;
    var apply = function () {
      if (doc.documentElement.classList.contains('zanini-hosted-openplan3d')) return;
      doc.documentElement.classList.add('zanini-hosted-openplan3d');
    };
    apply();
    if (typeof MutationObserver !== 'function') return null;
    var obs = new MutationObserver(apply);
    obs.observe(doc.documentElement, { attributes: true, attributeFilter: ['class'] });
    return obs;
  }

  function scheduleHideNativeSaveUi(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc) return null;
    hideNativeSaveUi(doc);
    var win = doc.defaultView || (typeof window !== 'undefined' ? window : null);
    var schedule = win && typeof win.setTimeout === 'function' ? win.setTimeout.bind(win) : setTimeout;
    var delays = [250, 750, 2000];
    var handles = [];
    for (var i = 0; i < delays.length; i++) {
      handles.push(schedule(function () { hideNativeSaveUi(doc); }, delays[i]));
    }
    return {
      disconnect: function () {
        for (var h = 0; h < handles.length; h++) {
          try {
            if (win && typeof win.clearTimeout === 'function') win.clearTimeout(handles[h]);
            else clearTimeout(handles[h]);
          } catch (e) {}
        }
      }
    };
  }

  function startHidingNativeSaveUi(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc) return null;
    if (doc.addEventListener && !doc.__zaniniNativeSaveClickLocked) {
      doc.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-zanini-hide-native-save]') : null;
        if (!el) return;
        e.preventDefault();
        e.stopPropagation();
      }, true);
      doc.__zaniniNativeSaveClickLocked = true;
    }
    return scheduleHideNativeSaveUi(doc);
  }

  function createSession(options) {
    options = options || {};
    var postToParent = options.postToParent || function (msg) {
      if (typeof window !== 'undefined' && window.parent) {
        var target = null;
        try {
          if (window.location && window.location.origin && window.location.origin !== 'null') {
            target = window.location.origin;
          }
        } catch (e) {}
        if (!target || target === '*' || target === 'null') {
          console.error('[openPlan3D-bridge] Refusing postMessage: invalid or missing target origin', target);
          return;
        }
        window.parent.postMessage(msg, target);
      }
    };
    var apiRef = null;
    var getApi = options.getApi || function () {
      if (apiRef) return apiRef;
      return typeof window !== 'undefined' ? window.__openPlan3D : null;
    };
    var setApi = options.setApi || function (api) {
      apiRef = api;
      if (typeof window !== 'undefined') window.__openPlan3D = api;
    };
    var showError = options.showError || defaultShowError;
    var now = options.now || function () { return Date.now(); };
    var schedule = options.schedule || function (fn, ms) { return setTimeout(fn, ms); };

    var queuedPlan = null;
    var editorMounted = false;
    var storeReady = false;
    var loadAcked = false;
    var ignoreStoreChanges = true;
    var applyGeneration = 0;

    function state() {
      return {
        queuedPlan: queuedPlan,
        editorMounted: editorMounted,
        storeReady: storeReady,
        loadAcked: loadAcked,
        ignoreStoreChanges: ignoreStoreChanges
      };
    }

    function emitAck(ok, plan) {
      if (ok) {
        loadAcked = true;
        ignoreStoreChanges = false;
        queuedPlan = null;
        postToParent({
          type: 'OPENPLAN3D_LOAD_ACK',
          ok: true,
          plan: serializePlan(plan)
        });
        return;
      }
      loadAcked = false;
      ignoreStoreChanges = true;
      postToParent({
        type: 'OPENPLAN3D_LOAD_ACK',
        ok: false,
        plan: null
      });
    }

    function tryApply() {
      if (!queuedPlan || !editorMounted || !storeReady) return;
      var api = getApi();
      if (!api || typeof api.setProject !== 'function' || typeof api.getProject !== 'function') {
        showError('openPlan3D editor API is unavailable (missing store exports). Room geometry cannot be loaded. Refresh the page.');
        postToParent({ type: 'OPENPLAN3D_BRIDGE_ERROR', message: 'missing-store-exports' });
        return;
      }
      ignoreStoreChanges = true;
      loadAcked = false;
      var expected = queuedPlan;
      var gen = ++applyGeneration;
      try {
        api.setProject(expected);
      } catch (err) {
        showError('Could not load the saved room into the editor.');
        postToParent({ type: 'OPENPLAN3D_BRIDGE_ERROR', message: 'setProject-failed' });
        return;
      }

      var attempts = 0;
      var poll = function () {
        if (gen !== applyGeneration) return;
        attempts++;
        var cur = null;
        try { cur = api.getProject(); } catch (e) { cur = null; }
        if (plansMatch(cur, expected)) {
          emitAck(true, cur);
          return;
        }
        if (attempts < 40) {
          if (attempts % 8 === 0) {
            try { api.setProject(expected); } catch (e2) {}
          }
          schedule(poll, 50);
          return;
        }
        try { api.setProject(expected); } catch (e3) {}
        try { cur = api.getProject(); } catch (e4) { cur = null; }
        if (plansMatch(cur, expected)) {
          emitAck(true, cur);
          return;
        }
        showError('Loaded plan did not match the editor project. Do not Save Plan until the geometry looks correct.');
        emitAck(false, cur);
      };
      schedule(poll, 0);
    }

    function queueLoad(plan) {
      queuedPlan = plan == null ? null : toEditorProject(plan);
      loadAcked = false;
      ignoreStoreChanges = true;
      tryApply();
    }

    function attachStore(storeModule) {
      var inspected = inspectStoreModule(storeModule);
      if (!inspected.ok) {
        storeReady = false;
        showError('openPlan3D editor API is unavailable (missing H/c store exports). Room geometry cannot be loaded. Refresh the page.');
        postToParent({ type: 'OPENPLAN3D_BRIDGE_ERROR', message: inspected.reason || 'missing-H-or-c' });
        return inspected;
      }
      setApi({
        getProject: function () {
          var cur = null;
          var unsub = inspected.store.subscribe(function (v) { cur = v; });
          if (typeof unsub === 'function') unsub();
          return cur;
        },
        setProject: function (project) {
          inspected.setter(project);
        },
        subscribe: function (callback) {
          return inspected.store.subscribe(callback);
        }
      });
      storeReady = true;
      postToParent({ type: 'OPENPLAN3D_READY' });
      tryApply();
      return inspected;
    }

    function isTrustedParentMessage(event) {
      if (typeof window === 'undefined' || !window.parent) return true;
      if (!event) return false;
      if (event.source !== window.parent) return false;
      if (!event.origin || event.origin === 'null') return false;
      if (!window.location || !window.location.origin || window.location.origin === 'null') return false;
      return event.origin === window.location.origin;
    }

    function handleParentMessage(event) {
      if (!isTrustedParentMessage(event)) return false;
      var data = event && event.data;
      if (!data || typeof data !== 'object') return false;
      if (data.type === 'OPENPLAN3D_LOAD_PLAN') {
        queueLoad(data.plan);
        return true;
      }
      if (data.type === 'OPENPLAN3D_SET_THEME') {
        applyEditorTheme(data.theme === 'dark' ? 'dark' : 'light');
        return true;
      }
      if (data.type === 'OPENPLAN3D_REQUEST_PLAN') {
        var api = getApi();
        var plan = api && typeof api.getProject === 'function' ? api.getProject() : null;
        postToParent({
          type: 'OPENPLAN3D_PLAN_RESPONSE',
          requestId: data.requestId,
          plan: serializePlan(plan)
        });
        return true;
      }
      return false;
    }

    function onStoreChange(project, emitChanged) {
      if (ignoreStoreChanges || !loadAcked) return false;
      if (typeof emitChanged === 'function') emitChanged(project);
      return true;
    }

    function onEditorMounted() {
      editorMounted = true;
      tryApply();
    }

    return {
      queueLoad: queueLoad,
      attachStore: attachStore,
      handleParentMessage: handleParentMessage,
      onStoreChange: onStoreChange,
      onEditorMounted: onEditorMounted,
      getState: state,
      serializePlan: serializePlan,
      postToParent: postToParent,
      now: now
    };
  }

  return {
    IDB_NAME: IDB_NAME,
    IDB_NAMESPACE: IDB_NAMESPACE,
    PROJECTS_KEY: PROJECTS_KEY,
    isBlockedStorageKey: isBlockedStorageKey,
    serializePlan: serializePlan,
    planFingerprint: planFingerprint,
    plansMatch: plansMatch,
    toEditorProject: toEditorProject,
    inspectStoreModule: inspectStoreModule,
    storeExportsOk: storeExportsOk,
    clearLocalStorageKeys: clearLocalStorageKeys,
    snapshotBlockedStorage: snapshotBlockedStorage,
    installStorageLock: installStorageLock,
    isEditorPanel: isEditorPanel,
    hideNativeSaveUi: hideNativeSaveUi,
    shouldHideNativeControl: shouldHideNativeControl,
    applyEditorTheme: applyEditorTheme,
    syncHostedThemeFromParent: syncHostedThemeFromParent,
    keepHostedClass: keepHostedClass,
    startHidingNativeSaveUi: startHidingNativeSaveUi,
    createSession: createSession,
    showVisibleError: defaultShowError
  };
}));

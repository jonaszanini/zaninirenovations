/**
 * Hosted openPlan3D iframe tools: area zones, bulkheads, wall finish flags.
 */
(function () {
  'use strict';

  var CM_PER_FOOT = 30.48;
  var state = {
    mode: null,
    drag: null,
    selectedWallId: null,
    overlay: null,
    toolbar: null,
    finishPanel: null,
    raf: null,
    canvasObserver: null,
    wallHooked: false
  };

  function uid(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 10);
  }

  function postPlanChanged(plan) {
    var session = window.__openPlan3DSession;
    if (session && typeof session.postToParent === 'function') {
      session.postToParent({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan });
    }
  }

  function getApi() {
    return window.__openPlan3D || null;
  }

  function readPlan() {
    var api = getApi();
    if (!api || typeof api.getProject !== 'function') return null;
    try {
      return api.getProject();
    } catch (e) {
      return null;
    }
  }

  function writePlan(mutator) {
    var api = getApi();
    if (!api || typeof api.getProject !== 'function' || typeof api.setProject !== 'function') return null;
    var plan = readPlan();
    if (!plan) return null;
    try {
      plan = JSON.parse(JSON.stringify(plan));
    } catch (err) {
      return null;
    }
    if (!plan.zanini || typeof plan.zanini !== 'object') {
      plan.zanini = { version: 1, zones: [], bulkheads: [], wallFinishes: {} };
    }
    mutator(plan);
    api.setProject(plan);
    postPlanChanged(plan);
    return plan;
  }

  function activeFloor(plan) {
    if (!plan || !Array.isArray(plan.floors)) return null;
    var id = plan.activeFloorId;
    if (id) {
      for (var i = 0; i < plan.floors.length; i++) {
        if (plan.floors[i] && plan.floors[i].id === id) return plan.floors[i];
      }
    }
    return plan.floors[0] || null;
  }

  function readStoreValue(store) {
    if (!store || typeof store.subscribe !== 'function') return null;
    var value = null;
    var unsub = store.subscribe(function (v) { value = v; });
    if (typeof unsub === 'function') unsub();
    return value;
  }

  function readViewState() {
    var api = getApi();
    if (api && typeof api.readViewState === 'function') {
      var fromApi = api.readViewState();
      if (fromApi) return fromApi;
    }
    var mod = window.__openPlan3DStoreModule;
    if (!mod) return null;
    var zoom = readStoreValue(mod.b2);
    var camX = readStoreValue(mod.bc);
    var camY = readStoreValue(mod.bd);
    if (zoom == null || camX == null || camY == null) return null;
    return { zoom: Number(zoom), camX: Number(camX), camY: Number(camY) };
  }

  function canvasEl() {
    var marked = document.querySelector('canvas[data-plan3d-canvas="true"]');
    if (marked) return marked;
    var canvases = document.querySelectorAll('canvas');
    var best = null;
    var bestArea = 0;
    for (var i = 0; i < canvases.length; i++) {
      var c = canvases[i];
      var area = (c.width || 0) * (c.height || 0);
      if (area > bestArea) {
        bestArea = area;
        best = c;
      }
    }
    return best;
  }

  function screenToPlan(canvas, clientX, clientY) {
    var rect = canvas.getBoundingClientRect();
    var scaleX = canvas.width / (rect.width || 1);
    var scaleY = canvas.height / (rect.height || 1);
    var px = (clientX - rect.left) * scaleX;
    var py = (clientY - rect.top) * scaleY;
    var view = readViewState();
    if (view && view.zoom) {
      return {
        x: (px - canvas.width / 2) / view.zoom + view.camX,
        y: (py - canvas.height / 2) / view.zoom + view.camY
      };
    }
    return { x: px, y: py };
  }

  function planToScreen(canvas, pt) {
    var rect = canvas.getBoundingClientRect();
    var view = readViewState();
    var x1 = pt.x1 != null ? pt.x1 : pt.x0;
    var y1 = pt.y1 != null ? pt.y1 : pt.y0;
    var x2 = pt.x2 != null ? pt.x2 : pt.x1;
    var y2 = pt.y2 != null ? pt.y2 : pt.y1;
    if (view && view.zoom) {
      var sx1 = (x1 - view.camX) * view.zoom + canvas.width / 2;
      var sy1 = (y1 - view.camY) * view.zoom + canvas.height / 2;
      var sx2 = (x2 - view.camX) * view.zoom + canvas.width / 2;
      var sy2 = (y2 - view.camY) * view.zoom + canvas.height / 2;
      var left = rect.left + (Math.min(sx1, sx2) / canvas.width) * rect.width;
      var top = rect.top + (Math.min(sy1, sy2) / canvas.height) * rect.height;
      var width = (Math.abs(sx2 - sx1) / canvas.width) * rect.width;
      var height = (Math.abs(sy2 - sy1) / canvas.height) * rect.height;
      return { left: left, top: top, width: width, height: height };
    }
    return {
      left: rect.left + Math.min(x1, x2),
      top: rect.top + Math.min(y1, y2),
      width: Math.abs(x2 - x1),
      height: Math.abs(y2 - y1)
    };
  }

  function ensureOverlay() {
    if (state.overlay) return state.overlay;
    var wrap = document.createElement('div');
    wrap.id = 'openplan3d-zanini-overlay';
    wrap.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:40;';
    document.body.appendChild(wrap);
    state.overlay = wrap;
    return wrap;
  }

  function renderOverlay() {
    var canvas = canvasEl();
    var overlay = ensureOverlay();
    overlay.innerHTML = '';
    if (!canvas) return;
    var plan = readPlan();
    if (!plan || !plan.zanini) return;

    function drawRect(shape, className) {
      var r = shape.rect || shape;
      if (r.x1 == null && r.x0 == null) return;
      var x1 = r.x1 != null ? r.x1 : r.x0;
      var y1 = r.y1 != null ? r.y1 : r.y0;
      var x2 = r.x2 != null ? r.x2 : r.x1;
      var y2 = r.y2 != null ? r.y2 : r.y1;
      var box = planToScreen(canvas, { x1: x1, y1: y1, x2: x2, y2: y2 });
      var el = document.createElement('div');
      el.className = className;
      el.style.cssText = 'position:absolute;border:2px dashed rgba(2,71,104,.85);background:rgba(2,71,104,.12);pointer-events:none;';
      el.style.left = Math.min(box.left, box.left + box.width) + 'px';
      el.style.top = Math.min(box.top, box.top + box.height) + 'px';
      el.style.width = Math.abs(box.width) + 'px';
      el.style.height = Math.abs(box.height) + 'px';
      overlay.appendChild(el);
    }

    (plan.zanini.zones || []).forEach(function (z) {
      if (z && z.mergedInto) return;
      drawRect(z, 'zanini-zone-rect');
    });
    (plan.zanini.bulkheads || []).forEach(function (b) {
      drawRect(b, 'zanini-bulkhead-rect');
    });

    if (state.drag && state.drag.preview) {
      var p = state.drag.preview;
      drawRect({ x1: p.x0, y1: p.y0, x2: p.x1, y2: p.y1 }, 'zanini-drag-preview');
    }
  }

  function scheduleOverlay() {
    if (state.raf) cancelAnimationFrame(state.raf);
    state.raf = requestAnimationFrame(renderOverlay);
  }

  function setMode(mode) {
    state.mode = state.mode === mode ? null : mode;
    if (state.toolbar) {
      [].forEach.call(state.toolbar.querySelectorAll('[data-zanini-mode]'), function (btn) {
        btn.classList.toggle('zanini-tool-active', btn.getAttribute('data-zanini-mode') === state.mode);
      });
    }
  }

  function onPointerDown(e) {
    if (!state.mode) return;
    var canvas = canvasEl();
    if (!canvas) return;
    var pt = screenToPlan(canvas, e.clientX, e.clientY);
    state.drag = { mode: state.mode, start: pt, preview: null };
    canvas.setPointerCapture(e.pointerId);
    e.preventDefault();
  }

  function onPointerMove(e) {
    if (!state.drag) return;
    var canvas = canvasEl();
    if (!canvas) return;
    var pt = screenToPlan(canvas, e.clientX, e.clientY);
    var s = state.drag.start;
    state.drag.preview = {
      x0: Math.min(s.x, pt.x),
      y0: Math.min(s.y, pt.y),
      x1: Math.max(s.x, pt.x),
      y1: Math.max(s.y, pt.y)
    };
    scheduleOverlay();
  }

  function onPointerUp(e) {
    if (!state.drag) return;
    var canvas = canvasEl();
    var preview = state.drag.preview;
    state.drag = null;
    if (canvas) {
      try { canvas.releasePointerCapture(e.pointerId); } catch (err) {}
    }
    if (!preview) return;
    var w = Math.abs(preview.x1 - preview.x0);
    var h = Math.abs(preview.y1 - preview.y0);
    if (w < 5 || h < 5) {
      scheduleOverlay();
      return;
    }

    if (state.mode === 'zone') {
      writePlan(function (plan) {
        plan.zanini.zones = plan.zanini.zones || [];
        plan.zanini.zones.push({
          id: uid('zone'),
          name: 'Zone ' + (plan.zanini.zones.length + 1),
          exclude: false,
          x1: preview.x0,
          y1: preview.y0,
          x2: preview.x1,
          y2: preview.y1
        });
      });
    } else if (state.mode === 'bulkhead') {
      writePlan(function (plan) {
        plan.zanini.bulkheads = plan.zanini.bulkheads || [];
        var lengthFt = w / CM_PER_FOOT;
        var widthFt = h / CM_PER_FOOT;
        plan.zanini.bulkheads.push({
          id: uid('bulkhead'),
          name: 'Bulkhead ' + (plan.zanini.bulkheads.length + 1),
          rect: { x1: preview.x0, y1: preview.y0, x2: preview.x1, y2: preview.y1 },
          drop_height_ft: 2,
          against_wall: true,
          width_ft: Math.max(lengthFt, widthFt) >= Math.min(lengthFt, widthFt) ? Math.min(lengthFt, widthFt) : 1
        });
      });
    }
    scheduleOverlay();
  }

  function injectToolbarStyles() {
    if (document.getElementById('openplan3d-zanini-toolbar-styles')) return;
    var style = document.createElement('style');
    style.id = 'openplan3d-zanini-toolbar-styles';
    style.textContent = [
      '#openplan3d-zanini-toolbar{z-index:45;bottom:56px;}',
      '#openplan3d-zanini-toolbar .zanini-tool-active{background:#024768!important;}',
      '#openplan3d-zanini-toolbar button{white-space:nowrap;font-size:11px;padding:0 8px;}',
      '@media (max-width:480px){#openplan3d-zanini-toolbar{bottom:72px;gap:4px;padding:4px;}}'
    ].join('');
    document.head.appendChild(style);
  }

  function buildToolbar() {
    if (state.toolbar) return state.toolbar;
    injectToolbarStyles();
    var bar = document.createElement('div');
    bar.id = 'openplan3d-zanini-toolbar';
    bar.style.cssText = 'position:fixed;left:50%;bottom:56px;transform:translateX(-50%);display:flex;gap:6px;padding:6px;background:rgba(15,23,42,.88);border-radius:10px;z-index:45;box-shadow:0 8px 24px rgba(0,0,0,.35);max-width:calc(100vw - 16px);overflow-x:auto;';
    bar.innerHTML = [
      '<button type="button" data-zanini-mode="zone" style="min-height:44px;min-width:44px;padding:0 10px;border-radius:8px;border:0;background:#1e293b;color:#f8fafc;font-size:11px;font-weight:600;">Zone</button>',
      '<button type="button" data-zanini-mode="bulkhead" style="min-height:44px;min-width:44px;padding:0 10px;border-radius:8px;border:0;background:#1e293b;color:#f8fafc;font-size:11px;font-weight:600;">Bulkhead</button>',
      '<button type="button" data-zanini-mode="finishes" style="min-height:44px;min-width:44px;padding:0 10px;border-radius:8px;border:0;background:#1e293b;color:#f8fafc;font-size:11px;font-weight:600;">Finishes</button>'
    ].join('');
    document.body.appendChild(bar);
    bar.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-zanini-mode]');
      if (!btn) return;
      var mode = btn.getAttribute('data-zanini-mode');
      if (mode === 'finishes') {
        toggleFinishPanel();
        return;
      }
      setMode(mode);
    });
    state.toolbar = bar;
    return bar;
  }

  function readSelectedWallIdFromStore() {
    var mod = window.__openPlan3DStoreModule;
    if (!mod || !mod.X) return null;
    return readStoreValue(mod.X);
  }

  function toggleFinishPanel() {
    if (state.finishPanel && !state.finishPanel.hidden) {
      state.finishPanel.hidden = true;
      return;
    }
    var wallId = state.selectedWallId || readSelectedWallIdFromStore();
    if (!wallId) {
      window.alert('Select a wall on the plan first, then open Wall Finishes.');
      return;
    }
    showFinishPanelForWall(wallId);
  }

  function wallIsBoundary(wallId) {
    var plan = readPlan();
    var floor = activeFloor(plan);
    if (!floor) return true;
    var rooms = floor.rooms || [];
    var room = rooms[0];
    if (!room) return true;
    var ids = room.boundary_wall_ids || room.walls || [];
    for (var i = 0; i < ids.length; i++) {
      if (String(ids[i]) === String(wallId)) return true;
    }
    return false;
  }

  function showFinishPanelForWall(wallId) {
    if (!wallId) return;
    if (!state.finishPanel) {
      var panel = document.createElement('div');
      panel.id = 'openplan3d-zanini-finish-panel';
      panel.style.cssText = 'position:fixed;right:12px;top:72px;width:min(280px,calc(100vw - 24px));padding:10px;background:rgba(15,23,42,.94);color:#f8fafc;border-radius:10px;z-index:46;font-size:12px;max-height:calc(100vh - 96px);overflow:auto;';
      document.body.appendChild(panel);
      state.finishPanel = panel;
    }
    state.selectedWallId = wallId;
    state.finishPanel.hidden = false;
    var plan = readPlan();
    var flags = (plan && plan.zanini && plan.zanini.wallFinishes && plan.zanini.wallFinishes[wallId]) || {};
    var isBoundary = wallIsBoundary(wallId);
    var defaults = {
      drywall: true,
      insulation: isBoundary,
      studs: !isBoundary,
      baseboard: true
    };
    state.finishPanel.innerHTML = '<div style="font-weight:700;margin-bottom:8px;">Wall ' + wallId + '</div>';
    ['drywall', 'insulation', 'studs', 'baseboard'].forEach(function (key) {
      var checked = Object.prototype.hasOwnProperty.call(flags, key) ? !!flags[key] : defaults[key];
      var row = document.createElement('label');
      row.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;';
      row.innerHTML = '<input type="checkbox" data-finish-key="' + key + '" ' + (checked ? 'checked' : '') + '> ' + key;
      state.finishPanel.appendChild(row);
    });
    if (!isBoundary) {
      var sidesRow = document.createElement('label');
      sidesRow.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;margin-top:4px;';
      var sides = flags.baseboardSides != null ? Number(flags.baseboardSides) : 2;
      sidesRow.innerHTML = 'Baseboard sides <select data-finish-sides style="min-height:44px;"><option value="1">1 side</option><option value="2">2 sides</option></select>';
      state.finishPanel.appendChild(sidesRow);
      var sel = state.finishPanel.querySelector('[data-finish-sides]');
      if (sel) sel.value = String(sides === 1 ? 1 : 2);
    }
    state.finishPanel.querySelectorAll('input[data-finish-key]').forEach(function (input) {
      input.addEventListener('change', function () {
        writePlan(function (plan) {
          plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
          plan.zanini.wallFinishes[wallId] = plan.zanini.wallFinishes[wallId] || {};
          plan.zanini.wallFinishes[wallId][input.getAttribute('data-finish-key')] = input.checked;
        });
      });
    });
    var sidesSelect = state.finishPanel.querySelector('[data-finish-sides]');
    if (sidesSelect) {
      sidesSelect.addEventListener('change', function () {
        writePlan(function (plan) {
          plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
          plan.zanini.wallFinishes[wallId] = plan.zanini.wallFinishes[wallId] || {};
          plan.zanini.wallFinishes[wallId].baseboardSides = Number(sidesSelect.value) === 1 ? 1 : 2;
        });
      });
    }
  }

  function ensureWallSelectionHook() {
    if (state.wallHooked) return;
    var mod = window.__openPlan3DStoreModule;
    if (!mod || !mod.X || typeof mod.X.subscribe !== 'function') return;
    state.wallHooked = true;
    mod.X.subscribe(function (wallId) {
      if (!wallId) return;
      state.selectedWallId = wallId;
      if (state.finishPanel && !state.finishPanel.hidden) {
        showFinishPanelForWall(wallId);
      }
    });
  }

  function bindCanvas(canvas) {
    if (!canvas || canvas.__zaniniToolsBound) return false;
    canvas.__zaniniToolsBound = true;
    canvas.style.touchAction = 'none';
    canvas.addEventListener('pointerdown', onPointerDown);
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerup', onPointerUp);
    canvas.addEventListener('pointercancel', onPointerUp);
    return true;
  }

  function ensureCanvasBound() {
    var canvas = canvasEl();
    if (!canvas) return false;
    return bindCanvas(canvas);
  }

  function watchCanvas() {
    if (ensureCanvasBound()) return;
    if (!state.canvasObserver && typeof MutationObserver !== 'undefined') {
      state.canvasObserver = new MutationObserver(function () {
        ensureCanvasBound();
      });
      state.canvasObserver.observe(document.body, { childList: true, subtree: true });
    }
    var attempts = 0;
    var timer = setInterval(function () {
      if (ensureCanvasBound() || ++attempts > 120) clearInterval(timer);
    }, 250);
    window.addEventListener('openplan3d-store-ready', function () {
      ensureCanvasBound();
      ensureWallSelectionHook();
    });
  }

  function mergeZones(sourceId, targetId) {
    writePlan(function (plan) {
      var zones = plan.zanini.zones || [];
      for (var i = 0; i < zones.length; i++) {
        if (zones[i].id === sourceId) {
          zones[i].mergedInto = targetId;
        }
      }
    });
  }

  function toggleZoneExclude(zoneId) {
    writePlan(function (plan) {
      var zones = plan.zanini.zones || [];
      for (var i = 0; i < zones.length; i++) {
        if (zones[i].id === zoneId) zones[i].exclude = !zones[i].exclude;
      }
    });
  }

  function init() {
    buildToolbar();
    watchCanvas();
    ensureWallSelectionHook();
    scheduleOverlay();
    window.addEventListener('resize', scheduleOverlay);
    setInterval(scheduleOverlay, 500);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setMode(null);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  var exports = {
    mergeZones: mergeZones,
    toggleZoneExclude: toggleZoneExclude,
    showFinishPanelForWall: showFinishPanelForWall,
    refreshOverlay: scheduleOverlay,
    canvasEl: canvasEl,
    screenToPlan: screenToPlan,
    ensureCanvasBound: ensureCanvasBound,
    readViewState: readViewState
  };

  window.OpenPlan3DZaniniTools = exports;

  if (typeof module === 'object' && module.exports) {
    module.exports = exports;
  }
})();

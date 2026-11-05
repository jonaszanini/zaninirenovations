/**
 * Hosted openPlan3D iframe tools: area zones, bulkheads, wall finish flags.
 */
(function () {
  'use strict';

  var CM_PER_FOOT = 30.48;
  var BULKHEAD_FILL = 'rgba(168, 85, 247, 0.38)';
  var BULKHEAD_BORDER = 'rgba(147, 51, 234, 0.95)';
  var PREVIEW_FILL = 'rgba(100, 116, 139, 0.22)';
  var PREVIEW_BORDER = 'rgba(71, 85, 105, 0.9)';
  var ZONE_PALETTE = [
    'rgba(14, 165, 233, 0.35)',
    'rgba(34, 197, 94, 0.35)',
    'rgba(249, 115, 22, 0.35)',
    'rgba(236, 72, 153, 0.35)',
    'rgba(234, 179, 8, 0.38)',
    'rgba(20, 184, 166, 0.35)',
    'rgba(59, 130, 246, 0.35)'
  ];

  var state = {
    mode: null,
    drag: null,
    selectedWallIds: [],
    selectedBulkheadId: null,
    overlay: null,
    toolbar: null,
    finishHost: null,
    finishPanel: null,
    sidebarObserver: null,
    sidebarObserverPaused: false,
    raf: null,
    canvasObserver: null,
    wallHooked: false
  };

  function uid(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 10);
  }

  function axisRect(shape) {
    if (!shape || typeof shape !== 'object') return null;
    var r = shape.rect || shape;
    var x1 = r.x1 != null ? r.x1 : r.x0;
    var y1 = r.y1 != null ? r.y1 : r.y0;
    var x2 = r.x2 != null ? r.x2 : r.x1;
    var y2 = r.y2 != null ? r.y2 : r.y1;
    if (x1 == null || y1 == null || x2 == null || y2 == null) return null;
    return {
      x1: Math.min(Number(x1), Number(x2)),
      y1: Math.min(Number(y1), Number(y2)),
      x2: Math.max(Number(x1), Number(x2)),
      y2: Math.max(Number(y1), Number(y2))
    };
  }

  function rectsOverlap(a, b) {
    var ra = axisRect(a);
    var rb = axisRect(b);
    if (!ra || !rb) return false;
    if (ra.x2 <= rb.x1 || rb.x2 <= ra.x1) return false;
    if (ra.y2 <= rb.y1 || rb.y2 <= ra.y1) return false;
    return true;
  }

  function zoneUnionBounds(zone, zones) {
    var bounds = null;
    function eat(shape) {
      var a = axisRect(shape);
      if (!a) return;
      if (!bounds) bounds = { x1: a.x1, y1: a.y1, x2: a.x2, y2: a.y2 };
      else {
        bounds.x1 = Math.min(bounds.x1, a.x1);
        bounds.y1 = Math.min(bounds.y1, a.y1);
        bounds.x2 = Math.max(bounds.x2, a.x2);
        bounds.y2 = Math.max(bounds.y2, a.y2);
      }
    }
    eat(zone);
    if (Array.isArray(zone.rects)) zone.rects.forEach(eat);
    (zones || []).forEach(function (z) {
      if (z && z.mergedInto === zone.id) eat(z);
    });
    return bounds;
  }

  function findOverlappingZone(zones, rect) {
    if (!Array.isArray(zones)) return null;
    for (var i = 0; i < zones.length; i++) {
      var zone = zones[i];
      if (!zone || zone.mergedInto) continue;
      var bounds = zoneUnionBounds(zone, zones);
      if (bounds && rectsOverlap(bounds, rect)) return zone;
    }
    return null;
  }

  function pickZoneColor(zones) {
    var used = {};
    (zones || []).forEach(function (z) {
      if (z && z.color) used[z.color] = true;
    });
    for (var i = 0; i < ZONE_PALETTE.length; i++) {
      if (!used[ZONE_PALETTE[i]]) return ZONE_PALETTE[i];
    }
    return ZONE_PALETTE[Math.floor(Math.random() * ZONE_PALETTE.length)];
  }

  function zoneAreaSqFt(zone, zones, cmPerUnit) {
    cmPerUnit = cmPerUnit || CM_PER_FOOT;
    var ext = typeof window !== 'undefined' && window.OpenPlan3DZaniniExtensions;
    if (ext && typeof ext.zoneAreaSqFt === 'function') {
      return ext.zoneAreaSqFt(zone, zones, cmPerUnit);
    }
    if (ext && typeof ext.collectZoneMemberLegacyRects === 'function' && typeof ext.rectsUnionAreaSqFt === 'function') {
      var members = ext.collectZoneMemberLegacyRects(zone, zones);
      var rects = [];
      for (var mi = 0; mi < members.length; mi++) {
        var m = members[mi];
        rects.push({ x0: m.x1, y0: m.y1, x1: m.x2, y1: m.y2 });
      }
      return ext.rectsUnionAreaSqFt(rects, cmPerUnit);
    }
    var b = zoneUnionBounds(zone, zones);
    if (!b) return 0;
    var w = Math.abs(b.x2 - b.x1) / cmPerUnit;
    var h = Math.abs(b.y2 - b.y1) / cmPerUnit;
    return w * h;
  }

  function legacyRectFromZone(zone) {
    var a = axisRect(zone);
    return a ? { x1: a.x1, y1: a.y1, x2: a.x2, y2: a.y2 } : null;
  }

  function mergeRectIntoZone(zones, targetId, rect) {
    var target = null;
    for (var i = 0; i < zones.length; i++) {
      if (zones[i].id === targetId) {
        target = zones[i];
        break;
      }
    }
    if (!target) return false;
    target.rects = Array.isArray(target.rects) ? target.rects.slice() : [];
    if (!target.rects.length) {
      var legacy = legacyRectFromZone(target);
      if (legacy) target.rects.push(legacy);
    }
    var a = axisRect(rect);
    if (!a) return false;
    target.rects.push({ x1: a.x1, y1: a.y1, x2: a.x2, y2: a.y2 });
    var b = zoneUnionBounds(target, zones);
    if (b) {
      target.x1 = b.x1;
      target.y1 = b.y1;
      target.x2 = b.x2;
      target.y2 = b.y2;
    }
    return true;
  }

  function addZoneFromPreview(plan, preview) {
    plan.zanini.zones = plan.zanini.zones || [];
    var zones = plan.zanini.zones;
    var rect = { x1: preview.x0, y1: preview.y0, x2: preview.x1, y2: preview.y1 };
    var overlap = findOverlappingZone(zones, rect);
    if (overlap) {
      mergeRectIntoZone(zones, overlap.id, rect);
      return overlap.id;
    }
    var id = uid('zone');
    zones.push({
      id: id,
      name: 'Zone ' + (zones.filter(function (z) { return z && !z.mergedInto; }).length + 1),
      exclude: false,
      color: pickZoneColor(zones),
      x1: preview.x0,
      y1: preview.y0,
      x2: preview.x1,
      y2: preview.y1
    });
    return id;
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

  function readStoreValue(store) {
    if (!store || typeof store.subscribe !== 'function') return null;
    var value = null;
    var unsub = store.subscribe(function (v) { value = v; });
    if (typeof unsub === 'function') unsub();
    return value;
  }

  function readSelectedWallIdFromStore() {
    var mod = window.__openPlan3DStoreModule;
    if (!mod) return null;
    if (mod.aB) {
      var fromUr = readStoreValue(mod.aB);
      if (fromUr) return String(fromUr);
    }
    if (mod.X) {
      var fromX = readStoreValue(mod.X);
      if (fromX) return String(fromX);
    }
    return null;
  }

  function readSelectedWallIds() {
    var mod = window.__openPlan3DStoreModule;
    var ids = [];
    if (mod && mod.bg) {
      var setVal = readStoreValue(mod.bg);
      if (setVal && typeof setVal.forEach === 'function') {
        setVal.forEach(function (id) {
          if (id != null && id !== '') ids.push(String(id));
        });
      }
    }
    // Multi-wall selection is driven by store export bg (Set). Shift/Ctrl additive clicks are vendor UI behaviour.
    if (!ids.length) {
      var one = readSelectedWallIdFromStore();
      if (one) ids.push(one);
    }
    var plan = readPlan();
    var floor = activeFloor(plan);
    if (!floor || !Array.isArray(floor.walls)) return ids;
    var known = {};
    floor.walls.forEach(function (w) { known[String(w.id)] = true; });
    return ids.filter(function (id) { return known[id]; });
  }

  function captureWallSelection() {
    return readSelectedWallIds();
  }

  function restoreWallSelection(ids) {
    var mod = window.__openPlan3DStoreModule;
    if (!mod || !ids || !ids.length) return;
    var primary = ids[0];
    try {
      if (mod.bg && typeof mod.bg.set === 'function') {
        mod.bg.set(new Set(ids));
      }
      if (mod.X && typeof mod.X.set === 'function') mod.X.set(primary);
      if (mod.aB && typeof mod.aB.set === 'function') mod.aB.set(primary);
    } catch (err) {}
    state.selectedWallIds = ids.slice();
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
    var savedSelection = captureWallSelection();
    mutator(plan);
    api.setProject(plan);
    postPlanChanged(plan);
    requestAnimationFrame(function () {
      restoreWallSelection(savedSelection);
      renderFinishPanel();
    });
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

  function readViewState() {
    var api = getApi();
    if (api && typeof api.readViewState === 'function') {
      var fromApi = api.readViewState();
      if (fromApi) return fromApi;
    }
    var mod = window.__openPlan3DStoreModule;
    if (!mod) return null;
    var zoom = readStoreValue(mod.b2);
    var camX = readStoreValue(mod.bd);
    var camY = readStoreValue(mod.bc);
    if (zoom == null || camX == null || camY == null) return null;
    return { zoom: Number(zoom), camX: Number(camX), camY: Number(camY) };
  }

  function isEditor3DViewActive() {
    var canvas = canvasEl();
    if (!canvas) return false;
    var style = window.getComputedStyle(canvas);
    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) return true;
    var rect = canvas.getBoundingClientRect();
    if (rect.width < 8 || rect.height < 8) return true;
    var ctx2d = null;
    try { ctx2d = canvas.getContext('2d'); } catch (e) {}
    if (!ctx2d) {
      var gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
      if (gl && !gl.isContextLost()) return true;
    }
    return false;
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
    var bridge = typeof window !== 'undefined' && window.OpenPlan3DEditorBridge;
    if (bridge && typeof bridge.clientToPlanCm === 'function') {
      var fromBridge = bridge.clientToPlanCm(canvas, clientX, clientY, readViewState(), window.__openPlan3DStoreModule);
      if (fromBridge) return fromBridge;
    }
    var rect = canvas.getBoundingClientRect();
    var cssW = rect.width || canvas.clientWidth || canvas.width || 1;
    var cssH = rect.height || canvas.clientHeight || canvas.height || 1;
    var px = clientX - rect.left;
    var py = clientY - rect.top;
    var view = readViewState();
    if (view && view.zoom) {
      return {
        x: (px - cssW / 2) / view.zoom + view.camX,
        y: (py - cssH / 2) / view.zoom + view.camY
      };
    }
    return { x: px, y: py };
  }

  function planToScreen(canvas, pt) {
    var rect = canvas.getBoundingClientRect();
    var view = readViewState();
    var cssW = rect.width || canvas.clientWidth || canvas.width || 1;
    var cssH = rect.height || canvas.clientHeight || canvas.height || 1;
    var x1 = pt.x1 != null ? pt.x1 : pt.x0;
    var y1 = pt.y1 != null ? pt.y1 : pt.y0;
    var x2 = pt.x2 != null ? pt.x2 : pt.x1;
    var y2 = pt.y2 != null ? pt.y2 : pt.y1;
    if (view && view.zoom) {
      var sx1 = (x1 - view.camX) * view.zoom + cssW / 2;
      var sy1 = (y1 - view.camY) * view.zoom + cssH / 2;
      var sx2 = (x2 - view.camX) * view.zoom + cssW / 2;
      var sy2 = (y2 - view.camY) * view.zoom + cssH / 2;
      return {
        left: rect.left + Math.min(sx1, sx2),
        top: rect.top + Math.min(sy1, sy2),
        width: Math.abs(sx2 - sx1),
        height: Math.abs(sy2 - sy1)
      };
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

  function hitTestBulkhead(plan, canvas, clientX, clientY) {
    if (!plan || !plan.zanini || !Array.isArray(plan.zanini.bulkheads)) return null;
    var pt = screenToPlan(canvas, clientX, clientY);
    for (var i = plan.zanini.bulkheads.length - 1; i >= 0; i--) {
      var b = plan.zanini.bulkheads[i];
      var a = axisRect(b.rect || b);
      if (!a) continue;
      if (pt.x >= a.x1 && pt.x <= a.x2 && pt.y >= a.y1 && pt.y <= a.y2) return b.id;
    }
    return null;
  }

  function renderOverlay() {
    var canvas = canvasEl();
    var overlay = ensureOverlay();
    overlay.innerHTML = '';
    if (!canvas || isEditor3DViewActive()) return;
    var plan = readPlan();
    if (!plan || !plan.zanini) return;
    var zones = plan.zanini.zones || [];

    function drawBox(bounds, opts) {
      if (!bounds) return;
      var box = planToScreen(canvas, bounds);
      var el = document.createElement('div');
      el.className = opts.className || 'zanini-overlay-rect';
      el.style.cssText = 'position:absolute;box-sizing:border-box;pointer-events:' + (opts.pointerEvents || 'none') + ';';
      el.style.border = '2px ' + (opts.dashed ? 'dashed' : 'solid') + ' ' + opts.border;
      el.style.background = opts.fill;
      el.style.left = box.left + 'px';
      el.style.top = box.top + 'px';
      el.style.width = Math.max(1, box.width) + 'px';
      el.style.height = Math.max(1, box.height) + 'px';
      if (opts.label) {
        var tag = document.createElement('span');
        tag.textContent = opts.label;
        tag.style.cssText = 'position:absolute;left:4px;top:2px;font-size:10px;font-weight:700;color:#0f172a;background:rgba(255,255,255,.82);padding:1px 4px;border-radius:4px;pointer-events:none;max-width:calc(100% - 8px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
        el.appendChild(tag);
      }
      if (opts.dataId) el.setAttribute('data-zanini-bulkhead-id', opts.dataId);
      overlay.appendChild(el);
    }

    zones.forEach(function (z) {
      if (!z || z.mergedInto) return;
      var bounds = zoneUnionBounds(z, zones);
      var color = z.color || pickZoneColor(zones);
      var sqft = zoneAreaSqFt(z, zones, CM_PER_FOOT);
      var label = (z.name || 'Zone') + ' · ' + sqft.toFixed(1) + ' sf';
      drawBox(bounds, {
        className: 'zanini-zone-rect',
        fill: color,
        border: 'rgba(15, 23, 42, 0.55)',
        dashed: true,
        label: label
      });
    });

    (plan.zanini.bulkheads || []).forEach(function (b) {
      var selected = state.selectedBulkheadId && b.id === state.selectedBulkheadId;
      drawBox(axisRect(b.rect || b), {
        className: 'zanini-bulkhead-rect',
        fill: BULKHEAD_FILL,
        border: selected ? '#ef4444' : BULKHEAD_BORDER,
        dashed: false,
        pointerEvents: state.mode ? 'none' : 'auto',
        dataId: b.id
      });
    });

    if (state.drag && state.drag.preview) {
      var p = state.drag.preview;
      drawBox({ x1: p.x0, y1: p.y0, x2: p.x1, y2: p.y1 }, {
        className: 'zanini-drag-preview',
        fill: PREVIEW_FILL,
        border: PREVIEW_BORDER,
        dashed: true
      });
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
      var delBtn = state.toolbar.querySelector('[data-zanini-action="delete-bulkhead"]');
      if (delBtn) delBtn.hidden = !state.selectedBulkheadId;
    }
    scheduleOverlay();
  }

  function deleteSelectedBulkhead() {
    if (!state.selectedBulkheadId) return;
    var id = state.selectedBulkheadId;
    writePlan(function (plan) {
      plan.zanini.bulkheads = (plan.zanini.bulkheads || []).filter(function (b) {
        return b && b.id !== id;
      });
    });
    state.selectedBulkheadId = null;
    scheduleOverlay();
  }

  function onOverlayClick(e) {
    var hit = e.target.closest('[data-zanini-bulkhead-id]');
    if (!hit || state.mode) return;
    state.selectedBulkheadId = hit.getAttribute('data-zanini-bulkhead-id');
    scheduleOverlay();
    if (state.toolbar) {
      var delBtn = state.toolbar.querySelector('[data-zanini-action="delete-bulkhead"]');
      if (delBtn) delBtn.hidden = !state.selectedBulkheadId;
    }
  }

  function onPointerDown(e) {
    var canvas = canvasEl();
    if (!canvas) return;
    if (!state.mode) {
      var plan = readPlan();
      var bulkId = hitTestBulkhead(plan, canvas, e.clientX, e.clientY);
      if (bulkId) {
        state.selectedBulkheadId = bulkId;
        scheduleOverlay();
        return;
      }
      return;
    }
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
    var mode = state.drag.mode;
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

    if (mode === 'zone') {
      writePlan(function (plan) {
        addZoneFromPreview(plan, preview);
      });
    } else if (mode === 'bulkhead') {
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
      state.selectedBulkheadId = null;
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
      '#openplan3d-zanini-overlay .zanini-bulkhead-rect{cursor:pointer;}',
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
      '<button type="button" data-zanini-mode="zone" title="Draw area zone" style="min-height:44px;min-width:44px;padding:0 10px;border-radius:8px;border:0;background:#1e293b;color:#f8fafc;font-size:11px;font-weight:600;">Zone</button>',
      '<button type="button" data-zanini-mode="bulkhead" title="Draw bulkhead" style="min-height:44px;min-width:44px;padding:0 10px;border-radius:8px;border:0;background:#1e293b;color:#f8fafc;font-size:11px;font-weight:600;">Bulkhead</button>',
      '<button type="button" data-zanini-action="delete-bulkhead" title="Delete selected bulkhead" hidden style="min-height:44px;min-width:44px;padding:0 10px;border-radius:8px;border:0;background:#7f1d1d;color:#fecaca;font-size:11px;font-weight:600;">Del</button>'
    ].join('');
    document.body.appendChild(bar);
    bar.addEventListener('click', function (e) {
      var actionBtn = e.target.closest('[data-zanini-action]');
      if (actionBtn && actionBtn.getAttribute('data-zanini-action') === 'delete-bulkhead') {
        deleteSelectedBulkhead();
        return;
      }
      var btn = e.target.closest('[data-zanini-mode]');
      if (!btn) return;
      setMode(btn.getAttribute('data-zanini-mode'));
    });
    state.toolbar = bar;
    return bar;
  }

  function findWallSidebarHost() {
    var finishMount = document.getElementById('openplan3d-zanini-finish-host');
    if (finishMount && finishMount.parentElement) {
      return finishMount.parentElement;
    }
    var elevBtn = document.querySelector('button.bg-blue-50.text-blue-700');
    if (elevBtn) {
      var panel = elevBtn.parentElement;
      while (panel && panel !== document.body) {
        var rect = panel.getBoundingClientRect();
        var txt = (panel.textContent || '').toLowerCase();
        if (rect.width >= 120 && rect.height >= 80 && rect.left > window.innerWidth * 0.45
          && (txt.indexOf('thickness') !== -1 || txt.indexOf('height') !== -1 || txt.indexOf('elevation') !== -1)) {
          return panel;
        }
        panel = panel.parentElement;
      }
    }
    var divs = document.querySelectorAll('div');
    var best = null;
    var bestScore = 0;
    for (var i = 0; i < divs.length; i++) {
      var el = divs[i];
      if (el.id === 'openplan3d-zanini-finish-host') continue;
      if (el.offsetParent === null) continue;
      var r = el.getBoundingClientRect();
      if (r.width < 120 || r.height < 80) continue;
      if (r.left < window.innerWidth * 0.5) continue;
      var text = (el.textContent || '').toLowerCase();
      var score = 0;
      if (text.indexOf('thickness') !== -1) score += 3;
      if (text.indexOf('height') !== -1) score += 1;
      if (el.querySelector('button.bg-blue-50.text-blue-700')) score += 4;
      if (el.querySelector('button.rounded-full')) score += 1;
      if (score > bestScore) {
        bestScore = score;
        best = el;
      }
    }
    return best;
  }

  function ensureFinishHost() {
    var sidebar = findWallSidebarHost();
    if (!sidebar) return null;
    if (!state.finishHost || state.finishHost.parentNode !== sidebar) {
      if (state.finishHost && state.finishHost.parentNode) {
        state.finishHost.parentNode.removeChild(state.finishHost);
      }
      state.finishHost = document.createElement('div');
      state.finishHost.id = 'openplan3d-zanini-finish-host';
      state.finishHost.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,.35);';
      sidebar.appendChild(state.finishHost);
      state.finishPanel = state.finishHost;
    }
    return state.finishHost;
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

  function renderFinishPanel() {
    state.sidebarObserverPaused = true;
    if (state.sidebarObserver) state.sidebarObserver.disconnect();
    try {
      renderFinishPanelNow();
    } finally {
      state.sidebarObserverPaused = false;
      if (state.sidebarObserver) {
        state.sidebarObserver.observe(document.body, { childList: true, subtree: true });
      }
    }
  }

  function renderFinishPanelNow() {
    var wallIds = readSelectedWallIds();
    var host = ensureFinishHost();
    if (!host) return;
    if (!wallIds.length) {
      host.innerHTML = '';
      host.hidden = true;
      return;
    }
    host.hidden = false;
    state.selectedWallIds = wallIds.slice();
    var plan = readPlan();
    var zanini = (plan && plan.zanini) || {};
    var wallFinishes = zanini.wallFinishes || {};
    var primary = wallIds[0];
    var flags = wallFinishes[primary] || {};
    var isBoundary = wallIsBoundary(primary);
    var floor = activeFloor(plan);
    var wallLenLf = 0;
    if (floor && Array.isArray(floor.walls)) {
      for (var wi = 0; wi < floor.walls.length; wi++) {
        if (String(floor.walls[wi].id) === String(primary)) {
          wallLenLf = Math.hypot(
            floor.walls[wi].end.x - floor.walls[wi].start.x,
            floor.walls[wi].end.y - floor.walls[wi].start.y
          ) / CM_PER_FOOT;
          break;
        }
      }
    }
    var defaults = {
      drywall: true,
      insulation: isBoundary,
      studs: !isBoundary,
      baseboard: true
    };
    var heading = wallIds.length > 1
      ? ('Wall finishes (' + wallIds.length + ' walls)')
      : ('Wall finishes · ' + primary);
    host.innerHTML = '<div style="font-weight:700;margin-bottom:6px;font-size:12px;">' + heading + '</div>';
    ['drywall', 'insulation', 'studs', 'baseboard'].forEach(function (key) {
      var checked = Object.prototype.hasOwnProperty.call(flags, key) ? !!flags[key] : defaults[key];
      if (wallIds.length > 1) {
        var mixed = false;
        var first = checked;
        for (var wi2 = 1; wi2 < wallIds.length; wi2++) {
          var f2 = wallFinishes[wallIds[wi2]] || {};
          var c2 = Object.prototype.hasOwnProperty.call(f2, key) ? !!f2[key] : defaults[key];
          if (c2 !== first) mixed = true;
        }
        if (mixed) checked = false;
      }
      var row = document.createElement('label');
      row.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;font-size:12px;';
      row.innerHTML = '<input type="checkbox" data-finish-key="' + key + '" ' + (checked ? 'checked' : '') + '> ' + key;
      host.appendChild(row);
    });
    if (!isBoundary && wallIds.length === 1) {
      var sidesRow = document.createElement('label');
      sidesRow.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;margin-top:4px;font-size:12px;';
      var sides = flags.baseboardSides != null ? Number(flags.baseboardSides) : 2;
      sidesRow.innerHTML = 'Baseboard sides <select data-finish-sides style="min-height:44px;"><option value="1">1 side</option><option value="2">2 sides</option></select>';
      host.appendChild(sidesRow);
      var sel = host.querySelector('[data-finish-sides]');
      if (sel) sel.value = String(sides === 1 ? 1 : 2);
    }
    host.querySelectorAll('input[data-finish-key]').forEach(function (input) {
      input.addEventListener('change', function () {
        var key = input.getAttribute('data-finish-key');
        var val = input.checked;
        writePlan(function (plan) {
          plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
          wallIds.forEach(function (wallId) {
            plan.zanini.wallFinishes[wallId] = plan.zanini.wallFinishes[wallId] || {};
            plan.zanini.wallFinishes[wallId][key] = val;
          });
        });
      });
    });
    var sidesSelect = host.querySelector('[data-finish-sides]');
    if (sidesSelect) {
      sidesSelect.addEventListener('change', function () {
        writePlan(function (plan) {
          plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
          plan.zanini.wallFinishes[wallIds[0]] = plan.zanini.wallFinishes[wallIds[0]] || {};
          plan.zanini.wallFinishes[wallIds[0]].baseboardSides = Number(sidesSelect.value) === 1 ? 1 : 2;
        });
      });
    }
    var bbChecked = Object.prototype.hasOwnProperty.call(flags, 'baseboard') ? !!flags.baseboard : defaults.baseboard;
    if (bbChecked && wallLenLf > 0 && wallIds.length === 1) {
      var sidesCount = flags.baseboardSides != null ? Number(flags.baseboardSides) : 2;
      if (sidesCount !== 1) sidesCount = 2;
      var wallBbLf = wallLenLf * sidesCount;
      var info = document.createElement('p');
      info.style.cssText = 'margin:6px 0 0;opacity:.85;font-size:11px;';
      info.textContent = 'Baseboard on this wall: ' + wallBbLf.toFixed(2) + ' lf';
      host.appendChild(info);
    }
  }

  function sidebarMutationIsOwnFinishHost(mutations) {
    if (!state.finishHost) return false;
    for (var i = 0; i < mutations.length; i++) {
      var m = mutations[i];
      var target = m.target;
      if (target === state.finishHost) return true;
      if (target && target.nodeType === 1 && state.finishHost.contains(target)) return true;
    }
    return false;
  }

  function watchWallSidebar() {
    if (state.sidebarObserver) return;
    state.sidebarObserver = new MutationObserver(function (mutations) {
      if (state.sidebarObserverPaused) return;
      if (sidebarMutationIsOwnFinishHost(mutations)) return;
      var sidebar = findWallSidebarHost();
      if (!sidebar) return;
      if (state.finishHost && sidebar.contains(state.finishHost)) return;
      state.finishHost = null;
      renderFinishPanel();
    });
    state.sidebarObserver.observe(document.body, { childList: true, subtree: true });
  }

  function ensureWallSelectionHook() {
    if (state.wallHooked) return;
    var mod = window.__openPlan3DStoreModule;
    if (!mod) return;
    state.wallHooked = true;
    function onSelectionChange() {
      renderFinishPanel();
    }
    if (mod.bg && typeof mod.bg.subscribe === 'function') mod.bg.subscribe(onSelectionChange);
    if (mod.X && typeof mod.X.subscribe === 'function') mod.X.subscribe(onSelectionChange);
    if (mod.aB && typeof mod.aB.subscribe === 'function') mod.aB.subscribe(onSelectionChange);
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
      watchWallSidebar();
      renderFinishPanel();
    });
  }

  function mergeZones(sourceId, targetId) {
    writePlan(function (plan) {
      var zones = plan.zanini.zones || [];
      var source = null;
      var target = null;
      for (var i = 0; i < zones.length; i++) {
        if (zones[i].id === sourceId) source = zones[i];
        if (zones[i].id === targetId) target = zones[i];
      }
      if (!source || !target || sourceId === targetId) return;
      target.rects = Array.isArray(target.rects) ? target.rects.slice() : [];
      if (!target.rects.length) {
        var legacy = legacyRectFromZone(target);
        if (legacy) target.rects.push(legacy);
      }
      var legacySource = legacyRectFromZone(source);
      if (legacySource) target.rects.push(legacySource);
      if (!target.color && source.color) target.color = source.color;
      var b = zoneUnionBounds(target, zones);
      if (b) {
        target.x1 = b.x1;
        target.y1 = b.y1;
        target.x2 = b.x2;
        target.y2 = b.y2;
      }
      source.mergedInto = targetId;
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
    watchWallSidebar();
    var overlay = ensureOverlay();
    overlay.addEventListener('click', onOverlayClick);
    scheduleOverlay();
    window.addEventListener('resize', scheduleOverlay);
    setInterval(scheduleOverlay, 500);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        setMode(null);
        state.selectedBulkheadId = null;
        scheduleOverlay();
        return;
      }
      if ((e.key === 'Delete' || e.key === 'Backspace') && state.selectedBulkheadId && !isEditableTarget(e.target)) {
        e.preventDefault();
        deleteSelectedBulkhead();
      }
    });
  }

  function isEditableTarget(el) {
    if (!el || !el.closest) return false;
    return !!el.closest('input, textarea, select, [contenteditable="true"]');
  }

  var skipBrowserInit = typeof process !== 'undefined' && process.env && process.env.OPENPLAN3D_TOOLS_TEST === '1';
  if (!skipBrowserInit) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  }

  var exports = {
    CM_PER_FOOT: CM_PER_FOOT,
    axisRect: axisRect,
    rectsOverlap: rectsOverlap,
    findOverlappingZone: findOverlappingZone,
    mergeRectIntoZone: mergeRectIntoZone,
    addZoneFromPreview: addZoneFromPreview,
    zoneAreaSqFt: zoneAreaSqFt,
    zoneUnionBounds: zoneUnionBounds,
    readSelectedWallIds: readSelectedWallIds,
    isEditor3DViewActive: isEditor3DViewActive,
    mergeZones: mergeZones,
    toggleZoneExclude: toggleZoneExclude,
    renderFinishPanel: renderFinishPanel,
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

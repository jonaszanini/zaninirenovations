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
    wallHooked: false,
    writingPlan: false,
    selectionRestoreGen: 0
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
      var members = collectZoneMemberRects(zone, zones);
      for (var mi = 0; mi < members.length; mi++) {
        if (rectsOverlap(members[mi], rect)) return zone;
      }
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

  function invalidatePendingSelectionRestore() {
    state.selectionRestoreGen += 1;
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
    // setProject can wipe vendor wall selection. Restore the snapshot so the
    // finish panel stays on the wall that was just edited — but only if the
    // user/test has not selected or cleared walls since this write.
    var savedSelection = captureWallSelection();
    var restoreGen = ++state.selectionRestoreGen;
    state.writingPlan = true;
    try {
      mutator(plan);
      api.setProject(plan);
      postPlanChanged(plan);
      if (savedSelection.length) restoreWallSelection(savedSelection);
    } finally {
      state.writingPlan = false;
    }
    requestAnimationFrame(function () {
      if (restoreGen !== state.selectionRestoreGen) return;
      var now = captureWallSelection();
      if (!now.length && savedSelection.length) {
        state.writingPlan = true;
        try {
          restoreWallSelection(savedSelection);
        } finally {
          state.writingPlan = false;
        }
      }
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

  function collectZoneMemberRects(zone, zones) {
    var ext = typeof window !== 'undefined' && window.OpenPlan3DZaniniExtensions;
    if (ext && typeof ext.collectZoneMemberLegacyRects === 'function') {
      return ext.collectZoneMemberLegacyRects(zone, zones);
    }
    var out = [];
    var seen = {};
    function pushShape(shape) {
      var a = axisRect(shape);
      if (!a) return;
      var key = [a.x1, a.y1, a.x2, a.y2].join('|');
      if (seen[key]) return;
      seen[key] = true;
      out.push({ x1: a.x1, y1: a.y1, x2: a.x2, y2: a.y2 });
    }
    if (Array.isArray(zone.rects) && zone.rects.length) {
      for (var r = 0; r < zone.rects.length; r++) {
        pushShape(zone.rects[r]);
      }
    } else {
      pushShape(zone);
    }
    for (var j = 0; j < (zones || []).length; j++) {
      var merged = zones[j];
      if (merged && merged.mergedInto === zone.id) pushShape(merged);
    }
    return out;
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
      if (!bounds) return null;
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
      return el;
    }

    zones.forEach(function (z) {
      if (!z || z.mergedInto) return;
      var color = z.color || pickZoneColor(zones);
      var sqft = zoneAreaSqFt(z, zones, CM_PER_FOOT);
      var label = (z.name || 'Zone') + ' · ' + sqft.toFixed(1) + ' sf';
      var memberRects = collectZoneMemberRects(z, zones);
      var labelPlaced = false;
      for (var ri = 0; ri < memberRects.length; ri++) {
        var rect = memberRects[ri];
        var el = drawBox(rect, {
          className: 'zanini-zone-rect',
          fill: color,
          border: 'rgba(15, 23, 42, 0.55)',
          dashed: true,
          label: !labelPlaced ? label : null
        });
        if (el && !labelPlaced) labelPlaced = true;
      }
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

  function updateDrawCursor() {
    var drawing = state.mode === 'zone' || state.mode === 'bulkhead';
    if (typeof document !== 'undefined' && document.documentElement) {
      document.documentElement.classList.toggle('openplan3d-zanini-draw-mode', drawing);
    }
    var canvas = canvasEl();
    if (canvas) canvas.style.cursor = drawing ? 'crosshair' : '';
    var overlay = state.overlay || (typeof document !== 'undefined' ? document.getElementById('openplan3d-zanini-overlay') : null);
    if (overlay) overlay.style.cursor = drawing ? 'crosshair' : '';
  }

  function setMode(mode, options) {
    options = options || {};
    if (mode == null || mode === '') {
      state.mode = null;
    } else if (options.explicit) {
      state.mode = mode;
    } else {
      state.mode = state.mode === mode ? null : mode;
    }
    var toggleWrap = document.getElementById('openplan3d-draw-toggle');
    if (toggleWrap) {
      [].forEach.call(toggleWrap.querySelectorAll('[data-zanini-mode]'), function (btn) {
        var isActive = btn.getAttribute('data-zanini-mode') === state.mode;
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    }
    var floatBar = document.getElementById('openplan3d-zanini-toolbar');
    if (floatBar) {
      [].forEach.call(floatBar.querySelectorAll('[data-zanini-mode]'), function (btn) {
        btn.classList.toggle('zanini-tool-active', btn.getAttribute('data-zanini-mode') === state.mode);
      });
      var floatDel = floatBar.querySelector('[data-zanini-action="delete-bulkhead"]');
      if (floatDel) floatDel.hidden = !state.selectedBulkheadId;
    }
    var delBtn = document.getElementById('openplan3d-delete-bulkhead-btn');
    if (delBtn) delBtn.hidden = !state.selectedBulkheadId;
    updateDrawCursor();
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
    renderFinishPanel();
  }

  function onOverlayClick(e) {
    var hit = e.target.closest('[data-zanini-bulkhead-id]');
    if (!hit || state.mode) return;
    state.selectedBulkheadId = hit.getAttribute('data-zanini-bulkhead-id');
    scheduleOverlay();
    var delBtn = document.getElementById('openplan3d-delete-bulkhead-btn');
    if (delBtn) delBtn.hidden = !state.selectedBulkheadId;
    renderFinishPanel();
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
        renderFinishPanel();
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
      'html.openplan3d-zanini-draw-mode canvas[data-plan3d-canvas="true"],html.openplan3d-zanini-draw-mode canvas{cursor:crosshair!important;}',
      'html.openplan3d-zanini-draw-mode #openplan3d-zanini-overlay,html.openplan3d-zanini-draw-mode #openplan3d-zanini-overlay *{cursor:crosshair!important;}',
      '#openplan3d-zanini-overlay .zanini-bulkhead-rect{cursor:pointer;}',
      '#openplan3d-zanini-toolbar .zanini-tool-active{background:#024768!important;}',
      '#openplan3d-zanini-toolbar{position:fixed;left:50%;top:12px;transform:translateX(-50%);display:flex;gap:6px;padding:6px;background:rgba(15,23,42,.88);border-radius:10px;z-index:45;box-shadow:0 8px 24px rgba(0,0,0,.35);}',
      '#openplan3d-zanini-toolbar button{white-space:nowrap;font-size:11px;padding:0 8px;min-height:44px;min-width:44px;border-radius:8px;border:0;background:#1e293b;color:#f8fafc;font-weight:600;}',
      '.admin-info-tip-wrap{position:relative;display:inline-flex;align-items:center;}',
      '.admin-info-tip{min-width:24px;min-height:24px;width:24px;height:24px;padding:0;border:1px solid #94a3b8;border-radius:50%;background:#fff;color:#334155;font-size:14px;line-height:22px;text-align:center;cursor:pointer;}',
      '.admin-info-tip__bubble{position:absolute;left:0;top:calc(100% + 6px);z-index:80;min-width:220px;max-width:min(92vw,320px);padding:8px 10px;border-radius:8px;background:#0f172a;color:#f8fafc;font-size:12px;line-height:1.4;box-shadow:0 8px 24px rgba(0,0,0,.35);}',
      '.admin-info-tip-wrap.is-open .admin-info-tip__bubble[hidden]{display:block;}',
      '#openplan3d-zanini-finish-host{margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,.35);}'
    ].join('');
    document.head.appendChild(style);
  }

  function buildFloatingToolbarFallback() {
    if (document.getElementById('openplan3d-zanini-toolbar')) {
      state.toolbar = document.getElementById('openplan3d-zanini-toolbar');
      return state.toolbar;
    }
    var bar = document.createElement('div');
    bar.id = 'openplan3d-zanini-toolbar';
    bar.innerHTML = [
      '<button type="button" data-zanini-mode="zone" title="Draw area zone">Zone</button>',
      '<button type="button" data-zanini-mode="bulkhead" title="Draw bulkhead">Bulkhead</button>',
      '<button type="button" data-zanini-action="delete-bulkhead" title="Delete selected bulkhead" hidden>Del</button>'
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

  function wireHeaderToolbar() {
    if (state.toolbar) return;
    injectToolbarStyles();
    if (typeof window !== 'undefined' && window.parent && window.parent !== window) {
      state.toolbar = { hosted: true };
      return;
    }
    var toggleWrap = document.getElementById('openplan3d-draw-toggle');
    var delBtn = document.getElementById('openplan3d-delete-bulkhead-btn');
    if (toggleWrap) {
      toggleWrap.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-zanini-mode]');
        if (!btn) return;
        setMode(btn.getAttribute('data-zanini-mode'));
      });
      if (delBtn) {
        delBtn.addEventListener('click', function () {
          deleteSelectedBulkhead();
        });
      }
      state.toolbar = toggleWrap;
      return;
    }
    buildFloatingToolbarFallback();
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
        var narrow = window.innerWidth < 520;
        var sideOk = narrow || rect.left > window.innerWidth * 0.45;
        if (rect.width >= 120 && rect.height >= 80 && sideOk
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
      if (window.innerWidth >= 520 && r.left < window.innerWidth * 0.5) continue;
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
    var bridge = typeof window !== 'undefined' && window.OpenPlan3DMeasurementBridge;
    if (bridge && typeof bridge.floorBoundaryWallIds === 'function') {
      var loopIds = bridge.floorBoundaryWallIds(floor, plan);
      if (loopIds.length) {
        return loopIds.indexOf(String(wallId)) >= 0;
      }
    }
    var rooms = floor.rooms || [];
    for (var ri = 0; ri < rooms.length; ri++) {
      var room = rooms[ri];
      if (!room) continue;
      var ids = room.boundary_wall_ids || [];
      for (var bi = 0; bi < ids.length; bi++) {
        if (String(ids[bi]) === String(wallId)) return true;
      }
    }
    return false;
  }

  function finishDefaultsForWall(wallId) {
    var isBoundary = wallIsBoundary(wallId);
    return {
      drywall: true,
      insulation: isBoundary,
      studs: !isBoundary,
      baseboard: true
    };
  }

  function roomsTouchingWall(wallId, floor) {
    var hits = [];
    if (!floor || !floor.rooms) return hits;
    floor.rooms.forEach(function (room) {
      var ids = [];
      var seen = {};
      var merge = function (list) {
        if (!Array.isArray(list)) return;
        for (var j = 0; j < list.length; j++) {
          var id = String(list[j]);
          if (!seen[id]) {
            seen[id] = true;
            ids.push(id);
          }
        }
      };
      merge(room.boundary_wall_ids);
      merge(room.walls);
      for (var i = 0; i < ids.length; i++) {
        if (String(ids[i]) === String(wallId)) {
          hits.push(String(room.name || room.id || 'Room').trim() || 'Room');
          break;
        }
      }
    });
    return hits;
  }

  function wallIsInteriorPartition(wallId, floor) {
    if (wallIsBoundary(wallId)) return false;
    if (roomsTouchingWall(wallId, floor).length >= 2) return true;
    var plan = readPlan();
    var bridge = typeof window !== 'undefined' && window.OpenPlan3DMeasurementBridge;
    if (bridge && typeof bridge.wallSeparatesTwoSpaces === 'function') {
      return bridge.wallSeparatesTwoSpaces(floor, wallId, plan);
    }
    return false;
  }

  function wallsEligibleForBaseboardSidesControl(wallIds, floor) {
    if (!floor) floor = activeFloor(readPlan());
    return (wallIds || []).filter(function (wallId) {
      return wallIsInteriorPartition(wallId, floor);
    });
  }

  function friendlyWallFinishHeading(wallIds, floor) {
    if (!floor) floor = activeFloor(readPlan());
    if (!wallIds || wallIds.length !== 1) {
      return wallIds.length > 1
        ? 'Wall finishes (' + wallIds.length + ' walls)'
        : 'Wall finishes';
    }
    var names = roomsTouchingWall(wallIds[0], floor);
    if (names.length >= 2) return 'Wall finishes · ' + names.join(' / ');
    if (names.length === 1) return 'Wall finishes · ' + names[0];
    return 'Wall finishes · partition';
  }

  function readBaseboardSidesForWall(wallFinishes, wallId) {
    var flags = (wallFinishes && wallFinishes[wallId]) || {};
    var plan = readPlan();
    var floor = activeFloor(plan);
    var touchCount = roomsTouchingWall(wallId, floor).length;
    var bridge = typeof window !== 'undefined' && window.OpenPlan3DMeasurementBridge;
    if (bridge && typeof bridge.geometryRoomTouchCount === 'function') {
      touchCount = bridge.geometryRoomTouchCount(wallId, floor, plan);
    }
    var ext = typeof window !== 'undefined' && window.OpenPlan3DZaniniExtensions;
    if (ext && typeof ext.resolveBaseboardSides === 'function') {
      return ext.resolveBaseboardSides(flags, wallIsBoundary(wallId), touchCount);
    }
    if (wallIsBoundary(wallId)) return 1;
    if (touchCount < 2) return 1;
    return flags.baseboardSides != null ? Number(flags.baseboardSides) : 2;
  }

  function roomNamesOnWall(wallId, floor) {
    return roomsTouchingWall(wallId, floor);
  }

  function drywallSideOptionLabels(wallId, floor) {
    var adj = roomNamesOnWall(wallId, floor);
    return {
      a: adj[0] ? adj[0] + ' side' : 'This side',
      b: adj[1] ? adj[1] + ' side' : (adj.length === 1 ? 'Other side' : 'Other side'),
      tipRooms: adj.length ? adj.join(' and ') : 'each adjacent room'
    };
  }

  function readDrywallFacesForWall(wallFinishes, wallId) {
    var flags = (wallFinishes && wallFinishes[wallId]) || {};
    var ext = typeof window !== 'undefined' && window.OpenPlan3DZaniniExtensions;
    if (ext && typeof ext.resolveDrywallFaces === 'function') {
      return ext.resolveDrywallFaces(flags, wallIsBoundary(wallId));
    }
    var faces = flags.drywallFaces != null ? String(flags.drywallFaces).toLowerCase() : 'both';
    return faces === 'a' || faces === 'b' ? faces : 'both';
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

  function renderBulkheadFinishPanelNow(host) {
    var id = state.selectedBulkheadId;
    var plan = readPlan();
    var bulkheads = (plan && plan.zanini && plan.zanini.bulkheads) || [];
    var bulkhead = null;
    for (var i = 0; i < bulkheads.length; i++) {
      if (bulkheads[i] && String(bulkheads[i].id) === String(id)) {
        bulkhead = bulkheads[i];
        break;
      }
    }
    if (!bulkhead) {
      host.innerHTML = '';
      host.hidden = true;
      return;
    }
    host.hidden = false;
    var hasKey = Object.prototype.hasOwnProperty.call(bulkhead, 'paint');
    var checked = hasKey ? !!bulkhead.paint : false;
    host.innerHTML = '<div style="font-weight:700;margin-bottom:6px;font-size:12px;">Bulkhead finishes · '
      + (bulkhead.name || id) + '</div>';
    var row = document.createElement('label');
    row.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;font-size:12px;';
    row.innerHTML = '<input type="checkbox" data-bulkhead-paint="1"' + (checked ? ' checked' : '') + '> Paint';
    host.appendChild(row);
    var input = row.querySelector('input');
    if (input && !hasKey) input.indeterminate = true;
    var note = document.createElement('p');
    note.style.cssText = 'margin:4px 0 0;opacity:.85;font-size:11px;line-height:1.35;';
    note.textContent = 'Unchecked/unassigned bulkheads are not added to wall paint. Drywall takeoff is separate.';
    host.appendChild(note);
    if (input) {
      input.addEventListener('change', function () {
        var val = input.checked;
        writePlan(function (next) {
          var list = next.zanini.bulkheads || [];
          for (var bi = 0; bi < list.length; bi++) {
            if (list[bi] && String(list[bi].id) === String(id)) {
              list[bi].paint = val;
            }
          }
        });
      });
    }
  }

  function renderFinishPanelNow() {
    var wallIds = readSelectedWallIds();
    var host = ensureFinishHost();
    if (!host) return;
    if (!wallIds.length && state.selectedBulkheadId) {
      host.removeAttribute('data-finish-walls');
      renderBulkheadFinishPanelNow(host);
      return;
    }
    if (!wallIds.length) {
      host.innerHTML = '';
      host.hidden = true;
      host.removeAttribute('data-finish-walls');
      return;
    }
    host.hidden = false;
    state.selectedWallIds = wallIds.slice();
    var plan = readPlan();
    var zanini = (plan && plan.zanini) || {};
    var wallFinishes = zanini.wallFinishes || {};
    var primary = wallIds[0];
    var flags = wallFinishes[primary] || {};
    var defaults = finishDefaultsForWall(primary);
    var floor = activeFloor(plan);
    var sidesEligible = wallsEligibleForBaseboardSidesControl(wallIds, floor);
    var wallLenLf = 0;
    var interiorGeom = typeof RoomAreaInteriorGeometry !== 'undefined' ? RoomAreaInteriorGeometry : null;
    if (floor && Array.isArray(floor.walls)) {
      var cmPerUnit = 1.0;
      var unit = String(plan.unit || plan.units || (floor && floor.units) || (floor && floor.unit) || 'cm').toLowerCase();
      if (unit === 'ft' || unit === 'feet' || unit === 'foot') cmPerUnit = CM_PER_FOOT;
      for (var wi = 0; wi < floor.walls.length; wi++) {
        if (String(floor.walls[wi].id) === String(primary)) {
          var wObj = floor.walls[wi];
          if (interiorGeom && typeof interiorGeom.wallPlanEdgeLengthCm === 'function') {
            wallLenLf = interiorGeom.wallPlanEdgeLengthCm(wObj, floor.walls, cmPerUnit, true) / CM_PER_FOOT;
          } else {
            wallLenLf = Math.hypot(wObj.end.x - wObj.start.x, wObj.end.y - wObj.start.y) / CM_PER_FOOT;
          }
          break;
        }
      }
    }
    var heading = friendlyWallFinishHeading(wallIds, floor);
    var finishKeys = ['drywall', 'paint', 'insulation', 'studs', 'baseboard'];
    var finishLabels = {
      drywall: 'Drywall',
      paint: 'Paint',
      insulation: 'Insulation',
      studs: 'Studs',
      baseboard: 'Baseboard'
    };
    function finishBoxState(key) {
      var defaultVal = key === 'paint' ? false : defaults[key];
      var hasKey = Object.prototype.hasOwnProperty.call(flags, key);
      var checked = hasKey ? !!flags[key] : defaultVal;
      var indeterminate = key === 'paint' && !hasKey;
      if (wallIds.length > 1) {
        var mixed = false;
        var firstState = hasKey ? (checked ? 'on' : 'off') : (key === 'paint' ? 'unassigned' : (defaultVal ? 'on' : 'off'));
        for (var wi2 = 1; wi2 < wallIds.length; wi2++) {
          var f2 = wallFinishes[wallIds[wi2]] || {};
          var wallDefaults = finishDefaultsForWall(wallIds[wi2]);
          var wallHas = Object.prototype.hasOwnProperty.call(f2, key);
          var wallDefault = key === 'paint' ? false : wallDefaults[key];
          var state2 = wallHas ? (f2[key] ? 'on' : 'off') : (key === 'paint' ? 'unassigned' : (wallDefault ? 'on' : 'off'));
          if (state2 !== firstState) mixed = true;
        }
        if (mixed) {
          checked = false;
          indeterminate = key === 'paint';
        }
      }
      return { checked: checked, indeterminate: indeterminate };
    }
    function onFinishChange(ev) {
      var input = ev && ev.target;
      if (!input || !input.getAttribute) return;
      var key = input.getAttribute('data-finish-key');
      var val = input.checked;
      writePlan(function (plan) {
        plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
        wallIds.forEach(function (wallId) {
          plan.zanini.wallFinishes[wallId] = plan.zanini.wallFinishes[wallId] || {};
          plan.zanini.wallFinishes[wallId][key] = val;
          if (key === 'baseboard' && wallIsBoundary(wallId)) {
            plan.zanini.wallFinishes[wallId].baseboardSides = 1;
          } else if (key === 'baseboard' && wallIsInteriorPartition(wallId, activeFloor(plan))) {
            if (!Object.prototype.hasOwnProperty.call(plan.zanini.wallFinishes[wallId], 'baseboardSides')) {
              plan.zanini.wallFinishes[wallId].baseboardSides = 2;
            }
          }
        });
      });
    }
    var wallsKey = wallIds.join('\n');
    if (host.getAttribute('data-finish-walls') === wallsKey && host.querySelector('input[data-finish-key="paint"]')) {
      finishKeys.forEach(function (key) {
        var existing = host.querySelector('input[data-finish-key="' + key + '"]');
        if (!existing || !existing.isConnected) return;
        var st = finishBoxState(key);
        existing.indeterminate = !!st.indeterminate;
        existing.checked = !!st.checked;
      });
      return;
    }
    host.setAttribute('data-finish-walls', wallsKey);
    host.innerHTML = '<div style="font-weight:700;margin-bottom:6px;font-size:12px;">' + heading + '</div>';
    finishKeys.forEach(function (key) {
      var st = finishBoxState(key);
      var row = document.createElement('label');
      row.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;font-size:12px;';
      var inputEl = document.createElement('input');
      inputEl.type = 'checkbox';
      inputEl.setAttribute('data-finish-key', key);
      if (st.checked) inputEl.checked = true;
      inputEl.addEventListener('change', onFinishChange);
      row.appendChild(inputEl);
      row.appendChild(document.createTextNode(' ' + (finishLabels[key] || key)));
      host.appendChild(row);
      if (st.indeterminate) inputEl.indeterminate = true;
    });
    if (sidesEligible.length === 0 && wallIds.length > 0 && wallIds.some(wallIsBoundary)) {
      var perimeterRow = document.createElement('div');
      perimeterRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin:4px 0 0;font-size:12px;';
      perimeterRow.innerHTML =
        '<span>Perimeter baseboard</span>' +
        '<span class="admin-info-tip-wrap" data-finish-perimeter-tip-wrap>' +
        '<button type="button" class="admin-info-tip" data-finish-perimeter-tip aria-label="Perimeter baseboard help">&#9432;</button>' +
        '<span class="admin-info-tip__bubble" role="tooltip" hidden>Exterior walls measure baseboard on the interior face only. The outside face has none.</span></span>';
      host.appendChild(perimeterRow);
      var perTip = perimeterRow.querySelector('[data-finish-perimeter-tip-wrap]');
      if (perTip) {
        var perBtn = perTip.querySelector('.admin-info-tip');
        var perBubble = perTip.querySelector('.admin-info-tip__bubble');
        if (perBtn && perBubble) {
          perBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var open = perTip.classList.toggle('is-open');
            perBubble.hidden = !open;
          });
        }
      }
    }
    if (sidesEligible.length > 0) {
      var drywallPrimary = readDrywallFacesForWall(wallFinishes, sidesEligible[0]);
      var drywallMixed = false;
      if (sidesEligible.length > 1) {
        for (var di = 1; di < sidesEligible.length; di++) {
          if (readDrywallFacesForWall(wallFinishes, sidesEligible[di]) !== drywallPrimary) {
            drywallMixed = true;
            break;
          }
        }
      }
      var drywallRow = document.createElement('div');
      drywallRow.style.cssText = 'display:flex;align-items:flex-start;gap:6px;min-height:44px;margin-top:6px;font-size:12px;flex-wrap:wrap;';
      var drywallLabel = sidesEligible.length === wallIds.length
        ? 'Drywall faces'
        : ('Drywall faces (' + sidesEligible.length + ' partition wall' + (sidesEligible.length === 1 ? '' : 's') + ')');
      var sideWall = null;
      for (var swi = 0; floor && swi < floor.walls.length; swi++) {
        if (String(floor.walls[swi].id) === String(sidesEligible[0])) {
          sideWall = floor.walls[swi];
          break;
        }
      }
      var sideInfo = drywallSideOptionLabels(sidesEligible[0], floor);
      var sideLabels = { a: sideInfo.a, b: sideInfo.b };
      drywallRow.innerHTML =
        '<span>' + drywallLabel + '</span>' +
        '<span class="admin-info-tip-wrap openplan3d-finish-drywall-tip-wrap" data-finish-drywall-tip-wrap>' +
        '<button type="button" class="admin-info-tip openplan3d-finish-drywall-tip" data-finish-drywall-tip aria-label="Drywall faces help">&#9432;</button>' +
        '<span class="admin-info-tip__bubble openplan3d-finish-drywall-tip__bubble" role="tooltip" hidden>Interior partitions default to drywall on both faces. ' +
        'Choose a single face when only one room gets drywall; labels match adjacent rooms (' + sideInfo.tipRooms + '). ' +
        'Exterior perimeter walls always measure the interior face only.</span></span>' +
        '<select data-finish-drywall-faces style="min-height:44px;flex:1;">' +
        '<option value="both">Both sides</option>' +
        '<option value="a">' + sideLabels.a + '</option>' +
        '<option value="b">' + sideLabels.b + '</option></select>';
      host.appendChild(drywallRow);
      var tipWrap = host.querySelector('[data-finish-drywall-tip-wrap]');
      if (tipWrap) {
        var tipBtn = tipWrap.querySelector('.admin-info-tip');
        var tipBubble = tipWrap.querySelector('.admin-info-tip__bubble');
        if (tipBtn && tipBubble) {
          tipBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var open = tipWrap.classList.toggle('is-open');
            tipBubble.hidden = !open;
          });
        }
      }
      var drywallSel = host.querySelector('[data-finish-drywall-faces]');
      if (drywallSel) {
        if (drywallMixed) {
          drywallSel.value = 'both';
          drywallSel.selectedIndex = -1;
        } else {
          drywallSel.value = drywallPrimary === 'a' || drywallPrimary === 'b' ? drywallPrimary : 'both';
        }
        drywallSel.addEventListener('change', function () {
          var faceVal = String(drywallSel.value || 'both').toLowerCase();
          if (faceVal !== 'a' && faceVal !== 'b') faceVal = 'both';
          writePlan(function (plan) {
            plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
            sidesEligible.forEach(function (wallId) {
              plan.zanini.wallFinishes[wallId] = plan.zanini.wallFinishes[wallId] || {};
              plan.zanini.wallFinishes[wallId].drywallFaces = faceVal;
            });
          });
        });
      }
      var sidesRow = document.createElement('label');
      sidesRow.style.cssText = 'display:flex;align-items:center;gap:8px;min-height:44px;margin-top:4px;font-size:12px;';
      var sidesPrimary = readBaseboardSidesForWall(wallFinishes, sidesEligible[0]);
      var sidesMixed = false;
      if (sidesEligible.length > 1) {
        for (var si = 1; si < sidesEligible.length; si++) {
          if (readBaseboardSidesForWall(wallFinishes, sidesEligible[si]) !== sidesPrimary) {
            sidesMixed = true;
            break;
          }
        }
      }
      var sidesLabel = sidesEligible.length === wallIds.length
        ? 'Baseboard sides'
        : ('Baseboard sides (' + sidesEligible.length + ' partition wall' + (sidesEligible.length === 1 ? '' : 's') + ')');
      sidesRow.innerHTML = sidesLabel + ' <select data-finish-sides style="min-height:44px;"><option value="1">1 side</option><option value="2">2 sides</option></select>';
      host.appendChild(sidesRow);
      var sel = host.querySelector('[data-finish-sides]');
      if (sel) {
        if (sidesMixed) {
          sel.value = '2';
          sel.selectedIndex = -1;
        } else {
          sel.value = String(sidesPrimary === 1 ? 1 : 2);
        }
      }
    }
    var sidesSelect = host.querySelector('[data-finish-sides]');
    if (sidesSelect) {
      sidesSelect.addEventListener('change', function () {
        var sidesVal = Number(sidesSelect.value) === 1 ? 1 : 2;
        writePlan(function (plan) {
          plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
          sidesEligible.forEach(function (wallId) {
            plan.zanini.wallFinishes[wallId] = plan.zanini.wallFinishes[wallId] || {};
            plan.zanini.wallFinishes[wallId].baseboardSides = sidesVal;
          });
        });
      });
    }
    var bbChecked = Object.prototype.hasOwnProperty.call(flags, 'baseboard') ? !!flags.baseboard : defaults.baseboard;
    if (bbChecked && wallLenLf > 0 && wallIds.length === 1 && !wallIsBoundary(primary)) {
      var sidesCount = readBaseboardSidesForWall(wallFinishes, primary);
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
      if (!state.writingPlan) invalidatePendingSelectionRestore();
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
    updateDrawCursor();
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
    wireHeaderToolbar();
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
        invalidatePendingSelectionRestore();
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
    collectZoneMemberRects: collectZoneMemberRects,
    zoneAreaSqFt: zoneAreaSqFt,
    zoneUnionBounds: zoneUnionBounds,
    wallIsBoundary: wallIsBoundary,
    wallIsInteriorPartition: wallIsInteriorPartition,
    finishDefaultsForWall: finishDefaultsForWall,
    wallsEligibleForBaseboardSidesControl: wallsEligibleForBaseboardSidesControl,
    readBaseboardSidesForWall: readBaseboardSidesForWall,
    readSelectedWallIds: readSelectedWallIds,
    writePlan: writePlan,
    invalidatePendingSelectionRestore: invalidatePendingSelectionRestore,
    isEditor3DViewActive: isEditor3DViewActive,
    mergeZones: mergeZones,
    toggleZoneExclude: toggleZoneExclude,
    renderFinishPanel: renderFinishPanel,
    refreshOverlay: scheduleOverlay,
    setMode: setMode,
    getMode: function () { return state.mode; },
    updateDrawCursor: updateDrawCursor,
    deleteSelectedBulkhead: deleteSelectedBulkhead,
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

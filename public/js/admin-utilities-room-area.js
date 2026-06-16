/* Room Area tool — Konva polygon drawing with scale calibration */
(function () {
  'use strict';

  if (typeof Konva === 'undefined') return;

  var root = document.querySelector('[data-room-area]');
  if (!root) return;

  var INCHES_PER_FOOT = 12;
  var SQFT_TO_SQM = 0.092903;
  var MIN_CANVAS_W = 400;
  var MIN_CANVAS_H = 420;
  var MAX_CANVAS_H = 680;
  var GRID_PX = 20;
  var VERTEX_HIT = 14;
  var CLOSE_HIT = 16;
  var WALL_HIT = 14;
  var MIN_ZOOM = 0.35;
  var MAX_ZOOM = 5;
  var ZOOM_FACTOR = 1.12;
  var SHIFT_SNAP_THRESHOLD_DEG = 10;
  var SHIFT_SNAP_STEP = Math.PI / 4;

  var els = {
    projectSelect: root.querySelector('[data-ra-project-select]'),
    newProjectToggle: root.querySelector('[data-ra-new-project-toggle]'),
    newProjectPanel: root.querySelector('[data-ra-new-project]'),
    newProjectName: root.querySelector('#ra-new-project-name'),
    newClientProject: root.querySelector('[data-ra-new-client-project]'),
    createProjectBtn: root.querySelector('[data-ra-create-project]'),
    deleteProjectBtn: root.querySelector('[data-ra-delete-project]'),
    linked: root.querySelector('[data-ra-linked]'),
    linkedLink: root.querySelector('[data-ra-linked-link]'),
    linkClientProject: root.querySelector('[data-ra-link-client-project]'),
    empty: root.querySelector('[data-ra-empty]'),
    workspace: root.querySelector('[data-ra-workspace]'),
    calibrateToggle: root.querySelector('[data-ra-calibrate-toggle]'),
    knownLength: root.querySelector('[data-ra-known-length]'),
    applyScale: root.querySelector('[data-ra-apply-scale]'),
    scaleStatus: root.querySelector('[data-ra-scale-status]'),
    bgFile: root.querySelector('[data-ra-bg-file]'),
    bgRemove: root.querySelector('[data-ra-bg-remove]'),
    bgDeleteCanvas: root.querySelector('[data-ra-bg-delete-canvas]'),
    snapGroup: root.querySelector('[data-ra-snap-group]'),
    undoVertex: root.querySelector('[data-ra-undo-vertex]'),
    closeShape: root.querySelector('[data-ra-close-shape]'),
    clearShape: root.querySelector('[data-ra-clear-shape]'),
    deleteWall: root.querySelector('[data-ra-delete-wall]'),
    canvasWrap: root.querySelector('[data-ra-canvas-wrap]'),
    liveArea: root.querySelector('[data-ra-live-area]'),
    liveSqft: root.querySelector('[data-ra-live-sqft]'),
    liveSqm: root.querySelector('[data-ra-live-sqm]'),
    feedback: root.querySelector('[data-ra-feedback]'),
    roomName: root.querySelector('[data-ra-room-name]'),
    saveRoom: root.querySelector('[data-ra-save-room]'),
    saveCardTitle: root.querySelector('[data-ra-save-card-title]'),
    cancelEdit: root.querySelector('[data-ra-cancel-edit]'),
    newRoom: root.querySelector('[data-ra-new-room]'),
    newRoomSecondary: root.querySelector('[data-ra-new-room-secondary]'),
    roomsEmpty: root.querySelector('[data-ra-rooms-empty]'),
    roomsList: root.querySelector('[data-ra-rooms-list]'),
    total: root.querySelector('[data-ra-total]'),
    totalSqft: root.querySelector('[data-ra-total-sqft]'),
    copyTotal: root.querySelector('[data-ra-copy-total]'),
    wallEdit: root.querySelector('[data-ra-wall-edit]'),
    wallLength: root.querySelector('[data-ra-wall-length]'),
    wallApply: root.querySelector('[data-ra-wall-apply]'),
    wallCancel: root.querySelector('[data-ra-wall-cancel]'),
    zoomIn: root.querySelector('[data-ra-zoom-in]'),
    zoomOut: root.querySelector('[data-ra-zoom-out]'),
    zoom100: root.querySelector('[data-ra-zoom-100]'),
    zoomReset: root.querySelector('[data-ra-zoom-reset]'),
    zoomLabel: root.querySelector('[data-ra-zoom-label]')
  };

  var state = {
    projects: [],
    activeProjectId: null,
    snapMode: 'off',
    vertices: [],
    closed: false,
    calibrating: false,
    calibPoints: [],
    stage: null,
    worldGroup: null,
    viewport: { scale: 1, x: 0, y: 0 },
    panning: false,
    didPan: false,
    lastPanScreen: null,
    spaceHeld: false,
    shiftHeld: false,
    layers: {},
    konvaLayer: null,
    bgImage: null,
    bgNaturalW: 0,
    bgNaturalH: 0,
    lastUiScale: 1,
    selectedWallIndex: null,
    draggingVertex: false,
    editingRoomId: null,
    editorToken: 0,
    canvasW: 900,
    canvasH: 560
  };

  var resizeTimer = null;

  function getCanvasDimensions() {
    var wrap = els.canvasWrap;
    if (!wrap || wrap.clientWidth < 1) {
      return { w: state.canvasW || 900, h: state.canvasH || 560 };
    }
    var w = Math.max(MIN_CANVAS_W, Math.floor(wrap.clientWidth));
    var h = Math.max(MIN_CANVAS_H, Math.min(MAX_CANVAS_H, Math.round(w * 0.52)));
    return { w: w, h: h };
  }

  function canvasW() {
    return state.canvasW;
  }

  function canvasH() {
    return state.canvasH;
  }

  function scalePoints(points, fromW, fromH, toW, toH) {
    if (!points || !points.length) return [];
    var sx = toW / fromW;
    var sy = toH / fromH;
    if (!fromW || !fromH || fromW < 1 || fromH < 1 || (Math.abs(sx - 1) < 0.0005 && Math.abs(sy - 1) < 0.0005)) {
      return points.map(function (v) {
        return { x: parseFloat(v.x), y: parseFloat(v.y) };
      });
    }
    return points.map(function (v) {
      return { x: parseFloat(v.x) * sx, y: parseFloat(v.y) * sy };
    });
  }

  function canvasScaleFactor(fromW, fromH, toW, toH) {
    if (!fromW || !fromH || fromW < 1 || fromH < 1) return 1;
    return Math.sqrt((toW / fromW) * (toH / fromH));
  }

  function scaleProjectPixelsPerFoot(factor) {
    var project = activeProject();
    if (!project || !project.scale_pixels_per_foot || Math.abs(factor - 1) < 0.0005) return;
    project.scale_pixels_per_foot = parseFloat(project.scale_pixels_per_foot) * factor;
  }

  function roomSourceCanvas(room, project) {
    return {
      w: room.canvas_width || project.canvas_width || state.canvasW,
      h: room.canvas_height || project.canvas_height || state.canvasH
    };
  }

  function confirmAction(message, options) {
    if (window.AdminDialog && window.AdminDialog.confirm) {
      return window.AdminDialog.confirm(message, options);
    }
    return Promise.resolve(window.confirm(message));
  }

  function csrf() {
    var input = root.querySelector('#util-ra-csrf');
    return input ? input.value : '';
  }

  function urlFromTemplate(tpl, id) {
    return tpl.replace('__ID__', String(id));
  }

  function show(el, visible) {
    if (!el) return;
    if (visible) el.removeAttribute('hidden');
    else el.setAttribute('hidden', '');
  }

  function parseJson(attr, fallback) {
    try { return JSON.parse(root.getAttribute(attr)) || fallback; } catch (e) { return fallback; }
  }

  function activeProject() {
    return state.projects.find(function (p) { return p.id === state.activeProjectId; }) || null;
  }

  function setFeedback(msg, isError) {
    if (!els.feedback) return;
    els.feedback.textContent = msg;
    els.feedback.className = 'util-ra-feedback ' + (isError ? 'is-error' : 'is-ok');
    show(els.feedback, !!msg);
    if (msg) setTimeout(function () { show(els.feedback, false); }, 4500);
  }

  function parseFeetInput(raw) {
    var s = String(raw).trim();
    if (!s) throw new Error('Enter a length (e.g. 12\' 6", 12.5, or 150").');

    s = s.replace(/feet|foot/gi, "'");
    s = s.replace(/\bft\b/gi, "'");
    s = s.replace(/inches|inch/gi, '"');
    s = s.replace(/\bin\b/gi, '"');
    s = s.replace(/\s+/g, ' ').trim();

    var m;
    if ((m = s.match(/^([\d.]+)\s*['′]\s*([\d.]+)\s*["″]?$/))) {
      return parseFloat(m[1]) + parseFloat(m[2]) / INCHES_PER_FOOT;
    }
    if ((m = s.match(/^([\d.]+)\s*-\s*([\d.]+)$/))) {
      return parseFloat(m[1]) + parseFloat(m[2]) / INCHES_PER_FOOT;
    }
    if ((m = s.match(/^([\d.]+)\s*['′]\s*$/))) {
      var feetOnly = parseFloat(m[1]);
      if (isNaN(feetOnly) || feetOnly <= 0) throw new Error('Enter a valid length greater than zero.');
      return feetOnly;
    }
    if ((m = s.match(/^([\d.]+)\s*["″]\s*$/))) {
      var inchesOnly = parseFloat(m[1]);
      if (isNaN(inchesOnly) || inchesOnly <= 0) throw new Error('Enter a valid length greater than zero.');
      return inchesOnly / INCHES_PER_FOOT;
    }

    if (/^[\d.,]+$/.test(s)) {
      var n = parseFloat(s.replace(/,/g, ''));
      if (isNaN(n) || n <= 0) throw new Error('Enter a valid length greater than zero.');
      if (s.indexOf('.') !== -1) return n;
      return n / INCHES_PER_FOOT;
    }

    throw new Error('Use 12\' 6", 12.5 ft, or 150" (inches).');
  }

  function fmt(n, d) {
    return (Math.round(n * Math.pow(10, d)) / Math.pow(10, d)).toFixed(d).replace(/\.?0+$/, '');
  }

  function shoelaceArea(vertices) {
    if (vertices.length < 3) return 0;
    var sum = 0;
    for (var i = 0; i < vertices.length; i++) {
      var j = (i + 1) % vertices.length;
      sum += vertices[i].x * vertices[j].y - vertices[j].x * vertices[i].y;
    }
    return Math.abs(sum) / 2;
  }

  function direction(p1, p2, p3) {
    var cross = (p3.x - p1.x) * (p2.y - p1.y) - (p2.x - p1.x) * (p3.y - p1.y);
    if (Math.abs(cross) < 1e-9) return 0;
    return cross > 0 ? 1 : -1;
  }

  function onSegment(p1, p2, p3) {
    return Math.min(p1.x, p2.x) <= p3.x && p3.x <= Math.max(p1.x, p2.x)
      && Math.min(p1.y, p2.y) <= p3.y && p3.y <= Math.max(p1.y, p2.y);
  }

  function segmentsIntersect(p1, p2, p3, p4) {
    var d1 = direction(p3, p4, p1);
    var d2 = direction(p3, p4, p2);
    var d3 = direction(p1, p2, p3);
    var d4 = direction(p1, p2, p4);
    if (((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0))) return true;
    if (d1 === 0 && onSegment(p3, p4, p1)) return true;
    if (d2 === 0 && onSegment(p3, p4, p2)) return true;
    if (d3 === 0 && onSegment(p1, p2, p3)) return true;
    if (d4 === 0 && onSegment(p1, p2, p4)) return true;
    return false;
  }

  function isSimplePolygon(vertices) {
    var n = vertices.length;
    if (n < 3) return false;
    for (var i = 0; i < n; i++) {
      var a1 = vertices[i];
      var a2 = vertices[(i + 1) % n];
      for (var j = i + 1; j < n; j++) {
        if (j === i + 1) continue;
        if (i === 0 && j === n - 1) continue;
        var b1 = vertices[j];
        var b2 = vertices[(j + 1) % n];
        if (segmentsIntersect(a1, a2, b1, b2)) return false;
      }
    }
    return true;
  }

  function isSimplePolyline(vertices) {
    var n = vertices.length;
    if (n < 2) return true;
    for (var i = 0; i < n - 1; i++) {
      var a1 = vertices[i];
      var a2 = vertices[i + 1];
      for (var j = i + 1; j < n - 1; j++) {
        if (j === i + 1) continue;
        var b1 = vertices[j];
        var b2 = vertices[j + 1];
        if (segmentsIntersect(a1, a2, b1, b2)) return false;
      }
    }
    return true;
  }

  function isValidShape(vertices, closed) {
    if (closed) return isSimplePolygon(vertices);
    return isSimplePolyline(vertices);
  }

  function pixelsPerFoot() {
    var p = activeProject();
    return p && p.scale_pixels_per_foot ? parseFloat(p.scale_pixels_per_foot) : 0;
  }

  function areaSqFt(vertices) {
    var ppf = pixelsPerFoot();
    if (!ppf || vertices.length < 3) return null;
    if (!isSimplePolygon(vertices)) return null;
    var pxArea = shoelaceArea(vertices);
    var fpp = 1 / ppf;
    return Math.round(pxArea * fpp * fpp * 100) / 100;
  }

  function dist(a, b) {
    return Math.hypot(b.x - a.x, b.y - a.y);
  }

  function angleDelta(a, b) {
    var d = Math.abs(a - b) % (Math.PI * 2);
    return d > Math.PI ? Math.PI * 2 - d : d;
  }

  function snapPoint(raw, prev, stepOverride) {
    if (!prev) return raw;
    var dx = raw.x - prev.x;
    var dy = raw.y - prev.y;
    var len = Math.hypot(dx, dy);
    if (len < 1e-6) return raw;
    var angle = Math.atan2(dy, dx);
    var step;
    if (stepOverride !== undefined) {
      step = stepOverride;
    } else if (state.snapMode === 'off') {
      return raw;
    } else {
      step = state.snapMode === '45' ? Math.PI / 4 : Math.PI / 2;
    }
    var snapped = Math.round(angle / step) * step;
    return {
      x: prev.x + Math.cos(snapped) * len,
      y: prev.y + Math.sin(snapped) * len
    };
  }

  function snapPointMagnetic(raw, anchor, mode) {
    var dx;
    var dy;
    if (mode === 'incoming') {
      dx = raw.x - anchor.x;
      dy = raw.y - anchor.y;
    } else {
      dx = anchor.x - raw.x;
      dy = anchor.y - raw.y;
    }
    var len = Math.hypot(dx, dy);
    if (len < 1e-6) {
      return { x: raw.x, y: raw.y, snapped: false };
    }

    var angle = Math.atan2(dy, dx);
    var snappedAngle = Math.round(angle / SHIFT_SNAP_STEP) * SHIFT_SNAP_STEP;
    var diff = angleDelta(angle, snappedAngle);
    var threshold = SHIFT_SNAP_THRESHOLD_DEG * Math.PI / 180;

    if (diff > threshold) {
      return { x: raw.x, y: raw.y, snapped: false };
    }

    if (mode === 'incoming') {
      return {
        x: anchor.x + Math.cos(snappedAngle) * len,
        y: anchor.y + Math.sin(snappedAngle) * len,
        snapped: true
      };
    }
    return {
      x: anchor.x - Math.cos(snappedAngle) * len,
      y: anchor.y - Math.sin(snappedAngle) * len,
      snapped: true
    };
  }

  function getVertexPrev(idx) {
    var n = state.vertices.length;
    if (idx > 0) return state.vertices[idx - 1];
    if (state.closed && n > 1) return state.vertices[n - 1];
    return null;
  }

  function getVertexNext(idx) {
    var n = state.vertices.length;
    if (idx < n - 1) return state.vertices[idx + 1];
    if (state.closed && n > 1) return state.vertices[0];
    return null;
  }

  function snapVertexPositionShift(idx, raw) {
    var prev = getVertexPrev(idx);
    var next = getVertexNext(idx);
    var best = raw;
    var bestOffset = Infinity;
    var found = false;

    if (prev) {
      var incoming = snapPointMagnetic(raw, prev, 'incoming');
      if (incoming.snapped) {
        var offset = dist(raw, incoming);
        if (offset < bestOffset) {
          best = { x: incoming.x, y: incoming.y };
          bestOffset = offset;
          found = true;
        }
      }
    }

    if (next) {
      var outgoing = snapPointMagnetic(raw, next, 'outgoing');
      if (outgoing.snapped) {
        var outOffset = dist(raw, outgoing);
        if (!found || outOffset < bestOffset) {
          best = { x: outgoing.x, y: outgoing.y };
          found = true;
        }
      }
    }

    return clampToCanvas(found ? best : raw);
  }

  function fmtFeetInches(lenFt) {
    var feet = Math.floor(lenFt);
    var inches = Math.round((lenFt - feet) * INCHES_PER_FOOT);
    if (inches === 12) { feet += 1; inches = 0; }
    if (inches === 0) return String(feet) + "'";
    return feet + "' " + inches + '"';
  }

  function wallCount() {
    var n = state.vertices.length;
    if (n < 2) return 0;
    return state.closed ? n : n - 1;
  }

  function wallEndIndex(wallIndex) {
    var n = state.vertices.length;
    if (state.closed) return (wallIndex + 1) % n;
    return wallIndex + 1;
  }

  function wallLengthFeet(wallIndex) {
    var ppf = pixelsPerFoot();
    if (!ppf) return null;
    var a = state.vertices[wallIndex];
    var b = state.vertices[wallEndIndex(wallIndex)];
    return dist(a, b) / ppf;
  }

  function pointToSegmentDistance(p, a, b) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var lenSq = dx * dx + dy * dy;
    if (lenSq < 1e-9) return dist(p, a);
    var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq;
    t = Math.max(0, Math.min(1, t));
    var proj = { x: a.x + t * dx, y: a.y + t * dy };
    return dist(p, proj);
  }

  function hitTolerance(base) {
    return base / Math.max(state.viewport.scale, 0.001);
  }

  function findVertexAtPoint(pos) {
    if (!pos) return -1;
    var hit = hitTolerance(VERTEX_HIT);
    for (var i = 0; i < state.vertices.length; i++) {
      if (dist(pos, state.vertices[i]) <= hit) return i;
    }
    return -1;
  }

  function clampToCanvas(p) {
    return {
      x: Math.max(0, Math.min(canvasW(), p.x)),
      y: Math.max(0, Math.min(canvasH(), p.y))
    };
  }

  function snapVertexPosition(idx, raw) {
    if (state.shiftHeld) {
      return snapVertexPositionShift(idx, raw);
    }

    if (state.snapMode === 'off') return clampToCanvas(raw);
    var prev = getVertexPrev(idx);
    if (!prev) return clampToCanvas(raw);
    return clampToCanvas(snapPoint(raw, prev));
  }

  function deleteSelectedWall() {
    if (state.selectedWallIndex === null) return;
    var n = state.vertices.length;
    if (wallCount() === 0) return;

    var wallIndex = state.selectedWallIndex;
    var removeIdx = wallEndIndex(wallIndex);

    state.vertices.splice(removeIdx, 1);
    clearWallSelection();

    if (state.closed && state.vertices.length < 3) {
      state.closed = false;
    }
    if (state.vertices.length === 0) {
      state.closed = false;
    }

    redrawAll();
    setFeedback('Wall removed.', false);
  }

  function findWallAtPoint(pos) {
    if (!pos || wallCount() === 0) return -1;
    var best = -1;
    var bestDist = hitTolerance(WALL_HIT) + 1;
    for (var i = 0; i < wallCount(); i++) {
      var a = state.vertices[i];
      var b = state.vertices[wallEndIndex(i)];
      var d = pointToSegmentDistance(pos, a, b);
      if (d <= hitTolerance(WALL_HIT) && d < bestDist) {
        bestDist = d;
        best = i;
      }
    }
    return best;
  }

  function clearWallSelection() {
    if (state.selectedWallIndex === null && els.wallEdit && els.wallEdit.hasAttribute('hidden')) return;
    state.selectedWallIndex = null;
    show(els.wallEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-wall-edit');
    if (els.wallLength) els.wallLength.value = '';
    if (state.layers.draw) {
      redrawLabels();
      redrawWalls();
      updateToolbar();
    }
  }

  function selectWall(wallIndex) {
    if (wallIndex < 0 || wallIndex >= wallCount()) return;
    state.selectedWallIndex = wallIndex;
    show(els.wallEdit, true);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-wall-edit');
    var ppf = pixelsPerFoot();
    if (ppf && els.wallLength) {
      var lenFt = wallLengthFeet(wallIndex);
      els.wallLength.value = lenFt !== null ? fmtFeetInches(lenFt) : '';
      els.wallLength.disabled = false;
      els.wallLength.focus();
      els.wallLength.select();
    } else if (els.wallLength) {
      els.wallLength.value = '';
      els.wallLength.disabled = true;
    }
    if (els.wallApply) els.wallApply.disabled = !ppf;
    updateToolbar();
    redrawLabels();
    redrawWalls();
  }

  function applyWallLength() {
    if (state.selectedWallIndex === null) return;
    if (!pixelsPerFoot()) {
      setFeedback('Calibrate scale before editing wall lengths.', true);
      return;
    }

    var wallIndex = state.selectedWallIndex;
    var endIdx = wallEndIndex(wallIndex);
    var start = state.vertices[wallIndex];
    var end = state.vertices[endIdx];
    var currentPx = dist(start, end);
    if (currentPx < 1e-6) {
      setFeedback('Wall has no length to adjust.', true);
      return;
    }

    var newFeet;
    try {
      newFeet = parseFeetInput(els.wallLength.value);
    } catch (e) {
      setFeedback(e.message, true);
      return;
    }

    var ppf = pixelsPerFoot();
    var newPx = newFeet * ppf;
    var ux = (end.x - start.x) / currentPx;
    var uy = (end.y - start.y) / currentPx;
    var oldEnd = { x: end.x, y: end.y };
    var newEnd = { x: start.x + ux * newPx, y: start.y + uy * newPx };

    state.vertices[endIdx] = newEnd;
    if (!isValidShape(state.vertices, state.closed)) {
      state.vertices[endIdx] = oldEnd;
      setFeedback('That length would make the shape cross itself.', true);
      return;
    }

    redrawAll();
    els.wallLength.value = fmtFeetInches(newFeet);
    setFeedback('Wall length updated.', false);
  }

  function gridStepPx() {
    var ppf = pixelsPerFoot();
    return ppf > 0 ? ppf : GRID_PX;
  }

  function flushKonva() {
    if (state.konvaLayer) state.konvaLayer.batchDraw();
  }

  function bumpEditorToken() {
    state.editorToken += 1;
    return state.editorToken;
  }

  function resetCalibrationUI() {
    stopCalibrationMode();
  }

  function resetEditor() {
    bumpEditorToken();
    state.vertices = [];
    state.closed = false;
    state.editingRoomId = null;
    state.selectedWallIndex = null;
    state.draggingVertex = false;
    resetCalibrationUI();
    clearWallSelection();
    if (els.roomName) els.roomName.value = '';
    show(els.liveArea, false);
    if (state.stage) {
      state.lastUiScale = 0;
      resetViewport();
      loadBackground(null);
      redrawAll();
    }
    updateEditUI();
    updateToolbar();
  }

  function uiScale() {
    return Math.max(state.viewport.scale, 0.001);
  }

  function uiSize(base) {
    return base / uiScale();
  }

  function updateCanvasCursor() {
    if (!state.stage) return;
    var el = state.stage.container();
    if (state.panning) {
      el.style.cursor = 'grabbing';
    } else if (state.spaceHeld) {
      el.style.cursor = 'grab';
    } else if (state.draggingVertex) {
      el.style.cursor = 'grabbing';
    } else {
      el.style.cursor = '';
    }
  }

  function applyViewport() {
    if (!state.worldGroup) return;
    state.worldGroup.scale({ x: state.viewport.scale, y: state.viewport.scale });
    state.worldGroup.position({ x: state.viewport.x, y: state.viewport.y });
    if (Math.abs(state.viewport.scale - state.lastUiScale) > 0.0001) {
      state.lastUiScale = state.viewport.scale;
      redrawZoomUi();
    } else {
      flushKonva();
    }
    updateZoomUI();
  }

  function redrawZoomUi() {
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    redrawCalib();
  }

  function resetViewport() {
    state.viewport.scale = 1;
    state.viewport.x = 0;
    state.viewport.y = 0;
    state.lastUiScale = 0;
    applyViewport();
    updateCanvasCursor();
  }

  function fitViewport() {
    if (!state.stage) return;
    var pad = 32;
    var cw = canvasW();
    var ch = canvasH();
    var minX = 0;
    var minY = 0;
    var maxX = cw;
    var maxY = ch;

    if (state.vertices.length > 0) {
      minX = Infinity;
      minY = Infinity;
      maxX = -Infinity;
      maxY = -Infinity;
      state.vertices.forEach(function (v) {
        minX = Math.min(minX, v.x);
        minY = Math.min(minY, v.y);
        maxX = Math.max(maxX, v.x);
        maxY = Math.max(maxY, v.y);
      });
    }

    var contentW = Math.max(maxX - minX, 1);
    var contentH = Math.max(maxY - minY, 1);
    var scale = Math.min((cw - pad * 2) / contentW, (ch - pad * 2) / contentH);
    scale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, scale));

    var cx = (minX + maxX) / 2;
    var cy = (minY + maxY) / 2;
    state.viewport.scale = scale;
    state.viewport.x = cw / 2 - cx * scale;
    state.viewport.y = ch / 2 - cy * scale;
    state.lastUiScale = 0;
    applyViewport();
    updateCanvasCursor();
  }

  function updateZoomUI() {
    if (els.zoomLabel) {
      els.zoomLabel.textContent = Math.round(state.viewport.scale * 100) + '%';
    }
  }

  function zoomAt(screenX, screenY, newScale) {
    newScale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, newScale));
    var oldScale = state.viewport.scale;
    var worldX = (screenX - state.viewport.x) / oldScale;
    var worldY = (screenY - state.viewport.y) / oldScale;
    state.viewport.scale = newScale;
    state.viewport.x = screenX - worldX * newScale;
    state.viewport.y = screenY - worldY * newScale;
    applyViewport();
  }

  function zoomBy(factor) {
    if (!state.stage) return;
    zoomAt(canvasW() / 2, canvasH() / 2, state.viewport.scale * factor);
  }

  function stageToWorld(pos) {
    if (!state.worldGroup || !pos) return null;
    var transform = state.worldGroup.getAbsoluteTransform().copy().invert();
    return transform.point(pos);
  }

  function bindViewportEvents() {
    if (!els.canvasWrap || els.canvasWrap._raViewportBound) return;
    els.canvasWrap._raViewportBound = true;

    els.canvasWrap.addEventListener('wheel', function (e) {
      if (!state.stage || !e.shiftKey) return;
      e.preventDefault();
      var rect = state.stage.container().getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      var dir = e.deltaY < 0 ? 1 : -1;
      var factor = dir > 0 ? ZOOM_FACTOR : 1 / ZOOM_FACTOR;
      zoomAt(x, y, state.viewport.scale * factor);
    }, { passive: false });

    document.addEventListener('keydown', function (e) {
      if (e.code === 'ShiftLeft' || e.code === 'ShiftRight') {
        state.shiftHeld = true;
        return;
      }
      if (e.code !== 'Space' || e.repeat) return;
      if (e.target && e.target.closest('input, textarea, select')) return;
      e.preventDefault();
      state.spaceHeld = true;
      if (els.canvasWrap) els.canvasWrap.classList.add('is-pan-ready');
      updateCanvasCursor();
      redrawVertices();
    });

    document.addEventListener('keyup', function (e) {
      if (e.code === 'ShiftLeft' || e.code === 'ShiftRight') {
        state.shiftHeld = false;
        return;
      }
      if (e.code !== 'Space') return;
      state.spaceHeld = false;
      state.panning = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-pan-ready', 'is-panning');
      updateCanvasCursor();
      redrawVertices();
    });

    document.addEventListener('mouseup', function () {
      if (!state.panning) return;
      state.panning = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
      updateCanvasCursor();
    });
  }

  function resizeStage() {
    if (!state.stage || !els.canvasWrap) return;
    var dims = getCanvasDimensions();
    if (dims.w === state.canvasW && dims.h === state.canvasH) return;

    var oldW = state.canvasW;
    var oldH = state.canvasH;

    if (oldW > 0 && oldH > 0) {
      var factor = canvasScaleFactor(oldW, oldH, dims.w, dims.h);
      if (state.vertices.length) {
        state.vertices = scalePoints(state.vertices, oldW, oldH, dims.w, dims.h);
      }
      if (state.calibPoints.length) {
        state.calibPoints = scalePoints(state.calibPoints, oldW, oldH, dims.w, dims.h);
      }
      if (Math.abs(factor - 1) > 0.0005) {
        scaleProjectPixelsPerFoot(factor);
        state.viewport.x *= dims.w / oldW;
        state.viewport.y *= dims.h / oldH;
        applyViewport();
      }
    }

    state.canvasW = dims.w;
    state.canvasH = dims.h;
    state.stage.width(dims.w);
    state.stage.height(dims.h);
    if (state.bgImage) {
      if (oldW > 0 && oldH > 0) {
        scaleBackgroundLayout(oldW, oldH, dims.w, dims.h);
      } else {
        layoutBackgroundImage();
      }
    }
    drawGrid();
    redrawAll();
  }

  function scheduleStageResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resizeStage, 120);
  }

  function initStage() {
    var container = document.getElementById('ra-konva-container');
    if (!container) return;

    if (state.stage) {
      state.stage.destroy();
    }

    var dims = getCanvasDimensions();
    state.canvasW = dims.w;
    state.canvasH = dims.h;

    state.stage = new Konva.Stage({
      container: 'ra-konva-container',
      width: dims.w,
      height: dims.h
    });

    state.worldGroup = new Konva.Group();
    state.konvaLayer = new Konva.Layer();
    state.stage.add(state.konvaLayer);
    state.konvaLayer.add(state.worldGroup);

    state.layers.bg = new Konva.Group();
    state.layers.grid = new Konva.Group();
    state.layers.draw = new Konva.Group();
    state.layers.vertices = new Konva.Group();
    state.layers.walls = new Konva.Group();
    state.layers.labels = new Konva.Group();
    state.layers.calib = new Konva.Group();

    state.worldGroup.add(state.layers.bg);
    state.worldGroup.add(state.layers.grid);
    state.worldGroup.add(state.layers.draw);
    state.worldGroup.add(state.layers.walls);
    state.worldGroup.add(state.layers.vertices);
    state.worldGroup.add(state.layers.labels);
    state.worldGroup.add(state.layers.calib);

    resetViewport();

    state.stage.on('mousedown touchstart', function (e) {
      var evt = e.evt;
      var isMiddle = evt.button === 1;
      var isSpacePan = evt.button === 0 && state.spaceHeld;
      if (!isMiddle && !isSpacePan) return;
      evt.preventDefault();
      state.panning = true;
      state.didPan = false;
      state.lastPanScreen = state.stage.getPointerPosition();
      if (els.canvasWrap) els.canvasWrap.classList.add('is-panning');
      updateCanvasCursor();
    });

    state.stage.on('mousemove touchmove', function () {
      if (!state.panning || !state.lastPanScreen) return;
      var pos = state.stage.getPointerPosition();
      if (!pos) return;
      var dx = pos.x - state.lastPanScreen.x;
      var dy = pos.y - state.lastPanScreen.y;
      if (Math.abs(dx) > 1 || Math.abs(dy) > 1) state.didPan = true;
      state.viewport.x += dx;
      state.viewport.y += dy;
      state.lastPanScreen = pos;
      applyViewport();
    });

    state.stage.on('mouseup touchend mouseleave', function () {
      if (!state.panning) return;
      state.panning = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
      updateCanvasCursor();
    });

    state.stage.on('click tap', onStageClick);
    state.stage.on('dblclick dbltap', onStageDblClick);

    bindViewportEvents();

    drawGrid();
    redrawAll();
  }

  function drawGrid() {
    var layer = state.layers.grid;
    if (!layer) return;
    layer.destroyChildren();
    var step = gridStepPx();
    var w = canvasW();
    var h = canvasH();
    for (var x = 0; x <= w; x += step) {
      layer.add(new Konva.Line({
        points: [x, 0, x, h],
        stroke: '#d8dee4',
        strokeWidth: x % (step * 5) === 0 ? 1 : 0.5,
        listening: false
      }));
    }
    for (var y = 0; y <= h; y += step) {
      layer.add(new Konva.Line({
        points: [0, y, w, y],
        stroke: '#d8dee4',
        strokeWidth: y % (step * 5) === 0 ? 1 : 0.5,
        listening: false
      }));
    }
    flushKonva();
  }

  function syncBackgroundUI() {
    var project = activeProject();
    var hasBg = !!(project && project.background_url) || !!state.bgImage;
    show(els.bgRemove, hasBg);
    show(els.bgDeleteCanvas, hasBg);
  }

  function layoutBackgroundImage() {
    if (!state.bgImage || !state.bgNaturalW || !state.bgNaturalH) return;
    var cw = canvasW();
    var ch = canvasH();
    var scale = Math.min(cw / state.bgNaturalW, ch / state.bgNaturalH);
    var w = state.bgNaturalW * scale;
    var h = state.bgNaturalH * scale;
    state.bgImage.position({ x: (cw - w) / 2, y: (ch - h) / 2 });
    state.bgImage.width(w);
    state.bgImage.height(h);
  }

  function scaleBackgroundLayout(fromW, fromH, toW, toH) {
    if (!state.bgImage || !fromW || !fromH || fromW < 1 || fromH < 1) return;
    var sx = toW / fromW;
    var sy = toH / fromH;
    state.bgImage.x(state.bgImage.x() * sx);
    state.bgImage.y(state.bgImage.y() * sy);
    state.bgImage.width(state.bgImage.width() * sx);
    state.bgImage.height(state.bgImage.height() * sy);
  }

  function ensureStage() {
    if (!state.stage || !state.konvaLayer || !state.konvaLayer.getStage()) {
      initStage();
    }
  }

  function loadBackground(url) {
    ensureStage();
    var layer = state.layers.bg;
    if (!layer) return;

    var token = bumpEditorToken();
    layer.destroyChildren();
    state.bgImage = null;
    state.bgNaturalW = 0;
    state.bgNaturalH = 0;
    if (!url) {
      flushKonva();
      syncBackgroundUI();
      return;
    }

    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () {
      if (token !== state.editorToken) return;
      var project = activeProject();
      if (!project || project.background_url !== url) return;
      if (!state.layers.bg) return;
      state.bgNaturalW = img.naturalWidth || img.width;
      state.bgNaturalH = img.naturalHeight || img.height;
      var kImg = new Konva.Image({
        image: img,
        listening: false,
        opacity: 0.85
      });
      state.layers.bg.add(kImg);
      state.bgImage = kImg;
      layoutBackgroundImage();
      flushKonva();
      syncBackgroundUI();
    };
    img.onerror = function () {
      if (token !== state.editorToken) return;
      setFeedback('Could not load floor plan image.', true);
      syncBackgroundUI();
    };
    img.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
    syncBackgroundUI();
  }

  function redrawAll() {
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    redrawCalib();
    updateLiveArea();
    updateToolbar();
  }

  function redrawPolygonLine() {
    var layer = state.layers.draw;
    if (!layer) return;
    layer.destroyChildren();

    var verts = state.vertices;
    if (verts.length === 0) {
      flushKonva();
      return;
    }

    var linePoints = [];
    verts.forEach(function (v) { linePoints.push(v.x, v.y); });
    if (state.closed && verts.length >= 3) {
      linePoints.push(verts[0].x, verts[0].y);
    }

    if (linePoints.length >= 4) {
      if (state.closed) {
        layer.add(new Konva.Line({
          points: linePoints,
          closed: true,
          fill: 'rgba(2, 71, 104, 0.12)',
          listening: false
        }));
      }
      layer.add(new Konva.Line({
        points: linePoints,
        stroke: '#024768',
        strokeWidth: uiSize(2.5),
        closed: state.closed,
        listening: false
      }));
    }

    flushKonva();
  }

  function redrawVertices() {
    var layer = state.layers.vertices;
    if (!layer) return;
    layer.destroyChildren();

    if (state.calibrating) {
      flushKonva();
      return;
    }

    var verts = state.vertices;
    verts.forEach(function (v, idx) {
      var isFirst = idx === 0;
      var showCloseTarget = isFirst && !state.closed && verts.length >= 3;
      (function (vertexIndex) {
        var circle = new Konva.Circle({
          x: v.x,
          y: v.y,
          radius: uiSize(showCloseTarget ? CLOSE_HIT / 2 : 8),
          fill: isFirst ? '#c62828' : '#024768',
          stroke: '#fff',
          strokeWidth: uiSize(2),
          draggable: !state.spaceHeld,
          listening: !state.spaceHeld,
          dragDistance: uiSize(3)
        });
        circle.on('mouseenter', function () {
          if (state.stage && !state.draggingVertex && !state.spaceHeld) {
            state.stage.container().style.cursor = 'move';
          }
        });
        circle.on('mouseleave', function () {
          if (state.stage && !state.draggingVertex) updateCanvasCursor();
        });
        circle.on('dragstart', function (e) {
          if (state.spaceHeld) {
            e.target.stopDrag();
            return;
          }
          e.cancelBubble = true;
          state.draggingVertex = true;
          updateCanvasCursor();
        });
        circle.on('click tap', function (e) {
          e.cancelBubble = true;
          if (vertexIndex === 0 && !state.closed && state.vertices.length >= 3 && !state.draggingVertex) {
            closeShape();
          }
        });
        circle.on('dragmove', function (e) {
          e.cancelBubble = true;
          var pos = snapVertexPosition(vertexIndex, { x: circle.x(), y: circle.y() });
          circle.position(pos);
          state.vertices[vertexIndex] = { x: pos.x, y: pos.y };
          redrawPolygonLine();
          redrawLabels();
          redrawWalls();
          updateLiveArea();
          if (state.selectedWallIndex !== null && els.wallLength) {
            var lenFt = wallLengthFeet(state.selectedWallIndex);
            if (lenFt !== null) els.wallLength.value = fmtFeetInches(lenFt);
          }
        });
        circle.on('dragend', function (e) {
          e.cancelBubble = true;
          state.draggingVertex = false;
          updateCanvasCursor();
          var pos = snapVertexPosition(vertexIndex, { x: circle.x(), y: circle.y() });
          var old = state.vertices[vertexIndex];
          state.vertices[vertexIndex] = pos;
          if (!isValidShape(state.vertices, state.closed)) {
            state.vertices[vertexIndex] = old;
            circle.position(old);
            setFeedback('That corner position would make the shape cross itself.', true);
          }
          redrawAll();
        });
        layer.add(circle);
      })(idx);
    });

    flushKonva();
  }

  function redrawLabels() {
    var labelLayer = state.layers.labels;
    if (!labelLayer) return;
    labelLayer.destroyChildren();

    var verts = state.vertices;
    var ppf = pixelsPerFoot();
    if (!ppf || verts.length === 0) {
      flushKonva();
      return;
    }

    var count = wallCount();
    for (var i = 0; i < count; i++) {
      var a = verts[i];
      var b = verts[wallEndIndex(i)];
      var midX = (a.x + b.x) / 2;
      var midY = (a.y + b.y) / 2;
      var lenFt = dist(a, b) / ppf;
      var lenLabel = fmtFeetInches(lenFt);
      var isSelected = state.selectedWallIndex === i;
      (function (wallIndex) {
        var kLabel = new Konva.Label({
          x: midX,
          y: midY,
          listening: true
        });
        kLabel.on('mouseenter', function () {
          if (state.stage && !state.draggingVertex && !state.spaceHeld) {
            state.stage.container().style.cursor = 'pointer';
          }
        });
        kLabel.on('mouseleave', function () {
          if (state.stage && !state.draggingVertex) updateCanvasCursor();
        });
        kLabel.on('click tap', function (e) {
          e.cancelBubble = true;
          selectWall(wallIndex);
        });
        kLabel.add(new Konva.Tag({
          fill: isSelected ? 'rgba(249, 168, 37, 0.95)' : 'rgba(255,255,255,0.92)',
          stroke: isSelected ? '#f57f17' : '#024768',
          strokeWidth: uiSize(isSelected ? 2 : 1),
          cornerRadius: uiSize(4),
          pointerDirection: 'none'
        }));
        kLabel.add(new Konva.Text({
          text: lenLabel,
          fontSize: uiSize(11),
          fontFamily: 'Open Sans, sans-serif',
          fill: isSelected ? '#5d4037' : '#024768',
          padding: uiSize(4)
        }));
        labelLayer.add(kLabel);
      })(i);
    }

    flushKonva();
  }

  function redrawWalls() {
    var wallLayer = state.layers.walls;
    if (!wallLayer) return;
    wallLayer.destroyChildren();

    var count = wallCount();
    if (count === 0) {
      flushKonva();
      return;
    }

    for (var i = 0; i < count; i++) {
      var a = state.vertices[i];
      var b = state.vertices[wallEndIndex(i)];
      var isSelected = state.selectedWallIndex === i;

      if (isSelected) {
        wallLayer.add(new Konva.Line({
          points: [a.x, a.y, b.x, b.y],
          stroke: '#f9a825',
          strokeWidth: uiSize(5),
          lineCap: 'round',
          listening: false
        }));
      }

      (function (wallIndex) {
        var hitLine = new Konva.Line({
          points: [a.x, a.y, b.x, b.y],
          stroke: 'rgba(0,0,0,0.001)',
          strokeWidth: uiSize(WALL_HIT * 2),
          lineCap: 'round',
          listening: !state.spaceHeld
        });
        hitLine.on('mouseenter', function () {
          if (state.stage && !state.draggingVertex && !state.spaceHeld) {
            state.stage.container().style.cursor = 'pointer';
          }
        });
        hitLine.on('mouseleave', function () {
          if (state.stage && !state.draggingVertex) updateCanvasCursor();
        });
        hitLine.on('click tap', function (e) {
          e.cancelBubble = true;
          selectWall(wallIndex);
        });
        wallLayer.add(hitLine);
      })(i);
    }

    flushKonva();
  }

  function redrawShape() {
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
  }

  function redrawCalib() {
    var layer = state.layers.calib;
    if (!layer) return;
    layer.destroyChildren();
    if (state.calibPoints.length === 0) {
      flushKonva();
      return;
    }
    state.calibPoints.forEach(function (p, idx) {
      layer.add(new Konva.Circle({
        x: p.x,
        y: p.y,
        radius: uiSize(7),
        fill: idx === 0 ? '#f9a825' : '#2e7d32',
        stroke: '#fff',
        strokeWidth: uiSize(2),
        listening: false
      }));
    });
    if (state.calibPoints.length === 2) {
      layer.add(new Konva.Line({
        points: [state.calibPoints[0].x, state.calibPoints[0].y, state.calibPoints[1].x, state.calibPoints[1].y],
        stroke: '#f9a825',
        strokeWidth: uiSize(2),
        dash: [uiSize(8), uiSize(4)],
        listening: false
      }));
    }
    flushKonva();
  }

  function getPointerPos() {
    var pos = state.stage.getPointerPosition();
    if (!pos) return null;
    return stageToWorld(pos);
  }

  function onStageClick(e) {
    if (state.spaceHeld || state.panning) return;
    var pos = getPointerPos();
    if (!pos) return;
    if (state.didPan) {
      state.didPan = false;
      return;
    }
    if (state.draggingVertex) return;

    if (state.calibrating) {
      if (state.calibPoints.length >= 2) {
        state.calibPoints = [];
      }
      state.calibPoints.push({ x: pos.x, y: pos.y });
      redrawCalib();
      var ready = state.calibPoints.length === 2;
      els.knownLength.disabled = !ready;
      els.applyScale.disabled = !ready;
      if (ready) els.knownLength.focus();
      return;
    }

    if (findVertexAtPoint(pos) >= 0) return;

    if (wallCount() > 0) {
      var wallIdx = findWallAtPoint(pos);
      if (wallIdx >= 0) {
        selectWall(wallIdx);
        return;
      }
    }

    if (state.closed) {
      clearWallSelection();
      return;
    }

    if (state.vertices.length >= 3) {
      var first = state.vertices[0];
      if (dist(pos, first) <= hitTolerance(CLOSE_HIT)) {
        closeShape();
        return;
      }
    }

    var shiftSnap = state.shiftHeld || !!(e && e.evt && e.evt.shiftKey);
    var point = pos;
    if (state.vertices.length > 0) {
      var prev = state.vertices[state.vertices.length - 1];
      if (shiftSnap) {
        point = snapPoint(pos, prev, SHIFT_SNAP_STEP);
      } else {
        point = snapPoint(pos, prev);
      }
    }
    state.vertices.push(clampToCanvas(point));
    redrawAll();
  }

  function onStageDblClick(e) {
    if (e && e.evt) e.evt.preventDefault();
    if (!state.closed && state.vertices.length >= 3) {
      closeShape();
    }
  }

  function closeShape() {
    if (state.vertices.length < 3) return;
    if (!isSimplePolygon(state.vertices)) {
      setFeedback('Shape crosses itself — adjust corners before closing.', true);
      return;
    }
    state.closed = true;
    redrawAll();
  }

  function clearDraftLayers() {
    ['draw', 'walls', 'vertices', 'labels', 'calib'].forEach(function (name) {
      var layer = state.layers[name];
      if (layer) layer.destroyChildren();
    });
  }

  function resetDraftCanvas() {
    bumpEditorToken();
    state.vertices = [];
    state.closed = false;
    state.editingRoomId = null;
    state.draggingVertex = false;
    stopCalibrationMode();
    clearWallSelection();
    if (els.roomName) els.roomName.value = '';
    show(els.liveArea, false);
    clearDraftLayers();
    updateEditUI();
    redrawAll();
    flushKonva();
  }

  function clearShape() {
    resetDraftCanvas();
  }

  function beginNewRoom() {
    resetDraftCanvas();
    if (els.roomName) {
      els.roomName.focus();
    }
    setFeedback('Draw a new room outline, enter a name, and click Save room.', false);
  }

  function startNewRoom() {
    var hasDraft = state.vertices.length > 0
      || state.closed
      || !!state.editingRoomId
      || (els.roomName && els.roomName.value.trim());
    if (!hasDraft) {
      beginNewRoom();
      return;
    }
    confirmAction('Clear the current outline and start a new room?', { title: 'New room' }).then(function (ok) {
      if (ok) beginNewRoom();
    });
  }

  function undoVertex() {
    if (state.closed) state.closed = false;
    else if (state.vertices.length) state.vertices.pop();
    redrawAll();
  }

  function updateLiveArea() {
    var sqft = state.closed ? areaSqFt(state.vertices) : null;
    if (sqft === null || sqft === undefined) {
      show(els.liveArea, false);
      els.saveRoom.disabled = true;
      return;
    }
    show(els.liveArea, true);
    els.liveSqft.textContent = fmt(sqft, 2);
    els.liveSqm.textContent = fmt(sqft * SQFT_TO_SQM, 2);
    els.saveRoom.disabled = !pixelsPerFoot();
  }

  function updateToolbar() {
    els.undoVertex.disabled = state.vertices.length === 0;
    els.closeShape.disabled = state.vertices.length < 3 || state.closed;
    if (els.deleteWall) {
      els.deleteWall.disabled = state.selectedWallIndex === null;
    }
    if (els.wallApply) {
      els.wallApply.disabled = state.selectedWallIndex === null || !pixelsPerFoot();
    }
  }

  function updateScaleUI() {
    var ppf = pixelsPerFoot();
    if (ppf > 0) {
      els.scaleStatus.textContent = 'Scale: ' + fmt(ppf, 2) + ' px per foot (1 grid cell ≈ 1 ft when calibrated).';
      els.scaleStatus.className = 'util-ra-scale-status is-set';
    } else {
      els.scaleStatus.textContent = 'Scale not set — calibrate before saving rooms.';
      els.scaleStatus.className = 'util-ra-scale-status';
    }
    drawGrid();
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    updateLiveArea();
  }

  function stopCalibrationMode() {
    state.calibrating = false;
    state.calibPoints = [];
    if (els.calibrateToggle) els.calibrateToggle.classList.remove('is-active');
    if (els.knownLength) {
      els.knownLength.value = '';
      els.knownLength.disabled = true;
    }
    if (els.applyScale) els.applyScale.disabled = true;
    redrawCalib();
    redrawVertices();
  }

  function toggleCalibrate() {
    if (state.calibrating) {
      stopCalibrationMode();
      setFeedback('Calibration cancelled.', false);
      return;
    }

    state.calibrating = true;
    state.calibPoints = [];
    if (els.calibrateToggle) els.calibrateToggle.classList.add('is-active');
    if (els.knownLength) {
      els.knownLength.disabled = true;
      els.knownLength.value = '';
    }
    if (els.applyScale) els.applyScale.disabled = true;
    redrawCalib();
    setFeedback('Click two points on a known wall length.', false);
  }

  function applyScale() {
    if (state.calibPoints.length !== 2) return;
    try {
      var feet = parseFeetInput(els.knownLength.value);
      var pxDist = dist(state.calibPoints[0], state.calibPoints[1]);
      if (pxDist < 5) throw new Error('Points are too close — pick a longer reference.');
      var ppf = pxDist / feet;
      var project = activeProject();
      if (!project) return;

      project.scale_pixels_per_foot = ppf;
      stopCalibrationMode();
      updateScaleUI();

      fetch(urlFromTemplate(root.getAttribute('data-project-update-template'), project.id), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          scale_pixels_per_foot: ppf,
          canvas_width: state.canvasW,
          canvas_height: state.canvasH
        })
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok) throw new Error(result.data.message || 'Could not save scale.');
          project.scale_pixels_per_foot = result.data.project.scale_pixels_per_foot;
          project.canvas_width = result.data.project.canvas_width;
          project.canvas_height = result.data.project.canvas_height;
          updateScaleUI();
          setFeedback('Scale calibrated. Click the canvas to start drawing.', false);
        })
        .catch(function (e) {
          setFeedback(e.message, true);
        });
    } catch (err) {
      setFeedback(err.message, true);
    }
  }

  function refreshProjectSelect() {
    var sel = els.projectSelect;
    var current = state.activeProjectId;
    sel.innerHTML = '<option value="">— Select a project —</option>';
    state.projects.forEach(function (p) {
      var opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.name;
      sel.appendChild(opt);
    });
    if (current) sel.value = String(current);
  }

  function renderLinked() {
    var project = activeProject();
    if (!project) {
      show(els.linked, false);
      return;
    }
    show(els.linked, true);
    if (project.client_project_id && project.client_project_name) {
      els.linkedLink.textContent = project.client_project_name;
      els.linkedLink.href = '/admin/client-projects/' + project.client_project_id;
      els.linkedLink.removeAttribute('hidden');
    } else {
      els.linkedLink.setAttribute('hidden', '');
    }
    els.linkClientProject.value = project.client_project_id ? String(project.client_project_id) : '';
  }

  function clearEditState() {
    state.editingRoomId = null;
    updateEditUI();
  }

  function updateEditUI() {
    var editing = !!state.editingRoomId;
    if (els.saveRoom) {
      els.saveRoom.innerHTML = editing
        ? '<i class="fa fa-save"></i> Update room'
        : '<i class="fa fa-save"></i> Save room';
    }
    if (els.saveCardTitle) {
      els.saveCardTitle.textContent = editing ? 'Update room' : 'Save room';
    }
    show(els.cancelEdit, editing);
    renderRooms();
  }

  function loadRoom(roomId) {
    var project = activeProject();
    if (!project) return;
    var room = project.rooms.find(function (r) { return r.id === roomId; });
    if (!room || !room.vertices || room.vertices.length < 3) {
      setFeedback('Could not load that room.', true);
      return;
    }

    var token = bumpEditorToken();
    clearWallSelection();
    state.editingRoomId = room.id;
    if (els.roomName) els.roomName.value = room.name;
    updateEditUI();

    ensureStage();
    requestAnimationFrame(function () {
      if (token !== state.editorToken) return;
      if (state.editingRoomId !== roomId) return;

      var currentProject = activeProject();
      if (!currentProject) return;
      var currentRoom = currentProject.rooms.find(function (r) { return r.id === roomId; });
      if (!currentRoom || !currentRoom.vertices || currentRoom.vertices.length < 3) {
        clearShape();
        return;
      }

      resizeStage();

      var source = roomSourceCanvas(currentRoom, currentProject);
      var factor = canvasScaleFactor(source.w, source.h, state.canvasW, state.canvasH);
      if (Math.abs(factor - 1) > 0.0005) {
        scaleProjectPixelsPerFoot(factor);
      }

      state.vertices = scalePoints(currentRoom.vertices, source.w, source.h, state.canvasW, state.canvasH);
      state.closed = true;
      applyViewport();
      redrawAll();
      setFeedback('Editing "' + currentRoom.name + '". Change the shape and click Update room.', false);
    });
  }

  function cancelEdit() {
    if (!state.editingRoomId) return;
    clearShape();
    setFeedback('Edit cancelled.', false);
  }

  function renderRooms() {
    var project = activeProject();
    var rooms = project ? project.rooms : [];
    show(els.roomsEmpty, rooms.length === 0);
    show(els.roomsList, rooms.length > 0);
    show(els.total, rooms.length > 0);
    els.roomsList.innerHTML = '';

    var total = 0;
    rooms.forEach(function (room) {
      total += parseFloat(room.area_sqft) || 0;
      var li = document.createElement('li');
      li.className = 'util-ra-rooms-item' + (state.editingRoomId === room.id ? ' is-editing' : '');
      li.innerHTML =
        '<div class="util-ra-rooms-item__info">' +
          '<span class="util-ra-rooms-item__name">' + esc(room.name) + '</span>' +
          '<span class="util-ra-rooms-item__area">' + fmt(room.area_sqft, 2) + ' sq ft</span>' +
        '</div>' +
        '<div class="util-ra-rooms-item__actions">' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-edit-room="' + room.id + '" title="Edit room"><i class="fa fa-pencil"></i></button>' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-copy-area="' + room.area_sqft + '" title="Copy sq ft"><i class="fa fa-copy"></i></button>' +
          '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-delete-room="' + room.id + '" title="Delete"><i class="fa fa-trash"></i></button>' +
        '</div>';
      els.roomsList.appendChild(li);
    });

    els.totalSqft.textContent = fmt(total, 2);

    els.roomsList.querySelectorAll('[data-edit-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        loadRoom(parseInt(btn.getAttribute('data-edit-room'), 10));
      });
    });
    els.roomsList.querySelectorAll('[data-copy-area]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        copyText(btn.getAttribute('data-copy-area'));
      });
    });
    els.roomsList.querySelectorAll('[data-delete-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        deleteRoom(parseInt(btn.getAttribute('data-delete-room'), 10));
      });
    });
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(String(text)).then(function () {
        setFeedback('Copied to clipboard.', false);
      }).catch(function () {
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = String(text);
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      setFeedback('Copied to clipboard.', false);
    } catch (e) {
      setFeedback('Could not copy.', true);
    }
    document.body.removeChild(ta);
  }

  function renderWorkspace() {
    var hasProject = !!state.activeProjectId;
    show(els.empty, !hasProject);
    show(els.workspace, hasProject);
    show(els.deleteProjectBtn, hasProject);
    renderLinked();
    renderRooms();

    if (!hasProject) {
      resetEditor();
      return;
    }

    var project = activeProject();
    if (!project) {
      resetEditor();
      return;
    }

    var projectId = project.id;
    clearShape();
    var token = state.editorToken;
    ensureStage();
    loadBackground(project.background_url);
    scheduleStageResize();
    setTimeout(function () {
      if (token !== state.editorToken) return;
      var currentProject = activeProject();
      if (!currentProject || currentProject.id !== projectId) return;

      resizeStage();
      var factor = canvasScaleFactor(
        currentProject.canvas_width,
        currentProject.canvas_height,
        state.canvasW,
        state.canvasH
      );
      if (Math.abs(factor - 1) > 0.0005) {
        scaleProjectPixelsPerFoot(factor);
        currentProject.canvas_width = state.canvasW;
        currentProject.canvas_height = state.canvasH;
      }
      loadBackground(currentProject.background_url);
      updateScaleUI();
    }, 0);
  }

  function createProject() {
    var name = (els.newProjectName.value || '').trim();
    if (!name) {
      els.newProjectName.focus();
      return;
    }
    var clientId = els.newClientProject.value || null;
    els.createProjectBtn.disabled = true;

    fetch(root.getAttribute('data-project-store-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({ name: name, client_project_id: clientId ? parseInt(clientId, 10) : null })
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        els.createProjectBtn.disabled = false;
        if (!result.ok) throw new Error(result.data.message || 'Could not create project.');
        state.projects.unshift(result.data.project);
        state.activeProjectId = result.data.project.id;
        els.newProjectName.value = '';
        els.newClientProject.value = '';
        show(els.newProjectPanel, false);
        refreshProjectSelect();
        renderWorkspace();
      })
      .catch(function (e) { els.createProjectBtn.disabled = false; setFeedback(e.message, true); });
  }

  function updateProjectLink(clientProjectId) {
    var project = activeProject();
    if (!project) return;

    fetch(urlFromTemplate(root.getAttribute('data-project-update-template'), project.id), {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({ client_project_id: clientProjectId ? parseInt(clientProjectId, 10) : null })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) return;
        project.client_project_id = data.project.client_project_id;
        project.client_project_name = data.project.client_project_name;
        renderLinked();
      });
  }

  function deleteProject() {
    var project = activeProject();
    if (!project) return;

    confirmAction('Delete this project and all saved rooms?', { title: 'Delete project', danger: true }).then(function (ok) {
      if (!ok) return;

      fetch(urlFromTemplate(root.getAttribute('data-project-destroy-template'), project.id), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.ok) return;
          state.projects = state.projects.filter(function (p) { return p.id !== project.id; });
          state.activeProjectId = null;
          refreshProjectSelect();
          renderWorkspace();
          setFeedback('Project deleted.', false);
        });
    });
  }

  function saveRoom() {
    var project = activeProject();
    if (!project || !state.closed || state.vertices.length < 3) return;
    var name = (els.roomName.value || '').trim();
    if (!name) {
      els.roomName.focus();
      setFeedback('Enter a room name.', true);
      return;
    }

    var isUpdate = !!state.editingRoomId;
    var url = isUpdate
      ? urlFromTemplate(root.getAttribute('data-room-update-template'), state.editingRoomId)
      : urlFromTemplate(root.getAttribute('data-room-store-template'), project.id);
    var method = isUpdate ? 'PUT' : 'POST';

    els.saveRoom.disabled = true;
    fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({
        name: name,
        vertices: state.vertices,
        canvas_width: state.canvasW,
        canvas_height: state.canvasH
      })
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        els.saveRoom.disabled = false;
        if (!result.ok) throw new Error(result.data.message || (isUpdate ? 'Could not update room.' : 'Could not save room.'));
        if (isUpdate) {
          var idx = project.rooms.findIndex(function (r) { return r.id === result.data.room.id; });
          if (idx >= 0) project.rooms[idx] = result.data.room;
        } else {
          project.rooms.push(result.data.room);
        }
        project.canvas_width = state.canvasW;
        project.canvas_height = state.canvasH;
        resetDraftCanvas();
        renderRooms();
        setFeedback(
          isUpdate
            ? 'Room updated.'
            : 'Room saved. Draw another outline or click New room.',
          false
        );
      })
      .catch(function (e) {
        els.saveRoom.disabled = false;
        setFeedback(e.message, true);
      });
  }

  function deleteRoom(roomId) {
    confirmAction('Delete this room?', { title: 'Delete room', danger: true }).then(function (ok) {
      if (!ok) return;

      fetch(urlFromTemplate(root.getAttribute('data-room-destroy-template'), roomId), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.ok) return;
          var project = activeProject();
          if (project) {
            project.rooms = project.rooms.filter(function (r) { return r.id !== roomId; });
            clearShape();
            renderRooms();
            setFeedback('Room deleted.', false);
          }
        });
    });
  }

  function uploadBackground(file) {
    var project = activeProject();
    if (!project || !file) return;

    var fd = new FormData();
    fd.append('background', file);

    fetch(urlFromTemplate(root.getAttribute('data-background-upload-template'), project.id), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: fd
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok) throw new Error(result.data.message || 'Upload failed.');
        project.background_url = result.data.background_url;
        ensureStage();
        loadBackground(project.background_url);
        setFeedback('Floor plan uploaded.', false);
      })
      .catch(function (e) { setFeedback(e.message, true); });
  }

  function removeBackground() {
    var project = activeProject();
    if (!project || !project.background_url) return;

    confirmAction(
      'Delete the floor plan image? Your room outlines and saved rooms will stay.',
      { title: 'Delete floor plan', danger: true }
    ).then(function (ok) {
      if (!ok) return;

      fetch(urlFromTemplate(root.getAttribute('data-background-destroy-template'), project.id), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.ok) return;
          project.background_url = null;
          ensureStage();
          loadBackground(null);
          setFeedback('Floor plan image deleted.', false);
        });
    });
  }

  function bindEvents() {
    els.projectSelect.addEventListener('change', function () {
      state.activeProjectId = els.projectSelect.value ? parseInt(els.projectSelect.value, 10) : null;
      state.calibrating = false;
      state.calibPoints = [];
      els.calibrateToggle.classList.remove('is-active');
      els.knownLength.disabled = true;
      els.applyScale.disabled = true;
      renderWorkspace();
    });

    els.newProjectToggle.addEventListener('click', function () {
      show(els.newProjectPanel, els.newProjectPanel.hasAttribute('hidden'));
    });

    els.createProjectBtn.addEventListener('click', createProject);
    els.deleteProjectBtn.addEventListener('click', deleteProject);
    els.linkClientProject.addEventListener('change', function () {
      updateProjectLink(els.linkClientProject.value);
    });

    els.calibrateToggle.addEventListener('click', toggleCalibrate);
    els.applyScale.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      applyScale();
    });

    els.snapGroup.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-ra-snap]');
      if (!btn) return;
      state.snapMode = btn.getAttribute('data-ra-snap');
      els.snapGroup.querySelectorAll('.util-segmented__btn').forEach(function (b) {
        b.classList.toggle('is-active', b === btn);
      });
    });

    els.undoVertex.addEventListener('click', undoVertex);
    els.closeShape.addEventListener('click', closeShape);
    els.clearShape.addEventListener('click', clearShape);
    els.saveRoom.addEventListener('click', saveRoom);
    if (els.newRoom) els.newRoom.addEventListener('click', startNewRoom);
    if (els.newRoomSecondary) els.newRoomSecondary.addEventListener('click', startNewRoom);
    if (els.cancelEdit) els.cancelEdit.addEventListener('click', cancelEdit);

    if (els.wallApply) els.wallApply.addEventListener('click', applyWallLength);
    if (els.wallCancel) els.wallCancel.addEventListener('click', clearWallSelection);
    if (els.deleteWall) els.deleteWall.addEventListener('click', deleteSelectedWall);
    if (els.wallLength) {
      els.wallLength.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyWallLength();
        }
        if (e.key === 'Escape') clearWallSelection();
        if (e.key === 'Delete' || e.key === 'Backspace') {
          e.preventDefault();
          deleteSelectedWall();
        }
      });
    }

    els.bgFile.addEventListener('change', function () {
      if (els.bgFile.files && els.bgFile.files[0]) {
        uploadBackground(els.bgFile.files[0]);
        els.bgFile.value = '';
      }
    });

    els.bgRemove.addEventListener('click', removeBackground);
    if (els.bgDeleteCanvas) els.bgDeleteCanvas.addEventListener('click', removeBackground);
    els.copyTotal.addEventListener('click', function () {
      copyText(els.totalSqft.textContent);
    });

    if (els.zoomIn) els.zoomIn.addEventListener('click', function () { zoomBy(ZOOM_FACTOR); });
    if (els.zoomOut) els.zoomOut.addEventListener('click', function () { zoomBy(1 / ZOOM_FACTOR); });
    if (els.zoom100) els.zoom100.addEventListener('click', resetViewport);
    if (els.zoomReset) els.zoomReset.addEventListener('click', fitViewport);

    window.addEventListener('resize', scheduleStageResize);
  }

  function init() {
    state.projects = parseJson('data-projects', []);
    bindEvents();
    bindViewportEvents();
    refreshProjectSelect();
    renderWorkspace();
  }

  init();
})();

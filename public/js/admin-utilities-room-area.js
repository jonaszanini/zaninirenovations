/* Room Area tool — Konva polygon drawing with scale calibration */
(function () {
  'use strict';

  if (typeof Konva === 'undefined') return;

  var root = document.querySelector('[data-room-area]');
  if (!root) return;

  var INCHES_PER_FOOT = 12;
  var SQFT_TO_SQM = 0.092903;
  var FEET_TO_METERS = 0.3048;
  var MEASURE_UNIT_STORAGE_KEY = 'room-area-measure-unit';
  var MEASURE_UNITS = {
    meter: 'meter',
    inch: 'inch',
    feet_decimal: 'feet_decimal',
    feet_inch_frac: 'feet_inch_frac'
  };
  var MIN_CANVAS_W = 400;
  var MIN_CANVAS_H = 520;
  var MAX_CANVAS_H = 1100;
  var CANVAS_HEIGHT_RATIO = 0.75;
  var CANVAS_MAX_VH = 0.72;
  var DRAW_AREA_FACTOR = 6;
  var GRID_PX = 20;
  var VERTEX_HIT = 14;
  var CLOSE_HIT = 16;
  var WALL_HIT = 14;
  var RULER_HIT = 12;
  var MIN_ZOOM_PERCENT = 8;
  var MAX_ZOOM_PERCENT = 500;
  var ZOOM_STEP = 1.12;
  var ZOOM_FIT_PAD = 32;
  var SHIFT_SNAP_THRESHOLD_DEG = 10;
  var SHIFT_SNAP_STEP = Math.PI / 4;
  var UNDO_MAX = 50;
  var AREA_NAME_MAX = 80;
  var WALL_DOUBLE_CLICK_MS = 400;
  var WALL_SPLIT_DEBOUNCE_MS = 400;
  var GROUP_COLORS = ['#024768', '#2e7d32', '#e65100', '#6a1b9a', '#c62828', '#00838f', '#5d4037'];
  var DEFAULT_GROUP_COLOR = GROUP_COLORS[0];
  var INACTIVE_POLYGON_OPACITY = 0.45;

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
    bgShowWrap: root.querySelector('[data-ra-bg-show-wrap]'),
    bgShowImage: root.querySelector('[data-ra-bg-show]'),
    bgMoveToggle: root.querySelector('[data-ra-bg-move]'),
    undoVertex: root.querySelector('[data-ra-undo-vertex]'),
    closeShape: root.querySelector('[data-ra-close-shape]'),
    addArea: root.querySelector('[data-ra-add-area]'),
    addAreaNewGroup: root.querySelector('[data-ra-add-area-new-group]'),
    deleteArea: root.querySelector('[data-ra-delete-area]'),
    groupChip: root.querySelector('[data-ra-group-chip]'),
    groupChipSwatch: root.querySelector('[data-ra-group-chip-swatch]'),
    groupChipTotal: root.querySelector('[data-ra-group-chip-total]'),
    clearShape: root.querySelector('[data-ra-clear-shape]'),
    deleteWall: root.querySelector('[data-ra-delete-wall]'),
    vertexEdit: root.querySelector('[data-ra-vertex-edit]'),
    deleteVertex: root.querySelector('[data-ra-delete-vertex]'),
    vertexCancel: root.querySelector('[data-ra-vertex-cancel]'),
    rulerToggle: root.querySelector('[data-ra-ruler-toggle]'),
    rulerEdit: root.querySelector('[data-ra-ruler-edit]'),
    rulerLength: root.querySelector('[data-ra-ruler-length]'),
    deleteRuler: root.querySelector('[data-ra-delete-ruler]'),
    rulerCancel: root.querySelector('[data-ra-ruler-cancel]'),
    canvasWrap: root.querySelector('[data-ra-canvas-wrap]'),
    liveArea: root.querySelector('[data-ra-live-area]'),
    liveActiveShape: root.querySelector('[data-ra-live-active-shape]'),
    liveGroupList: root.querySelector('[data-ra-live-group-list]'),
    liveGrandTotal: root.querySelector('[data-ra-live-grand-total]'),
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
    wallScaleProject: root.querySelector('[data-ra-wall-scale-project]'),
    wallApply: root.querySelector('[data-ra-wall-apply]'),
    wallCancel: root.querySelector('[data-ra-wall-cancel]'),
    zoomIn: root.querySelector('[data-ra-zoom-in]'),
    zoomOut: root.querySelector('[data-ra-zoom-out]'),
    zoom100: root.querySelector('[data-ra-zoom-100]'),
    zoomReset: root.querySelector('[data-ra-zoom-reset]'),
    zoomLabel: root.querySelector('[data-ra-zoom-label]'),
    measureUnit: root.querySelector('[data-ra-measure-unit]'),
    areaName: root.querySelector('[data-ra-area-name]'),
    areaNameWrap: root.querySelector('[data-ra-area-name-wrap]')
  };

  var state = {
    projects: [],
    activeProjectId: null,
    groups: [],
    activeGroupId: null,
    activePolygonId: null,
    nextGroupId: 1,
    nextPolygonId: 1,
    calibrating: false,
    calibPoints: [],
    rulerMode: false,
    rulerDraft: [],
    selectedRulerId: null,
    nextRulerId: 1,
    rulerPreview: null,
    drawPreview: null,
    draftRulers: [],
    activeBackgroundUrl: null,
    bgImageVisible: true,
    bgLayout: null,
    bgMoveMode: false,
    draggingBg: false,
    draftBackground: null,
    draftScale: null,
    workingPpf: null,
    stage: null,
    worldGroup: null,
    viewport: { scale: 1, x: 0, y: 0, zoomPercent: 100 },
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
    selectedVertexIndices: [],
    draggingVertex: false,
    draggingWall: false,
    wallDragIndex: null,
    wallDragPointerStart: null,
    wallDragOrigA: null,
    wallDragOrigB: null,
    wallPointerDown: null,
    wallClickTimer: null,
    lastWallClick: null,
    lastWallSplit: null,
    didWallDrag: false,
    didVertexDrag: false,
    vertexDragFrom: null,
    vertexDragOrigins: null,
    undoStack: [],
    draggingRuler: false,
    editingRoomId: null,
    editorToken: 0,
    savedSnapshot: null,
    measureUnit: MEASURE_UNITS.feet_inch_frac,
    viewportW: 900,
    viewportH: 640,
    canvasW: 5400,
    canvasH: 3840
  };

  var resizeTimer = null;

  function cloneGroups(groups) {
    return (groups || []).map(function (group) {
      return {
        id: group.id,
        color: group.color,
        polygons: (group.polygons || []).map(function (poly) {
          return {
            id: poly.id,
            closed: !!poly.closed,
            name: polygonName(poly),
            vertices: (poly.vertices || []).map(function (v) {
              return { x: v.x, y: v.y };
            })
          };
        })
      };
    });
  }

  function findGroupById(groupId) {
    return state.groups.find(function (g) { return g.id === groupId; }) || null;
  }

  function findPolygonInGroup(group, polygonId) {
    if (!group) return null;
    return group.polygons.find(function (p) { return p.id === polygonId; }) || null;
  }

  function activeGroup() {
    return findGroupById(state.activeGroupId);
  }

  function activePolygon() {
    var group = activeGroup();
    return group ? findPolygonInGroup(group, state.activePolygonId) : null;
  }

  function ensureInitialCanvas() {
    if (!state.groups.length) {
      startNewGroup(false);
    }
    if (!activePolygon()) {
      var group = activeGroup() || state.groups[0];
      if (group && group.polygons.length) {
        state.activeGroupId = group.id;
        state.activePolygonId = group.polygons[0].id;
      } else if (group) {
        startNewPolygon(group.id);
      }
    }
  }

  function activeVertices() {
    ensureInitialCanvas();
    var poly = activePolygon();
    return poly ? poly.vertices : [];
  }

  function isActiveClosed() {
    var poly = activePolygon();
    return poly ? !!poly.closed : false;
  }

  function setActiveClosed(closed) {
    var poly = activePolygon();
    if (poly) poly.closed = !!closed;
  }

  function setActiveVertices(vertices) {
    var poly = activePolygon();
    if (!poly) return;
    poly.vertices = (vertices || []).map(function (v) {
      return { x: parseFloat(v.x), y: parseFloat(v.y) };
    });
  }

  function paletteColorForIndex(index) {
    return GROUP_COLORS[index % GROUP_COLORS.length];
  }

  function createPolygon() {
    var id = 'p' + state.nextPolygonId;
    state.nextPolygonId += 1;
    return { id: id, vertices: [], closed: false, name: '' };
  }

  function polygonName(poly) {
    return poly && typeof poly.name === 'string' ? poly.name.trim() : '';
  }

  function sanitizeAreaName(raw) {
    return String(raw || '').replace(/\s+/g, ' ').trim().slice(0, AREA_NAME_MAX);
  }

  function createGroup(color) {
    var id = 'g' + state.nextGroupId;
    state.nextGroupId += 1;
    var poly = createPolygon();
    return {
      id: id,
      color: color || paletteColorForIndex(state.groups.length),
      polygons: [poly]
    };
  }

  function startNewGroup(selectIt) {
    var group = createGroup();
    state.groups.push(group);
    if (selectIt !== false) {
      state.activeGroupId = group.id;
      state.activePolygonId = group.polygons[0].id;
      clearWallSelection();
      clearVertexSelection();
    }
    return group;
  }

  function startNewPolygon(groupId) {
    var group = groupId ? findGroupById(groupId) : activeGroup();
    if (!group) group = startNewGroup();
    var poly = createPolygon();
    group.polygons.push(poly);
    state.activeGroupId = group.id;
    state.activePolygonId = poly.id;
    clearWallSelection();
    clearVertexSelection();
    return poly;
  }

  function resetCanvasGroups() {
    state.groups = [];
    state.activeGroupId = null;
    state.activePolygonId = null;
    state.nextGroupId = 1;
    state.nextPolygonId = 1;
  }

  function allPolygons() {
    var out = [];
    state.groups.forEach(function (group) {
      (group.polygons || []).forEach(function (poly) {
        out.push({ group: group, polygon: poly });
      });
    });
    return out;
  }

  function allClosedPolygons() {
    return allPolygons().filter(function (entry) {
      return entry.polygon.closed && entry.polygon.vertices.length >= 3;
    });
  }

  function hasAnyClosedPolygon() {
    return allClosedPolygons().length > 0;
  }

  function groupAreaSqFt(group) {
    var total = 0;
    (group.polygons || []).forEach(function (poly) {
      if (poly.closed && poly.vertices.length >= 3) {
        var sqft = areaSqFt(poly.vertices);
        if (sqft !== null) total += sqft;
      }
    });
    return Math.round(total * 100) / 100;
  }

  function canvasTotalAreaSqFt() {
    var total = 0;
    state.groups.forEach(function (group) {
      total += groupAreaSqFt(group);
    });
    return Math.round(total * 100) / 100;
  }

  function pointInPolygon(point, vertices) {
    var inside = false;
    var n = vertices.length;
    if (n < 3) return false;
    for (var i = 0, j = n - 1; i < n; j = i++) {
      var xi = vertices[i].x;
      var yi = vertices[i].y;
      var xj = vertices[j].x;
      var yj = vertices[j].y;
      var intersect = ((yi > point.y) !== (yj > point.y))
        && (point.x < ((xj - xi) * (point.y - yi)) / (yj - yi + 1e-12) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function distanceToSegment(point, a, b) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    if (Math.abs(dx) < 1e-9 && Math.abs(dy) < 1e-9) return dist(point, a);
    var t = ((point.x - a.x) * dx + (point.y - a.y) * dy) / (dx * dx + dy * dy);
    t = Math.max(0, Math.min(1, t));
    return dist(point, { x: a.x + t * dx, y: a.y + t * dy });
  }

  function polygonAtPoint(pos) {
    var hit = null;
    var bestDist = Infinity;
    allPolygons().forEach(function (entry) {
      var poly = entry.polygon;
      var verts = poly.vertices;
      if (verts.length < 2) return;
      if (poly.closed && verts.length >= 3 && pointInPolygon(pos, verts)) {
        hit = entry;
        bestDist = 0;
        return;
      }
      var wallTotal = poly.closed ? verts.length : verts.length - 1;
      for (var i = 0; i < wallTotal; i++) {
        var a = verts[i];
        var b = verts[(i + 1) % verts.length];
        var d = distanceToSegment(pos, a, b);
        if (d <= hitTolerance(WALL_HIT) && d < bestDist) {
          bestDist = d;
          hit = entry;
        }
      }
    });
    return hit;
  }

  function activatePolygon(groupId, polygonId) {
    if (state.activeGroupId === groupId && state.activePolygonId === polygonId) return;
    state.activeGroupId = groupId;
    state.activePolygonId = polygonId;
    clearWallSelection();
    clearVertexSelection();
    updateGroupChip();
    redrawAll();
  }

  function deleteActivePolygon() {
    var group = activeGroup();
    var poly = activePolygon();
    if (!group || !poly) return;
    pushUndoSnapshot();
    if (group.polygons.length === 1 && state.groups.length === 1 && !poly.closed && !poly.vertices.length) {
      discardLastUndoSnapshot();
      return;
    }
    group.polygons = group.polygons.filter(function (p) { return p.id !== poly.id; });
    if (!group.polygons.length) {
      state.groups = state.groups.filter(function (g) { return g.id !== group.id; });
    }
    var next = allPolygons()[0];
    if (next) {
      state.activeGroupId = next.group.id;
      state.activePolygonId = next.polygon.id;
    } else {
      state.activeGroupId = null;
      state.activePolygonId = null;
      ensureInitialCanvas();
    }
    clearWallSelection();
    clearVertexSelection();
    if (!allPolygons().length) {
      ensureInitialCanvas();
    }
    redrawAll();
  }

  function serializeCanvas() {
    var groups = [];
    state.groups.forEach(function (group) {
      var polygons = [];
      (group.polygons || []).forEach(function (poly) {
        if (!poly.closed || poly.vertices.length < 3) return;
        var item = {
          id: poly.id,
          vertices: poly.vertices.map(function (v) {
            return { x: Math.round(v.x * 1000) / 1000, y: Math.round(v.y * 1000) / 1000 };
          })
        };
        var name = polygonName(poly);
        if (name) item.name = name.slice(0, AREA_NAME_MAX);
        polygons.push(item);
      });
      if (polygons.length) {
        groups.push({
          id: group.id,
          color: group.color,
          polygons: polygons
        });
      }
    });
    var payload = { v: 2, groups: groups };
    var bg = captureBackgroundLayoutForSave();
    if (bg) payload.background = bg;
    return payload;
  }

  function captureBackgroundLayoutForSave() {
    syncBackgroundLayoutFromKonva();
    if (!state.bgLayout) return null;
    return {
      x: Math.round(state.bgLayout.x * 1000) / 1000,
      y: Math.round(state.bgLayout.y * 1000) / 1000,
      width: Math.round(state.bgLayout.width * 1000) / 1000,
      height: Math.round(state.bgLayout.height * 1000) / 1000
    };
  }

  function syncBackgroundLayoutFromKonva() {
    if (!state.bgImage) return;
    state.bgLayout = {
      x: state.bgImage.x(),
      y: state.bgImage.y(),
      width: state.bgImage.width(),
      height: state.bgImage.height()
    };
  }

  function applyBackgroundLayoutToKonva() {
    if (!state.bgImage || !state.bgLayout) return;
    state.bgImage.position({ x: state.bgLayout.x, y: state.bgLayout.y });
    state.bgImage.width(state.bgLayout.width);
    state.bgImage.height(state.bgLayout.height);
    flushKonva();
  }

  function hydrateBackgroundLayout(bg, scaleFromW, scaleFromH, scaleToW, scaleToH) {
    if (!bg || bg.x == null || bg.y == null || !bg.width || !bg.height) {
      state.bgLayout = null;
      return;
    }
    if (!scaleFromW || !scaleFromH || scaleFromW < 1 || scaleFromH < 1) {
      state.bgLayout = {
        x: parseFloat(bg.x),
        y: parseFloat(bg.y),
        width: parseFloat(bg.width),
        height: parseFloat(bg.height)
      };
      return;
    }
    var s = canvasScaleFactor(scaleFromW, scaleFromH, scaleToW, scaleToH);
    state.bgLayout = {
      x: parseFloat(bg.x) * s,
      y: parseFloat(bg.y) * s,
      width: parseFloat(bg.width) * s,
      height: parseFloat(bg.height) * s
    };
  }

  function scaleBgLayoutState(fromW, fromH, toW, toH) {
    if (!state.bgLayout || !fromW || fromW < 1) return;
    var s = canvasScaleFactor(fromW, fromH, toW, toH);
    if (s === 1) return;
    state.bgLayout = {
      x: state.bgLayout.x * s,
      y: state.bgLayout.y * s,
      width: state.bgLayout.width * s,
      height: state.bgLayout.height * s
    };
  }

  function normalizeCanvasInput(raw) {
    if (!raw) return { v: 2, groups: [] };
    if (Array.isArray(raw) && raw.length && raw[0] && typeof raw[0].x === 'number') {
      return {
        v: 2,
        groups: [{
          id: 'g1',
          color: DEFAULT_GROUP_COLOR,
          polygons: [{ id: 'p1', vertices: raw }]
        }]
      };
    }
    if (raw.v === 2 && Array.isArray(raw.groups)) {
      var out = { v: 2, groups: raw.groups };
      if (raw.background) out.background = raw.background;
      return out;
    }
    return { v: 2, groups: [] };
  }

  function hydrateCanvas(raw, scaleFromW, scaleFromH, scaleToW, scaleToH) {
    resetCanvasGroups();
    var data = normalizeCanvasInput(raw);
    var maxGroupNum = 0;
    var maxPolyNum = 0;
    data.groups.forEach(function (group, gi) {
      var groupId = group.id || ('g' + (gi + 1));
      var groupNum = parseInt(String(groupId).replace(/\D/g, ''), 10);
      if (groupNum > maxGroupNum) maxGroupNum = groupNum;
      var polygons = [];
      (group.polygons || []).forEach(function (poly, pi) {
        var polyId = poly.id || ('p' + (pi + 1));
        var polyNum = parseInt(String(polyId).replace(/\D/g, ''), 10);
        if (polyNum > maxPolyNum) maxPolyNum = polyNum;
        var verts = poly.vertices || [];
        if (scaleFromW && scaleFromH && scaleToW && scaleToH) {
          verts = scalePoints(verts, scaleFromW, scaleFromH, scaleToW, scaleToH);
        }
        if (verts.length < 3) return;
        polygons.push({
          id: polyId,
          closed: true,
          name: sanitizeAreaName(poly.name),
          vertices: verts.map(function (v) { return { x: v.x, y: v.y }; })
        });
      });
      if (polygons.length) {
        state.groups.push({
          id: groupId,
          color: group.color || paletteColorForIndex(gi),
          polygons: polygons
        });
      }
    });
    state.nextGroupId = Math.max(maxGroupNum + 1, state.groups.length + 1);
    state.nextPolygonId = Math.max(maxPolyNum + 1, 2);
    var first = allPolygons()[0];
    if (first) {
      state.activeGroupId = first.group.id;
      state.activePolygonId = first.polygon.id;
    } else {
      ensureInitialCanvas();
    }
    if (data.background) {
      hydrateBackgroundLayout(data.background, scaleFromW, scaleFromH, scaleToW, scaleToH);
    } else {
      state.bgLayout = null;
    }
  }

  function scaleAllCanvasPoints(fromW, fromH, toW, toH) {
    if (!fromW || !fromH || fromW < 1 || fromH < 1) return;
    state.groups.forEach(function (group) {
      group.polygons.forEach(function (poly) {
        poly.vertices = scalePoints(poly.vertices, fromW, fromH, toW, toH);
      });
    });
  }

  function syncIdCountersFromGroups() {
    var maxGroup = 0;
    var maxPoly = 0;
    state.groups.forEach(function (group) {
      var gn = parseInt(String(group.id).replace(/\D/g, ''), 10);
      if (gn > maxGroup) maxGroup = gn;
      group.polygons.forEach(function (poly) {
        var pn = parseInt(String(poly.id).replace(/\D/g, ''), 10);
        if (pn > maxPoly) maxPoly = pn;
      });
    });
    state.nextGroupId = Math.max(state.nextGroupId, maxGroup + 1);
    state.nextPolygonId = Math.max(state.nextPolygonId, maxPoly + 1);
  }

  function updateGroupChip() {
    if (!els.groupChip) return;
    var group = activeGroup();
    var hasClosed = hasAnyClosedPolygon();
    show(els.groupChip, !!group && hasClosed);
    if (!group || !hasClosed) return;
    if (els.groupChipSwatch) els.groupChipSwatch.style.backgroundColor = group.color;
    if (els.groupChipTotal) {
      var sqft = groupAreaSqFt(group);
      var count = (group.polygons || []).filter(function (p) { return p.closed && p.vertices.length >= 3; }).length;
      els.groupChipTotal.textContent = fmt(sqft, 2) + ' sq ft' + (count > 1 ? ' (' + count + ' areas)' : '');
    }
  }

  function getViewportDimensions() {
    var wrap = els.canvasWrap;
    if (!wrap || wrap.clientWidth < 1) {
      return { w: state.viewportW || 900, h: state.viewportH || 640 };
    }
    var w = Math.max(MIN_CANVAS_W, Math.floor(wrap.clientWidth));
    var maxH = Math.min(Math.floor(window.innerHeight * CANVAS_MAX_VH), MAX_CANVAS_H);
    var h = Math.max(MIN_CANVAS_H, Math.min(maxH, Math.round(w * CANVAS_HEIGHT_RATIO)));
    return { w: w, h: h };
  }

  function syncDrawableDimensions() {
    var vp = getViewportDimensions();
    state.viewportW = vp.w;
    state.viewportH = vp.h;
    state.canvasW = Math.round(vp.w * DRAW_AREA_FACTOR);
    state.canvasH = Math.round(vp.h * DRAW_AREA_FACTOR);
    syncCanvasWrapSize(vp.w, vp.h);
    return vp;
  }

  function syncCanvasWrapSize(w, h) {
    if (!els.canvasWrap) return;
    els.canvasWrap.style.height = h + 'px';
  }

  function viewportW() {
    return state.viewportW || Math.round(state.canvasW / DRAW_AREA_FACTOR);
  }

  function viewportH() {
    return state.viewportH || Math.round(state.canvasH / DRAW_AREA_FACTOR);
  }

  function canvasW() {
    return state.canvasW;
  }

  function canvasH() {
    return state.canvasH;
  }

  /** Natural drawing density: one screen pixel at 100% zoom. Extra world beyond this is pan space. */
  function contentW() {
    return viewportW();
  }

  function contentH() {
    return viewportH();
  }

  function scalePoints(points, fromW, fromH, toW, toH) {
    if (!points || !points.length) return [];
    var s = canvasScaleFactor(fromW, fromH, toW, toH);
    return points.map(function (v) {
      return { x: parseFloat(v.x) * s, y: parseFloat(v.y) * s };
    });
  }

  /**
   * One factor for x, y, and pixels-per-foot. Stretching width and height
   * independently (or scaling geometry without scale) changes wall lengths.
   */
  function canvasScaleFactor(fromW, fromH, toW, toH) {
    if (!fromW || fromW < 1 || !toW) return 1;
    var s = toW / fromW;
    if (!isFinite(s) || s <= 0) return 1;
    if (Math.abs(s - 1) < 0.0005) return 1;
    return s;
  }

  function activeRoom() {
    var project = activeProject();
    if (!project || !state.editingRoomId) return null;
    return project.rooms.find(function (r) { return r.id === state.editingRoomId; }) || null;
  }

  function revokeDraftBackground() {
    if (state.draftBackground && state.draftBackground.url) {
      URL.revokeObjectURL(state.draftBackground.url);
    }
    state.draftBackground = null;
  }

  function currentBackgroundUrl() {
    if (state.draftBackground && state.draftBackground.url) return state.draftBackground.url;
    var room = activeRoom();
    if (room && room.background_url) return room.background_url;
    return null;
  }

  function setWorkingPixelsPerFoot(ppf) {
    var n = parseFloat(ppf);
    state.workingPpf = (n && isFinite(n) && n > 0) ? n : null;
  }

  function scaleActivePixelsPerFoot(factor) {
    if (Math.abs(factor - 1) < 0.0005) return;
    var current = pixelsPerFoot();
    if (!current) return;
    setWorkingPixelsPerFoot(current * factor);
    if (!state.editingRoomId) {
      state.draftScale = state.workingPpf;
    }
  }

  function roomSourceCanvas(room, project) {
    return {
      w: room.canvas_width || project.canvas_width || contentW(),
      h: room.canvas_height || project.canvas_height || contentH()
    };
  }

  function canvasExtentsFromRaw(raw) {
    var maxX = 0;
    var maxY = 0;
    var data = normalizeCanvasInput(raw);
    data.groups.forEach(function (group) {
      (group.polygons || []).forEach(function (poly) {
        (poly.vertices || []).forEach(function (v) {
          maxX = Math.max(maxX, parseFloat(v.x) || 0);
          maxY = Math.max(maxY, parseFloat(v.y) || 0);
        });
      });
    });
    return { w: Math.max(maxX, 1), h: Math.max(maxY, 1) };
  }

  /**
   * Map saved coordinates into viewport-density content space.
   */
  function hydrateScaleTarget(raw, sourceW, sourceH) {
    var toW = contentW();
    var toH = contentH();
    var ext = canvasExtentsFromRaw(raw);
    var fromW = Math.max(sourceW || 0, ext.w);
    var fromH = Math.max(sourceH || 0, ext.h);

    if (!fromW || !fromH || fromW < 1 || fromH < 1) {
      return { fromW: toW, fromH: toH, toW: toW, toH: toH };
    }

    if (Math.abs(fromW - toW) < 3 && Math.abs(fromH - toH) < 3) {
      return { fromW: toW, fromH: toH, toW: toW, toH: toH };
    }

    // Oversized canvas metadata but geometry only occupies the content viewport.
    if (fromW > toW * 1.4 && ext.w <= toW * 1.35 && ext.h <= toH * 1.35) {
      return { fromW: toW, fromH: toH, toW: toW, toH: toH };
    }

    return { fromW: fromW, fromH: fromH, toW: toW, toH: toH };
  }

  function applyWorkingScaleFromSaved(savedPpf, target) {
    var base = parseFloat(savedPpf) || 0;
    if (base <= 0 || !target) {
      setWorkingPixelsPerFoot(null);
      return;
    }
    var s = canvasScaleFactor(target.fromW, target.fromH, target.toW, target.toH);
    setWorkingPixelsPerFoot(base * s);
  }

  function confirmAction(message, options) {
    if (window.AdminDialog && window.AdminDialog.confirm) {
      return window.AdminDialog.confirm(message, options);
    }
    return Promise.resolve(window.confirm(message));
  }

  function normalizeVertices(vertices) {
    return (vertices || []).map(function (v) {
      return {
        x: Math.round(v.x * 1000) / 1000,
        y: Math.round(v.y * 1000) / 1000
      };
    });
  }

  function buildWorkSnapshot() {
    return {
      editingRoomId: state.editingRoomId,
      name: (els.roomName && els.roomName.value || '').trim(),
      groups: cloneGroups(state.groups),
      activeGroupId: state.activeGroupId,
      activePolygonId: state.activePolygonId,
      scale: pixelsPerFoot() || null,
      backgroundUrl: state.activeBackgroundUrl || null,
      hasDraftBackground: !!(state.draftBackground && state.draftBackground.file),
      draftScale: state.draftScale ? parseFloat(state.draftScale) : null,
      rulers: projectRulers().map(function (r) {
        return {
          id: r.id,
          p1: normalizeVertices([r.p1])[0],
          p2: normalizeVertices([r.p2])[0]
        };
      }),
      bgLayout: state.bgLayout
        ? {
          x: Math.round(state.bgLayout.x * 1000) / 1000,
          y: Math.round(state.bgLayout.y * 1000) / 1000,
          width: Math.round(state.bgLayout.width * 1000) / 1000,
          height: Math.round(state.bgLayout.height * 1000) / 1000
        }
        : null
    };
  }

  function captureSavedSnapshot() {
    state.savedSnapshot = JSON.stringify(buildWorkSnapshot());
  }

  function hasUnsavedChanges() {
    if (!state.savedSnapshot) return false;
    return state.savedSnapshot !== JSON.stringify(buildWorkSnapshot());
  }

  function promptUnsavedBeforeAction(action, options) {
    options = options || {};
    if (!hasUnsavedChanges()) {
      action();
      return Promise.resolve(true);
    }
    return confirmAction(
      options.message || 'You have unsaved changes. Save them before continuing?',
      {
        title: 'Unsaved changes',
        confirmLabel: options.confirmLabel || 'Save changes',
        cancelLabel: options.cancelLabel || 'Stay on page'
      }
    ).then(function (shouldSave) {
      if (!shouldSave) return false;
      return saveRoom()
        .then(function () {
          action();
          return true;
        })
        .catch(function (err) {
          setFeedback((err && err.message) || 'Could not save changes.', true);
          return false;
        });
    });
  }

  function saveErrorMessage(data, fallback) {
    if (!data) return fallback;
    if (data.message && (!data.errors || typeof data.errors !== 'object')) {
      return data.message;
    }
    if (data.errors && typeof data.errors === 'object') {
      var keys = Object.keys(data.errors);
      for (var i = 0; i < keys.length; i++) {
        var msgs = data.errors[keys[i]];
        if (Array.isArray(msgs) && msgs.length) return msgs[0];
        if (typeof msgs === 'string' && msgs) return msgs;
      }
    }
    return data.message || fallback;
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

  function projectRulers() {
    var room = activeRoom();
    if (room) {
      if (!room.rulers) room.rulers = [];
      return room.rulers;
    }
    if (!state.draftRulers) state.draftRulers = [];
    return state.draftRulers;
  }

  function findRulerById(id) {
    var rulers = projectRulers();
    for (var i = 0; i < rulers.length; i++) {
      if (rulers[i].id === id) return rulers[i];
    }
    return null;
  }

  function rulerLengthFeet(ruler) {
    var ppf = pixelsPerFoot();
    if (!ppf || !ruler) return null;
    return dist(ruler.p1, ruler.p2) / ppf;
  }

  function rulerLengthLabel(ruler) {
    var feet = rulerLengthFeet(ruler);
    if (feet === null) return 'Scale not set';
    return fmtLengthFromFeet(feet);
  }

  function findRulerAtPoint(pos) {
    if (!pos) return null;
    var rulers = projectRulers();
    var hit = hitTolerance(RULER_HIT);
    var best = null;
    var bestDist = hit + 1;
    for (var i = rulers.length - 1; i >= 0; i--) {
      var ruler = rulers[i];
      var d = pointToSegmentDistance(pos, ruler.p1, ruler.p2);
      if (d <= hit && d < bestDist) {
        best = ruler;
        bestDist = d;
      }
    }
    return best;
  }

  function scaleProjectRulers(oldW, oldH, newW, newH) {
    var rulers = projectRulers();
    if (!rulers.length && !state.rulerDraft.length && !state.rulerPreview) return;
    rulers.forEach(function (ruler) {
      ruler.p1 = scalePoints([ruler.p1], oldW, oldH, newW, newH)[0];
      ruler.p2 = scalePoints([ruler.p2], oldW, oldH, newW, newH)[0];
    });
    if (state.rulerDraft.length) {
      state.rulerDraft = scalePoints(state.rulerDraft, oldW, oldH, newW, newH);
    }
    if (state.rulerPreview) {
      state.rulerPreview = scalePoints([state.rulerPreview], oldW, oldH, newW, newH)[0];
    }
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
    if ((m = s.match(/^([\d.]+)\s*['′]\s*([\d.]+)\s+(\d+)\s*\/\s*(\d+)\s*["″]?$/))) {
      return parseFloat(m[1]) + (parseFloat(m[2]) + parseFloat(m[3]) / parseFloat(m[4])) / INCHES_PER_FOOT;
    }
    if ((m = s.match(/^([\d.]+)\s*['′]\s*(\d+)\s*\/\s*(\d+)\s*["″]?$/))) {
      return parseFloat(m[1]) + (parseFloat(m[2]) / parseFloat(m[3])) / INCHES_PER_FOOT;
    }
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

  function loadMeasureUnit() {
    try {
      var saved = localStorage.getItem(MEASURE_UNIT_STORAGE_KEY);
      if (saved && MEASURE_UNITS[saved]) return saved;
    } catch (e) { /* ignore */ }
    return MEASURE_UNITS.feet_inch_frac;
  }

  function reduceFraction(num, den) {
    function gcd(a, b) { return b ? gcd(b, a % b) : Math.abs(a); }
    var g = gcd(num, den);
    return { num: num / g, den: den / g };
  }

  function formatSixteenthInches(sixteenths) {
    if (!sixteenths) return '';
    var parts = reduceFraction(sixteenths, 16);
    if (parts.num === parts.den) return '1';
    return parts.num + '/' + parts.den;
  }

  function fmtFeetInchesFrac(lenFt) {
    var totalInches = lenFt * INCHES_PER_FOOT;
    var rounded = Math.round(totalInches * 16) / 16;
    if (rounded < 1 / 16) return '0"';
    var feet = Math.floor(rounded / 12);
    var rem = rounded - feet * 12;
    var whole = Math.floor(rem);
    var frac = Math.round((rem - whole) * 16);
    if (frac === 16) { whole += 1; frac = 0; }
    if (whole === 12) { feet += 1; whole = 0; }
    var fracStr = formatSixteenthInches(frac);

    if (feet === 0) {
      if (fracStr === '') return whole + '"';
      if (whole === 0) return fracStr + '"';
      return whole + ' ' + fracStr + '"';
    }
    if (whole === 0 && fracStr === '') return feet + "'";
    if (whole === 0) return feet + "' " + fracStr + '"';
    if (fracStr === '') return feet + "' " + whole + '"';
    return feet + "' " + whole + ' ' + fracStr + '"';
  }

  function fmtFeetDecimal(lenFt) {
    return fmt(Math.round(lenFt * 1000) / 1000, 3) + ' ft';
  }

  function fmtInchesOnly(lenFt) {
    var inches = lenFt * INCHES_PER_FOOT;
    var decimals = inches >= 100 ? 1 : 2;
    return fmt(inches, decimals) + '"';
  }

  function fmtMeters(lenFt) {
    var meters = lenFt * FEET_TO_METERS;
    var decimals = meters >= 10 ? 2 : 3;
    return fmt(meters, decimals) + ' m';
  }

  function fmtLengthFromFeet(lenFt) {
    if (lenFt == null || !isFinite(lenFt)) return '—';
    switch (state.measureUnit) {
      case MEASURE_UNITS.meter:
        return fmtMeters(lenFt);
      case MEASURE_UNITS.inch:
        return fmtInchesOnly(lenFt);
      case MEASURE_UNITS.feet_decimal:
        return fmtFeetDecimal(lenFt);
      case MEASURE_UNITS.feet_inch_frac:
      default:
        return fmtFeetInchesFrac(lenFt);
    }
  }

  function measureUnitPlaceholder() {
    switch (state.measureUnit) {
      case MEASURE_UNITS.meter:
        return 'e.g. 3.5 m';
      case MEASURE_UNITS.inch:
        return 'e.g. 150"';
      case MEASURE_UNITS.feet_decimal:
        return 'e.g. 12.5 ft';
      default:
        return 'e.g. 12\' 6 1/2"';
    }
  }

  function updateMeasureUnitPlaceholders() {
    var ph = measureUnitPlaceholder();
    if (els.wallLength) els.wallLength.placeholder = ph;
    if (els.knownLength) els.knownLength.placeholder = ph;
  }

  function setMeasureUnit(unit) {
    if (!MEASURE_UNITS[unit]) return;
    state.measureUnit = unit;
    try { localStorage.setItem(MEASURE_UNIT_STORAGE_KEY, unit); } catch (e) { /* ignore */ }
    if (els.measureUnit) els.measureUnit.value = unit;
    updateMeasureUnitPlaceholders();
    redrawLabels();
    redrawRulers();
    refreshWallLengthInput();
    if (state.selectedRulerId !== null && els.rulerLength) {
      var ruler = findRulerById(state.selectedRulerId);
      if (ruler) els.rulerLength.textContent = rulerLengthLabel(ruler);
    }
  }

  function refreshWallLengthInput() {
    if (state.selectedWallIndex === null || !els.wallLength || !pixelsPerFoot()) return;
    var lenFt = wallLengthFeet(state.selectedWallIndex);
    if (lenFt !== null) els.wallLength.value = fmtLengthFromFeet(lenFt);
  }

  function parseMetersInput(raw) {
    var s = String(raw).trim().replace(/\s*m(eters?)?\s*$/i, '').trim();
    if (!s) throw new Error('Enter a length in meters.');
    var n = parseFloat(s.replace(/,/g, ''));
    if (isNaN(n) || n <= 0) throw new Error('Enter a valid length greater than zero.');
    return n / FEET_TO_METERS;
  }

  function parseInchesOnlyInput(raw) {
    var s = String(raw).trim();
    s = s.replace(/["″]\s*$/, '').replace(/\s*in(ch(es)?)?\s*$/i, '').trim();
    if (!s) throw new Error('Enter a length in inches.');
    var n = parseFloat(s.replace(/,/g, ''));
    if (isNaN(n) || n <= 0) throw new Error('Enter a valid length greater than zero.');
    return n / INCHES_PER_FOOT;
  }

  function parseFeetDecimalInput(raw) {
    var s = String(raw).trim().replace(/\s*(ft|feet|foot)\s*$/i, '').trim();
    if (!s) throw new Error('Enter a length in feet.');
    var n = parseFloat(s.replace(/,/g, ''));
    if (isNaN(n) || n <= 0) throw new Error('Enter a valid length greater than zero.');
    return n;
  }

  function parseLengthInput(raw) {
    switch (state.measureUnit) {
      case MEASURE_UNITS.meter:
        return parseMetersInput(raw);
      case MEASURE_UNITS.inch:
        return parseInchesOnlyInput(raw);
      case MEASURE_UNITS.feet_decimal:
        return parseFeetDecimalInput(raw);
      default:
        return parseFeetInput(raw);
    }
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

  function snapshotState() {
    return {
      groups: cloneGroups(state.groups),
      activeGroupId: state.activeGroupId,
      activePolygonId: state.activePolygonId,
      scalePixelsPerFoot: captureScaleSnapshot()
    };
  }

  function captureScaleSnapshot() {
    var ppf = pixelsPerFoot();
    return ppf > 0 ? ppf : null;
  }

  function restoreScaleSnapshot(ppf) {
    if (ppf == null || !isFinite(ppf) || ppf <= 0) return;
    setWorkingPixelsPerFoot(ppf);
    if (!state.editingRoomId) {
      state.draftScale = ppf;
    }
  }

  function pushUndoSnapshot() {
    state.undoStack.push(snapshotState());
    if (state.undoStack.length > UNDO_MAX) state.undoStack.shift();
    updateToolbar();
  }

  function discardLastUndoSnapshot() {
    if (!state.undoStack.length) return;
    state.undoStack.pop();
    updateToolbar();
  }

  function clearUndoStack() {
    state.undoStack = [];
    updateToolbar();
  }

  function restoreUndoSnapshot(snap) {
    if (snap.groups) {
      state.groups = cloneGroups(snap.groups);
      state.activeGroupId = snap.activeGroupId || null;
      state.activePolygonId = snap.activePolygonId || null;
      syncIdCountersFromGroups();
      if (!activePolygon() && allPolygons().length) {
        var first = allPolygons()[0];
        state.activeGroupId = first.group.id;
        state.activePolygonId = first.polygon.id;
      }
    } else if (snap.vertices) {
      ensureInitialCanvas();
      setActiveVertices(snap.vertices);
      setActiveClosed(!!snap.closed);
    }
    state.draggingVertex = false;
    state.draggingWall = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    state.didWallDrag = false;
    state.didVertexDrag = false;
    state.vertexDragFrom = null;
    state.vertexDragOrigins = null;
    if (snap.scalePixelsPerFoot != null) {
      restoreScaleSnapshot(snap.scalePixelsPerFoot);
    }
    state.selectedVertexIndices = [];
    clearWallSelection();
    clearVertexSelection();
    resetWallClickState();
  }

  function pixelsPerFoot() {
    if (state.workingPpf && isFinite(state.workingPpf) && state.workingPpf > 0) {
      return parseFloat(state.workingPpf);
    }
    var room = activeRoom();
    if (room && room.scale_pixels_per_foot) {
      return parseFloat(room.scale_pixels_per_foot);
    }
    if (!state.editingRoomId && state.draftScale) {
      return parseFloat(state.draftScale);
    }
    var project = activeProject();
    return project && project.scale_pixels_per_foot ? parseFloat(project.scale_pixels_per_foot) : 0;
  }

  function areaSqFt(vertices) {
    var ppf = pixelsPerFoot();
    if (!ppf || vertices.length < 3) return null;
    if (!isSimplePolygon(vertices)) return null;
    var footVerts = verticesInFeet(vertices);
    if (!footVerts) return null;
    return Math.round(shoelaceArea(footVerts) * 100) / 100;
  }

  function dist(a, b) {
    return Math.hypot(b.x - a.x, b.y - a.y);
  }

  function angleDelta(a, b) {
    var d = Math.abs(a - b) % (Math.PI * 2);
    return d > Math.PI ? Math.PI * 2 - d : d;
  }

  function snapPoint(raw, prev, step) {
    if (!prev || step === undefined) return raw;
    var dx = raw.x - prev.x;
    var dy = raw.y - prev.y;
    var len = Math.hypot(dx, dy);
    if (len < 1e-6) return raw;
    var angle = Math.atan2(dy, dx);
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
    var n = activeVertices().length;
    if (idx > 0) return activeVertices()[idx - 1];
    if (isActiveClosed() && n > 1) return activeVertices()[n - 1];
    return null;
  }

  function getVertexNext(idx) {
    var n = activeVertices().length;
    if (idx < n - 1) return activeVertices()[idx + 1];
    if (isActiveClosed() && n > 1) return activeVertices()[0];
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
    return fmtLengthFromFeet(lenFt);
  }

  function wallCount() {
    var n = activeVertices().length;
    if (n < 2) return 0;
    return isActiveClosed() ? n : n - 1;
  }

  function wallEndIndex(wallIndex) {
    var n = activeVertices().length;
    if (isActiveClosed()) return (wallIndex + 1) % n;
    return wallIndex + 1;
  }

  function wallLengthFeet(wallIndex) {
    var ppf = pixelsPerFoot();
    if (!ppf) return null;
    var a = activeVertices()[wallIndex];
    var b = activeVertices()[wallEndIndex(wallIndex)];
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
    for (var i = 0; i < activeVertices().length; i++) {
      if (dist(pos, activeVertices()[i]) <= hit) return i;
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
    return clampToCanvas(raw);
  }

  /** Move one or more selected corners; group drags keep relative spacing (uniform delta). */
  function applyVertexGroupDrag(anchorIndex, rawPos, finalize) {
    var origins = state.vertexDragOrigins;
    if (!origins || state.selectedVertexIndices.length <= 1) {
      var single = snapVertexPosition(anchorIndex, rawPos);
      activeVertices()[anchorIndex] = { x: single.x, y: single.y };
      return single;
    }

    var anchorOrig = origins[anchorIndex];
    var raw = clampToCanvas(rawPos);
    var dx = raw.x - anchorOrig.x;
    var dy = raw.y - anchorOrig.y;

    if (finalize && pixelsPerFoot() && !state.calibrating && !state.rulerMode) {
      var step = gridStepPx();
      dx = Math.round(dx / step) * step;
      dy = Math.round(dy / step) * step;
    } else if (finalize && state.shiftHeld && pixelsPerFoot() > 0) {
      var snapStep = gridStepPx();
      dx = Math.round(dx / snapStep) * snapStep;
      dy = Math.round(dy / snapStep) * snapStep;
    }

    state.selectedVertexIndices.forEach(function (i) {
      var o = origins[i];
      activeVertices()[i] = clampToCanvas({ x: o.x + dx, y: o.y + dy });
    });
    return activeVertices()[anchorIndex];
  }

  function syncSelectedVertexCircles() {
    var layer = state.layers.vertices;
    if (!layer) return;
    var circles = layer.getChildren();
    state.selectedVertexIndices.forEach(function (i) {
      if (circles[i]) circles[i].position(activeVertices()[i]);
    });
  }

  function deleteSelectedWall() {
    if (state.selectedWallIndex === null) return;
    var n = activeVertices().length;
    if (wallCount() === 0) return;

    pushUndoSnapshot();
    var wallIndex = state.selectedWallIndex;
    var removeIdx = wallEndIndex(wallIndex);

    activeVertices().splice(removeIdx, 1);
    clearWallSelection();

    if (isActiveClosed() && activeVertices().length < 3) {
      setActiveClosed(false);
    }
    if (activeVertices().length === 0) {
      setActiveClosed(false);
    }

    redrawAll();
    setFeedback('Wall removed.', false);
  }

  function isVertexSelected(vertexIndex) {
    return state.selectedVertexIndices.indexOf(vertexIndex) >= 0;
  }

  function cancelPendingWallClick() {
    if (state.wallClickTimer) {
      clearTimeout(state.wallClickTimer);
      state.wallClickTimer = null;
    }
  }

  function resetWallClickState() {
    cancelPendingWallClick();
    state.lastWallClick = null;
    state.wallPointerDown = null;
  }

  function shouldDebounceWallSplit(wallIndex) {
    var now = Date.now();
    if (state.lastWallSplit
        && state.lastWallSplit.wallIndex === wallIndex
        && now - state.lastWallSplit.time < WALL_SPLIT_DEBOUNCE_MS) {
      return true;
    }
    state.lastWallSplit = { wallIndex: wallIndex, time: now };
    return false;
  }

  function isShiftKey(e) {
    return state.shiftHeld || !!(e && e.evt && e.evt.shiftKey);
  }

  function wallVertexIndices(wallIndex) {
    return [wallIndex, wallEndIndex(wallIndex)];
  }

  function handleWallTap(wallIndex) {
    if (!canDragWalls() || wallIndex < 0 || wallIndex >= wallCount()) return;
    cancelPendingWallClick();
    var now = Date.now();
    if (state.lastWallClick
        && state.lastWallClick.wallIndex === wallIndex
        && now - state.lastWallClick.time < WALL_DOUBLE_CLICK_MS) {
      state.lastWallClick = null;
      clearWallSelection();
      splitWallAtPointer(wallIndex);
      return;
    }
    if (state.shiftHeld) {
      state.lastWallClick = null;
      selectVertices(wallVertexIndices(wallIndex), { mode: 'toggle' });
      return;
    }
    selectWall(wallIndex);
    state.lastWallClick = { wallIndex: wallIndex, time: now };
  }

  function clearVertexSelection() {
    if (state.selectedVertexIndices.length === 0 && els.vertexEdit && els.vertexEdit.hasAttribute('hidden')) return;
    state.selectedVertexIndices = [];
    state.vertexDragOrigins = null;
    show(els.vertexEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-vertex-edit');
    if (state.layers.vertices) {
      redrawVertices();
      updateToolbar();
    }
  }

  function selectVertices(vertexIndices, options) {
    options = options || {};
    var indices = (vertexIndices || []).filter(function (idx) {
      return idx >= 0 && idx < activeVertices().length;
    });
    if (options.mode === 'toggle') {
      indices.forEach(function (idx) {
        var at = state.selectedVertexIndices.indexOf(idx);
        if (at >= 0) state.selectedVertexIndices.splice(at, 1);
        else state.selectedVertexIndices.push(idx);
      });
    } else {
      state.selectedVertexIndices = indices.slice();
    }
    if (state.selectedVertexIndices.length > 0) {
      clearRulerSelection();
      clearWallSelection();
      show(els.vertexEdit, true);
      if (els.canvasWrap) els.canvasWrap.classList.add('is-vertex-edit');
    } else {
      show(els.vertexEdit, false);
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-vertex-edit');
    }
    redrawVertices();
    updateToolbar();
  }

  function toggleVertexSelection(vertexIndex) {
    selectVertices([vertexIndex], { mode: 'toggle' });
  }

  function deleteSelectedVertex() {
    if (state.selectedVertexIndices.length === 0) return;
    if (activeVertices().length - state.selectedVertexIndices.length < 2) {
      setFeedback('Need at least two corners to keep a wall segment.', true);
      return;
    }

    var indices = state.selectedVertexIndices.slice().sort(function (a, b) { return b - a; });
    var copy = activeVertices().map(function (v) { return { x: v.x, y: v.y }; });
    indices.forEach(function (idx) {
      copy.splice(idx, 1);
    });
    var closed = isActiveClosed();
    if (closed && copy.length < 3) closed = false;
    if (!isValidShape(copy, closed)) {
      setFeedback('Removing those corners would make the shape cross itself.', true);
      return;
    }

    pushUndoSnapshot();
    indices.forEach(function (idx) {
      activeVertices().splice(idx, 1);
    });
    if (isActiveClosed() && activeVertices().length < 3) {
      setActiveClosed(false);
    }
    if (activeVertices().length === 0) {
      setActiveClosed(false);
    }
    clearVertexSelection();
    redrawAll();
    setFeedback(indices.length > 1 ? 'Corners removed.' : 'Corner removed.', false);
  }

  function findWallAtPoint(pos) {
    if (!pos || wallCount() === 0) return -1;
    var best = -1;
    var bestDist = hitTolerance(WALL_HIT) + 1;
    for (var i = 0; i < wallCount(); i++) {
      var a = activeVertices()[i];
      var b = activeVertices()[wallEndIndex(i)];
      var d = pointToSegmentDistance(pos, a, b);
      if (d <= hitTolerance(WALL_HIT) && d < bestDist) {
        bestDist = d;
        best = i;
      }
    }
    return best;
  }

  function wallDragCursorFor() {
    return 'move';
  }

  function canDragWalls() {
    return wallCount() > 0 && isActiveClosed() && !state.spaceHeld && !state.rulerMode && !state.calibrating;
  }

  function refreshSelectedWallLengthInput() {
    if (state.selectedWallIndex !== null && els.wallLength && pixelsPerFoot()) {
      var lenFt = wallLengthFeet(state.selectedWallIndex);
      if (lenFt !== null) els.wallLength.value = fmtFeetInches(lenFt);
    }
  }

  function onWallPointerDown(wallIndex) {
    if (!canDragWalls() || wallIndex < 0 || wallIndex >= wallCount()) return;
    var pos = getPointerPos();
    if (!pos) return;
    cancelPendingWallClick();
    clearRulerSelection();
    var a = activeVertices()[wallIndex];
    var b = activeVertices()[wallEndIndex(wallIndex)];
    state.wallPointerDown = {
      wallIndex: wallIndex,
      x: pos.x,
      y: pos.y,
      origA: { x: a.x, y: a.y },
      origB: { x: b.x, y: b.y }
    };
    state.didWallDrag = false;
  }

  function tryStartWallDragFromPending(pos) {
    if (!state.wallPointerDown || state.draggingWall || !pos) return;
    var pending = state.wallPointerDown;
    if (Math.hypot(pos.x - pending.x, pos.y - pending.y) < uiSize(4)) return;
    cancelPendingWallClick();
    state.draggingWall = true;
    state.didWallDrag = false;
    state.wallDragIndex = pending.wallIndex;
    state.wallDragPointerStart = { x: pending.x, y: pending.y };
    state.wallDragOrigA = pending.origA;
    state.wallDragOrigB = pending.origB;
    pushUndoSnapshot();
    selectWall(pending.wallIndex);
    updateCanvasCursor();
    moveWallDrag(pos);
  }

  function finishWallPointerUp(pos) {
    if (state.draggingWall) {
      finishWallDrag(pos);
      state.wallPointerDown = null;
      return;
    }
    if (!state.wallPointerDown) return;
    var wallIndex = state.wallPointerDown.wallIndex;
    state.wallPointerDown = null;
    if (state.didWallDrag) return;
    handleWallTap(wallIndex);
  }

  function moveWallDrag(pos) {
    if (!state.draggingWall || state.wallDragIndex === null || !pos || !state.wallDragPointerStart) return;
    if (!state.wallDragOrigA || !state.wallDragOrigB) return;
    var ox = pos.x - state.wallDragPointerStart.x;
    var oy = pos.y - state.wallDragPointerStart.y;
    if (state.shiftHeld && pixelsPerFoot() > 0) {
      var step = gridStepPx();
      ox = Math.round(ox / step) * step;
      oy = Math.round(oy / step) * step;
    }
    if (Math.hypot(ox, oy) > 0.5 / uiScale()) {
      state.didWallDrag = true;
    }
    var wallIndex = state.wallDragIndex;
    var endIdx = wallEndIndex(wallIndex);
    activeVertices()[wallIndex] = clampToCanvas({
      x: state.wallDragOrigA.x + ox,
      y: state.wallDragOrigA.y + oy
    });
    activeVertices()[endIdx] = clampToCanvas({
      x: state.wallDragOrigB.x + ox,
      y: state.wallDragOrigB.y + oy
    });
    redrawPolygonLine();
    redrawLabels();
    redrawWalls();
    redrawVertices();
    updateLiveArea();
    refreshSelectedWallLengthInput();
  }

  function finishWallDrag(pos) {
    if (!state.draggingWall) return;
    if (pos) moveWallDrag(pos);
    var wallIndex = state.wallDragIndex;
    var endIdx = wallEndIndex(wallIndex);
    var origA = state.wallDragOrigA;
    var origB = state.wallDragOrigB;
    var hadWallDrag = state.didWallDrag;
    if (origA && origB && !isValidShape(activeVertices(), isActiveClosed())) {
      activeVertices()[wallIndex] = origA;
      activeVertices()[endIdx] = origB;
      if (hadWallDrag) discardLastUndoSnapshot();
      state.didWallDrag = false;
      setFeedback('That wall position would make the shape cross itself.', true);
    }
    state.draggingWall = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    updateCanvasCursor();
    redrawAll();
  }

  function bindWallDrag(node, wallIndex) {
    node.on('mousedown touchstart', function (e) {
      if (!canDragWalls()) return;
      if (e.evt && e.evt.button !== undefined && e.evt.button !== 0) return;
      e.cancelBubble = true;
      onWallPointerDown(wallIndex);
    });
    node.on('mouseenter', function () {
      if (!canDragWalls() || !state.stage) return;
      if (state.draggingWall || state.draggingVertex || state.spaceHeld) return;
      state.stage.container().style.cursor = 'move';
    });
    node.on('mouseleave', function () {
      if (state.stage && !state.draggingWall) updateCanvasCursor();
    });
  }

  function wallInsertIndex(wallIndex) {
    var n = activeVertices().length;
    if (!isActiveClosed()) return wallIndex + 1;
    return wallIndex === n - 1 ? n : wallIndex + 1;
  }

  function projectPointOnSegment(p, a, b) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var lenSq = dx * dx + dy * dy;
    if (lenSq < 1e-9) return null;
    var len = Math.sqrt(lenSq);
    var minT = hitTolerance(VERTEX_HIT) / len;
    if (minT >= 0.5) return null;
    var maxT = 1 - minT;
    var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq;
    t = Math.max(minT, Math.min(maxT, t));
    return { x: a.x + t * dx, y: a.y + t * dy };
  }

  function insertVertexOnWall(wallIndex, rawPos) {
    if (wallIndex < 0 || wallIndex >= wallCount()) return -1;
    var a = activeVertices()[wallIndex];
    var b = activeVertices()[wallEndIndex(wallIndex)];
    var projected = projectPointOnSegment(rawPos, a, b);
    if (!projected) {
      setFeedback('Wall is too short to add a corner here.', true);
      return -1;
    }
    var point = clampToCanvas(projected);
    var insertIdx = wallInsertIndex(wallIndex);
    activeVertices().splice(insertIdx, 0, point);
    if (!isValidShape(activeVertices(), isActiveClosed())) {
      activeVertices().splice(insertIdx, 1);
      setFeedback('That corner would make the shape cross itself.', true);
      return -1;
    }
    return insertIdx;
  }

  function startVertexDragAt(vertexIndex) {
    var layer = state.layers.vertices;
    if (!layer) return;
    var circles = layer.getChildren();
    if (vertexIndex < 0 || vertexIndex >= circles.length) return;
    var circle = circles[vertexIndex];
    if (circle && typeof circle.startDrag === 'function') {
      circle.startDrag();
    }
  }

  function splitWallAtPointer(wallIndex) {
    if (!canDragWalls()) return;
    if (shouldDebounceWallSplit(wallIndex)) return;
    if (state.draggingWall) {
      finishWallDrag(getPointerPos());
      state.didWallDrag = false;
    }
    var pos = getPointerPos();
    if (!pos) return;
    pushUndoSnapshot();
    var newIdx = insertVertexOnWall(wallIndex, pos);
    if (newIdx < 0) {
      discardLastUndoSnapshot();
      return;
    }
    clearWallSelection();
    redrawAll();
    setFeedback('Corner added — drag to shape the wall.', false);
    requestAnimationFrame(function () {
      startVertexDragAt(newIdx);
    });
  }

  function bindWallSplit(node, wallIndex) {
    node.on('dblclick dbltap', function (e) {
      if (!canDragWalls()) return;
      if (e.evt) {
        e.evt.preventDefault();
      }
      e.cancelBubble = true;
      resetWallClickState();
      if (state.draggingWall) {
        finishWallDrag(getPointerPos());
        state.didWallDrag = false;
      }
      clearWallSelection();
      splitWallAtPointer(wallIndex);
    });
  }

  function clearWallSelection() {
    if (state.selectedWallIndex === null && els.wallEdit && els.wallEdit.hasAttribute('hidden')) return;
    state.selectedWallIndex = null;
    show(els.wallEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-wall-edit');
    if (els.wallLength) els.wallLength.value = '';
    if (els.wallScaleProject) els.wallScaleProject.checked = false;
    if (els.wallScaleProject) els.wallScaleProject.checked = false;
    if (state.layers.draw) {
      redrawLabels();
      redrawWalls();
      updateToolbar();
    }
  }

  function clearRulerSelection() {
    if (state.selectedRulerId === null && els.rulerEdit && els.rulerEdit.hasAttribute('hidden')) return;
    state.selectedRulerId = null;
    show(els.rulerEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-ruler-edit');
    if (els.rulerLength) els.rulerLength.textContent = '—';
    redrawRulers();
    updateToolbar();
  }

  function selectRuler(rulerId) {
    var ruler = findRulerById(rulerId);
    if (!ruler) return;
    clearWallSelection();
    clearVertexSelection();
    state.selectedRulerId = rulerId;
    show(els.rulerEdit, true);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-ruler-edit');
    if (els.rulerLength) els.rulerLength.textContent = rulerLengthLabel(ruler);
    redrawRulers();
    updateToolbar();
  }

  function deleteSelectedRuler() {
    if (state.selectedRulerId === null) return;
    var rulers = projectRulers();
    var idx = rulers.findIndex(function (r) { return r.id === state.selectedRulerId; });
    if (idx < 0) {
      clearRulerSelection();
      return;
    }
    rulers.splice(idx, 1);
    clearRulerSelection();
    redrawRulers();
    setFeedback('Ruler deleted.', false);
  }

  function stopRulerMode() {
    state.rulerMode = false;
    state.rulerDraft = [];
    state.rulerPreview = null;
    if (els.rulerToggle) els.rulerToggle.classList.remove('is-active');
    redrawRulers();
    redrawWalls();
    redrawLabels();
    redrawVertices();
  }

  function toggleRulerMode() {
    if (state.rulerMode) {
      stopRulerMode();
      setFeedback('Ruler mode off. Click a ruler to select it; drag its corners to adjust.', false);
      return;
    }

    if (state.calibrating) stopCalibrationMode();
    stopBgMoveMode();
    clearRulerSelection();
    clearWallSelection();
    state.rulerMode = true;
    state.rulerDraft = [];
    state.rulerPreview = null;
    if (els.rulerToggle) els.rulerToggle.classList.add('is-active');
    redrawAll();
    var ppf = pixelsPerFoot();
    if (ppf > 0) {
      setFeedback('Click two points to place a ruler. Create as many as you need. Turn Ruler off to select and delete.', false);
    } else {
      setFeedback('Scale is not set — rulers will show lengths after you calibrate.', false);
    }
  }

  function placeRulerPoint(pos, shiftSnap) {
    if (state.rulerDraft.length >= 2) {
      state.rulerDraft = [];
    }

    var point = pos;
    if (state.rulerDraft.length === 1) {
      var prev = state.rulerDraft[0];
      if (shiftSnap) point = snapPoint(pos, prev, SHIFT_SNAP_STEP);
      else point = pos;
    }
    point = clampToCanvas(point);
    state.rulerDraft.push(point);
    state.rulerPreview = null;

    if (state.rulerDraft.length < 2) {
      redrawRulers();
      return;
    }

    var rulers = projectRulers();
    var ruler = {
      id: state.nextRulerId++,
      p1: { x: state.rulerDraft[0].x, y: state.rulerDraft[0].y },
      p2: { x: state.rulerDraft[1].x, y: state.rulerDraft[1].y }
    };
    rulers.push(ruler);
    state.rulerDraft = [];
    redrawRulers();
    setFeedback('Ruler placed: ' + rulerLengthLabel(ruler) + '. Click two more points for another.', false);
  }

  function cancelRulerDraft() {
    if (!state.rulerMode || state.rulerDraft.length === 0) return false;
    state.rulerDraft = [];
    state.rulerPreview = null;
    redrawRulers();
    return true;
  }

  function selectWall(wallIndex) {
    if (wallIndex < 0 || wallIndex >= wallCount()) return;
    clearRulerSelection();
    clearVertexSelection();
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
    var newFeet;
    try {
      newFeet = parseLengthInput(els.wallLength.value);
    } catch (e) {
      setFeedback(e.message, true);
      return;
    }

    if (els.wallScaleProject && els.wallScaleProject.checked) {
      applyWallLengthScaleProject(wallIndex, newFeet);
      return;
    }

    var ppf = pixelsPerFoot();
    var newPx = newFeet * ppf;
    var proposed = bestWallResizeVertices(activeVertices(), isActiveClosed(), wallIndex, newPx);
    if (!proposed) {
      setFeedback('That wall has no length to adjust.', true);
      return;
    }

    pushUndoSnapshot();
    var previous = copyVertices(activeVertices());
    var i;
    for (i = 0; i < proposed.length; i++) {
      activeVertices()[i] = clampToCanvas(proposed[i]);
    }
    if (!isValidShape(activeVertices(), isActiveClosed())) {
      for (i = 0; i < previous.length; i++) {
        activeVertices()[i] = previous[i];
      }
      discardLastUndoSnapshot();
      setFeedback('That length would make the shape cross itself.', true);
      return;
    }

    redrawAll();
    els.wallLength.value = fmtLengthFromFeet(newFeet);
    setFeedback('Wall length updated.', false);
  }

  function copyVertices(verts) {
    return verts.map(function (v) {
      return { x: v.x, y: v.y };
    });
  }

  function interiorCornerAngle(prev, vertex, next) {
    var ax = prev.x - vertex.x;
    var ay = prev.y - vertex.y;
    var bx = next.x - vertex.x;
    var by = next.y - vertex.y;
    var la = Math.hypot(ax, ay);
    var lb = Math.hypot(bx, by);
    if (la < 1e-9 || lb < 1e-9) return Math.PI;
    var cos = (ax * bx + ay * by) / (la * lb);
    return Math.acos(Math.max(-1, Math.min(1, cos)));
  }

  function polygonRightAngleError(verts, closed) {
    if (!closed || verts.length < 3) return 0;
    var n = verts.length;
    var err = 0;
    for (var i = 0; i < n; i++) {
      var prev = verts[(i - 1 + n) % n];
      var next = verts[(i + 1) % n];
      err += Math.abs(interiorCornerAngle(prev, verts[i], next) - Math.PI / 2);
    }
    return err;
  }

  function otherWallsLengthError(original, candidate, closed, skipWallIndex) {
    var n = original.length;
    var wallN = closed ? n : Math.max(0, n - 1);
    var err = 0;
    for (var i = 0; i < wallN; i++) {
      if (i === skipWallIndex) continue;
      var b0 = original[closed ? (i + 1) % n : i + 1];
      var b1 = candidate[closed ? (i + 1) % n : i + 1];
      err += Math.abs(dist(candidate[i], b1) - dist(original[i], b0));
    }
    return err;
  }

  function snapUnitToRightAngle(ux, uy) {
    var angle = Math.atan2(uy, ux);
    var snapped = Math.round(angle / (Math.PI / 2)) * (Math.PI / 2);
    if (angleDelta(angle, snapped) <= SHIFT_SNAP_THRESHOLD_DEG * Math.PI / 180) {
      return { x: Math.cos(snapped), y: Math.sin(snapped) };
    }
    return { x: ux, y: uy };
  }

  function translateVertexCopy(verts, indices, dx, dy) {
    var out = copyVertices(verts);
    var seen = {};
    for (var i = 0; i < indices.length; i++) {
      var idx = indices[i];
      if (idx == null || idx < 0 || idx >= out.length || seen[idx]) continue;
      seen[idx] = true;
      out[idx] = { x: out[idx].x + dx, y: out[idx].y + dy };
    }
    return out;
  }

  function scoreWallResizeCandidate(original, candidate, closed, wallIndex) {
    var originMoved = dist(original[0], candidate[0]);
    var angleErr = polygonRightAngleError(candidate, closed);
    var lengthErr = otherWallsLengthError(original, candidate, closed, wallIndex);
    var travel = 0;
    for (var i = 0; i < original.length; i++) {
      travel += dist(original[i], candidate[i]);
    }
    return angleErr * 40 + lengthErr * 0.02 + (originMoved > 0.5 ? 12 : 0) + travel * 0.001;
  }

  /**
   * Resize wall wallIndex to newPx. The old code always kept vertices[i]
   * fixed and moved vertices[i+1], so a clockwise room grew the top to the
   * right and the bottom to the left, shearing 90° corners. Pick the
   * translation (one end, or that end plus the adjacent side) that keeps
   * corners closest to square and prefers not to move the origin vertex.
   */
  function bestWallResizeVertices(verts, closed, wallIndex, newPx) {
    var n = verts.length;
    if (wallIndex < 0 || n < 2) return null;
    var endIdx = closed ? (wallIndex + 1) % n : wallIndex + 1;
    if (endIdx < 0 || endIdx >= n) return null;
    var start = verts[wallIndex];
    var end = verts[endIdx];
    var currentPx = dist(start, end);
    if (currentPx < 1e-6) return null;

    var ux = (end.x - start.x) / currentPx;
    var uy = (end.y - start.y) / currentPx;
    var unit = snapUnitToRightAngle(ux, uy);
    ux = unit.x;
    uy = unit.y;

    var bPrime = { x: start.x + ux * newPx, y: start.y + uy * newPx };
    var aPrime = { x: end.x - ux * newPx, y: end.y - uy * newPx };
    var dBx = bPrime.x - end.x;
    var dBy = bPrime.y - end.y;
    var dAx = aPrime.x - start.x;
    var dAy = aPrime.y - start.y;

    var nextIdx = closed ? (endIdx + 1) % n : (endIdx + 1 < n ? endIdx + 1 : null);
    var prevIdx = closed ? ((wallIndex - 1 + n) % n) : (wallIndex > 0 ? wallIndex - 1 : null);
    if (nextIdx === wallIndex) nextIdx = null;
    if (prevIdx === endIdx) prevIdx = null;

    var options = [
      translateVertexCopy(verts, [endIdx], dBx, dBy),
      translateVertexCopy(verts, [wallIndex], dAx, dAy)
    ];
    if (nextIdx != null) {
      options.push(translateVertexCopy(verts, [endIdx, nextIdx], dBx, dBy));
    }
    if (prevIdx != null) {
      options.push(translateVertexCopy(verts, [wallIndex, prevIdx], dAx, dAy));
    }

    var best = null;
    var bestScore = Infinity;
    for (var i = 0; i < options.length; i++) {
      var cand = options[i];
      if (!isValidShape(cand, closed)) continue;
      var score = scoreWallResizeCandidate(verts, cand, closed, wallIndex);
      if (score < bestScore) {
        bestScore = score;
        best = cand;
      }
    }
    return best || options[0] || null;
  }

  function applyWallLengthScaleProject(wallIndex, newFeet) {
    var currentFeet = wallLengthFeet(wallIndex);
    if (currentFeet === null || currentFeet < 1e-6) {
      setFeedback('Wall has no length to scale from.', true);
      return;
    }
    if (newFeet < 1e-6) {
      setFeedback('Enter a positive wall length.', true);
      return;
    }
    if (Math.abs(newFeet - currentFeet) < 1 / 192) {
      setFeedback('Length unchanged.', false);
      return;
    }

    var factor = currentFeet / newFeet;
    pushUndoSnapshot();
    scaleActivePixelsPerFoot(factor);
    updateScaleUI();
    if (els.wallLength) {
      var updatedFeet = wallLengthFeet(wallIndex);
      els.wallLength.value = updatedFeet !== null ? fmtLengthFromFeet(updatedFeet) : fmtLengthFromFeet(newFeet);
    }
    setFeedback('Project scale updated — all measurements rescaled from this wall.', false);
  }

  function gridStepPx() {
    var ppf = pixelsPerFoot();
    return ppf > 0 ? ppf : GRID_PX;
  }

  /** Snap a length in feet to the nearest 1/16" (matches construction precision). */
  function roundToSixteenthFoot(lenFt) {
    return Math.round(lenFt * 16) / 16;
  }

  function verticesInFeet(vertices) {
    var ppf = pixelsPerFoot();
    if (!ppf) return null;
    return vertices.map(function (v) {
      return {
        x: roundToSixteenthFoot(v.x / ppf),
        y: roundToSixteenthFoot(v.y / ppf)
      };
    });
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
    resetCanvasGroups();
    ensureInitialCanvas();
    state.editingRoomId = null;
    state.selectedWallIndex = null;
    state.selectedVertexIndices = [];
    state.vertexDragOrigins = null;
    state.draggingVertex = false;
    state.draggingWall = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    state.wallPointerDown = null;
    cancelPendingWallClick();
    state.didWallDrag = false;
    state.didVertexDrag = false;
    state.vertexDragFrom = null;
    state.draftScale = null;
    setWorkingPixelsPerFoot(null);
    state.draftRulers = [];
    revokeDraftBackground();
    state.bgLayout = null;
    stopBgMoveMode();
    resetCalibrationUI();
    clearWallSelection();
    clearVertexSelection();
    clearRulerSelection();
    clearUndoStack();
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
    captureSavedSnapshot();
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
    } else if (state.draggingWall && state.wallDragIndex !== null) {
      el.style.cursor = wallDragCursorFor();
    } else if (state.draggingRuler) {
      el.style.cursor = 'grabbing';
    } else if (state.draggingBg) {
      el.style.cursor = 'grabbing';
    } else if (state.bgMoveMode) {
      el.style.cursor = 'grab';
    } else {
      el.style.cursor = '';
    }
  }

  function naturalWorldScale() {
    return 1;
  }

  function zoomPercentFromScale(scale) {
    return Math.round((scale / naturalWorldScale()) * 100);
  }

  function scaleFromZoomPercent(percent) {
    return (percent / 100) * naturalWorldScale();
  }

  function minZoomPercent() {
    var vw = viewportW();
    var vh = viewportH();
    var pad = 20;
    var cw = canvasW();
    var ch = canvasH();
    if (!vw || !vh || !cw || !ch) return MIN_ZOOM_PERCENT;
    var fitWorldScale = Math.min((vw - pad) / cw, (vh - pad) / ch);
    return Math.max(MIN_ZOOM_PERCENT, zoomPercentFromScale(fitWorldScale));
  }

  function clampZoomPercent(percent) {
    return Math.max(minZoomPercent(), Math.min(MAX_ZOOM_PERCENT, Math.round(percent)));
  }

  function clampWorldScale(scale) {
    return scaleFromZoomPercent(clampZoomPercent(zoomPercentFromScale(scale)));
  }

  function setWorldScale(scale, screenX, screenY) {
    var nextScale = clampWorldScale(scale);
    if (screenX != null && screenY != null && state.viewport.scale > 0) {
      var oldScale = state.viewport.scale;
      var worldX = (screenX - state.viewport.x) / oldScale;
      var worldY = (screenY - state.viewport.y) / oldScale;
      state.viewport.scale = nextScale;
      state.viewport.x = screenX - worldX * nextScale;
      state.viewport.y = screenY - worldY * nextScale;
    } else {
      state.viewport.scale = nextScale;
    }
    state.viewport.zoomPercent = zoomPercentFromScale(state.viewport.scale);
    applyViewport();
  }

  function setZoomPercent(percent, screenX, screenY) {
    setWorldScale(scaleFromZoomPercent(clampZoomPercent(percent)), screenX, screenY);
  }

  function zoomIn() {
    setZoomPercent(state.viewport.zoomPercent * ZOOM_STEP, viewportW() / 2, viewportH() / 2);
  }

  function zoomOut() {
    setZoomPercent(state.viewport.zoomPercent / ZOOM_STEP, viewportW() / 2, viewportH() / 2);
  }

  function viewportContentBounds() {
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    var has = false;

    function grow(x, y) {
      if (!isFinite(x) || !isFinite(y)) return;
      has = true;
      minX = Math.min(minX, x);
      minY = Math.min(minY, y);
      maxX = Math.max(maxX, x);
      maxY = Math.max(maxY, y);
    }

    allPolygons().forEach(function (entry) {
      entry.polygon.vertices.forEach(function (v) {
        grow(v.x, v.y);
      });
    });

    state.calibPoints.forEach(function (p) {
      grow(p.x, p.y);
    });

    projectRulers().forEach(function (ruler) {
      if (ruler.p1) grow(ruler.p1.x, ruler.p1.y);
      if (ruler.p2) grow(ruler.p2.x, ruler.p2.y);
    });

    if (state.bgImage) {
      var bx = state.bgImage.x();
      var by = state.bgImage.y();
      grow(bx, by);
      grow(bx + state.bgImage.width(), by + state.bgImage.height());
    }

    if (!has) {
      return { minX: 0, minY: 0, maxX: contentW(), maxY: contentH(), hasContent: false };
    }

    return { minX: minX, minY: minY, maxX: maxX, maxY: maxY, hasContent: true };
  }

  function clampViewportPan() {
    var s = state.viewport.scale;
    var vw = viewportW();
    var vh = viewportH();
    var cw = canvasW() * s;
    var ch = canvasH() * s;
    var minX = vw - cw;
    var minY = vh - ch;
    if (cw <= vw) {
      state.viewport.x = (vw - cw) / 2;
    } else {
      state.viewport.x = Math.max(minX, Math.min(0, state.viewport.x));
    }
    if (ch <= vh) {
      state.viewport.y = (vh - ch) / 2;
    } else {
      state.viewport.y = Math.max(minY, Math.min(0, state.viewport.y));
    }
  }

  function applyViewport() {
    if (!state.worldGroup) return;
    clampViewportPan();
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

  function panToWorldPoint(x, y) {
    var s = state.viewport.scale;
    state.viewport.x = viewportW() / 2 - x * s;
    state.viewport.y = viewportH() / 2 - y * s;
    applyViewport();
  }

  function panToContentCenter() {
    var bounds = viewportContentBounds();
    if (!bounds.hasContent) return;
    panToWorldPoint((bounds.minX + bounds.maxX) / 2, (bounds.minY + bounds.maxY) / 2);
  }

  function resetViewport() {
    state.viewport.zoomPercent = 100;
    state.viewport.scale = naturalWorldScale();
    state.viewport.x = 0;
    state.viewport.y = 0;
    state.lastUiScale = 0;
    applyViewport();
    updateCanvasCursor();
  }

  function refreshViewportAfterContentChange() {
    if (viewportContentBounds().hasContent) {
      fitViewport();
    } else {
      resetViewport();
    }
  }

  function fitViewport() {
    if (!state.stage) return;
    var pad = ZOOM_FIT_PAD;
    var vw = viewportW();
    var vh = viewportH();
    var bounds = viewportContentBounds();
    var bw = Math.max(bounds.maxX - bounds.minX, 1);
    var bh = Math.max(bounds.maxY - bounds.minY, 1);
    var scale = Math.min((vw - pad * 2) / bw, (vh - pad * 2) / bh);
    scale = clampWorldScale(scale);

    var cx = (bounds.minX + bounds.maxX) / 2;
    var cy = (bounds.minY + bounds.maxY) / 2;
    state.viewport.scale = scale;
    state.viewport.zoomPercent = zoomPercentFromScale(scale);
    state.viewport.x = vw / 2 - cx * scale;
    state.viewport.y = vh / 2 - cy * scale;
    state.lastUiScale = 0;
    applyViewport();
    updateCanvasCursor();
  }

  function updateZoomUI() {
    if (els.zoomLabel) {
      els.zoomLabel.textContent = state.viewport.zoomPercent + '%';
    }
  }

  function zoomAt(screenX, screenY, newScale) {
    setWorldScale(newScale, screenX, screenY);
  }

  function zoomBy(factor) {
    if (!state.stage) return;
    setZoomPercent(state.viewport.zoomPercent * factor, viewportW() / 2, viewportH() / 2);
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
      var factor = dir > 0 ? ZOOM_STEP : 1 / ZOOM_STEP;
      setZoomPercent(state.viewport.zoomPercent * factor, x, y);
    }, { passive: false });

    document.addEventListener('keydown', function (e) {
      if (e.code === 'ShiftLeft' || e.code === 'ShiftRight') {
        state.shiftHeld = true;
        updateDrawPreviewFromPointer();
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
        updateDrawPreviewFromPointer();
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
      if (state.wallPointerDown || state.draggingWall) {
        finishWallPointerUp(getPointerPos());
      }
      if (!state.panning) return;
      state.panning = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
      updateCanvasCursor();
    });
  }

  function resizeStage() {
    if (!state.stage || !els.canvasWrap) return;
    var vp = getViewportDimensions();
    if (vp.w === state.viewportW && vp.h === state.viewportH) return;

    var oldContentW = state.viewportW;
    var oldContentH = state.viewportH;
    var oldWorldW = state.canvasW;
    var oldWorldH = state.canvasH;

    syncDrawableDimensions();

    if (oldContentW > 0 && oldContentH > 0) {
      var factor = canvasScaleFactor(oldContentW, oldContentH, contentW(), contentH());
      if (state.groups.length && allPolygons().some(function (e) { return e.polygon.vertices.length; })) {
        scaleAllCanvasPoints(oldContentW, oldContentH, contentW(), contentH());
        clearUndoStack();
      }
      if (state.calibPoints.length) {
        state.calibPoints = scalePoints(state.calibPoints, oldContentW, oldContentH, contentW(), contentH());
      }
      scaleProjectRulers(oldContentW, oldContentH, contentW(), contentH());
      scaleBgLayoutState(oldContentW, oldContentH, contentW(), contentH());
      if (Math.abs(factor - 1) > 0.0005) {
        scaleActivePixelsPerFoot(factor);
      }
    }

    state.stage.width(state.viewportW);
    state.stage.height(state.viewportH);
    if (state.bgImage) {
      if (oldContentW > 0 && oldContentH > 0) {
        applyBackgroundLayoutToKonva();
      } else {
        layoutBackgroundImage(true);
      }
    }
    drawGrid();
    redrawAll();
    // Keep pan within the new world bounds after a resize.
    if (oldWorldW !== state.canvasW || oldWorldH !== state.canvasH) {
      applyViewport();
    }
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

    syncDrawableDimensions();

    state.stage = new Konva.Stage({
      container: 'ra-konva-container',
      width: state.viewportW,
      height: state.viewportH
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
    state.layers.preview = new Konva.Group({ listening: false });
    state.layers.calib = new Konva.Group();
    state.layers.rulers = new Konva.Group();

    state.worldGroup.add(state.layers.bg);
    state.worldGroup.add(state.layers.grid);
    state.worldGroup.add(state.layers.draw);
    state.worldGroup.add(state.layers.walls);
    state.worldGroup.add(state.layers.vertices);
    state.worldGroup.add(state.layers.labels);
    state.worldGroup.add(state.layers.preview);
    state.worldGroup.add(state.layers.calib);
    state.worldGroup.add(state.layers.rulers);

    resetViewport();
    drawGrid();
    redrawAll();

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
      var pos = getPointerPos();
      if (state.wallPointerDown && !state.draggingWall) {
        tryStartWallDragFromPending(pos);
      }
      if (state.draggingWall) {
        moveWallDrag(pos);
        return;
      }
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

    state.stage.on('mousemove touchmove', function () {
      if (state.panning || !state.rulerMode || state.rulerDraft.length !== 1) {
        if (!state.rulerMode && state.rulerPreview) {
          state.rulerPreview = null;
          redrawRulers();
        }
        if (!state.panning && !state.draggingWall && !state.draggingVertex) {
          updateDrawPreviewFromPointer();
        }
        return;
      }
      var pos = getPointerPos();
      if (!pos) return;
      var preview = pos;
      if (state.shiftHeld) preview = snapPoint(pos, state.rulerDraft[0], SHIFT_SNAP_STEP);
      else preview = pos;
      state.rulerPreview = clampToCanvas(preview);
      redrawRulers();
    });

    state.stage.on('mouseup touchend mouseleave', function () {
      finishWallPointerUp(getPointerPos());
      if (state.draggingWall) return;
      if (!state.panning) return;
      state.panning = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
      updateCanvasCursor();
    });

    state.stage.on('mouseleave', function () {
      clearDrawPreview();
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
    var hasBg = !!state.activeBackgroundUrl || !!state.bgImage;
    show(els.bgRemove, hasBg);
    show(els.bgDeleteCanvas, hasBg);
    show(els.bgShowWrap, hasBg);
    show(els.bgMoveToggle, hasBg);
    if (els.bgShowImage) {
      els.bgShowImage.checked = state.bgImageVisible;
    }
    if (!hasBg) stopBgMoveMode();
    applyBackgroundImageVisibility();
  }

  function applyBackgroundImageVisibility() {
    if (!state.layers.bg) return;
    var hasBg = !!state.activeBackgroundUrl || !!state.bgImage;
    state.layers.bg.visible(hasBg && state.bgImageVisible);
    flushKonva();
  }

  function setBackgroundImageVisible(visible) {
    state.bgImageVisible = !!visible;
    if (els.bgShowImage) els.bgShowImage.checked = state.bgImageVisible;
    applyBackgroundImageVisibility();
  }

  function layoutBackgroundImage(forceCenter) {
    if (!state.bgImage || !state.bgNaturalW || !state.bgNaturalH) return;
    if (!forceCenter && state.bgLayout) {
      applyBackgroundLayoutToKonva();
      return;
    }
    var cw = contentW();
    var ch = contentH();
    var scale = Math.min(cw / state.bgNaturalW, ch / state.bgNaturalH);
    var w = state.bgNaturalW * scale;
    var h = state.bgNaturalH * scale;
    state.bgLayout = {
      x: (cw - w) / 2,
      y: (ch - h) / 2,
      width: w,
      height: h
    };
    applyBackgroundLayoutToKonva();
  }

  function scaleBackgroundLayout(fromW, fromH, toW, toH) {
    scaleBgLayoutState(fromW, fromH, toW, toH);
    applyBackgroundLayoutToKonva();
  }

  function bindBackgroundImageEvents(kImg) {
    if (kImg._raBgBound) return;
    kImg._raBgBound = true;
    kImg.on('dragstart', function () {
      if (!state.bgMoveMode) return;
      state.draggingBg = true;
      if (els.canvasWrap) els.canvasWrap.classList.add('is-bg-dragging');
      updateCanvasCursor();
    });
    kImg.on('dragmove', function () {
      if (!state.bgMoveMode) return;
      var pos = clampBgPosition(kImg.x(), kImg.y(), kImg.width(), kImg.height());
      kImg.position(pos);
    });
    kImg.on('dragend', function () {
      if (!state.draggingBg) return;
      state.draggingBg = false;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-bg-dragging');
      syncBackgroundLayoutFromKonva();
      updateCanvasCursor();
    });
  }

  function clampBgPosition(x, y, w, h) {
    var margin = 40;
    return {
      x: Math.max(-w + margin, Math.min(canvasW() - margin, x)),
      y: Math.max(-h + margin, Math.min(canvasH() - margin, y))
    };
  }

  function updateBackgroundInteraction() {
    if (!state.bgImage) return;
    var canMove = state.bgMoveMode && !state.spaceHeld && !state.panning;
    state.bgImage.draggable(canMove);
    state.bgImage.listening(canMove);
    flushKonva();
  }

  function stopBgMoveMode() {
    state.bgMoveMode = false;
    state.draggingBg = false;
    if (els.bgMoveToggle) els.bgMoveToggle.classList.remove('is-active');
    if (els.canvasWrap) {
      els.canvasWrap.classList.remove('is-bg-move', 'is-bg-dragging');
    }
    updateBackgroundInteraction();
    updateCanvasCursor();
  }

  function toggleBgMoveMode() {
    if (!state.bgImage) return;
    if (state.bgMoveMode) {
      stopBgMoveMode();
      setFeedback('Image move mode off.', false);
      return;
    }
    stopRulerMode();
    if (state.calibrating) {
      state.calibrating = false;
      state.calibPoints = [];
      resetCalibrationUI();
    }
    clearWallSelection();
    clearVertexSelection();
    state.bgMoveMode = true;
    if (els.bgMoveToggle) els.bgMoveToggle.classList.add('is-active');
    if (els.canvasWrap) els.canvasWrap.classList.add('is-bg-move');
    updateBackgroundInteraction();
    setFeedback('Drag the floor plan to reposition it. Click Move image again when done.', false);
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
    state.activeBackgroundUrl = null;
    state.bgLayout = null;
    stopBgMoveMode();
    if (!url) {
      flushKonva();
      syncBackgroundUI();
      return;
    }

    state.activeBackgroundUrl = url;
    state.bgImageVisible = true;

    var img = new Image();
    if (url.indexOf('blob:') !== 0 && url.indexOf('data:') !== 0) {
      img.crossOrigin = 'anonymous';
    }
    img.onload = function () {
      if (token !== state.editorToken) return;
      if (state.activeBackgroundUrl !== url) return;
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
      bindBackgroundImageEvents(kImg);
      if (state.bgLayout) {
        applyBackgroundLayoutToKonva();
      } else {
        layoutBackgroundImage(true);
      }
      updateBackgroundInteraction();
      applyBackgroundImageVisibility();
      syncBackgroundUI();
    };
    img.onerror = function () {
      if (token !== state.editorToken) return;
      setFeedback('Could not load floor plan image.', true);
      syncBackgroundUI();
    };
    img.src = url.indexOf('blob:') === 0 || url.indexOf('data:') === 0
      ? url
      : url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
    syncBackgroundUI();
  }

  function redrawAll() {
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    redrawCalib();
    redrawRulers();
    redrawDrawPreview();
    updateLiveArea();
    updateToolbar();
  }

  function hexToRgba(hex, alpha) {
    var h = String(hex || DEFAULT_GROUP_COLOR).replace('#', '');
    if (h.length === 3) {
      h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    var r = parseInt(h.substring(0, 2), 16) || 2;
    var g = parseInt(h.substring(2, 4), 16) || 71;
    var b = parseInt(h.substring(4, 6), 16) || 104;
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function roomCanvasRaw(room) {
    if (room && room.canvas && room.canvas.groups) return room.canvas;
    if (room && room.vertices) return room.vertices;
    return null;
  }

  function roomHasDrawableData(room) {
    var raw = roomCanvasRaw(room);
    if (!raw) return false;
    return normalizeCanvasInput(raw).groups.length > 0;
  }

  function reloadSavedRoomCanvas(room) {
    var project = activeProject();
    if (!project || !room) return;
    var source = roomSourceCanvas(room, project);
    var target = hydrateScaleTarget(roomCanvasRaw(room), source.w, source.h);
    hydrateCanvas(roomCanvasRaw(room), target.fromW, target.fromH, target.toW, target.toH);
    applyWorkingScaleFromSaved(
      room.scale_pixels_per_foot || project.scale_pixels_per_foot,
      target
    );
    state.editingRoomId = room.id;
    if (els.roomName) els.roomName.value = room.name;
    clearUndoStack();
    updateEditUI();
    refreshViewportAfterContentChange();
    redrawAll();
  }

  function addAreaSameGroup() {
    if (!isActiveClosed()) {
      setFeedback('Close the current shape before adding another area.', true);
      return;
    }
    pushUndoSnapshot();
    startNewPolygon(state.activeGroupId);
    redrawAll();
    setFeedback('Draw another area in the same color group.', false);
  }

  function addAreaNewGroup() {
    if (!isActiveClosed()) {
      setFeedback('Close the current shape before starting a new color group.', true);
      return;
    }
    pushUndoSnapshot();
    startNewGroup();
    redrawAll();
    setFeedback('Draw an area in a new color group.', false);
  }

  function isDrawingOpenShape() {
    var poly = activePolygon();
    return !!poly && !poly.closed;
  }

  function removeLastDrawVertex() {
    if (!isDrawingOpenShape() || !activeVertices().length) return false;
    pushUndoSnapshot();
    activeVertices().pop();
    clearWallSelection();
    clearVertexSelection();
    redrawAll();
    setFeedback('Last corner removed.', false);
    return true;
  }

  function cancelActiveDrawing() {
    if (!isDrawingOpenShape()) return false;

    var group = activeGroup();
    var poly = activePolygon();
    if (!group || !poly) return false;

    pushUndoSnapshot();
    group.polygons = group.polygons.filter(function (p) { return p.id !== poly.id; });
    if (!group.polygons.length) {
      state.groups = state.groups.filter(function (g) { return g.id !== group.id; });
    }

    var closedEntries = allClosedPolygons();
    if (closedEntries.length) {
      var target = closedEntries[closedEntries.length - 1];
      state.activeGroupId = target.group.id;
      state.activePolygonId = target.polygon.id;
    } else {
      var any = allPolygons()[0];
      if (any) {
        state.activeGroupId = any.group.id;
        state.activePolygonId = any.polygon.id;
      } else {
        state.activeGroupId = null;
        state.activePolygonId = null;
        ensureInitialCanvas();
      }
    }

    clearWallSelection();
    clearVertexSelection();
    redrawAll();
    setFeedback('Drawing cancelled.', false);
    return true;
  }

  function lastDrawVertex() {
    if (isActiveClosed()) return null;
    var verts = activeVertices();
    if (!verts.length) return null;
    return verts[verts.length - 1];
  }

  function snapDrawPreviewPoint(pos) {
    var prev = lastDrawVertex();
    if (!prev || !pos) return null;
    var point = state.shiftHeld ? snapPoint(pos, prev, SHIFT_SNAP_STEP) : pos;
    var verts = activeVertices();
    if (verts.length >= 3) {
      var first = verts[0];
      if (dist(point, first) <= hitTolerance(CLOSE_HIT)) {
        return { x: first.x, y: first.y };
      }
    }
    return clampToCanvas(point);
  }

  function clearDrawPreview() {
    var layer = state.layers.preview;
    var hadPreview = !!state.drawPreview || (layer && layer.getChildren && layer.getChildren().length);
    state.drawPreview = null;
    if (hadPreview) redrawDrawPreview();
  }

  function updateDrawPreviewFromPointer() {
    if (
      state.panning
      || state.rulerMode
      || state.calibrating
      || state.spaceHeld
      || state.draggingWall
      || state.draggingVertex
      || state.bgMoveMode
    ) {
      clearDrawPreview();
      return;
    }
    if (!lastDrawVertex()) {
      clearDrawPreview();
      return;
    }
    var pos = getPointerPos();
    if (!pos) return;
    state.drawPreview = snapDrawPreviewPoint(pos);
    redrawDrawPreview();
  }

  function redrawDrawPreview() {
    var layer = state.layers.preview;
    if (!layer) return;
    layer.destroyChildren();

    var prev = lastDrawVertex();
    var dest = state.drawPreview;
    if (!prev || !dest || dist(prev, dest) < 1) {
      flushKonva();
      return;
    }

    var group = activeGroup();
    var color = group ? group.color : DEFAULT_GROUP_COLOR;
    layer.add(new Konva.Line({
      points: [prev.x, prev.y, dest.x, dest.y],
      stroke: color,
      strokeWidth: uiSize(2),
      dash: [uiSize(8), uiSize(5)],
      opacity: 0.4,
      lineCap: 'round',
      listening: false
    }));
    layer.add(new Konva.Circle({
      x: dest.x,
      y: dest.y,
      radius: uiSize(6),
      fill: hexToRgba(color, 0.2),
      stroke: color,
      strokeWidth: uiSize(1.5),
      opacity: 0.65,
      listening: false
    }));

    var ppf = pixelsPerFoot();
    if (ppf > 0) {
      var kLabel = new Konva.Label({
        x: (prev.x + dest.x) / 2,
        y: (prev.y + dest.y) / 2,
        opacity: 0.88,
        listening: false
      });
      kLabel.add(new Konva.Tag({
        fill: 'rgba(255,255,255,0.9)',
        stroke: color,
        strokeWidth: uiSize(1),
        cornerRadius: uiSize(4),
        pointerDirection: 'none'
      }));
      kLabel.add(new Konva.Text({
        text: fmtLengthFromFeet(dist(prev, dest) / ppf),
        fontSize: uiSize(11),
        fontFamily: 'Open Sans, sans-serif',
        fill: color,
        padding: uiSize(4)
      }));
      kLabel.offsetX(kLabel.width() / 2);
      kLabel.offsetY(kLabel.height() / 2);
      layer.add(kLabel);
    }

    flushKonva();
  }

  function redrawPolygonLine() {
    var layer = state.layers.draw;
    if (!layer) return;
    layer.destroyChildren();

    var hasAny = false;
    allPolygons().forEach(function (entry) {
      var group = entry.group;
      var poly = entry.polygon;
      var verts = poly.vertices;
      if (!verts.length) return;
      hasAny = true;
      var isActive = group.id === state.activeGroupId && poly.id === state.activePolygonId;
      var opacity = isActive ? 1 : INACTIVE_POLYGON_OPACITY;
      var linePoints = [];
      verts.forEach(function (v) { linePoints.push(v.x, v.y); });
      if (poly.closed && verts.length >= 3) {
        linePoints.push(verts[0].x, verts[0].y);
        layer.add(new Konva.Line({
          points: linePoints,
          closed: true,
          fill: hexToRgba(group.color, 0.12 * opacity),
          opacity: opacity,
          listening: false
        }));
      }
      if (linePoints.length >= 4 || (!poly.closed && linePoints.length >= 2)) {
        layer.add(new Konva.Line({
          points: linePoints,
          stroke: group.color,
          strokeWidth: uiSize(isActive ? 2.5 : 2),
          opacity: opacity,
          closed: poly.closed,
          listening: false
        }));
      }
    });

    if (!hasAny) {
      flushKonva();
      return;
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

    var verts = activeVertices();
    verts.forEach(function (v, idx) {
      var isFirst = idx === 0;
      var isSelected = isVertexSelected(idx);
      var showCloseTarget = isFirst && !isActiveClosed() && verts.length >= 3;
      (function (vertexIndex) {
        var groupColor = activeGroup() ? activeGroup().color : DEFAULT_GROUP_COLOR;
        var circle = new Konva.Circle({
          x: v.x,
          y: v.y,
          radius: uiSize(isSelected ? 10 : (showCloseTarget ? CLOSE_HIT / 2 : 8)),
          fill: isSelected ? '#f9a825' : (isFirst ? '#c62828' : groupColor),
          stroke: isSelected ? '#f57f17' : '#fff',
          strokeWidth: uiSize(isSelected ? 3 : 2),
          draggable: !state.spaceHeld && !state.rulerMode && !state.bgMoveMode,
          listening: !state.spaceHeld && !state.rulerMode && !state.bgMoveMode,
          dragDistance: uiSize(3)
        });
        circle.on('mouseenter', function () {
          if (state.stage && !state.draggingVertex && !state.draggingWall && !state.spaceHeld && !state.rulerMode) {
            state.stage.container().style.cursor = 'move';
          }
        });
        circle.on('mouseleave', function () {
          if (state.stage && !state.draggingVertex && !state.draggingWall) updateCanvasCursor();
        });
        circle.on('dragstart', function (e) {
          if (state.spaceHeld) {
            e.target.stopDrag();
            return;
          }
          var shiftKey = isShiftKey(e);
          if (shiftKey && !isVertexSelected(vertexIndex)) {
            e.target.stopDrag();
            return;
          }
          e.cancelBubble = true;
          state.didVertexDrag = false;
          if (!isVertexSelected(vertexIndex)) {
            clearRulerSelection();
            clearWallSelection();
            state.selectedVertexIndices = [vertexIndex];
            show(els.vertexEdit, true);
            if (els.canvasWrap) els.canvasWrap.classList.add('is-vertex-edit');
            updateToolbar();
          }
          state.vertexDragOrigins = {};
          state.selectedVertexIndices.forEach(function (i) {
            var v = activeVertices()[i];
            state.vertexDragOrigins[i] = { x: v.x, y: v.y };
          });
          state.draggingVertex = true;
          state.vertexDragFrom = {
            x: activeVertices()[vertexIndex].x,
            y: activeVertices()[vertexIndex].y
          };
          pushUndoSnapshot();
          updateCanvasCursor();
        });
        circle.on('click tap', function (e) {
          e.cancelBubble = true;
          if (state.rulerMode) return;
          if (state.didVertexDrag) {
            state.didVertexDrag = false;
            return;
          }
          var shiftKey = isShiftKey(e);
          if (vertexIndex === 0 && !isActiveClosed() && activeVertices().length >= 3 && !state.draggingVertex && !shiftKey) {
            closeShape();
            return;
          }
          if (!state.draggingVertex) {
            if (shiftKey) toggleVertexSelection(vertexIndex);
            else selectVertices([vertexIndex]);
          }
        });
        circle.on('dragmove', function (e) {
          e.cancelBubble = true;
          state.didVertexDrag = true;
          var raw = { x: circle.x(), y: circle.y() };
          applyVertexGroupDrag(vertexIndex, raw, false);
          if (state.selectedVertexIndices.length > 1) {
            syncSelectedVertexCircles();
          } else {
            circle.position(activeVertices()[vertexIndex]);
          }
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
          var raw = { x: circle.x(), y: circle.y() };
          var dragFrom = state.vertexDragFrom;
          var origins = state.vertexDragOrigins;
          var multi = origins && state.selectedVertexIndices.length > 1;
          state.vertexDragFrom = null;
          state.vertexDragOrigins = null;
          applyVertexGroupDrag(vertexIndex, raw, true);
          if (!isValidShape(activeVertices(), isActiveClosed())) {
            var snap = state.undoStack.length ? state.undoStack[state.undoStack.length - 1] : null;
            if (snap) restoreUndoSnapshot(snap);
            setFeedback('That corner position would make the shape cross itself.', true);
          } else if (dragFrom && dist(dragFrom, activeVertices()[vertexIndex]) < 1e-6 && !multi) {
            discardLastUndoSnapshot();
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

    drawAreaNameLabels(labelLayer);

    var verts = activeVertices();
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
      var lenLabel = fmtLengthFromFeet(lenFt);
      var isSelected = state.selectedWallIndex === i;
      (function (wallIndex) {
        var kLabel = new Konva.Label({
          x: midX,
          y: midY,
          listening: !state.rulerMode && !state.spaceHeld
        });
        bindWallDrag(kLabel, wallIndex);
        bindWallSplit(kLabel, wallIndex);
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

  function polygonCentroid(verts) {
    var n = verts.length;
    if (!n) return null;
    if (n < 3) {
      var sx = 0;
      var sy = 0;
      verts.forEach(function (v) {
        sx += v.x;
        sy += v.y;
      });
      return { x: sx / n, y: sy / n };
    }
    var area2 = 0;
    var cx = 0;
    var cy = 0;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      var cross = verts[i].x * verts[j].y - verts[j].x * verts[i].y;
      area2 += cross;
      cx += (verts[i].x + verts[j].x) * cross;
      cy += (verts[i].y + verts[j].y) * cross;
    }
    if (Math.abs(area2) < 1e-9) {
      var ax = 0;
      var ay = 0;
      verts.forEach(function (v) {
        ax += v.x;
        ay += v.y;
      });
      return { x: ax / n, y: ay / n };
    }
    return { x: cx / (3 * area2), y: cy / (3 * area2) };
  }

  function polygonRayDistance(origin, dx, dy, verts) {
    var best = Infinity;
    var n = verts.length;
    for (var i = 0; i < n; i++) {
      var a = verts[i];
      var b = verts[(i + 1) % n];
      var ex = b.x - a.x;
      var ey = b.y - a.y;
      var denom = dx * ey - dy * ex;
      if (Math.abs(denom) < 1e-9) continue;
      var t = ((a.x - origin.x) * ey - (a.y - origin.y) * ex) / denom;
      var u = ((a.x - origin.x) * dy - (a.y - origin.y) * dx) / denom;
      if (t > 1e-6 && u >= -1e-6 && u <= 1 + 1e-6 && t < best) {
        best = t;
      }
    }
    return best;
  }

  function polygonAabbSize(verts) {
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    verts.forEach(function (v) {
      if (v.x < minX) minX = v.x;
      if (v.y < minY) minY = v.y;
      if (v.x > maxX) maxX = v.x;
      if (v.y > maxY) maxY = v.y;
    });
    return { width: Math.max(0, maxX - minX), height: Math.max(0, maxY - minY) };
  }

  function polygonAxisSpan(origin, verts, axisX, axisY) {
    var fwd = polygonRayDistance(origin, axisX, axisY, verts);
    var back = polygonRayDistance(origin, -axisX, -axisY, verts);
    if (isFinite(fwd) && isFinite(back)) return fwd + back;
    var box = polygonAabbSize(verts);
    return axisX ? box.width : box.height;
  }

  function labelOverflow(labelW, labelH, boxW, boxH) {
    return Math.max(0, labelW - boxW) + Math.max(0, labelH - boxH);
  }

  function drawAreaNameLabels(labelLayer) {
    allPolygons().forEach(function (entry) {
      var poly = entry.polygon;
      var name = polygonName(poly);
      if (!name || !poly.closed || poly.vertices.length < 3) return;
      var center = polygonCentroid(poly.vertices);
      if (!center) return;
      var isActive = entry.group.id === state.activeGroupId && poly.id === state.activePolygonId;
      var kLabel = new Konva.Label({
        x: center.x,
        y: center.y,
        opacity: isActive ? 1 : INACTIVE_POLYGON_OPACITY,
        listening: false
      });
      kLabel.add(new Konva.Tag({
        fill: 'rgba(255,255,255,0.92)',
        stroke: entry.group.color || DEFAULT_GROUP_COLOR,
        strokeWidth: uiSize(isActive ? 1.5 : 1),
        cornerRadius: uiSize(4),
        pointerDirection: 'none'
      }));
      var textNode = new Konva.Text({
        text: name,
        fontSize: uiSize(13),
        fontFamily: 'Open Sans, sans-serif',
        fontStyle: 'bold',
        fill: entry.group.color || DEFAULT_GROUP_COLOR,
        padding: uiSize(6),
        align: 'center'
      });
      if (textNode.width() > uiSize(160)) {
        textNode.width(uiSize(160));
        textNode.wrap('word');
      }
      kLabel.add(textNode);
      kLabel.offsetX(kLabel.width() / 2);
      kLabel.offsetY(kLabel.height() / 2);

      var slack = uiSize(8);
      var hSpan = Math.max(0, polygonAxisSpan(center, poly.vertices, 1, 0) - slack);
      var vSpan = Math.max(0, polygonAxisSpan(center, poly.vertices, 0, 1) - slack);
      var lw = kLabel.width();
      var lh = kLabel.height();
      if (labelOverflow(lh, lw, hSpan, vSpan) + 1 < labelOverflow(lw, lh, hSpan, vSpan)) {
        kLabel.rotation(-90);
      }
      labelLayer.add(kLabel);
    });
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
      var a = activeVertices()[i];
      var b = activeVertices()[wallEndIndex(i)];
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
          listening: !state.spaceHeld && !state.rulerMode && isActiveClosed()
        });
        bindWallDrag(hitLine, wallIndex);
        bindWallSplit(hitLine, wallIndex);
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

  function drawRulerSegment(layer, p1, p2, label, isSelected, rulerId, listening) {
    var stroke = isSelected ? '#f57f17' : '#6a1b9a';
    var line = new Konva.Line({
      points: [p1.x, p1.y, p2.x, p2.y],
      stroke: stroke,
      strokeWidth: uiSize(isSelected ? 3 : 2),
      dash: [uiSize(6), uiSize(4)],
      listening: !!listening,
      hitStrokeWidth: uiSize(Math.max(RULER_HIT, 12))
    });

    if (listening && rulerId !== null) {
      line.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      line.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      line.on('mouseleave', function () {
        if (state.stage) updateCanvasCursor();
      });
    }
    layer.add(line);

    [p1, p2].forEach(function (p, idx) {
      layer.add(new Konva.Circle({
        x: p.x,
        y: p.y,
        radius: uiSize(5),
        fill: idx === 0 ? '#8e24aa' : '#6a1b9a',
        stroke: '#fff',
        strokeWidth: uiSize(1.5),
        listening: false
      }));
    });

    if (!label) return;

    var midX = (p1.x + p2.x) / 2;
    var midY = (p1.y + p2.y) / 2;
    var kLabel = new Konva.Label({
      x: midX,
      y: midY,
      listening: !!listening && rulerId !== null
    });
    if (listening && rulerId !== null) {
      kLabel.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      kLabel.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      kLabel.on('mouseleave', function () {
        if (state.stage) updateCanvasCursor();
      });
    }
    kLabel.add(new Konva.Tag({
      fill: isSelected ? 'rgba(249, 168, 37, 0.95)' : 'rgba(255, 255, 255, 0.94)',
      stroke: isSelected ? '#f57f17' : '#6a1b9a',
      strokeWidth: uiSize(1),
      cornerRadius: uiSize(4),
      pointerDirection: 'none',
      pointerWidth: 0,
      pointerHeight: 0,
      lineJoin: 'round'
    }));
    kLabel.add(new Konva.Text({
      text: label,
      fontFamily: 'system-ui, sans-serif',
      fontSize: uiSize(12),
      fontStyle: 'bold',
      fill: isSelected ? '#5d4037' : '#4a148c',
      padding: uiSize(5)
    }));
    layer.add(kLabel);
  }

  function addSavedRuler(layer, ruler) {
    var rulerId = ruler.id;
    var isSelected = state.selectedRulerId === rulerId;
    var listening = !state.rulerMode;
    var stroke = isSelected ? '#f57f17' : '#6a1b9a';
    var line = new Konva.Line({
      points: [ruler.p1.x, ruler.p1.y, ruler.p2.x, ruler.p2.y],
      stroke: stroke,
      strokeWidth: uiSize(isSelected ? 3 : 2),
      dash: [uiSize(6), uiSize(4)],
      listening: !!listening,
      hitStrokeWidth: uiSize(Math.max(RULER_HIT, 12))
    });

    if (listening) {
      line.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      line.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld && !state.draggingRuler) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      line.on('mouseleave', function () {
        if (state.stage && !state.draggingRuler) updateCanvasCursor();
      });
    }
    layer.add(line);

    var label = new Konva.Label({
      x: (ruler.p1.x + ruler.p2.x) / 2,
      y: (ruler.p1.y + ruler.p2.y) / 2,
      listening: !!listening
    });
    if (listening) {
      label.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      label.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld && !state.draggingRuler) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      label.on('mouseleave', function () {
        if (state.stage && !state.draggingRuler) updateCanvasCursor();
      });
    }
    label.add(new Konva.Tag({
      fill: isSelected ? 'rgba(249, 168, 37, 0.95)' : 'rgba(255, 255, 255, 0.94)',
      stroke: isSelected ? '#f57f17' : '#6a1b9a',
      strokeWidth: uiSize(1),
      cornerRadius: uiSize(4),
      pointerDirection: 'none',
      pointerWidth: 0,
      pointerHeight: 0,
      lineJoin: 'round'
    }));
    var labelText = new Konva.Text({
      text: rulerLengthLabel(ruler),
      fontFamily: 'system-ui, sans-serif',
      fontSize: uiSize(12),
      fontStyle: 'bold',
      fill: isSelected ? '#5d4037' : '#4a148c',
      padding: uiSize(5)
    });
    label.add(labelText);
    layer.add(label);

    function syncRulerGraphics() {
      line.points([ruler.p1.x, ruler.p1.y, ruler.p2.x, ruler.p2.y]);
      label.position({
        x: (ruler.p1.x + ruler.p2.x) / 2,
        y: (ruler.p1.y + ruler.p2.y) / 2
      });
      labelText.text(rulerLengthLabel(ruler));
      if (isSelected && state.selectedRulerId === rulerId && els.rulerLength) {
        els.rulerLength.textContent = rulerLengthLabel(ruler);
      }
      flushKonva();
    }

    ['p1', 'p2'].forEach(function (key, idx) {
      var canDrag = isSelected && listening && !state.spaceHeld;
      var circle = new Konva.Circle({
        x: ruler[key].x,
        y: ruler[key].y,
        radius: uiSize(canDrag ? 8 : 5),
        fill: idx === 0 ? '#8e24aa' : '#6a1b9a',
        stroke: '#fff',
        strokeWidth: uiSize(canDrag ? 2 : 1.5),
        draggable: canDrag,
        listening: canDrag,
        dragDistance: uiSize(3)
      });

      if (canDrag) {
        circle.on('dragstart', function (e) {
          if (state.spaceHeld || state.rulerMode) {
            e.target.stopDrag();
            return;
          }
          e.cancelBubble = true;
          state.draggingRuler = true;
          if (state.selectedRulerId !== rulerId) selectRuler(rulerId);
          updateCanvasCursor();
        });
        circle.on('dragmove', function (e) {
          e.cancelBubble = true;
          var pos = clampToCanvas({ x: circle.x(), y: circle.y() });
          var anchor = key === 'p1' ? ruler.p2 : ruler.p1;
          if (state.shiftHeld) {
            pos = clampToCanvas(snapPoint(pos, anchor, SHIFT_SNAP_STEP));
          }
          circle.position(pos);
          ruler[key] = { x: pos.x, y: pos.y };
          syncRulerGraphics();
        });
        circle.on('dragend', function (e) {
          e.cancelBubble = true;
          state.draggingRuler = false;
          updateCanvasCursor();
        });
        circle.on('mouseenter', function () {
          if (state.stage && !state.draggingRuler && !state.spaceHeld && !state.rulerMode) {
            state.stage.container().style.cursor = 'move';
          }
        });
        circle.on('mouseleave', function () {
          if (state.stage && !state.draggingRuler) updateCanvasCursor();
        });
      }

      layer.add(circle);
    });
  }

  function redrawRulers() {
    var layer = state.layers.rulers;
    if (!layer) return;
    layer.destroyChildren();

    projectRulers().forEach(function (ruler) {
      addSavedRuler(layer, ruler);
    });

    if (state.rulerDraft.length === 1) {
      layer.add(new Konva.Circle({
        x: state.rulerDraft[0].x,
        y: state.rulerDraft[0].y,
        radius: uiSize(7),
        fill: '#8e24aa',
        stroke: '#fff',
        strokeWidth: uiSize(2),
        listening: false
      }));
      if (state.rulerPreview) {
        drawRulerSegment(
          layer,
          state.rulerDraft[0],
          state.rulerPreview,
          rulerLengthLabel({ p1: state.rulerDraft[0], p2: state.rulerPreview }),
          false,
          null,
          false
        );
      }
    } else if (state.rulerDraft.length === 2) {
      drawRulerSegment(
        layer,
        state.rulerDraft[0],
        state.rulerDraft[1],
        rulerLengthLabel({ p1: state.rulerDraft[0], p2: state.rulerDraft[1] }),
        false,
        null,
        false
      );
    }

    flushKonva();
  }

  function getPointerPos() {
    var pos = state.stage.getPointerPosition();
    if (!pos) return null;
    return stageToWorld(pos);
  }

  function onStageClick(e) {
    if (state.spaceHeld || state.panning || state.bgMoveMode || state.draggingBg) return;
    var pos = getPointerPos();
    if (!pos) return;
    if (state.didPan) {
      state.didPan = false;
      return;
    }
    if (state.draggingVertex || state.draggingWall || state.draggingRuler || state.didWallDrag) {
      if (state.didWallDrag) state.didWallDrag = false;
      return;
    }

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

    if (state.rulerMode) {
      var rulerShiftSnap = state.shiftHeld || !!(e && e.evt && e.evt.shiftKey);
      placeRulerPoint(pos, rulerShiftSnap);
      return;
    }

    var clickedRuler = findRulerAtPoint(pos);
    if (clickedRuler) {
      selectRuler(clickedRuler.id);
      return;
    }

    if (state.selectedRulerId !== null) {
      clearRulerSelection();
    }

    if (findVertexAtPoint(pos) >= 0) return;

    // While drawing an open shape, prioritize new corners over selecting other areas.
    if (!isActiveClosed()) {
      clearVertexSelection();
      clearWallSelection();

      if (activeVertices().length >= 3) {
        var closeFirst = activeVertices()[0];
        if (dist(pos, closeFirst) <= hitTolerance(CLOSE_HIT)) {
          closeShape();
          return;
        }
      }

      var drawShiftSnap = state.shiftHeld || !!(e && e.evt && e.evt.shiftKey);
      var drawPoint = pos;
      if (activeVertices().length > 0) {
        var drawPrev = activeVertices()[activeVertices().length - 1];
        if (drawShiftSnap) {
          drawPoint = snapPoint(pos, drawPrev, SHIFT_SNAP_STEP);
        }
      }
      pushUndoSnapshot();
      activeVertices().push(clampToCanvas(drawPoint));
      redrawAll();
      updateDrawPreviewFromPointer();
      return;
    }

    var polygonHit = polygonAtPoint(pos);
    if (polygonHit) {
      if (polygonHit.group.id !== state.activeGroupId || polygonHit.polygon.id !== state.activePolygonId) {
        activatePolygon(polygonHit.group.id, polygonHit.polygon.id);
        return;
      }
      if (wallCount() > 0) {
        var wallIdx = findWallAtPoint(pos);
        if (wallIdx >= 0) return;
      }
    } else if (wallCount() > 0) {
      var wallIdxInactive = findWallAtPoint(pos);
      if (wallIdxInactive >= 0) return;
    }

    clearVertexSelection();

    if (isActiveClosed()) {
      clearWallSelection();
      return;
    }

    if (activeVertices().length >= 3) {
      var first = activeVertices()[0];
      if (dist(pos, first) <= hitTolerance(CLOSE_HIT)) {
        closeShape();
        return;
      }
    }

    var shiftSnap = state.shiftHeld || !!(e && e.evt && e.evt.shiftKey);
    var point = pos;
    if (activeVertices().length > 0) {
      var prev = activeVertices()[activeVertices().length - 1];
      if (shiftSnap) {
        point = snapPoint(pos, prev, SHIFT_SNAP_STEP);
      } else {
        point = pos;
      }
    }
    pushUndoSnapshot();
    activeVertices().push(clampToCanvas(point));
    redrawAll();
  }

  function onStageDblClick(e) {
    if (e && e.evt) e.evt.preventDefault();
    resetWallClickState();
    if (state.spaceHeld || state.panning || state.calibrating || state.rulerMode) return;
    if (state.draggingWall) {
      finishWallDrag(getPointerPos());
      state.didWallDrag = false;
    }
    var pos = getPointerPos();
    if (!pos) return;

    if (findVertexAtPoint(pos) >= 0) return;

    if (wallCount() > 0) {
      var wallIdx = findWallAtPoint(pos);
      if (wallIdx >= 0) {
        clearWallSelection();
        splitWallAtPointer(wallIdx);
        return;
      }
    }

    if (!isActiveClosed() && activeVertices().length >= 3) {
      closeShape();
    }
  }

  function closeShape() {
    if (activeVertices().length < 3) return;
    if (!isSimplePolygon(activeVertices())) {
      setFeedback('Shape crosses itself — adjust corners before closing.', true);
      return;
    }
    pushUndoSnapshot();
    setActiveClosed(true);
    clearDrawPreview();
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
    resetCanvasGroups();
    ensureInitialCanvas();
    state.editingRoomId = null;
    state.selectedVertexIndices = [];
    state.vertexDragOrigins = null;
    state.draggingVertex = false;
    state.draggingWall = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    state.wallPointerDown = null;
    cancelPendingWallClick();
    state.didWallDrag = false;
    state.didVertexDrag = false;
    stopCalibrationMode();
    state.draftRulers = [];
    setWorkingPixelsPerFoot(null);
    clearWallSelection();
    clearVertexSelection();
    clearRulerSelection();
    clearUndoStack();
    if (els.roomName) els.roomName.value = '';
    show(els.liveArea, false);
    clearDraftLayers();
    updateEditUI();
    redrawAll();
    flushKonva();
    captureSavedSnapshot();
  }

  function clearShape() {
    resetDraftCanvas();
  }

  function beginNewRoom() {
    resetDraftCanvas();
    revokeDraftBackground();
    state.bgLayout = null;
    stopBgMoveMode();
    state.draftScale = null;
    setWorkingPixelsPerFoot(null);
    loadBackground(null);
    captureSavedSnapshot();
    if (els.roomName) {
      els.roomName.focus();
    }
    setFeedback('Draw a new room outline, upload its floor plan, enter a name, and click Save room.', false);
  }

  function startNewRoom() {
    if (!hasUnsavedChanges()) {
      beginNewRoom();
      return;
    }
    confirmAction(
      'You have unsaved changes. Save them before starting a new room, or discard them?',
      {
        title: 'Unsaved changes',
        confirmLabel: 'Save changes',
        cancelLabel: 'Discard & continue'
      }
    ).then(function (shouldSave) {
      if (!shouldSave) {
        beginNewRoom();
        return;
      }
      return saveRoom().then(function () {
        beginNewRoom();
      }).catch(function (err) {
        setFeedback((err && err.message) || 'Could not save changes.', true);
      });
    });
  }

  function undoVertex() {
    if (!state.undoStack.length) return;
    if (state.draggingWall) finishWallDrag(getPointerPos());
    state.draggingVertex = false;
    state.vertexDragFrom = null;
    var snap = state.undoStack.pop();
    restoreUndoSnapshot(snap);
    redrawAll();
    updateToolbar();
    setFeedback('Undone.', false);
  }

  function updateLiveArea() {
    var activeSqft = isActiveClosed() ? areaSqFt(activeVertices()) : null;
    var grandTotal = hasAnyClosedPolygon() ? canvasTotalAreaSqFt() : null;
    if (grandTotal === null) {
      show(els.liveArea, false);
      els.saveRoom.disabled = true;
      updateGroupChip();
      return;
    }
    show(els.liveArea, true);
    if (els.liveActiveShape) {
      els.liveActiveShape.textContent = activeSqft !== null ? fmt(activeSqft, 2) : '—';
    }
    if (els.liveGrandTotal) {
      els.liveGrandTotal.textContent = fmt(grandTotal, 2);
    }
    if (els.liveSqft) els.liveSqft.textContent = fmt(grandTotal, 2);
    if (els.liveSqm) els.liveSqm.textContent = fmt(grandTotal * SQFT_TO_SQM, 2);
    if (els.liveGroupList) {
      els.liveGroupList.innerHTML = '';
      state.groups.forEach(function (group) {
        var count = (group.polygons || []).filter(function (p) { return p.closed && p.vertices.length >= 3; }).length;
        if (!count) return;
        var li = document.createElement('li');
        li.className = 'util-ra-live-area-groups__item';
        li.innerHTML =
          '<span class="util-ra-live-area-groups__swatch" style="background:' + esc(group.color) + '"></span>' +
          '<span class="util-ra-live-area-groups__label">Group total:</span>' +
          '<strong>' + fmt(groupAreaSqFt(group), 2) + '</strong> sq ft' +
          (count > 1 ? ' <span class="util-ra-live-area-groups__count">(' + count + ' areas)</span>' : '');
        els.liveGroupList.appendChild(li);
      });
    }
    updateGroupChip();
    els.saveRoom.disabled = !pixelsPerFoot();
  }

  function updateToolbar() {
    els.undoVertex.disabled = state.undoStack.length === 0;
    els.closeShape.disabled = activeVertices().length < 3 || isActiveClosed();
    if (els.addArea) els.addArea.disabled = !isActiveClosed();
    if (els.addAreaNewGroup) els.addAreaNewGroup.disabled = !isActiveClosed();
    if (els.deleteArea) {
      var canDelete = hasAnyClosedPolygon() || activeVertices().length > 0;
      els.deleteArea.disabled = !canDelete;
    }
    if (els.deleteWall) {
      els.deleteWall.disabled = state.selectedWallIndex === null;
    }
    if (els.deleteVertex) {
      els.deleteVertex.disabled = state.selectedVertexIndices.length === 0 || activeVertices().length < 2;
    }
    if (els.deleteRuler) {
      els.deleteRuler.disabled = state.selectedRulerId === null;
    }
    if (els.wallApply) {
      els.wallApply.disabled = state.selectedWallIndex === null || !pixelsPerFoot();
    }
    syncAreaNameField();
  }

  function syncAreaNameField() {
    if (!els.areaNameWrap || !els.areaName) return;
    var poly = activePolygon();
    var closed = !!(poly && poly.closed && poly.vertices.length >= 3);
    show(els.areaNameWrap, closed);
    if (!closed) return;
    if (document.activeElement === els.areaName) return;
    els.areaName.value = poly.name || '';
  }

  var areaNameUndoPushed = false;

  function bindAreaNameField() {
    if (!els.areaName) return;
    els.areaName.addEventListener('focus', function () {
      areaNameUndoPushed = false;
    });
    els.areaName.addEventListener('input', function () {
      var poly = activePolygon();
      if (!poly) return;
      if (!areaNameUndoPushed) {
        pushUndoSnapshot();
        areaNameUndoPushed = true;
      }
      poly.name = String(els.areaName.value).slice(0, AREA_NAME_MAX);
      redrawLabels();
    });
    els.areaName.addEventListener('change', function () {
      var poly = activePolygon();
      if (!poly) return;
      poly.name = sanitizeAreaName(els.areaName.value);
      els.areaName.value = poly.name;
      redrawLabels();
    });
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
    redrawRulers();
    if (state.selectedRulerId !== null && els.rulerLength) {
      var selectedRuler = findRulerById(state.selectedRulerId);
      if (selectedRuler) els.rulerLength.textContent = rulerLengthLabel(selectedRuler);
    }
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

    if (state.rulerMode) stopRulerMode();
    stopBgMoveMode();
    clearRulerSelection();

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
      var feet = parseLengthInput(els.knownLength.value);
      var pxDist = dist(state.calibPoints[0], state.calibPoints[1]);
      if (pxDist < 5) throw new Error('Points are too close — pick a longer reference.');
      var ppf = pxDist / feet;
      var project = activeProject();
      if (!project) return;

      stopCalibrationMode();
      updateScaleUI();

      if (state.editingRoomId) {
        var room = activeRoom();
        if (!room) return;
        setWorkingPixelsPerFoot(ppf);
        fetch(urlFromTemplate(root.getAttribute('data-room-update-template'), room.id), {
          method: 'PUT',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
          },
          body: JSON.stringify({
            name: room.name,
            canvas: serializeCanvas(),
            scale_pixels_per_foot: ppf,
            canvas_width: contentW(),
            canvas_height: contentH()
          })
        })
          .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
          .then(function (result) {
            if (!result.ok) throw new Error(result.data.message || 'Could not save scale.');
            var idx = project.rooms.findIndex(function (r) { return r.id === room.id; });
            if (idx >= 0) project.rooms[idx] = result.data.room;
            updateScaleUI();
            captureSavedSnapshot();
            setFeedback('Scale calibrated for this room.', false);
          })
          .catch(function (e) {
            setFeedback(e.message, true);
          });
        return;
      }

      state.draftScale = ppf;
      setWorkingPixelsPerFoot(ppf);
      setFeedback('Scale calibrated. Save the room to keep this scale with it.', false);
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
        ? '<i class="fa fa-save"></i> Save changes'
        : '<i class="fa fa-save"></i> Save room';
    }
    if (els.saveCardTitle) {
      els.saveCardTitle.textContent = editing ? 'Save changes' : 'Save room';
    }
    show(els.cancelEdit, editing);
    renderRooms();
  }

  function loadRoom(roomId) {
    var project = activeProject();
    if (!project) return;
    var room = project.rooms.find(function (r) { return r.id === roomId; });
    if (!room || !roomHasDrawableData(room)) {
      setFeedback('Could not load that room.', true);
      return;
    }

    var token = bumpEditorToken();
    clearWallSelection();
    clearRulerSelection();
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
      if (!currentRoom || !roomHasDrawableData(currentRoom)) {
        clearShape();
        return;
      }

      resizeStage();
      loadBackground(currentRoom.background_url || null);

      var source = roomSourceCanvas(currentRoom, currentProject);
      var target = hydrateScaleTarget(roomCanvasRaw(currentRoom), source.w, source.h);
      hydrateCanvas(roomCanvasRaw(currentRoom), target.fromW, target.fromH, target.toW, target.toH);
      applyWorkingScaleFromSaved(
        currentRoom.scale_pixels_per_foot || currentProject.scale_pixels_per_foot,
        target
      );
      clearUndoStack();
      redrawAll();
      fitViewport();
      captureSavedSnapshot();
      setFeedback('Editing "' + currentRoom.name + '". Change the shape and click Save changes.', false);
    });
  }

  function cancelEdit() {
    if (!state.editingRoomId) return;
    clearShape();
    loadBackground(null);
    captureSavedSnapshot();
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
      var summaries = room.group_summaries || [];
      var breakdownHtml = '';
      if (summaries.length > 1) {
        breakdownHtml = '<ul class="util-ra-rooms-item__groups">';
        summaries.forEach(function (summary) {
          breakdownHtml +=
            '<li class="util-ra-rooms-item__group">' +
              '<span class="util-ra-rooms-item__group-swatch" style="background:' + esc(summary.color) + '"></span>' +
              '<span class="util-ra-rooms-item__group-area">' + fmt(summary.area_sqft, 2) + ' sq ft</span>' +
              (summary.polygon_count > 1
                ? '<span class="util-ra-rooms-item__group-count">' + summary.polygon_count + ' areas</span>'
                : '') +
            '</li>';
        });
        breakdownHtml += '</ul>';
      }
      li.innerHTML =
        '<div class="util-ra-rooms-item__main">' +
          '<div class="util-ra-rooms-item__title-row">' +
            '<span class="util-ra-rooms-item__name">' + esc(room.name) + '</span>' +
            '<span class="util-ra-rooms-item__area">' + fmt(room.area_sqft, 2) + ' sq ft</span>' +
          '</div>' +
          breakdownHtml +
        '</div>' +
        '<div class="util-ra-rooms-item__actions">' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-edit-room="' + room.id + '" title="Edit room"><i class="fa fa-pencil"></i><span class="util-ra-rooms-item__action-label">Edit</span></button>' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-copy-area="' + room.area_sqft + '" title="Copy sq ft"><i class="fa fa-copy"></i><span class="util-ra-rooms-item__action-label">Copy</span></button>' +
          '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-delete-room="' + room.id + '" title="Delete"><i class="fa fa-trash"></i><span class="util-ra-rooms-item__action-label">Delete</span></button>' +
        '</div>';
      els.roomsList.appendChild(li);
    });

    els.totalSqft.textContent = fmt(total, 2);

    els.roomsList.querySelectorAll('[data-edit-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var roomId = parseInt(btn.getAttribute('data-edit-room'), 10);
        if (roomId === state.editingRoomId) return;
        promptUnsavedBeforeAction(function () {
          loadRoom(roomId);
        });
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
    stopRulerMode();
    clearRulerSelection();
    clearShape();
    var token = state.editorToken;
    ensureStage();
    loadBackground(null);
    scheduleStageResize();
    setTimeout(function () {
      if (token !== state.editorToken) return;
      var currentProject = activeProject();
      if (!currentProject || currentProject.id !== projectId) return;

      resizeStage();
      applyWorkingScaleFromSaved(
        currentProject.scale_pixels_per_foot,
        {
          fromW: currentProject.canvas_width || contentW(),
          fromH: currentProject.canvas_height || contentH(),
          toW: contentW(),
          toH: contentH()
        }
      );
      loadBackground(null);
      updateScaleUI();
      refreshViewportAfterContentChange();
      captureSavedSnapshot();
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
    if (!project || !hasAnyClosedPolygon()) {
      return Promise.reject(new Error('Complete at least one closed area before saving.'));
    }
    var name = (els.roomName.value || '').trim();
    if (!name) {
      els.roomName.focus();
      setFeedback('Enter a room name.', true);
      return Promise.reject(new Error('Enter a room name.'));
    }

    var isUpdate = !!state.editingRoomId;
    var url = isUpdate
      ? urlFromTemplate(root.getAttribute('data-room-update-template'), state.editingRoomId)
      : urlFromTemplate(root.getAttribute('data-room-store-template'), project.id);
    var method = isUpdate ? 'PUT' : 'POST';

    var scale = pixelsPerFoot();
    if (!scale) {
      var scaleErr = new Error('Calibrate scale before saving.');
      setFeedback(scaleErr.message, true);
      return Promise.reject(scaleErr);
    }

    els.saveRoom.disabled = true;
    return fetch(url, {
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
        canvas: serializeCanvas(),
        canvas_width: contentW(),
        canvas_height: contentH(),
        scale_pixels_per_foot: scale
      })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, status: res.status, data: data };
        }).catch(function () {
          return { ok: false, status: res.status, data: { message: 'Could not read server response.' } };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          throw new Error(saveErrorMessage(
            result.data,
            isUpdate ? 'Could not update room.' : 'Could not save room.'
          ));
        }
        var savedRoom = result.data.room;
        if (!isUpdate && state.draftBackground && state.draftBackground.file) {
          return uploadRoomBackgroundFile(savedRoom.id, state.draftBackground.file).then(function (bgResult) {
            savedRoom.background_url = bgResult.background_url;
            return { ok: true, data: { room: savedRoom } };
          });
        }
        return result;
      })
      .then(function (result) {
        els.saveRoom.disabled = false;
        if (isUpdate) {
          var idx = project.rooms.findIndex(function (r) { return r.id === result.data.room.id; });
          if (idx >= 0) {
            var oldRoom = project.rooms[idx];
            var newRoom = result.data.room;
            if (oldRoom && oldRoom.rulers) newRoom.rulers = oldRoom.rulers;
            project.rooms[idx] = newRoom;
          }
        } else {
          var newRoom = result.data.room;
          if (state.draftRulers && state.draftRulers.length) {
            newRoom.rulers = state.draftRulers.map(function (r) {
              return {
                id: r.id,
                p1: { x: r.p1.x, y: r.p1.y },
                p2: { x: r.p2.x, y: r.p2.y }
              };
            });
          }
          project.rooms.push(newRoom);
        }
        project.canvas_width = contentW();
        project.canvas_height = contentH();
        revokeDraftBackground();
        state.draftScale = null;
        var saved = result.data.room;
        if (saved.background_url) loadBackground(saved.background_url);
        reloadSavedRoomCanvas(saved);
        renderRooms();
        captureSavedSnapshot();
        setFeedback(
          isUpdate
            ? 'Changes saved.'
            : 'Room saved. Add more areas or click New room.',
          false
        );
      })
      .catch(function (e) {
        els.saveRoom.disabled = false;
        setFeedback(e.message, true);
        throw e;
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
            if (state.editingRoomId === roomId) {
              clearShape();
              loadBackground(null);
            }
            renderRooms();
            setFeedback('Room deleted.', false);
          }
        });
    });
  }

  function uploadRoomBackgroundFile(roomId, file) {
    var fd = new FormData();
    fd.append('background', file);

    return fetch(urlFromTemplate(root.getAttribute('data-room-background-upload-template'), roomId), {
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
        return result.data;
      });
  }

  function uploadBackground(file) {
    if (!file) return;
    state.bgLayout = null;

    if (state.editingRoomId) {
      var room = activeRoom();
      if (!room) return;

      uploadRoomBackgroundFile(room.id, file)
        .then(function (data) {
          room.background_url = data.background_url;
          loadBackground(room.background_url);
          captureSavedSnapshot();
          setFeedback('Floor plan uploaded for this room.', false);
        })
        .catch(function (e) { setFeedback(e.message, true); });
      return;
    }

    revokeDraftBackground();
    state.draftBackground = {
      file: file,
      url: URL.createObjectURL(file)
    };
    loadBackground(state.draftBackground.url);
    setFeedback('Floor plan ready for this new room. Save the room to keep it.', false);
  }

  function removeBackground() {
    if (state.editingRoomId) {
      var room = activeRoom();
      if (!room || !room.background_url) return;

      confirmAction(
        'Delete the floor plan image for this room? The room outline will stay.',
        { title: 'Delete floor plan', danger: true }
      ).then(function (ok) {
        if (!ok) return;

        fetch(urlFromTemplate(root.getAttribute('data-room-background-destroy-template'), room.id), {
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
            room.background_url = null;
            loadBackground(null);
            captureSavedSnapshot();
            setFeedback('Floor plan image deleted for this room.', false);
          });
      });
      return;
    }

    if (!state.draftBackground) return;

    confirmAction(
      'Remove the floor plan image for this new room?',
      { title: 'Delete floor plan', danger: true }
    ).then(function (ok) {
      if (!ok) return;
      revokeDraftBackground();
      loadBackground(null);
      setFeedback('Floor plan image removed.', false);
    });
  }

  function bindEvents() {
    els.projectSelect.addEventListener('change', function () {
      var newId = els.projectSelect.value ? parseInt(els.projectSelect.value, 10) : null;
      var previousId = state.activeProjectId;
      if (newId === previousId) return;

      els.projectSelect.value = previousId ? String(previousId) : '';

      promptUnsavedBeforeAction(function () {
        state.activeProjectId = newId;
        els.projectSelect.value = newId ? String(newId) : '';
        state.calibrating = false;
        state.calibPoints = [];
        els.calibrateToggle.classList.remove('is-active');
        els.knownLength.disabled = true;
        els.applyScale.disabled = true;
        stopRulerMode();
        clearRulerSelection();
        renderWorkspace();
      });
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

    els.undoVertex.addEventListener('click', undoVertex);
    els.closeShape.addEventListener('click', closeShape);
    if (els.addArea) els.addArea.addEventListener('click', addAreaSameGroup);
    if (els.addAreaNewGroup) els.addAreaNewGroup.addEventListener('click', addAreaNewGroup);
    if (els.deleteArea) els.deleteArea.addEventListener('click', deleteActivePolygon);
    els.clearShape.addEventListener('click', clearShape);
    els.saveRoom.addEventListener('click', function () {
      saveRoom().catch(function () {});
    });
    if (els.newRoom) els.newRoom.addEventListener('click', startNewRoom);
    if (els.newRoomSecondary) els.newRoomSecondary.addEventListener('click', startNewRoom);
    if (els.cancelEdit) els.cancelEdit.addEventListener('click', cancelEdit);

    if (els.wallApply) els.wallApply.addEventListener('click', applyWallLength);
    if (els.wallCancel) els.wallCancel.addEventListener('click', clearWallSelection);
    if (els.deleteWall) els.deleteWall.addEventListener('click', deleteSelectedWall);
    if (els.deleteVertex) els.deleteVertex.addEventListener('click', deleteSelectedVertex);
    if (els.vertexCancel) els.vertexCancel.addEventListener('click', clearVertexSelection);
    if (els.rulerToggle) els.rulerToggle.addEventListener('click', toggleRulerMode);
    if (els.deleteRuler) els.deleteRuler.addEventListener('click', deleteSelectedRuler);
    if (els.rulerCancel) els.rulerCancel.addEventListener('click', clearRulerSelection);
    bindAreaNameField();

    document.addEventListener('keydown', function (e) {
      if (e.target && e.target.closest('input, textarea, select')) return;

      if (e.key === 'Escape') {
        if (cancelRulerDraft()) {
          e.preventDefault();
          return;
        }
        if (cancelActiveDrawing()) {
          e.preventDefault();
          return;
        }
        return;
      }

      if (e.key === 'Backspace' && isDrawingOpenShape()) {
        if (!state.selectedVertexIndices.length && state.selectedWallIndex === null && state.selectedRulerId === null) {
          e.preventDefault();
          removeLastDrawVertex();
          return;
        }
      }

      if (e.key !== 'Delete' && e.key !== 'Backspace') return;
      if (state.selectedVertexIndices.length > 0) {
        e.preventDefault();
        deleteSelectedVertex();
      } else if (state.selectedWallIndex !== null) {
        e.preventDefault();
        deleteSelectedWall();
      } else if (state.selectedRulerId !== null && !state.rulerMode) {
        e.preventDefault();
        deleteSelectedRuler();
      }
    });

    if (els.wallLength) {
      els.wallLength.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyWallLength();
        }
        if (e.key === 'Escape') clearWallSelection();
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
    if (els.bgShowImage) {
      els.bgShowImage.addEventListener('change', function () {
        setBackgroundImageVisible(els.bgShowImage.checked);
      });
    }
    if (els.bgMoveToggle) els.bgMoveToggle.addEventListener('click', toggleBgMoveMode);
    els.copyTotal.addEventListener('click', function () {
      copyText(els.totalSqft.textContent);
    });

    if (els.zoomIn) els.zoomIn.addEventListener('click', zoomIn);
    if (els.zoomOut) els.zoomOut.addEventListener('click', zoomOut);
    if (els.zoom100) els.zoom100.addEventListener('click', resetViewport);
    if (els.zoomReset) els.zoomReset.addEventListener('click', fitViewport);

    if (els.measureUnit) {
      els.measureUnit.addEventListener('change', function () {
        setMeasureUnit(els.measureUnit.value);
      });
    }

    window.addEventListener('resize', scheduleStageResize);

    window.addEventListener('beforeunload', function (e) {
      if (!hasUnsavedChanges()) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  function init() {
    state.projects = parseJson('data-projects', []);
    state.measureUnit = loadMeasureUnit();
    if (els.measureUnit) els.measureUnit.value = state.measureUnit;
    updateMeasureUnitPlaceholders();
    bindEvents();
    bindViewportEvents();
    refreshProjectSelect();
    var raProject = (function () {
      try { return new URLSearchParams(window.location.search).get('ra_project'); } catch (e) { return null; }
    })();
    if (raProject) {
      var raId = parseInt(raProject, 10);
      if (state.projects.some(function (p) { return p.id === raId; })) {
        state.activeProjectId = raId;
        refreshProjectSelect();
      }
    }
    renderWorkspace();
  }

  init();
})();

/**
 * Zanini Room Area host for the openPlan3D iframe.
 * One save path (Save Plan), load-ack gate, and metrics scoped to the plan
 * (never the database room id) unless a real openPlan3D room is selected.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }
  if (typeof root !== 'undefined') {
    root.OpenPlan3DHostLib = api;
  }
  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function () {
      api.boot();
    });
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var pendingEditorMessages = [];
  var pendingEditorMessagesMax = 50;
  var deliverEditorMessage = null;

  if (typeof window !== 'undefined') {
    window.addEventListener('message', function (e) {
      if (deliverEditorMessage) {
        deliverEditorMessage(e);
      } else {
        pendingEditorMessages.push(e);
        while (pendingEditorMessages.length > pendingEditorMessagesMax) {
          pendingEditorMessages.shift();
        }
      }
    });
  }

  function collectPlanRooms(plan) {
    if (!plan || typeof plan !== 'object') return [];
    var rooms = [];
    function addFrom(obj) {
      if (obj && Array.isArray(obj.rooms)) {
        for (var i = 0; i < obj.rooms.length; i++) {
          if (obj.rooms[i]) rooms.push(obj.rooms[i]);
        }
      }
    }
    addFrom(plan);
    addFrom(plan.floor);
    if (Array.isArray(plan.floors)) {
      for (var f = 0; f < plan.floors.length; f++) addFrom(plan.floors[f]);
    }
    if (plan.project && Array.isArray(plan.project.floors)) {
      for (var p = 0; p < plan.project.floors.length; p++) addFrom(plan.project.floors[p]);
    }
    return rooms;
  }

  function resolveOpenPlan3DRoomId(plan, selectedOpenPlan3DRoomId) {
    var rooms = collectPlanRooms(plan);
    var candidate = selectedOpenPlan3DRoomId;
    if (candidate == null && plan && typeof plan === 'object') {
      candidate = plan.activeRoomId || plan.selectedRoomId || null;
      var floor = plan.floor || null;
      if (!floor && Array.isArray(plan.floors) && plan.floors.length) {
        var activeId = plan.activeFloorId;
        if (activeId) {
          for (var i = 0; i < plan.floors.length; i++) {
            if (plan.floors[i] && plan.floors[i].id === activeId) {
              floor = plan.floors[i];
              break;
            }
          }
        }
        if (!floor) floor = plan.floors[0];
      }
      if (candidate == null && floor) {
        candidate = floor.activeRoomId || floor.selectedRoomId || null;
      }
    }
    if (candidate == null || candidate === '') return null;
    var s = String(candidate);
    for (var r = 0; r < rooms.length; r++) {
      var room = rooms[r] || {};
      if (String(room.id) === s || String(room.name) === s) {
        return room.id || room.name;
      }
    }
    return null;
  }

  function computeMetrics(plan, finish, selectedOpenPlan3DRoomId, bridge) {
    bridge = bridge || (typeof window !== 'undefined' ? window.OpenPlan3DMeasurementBridge : null);
    if (!plan || !bridge || typeof bridge.layoutMetrics !== 'function') return null;
    var roomId = resolveOpenPlan3DRoomId(plan, selectedOpenPlan3DRoomId);
    return bridge.layoutMetrics(plan, roomId, finish || {});
  }

  function collectPlanWalls(plan) {
    var walls = [];
    function addFrom(obj) {
      if (obj && Array.isArray(obj.walls)) {
        for (var i = 0; i < obj.walls.length; i++) {
          if (obj.walls[i]) walls.push(obj.walls[i]);
        }
      }
    }
    if (!plan || typeof plan !== 'object') return walls;
    addFrom(plan);
    addFrom(plan.floor);
    if (Array.isArray(plan.floors)) {
      for (var f = 0; f < plan.floors.length; f++) addFrom(plan.floors[f]);
    }
    if (plan.project && Array.isArray(plan.project.floors)) {
      for (var p = 0; p < plan.project.floors.length; p++) addFrom(plan.project.floors[p]);
    }
    return walls;
  }

  function roundCoord(n) {
    if (typeof n !== 'number' || !isFinite(n)) return n == null ? null : n;
    return Math.round(n * 1e6) / 1e6;
  }

  function roundPoint(p) {
    if (!p || typeof p !== 'object') return null;
    var out = { x: roundCoord(p.x), y: roundCoord(p.y) };
    if (p.z != null) out.z = roundCoord(p.z);
    return out;
  }

  function snapshotWall(w) {
    if (!w) return null;
    return {
      id: w.id || null,
      start: roundPoint(w.start),
      end: roundPoint(w.end),
      thickness: roundCoord(w.thickness != null ? w.thickness : w.thickness_cm),
      height: roundCoord(w.height != null ? w.height : w.height_cm)
    };
  }

  function snapshotRoom(r) {
    if (!r) return null;
    var poly = Array.isArray(r.polygon) ? r.polygon : (Array.isArray(r.points) ? r.points : []);
    return {
      id: r.id || null,
      name: r.name || null,
      walls: Array.isArray(r.walls) ? r.walls.slice() : [],
      polygon: poly.map(roundPoint)
    };
  }

  function snapshotOpening(o) {
    if (!o) return null;
    var pos = o.position;
    if (pos && typeof pos === 'object') pos = roundPoint(pos);
    else pos = roundCoord(pos);
    return {
      id: o.id || null,
      wallId: o.wallId || o.wall_id || null,
      position: pos,
      offset: roundCoord(o.offset != null ? o.offset : null),
      width: roundCoord(o.width),
      height: roundCoord(o.height),
      start: roundPoint(o.start),
      end: roundPoint(o.end),
      x: roundCoord(o.x),
      y: roundCoord(o.y),
      swing: o.swing || o.openDirection || o.opening || null,
      hinge: o.hinge || o.hingeSide || null
    };
  }

  function snapshotFurniture(item) {
    if (!item) return null;
    var pos = item.position && typeof item.position === 'object'
      ? item.position
      : { x: item.x, y: item.y };
    return {
      id: item.id || null,
      type: item.type || item.kind || item.catalogId || item.itemType || null,
      material: item.material || item.materialId || item.finish || null,
      x: roundCoord(pos.x),
      y: roundCoord(pos.y),
      rotation: roundCoord(item.rotation),
      width: roundCoord(item.width),
      depth: roundCoord(item.depth),
      height: roundCoord(item.height)
    };
  }

  function snapshotStairs(item) {
    if (!item) return null;
    var pos = item.position && typeof item.position === 'object'
      ? item.position
      : { x: item.x, y: item.y };
    return {
      id: item.id || null,
      type: item.type || item.kind || null,
      material: item.material || item.materialId || null,
      x: roundCoord(pos.x),
      y: roundCoord(pos.y),
      rotation: roundCoord(item.rotation),
      width: roundCoord(item.width),
      run: roundCoord(item.run != null ? item.run : item.length),
      treads: item.treads != null ? item.treads : (item.treadCount != null ? item.treadCount : null)
    };
  }

  function snapshotLabel(item) {
    if (!item) return null;
    var pos = item.position && typeof item.position === 'object'
      ? item.position
      : { x: item.x, y: item.y };
    return {
      id: item.id || null,
      text: item.text || item.label || item.content || item.name || null,
      x: roundCoord(pos.x),
      y: roundCoord(pos.y)
    };
  }

  function collectLabels(floor) {
    var out = [];
    var keys = ['labels', 'annotations', 'textAnnotations', 'texts', 'measurements'];
    for (var k = 0; k < keys.length; k++) {
      var list = floor[keys[k]];
      if (!Array.isArray(list)) continue;
      for (var i = 0; i < list.length; i++) out.push(snapshotLabel(list[i]));
    }
    return out;
  }

  function floorSnapshot(floor) {
    if (!floor || typeof floor !== 'object') return null;
    var furniture = [];
    if (Array.isArray(floor.furniture)) {
      for (var f = 0; f < floor.furniture.length; f++) furniture.push(snapshotFurniture(floor.furniture[f]));
    }
    return {
      walls: (Array.isArray(floor.walls) ? floor.walls : []).map(snapshotWall),
      rooms: (Array.isArray(floor.rooms) ? floor.rooms : []).map(snapshotRoom),
      doors: (Array.isArray(floor.doors) ? floor.doors : []).map(snapshotOpening),
      windows: (Array.isArray(floor.windows) ? floor.windows : []).map(snapshotOpening),
      furniture: furniture,
      stairs: (Array.isArray(floor.stairs) ? floor.stairs : []).map(snapshotStairs),
      labels: collectLabels(floor)
    };
  }

  function editorExpectedOrigin(iframe, win) {
    if (!iframe) {
      var wOrigin = win && win.location ? win.location.origin : '';
      return (wOrigin && wOrigin !== 'null') ? wOrigin : '';
    }
    var src = (iframe.getAttribute && iframe.getAttribute('src')) || iframe.src || '';
    if (!src || src === 'about:blank') {
      var baseOrigin = win && win.location ? win.location.origin : '';
      return (baseOrigin && baseOrigin !== 'null') ? baseOrigin : '';
    }
    try {
      var parsed = new URL(src, win && win.location ? win.location.href : undefined);
      return (parsed.origin && parsed.origin !== 'null') ? parsed.origin : '';
    } catch (err) {
      var fallbackOrigin = win && win.location ? win.location.origin : '';
      return (fallbackOrigin && fallbackOrigin !== 'null') ? fallbackOrigin : '';
    }
  }

  function isTrustedEditorMessage(event, iframe, win) {
    if (!event || !iframe || !iframe.contentWindow) return false;
    if (event.source !== iframe.contentWindow) return false;
    if (!event.origin || event.origin === 'null') return false;
    var expected = editorExpectedOrigin(iframe, win);
    if (!expected || expected === 'null') return false;
    return event.origin === expected;
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
    return { floors: floors.map(floorSnapshot) };
  }

  function plansMatch(a, b) {
    if (!a || !b) return false;
    try {
      return JSON.stringify(planFingerprint(a)) === JSON.stringify(planFingerprint(b));
    } catch (e) {
      return false;
    }
  }

  function clonePlan(plan) {
    if (!plan || typeof plan !== 'object') return plan;
    try {
      return JSON.parse(JSON.stringify(plan));
    } catch (e) {
      return plan;
    }
  }

  function boot(options) {
    options = options || {};
    var doc = options.document || (typeof document !== 'undefined' ? document : null);
    var win = options.window || (typeof window !== 'undefined' ? window : null);
    if (!doc) return null;

    var rootEl = doc.querySelector('[data-room-area-openplan3d]');
    if (!rootEl) return null;

    var iframe = doc.getElementById('openplan3d-iframe');
    var projectSelect = doc.getElementById('openplan3d-project-select');
    var roomSelect = doc.getElementById('openplan3d-room-select');
    var roomNameInput = doc.getElementById('openplan3d-room-name');
    var saveBtn = doc.getElementById('openplan3d-save-btn');
    var newRoomBtn = doc.getElementById('openplan3d-new-room-btn');
    var settingsBtn = doc.getElementById('openplan3d-settings-btn');
    var settingsDrawer = doc.getElementById('openplan3d-settings-drawer');
    var settingsClose = doc.getElementById('openplan3d-settings-close');
    var dirtyBadge = doc.getElementById('openplan3d-dirty-badge');
    var noScaleWarning = doc.getElementById('openplan3d-noscale-warning');
    var feedbackEl = doc.getElementById('openplan3d-feedback');

    var wallHeightInput = doc.getElementById('ra-wall-height');
    var ceilingHeightInput = doc.getElementById('ra-ceiling-height');
    var wasteInput = doc.getElementById('ra-floor-waste');
    var boxInput = doc.getElementById('ra-floor-box');

    var metricArea = doc.getElementById('metric-area');
    var metricPerimeter = doc.getElementById('metric-perimeter');
    var metricBaseboard = doc.getElementById('metric-baseboard');
    var metricWallArea = doc.getElementById('metric-wall-area');
    var metricOpenings = doc.getElementById('metric-openings');
    var metricCasing = doc.getElementById('metric-casing');
    var metricBoxes = doc.getElementById('metric-boxes');

    var csrfToken = (doc.getElementById('openplan3d-csrf') || {}).value || '';
    var projects = [];
    try {
      projects = JSON.parse(rootEl.dataset.projects || '[]');
    } catch (err) {
      projects = [];
    }

    var initialRoomId = parseInt(rootEl.dataset.initialRoomId, 10) || null;
    var initialProjectId = parseInt(rootEl.dataset.initialProjectId, 10) || null;

    var roomStoreTemplate = rootEl.dataset.roomStoreTemplate || '';
    var roomUpdateTemplate = rootEl.dataset.roomUpdateTemplate || '';
    var projectStoreUrl = rootEl.dataset.projectStoreUrl || '';
    var projectUpdateTemplate = rootEl.dataset.projectUpdateTemplate || '';
    var quoteRoomTemplate = rootEl.dataset.quoteRoomTemplate || '';
    var boundClientProjectId = parseInt(rootEl.dataset.boundClientProject, 10) || null;
    var clientProjects = [];
    try {
      clientProjects = JSON.parse(rootEl.dataset.clientProjects || '[]');
    } catch (err2) {
      clientProjects = [];
    }

    var quoteBtn = doc.getElementById('openplan3d-quote-btn');
    var currentProject = null;
    var currentRoom = null;
    var currentPlan = null;
    var isDirty = false;
    var isSaving = false;
    var iframeReady = false;
    var loadAcked = false;
    var loadedPlanBaseline = null;
    var selectedOpenPlan3DRoomId = null;

    function setDirty(val) {
      isDirty = !!val;
      if (dirtyBadge) {
        dirtyBadge.hidden = !isDirty;
      }
    }

    function planHasUnit(plan) {
      if (!plan || typeof plan !== 'object') return false;
      if (plan.unit || plan.units) return true;
      var floor = plan.floor || (Array.isArray(plan.floors) && plan.floors[0]) || null;
      return !!(floor && (floor.unit || floor.units));
    }

    function hasUsableScale() {
      var roomScale = currentRoom && currentRoom.scale_pixels_per_foot;
      if (roomScale && Number(roomScale) > 0) return true;
      var projectScale = currentProject && currentProject.scale_pixels_per_foot;
      if (projectScale && Number(projectScale) > 0) return true;
      return planHasUnit(currentPlan);
    }

    function updateScaleWarning() {
      if (!noScaleWarning) return;
      var show = !!(currentRoom || currentPlan) && !hasUsableScale();
      noScaleWarning.hidden = !show;
    }

    var feedbackTimer = null;
    function showFeedback(msg, isError) {
      if (!feedbackEl) return;
      if (feedbackTimer) {
        var cancelTimer = (win && typeof win.clearTimeout === 'function') ? win.clearTimeout : clearTimeout;
        cancelTimer(feedbackTimer);
        feedbackTimer = null;
      }
      feedbackEl.textContent = msg;
      feedbackEl.className = 'openplan3d-feedback ' + (isError ? 'openplan3d-feedback--error' : 'openplan3d-feedback--success');
      feedbackEl.hidden = false;
      if (!isError) {
        var scheduleTimer = (win && typeof win.setTimeout === 'function') ? win.setTimeout : setTimeout;
        feedbackTimer = scheduleTimer(function () {
          feedbackEl.hidden = true;
          feedbackTimer = null;
        }, 5000);
      }
    }

    var modalEl = doc.getElementById('openplan3d-modal');
    var modalTitle = doc.getElementById('openplan3d-modal-title');
    var modalBody = doc.getElementById('openplan3d-modal-body');
    var modalInputWrap = doc.getElementById('openplan3d-modal-input-wrap');
    var modalInput = doc.getElementById('openplan3d-modal-input');
    var modalLink = doc.getElementById('openplan3d-modal-link');
    var modalActions = doc.getElementById('openplan3d-modal-actions');
    var modalCancel = doc.getElementById('openplan3d-modal-cancel');
    var modalConfirm = doc.getElementById('openplan3d-modal-confirm');
    var modalLinkBtn = doc.getElementById('openplan3d-modal-link-btn');
    var modalStandaloneBtn = doc.getElementById('openplan3d-modal-standalone-btn');
    var modalLinkCancel = doc.getElementById('openplan3d-modal-link-cancel');
    var modalClientSelect = doc.getElementById('openplan3d-modal-client-project');
    var modalResolve = null;
    var modalIsSaveFlow = false;
    var modalOpenerEl = null;

    function getModalFocusable() {
      if (!modalEl || modalEl.hidden) return [];
      var nodes = modalEl.querySelectorAll(
        'button:not([disabled]):not([hidden]), [href], input:not([disabled]):not([hidden]), select:not([disabled]):not([hidden]), textarea:not([disabled]):not([hidden]), [tabindex]:not([tabindex="-1"])'
      );
      var visible = [];
      for (var i = 0; i < nodes.length; i++) {
        var el = nodes[i];
        if (el.hidden) continue;
        if (el.closest && el.closest('[hidden]')) continue;
        visible.push(el);
      }
      return visible;
    }

    function handleModalKeyDown(e) {
      if (!modalEl || modalEl.hidden) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        finishModal(null);
        return;
      }
      var isTab = e.key === 'Tab' || e.key === 'BackTab' || e.keyCode === 9;
      if (isTab) {
        var focusable = getModalFocusable();
        if (focusable.length === 0) {
          e.preventDefault();
          e.stopPropagation();
          return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        var active = doc.activeElement;
        var idx = focusable.indexOf(active);
        var isShift = !!(e.shiftKey || e.key === 'BackTab');
        if (isShift) {
          if (idx <= 0) {
            e.preventDefault();
            e.stopPropagation();
            last.focus();
          }
        } else if (idx === focusable.length - 1 || idx < 0) {
          e.preventDefault();
          e.stopPropagation();
          first.focus();
        }
      }
    }

    function finishModal(result) {
      if (modalEl) modalEl.hidden = true;
      var resolve = modalResolve;
      modalResolve = null;
      var wasSaveFlow = modalIsSaveFlow;
      modalIsSaveFlow = false;
      if (wasSaveFlow && result == null && isSaving) {
        isSaving = false;
        if (saveBtn) {
          saveBtn.removeAttribute('aria-busy');
          saveBtn.disabled = !currentPlan || !loadAcked;
        }
      }
      var toFocus = modalOpenerEl || saveBtn;
      modalOpenerEl = null;
      if (toFocus && typeof toFocus.focus === 'function') {
        try { toFocus.focus(); } catch (e) {}
      }
      if (typeof resolve === 'function') resolve(result);
    }

    function fillClientProjectSelect() {
      if (!modalClientSelect) return;
      modalClientSelect.innerHTML = '';
      var empty = doc.createElement('option');
      empty.value = '';
      empty.textContent = '— Select a client project —';
      modalClientSelect.appendChild(empty);
      clientProjects.forEach(function (p) {
        var opt = doc.createElement('option');
        opt.value = String(p.id);
        opt.textContent = p.name || ('Project #' + p.id);
        modalClientSelect.appendChild(opt);
      });
    }

    function showModal(opts) {
      opts = opts || {};
      return new Promise(function (resolve) {
        if (!modalEl) {
          resolve(opts.fallback != null ? opts.fallback : null);
          return;
        }
        if (modalResolve) {
          resolve(null);
          return;
        }
        modalResolve = resolve;
        modalIsSaveFlow = !!opts.isSaveFlow;
        modalOpenerEl = opts.opener || (doc ? doc.activeElement : null) || saveBtn;
        if (modalTitle) modalTitle.textContent = opts.title || 'Confirm';
        if (modalBody) {
          modalBody.textContent = opts.body || '';
          modalBody.hidden = !opts.body;
        }
        var mode = opts.mode || 'confirm';
        if (modalInputWrap) modalInputWrap.hidden = mode !== 'prompt';
        if (modalLink) modalLink.hidden = mode !== 'save-destination';
        if (modalActions) modalActions.hidden = mode === 'save-destination';
        if (modalCancel) modalCancel.hidden = !!opts.hideCancel;
        if (modalConfirm) modalConfirm.textContent = opts.confirmLabel || (mode === 'prompt' ? 'Continue' : 'OK');
        if (mode === 'save-destination') fillClientProjectSelect();
        modalEl.hidden = false;
        setTimeout(function () {
          if (mode === 'prompt' && modalInput) {
            modalInput.value = opts.defaultValue || '';
            modalInput.focus();
            modalInput.select();
            return;
          }
          if (mode === 'save-destination' && modalLinkBtn) {
            modalLinkBtn.focus();
            return;
          }
          if (modalConfirm) modalConfirm.focus();
        }, 0);
      });
    }

    function askConfirm(message, title) {
      return showModal({
        mode: 'confirm',
        title: title || 'Unsaved changes',
        body: message,
        confirmLabel: 'Discard'
      }).then(function (result) { return result === true; });
    }

    function askPrompt(title, defaultValue) {
      return showModal({
        mode: 'prompt',
        title: title,
        body: '',
        defaultValue: defaultValue || '',
        confirmLabel: 'Continue'
      }).then(function (result) {
        if (result == null) return null;
        var value = String(result).trim();
        return value || null;
      });
    }

    function askAlert(message) {
      return showModal({
        mode: 'alert',
        title: 'Room Area',
        body: message,
        confirmLabel: 'OK',
        hideCancel: true
      }).then(function () { return true; });
    }

    function jsonPost(url, method, payload) {
      var controller = (win && typeof win.AbortController !== 'undefined')
        ? new win.AbortController()
        : (typeof AbortController !== 'undefined' ? new AbortController() : null);
      var timeoutId = null;
      if (controller) {
        var scheduleTimer = (win && typeof win.setTimeout === 'function') ? win.setTimeout : setTimeout;
        timeoutId = scheduleTimer(function () {
          try { controller.abort(); } catch (e) {}
        }, 30000);
      }

      return fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload),
        signal: controller ? controller.signal : undefined
      }).then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, ok: res.ok, data: data };
        });
      }).finally(function () {
        if (timeoutId) {
          var cancelTimer = (win && typeof win.clearTimeout === 'function') ? win.clearTimeout : clearTimeout;
          cancelTimer(timeoutId);
        }
      });
    }

    function getFinishSettings() {
      return {
        wall_height_ft: parseFloat(wallHeightInput ? wallHeightInput.value : 8) || 8,
        ceiling_height_ft: parseFloat(ceilingHeightInput ? ceilingHeightInput.value : 8) || 8,
        flooring_waste_percent: parseFloat(wasteInput ? wasteInput.value : 10) || 10,
        flooring_box_sqft: parseFloat(boxInput ? boxInput.value : 20) || 20
      };
    }

    function currentMetrics(plan) {
      return computeMetrics(
        plan,
        getFinishSettings(),
        selectedOpenPlan3DRoomId,
        win && win.OpenPlan3DMeasurementBridge
      );
    }

    function updateMetrics(plan) {
      if (!metricArea) return;
      if (!plan || !win || !win.OpenPlan3DMeasurementBridge) {
        metricArea.textContent = '0.00';
        if (metricPerimeter) metricPerimeter.textContent = '0.00';
        if (metricBaseboard) metricBaseboard.textContent = '0.00';
        if (metricWallArea) metricWallArea.textContent = '0.00';
        if (metricOpenings) metricOpenings.textContent = '0 doors · 0 windows';
        if (metricCasing) metricCasing.textContent = '0.00';
        if (metricBoxes) metricBoxes.textContent = '0';
        return;
      }

      try {
        var metrics = currentMetrics(plan) || {};
        metricArea.textContent = (metrics.area_sqft || 0).toFixed(2);
        if (metricPerimeter) {
          metricPerimeter.textContent = (metrics.perimeter_lf != null ? metrics.perimeter_lf : (metrics.perimeter_ft || 0)).toFixed(2);
        }
        if (metricBaseboard) {
          metricBaseboard.textContent = (metrics.baseboard_lf != null ? metrics.baseboard_lf : (metrics.baseboard_ft || 0)).toFixed(2);
        }
        if (metricWallArea) {
          metricWallArea.textContent = (metrics.net_wall_area_sqft || metrics.net_wall_sqft || metrics.wall_area_sqft || 0).toFixed(2);
        }
        if (metricOpenings) {
          metricOpenings.textContent = (metrics.door_count || 0) + ' doors · ' + (metrics.window_count || 0) + ' windows';
        }
        if (metricCasing) {
          metricCasing.textContent = (metrics.casing_lf != null ? metrics.casing_lf : (metrics.casing_ft || 0)).toFixed(2);
        }
        if (metricBoxes) {
          metricBoxes.textContent = String(metrics.flooring_boxes != null ? metrics.flooring_boxes : (metrics.boxes || 0));
        }
      } catch (err) {
        console.warn('[openPlan3D] Metrics computation failed:', err);
      }
    }

    function postToEditor(payload) {
      if (iframe && iframe.contentWindow) {
        var targetOrigin = editorExpectedOrigin(iframe, win);
        if (!targetOrigin || targetOrigin === '*' || targetOrigin === 'null') {
          console.error('[openPlan3D] Refusing postMessage: invalid or missing target origin', targetOrigin);
          return;
        }
        iframe.contentWindow.postMessage(payload, targetOrigin);
      }
    }

    function requestEditorLoad(plan) {
      loadAcked = false;
      postToEditor({
        type: 'OPENPLAN3D_LOAD_PLAN',
        plan: plan
      });
    }

    function populateProjects() {
      if (!projectSelect) return;
      projectSelect.innerHTML = '';
      var defaultOpt = doc.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = '— Select Project —';
      projectSelect.appendChild(defaultOpt);

      projects.forEach(function (proj) {
        var opt = doc.createElement('option');
        opt.value = proj.id;
        opt.textContent = proj.name + (proj.client_project_name ? ' (' + proj.client_project_name + ')' : '');
        projectSelect.appendChild(opt);
      });

      var newOpt = doc.createElement('option');
      newOpt.value = '__new__';
      newOpt.textContent = '+ Create New Project...';
      projectSelect.appendChild(newOpt);
    }

    function populateRooms() {
      if (!roomSelect) return;
      roomSelect.innerHTML = '';
      var defaultOpt = doc.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = '— Select Room —';
      roomSelect.appendChild(defaultOpt);

      if (!currentProject || !currentProject.rooms) {
        return;
      }

      currentProject.rooms.forEach(function (room) {
        var opt = doc.createElement('option');
        opt.value = room.id;
        opt.textContent = room.name + (room.area_sqft ? ' (' + parseFloat(room.area_sqft).toFixed(1) + ' sf)' : '');
        roomSelect.appendChild(opt);
      });

      var newOpt = doc.createElement('option');
      newOpt.value = '__new__';
      newOpt.textContent = '+ Create New Room...';
      roomSelect.appendChild(newOpt);
    }

    function selectProject(projectId) {
      var found = null;
      for (var i = 0; i < projects.length; i++) {
        if (projects[i].id === projectId) {
          found = projects[i];
          break;
        }
      }
      currentProject = found;
      if (projectSelect) projectSelect.value = currentProject ? String(currentProject.id) : '';
      populateRooms();
    }

    function selectRoom(room) {
      currentRoom = room;
      selectedOpenPlan3DRoomId = null;
      if (currentRoom) {
        if (roomSelect) roomSelect.value = currentRoom.id ? String(currentRoom.id) : '__new__';
        if (roomNameInput) roomNameInput.value = currentRoom.name || '';
        if (wallHeightInput) wallHeightInput.value = currentRoom.wall_height_ft || 8;
        if (ceilingHeightInput) ceilingHeightInput.value = currentRoom.ceiling_height_ft || 8;
        if (wasteInput) wasteInput.value = currentRoom.flooring_waste_percent || 10;
        if (boxInput) boxInput.value = currentRoom.flooring_box_sqft || 20;

        currentPlan = currentRoom.plan || null;
        updateMetrics(currentPlan);
        updateScaleWarning();

        if (iframeReady) {
          requestEditorLoad(currentPlan);
        }
        if (quoteBtn) {
          if (currentRoom.id) {
            quoteBtn.href = quoteRoomTemplate.replace('__ID__', currentRoom.id);
            quoteBtn.hidden = false;
          } else {
            quoteBtn.hidden = true;
          }
        }
        setDirty(false);
        if (saveBtn) saveBtn.disabled = !currentPlan;
      } else {
        if (roomSelect) roomSelect.value = '';
        if (roomNameInput) roomNameInput.value = '';
        currentPlan = null;
        updateMetrics(null);
        updateScaleWarning();
        if (quoteBtn) quoteBtn.hidden = true;
        setDirty(false);
        if (saveBtn) saveBtn.disabled = true;
      }
    }

    function startBlankSession() {
      currentProject = null;
      if (projectSelect) projectSelect.value = '';
      populateRooms();
      applyNewRoom('Room 1');
    }

    function initializeSelection() {
      populateProjects();

      var targetProject = null;
      var targetRoom = null;

      if (initialRoomId) {
        for (var p = 0; p < projects.length; p++) {
          var pr = projects[p];
          if (pr.rooms) {
            for (var r = 0; r < pr.rooms.length; r++) {
              if (pr.rooms[r].id === initialRoomId) {
                targetProject = pr;
                targetRoom = pr.rooms[r];
                break;
              }
            }
          }
          if (targetRoom) break;
        }
      }

      if (!targetProject && initialProjectId) {
        for (var pi = 0; pi < projects.length; pi++) {
          if (projects[pi].id === initialProjectId) {
            targetProject = projects[pi];
            break;
          }
        }
      }

      if (!targetProject && boundClientProjectId && projects.length > 0) {
        targetProject = projects[0];
      }

      if (targetProject) {
        selectProject(targetProject.id);
        if (targetRoom) {
          selectRoom(targetRoom);
        } else if (targetProject.rooms && targetProject.rooms.length > 0) {
          selectRoom(targetProject.rooms[0]);
        } else {
          applyNewRoom('Room 1');
        }
        return;
      }

      startBlankSession();
    }

    function applyNewRoom(name) {
      var newRoom = {
        id: null,
        name: name,
        plan: { unit: 'cm', floor: { walls: [], rooms: [] } },
        wall_height_ft: 8,
        ceiling_height_ft: 8,
        flooring_waste_percent: 10,
        flooring_box_sqft: 20
      };
      selectRoom(newRoom);
      if (saveBtn) saveBtn.disabled = false;
      setDirty(false);
    }

    function createNewRoom() {
      var roomNumber = ((currentProject && currentProject.rooms) ? currentProject.rooms.length : 0) + 1;
      askPrompt('Room name', 'Room ' + roomNumber).then(function (name) {
        if (!name) return;
        applyNewRoom(name);
      });
    }

    function rememberProject(project) {
      if (!project) return;
      var replaced = false;
      for (var i = 0; i < projects.length; i++) {
        if (projects[i].id === project.id) {
          projects[i] = project;
          replaced = true;
          break;
        }
      }
      if (!replaced) projects.push(project);
      currentProject = project;
      populateProjects();
      selectProject(project.id);
    }

    function createNewProject() {
      askPrompt('Project name', '').then(function (name) {
        if (!name) {
          if (projectSelect) projectSelect.value = currentProject ? String(currentProject.id) : '';
          return;
        }
        return jsonPost(projectStoreUrl, 'POST', { name: name, _token: csrfToken }).then(function (result) {
          if (result.ok && result.data && result.data.ok && result.data.project) {
            rememberProject(result.data.project);
            applyNewRoom('Room 1');
            showFeedback('Project created successfully!');
          } else {
            showFeedback((result.data && result.data.message) || 'Failed to create project.', true);
            if (projectSelect) projectSelect.value = currentProject ? String(currentProject.id) : '';
          }
        });
      }).catch(function () {
        showFeedback('Server error creating project.', true);
      });
    }

    function needsDestinationChoice() {
      if (boundClientProjectId) return false;
      if (currentProject && currentProject.client_project_id) return false;
      return true;
    }

    function ensureProjectForSave(choice) {
      var name = ((roomNameInput && roomNameInput.value) || 'Room plans').trim() || 'Room plans';
      if (choice && choice.action === 'link') {
        var clientId = parseInt(choice.clientProjectId, 10);
        if (!clientId) {
          showFeedback('Select a client project to link.', true);
          return Promise.resolve(false);
        }
        if (currentProject && currentProject.id) {
          var updateUrl = projectUpdateTemplate.replace('__ID__', currentProject.id);
          return jsonPost(updateUrl, 'PUT', { client_project_id: clientId, _token: csrfToken }).then(function (result) {
            if (result.ok && result.data && result.data.project) {
              rememberProject(result.data.project);
              return true;
            }
            showFeedback((result.data && result.data.message) || 'Could not link to that project.', true);
            return false;
          });
        }
        return jsonPost(projectStoreUrl, 'POST', {
          name: name,
          client_project_id: clientId,
          _token: csrfToken
        }).then(function (result) {
          if (result.ok && result.data && result.data.project) {
            rememberProject(result.data.project);
            return true;
          }
          showFeedback((result.data && result.data.message) || 'Could not link to that project.', true);
          return false;
        });
      }
      if (currentProject && currentProject.id) return Promise.resolve(true);
      return jsonPost(projectStoreUrl, 'POST', { name: name, _token: csrfToken }).then(function (result) {
        if (result.ok && result.data && result.data.project) {
          rememberProject(result.data.project);
          return true;
        }
        showFeedback((result.data && result.data.message) || 'Could not create a standalone project.', true);
        return false;
      });
    }

    function storedAreaSqft() {
      if (!currentRoom || currentRoom.area_sqft == null) return 0;
      var n = Number(currentRoom.area_sqft);
      return isFinite(n) ? n : 0;
    }

    function submitSave(confirmZero) {
      if (!currentProject) {
        showFeedback('No active project selected.', true);
        isSaving = false;
        if (saveBtn) {
          saveBtn.removeAttribute('aria-busy');
          saveBtn.disabled = !currentPlan || !loadAcked;
          try { saveBtn.focus(); } catch (e) {}
        }
        return;
      }

      var roomName = ((roomNameInput && roomNameInput.value) || '').trim();
      if (!roomName) {
        showFeedback('Please enter a room name.', true);
        isSaving = false;
        if (saveBtn) {
          saveBtn.removeAttribute('aria-busy');
          saveBtn.disabled = !currentPlan || !loadAcked;
        }
        if (roomNameInput) roomNameInput.focus();
        return;
      }

      if (!currentPlan) {
        showFeedback('No floor plan data available to save.', true);
        isSaving = false;
        if (saveBtn) {
          saveBtn.removeAttribute('aria-busy');
          saveBtn.disabled = !currentPlan || !loadAcked;
          try { saveBtn.focus(); } catch (e) {}
        }
        return;
      }

      isSaving = true;
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.setAttribute('aria-busy', 'true');
      }
      var saveSpinner = saveBtn ? saveBtn.querySelector('.openplan3d-save-spinner') : null;
      var saveText = saveBtn ? saveBtn.querySelector('.openplan3d-save-text') : null;
      if (saveSpinner) saveSpinner.hidden = false;
      if (saveText) saveText.hidden = true;

      var isUpdate = currentRoom && currentRoom.id;
      var url = isUpdate
        ? roomUpdateTemplate.replace('__ID__', currentRoom.id)
        : roomStoreTemplate.replace('__ID__', currentProject.id);

      var finish = getFinishSettings();
      var submitOnce = 'openplan3d_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

      var sentPlan = clonePlan(currentPlan);
      var payload = {
        name: roomName,
        plan: sentPlan,
        wall_height_ft: finish.wall_height_ft,
        ceiling_height_ft: finish.ceiling_height_ft,
        flooring_waste_percent: finish.flooring_waste_percent,
        flooring_box_sqft: finish.flooring_box_sqft,
        confirm_zero_area: !!confirmZero,
        _submit_once: submitOnce,
        _token: csrfToken
      };

      if (win) win.__lastSavedPayload = payload;

      var controller = (win && typeof win.AbortController !== 'undefined')
        ? new win.AbortController()
        : (typeof AbortController !== 'undefined' ? new AbortController() : null);
      var timeoutId = null;
      if (controller) {
        var scheduleTimer = (win && typeof win.setTimeout === 'function') ? win.setTimeout : setTimeout;
        timeoutId = scheduleTimer(function () {
          try { controller.abort(); } catch (e) {}
        }, 30000);
      }

      fetch(url, {
        method: isUpdate ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload),
        signal: controller ? controller.signal : undefined
      })
      .then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (result.ok && result.data && result.data.ok) {
          var updatedRoom = result.data.room;
          var updatedProject = result.data.project;

          currentRoom = updatedRoom;
          if (updatedProject) {
            for (var i = 0; i < projects.length; i++) {
              if (projects[i].id === updatedProject.id) {
                projects[i] = updatedProject;
                currentProject = updatedProject;
                break;
              }
            }
          }

          populateRooms();
          if (roomSelect && currentRoom) roomSelect.value = String(currentRoom.id);
          if (roomNameInput && currentRoom) roomNameInput.value = currentRoom.name;
          if (quoteBtn && currentRoom && currentRoom.id) {
            quoteBtn.href = quoteRoomTemplate.replace('__ID__', currentRoom.id);
            quoteBtn.hidden = false;
          }

          loadedPlanBaseline = sentPlan;
          setDirty(!plansMatch(currentPlan, sentPlan));
          showFeedback('Plan saved successfully!');
        } else {
          var errMsg = (result.data && (result.data.message || (result.data.errors ? Object.values(result.data.errors).flat().join(' ') : null))) || 'Save failed.';
          showFeedback(errMsg, true);
        }
      })
      .catch(function (err) {
        if (err && (err.name === 'AbortError' || err.code === 20)) {
          showFeedback('Save request timed out. Please try again.', true);
        } else {
          showFeedback((err && err.message) || 'An unexpected error occurred while saving the plan.', true);
        }
      })
      .finally(function () {
        if (timeoutId) {
          var cancelTimer = (win && typeof win.clearTimeout === 'function') ? win.clearTimeout : clearTimeout;
          cancelTimer(timeoutId);
        }
        isSaving = false;
        if (saveBtn) {
          saveBtn.removeAttribute('aria-busy');
          saveBtn.disabled = !currentPlan || !loadAcked;
        }
        if (saveSpinner) saveSpinner.hidden = true;
        if (saveText) saveText.hidden = false;
      });
    }

    function savePlan() {
      if (isSaving) return;
      var roomName = ((roomNameInput && roomNameInput.value) || '').trim();
      if (!roomName) {
        showFeedback('Please enter a room name.', true);
        if (roomNameInput) roomNameInput.focus();
        return;
      }
      if (!currentPlan) {
        showFeedback('No floor plan data available to save.', true);
        return;
      }

      isSaving = true;
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.setAttribute('aria-busy', 'true');
      }

      var unlockSave = function () {
        isSaving = false;
        if (saveBtn) {
          saveBtn.removeAttribute('aria-busy');
          saveBtn.disabled = !currentPlan || !loadAcked;
          try { saveBtn.focus(); } catch (e) {}
        }
      };

      var metrics = currentMetrics(currentPlan);
      var storedArea = storedAreaSqft();
      var incomingArea = metrics && metrics.area_sqft != null ? Number(metrics.area_sqft) : 0;
      var confirmZero = false;

      // Ask the zero-area replace question BEFORE linking or creating a project
      var zeroAreaCheck = Promise.resolve(true);
      if (storedArea > 0 && (!isFinite(incomingArea) || incomingArea <= 0)) {
        zeroAreaCheck = showModal({
          mode: 'confirm',
          title: 'Replace stored area?',
          body: 'This save would replace the stored room area (' + storedArea.toFixed(2) + ' sq ft) with 0. Continue?',
          confirmLabel: 'Replace',
          isSaveFlow: true,
          opener: saveBtn
        }).then(function (ok) {
          if (ok !== true) {
            showFeedback('Save cancelled. The stored room area was kept.', true);
            return false;
          }
          confirmZero = true;
          return true;
        });
      }

      zeroAreaCheck.then(function (proceed) {
        if (!proceed) {
          unlockSave();
          return null;
        }

        var dest = Promise.resolve({ action: 'keep' });
        if (needsDestinationChoice()) {
          dest = showModal({
            mode: 'save-destination',
            title: 'Save plan',
            body: '',
            isSaveFlow: true,
            opener: saveBtn
          });
        }

        return dest.then(function (choice) {
          if (needsDestinationChoice() && !choice) {
            unlockSave();
            return null;
          }

          var projectPromise = needsDestinationChoice()
            ? ensureProjectForSave(choice)
            : Promise.resolve(true);

          return projectPromise.then(function (ok) {
            if (!ok) {
              unlockSave();
              return null;
            }
            return submitSave(confirmZero);
          });
        });
      }).catch(function (err) {
        unlockSave();
        showFeedback((err && err.message) || 'An unexpected error occurred while saving the plan.', true);
      });
    }

    if (modalCancel) {
      modalCancel.addEventListener('click', function () { finishModal(null); });
    }
    if (modalConfirm) {
      modalConfirm.addEventListener('click', function () {
        if (modalInputWrap && !modalInputWrap.hidden) {
          finishModal(modalInput ? modalInput.value : '');
          return;
        }
        finishModal(true);
      });
    }
    if (modalStandaloneBtn) {
      modalStandaloneBtn.addEventListener('click', function () {
        finishModal({ action: 'standalone' });
      });
    }
    if (modalLinkCancel) {
      modalLinkCancel.addEventListener('click', function () { finishModal(null); });
    }
    if (modalLinkBtn) {
      modalLinkBtn.addEventListener('click', function () {
        var clientProjectId = modalClientSelect ? String(modalClientSelect.value || '').trim() : '';
        if (!clientProjectId) {
          if (modalClientSelect) modalClientSelect.focus();
          return;
        }
        finishModal({
          action: 'link',
          clientProjectId: clientProjectId
        });
      });
    }
    if (doc) {
      doc.addEventListener('keydown', handleModalKeyDown, true);
    }
    if (modalEl) {
      var backdrop = modalEl.querySelector('[data-openplan3d-modal-dismiss]');
      if (backdrop) backdrop.addEventListener('click', function () { finishModal(null); });
    }
    if (modalInput) {
      modalInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          finishModal(modalInput.value);
        }
      });
    }

    if (projectSelect) {
      projectSelect.addEventListener('change', function () {
        var val = projectSelect.value;
        if (val === '__new__') {
          createNewProject();
          return;
        }
        var proceed = function () {
          if (!val) {
            startBlankSession();
            return;
          }
          selectProject(parseInt(val, 10));
          if (currentProject && currentProject.rooms && currentProject.rooms.length > 0) {
            selectRoom(currentProject.rooms[0]);
          } else {
            applyNewRoom('Room 1');
          }
        };
        if (!isDirty) {
          proceed();
          return;
        }
        askConfirm('You have unsaved changes. Discard changes and switch project?').then(function (ok) {
          if (!ok) {
            projectSelect.value = currentProject ? String(currentProject.id) : '';
            return;
          }
          proceed();
        });
      });
    }

    if (roomSelect) {
      roomSelect.addEventListener('change', function () {
        var val = roomSelect.value;
        if (val === '__new__') {
          createNewRoom();
          return;
        }
        var applyRoom = function () {
          var roomId = parseInt(val, 10);
          var found = null;
          if (currentProject && currentProject.rooms) {
            for (var i = 0; i < currentProject.rooms.length; i++) {
              if (currentProject.rooms[i].id === roomId) {
                found = currentProject.rooms[i];
                break;
              }
            }
          }
          selectRoom(found);
        };
        if (!isDirty) {
          applyRoom();
          return;
        }
        askConfirm('You have unsaved changes. Discard changes and switch room?').then(function (ok) {
          if (!ok) {
            roomSelect.value = currentRoom && currentRoom.id ? String(currentRoom.id) : '';
            return;
          }
          applyRoom();
        });
      });
    }

    if (roomNameInput) {
      roomNameInput.addEventListener('input', function () {
        setDirty(true);
      });
    }

    if (newRoomBtn) {
      newRoomBtn.addEventListener('click', function () {
        if (!isDirty) {
          createNewRoom();
          return;
        }
        askConfirm('You have unsaved changes. Discard changes and create new room?').then(function (ok) {
          if (ok) createNewRoom();
        });
      });
    }

    if (saveBtn) {
      saveBtn.addEventListener('click', savePlan);
    }

    if (settingsBtn && settingsDrawer) {
      settingsBtn.addEventListener('click', function () {
        var isExpanded = settingsBtn.getAttribute('aria-expanded') === 'true';
        settingsBtn.setAttribute('aria-expanded', String(!isExpanded));
        settingsDrawer.hidden = isExpanded;
      });
    }

    if (settingsClose && settingsBtn && settingsDrawer) {
      settingsClose.addEventListener('click', function () {
        settingsBtn.setAttribute('aria-expanded', 'false');
        settingsDrawer.hidden = true;
      });
    }

    [wallHeightInput, ceilingHeightInput, wasteInput, boxInput].forEach(function (el) {
      if (!el) return;
      el.addEventListener('input', function () {
        setDirty(true);
        if (currentPlan) {
          updateMetrics(currentPlan);
        }
      });
    });

    if (win) {

      win.addEventListener('beforeunload', function (e) {
        if (isDirty) {
          e.preventDefault();
          e.returnValue = '';
        }
      });

      function onEditorMessage(e) {
        if (!isTrustedEditorMessage(e, iframe, win)) return;
        if (!e.data || typeof e.data !== 'object') return;

        if (e.data.type === 'OPENPLAN3D_READY') {
          iframeReady = true;
          var isDark = doc.documentElement.classList.contains('admin-dark');
          postToEditor({
            type: 'OPENPLAN3D_SET_THEME',
            theme: isDark ? 'dark' : 'light'
          });
          if (currentPlan) {
            requestEditorLoad(currentPlan);
          } else {
            requestEditorLoad({ unit: 'cm', floor: { walls: [], rooms: [] } });
          }
        } else if (e.data.type === 'OPENPLAN3D_LOAD_ACK') {
          if (e.data.ok === false) {
            loadAcked = false;
            if (saveBtn) saveBtn.disabled = true;
            setDirty(false);
            showFeedback('The editor did not confirm the loaded room. Do not Save Plan until the geometry looks correct.', true);
            return;
          }
          loadAcked = true;
          if (e.data.plan) {
            currentPlan = e.data.plan;
          }
          loadedPlanBaseline = currentPlan;
          updateMetrics(currentPlan);
          updateScaleWarning();
          setDirty(false);
          if (saveBtn) saveBtn.disabled = !currentPlan || isSaving;
        } else if (e.data.type === 'OPENPLAN3D_BRIDGE_ERROR') {
          showFeedback('openPlan3D editor API is unavailable. Room geometry cannot be loaded. Refresh the page.', true);
        } else if (e.data.type === 'OPENPLAN3D_PLAN_CHANGED') {
          if (!loadAcked) return;
          currentPlan = e.data.plan;
          var planChanged = !plansMatch(currentPlan, loadedPlanBaseline);
          setDirty(planChanged);
          if (saveBtn) saveBtn.disabled = !currentPlan || isSaving;
          updateMetrics(currentPlan);
          updateScaleWarning();
        } else if (e.data.type === 'OPENPLAN3D_SELECT_ROOM') {
          var nextId = resolveOpenPlan3DRoomId(currentPlan, e.data.roomId);
          selectedOpenPlan3DRoomId = nextId;
          if (currentPlan) updateMetrics(currentPlan);
        } else if (e.data.type === 'OPENPLAN3D_TRIGGER_SAVE') {
          if (saveBtn && !saveBtn.disabled && !isSaving) {
            savePlan();
          }
        }
      }

      deliverEditorMessage = onEditorMessage;
      for (var pi = 0; pi < pendingEditorMessages.length; pi++) {
        onEditorMessage(pendingEditorMessages[pi]);
      }
      pendingEditorMessages.length = 0;
    }

    var themeToggleBtn = doc.getElementById('openplan3d-theme-toggle');
    if (themeToggleBtn) {
      function syncThemeButton() {
        var isDark = doc.documentElement.classList.contains('admin-dark');
        var moon = themeToggleBtn.querySelector('[data-theme-icon="moon"]');
        var sun = themeToggleBtn.querySelector('[data-theme-icon="sun"]');
        if (moon) moon.hidden = isDark;
        if (sun) sun.hidden = !isDark;
        themeToggleBtn.setAttribute('title', isDark ? 'Switch to light theme' : 'Switch to dark theme');
        themeToggleBtn.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
      }

      syncThemeButton();

      themeToggleBtn.addEventListener('click', function () {
        var wasDark = doc.documentElement.classList.contains('admin-dark');
        var nowDark = !wasDark;
        doc.documentElement.classList.toggle('admin-dark', nowDark);
        if (doc.body) {
          doc.body.classList.toggle('admin-dark', nowDark);
        }
        try {
          localStorage.setItem('zanini-admin-theme', nowDark ? 'dark' : 'light');
        } catch (e) {}
        postToEditor({
          type: 'OPENPLAN3D_SET_THEME',
          theme: nowDark ? 'dark' : 'light'
        });
        syncThemeButton();
        doc.dispatchEvent(new CustomEvent('admin-theme-change', { detail: { dark: nowDark } }));
      });

      doc.addEventListener('admin-theme-change', syncThemeButton);
    }

    initializeSelection();

    var hostApi = {
      resolveOpenPlan3DRoomId: function (plan, selected) {
        return resolveOpenPlan3DRoomId(plan, selected != null ? selected : selectedOpenPlan3DRoomId);
      },
      computeMetrics: currentMetrics,
      savePlan: savePlan,
      selectRoom: selectRoom,
      askConfirm: askConfirm,
      askPrompt: askPrompt,
      showModal: showModal,
      finishModal: finishModal,
      jsonPost: jsonPost,
      getState: function () {
        return {
          isDirty: isDirty,
          isSaving: isSaving,
          loadAcked: loadAcked,
          iframeReady: iframeReady,
          currentPlan: currentPlan,
          currentRoom: currentRoom,
          currentProject: currentProject,
          loadedPlanBaseline: loadedPlanBaseline,
          selectedOpenPlan3DRoomId: selectedOpenPlan3DRoomId,
          hasUsableScale: hasUsableScale()
        };
      }
    };
    if (win) win.OpenPlan3DHost = hostApi;
    return hostApi;
  }

  return {
    collectPlanRooms: collectPlanRooms,
    collectPlanWalls: collectPlanWalls,
    resolveOpenPlan3DRoomId: resolveOpenPlan3DRoomId,
    computeMetrics: computeMetrics,
    plansMatch: plansMatch,
    planFingerprint: planFingerprint,
    clonePlan: clonePlan,
    editorExpectedOrigin: editorExpectedOrigin,
    isTrustedEditorMessage: isTrustedEditorMessage,
    boot: boot
  };
}));

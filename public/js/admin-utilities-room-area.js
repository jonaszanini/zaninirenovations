/* Room Area tool — Konva polygon drawing with scale calibration */
(function () {
  'use strict';

  if (typeof Konva === 'undefined') return;

  var root = document.querySelector('[data-room-area]');
  if (!root) return;

  var INCHES_PER_FOOT = 12;
  var SQFT_TO_SQM = 0.092903;
  var CANVAS_W = 900;
  var CANVAS_H = 560;
  var GRID_PX = 20;
  var VERTEX_HIT = 14;
  var CLOSE_HIT = 16;

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
    snapGroup: root.querySelector('[data-ra-snap-group]'),
    undoVertex: root.querySelector('[data-ra-undo-vertex]'),
    closeShape: root.querySelector('[data-ra-close-shape]'),
    clearShape: root.querySelector('[data-ra-clear-shape]'),
    canvasWrap: root.querySelector('[data-ra-canvas-wrap]'),
    liveArea: root.querySelector('[data-ra-live-area]'),
    liveSqft: root.querySelector('[data-ra-live-sqft]'),
    liveSqm: root.querySelector('[data-ra-live-sqm]'),
    feedback: root.querySelector('[data-ra-feedback]'),
    roomName: root.querySelector('[data-ra-room-name]'),
    saveRoom: root.querySelector('[data-ra-save-room]'),
    roomsEmpty: root.querySelector('[data-ra-rooms-empty]'),
    roomsList: root.querySelector('[data-ra-rooms-list]'),
    total: root.querySelector('[data-ra-total]'),
    totalSqft: root.querySelector('[data-ra-total-sqft]'),
    copyTotal: root.querySelector('[data-ra-copy-total]')
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
    layers: {},
    bgImage: null
  };

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
    if (!s) throw new Error('Enter a length in feet.');
    s = s.replace(/feet|foot|ft/gi, "'");
    s = s.replace(/inches|inch|in/gi, '"');
    var m;
    if ((m = s.match(/^([\d.]+)\s*['′]\s*([\d.]+)\s*["″]?$/))) {
      return parseFloat(m[1]) + parseFloat(m[2]) / INCHES_PER_FOOT;
    }
    if ((m = s.match(/^([\d.]+)\s*-\s*([\d.]+)$/))) {
      return parseFloat(m[1]) + parseFloat(m[2]) / INCHES_PER_FOOT;
    }
    var n = parseFloat(s.replace(/,/g, ''));
    if (isNaN(n) || n <= 0) throw new Error('Enter a valid length greater than zero.');
    return n;
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

  function snapPoint(raw, prev) {
    if (state.snapMode === 'off' || !prev) return raw;
    var dx = raw.x - prev.x;
    var dy = raw.y - prev.y;
    var angle = Math.atan2(dy, dx);
    var step = state.snapMode === '45' ? Math.PI / 4 : Math.PI / 2;
    var snapped = Math.round(angle / step) * step;
    var len = Math.hypot(dx, dy);
    return {
      x: prev.x + Math.cos(snapped) * len,
      y: prev.y + Math.sin(snapped) * len
    };
  }

  function gridStepPx() {
    var ppf = pixelsPerFoot();
    return ppf > 0 ? ppf : GRID_PX;
  }

  function initStage() {
    var container = document.getElementById('ra-konva-container');
    if (!container) return;

    if (state.stage) {
      state.stage.destroy();
    }

    state.stage = new Konva.Stage({
      container: 'ra-konva-container',
      width: CANVAS_W,
      height: CANVAS_H
    });

    state.layers.bg = new Konva.Layer();
    state.layers.grid = new Konva.Layer();
    state.layers.draw = new Konva.Layer();
    state.layers.labels = new Konva.Layer();
    state.layers.calib = new Konva.Layer();

    state.stage.add(state.layers.bg);
    state.stage.add(state.layers.grid);
    state.stage.add(state.layers.draw);
    state.stage.add(state.layers.labels);
    state.stage.add(state.layers.calib);

    state.stage.on('click tap', onStageClick);
    state.stage.on('dblclick dbltap', onStageDblClick);

    drawGrid();
    redrawAll();
  }

  function drawGrid() {
    var layer = state.layers.grid;
    layer.destroyChildren();
    var step = gridStepPx();
    for (var x = 0; x <= CANVAS_W; x += step) {
      layer.add(new Konva.Line({
        points: [x, 0, x, CANVAS_H],
        stroke: '#d8dee4',
        strokeWidth: x % (step * 5) === 0 ? 1 : 0.5,
        listening: false
      }));
    }
    for (var y = 0; y <= CANVAS_H; y += step) {
      layer.add(new Konva.Line({
        points: [0, y, CANVAS_W, y],
        stroke: '#d8dee4',
        strokeWidth: y % (step * 5) === 0 ? 1 : 0.5,
        listening: false
      }));
    }
    layer.draw();
  }

  function loadBackground(url) {
    state.layers.bg.destroyChildren();
    state.bgImage = null;
    if (!url) {
      state.layers.bg.draw();
      show(els.bgRemove, false);
      return;
    }

    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () {
      var kImg = new Konva.Image({
        image: img,
        x: 0,
        y: 0,
        width: CANVAS_W,
        height: CANVAS_H,
        listening: false,
        opacity: 0.85
      });
      state.layers.bg.add(kImg);
      state.layers.bg.draw();
      state.bgImage = kImg;
    };
    img.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
    show(els.bgRemove, true);
  }

  function redrawAll() {
    redrawShape();
    redrawCalib();
    updateLiveArea();
    updateToolbar();
  }

  function redrawShape() {
    var layer = state.layers.draw;
    var labelLayer = state.layers.labels;
    layer.destroyChildren();
    labelLayer.destroyChildren();

    var verts = state.vertices;
    if (verts.length === 0) {
      layer.draw();
      labelLayer.draw();
      return;
    }

    var linePoints = [];
    verts.forEach(function (v) { linePoints.push(v.x, v.y); });
    if (state.closed && verts.length >= 3) {
      linePoints.push(verts[0].x, verts[0].y);
    }

    if (linePoints.length >= 4) {
      layer.add(new Konva.Line({
        points: linePoints,
        stroke: '#024768',
        strokeWidth: 2.5,
        closed: state.closed,
        fill: state.closed ? 'rgba(2, 71, 104, 0.12)' : undefined,
        listening: false
      }));
    }

    verts.forEach(function (v, idx) {
      var isFirst = idx === 0;
      layer.add(new Konva.Circle({
        x: v.x,
        y: v.y,
        radius: isFirst && !state.closed && verts.length >= 3 ? CLOSE_HIT / 2 : 6,
        fill: isFirst ? '#c62828' : '#024768',
        stroke: '#fff',
        strokeWidth: 2,
        listening: false
      }));
    });

    var ppf = pixelsPerFoot();
    if (ppf > 0) {
      var count = state.closed ? verts.length : verts.length - 1;
      for (var i = 0; i < count; i++) {
        var a = verts[i];
        var b = verts[(i + 1) % verts.length];
        var midX = (a.x + b.x) / 2;
        var midY = (a.y + b.y) / 2;
        var lenFt = dist(a, b) / ppf;
        var feet = Math.floor(lenFt);
        var inches = Math.round((lenFt - feet) * INCHES_PER_FOOT);
        if (inches === 12) { feet += 1; inches = 0; }
        var lenLabel = feet + "' " + inches + '"';
        var kLabel = new Konva.Label({ x: midX, y: midY, listening: false });
        kLabel.add(new Konva.Tag({
          fill: 'rgba(255,255,255,0.92)',
          stroke: '#024768',
          strokeWidth: 1,
          cornerRadius: 4,
          pointerDirection: 'none'
        }));
        kLabel.add(new Konva.Text({
          text: lenLabel,
          fontSize: 11,
          fontFamily: 'Open Sans, sans-serif',
          fill: '#024768',
          padding: 4
        }));
        labelLayer.add(kLabel);
      }
    }

    layer.draw();
    labelLayer.draw();
  }

  function redrawCalib() {
    var layer = state.layers.calib;
    layer.destroyChildren();
    if (state.calibPoints.length === 0) {
      layer.draw();
      return;
    }
    state.calibPoints.forEach(function (p, idx) {
      layer.add(new Konva.Circle({
        x: p.x,
        y: p.y,
        radius: 7,
        fill: idx === 0 ? '#f9a825' : '#2e7d32',
        stroke: '#fff',
        strokeWidth: 2,
        listening: false
      }));
    });
    if (state.calibPoints.length === 2) {
      layer.add(new Konva.Line({
        points: [state.calibPoints[0].x, state.calibPoints[0].y, state.calibPoints[1].x, state.calibPoints[1].y],
        stroke: '#f9a825',
        strokeWidth: 2,
        dash: [8, 4],
        listening: false
      }));
    }
    layer.draw();
  }

  function getPointerPos() {
    var pos = state.stage.getPointerPosition();
    if (!pos) return null;
    return { x: pos.x, y: pos.y };
  }

  function onStageClick() {
    var pos = getPointerPos();
    if (!pos) return;

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

    if (state.closed) return;

    if (state.vertices.length >= 3) {
      var first = state.vertices[0];
      if (dist(pos, first) <= CLOSE_HIT) {
        closeShape();
        return;
      }
    }

    var point = pos;
    if (state.vertices.length > 0) {
      point = snapPoint(pos, state.vertices[state.vertices.length - 1]);
    }
    state.vertices.push(point);
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

  function clearShape() {
    state.vertices = [];
    state.closed = false;
    redrawAll();
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
    redrawShape();
    updateLiveArea();
  }

  function toggleCalibrate() {
    state.calibrating = !state.calibrating;
    state.calibPoints = [];
    els.calibrateToggle.classList.toggle('is-active', state.calibrating);
    els.knownLength.disabled = true;
    els.applyScale.disabled = true;
    redrawCalib();
    if (state.calibrating) {
      setFeedback('Click two points on a known wall length.', false);
    }
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

      fetch(urlFromTemplate(root.getAttribute('data-project-update-template'), project.id), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: JSON.stringify({ scale_pixels_per_foot: ppf })
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok) throw new Error(result.data.message || 'Could not save scale.');
          project.scale_pixels_per_foot = result.data.project.scale_pixels_per_foot;
          state.calibrating = false;
          state.calibPoints = [];
          els.calibrateToggle.classList.remove('is-active');
          els.knownLength.value = '';
          els.knownLength.disabled = true;
          els.applyScale.disabled = true;
          updateScaleUI();
          redrawCalib();
          setFeedback('Scale calibrated.', false);
        })
        .catch(function (e) { setFeedback(e.message, true); });
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
      li.className = 'util-ra-rooms-item';
      li.innerHTML =
        '<div class="util-ra-rooms-item__info">' +
          '<span class="util-ra-rooms-item__name">' + esc(room.name) + '</span>' +
          '<span class="util-ra-rooms-item__area">' + fmt(room.area_sqft, 2) + ' sq ft</span>' +
        '</div>' +
        '<div class="util-ra-rooms-item__actions">' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-copy-area="' + room.area_sqft + '" title="Copy sq ft"><i class="fa fa-copy"></i></button>' +
          '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-delete-room="' + room.id + '" title="Delete"><i class="fa fa-trash"></i></button>' +
        '</div>';
      els.roomsList.appendChild(li);
    });

    els.totalSqft.textContent = fmt(total, 2);

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

    if (hasProject) {
      var project = activeProject();
      loadBackground(project.background_url);
      updateScaleUI();
      clearShape();
      if (!state.stage) initStage();
      else redrawAll();
    }
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
    if (!project || !window.confirm('Delete this project and all saved rooms?')) return;

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

    els.saveRoom.disabled = true;
    fetch(urlFromTemplate(root.getAttribute('data-room-store-template'), project.id), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({ name: name, vertices: state.vertices })
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        els.saveRoom.disabled = false;
        if (!result.ok) throw new Error(result.data.message || 'Could not save room.');
        project.rooms.push(result.data.room);
        els.roomName.value = '';
        clearShape();
        renderRooms();
        setFeedback('Room saved.', false);
      })
      .catch(function (e) {
        els.saveRoom.disabled = false;
        setFeedback(e.message, true);
      });
  }

  function deleteRoom(roomId) {
    if (!window.confirm('Delete this room?')) return;
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
          renderRooms();
        }
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
        loadBackground(project.background_url);
        setFeedback('Floor plan uploaded.', false);
      })
      .catch(function (e) { setFeedback(e.message, true); });
  }

  function removeBackground() {
    var project = activeProject();
    if (!project) return;

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
        loadBackground(null);
        setFeedback('Background removed.', false);
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
    els.applyScale.addEventListener('click', applyScale);

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

    els.bgFile.addEventListener('change', function () {
      if (els.bgFile.files && els.bgFile.files[0]) {
        uploadBackground(els.bgFile.files[0]);
        els.bgFile.value = '';
      }
    });

    els.bgRemove.addEventListener('click', removeBackground);
    els.copyTotal.addEventListener('click', function () {
      copyText(els.totalSqft.textContent);
    });
  }

  function init() {
    state.projects = parseJson('data-projects', []);
    bindEvents();
    refreshProjectSelect();
    initStage();
    renderWorkspace();
  }

  init();
})();

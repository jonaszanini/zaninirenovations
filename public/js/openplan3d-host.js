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
      y: roundCoord(o.y)
    };
  }

  function snapshotFurniture(item) {
    if (!item) return null;
    var pos = item.position && typeof item.position === 'object'
      ? item.position
      : { x: item.x, y: item.y };
    return {
      id: item.id || null,
      x: roundCoord(pos.x),
      y: roundCoord(pos.y),
      rotation: roundCoord(item.rotation),
      width: roundCoord(item.width),
      depth: roundCoord(item.depth),
      height: roundCoord(item.height)
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
      labels: collectLabels(floor)
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
    var quoteRoomTemplate = rootEl.dataset.quoteRoomTemplate || '';

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

    function showFeedback(msg, isError) {
      if (!feedbackEl) return;
      feedbackEl.textContent = msg;
      feedbackEl.className = 'openplan3d-feedback ' + (isError ? 'openplan3d-feedback--error' : 'openplan3d-feedback--success');
      feedbackEl.hidden = false;
      if (!isError) {
        setTimeout(function () {
          feedbackEl.hidden = true;
        }, 5000);
      }
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
        iframe.contentWindow.postMessage(payload, '*');
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
        if (quoteBtn) quoteBtn.hidden = true;
        setDirty(false);
        if (saveBtn) saveBtn.disabled = true;
      }
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

      if (!targetProject && projects.length > 0) {
        targetProject = projects[0];
      }

      if (targetProject) {
        selectProject(targetProject.id);
        if (targetRoom) {
          selectRoom(targetRoom);
        } else if (targetProject.rooms && targetProject.rooms.length > 0) {
          selectRoom(targetProject.rooms[0]);
        } else {
          createNewRoom();
        }
      }
    }

    function createNewRoom() {
      if (!currentProject) {
        if (win && win.alert) win.alert('Please select or create a project first.');
        return;
      }
      var roomNumber = (currentProject.rooms ? currentProject.rooms.length : 0) + 1;
      var name = win && win.prompt ? win.prompt('Room name:', 'Room ' + roomNumber) : ('Room ' + roomNumber);
      if (!name || !name.trim()) return;

      var newRoom = {
        id: null,
        name: name.trim(),
        plan: null,
        wall_height_ft: 8,
        ceiling_height_ft: 8,
        flooring_waste_percent: 10,
        flooring_box_sqft: 20
      };

      selectRoom(newRoom);
      if (saveBtn) saveBtn.disabled = false;
      setDirty(false);
    }

    function createNewProject() {
      var name = win && win.prompt ? win.prompt('Project name:') : null;
      if (!name || !name.trim()) return;

      var payload = {
        name: name.trim(),
        _token: csrfToken
      };

      fetch(projectStoreUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok && data.project) {
          projects.push(data.project);
          populateProjects();
          selectProject(data.project.id);
          createNewRoom();
          showFeedback('Project created successfully!');
        } else {
          showFeedback(data.message || 'Failed to create project.', true);
        }
      })
      .catch(function () {
        showFeedback('Server error creating project.', true);
      });
    }

    function storedAreaSqft() {
      if (!currentRoom || currentRoom.area_sqft == null) return 0;
      var n = Number(currentRoom.area_sqft);
      return isFinite(n) ? n : 0;
    }

    function savePlan() {
      if (isSaving) return;
      if (!currentProject) {
        showFeedback('No active project selected.', true);
        return;
      }

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

      var metrics = currentMetrics(currentPlan);
      var storedArea = storedAreaSqft();
      var incomingArea = metrics && metrics.area_sqft != null ? Number(metrics.area_sqft) : 0;
      var confirmZero = false;
      if (storedArea > 0 && (!isFinite(incomingArea) || incomingArea <= 0)) {
        var ok = win && typeof win.confirm === 'function'
          ? win.confirm('This save would replace the stored room area (' + storedArea.toFixed(2) + ' sq ft) with 0. Continue?')
          : false;
        if (!ok) {
          showFeedback('Save cancelled. The stored room area was kept.', true);
          return;
        }
        confirmZero = true;
      }

      isSaving = true;
      if (saveBtn) saveBtn.disabled = true;
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
        confirm_zero_area: confirmZero,
        _submit_once: submitOnce,
        _token: csrfToken
      };

      if (win) win.__lastSavedPayload = payload;

      fetch(url, {
        method: isUpdate ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
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
      .catch(function () {
        showFeedback('An unexpected error occurred while saving the plan.', true);
      })
      .finally(function () {
        isSaving = false;
        if (saveBtn) saveBtn.disabled = false;
        if (saveSpinner) saveSpinner.hidden = true;
        if (saveText) saveText.hidden = false;
      });
    }

    if (projectSelect) {
      projectSelect.addEventListener('change', function () {
        var val = projectSelect.value;
        if (val === '__new__') {
          createNewProject();
          return;
        }
        if (isDirty) {
          if (!win.confirm('You have unsaved changes. Discard changes and switch project?')) {
            projectSelect.value = currentProject ? String(currentProject.id) : '';
            return;
          }
        }
        selectProject(parseInt(val, 10));
        if (currentProject && currentProject.rooms && currentProject.rooms.length > 0) {
          selectRoom(currentProject.rooms[0]);
        } else {
          selectRoom(null);
        }
      });
    }

    if (roomSelect) {
      roomSelect.addEventListener('change', function () {
        var val = roomSelect.value;
        if (val === '__new__') {
          createNewRoom();
          return;
        }
        if (isDirty) {
          if (!win.confirm('You have unsaved changes. Discard changes and switch room?')) {
            roomSelect.value = currentRoom && currentRoom.id ? String(currentRoom.id) : '';
            return;
          }
        }
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
      });
    }

    if (roomNameInput) {
      roomNameInput.addEventListener('input', function () {
        setDirty(true);
      });
    }

    if (newRoomBtn) {
      newRoomBtn.addEventListener('click', function () {
        if (isDirty) {
          if (!win.confirm('You have unsaved changes. Discard changes and create new room?')) {
            return;
          }
        }
        createNewRoom();
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

      win.addEventListener('message', function (e) {
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
          setDirty(false);
          if (saveBtn) saveBtn.disabled = !currentPlan;
        } else if (e.data.type === 'OPENPLAN3D_BRIDGE_ERROR') {
          showFeedback('openPlan3D editor API is unavailable. Room geometry cannot be loaded. Refresh the page.', true);
        } else if (e.data.type === 'OPENPLAN3D_PLAN_CHANGED') {
          if (!loadAcked) return;
          currentPlan = e.data.plan;
          var planChanged = !plansMatch(currentPlan, loadedPlanBaseline);
          setDirty(planChanged);
          if (saveBtn) saveBtn.disabled = !currentPlan;
          updateMetrics(currentPlan);
        } else if (e.data.type === 'OPENPLAN3D_SELECT_ROOM') {
          var nextId = resolveOpenPlan3DRoomId(currentPlan, e.data.roomId);
          selectedOpenPlan3DRoomId = nextId;
          if (currentPlan) updateMetrics(currentPlan);
        } else if (e.data.type === 'OPENPLAN3D_TRIGGER_SAVE') {
          if (saveBtn && !saveBtn.disabled && !isSaving) {
            savePlan();
          }
        }
      });
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
      getState: function () {
        return {
          isDirty: isDirty,
          loadAcked: loadAcked,
          iframeReady: iframeReady,
          currentPlan: currentPlan,
          currentRoom: currentRoom,
          loadedPlanBaseline: loadedPlanBaseline,
          selectedOpenPlan3DRoomId: selectedOpenPlan3DRoomId
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
    boot: boot
  };
}));

/* Measurement Conversion tool — project-based unit converter */
(function () {
  'use strict';

  var root = document.querySelector('[data-measurement-conversion]');
  if (!root) return;

  var MM_PER_INCH = 25.4;
  var INCHES_PER_FOOT = 12;

  var UNIT_KEYS = ['mm', 'cm', 'm', 'in_dec', 'in_frac', 'ft_dec', 'ft_in_dec', 'ft_in_frac'];

  var UNIT_HINTS = {
    mm: 'Enter a decimal number (e.g. 1200).',
    cm: 'Enter a decimal number (e.g. 120.0).',
    m: 'Enter a decimal number (e.g. 1.20).',
    in_dec: 'Enter decimal inches (e.g. 47.25).',
    in_frac: 'Enter inches as a fraction (e.g. 1 1/4 or 47 1/2).',
    ft_dec: 'Enter decimal feet (e.g. 3.93).',
    ft_in_dec: 'Use format 3\' 11.25" or 3-11.25.',
    ft_in_frac: 'Use format 3\' 11 1/4" or 3\' 11 1/4.'
  };

  var COLUMN_KEYS = ['mm', 'cm', 'm', 'in_dec', 'in_frac', 'ft_dec', 'ft_in_dec', 'ft_in_frac'];

  var els = {
    projectSelect: root.querySelector('[data-mc-project-select]'),
    newProjectToggle: root.querySelector('[data-mc-new-project-toggle]'),
    newProjectPanel: root.querySelector('[data-mc-new-project]'),
    newProjectName: root.querySelector('#mc-new-project-name'),
    newClientProject: root.querySelector('[data-mc-new-client-project]'),
    createProjectBtn: root.querySelector('[data-mc-create-project]'),
    deleteProjectBtn: root.querySelector('[data-mc-delete-project]'),
    linked: root.querySelector('[data-mc-linked]'),
    linkedLink: root.querySelector('[data-mc-linked-link]'),
    linkClientProject: root.querySelector('[data-mc-link-client-project]'),
    empty: root.querySelector('[data-mc-empty]'),
    workspace: root.querySelector('[data-mc-workspace]'),
    value: root.querySelector('[data-mc-value]'),
    unit: root.querySelector('[data-mc-unit]'),
    label: root.querySelector('[data-mc-label]'),
    addBtn: root.querySelector('[data-mc-add]'),
    unitHint: root.querySelector('[data-mc-unit-hint]'),
    feedback: root.querySelector('[data-mc-feedback]'),
    preview: root.querySelector('[data-mc-preview]'),
    previewChips: root.querySelector('[data-mc-preview-chips]'),
    tableEmpty: root.querySelector('[data-mc-table-empty]'),
    tableWrap: root.querySelector('[data-mc-table-wrap]'),
    tableBody: root.querySelector('[data-mc-table-body]')
  };

  var state = {
    projects: [],
    activeProjectId: null
  };

  var previewTimer = null;

  function csrf() {
    var input = root.querySelector('#util-mc-csrf');
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

  function fmt(value, maxDecimals) {
    var rounded = Math.round(value * Math.pow(10, maxDecimals)) / Math.pow(10, maxDecimals);
    var s = rounded.toFixed(maxDecimals);
    s = s.replace(/\.?0+$/, '');
    return s === '' || s === '-' ? '0' : s;
  }

  function inchFraction(inches) {
    var sign = inches < 0 ? '-' : '';
    var v = Math.abs(inches);
    var whole = Math.floor(v);
    var sixteenths = Math.round((v - whole) * 16);
    if (sixteenths === 16) { whole += 1; sixteenths = 0; }
    if (sixteenths === 0) return sign + String(whole);
    var n = sixteenths, d = 16;
    while (n % 2 === 0 && d % 2 === 0) { n /= 2; d /= 2; }
    var frac = n + '/' + d;
    return sign + (whole > 0 ? whole + ' ' + frac : frac);
  }

  function parseDecimal(value) {
    var clean = String(value).replace(/,/g, '').replace(/\s/g, '');
    if (clean === '' || isNaN(parseFloat(clean))) throw new Error('Enter a valid number.');
    return parseFloat(clean);
  }

  function parseInchFraction(input) {
    var s = String(input).toLowerCase().trim();
    s = s.replace(/inches|inch|in|"|″/g, '').trim();
    var m;
    if ((m = s.match(/^(-?\d+)\s+(\d+)\s*\/\s*(\d+)$/))) {
      var sign = parseInt(m[1], 10) < 0 ? -1 : 1;
      var whole = Math.abs(parseInt(m[1], 10));
      var den = parseInt(m[3], 10);
      if (!den) throw new Error('Invalid fraction.');
      return sign * (whole + parseInt(m[2], 10) / den);
    }
    if ((m = s.match(/^(-?\d+)\s*\/\s*(\d+)$/))) {
      var d2 = parseInt(m[2], 10);
      if (!d2) throw new Error('Invalid fraction.');
      return parseInt(m[1], 10) / d2;
    }
    return parseDecimal(s);
  }

  function parseFeetInchDecimal(input) {
    var s = String(input).trim();
    s = s.replace(/feet|foot|ft/gi, "'");
    s = s.replace(/inches|inch|in/gi, '"');
    var m;
    if ((m = s.match(/^(-?\d+)\s*['′]\s*([\d.]+)\s*["″]?$/))) {
      return parseInt(m[1], 10) * INCHES_PER_FOOT + parseDecimal(m[2]);
    }
    if ((m = s.match(/^(-?\d+)\s*-\s*([\d.]+)$/))) {
      return parseInt(m[1], 10) * INCHES_PER_FOOT + parseDecimal(m[2]);
    }
    throw new Error('Use format like 3\' 11.25" or 3-11.25.');
  }

  function parseFeetInchFraction(input) {
    var s = String(input).trim();
    s = s.replace(/feet|foot|ft/gi, "'");
    s = s.replace(/["″]\s*$/, '');
    var m = s.match(/^(-?\d+)\s*['′]\s*(.+)$/);
    if (!m) throw new Error('Use format like 3\' 11 1/4" or 3\' 11 1/4.');
    return parseInt(m[1], 10) * INCHES_PER_FOOT + parseInchFraction(m[2].trim());
  }

  function parseToMm(unit, raw) {
    var value = String(raw).trim();
    if (!value) throw new Error('Enter a measurement value.');
    switch (unit) {
      case 'mm': return parseDecimal(value);
      case 'cm': return parseDecimal(value) * 10;
      case 'm': return parseDecimal(value) * 1000;
      case 'in_dec': return parseDecimal(value) * MM_PER_INCH;
      case 'in_frac': return parseInchFraction(value) * MM_PER_INCH;
      case 'ft_dec': return parseDecimal(value) * INCHES_PER_FOOT * MM_PER_INCH;
      case 'ft_in_dec': return parseFeetInchDecimal(value) * MM_PER_INCH;
      case 'ft_in_frac': return parseFeetInchFraction(value) * MM_PER_INCH;
      default: throw new Error('Unknown unit.');
    }
  }

  function formatsFromMm(mm) {
    var totalInches = mm / MM_PER_INCH;
    var feet = Math.floor(totalInches / INCHES_PER_FOOT);
    var inchRem = totalInches - feet * INCHES_PER_FOOT;
    return {
      mm: fmt(mm, 2),
      cm: fmt(mm / 10, 2),
      m: fmt(mm / 1000, 4),
      in_dec: fmt(totalInches, 4),
      in_frac: inchFraction(totalInches),
      ft_dec: fmt(totalInches / INCHES_PER_FOOT, 4),
      ft_in_dec: feet + "' " + fmt(inchRem, 4) + '"',
      ft_in_frac: feet + "' " + inchFraction(inchRem) + '"'
    };
  }

  function activeProject() {
    return state.projects.find(function (p) { return p.id === state.activeProjectId; }) || null;
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

  function renderTable() {
    var project = activeProject();
    var rows = project ? project.rows : [];
    show(els.tableEmpty, rows.length === 0);
    show(els.tableWrap, rows.length > 0);
    els.tableBody.innerHTML = '';

    rows.forEach(function (row) {
      var tr = document.createElement('tr');
      var labelTd = document.createElement('td');
      labelTd.className = 'util-mc-col-label';
      labelTd.textContent = row.label || '—';
      tr.appendChild(labelTd);

      COLUMN_KEYS.forEach(function (key) {
        var td = document.createElement('td');
        td.textContent = row.formats[key] || '—';
        tr.appendChild(td);
      });

      var actTd = document.createElement('td');
      actTd.className = 'util-mc-col-actions';
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'admin-btn admin-btn--danger admin-btn--sm';
      delBtn.setAttribute('aria-label', 'Delete row');
      delBtn.innerHTML = '<i class="fa fa-trash"></i>';
      delBtn.addEventListener('click', function () { deleteRow(row.id); });
      actTd.appendChild(delBtn);
      tr.appendChild(actTd);

      els.tableBody.appendChild(tr);
    });
  }

  function renderWorkspace() {
    var hasProject = !!state.activeProjectId;
    show(els.empty, !hasProject);
    show(els.workspace, hasProject);
    show(els.deleteProjectBtn, hasProject);
    renderLinked();
    renderTable();
  }

  function setFeedback(msg, isError) {
    if (!els.feedback) return;
    els.feedback.textContent = msg;
    els.feedback.className = 'util-mc-feedback ' + (isError ? 'is-error' : 'is-ok');
    show(els.feedback, !!msg);
    if (msg) setTimeout(function () { show(els.feedback, false); }, 4000);
  }

  function renderPreview() {
    var unit = els.unit.value;
    var raw = els.value.value.trim();
    if (!raw) {
      show(els.preview, false);
      return;
    }
    try {
      var mm = parseToMm(unit, raw);
      if (mm < 0) throw new Error('Measurement must be zero or positive.');
      var formats = formatsFromMm(mm);
      var html = '';
      COLUMN_KEYS.forEach(function (key) {
        html += '<span class="util-mc-preview__chip"><strong>' + key.replace(/_/g, ' ') + '</strong> ' + esc(formats[key]) + '</span>';
      });
      els.previewChips.innerHTML = html;
      show(els.preview, true);
    } catch (e) {
      show(els.preview, false);
    }
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(renderPreview, 200);
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
    window.AdminDialog.confirm('Delete "' + project.name + '" and all its measurements?', { title: 'Delete project', danger: true }).then(function (ok) {
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
        state.activeProjectId = state.projects.length ? state.projects[0].id : null;
        refreshProjectSelect();
        renderWorkspace();
      });
    });
  }

  function addRow() {
    var project = activeProject();
    if (!project) return;

    var unit = els.unit.value;
    var raw = els.value.value.trim();
    var label = (els.label.value || '').trim();

    if (!raw) {
      els.value.focus();
      setFeedback('Enter a value first.', true);
      return;
    }

    els.addBtn.disabled = true;

    fetch(urlFromTemplate(root.getAttribute('data-row-store-template'), project.id), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({ source_unit: unit, source_value: raw, label: label })
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        els.addBtn.disabled = false;
        if (!result.ok) throw new Error(result.data.message || 'Could not add measurement.');
        project.rows.push(result.data.row);
        els.value.value = '';
        els.label.value = '';
        show(els.preview, false);
        renderTable();
        setFeedback('Measurement added.', false);
      })
      .catch(function (e) {
        els.addBtn.disabled = false;
        setFeedback(e.message, true);
      });
  }

  function deleteRow(rowId) {
    var project = activeProject();
    if (!project) return;

    fetch(urlFromTemplate(root.getAttribute('data-row-destroy-template'), rowId), {
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
        project.rows = project.rows.filter(function (r) { return r.id !== rowId; });
        renderTable();
      });
  }

  function updateUnitHint() {
    var unit = els.unit.value;
    var hint = UNIT_HINTS[unit] || '';
    els.unitHint.innerHTML = hint;
  }

  // Events
  els.projectSelect.addEventListener('change', function () {
    state.activeProjectId = els.projectSelect.value ? parseInt(els.projectSelect.value, 10) : null;
    renderWorkspace();
  });

  els.newProjectToggle.addEventListener('click', function () {
    var open = els.newProjectPanel.hasAttribute('hidden');
    show(els.newProjectPanel, open);
    if (open) els.newProjectName.focus();
  });

  els.createProjectBtn.addEventListener('click', createProject);
  els.deleteProjectBtn.addEventListener('click', deleteProject);
  els.addBtn.addEventListener('click', addRow);

  els.linkClientProject.addEventListener('change', function () {
    updateProjectLink(els.linkClientProject.value);
  });

  els.unit.addEventListener('change', function () {
    updateUnitHint();
    schedulePreview();
  });

  els.value.addEventListener('input', schedulePreview);
  els.label.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); addRow(); }
  });
  els.value.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); addRow(); }
  });

  // Init
  state.projects = parseJson('data-projects', []);
  if (state.projects.length) state.activeProjectId = state.projects[0].id;
  var mcProject = (function () {
    try { return new URLSearchParams(window.location.search).get('mc_project'); } catch (e) { return null; }
  })();
  if (mcProject) {
    var mcId = parseInt(mcProject, 10);
    if (state.projects.some(function (p) { return p.id === mcId; })) {
      state.activeProjectId = mcId;
    }
  }
  refreshProjectSelect();
  updateUnitHint();
  renderWorkspace();
})();

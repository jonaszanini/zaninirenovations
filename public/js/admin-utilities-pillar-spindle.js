/* Pillar & Spindle Layout Calculator
 * All maths run live in the browser. Saving + PDF export talk to the
 * UtilitiesController endpoints. */
(function () {
  'use strict';

  var root = document.querySelector('[data-pillar-spindle]');
  if (!root) return;

  // ── Element references ──────────────────────────────────────────────
  var els = {
    length: root.querySelector('#ps-length'),
    unit: root.querySelector('#ps-unit'),
    thickness: root.querySelector('#ps-thickness'),
    thicknessUnit: root.querySelector('#ps-thickness-unit'),
    spacing: root.querySelector('#ps-spacing'),
    gapUnit: root.querySelector('#ps-gap-unit'),
    count: root.querySelector('#ps-count'),
    placement: root.querySelector('#ps-placement'),
    modeToggle: root.querySelector('[data-ps-mode-toggle]'),
    empty: root.querySelector('[data-ps-empty]'),
    error: root.querySelector('[data-ps-error]'),
    errorText: root.querySelector('[data-ps-error-text]'),
    live: root.querySelector('[data-ps-live]'),
    breakdown: root.querySelector('[data-ps-breakdown]'),
    statCount: root.querySelector('[data-ps-stat-count]'),
    statCountLabel: root.querySelector('[data-ps-stat-count-label]'),
    statGaps: root.querySelector('[data-ps-stat-gaps]'),
    statClear: root.querySelector('[data-ps-stat-clear]'),
    statClearFraction: root.querySelector('[data-ps-stat-clear-fraction]'),
    statC2c: root.querySelector('[data-ps-stat-c2c]'),
    statC2cFraction: root.querySelector('[data-ps-stat-c2c-fraction]'),
    diagramCard: root.querySelector('[data-ps-diagram-card]'),
    diagram: root.querySelector('[data-ps-diagram]'),
    marksCard: root.querySelector('[data-ps-marks-card]'),
    marksBody: root.querySelector('[data-ps-marks-body]'),
    copyMarksBtn: root.querySelector('[data-ps-copy-marks]'),
    saveCard: root.querySelector('[data-ps-save-card]'),
    saveName: root.querySelector('#ps-save-name'),
    saveFeedback: root.querySelector('[data-ps-save-feedback]'),
    copyBtn: root.querySelector('[data-ps-copy]'),
    savedList: root.querySelector('[data-ps-saved-list]'),
    savedEmpty: root.querySelector('[data-ps-saved-empty]'),
    modeFields: root.querySelectorAll('[data-ps-mode-field]')
  };

  var state = {
    mode: 'spacing' // 'spacing' | 'count'
  };

  var lastResult = null; // cached computed result for export/copy/save

  // ── Helpers ─────────────────────────────────────────────────────────
  function csrf() {
    var input = root.querySelector('#util-ps-csrf');
    if (input && input.value) return input.value;
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function fmt(v) {
    if (!isFinite(v)) return '—';
    var rounded = Math.round(v * 1000) / 1000;
    return String(parseFloat(rounded.toFixed(3)));
  }

  // Each input carries its own unit; convert everything to metres for the
  // maths, then format results back into the relevant display unit.
  var UNIT_TO_M = { ft: 0.3048, m: 1, in: 0.0254 };

  function toM(v, u) { return v * (UNIT_TO_M[u] || 1); }
  function fromM(v, u) { return v / (UNIT_TO_M[u] || 1); }

  function lengthUnit() { return els.unit.value || 'ft'; }
  function thicknessUnit() { return els.thicknessUnit.value || 'in'; }
  function gapUnit() { return els.gapUnit.value || 'in'; }

  // Display unit for spacing results (clear gap + centre-to-centre). The
  // thickness unit is always present, so it keeps the results consistent.
  function spacingUnit() { return thicknessUnit(); }

  function fmtM(meters, u) { return fmt(fromM(meters, u)) + ' ' + u; }

  // Nearest 1/16" expressed as a reduced fraction (denominators 2/4/8/16),
  // e.g. 2.893 → "2 7/8", 0.25 → "1/4", 0.5 → "1/2".
  function inchFraction(inches) {
    var sign = inches < 0 ? '-' : '';
    var v = Math.abs(inches);
    var whole = Math.floor(v);
    var sixteenths = Math.round((v - whole) * 16);
    if (sixteenths === 16) { whole += 1; sixteenths = 0; }
    if (sixteenths === 0) {
      return sign + String(whole);
    }
    var n = sixteenths;
    var d = 16;
    while (n % 2 === 0 && d % 2 === 0) { n /= 2; d /= 2; }
    var frac = n + '/' + d;
    return sign + (whole > 0 ? whole + ' ' + frac : frac);
  }

  // The fractional "≈" readout only makes sense for inch values.
  function inchApprox(meters) {
    if (spacingUnit() !== 'in') return '';
    return '≈ ' + inchFraction(fromM(meters, 'in')) + ' in';
  }

  function setFraction(el, meters) {
    if (!el) return;
    var text = inchApprox(meters);
    el.textContent = text;
    show(el, text !== '');
  }

  // Distance label for tape-measure marks (measured from the start of the
  // run). Imperial → inch fractions; fully metric → metres.
  function markLabel(meters) {
    if (lengthUnit() === 'm' && thicknessUnit() === 'm') {
      return fmt(meters) + ' m';
    }
    return inchFraction(fromM(meters, 'in')) + '"';
  }

  // One entry per pillar: where its near and far edges fall along the run.
  function markRows(r) {
    if (!r || !r.positions) return [];
    var rows = [];
    for (var i = 0; i < r.positions.length; i++) {
      var near = r.positions[i];
      rows.push({
        n: i + 1,
        start: markLabel(near),
        end: markLabel(near + r.tm)
      });
    }
    return rows;
  }

  function elementWord(plural, capital) {
    var word = plural ? 'pillars' : 'pillar';
    if (capital) return word.charAt(0).toUpperCase() + word.slice(1);
    return word;
  }

  function num(input) {
    var v = parseFloat(input.value);
    return isNaN(v) ? null : v;
  }

  // Gap-count offset per placement: total gaps G = N + k.
  function placementOffset(placement) {
    if (placement === 'both') return -1;
    if (placement === 'none') return 1;
    return 0; // start | end
  }

  function minCount(placement) {
    return placement === 'both' ? 2 : 1;
  }

  // ── Core calculation ────────────────────────────────────────────────
  function compute() {
    var Lraw = num(els.length);
    var traw = num(els.thickness);
    var placement = els.placement.value;
    var k = placementOffset(placement);
    var minN = minCount(placement);

    // Not enough input yet → empty state (not an error).
    if (Lraw === null || traw === null || Lraw <= 0 || traw <= 0) {
      return { status: 'empty' };
    }

    var Lm = toM(Lraw, lengthUnit());
    var tm = toM(traw, thicknessUnit());

    if (tm >= Lm) {
      return { status: 'error', message: 'The thickness is bigger than the total length. Lower the thickness or increase the length.' };
    }

    var N;

    if (state.mode === 'spacing') {
      var draw = num(els.spacing);
      if (draw === null || draw <= 0) {
        return { status: 'empty' };
      }
      var dm = toM(draw, gapUnit());
      // Fewest pillars that keep every clear gap <= the desired gap.
      // N = ceil((L - d*k) / (t + d)), then clamped to the minimum.
      N = Math.ceil((Lm - dm * k) / (tm + dm));
      if (N < minN) N = minN;
    } else {
      var craw = num(els.count);
      if (craw === null || craw <= 0) {
        return { status: 'empty' };
      }
      N = Math.round(craw);
      if (placement === 'both' && N < 2) {
        return { status: 'error', message: 'You need at least 2 ' + elementWord(true) + ' when placing one at each end.' };
      }
      if (N < 1) {
        return { status: 'error', message: 'Enter a number of ' + elementWord(true) + ' of 1 or more.' };
      }
    }

    var G = N + k; // number of gaps

    // Does the solid material fit at all?
    if (N * tm > Lm + 1e-9) {
      var su = spacingUnit();
      return {
        status: 'error',
        message: N + ' ' + elementWord(true) + ' at ' + fmtM(tm, su) + ' thick need ' +
          fmtM(N * tm, su) + ', which is more than the ' + fmtM(Lm, lengthUnit()) + ' available.'
      };
    }

    var clearM = G > 0 ? (Lm - N * tm) / G : 0;
    if (clearM < -1e-9) {
      return { status: 'error', message: 'These values leave no room between ' + elementWord(true) + '. Reduce the count or thickness.' };
    }
    if (clearM < 0) clearM = 0;

    // Pillar left-edge positions (metres, from the start of the run). Shared
    // by the diagram and the tape-measure marking guide.
    var lead = (placement === 'end' || placement === 'none') ? clearM : 0;
    var positions = [];
    var p = lead;
    for (var i = 0; i < N; i++) {
      positions.push(p);
      p += tm;
      if (i < N - 1) p += clearM;
    }

    return {
      status: 'ok',
      Lm: Lm,
      tm: tm,
      placement: placement,
      N: N,
      G: G,
      clearM: clearM,
      c2cM: clearM + tm,
      lead: lead,
      positions: positions
    };
  }

  // ── Rendering ───────────────────────────────────────────────────────
  function show(el, visible) {
    if (!el) return;
    if (visible) el.removeAttribute('hidden');
    else el.setAttribute('hidden', '');
  }

  function placementLabel(p) {
    return {
      both: 'At both ends',
      start: 'Only at start',
      end: 'Only at end',
      none: 'No pillars at ends'
    }[p] || p;
  }

  function render() {
    var result = compute();
    lastResult = result.status === 'ok' ? result : null;

    var ok = result.status === 'ok';
    var isError = result.status === 'error';

    show(els.empty, result.status === 'empty');
    show(els.error, isError);
    show(els.live, ok);
    show(els.diagramCard, ok);
    show(els.marksCard, ok);
    show(els.saveCard, ok);
    if (els.copyBtn) show(els.copyBtn, ok);

    if (isError) {
      els.errorText.textContent = result.message;
      return;
    }
    if (!ok) return;

    var su = spacingUnit();
    var gapWord = result.G === 1 ? 'space' : 'spaces';
    els.breakdown.innerHTML =
      'You need <strong>' + result.N + ' ' + elementWord(true) + '</strong> with <strong>' +
      result.G + ' ' + gapWord + '</strong> of <strong>' + fmtM(result.clearM, su) + '</strong> each (clear gap). ' +
      'Center&#8209;to&#8209;center spacing is <strong>' + fmtM(result.c2cM, su) + '</strong>.';

    els.statCount.textContent = result.N;
    els.statCountLabel.textContent = elementWord(true, true);
    els.statGaps.textContent = result.G;
    els.statClear.textContent = fmtM(result.clearM, su);
    els.statC2c.textContent = fmtM(result.c2cM, su);
    setFraction(els.statClearFraction, result.clearM);
    setFraction(els.statC2cFraction, result.c2cM);

    els.diagram.innerHTML = buildSvg(result);
    renderMarks(result);
  }

  function renderMarks(r) {
    if (!els.marksBody) return;
    var rows = markRows(r);
    var html = '';
    for (var i = 0; i < rows.length; i++) {
      html += '<tr><td class="util-ps-marks-n">' + rows[i].n + '</td><td>' +
        esc(rows[i].start) + '</td><td>' + esc(rows[i].end) + '</td></tr>';
    }
    els.marksBody.innerHTML = html;
  }

  // ── SVG diagram ─────────────────────────────────────────────────────
  function buildSvg(r) {
    var W = 1000;
    var H = 260;
    var padX = 60;
    var baselineY = 190;
    var pillarH = 96;
    var drawW = W - padX * 2;
    var scale = drawW / r.Lm;
    var lengthU = lengthUnit();
    var spacingU = spacingUnit();

    var lead = r.lead;
    var positions = r.positions;

    function px(pos) { return padX + pos * scale; }

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Layout diagram">');
    svg.push('<rect x="0" y="0" width="' + W + '" height="' + H + '" fill="#ffffff"/>');

    // Ground / run track.
    svg.push('<line x1="' + padX + '" y1="' + baselineY + '" x2="' + (W - padX) + '" y2="' + baselineY + '" stroke="#c5cdd6" stroke-width="2"/>');

    // Pillars.
    var minW = 4;
    for (var j = 0; j < positions.length; j++) {
      var leftUnits = positions[j];
      var centerUnits = leftUnits + r.tm / 2;
      var pw = Math.max(r.tm * scale, minW);
      var x = px(centerUnits) - pw / 2;
      svg.push('<rect x="' + x.toFixed(1) + '" y="' + (baselineY - pillarH) + '" width="' + pw.toFixed(1) +
        '" height="' + pillarH + '" rx="2" fill="#024768"/>');
      if (r.N <= 24) {
        svg.push('<text x="' + px(centerUnits).toFixed(1) + '" y="' + (baselineY - pillarH - 8) +
          '" text-anchor="middle" font-family="Segoe UI, sans-serif" font-size="13" fill="#5a6570">' + (j + 1) + '</text>');
      }
    }

    // Clear-gap dimension (first interior gap when possible).
    var gapStart, gapEnd;
    if (positions.length >= 2) {
      gapStart = positions[0] + r.tm;
      gapEnd = positions[1];
    } else if (lead > 0) {
      gapStart = 0; gapEnd = lead;
    } else {
      gapStart = positions[0] + r.tm; gapEnd = r.Lm;
    }
    if (r.clearM > 0 && gapEnd > gapStart) {
      var gy = baselineY - pillarH / 2;
      var gx1 = px(gapStart);
      var gx2 = px(gapEnd);
      svg.push('<line x1="' + gx1.toFixed(1) + '" y1="' + gy + '" x2="' + gx2.toFixed(1) + '" y2="' + gy +
        '" stroke="#2e7d4a" stroke-width="1.5" stroke-dasharray="4 3"/>');
      svg.push(tick(gx1, gy, '#2e7d4a'));
      svg.push(tick(gx2, gy, '#2e7d4a'));
      svg.push('<text x="' + ((gx1 + gx2) / 2).toFixed(1) + '" y="' + (gy - 8) +
        '" text-anchor="middle" font-family="Segoe UI, sans-serif" font-size="12" fill="#2e7d4a">' +
        esc(fmtM(r.clearM, spacingU)) + '</text>');
    }

    // Total-length dimension.
    var dimY = baselineY + 38;
    svg.push('<line x1="' + padX + '" y1="' + dimY + '" x2="' + (W - padX) + '" y2="' + dimY +
      '" stroke="#024768" stroke-width="1.5"/>');
    svg.push(tick(padX, dimY, '#024768'));
    svg.push(tick(W - padX, dimY, '#024768'));
    svg.push('<text x="' + (W / 2) + '" y="' + (dimY + 22) +
      '" text-anchor="middle" font-family="Segoe UI, sans-serif" font-size="14" font-weight="700" fill="#024768">' +
      esc('Total length: ' + fmtM(r.Lm, lengthU)) + '</text>');

    svg.push('</svg>');
    return svg.join('');
  }

  function tick(x, y, color) {
    return '<line x1="' + x.toFixed(1) + '" y1="' + (y - 6) + '" x2="' + x.toFixed(1) + '" y2="' + (y + 6) +
      '" stroke="' + color + '" stroke-width="1.5"/>';
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // ── Export / copy ───────────────────────────────────────────────────
  function resultRows() {
    if (!lastResult) return [];
    var r = lastResult;
    var su = spacingUnit();
    var modeLabel = state.mode === 'spacing'
      ? 'By spacing (target ' + fmt(num(els.spacing) || 0) + ' ' + gapUnit() + ')'
      : 'By number';
    return [
      { label: 'Total length', value: fmtM(r.Lm, lengthUnit()) },
      { label: 'Pillar thickness', value: fmtM(r.tm, thicknessUnit()) },
      { label: 'End placement', value: placementLabel(r.placement) },
      { label: 'Calculation mode', value: modeLabel },
      { label: 'Number of ' + elementWord(true), value: String(r.N) },
      { label: 'Spaces / gaps', value: String(r.G) },
      { label: 'Clear gap (each)', value: withApprox(fmtM(r.clearM, su), r.clearM) },
      { label: 'Center-to-center', value: withApprox(fmtM(r.c2cM, su), r.c2cM) }
    ];
  }

  function withApprox(text, meters) {
    var approx = inchApprox(meters);
    return approx ? text + ' (' + approx + ')' : text;
  }

  function summaryText() {
    if (!lastResult) return '';
    var r = lastResult;
    var su = spacingUnit();
    var gapWord = r.G === 1 ? 'space' : 'spaces';
    return 'You need ' + r.N + ' ' + elementWord(true) + ' with ' + r.G + ' ' + gapWord +
      ' of ' + fmtM(r.clearM, su) + ' each (clear gap). Center-to-center spacing is ' + fmtM(r.c2cM, su) + '.';
  }

  function copyText(text, btn) {
    var done = function () { flashButton(btn, '<i class="fa fa-check"></i> Copied'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
    } else {
      fallbackCopy(text, done);
    }
  }

  function copyResults() {
    if (!lastResult) return;
    var lines = [elementWord(true, true) + ' layout', summaryText(), ''];
    resultRows().forEach(function (row) { lines.push(row.label + ': ' + row.value); });
    copyText(lines.join('\n'), els.copyBtn);
  }

  function copyMarks() {
    if (!lastResult) return;
    var rows = markRows(lastResult);
    var lines = ['Tape-measure marks (from the start of the run):'];
    rows.forEach(function (row) {
      lines.push('Pillar ' + row.n + ': ' + row.start + ' to ' + row.end);
    });
    copyText(lines.join('\n'), els.copyMarksBtn);
  }

  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
    document.body.removeChild(ta);
  }

  function flashButton(btn, html) {
    if (!btn) return;
    var original = btn.innerHTML;
    btn.innerHTML = html;
    btn.disabled = true;
    setTimeout(function () { btn.innerHTML = original; btn.disabled = false; }, 1500);
  }

  // SVG → PNG data URI (returns a Promise).
  function diagramPng(scaleFactor) {
    return new Promise(function (resolve, reject) {
      var svgEl = els.diagram.querySelector('svg');
      if (!svgEl) { reject(new Error('No diagram')); return; }
      var s = scaleFactor || 2;
      var vb = svgEl.viewBox.baseVal;
      var w = (vb && vb.width) ? vb.width : 1000;
      var h = (vb && vb.height) ? vb.height : 260;
      var xml = new XMLSerializer().serializeToString(svgEl);
      var svg64 = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
      var img = new Image();
      img.onload = function () {
        var canvas = document.createElement('canvas');
        canvas.width = w * s;
        canvas.height = h * s;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        resolve(canvas.toDataURL('image/png'));
      };
      img.onerror = function () { reject(new Error('Render failed')); };
      img.src = svg64;
    });
  }

  function exportImage() {
    diagramPng(2).then(function (dataUrl) {
      var a = document.createElement('a');
      a.href = dataUrl;
      a.download = elementWord(true) + '-layout.png';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }).catch(function () { window.alert('Could not generate the image.'); });
  }

  function exportPdf() {
    if (!lastResult) return;
    diagramPng(2).then(function (dataUrl) {
      submitPdfForm(dataUrl);
    }).catch(function () { submitPdfForm(''); });
  }

  function submitPdfForm(diagram) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = root.getAttribute('data-pdf-url');
    form.target = '_blank';
    form.style.display = 'none';

    function add(name, value) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }

    add('_token', csrf());
    add('title', elementWord(true, true) + ' Layout');
    add('summary', summaryText());
    if (diagram) add('diagram', diagram);
    resultRows().forEach(function (row, i) {
      add('rows[' + i + '][label]', row.label);
      add('rows[' + i + '][value]', row.value);
    });
    markRows(lastResult).forEach(function (row, i) {
      add('marks[' + i + '][n]', row.n);
      add('marks[' + i + '][start]', row.start);
      add('marks[' + i + '][end]', row.end);
    });

    document.body.appendChild(form);
    form.submit();
    setTimeout(function () { document.body.removeChild(form); }, 1000);
  }

  // ── Save / load / delete ────────────────────────────────────────────
  function currentPayload() {
    return {
      mode: state.mode,
      length_unit: lengthUnit(),
      thickness_unit: thicknessUnit(),
      gap_unit: gapUnit(),
      length: els.length.value,
      thickness: els.thickness.value,
      spacing: els.spacing.value,
      count: els.count.value,
      placement: els.placement.value,
      summary: summaryText()
    };
  }

  function saveCalculation() {
    if (!lastResult) return;
    var name = (els.saveName.value || '').trim();
    if (!name) {
      showSaveFeedback('Give this calculation a name first.', false);
      els.saveName.focus();
      return;
    }

    var btn = root.querySelector('[data-ps-save]');
    if (btn) { btn.disabled = true; }

    fetch(root.getAttribute('data-save-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({
        tool: 'pillar_spindle',
        name: name,
        payload: currentPayload()
      })
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function (data) {
        if (btn) btn.disabled = false;
        els.saveName.value = '';
        showSaveFeedback('Saved "' + data.calculation.name + '".', true);
        prependSaved(data.calculation);
      })
      .catch(function () {
        if (btn) btn.disabled = false;
        showSaveFeedback('Could not save. Please try again.', false);
      });
  }

  function showSaveFeedback(msg, ok) {
    if (!els.saveFeedback) return;
    els.saveFeedback.textContent = msg;
    els.saveFeedback.className = 'util-ps-save-feedback ' + (ok ? 'is-ok' : 'is-error');
    show(els.saveFeedback, true);
    setTimeout(function () { show(els.saveFeedback, false); }, 3500);
  }

  function savedItemHtml(c) {
    var payload = c.payload || {};
    var summary = payload.summary || '';
    var li = document.createElement('li');
    li.className = 'util-ps-saved-item';
    li.setAttribute('data-id', c.id);
    li.innerHTML =
      '<div class="util-ps-saved-item__main">' +
        '<span class="util-ps-saved-item__name"></span>' +
        '<span class="util-ps-saved-item__summary"></span>' +
        '<span class="util-ps-saved-item__date"></span>' +
      '</div>' +
      '<div class="util-ps-saved-item__actions">' +
        '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ps-load><i class="fa fa-upload"></i> Load</button>' +
        '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-ps-delete><i class="fa fa-trash"></i></button>' +
      '</div>';
    li.querySelector('.util-ps-saved-item__name').textContent = c.name;
    li.querySelector('.util-ps-saved-item__summary').textContent = summary;
    li.querySelector('.util-ps-saved-item__date').textContent = c.created_label || '';
    li.querySelector('[data-ps-load]').addEventListener('click', function () { loadPayload(payload); });
    li.querySelector('[data-ps-delete]').addEventListener('click', function () { deleteSaved(c.id, li); });
    return li;
  }

  function prependSaved(c) {
    els.savedList.insertBefore(savedItemHtml(c), els.savedList.firstChild);
    refreshSavedEmpty();
  }

  function refreshSavedEmpty() {
    var has = els.savedList.children.length > 0;
    show(els.savedEmpty, !has);
  }

  function deleteSaved(id, li) {
    if (!window.confirm('Delete this saved calculation?')) return;
    var url = root.getAttribute('data-destroy-template').replace('__ID__', id);
    fetch(url, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      }
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function () { li.remove(); refreshSavedEmpty(); })
      .catch(function () { window.alert('Could not delete. Please try again.'); });
  }

  function loadPayload(p) {
    if (!p) return;
    // `unit` is the legacy single-unit key from older saved calculations.
    if (p.length_unit || p.unit) els.unit.value = p.length_unit || p.unit;
    if (p.thickness_unit || p.unit) els.thicknessUnit.value = p.thickness_unit || p.unit;
    if (p.gap_unit || p.unit) els.gapUnit.value = p.gap_unit || p.unit;
    els.length.value = p.length != null ? p.length : '';
    els.thickness.value = p.thickness != null ? p.thickness : '';
    els.spacing.value = p.spacing != null ? p.spacing : '';
    els.count.value = p.count != null ? p.count : '';
    if (p.placement) els.placement.value = p.placement;
    setMode(p.mode === 'count' ? 'count' : 'spacing');
    render();
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ── Toggles + labels ────────────────────────────────────────────────
  function setActive(group, attr, value) {
    if (!group) return;
    group.querySelectorAll('.util-segmented__btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute(attr) === value);
    });
  }

  function setMode(value) {
    state.mode = value;
    setActive(els.modeToggle, 'data-mode', value);
    els.modeFields.forEach(function (field) {
      show(field, field.getAttribute('data-ps-mode-field') === value);
    });
  }

  // ── Wire up events ──────────────────────────────────────────────────
  [els.length, els.thickness, els.spacing, els.count].forEach(function (input) {
    input.addEventListener('input', render);
  });
  [els.placement, els.unit, els.thicknessUnit, els.gapUnit].forEach(function (sel) {
    sel.addEventListener('change', render);
  });

  els.modeToggle.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-mode]');
    if (!btn) return;
    setMode(btn.getAttribute('data-mode'));
    render();
  });

  root.querySelector('[data-ps-reset]').addEventListener('click', function () {
    els.length.value = '';
    els.thickness.value = '';
    els.spacing.value = '';
    els.count.value = '';
    els.placement.value = 'both';
    els.unit.value = 'ft';
    els.thicknessUnit.value = 'in';
    els.gapUnit.value = 'in';
    setMode('spacing');
    render();
  });

  if (els.copyBtn) els.copyBtn.addEventListener('click', copyResults);
  if (els.copyMarksBtn) els.copyMarksBtn.addEventListener('click', copyMarks);
  root.querySelector('[data-ps-export-image]').addEventListener('click', exportImage);
  root.querySelector('[data-ps-export-pdf]').addEventListener('click', exportPdf);
  root.querySelector('[data-ps-save]').addEventListener('click', saveCalculation);
  els.saveName.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); saveCalculation(); }
  });

  // ── Hydrate saved list ──────────────────────────────────────────────
  (function hydrate() {
    var raw = root.getAttribute('data-saved');
    var saved = [];
    try { saved = JSON.parse(raw) || []; } catch (e) { saved = []; }
    saved.forEach(function (c) { els.savedList.appendChild(savedItemHtml(c)); });
    refreshSavedEmpty();
  })();

  // ── Init ────────────────────────────────────────────────────────────
  setMode('spacing');
  render();
})();

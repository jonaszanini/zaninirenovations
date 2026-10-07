/* Lumber / Board Material Calculator — multi board-type bin-packing. */
(function () {
  'use strict';

  var root = document.querySelector('[data-lumber-board]');
  if (!root) return;

  var PIECE_COLORS = ['#024768', '#2a7a9b', '#3d8b6e', '#5c6bc0', '#8d6e63', '#6a8f3a'];

  var els = {
    boardTypes: root.querySelector('[data-lb-board-types]'),
    typeResults: root.querySelector('[data-lb-type-results]'),
    globalSummary: root.querySelector('[data-lb-global-summary]'),
    globalBoards: root.querySelector('[data-lb-global-boards]'),
    globalCost: root.querySelector('[data-lb-global-cost]'),
    globalWaste: root.querySelector('[data-lb-global-waste]'),
    globalWasteWrap: root.querySelector('[data-lb-global-waste-wrap]'),
    globalBreakdown: root.querySelector('[data-lb-global-breakdown]'),
    roundUp: root.querySelector('[data-lb-round-up]'),
    kerf: root.querySelector('[data-lb-kerf]'),
    empty: root.querySelector('[data-lb-empty]'),
    diagramCard: root.querySelector('[data-lb-diagram-card]'),
    diagrams: root.querySelector('[data-lb-diagrams]'),
    saveCard: root.querySelector('[data-lb-save-card]'),
    saveName: root.querySelector('#lb-save-name'),
    linkProject: root.querySelector('[data-lb-link-project]'),
    saveFeedback: root.querySelector('[data-lb-save-feedback]'),
    copyBtn: root.querySelector('[data-lb-copy]'),
    resultsActions: root.querySelector('[data-lb-results-actions]'),
    savedList: root.querySelector('[data-lb-saved-list]'),
    savedEmpty: root.querySelector('[data-lb-saved-empty]'),
    useOffcuts: root.querySelector('[data-lb-use-offcuts]'),
    minOffcut: root.querySelector('[data-lb-min-offcut]'),
    importStatus: root.querySelector('[data-lb-import-status]'),
    offcutUsed: root.querySelector('[data-lb-offcut-used]'),
    saveLeftoversWrap: root.querySelector('[data-lb-save-leftovers-wrap]'),
    saveLeftoversBtn: root.querySelector('[data-lb-save-leftovers]'),
    leftoversFeedback: root.querySelector('[data-lb-leftovers-feedback]'),
    sheetCard: root.querySelector('[data-lb-sheet-card]'),
    sheetGroups: root.querySelector('[data-lb-sheet-groups]'),
    sheetUseOffcuts: root.querySelector('[data-lb-sheet-use-offcuts]'),
    runSheetsBtn: root.querySelector('[data-lb-run-sheets]'),
    saveSheetLeftoversBtn: root.querySelector('[data-lb-save-sheet-leftovers]'),
    sheetUsed: root.querySelector('[data-lb-sheet-used]'),
    sheetLayouts: root.querySelector('[data-lb-sheet-layouts]')
  };

  var state = {
    btIdSeq: 1,
    stockIdSeq: 1,
    loadedCalculationId: null,
    dragBtId: null
  };

  var lastResults = null;
  var renderScheduled = false;
  var initialLinkProjectId = root.getAttribute('data-link-project-id') || '';
  var offcutCache = { linear: [], sheets: [] };
  var pendingLeftovers = [];
  var sheetState = { groups: [], last: null };

  /* ── Utilities ───────────────────────────────────────────────────── */

  function csrf() {
    var input = root.querySelector('#util-lb-csrf');
    if (input && input.value) return input.value;
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function show(el, on) {
    if (!el) return;
    if (on) el.removeAttribute('hidden');
    else el.setAttribute('hidden', '');
  }

  function parseNum(v) {
    var n = parseFloat(String(v).replace(',', '.'));
    return isFinite(n) ? n : 0;
  }

  function parseMoney(v) {
    var n = parseFloat(String(v == null ? '' : v).replace(/,/g, ''));
    return isFinite(n) ? n : 0;
  }

  function toSixteenths(val, unit) {
    var n = parseNum(val);
    if (n <= 0) return 0;
    if (unit === 'ft') return Math.round(n * 12 * 16);
    return Math.round(n * 16);
  }

  function sixteenthsToInches(six) {
    return six / 16;
  }

  function reduceFrac(n, d) {
    var a = n;
    var b = d;
    while (b) { var t = a % b; a = b; b = t; }
    return (n / a) + '/' + (d / a);
  }

  function formatInches(inches) {
    var whole = Math.floor(inches);
    var fracSix = Math.round((inches - whole) * 16);
    if (fracSix === 16) { whole += 1; fracSix = 0; }
    if (fracSix === 0) return String(whole);
    return whole > 0 ? whole + '-' + reduceFrac(fracSix, 16) : reduceFrac(fracSix, 16);
  }

  function formatLength(six, unit) {
    if (!six || six <= 0) return '—';
    if (unit === 'in') {
      return formatInches(sixteenthsToInches(six)) + ' in';
    }
    var totalIn = six / 16;
    var ft = Math.floor(totalIn / 12);
    var remIn = totalIn - ft * 12;
    var wholeIn = Math.floor(remIn);
    var fracSix = Math.round((remIn - wholeIn) * 16);
    if (fracSix === 16) { wholeIn += 1; fracSix = 0; }
    if (wholeIn === 12) { ft += 1; wholeIn = 0; }
    var parts = [];
    if (ft > 0) parts.push(ft + '′');
    if (wholeIn > 0 || fracSix === 0) {
      if (wholeIn > 0 || ft === 0) parts.push(wholeIn + '″');
    }
    if (fracSix > 0) {
      var frac = reduceFrac(fracSix, 16);
      if (parts.length && (wholeIn > 0 || ft > 0)) {
        parts[parts.length - 1] = (wholeIn > 0 ? wholeIn : '') + '-' + frac;
        if (ft > 0 && wholeIn === 0) parts.push(frac + '″');
        else if (wholeIn > 0) parts[parts.length - 1] = wholeIn + '-' + frac + '″';
      } else {
        parts.push(frac + '″');
      }
    }
    return parts.join(' ') || '0';
  }

  function formatDiagramCutLength(six, pieceUnit) {
    if (!six || six <= 0) return '';
    if (pieceUnit === 'in') {
      return formatInches(sixteenthsToInches(six)) + '\u2033';
    }
    return formatLength(six, 'ft');
  }

  function formatMoney(n) {
    if (!isFinite(n) || n <= 0) return '—';
    var parts = (Math.round(n * 100) / 100).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return '$' + parts.join('.');
  }

  function setStatValue(el, primary, pct) {
    if (!el) return;
    el.textContent = '';
    var main = document.createElement('span');
    main.className = 'util-lb-stat__primary';
    main.textContent = primary;
    el.appendChild(main);
    if (pct) {
      var extra = document.createElement('span');
      extra.className = 'util-lb-stat__pct';
      extra.textContent = pct;
      el.appendChild(extra);
    }
  }

  function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeXml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function setActive(group, attr, value) {
    if (!group) return;
    group.querySelectorAll('.util-segmented__btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute(attr) === value);
    });
  }

  function projectUrl(id) {
    if (!id) return '#';
    return (root.getAttribute('data-project-url-template') || '').replace('__ID__', String(id));
  }

  function setLinkProject(id) {
    if (!els.linkProject) return;
    els.linkProject.value = id ? String(id) : '';
  }

  function updateSaveUi() {
    var editing = !!state.loadedCalculationId;
    var saveBtn = root.querySelector('[data-lb-save]');
    var saveCardTitle = els.saveCard && els.saveCard.querySelector('.util-lb-card-title');
    if (saveBtn) {
      saveBtn.innerHTML = editing
        ? '<i class="fa fa-save"></i> Update'
        : '<i class="fa fa-save"></i> Save';
    }
    if (saveCardTitle) {
      saveCardTitle.textContent = editing ? 'Update this calculation' : 'Save this calculation';
    }
  }

  function savedListLabel(c) {
    return c.saved_label || c.created_label || '';
  }

  function kerfSixteenths() {
    return els.kerf && els.kerf.checked ? 2 : 0;
  }

  function scheduleRender() {
    if (renderScheduled) return;
    renderScheduled = true;
    requestAnimationFrame(function () {
      renderScheduled = false;
      render();
    });
  }

  function nextBtId() {
    return 'bt' + (state.btIdSeq++);
  }

  function nextStockId() {
    return 's' + (state.stockIdSeq++);
  }

  /* ── Bin-packing (unchanged core) ────────────────────────────────── */

  function binUsedSix(segments, kerf) {
    var pieces = segments.filter(function (s) { return !s.isWaste; });
    if (!pieces.length) return 0;
    var used = 0;
    pieces.forEach(function (seg, i) {
      used += seg.len;
      if (i < pieces.length - 1) used += kerf;
    });
    return used;
  }

  function packBestFit(pieces, stockSix, kerf) {
    var bins = [];
    pieces.forEach(function (piece) {
      var bestBin = null;
      var bestRemain = Infinity;
      bins.forEach(function (bin) {
        var used = binUsedSix(bin.segments, kerf);
        var kerfCost = bin.segments.length ? kerf : 0;
        var need = piece.len + kerfCost;
        var remain = bin.stockLen - used;
        if (need <= remain && remain - need < bestRemain) {
          bestRemain = remain - need;
          bestBin = bin;
        }
      });
      if (!bestBin) {
        bestBin = { stockLen: stockSix, segments: [] };
        bins.push(bestBin);
      }
      bestBin.segments.push({ len: piece.len, label: piece.label, isWaste: false });
    });
    bins.forEach(function (bin) {
      var used = binUsedSix(bin.segments, kerf);
      var waste = bin.stockLen - used;
      if (waste > 0) {
        bin.segments.push({ len: waste, label: 'Waste', isWaste: true });
      }
    });
    return bins;
  }

  function packMultiStock(pieces, stocks, kerf) {
    var stocksAsc = stocks.slice().sort(function (a, b) { return a.lengthSix - b.lengthSix; });
    var openBins = [];
    pieces.forEach(function (piece) {
      var bestBin = null;
      var bestRemain = Infinity;
      openBins.forEach(function (bin) {
        var used = binUsedSix(bin.segments, kerf);
        var kerfCost = bin.segments.length ? kerf : 0;
        var need = piece.len + kerfCost;
        var remain = bin.stockLen - used;
        if (need <= remain && remain - need < bestRemain) {
          bestRemain = remain - need;
          bestBin = bin;
        }
      });
      if (!bestBin) {
        var stock = stocksAsc.find(function (s) { return s.lengthSix >= piece.len; });
        if (!stock) return;
        bestBin = { stockLen: stock.lengthSix, stockId: stock.id, segments: [] };
        openBins.push(bestBin);
      }
      bestBin.segments.push({ len: piece.len, label: piece.label, isWaste: false });
    });
    openBins.forEach(function (bin) {
      var used = binUsedSix(bin.segments, kerf);
      var waste = bin.stockLen - used;
      if (waste > 0) {
        bin.segments.push({ len: waste, label: 'Waste', isWaste: true });
      }
    });
    var groups = {};
    openBins.forEach(function (bin) {
      var key = String(bin.stockLen);
      if (!groups[key]) {
        var stock = stocks.find(function (s) { return s.lengthSix === bin.stockLen; });
        groups[key] = { stockId: stock ? stock.id : key, lengthSix: bin.stockLen, bins: [] };
      }
      groups[key].bins.push(bin);
    });
    return Object.keys(groups).map(function (k) { return groups[k]; });
  }

  function solutionStats(groups, stocks, kerf) {
    var purchased = 0;
    var waste = 0;
    var boards = 0;
    var cost = 0;
    groups.forEach(function (g) {
      var count = g.count != null ? g.count : g.bins.length;
      boards += count;
      purchased += count * g.lengthSix;
      g.bins.forEach(function (bin, i) {
        if (i >= count) return;
        waste += (bin.segments.filter(function (s) { return s.isWaste; })[0] || { len: 0 }).len;
      });
      var stock = stocks.find(function (s) { return s.id === g.stockId || s.lengthSix === g.lengthSix; });
      if (stock && stock.price > 0) cost += count * stock.price;
    });
    if (groups.some(function (g) { return g.count != null && g.count > g.bins.length; })) {
      groups.forEach(function (g) {
        var count = g.count != null ? g.count : g.bins.length;
        if (count > g.bins.length) {
          waste += (count - g.bins.length) * g.lengthSix;
        }
      });
    }
    return { purchased: purchased, waste: waste, boards: boards, cost: cost, wastePct: purchased > 0 ? (waste / purchased) * 100 : 0 };
  }

  function matchingLinearOffcuts(materialKey) {
    var key = String(materialKey || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    var rows = offcutCache.linear || [];
    if (!rows.length) return [];
    if (!key) return rows.slice();
    var exact = rows.filter(function (row) {
      var mk = String(row.material_key || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
      return mk === key || (mk && key && (mk.indexOf(key) !== -1 || key.indexOf(mk) !== -1));
    });
    return exact.length ? exact : rows.slice();
  }

  function packOntoOffcuts(pieces, offcuts, kerf) {
    var bins = [];
    offcuts.forEach(function (oc) {
      var qty = Math.max(1, parseInt(oc.qty, 10) || 1);
      var lenSix = toSixteenths(oc.length_in, 'in');
      if (lenSix <= 0) return;
      for (var i = 0; i < qty; i++) {
        bins.push({
          stockLen: lenSix,
          stockId: 'offcut-' + oc.id + '-' + i,
          offcutId: oc.id,
          isOffcut: true,
          segments: [],
          materialKey: oc.material_key
        });
      }
    });
    var remaining = [];
    pieces.forEach(function (piece) {
      var bestBin = null;
      var bestRemain = Infinity;
      bins.forEach(function (bin) {
        var used = binUsedSix(bin.segments, kerf);
        var kerfCost = bin.segments.length ? kerf : 0;
        var need = piece.len + kerfCost;
        var remain = bin.stockLen - used;
        if (need <= remain && remain - need < bestRemain) {
          bestRemain = remain - need;
          bestBin = bin;
        }
      });
      if (bestBin) {
        bestBin.segments.push({ len: piece.len, label: piece.label, isWaste: false });
      } else {
        remaining.push(piece);
      }
    });
    var usedBins = bins.filter(function (bin) { return bin.segments.length > 0; });
    usedBins.forEach(function (bin) {
      var used = binUsedSix(bin.segments, kerf);
      var waste = bin.stockLen - used;
      if (waste > 0) {
        bin.segments.push({ len: waste, label: 'Waste', isWaste: true });
      }
    });
    var usedIds = [];
    usedBins.forEach(function (bin) {
      if (bin.offcutId && usedIds.indexOf(bin.offcutId) === -1) usedIds.push(bin.offcutId);
    });
    return { bins: usedBins, remaining: remaining, usedOffcutIds: usedIds };
  }

  function solve(pieces, stocks, mode, singleStockId, manualCounts, stockUnit, kerf) {
    if (!pieces.length || !stocks.length) return null;
    var maxPiece = Math.max.apply(null, pieces.map(function (p) { return p.len; }));
    var maxStock = Math.max.apply(null, stocks.map(function (s) { return s.lengthSix; }));
    if (maxPiece > maxStock) {
      return { error: 'One or more pieces are longer than the longest available board (' + formatLength(maxStock, stockUnit) + ').' };
    }

    if (mode === 'single') {
      var stock = stocks.find(function (s) { return s.id === singleStockId; });
      if (!stock) return { error: 'Select a board length for single-board mode.' };
      if (maxPiece > stock.lengthSix) {
        return { error: 'Some pieces are longer than the selected board (' + formatLength(stock.lengthSix, stockUnit) + ').' };
      }
      var bins = packBestFit(pieces, stock.lengthSix, kerf);
      var groups = [{ stockId: stock.id, lengthSix: stock.lengthSix, bins: bins, count: manualCounts[stock.id] || bins.length }];
      var stats = solutionStats(groups, stocks, kerf);
      return {
        strategy: 'Single board length — ' + formatLength(stock.lengthSix, stockUnit),
        groups: groups,
        stats: stats,
        stocks: stocks
      };
    }

    var candidates = [];
    stocks.forEach(function (stock) {
      var sbins = packBestFit(pieces, stock.lengthSix, kerf);
      var sgroups = [{ stockId: stock.id, lengthSix: stock.lengthSix, bins: sbins }];
      var sstats = solutionStats(sgroups, stocks, kerf);
      candidates.push({
        strategy: 'All ' + formatLength(stock.lengthSix, stockUnit) + ' boards',
        groups: sgroups,
        stats: sstats,
        waste: sstats.waste
      });
    });

    var multiGroups = packMultiStock(pieces, stocks, kerf);
    multiGroups.forEach(function (g) {
      if (manualCounts[g.stockId] != null) g.count = manualCounts[g.stockId];
    });
    var multiStats = solutionStats(multiGroups, stocks, kerf);
    candidates.push({
      strategy: 'Mixed board lengths',
      groups: multiGroups,
      stats: multiStats,
      waste: multiStats.waste
    });

    candidates.sort(function (a, b) {
      return a.waste - b.waste || a.stats.boards - b.stats.boards || a.stats.purchased - b.stats.purchased;
    });

    var best = candidates[0];
    best.groups.forEach(function (g) {
      if (manualCounts[g.stockId] != null) g.count = manualCounts[g.stockId];
    });
    best.stats = solutionStats(best.groups, stocks, kerf);
    best.stocks = stocks;
    best.suggestion = candidates.length > 1 ? candidates[1].strategy : '';
    return best;
  }

  function wasteClass(pct) {
    if (pct < 5) return 'is-low';
    if (pct < 15) return 'is-mid';
    return 'is-high';
  }

  /* ── Board type DOM helpers ───────────────────────────────────────── */

  function getBoardTypeCards() {
    return Array.prototype.slice.call(els.boardTypes.querySelectorAll('[data-lb-board-type]'));
  }

  function getBoardTypeCard(btId) {
    return els.boardTypes.querySelector('[data-lb-board-type][data-bt-id="' + btId + '"]');
  }

  function readBoardTypeMeta(card) {
    var stockUnitBtn = card.querySelector('[data-lb-stock-unit-toggle] .util-segmented__btn.is-active');
    var pieceUnitBtn = card.querySelector('[data-lb-piece-unit-toggle] .util-segmented__btn.is-active');
    var modeBtn = card.querySelector('[data-lb-mode-toggle] .util-segmented__btn.is-active');
    var nameInput = card.querySelector('[data-lb-bt-name]');
    var singleStock = card.querySelector('[data-lb-single-stock]');
    return {
      id: card.getAttribute('data-bt-id'),
      name: nameInput ? nameInput.value.trim() : '',
      materialKey: (card.getAttribute('data-material-key') || '').trim(),
      stockUnit: stockUnitBtn ? stockUnitBtn.getAttribute('data-stock-unit') : 'ft',
      pieceUnit: pieceUnitBtn ? pieceUnitBtn.getAttribute('data-piece-unit') : 'in',
      mode: modeBtn ? modeBtn.getAttribute('data-mode') : 'minimize',
      singleStockId: singleStock ? singleStock.value : '',
      collapsed: card.classList.contains('is-collapsed')
    };
  }

  function stockRows(card, stockUnit) {
    var rows = [];
    card.querySelectorAll('[data-lb-stock-row]').forEach(function (row) {
      var id = row.getAttribute('data-id');
      var lenInput = row.querySelector('[data-lb-stock-length]');
      var priceInput = row.querySelector('[data-lb-stock-price]');
      var lenVal = lenInput ? lenInput.value : '';
      var six = toSixteenths(lenVal, stockUnit);
      if (six > 0) {
        rows.push({
          id: id,
          lengthVal: lenVal,
          lengthSix: six,
          price: parseMoney(priceInput ? priceInput.value : '')
        });
      }
    });
    return rows.sort(function (a, b) { return a.lengthSix - b.lengthSix; });
  }

  function pieceRows(card, pieceUnit) {
    var rows = [];
    card.querySelectorAll('[data-lb-piece-row]').forEach(function (row) {
      var desc = row.querySelector('[data-lb-piece-desc]');
      var len = row.querySelector('[data-lb-piece-length]');
      var qty = row.querySelector('[data-lb-piece-qty]');
      var q = parseInt(qty && qty.value ? qty.value : '0', 10);
      if (els.roundUp && els.roundUp.checked && q > 0) {
        q = Math.ceil(q);
        if (qty) qty.value = String(q);
      }
      rows.push({
        desc: desc ? desc.value.trim() : '',
        lengthVal: len ? len.value : '',
        lengthSix: toSixteenths(len ? len.value : '', pieceUnit),
        qty: q
      });
    });
    return rows;
  }

  function expandPieces(rows) {
    var pieces = [];
    rows.forEach(function (row, ri) {
      if (!row.lengthSix || row.qty < 1) return;
      for (var i = 0; i < row.qty; i++) {
        pieces.push({
          len: row.lengthSix,
          label: row.desc || ('Piece ' + (ri + 1))
        });
      }
    });
    return pieces.sort(function (a, b) { return b.len - a.len; });
  }

  function getManualCounts(card) {
    var counts = {};
    try {
      counts = JSON.parse(card.getAttribute('data-manual-counts') || '{}');
    } catch (e) { counts = {}; }
    return counts;
  }

  function setManualCount(card, stockId, n) {
    var counts = getManualCounts(card);
    counts[stockId] = n;
    card.setAttribute('data-manual-counts', JSON.stringify(counts));
  }

  function syncSingleStockOptions(card, stockUnit) {
    var select = card.querySelector('[data-lb-single-stock]');
    if (!select) return;
    var current = select.value;
    var stocks = stockRows(card, stockUnit);
    select.innerHTML = '<option value="">— Select board length —</option>';
    stocks.forEach(function (s) {
      var opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = formatLength(s.lengthSix, stockUnit);
      select.appendChild(opt);
    });
    if (current && stocks.some(function (s) { return s.id === current; })) {
      select.value = current;
    }
  }

  function updateUnitLabelsInCard(card, stockUnit, pieceUnit) {
    var stockTag = stockUnit === 'ft' ? 'ft' : 'in';
    var pieceTag = pieceUnit === 'ft' ? 'ft' : 'in';
    card.querySelectorAll('[data-lb-stock-list] .util-lb-unit-tag').forEach(function (el) {
      el.textContent = stockTag;
    });
    card.querySelectorAll('[data-lb-pieces-body] .util-lb-unit-tag').forEach(function (el) {
      el.textContent = pieceTag;
    });
    card.querySelectorAll('[data-quick-ft]').forEach(function (btn) {
      btn.hidden = stockUnit !== 'ft';
    });
  }

  function buildStockRow(card, data, stockUnit) {
    var id = data && data.id ? data.id : nextStockId();
    var row = document.createElement('tr');
    row.setAttribute('data-lb-stock-row', '');
    row.setAttribute('data-id', id);
    var unitLabel = stockUnit === 'ft' ? 'ft' : 'in';
    row.innerHTML =
      '<td>' +
        '<div class="util-lb-stock-length-cell">' +
          '<input type="number" min="0" step="any" inputmode="decimal" placeholder="Length" data-lb-stock-length value="' + (data && data.lengthVal != null ? escapeAttr(data.lengthVal) : '') + '">' +
          '<span class="util-lb-unit-tag">' + unitLabel + '</span>' +
        '</div>' +
      '</td>' +
      '<td>' +
        '<input type="text" class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" placeholder="Optional" data-lb-stock-price value="' + (data && data.price != null ? escapeAttr(data.price) : '') + '">' +
      '</td>' +
      '<td class="util-lb-col-action">' +
        '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm util-lb-row-remove" data-lb-remove-stock title="Remove" aria-label="Remove board length"><i class="fa fa-times"></i></button>' +
      '</td>';
    row.querySelector('[data-lb-remove-stock]').addEventListener('click', function () {
      var counts = getManualCounts(card);
      delete counts[id];
      card.setAttribute('data-manual-counts', JSON.stringify(counts));
      row.remove();
      syncSingleStockOptions(card, stockUnit);
      scheduleRender();
    });
    if (window.cpMoneyMask) {
      window.cpMoneyMask.wire(row.querySelector('[data-lb-stock-price]'));
    }
    return row;
  }

  function buildPieceRow(card, data, pieceUnit) {
    var row = document.createElement('tr');
    row.setAttribute('data-lb-piece-row', '');
    var pieceUnitLabel = pieceUnit === 'ft' ? 'ft' : 'in';
    row.innerHTML =
      '<td class="util-lb-piece-desc"><input type="text" placeholder="Description" data-lb-piece-desc value="' + (data && data.desc ? escapeAttr(data.desc) : '') + '"></td>' +
      '<td class="util-lb-piece-len">' +
        '<div class="util-lb-stock-length-cell">' +
          '<input type="number" min="0" step="any" inputmode="decimal" placeholder="Length" data-lb-piece-length value="' + (data && data.lengthVal != null ? escapeAttr(data.lengthVal) : '') + '">' +
          '<span class="util-lb-unit-tag">' + pieceUnitLabel + '</span>' +
        '</div>' +
      '</td>' +
      '<td class="util-lb-piece-qty"><input type="number" min="1" step="1" inputmode="numeric" placeholder="Qty" data-lb-piece-qty value="' + (data && data.qty != null ? escapeAttr(data.qty) : '1') + '"></td>' +
      '<td class="util-lb-col-action"><button type="button" class="admin-btn admin-btn--danger admin-btn--sm util-lb-row-remove" data-lb-remove-piece title="Remove" aria-label="Remove piece"><i class="fa fa-times"></i></button></td>';
    row.querySelector('[data-lb-remove-piece]').addEventListener('click', function () {
      row.remove();
      scheduleRender();
    });
    return row;
  }

  function defaultBoardTypeData(overrides) {
    var id = overrides && overrides.id ? overrides.id : nextBtId();
    if (overrides && overrides.id) {
      var num = parseInt(String(overrides.id).replace(/\D/g, ''), 10);
      if (num >= state.btIdSeq) state.btIdSeq = num + 1;
    }
    return Object.assign({
      id: id,
      name: '',
      material_key: '',
      stock_unit: 'ft',
      piece_unit: 'in',
      mode: 'minimize',
      single_stock_id: '',
      collapsed: false,
      stocks: [{ lengthVal: '8' }, { lengthVal: '10' }, { lengthVal: '12' }, { lengthVal: '16' }],
      pieces: [{ qty: 1 }],
      manual_counts: {}
    }, overrides || {});
  }

  function buildBoardTypeCard(data) {
    data = defaultBoardTypeData(data);
    var card = document.createElement('article');
    card.className = 'util-lb-bt-card' + (data.collapsed ? ' is-collapsed' : '');
    card.setAttribute('data-lb-board-type', '');
    card.setAttribute('data-bt-id', data.id);
    card.setAttribute('data-material-key', data.material_key || '');
    card.setAttribute('data-manual-counts', JSON.stringify(data.manual_counts || {}));
    card.setAttribute('draggable', 'true');

    card.innerHTML =
      '<header class="util-lb-bt-head">' +
        '<button type="button" class="util-lb-bt-drag" data-lb-bt-drag aria-label="Drag to reorder" title="Drag to reorder"><i class="fa fa-bars"></i></button>' +
        '<button type="button" class="util-lb-bt-toggle" data-lb-bt-toggle aria-expanded="' + (!data.collapsed) + '"><i class="fa fa-chevron-down"></i></button>' +
        '<input type="text" class="util-lb-bt-name" data-lb-bt-name maxlength="80" placeholder="Board type name (e.g. 2×6 Joists)" value="' + escapeAttr(data.name || '') + '">' +
        '<div class="util-lb-bt-head-actions">' +
          // Drag-and-drop (via [data-lb-bt-drag]) only works with a mouse —
          // HTML5 DnD is not reliably touch-capable (notably iOS Safari).
          // These arrow buttons give touch users an equivalent reorder path.
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-bt-move-up title="Move up" aria-label="Move board type up"><i class="fa fa-arrow-up"></i></button>' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-bt-move-down title="Move down" aria-label="Move board type down"><i class="fa fa-arrow-down"></i></button>' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-bt-duplicate title="Duplicate"><i class="fa fa-copy"></i></button>' +
          '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-lb-bt-remove title="Remove"><i class="fa fa-times"></i></button>' +
        '</div>' +
      '</header>' +
      '<div class="util-lb-bt-body">' +
        '<div class="admin-form-group">' +
          '<span class="admin-form-group-label">Calculation mode' +
            '<span class="util-tip" tabindex="0" data-tip="Minimize waste compares every stock length and a mixed-length plan. Single board length uses only the length you select below.">' +
              '<i class="fa fa-info-circle"></i>' +
            '</span>' +
          '</span>' +
          '<div class="util-segmented" role="group" aria-label="Calculation mode" data-lb-mode-toggle>' +
            '<button type="button" class="util-segmented__btn' + (data.mode !== 'single' ? ' is-active' : '') + '" data-mode="minimize">Minimize waste</button>' +
            '<button type="button" class="util-segmented__btn' + (data.mode === 'single' ? ' is-active' : '') + '" data-mode="single">Single board length</button>' +
          '</div>' +
        '</div>' +
        '<div class="admin-form-group" data-lb-mode-field="single"' + (data.mode === 'single' ? '' : ' hidden') + '>' +
          '<label>Board length to use</label>' +
          '<select data-lb-single-stock><option value="">— Select board length —</option></select>' +
        '</div>' +
        '<div class="util-lb-section-block">' +
          '<div class="util-lb-section-head-inline">' +
            '<span class="util-lb-section-label">Available board lengths</span>' +
            '<div class="util-segmented util-segmented--compact" role="group" aria-label="Board length units" data-lb-stock-unit-toggle>' +
              '<button type="button" class="util-segmented__btn' + (data.stock_unit !== 'in' ? ' is-active' : '') + '" data-stock-unit="ft">Feet</button>' +
              '<button type="button" class="util-segmented__btn' + (data.stock_unit === 'in' ? ' is-active' : '') + '" data-stock-unit="in">Inches</button>' +
            '</div>' +
          '</div>' +
          '<div class="util-lb-quick-btns">' +
            '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="8">8′</button>' +
            '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="10">10′</button>' +
            '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="12">12′</button>' +
            '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="16">16′</button>' +
            '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-quick-ft="20">20′</button>' +
          '</div>' +
        '</div>' +
        '<p class="util-lb-hint">Optional price per board helps estimate total cost.</p>' +
        '<div class="util-lb-stock-wrap">' +
          '<table class="util-lb-stock-table"><thead><tr><th>Length</th><th>Price ($)</th><th class="util-lb-col-action" aria-hidden="true"></th></tr></thead>' +
          '<tbody data-lb-stock-list></tbody></table>' +
        '</div>' +
        '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm util-lb-add-row" data-lb-add-stock><i class="fa fa-plus"></i> Add board length</button>' +
        '<div class="util-lb-section-head util-lb-section-head--spaced util-lb-section-head-inline">' +
          '<span class="util-lb-section-label">Required pieces</span>' +
          '<div class="util-segmented util-segmented--compact" role="group" aria-label="Piece length units" data-lb-piece-unit-toggle>' +
            '<button type="button" class="util-segmented__btn' + (data.piece_unit === 'ft' ? ' is-active' : '') + '" data-piece-unit="ft">Feet</button>' +
            '<button type="button" class="util-segmented__btn' + (data.piece_unit !== 'ft' ? ' is-active' : '') + '" data-piece-unit="in">Inches</button>' +
          '</div>' +
        '</div>' +
        '<div class="util-lb-pieces-wrap">' +
          '<table class="util-lb-pieces-table"><thead><tr><th>Description</th><th>Length</th><th>Qty</th><th class="util-lb-col-action" aria-hidden="true"></th></tr></thead>' +
          '<tbody data-lb-pieces-body></tbody></table>' +
        '</div>' +
        '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm util-lb-add-row" data-lb-add-piece><i class="fa fa-plus"></i> Add piece</button>' +
      '</div>';

    var stockList = card.querySelector('[data-lb-stock-list]');
    var piecesBody = card.querySelector('[data-lb-pieces-body]');
    (data.stocks || []).forEach(function (s) {
      if (s.id) {
        var num = parseInt(String(s.id).replace(/\D/g, ''), 10);
        if (num >= state.stockIdSeq) state.stockIdSeq = num + 1;
      }
      stockList.appendChild(buildStockRow(card, s, data.stock_unit || 'ft'));
    });
    (data.pieces || [{ qty: 1 }]).forEach(function (p) {
      piecesBody.appendChild(buildPieceRow(card, p, data.piece_unit || 'in'));
    });

    syncSingleStockOptions(card, data.stock_unit || 'ft');
    if (data.single_stock_id) {
      var sel = card.querySelector('[data-lb-single-stock]');
      if (sel) sel.value = data.single_stock_id;
    }
    updateUnitLabelsInCard(card, data.stock_unit || 'ft', data.piece_unit || 'in');

    card.querySelector('[data-lb-bt-toggle]').addEventListener('click', function () {
      card.classList.toggle('is-collapsed');
      var expanded = !card.classList.contains('is-collapsed');
      card.querySelector('[data-lb-bt-toggle]').setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });

    card.querySelector('[data-lb-bt-remove]').addEventListener('click', function () {
      if (getBoardTypeCards().length <= 1) {
        window.AdminDialog.alert('Keep at least one board type.');
        return;
      }
      window.AdminDialog.confirm('Remove this board type?', { title: 'Remove board type', danger: true }).then(function (ok) {
        if (!ok) return;
        card.remove();
        scheduleRender();
      });
    });

    card.querySelector('[data-lb-bt-move-up]').addEventListener('click', function () {
      var prev = card.previousElementSibling;
      if (prev) {
        els.boardTypes.insertBefore(card, prev);
        scheduleRender();
      }
    });

    card.querySelector('[data-lb-bt-move-down]').addEventListener('click', function () {
      var next = card.nextElementSibling;
      if (next) {
        els.boardTypes.insertBefore(next, card);
        scheduleRender();
      }
    });

    card.querySelector('[data-lb-bt-duplicate]').addEventListener('click', function () {
      var dup = serializeBoardTypeCard(card);
      dup.id = nextBtId();
      dup.name = dup.name ? dup.name + ' (copy)' : '';
      dup.manual_counts = {};
      var newCard = buildBoardTypeCard(dup);
      card.parentNode.insertBefore(newCard, card.nextSibling);
      scheduleRender();
    });

    card.querySelector('[data-lb-add-stock]').addEventListener('click', function () {
      var meta = readBoardTypeMeta(card);
      stockList.appendChild(buildStockRow(card, {}, meta.stockUnit));
      scheduleRender();
    });

    card.querySelector('[data-lb-add-piece]').addEventListener('click', function () {
      var meta = readBoardTypeMeta(card);
      piecesBody.appendChild(buildPieceRow(card, { qty: 1 }, meta.pieceUnit));
      scheduleRender();
    });

    card.querySelectorAll('[data-quick-ft]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var meta = readBoardTypeMeta(card);
        var ft = parseInt(btn.getAttribute('data-quick-ft'), 10);
        var existing = stockRows(card, meta.stockUnit).some(function (s) {
          return Math.abs(s.lengthSix - toSixteenths(ft, 'ft')) < 1;
        });
        if (!existing) {
          stockList.appendChild(buildStockRow(card, { lengthVal: String(ft) }, meta.stockUnit));
          scheduleRender();
        }
      });
    });

    card.querySelector('[data-lb-stock-unit-toggle]').addEventListener('click', function (e) {
      var btn = e.target.closest('[data-stock-unit]');
      if (!btn) return;
      setActive(card.querySelector('[data-lb-stock-unit-toggle]'), 'data-stock-unit', btn.getAttribute('data-stock-unit'));
      var meta = readBoardTypeMeta(card);
      updateUnitLabelsInCard(card, meta.stockUnit, meta.pieceUnit);
      syncSingleStockOptions(card, meta.stockUnit);
      scheduleRender();
    });

    card.querySelector('[data-lb-piece-unit-toggle]').addEventListener('click', function (e) {
      var btn = e.target.closest('[data-piece-unit]');
      if (!btn) return;
      setActive(card.querySelector('[data-lb-piece-unit-toggle]'), 'data-piece-unit', btn.getAttribute('data-piece-unit'));
      var meta = readBoardTypeMeta(card);
      updateUnitLabelsInCard(card, meta.stockUnit, meta.pieceUnit);
      scheduleRender();
    });

    card.querySelector('[data-lb-mode-toggle]').addEventListener('click', function (e) {
      var btn = e.target.closest('[data-mode]');
      if (!btn) return;
      var mode = btn.getAttribute('data-mode');
      setActive(card.querySelector('[data-lb-mode-toggle]'), 'data-mode', mode);
      card.querySelectorAll('[data-lb-mode-field]').forEach(function (f) {
        show(f, f.getAttribute('data-lb-mode-field') === mode);
      });
      scheduleRender();
    });

    var singleSelect = card.querySelector('[data-lb-single-stock]');
    if (singleSelect) singleSelect.addEventListener('change', scheduleRender);

    card.addEventListener('dragstart', function (e) {
      if (!e.target.closest('[data-lb-bt-drag]')) {
        e.preventDefault();
        return;
      }
      state.dragBtId = card.getAttribute('data-bt-id');
      card.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', function () {
      card.classList.remove('is-dragging');
      state.dragBtId = null;
      els.boardTypes.querySelectorAll('.util-lb-bt-card').forEach(function (c) {
        c.classList.remove('is-drag-over');
      });
    });
    card.addEventListener('dragover', function (e) {
      e.preventDefault();
      if (state.dragBtId && card.getAttribute('data-bt-id') !== state.dragBtId) {
        card.classList.add('is-drag-over');
      }
    });
    card.addEventListener('dragleave', function () {
      card.classList.remove('is-drag-over');
    });
    card.addEventListener('drop', function (e) {
      e.preventDefault();
      card.classList.remove('is-drag-over');
      if (!state.dragBtId) return;
      var dragged = getBoardTypeCard(state.dragBtId);
      if (dragged && dragged !== card) {
        els.boardTypes.insertBefore(dragged, card);
        scheduleRender();
      }
    });

    return card;
  }

  function serializeBoardTypeCard(card) {
    var meta = readBoardTypeMeta(card);
    return {
      id: meta.id,
      name: meta.name,
      material_key: meta.materialKey,
      stock_unit: meta.stockUnit,
      piece_unit: meta.pieceUnit,
      mode: meta.mode,
      single_stock_id: meta.singleStockId,
      collapsed: meta.collapsed,
      stocks: stockRows(card, meta.stockUnit).map(function (s) {
        return { id: s.id, lengthVal: s.lengthVal, price: s.price };
      }),
      pieces: pieceRows(card, meta.pieceUnit).map(function (p) {
        return { desc: p.desc, lengthVal: p.lengthVal, qty: p.qty };
      }),
      manual_counts: getManualCounts(card)
    };
  }

  function serializeAllBoardTypes() {
    return getBoardTypeCards().map(serializeBoardTypeCard);
  }

  function migratePayload(p) {
    if (!p) return { version: 2, kerf: true, round_up: false, board_types: [defaultBoardTypeData()] };
    if (p.board_types && p.board_types.length) {
      return {
        version: 2,
        kerf: p.kerf !== false,
        round_up: !!p.round_up,
        board_types: p.board_types
      };
    }
    return {
      version: 2,
      kerf: p.kerf !== false,
      round_up: !!p.round_up,
      board_types: [defaultBoardTypeData({
        id: 'bt1',
        name: '',
        stock_unit: p.stock_unit === 'in' ? 'in' : 'ft',
        piece_unit: p.piece_unit === 'in' ? 'in' : (p.unit === 'in' ? 'in' : 'ft'),
        mode: p.mode === 'single' ? 'single' : 'minimize',
        single_stock_id: p.single_stock_id || '',
        stocks: p.stocks || [],
        pieces: p.pieces || [{ qty: 1 }],
        manual_counts: p.manual_counts || {}
      })]
    };
  }

  /* ── Rendering results ───────────────────────────────────────────── */

  function buildDiagramSvg(group, stockLabel, pieceUnit) {
    var bins = group.bins;
    var count = group.count != null ? group.count : bins.length;
    var barH = 28;
    var gap = 10;
    var pad = 12;
    var width = 560;
    var visible = bins.slice(0, count);
    var height = pad * 2 + visible.length * (barH + gap);
    var stockLen = group.lengthSix;
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + width + ' ' + height + '" class="util-lb-diagram-svg">';
    svg += '<text x="' + pad + '" y="16" class="util-lb-diagram-title">' + escapeXml(stockLabel) + ' (' + count + ' board' + (count === 1 ? '' : 's') + ')</text>';
    visible.forEach(function (bin, bi) {
      var y = pad + 20 + bi * (barH + gap);
      var x = pad;
      var barW = width - pad * 2;
      svg += '<rect x="' + x + '" y="' + y + '" width="' + barW + '" height="' + barH + '" fill="#eef2f5" stroke="#c5d0d8" rx="3"/>';
      var cx = x;
      var colorIdx = 0;
      bin.segments.forEach(function (seg) {
        var segW = Math.max(1, (seg.len / stockLen) * barW);
        var fill = seg.isWaste ? '#b0bec5' : PIECE_COLORS[colorIdx % PIECE_COLORS.length];
        if (!seg.isWaste) colorIdx++;
        svg += '<rect x="' + cx + '" y="' + y + '" width="' + segW + '" height="' + barH + '" fill="' + fill + '"/>';
        if (!seg.isWaste && segW >= 14) {
          var sizeLabel = formatDiagramCutLength(seg.len, pieceUnit);
          if (sizeLabel) {
            svg += '<text x="' + (cx + segW / 2) + '" y="' + (y - 2) + '" text-anchor="middle" fill="#37474f" font-size="8" font-family="Open Sans,sans-serif">' + escapeXml(sizeLabel) + '</text>';
          }
        }
        if (segW > 36 && !seg.isWaste) {
          var t = seg.label.length > 14 ? seg.label.slice(0, 12) + '…' : seg.label;
          svg += '<text x="' + (cx + segW / 2) + '" y="' + (y + barH / 2 + 4) + '" text-anchor="middle" fill="#fff" font-size="10" font-family="Open Sans,sans-serif">' + escapeXml(t) + '</text>';
        } else if (seg.isWaste && segW > 24) {
          svg += '<text x="' + (cx + segW / 2) + '" y="' + (y + barH / 2 + 4) + '" text-anchor="middle" fill="#546e7a" font-size="9" font-family="Open Sans,sans-serif">Waste</text>';
        }
        cx += segW;
      });
    });
    svg += '</svg>';
    return svg;
  }

  function renderBreakdown(container, result, card, stockUnit) {
    container.innerHTML = '';

    result.groups.forEach(function (g) {
      if (g.isOffcut) {
        var ocRow = document.createElement('div');
        ocRow.className = 'util-lb-breakdown-row util-lb-breakdown-row--offcut';
        ocRow.innerHTML = '<div class="util-lb-breakdown-row__info"><span class="util-lb-breakdown-row__prefix">From inventory:</span> <strong>' + g.bins.length + ' offcut' + (g.bins.length === 1 ? '' : 's') + '</strong></div>';
        container.appendChild(ocRow);
        return;
      }
      var stock = result.stocks.find(function (s) { return s.id === g.stockId || s.lengthSix === g.lengthSix; });
      var count = g.count != null ? g.count : g.bins.length;
      var row = document.createElement('div');
      row.className = 'util-lb-breakdown-row';
      var label = formatLength(g.lengthSix, stockUnit);
      var priceHint = stock && stock.price > 0 ? ' @ ' + formatMoney(stock.price) + ' each' : '';
      row.innerHTML =
        '<div class="util-lb-breakdown-row__info">' +
          '<span class="util-lb-breakdown-row__prefix">Boards to buy:</span> <strong>' + label + '</strong>' + priceHint +
        '</div>' +
        '<div class="util-lb-breakdown-row__qty">' +
          '<button type="button" class="util-lb-qty-btn" data-lb-qty-dec aria-label="Decrease">−</button>' +
          '<input type="number" min="0" step="1" class="util-lb-qty-input" data-lb-qty-input value="' + count + '">' +
          '<button type="button" class="util-lb-qty-btn" data-lb-qty-inc aria-label="Increase">+</button>' +
        '</div>';
      var stockId = g.stockId;
      function setCount(n) {
        setManualCount(card, stockId, Math.max(0, parseInt(n, 10) || 0));
        scheduleRender();
      }
      row.querySelector('[data-lb-qty-dec]').addEventListener('click', function () {
        setCount((parseInt(row.querySelector('[data-lb-qty-input]').value, 10) || 0) - 1);
      });
      row.querySelector('[data-lb-qty-inc]').addEventListener('click', function () {
        setCount((parseInt(row.querySelector('[data-lb-qty-input]').value, 10) || 0) + 1);
      });
      row.querySelector('[data-lb-qty-input]').addEventListener('change', function (e) {
        setCount(e.target.value);
      });
      container.appendChild(row);
    });
  }

  function typeSummaryText(result, linearSix, stockUnit, pieceUnit) {
    if (!result || result.error) return '';
    var s = result.stats;
    return result.strategy + ' — ' + s.boards + ' board(s), ' +
      formatLength(s.waste, stockUnit) + ' waste (' + s.wastePct.toFixed(1) + '%), ' +
      formatLength(linearSix, pieceUnit) + ' required';
  }

  function globalSummaryText(entries) {
    var boards = 0;
    var cost = 0;
    var waste = 0;
    var purchased = 0;
    entries.forEach(function (e) {
      if (!e.result || e.result.error) return;
      boards += e.result.stats.boards;
      cost += e.result.stats.cost;
      waste += e.result.stats.waste;
      purchased += e.result.stats.purchased;
    });
    var wastePct = purchased > 0 ? (waste / purchased) * 100 : 0;
    return boards + ' board(s) total, ' + formatLength(waste, 'ft') + ' waste (' + wastePct.toFixed(1) + '%)' +
      (cost > 0 ? ', ' + formatMoney(cost) + ' estimated' : '');
  }

  function render() {
    var kerf = kerfSixteenths();
    var cards = getBoardTypeCards();
    var entries = [];
    var hasAnyInput = false;
    var hasAnyResult = false;
    var hasError = false;

    els.typeResults.innerHTML = '';
    if (els.diagrams) els.diagrams.innerHTML = '';

    cards.forEach(function (card) {
      var meta = readBoardTypeMeta(card);
      var stocks = stockRows(card, meta.stockUnit);
      var pieces = expandPieces(pieceRows(card, meta.pieceUnit));
      var linearSix = pieces.reduce(function (sum, p) { return sum + p.len; }, 0);
      var displayName = meta.name || 'Unnamed board type';

      if (stocks.length || pieces.length) hasAnyInput = true;

      var resultBlock = document.createElement('div');
      resultBlock.className = 'admin-card util-lb-type-result';
      resultBlock.setAttribute('data-lb-type-result', '');
      resultBlock.setAttribute('data-bt-id', meta.id);

      if (!stocks.length || !pieces.length) {
        resultBlock.innerHTML =
          '<h4 class="util-lb-type-result__title">' + escapeHtml(displayName) + '</h4>' +
          '<p class="util-lb-type-result__hint">Add stock lengths and required pieces to calculate.</p>';
        els.typeResults.appendChild(resultBlock);
        entries.push({ meta: meta, card: card, result: null, linearSix: 0 });
        return;
      }

      var offcutPack = null;
      var piecesForStock = pieces;
      if (els.useOffcuts && els.useOffcuts.checked) {
        offcutPack = packOntoOffcuts(pieces, matchingLinearOffcuts(meta.materialKey), kerf);
        piecesForStock = offcutPack.remaining;
      }
      var result = piecesForStock.length
        ? solve(piecesForStock, stocks, meta.mode, meta.singleStockId, getManualCounts(card), meta.stockUnit, kerf)
        : { strategy: 'Inventory offcuts only', groups: [], stats: { purchased: 0, waste: 0, boards: 0, cost: 0, wastePct: 0 }, stocks: stocks };
      if (offcutPack && offcutPack.bins.length) {
        var ocGroups = {};
        offcutPack.bins.forEach(function (bin) {
          var gk = String(bin.stockLen);
          if (!ocGroups[gk]) {
            ocGroups[gk] = {
              stockId: 'offcut-' + gk,
              lengthSix: bin.stockLen,
              bins: [],
              isOffcut: true
            };
          }
          ocGroups[gk].bins.push(bin);
        });
        var ocGroupList = Object.keys(ocGroups).map(function (k) {
          ocGroups[k].count = ocGroups[k].bins.length;
          return ocGroups[k];
        });
        result.groups = ocGroupList.concat(result.groups || []);
        result.usedOffcutIds = offcutPack.usedOffcutIds;
        result.usedOffcutBins = offcutPack.bins;
        var extraWaste = 0;
        offcutPack.bins.forEach(function (bin) {
          extraWaste += (bin.segments.filter(function (s) { return s.isWaste; })[0] || { len: 0 }).len;
        });
        result.stats = result.stats || { purchased: 0, waste: 0, boards: 0, cost: 0, wastePct: 0 };
        result.stats.waste += extraWaste;
        result.stats.offcutBoards = offcutPack.bins.length;
        if (result.strategy && offcutPack.bins.length) {
          result.strategy = offcutPack.bins.length + ' offcut(s) first' + (piecesForStock.length ? ' · ' + result.strategy : '');
        }
      }

      if (!result || result.error) {
        hasError = true;
        resultBlock.innerHTML =
          '<h4 class="util-lb-type-result__title">' + escapeHtml(displayName) + '</h4>' +
          '<div class="util-lb-error util-lb-error--inline"><i class="fa fa-exclamation-triangle"></i><span>' + escapeHtml(result ? result.error : 'Could not calculate.') + '</span></div>';
        els.typeResults.appendChild(resultBlock);
        entries.push({ meta: meta, card: card, result: result, linearSix: linearSix });
        return;
      }

      hasAnyResult = true;
      var s = result.stats;
      var hint = result.suggestion && result.suggestion !== result.strategy
        ? ' <span class="util-lb-strategy__sep" aria-hidden="true">/</span> <span class="util-lb-strategy__alt">Next best: ' + escapeHtml(result.suggestion) + '</span>'
        : '';

      resultBlock.innerHTML =
        '<h4 class="util-lb-type-result__title">' + escapeHtml(displayName) + '</h4>' +
        '<p class="util-lb-strategy"><strong>' + escapeHtml(result.strategy) + '</strong>' + hint + '</p>' +
        '<div class="util-lb-stats">' +
          '<div class="util-lb-stat"><span class="util-lb-stat__value" data-lb-stat-linear></span><span class="util-lb-stat__label">Linear required</span></div>' +
          '<div class="util-lb-stat"><span class="util-lb-stat__value" data-lb-stat-boards></span><span class="util-lb-stat__label">Boards</span></div>' +
          '<div class="util-lb-stat"><span class="util-lb-stat__value" data-lb-stat-cost></span><span class="util-lb-stat__label">Est. cost</span></div>' +
          '<div class="util-lb-stat util-lb-stat--waste" data-lb-stat-waste-wrap><span class="util-lb-stat__value" data-lb-stat-waste></span><span class="util-lb-stat__label">Waste</span></div>' +
        '</div>' +
        '<div class="util-lb-breakdown" data-lb-breakdown></div>' +
        '<div class="util-lb-type-diagrams" data-lb-type-diagrams></div>';

      setStatValue(resultBlock.querySelector('[data-lb-stat-linear]'), formatLength(linearSix, meta.pieceUnit));
      setStatValue(resultBlock.querySelector('[data-lb-stat-boards]'), String(s.boards));
      setStatValue(resultBlock.querySelector('[data-lb-stat-cost]'), s.cost > 0 ? formatMoney(s.cost) : '—');
      setStatValue(resultBlock.querySelector('[data-lb-stat-waste]'), formatLength(s.waste, meta.stockUnit), '(' + s.wastePct.toFixed(1) + '%)');
      resultBlock.querySelector('[data-lb-stat-waste-wrap]').className = 'util-lb-stat util-lb-stat--waste ' + wasteClass(s.wastePct);

      renderBreakdown(resultBlock.querySelector('[data-lb-breakdown]'), result, card, meta.stockUnit);

      var diagramsWrap = resultBlock.querySelector('[data-lb-type-diagrams]');
      result.groups.forEach(function (g) {
        var count = g.count != null ? g.count : g.bins.length;
        if (count < 1) return;
        var block = document.createElement('div');
        block.className = 'util-lb-diagram-block';
        block.innerHTML = buildDiagramSvg(g, (g.isOffcut ? 'Inventory ' : '') + formatLength(g.lengthSix, meta.stockUnit), meta.pieceUnit);
        diagramsWrap.appendChild(block);
        if (els.diagrams) {
          var clone = block.cloneNode(true);
          var heading = document.createElement('h4');
          heading.className = 'util-lb-diagram-type-label';
          heading.textContent = displayName;
          var outer = document.createElement('div');
          outer.className = 'util-lb-diagram-block util-lb-diagram-block--grouped';
          outer.appendChild(heading);
          outer.appendChild(clone);
          els.diagrams.appendChild(outer);
        }
      });

      els.typeResults.appendChild(resultBlock);
      entries.push({ meta: meta, card: card, result: result, linearSix: linearSix });
    });

    lastResults = { entries: entries, kerf: kerf };

    show(els.empty, !hasAnyInput);
    show(els.typeResults, hasAnyInput);
    show(els.saveCard, hasAnyResult);
    show(els.resultsActions, hasAnyResult);
    show(els.diagramCard, hasAnyResult);
    show(els.globalSummary, hasAnyResult);

    if (hasAnyResult) {
      var totalBoards = 0;
      var totalCost = 0;
      var totalWaste = 0;
      var totalPurchased = 0;
      var purchaseMap = {};

      entries.forEach(function (e) {
        if (!e.result || e.result.error) return;
        var st = e.result.stats;
        totalBoards += st.boards;
        totalCost += st.cost;
        totalWaste += st.waste;
        totalPurchased += st.purchased;
        e.result.groups.forEach(function (g) {
          if (g.isOffcut) return;
          var count = g.count != null ? g.count : g.bins.length;
          var key = e.meta.name + '\0' + g.lengthSix;
          if (!purchaseMap[key]) {
            purchaseMap[key] = {
              typeName: e.meta.name || 'Unnamed',
              lengthSix: g.lengthSix,
              stockUnit: e.meta.stockUnit,
              qty: 0
            };
          }
          purchaseMap[key].qty += count;
        });
      });

      setStatValue(els.globalBoards, String(totalBoards));
      setStatValue(els.globalCost, totalCost > 0 ? formatMoney(totalCost) : '—');
      if (els.globalWaste) {
        var gWastePct = totalPurchased > 0 ? (totalWaste / totalPurchased) * 100 : 0;
        setStatValue(els.globalWaste, formatLength(totalWaste, 'ft'), '(' + gWastePct.toFixed(1) + '%)');
        if (els.globalWasteWrap) {
          els.globalWasteWrap.className = 'util-lb-stat util-lb-stat--waste ' + wasteClass(gWastePct);
        }
      }

      if (els.globalBreakdown) {
        els.globalBreakdown.innerHTML = '';
        var gHead = document.createElement('p');
        gHead.className = 'util-lb-breakdown__title';
        gHead.textContent = 'All boards to buy';
        els.globalBreakdown.appendChild(gHead);
        Object.keys(purchaseMap).sort().forEach(function (k) {
          var row = purchaseMap[k];
          var div = document.createElement('div');
          div.className = 'util-lb-breakdown-row util-lb-breakdown-row--global';
          div.innerHTML =
            '<div class="util-lb-breakdown-row__info">' +
              '<span class="util-lb-breakdown-row__type">' + escapeHtml(row.typeName) + '</span> ' +
              '<strong>' + escapeHtml(formatLength(row.lengthSix, row.stockUnit)) + '</strong>' +
            '</div>' +
            '<div class="util-lb-breakdown-row__qty-static">' + row.qty + ' board' + (row.qty === 1 ? '' : 's') + '</div>';
          els.globalBreakdown.appendChild(div);
        });
      }
    }

    updateOffcutSummary(entries, hasAnyResult);
  }

  function minOffcutSix() {
    var n = parseNum(els.minOffcut && els.minOffcut.value);
    if (!(n > 0)) n = 12;
    return toSixteenths(n, 'in');
  }

  function collectLinearLeftovers(entries) {
    var minSix = minOffcutSix();
    var leftovers = [];
    var usedIds = [];
    entries.forEach(function (e) {
      if (!e.result || e.result.error) return;
      (e.result.usedOffcutIds || []).forEach(function (id) {
        if (usedIds.indexOf(id) === -1) usedIds.push(id);
      });
      e.result.groups.forEach(function (g) {
        var count = g.count != null ? g.count : g.bins.length;
        g.bins.forEach(function (bin, i) {
          if (i >= count) return;
          var waste = (bin.segments.filter(function (s) { return s.isWaste; })[0] || { len: 0 }).len;
          if (waste < minSix) return;
          leftovers.push({
            material_key: e.meta.materialKey || e.meta.name || 'lumber',
            length_in: sixteenthsToInches(waste),
            qty: 1,
            notes: g.isOffcut ? 'Remainder of inventory offcut' : 'Leftover from ' + (e.meta.name || 'board')
          });
        });
      });
    });
    return { leftovers: leftovers, usedIds: usedIds };
  }

  function updateOffcutSummary(entries, hasAnyResult) {
    var info = collectLinearLeftovers(entries);
    pendingLeftovers = info.leftovers;
    var usedCount = 0;
    entries.forEach(function (e) {
      if (e.result && e.result.stats && e.result.stats.offcutBoards) {
        usedCount += e.result.stats.offcutBoards;
      }
    });
    if (els.offcutUsed) {
      if (usedCount > 0) {
        els.offcutUsed.innerHTML = '<p class="util-lb-offcut-used__msg"><i class="fa fa-check"></i> Used ' + usedCount + ' inventory offcut' + (usedCount === 1 ? '' : 's') + ' before buying stock.</p>';
        show(els.offcutUsed, true);
      } else {
        els.offcutUsed.innerHTML = '';
        show(els.offcutUsed, false);
      }
    }
    show(els.saveLeftoversWrap, hasAnyResult && pendingLeftovers.length > 0);
  }

  function jsonHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json'
    };
  }

  function fetchOffcuts() {
    var url = root.getAttribute('data-offcuts-url');
    if (!url) return Promise.resolve();
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.ok ? res.json() : { linear: [], sheets: [] }; })
      .then(function (data) {
        offcutCache.linear = data.linear || [];
        offcutCache.sheets = data.sheets || [];
      })
      .catch(function () {
        offcutCache.linear = [];
        offcutCache.sheets = [];
      });
  }

  function applyImportedBoardTypes(lumber) {
    if (!lumber || !lumber.length) return;
    var types = lumber.map(function (bt) {
      return defaultBoardTypeData({
        name: bt.name || '',
        material_key: bt.material_key || '',
        stock_unit: bt.stock_unit === 'in' ? 'in' : 'ft',
        piece_unit: bt.piece_unit === 'in' ? 'in' : 'ft',
        stocks: (bt.stocks || []).map(function (s) {
          return { lengthVal: String(s.lengthVal != null ? s.lengthVal : ''), price: s.price || '' };
        }),
        pieces: (bt.pieces || []).map(function (p) {
          return { desc: p.desc || '', lengthVal: String(p.lengthVal != null ? p.lengthVal : ''), qty: p.qty || 1 };
        })
      });
    });
    state.loadedCalculationId = null;
    updateSaveUi();
    loadBoardTypes(types);
    render();
  }

  function applyImportedSheets(sheets) {
    sheetState.groups = sheets || [];
    sheetState.last = null;
    if (!els.sheetGroups) return;
    if (!sheetState.groups.length) {
      show(els.sheetCard, false);
      return;
    }
    show(els.sheetCard, true);
    els.sheetGroups.innerHTML = sheetState.groups.map(function (g, gi) {
      var parts = (g.parts || []).map(function (p) {
        return escapeHtml(p.label || 'Part') + ' — ' + p.width + '×' + p.length + ' in × ' + (p.qty || 1);
      }).join('<br>');
      return '<article class="util-lb-sheet-group" data-sheet-i="' + gi + '">' +
        '<h4>' + escapeHtml(g.name || g.material_key || 'Sheet goods') + '</h4>' +
        '<p class="util-lb-hint">' + (parts || 'No parts') + '</p></article>';
    }).join('');
    if (els.sheetLayouts) els.sheetLayouts.innerHTML = '';
    show(els.sheetUsed, false);
    if (els.saveSheetLeftoversBtn) show(els.saveSheetLeftoversBtn, false);
  }

  function importQuotation(id) {
    if (!id) return;
    var url = root.getAttribute('data-import-url') + (root.getAttribute('data-import-url').indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(id);
    if (els.importStatus) {
      els.importStatus.textContent = 'Loading quotation…';
      show(els.importStatus, true);
    }
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function (data) {
        if (data.client_project_id) setLinkProject(data.client_project_id);
        applyImportedBoardTypes(data.lumber || []);
        applyImportedSheets(data.sheets || []);
        if (els.saveName && data.title) els.saveName.value = data.title;
        if (els.importStatus) {
          if (!data.has_parts) {
            els.importStatus.textContent = 'No cut-list parts on this quotation.';
          } else {
            var nL = (data.lumber || []).length;
            var nS = (data.sheets || []).length;
            els.importStatus.textContent = 'Imported ' + nL + ' lumber group' + (nL === 1 ? '' : 's') +
              (nS ? ' and ' + nS + ' sheet group' + (nS === 1 ? '' : 's') : '') +
              ' from ' + (data.quote_number || 'quotation') + '.';
          }
          show(els.importStatus, true);
        }
      })
      .catch(function () {
        if (els.importStatus) {
          els.importStatus.textContent = 'Could not import that quotation.';
          show(els.importStatus, true);
        }
      });
  }

  function saveLeftovers(list, minIn) {
    var url = root.getAttribute('data-save-leftovers-url');
    if (!url || !list || !list.length) return Promise.resolve();
    var projectId = els.linkProject && els.linkProject.value ? parseInt(els.linkProject.value, 10) : null;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: jsonHeaders(),
      body: JSON.stringify({
        leftovers: list,
        min_offcut_in: minIn,
        client_project_id: projectId
      })
    }).then(function (res) { return res.ok ? res.json() : Promise.reject(res); });
  }

  function consumeOffcuts(ids) {
    var url = root.getAttribute('data-consume-url');
    if (!url || !ids || !ids.length) return Promise.resolve();
    var projectId = els.linkProject && els.linkProject.value ? parseInt(els.linkProject.value, 10) : null;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: jsonHeaders(),
      body: JSON.stringify({ ids: ids, client_project_id: projectId, qty: 1 })
    }).then(function (res) { return res.ok ? res.json() : Promise.reject(res); });
  }

  function runSheetOptimizer() {
    if (!sheetState.groups.length) return;
    var url = root.getAttribute('data-optimize-sheets-url');
    if (!url) return;
    var minIn = parseNum(els.minOffcut && els.minOffcut.value) || 2;
    var projectId = els.linkProject && els.linkProject.value ? parseInt(els.linkProject.value, 10) : null;
    var requests = sheetState.groups.map(function (group) {
      var payload = {
        parts: (group.parts || []).map(function (p, i) {
          return {
            id: 'p' + i,
            label: p.label || 'Part',
            width: p.width,
            length: p.length,
            qty: p.qty || 1,
            grain: p.grain || 'any'
          };
        }),
        stock: (group.stock || [{ width: 48, length: 96, label: '4×8' }]).map(function (s, i) {
          return {
            id: 'st' + i,
            label: s.label || 'Sheet',
            width: s.width,
            length: s.length,
            price: s.price || 0
          };
        }),
        material_key: group.material_key || 'sheet',
        thickness_in: group.thickness_in || null,
        kerf: els.kerf && els.kerf.checked ? 0.125 : 0,
        trim: 0.125,
        min_offcut_in: minIn,
        use_offcuts: !!(els.sheetUseOffcuts && els.sheetUseOffcuts.checked),
        client_project_id: projectId
      };
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(payload)
      }).then(function (res) { return res.ok ? res.json() : Promise.reject(res); });
    });
    if (els.runSheetsBtn) els.runSheetsBtn.disabled = true;
    Promise.all(requests)
      .then(function (results) {
        if (els.runSheetsBtn) els.runSheetsBtn.disabled = false;
        var merged = { layouts: [], leftovers: [], used_offcut_ids: [], used_offcuts: [], estimated_cost: 0 };
        results.forEach(function (data) {
          (data.layouts || []).forEach(function (layout) { merged.layouts.push(layout); });
          (data.leftovers || []).forEach(function (row) { merged.leftovers.push(row); });
          (data.used_offcut_ids || []).forEach(function (id) {
            if (merged.used_offcut_ids.indexOf(id) === -1) merged.used_offcut_ids.push(id);
          });
          (data.used_offcuts || []).forEach(function (row) {
            if (!merged.used_offcuts.some(function (u) { return u.id === row.id; })) {
              merged.used_offcuts.push(row);
            }
          });
          merged.estimated_cost += data.estimated_cost || 0;
        });
        sheetState.last = merged;
        if (els.sheetLayouts) {
          els.sheetLayouts.innerHTML = merged.layouts.map(function (layout, i) {
            var stats = layout.stats || {};
            var usedNote = layout.from_offcut ? ' · from inventory' : '';
            return '<article class="util-lb-sheet-layout' + (layout.from_offcut ? ' is-offcut' : '') + '">' +
              '<h4>Sheet ' + (i + 1) + ': ' + escapeHtml(stats.label || 'Sheet') + usedNote +
              ' · waste ' + (stats.waste_pct || 0) + '%</h4>' +
              '<div class="util-lb-sheet-svg">' + (layout.svg || '') + '</div></article>';
          }).join('');
        }
        if (els.sheetUsed) {
          if (merged.used_offcuts.length) {
            els.sheetUsed.innerHTML = '<p class="util-lb-offcut-used__msg"><i class="fa fa-check"></i> Used inventory: ' +
              merged.used_offcuts.map(function (u) {
                return escapeHtml((u.material_key || 'offcut') + ' ' + (u.size_label || ''));
              }).join(', ') + '</p>';
            show(els.sheetUsed, true);
          } else {
            show(els.sheetUsed, false);
          }
        }
        if (els.saveSheetLeftoversBtn) {
          show(els.saveSheetLeftoversBtn, merged.leftovers.length > 0);
        }
      })
      .catch(function () {
        if (els.runSheetsBtn) els.runSheetsBtn.disabled = false;
        window.AdminDialog.alert('Could not run the sheet optimizer.');
      });
  }

  /* ── Export / print / save ───────────────────────────────────────── */

  function resultRows() {
    if (!lastResults) return [];
    var rows = [];
    lastResults.entries.forEach(function (e) {
      if (!e.result || e.result.error) return;
      var name = e.meta.name || 'Unnamed board type';
      rows.push({ label: name + ' — strategy', value: e.result.strategy });
      rows.push({ label: name + ' — linear required', value: formatLength(e.linearSix, e.meta.pieceUnit) });
      rows.push({ label: name + ' — boards', value: String(e.result.stats.boards) });
      rows.push({ label: name + ' — waste', value: formatLength(e.result.stats.waste, e.meta.stockUnit) + ' (' + e.result.stats.wastePct.toFixed(1) + '%)' });
      if (e.result.stats.cost > 0) {
        rows.push({ label: name + ' — cost', value: formatMoney(e.result.stats.cost) });
      }
    });
    return rows;
  }

  function breakdownRows() {
    if (!lastResults) return [];
    var rows = [];
    lastResults.entries.forEach(function (e) {
      if (!e.result || e.result.error) return;
      var prefix = (e.meta.name || 'Unnamed') + ' — ';
      e.result.groups.forEach(function (g) {
        var count = g.count != null ? g.count : g.bins.length;
        rows.push({
          label: prefix + (g.isOffcut ? 'Inventory ' : '') + formatLength(g.lengthSix, e.meta.stockUnit),
          value: count + (g.isOffcut ? ' offcut' : ' board') + (count === 1 ? '' : 's')
        });
      });
    });
    return rows;
  }

  function pieceListGrouped() {
    if (!lastResults) return [];
    var list = [];
    lastResults.entries.forEach(function (e) {
      var map = {};
      pieceRows(e.card, e.meta.pieceUnit).forEach(function (row) {
        if (!row.lengthSix || row.qty < 1) return;
        var key = (row.desc || '') + '\0' + row.lengthSix;
        if (!map[key]) {
          map[key] = {
            typeName: e.meta.name || 'Unnamed',
            desc: row.desc || '—',
            length: formatLength(row.lengthSix, e.meta.pieceUnit),
            qty: 0
          };
        }
        map[key].qty += row.qty;
      });
      Object.keys(map).forEach(function (k) { list.push(map[k]); });
    });
    return list;
  }

  function purchaseListDetailed() {
    if (!lastResults) return [];
    var list = [];
    lastResults.entries.forEach(function (e) {
      if (!e.result || e.result.error) return;
      e.result.groups.forEach(function (g) {
        var stock = e.result.stocks.find(function (s) {
          return s.id === g.stockId || s.lengthSix === g.lengthSix;
        });
        var count = g.count != null ? g.count : g.bins.length;
        var unitPrice = stock && stock.price > 0 ? stock.price : 0;
        list.push({
          typeName: e.meta.name || 'Unnamed',
          length: formatLength(g.lengthSix, e.meta.stockUnit),
          qty: count,
          unitPrice: unitPrice > 0 ? formatMoney(unitPrice) : '—',
          lineTotal: unitPrice > 0 ? formatMoney(count * unitPrice) : '—'
        });
      });
    });
    return list;
  }

  function svgToPngDataUri(svgEl, cb) {
    if (!svgEl) { cb(''); return; }
    var xml = new XMLSerializer().serializeToString(svgEl);
    var img = new Image();
    var canvas = document.createElement('canvas');
    var vb = svgEl.viewBox.baseVal;
    canvas.width = vb.width || 560;
    canvas.height = vb.height || 200;
    img.onload = function () {
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0);
      cb(canvas.toDataURL('image/png'));
    };
    img.onerror = function () { cb(''); };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
  }

  function exportPdf() {
    if (!lastResults) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = root.getAttribute('data-pdf-url');
    form.target = '_blank';
    form.style.display = 'none';
    var add = function (name, val) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = name;
      inp.value = val;
      form.appendChild(inp);
    };
    add('_token', csrf());
    add('title', (els.saveName && els.saveName.value.trim()) || 'Lumber / Board Material');
    add('summary', globalSummaryText(lastResults.entries));
    resultRows().forEach(function (row, i) {
      add('rows[' + i + '][label]', row.label);
      add('rows[' + i + '][value]', row.value);
    });
    breakdownRows().forEach(function (row, i) {
      add('breakdown[' + i + '][label]', row.label);
      add('breakdown[' + i + '][value]', row.value);
    });

    var svgs = els.diagrams ? els.diagrams.querySelectorAll('svg') : [];
    var pending = svgs.length;
    if (!pending) {
      document.body.appendChild(form);
      form.submit();
      setTimeout(function () { document.body.removeChild(form); }, 1000);
      return;
    }
    var idx = 0;
    svgs.forEach(function (svg) {
      svgToPngDataUri(svg, function (dataUri) {
        if (dataUri) add('diagrams[' + idx + ']', dataUri);
        idx++;
        pending--;
        if (pending === 0) {
          document.body.appendChild(form);
          form.submit();
          setTimeout(function () { document.body.removeChild(form); }, 1000);
        }
      });
    });
  }

  function copyResults() {
    if (!lastResults) return;
    var lines = ['Grand total: ' + globalSummaryText(lastResults.entries), ''];
    resultRows().forEach(function (r) { lines.push(r.label + ': ' + r.value); });
    lines.push('');
    lines.push('Boards to buy:');
    breakdownRows().forEach(function (r) { lines.push('  ' + r.label + ': ' + r.value); });
    var text = lines.join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text);
    } else if (window.AdminDialog && window.AdminDialog.copyFallback) {
      window.AdminDialog.copyFallback(text);
    } else {
      window.prompt('Copy:', text);
    }
  }

  function printDiagramsHtml() {
    if (!els.diagrams) return '';
    var blocks = els.diagrams.querySelectorAll('.util-lb-diagram-block--grouped, .util-lb-diagram-block');
    if (!blocks.length) return '';
    var html = '<h2>Cutting diagrams</h2>';
    blocks.forEach(function (block) {
      html += '<div class="diagram">' + block.innerHTML + '</div>';
    });
    return html;
  }

  function buildPrintHtml() {
    var title = (els.saveName && els.saveName.value.trim()) || 'Lumber material list';
    var now = new Date().toLocaleString();
    var kerfNote = els.kerf && els.kerf.checked ? 'Includes 1/8″ kerf between cuts.' : 'Kerf not included.';
    var purchase = purchaseListDetailed();
    var hasPrice = purchase.some(function (row) { return row.unitPrice !== '—'; });
    var totalBoards = 0;
    var totalCost = 0;
    var totalWaste = 0;
    var totalPurchased = 0;

    if (lastResults) {
      lastResults.entries.forEach(function (e) {
        if (!e.result || e.result.error) return;
        totalBoards += e.result.stats.boards;
        totalCost += e.result.stats.cost;
        totalWaste += e.result.stats.waste;
        totalPurchased += e.result.stats.purchased;
      });
    }

    var purchaseHead = '<tr><th>Board type</th><th>Length</th><th>Qty</th>';
    if (hasPrice) purchaseHead += '<th>Unit price</th><th>Line total</th>';
    purchaseHead += '</tr>';

    var purchaseBody = purchase.map(function (row) {
      var tr = '<tr><td>' + escapeHtml(row.typeName) + '</td><td>' + escapeHtml(row.length) + '</td><td>' + row.qty + '</td>';
      if (hasPrice) {
        tr += '<td>' + escapeHtml(row.unitPrice) + '</td><td>' + escapeHtml(row.lineTotal) + '</td>';
      }
      return tr + '</tr>';
    }).join('');

    var pieces = pieceListGrouped();
    var piecesBody = pieces.map(function (row) {
      return '<tr><td>' + escapeHtml(row.typeName) + '</td><td>' + escapeHtml(row.desc) + '</td><td>' + escapeHtml(row.length) + '</td><td>' + row.qty + '</td></tr>';
    }).join('');

    var gWastePct = totalPurchased > 0 ? (totalWaste / totalPurchased) * 100 : 0;
    var summaryRows = [
      ['Total boards', String(totalBoards)],
      ['Total waste', formatLength(totalWaste, 'ft') + ' (' + gWastePct.toFixed(1) + '%)'],
      ['Estimated cost', totalCost > 0 ? formatMoney(totalCost) : '—']
    ];

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' + escapeHtml(title) + '</title>' +
      '<style>body{font-family:"Open Sans",Segoe UI,sans-serif;color:#373435;margin:0;padding:24px 28px;font-size:13px;}' +
      '.head{border-bottom:2px solid #024768;padding-bottom:10px;margin-bottom:18px;}' +
      'h1{font-size:22px;color:#024768;margin:0 0 4px;}h3{font-size:14px;color:#024768;margin:18px 0 6px;}' +
      '.meta{color:#5a6570;font-size:11px;margin:0;}.note{margin:0 0 18px;line-height:1.5;}' +
      'h2{font-size:15px;color:#024768;margin:22px 0 8px;}table{width:100%;border-collapse:collapse;margin-bottom:8px;}' +
      'th,td{border:1px solid #d8dee4;padding:8px 10px;text-align:left;}' +
      'th{background:#f4f6f8;color:#5a6570;font-size:10px;text-transform:uppercase;}' +
      '.summary td.label{color:#5a6570;width:55%;}.summary td.value{font-weight:700;text-align:right;}' +
      '.diagram{margin:12px 0 20px;page-break-inside:avoid;}.diagram svg{width:100%;max-width:100%;height:auto;}' +
      '@media print{body{padding:12mm;}}</style></head><body>' +
      '<div class="head"><h1>' + escapeHtml(title) + '</h1>' +
      '<p class="meta">Zanini Renovations &middot; ' + escapeHtml(now) + '</p></div>' +
      '<p class="note">' + escapeHtml(globalSummaryText(lastResults ? lastResults.entries : [])) + '<br>' + escapeHtml(kerfNote) + '</p>' +
      '<h2>Material to purchase</h2><table><thead>' + purchaseHead + '</thead><tbody>' + purchaseBody + '</tbody></table>' +
      '<h2>Pieces required</h2><table><thead><tr><th>Board type</th><th>Description</th><th>Length</th><th>Qty</th></tr></thead><tbody>' + piecesBody + '</tbody></table>' +
      '<h2>Summary</h2><table class="summary"><tbody>' +
      summaryRows.map(function (row) {
        return '<tr><td class="label">' + escapeHtml(row[0]) + '</td><td class="value">' + escapeHtml(row[1]) + '</td></tr>';
      }).join('') +
      '</tbody></table>' + printDiagramsHtml() + '</body></html>';
  }

  function printMaterialList() {
    if (!lastResults) return;
    var html = buildPrintHtml();
    var w = window.open('', 'lb_material_print', 'noopener,width=820,height=900');
    if (!w) {
      window.AdminDialog.alert('Pop-up blocked. Allow pop-ups for this site to print the material list.');
      return;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();
    w.focus();
    window.setTimeout(function () {
      try { w.print(); } finally { w.close(); }
    }, 400);
  }

  function currentPayload() {
    return {
      version: 2,
      kerf: !!(els.kerf && els.kerf.checked),
      round_up: !!(els.roundUp && els.roundUp.checked),
      board_types: serializeAllBoardTypes(),
      summary: lastResults ? globalSummaryText(lastResults.entries) : ''
    };
  }

  function focusSaveForm() {
    if (!lastResults) return;
    show(els.saveCard, true);
    if (els.saveCard) els.saveCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    if (els.saveName) { els.saveName.focus(); els.saveName.select(); }
  }

  function saveCalculation() {
    if (!lastResults) return;
    var name = (els.saveName.value || '').trim();
    if (!name) {
      showSaveFeedback('Give this calculation a name first.', false);
      els.saveName.focus();
      return;
    }
    var btn = root.querySelector('[data-lb-save]');
    if (btn) btn.disabled = true;
    var clientProjectId = els.linkProject && els.linkProject.value ? parseInt(els.linkProject.value, 10) : null;
    var isUpdate = !!state.loadedCalculationId;
    var url = isUpdate
      ? root.getAttribute('data-update-template').replace('__ID__', state.loadedCalculationId)
      : root.getAttribute('data-save-url');
    fetch(url, {
      method: isUpdate ? 'PUT' : 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({
        tool: 'lumber_board',
        name: name,
        client_project_id: clientProjectId,
        payload: currentPayload()
      })
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function (data) {
        if (btn) btn.disabled = false;
        state.loadedCalculationId = data.calculation.id;
        updateSaveUi();
        if (isUpdate) {
          showSaveFeedback('Updated "' + data.calculation.name + '".', true);
          replaceSaved(data.calculation);
        } else {
          els.saveName.value = '';
          showSaveFeedback('Saved "' + data.calculation.name + '".', true);
          prependSaved(data.calculation);
        }
      })
      .catch(function () {
        if (btn) btn.disabled = false;
        showSaveFeedback('Could not save. Please try again.', false);
      });
  }

  function showSaveFeedback(msg, ok) {
    if (!els.saveFeedback) return;
    els.saveFeedback.textContent = msg;
    els.saveFeedback.className = 'util-lb-save-feedback ' + (ok ? 'is-ok' : 'is-error');
    show(els.saveFeedback, true);
    setTimeout(function () { show(els.saveFeedback, false); }, 3500);
  }

  function savedItemHtml(c) {
    var payload = c.payload || {};
    var summary = payload.summary || '';
    var projectHtml = '';
    if (c.client_project_id && c.client_project_name) {
      projectHtml = '<span class="util-lb-saved-item__project">Linked: <a href="' + escapeAttr(projectUrl(c.client_project_id)) + '">' + escapeHtml(c.client_project_name) + '</a></span>';
    }
    var li = document.createElement('li');
    li.className = 'util-lb-saved-item';
    li.setAttribute('data-id', c.id);
    li.innerHTML =
      '<div class="util-lb-saved-item__main">' +
        '<span class="util-lb-saved-item__name"></span>' + projectHtml +
        '<span class="util-lb-saved-item__summary"></span>' +
        '<span class="util-lb-saved-item__date"></span>' +
      '</div>' +
      '<div class="util-lb-saved-item__actions">' +
        '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-load><i class="fa fa-upload"></i> Load</button>' +
        '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-lb-delete><i class="fa fa-trash"></i></button>' +
      '</div>';
    li.querySelector('.util-lb-saved-item__name').textContent = c.name;
    li.querySelector('.util-lb-saved-item__summary').textContent = summary;
    li.querySelector('.util-lb-saved-item__date').textContent = savedListLabel(c);
    li.querySelector('[data-lb-load]').addEventListener('click', function () {
      loadPayload(payload, c.client_project_id, c.name, c.id);
    });
    li.querySelector('[data-lb-delete]').addEventListener('click', function () { deleteSaved(c.id, li); });
    return li;
  }

  function prependSaved(c) {
    els.savedList.insertBefore(savedItemHtml(c), els.savedList.firstChild);
    refreshSavedEmpty();
  }

  function replaceSaved(c) {
    var li = els.savedList.querySelector('[data-id="' + c.id + '"]');
    var newLi = savedItemHtml(c);
    if (li) {
      li.replaceWith(newLi);
      els.savedList.insertBefore(newLi, els.savedList.firstChild);
    } else {
      prependSaved(c);
    }
    refreshSavedEmpty();
  }

  function refreshSavedEmpty() {
    show(els.savedEmpty, els.savedList.children.length === 0);
  }

  function deleteSaved(id, li) {
    window.AdminDialog.confirm('Delete this saved calculation?', { title: 'Delete calculation', danger: true }).then(function (ok) {
      if (!ok) return;
    var url = root.getAttribute('data-destroy-template').replace('__ID__', id);
    fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function () {
        if (String(state.loadedCalculationId) === String(id)) {
          state.loadedCalculationId = null;
          updateSaveUi();
        }
        li.remove();
        refreshSavedEmpty();
      })
      .catch(function () { window.AdminDialog.alert('Could not delete. Please try again.'); });
    });
  }

  function loadBoardTypes(boardTypes) {
    els.boardTypes.innerHTML = '';
    state.btIdSeq = 1;
    state.stockIdSeq = 1;
    boardTypes.forEach(function (bt) {
      els.boardTypes.appendChild(buildBoardTypeCard(bt));
    });
  }

  function loadPayload(p, clientProjectId, calcName, calcId) {
    if (!p) return;
    state.loadedCalculationId = calcId ? String(calcId) : null;
    var migrated = migratePayload(p);
    if (els.roundUp) els.roundUp.checked = !!migrated.round_up;
    if (els.kerf) els.kerf.checked = migrated.kerf !== false;
    loadBoardTypes(migrated.board_types);
    setLinkProject(clientProjectId);
    if (els.saveName) els.saveName.value = calcName ? String(calcName) : '';
    updateSaveUi();
    render();
    if (lastResults && lastResults.entries.some(function (e) { return e.result && !e.result.error; })) {
      focusSaveForm();
    }
  }

  function resetAll() {
    state.loadedCalculationId = null;
    state.btIdSeq = 1;
    state.stockIdSeq = 1;
    if (els.roundUp) els.roundUp.checked = false;
    if (els.kerf) els.kerf.checked = true;
    loadBoardTypes([defaultBoardTypeData({ id: 'bt1', name: '' })]);
    setLinkProject(initialLinkProjectId);
    if (els.saveName) els.saveName.value = '';
    updateSaveUi();
    render();
  }

  /* ── Init & events ───────────────────────────────────────────────── */

  root.addEventListener('input', function (e) {
    if (e.target.closest('[data-lb-board-type]')) scheduleRender();
  });

  root.querySelector('[data-lb-add-board-type]').addEventListener('click', function () {
    els.boardTypes.appendChild(buildBoardTypeCard({ name: '', stocks: [{ lengthVal: '8' }, { lengthVal: '10' }, { lengthVal: '12' }, { lengthVal: '16' }], pieces: [{ qty: 1 }] }));
    scheduleRender();
  });

  root.querySelector('[data-lb-reset]').addEventListener('click', function () {
    window.AdminDialog.confirm('Reset all board types and start over?', { title: 'Reset calculator', danger: true }).then(function (ok) {
      if (!ok) return;
      resetAll();
    });
  });

  if (els.roundUp) els.roundUp.addEventListener('change', scheduleRender);
  if (els.kerf) els.kerf.addEventListener('change', scheduleRender);
  if (els.useOffcuts) els.useOffcuts.addEventListener('change', function () {
    fetchOffcuts().then(scheduleRender);
  });
  if (els.minOffcut) els.minOffcut.addEventListener('change', scheduleRender);
  if (els.saveLeftoversBtn) {
    els.saveLeftoversBtn.addEventListener('click', function () {
      var info = lastResults ? collectLinearLeftovers(lastResults.entries) : { leftovers: [], usedIds: [] };
      if (!info.leftovers.length) return;
      var minIn = parseNum(els.minOffcut && els.minOffcut.value) || 12;
      els.saveLeftoversBtn.disabled = true;
      saveLeftovers(info.leftovers, minIn)
        .then(function (data) {
          return consumeOffcuts(info.usedIds).then(function () { return data; });
        })
        .then(function (data) {
          els.saveLeftoversBtn.disabled = false;
          if (els.leftoversFeedback) {
            els.leftoversFeedback.textContent = 'Saved ' + (data.saved || info.leftovers.length) + ' leftover(s) to inventory.';
            show(els.leftoversFeedback, true);
          }
          return fetchOffcuts();
        })
        .then(scheduleRender)
        .catch(function () {
          els.saveLeftoversBtn.disabled = false;
          window.AdminDialog.alert('Could not save leftovers.');
        });
    });
  }
  if (els.runSheetsBtn) els.runSheetsBtn.addEventListener('click', runSheetOptimizer);
  if (els.saveSheetLeftoversBtn) {
    els.saveSheetLeftoversBtn.addEventListener('click', function () {
      if (!sheetState.last || !(sheetState.last.leftovers || []).length) return;
      var minIn = parseNum(els.minOffcut && els.minOffcut.value) || 2;
      els.saveSheetLeftoversBtn.disabled = true;
      saveLeftovers(sheetState.last.leftovers, minIn)
        .then(function () {
          return consumeOffcuts(sheetState.last.used_offcut_ids || []);
        })
        .then(function () {
          els.saveSheetLeftoversBtn.disabled = false;
          window.AdminDialog.alert('Sheet leftovers saved to inventory.');
          return fetchOffcuts();
        })
        .catch(function () {
          els.saveSheetLeftoversBtn.disabled = false;
          window.AdminDialog.alert('Could not save sheet leftovers.');
        });
    });
  }
  document.addEventListener('admin-search-select:change', function (e) {
    var picker = document.getElementById('lb-import-quote');
    if (!picker || !picker.contains(e.target)) return;
    var id = e.detail && e.detail.id;
    if (id) importQuotation(id);
  });
  if (els.copyBtn) els.copyBtn.addEventListener('click', copyResults);
  root.querySelectorAll('[data-lb-save-quick]').forEach(function (btn) {
    btn.addEventListener('click', focusSaveForm);
  });
  root.querySelectorAll('[data-lb-print]').forEach(function (btn) {
    btn.addEventListener('click', printMaterialList);
  });
  var exportPdfBtn = root.querySelector('[data-lb-export-pdf]');
  if (exportPdfBtn) exportPdfBtn.addEventListener('click', exportPdf);
  root.querySelector('[data-lb-save]').addEventListener('click', saveCalculation);
  els.saveName.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); saveCalculation(); }
  });

  (function hydrate() {
    var saved = [];
    try { saved = JSON.parse(root.getAttribute('data-saved')) || []; } catch (e) { saved = []; }
    saved.forEach(function (c) { els.savedList.appendChild(savedItemHtml(c)); });
    refreshSavedEmpty();
  })();

  resetAll();
  fetchOffcuts();

  (function loadFromQuery() {
    var calcId;
    try { calcId = new URLSearchParams(window.location.search).get('calculation'); } catch (e) { calcId = null; }
    if (!calcId) return;
    var li = els.savedList.querySelector('[data-id="' + calcId + '"]');
    var btn = li && li.querySelector('[data-lb-load]');
    if (btn) btn.click();
  })();
})();

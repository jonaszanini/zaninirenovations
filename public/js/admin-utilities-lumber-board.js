/* Lumber / Board Material Calculator — live bin-packing in the browser. */
(function () {
  'use strict';

  var root = document.querySelector('[data-lumber-board]');
  if (!root) return;

  var PIECE_COLORS = ['#024768', '#2a7a9b', '#3d8b6e', '#5c6bc0', '#8d6e63', '#6a8f3a'];

  var els = {
    unitToggle: root.querySelector('[data-lb-unit-toggle]'),
    modeToggle: root.querySelector('[data-lb-mode-toggle]'),
    modeFields: root.querySelectorAll('[data-lb-mode-field]'),
    singleStock: root.querySelector('[data-lb-single-stock]'),
    roundUp: root.querySelector('[data-lb-round-up]'),
    kerf: root.querySelector('[data-lb-kerf]'),
    stockList: root.querySelector('[data-lb-stock-list]'),
    piecesBody: root.querySelector('[data-lb-pieces-body]'),
    empty: root.querySelector('[data-lb-empty]'),
    error: root.querySelector('[data-lb-error]'),
    errorText: root.querySelector('[data-lb-error-text]'),
    live: root.querySelector('[data-lb-live]'),
    strategy: root.querySelector('[data-lb-strategy]'),
    statLinear: root.querySelector('[data-lb-stat-linear]'),
    statBoards: root.querySelector('[data-lb-stat-boards]'),
    statCost: root.querySelector('[data-lb-stat-cost]'),
    statWaste: root.querySelector('[data-lb-stat-waste]'),
    statWasteWrap: root.querySelector('[data-lb-stat-waste-wrap]'),
    breakdown: root.querySelector('[data-lb-breakdown]'),
    diagramCard: root.querySelector('[data-lb-diagram-card]'),
    diagrams: root.querySelector('[data-lb-diagrams]'),
    saveCard: root.querySelector('[data-lb-save-card]'),
    saveName: root.querySelector('#lb-save-name'),
    saveFeedback: root.querySelector('[data-lb-save-feedback]'),
    copyBtn: root.querySelector('[data-lb-copy]'),
    savedList: root.querySelector('[data-lb-saved-list]'),
    savedEmpty: root.querySelector('[data-lb-saved-empty]')
  };

  var state = {
    unit: 'ft',
    mode: 'minimize',
    manualCounts: {},
    stockIdSeq: 1,
    pieceIdSeq: 1
  };

  var lastResult = null;

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

  function toSixteenths(val, unit) {
    var n = parseNum(val);
    if (n <= 0) return 0;
    if (unit === 'ft') return Math.round(n * 12 * 16);
    return Math.round(n * 16);
  }

  function sixteenthsToInches(six) {
    return six / 16;
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

  function formatMoney(n) {
    if (!isFinite(n) || n <= 0) return '—';
    return '$' + (Math.round(n * 100) / 100).toFixed(2);
  }

  function kerfSixteenths() {
    return els.kerf && els.kerf.checked ? 2 : 0;
  }

  function stockRows() {
    var rows = [];
    els.stockList.querySelectorAll('[data-lb-stock-row]').forEach(function (row) {
      var id = row.getAttribute('data-id');
      var lenInput = row.querySelector('[data-lb-stock-length]');
      var priceInput = row.querySelector('[data-lb-stock-price]');
      var lenVal = lenInput ? lenInput.value : '';
      var six = toSixteenths(lenVal, state.unit);
      if (six > 0) {
        rows.push({
          id: id,
          lengthVal: lenVal,
          lengthSix: six,
          price: parseNum(priceInput ? priceInput.value : '')
        });
      }
    });
    return rows.sort(function (a, b) { return a.lengthSix - b.lengthSix; });
  }

  function pieceRows() {
    var rows = [];
    els.piecesBody.querySelectorAll('[data-lb-piece-row]').forEach(function (row) {
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
        lengthSix: toSixteenths(len ? len.value : '', state.unit),
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

  function solve(pieces, stocks, mode, singleStockId, kerf) {
    if (!pieces.length || !stocks.length) return null;
    var maxPiece = Math.max.apply(null, pieces.map(function (p) { return p.len; }));
    var maxStock = Math.max.apply(null, stocks.map(function (s) { return s.lengthSix; }));
    if (maxPiece > maxStock) {
      return { error: 'One or more pieces are longer than the longest available board (' + formatLength(maxStock, state.unit) + ').' };
    }

    if (mode === 'single') {
      var stock = stocks.find(function (s) { return s.id === singleStockId; });
      if (!stock) return { error: 'Select a board length for single-board mode.' };
      if (maxPiece > stock.lengthSix) {
        return { error: 'Some pieces are longer than the selected board (' + formatLength(stock.lengthSix, state.unit) + ').' };
      }
      var bins = packBestFit(pieces, stock.lengthSix, kerf);
      var groups = [{ stockId: stock.id, lengthSix: stock.lengthSix, bins: bins, count: state.manualCounts[stock.id] || bins.length }];
      var stats = solutionStats(groups, stocks, kerf);
      return {
        strategy: 'Single board type — ' + formatLength(stock.lengthSix, state.unit),
        groups: groups,
        stats: stats,
        stocks: stocks
      };
    }

    var candidates = [];
    stocks.forEach(function (stock) {
      var bins = packBestFit(pieces, stock.lengthSix, kerf);
      var groups = [{ stockId: stock.id, lengthSix: stock.lengthSix, bins: bins }];
      var stats = solutionStats(groups, stocks, kerf);
      candidates.push({
        strategy: 'All ' + formatLength(stock.lengthSix, state.unit) + ' boards',
        groups: groups,
        stats: stats,
        waste: stats.waste
      });
    });

    var multiGroups = packMultiStock(pieces, stocks, kerf);
    multiGroups.forEach(function (g) {
      if (state.manualCounts[g.stockId] != null) g.count = state.manualCounts[g.stockId];
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
      if (state.manualCounts[g.stockId] != null) g.count = state.manualCounts[g.stockId];
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

  function buildStockRow(data) {
    var id = data && data.id ? data.id : 's' + (state.stockIdSeq++);
    var row = document.createElement('div');
    row.className = 'util-lb-stock-row';
    row.setAttribute('data-lb-stock-row', '');
    row.setAttribute('data-id', id);
    var unitLabel = state.unit === 'ft' ? 'ft' : 'in';
    row.innerHTML =
      '<div class="util-lb-stock-row__length">' +
        '<input type="number" min="0" step="any" inputmode="decimal" placeholder="Length" data-lb-stock-length value="' + (data && data.lengthVal != null ? escapeAttr(data.lengthVal) : '') + '">' +
        '<span class="util-lb-unit-tag">' + unitLabel + '</span>' +
      '</div>' +
      '<div class="util-lb-stock-row__price">' +
        '<input type="number" min="0" step="any" inputmode="decimal" placeholder="Price ($)" data-lb-stock-price value="' + (data && data.price != null ? escapeAttr(data.price) : '') + '">' +
      '</div>' +
      '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-lb-remove-stock title="Remove"><i class="fa fa-times"></i></button>';
    row.querySelector('[data-lb-remove-stock]').addEventListener('click', function () {
      delete state.manualCounts[id];
      row.remove();
      syncSingleStockOptions();
      render();
    });
    row.querySelectorAll('input').forEach(function (inp) {
      inp.addEventListener('input', render);
    });
    return row;
  }

  function buildPieceRow(data) {
    var row = document.createElement('tr');
    row.setAttribute('data-lb-piece-row', '');
    row.innerHTML =
      '<td><input type="text" placeholder="Optional" data-lb-piece-desc value="' + (data && data.desc ? escapeAttr(data.desc) : '') + '"></td>' +
      '<td><input type="number" min="0" step="any" inputmode="decimal" placeholder="Length" data-lb-piece-length value="' + (data && data.lengthVal != null ? escapeAttr(data.lengthVal) : '') + '"></td>' +
      '<td><input type="number" min="1" step="1" inputmode="numeric" placeholder="1" data-lb-piece-qty value="' + (data && data.qty != null ? escapeAttr(data.qty) : '1') + '"></td>' +
      '<td><button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-lb-remove-piece title="Remove"><i class="fa fa-times"></i></button></td>';
    row.querySelector('[data-lb-remove-piece]').addEventListener('click', function () {
      row.remove();
      render();
    });
    row.querySelectorAll('input').forEach(function (inp) {
      inp.addEventListener('input', render);
    });
    return row;
  }

  function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function syncSingleStockOptions() {
    if (!els.singleStock) return;
    var current = els.singleStock.value;
    var stocks = stockRows();
    els.singleStock.innerHTML = '<option value="">— Select board length —</option>';
    stocks.forEach(function (s) {
      var opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = formatLength(s.lengthSix, state.unit);
      els.singleStock.appendChild(opt);
    });
    if (current && stocks.some(function (s) { return s.id === current; })) {
      els.singleStock.value = current;
    }
  }

  function updateUnitLabels() {
    var tag = state.unit === 'ft' ? 'ft' : 'in';
    root.querySelectorAll('.util-lb-unit-tag').forEach(function (el) {
      el.textContent = tag;
    });
    root.querySelectorAll('[data-quick-ft]').forEach(function (btn) {
      btn.hidden = state.unit !== 'ft';
    });
  }

  function renderBreakdown(result) {
    els.breakdown.innerHTML = '';
    var head = document.createElement('p');
    head.className = 'util-lb-breakdown__title';
    head.textContent = 'Boards to buy';
    els.breakdown.appendChild(head);

    result.groups.forEach(function (g) {
      var stock = result.stocks.find(function (s) { return s.id === g.stockId || s.lengthSix === g.lengthSix; });
      var count = g.count != null ? g.count : g.bins.length;
      var row = document.createElement('div');
      row.className = 'util-lb-breakdown-row';
      var label = formatLength(g.lengthSix, state.unit);
      var priceHint = stock && stock.price > 0 ? ' @ ' + formatMoney(stock.price) + ' each' : '';
      row.innerHTML =
        '<div class="util-lb-breakdown-row__info">' +
          '<strong>' + label + '</strong>' + priceHint +
        '</div>' +
        '<div class="util-lb-breakdown-row__qty">' +
          '<button type="button" class="util-lb-qty-btn" data-lb-qty-dec aria-label="Decrease">−</button>' +
          '<input type="number" min="0" step="1" class="util-lb-qty-input" data-lb-qty-input value="' + count + '">' +
          '<button type="button" class="util-lb-qty-btn" data-lb-qty-inc aria-label="Increase">+</button>' +
        '</div>';
      var stockId = g.stockId;
      function setCount(n) {
        n = Math.max(0, parseInt(n, 10) || 0);
        state.manualCounts[stockId] = n;
        render();
      }
      row.querySelector('[data-lb-qty-dec]').addEventListener('click', function () {
        var inp = row.querySelector('[data-lb-qty-input]');
        setCount((parseInt(inp.value, 10) || 0) - 1);
      });
      row.querySelector('[data-lb-qty-inc]').addEventListener('click', function () {
        var inp = row.querySelector('[data-lb-qty-input]');
        setCount((parseInt(inp.value, 10) || 0) + 1);
      });
      row.querySelector('[data-lb-qty-input]').addEventListener('change', function (e) {
        setCount(e.target.value);
      });
      els.breakdown.appendChild(row);
    });
  }

  function buildDiagramSvg(group, stockLabel) {
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
    svg += '<text x="' + pad + '" y="16" class="util-lb-diagram-title">' + stockLabel + ' (' + count + ' board' + (count === 1 ? '' : 's') + ')</text>';
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

  function escapeXml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderDiagrams(result) {
    els.diagrams.innerHTML = '';
    result.groups.forEach(function (g) {
      var count = g.count != null ? g.count : g.bins.length;
      if (count < 1) return;
      var wrap = document.createElement('div');
      wrap.className = 'util-lb-diagram-block';
      wrap.innerHTML = buildDiagramSvg(g, formatLength(g.lengthSix, state.unit));
      els.diagrams.appendChild(wrap);
    });
  }

  function summaryText(result, linearSix) {
    if (!result || result.error) return '';
    var s = result.stats;
    return result.strategy + ' — ' + result.stats.boards + ' board(s), ' +
      formatLength(s.waste, state.unit) + ' waste (' + s.wastePct.toFixed(1) + '%), ' +
      formatLength(linearSix, state.unit) + ' required';
  }

  function render() {
    syncSingleStockOptions();
    var stocks = stockRows();
    var pieces = expandPieces(pieceRows());
    var linearSix = pieces.reduce(function (sum, p) { return sum + p.len; }, 0);
    var kerf = kerfSixteenths();
    var singleId = els.singleStock ? els.singleStock.value : '';

    if (!stocks.length || !pieces.length) {
      lastResult = null;
      show(els.empty, true);
      show(els.error, false);
      show(els.live, false);
      show(els.diagramCard, false);
      show(els.saveCard, false);
      show(els.copyBtn, false);
      return;
    }

    var result = solve(pieces, stocks, state.mode, singleId, kerf);
    if (!result || result.error) {
      lastResult = null;
      show(els.empty, false);
      show(els.error, true);
      if (els.errorText) els.errorText.textContent = result ? result.error : 'Could not calculate.';
      show(els.live, false);
      show(els.diagramCard, false);
      show(els.saveCard, false);
      show(els.copyBtn, false);
      return;
    }

    lastResult = { result: result, linearSix: linearSix, pieces: pieces };
    show(els.empty, false);
    show(els.error, false);
    show(els.live, true);
    show(els.diagramCard, true);
    show(els.saveCard, true);
    show(els.copyBtn, true);

    var s = result.stats;
    if (els.strategy) {
      var hint = result.suggestion && result.suggestion !== result.strategy
        ? ' <span class="util-lb-strategy__alt">Next best: ' + result.suggestion + '</span>'
        : '';
      els.strategy.innerHTML = '<strong>' + result.strategy + '</strong>' + hint;
    }
    if (els.statLinear) els.statLinear.textContent = formatLength(linearSix, state.unit);
    if (els.statBoards) els.statBoards.textContent = String(s.boards);
    if (els.statCost) els.statCost.textContent = s.cost > 0 ? formatMoney(s.cost) : '—';
    if (els.statWaste) {
      els.statWaste.textContent = formatLength(s.waste, state.unit) + ' (' + s.wastePct.toFixed(1) + '%)';
    }
    if (els.statWasteWrap) {
      els.statWasteWrap.className = 'util-lb-stat util-lb-stat--waste ' + wasteClass(s.wastePct);
    }

    renderBreakdown(result);
    renderDiagrams(result);
  }

  function resultRows() {
    if (!lastResult) return [];
    var r = lastResult.result;
    var s = r.stats;
    return [
      { label: 'Strategy', value: r.strategy },
      { label: 'Linear length required', value: formatLength(lastResult.linearSix, state.unit) },
      { label: 'Total boards', value: String(s.boards) },
      { label: 'Total waste', value: formatLength(s.waste, state.unit) + ' (' + s.wastePct.toFixed(1) + '%)' },
      { label: 'Estimated cost', value: s.cost > 0 ? formatMoney(s.cost) : '—' }
    ];
  }

  function breakdownRows() {
    if (!lastResult) return [];
    return lastResult.result.groups.map(function (g) {
      var count = g.count != null ? g.count : g.bins.length;
      return {
        label: formatLength(g.lengthSix, state.unit),
        value: count + ' board' + (count === 1 ? '' : 's')
      };
    });
  }

  function svgToPngDataUri(svgEl, cb) {
    var svg = svgEl.outerHTML || svgEl;
    if (typeof svg === 'string') {
      var tmp = document.createElement('div');
      tmp.innerHTML = svg;
      svgEl = tmp.querySelector('svg');
    }
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
    if (!lastResult) return;
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
    add('summary', summaryText(lastResult.result, lastResult.linearSix));
    resultRows().forEach(function (row, i) {
      add('rows[' + i + '][label]', row.label);
      add('rows[' + i + '][value]', row.value);
    });
    breakdownRows().forEach(function (row, i) {
      add('breakdown[' + i + '][label]', row.label);
      add('breakdown[' + i + '][value]', row.value);
    });

    var svgs = els.diagrams.querySelectorAll('svg');
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
    if (!lastResult) return;
    var lines = resultRows().map(function (r) { return r.label + ': ' + r.value; });
    lines.push('');
    lines.push('Boards to buy:');
    breakdownRows().forEach(function (r) { lines.push('  ' + r.label + ': ' + r.value); });
    var text = lines.join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text);
    } else {
      window.prompt('Copy:', text);
    }
  }

  function currentPayload() {
    return {
      unit: state.unit,
      mode: state.mode,
      single_stock_id: els.singleStock ? els.singleStock.value : '',
      round_up: !!(els.roundUp && els.roundUp.checked),
      kerf: !!(els.kerf && els.kerf.checked),
      stocks: stockRows().map(function (s) { return { id: s.id, lengthVal: s.lengthVal, price: s.price }; }),
      pieces: pieceRows().map(function (p) { return { desc: p.desc, lengthVal: p.lengthVal, qty: p.qty }; }),
      manual_counts: state.manualCounts,
      summary: lastResult ? summaryText(lastResult.result, lastResult.linearSix) : ''
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
    var btn = root.querySelector('[data-lb-save]');
    if (btn) btn.disabled = true;
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
        tool: 'lumber_board',
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
    els.saveFeedback.className = 'util-lb-save-feedback ' + (ok ? 'is-ok' : 'is-error');
    show(els.saveFeedback, true);
    setTimeout(function () { show(els.saveFeedback, false); }, 3500);
  }

  function savedItemHtml(c) {
    var payload = c.payload || {};
    var summary = payload.summary || '';
    var li = document.createElement('li');
    li.className = 'util-lb-saved-item';
    li.setAttribute('data-id', c.id);
    li.innerHTML =
      '<div class="util-lb-saved-item__main">' +
        '<span class="util-lb-saved-item__name"></span>' +
        '<span class="util-lb-saved-item__summary"></span>' +
        '<span class="util-lb-saved-item__date"></span>' +
      '</div>' +
      '<div class="util-lb-saved-item__actions">' +
        '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-lb-load><i class="fa fa-upload"></i> Load</button>' +
        '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-lb-delete><i class="fa fa-trash"></i></button>' +
      '</div>';
    li.querySelector('.util-lb-saved-item__name').textContent = c.name;
    li.querySelector('.util-lb-saved-item__summary').textContent = summary;
    li.querySelector('.util-lb-saved-item__date').textContent = c.created_label || '';
    li.querySelector('[data-lb-load]').addEventListener('click', function () { loadPayload(payload); });
    li.querySelector('[data-lb-delete]').addEventListener('click', function () { deleteSaved(c.id, li); });
    return li;
  }

  function prependSaved(c) {
    els.savedList.insertBefore(savedItemHtml(c), els.savedList.firstChild);
    refreshSavedEmpty();
  }

  function refreshSavedEmpty() {
    show(els.savedEmpty, els.savedList.children.length === 0);
  }

  function deleteSaved(id, li) {
    if (!window.confirm('Delete this saved calculation?')) return;
    var url = root.getAttribute('data-destroy-template').replace('__ID__', id);
    fetch(url, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function () { li.remove(); refreshSavedEmpty(); })
      .catch(function () { window.alert('Could not delete. Please try again.'); });
  }

  function loadPayload(p) {
    if (!p) return;
    state.unit = p.unit === 'in' ? 'in' : 'ft';
    state.mode = p.mode === 'single' ? 'single' : 'minimize';
    state.manualCounts = p.manual_counts || {};
    setActive(els.unitToggle, 'data-unit', state.unit);
    setActive(els.modeToggle, 'data-mode', state.mode);
    els.modeFields.forEach(function (f) {
      show(f, f.getAttribute('data-lb-mode-field') === state.mode);
    });
    if (els.roundUp) els.roundUp.checked = !!p.round_up;
    if (els.kerf) els.kerf.checked = p.kerf !== false;
    els.stockList.innerHTML = '';
    (p.stocks || []).forEach(function (s) {
      state.stockIdSeq = Math.max(state.stockIdSeq, parseInt(String(s.id).replace(/\D/g, ''), 10) + 1 || 1);
      els.stockList.appendChild(buildStockRow(s));
    });
    els.piecesBody.innerHTML = '';
    (p.pieces || []).forEach(function (row) {
      els.piecesBody.appendChild(buildPieceRow(row));
    });
    if (els.singleStock && p.single_stock_id) els.singleStock.value = p.single_stock_id;
    updateUnitLabels();
    render();
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function setActive(group, attr, value) {
    if (!group) return;
    group.querySelectorAll('.util-segmented__btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute(attr) === value);
    });
  }

  function addQuickStock(ft) {
    var existing = stockRows().some(function (s) {
      return Math.abs(s.lengthSix - toSixteenths(ft, 'ft')) < 1;
    });
    if (existing) return;
    els.stockList.appendChild(buildStockRow({ lengthVal: String(ft) }));
    render();
  }

  function initDefaults() {
    [8, 10, 12, 16].forEach(function (ft) {
      els.stockList.appendChild(buildStockRow({ lengthVal: String(ft) }));
    });
    els.piecesBody.appendChild(buildPieceRow({ qty: 1 }));
    updateUnitLabels();
  }

  root.querySelector('[data-lb-add-stock]').addEventListener('click', function () {
    els.stockList.appendChild(buildStockRow({}));
    render();
  });
  root.querySelector('[data-lb-add-piece]').addEventListener('click', function () {
    els.piecesBody.appendChild(buildPieceRow({ qty: 1 }));
    render();
  });
  root.querySelector('[data-lb-reset]').addEventListener('click', function () {
    state.manualCounts = {};
    state.mode = 'minimize';
    state.unit = 'ft';
    setActive(els.unitToggle, 'data-unit', 'ft');
    setActive(els.modeToggle, 'data-mode', 'minimize');
    els.modeFields.forEach(function (f) { show(f, false); });
    if (els.roundUp) els.roundUp.checked = false;
    if (els.kerf) els.kerf.checked = true;
    els.stockList.innerHTML = '';
    els.piecesBody.innerHTML = '';
    initDefaults();
    render();
  });

  els.unitToggle.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-unit]');
    if (!btn) return;
    state.unit = btn.getAttribute('data-unit');
    setActive(els.unitToggle, 'data-unit', state.unit);
    updateUnitLabels();
    render();
  });

  els.modeToggle.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-mode]');
    if (!btn) return;
    state.mode = btn.getAttribute('data-mode');
    setActive(els.modeToggle, 'data-mode', state.mode);
    els.modeFields.forEach(function (f) {
      show(f, f.getAttribute('data-lb-mode-field') === state.mode);
    });
    render();
  });

  if (els.singleStock) els.singleStock.addEventListener('change', render);
  if (els.roundUp) els.roundUp.addEventListener('change', render);
  if (els.kerf) els.kerf.addEventListener('change', render);

  root.querySelectorAll('[data-quick-ft]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      addQuickStock(parseInt(btn.getAttribute('data-quick-ft'), 10));
    });
  });

  if (els.copyBtn) els.copyBtn.addEventListener('click', copyResults);
  root.querySelector('[data-lb-export-pdf]').addEventListener('click', exportPdf);
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

  initDefaults();
  render();
})();

(function () {
  var cfg = window.MW_QUOTATION || {};
  var form = document.getElementById('mw-main-form');
  if (form) {
    var previewUrl = cfg.previewUrl || '';
    var isEdit = !!cfg.isEdit;
    var sectionKeys = (cfg.sectionKeys || []).slice();
    var wizardKeys = sectionKeys.slice();
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';
    var debounceTimer = null;
    var lastDebrisAmount = 0;
    var lastAdditionalMaterialsPreTax = 0;
    var lastAdditionalMaterialsHst = 0;
    var lastAdditionalLaborPreTax = 0;
    var lastAdditionalLaborHst = 0;
    var lastAdditionalTaxIn = 0;
    var lastCommuteAmount = 0;
    var lastPreviewData = null;
    var currentIndex = 0;

    var fmt = function (n) {
      return 'CA$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };
    var escapeHtml = function (s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    };
    var kerfLabel = function (inches) {
      var n = Number(inches);
      if (!(n > 0)) return '';
      if (Math.abs(n - 0.125) < 0.0005) return '1/8"';
      if (Math.abs(n - 0.25) < 0.0005) return '1/4"';
      return String(Math.round(n * 1000) / 1000) + '"';
    };
    var cutPlanMetaText = function (plan) {
      var text = (plan.sheet_count || 0) + ' sheet(s)'
        + (plan.yield_pct ? ' · ' + plan.yield_pct + '% yield' : '');
      var kerf = kerfLabel(plan.kerf_in);
      if (kerf) text += ' · Kerf ' + kerf;
      return text;
    };

    function findStepKeyForElement(el) {
      var step = el && el.closest ? el.closest('[data-br-step]') : null;
      return step ? step.getAttribute('data-br-step') : null;
    }

    function sectionEl(key) {
      return form.querySelector('[data-br-step="' + key + '"]');
    }

    function setWizardStep(idx, scroll) {
      if (idx < 0) idx = 0;
      if (idx >= wizardKeys.length) idx = wizardKeys.length - 1;
      currentIndex = idx;
      form.querySelectorAll('.br-acc').forEach(function (el) {
        var on = el.getAttribute('data-br-step') === wizardKeys[idx];
        el.classList.toggle('is-active', on);
        if (on) el.setAttribute('open', '');
        else el.removeAttribute('open');
        el.hidden = !on;
      });
      form.querySelectorAll('.br-progress__item').forEach(function (b) {
        b.classList.toggle('is-current', b.getAttribute('data-mw-goto') === wizardKeys[idx]);
      });
      var status = document.getElementById('mw-step-status');
      if (status) status.textContent = (idx + 1) + ' of ' + wizardKeys.length;
      var prevBtn = document.getElementById('mw-prev');
      var nextBtn = document.getElementById('mw-next');
      if (prevBtn) prevBtn.disabled = idx === 0;
      if (nextBtn) {
        nextBtn.disabled = false;
        nextBtn.textContent = idx === wizardKeys.length - 1 ? 'Review' : 'Next';
      }
      if (scroll) {
        var el = sectionEl(wizardKeys[idx]);
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function submitWizard(intent) {
      var intentEl = document.getElementById('mw-save-intent');
      if (intentEl) intentEl.value = intent;
      if (!isEdit) {
        form.querySelectorAll('.br-acc').forEach(function (el) {
          el.classList.add('is-active');
          el.setAttribute('open', '');
          el.hidden = false;
        });
      }
      if (!form.checkValidity()) {
        var invalid = form.querySelector(':invalid');
        if (!isEdit) {
          var key = findStepKeyForElement(invalid);
          var idx = key ? wizardKeys.indexOf(key) : 0;
          if (idx < 0) idx = 0;
          setWizardStep(idx, true);
        }
        setTimeout(function () {
          if (!invalid) return;
          try { invalid.focus(); } catch (err) { /* ignore */ }
          if (typeof invalid.reportValidity === 'function') invalid.reportValidity();
        }, 80);
        return;
      }
      HTMLFormElement.prototype.submit.call(form);
    }

    var draftBtn = document.getElementById('mw-btn-draft');
    var publishBtn = document.getElementById('mw-btn-publish');
    if (draftBtn) {
      draftBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitWizard('draft');
      });
    }
    if (publishBtn) {
      publishBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitWizard('publish');
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitWizard('publish');
    });

    if (!isEdit) {
      setWizardStep(0, false);
      var prevBtn = document.getElementById('mw-prev');
      var nextBtn = document.getElementById('mw-next');
      if (prevBtn) prevBtn.addEventListener('click', function () { setWizardStep(currentIndex - 1, true); });
      if (nextBtn) nextBtn.addEventListener('click', function () { setWizardStep(currentIndex + 1, true); });
    } else {
      var nav = document.getElementById('mw-step-nav');
      if (nav) nav.hidden = true;
    }

    form.querySelectorAll('[data-mw-goto]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-mw-goto');
        var idx = wizardKeys.indexOf(key);
        if (idx < 0) return;
        if (isEdit) {
          var el = sectionEl(key);
          if (el) {
            el.setAttribute('open', '');
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
          form.querySelectorAll('.br-progress__item').forEach(function (b) {
            b.classList.toggle('is-current', b.getAttribute('data-mw-goto') === key);
          });
          return;
        }
        setWizardStep(idx, true);
      });
    });

    function schedulePreview() {
      syncHardwareCatalog();
      syncMaterialsCatalog();
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(runPreview, 280);
    }

    function runPreview() {
      if (!previewUrl) return;
      var body = new FormData(form);
      body.delete('_method');
      fetch(previewUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body,
        credentials: 'same-origin'
      }).then(function (res) { return res.json(); }).then(function (data) {
        renderPreview(data);
      }).catch(function () { /* ignore */ });
    }

    function renderPreview(data) {
      lastPreviewData = data;
      if (window.QuotationBuildingLaborUi) window.QuotationBuildingLaborUi.sync(data);
      var empty = document.getElementById('mw-live-empty');
      var body = document.getElementById('mw-live-body');
      var summary = data.summary || {};
      rebuildLaborTable(data.labor || []);
      rebuildMatTable((data.materials || []).concat(data.extra_materials || []).filter(function (r) {
        return !String(r.key || '').startsWith('extra_');
      }));
      renderCutPlan(data.cut_plan || {});
      var liveGrand = Number(summary.grand_total || 0) > 0
        ? Number(summary.grand_total) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount
        : 0;
      window.__quoteGrandTotal = liveGrand;
      document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: liveGrand } }));
      if (!summary.grand_total || summary.grand_total <= 0) {
        if (empty) empty.hidden = false;
        if (body) body.hidden = true;
        return;
      }
      if (empty) empty.hidden = true;
      if (body) body.hidden = false;
      var laborPre = Number(summary.labor_pre_hst || 0) + lastAdditionalLaborPreTax;
      var laborHst = Number(summary.labor_hst != null ? summary.labor_hst : Number(summary.labor_pre_hst || 0) * 0.13) + lastAdditionalLaborHst;
      var matPre = Number(summary.materials_pre_hst || 0) + lastAdditionalMaterialsPreTax;
      var matHst = Number(summary.materials_hst || 0) + lastAdditionalMaterialsHst;
      var html = '';
      html += '<div class="pq-row"><span>Labor (pre-HST)</span><span>' + fmt(laborPre) + '</span></div>';
      html += '<div class="pq-row"><span>Labor HST</span><span>' + fmt(laborHst) + '</span></div>';
      html += '<div class="pq-row pq-row--sub"><span>Total labor</span><span>' + fmt(laborPre + laborHst) + '</span></div>';
      html += '<div class="pq-row pq-row--group"><span>Materials (pre-HST)</span><span>' + fmt(matPre) + '</span></div>';
      html += '<div class="pq-row"><span>Materials HST</span><span>' + fmt(matHst) + '</span></div>';
      html += '<div class="pq-row pq-row--sub"><span>Total materials</span><span>' + fmt(matPre + matHst) + '</span></div>';
      if (lastCommuteAmount > 0) {
        html += '<div class="pq-row pq-row--group"><span>Commute (incl. HST)</span><span>' + fmt(lastCommuteAmount) + '</span></div>';
      }
      if (lastDebrisAmount > 0) {
        html += '<div class="pq-row pq-row--group"><span>Debris removal (incl. HST)</span><span>' + fmt(lastDebrisAmount) + '</span></div>';
      }
      var sheets = (data.cut_plan && data.cut_plan.sheet_count) || 0;
      if (sheets) {
        html += '<div class="pq-row pq-row--group"><span>Sheets</span><span>' + sheets + '</span></div>';
      }
      var edgeLf = Number(summary.edge_band_lf || 0);
      if (edgeLf > 0) {
        html += '<div class="pq-row pq-row--group"><span>Edge banding</span><span>' + edgeLf.toFixed(1) + ' LF</span></div>';
      }
      var ebMeta = document.getElementById('mw-eb-meta');
      if (ebMeta) {
        ebMeta.textContent = edgeLf > 0 ? ('Total edge banding: ' + edgeLf.toFixed(1) + ' LF required') : '';
      }
      var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
      html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + fmt(grand) + '</span></div>';
      body.innerHTML = html;
    }

    function renderCutPlan(plan) {
      var root = document.getElementById('mw-cut-plan');
      var meta = document.getElementById('mw-cut-meta');
      var printBtn = document.getElementById('mw-print-cut-list');
      var layouts = plan.layouts || [];
      if (meta) {
        meta.textContent = cutPlanMetaText(plan);
      }
      if (printBtn) printBtn.disabled = !layouts.length;
      if (!root) return;
      if (!layouts.length) {
        root.innerHTML = '<p class="admin-form-help">Enter piece dimensions to generate a guillotine cut plan.</p>';
        return;
      }
      root.innerHTML = layouts.map(function (layout) {
        var stats = layout.stats || {};
        var legend = (layout.legend || []).map(function (row) {
          return '<li><span class="mw-cut-legend__n">' + escapeHtml(row.n || '') + '</span>'
            + '<span>' + escapeHtml(row.label || 'Part') + ' · ' + escapeHtml(row.size || '')
            + (row.rotated ? ' · rotated' : '') + '</span></li>';
        }).join('');
        return '<div class="mw-cut-sheet">'
          + '<div class="mw-cut-sheet__stats"><strong>' + escapeHtml(stats.label || 'Sheet') + '</strong>'
          + '<span>Used ' + escapeHtml(stats.used_pct || 0) + '% · waste ' + escapeHtml(stats.waste_pct || 0) + '%</span></div>'
          + '<div class="mw-cut-svg-wrap">' + (layout.svg || '') + '</div>'
          + (legend ? '<ul class="mw-cut-legend">' + legend + '</ul>' : '')
          + '</div>';
      }).join('');
    }

    function printCutList() {
      var plan = (lastPreviewData && lastPreviewData.cut_plan) || {};
      var layouts = plan.layouts || [];
      if (!layouts.length) return;
      var clientEl = document.getElementById('mw-client-name');
      var client = clientEl ? String(clientEl.value || '').trim() : '';
      var quote = String(cfg.quoteNumber || '').trim();
      var titleBits = [];
      if (quote) titleBits.push(quote);
      if (client) titleBits.push(client);
      var drawingsHtml = layouts.map(function (layout, i) {
        return '<section class="mw-cut-print-drawing" aria-label="Sheet ' + (i + 1) + ' drawing">'
          + '<div class="mw-cut-svg-wrap">' + (layout.svg || '') + '</div>'
          + '</section>';
      }).join('');
      var detailsHtml = layouts.map(function (layout, i) {
        var stats = layout.stats || {};
        var legend = (layout.legend || []).map(function (row) {
          return '<li><span class="mw-cut-legend__n">' + escapeHtml(row.n || '') + '</span>'
            + '<span>' + escapeHtml(row.label || 'Part') + ' · ' + escapeHtml(row.size || '')
            + (row.rotated ? ' · rotated' : '') + '</span></li>';
        }).join('');
        return '<article class="mw-cut-print-sheet-details">'
          + '<h2>Sheet ' + (i + 1) + ': ' + escapeHtml(stats.label || 'Sheet')
          + ' Used ' + escapeHtml(stats.used_pct || 0) + '% · waste ' + escapeHtml(stats.waste_pct || 0) + '%</h2>'
          + (legend ? '<ul class="mw-cut-legend">' + legend + '</ul>' : '')
          + '</article>';
      }).join('');
      var html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width, initial-scale=1">'
        + '<title>Cut list' + (quote ? ' — ' + escapeHtml(quote) : '') + '</title>'
        + (cfg.printCssUrl ? '<link rel="stylesheet" href="' + escapeHtml(cfg.printCssUrl) + '">' : '')
        + '</head><body>'
        + '<div class="toolbar no-print"><button type="button" onclick="window.print()">Print cut list</button></div>'
        + '<div class="mw-cut-print-drawings">' + drawingsHtml + '</div>'
        + '<section class="mw-cut-print-details"><h1>Details</h1>'
        + '<p class="meta">' + (titleBits.length ? '<strong>' + escapeHtml(titleBits.join(' · ')) + '</strong><br>' : '')
        + escapeHtml(cutPlanMetaText(plan)) + '</p>'
        + detailsHtml + '</section>'
        + '</body></html>';
      printHtmlDocument(html);
    }

    function printHtmlDocument(html) {
      var existing = document.getElementById('mw-cut-print-frame');
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      var iframe = document.createElement('iframe');
      iframe.id = 'mw-cut-print-frame';
      iframe.setAttribute('title', 'Cut list print');
      iframe.setAttribute('aria-hidden', 'true');
      iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
      document.body.appendChild(iframe);
      var win = iframe.contentWindow;
      var doc = win && win.document;
      if (!doc) return;
      doc.open();
      doc.write(html);
      doc.close();

      var printed = false;
      var finish = function () {
        if (printed) return;
        printed = true;
        try {
          win.focus();
          win.print();
        } catch (err) { /* ignore */ }
        window.setTimeout(function () {
          if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
        }, 1500);
      };

      var links = doc.querySelectorAll('link[rel="stylesheet"]');
      if (!links.length) {
        window.setTimeout(finish, 50);
        return;
      }
      var pending = links.length;
      var onSheet = function () {
        pending -= 1;
        if (pending <= 0) finish();
      };
      for (var i = 0; i < links.length; i++) {
        links[i].addEventListener('load', onSheet);
        links[i].addEventListener('error', onSheet);
      }
      window.setTimeout(finish, 1200);
    }

    function rebuildLaborTable(rows) {
      var tbody = document.getElementById('mw-labor-rows');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="3" class="admin-form-help">Labor appears after pieces are sized.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(function (row) {
        return '<tr><td>' + escapeHtml(row.label) + '</td><td>' + Number(row.hours || 0).toFixed(2) + '</td>'
          + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td></tr>';
      }).join('');
    }

    function rebuildMatTable(rows) {
      var tbody = document.getElementById('mw-mat-rows');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after size is entered.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(function (row) {
        var provided = row.client_provided ? ' <span class="admin-form-help"> · client provided</span>' : '';
        return '<tr><td>' + escapeHtml(row.label) + provided + '</td>'
          + '<td>' + Number(row.qty || 0).toFixed(2) + ' ' + escapeHtml(row.unit || '') + '</td>'
          + '<td>' + fmt(row.unit_price || 0) + '</td>'
          + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td></tr>';
      }).join('');
    }

    function bindPreview(el) {
      if (!el || el.dataset.mwBound === '1') return;
      el.dataset.mwBound = '1';
      el.addEventListener('input', schedulePreview);
      el.addEventListener('change', schedulePreview);
    }
    form.querySelectorAll('.mw-preview').forEach(bindPreview);

    function syncPieceType(piece) {
      var checked = piece.querySelector('[data-mw-type-input]:checked');
      var type = checked ? checked.value : 'shelf';
      piece.setAttribute('data-mw-type', type);
      var below = piece.querySelector('select[name$="[below]"]');
      piece.setAttribute('data-mw-below', (below && below.value === 'drawers') ? 'drawers' : 'divisions');
      syncHardwareCatalog();
      syncMaterialsCatalog();
    }

    function pieceNeedsCabinetHardware(piece) {
      var type = piece.getAttribute('data-mw-type') || 'shelf';
      if (type === 'cabinet' || type === 'cabinet_door') return true;
      return type === 'bench' && piece.getAttribute('data-mw-below') === 'drawers';
    }

    function syncHardwareCatalog() {
      var show = false;
      form.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(function (piece) {
        if (pieceNeedsCabinetHardware(piece)) show = true;
      });
      form.querySelectorAll('[data-mw-hw="cabinet-family"]').forEach(function (el) {
        el.hidden = !show;
      });
    }

    function syncMaterialsCatalog() {
      var hasBack = false;
      var hasEdgeBanding = false;
      var hasPaint = false;
      var hasStain = false;

      form.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(function (piece) {
        var typeInput = piece.querySelector('[data-mw-type-input]:checked');
        var type = typeInput ? typeInput.value : (piece.getAttribute('data-mw-type') || 'shelf');

        var backSupports = ['floating_shelf', 'kallax', 'cabinet', 'bench'].indexOf(type) !== -1;
        var backCb = piece.querySelector('input[type="checkbox"][name$="[include_back]"]');
        if (backSupports && backCb && backCb.checked) {
          hasBack = true;
        }

        var ebCb = piece.querySelector('input[type="checkbox"][name$="[edge_banding]"]');
        if (ebCb && ebCb.checked) {
          hasEdgeBanding = true;
        }

        var finishSel = piece.querySelector('select[name$="[finish]"]');
        var finishVal = finishSel ? finishSel.value : 'none';
        if (finishVal === 'paint') {
          hasPaint = true;
        } else if (finishVal === 'stain') {
          hasStain = true;
        }
      });

      form.querySelectorAll('[data-mw-mat="back"]').forEach(function (el) {
        el.hidden = !hasBack;
      });
      form.querySelectorAll('[data-mw-mat="edge-banding"]').forEach(function (el) {
        el.hidden = !hasEdgeBanding;
      });
      form.querySelectorAll('[data-mw-mat="paint"]').forEach(function (el) {
        el.hidden = !hasPaint;
      });
      form.querySelectorAll('[data-mw-mat="stain"]').forEach(function (el) {
        el.hidden = !hasStain;
      });
    }

    function reindexPieces() {
      form.querySelectorAll('[data-mw-piece]').forEach(function (piece, i) {
        var n = piece.querySelector('[data-mw-piece-n]');
        if (n) n.textContent = String(i + 1);
        piece.querySelectorAll('[name]').forEach(function (input) {
          input.name = input.name.replace(/pieces\[\d+\]/, 'pieces[' + i + ']');
          if (input.id) input.id = input.id.replace(/pieces-\d+/, 'pieces-' + i);
        });
      });
    }

    function initPiece(piece) {
      if (!piece) return;
      piece.querySelectorAll('.mw-preview').forEach(bindPreview);
      piece.querySelectorAll('[data-mw-type-input]').forEach(function (inp) {
        inp.addEventListener('change', function () {
          syncPieceType(piece);
          schedulePreview();
        });
      });
      var below = piece.querySelector('select[name$="[below]"]');
      if (below) {
        below.addEventListener('change', function () {
          syncPieceType(piece);
          schedulePreview();
        });
      }
      syncPieceType(piece);
      var remove = piece.querySelector('[data-mw-remove-piece]');
      if (remove) {
        remove.addEventListener('click', function () {
          var list = document.getElementById('mw-piece-list');
          if (list && list.querySelectorAll('[data-mw-piece]').length <= 1) return;
          piece.remove();
          reindexPieces();
          syncHardwareCatalog();
          schedulePreview();
        });
      }
      var addSet = piece.querySelector('[data-mw-add-set]');
      if (addSet) {
        addSet.addEventListener('click', function () {
          var list = piece.querySelector('[data-mw-set-list]');
          var tpl = document.getElementById('mw-set-template');
          if (!list || !tpl) return;
          var pIndex = Array.prototype.indexOf.call(document.querySelectorAll('#mw-piece-list [data-mw-piece]'), piece);
          var sIndex = list.querySelectorAll('[data-mw-set-row]').length;
          var wrap = document.createElement('div');
          wrap.innerHTML = tpl.innerHTML.replace(/__P__/g, String(pIndex)).replace(/__S__/g, String(sIndex)).trim();
          var row = wrap.firstElementChild;
          list.appendChild(row);
          row.querySelectorAll('.mw-preview').forEach(bindPreview);
          bindSetRemove(row);
          schedulePreview();
        });
      }
      piece.querySelectorAll('[data-mw-set-row]').forEach(bindSetRemove);
    }

    function bindSetRemove(row) {
      var btn = row.querySelector('[data-mw-remove-set]');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var list = row.closest('[data-mw-set-list]');
        if (list && list.querySelectorAll('[data-mw-set-row]').length <= 1) return;
        row.remove();
        schedulePreview();
      });
    }

    document.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(initPiece);
    syncHardwareCatalog();
    syncMaterialsCatalog();
    var addPiece = document.getElementById('mw-add-piece');
    var pieceTpl = document.getElementById('mw-piece-template');
    var pieceList = document.getElementById('mw-piece-list');
    if (addPiece && pieceTpl && pieceList) {
      addPiece.addEventListener('click', function () {
        var i = pieceList.querySelectorAll('[data-mw-piece]').length;
        var wrap = document.createElement('div');
        wrap.innerHTML = pieceTpl.innerHTML.replace(/__P__/g, String(i)).trim();
        var piece = wrap.firstElementChild;
        pieceList.appendChild(piece);
        initPiece(piece);
        if (window.cpMoneyMask) window.cpMoneyMask.wire(piece.querySelectorAll('.cp-money-input'));
        schedulePreview();
      });
    }

    var extraList = document.getElementById('mw-extra-list');
    var extraTpl = document.getElementById('mw-extra-template');
    var extraAdd = document.getElementById('mw-extra-add');

    function setExtraSource(row, linked) {
      if (!row) return;
      row.classList.toggle('is-linked', !!linked);
      row.classList.toggle('is-manual', !linked);
      var badge = row.querySelector('[data-mw-extra-source]');
      if (!badge) return;
      badge.className = 'br-config-source ' + (linked ? 'br-config-source--linked' : 'br-config-source--manual');
      badge.innerHTML = linked
        ? '<i class="fa fa-link" aria-hidden="true"></i> Catalog'
        : '<i class="fa fa-pencil" aria-hidden="true"></i> Manual';
    }

    function initExtraRow(row) {
      if (!row) return;
      row.querySelectorAll('.mw-preview').forEach(bindPreview);
      if (window.cpMoneyMask) window.cpMoneyMask.wire(row.querySelectorAll('.cp-money-input'));
      var root = row.querySelector('[data-admin-search-select]');
      if (root && window.AdminSearchSelect) AdminSearchSelect.init(root);
      var remove = row.querySelector('.br-extra-remove');
      if (remove) {
        remove.addEventListener('click', function () {
          if (root && window.AdminSearchSelect) AdminSearchSelect.destroy(root);
          row.remove();
          schedulePreview();
        });
      }
    }

    if (extraAdd && extraTpl && extraList) {
      extraAdd.addEventListener('click', function () {
        var i = extraList.querySelectorAll('[data-mw-extra-row]').length;
        var wrap = document.createElement('div');
        wrap.innerHTML = extraTpl.innerHTML.replace(/__I__/g, String(i)).trim();
        var row = wrap.firstElementChild;
        extraList.appendChild(row);
        initExtraRow(row);
        schedulePreview();
      });
    }
    if (extraList) extraList.querySelectorAll('[data-mw-extra-row]').forEach(initExtraRow);

    document.addEventListener('admin-search-select:change', function (e) {
      var row = e.target && e.target.closest ? e.target.closest('[data-mw-extra-row]') : null;
      if (!row) return;
      var opt = (e.detail && e.detail.option) || null;
      var deliveryEl = row.querySelector('[data-mw-extra-delivery]');
      var nameEl = row.querySelector('[data-mw-extra-name]');
      var priceEl = row.querySelector('[data-mw-extra-price]');
      if (!opt) {
        if (deliveryEl) deliveryEl.value = '0.00';
        setExtraSource(row, false);
        schedulePreview();
        return;
      }
      if (nameEl) nameEl.value = opt.name || '';
      if (priceEl) priceEl.value = opt.price || '';
      if (deliveryEl) deliveryEl.value = opt.delivery || '0.00';
      if (window.cpMoneyMask && priceEl) window.cpMoneyMask.wire(priceEl);
      setExtraSource(row, true);
      schedulePreview();
    });

    document.addEventListener('debris-removal:change', function (e) {
      var d = e.detail || {};
      lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
      if (lastPreviewData) renderPreview(lastPreviewData);
    });
    document.addEventListener('additional-costs:change', function (e) {
      var d = e.detail || {};
      lastAdditionalMaterialsPreTax = Number(d.materialsPreTax || 0);
      lastAdditionalMaterialsHst = Number(d.materialsHst || 0);
      lastAdditionalLaborPreTax = Number(d.laborPreTax || 0);
      lastAdditionalLaborHst = Number(d.laborHst || 0);
      lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
      if (lastPreviewData) renderPreview(lastPreviewData);
    });
    document.addEventListener('commute-expense:change', function (e) {
      var d = e.detail || {};
      lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
      if (lastPreviewData) renderPreview(lastPreviewData);
    });

    var printBtn = document.getElementById('mw-print-cut-list');
    if (printBtn) {
      printBtn.addEventListener('click', function () { printCutList(); });
    }

    if (cfg.initialPreview) renderPreview(cfg.initialPreview);
    schedulePreview();
  }
})();

(function () {
  var root = document.getElementById('mw-config-form');
  if (!root) return;
  var tabs = root.querySelectorAll('.cp-tab');
  var panels = root.querySelectorAll('.cp-tab-panel');
  var tabInput = document.getElementById('mw-config-tab');

  function show(key) {
    if (!key || !root.querySelector('.cp-tab[data-tab="' + key + '"]')) return;
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === key;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) {
      p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
    });
    if (tabInput) tabInput.value = key;
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', key);
      if (window.history && window.history.replaceState) window.history.replaceState(null, '', url.toString());
    } catch (e) {}
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
  });

  document.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('[data-mw-catalog-row]') : null;
    if (!row) return;
    var opt = (e.detail && e.detail.option) || null;
    var nameEl = row.querySelector('[data-mw-cat-name]');
    var priceEl = row.querySelector('[data-mw-cat-price]');
    var covEl = row.querySelector('[data-mw-cat-coverage]');
    if (!opt) return;
    if (nameEl && !nameEl.value) nameEl.value = opt.name || '';
    if (priceEl) priceEl.value = opt.price || '';
    if (window.cpMoneyMask && priceEl) window.cpMoneyMask.wire(priceEl);
    if (covEl) {
      var text = (opt.name || '') + ' ' + (opt.store_name || '') + ' ' + (opt.description || '');
      var match = text.match(/(\d+(?:\.\d+)?)\s*(?:feet|foot|ft|lf)\b/i);
      if (match && match[1]) {
        covEl.value = match[1];
      } else if (!covEl.value || Number(covEl.value) <= 0) {
        covEl.value = '50';
      }
    }
    row.classList.add('is-linked');
    row.classList.remove('is-manual');
  });

  root.querySelectorAll('[data-mw-add-catalog]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var slot = btn.getAttribute('data-mw-add-catalog');
      var body = root.querySelector('[data-mw-catalog-body="' + slot + '"]');
      var tpl = root.querySelector('[data-mw-catalog-template="' + slot + '"]');
      if (!body || !tpl) return;
      var i = body.querySelectorAll('[data-mw-catalog-row]').length;
      var html = tpl.innerHTML.replace(/__R__/g, String(i));
      var wrap = document.createElement('tbody');
      wrap.innerHTML = html;
      var row = wrap.querySelector('[data-mw-catalog-row]');
      if (!row) return;
      body.appendChild(row);
      var ss = row.querySelector('[data-admin-search-select]');
      if (ss && window.AdminSearchSelect) AdminSearchSelect.init(ss);
      if (window.cpMoneyMask) window.cpMoneyMask.wire(row.querySelectorAll('.cp-money-input'));
    });
  });

  root.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-mw-remove-catalog]') : null;
    if (!btn) return;
    var row = btn.closest('[data-mw-catalog-row]');
    if (row) row.remove();
  });
})();

(function () {
  'use strict';

  function errorFromBody(body, status) {
    if (body && body.error) return body.error;
    if (body && body.errors) {
      var keys = Object.keys(body.errors);
      if (keys.length && body.errors[keys[0]] && body.errors[keys[0]][0]) {
        return body.errors[keys[0]][0];
      }
    }
    if (body && body.message) return body.message;
    if (status === 504 || status === 502) {
      return 'The server timed out. Try again with a shorter brief.';
    }
    return 'HTTP ' + status;
  }

  function formValue(form, name) {
    if (!form || !name) return '';
    var el = form.elements[name];
    if (!el) return '';
    if (el.type === 'radio') {
      var checked = form.querySelector('input[name="' + name.replace(/"/g, '\\"') + '"]:checked');
      return checked ? checked.value : '';
    }
    if (el.type === 'select-one') {
      var opt = el.options[el.selectedIndex];
      return opt ? (opt.text || opt.value || '') : '';
    }
    return el.value != null ? String(el.value).trim() : '';
  }

  function formSelectValue(form, name) {
    if (!form || !name) return '';
    var el = form.elements[name];
    if (!el || el.type !== 'select-one') return formValue(form, name);
    return el.value != null ? String(el.value).trim() : '';
  }

  function panelText(id) {
    var el = document.getElementById(id);
    if (!el) return '';
    return el.innerText.replace(/\s+/g, ' ').trim();
  }

  function collectPaintingContext(form) {
    var excluded = [];
    form.querySelectorAll('[data-excluded-row]').forEach(function (row) {
      var nameEl = row.querySelector('input[name*="[name]"]');
      var modeEl = row.querySelector('select[name*="[mode]"]');
      var lengthEl = row.querySelector('input[name*="[length_ft]"]');
      var widthEl = row.querySelector('input[name*="[width_ft]"]');
      var sqfEl = row.querySelector('input[name*="[sqf]"]');
      excluded.push({
        name: nameEl ? nameEl.value.trim() : '',
        mode: modeEl ? modeEl.value : 'dimensions',
        length_ft: lengthEl ? lengthEl.value : '',
        width_ft: widthEl ? widthEl.value : '',
        sqf: sqfEl ? sqfEl.value : '',
      });
    });

    var paintSelect = form.elements['paint_line_id'];
    var paintLineName = '';
    if (paintSelect && paintSelect.type === 'select-one' && paintSelect.selectedIndex >= 0) {
      paintLineName = paintSelect.options[paintSelect.selectedIndex].text || '';
    }

    return {
      client_name: formValue(form, 'client_name'),
      total_property_sqf: formValue(form, 'total_property_sqf'),
      paint_line_id: formSelectValue(form, 'paint_line_id'),
      paint_line_name: paintLineName,
      surface_prep_description: formValue(form, 'surface_prep_description'),
      excluded_areas: excluded,
      breakdown_summary: panelText('pt-live-root'),
    };
  }

  function collectFenceContext(form) {
    var sides = [];
    form.querySelectorAll('.fq-side-length').forEach(function (input) {
      sides.push({ length_ft: input.value });
    });

    var materials = [];
    document.querySelectorAll('#fq-materials tbody tr').forEach(function (tr) {
      var cells = tr.querySelectorAll('td');
      if (cells.length < 2) return;
      var label = cells[0].innerText.replace(/\s+/g, ' ').trim();
      var amount = cells[cells.length - 1].innerText.replace(/\s+/g, ' ').trim();
      if (label) materials.push(label + (amount ? ' — ' + amount : ''));
    });

    var totals = [
      panelText('fq-t-materials') ? 'Materials: ' + panelText('fq-t-materials') : '',
      panelText('fq-t-labour') ? 'Labour: ' + panelText('fq-t-labour') : '',
      panelText('fq-t-grand') ? 'Grand total: ' + panelText('fq-t-grand') : '',
    ].filter(Boolean).join('; ');

    return {
      client_name: formValue(form, 'client_name'),
      fence_height_ft: formSelectValue(form, 'fence_height_ft') || formValue(form, 'fence_height_ft'),
      board_style: formValue(form, 'board_style'),
      gates: formValue(form, 'gates'),
      sides: sides,
      geometry_summary: panelText('fq-geometry'),
      materials_summary: materials.join('\n'),
      totals_summary: totals,
    };
  }

  function collectPavingContext(form) {
    var stoneLines = [];
    form.querySelectorAll('.pq-stone-entry').forEach(function (row, idx) {
      var sel = row.querySelector('.pq-stone-entry-select');
      var stoneName = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].textContent.trim() : '';
      var modeRadio = row.querySelector('.pq-stone-mode-radio:checked');
      var mode = modeRadio ? modeRadio.value : 'sqft';
      var detail = '';
      if (mode === 'qty') {
        var qtyIn = row.querySelector('.pq-stone-qty-in');
        detail = qtyIn && qtyIn.value ? qtyIn.value + ' stones' : '';
      } else if (mode === 'dimensions') {
        var lenIn = row.querySelector('.pq-stone-len-in');
        var widIn = row.querySelector('.pq-stone-wid-in');
        detail = (lenIn && widIn && lenIn.value && widIn.value) ? lenIn.value + ' ft × ' + widIn.value + ' ft' : '';
      } else {
        var areaIn = row.querySelector('.pq-stone-area-in');
        detail = areaIn && areaIn.value ? areaIn.value + ' sq ft' : '';
      }
      if (stoneName || detail) {
        stoneLines.push((stoneName || 'Stone ' + (idx + 1)) + (detail ? ': ' + detail : ''));
      }
    });
    var totalAreaLine = panelText('pq-total-area-line');

    return {
      client_name: formValue(form, 'client_name'),
      total_area_sq_ft: totalAreaLine || stoneLines.join('; '),
      stone_types: stoneLines.join('; '),
      waste_factor: formValue(form, 'waste_factor') ? formValue(form, 'waste_factor') + '%' : '',
      excavation_depth_in: formValue(form, 'excavation_depth_in') ? formValue(form, 'excavation_depth_in') + ' in' : '',
      profit_margin: formValue(form, 'profit_margin') ? formValue(form, 'profit_margin') + '%' : '',
      mini_skid_days: formValue(form, 'mini_skid_days'),
      plate_compactor_days: formValue(form, 'plate_compactor_days'),
      helper_hourly_rate: formValue(form, 'helper_hourly_rate'),
      owner_hourly_rate: formValue(form, 'owner_hourly_rate'),
      breakdown_summary: panelText('pq-live-root'),
    };
  }

  function collectStandardContext(form) {
    var materials = [];
    form.querySelectorAll('.sq-material-row').forEach(function (row) {
      var shortName = row.querySelector('input[name*="[short_name]"]');
      var desc = row.querySelector('input[name*="[description]"], textarea[name*="[description]"]');
      var qty = row.querySelector('input[name*="[quantity]"]');
      var unit = row.querySelector('input[name*="[unit_price]"]');
      materials.push({
        short_name: shortName ? shortName.value.trim() : '',
        description: desc ? desc.value.trim() : '',
        quantity: qty ? qty.value.trim() : '',
        unit_price: unit ? unit.value.trim() : '',
      });
    });

    var docTypeEl = form.querySelector('input[name="document_type"]:checked');
    var pdfModeEl = form.querySelector('input[name="pdf_detail_mode"]:checked');

    var totals = [
      panelText('sq-mat-with-hst') ? 'Materials w/ HST: ' + panelText('sq-mat-with-hst') : '',
      panelText('sq-labour-with-hst') ? 'Labour w/ HST: ' + panelText('sq-labour-with-hst') : '',
      panelText('sq-g-grand') ? 'Grand total: ' + panelText('sq-g-grand') : '',
    ].filter(Boolean).join('; ');

    return {
      client_name: formValue(form, 'client_name'),
      display_title: formValue(form, 'display_title'),
      document_type: docTypeEl ? docTypeEl.value : '',
      pdf_detail_mode: pdfModeEl ? pdfModeEl.value : '',
      includes_material: form.elements['includes_material'] && form.elements['includes_material'].checked,
      includes_labour: form.elements['includes_labour'] && form.elements['includes_labour'].checked,
      contingency_enabled: form.elements['contingency_enabled'] && form.elements['contingency_enabled'].checked,
      contingency_percent: formValue(form, 'contingency_percent'),
      labour_use_fixed: form.elements['sq-labour-use-fixed'] && form.elements['sq-labour-use-fixed'].checked,
      labour_fixed_amount: formValue(form, 'labour_fixed_amount'),
      labour_hourly_rate: formValue(form, 'labour_hourly_rate'),
      labour_quantity: formValue(form, 'labour_quantity'),
      materials: materials,
      totals_summary: totals,
    };
  }

  function collectTileContext(form) {
    var includeMaterial = form.elements['include_tile_material'] && form.elements['include_tile_material'].checked;
    return {
      client_name: formValue(form, 'client_name'),
      length_ft: formValue(form, 'length_ft'),
      width_ft: formValue(form, 'width_ft'),
      waste_margin_percent: formValue(form, 'waste_margin_percent'),
      tile_product_id: formSelectValue(form, 'tile_product_id'),
      include_tile_material: includeMaterial,
      mortar_type_id: formSelectValue(form, 'mortar_type_id'),
      trowel_size: formSelectValue(form, 'trowel_size'),
      grout_type_id: formSelectValue(form, 'grout_type_id'),
      joint_width_decimal: formSelectValue(form, 'joint_width_decimal'),
      labor_rate: formValue(form, 'labor_rate'),
      breakdown_summary: panelText('tl-live-root')
    };
  }

  function collectStairsContext(form) {
    return {
      client_name: formValue(form, 'client_name'),
      total_steps: formValue(form, 'total_steps'),
      material_type: formSelectValue(form, 'material_type_id'),
      nosing_choice: formValue(form, 'nosing_choice'),
      carpet_removal: form.elements['carpet_removal'] && form.elements['carpet_removal'].checked,
      requires_stain: form.elements['requires_stain'] && form.elements['requires_stain'].checked,
      requires_clear_coat: form.elements['requires_clear_coat'] && form.elements['requires_clear_coat'].checked,
      breakdown_summary: panelText('st-live-root')
    };
  }

  function collectFlooringContext(form) {
    return {
      client_name: formValue(form, 'client_name'),
      square_footage: formValue(form, 'square_footage'),
      flooring_type: formSelectValue(form, 'flooring_type_id'),
      remove_carpet: form.elements['remove_carpet'] && form.elements['remove_carpet'].checked,
      rescrew_subfloor: form.elements['rescrew_subfloor'] && form.elements['rescrew_subfloor'].checked,
      install_shoe_moulding: form.elements['install_shoe_moulding'] && form.elements['install_shoe_moulding'].checked,
      caulk_paint_shoe_moulding: form.elements['caulk_paint_shoe_moulding'] && form.elements['caulk_paint_shoe_moulding'].checked,
      closet_doors: form.elements['closet_doors'] && form.elements['closet_doors'].checked,
      closet_door_qty: formValue(form, 'closet_door_qty'),
      material_name: formValue(form, 'material_name'),
      breakdown_summary: panelText('fl-live-root')
    };
  }

  function collectBasementContext(form) {
    return {
      client_name: formValue(form, 'client_name'),
      area_sqft: formValue(form, 'area_sqft'),
      ceiling_height_ft: formValue(form, 'ceiling_height_ft'),
      room_count: formValue(form, 'room_count'),
      flooring_type: formSelectValue(form, 'flooring_type'),
      ceiling_type: formSelectValue(form, 'ceiling_type'),
      include_bathroom: form.elements['include_bathroom'] && form.elements['include_bathroom'].checked,
      include_demolition: form.elements['include_demolition'] && form.elements['include_demolition'].checked,
      existing_notes: formValue(form, 'existing_notes'),
      breakdown_summary: panelText('br-live-root')
    };
  }

  var collectors = {
    painting: collectPaintingContext,
    fence: collectFenceContext,
    paving: collectPavingContext,
    standard: collectStandardContext,
    stairs: collectStairsContext,
    flooring: collectFlooringContext,
    tile: collectTileContext,
    basement: collectBasementContext,
  };

  function collectQuotationContext(formId, quotationType) {
    if (!formId || !quotationType || !collectors[quotationType]) return null;
    var form = document.getElementById(formId);
    if (!form) return null;
    return collectors[quotationType](form);
  }

  function contextHasData(context) {
    if (!context || typeof context !== 'object') return false;
    return Object.keys(context).some(function (key) {
      var val = context[key];
      if (val == null || val === '') return false;
      if (Array.isArray(val)) return val.some(function (row) {
        if (!row || typeof row !== 'object') return !!row;
        return Object.keys(row).some(function (k) {
          return String(row[k] || '').trim() !== '';
        });
      });
      if (typeof val === 'boolean') return val;
      return String(val).trim() !== '';
    });
  }

  function init(root) {
    if (!root || root.getAttribute('data-quotation-work-desc-ai-init') === '1') return;
    root.setAttribute('data-quotation-work-desc-ai-init', '1');

    var textareaId = root.getAttribute('data-textarea-id') || '';
    var improveUrl = root.getAttribute('data-improve-url') || '';
    var csrfToken = root.getAttribute('data-csrf') || '';
    var contextFormId = root.getAttribute('data-context-form') || '';
    var quotationType = root.getAttribute('data-quotation-type') || '';
    var historyKey = root.getAttribute('data-history-key') || ('quotation-work-desc:' + textareaId);
    var textarea = document.getElementById(textareaId);
    var btn = root.querySelector('.quotation-work-description-ai__btn');
    var loadingEl = root.querySelector('.quotation-work-description-ai__loading');
    var statusEl = root.querySelector('.quotation-work-description-ai__status');
    var historyMount = root.querySelector('[data-history-mount]');

    if (!btn || !textarea || !improveUrl) return;

    var history = null;
    if (historyMount && window.AdminAiTextHistory) {
      history = window.AdminAiTextHistory.create({
        storageKey: historyKey,
        mountEl: historyMount,
        title: 'Work description versions',
        getText: function () { return textarea.value; },
        setText: function (text) { textarea.value = text; },
      });
      if (textarea.value.trim() === '') {
        history.clear();
      }
    }

    function setStatus(message, isError) {
      if (!statusEl) return;
      statusEl.textContent = message || '';
      statusEl.classList.toggle('is-error', !!isError);
    }

    function setLoading(active) {
      if (loadingEl) loadingEl.hidden = !active;
      btn.disabled = !!active;
    }

    btn.addEventListener('click', async function () {
      var notes = textarea.value.trim();
      var context = collectQuotationContext(contextFormId, quotationType);
      var hasContext = contextHasData(context);

      if (!notes && !hasContext) {
        setStatus('Enter a brief description or fill in quotation details first, then click Improve description with AI.', true);
        textarea.focus();
        return;
      }

      if (history) {
        history.captureOriginalIfNeeded();
      }

      setLoading(true);
      setStatus('Rewriting your description with Gemini…', false);

      try {
        var payload = {
          contractor_notes: notes,
        };
        if (quotationType && hasContext) {
          payload.quotation_type = quotationType;
          payload.context = context;
        }

        var res = await fetch(improveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify(payload),
        });

        var text = await res.text();
        var body = null;
        if (text) {
          try {
            body = JSON.parse(text);
          } catch (ignore) {
            throw new Error(errorFromBody(null, res.status));
          }
        }

        if (!res.ok) {
          throw new Error(errorFromBody(body, res.status));
        }

        var description = (body && body.description) ? String(body.description).trim() : '';
        if (!description) {
          throw new Error('Gemini did not return a description.');
        }

        if (history) {
          history.addVersion(description);
        }

        textarea.value = description;
        textarea.focus();
        setStatus('Description updated. Use the history icon to restore earlier versions.', false);
      } catch (err) {
        setStatus(err.message || 'Could not improve description.', true);
      } finally {
        setLoading(false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.quotation-work-description-ai').forEach(init);
  });
})();

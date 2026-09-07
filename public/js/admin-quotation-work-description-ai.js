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

    var jobs = [];
    form.querySelectorAll('.sq-work-card').forEach(function (card) {
      var title = card.querySelector('.sq-work-title');
      var useFixed = card.querySelector('.sq-labour-use-fixed-cb');
      var fixedAmt = card.querySelector('.sq-labour-fixed-amount');
      var rate = card.querySelector('.sq-labour-rate');
      var qty = card.querySelector('.sq-labour-qty');
      jobs.push({
        title: title ? title.value.trim() : '',
        labour_use_fixed: !!(useFixed && useFixed.checked),
        labour_fixed_amount: fixedAmt ? fixedAmt.value.trim() : '',
        labour_hourly_rate: rate ? rate.value.trim() : '',
        labour_quantity: qty ? qty.value.trim() : '',
      });
    });

    var first = jobs[0] || {};
    var docTypeEl = form.querySelector('input[name="document_type"]:checked');
    var pdfModeEl = form.querySelector('input[name="pdf_detail_mode"]:checked');

    var totals = [
      panelText('sq-mat-with-hst') ? 'Materials w/ HST: ' + panelText('sq-mat-with-hst') : '',
      panelText('sq-g-labour-total') ? 'Labour w/ HST: ' + panelText('sq-g-labour-total') : '',
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
      labour_use_fixed: !!first.labour_use_fixed,
      labour_fixed_amount: first.labour_fixed_amount || '',
      labour_hourly_rate: first.labour_hourly_rate || '',
      labour_quantity: first.labour_quantity || '',
      materials: materials,
      jobs: jobs,
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
      scope_preset: formSelectValue(form, 'scope_preset') || formValue(form, 'scope_preset'),
      area_sqft: formValue(form, 'area_sqft'),
      area_entry_mode: formValue(form, 'area_entry_mode'),
      area_length_ft: formValue(form, 'area_length_ft'),
      area_width_ft: formValue(form, 'area_width_ft'),
      ceiling_height_ft: formValue(form, 'ceiling_height_ft'),
      room_count: formValue(form, 'room_count'),
      flooring_type: formSelectValue(form, 'flooring_type'),
      ceiling_type: formSelectValue(form, 'ceiling_type'),
      include_bathroom: form.elements['include_bathroom'] && form.elements['include_bathroom'].checked,
      include_demolition: isChecked(form, 'include_demolition'),
      demo_include_flooring: isChecked(form, 'demo_include_flooring'),
      demo_include_studs: isChecked(form, 'demo_include_studs'),
      demo_include_drywall: isChecked(form, 'demo_include_drywall'),
      demo_include_baseboard: isChecked(form, 'demo_include_baseboard'),
      existing_notes: formValue(form, 'existing_notes'),
      labor_summary: tableScopeLines('br-labor-rows'),
      materials_summary: tableScopeLines('br-mat-rows'),
      breakdown_summary: panelText('br-live-root')
    };
  }

  function collectShedContext(form) {
    return {
      client_name: formValue(form, 'client_name'),
      shed_type: choiceValue(form, 'shed_type'),
      attached: isChecked(form, 'attached'),
      length_ft: formValue(form, 'length_ft'),
      width_ft: formValue(form, 'width_ft'),
      wall_height_ft: formValue(form, 'wall_height_ft'),
      roof_pitch: formSelectValue(form, 'roof_pitch') || formValue(form, 'roof_pitch'),
      overhang_in: formValue(form, 'overhang_in'),
      area_label: panelText('sh-area-readout'),
      foundation: choiceValue(form, 'foundation'),
      joist_size: choiceValue(form, 'joist_size'),
      joist_spacing_in: formSelectValue(form, 'joist_spacing_in') || formValue(form, 'joist_spacing_in'),
      floor_sheathing: choiceValue(form, 'floor_sheathing'),
      stud_size: choiceValue(form, 'stud_size'),
      stud_spacing_in: formSelectValue(form, 'stud_spacing_in') || formValue(form, 'stud_spacing_in'),
      wall_sheathing: isChecked(form, 'wall_sheathing'),
      roof_type: choiceValue(form, 'roof_type'),
      siding: choiceValue(form, 'siding'),
      include_paint: isChecked(form, 'include_paint'),
      door_type: formSelectValue(form, 'door_type') || formValue(form, 'door_type'),
      door_qty: formValue(form, 'door_qty'),
      double_door: isChecked(form, 'double_door'),
      window_type: formSelectValue(form, 'window_type') || formValue(form, 'window_type'),
      window_qty: formValue(form, 'window_qty'),
      interior_finish: choiceValue(form, 'interior_finish'),
      include_electrical: isChecked(form, 'include_electrical'),
      outlet_qty: formValue(form, 'outlet_qty'),
      light_qty: formValue(form, 'light_qty'),
      include_subpanel: isChecked(form, 'include_subpanel'),
      include_loft: isChecked(form, 'include_loft'),
      include_ramp: isChecked(form, 'include_ramp'),
      include_gutters: isChecked(form, 'include_gutters'),
      include_plumbing: isChecked(form, 'include_plumbing'),
      client_provides_all: isChecked(form, 'client_provides_all'),
      labor_summary: tableScopeLines('sh-labor-rows'),
      materials_summary: tableScopeLines('sh-mat-rows'),
      breakdown_summary: panelText('sh-live-root')
    };
  }

  function collectTrimmingContext(form) {
    var families = [];
    form.querySelectorAll('input[name="enabled_families[]"]:checked').forEach(function (el) {
      families.push(el.value);
    });
    return {
      client_name: formValue(form, 'client_name'),
      enabled_families: families,
      door_qty: formValue(form, 'door_qty'),
      window_qty: formValue(form, 'window_qty'),
      floor_lf: formValue(form, 'floor_lf'),
      crown_lf: formValue(form, 'crown_lf'),
      cove_lf: formValue(form, 'cove_lf'),
      chair_rail_lf: formValue(form, 'chair_rail_lf'),
      wainscot_wall_lf: formValue(form, 'wainscot_wall_lf'),
      breakdown_summary: panelText('tm-live-root')
    };
  }

  function collectLowDeckContext(form) {
    return {
      client_name: formValue(form, 'client_name'),
      width_ft: formValue(form, 'width_ft'),
      length_ft: formValue(form, 'length_ft'),
      joist_spacing_in: formValue(form, 'joist_spacing_in'),
      geometry_summary: panelText('ld-geometry') || panelText('ld-area-value'),
      materials_summary: panelText('ld-materials'),
      totals_summary: panelText('ld-live-root') || panelText('ld-totals'),
      breakdown_summary: panelText('ld-live-root')
    };
  }

  function isChecked(form, name) {
    var nodes = form.querySelectorAll('input[name="' + name.replace(/"/g, '\\"') + '"]');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].type === 'checkbox' && nodes[i].checked && nodes[i].value !== '0') return true;
    }
    return false;
  }

  function choiceValue(form, name) {
    var input = form.querySelector('input[name="' + name.replace(/"/g, '\\"') + '"]:checked');
    return input ? String(input.value || '').trim() : formValue(form, name);
  }

  function tableScopeLines(tbodyId) {
    var lines = [];
    document.querySelectorAll('#' + tbodyId + ' tr').forEach(function (tr) {
      if (tr.classList.contains('br-empty-row')) return;
      var first = tr.querySelector('td');
      if (!first) return;
      var text = first.innerText.replace(/\s+/g, ' ').trim();
      if (text) lines.push(text);
    });
    return lines.join('\n');
  }

  function shouldSkipFormKey(key) {
    if (!key) return true;
    if (/^(?:_token|_method|save_intent|work_description|owner_hourly_rate|helper_hourly_rate|link_client_project_id|tm_client_pick|include_commute|include_debris_removal)$/.test(key)) {
      return true;
    }
    if (/override|unit_price|hourly_rate/.test(key)) return true;
    if (/^(?:payment_|commute_|fp_|debris_|additional_cost)/.test(key)) return true;
    return false;
  }

  function assignFormPath(obj, rawName, value) {
    var name = String(rawName);
    var isArray = /\[\]$/.test(name);
    name = name.replace(/\[\]$/, '');
    var parts = name.split(/\[|\]/).filter(Boolean);
    if (!parts.length) return;
    var cur = obj;
    for (var i = 0; i < parts.length - 1; i++) {
      var part = parts[i];
      if (!cur[part] || typeof cur[part] !== 'object') cur[part] = {};
      cur = cur[part];
    }
    var last = parts[parts.length - 1];
    if (isArray) {
      if (!Array.isArray(cur[last])) cur[last] = [];
      cur[last].push(value);
    } else {
      cur[last] = value;
    }
  }

  function collectFormSnapshot(form) {
    var obj = {};
    var fd = new FormData(form);
    fd.delete('_method');
    fd.forEach(function (value, key) {
      if (shouldSkipFormKey(key)) return;
      var text = value == null ? '' : String(value).trim();
      if (text === '') return;
      assignFormPath(obj, key, text);
    });
    return obj;
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
    shed: collectShedContext,
    trimming: collectTrimmingContext,
    low_deck: collectLowDeckContext,
  };

  function collectQuotationContext(formId, quotationType) {
    if (!formId) return null;
    var form = document.getElementById(formId);
    if (!form) return null;
    var snapshot = collectFormSnapshot(form);
    var specialized = (quotationType && collectors[quotationType]) ? collectors[quotationType](form) : {};
    return Object.assign({}, snapshot, specialized);
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
    var fromQuoteBtn = root.querySelector('.quotation-work-description-ai__from-quote');
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

    function setLoading(active, label) {
      if (loadingEl) {
        loadingEl.hidden = !active;
        var span = loadingEl.querySelector('span');
        if (span && label) span.textContent = label;
      }
      btn.disabled = !!active;
      if (fromQuoteBtn) fromQuoteBtn.disabled = !!active;
    }

    async function requestDescription(payload, statusMessage) {
      if (history) {
        history.captureOriginalIfNeeded();
      }

      setLoading(true, 'Writing with Gemini…');
      setStatus(statusMessage, false);

      try {
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
        setStatus(err.message || 'Could not generate description.', true);
      } finally {
        setLoading(false);
      }
    }

    btn.addEventListener('click', function () {
      var notes = textarea.value.trim();
      var context = collectQuotationContext(contextFormId, quotationType);
      var hasContext = contextHasData(context);

      if (!notes && !hasContext) {
        setStatus('Enter a brief description or fill in quotation details first, then click Improve description with AI.', true);
        textarea.focus();
        return;
      }

      var payload = {
        mode: 'improve',
        contractor_notes: notes,
      };
      if (quotationType && hasContext) {
        payload.quotation_type = quotationType;
        payload.context = context;
      }

      requestDescription(payload, 'Rewriting your description with Gemini…');
    });

    if (fromQuoteBtn) {
      fromQuoteBtn.addEventListener('click', function () {
        var context = collectQuotationContext(contextFormId, quotationType);
        if (!quotationType || !contextHasData(context)) {
          setStatus('Fill in quotation details first, then click Create automatically from quotation.', true);
          return;
        }

        requestDescription({
          mode: 'from_quotation',
          quotation_type: quotationType,
          context: context,
        }, 'Writing a description from this quotation…');
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.quotation-work-description-ai').forEach(init);
  });
})();

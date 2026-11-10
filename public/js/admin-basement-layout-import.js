/**
 * Room Area → basement quotation field mapping and re-import overwrite checks.
 *
 * Null proposed values mean the drawing did not measure that takeoff — leave
 * the form field alone. Explicit 0 is a measured zero.
 */
(function (root) {
  'use strict';

  var FIELD_IDS = {
    area_sqft: 'br-area',
    ceiling_height_ft: 'br-height',
    room_count: 'br-rooms',
    perimeter_lf: 'br-perimeter-lf',
    partition_lf: 'br-partition',
    bulkhead_lf: 'br-bulkhead',
    header_lf: 'br-headers',
    framing_door_openings: 'br-frame-doors',
    insulation_wall_sf: 'br-ins-wall-sf',
    insulation_ceiling_sf: 'br-ins-ceil-sf',
    ceiling_sf: 'br-ceil-sf',
    drywall_wall_sf: 'br-dw-wall',
    drywall_ceiling_sf: 'br-dw-ceil',
    drywall_door_openings: 'br-dw-door-open',
    drywall_window_openings: 'br-dw-window-open',
    drywall_openings: 'br-dw-open',
    drywall_opening_sf: 'br-dw-opening-sf',
    drywall_corners: 'br-dw-corners',
    drywall_inside_corners: 'br-dw-inside-corners',
    flooring_sf: 'br-fl-sf',
    door_qty: 'br-doors',
    baseboard_lf: 'br-base',
    casing_openings: 'br-casing',
    window_trim_qty: 'br-windows',
    paint_wall_sf: 'br-p-wall',
    paint_ceiling_sf: 'br-p-ceil',
    paint_trim_lf: 'br-p-trim'
  };

  var LW_KEYS = {
    area_sqft: true,
    insulation_wall_sf: true,
    insulation_ceiling_sf: true,
    ceiling_sf: true,
    drywall_wall_sf: true,
    drywall_ceiling_sf: true,
    flooring_sf: true,
    paint_wall_sf: true,
    paint_ceiling_sf: true
  };

  var SKIP_FORM_KEYS = {
    drywall_ceiling_sf: true
  };

  var NEVER_OVERWRITE = [
    'owner_hourly_rate',
    'helper_hourly_rate',
    'include_helper',
    'material_waste_percent',
    'flooring_type',
    'ceiling_type',
    'finish_level',
    'drywall_sheet_size',
    'paint_colors',
    'paint_coats',
    'stud_spacing_in',
    'underlayment',
    'subfloor_prep',
    'include_shoe',
    'scope_preset',
    'enabled_sections',
    'electrical_lines',
    'plumbing_lines',
    'labor_override',
    'materials_override',
    'insulate_walls',
    'insulate_ceiling',
    'underslab'
  ];

  function formatQty(n) {
    var rounded = Math.round(Number(n) * 100) / 100;
    if (!isFinite(rounded)) return '0';
    return String(rounded);
  }

  function num(v) {
    var n = Number(v);
    return isFinite(n) ? n : 0;
  }

  function present(opt, key) {
    if (!opt || !Object.prototype.hasOwnProperty.call(opt, key)) return false;
    var v = opt[key];
    return v !== null && v !== undefined && v !== '';
  }

  function ceilingTakeoffAvailable(opt) {
    opt = opt || {};
    if (opt.geometry_unmeasurable) return false;
    if (Object.prototype.hasOwnProperty.call(opt, 'ceiling_area_available')
      && opt.ceiling_area_available !== null
      && opt.ceiling_area_available !== undefined
      && opt.ceiling_area_available !== '') {
      return !!opt.ceiling_area_available;
    }
    if (!present(opt, 'ceiling_area_sqft')) return false;
    var ceiling = num(opt.ceiling_area_sqft);
    var area = present(opt, 'area_sqft') ? num(opt.area_sqft) : null;
    var excluded = present(opt, 'excluded_area_sqft') ? num(opt.excluded_area_sqft) : 0;
    var polygons = parseInt(opt.polygon_count, 10) || 0;
    if (ceiling <= 0 && (area === null || area <= 0) && excluded <= 0 && polygons <= 0) return false;
    if (ceiling <= 0 && area !== null && area > 0 && excluded <= 0) return false;
    return true;
  }

  function resolveImportedCeilingArea(opt, area) {
    if (ceilingTakeoffAvailable(opt) && present(opt, 'ceiling_area_sqft')) {
      return num(opt.ceiling_area_sqft);
    }
    if (area !== null && area > 0) return area;
    return null;
  }

  function resolveImportedPaintWallSf(opt, drywallWallSf) {
    if (present(opt, 'finish_paint_sqft')) {
      return num(opt.finish_paint_sqft);
    }
    if (drywallWallSf !== null) {
      return drywallWallSf;
    }
    return null;
  }

  function resolveImportedPaintCeilingSf(ceilingArea, ceilingType) {
    var type = String(ceilingType || '').toLowerCase();
    if (type === 'drop' || type === 'none') {
      return 0;
    }
    return ceilingArea;
  }

  function drywallOpeningSfFromLayout(doors, windows, openings) {
    var d = Math.max(0, parseInt(doors, 10) || 0);
    var w = Math.max(0, parseInt(windows, 10) || 0);
    var listed = Math.max(0, parseInt(openings, 10) || 0);
    var extra = Math.max(0, listed - d - w);
    return Math.round((d * 21 + w * 12 + extra * 21) * 100) / 100;
  }

  function readFieldNumber(el) {
    if (!el) return 0;
    var n = parseFloat(el.value);
    return isFinite(n) ? n : 0;
  }

  function proposedQuantities(opt, ceilingHeightFt, ceilingType) {
    opt = opt || {};
    var height = 8;
    if (present(opt, 'ceiling_height_ft') && num(opt.ceiling_height_ft) > 0) {
      height = num(opt.ceiling_height_ft);
    } else if (num(ceilingHeightFt) > 0) {
      height = num(ceilingHeightFt);
    }

    var peri = present(opt, 'perimeter_lf') ? num(opt.perimeter_lf) : null;
    var area = present(opt, 'area_sqft') ? num(opt.area_sqft) : null;
    var ceilingArea = resolveImportedCeilingArea(opt, area);

    var netWall = null;
    if (present(opt, 'finish_drywall_sqft')) netWall = num(opt.finish_drywall_sqft);
    else if (present(opt, 'net_wall_area_sqft')) netWall = num(opt.net_wall_area_sqft);
    else if (present(opt, 'wall_area_sqft')) netWall = num(opt.wall_area_sqft);

    var bulkheadDrywall = present(opt, 'bulkhead_drywall_sqft')
      ? Math.max(0, num(opt.bulkhead_drywall_sqft))
      : 0;
    var measuredNet = netWall !== null;
    if (netWall === null && peri !== null && peri > 0) {
      netWall = peri * height;
    }
    var wallSf = netWall !== null ? netWall + bulkheadDrywall : null;

    var insWall = null;
    if (present(opt, 'finish_insulation_sqft')) insWall = num(opt.finish_insulation_sqft);
    else if (present(opt, 'net_wall_area_sqft')) insWall = num(opt.net_wall_area_sqft);
    else if (present(opt, 'wall_area_sqft')) insWall = num(opt.wall_area_sqft);

    var partition = null;
    if (present(opt, 'finish_stud_lf')) partition = num(opt.finish_stud_lf);
    else if (present(opt, 'partition_lf')) partition = num(opt.partition_lf);

    var baseboard = null;
    if (present(opt, 'finish_baseboard_lf')) baseboard = num(opt.finish_baseboard_lf);
    else if (present(opt, 'baseboard_lf')) baseboard = num(opt.baseboard_lf);

    var bulkhead = null;
    if (present(opt, 'bulkhead_lf')) bulkhead = num(opt.bulkhead_lf);
    else if (present(opt, 'bulkhead_framing_lf')) bulkhead = num(opt.bulkhead_framing_lf);

    var doors = present(opt, 'door_count') ? Math.max(0, parseInt(opt.door_count, 10) || 0) : null;
    var windows = present(opt, 'window_count') ? Math.max(0, parseInt(opt.window_count, 10) || 0) : null;
    var openings = present(opt, 'opening_count') ? Math.max(0, parseInt(opt.opening_count, 10) || 0) : null;

    var casingOpenings = null;
    if (doors !== null || openings !== null) {
      casingOpenings = (doors || 0) + (openings || 0);
    }

    var casingLf = null;
    if (present(opt, 'finish_casing_lf')) {
      casingLf = num(opt.finish_casing_lf);
    } else if (present(opt, 'casing_lf')) {
      casingLf = num(opt.casing_lf);
    }
    var paintTrim = null;
    if (baseboard !== null || casingLf !== null) {
      paintTrim = Math.max(0, (baseboard || 0) + (casingLf || 0));
    }

    var paintWallBasis = wallSf;
    if (wallSf !== null && !present(opt, 'finish_paint_sqft') && !measuredNet) {
      paintWallBasis = Math.max(0, wallSf - drywallOpeningSfFromLayout(doors, windows, openings));
    }

    return {
      area_sqft: area,
      ceiling_height_ft: present(opt, 'ceiling_height_ft') ? height : null,
      room_count: present(opt, 'polygon_count') ? Math.max(0, parseInt(opt.polygon_count, 10) || 0) : null,
      perimeter_lf: peri,
      partition_lf: partition,
      bulkhead_lf: bulkhead,
      framing_door_openings: doors,
      insulation_wall_sf: insWall,
      insulation_ceiling_sf: ceilingArea,
      ceiling_sf: ceilingArea,
      drywall_wall_sf: wallSf,
      drywall_ceiling_sf: ceilingArea,
      drywall_door_openings: doors,
      drywall_window_openings: windows,
      drywall_openings: openings,
      drywall_opening_sf: measuredNet ? 0 : null,
      drywall_corners: present(opt, 'outside_corners') ? Math.max(0, parseInt(opt.outside_corners, 10) || 0) : null,
      drywall_inside_corners: present(opt, 'inside_corners') ? Math.max(0, parseInt(opt.inside_corners, 10) || 0) : null,
      flooring_sf: area,
      door_qty: doors,
      baseboard_lf: baseboard,
      casing_openings: casingOpenings,
      window_trim_qty: windows,
      paint_wall_sf: resolveImportedPaintWallSf(opt, paintWallBasis),
      paint_ceiling_sf: resolveImportedPaintCeilingSf(ceilingArea, ceilingType),
      paint_trim_lf: paintTrim
    };
  }

  function emptyCurrent() {
    var out = { import_label: '' };
    Object.keys(FIELD_IDS).forEach(function (key) {
      out[key] = 0;
    });
    return out;
  }

  function currentQuantities(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc || typeof doc.getElementById !== 'function') {
      return emptyCurrent();
    }
    var labelEl = doc.getElementById('br-layout-import-label');
    var out = {
      import_label: labelEl ? String(labelEl.value || '') : ''
    };
    Object.keys(FIELD_IDS).forEach(function (key) {
      out[key] = readFieldNumber(doc.getElementById(FIELD_IDS[key]));
    });
    return out;
  }

  function valuesDiverge(currentVal, proposedVal) {
    if (proposedVal == null) return false;
    var a = Number(currentVal);
    var b = Number(proposedVal);
    if (isFinite(a) && isFinite(b)) {
      return Math.abs(a - b) > 0.009;
    }
    return String(currentVal) !== String(proposedVal);
  }

  function formHasQuantities(current) {
    current = current || {};
    var keys = Object.keys(FIELD_IDS);
    for (var i = 0; i < keys.length; i++) {
      if (Math.abs(num(current[keys[i]])) > 0.009) return true;
    }
    return String(current.import_label || '').trim() !== '';
  }

  function wouldOverwrite(current, proposed) {
    if (!formHasQuantities(current)) return false;
    proposed = proposed || {};
    var keys = Object.keys(proposed);
    for (var i = 0; i < keys.length; i++) {
      var k = keys[i];
      if (SKIP_FORM_KEYS[k]) continue;
      if (proposed[k] == null) continue;
      if (valuesDiverge(current[k], proposed[k])) return true;
    }
    return false;
  }

  function paintAssignmentMixed(opt) {
    opt = opt || {};
    var unassigned = (parseInt(opt.paint_walls_unassigned, 10) || 0)
      + (parseInt(opt.paint_bulkheads_unassigned, 10) || 0);
    var assigned = (parseInt(opt.paint_walls_marked, 10) || 0)
      + (parseInt(opt.paint_walls_explicit_off, 10) || 0)
      + (parseInt(opt.paint_bulkheads_marked, 10) || 0)
      + (parseInt(opt.paint_bulkheads_explicit_off, 10) || 0);
    return unassigned > 0 && assigned > 0;
  }

  function needsImportConfirm(current, proposed, opt) {
    if (wouldOverwrite(current, proposed)) return true;
    if (!paintAssignmentMixed(opt)) return false;
    proposed = proposed || {};
    if (proposed.paint_wall_sf == null) return false;
    return valuesDiverge(current && current.paint_wall_sf, proposed.paint_wall_sf);
  }

  function confirmImportMessage(current, proposed, opt) {
    proposed = proposed || {};
    opt = opt || {};
    current = current || {};
    var parts = [api.CONFIRM_MESSAGE];
    var marked = parseInt(opt.paint_walls_marked, 10) || 0;
    var unassigned = parseInt(opt.paint_walls_unassigned, 10) || 0;
    var off = parseInt(opt.paint_walls_explicit_off, 10) || 0;
    var bhMarked = parseInt(opt.paint_bulkheads_marked, 10) || 0;
    var bhUnassigned = parseInt(opt.paint_bulkheads_unassigned, 10) || 0;
    var imported = proposed.paint_wall_sf;
    var previous = current.paint_wall_sf;
    if (marked > 0 || unassigned > 0 || off > 0 || imported != null || bhMarked > 0 || bhUnassigned > 0) {
      parts.push('Paint: ' + marked + ' wall(s) marked Paint, ' + unassigned + ' wall(s) with no Paint choice'
        + (off > 0 ? ', ' + off + ' wall(s) explicitly off' : '') + '.');
      if (bhMarked > 0 || bhUnassigned > 0) {
        parts.push('Bulkheads: ' + bhMarked + ' marked Paint, ' + bhUnassigned + ' with no Paint choice.');
      }
      if (imported != null) {
        parts.push('Imported wall paint: ' + formatQty(imported) + ' sq ft.');
      }
      if (previous != null && imported != null && valuesDiverge(previous, imported)) {
        parts.push('This replaces the current wall paint of ' + formatQty(previous) + ' sq ft.');
      }
      if (unassigned > 0 && imported != null) {
        parts.push('Walls without a Paint choice are not counted as painted. Confirm to import this quantity.');
      }
    }
    return parts.join(' ');
  }

  function importStatusText(proposed, opt, extras) {
    proposed = proposed || {};
    extras = extras || {};
    var name = (opt && (opt.short_name || opt.name)) || 'layout';
    var floor = formatQty(proposed.area_sqft != null ? proposed.area_sqft : 0);
    var parts = ['Imported ' + name + ': ' + floor + ' sq ft floor'];
    if (proposed.ceiling_sf != null) {
      parts.push(formatQty(proposed.ceiling_sf) + ' sq ft ceiling');
    }
    var periLabel = formatQty(proposed.perimeter_lf != null ? proposed.perimeter_lf : 0);
    var height = formatQty(extras.heightFt != null ? extras.heightFt : 8);
    var walls = formatQty(proposed.drywall_wall_sf != null ? proposed.drywall_wall_sf : 0);
    var openingTotal = (proposed.drywall_door_openings || 0)
      + (proposed.drywall_window_openings || 0)
      + (proposed.drywall_openings || 0);
    var openLabel = openingTotal === 1 ? '1 opening' : openingTotal + ' openings';
    parts.push(periLabel + ' lf walls × ' + height + ' ft = ' + walls + ' sq ft wall drywall'
      + (extras.measuredNet ? ' (openings already deducted)' : ''));
    parts.push(openLabel + '.');
    return parts.join(', ');
  }

  var api = {
    formatQty: formatQty,
    present: present,
    ceilingTakeoffAvailable: ceilingTakeoffAvailable,
    resolveImportedCeilingArea: resolveImportedCeilingArea,
    resolveImportedPaintCeilingSf: resolveImportedPaintCeilingSf,
    proposedQuantities: proposedQuantities,
    currentQuantities: currentQuantities,
    formHasQuantities: formHasQuantities,
    wouldOverwrite: wouldOverwrite,
    paintAssignmentMixed: paintAssignmentMixed,
    needsImportConfirm: needsImportConfirm,
    confirmImportMessage: confirmImportMessage,
    importStatusText: importStatusText,
    FIELD_IDS: FIELD_IDS,
    LW_KEYS: LW_KEYS,
    NEVER_OVERWRITE: NEVER_OVERWRITE,
    SKIP_FORM_KEYS: SKIP_FORM_KEYS,
    CONFIRM_MESSAGE: 'This layout’s quantities differ from the values currently in the form. Replace the form values with the imported layout?'
  };

  root.BasementLayoutImport = api;
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);

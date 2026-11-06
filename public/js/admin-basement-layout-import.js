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

  function readFieldNumber(el) {
    if (!el) return 0;
    var n = parseFloat(el.value);
    return isFinite(n) ? n : 0;
  }

  function proposedQuantities(opt, ceilingHeightFt) {
    opt = opt || {};
    var height = 8;
    if (present(opt, 'ceiling_height_ft') && num(opt.ceiling_height_ft) > 0) {
      height = num(opt.ceiling_height_ft);
    } else if (num(ceilingHeightFt) > 0) {
      height = num(ceilingHeightFt);
    }

    var peri = present(opt, 'perimeter_lf') ? num(opt.perimeter_lf) : null;
    var area = present(opt, 'area_sqft') ? num(opt.area_sqft) : null;
    var ceilingArea = null;
    if (present(opt, 'ceiling_area_sqft') && num(opt.ceiling_area_sqft) > 0) {
      ceilingArea = num(opt.ceiling_area_sqft);
    } else if (area !== null && area > 0) {
      ceilingArea = area;
    }

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

    var paintTrim = null;
    if (present(opt, 'casing_lf') || present(opt, 'finish_casing_lf')) {
      var casingLf = present(opt, 'finish_casing_lf') ? num(opt.finish_casing_lf) : num(opt.casing_lf);
      paintTrim = Math.max(0, (baseboard || 0) + casingLf);
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
      paint_wall_sf: wallSf,
      paint_ceiling_sf: ceilingArea,
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
      if (proposed[k] == null) continue;
      if (valuesDiverge(current[k], proposed[k])) return true;
    }
    return false;
  }

  var api = {
    formatQty: formatQty,
    present: present,
    proposedQuantities: proposedQuantities,
    currentQuantities: currentQuantities,
    formHasQuantities: formHasQuantities,
    wouldOverwrite: wouldOverwrite,
    FIELD_IDS: FIELD_IDS,
    LW_KEYS: LW_KEYS,
    NEVER_OVERWRITE: NEVER_OVERWRITE,
    CONFIRM_MESSAGE: 'This layout’s quantities differ from the values currently in the form. Replace the form values with the imported layout?'
  };

  root.BasementLayoutImport = api;
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);

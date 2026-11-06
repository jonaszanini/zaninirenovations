/**
 * Room Area → basement quotation field mapping and re-import overwrite checks.
 */
(function (root) {
  'use strict';

  function formatQty(n) {
    var rounded = Math.round(Number(n) * 100) / 100;
    if (!isFinite(rounded)) return '0';
    return String(rounded);
  }

  function num(v) {
    var n = Number(v);
    return isFinite(n) ? n : 0;
  }

  function readFieldNumber(el) {
    if (!el) return 0;
    var n = parseFloat(el.value);
    return isFinite(n) ? n : 0;
  }

  function proposedQuantities(opt, ceilingHeightFt) {
    opt = opt || {};
    var peri = num(opt.perimeter_lf);
    var area = num(opt.area_sqft);
    var measuredWall = num(opt.wall_area_sqft);
    var height = num(ceilingHeightFt) > 0 ? num(ceilingHeightFt) : 8;
    var wallSf = measuredWall > 0 ? measuredWall : (peri > 0 ? peri * height : 0);
    var bulkhead = opt.bulkhead_lf != null && opt.bulkhead_lf !== ''
      ? num(opt.bulkhead_lf)
      : num(opt.bulkhead_framing_lf);
    var partition = opt.partition_lf != null && opt.partition_lf !== ''
      ? num(opt.partition_lf)
      : 0;
    var base = (opt.baseboard_lf == null || opt.baseboard_lf === '')
      ? null
      : num(opt.baseboard_lf);

    return {
      area_sqft: area,
      perimeter_lf: peri,
      wall_area_sqft: wallSf,
      ceiling_sf: area,
      inside_corners: Math.max(0, parseInt(opt.inside_corners, 10) || 0),
      outside_corners: Math.max(0, parseInt(opt.outside_corners, 10) || 0),
      door_count: Math.max(0, parseInt(opt.door_count, 10) || 0),
      window_count: Math.max(0, parseInt(opt.window_count, 10) || 0),
      opening_count: Math.max(0, parseInt(opt.opening_count, 10) || 0),
      bulkhead_lf: bulkhead,
      partition_lf: partition,
      baseboard_lf: base
    };
  }

  function currentQuantities(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc || typeof doc.getElementById !== 'function') {
      return {
        area_sqft: 0,
        perimeter_lf: 0,
        wall_area_sqft: 0,
        ceiling_sf: 0,
        inside_corners: 0,
        outside_corners: 0,
        door_count: 0,
        window_count: 0,
        opening_count: 0,
        bulkhead_lf: 0,
        partition_lf: 0,
        baseboard_lf: 0,
        import_label: ''
      };
    }
    var labelEl = doc.getElementById('br-layout-import-label');
    return {
      area_sqft: readFieldNumber(doc.getElementById('br-area')),
      perimeter_lf: readFieldNumber(doc.getElementById('br-perimeter-lf')),
      wall_area_sqft: readFieldNumber(doc.getElementById('br-dw-wall')),
      ceiling_sf: readFieldNumber(doc.getElementById('br-dw-ceil')),
      inside_corners: readFieldNumber(doc.getElementById('br-dw-inside-corners')),
      outside_corners: readFieldNumber(doc.getElementById('br-dw-corners')),
      door_count: readFieldNumber(doc.getElementById('br-dw-door-open')),
      window_count: readFieldNumber(doc.getElementById('br-dw-window-open')),
      opening_count: readFieldNumber(doc.getElementById('br-dw-open')),
      bulkhead_lf: readFieldNumber(doc.getElementById('br-bulkhead')),
      partition_lf: readFieldNumber(doc.getElementById('br-partition')),
      baseboard_lf: readFieldNumber(doc.getElementById('br-base')),
      import_label: labelEl ? String(labelEl.value || '') : ''
    };
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
    var keys = [
      'area_sqft', 'perimeter_lf', 'wall_area_sqft', 'ceiling_sf',
      'inside_corners', 'outside_corners', 'door_count', 'window_count',
      'opening_count', 'bulkhead_lf', 'partition_lf', 'baseboard_lf'
    ];
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
    proposedQuantities: proposedQuantities,
    currentQuantities: currentQuantities,
    formHasQuantities: formHasQuantities,
    wouldOverwrite: wouldOverwrite,
    CONFIRM_MESSAGE: 'This layout’s quantities differ from the values currently in the form. Replace the form values with the imported layout?'
  };

  root.BasementLayoutImport = api;
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);

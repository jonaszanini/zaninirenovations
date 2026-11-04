/**
 * Zanini overlays on openPlan3D plans. Mirrors app/Services/RoomAreaOpenPlan3DExtensions.php.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.OpenPlan3DZaniniExtensions = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var CM_PER_FOOT = 30.48;
  var DEFAULT_WASTE_PERCENT = 10.0;
  var DEFAULT_BOX_SQFT = 20.0;

  function roundTo(value, digits) {
    var places = Math.max(0, digits);
    var factor = Math.pow(10, places);
    var shifted = value * factor;
    var sign = shifted < 0 ? -1 : 1;
    return sign * Math.round(Math.abs(shifted) + 1e-9) / factor;
  }

  function normalizeLegacyRect(rect) {
    if (!rect || typeof rect !== 'object') return null;
    var x0, y0, x1, y1;
    if (rect.x1 != null && rect.y1 != null && rect.x2 != null && rect.y2 != null) {
      x0 = Number(rect.x1);
      y0 = Number(rect.y1);
      x1 = Number(rect.x2);
      y1 = Number(rect.y2);
    } else if (rect.x0 != null && rect.y0 != null && rect.x1 != null && rect.y1 != null) {
      x0 = Number(rect.x0);
      y0 = Number(rect.y0);
      x1 = Number(rect.x1);
      y1 = Number(rect.y1);
    } else if (rect.x != null && rect.y != null && rect.width != null && rect.height != null) {
      x0 = Number(rect.x);
      y0 = Number(rect.y);
      x1 = x0 + Number(rect.width);
      y1 = y0 + Number(rect.height);
    } else {
      return null;
    }
    if (!isFinite(x0 + y0 + x1 + y1) || x0 === x1 || y0 === y1) return null;
    return {
      x0: Math.min(x0, x1),
      y0: Math.min(y0, y1),
      x1: Math.max(x0, x1),
      y1: Math.max(y0, y1)
    };
  }

  function rectangleUnionAreaSqFt(rects, cmPerUnit) {
    if (!rects || rects.length === 0) return 0.0;
    cmPerUnit = cmPerUnit > 0 ? cmPerUnit : 1.0;
    var xs = [];
    for (var i = 0; i < rects.length; i++) {
      xs.push(rects[i].x0);
      xs.push(rects[i].x1);
    }
    xs = Array.from(new Set(xs)).sort(function (a, b) { return a - b; });
    var cmPerFoot = CM_PER_FOOT * cmPerUnit;
    var totalCm2 = 0.0;
    for (var xi = 0; xi < xs.length - 1; xi++) {
      var x0 = xs[xi], x1 = xs[xi + 1], dx = x1 - x0;
      if (dx <= 0) continue;
      var spans = [];
      for (var r = 0; r < rects.length; r++) {
        if (rects[r].x0 < x1 && rects[r].x1 > x0) {
          spans.push([rects[r].y0, rects[r].y1]);
        }
      }
      if (!spans.length) continue;
      spans.sort(function (a, b) { return a[0] - b[0]; });
      var cur0 = spans[0][0], cur1 = spans[0][1], cover = 0.0;
      for (var j = 1; j < spans.length; j++) {
        if (spans[j][0] <= cur1) cur1 = Math.max(cur1, spans[j][1]);
        else {
          cover += cur1 - cur0;
          cur0 = spans[j][0];
          cur1 = spans[j][1];
        }
      }
      cover += cur1 - cur0;
      totalCm2 += dx * cover;
    }
    return roundTo(Math.max(0.0, totalCm2 / (cmPerFoot * cmPerFoot)), 2);
  }

  function rectsUnionAreaSqFt(rects, cmPerUnit) {
    var normalized = [];
    for (var i = 0; i < (rects || []).length; i++) {
      var n = normalizeLegacyRect(rects[i]);
      if (n) normalized.push(n);
    }
    return rectangleUnionAreaSqFt(normalized, cmPerUnit);
  }

  function resolveActiveFloor(plan) {
    if (plan.floor && typeof plan.floor === 'object') return plan.floor;
    if (!Array.isArray(plan.floors)) return null;
    var activeId = plan.activeFloorId;
    if (activeId) {
      for (var i = 0; i < plan.floors.length; i++) {
        if (plan.floors[i] && plan.floors[i].id === activeId) return plan.floors[i];
      }
    }
    return plan.floors[0] || null;
  }

  function extractZanini(plan) {
    if (!plan || typeof plan !== 'object') return {};
    if (plan.zanini && typeof plan.zanini === 'object') return plan.zanini;
    var floor = resolveActiveFloor(plan);
    if (floor && floor.zanini && typeof floor.zanini === 'object') return floor.zanini;
    return {};
  }

  function mergeZaniniIntoPlan(plan, zanini) {
    var out = plan && typeof plan === 'object' ? plan : {};
    out.zanini = zanini;
    return out;
  }

  function normalizeZanini(raw) {
    raw = raw || {};
    return {
      excluded_area_zones: Array.isArray(raw.excluded_area_zones) ? raw.excluded_area_zones : [],
      zones: Array.isArray(raw.zones) ? raw.zones : [],
      bulkheads: Array.isArray(raw.bulkheads) ? raw.bulkheads : [],
      wallFinishes: raw.wallFinishes && typeof raw.wallFinishes === 'object' ? raw.wallFinishes : {}
    };
  }

  function zonesToLegacyRects(zones, exclude) {
    var out = [];
    for (var i = 0; i < zones.length; i++) {
      var zone = zones[i];
      if (!zone || typeof zone !== 'object') continue;
      var isExclude = !!zone.exclude;
      if (isExclude !== exclude) continue;
      var rect = normalizeLegacyRect(zone);
      if (!rect) continue;
      out.push({ x1: rect.x0, y1: rect.y0, x2: rect.x1, y2: rect.y1 });
    }
    return out;
  }

  function resolveCmPerUnit(plan, options) {
    options = options || {};
    if (typeof options === 'number' && isFinite(options)) return options;
    if (options.cmPerUnit != null && isFinite(Number(options.cmPerUnit))) {
      return Number(options.cmPerUnit);
    }
    var unit = String(options.unit || (plan && (plan.units || plan.unit)) || 'cm').toLowerCase();
    if (unit === 'm' || unit === 'meter' || unit === 'meters') return 100.0;
    if (unit === 'in' || unit === 'inch' || unit === 'inches') return 2.54;
    if (unit === 'ft' || unit === 'feet' || unit === 'foot') return CM_PER_FOOT;
    return 1.0;
  }

  function normalizeWastePercent(p) {
    if (p == null || p === '') return DEFAULT_WASTE_PERCENT;
    var val = typeof p === 'string' ? parseFloat(p.replace('%', '')) : Number(p);
    if (!isFinite(val) || val < 0 || val > 100) return DEFAULT_WASTE_PERCENT;
    return roundTo(val, 2);
  }

  function normalizeBoxSqFt(b) {
    if (b == null || b === '') return DEFAULT_BOX_SQFT;
    var val = Number(b);
    if (!isFinite(val) || val < 0.25) return 0.0;
    if (val > 500) return 500.0;
    return roundTo(val, 2);
  }

  function flooringBoxes(area, waste, box) {
    area = Math.max(0, area);
    box = normalizeBoxSqFt(box);
    if (area <= 0 || box < 0.25) return 0;
    return Math.ceil(roundTo(area * (1.0 + normalizeWastePercent(waste) / 100.0) / box, 9));
  }

  function flooringWithWasteSqFt(area, waste) {
    area = Math.max(0, area);
    if (area <= 0) return 0.0;
    return roundTo(area * (1.0 + normalizeWastePercent(waste) / 100.0), 2);
  }

  function flagEnabled(flags, key, defaultVal) {
    if (!flags || !Object.prototype.hasOwnProperty.call(flags, key)) return defaultVal;
    return !!flags[key];
  }

  function applyExcludedZones(metrics, zanini, cmPerUnit, finish) {
    finish = finish || {};
    var excludeRects = (zanini.excluded_area_zones || []).concat(zonesToLegacyRects(zanini.zones || [], true));
    var includeRects = zonesToLegacyRects(zanini.zones || [], false);
    var excludedSqFt = rectsUnionAreaSqFt(excludeRects, cmPerUnit);
    var includedSqFt = rectsUnionAreaSqFt(includeRects, cmPerUnit);
    var area = roundTo(Math.max(0, Number(metrics.area_sqft || 0) - excludedSqFt + includedSqFt), 2);
    metrics.excluded_area_sqft = excludedSqFt;
    metrics.area_sqft = area;
    metrics.inside_area_sqft = area;
    metrics.ceiling_area_sqft = area;
    var waste = metrics.flooring_waste_percent != null ? metrics.flooring_waste_percent : finish.flooring_waste_percent;
    var box = metrics.flooring_box_sqft != null ? metrics.flooring_box_sqft : finish.flooring_box_sqft;
    metrics.flooring_with_waste_sqft = flooringWithWasteSqFt(area, waste);
    metrics.flooring_boxes = flooringBoxes(area, waste, box);
    return metrics;
  }

  function applyBulkheadMetrics(metrics, zanini, cmPerUnit) {
    var framingLf = 0.0, drywallSqFt = 0.0, cornerBeadLf = 0.0;
    var bulkheads = zanini.bulkheads || [];
    for (var i = 0; i < bulkheads.length; i++) {
      var bulkhead = bulkheads[i];
      if (!bulkhead || typeof bulkhead !== 'object') continue;
      var rect = bulkhead.rect || bulkhead;
      var normalized = normalizeLegacyRect(rect);
      if (!normalized) continue;
      var lengthFt = Math.abs(normalized.x1 - normalized.x0) * cmPerUnit / CM_PER_FOOT;
      var widthFt = Math.abs(normalized.y1 - normalized.y0) * cmPerUnit / CM_PER_FOOT;
      var dropFt = Math.max(0, Number(bulkhead.drop_height_ft != null ? bulkhead.drop_height_ft : 1.0));
      var longFt = Math.max(lengthFt, widthFt);
      var shortFt = Math.min(lengthFt, widthFt);
      var perimeterLf = roundTo(2.0 * (lengthFt + widthFt), 2);
      framingLf += perimeterLf;
      drywallSqFt += roundTo(2.0 * (longFt * dropFt + shortFt * dropFt) + (longFt * shortFt), 2);
      cornerBeadLf += roundTo(4.0 * dropFt, 2);
    }
    metrics.bulkhead_framing_lf = roundTo(framingLf, 2);
    metrics.bulkhead_drywall_sqft = roundTo(drywallSqFt, 2);
    metrics.bulkhead_corner_bead_lf = roundTo(cornerBeadLf, 2);
    metrics.bulkhead_lf = metrics.bulkhead_framing_lf;
    return metrics;
  }

  function applyWallFinishMetrics(metrics, zanini) {
    var walls = metrics.walls || [];
    var boundaryIds = metrics.boundary_wall_ids || [];
    var boundaryMap = {};
    for (var b = 0; b < boundaryIds.length; b++) boundaryMap[String(boundaryIds[b])] = true;
    var finishes = zanini.wallFinishes || {};
    var finishKeys = Object.keys(finishes);
    var drywall = 0.0, insulation = 0.0, studs = 0.0, baseboard = 0.0;

    if (finishKeys.length === 0 && walls.length > 0) {
      for (var i = 0; i < walls.length; i++) {
        var wall = walls[i];
        if (!wall) continue;
        var wallId = String(wall.id || '');
        var isBoundary = !!boundaryMap[wallId];
        var lengthLf = Number(wall.length_lf || 0);
        var netSqFt = Number(wall.net_area_sqft || 0);
        drywall += netSqFt;
        if (isBoundary) {
          insulation += netSqFt;
          baseboard += lengthLf;
        } else {
          studs += lengthLf;
          baseboard += lengthLf * 2.0;
        }
      }
    } else {
      for (var j = 0; j < walls.length; j++) {
        var w = walls[j];
        if (!w) continue;
        var id = String(w.id || '');
        var isB = !!boundaryMap[id];
        var len = Number(w.length_lf || 0);
        var net = Number(w.net_area_sqft || 0);
        var flags = finishes[id] || {};
        if (flagEnabled(flags, 'drywall', true)) drywall += net;
        if (flagEnabled(flags, 'insulation', isB)) insulation += net;
        if (flagEnabled(flags, 'studs', !isB)) studs += len;
        if (flagEnabled(flags, 'baseboard', isB)) baseboard += len;
        else if (flagEnabled(flags, 'baseboard', !isB)) baseboard += len * 2.0;
      }
    }

    metrics.finish_drywall_sqft = roundTo(Math.max(0, drywall), 2);
    metrics.finish_insulation_sqft = roundTo(Math.max(0, insulation), 2);
    metrics.finish_stud_lf = roundTo(Math.max(0, studs), 2);
    metrics.finish_baseboard_lf = roundTo(Math.max(0, baseboard), 2);
    metrics.finish_casing_lf = roundTo(Number(metrics.casing_lf || 0), 2);
    return metrics;
  }

  function applyToMetrics(metrics, plan, finish, options) {
    if (!metrics || !plan) return metrics || {};
    var cmPerUnit = resolveCmPerUnit(plan, options);
    var zanini = normalizeZanini(extractZanini(plan));
    var out = applyExcludedZones(metrics, zanini, cmPerUnit, finish || {});
    out = applyBulkheadMetrics(out, zanini, cmPerUnit);
    out = applyWallFinishMetrics(out, zanini);
    return out;
  }

  return {
    CM_PER_FOOT: CM_PER_FOOT,
    roundTo: roundTo,
    normalizeLegacyRect: normalizeLegacyRect,
    rectangleUnionAreaSqFt: rectangleUnionAreaSqFt,
    rectsUnionAreaSqFt: rectsUnionAreaSqFt,
    extractZanini: extractZanini,
    mergeZaniniIntoPlan: mergeZaniniIntoPlan,
    normalizeZanini: normalizeZanini,
    applyToMetrics: applyToMetrics
  };
}));

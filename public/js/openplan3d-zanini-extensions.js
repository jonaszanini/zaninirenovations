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
      if (zone.mergedInto) continue;
      var isExclude = !!zone.exclude;
      if (isExclude !== exclude) continue;
      var rect = normalizeLegacyRect(zone);
      if (!rect) continue;
      for (var j = 0; j < zones.length; j++) {
        var merged = zones[j];
        if (!merged || !merged.mergedInto || merged.mergedInto !== zone.id) continue;
        var mergedRect = normalizeLegacyRect(merged);
        if (!mergedRect) continue;
        rect = {
          x0: Math.min(rect.x0, mergedRect.x0),
          y0: Math.min(rect.y0, mergedRect.y0),
          x1: Math.max(rect.x1, mergedRect.x1),
          y1: Math.max(rect.y1, mergedRect.y1)
        };
      }
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

  function mergeYSpans(spans) {
    if (!spans.length) return [];
    spans.sort(function (a, b) { return a[0] - b[0]; });
    var cur0 = spans[0][0], cur1 = spans[0][1], merged = [];
    for (var i = 1; i < spans.length; i++) {
      if (spans[i][0] <= cur1) cur1 = Math.max(cur1, spans[i][1]);
      else {
        merged.push([cur0, cur1]);
        cur0 = spans[i][0];
        cur1 = spans[i][1];
      }
    }
    merged.push([cur0, cur1]);
    return merged;
  }

  function spanListsIntersectionLength(a, b) {
    var len = 0;
    for (var i = 0; i < a.length; i++) {
      for (var j = 0; j < b.length; j++) {
        var y0 = Math.max(a[i][0], b[j][0]);
        var y1 = Math.min(a[i][1], b[j][1]);
        if (y1 > y0) len += y1 - y0;
      }
    }
    return len;
  }

  function rectUnionYSpansAtX(rects, x) {
    var spans = [];
    for (var i = 0; i < rects.length; i++) {
      if (rects[i].x0 < x && rects[i].x1 > x) {
        spans.push([rects[i].y0, rects[i].y1]);
      }
    }
    return mergeYSpans(spans);
  }

  function polygonYSpansAtX(poly, x) {
    var ys = [];
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      var x1 = poly[i].x, y1 = poly[i].y, x2 = poly[j].x, y2 = poly[j].y;
      if (x1 === x2) continue;
      var minX = Math.min(x1, x2), maxX = Math.max(x1, x2);
      if (x < minX || x >= maxX) continue;
      var t = (x - x1) / (x2 - x1);
      ys.push(y1 + t * (y2 - y1));
    }
    ys.sort(function (a, b) { return a - b; });
    var spans = [];
    for (var k = 0; k + 1 < ys.length; k += 2) spans.push([ys[k], ys[k + 1]]);
    return spans;
  }

  function rectUnionPolygonIntersectionAreaSqFt(rects, polygonCm, cmPerUnit) {
    cmPerUnit = cmPerUnit > 0 ? cmPerUnit : 1.0;
    var poly = [];
    for (var p = 0; p < (polygonCm || []).length; p++) {
      poly.push({ x: Number(polygonCm[p].x || 0), y: Number(polygonCm[p].y || 0) });
    }
    if (poly.length < 3) return rectangleUnionAreaSqFt(rects, cmPerUnit);
    var cmPerFoot = CM_PER_FOOT * cmPerUnit;
    var xs = [];
    for (var r = 0; r < rects.length; r++) {
      xs.push(rects[r].x0, rects[r].x1);
    }
    for (var q = 0; q < poly.length; q++) xs.push(poly[q].x);
    xs = Array.from(new Set(xs)).sort(function (a, b) { return a - b; });
    var totalCm2 = 0;
    for (var xi = 0; xi < xs.length - 1; xi++) {
      var x0 = xs[xi], x1 = xs[xi + 1], dx = x1 - x0;
      if (dx <= 0) continue;
      var xMid = (x0 + x1) / 2;
      totalCm2 += dx * spanListsIntersectionLength(
        rectUnionYSpansAtX(rects, xMid),
        polygonYSpansAtX(poly, xMid)
      );
    }
    return roundTo(Math.max(0, totalCm2 / (cmPerFoot * cmPerFoot)), 2);
  }

  function excludedZonesAreaSqFt(rects, polygonCm, cmPerUnit) {
    if (!rects || !rects.length) return 0;
    if (!polygonCm || polygonCm.length < 3) return rectangleUnionAreaSqFt(rects, cmPerUnit);
    return rectUnionPolygonIntersectionAreaSqFt(rects, polygonCm, cmPerUnit);
  }

  function rectInsidePolygonAreaSqFt(rect, polygonCm, cmPerUnit) {
    return rectUnionPolygonIntersectionAreaSqFt([rect], polygonCm, cmPerUnit);
  }

  function bulkheadTakeoff(longFt, shortFt, dropFt, againstWall) {
    longFt = Math.max(0, longFt);
    shortFt = Math.max(0, shortFt);
    dropFt = Math.max(0, dropFt);
    var drywallSqFt = (longFt * shortFt) + (longFt * dropFt);
    if (!againstWall) {
      drywallSqFt += (longFt * dropFt) + (2 * shortFt * dropFt);
    }
    var cornerBeadLf = againstWall ? longFt : (2 * longFt + 2 * shortFt);
    var spacingFt = 16 / 12;
    var crossCount = longFt > 0 ? Math.max(0, Math.ceil(longFt / spacingFt) - 1) : 0;
    var framingLf = (2 * longFt) + (crossCount * shortFt);
    return {
      framing_lf: roundTo(framingLf, 2),
      drywall_sqft: roundTo(Math.max(0, drywallSqFt), 2),
      corner_bead_lf: roundTo(Math.max(0, cornerBeadLf), 2)
    };
  }

  function applyExcludedZones(metrics, zanini, cmPerUnit, finish) {
    finish = finish || {};
    var roomPolyCm = metrics.room_polygon_cm || [];
    var excludeRects = (zanini.excluded_area_zones || []).concat(zonesToLegacyRects(zanini.zones || [], true));
    var normalized = [];
    for (var i = 0; i < excludeRects.length; i++) {
      var n = normalizeLegacyRect(excludeRects[i]);
      if (n) normalized.push(n);
    }
    var excludedSqFt = 0;
    if (normalized.length) {
      excludedSqFt = excludedZonesAreaSqFt(normalized, roomPolyCm, cmPerUnit);
    }
    excludedSqFt = roundTo(Math.max(0, excludedSqFt), 2);
    var area = roundTo(Math.max(0, Number(metrics.area_sqft || 0) - excludedSqFt), 2);
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
      var againstWall = !Object.prototype.hasOwnProperty.call(bulkhead, 'against_wall') || !!bulkhead.against_wall;
      var takeoff = bulkheadTakeoff(longFt, shortFt, dropFt, againstWall);
      framingLf += takeoff.framing_lf;
      drywallSqFt += takeoff.drywall_sqft;
      cornerBeadLf += takeoff.corner_bead_lf;
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
        } else {
          studs += lengthLf;
        }
      }
      baseboard = Number(metrics.baseboard_lf || 0);
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
        if (flagEnabled(flags, 'baseboard', true)) {
          var sides = flags.baseboardSides != null ? Number(flags.baseboardSides) : (isB ? 1 : 2);
          sides = Math.max(1, Math.min(2, sides));
          baseboard += len * sides;
        }
      }
    }

    metrics.finish_drywall_sqft = roundTo(Math.max(0, drywall), 2);
    metrics.finish_insulation_sqft = roundTo(Math.max(0, insulation), 2);
    metrics.finish_stud_lf = roundTo(Math.max(0, studs), 2);
    metrics.finish_baseboard_lf = roundTo(Math.max(0, baseboard), 2);
    metrics.finish_casing_lf = roundTo(Number(metrics.casing_lf || 0), 2);
    return metrics;
  }

  function flagEnabled(flags, key, defaultVal) {
    if (!flags || !Object.prototype.hasOwnProperty.call(flags, key)) return defaultVal;
    return !!flags[key];
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
    applyToMetrics: applyToMetrics,
    bulkheadTakeoff: bulkheadTakeoff,
    rectInsidePolygonAreaSqFt: rectInsidePolygonAreaSqFt,
    excludedZonesAreaSqFt: excludedZonesAreaSqFt,
    rectUnionPolygonIntersectionAreaSqFt: rectUnionPolygonIntersectionAreaSqFt
  };
}));

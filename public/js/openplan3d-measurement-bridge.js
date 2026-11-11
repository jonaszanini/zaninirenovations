/**
 * OpenPlan3D Measurement Bridge (JavaScript).
 * Computes per-room measurements matching the exact calculation rules and rounding of PHP OpenPlan3DMeasurementBridge.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.OpenPlan3DMeasurementBridge = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function resolveInteriorGeom() {
    try {
      return require('./room-area-interior-geometry.js');
    } catch (e) { /* browser bundle */ }
    if (typeof RoomAreaInteriorGeometry !== 'undefined') return RoomAreaInteriorGeometry;
    return null;
  }
  var interiorGeom = resolveInteriorGeom();
  function interiorGeomRuntime() {
    return interiorGeom || resolveInteriorGeom();
  }

  var CM_PER_INCH = 2.54;
  var CM_PER_FOOT = 30.48;
  var DEFAULT_WALL_HEIGHT_FT = 8.0;
  var DEFAULT_DOOR_HEIGHT_IN = 80.0;
  var DEFAULT_WINDOW_HEIGHT_IN = 36.0;
  var DEFAULT_OPENING_HEIGHT_IN = 84.0;
  var DEFAULT_WALL_THICKNESS_IN = 4.5;
  var DEFAULT_WASTE_PERCENT = 10.0;
  var DEFAULT_BOX_SQFT = 20.0;
  var STAIR_TREAD_IN = 10.0;

  function roundTo(value, digits) {
    var places = Math.max(0, digits);
    var factor = Math.pow(10, places);
    var shifted = value * factor;
    var sign = shifted < 0 ? -1 : 1;
    return sign * Math.round(Math.abs(shifted) + 1e-9) / factor;
  }

  function hypot(dx, dy) {
    return Math.sqrt(dx * dx + dy * dy);
  }

  /** Snap plan coordinates to 0.1 cm so editor float noise does not break graph tracing. */
  function snapPlanCoordinate(value, cmPerUnit) {
    var unitScale = cmPerUnit > 0 ? cmPerUnit : 1.0;
    var cm = Number(value || 0) * unitScale;
    var snappedCm = Math.round(cm * 100) / 100;
    return snappedCm / unitScale;
  }

  function snapPlanPoint(pt, cmPerUnit) {
    return {
      x: snapPlanCoordinate(pt.x, cmPerUnit),
      y: snapPlanCoordinate(pt.y, cmPerUnit)
    };
  }

  function wallThicknessFt(wall, cmPerUnit) {
    var ig = interiorGeomRuntime();
    var thickCm = ig
      ? ig.wallThicknessCm(wall && wall.thickness, cmPerUnit)
      : (Number(wall && wall.thickness != null ? wall.thickness : 11.43) * Math.max(cmPerUnit, 1e-9));
    return thickCm / CM_PER_FOOT;
  }

  /**
   * Interior (partition) walls that T into a boundary segment deduct their thickness
   * from baseboard / drywall runs on that exterior wall (not at corners).
   */
  function boundaryWallCollinearNeighborAtPoint(boundaryWall, px, py, allWalls, boundaryIdMap, cmPerUnit, unit) {
    var eps = partitionToleranceCm(allWalls, cmPerUnit);
    var bx0 = boundaryWall.start.x * unit;
    var by0 = boundaryWall.start.y * unit;
    var bx1 = boundaryWall.end.x * unit;
    var by1 = boundaryWall.end.y * unit;
    var atStart = hypot(px - bx0, py - by0) < eps;
    var atEnd = hypot(px - bx1, py - by1) < eps;
    if (!atStart && !atEnd) return false;
    var edx = bx1 - bx0;
    var edy = by1 - by0;
    var elen = hypot(edx, edy) || 1;
    var ux = edx / elen;
    var uy = edy / elen;
    for (var i = 0; i < allWalls.length; i++) {
      var bw = allWalls[i];
      if (!bw || !bw.start || !bw.end) continue;
      var bid = String(bw.id);
      if (bid === String(boundaryWall.id) || !boundaryIdMap[bid]) continue;
      var sx = bw.start.x * unit;
      var sy = bw.start.y * unit;
      var ex = bw.end.x * unit;
      var ey = bw.end.y * unit;
      var shares = false;
      if (atStart && hypot(sx - px, sy - py) < eps) shares = true;
      if (atStart && hypot(ex - px, ey - py) < eps) shares = true;
      if (atEnd && hypot(sx - px, sy - py) < eps) shares = true;
      if (atEnd && hypot(ex - px, ey - py) < eps) shares = true;
      if (!shares) continue;
      var ndx = ex - sx;
      var ndy = ey - sy;
      var nlen = hypot(ndx, ndy) || 1;
      var dot = Math.abs(ux * (ndx / nlen) + uy * (ndy / nlen));
      if (dot > 0.995) return true;
    }
    return false;
  }

  function interiorButtDeductionLfOnBoundaryWall(boundaryWall, allWalls, boundaryIdMap, cmPerUnit, junctionDedup) {
    if (!boundaryWall || !boundaryWall.start || !boundaryWall.end) return 0;
    var unit = Math.max(cmPerUnit, 1e-9);
    var eps = partitionToleranceCm(allWalls, cmPerUnit);
    var ax = boundaryWall.start.x * unit;
    var ay = boundaryWall.start.y * unit;
    var bx = boundaryWall.end.x * unit;
    var by = boundaryWall.end.y * unit;
    var edx = bx - ax;
    var edy = by - ay;
    var elen = hypot(edx, edy) || 1;
    var ded = 0.0;
    var seen = {};
    for (var i = 0; i < allWalls.length; i++) {
      var other = allWalls[i];
      if (!other || !other.start || !other.end) continue;
      var oid = String(other.id);
      if (oid === String(boundaryWall.id)) continue;
      if (boundaryIdMap[oid]) continue;
      function checkPoint(px, py) {
        var cross = Math.abs(edx * (py - ay) - edy * (px - ax)) / elen;
        if (cross > eps) return;
        var t = ((px - ax) * edx + (py - ay) * edy) / (elen * elen);
        var atEnd = t <= eps / elen || t >= 1 - eps / elen;
        if (atEnd) {
          if (!boundaryWallCollinearNeighborAtPoint(boundaryWall, px, py, allWalls, boundaryIdMap, cmPerUnit, unit)) {
            return;
          }
        }
        var jkey = Math.round(px * 10) + ',' + Math.round(py * 10);
        if (junctionDedup) {
          if (junctionDedup[jkey]) return;
          junctionDedup[jkey] = true;
        }
        var key = oid + '@' + jkey;
        if (seen[key]) return;
        seen[key] = true;
        ded += wallThicknessFt(other, cmPerUnit);
      }
      checkPoint(other.start.x * unit, other.start.y * unit);
      checkPoint(other.end.x * unit, other.end.y * unit);
    }
    return ded;
  }

  function interiorButtDeductionLfOnInteriorWall(interiorWall, allWalls, boundaryIdMap, cmPerUnit, junctionDedup) {
    if (!interiorWall || !interiorWall.start || !interiorWall.end) return 0;
    var wid = String(interiorWall.id);
    if (boundaryIdMap[wid]) return 0;
    var unit = Math.max(cmPerUnit, 1e-9);
    var eps = partitionToleranceCm(allWalls, cmPerUnit);
    var ax = interiorWall.start.x * unit;
    var ay = interiorWall.start.y * unit;
    var bx = interiorWall.end.x * unit;
    var by = interiorWall.end.y * unit;
    var edx = bx - ax;
    var edy = by - ay;
    var elen = hypot(edx, edy) || 1;
    var ded = 0.0;
    for (var i = 0; i < allWalls.length; i++) {
      var other = allWalls[i];
      if (!other || !other.start || !other.end) continue;
      var oid = String(other.id);
      if (oid === wid) continue;
      if (boundaryIdMap[oid]) continue;
      function checkPoint(px, py) {
        var cross = Math.abs(edx * (py - ay) - edy * (px - ax)) / elen;
        if (cross > eps) return;
        var t = ((px - ax) * edx + (py - ay) * edy) / (elen * elen);
        if (t <= eps / elen || t >= 1 - eps / elen) return;
        var jkey = 'p:' + Math.round(px * 10) + ',' + Math.round(py * 10);
        if (junctionDedup && junctionDedup[jkey]) return;
        if (junctionDedup) junctionDedup[jkey] = true;
        ded += wallThicknessFt(other, cmPerUnit);
      }
      checkPoint(other.start.x * unit, other.start.y * unit);
      checkPoint(other.end.x * unit, other.end.y * unit);
    }
    return ded;
  }

  function partitionToleranceCm(wallsList, cmPerUnit) {
    var baseCm = 20.0;
    var maxCm = 30.0;
    var minLenCm = Infinity;
    for (var i = 0; i < (wallsList || []).length; i++) {
      var w = wallsList[i];
      if (!w || !w.start || !w.end) continue;
      var lenCm = hypot((w.end.x - w.start.x) * cmPerUnit, (w.end.y - w.start.y) * cmPerUnit);
      if (lenCm > 0.01 && lenCm < minLenCm) minLenCm = lenCm;
    }
    if (!isFinite(minLenCm)) return baseCm;
    return Math.min(maxCm, Math.max(baseCm, minLenCm * 0.08));
  }

  function onSegment(p, q, r) {
    return (
      Math.min(p.x, q.x) - 1e-9 <= r.x && r.x <= Math.max(p.x, q.x) + 1e-9 &&
      Math.min(p.y, q.y) - 1e-9 <= r.y && r.y <= Math.max(p.y, q.y) + 1e-9
    );
  }

  function direction(p, q, r) {
    var val = (q.y - p.y) * (r.x - q.x) - (q.x - p.x) * (r.y - q.y);
    if (Math.abs(val) < 1e-9) return 0;
    return val > 0 ? 1 : 2;
  }

  function segmentsIntersect(p1, q1, p2, q2) {
    var d1 = direction(p1, q1, p2);
    var d2 = direction(p1, q1, q2);
    var d3 = direction(p2, q2, p1);
    var d4 = direction(p2, q2, q1);
    if (((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0))) return true;
    if (d1 === 0 && onSegment(p1, q1, p2)) return true;
    if (d2 === 0 && onSegment(p1, q1, q2)) return true;
    if (d3 === 0 && onSegment(p2, q2, p1)) return true;
    if (d4 === 0 && onSegment(p2, q2, q1)) return true;
    return false;
  }

  function isSimplePolygon(vertices) {
    var n = (vertices || []).length;
    if (n < 3) return false;
    for (var i = 0; i < n; i++) {
      var a1 = vertices[i];
      var a2 = vertices[(i + 1) % n];
      for (var j = i + 1; j < n; j++) {
        if (j === i + 1) continue;
        if (i === 0 && j === n - 1) continue;
        var b1 = vertices[j];
        var b2 = vertices[(j + 1) % n];
        if (segmentsIntersect(a1, a2, b1, b2)) return false;
      }
    }
    return true;
  }

  function isCollapsedZeroFootprint(verticesFt) {
    var verts = verticesFt || [];
    if (verts.length < 3) return true;
    var minX = Number(verts[0].x);
    var maxX = minX;
    var minY = Number(verts[0].y);
    var maxY = minY;
    for (var i = 1; i < verts.length; i++) {
      var x = Number(verts[i].x);
      var y = Number(verts[i].y);
      if (x < minX) minX = x;
      if (x > maxX) maxX = x;
      if (y < minY) minY = y;
      if (y > maxY) maxY = y;
    }
    return hypot(maxX - minX, maxY - minY) < 0.02;
  }

  function polygonVerticesToFeet(polygon, cmPerUnit) {
    var out = [];
    for (var i = 0; i < (polygon || []).length; i++) {
      var pt = polygon[i];
      out.push({
        x: (Number(pt.x || 0) * cmPerUnit) / CM_PER_FOOT,
        y: (Number(pt.y || 0) * cmPerUnit) / CM_PER_FOOT
      });
    }
    return out;
  }

  function outerLoopHasUsableArea(outerPolygon, cmPerUnit) {
    if (!outerPolygon || outerPolygon.length < 3) return false;
    var area = verticesToSquareFeet(polygonVerticesToFeet(outerPolygon, cmPerUnit));
    return area > 0.01;
  }

  function storedPolygonMeasurable(storedPoly, cmPerUnit) {
    if (!storedPoly || storedPoly.length < 3) return false;
    var feet = polygonVerticesToFeet(storedPoly, cmPerUnit);
    if (!isSimplePolygon(feet)) return false;
    return verticesToSquareFeet(feet) > 0.01;
  }

  function endpointClusterToleranceCm(wallsList, cmPerUnit) {
    // Independent draw jitter per endpoint can separate a corner by ~2*maxOffset*sqrt(2).
    // Use a generous merge distance (up to partition tolerance) without replacing T-junction eps.
    var junctionCm = partitionToleranceCm(wallsList, cmPerUnit);
    var drawNoiseCm = 3.5;
    return Math.min(junctionCm, Math.max(drawNoiseCm, junctionCm * 0.45));
  }

  function clusterSegmentEndpoints(rawSegments, cmPerUnit, wallsList) {
    var clusterCm = endpointClusterToleranceCm(wallsList || rawSegments.map(function (s) { return s.wall; }), cmPerUnit);
    var epsUnit = clusterCm / (cmPerUnit > 0 ? cmPerUnit : 1.0);
    var refs = [];
    for (var si = 0; si < rawSegments.length; si++) {
      var seg = rawSegments[si];
      refs.push({ seg: seg, key: 'start', x: seg.start.x, y: seg.start.y });
      refs.push({ seg: seg, key: 'end', x: seg.end.x, y: seg.end.y });
    }
    if (refs.length === 0) return;

    var parent = refs.map(function (_, i) { return i; });
    function find(i) {
      while (parent[i] !== i) {
        parent[i] = parent[parent[i]];
        i = parent[i];
      }
      return i;
    }
    function unite(a, b) {
      var ra = find(a);
      var rb = find(b);
      if (ra !== rb) parent[rb] = ra;
    }

    for (var i = 0; i < refs.length; i++) {
      for (var j = i + 1; j < refs.length; j++) {
        if (hypot(refs[i].x - refs[j].x, refs[i].y - refs[j].y) < epsUnit) {
          unite(i, j);
        }
      }
    }

    var sums = {};
    var counts = {};
    for (var i = 0; i < refs.length; i++) {
      var root = find(i);
      if (!sums[root]) {
        sums[root] = { x: 0, y: 0 };
        counts[root] = 0;
      }
      sums[root].x += refs[i].x;
      sums[root].y += refs[i].y;
      counts[root]++;
    }

    for (var i = 0; i < refs.length; i++) {
      var root = find(i);
      var n = counts[root] || 1;
      var cx = sums[root].x / n;
      var cy = sums[root].y / n;
      var canon = snapPlanPoint({ x: cx, y: cy }, cmPerUnit);
      if (refs[i].key === 'start') {
        refs[i].seg.start = canon;
      } else {
        refs[i].seg.end = canon;
      }
    }

    // Snap free endpoints onto nearby segment interiors (almost-T junctions from draw error)
    for (var si = 0; si < rawSegments.length; si++) {
      var seg = rawSegments[si];
      var keys = ['start', 'end'];
      for (var ki = 0; ki < keys.length; ki++) {
        var key = keys[ki];
        var pt = seg[key];
        var best = null;
        var bestDist = epsUnit;
        for (var sj = 0; sj < rawSegments.length; sj++) {
          var s = rawSegments[sj];
          var res = distToSegment(pt, s.start, s.end);
          var segLen = hypot(s.end.x - s.start.x, s.end.y - s.start.y);
          if (segLen < 1e-6) continue;
          if (res.dist >= bestDist) continue;
          if (res.t * segLen <= epsUnit || (1.0 - res.t) * segLen <= epsUnit) continue;
          bestDist = res.dist;
          best = snapPlanPoint(res.proj, cmPerUnit);
        }
        if (best) seg[key] = best;
      }
    }
  }

  function polygonAreaSqFt(polygon, cmPerUnit) {
    if (!polygon || polygon.length < 3) return 0.0;
    return verticesToSquareFeet(polygonVerticesToFeet(polygon, cmPerUnit));
  }

  function shouldReplaceRoomPolygonWithOuterLoop(storedPoly, loopData, cmPerUnit, roomWallIds) {
    var outer = loopData && loopData.outer_polygon ? loopData.outer_polygon : [];
    if (!outerLoopHasUsableArea(outer, cmPerUnit)) return false;
    if (!storedPoly || storedPoly.length < 3) return true;

    var partitions = (loopData.partition_walls && loopData.partition_walls.length) || 0;
    var outerArea = polygonAreaSqFt(outer, cmPerUnit);
    var storedArea = polygonAreaSqFt(storedPoly, cmPerUnit);
    var storedMeasurable = storedPolygonMeasurable(storedPoly, cmPerUnit);

    if (!storedMeasurable) {
      return true;
    }

    if (partitions > 0 && outerArea > storedArea + 0.5) {
      var wallIds = roomWallIds || [];
      return wallIds.length > 0;
    }

    return false;
  }

  function applyOuterLoopPolygon(storedPoly, loopData, cmPerUnit, roomWallIds) {
    if (shouldReplaceRoomPolygonWithOuterLoop(storedPoly, loopData, cmPerUnit, roomWallIds)) {
      return loopData.outer_polygon.slice();
    }
    var poly = storedPoly || [];
    if (poly.length < 3 && outerLoopHasUsableArea(loopData && loopData.outer_polygon, cmPerUnit)) {
      return loopData.outer_polygon.slice();
    }
    return poly;
  }

  function normalizeWallHeight(h) {
    var val = Number(h);
    if (!isFinite(val) || val < 1) return DEFAULT_WALL_HEIGHT_FT;
    if (val > 20) return 20.0;
    return roundTo(val, 2);
  }

  function normalizeWastePercent(p) {
    if (p == null || p === '') return DEFAULT_WASTE_PERCENT;
    var val = typeof p === 'string' ? parseFloat(p.replace('%', '').replace(',', '.')) : Number(p);
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

  function normalizeDirection(d) {
    var val = Number(d);
    if (!isFinite(val)) return 0;
    return (Math.abs(val) % 180) >= 45 ? 90 : 0;
  }

  function flooringBoxes(floorAreaSqFt, wastePercent, boxSqFt) {
    var area = Math.max(0, floorAreaSqFt);
    var box = normalizeBoxSqFt(boxSqFt);
    if (area <= 0 || box < 0.25) return 0;
    var needed = area * (1.0 + (normalizeWastePercent(wastePercent) / 100.0));
    return Math.ceil(roundTo(needed / box, 9));
  }

  function flooringWithWasteSqFt(floorAreaSqFt, wastePercent) {
    var area = Math.max(0, floorAreaSqFt);
    if (area <= 0) return 0.0;
    return roundTo(area * (1.0 + (normalizeWastePercent(wastePercent) / 100.0)), 2);
  }

  function ceilingHeightFt(ceilingHeight, wallHeight) {
    var wall = normalizeWallHeight(wallHeight);
    if (ceilingHeight == null || ceilingHeight === '' || !isFinite(Number(ceilingHeight))) {
      return wall;
    }
    return normalizeWallHeight(ceilingHeight);
  }

  function casingLf(widthIn, heightIn, sides, type) {
    type = String(type || 'door').toLowerCase().trim();
    if (widthIn <= 0 || heightIn <= 0) return 0.0;
    if (type === 'window') {
      if (sides >= 4) return roundTo((2.0 * widthIn + 2.0 * heightIn) / 12.0, 2);
      if (sides === 3) return roundTo((widthIn + 2.0 * heightIn) / 12.0, 2);
      return 0.0;
    }
    sides = Math.max(0, Math.min(2, sides));
    if (sides <= 0) return 0.0;
    return roundTo(sides * (widthIn + 2.0 * heightIn) / 12.0, 2);
  }

  function netWallAreaSqFt(lengthFt, heightFt, openingAreaSqFt) {
    return roundTo(Math.max(0.0, lengthFt * heightFt - Math.max(0.0, openingAreaSqFt || 0.0)), 2);
  }

  function unionOpeningAreaRawSqFt(rects) {
    if (!rects || rects.length === 0) return 0.0;
    var xs = [];
    for (var i = 0; i < rects.length; i++) {
      xs.push(rects[i].x0);
      xs.push(rects[i].x1);
    }
    xs = Array.from(new Set(xs)).sort(function (a, b) { return a - b; });

    var totalArea = 0.0;
    for (var i = 0; i < xs.length - 1; i++) {
      var x0 = xs[i], x1 = xs[i + 1], dx = x1 - x0;
      if (dx <= 0) continue;

      var spans = [];
      for (var r = 0; r < rects.length; r++) {
        if (rects[r].x0 < x1 && rects[r].x1 > x0) {
          spans.push([rects[r].y0, rects[r].y1]);
        }
      }
      if (spans.length === 0) continue;

      spans.sort(function (a, b) { return a[0] - b[0]; });
      var cur0 = spans[0][0], cur1 = spans[0][1];
      var cover = 0.0;
      for (var j = 1; j < spans.length; j++) {
        if (spans[j][0] <= cur1) {
          cur1 = Math.max(cur1, spans[j][1]);
        } else {
          cover += cur1 - cur0;
          cur0 = spans[j][0];
          cur1 = spans[j][1];
        }
      }
      cover += cur1 - cur0;
      totalArea += dx * cover;
    }
    return totalArea / 144.0;
  }

  function roundToSixteenthFoot(feet) {
    return Math.round(feet * 16) / 16;
  }

  function verticesToSquareFeet(vertices) {
    var n = vertices.length;
    if (n < 3) return 0.0;
    var sum = 0.0;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      var ax = Number(vertices[i].x) || 0;
      var ay = Number(vertices[i].y) || 0;
      var bx = Number(vertices[j].x) || 0;
      var by = Number(vertices[j].y) || 0;
      sum += (ax * by) - (bx * ay);
    }
    return roundTo(Math.abs(sum) * 0.5, 2);
  }

  function cornerCounts(vertices) {
    var n = vertices.length;
    if (n < 3) return { inside: 0, outside: 0 };
    var crossSum = 0.0;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      crossSum += (vertices[i].x * vertices[j].y) - (vertices[j].x * vertices[i].y);
    }
    var isCCW = crossSum > 0;

    var inside = 0, outside = 0;
    for (var i = 0; i < n; i++) {
      var prev = vertices[(i - 1 + n) % n];
      var curr = vertices[i];
      var next = vertices[(i + 1) % n];

      var v1x = curr.x - prev.x, v1y = curr.y - prev.y;
      var v2x = next.x - curr.x, v2y = next.y - curr.y;
      var cp = (v1x * v2y) - (v1y * v2x);

      if (Math.abs(cp) < 1e-9) continue;
      if (isCCW ? cp > 0 : cp < 0) {
        inside++;
      } else {
        outside++;
      }
    }
    return { inside: inside, outside: outside };
  }

  function isPlan(data) {
    if (!data || typeof data !== 'object') return false;
    if (data.format && String(data.format).toLowerCase().trim() === 'openplan3d') return true;
    if (data.engine && String(data.engine).toLowerCase().trim() === 'openplan3d') return true;
    if (Array.isArray(data.floors)) return true;
    if (data.floor && typeof data.floor === 'object') return true;
    if (data.project && Array.isArray(data.project.floors)) return true;
    if (Array.isArray(data.walls) && (Array.isArray(data.rooms) || Array.isArray(data.doors) || Array.isArray(data.windows))) return true;
    return false;
  }

  function resolveFloorFromList(floors, activeId) {
    if (!Array.isArray(floors) || floors.length === 0) return null;
    if (activeId) {
      for (var i = 0; i < floors.length; i++) {
        if (floors[i] && floors[i].id === activeId) return floors[i];
      }
      return null;
    }
    return floors[0] || null;
  }

  function resolveActiveFloorEntry(floors, activeId) {
    if (!Array.isArray(floors) || floors.length === 0) {
      return { floor: null, matchedById: false };
    }
    if (activeId) {
      for (var i = 0; i < floors.length; i++) {
        if (floors[i] && floors[i].id === activeId) {
          return { floor: floors[i], matchedById: true };
        }
      }
      return { floor: null, matchedById: false, activeMissing: true };
    }
    return { floor: floors[0] || null, matchedById: false };
  }

  function countDrawableWalls(floor) {
    if (!floor || !Array.isArray(floor.walls)) return 0;
    var n = 0;
    for (var i = 0; i < floor.walls.length; i++) {
      var w = floor.walls[i];
      if (w && w.start && w.end) n++;
    }
    return n;
  }

  /** Prefer vendor live floor (plan.floors[active]); fall back to plan.floor only when floors slot is missing. */
  function resolveLiveFloorSnapshot(plan) {
    if (!plan || typeof plan !== 'object') return null;
    var activeId = plan.activeFloorId || (plan.project && plan.project.activeFloorId);
    var planFloors = resolveActiveFloorEntry(plan.floors, activeId);
    if (planFloors.matchedById && planFloors.floor) {
      return planFloors.floor;
    }
    if (planFloors.floor && !planFloors.activeMissing) {
      return planFloors.floor;
    }
    var projectFloors = resolveActiveFloorEntry(
      plan.project && Array.isArray(plan.project.floors) ? plan.project.floors : null,
      activeId
    );
    if (projectFloors.matchedById && projectFloors.floor) {
      return projectFloors.floor;
    }
    if (projectFloors.floor && !projectFloors.activeMissing) {
      return projectFloors.floor;
    }
    if (plan.floor && typeof plan.floor === 'object') return plan.floor;
    if (Array.isArray(plan.walls)) return plan;
    return planFloors.floor || projectFloors.floor || null;
  }

  function resolveFloor(plan) {
    return resolveLiveFloorSnapshot(plan);
  }

  function emptyLayoutMetrics(wallHeightFt) {
    var h = normalizeWallHeight(wallHeightFt);
    return {
      area_sqft: 0.0,
      perimeter_lf: 0.0,
      baseboard_lf: 0.0,
      wall_height_ft: h,
      wall_area_sqft: 0.0,
      door_count: 0,
      window_count: 0,
      opening_count: 0,
      drywall_openings: 0,
      inside_corners: 0,
      outside_corners: 0,
      polygon_count: 0,
      wall_thickness_in: 0.0,
      inside_area_sqft: 0.0,
      outside_area_sqft: 0.0,
      shared_wall_lf: 0.0,
      framing_lf: 0.0,
      partition_lf: 0.0,
      cabinet_base_lf: 0.0,
      cabinet_upper_lf: 0.0,
      stairs_count: 0,
      stairs_treads: 0,
      stairs_run_lf: 0.0,
      stairs_quote_type: 'standard',
      ceiling_height_ft: h,
      ceiling_area_sqft: 0.0,
      net_wall_area_sqft: 0.0,
      walls: [],
      flooring_waste_percent: DEFAULT_WASTE_PERCENT,
      flooring_direction: 0,
      flooring_box_sqft: DEFAULT_BOX_SQFT,
      flooring_boxes: 0,
      flooring_with_waste_sqft: 0.0,
      casing_lf: 0.0
    };
  }

  function resolveLibraryMetrics(floor, cmPerUnit) {
    var baseIn = 0.0, upperIn = 0.0;
    var rawFurniture = (floor && floor.furniture) || [];
    for (var i = 0; i < rawFurniture.length; i++) {
      var f = rawFurniture[i];
      if (!f || typeof f !== 'object') continue;
      var cat = String(f.catalogId || f.sourceCategory || '').toLowerCase();
      var wIn = ((Number(f.width) || 36.0) * cmPerUnit) / CM_PER_INCH;
      var dIn = ((Number(f.depth) || 24.0) * cmPerUnit) / CM_PER_INCH;
      if (cat.indexOf('cabinet') !== -1) {
        if (cat.indexOf('upper') !== -1) {
          upperIn += Math.max(0.0, wIn);
        } else if (cat.indexOf('corner') !== -1) {
          baseIn += Math.max(0.0, wIn) + Math.max(0.0, dIn);
        } else {
          baseIn += Math.max(0.0, wIn);
        }
      }
    }

    var stairsCount = 0, treads = 0, runIn = 0.0, quoteType = 'standard';
    var rawStairs = (floor && floor.stairs) || [];
    for (var i = 0; i < rawStairs.length; i++) {
      var s = rawStairs[i];
      if (!s || typeof s !== 'object') continue;
      stairsCount++;
      var stType = String(s.stairType || 'straight').toLowerCase();
      var sDepthIn = ((Number(s.depth) || 300.0) * cmPerUnit) / CM_PER_INCH;
      runIn += Math.max(0.0, sDepthIn);
      var risers = Number(s.riserCount || 0);
      if (risers <= 0) {
        var baseR = Math.max(1, Math.round(sDepthIn / STAIR_TREAD_IN));
        risers = stType.indexOf('u') !== -1 ? baseR + 6 : (stType.indexOf('l') !== -1 ? baseR + 3 : baseR);
      }
      treads += risers;
      if (stType.indexOf('u') !== -1) {
        quoteType = 'double_landing';
      } else if (stType.indexOf('l') !== -1 && quoteType !== 'double_landing') {
        quoteType = 'single_landing';
      }
    }

    return {
      cabinet_base_lf: roundTo(baseIn / 12.0, 2),
      cabinet_upper_lf: roundTo(upperIn / 12.0, 2),
      stairs_count: stairsCount,
      stairs_treads: Math.max(0, treads),
      stairs_run_lf: roundTo(runIn / 12.0, 2),
      stairs_quote_type: quoteType
    };
  }


  function distToSegment(p, s, e) {
    var dx = e.x - s.x;
    var dy = e.y - s.y;
    var l2 = dx * dx + dy * dy;
    if (l2 < 1e-6) {
      return { dist: hypot(p.x - s.x, p.y - s.y), t: 0.0, proj: s };
    }
    var t = Math.max(0.0, Math.min(1.0, ((p.x - s.x) * dx + (p.y - s.y) * dy) / l2));
    var proj = { x: s.x + t * dx, y: s.y + t * dy };
    var dist = hypot(p.x - proj.x, p.y - proj.y);
    return { dist: dist, t: t, proj: proj };
  }

  function simplifyRing(pts) {
    var n = pts.length;
    if (n < 3) return pts;
    var out = [];
    for (var i = 0; i < n; i++) {
      var prev = pts[(i - 1 + n) % n];
      var curr = pts[i];
      var next = pts[(i + 1) % n];
      var dx1 = curr.x - prev.x;
      var dy1 = curr.y - prev.y;
      var dx2 = next.x - curr.x;
      var dy2 = next.y - curr.y;
      var len1 = hypot(dx1, dy1);
      var len2 = hypot(dx2, dy2);
      if (len1 > 1e-6 && len2 > 1e-6) {
        var cross = Math.abs(dx1 * dy2 - dy1 * dx2);
        var dot = dx1 * dx2 + dy1 * dy2;
        if ((cross / (len1 * len2)) < 0.02 && dot > 0) {
          continue;
        }
      }
      out.push(curr);
    }
    return out.length >= 3 ? out : pts;
  }

  function extractOuterLoopAndPartitions(wallsList, cmPerUnit, eps) {
    cmPerUnit = cmPerUnit || 1.0;
    if (eps == null || !isFinite(Number(eps))) {
      eps = partitionToleranceCm(wallsList, cmPerUnit);
    }
    var epsUnit = eps / (cmPerUnit > 0 ? cmPerUnit : 1.0);

    if (!wallsList || wallsList.length < 3) {
      var partIds = [];
      for (var i = 0; i < (wallsList ? wallsList.length : 0); i++) partIds.push(String(wallsList[i].id));
      return {
        outer_polygon: [],
        boundary_wall_ids: [],
        partition_wall_ids: partIds,
        partition_walls: wallsList ? wallsList.slice() : []
      };
    }

    // 1. Collect segments
    var rawSegments = [];
    for (var i = 0; i < wallsList.length; i++) {
      var w = wallsList[i];
      if (!w || !w.start || !w.end) continue;
      var start = snapPlanPoint(w.start, cmPerUnit);
      var end = snapPlanPoint(w.end, cmPerUnit);
      rawSegments.push({
        wallId: String(w.id != null ? w.id : 'w_' + i),
        start: start,
        end: end,
        wall: w,
        splitPoints: []
      });
    }

    if (rawSegments.length < 3) {
      return {
        outer_polygon: [],
        boundary_wall_ids: [],
        partition_wall_ids: rawSegments.map(function(s) { return s.wallId; }),
        partition_walls: rawSegments.map(function(s) { return s.wall; })
      };
    }

    clusterSegmentEndpoints(rawSegments, cmPerUnit, wallsList);

    var allEndpoints = [];
    for (var i = 0; i < rawSegments.length; i++) {
      allEndpoints.push(rawSegments[i].start);
      allEndpoints.push(rawSegments[i].end);
    }

    // 2. Detect T-junctions
    for (var i = 0; i < rawSegments.length; i++) {
      var s = rawSegments[i];
      var dx = s.end.x - s.start.x;
      var dy = s.end.y - s.start.y;
      var len = hypot(dx, dy);
      if (len < epsUnit) continue;

      for (var j = 0; j < allEndpoints.length; j++) {
        var pt = allEndpoints[j];
        if (hypot(pt.x - s.start.x, pt.y - s.start.y) < epsUnit) continue;
        if (hypot(pt.x - s.end.x, pt.y - s.end.y) < epsUnit) continue;

        var res = distToSegment(pt, s.start, s.end);
        if (res.dist < epsUnit && res.t * len > epsUnit && (1.0 - res.t) * len > epsUnit) {
          var already = false;
          for (var k = 0; k < s.splitPoints.length; k++) {
            if (hypot(s.splitPoints[k].point.x - res.proj.x, s.splitPoints[k].point.y - res.proj.y) < epsUnit) {
              already = true;
              break;
            }
          }
          if (!already) {
            s.splitPoints.push({ point: res.proj, t: res.t });
          }
        }
      }
    }

    // 3. Subdivide segments at split points
    var subSegments = [];
    for (var i = 0; i < rawSegments.length; i++) {
      var s = rawSegments[i];
      if (s.splitPoints.length === 0) {
        subSegments.push({
          wallId: s.wallId,
          start: s.start,
          end: s.end,
          wall: s.wall
        });
      } else {
        s.splitPoints.sort(function(a, b) { return a.t - b.t; });
        var cur = s.start;
        for (var k = 0; k < s.splitPoints.length; k++) {
          var sp = s.splitPoints[k];
          if (hypot(sp.point.x - cur.x, sp.point.y - cur.y) >= epsUnit) {
            subSegments.push({
              wallId: s.wallId,
              start: cur,
              end: sp.point,
              wall: s.wall
            });
            cur = sp.point;
          }
        }
        if (hypot(s.end.x - cur.x, s.end.y - cur.y) >= epsUnit) {
          subSegments.push({
            wallId: s.wallId,
            start: cur,
            end: s.end,
            wall: s.wall
          });
        }
      }
    }

    // 4. Cluster vertices into unique node IDs
    var nodes = [];
    var nodeClusterUnit = endpointClusterToleranceCm(wallsList, cmPerUnit) / (cmPerUnit > 0 ? cmPerUnit : 1.0);
    function findNode(pt) {
      var snapped = snapPlanPoint(pt, cmPerUnit);
      for (var idx = 0; idx < nodes.length; idx++) {
        if (hypot(nodes[idx].x - snapped.x, nodes[idx].y - snapped.y) < nodeClusterUnit) {
          return idx;
        }
      }
      var newId = nodes.length;
      nodes.push(snapped);
      return newId;
    }

    var edges = [];
    for (var i = 0; i < subSegments.length; i++) {
      var seg = subSegments[i];
      var u = findNode(seg.start);
      var v = findNode(seg.end);
      if (u !== v) {
        edges.push({
          u: u,
          v: v,
          wallId: seg.wallId,
          wall: seg.wall,
          start: nodes[u],
          end: nodes[v]
        });
      }
    }

    // 5. Degree-1 iterative pruning
    var activeEdges = edges.slice();
    var partitionWallIds = {};

    var pruning = true;
    while (pruning && activeEdges.length >= 3) {
      pruning = false;
      var degrees = new Array(nodes.length).fill(0);
      for (var i = 0; i < activeEdges.length; i++) {
        degrees[activeEdges[i].u]++;
        degrees[activeEdges[i].v]++;
      }

      var nextEdges = [];
      for (var i = 0; i < activeEdges.length; i++) {
        var e = activeEdges[i];
        if (degrees[e.u] < 2 || degrees[e.v] < 2) {
          partitionWallIds[e.wallId] = true;
          pruning = true;
        } else {
          nextEdges.push(e);
        }
      }
      activeEdges = nextEdges;
    }

    if (activeEdges.length < 3) {
      return {
        outer_polygon: [],
        boundary_wall_ids: [],
        partition_wall_ids: wallsList.map(function(w) { return String(w.id); }),
        partition_walls: wallsList.slice()
      };
    }

    // 6. Trace outer boundary cycle
    var adj = {};
    for (var i = 0; i < activeEdges.length; i++) {
      var e = activeEdges[i];
      if (!adj[e.u]) adj[e.u] = [];
      if (!adj[e.v]) adj[e.v] = [];
      adj[e.u].push({ to: e.v, edgeIdx: i });
      adj[e.v].push({ to: e.u, edgeIdx: i });
    }

    // Extreme vertex (min X, then min Y)
    var startNode = 0;
    var minX = Infinity;
    var minY = Infinity;
    for (var i = 0; i < activeEdges.length; i++) {
      var e = activeEdges[i];
      var uPt = nodes[e.u];
      var vPt = nodes[e.v];
      if (uPt.x < minX || (Math.abs(uPt.x - minX) < 1e-4 && uPt.y < minY)) {
        minX = uPt.x; minY = uPt.y; startNode = e.u;
      }
      if (vPt.x < minX || (Math.abs(vPt.x - minX) < 1e-4 && vPt.y < minY)) {
        minX = vPt.x; minY = vPt.y; startNode = e.v;
      }
    }

    var cycle = [];
    var cycleWallIds = [];
    var currNode = startNode;
    var prevAngle = -Math.PI / 2.0;
    var visitedEdges = {};

    var maxSteps = activeEdges.length * 2;
    for (var step = 0; step < maxSteps; step++) {
      cycle.push(nodes[currNode]);
      var neighbors = adj[currNode] || [];
      if (neighbors.length === 0) break;

      var bestNext = null;
      var bestDiff = Infinity;
      var bestEdgeIdx = null;

      for (var j = 0; j < neighbors.length; j++) {
        var nbr = neighbors[j];
        var toNode = nbr.to;
        var eIdx = nbr.edgeIdx;
        if ((visitedEdges[eIdx] || 0) >= 1) continue;

        var toPt = nodes[toNode];
        var edgeAngle = Math.atan2(toPt.y - nodes[currNode].y, toPt.x - nodes[currNode].x);

        var incoming = prevAngle + Math.PI;
        var diff = edgeAngle - incoming;
        while (diff <= 0) diff += 2 * Math.PI;
        while (diff > 2 * Math.PI) diff -= 2 * Math.PI;

        if (diff < bestDiff) {
          bestDiff = diff;
          bestNext = toNode;
          bestEdgeIdx = eIdx;
        }
      }

      if (bestNext === null) break;

      visitedEdges[bestEdgeIdx] = (visitedEdges[bestEdgeIdx] || 0) + 1;
      cycleWallIds.push(activeEdges[bestEdgeIdx].wallId);

      if (bestNext === startNode && cycle.length >= 3) break;

      prevAngle = Math.atan2(nodes[bestNext].y - nodes[currNode].y, nodes[bestNext].x - nodes[currNode].x);
      currNode = bestNext;
    }

    cycle = simplifyRing(cycle);

    var boundaryWallIds = [];
    var boundaryMap = {};
    for (var i = 0; i < cycleWallIds.length; i++) {
      var wid = cycleWallIds[i];
      if (!boundaryMap[wid]) {
        boundaryMap[wid] = true;
        boundaryWallIds.push(wid);
      }
    }

    var partitionWalls = [];
    for (var i = 0; i < wallsList.length; i++) {
      var w = wallsList[i];
      var wid = String(w.id);
      if (!boundaryMap[wid]) {
        partitionWalls.push(w);
        partitionWallIds[wid] = true;
      }
    }

    return {
      outer_polygon: cycle,
      boundary_wall_ids: boundaryWallIds,
      partition_wall_ids: Object.keys(partitionWallIds),
      partition_walls: partitionWalls
    };
  }

  function layoutMetricsUnmeasurable(plan, finish, options, wallHeightFt, message) {
    var unmeasurable = emptyLayoutMetrics(wallHeightFt || normalizeWallHeight(finish.wall_height_ft));
    unmeasurable.geometry_unmeasurable = true;
    unmeasurable.geometry_message = message || 'Room geometry could not be measured.';
    return applyZaniniExtensions(unmeasurable, plan, finish, options);
  }

  function getZaniniExtensions() {
    if (typeof OpenPlan3DZaniniExtensions !== 'undefined') {
      return OpenPlan3DZaniniExtensions;
    }
    if (typeof globalThis !== 'undefined' && globalThis.OpenPlan3DZaniniExtensions) {
      return globalThis.OpenPlan3DZaniniExtensions;
    }
    if (typeof require === 'function') {
      try {
        return require('./openplan3d-zanini-extensions.js');
      } catch (err) {
        return null;
      }
    }
    return null;
  }

  function applyZaniniExtensions(metrics, plan, finish, options) {
    var ext = getZaniniExtensions();
    if (ext && typeof ext.applyToMetrics === 'function') {
      return ext.applyToMetrics(metrics, plan, finish || {}, options || {});
    }
    if (ext && typeof ext.withDefaultFinishFields === 'function') {
      return ext.withDefaultFinishFields(metrics);
    }
    return metrics;
  }

  function layoutMetricsFloorShell(plan, finish, options) {
    var floor = resolveFloor(plan);
    if (!floor) return null;
    var clone = JSON.parse(JSON.stringify(plan));
    var cloneFloor = resolveFloor(clone);
    if (!cloneFloor) return null;
    var savedRooms = cloneFloor.rooms;
    cloneFloor.rooms = [];
  if (clone.floor) clone.floor.rooms = [];
    if (Array.isArray(clone.floors)) {
      for (var fi = 0; fi < clone.floors.length; fi++) {
        if (clone.floors[fi] && String(clone.floors[fi].id) === String(cloneFloor.id || clone.activeFloorId || '')) {
          clone.floors[fi].rooms = [];
        }
      }
    }
    cloneFloor.rooms = [];
    var shell = layoutMetrics(clone, null, finish, options);
    if (cloneFloor) cloneFloor.rooms = savedRooms;
    return shell;
  }

  function layoutMetrics(plan, roomId, finish, options) {
    var interiorGeom = interiorGeomRuntime();
    finish = finish || {};
    options = options || {};
    var floor = resolveFloor(plan);
    if (!floor) return emptyLayoutMetrics(finish.wall_height_ft);

    var unit = String(options.unit || plan.units || floor.units || 'cm').toLowerCase();
    var cmPerUnit = unit === 'm' || unit === 'meter' || unit === 'meters' ? 100.0 :
      (unit === 'in' || unit === 'inch' || unit === 'inches' ? CM_PER_INCH :
      (unit === 'ft' || unit === 'feet' || unit === 'foot' ? CM_PER_FOOT : 1.0));

    var walls = {};
    var rawWalls = floor.walls || [];
    for (var i = 0; i < rawWalls.length; i++) {
      var w = rawWalls[i];
      if (w && w.id && w.start && w.end) walls[String(w.id)] = w;
    }
    if (Object.keys(walls).length === 0) return emptyLayoutMetrics(finish.wall_height_ft);

    // Resolve rooms
    var rooms = floor.rooms || [];
    if (!roomId && rooms.length > 1) {
      var totalArea = 0.0, totalPerimeter = 0.0, totalBaseboard = 0.0, totalNetWall = 0.0;
      var totalDoors = 0, totalWindows = 0, totalOpenings = 0, totalDrywall = 0;
      var totalInCorners = 0, totalOutCorners = 0, totalPolyCount = rooms.length;
      var totalSharedWall = 0.0, totalFraming = 0.0, totalPartition = 0.0, totalOutsideArea = 0.0, totalCeilingArea = 0.0, totalCasing = 0.0;
      var allWalls = [];
      var firstSub = null;

      for (var rIdx = 0; rIdx < rooms.length; rIdx++) {
        var sub = layoutMetrics(plan, rooms[rIdx].id || rooms[rIdx].name, finish);
        if (!firstSub) firstSub = sub;
        totalArea += sub.area_sqft;
        totalPerimeter += sub.perimeter_lf;
        totalBaseboard += sub.baseboard_lf;
        totalNetWall += sub.net_wall_area_sqft;
        totalDoors += sub.door_count;
        totalWindows += sub.window_count;
        totalOpenings += sub.opening_count;
        totalDrywall += sub.drywall_openings;
        totalInCorners += sub.inside_corners;
        totalOutCorners += sub.outside_corners;
        totalSharedWall += sub.shared_wall_lf;
        totalFraming += sub.framing_lf;
        totalPartition += sub.partition_lf || 0;
        totalOutsideArea += sub.outside_area_sqft;
        totalCeilingArea += sub.ceiling_area_sqft;
        totalCasing += sub.casing_lf;
        for (var wIdx = 0; wIdx < sub.walls.length; wIdx++) {
          allWalls.push(sub.walls[wIdx]);
        }
      }

      firstSub = firstSub || emptyLayoutMetrics();
      var wastePct = firstSub.flooring_waste_percent;
      var boxSqFt = firstSub.flooring_box_sqft;
      var flBoxes = flooringBoxes(totalArea, wastePct, boxSqFt);
      var flWithWaste = flooringWithWasteSqFt(totalArea, wastePct);
      var lib = resolveLibraryMetrics(floor, cmPerUnit);
      var shell = layoutMetricsFloorShell(plan, finish, options) || firstSub || emptyLayoutMetrics();
      var shellWalls = shell.walls || [];

      return applyZaniniExtensions({
        area_sqft: roundTo(totalArea, 2),
        perimeter_lf: roundTo(shell.perimeter_lf != null ? shell.perimeter_lf : totalPerimeter, 2),
        baseboard_lf: roundTo(shell.baseboard_lf != null ? shell.baseboard_lf : totalBaseboard, 2),
        wall_height_ft: shell.wall_height_ft != null ? shell.wall_height_ft : firstSub.wall_height_ft,
        wall_area_sqft: roundTo(shell.wall_area_sqft != null ? shell.wall_area_sqft : totalNetWall, 2),
        door_count: totalDoors,
        window_count: totalWindows,
        opening_count: totalOpenings,
        drywall_openings: totalDrywall,
        inside_corners: totalInCorners,
        outside_corners: totalOutCorners,
        polygon_count: totalPolyCount,
        wall_thickness_in: shell.wall_thickness_in != null ? shell.wall_thickness_in : firstSub.wall_thickness_in,
        inside_area_sqft: roundTo(totalArea, 2),
        outside_area_sqft: roundTo(shell.outside_area_sqft != null ? shell.outside_area_sqft : totalOutsideArea, 2),
        shared_wall_lf: roundTo(shell.shared_wall_lf != null ? shell.shared_wall_lf : totalSharedWall, 2),
        framing_lf: roundTo(shell.framing_lf != null ? shell.framing_lf : totalFraming, 2),
        partition_lf: roundTo(shell.partition_lf != null ? shell.partition_lf : totalPartition, 2),
        cabinet_base_lf: lib.cabinet_base_lf,
        cabinet_upper_lf: lib.cabinet_upper_lf,
        stairs_count: lib.stairs_count,
        stairs_treads: lib.stairs_treads,
        stairs_run_lf: lib.stairs_run_lf,
        stairs_quote_type: lib.stairs_quote_type,
        ceiling_height_ft: firstSub.ceiling_height_ft,
        ceiling_area_sqft: roundTo(totalCeilingArea, 2),
        net_wall_area_sqft: roundTo(shell.net_wall_area_sqft != null ? shell.net_wall_area_sqft : totalNetWall, 2),
        walls: shellWalls,
        boundary_wall_ids: shell.boundary_wall_ids || [],
        flooring_waste_percent: wastePct,
        flooring_direction: firstSub.flooring_direction,
        flooring_box_sqft: boxSqFt,
        flooring_boxes: flBoxes,
        flooring_with_waste_sqft: flWithWaste,
        casing_lf: roundTo(shell.casing_lf != null ? shell.casing_lf : totalCasing, 2)
      }, plan, finish, options);
    }

    var selectedRoom = null;
    if (roomId) {
      for (var i = 0; i < rooms.length; i++) {
        if (rooms[i].id === roomId || rooms[i].name === roomId) {
          selectedRoom = rooms[i];
          break;
        }
      }
    }
    if (!selectedRoom && rooms.length > 0) selectedRoom = rooms[0];

    // Extract outer loop and partitions
    var candidateWalls = [];
    var roomWIds = (selectedRoom && selectedRoom.walls) || [];
    var isSingleRoom = rooms.length <= 1;
    if (isSingleRoom || roomWIds.length === 0) {
      candidateWalls = Object.values(walls);
    } else {
      var idMap = {};
      for (var ci = 0; ci < roomWIds.length; ci++) idMap[String(roomWIds[ci])] = true;
      for (var wid in walls) {
        if (idMap[wid]) candidateWalls.push(walls[wid]);
      }
      if (candidateWalls.length === 0) candidateWalls = Object.values(walls);
    }

    var loopData = extractOuterLoopAndPartitions(candidateWalls, cmPerUnit);
    var partitionWalls = loopData.partition_walls || [];
    var boundaryWallIds = loopData.boundary_wall_ids || [];
    var storedPoly = (selectedRoom && (selectedRoom.polygon || selectedRoom.vertices)) || [];
    var polyVertices = applyOuterLoopPolygon(storedPoly, loopData, cmPerUnit, roomWIds);
    if (polyVertices.length < 3) {
      var preHeight = normalizeWallHeight(finish.wall_height_ft || DEFAULT_WALL_HEIGHT_FT);
      return layoutMetricsUnmeasurable(plan, finish, options, preHeight,
        'Room geometry could not be measured.');
    }

    var allWallsList = Object.values(walls);
    var boundaryIds = (boundaryWallIds && boundaryWallIds.length)
      ? boundaryWallIds.slice()
      : ((selectedRoom && selectedRoom.boundary_wall_ids) || (selectedRoom && selectedRoom.walls) || []);
    var alreadyInterior = interiorGeom && interiorGeom.roomUsesInteriorPolygonDirectly(selectedRoom);
    var interiorRingCm = polyVertices;
    if (interiorGeom && !alreadyInterior) {
      interiorRingCm = interiorGeom.interiorPolygonCm(polyVertices, boundaryIds, walls, cmPerUnit);
      if (!interiorRingCm || interiorRingCm.length < 3) interiorRingCm = polyVertices;
    }

    var verticesFt = [];
    for (var i = 0; i < interiorRingCm.length; i++) {
      verticesFt.push({
        x: (interiorRingCm[i].x * cmPerUnit) / CM_PER_FOOT,
        y: (interiorRingCm[i].y * cmPerUnit) / CM_PER_FOOT
      });
    }

    // Heights
    var roomDetails = (selectedRoom && selectedRoom.details) || {};
    var rawWallHeight = finish.wall_height_ft || (selectedRoom && (selectedRoom.wall_height_ft || selectedRoom.wall_height || selectedRoom.height));
    if (rawWallHeight == null && Object.keys(walls).length > 0) {
      var firstW = walls[Object.keys(walls)[0]];
      if (firstW && firstW.height) rawWallHeight = (firstW.height * cmPerUnit) / CM_PER_FOOT;
    }
    var wallHeightFt = normalizeWallHeight(rawWallHeight || DEFAULT_WALL_HEIGHT_FT);

    var rawCeilingHeight = finish.ceiling_height_ft ||
      (roomDetails.ceilingHeight ? (roomDetails.ceilingHeight * cmPerUnit) / CM_PER_FOOT : null) ||
      wallHeightFt;
    var ceilingHeight = ceilingHeightFt(rawCeilingHeight, wallHeightFt);

    // Floor area in sq ft
    var insideAreaSqFt = roundTo(verticesToSquareFeet(verticesFt), 2);
    var polygonSimple = isSimplePolygon(verticesFt);
    if ((!polygonSimple || insideAreaSqFt <= 0) && !isCollapsedZeroFootprint(verticesFt)) {
      var unmeasurable = emptyLayoutMetrics(wallHeightFt);
      unmeasurable.geometry_unmeasurable = true;
      unmeasurable.geometry_message = 'Room shape crosses itself — adjust corners.';
      if (verticesFt.length >= 3) {
        var perimLf = 0.0;
        for (var pi = 0; pi < verticesFt.length; pi++) {
          var pj = (pi + 1) % verticesFt.length;
          perimLf += hypot(verticesFt[pj].x - verticesFt[pi].x, verticesFt[pj].y - verticesFt[pi].y);
        }
        unmeasurable.perimeter_lf = roundTo(perimLf, 2);
        unmeasurable.baseboard_lf = unmeasurable.perimeter_lf;
      }
      return applyZaniniExtensions(unmeasurable, plan, finish, options);
    }
    var ceilingAreaSqFt = insideAreaSqFt;

    // Corners
    var corners = cornerCounts(verticesFt);

    // Boundary walls
    var boundaryWalls = [];
    var bWallIds = (boundaryWallIds && boundaryWallIds.length > 0)
      ? boundaryWallIds
      : ((selectedRoom && selectedRoom.boundary_wall_ids) || (selectedRoom && selectedRoom.walls) || []);

    var trimBoundaryCorners = !alreadyInterior;
    var useRingEdges = interiorRingCm.length >= 3 && bWallIds.length === interiorRingCm.length;
    var distributedFt = null;
    if (interiorGeom && interiorRingCm.length >= 3 && bWallIds.length > 0) {
      distributedFt = interiorGeom.distributeBoundaryLengthsFt(
        polyVertices,
        interiorRingCm,
        bWallIds,
        walls,
        cmPerUnit
      );
    }
    if (bWallIds.length > 0) {
      for (var i = 0; i < bWallIds.length; i++) {
        var w = walls[String(bWallIds[i])];
        if (!w) continue;
        var lenFt;
        if (distributedFt && distributedFt[String(bWallIds[i])] != null) {
          lenFt = distributedFt[String(bWallIds[i])];
        } else if (useRingEdges) {
          var ringJ = (i + 1) % interiorRingCm.length;
          var ringDx = (interiorRingCm[ringJ].x - interiorRingCm[i].x) * cmPerUnit;
          var ringDy = (interiorRingCm[ringJ].y - interiorRingCm[i].y) * cmPerUnit;
          lenFt = hypot(ringDx, ringDy) / CM_PER_FOOT;
        } else {
          var lenCm = interiorGeom
            ? interiorGeom.wallPlanEdgeLengthCm(w, allWallsList, cmPerUnit, trimBoundaryCorners)
            : hypot((w.end.x - w.start.x) * cmPerUnit, (w.end.y - w.start.y) * cmPerUnit);
          lenFt = lenCm / CM_PER_FOOT;
        }
        boundaryWalls.push({
          id: String(w.id),
          length_ft: lenFt,
          thickness_cm: interiorGeom
            ? interiorGeom.wallThicknessCm(w.thickness, cmPerUnit)
            : (Number(w.thickness || 11.43)) * cmPerUnit
        });
      }
    } else {
      for (var i = 0; i < polyVertices.length; i++) {
        var j = (i + 1) % polyVertices.length;
        var dx = (polyVertices[j].x - polyVertices[i].x) * cmPerUnit;
        var dy = (polyVertices[j].y - polyVertices[i].y) * cmPerUnit;
        var lenCm = hypot(dx, dy);
        boundaryWalls.push({
          id: 'wall_' + (i + 1),
          length_ft: lenCm / CM_PER_FOOT,
          thickness_cm: 11.43
        });
      }
    }

    var perimeter = interiorGeom
      ? interiorGeom.planRingPerimeterLf(interiorRingCm, cmPerUnit)
      : 0.0;
    if (!perimeter) {
      for (var i = 0; i < boundaryWalls.length; i++) perimeter += boundaryWalls[i].length_ft;
    }
    perimeter = roundTo(perimeter, 2);

    // Openings on boundary walls and partition walls
    var wallIdMap = {};
    var boundaryIdMap = {};
    for (var i = 0; i < boundaryWalls.length; i++) {
      wallIdMap[boundaryWalls[i].id] = true;
      boundaryIdMap[boundaryWalls[i].id] = true;
    }
    for (var i = 0; i < partitionWalls.length; i++) {
      wallIdMap[String(partitionWalls[i].id)] = true;
    }

    var openings = [];
    var rawDoors = floor.doors || [];
    for (var i = 0; i < rawDoors.length; i++) {
      var d = rawDoors[i];
      if (!d || !wallIdMap[String(d.wallId)]) continue;
      var wObj = walls[String(d.wallId)];
      var wLenFt = 0;
      if (wObj) {
        for (var bwi = 0; bwi < boundaryWalls.length; bwi++) {
          if (String(boundaryWalls[bwi].id) === String(d.wallId)) {
            wLenFt = boundaryWalls[bwi].length_ft;
            break;
          }
        }
        if (!wLenFt) {
          var lenCm = interiorGeom
            ? interiorGeom.wallPlanEdgeLengthCm(wObj, allWallsList, cmPerUnit)
            : hypot((wObj.end.x - wObj.start.x) * cmPerUnit, (wObj.end.y - wObj.start.y) * cmPerUnit);
          wLenFt = lenCm / CM_PER_FOOT;
        }
      }
      var wLenIn = wLenFt * 12.0;
      var rawType = String(d.type || 'single').toLowerCase();
      var isOpening = rawType === 'opening' || rawType === 'archway';
      var wIn = ((d.width || 81.28) * cmPerUnit) / CM_PER_INCH;
      var hIn = d.height ? ((d.height * cmPerUnit) / CM_PER_INCH) : (isOpening ? DEFAULT_OPENING_HEIGHT_IN : DEFAULT_DOOR_HEIGHT_IN);
      var pos = d.position != null ? Number(d.position) : 0.5;
      var offIn = d.details && d.details.offset != null ? Number(d.details.offset) : (pos * wLenIn - wIn / 2.0);

      openings.push({
        id: String(d.id || 'door_' + i),
        wallId: String(d.wallId),
        type: isOpening ? 'opening' : 'door',
        width_in: Math.max(0, wIn),
        height_in: Math.max(0, hIn),
        offset_in: Math.max(0, offIn),
        sill_height_in: 0.0,
        details: d.details || {}
      });
    }

    var rawWindows = floor.windows || [];
    for (var i = 0; i < rawWindows.length; i++) {
      var w = rawWindows[i];
      if (!w || !wallIdMap[String(w.wallId)]) continue;
      var wObj = walls[String(w.wallId)];
      var wLenIn = wObj ? (hypot((wObj.end.x - wObj.start.x) * cmPerUnit, (wObj.end.y - wObj.start.y) * cmPerUnit) / CM_PER_INCH) : 0;
      var wIn = ((w.width || 91.44) * cmPerUnit) / CM_PER_INCH;
      var hIn = w.height ? ((w.height * cmPerUnit) / CM_PER_INCH) : DEFAULT_WINDOW_HEIGHT_IN;
      var sillIn = w.sillHeight != null ? ((w.sillHeight * cmPerUnit) / CM_PER_INCH) : (w.details && w.details.sill_height ? Number(w.details.sill_height) : 0);
      var pos = w.position != null ? Number(w.position) : 0.5;
      var offIn = w.details && w.details.offset != null ? Number(w.details.offset) : (pos * wLenIn - wIn / 2.0);

      openings.push({
        id: String(w.id || 'win_' + i),
        wallId: String(w.wallId),
        type: 'window',
        width_in: Math.max(0, wIn),
        height_in: Math.max(0, hIn),
        offset_in: Math.max(0, offIn),
        sill_height_in: Math.max(0, sillIn),
        details: w.details || {}
      });
    }

    var doorCount = 0, windowCount = 0, openingCount = 0;
    var baseboardDedIn = 0.0, casingTotalLf = 0.0;
    var openingsByWall = {};

    for (var i = 0; i < openings.length; i++) {
      var op = openings[i];
      if (!openingsByWall[op.wallId]) openingsByWall[op.wallId] = [];
      openingsByWall[op.wallId].push(op);

      var isBoundary = !!boundaryIdMap[op.wallId];
      if (op.type === 'door') {
        doorCount++;
        if (isBoundary) baseboardDedIn += op.width_in;
        var sides = op.details.casing_sides != null ? Number(op.details.casing_sides) :
          (String(op.details.door_face).toLowerCase() === 'exterior' ? 1 : 2);
        casingTotalLf += casingLf(op.width_in, op.height_in, sides, 'door');
      } else if (op.type === 'window') {
        windowCount++;
        var sides = op.details.casing_sides != null ? Number(op.details.casing_sides) : 4;
        casingTotalLf += casingLf(op.width_in, op.height_in, sides, 'window');
      } else {
        openingCount++;
        if (isBoundary) baseboardDedIn += op.width_in;
        var sides = op.details.casing_sides != null ? Number(op.details.casing_sides) : 2;
        casingTotalLf += casingLf(op.width_in, op.height_in, sides, 'opening');
      }
    }

    var baseboardDedLf = roundTo(baseboardDedIn / 12.0, 2);
    var junctionDedup = {};
    var buttByBoundaryId = {};
    var totalInteriorButtLf = 0.0;
    for (var bi = 0; bi < boundaryWalls.length; bi++) {
      var bWallObj = walls[String(boundaryWalls[bi].id)];
      if (!bWallObj) continue;
      var bid = String(boundaryWalls[bi].id);
      var bDed = interiorButtDeductionLfOnBoundaryWall(
        bWallObj,
        allWallsList,
        boundaryIdMap,
        cmPerUnit,
        junctionDedup
      );
      buttByBoundaryId[bid] = bDed;
      totalInteriorButtLf += bDed;
    }
    var baseboardLf = Math.max(0.0, roundTo(perimeter - baseboardDedLf - totalInteriorButtLf, 2));
    var casingTotalLfRounded = roundTo(casingTotalLf, 2);

    // Per wall net areas
    var perWallNetAreas = [];
    var totalNetWallArea = 0.0;
    var wallIdx = 1;

    for (var i = 0; i < boundaryWalls.length; i++) {
      var bw = boundaryWalls[i];
      var wLenFt = bw.length_ft;
      var bObj = walls[String(bw.id)];
      var buttLf = bObj ? (buttByBoundaryId[String(bw.id)] || 0) : 0;
      var finishLenFt = Math.max(0, wLenFt - buttLf);
      var wLenIn = finishLenFt * 12.0;
      var wOpenings = openingsByWall[bw.id] || [];

      var rects = [];
      for (var j = 0; j < wOpenings.length; j++) {
        var op = wOpenings[j];
        var off = Math.max(0, Math.min(Math.max(0, wLenIn - op.width_in), op.offset_in));
        var y0 = op.sill_height_in;
        var y1 = Math.min(ceilingHeight * 12.0, y0 + op.height_in);
        if (y1 > y0 && op.width_in > 0) {
          rects.push({ x0: off, x1: off + op.width_in, y0: y0, y1: y1 });
        }
      }

      var dedRaw = unionOpeningAreaRawSqFt(rects);
      var netAreaExact = Math.max(0.0, finishLenFt * ceilingHeight - Math.max(0.0, dedRaw || 0.0));
      var netArea = roundTo(netAreaExact, 2);
      totalNetWallArea += netAreaExact;

      perWallNetAreas.push({
        id: bw.id,
        index: wallIdx,
        label: 'W' + wallIdx,
        length_lf: roundTo(wLenFt, 2),
        length_lf_exact: wLenFt,
        interior_butt_deduction_lf: roundTo(buttLf, 2),
        finish_run_lf: finishLenFt,
        opening_area_sqft: roundTo(dedRaw, 2),
        net_area_sqft: netArea,
        net_area_sqft_exact: netAreaExact
      });
      wallIdx++;
    }

    // Partition walls (interior): count both faces (2x)
    var partitionLf = 0.0;
    var partitionJunctionDedup = {};
    for (var i = 0; i < partitionWalls.length; i++) {
      var pw = partitionWalls[i];
      var pwId = String(pw.id);
      var lenCm = interiorGeom
        ? interiorGeom.wallPlanEdgeLengthCm(pw, allWallsList, cmPerUnit)
        : hypot((pw.end.x - pw.start.x) * cmPerUnit, (pw.end.y - pw.start.y) * cmPerUnit);
      var wLenFt = lenCm / CM_PER_FOOT;
      var partButt = interiorButtDeductionLfOnInteriorWall(pw, allWallsList, boundaryIdMap, cmPerUnit, partitionJunctionDedup);
      var finishPartLenFt = Math.max(0, wLenFt - partButt);
      partitionLf += wLenFt;
      var wLenIn = wLenFt * 12.0;
      var wOpenings = openingsByWall[pwId] || [];

      var rects = [];
      for (var j = 0; j < wOpenings.length; j++) {
        var op = wOpenings[j];
        var off = Math.max(0, Math.min(Math.max(0, wLenIn - op.width_in), op.offset_in));
        var y0 = op.sill_height_in;
        var y1 = Math.min(ceilingHeight * 12.0, y0 + op.height_in);
        if (y1 > y0 && op.width_in > 0) {
          rects.push({ x0: off, x1: off + op.width_in, y0: y0, y1: y1 });
        }
      }

      var dedRaw = unionOpeningAreaRawSqFt(rects);
      var netAreaSingle = Math.max(0.0, wLenFt * ceilingHeight - Math.max(0.0, dedRaw || 0.0));
      var buttOverlapSqFt = partButt > 0 ? partButt * ceilingHeight : 0;
      var netAreaExact = Math.max(0, netAreaSingle * 2.0 - buttOverlapSqFt);
      var netArea = roundTo(netAreaExact, 2);
      totalNetWallArea += netAreaExact;

      perWallNetAreas.push({
        id: pwId,
        index: wallIdx,
        label: 'W' + wallIdx,
        length_lf: roundTo(wLenFt, 2),
        length_lf_exact: wLenFt,
        interior_butt_deduction_lf: roundTo(partButt, 2),
        finish_run_lf: finishPartLenFt,
        opening_area_sqft: roundTo(dedRaw * 2.0, 2),
        net_area_sqft: netArea,
        net_area_sqft_exact: netAreaExact
      });
      wallIdx++;
    }
    totalNetWallArea = roundTo(totalNetWallArea, 2);

    // Flooring
    var waste = normalizeWastePercent(finish.flooring_waste_percent || roomDetails.flooring_waste_percent);
    var direction = normalizeDirection(finish.flooring_direction || roomDetails.flooring_direction);
    var box = normalizeBoxSqFt(finish.flooring_box_sqft || roomDetails.flooring_box_sqft);
    var boxes = flooringBoxes(insideAreaSqFt, waste, box);
    var flooringWasteSqFt = flooringWithWasteSqFt(insideAreaSqFt, waste);

    // Thickness & outside area
    var firstBw = boundaryWalls[0] || {};
    var thicknessIn = firstBw.thickness_cm ? roundTo(firstBw.thickness_cm / CM_PER_INCH, 2) :
      (finish.wall_thickness_in ? roundTo(Number(finish.wall_thickness_in), 2) : DEFAULT_WALL_THICKNESS_IN);
    var thicknessFt = thicknessIn / 12.0;

    // Shared walls
    var sharedLf = 0.0;
    if (floor.rooms && floor.rooms.length > 1 && selectedRoom && selectedRoom.walls) {
      var selIds = {};
      for (var i = 0; i < selectedRoom.walls.length; i++) selIds[String(selectedRoom.walls[i])] = true;
      var sharedIds = {};
      for (var i = 0; i < floor.rooms.length; i++) {
        var r = floor.rooms[i];
        if (!r || r.id === selectedRoom.id) continue;
        for (var j = 0; j < (r.walls || []).length; j++) {
          if (selIds[String(r.walls[j])]) sharedIds[String(r.walls[j])] = true;
        }
      }
      for (var sId in sharedIds) {
        var sw = walls[sId];
        if (sw) {
          var sharedLenCm = interiorGeom
            ? interiorGeom.wallPlanEdgeLengthCm(sw, allWallsList, cmPerUnit)
            : hypot((sw.end.x - sw.start.x) * cmPerUnit, (sw.end.y - sw.start.y) * cmPerUnit);
          sharedLf += sharedLenCm / CM_PER_FOOT;
        }
      }
      sharedLf = roundTo(sharedLf, 2);
    }
    var framingLf = Math.max(0.0, roundTo(perimeter - sharedLf, 2));
  var outsideRingVerts = alreadyInterior ? interiorRingCm : polyVertices;
    var outsideAreaSqFt = interiorGeom
      ? roundTo(
        interiorGeom.exteriorFloorAreaSqFt(outsideRingVerts, boundaryIds, walls, cmPerUnit, alreadyInterior),
        2
      )
      : roundTo(Math.max(insideAreaSqFt, insideAreaSqFt + (Math.max(0, perimeter - 2 * sharedLf) * thicknessFt)), 2);

    // Furniture / Cabinets / Stairs
    var lib = resolveLibraryMetrics(floor, cmPerUnit);

    return applyZaniniExtensions({
      area_sqft: insideAreaSqFt,
      perimeter_lf: perimeter,
      baseboard_lf: baseboardLf,
      wall_height_ft: wallHeightFt,
      wall_area_sqft: totalNetWallArea,
      door_count: doorCount,
      window_count: windowCount,
      opening_count: openingCount,
      drywall_openings: doorCount + windowCount + openingCount,
      inside_corners: corners.inside,
      outside_corners: corners.outside,
      polygon_count: 1,
      wall_thickness_in: thicknessIn,
      inside_area_sqft: insideAreaSqFt,
      outside_area_sqft: outsideAreaSqFt,
      shared_wall_lf: sharedLf,
      framing_lf: framingLf,
      partition_lf: roundTo(partitionLf, 2),
      cabinet_base_lf: lib.cabinet_base_lf,
      cabinet_upper_lf: lib.cabinet_upper_lf,
      stairs_count: lib.stairs_count,
      stairs_treads: lib.stairs_treads,
      stairs_run_lf: lib.stairs_run_lf,
      stairs_quote_type: lib.stairs_quote_type,
      ceiling_height_ft: ceilingHeight,
      ceiling_area_sqft: ceilingAreaSqFt,
      net_wall_area_sqft: totalNetWallArea,
      walls: perWallNetAreas,
      room_polygon_cm: polyVertices.map(function (pt) {
        return { x: Number(pt.x || 0), y: Number(pt.y || 0) };
      }),
      boundary_wall_ids: boundaryWallIds,
      flooring_waste_percent: waste,
      flooring_direction: direction,
      flooring_box_sqft: box,
      flooring_boxes: boxes,
      flooring_with_waste_sqft: flooringWasteSqFt,
      casing_lf: casingTotalLfRounded
    }, plan, finish, options);
  }

  function wallIdListKey(ids) {
    if (!Array.isArray(ids)) return '';
    return ids.slice().sort().join('|');
  }

  function drawableWallIdSet(walls) {
    var out = {};
    if (!Array.isArray(walls)) return out;
    for (var i = 0; i < walls.length; i++) {
      if (walls[i] && walls[i].id) out[String(walls[i].id)] = true;
    }
    return out;
  }

  function pointNearRing(pt, ring, epsUnit) {
    if (!pt || !ring || ring.length < 2) return false;
    for (var i = 0; i < ring.length; i++) {
      var a = ring[i];
      var b = ring[(i + 1) % ring.length];
      var res = distToSegment(pt, a, b);
      if (res.dist < epsUnit) return true;
    }
    return false;
  }

  function classifyPartitionWalls(partitionWalls, outerRing, cmPerUnit, wallsList) {
    var epsUnit = partitionToleranceCm(wallsList, cmPerUnit) / (cmPerUnit > 0 ? cmPerUnit : 1.0);
    var dangling = [];
    var spanning = [];
    for (var i = 0; i < (partitionWalls || []).length; i++) {
      var w = partitionWalls[i];
      if (!w || !w.start || !w.end) {
        if (w) dangling.push(w);
        continue;
      }
      var s = snapPlanPoint(w.start, cmPerUnit);
      var e = snapPlanPoint(w.end, cmPerUnit);
      if (pointNearRing(s, outerRing, epsUnit) && pointNearRing(e, outerRing, epsUnit)) {
        spanning.push(w);
      } else {
        dangling.push(w);
      }
    }
    return { dangling: dangling, spanning: spanning, epsUnit: epsUnit };
  }

  function pointInPolygon(pt, polygon, epsUnit) {
    if (!polygon || polygon.length < 3) return false;
    var x = pt.x;
    var y = pt.y;
    var inside = false;
    for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
      var xi = polygon[i].x;
      var yi = polygon[i].y;
      var xj = polygon[j].x;
      var yj = polygon[j].y;
      if (Math.abs(x - xi) < epsUnit && Math.abs(y - yi) < epsUnit) return true;
      var intersect =
        yi > y !== yj > y &&
        x < ((xj - xi) * (y - yi)) / (yj - yi + 1e-12) + xi;
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function segmentsOverlapNear(a0, a1, b0, b1, epsUnit) {
    var res = distToSegment(a0, b0, b1);
    if (res.dist < epsUnit) return true;
    res = distToSegment(a1, b0, b1);
    if (res.dist < epsUnit) return true;
    res = distToSegment(b0, a0, a1);
    if (res.dist < epsUnit) return true;
    res = distToSegment(b1, a0, a1);
    return res.dist < epsUnit;
  }

  function wallContributesToRoomPolygon(w, poly, cmPerUnit, epsUnit) {
    if (!w || !w.start || !w.end || !poly || poly.length < 3) return false;
    var s = snapPlanPoint(w.start, cmPerUnit);
    var e = snapPlanPoint(w.end, cmPerUnit);
    if (pointNearRing(s, poly, epsUnit) || pointNearRing(e, poly, epsUnit)) return true;
    for (var i = 0; i < poly.length; i++) {
      var a = poly[i];
      var b = poly[(i + 1) % poly.length];
      if (segmentsOverlapNear(s, e, a, b, epsUnit)) return true;
    }
    return false;
  }

  function wallsForRoomPolygon(poly, wallsList, cmPerUnit, epsUnit) {
    var ids = [];
    var seen = {};
    for (var i = 0; i < wallsList.length; i++) {
      var w = wallsList[i];
      if (!w || !w.id) continue;
      if (!wallContributesToRoomPolygon(w, poly, cmPerUnit, epsUnit)) continue;
      var wid = String(w.id);
      if (seen[wid]) continue;
      seen[wid] = true;
      ids.push(wid);
    }
    return ids;
  }

  function locateOnRing(pt, ring, epsUnit) {
    for (var i = 0; i < ring.length; i++) {
      var a = ring[i];
      var b = ring[(i + 1) % ring.length];
      var res = distToSegment(pt, a, b);
      if (res.dist < epsUnit && res.t >= -1e-4 && res.t <= 1 + 1e-4) {
        return { edgeIndex: i, t: res.t, point: snapPlanPoint(res.proj, 1) };
      }
    }
    return null;
  }

  function appendRingChain(out, ring, fromIdx, toIdx, fromT, toT, fromPt, toPt) {
    out.push({ x: fromPt.x, y: fromPt.y });
    var idx = fromIdx;
    var guard = 0;
    while (guard++ < ring.length + 2) {
      idx = (idx + 1) % ring.length;
      if (idx === (toIdx + 1) % ring.length) break;
      out.push({ x: ring[idx].x, y: ring[idx].y });
    }
    out.push({ x: toPt.x, y: toPt.y });
    return simplifyRing(out);
  }

  function trySplitPolygonBySpanningWall(outer, wall, cmPerUnit, epsUnit) {
    if (!outer || outer.length < 3 || !wall) return null;
    var s = snapPlanPoint(wall.start, cmPerUnit);
    var e = snapPlanPoint(wall.end, cmPerUnit);
    var locA = locateOnRing(s, outer, epsUnit);
    var locB = locateOnRing(e, outer, epsUnit);
    if (!locA || !locB) return null;
    if (locA.edgeIndex === locB.edgeIndex && Math.abs(locA.t - locB.t) < 1e-3) return null;

    var chain1 = [];
    appendRingChain(chain1, outer, locA.edgeIndex, locB.edgeIndex, locA.t, locB.t, locA.point, locB.point);
    chain1.push({ x: locA.point.x, y: locA.point.y });

    var chain2 = [];
    appendRingChain(chain2, outer, locB.edgeIndex, locA.edgeIndex, locB.t, locA.t, locB.point, locA.point);
    chain2.push({ x: locB.point.x, y: locB.point.y });

    var a1 = polygonAreaSqFt(chain1, cmPerUnit);
    var a2 = polygonAreaSqFt(chain2, cmPerUnit);
    if (a1 < 0.5 || a2 < 0.5) return null;
    return [chain1, chain2];
  }

  function splitRegionsBySpanningWalls(outer, spanningWalls, cmPerUnit, epsUnit) {
    var regions = [outer.slice()];
    for (var si = 0; si < spanningWalls.length; si++) {
      var wall = spanningWalls[si];
      var next = [];
      for (var ri = 0; ri < regions.length; ri++) {
        var split = trySplitPolygonBySpanningWall(regions[ri], wall, cmPerUnit, epsUnit);
        if (split) next.push(split[0], split[1]);
        else next.push(regions[ri]);
      }
      regions = next;
    }
    return regions.filter(function (poly) {
      return poly && poly.length >= 3 && outerLoopHasUsableArea(poly, cmPerUnit);
    });
  }

  function roomsMatchPersisted(existing, nextRooms, cmPerUnit) {
    if (!Array.isArray(existing) || !Array.isArray(nextRooms)) return false;
    if (existing.length !== nextRooms.length) return false;
    for (var i = 0; i < nextRooms.length; i++) {
      var a = existing[i];
      var b = nextRooms[i];
      if (!a || !b) return false;
      if (wallIdListKey(a.walls) !== wallIdListKey(b.walls)) return false;
      var pa = a.polygon || a.vertices || [];
      var pb = b.polygon || b.vertices || [];
      if (pa.length !== pb.length) return false;
      if (Math.abs(polygonAreaSqFt(pa, cmPerUnit) - polygonAreaSqFt(pb, cmPerUnit)) > 0.75) return false;
    }
    return true;
  }

  function buildRoomsFromRegions(regions, wallsList, cmPerUnit, epsUnit, floorCtx) {
    var sorted = regions.slice().sort(function (a, b) {
      return polygonAreaSqFt(b, cmPerUnit) - polygonAreaSqFt(a, cmPerUnit);
    });
    var rooms = [];
    for (var i = 0; i < sorted.length; i++) {
      var poly = sorted[i];
      var wallIds = wallsForRoomPolygon(poly, wallsList, cmPerUnit, epsUnit);
      if (wallIds.length < 3) continue;
      rooms.push(
        enrichVendorRendererRoom(
          {
            id: 'room_' + (i + 1),
            name: 'Room ' + (i + 1),
            walls: wallIds
          },
          poly,
          wallIds,
          floorCtx || { walls: wallsList }
        )
      );
    }
    return rooms;
  }

  function polygonSignedAreaPlanUnits(polygon) {
    if (!polygon || polygon.length < 3) return 0;
    var t = 0;
    for (var n = 0; n < polygon.length; n++) {
      var o = (n + 1) % polygon.length;
      t += polygon[n].x * polygon[o].y - polygon[o].x * polygon[n].y;
    }
    return t / 2;
  }

  /** Vendor Oo() stores room.area as m² from plan coordinates (cm). */
  function vendorAreaM2FromPolygon(polygon) {
    return Math.round(Math.abs(polygonSignedAreaPlanUnits(polygon)) / 100) / 100;
  }

  /**
   * Room objects consumed by vendor detectedRooms / n3() must include finite area,
   * floorTexture, and walls[] covering every wall id that should stay visible.
   */
  function enrichVendorRendererRoom(room, polygon, drawableWallIds, floorCtx) {
    var poly = polygon && polygon.length >= 3 ? polygon : room.polygon || [];
    var walls =
      Array.isArray(drawableWallIds) && drawableWallIds.length
        ? drawableWallIds.slice()
        : room.walls && room.walls.length
          ? room.walls.slice()
          : [];
    var boundaryIds = room.boundary_wall_ids || walls;
    var area =
      typeof room.area === 'number' && isFinite(room.area)
        ? room.area
        : vendorAreaM2FromPolygon(poly);
    var interiorGeom = interiorGeomRuntime();
    if (interiorGeom && floorCtx && poly.length >= 3) {
      var wallsById = {};
      var wallList = floorCtx.walls || [];
      for (var wi = 0; wi < wallList.length; wi++) {
        var ww = wallList[wi];
        if (ww && ww.id) wallsById[String(ww.id)] = ww;
      }
      var unit = String(floorCtx.units || floorCtx.unit || 'cm').toLowerCase();
      var cmPerUnit = cmPerUnitForPlan({ unit: unit, units: unit, floor: floorCtx });
      var sqFt = interiorGeom.interiorFloorAreaSqFt(
        poly,
        boundaryIds,
        wallsById,
        wallList,
        cmPerUnit,
        false
      );
      if (sqFt > 0) {
        var sqFtRounded = Math.round(sqFt * 100) / 100;
        area = sqFtRounded / 10.7639;
      }
    }
    return {
      id: room.id,
      name: room.name || 'Room 1',
      walls: walls,
      floorTexture: room.floorTexture || 'hardwood',
      area: area,
      polygon: poly.slice()
    };
  }

  function purgeStaleRooms(floor, wallIdSet, cmPerUnit, loopValid) {
    if (!floor || !Array.isArray(floor.rooms)) return false;
    if (!loopValid) {
      if (floor.rooms.length === 0) return false;
      floor.rooms = [];
      return true;
    }
    var kept = [];
    var changed = false;
    for (var i = 0; i < floor.rooms.length; i++) {
      var room = floor.rooms[i];
      if (!room || !Array.isArray(room.walls) || room.walls.length === 0) {
        changed = true;
        continue;
      }
      var bad = false;
      for (var w = 0; w < room.walls.length; w++) {
        if (!wallIdSet[room.walls[w]]) {
          bad = true;
          break;
        }
      }
      if (bad) {
        changed = true;
        continue;
      }
      var poly = room.polygon || room.vertices || [];
      if (poly.length >= 3 && !outerLoopHasUsableArea(poly, cmPerUnit)) {
        changed = true;
        continue;
      }
      kept.push(room);
    }
    if (kept.length !== floor.rooms.length) {
      floor.rooms = kept;
      changed = true;
    }
    return changed;
  }

  function applyFloorSnapshotToPlan(out, snap, activeId) {
    if (out.floor && typeof out.floor === 'object') out.floor = snap;
    if (Array.isArray(out.floors)) {
      for (var fi = 0; fi < out.floors.length; fi++) {
        if (!out.floors[fi]) continue;
        if (activeId && out.floors[fi].id && out.floors[fi].id !== activeId) continue;
        if (!activeId && fi > 0) continue;
        out.floors[fi] = JSON.parse(JSON.stringify(snap));
        break;
      }
    }
  }

  /** Wall x/y are already in plan units (cm when unit is cm); only ft/m/mm need scaling to cm. */
  function cmPerUnitForPlan(plan) {
    if (!plan || typeof plan !== 'object') return 1.0;
    var floor = plan.floor || (plan.floors && plan.floors[0]) || plan;
    var unit = String(plan.unit || plan.units || floor.unit || floor.units || 'cm').toLowerCase();
    if (unit === 'ft' || unit === 'feet' || unit === 'foot') return CM_PER_FOOT;
    if (unit === 'm' || unit === 'meter' || unit === 'meters') return 100;
    if (unit === 'mm' || unit === 'millimeter' || unit === 'millimeters') return 0.1;
    if (unit === 'in' || unit === 'inch' || unit === 'inches') return CM_PER_INCH;
    return 1.0;
  }

  function floorEligibleForCanvasFillSync(floor, activeId) {
    if (!floor || typeof floor !== 'object') return false;
    if (activeId && floor.id && floor.id !== activeId) return false;
    return true;
  }

  /**
   * Canvas fill uses persisted room polygons. Vendor auto-detect leaves rooms[] empty
   * when dangling stubs break Bo(), but fully spanning partitions need real multi-room
   * topology — not one synthetic outer room.
   */
  function syncEditorRoomBoundariesForCanvasFill(plan) {
    if (!plan || typeof plan !== 'object') return { plan: plan, changed: false };
    var activeId = plan.activeFloorId || (plan.project && plan.project.activeFloorId) || null;
    var cmPerUnit = cmPerUnitForPlan(plan);
    var live = resolveFloor(plan);
    if (!live || !floorEligibleForCanvasFillSync(live, activeId)) {
      return { plan: plan, changed: false };
    }

    var drawable = [];
    for (var wi = 0; wi < (live.walls || []).length; wi++) {
      var w = live.walls[wi];
      if (w && w.id && w.start && w.end) drawable.push(w);
    }
    var wallIdSet = drawableWallIdSet(drawable);

    if (drawable.length < 4) {
      if (!Array.isArray(live.rooms) || live.rooms.length === 0) return { plan: plan, changed: false };
      var outEmpty = JSON.parse(JSON.stringify(plan));
      var floorEmpty = resolveFloor(outEmpty);
      floorEmpty.rooms = [];
      applyFloorSnapshotToPlan(outEmpty, JSON.parse(JSON.stringify(floorEmpty)), activeId);
      return { plan: outEmpty, changed: true };
    }

    var loopData = extractOuterLoopAndPartitions(drawable, cmPerUnit);
    var boundary = loopData.boundary_wall_ids || [];
    var outer = loopData.outer_polygon || [];
    var loopValid = boundary.length >= 3 && outerLoopHasUsableArea(outer, cmPerUnit);

    if (!loopValid) {
      var outOpen = JSON.parse(JSON.stringify(plan));
      var floorOpen = resolveFloor(outOpen);
      var purged = purgeStaleRooms(floorOpen, wallIdSet, cmPerUnit, false);
      if (!purged) return { plan: plan, changed: false };
      applyFloorSnapshotToPlan(outOpen, JSON.parse(JSON.stringify(floorOpen)), activeId);
      return { plan: outOpen, changed: true };
    }

    var roomCount = Array.isArray(live.rooms) ? live.rooms.length : 0;
    if (roomCount >= 1) {
      var outPersisted = JSON.parse(JSON.stringify(plan));
      var floorPersisted = resolveFloor(outPersisted);
      var touchedPersisted = purgeStaleRooms(floorPersisted, wallIdSet, cmPerUnit, true);
      if (!touchedPersisted) return { plan: plan, changed: false };
      applyFloorSnapshotToPlan(outPersisted, JSON.parse(JSON.stringify(floorPersisted)), activeId);
      return { plan: outPersisted, changed: true };
    }

    var parts = classifyPartitionWalls(loopData.partition_walls, outer, cmPerUnit, drawable);
    var dangling = parts.dangling;
    var spanning = parts.spanning;
    var epsUnit = parts.epsUnit;

    if (spanning.length > 0) {
      var regions = splitRegionsBySpanningWalls(outer, spanning, cmPerUnit, epsUnit);
      if (regions.length >= 2) {
        var nextRooms = buildRoomsFromRegions(regions, drawable, cmPerUnit, epsUnit, live);
        if (nextRooms.length >= 2) {
          if (roomsMatchPersisted(live.rooms, nextRooms, cmPerUnit)) {
            return { plan: plan, changed: false };
          }
          var outSplit = JSON.parse(JSON.stringify(plan));
          var floorSplit = resolveFloor(outSplit);
          floorSplit.rooms = nextRooms;
          applyFloorSnapshotToPlan(outSplit, JSON.parse(JSON.stringify(floorSplit)), activeId);
          return { plan: outSplit, changed: true };
        }
      }
      return { plan: plan, changed: false };
    }

    var nextPoly = applyOuterLoopPolygon([], loopData, cmPerUnit, boundary);
    if (nextPoly.length < 3) nextPoly = outer.slice();
    if (nextPoly.length < 3) return { plan: plan, changed: false };

    var out = JSON.parse(JSON.stringify(plan));
    var target = resolveFloor(out);
    if (!target) return { plan: plan, changed: false };
    target.rooms = [
      enrichVendorRendererRoom(
        { id: 'room_1', name: 'Room 1', walls: drawable.map(function (w) { return w.id; }) },
        nextPoly,
        drawable.map(function (w) { return w.id; }),
        target
      )
    ];

    applyFloorSnapshotToPlan(out, JSON.parse(JSON.stringify(target)), activeId);
    return { plan: out, changed: true };
  }

  /**
   * Read-only room polygons for vendor va() when Ro/Bo leave gaps (dangling stubs, live edit).
   * Never mutates the plan; does not run when floor.rooms already has persisted entries.
   */
  function canvasFillRoomEntries(plan) {
    if (!plan || typeof plan !== 'object') return [];
    var live = resolveFloor(plan);
    if (!live) return [];
    var roomCount = Array.isArray(live.rooms) ? live.rooms.length : 0;
    if (roomCount >= 1) return [];

    var cmPerUnit = cmPerUnitForPlan(plan);
    var drawable = [];
    for (var wi = 0; wi < (live.walls || []).length; wi++) {
      var w = live.walls[wi];
      if (w && w.id && w.start && w.end) drawable.push(w);
    }
    if (drawable.length < 4) return [];

    var loopData = extractOuterLoopAndPartitions(drawable, cmPerUnit);
    var boundary = loopData.boundary_wall_ids || [];
    var outer = loopData.outer_polygon || [];
    if (boundary.length < 3 || !outerLoopHasUsableArea(outer, cmPerUnit)) return [];

    var parts = classifyPartitionWalls(loopData.partition_walls, outer, cmPerUnit, drawable);
    var spanning = parts.spanning;
    var epsUnit = parts.epsUnit;

    if (spanning.length > 0) {
      var regions = splitRegionsBySpanningWalls(outer, spanning, cmPerUnit, epsUnit);
      if (regions.length >= 2) {
        var multi = buildRoomsFromRegions(regions, drawable, cmPerUnit, epsUnit, live);
        if (multi.length >= 2) {
          return multi.map(function (room) {
            return { room: room, polygon: room.polygon.slice() };
          });
        }
      }
    }

    var nextPoly = applyOuterLoopPolygon([], loopData, cmPerUnit, boundary);
    if (nextPoly.length < 3) nextPoly = outer.slice();
    if (nextPoly.length < 3) return [];

    var allWallIds = drawable.map(function (w) { return w.id; });
    var roomObj = enrichVendorRendererRoom(
      { id: 'room-1-canvas', name: 'Room 1', walls: allWallIds },
      nextPoly,
      allWallIds,
      live
    );
    return [{ room: roomObj, polygon: nextPoly.slice() }];
  }

  function editorFloorNeedsPersistedRoom(plan) {
    var floor = resolveFloor(plan);
    if (!floor || countDrawableWalls(floor) < 4) return false;
    return !Array.isArray(floor.rooms) || floor.rooms.length === 0;
  }

  function drawableWallsList(floor) {
    var out = [];
    for (var wi = 0; wi < (floor && floor.walls || []).length; wi++) {
      var w = floor.walls[wi];
      if (w && w.id && w.start && w.end) out.push(w);
    }
    return out;
  }

  function floorBoundaryWallIds(floor, plan) {
    var drawable = drawableWallsList(floor);
    if (drawable.length < 3) return [];
    var cm = cmPerUnitForPlan(plan || { floor: floor, unit: floor && floor.unit, units: floor && floor.units });
    var loop = extractOuterLoopAndPartitions(drawable, cm);
    return (loop.boundary_wall_ids || []).map(function (id) { return String(id); });
  }

  function savedRoomsTouchingWall(wallId, floor) {
    if (!floor || !Array.isArray(floor.rooms)) return 0;
    var id = String(wallId);
    var count = 0;
    for (var ri = 0; ri < floor.rooms.length; ri++) {
      var room = floor.rooms[ri];
      if (!room) continue;
      var seen = {};
      ['boundary_wall_ids', 'walls'].forEach(function (key) {
        var list = room[key];
        if (!Array.isArray(list)) return;
        for (var i = 0; i < list.length; i++) seen[String(list[i])] = true;
      });
      if (seen[id]) count += 1;
    }
    return count;
  }

  function wallSeparatesTwoSpaces(floor, wallId, plan) {
    var drawable = drawableWallsList(floor);
    if (drawable.length < 4) return false;
    var cm = cmPerUnitForPlan(plan || { floor: floor, unit: floor && floor.unit, units: floor && floor.units });
    var loop = extractOuterLoopAndPartitions(drawable, cm);
    var outer = loop.outer_polygon || [];
    if (outer.length < 3) return false;
    var parts = classifyPartitionWalls(loop.partition_walls, outer, cm, drawable);
    var wid = String(wallId);
    var inSpan = false;
    for (var si = 0; si < parts.spanning.length; si++) {
      if (String(parts.spanning[si].id) === wid) {
        inSpan = true;
        break;
      }
    }
    if (!inSpan) return false;
    var regions = splitRegionsBySpanningWalls(outer, parts.spanning, cm, parts.epsUnit);
    if (regions.length < 2) return false;
    var touch = 0;
    for (var ri = 0; ri < regions.length; ri++) {
      var wallIds = wallsForRoomPolygon(regions[ri], drawable, cm, parts.epsUnit);
      if (wallIds.indexOf(wid) !== -1) touch += 1;
    }
    return touch >= 2;
  }

  function geometryRoomTouchCount(wallId, floor, plan) {
    var saved = savedRoomsTouchingWall(wallId, floor);
    if (saved >= 2) return saved;
    if (wallSeparatesTwoSpaces(floor, wallId, plan)) return 2;
    if (floorBoundaryWallIds(floor, plan).indexOf(String(wallId)) >= 0) return 1;
    return saved > 0 ? saved : 1;
  }

  function onPlanRoomAreaSqFt(room, polygonCm, holeRings, floor, settings) {
    var wallsBy = {};
    var wallList = (floor && floor.walls) || [];
    for (var wi = 0; wi < wallList.length; wi++) {
      var w = wallList[wi];
      if (w && w.id) wallsBy[String(w.id)] = w;
    }
    var planCtx = { floor: floor, unit: floor && floor.unit, units: floor && floor.units };
    var boundaryIds = (room && room.boundary_wall_ids) || [];
    if (!boundaryIds.length) boundaryIds = floorBoundaryWallIds(floor, planCtx);
    if (!boundaryIds.length) boundaryIds = (room && room.walls) || [];
    var cm = cmPerUnitForPlan(planCtx);
    var sqFt = 0;
    var interiorGeom = interiorGeomRuntime();
    if (interiorGeom && polygonCm && polygonCm.length >= 3) {
      sqFt = interiorGeom.interiorFloorAreaSqFt(
        polygonCm,
        boundaryIds,
        wallsBy,
        wallList,
        cm,
        false
      );
    }
    var persistedRoom = false;
    if (floor && Array.isArray(floor.rooms) && floor.rooms.length > 0 && room && room.id) {
      for (var ri = 0; ri < floor.rooms.length; ri++) {
        if (String(floor.rooms[ri].id) === String(room.id)) {
          persistedRoom = true;
          break;
        }
      }
    }
    if (persistedRoom && floor && room && room.id && wallList.length >= 3) {
      try {
        var metricsPlan = {
          floor: floor,
          floors: [floor],
          unit: floor.unit,
          units: floor.units || floor.unit
        };
        var live = layoutMetrics(metricsPlan, room.id, {});
        if (live && Number(live.area_sqft) > 0) {
          sqFt = Number(live.area_sqft);
        }
      } catch (ignore) { /* keep polygon-derived area */ }
    } else if (sqFt <= 0 && polygonCm && polygonCm.length >= 3 && interiorGeom) {
      sqFt = interiorGeom.interiorFloorAreaSqFt(
        polygonCm,
        boundaryIds,
        wallsBy,
        wallList,
        cm,
        false
      );
    }
    return Math.round(sqFt * 100) / 100;
  }

  function formatOnPlanRoomAreaDisplay(sqFt, settings, floor) {
    var units = String(
      (settings && settings.units) || (floor && floor.units) || (floor && floor.unit) || 'metric'
    ).toLowerCase();
    var imperial =
      units === 'imperial' || units === 'ft' || units === 'feet' || units === 'foot' || units === 'in';
    if (imperial) {
      return sqFt.toFixed(2) + ' ft\u00b2';
    }
    var m2 = Math.round((sqFt / 10.7639) * 100) / 100;
    return m2.toFixed(2) + ' m\u00b2';
  }

  function formatOnPlanCanvasStatusArea(rooms, floor, settings, polygonByRoomId, holesByRoomId) {
    if (!rooms || !rooms.length) {
      return formatOnPlanRoomAreaDisplay(0, settings, floor);
    }
    var totalSqFt = 0;
    for (var i = 0; i < rooms.length; i++) {
      var room = rooms[i];
      if (!room) continue;
      var poly = null;
      if (polygonByRoomId && typeof polygonByRoomId.get === 'function') {
        poly = polygonByRoomId.get(room.id);
      } else if (polygonByRoomId && room.id) {
        poly = polygonByRoomId[room.id];
      }
      if ((!poly || poly.length < 3) && room.polygon && room.polygon.length >= 3) {
        poly = room.polygon;
      }
      var holes = [];
      if (holesByRoomId && typeof holesByRoomId.get === 'function') {
        holes = holesByRoomId.get(room.id) || [];
      } else if (holesByRoomId && room.id && holesByRoomId[room.id]) {
        holes = holesByRoomId[room.id];
      }
      totalSqFt += onPlanRoomAreaSqFt(room, poly, holes, floor, settings);
    }
    totalSqFt = Math.round(totalSqFt * 100) / 100;
    return formatOnPlanRoomAreaDisplay(totalSqFt, settings, floor);
  }

  return {
    CM_PER_INCH: CM_PER_INCH,
    CM_PER_FOOT: CM_PER_FOOT,
    roundTo: roundTo,
    isPlan: isPlan,
    emptyLayoutMetrics: emptyLayoutMetrics,
    layoutMetrics: layoutMetrics,
    resolveFloor: resolveFloor,
    countDrawableWalls: countDrawableWalls,
    extractOuterLoopAndPartitions: extractOuterLoopAndPartitions,
    shouldReplaceRoomPolygonWithOuterLoop: shouldReplaceRoomPolygonWithOuterLoop,
    isSimplePolygon: isSimplePolygon,
    syncEditorRoomBoundariesForCanvasFill: syncEditorRoomBoundariesForCanvasFill,
    canvasFillRoomEntries: canvasFillRoomEntries,
    editorFloorNeedsPersistedRoom: editorFloorNeedsPersistedRoom,
    cmPerUnitForPlan: cmPerUnitForPlan,
    floorBoundaryWallIds: floorBoundaryWallIds,
    wallSeparatesTwoSpaces: wallSeparatesTwoSpaces,
    geometryRoomTouchCount: geometryRoomTouchCount,
    interiorButtDeductionLfOnBoundaryWall: interiorButtDeductionLfOnBoundaryWall,
    onPlanRoomAreaSqFt: onPlanRoomAreaSqFt,
    formatOnPlanRoomArea: function (room, polygonCm, holeRings, floor, settings) {
      return formatOnPlanRoomAreaDisplay(
        onPlanRoomAreaSqFt(room, polygonCm, holeRings, floor, settings),
        settings,
        floor
      );
    },
    formatOnPlanCanvasStatusArea: formatOnPlanCanvasStatusArea,
    interiorRoomLabelAreaSqFt: function (floor, polygonCm, boundaryWallIds, wallsById) {
      var interiorGeom = interiorGeomRuntime();
      if (!interiorGeom) return 0;
      var all = [];
      if (wallsById) {
        Object.keys(wallsById).forEach(function (k) { all.push(wallsById[k]); });
      } else if (floor && floor.walls) {
        for (var i = 0; i < floor.walls.length; i++) all.push(floor.walls[i]);
      }
      var unit = String((floor && floor.units) || (floor && floor.unit) || 'cm').toLowerCase();
      var cmPerUnit = cmPerUnitForPlan({ unit: unit, units: unit, floor: floor });
      return interiorGeom.interiorFloorAreaSqFt(
        polygonCm,
        boundaryWallIds || [],
        wallsById || {},
        all,
        cmPerUnit,
        false
      );
    }
  };
}));

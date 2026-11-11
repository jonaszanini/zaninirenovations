/**
 * Interior-face polygons and wall edge lengths (matches RoomAreaInteriorGeometry.php).
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.RoomAreaInteriorGeometry = api;
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var CM_PER_INCH = 2.54;
  var CM_PER_FOOT = 30.48;
  var WALL_JOIN_TOLERANCE_CM = 5.0;
  var INTERIOR_FACE_MODE = 'interior_face';

  function hypot(dx, dy) {
    return Math.sqrt(dx * dx + dy * dy);
  }

  /** Wall thickness on plan records may be cm (11.43), inches on ft plans (4.5), or native plan units. */
  function wallThicknessCm(thickness, cmPerUnit) {
    var t = Number(thickness == null ? 11.43 : thickness);
    if (!(t > 0)) t = 11.43;
    if (cmPerUnit >= CM_PER_FOOT - 0.01) {
      return t * CM_PER_INCH;
    }
    if (cmPerUnit <= 1.01) {
      return t;
    }
    return t * cmPerUnit;
  }

  function signedShoelace(vertices) {
    var sum = 0;
    var n = vertices.length;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      sum += vertices[i].x * vertices[j].y - vertices[j].x * vertices[i].y;
    }
    return sum / 2;
  }

  function shoelaceArea(vertices) {
    return Math.abs(signedShoelace(vertices));
  }

  function pointInPolygon(point, vertices) {
    var inside = false;
    var n = vertices.length;
    for (var i = 0, j = n - 1; i < n; j = i++) {
      var xi = vertices[i].x;
      var yi = vertices[i].y;
      var xj = vertices[j].x;
      var yj = vertices[j].y;
      var intersect = ((yi > point.y) !== (yj > point.y))
        && (point.x < ((xj - xi) * (point.y - yi)) / ((yj - yi) + 1e-12) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function outwardUnitNormal(a, b, polygon) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var len = hypot(dx, dy) || 1;
    var nx = -dy / len;
    var ny = dx / len;
    var probe = { x: (a.x + b.x) / 2 + nx * 6, y: (a.y + b.y) / 2 + ny * 6 };
    if (pointInPolygon(probe, polygon)) {
      nx = -nx;
      ny = -ny;
    }
    return { x: nx, y: ny };
  }

  function inwardUnitNormal(a, b, polygon) {
    var out = outwardUnitNormal(a, b, polygon);
    return { x: -out.x, y: -out.y };
  }

  function normalizeRing(vertices) {
    if (!vertices || vertices.length < 3) return vertices ? vertices.slice() : [];
    return vertices.map(function (v) { return { x: Number(v.x), y: Number(v.y) }; });
  }

  function offsetPolygonByEdgeOffsetsCm(vertices, signedEdgeOffsetsCm, inward) {
    var ring = normalizeRing(vertices);
    var count = ring.length;
    if (count < 3) return ring;
    var out = [];
    for (var i = 0; i < count; i++) {
      var prev = ring[(i - 1 + count) % count];
      var curr = ring[i];
      var next = ring[(i + 1) % count];
      var edgePrev = (i - 1 + count) % count;
      var edgeCurr = i;
      var d0 = Math.abs(Number(signedEdgeOffsetsCm[edgePrev] || 0));
      var d1 = Math.abs(Number(signedEdgeOffsetsCm[edgeCurr] || 0));
      var n1 = inward ? inwardUnitNormal(prev, curr, ring) : outwardUnitNormal(prev, curr, ring);
      var n2 = inward ? inwardUnitNormal(curr, next, ring) : outwardUnitNormal(curr, next, ring);
      var dot = n1.x * n2.x + n1.y * n2.y;
      var denom = 1.0 + dot;
      var ox;
      var oy;
      if (Math.abs(denom) < 0.12) {
        ox = n1.x * d0;
        oy = n1.y * d0;
      } else {
        ox = (n1.x * d0 + n2.x * d1) / denom;
        oy = (n1.y * d0 + n2.y * d1) / denom;
      }
      var miter = hypot(ox, oy);
      var maxDist = Math.max(d0, d1);
      var maxMiter = maxDist > 0 ? maxDist * 4.0 : 0;
      if (miter > maxMiter && miter > 0) {
        ox *= maxMiter / miter;
        oy *= maxMiter / miter;
      }
      out.push({ x: Math.round((curr.x + ox) * 10000) / 10000, y: Math.round((curr.y + oy) * 10000) / 10000 });
    }
    return out;
  }

  function matchWallToEdge(a, b, wallsById, cmPerUnit) {
    var eps = WALL_JOIN_TOLERANCE_CM / Math.max(cmPerUnit, 1e-9);
    var bestId = null;
    var bestScore = Infinity;
    Object.keys(wallsById).forEach(function (id) {
      var wall = wallsById[id];
      if (!wall || !wall.start || !wall.end) return;
      var d1 = hypot(wall.start.x - a.x, wall.start.y - a.y) + hypot(wall.end.x - b.x, wall.end.y - b.y);
      var d2 = hypot(wall.end.x - a.x, wall.end.y - a.y) + hypot(wall.start.x - b.x, wall.start.y - b.y);
      var score = Math.min(d1, d2);
      if (score < eps && score < bestScore) {
        bestScore = score;
        bestId = id;
      }
    });
    return bestId;
  }

  function planRingPerimeterLf(ring, cmPerUnit) {
    if (!ring || ring.length < 3) return 0;
    var unit = Math.max(cmPerUnit, 1e-9);
    var lf = 0;
    for (var i = 0; i < ring.length; i++) {
      var j = (i + 1) % ring.length;
      var dx = (ring[j].x - ring[i].x) * unit;
      var dy = (ring[j].y - ring[i].y) * unit;
      lf += hypot(dx, dy) / CM_PER_FOOT;
    }
    return Math.round(lf * 100) / 100;
  }

  function halfThicknessPerEdgeCm(ring, boundaryWallIds, wallsById, cmPerUnit) {
    var count = ring.length;
    var out = [];
    for (var i = 0; i < count; i++) {
      var j = (i + 1) % count;
      var wallId = boundaryWallIds[i] || matchWallToEdge(ring[i], ring[j], wallsById, cmPerUnit);
      var wall = wallId ? wallsById[String(wallId)] : null;
      var thickCm = wallThicknessCm(wall ? wall.thickness : 11.43, cmPerUnit);
      out.push(Math.max(0, thickCm / 2));
    }
    return out;
  }

  function roomUsesInteriorPolygonDirectly(room) {
    if (!room) return false;
    var details = room.details || {};
    var mode = String(room.floor_polygon_mode || details.floor_polygon_mode || '').toLowerCase();
    return mode === INTERIOR_FACE_MODE;
  }

  function interiorPolygonCm(centerlineVertsCm, boundaryWallIds, wallsById, cmPerUnit) {
    var ring = normalizeRing(centerlineVertsCm);
    if (ring.length < 3) return [];
    var unit = Math.max(cmPerUnit, 1e-9);
    var offsetsCm = halfThicknessPerEdgeCm(ring, boundaryWallIds, wallsById, cmPerUnit);
    var offsets = offsetsCm.map(function (o) { return -Math.abs(o) / unit; });
    return offsetPolygonByEdgeOffsetsCm(ring, offsets, true);
  }

  function roundToSixteenthFoot(feet) {
    return Math.round(feet * 16) / 16;
  }

  function verticesToSquareFeet(verticesFt) {
    return Math.round(shoelaceArea(verticesFt) * 100) / 100;
  }

  function interiorFloorAreaSqFt(centerlineVertsCm, boundaryWallIds, wallsById, allWallsList, cmPerUnit, polygonAlreadyInterior) {
    if (!centerlineVertsCm || centerlineVertsCm.length < 3) return 0;
    var ring = polygonAlreadyInterior
      ? normalizeRing(centerlineVertsCm)
      : interiorPolygonCm(centerlineVertsCm, boundaryWallIds, wallsById, cmPerUnit);
    if (ring.length < 3) return 0;
    var verticesFt = ring.map(function (pt) {
      return {
        x: (pt.x * cmPerUnit) / CM_PER_FOOT,
        y: (pt.y * cmPerUnit) / CM_PER_FOOT
      };
    });
    return verticesToSquareFeet(verticesFt);
  }

  function wallThicknessPerEdgePlan(ring, boundaryWallIds, wallsById, cmPerUnit, outwardFactor) {
    var count = ring.length;
    var unit = Math.max(cmPerUnit, 1e-9);
    var mult = outwardFactor > 0 ? outwardFactor : 1.0;
    var out = [];
    for (var i = 0; i < count; i++) {
      var j = (i + 1) % count;
      var wallId = boundaryWallIds[i] || matchWallToEdge(ring[i], ring[j], wallsById, cmPerUnit);
      var wall = wallId ? wallsById[String(wallId)] : null;
      var thickCm = wallThicknessCm(wall ? wall.thickness : 11.43, cmPerUnit);
      out.push((thickCm * mult) / unit);
    }
    return out;
  }

  function exteriorFloorAreaSqFt(ringVertsCm, boundaryWallIds, wallsById, cmPerUnit, polygonIsInteriorFace) {
    var ring = normalizeRing(ringVertsCm);
    if (ring.length < 3) return 0;
    var unit = Math.max(cmPerUnit, 1e-9);
    var outwardFactor = 0.5;
    var offsets = wallThicknessPerEdgePlan(ring, boundaryWallIds, wallsById, cmPerUnit, outwardFactor);
    var outer = offsetPolygonByEdgeOffsetsCm(ring, offsets, false);
    if (outer.length < 3) return 0;
    var verticesFt = outer.map(function (pt) {
      return { x: (pt.x * cmPerUnit) / CM_PER_FOOT, y: (pt.y * cmPerUnit) / CM_PER_FOOT };
    });
    return verticesToSquareFeet(verticesFt);
  }

  function wallCenterlineLengthCm(wall, cmPerUnit) {
    if (!wall || !wall.start || !wall.end) return 0;
    var dx = (wall.end.x - wall.start.x) * cmPerUnit;
    var dy = (wall.end.y - wall.start.y) * cmPerUnit;
    return hypot(dx, dy);
  }

  function wallEndpointTrimCm(wall, allWalls, cmPerUnit, trimCornersAtJoints) {
    if (trimCornersAtJoints === undefined) trimCornersAtJoints = true;
    var dx = (wall.end.x - wall.start.x) * cmPerUnit;
    var dy = (wall.end.y - wall.start.y) * cmPerUnit;
    var len = hypot(dx, dy) || 1;
    var eps = WALL_JOIN_TOLERANCE_CM;
    function trimAt(point) {
      var trim = 0;
      var px = point.x * cmPerUnit;
      var py = point.y * cmPerUnit;
      for (var i = 0; i < allWalls.length; i++) {
        var other = allWalls[i];
        if (!other || other.id === wall.id || !other.start || !other.end) continue;
        var sx = other.start.x * cmPerUnit;
        var sy = other.start.y * cmPerUnit;
        var ex = other.end.x * cmPerUnit;
        var ey = other.end.y * cmPerUnit;
        var ox = ex - sx;
        var oy = ey - sy;
        var olen = hypot(ox, oy) || 1;
        var atStart = hypot(sx - px, sy - py) < eps;
        var atEnd = hypot(ex - px, ey - py) < eps;
        var cross = (dx / len) * (oy / olen) - (dy / len) * (ox / olen);
        if (atStart || atEnd) {
          if (!trimCornersAtJoints || Math.abs(cross) < 0.1) continue;
          trim = Math.max(trim, wallThicknessCm(other.thickness, cmPerUnit) / 2);
          continue;
        }
        var t = ((px - sx) * ox + (py - sy) * oy) / (olen * olen);
        var onInterior = t > eps / olen && t < 1 - eps / olen;
        if (!onInterior) continue;
        var projX = sx + t * ox;
        var projY = sy + t * oy;
        if (hypot(px - projX, py - projY) >= eps) continue;
        if (Math.abs(cross) < 0.1) continue;
        var thick = wallThicknessCm(other.thickness, cmPerUnit);
        trim = Math.max(trim, thick / 2);
      }
      return trim;
    }
    return { start: trimAt(wall.start), end: trimAt(wall.end) };
  }

  function wallPlanEdgeLengthCm(wall, allWalls, cmPerUnit, trimCornersAtJoints) {
    if (trimCornersAtJoints === undefined) trimCornersAtJoints = true;
    var center = wallCenterlineLengthCm(wall, cmPerUnit);
    if (!wall || wall.curvePoint) return center;
    var trim = wallEndpointTrimCm(wall, allWalls, cmPerUnit, trimCornersAtJoints);
    return Math.max(0, center - trim.start - trim.end);
  }

  function wallOverlapsRingEdge(wall, ax, ay, bx, by, cmPerUnit) {
    if (!wall || !wall.start || !wall.end) return false;
    var eps = WALL_JOIN_TOLERANCE_CM;
    var unit = Math.max(cmPerUnit, 1e-9);
    var sx = wall.start.x * unit;
    var sy = wall.start.y * unit;
    var ex = wall.end.x * unit;
    var ey = wall.end.y * unit;
    var edx = bx - ax;
    var edy = by - ay;
    var elen = hypot(edx, edy) || 1;
    var cross1 = Math.abs(edx * (sy - ay) - edy * (sx - ax)) / elen;
    var cross2 = Math.abs(edx * (ey - ay) - edy * (ex - ax)) / elen;
    if (cross1 > eps || cross2 > eps) return false;
    var t1 = ((sx - ax) * edx + (sy - ay) * edy) / (elen * elen);
    var t2 = ((ex - ax) * edx + (ey - ay) * edy) / (elen * elen);
    var lo = Math.max(0, Math.min(t1, t2));
    var hi = Math.min(1, Math.max(t1, t2));
    return hi - lo > eps / elen;
  }

  function wallEndpointTouchesOther(wall, others, cmPerUnit) {
    if (!wall || !wall.start || !wall.end) return { start: false, end: false };
    var unit = Math.max(cmPerUnit, 1e-9);
    var eps = WALL_JOIN_TOLERANCE_CM;
    function near(point, px, py) {
      return hypot(point.x * unit - px, point.y * unit - py) < eps;
    }
    var touchesStart = false;
    var touchesEnd = false;
    for (var i = 0; i < others.length; i++) {
      var o = others[i];
      if (!o || o.id === wall.id || !o.start || !o.end) continue;
      var osx = o.start.x * unit;
      var osy = o.start.y * unit;
      var oex = o.end.x * unit;
      var oey = o.end.y * unit;
      if (near(wall.start, osx, osy) || near(wall.start, oex, oey)) touchesStart = true;
      if (near(wall.end, osx, osy) || near(wall.end, oex, oey)) touchesEnd = true;
    }
    return { start: touchesStart, end: touchesEnd };
  }

  function distributeCollinearEdgeLengthsFt(matched, edgeLenFt, cmPerUnit, allWallsList) {
    if (!matched || matched.length < 2) return null;
    var ctx = (allWallsList && allWallsList.length) ? allWallsList : matched;
    var out = {};
    var totalEdgeCm = 0;
    for (var ti = 0; ti < matched.length; ti++) {
      totalEdgeCm += wallPlanEdgeLengthCm(matched[ti], ctx, cmPerUnit, true);
    }
    if (!(totalEdgeCm > 0)) return null;
    for (var tj = 0; tj < matched.length; tj++) {
      var edgeCm = wallPlanEdgeLengthCm(matched[tj], ctx, cmPerUnit, true);
      out[String(matched[tj].id)] = edgeLenFt * (edgeCm / totalEdgeCm);
    }
    return out;
  }

  function distributeBoundaryLengthsFt(centerlineRingCm, interiorRingCm, boundaryWallIds, wallsById, cmPerUnit) {
    var out = {};
    var matchRing = centerlineRingCm && centerlineRingCm.length >= 3 ? centerlineRingCm : interiorRingCm;
    if (!matchRing || matchRing.length < 3 || !boundaryWallIds || !boundaryWallIds.length) {
      return out;
    }
    var lenRing = interiorRingCm && interiorRingCm.length >= 3 ? interiorRingCm : matchRing;
    var n = matchRing.length;
    var unit = Math.max(cmPerUnit, 1e-9);
    for (var ei = 0; ei < n; ei++) {
      var ax = matchRing[ei].x * unit;
      var ay = matchRing[ei].y * unit;
      var bx = matchRing[(ei + 1) % n].x * unit;
      var by = matchRing[(ei + 1) % n].y * unit;
      var li = ei % lenRing.length;
      var lj = (ei + 1) % lenRing.length;
      var ix0 = lenRing[li].x * unit;
      var iy0 = lenRing[li].y * unit;
      var ix1 = lenRing[lj].x * unit;
      var iy1 = lenRing[lj].y * unit;
      var edgeLenFt = hypot(ix1 - ix0, iy1 - iy0) / CM_PER_FOOT;
      var matched = [];
      for (var wi = 0; wi < boundaryWallIds.length; wi++) {
        var w = wallsById[String(boundaryWallIds[wi])];
        if (!w) continue;
        if (wallOverlapsRingEdge(w, ax, ay, bx, by, cmPerUnit)) matched.push(w);
      }
      if (!matched.length) continue;
      var allWallsList = [];
      for (var wk in wallsById) {
        if (Object.prototype.hasOwnProperty.call(wallsById, wk)) allWallsList.push(wallsById[wk]);
      }
      var collinear = distributeCollinearEdgeLengthsFt(matched, edgeLenFt, cmPerUnit, allWallsList);
      if (collinear) {
        for (var cid in collinear) out[cid] = collinear[cid];
        continue;
      }
      var totalCenterCm = 0;
      for (var mi = 0; mi < matched.length; mi++) {
        totalCenterCm += wallCenterlineLengthCm(matched[mi], cmPerUnit);
      }
      if (!(totalCenterCm > 0)) continue;
      for (var mj = 0; mj < matched.length; mj++) {
        var frac = wallCenterlineLengthCm(matched[mj], cmPerUnit) / totalCenterCm;
        out[String(matched[mj].id)] = edgeLenFt * frac;
      }
    }
    return out;
  }

  return {
    CM_PER_INCH: CM_PER_INCH,
    CM_PER_FOOT: CM_PER_FOOT,
    INTERIOR_FACE_MODE: INTERIOR_FACE_MODE,
    roomUsesInteriorPolygonDirectly: roomUsesInteriorPolygonDirectly,
    interiorPolygonCm: interiorPolygonCm,
    interiorFloorAreaSqFt: interiorFloorAreaSqFt,
    exteriorFloorAreaSqFt: exteriorFloorAreaSqFt,
    wallPlanEdgeLengthCm: wallPlanEdgeLengthCm,
    distributeBoundaryLengthsFt: distributeBoundaryLengthsFt,
    wallEndpointTrimCm: wallEndpointTrimCm,
    wallThicknessCm: wallThicknessCm,
    planRingPerimeterLf: planRingPerimeterLf,
    offsetPolygonByEdgeOffsetsCm: offsetPolygonByEdgeOffsetsCm,
    shoelaceArea: shoelaceArea
  };
}));

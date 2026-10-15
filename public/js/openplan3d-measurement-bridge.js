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

  function verticesToSquareFeet(vertices) {
    var n = vertices.length;
    if (n < 3) return 0.0;
    var sum = 0.0;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      sum += (vertices[i].x * vertices[j].y) - (vertices[j].x * vertices[i].y);
    }
    return Math.abs(sum) * 0.5;
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

  function resolveFloor(plan) {
    if (plan.floor && typeof plan.floor === 'object') {
      return plan.floor;
    }
    if (plan.project && Array.isArray(plan.project.floors) && plan.project.floors.length > 0) {
      var activeId = plan.project.activeFloorId;
      if (activeId) {
        for (var i = 0; i < plan.project.floors.length; i++) {
          if (plan.project.floors[i].id === activeId) return plan.project.floors[i];
        }
      }
      return plan.project.floors[0];
    }
    if (Array.isArray(plan.floors) && plan.floors.length > 0) {
      var activeId = plan.activeFloorId;
      if (activeId) {
        for (var i = 0; i < plan.floors.length; i++) {
          if (plan.floors[i].id === activeId) return plan.floors[i];
        }
      }
      return plan.floors[0];
    }
    if (Array.isArray(plan.walls)) return plan;
    return null;
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

  function layoutMetrics(plan, roomId, finish, options) {
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
      var totalSharedWall = 0.0, totalFraming = 0.0, totalOutsideArea = 0.0, totalCeilingArea = 0.0, totalCasing = 0.0;
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

      return {
        area_sqft: roundTo(totalArea, 2),
        perimeter_lf: roundTo(totalPerimeter, 2),
        baseboard_lf: roundTo(totalBaseboard, 2),
        wall_height_ft: firstSub.wall_height_ft,
        wall_area_sqft: roundTo(totalNetWall, 2),
        door_count: totalDoors,
        window_count: totalWindows,
        opening_count: totalOpenings,
        drywall_openings: totalDrywall,
        inside_corners: totalInCorners,
        outside_corners: totalOutCorners,
        polygon_count: totalPolyCount,
        wall_thickness_in: firstSub.wall_thickness_in,
        inside_area_sqft: roundTo(totalArea, 2),
        outside_area_sqft: roundTo(totalOutsideArea, 2),
        shared_wall_lf: roundTo(totalSharedWall, 2),
        framing_lf: roundTo(totalFraming, 2),
        cabinet_base_lf: lib.cabinet_base_lf,
        cabinet_upper_lf: lib.cabinet_upper_lf,
        stairs_count: lib.stairs_count,
        stairs_treads: lib.stairs_treads,
        stairs_run_lf: lib.stairs_run_lf,
        stairs_quote_type: lib.stairs_quote_type,
        ceiling_height_ft: firstSub.ceiling_height_ft,
        ceiling_area_sqft: roundTo(totalCeilingArea, 2),
        net_wall_area_sqft: roundTo(totalNetWall, 2),
        walls: allWalls,
        flooring_waste_percent: wastePct,
        flooring_direction: firstSub.flooring_direction,
        flooring_box_sqft: boxSqFt,
        flooring_boxes: flBoxes,
        flooring_with_waste_sqft: flWithWaste,
        casing_lf: roundTo(totalCasing, 2)
      };
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

    // Polygon vertices
    var polyVertices = (selectedRoom && (selectedRoom.polygon || selectedRoom.vertices)) || [];
    if (!polyVertices || polyVertices.length < 3) {
      // Chain walls by room.walls or all walls
      var chainWalls = [];
      var roomWIds = (selectedRoom && selectedRoom.walls) || Object.keys(walls);
      for (var i = 0; i < roomWIds.length; i++) {
        if (walls[String(roomWIds[i])]) chainWalls.push(walls[String(roomWIds[i])]);
      }
      if (chainWalls.length >= 3) {
        var first = chainWalls[0];
        polyVertices = [{ x: first.start.x, y: first.start.y }];
        var cur = { x: first.end.x, y: first.end.y };
        var rem = chainWalls.slice(1);
        while (rem.length > 0) {
          var found = -1, rev = false;
          for (var j = 0; j < rem.length; j++) {
            if (hypot(rem[j].start.x - cur.x, rem[j].start.y - cur.y) < 1.0) {
              found = j; rev = false; break;
            }
            if (hypot(rem[j].end.x - cur.x, rem[j].end.y - cur.y) < 1.0) {
              found = j; rev = true; break;
            }
          }
          if (found === -1) {
            polyVertices.push(cur);
            var next = rem.shift();
            cur = { x: next.end.x, y: next.end.y };
          } else {
            polyVertices.push(cur);
            var wFound = rem.splice(found, 1)[0];
            cur = rev ? { x: wFound.start.x, y: wFound.start.y } : { x: wFound.end.x, y: wFound.end.y };
          }
        }
      }
    }
    if (polyVertices.length < 3) return emptyLayoutMetrics(finish.wall_height_ft);

    // Convert polygon vertices to feet
    var verticesFt = [];
    for (var i = 0; i < polyVertices.length; i++) {
      verticesFt.push({
        x: (polyVertices[i].x * cmPerUnit) / CM_PER_FOOT,
        y: (polyVertices[i].y * cmPerUnit) / CM_PER_FOOT
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
    var ceilingAreaSqFt = insideAreaSqFt;

    // Corners
    var corners = cornerCounts(verticesFt);

    // Boundary walls
    var boundaryWalls = [];
    var roomWallIds = (selectedRoom && selectedRoom.walls) || [];
    if (roomWallIds.length > 0) {
      for (var i = 0; i < roomWallIds.length; i++) {
        var w = walls[String(roomWallIds[i])];
        if (!w) continue;
        var dx = (w.end.x - w.start.x) * cmPerUnit;
        var dy = (w.end.y - w.start.y) * cmPerUnit;
        var lenCm = hypot(dx, dy);
        boundaryWalls.push({
          id: String(w.id),
          length_ft: lenCm / CM_PER_FOOT,
          thickness_cm: (Number(w.thickness || 11.43)) * cmPerUnit
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

    var perimeter = 0.0;
    for (var i = 0; i < boundaryWalls.length; i++) perimeter += boundaryWalls[i].length_ft;
    perimeter = roundTo(perimeter, 2);

    // Openings on boundary walls
    var wallIdMap = {};
    for (var i = 0; i < boundaryWalls.length; i++) wallIdMap[boundaryWalls[i].id] = true;

    var openings = [];
    var rawDoors = floor.doors || [];
    for (var i = 0; i < rawDoors.length; i++) {
      var d = rawDoors[i];
      if (!d || !wallIdMap[String(d.wallId)]) continue;
      var wObj = walls[String(d.wallId)];
      var wLenIn = wObj ? (hypot((wObj.end.x - wObj.start.x) * cmPerUnit, (wObj.end.y - wObj.start.y) * cmPerUnit) / CM_PER_INCH) : 0;
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

      if (op.type === 'door') {
        doorCount++;
        baseboardDedIn += op.width_in;
        var sides = op.details.casing_sides != null ? Number(op.details.casing_sides) :
          (String(op.details.door_face).toLowerCase() === 'exterior' ? 1 : 2);
        casingTotalLf += casingLf(op.width_in, op.height_in, sides, 'door');
      } else if (op.type === 'window') {
        windowCount++;
        var sides = op.details.casing_sides != null ? Number(op.details.casing_sides) : 4;
        casingTotalLf += casingLf(op.width_in, op.height_in, sides, 'window');
      } else {
        openingCount++;
        baseboardDedIn += op.width_in;
        var sides = op.details.casing_sides != null ? Number(op.details.casing_sides) : 2;
        casingTotalLf += casingLf(op.width_in, op.height_in, sides, 'opening');
      }
    }

    var baseboardDedLf = roundTo(baseboardDedIn / 12.0, 2);
    var baseboardLf = Math.max(0.0, roundTo(perimeter - baseboardDedLf, 2));
    var casingTotalLfRounded = roundTo(casingTotalLf, 2);

    // Per wall net areas
    var perWallNetAreas = [];
    var totalNetWallArea = 0.0;
    for (var i = 0; i < boundaryWalls.length; i++) {
      var bw = boundaryWalls[i];
      var wLenFt = bw.length_ft;
      var wLenIn = wLenFt * 12.0;
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
      var netArea = netWallAreaSqFt(wLenFt, ceilingHeight, dedRaw);
      totalNetWallArea += netArea;

      perWallNetAreas.push({
        id: bw.id,
        index: i + 1,
        label: 'W' + (i + 1),
        length_lf: roundTo(wLenFt, 2),
        opening_area_sqft: roundTo(dedRaw, 2),
        net_area_sqft: netArea
      });
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
        if (sw) sharedLf += hypot((sw.end.x - sw.start.x) * cmPerUnit, (sw.end.y - sw.start.y) * cmPerUnit) / CM_PER_FOOT;
      }
      sharedLf = roundTo(sharedLf, 2);
    }
    var framingLf = Math.max(0.0, roundTo(perimeter - sharedLf, 2));
    var exteriorLf = Math.max(0.0, roundTo(perimeter - 2.0 * sharedLf, 2));
    var outsideAreaSqFt = roundTo(Math.max(insideAreaSqFt, insideAreaSqFt + (exteriorLf * thicknessFt)), 2);

    // Furniture / Cabinets / Stairs
    var lib = resolveLibraryMetrics(floor, cmPerUnit);

    return {
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
      flooring_waste_percent: waste,
      flooring_direction: direction,
      flooring_box_sqft: box,
      flooring_boxes: boxes,
      flooring_with_waste_sqft: flooringWasteSqFt,
      casing_lf: casingTotalLfRounded
    };
  }

  return {
    CM_PER_INCH: CM_PER_INCH,
    CM_PER_FOOT: CM_PER_FOOT,
    roundTo: roundTo,
    isPlan: isPlan,
    emptyLayoutMetrics: emptyLayoutMetrics,
    layoutMetrics: layoutMetrics
  };
}));

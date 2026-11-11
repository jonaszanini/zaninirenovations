'use strict';

const assert = require('assert');
const lib = require('../../public/js/room-area-lib.js');
const bridge = require('../../public/js/openplan3d-measurement-bridge.js');
const ext = require('../../public/js/openplan3d-zanini-extensions.js');

console.log('Running openPlan3D Measurement Bridge JavaScript Parity Tests...');

// Verify bridge methods exist
assert.strictEqual(typeof bridge.layoutMetrics, 'function', 'bridge.layoutMetrics is a function');
assert.strictEqual(typeof bridge.isPlan, 'function', 'bridge.isPlan is a function');
assert.strictEqual(typeof bridge.emptyLayoutMetrics, 'function', 'bridge.emptyLayoutMetrics is a function');

// Test 1: Empty layout metrics
const empty = bridge.emptyLayoutMetrics(8.0);
assert.strictEqual(empty.area_sqft, 0.0);
assert.strictEqual(empty.perimeter_lf, 0.0);
assert.strictEqual(empty.wall_height_ft, 8.0);
assert.strictEqual(empty.door_count, 0);
assert.strictEqual(empty.partition_lf, 0.0);

// Test 2: Rectangle 10x12 ft clean
// In cm: 10 ft = 304.8 cm, 12 ft = 365.76 cm
const rectPlan = {
  format: 'openplan3d',
  units: 'cm',
  walls: [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 }, thickness: 11.43, height: 243.84 },
    { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 365.76 }, thickness: 11.43, height: 243.84 },
    { id: 'w3', start: { x: 304.8, y: 365.76 }, end: { x: 0, y: 365.76 }, thickness: 11.43, height: 243.84 },
    { id: 'w4', start: { x: 0, y: 365.76 }, end: { x: 0, y: 0 }, thickness: 11.43, height: 243.84 }
  ],
  rooms: [
    { id: 'r1', name: 'Room 1', walls: ['w1', 'w2', 'w3', 'w4'] }
  ]
};

const rectMetrics = bridge.layoutMetrics(rectPlan, 'r1', { wall_height_ft: 8.0 });
// 10×12 ft centerline rectangle, 4.5″ walls → ~9.625×11.625 ft interior face (111.89 sf); perimeter walks inset ring (42.5 lf); drywall uses interior-face spans (42.5 lf × 8 ft = 340 sf).
assert.strictEqual(rectMetrics.area_sqft, 111.89, '10x12 rectangle interior floor area');
assert.strictEqual(rectMetrics.perimeter_lf, 42.5, '10x12 rectangle clear perimeter');
assert.strictEqual(rectMetrics.baseboard_lf, 42.5, 'No openings, baseboard equals perimeter');
assert.strictEqual(rectMetrics.wall_area_sqft, 340, 'interior-face wall edges × 8 ft wall height');
assert.strictEqual(rectMetrics.ceiling_area_sqft, 111.89);
// Exterior footprint from interior ring + half wall per edge → 128.39 sf (not centerline + full thickness).
assert.strictEqual(rectMetrics.outside_area_sqft, 128.39, 'outer footprint uses exterior wall faces');
assert.strictEqual(rectMetrics.inside_corners, 4);
assert.strictEqual(rectMetrics.outside_corners, 0);
assert.strictEqual(rectMetrics.partition_lf, 0.0);

// Test 3: Rectangle with door (36"x80") and window (36"x48", 4-side casing)
// Door: 36" = 91.44 cm, 80" = 203.2 cm
// Window: 36" = 91.44 cm, 48" = 121.92 cm
const rectWithOpenings = {
  format: 'openplan3d',
  units: 'cm',
  walls: rectPlan.walls,
  rooms: rectPlan.rooms,
  doors: [
    { id: 'd1', wallId: 'w1', position: 0.5, width: 91.44, height: 203.2, type: 'single', details: { casing_sides: 2 } }
  ],
  windows: [
    { id: 'win1', wallId: 'w2', position: 0.5, width: 91.44, height: 121.92, sillHeight: 91.44, type: 'standard', details: { casing_sides: 4 } }
  ]
};

const rectWithOpeningsMetrics = bridge.layoutMetrics(rectWithOpenings, 'r1', { wall_height_ft: 8.0 });
assert.strictEqual(rectWithOpeningsMetrics.door_count, 1);
assert.strictEqual(rectWithOpeningsMetrics.window_count, 1);
assert.strictEqual(rectWithOpeningsMetrics.drywall_openings, 2);
// Door casing: 2 * (36 + 2*80) / 12 = 32.67 LF
// Window casing: (2*36 + 2*48) / 12 = 14.0 LF
// Total casing: 46.67 LF
assert.strictEqual(rectWithOpeningsMetrics.casing_lf, 46.67, 'Total casing LF is 46.67');
// Baseboard: 42.5 lf perimeter − 36″ door (3 lf) = 39.5 lf
assert.strictEqual(rectWithOpeningsMetrics.baseboard_lf, 39.5, 'Baseboard is 39.5 LF');
const rectFinishPlan = Object.assign({}, rectWithOpenings, {
  zanini: {
    wallFinishes: {
      w1: { baseboard: true, drywall: true, insulation: true, paint: true },
      w2: { baseboard: true, drywall: true, insulation: true, paint: true },
      w3: { baseboard: true, drywall: true, insulation: true, paint: true },
      w4: { baseboard: true, drywall: true, insulation: true, paint: true },
    },
  },
});
const rectFinishMetrics = bridge.layoutMetrics(rectFinishPlan, 'r1', { wall_height_ft: 8.0, ceiling_height_ft: 8.0 });
assert.strictEqual(rectFinishMetrics.finish_baseboard_lf, 39.5, 'finish_baseboard_lf deducts door on boundary walls');

// Door area: (36/12) * (80/12) = 20.00 sqft
// Window area: (36/12) * (48/12) = 12.00 sqft
// Net wall area: 340 − 20.00 − 12.00 = 308 sqft
assert.strictEqual(rectWithOpeningsMetrics.wall_area_sqft, 308, 'Net wall area is 308 sqft');

// Test 4: L-Shape 16x12 ft (return 6x4 ft)
// Vertices in ft: (0,0), (16,0), (16,12), (10,12), (10,8), (0,8)
// Floor area = 16*12 - 6*4 = 192 - 24 = 168 sqft
// Or return in Zanini template: 16x12 outer, return 6x4 at bottom right -> 152 sqft, 56 LF perimeter
const lShapePlan = {
  format: 'openplan3d',
  units: 'ft',
  walls: [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 16, y: 0 }, thickness: 4.5, height: 8 },
    { id: 'w2', start: { x: 16, y: 0 }, end: { x: 16, y: 8 }, thickness: 4.5, height: 8 },
    { id: 'w3', start: { x: 16, y: 8 }, end: { x: 10, y: 8 }, thickness: 4.5, height: 8 },
    { id: 'w4', start: { x: 10, y: 8 }, end: { x: 10, y: 12 }, thickness: 4.5, height: 8 },
    { id: 'w5', start: { x: 10, y: 12 }, end: { x: 0, y: 12 }, thickness: 4.5, height: 8 },
    { id: 'w6', start: { x: 0, y: 12 }, end: { x: 0, y: 0 }, thickness: 4.5, height: 8 }
  ],
  rooms: [
    { id: 'r_l', name: 'L Room', walls: ['w1', 'w2', 'w3', 'w4', 'w5', 'w6'] }
  ]
};

const lMetrics = bridge.layoutMetrics(lShapePlan, 'r_l', { wall_height_ft: 8.0 });
assert.strictEqual(lMetrics.area_sqft, 157.64, 'L shape interior floor area');
assert.strictEqual(lMetrics.perimeter_lf, 54.5, 'L shape clear perimeter (interior-face ring walk)');
assert.strictEqual(lMetrics.inside_corners, 5, 'L shape has 5 inside corners');
assert.strictEqual(lMetrics.outside_corners, 1, 'L shape has 1 outside corner');

// Test 5: 3-side casing stool window
const stoolPlan = {
  format: 'openplan3d',
  units: 'in',
  walls: [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 144, y: 0 }, thickness: 4.5, height: 96 },
    { id: 'w2', start: { x: 144, y: 0 }, end: { x: 144, y: 144 }, thickness: 4.5, height: 96 },
    { id: 'w3', start: { x: 144, y: 144 }, end: { x: 0, y: 144 }, thickness: 4.5, height: 96 },
    { id: 'w4', start: { x: 0, y: 144 }, end: { x: 0, y: 0 }, thickness: 4.5, height: 96 }
  ],
  rooms: [{ id: 'r1', name: 'Room 1', walls: ['w1', 'w2', 'w3', 'w4'] }],
  windows: [
    { id: 'win1', wallId: 'w1', position: 0.5, width: 48, height: 36, sillHeight: 36, type: 'standard', details: { casing_sides: 3 } }
  ]
};
const stoolMetrics = bridge.layoutMetrics(stoolPlan, 'r1', { wall_height_ft: 8.0 });
// 3-side casing: (W + 2H) / 12 = (48 + 72) / 12 = 120 / 12 = 10.0 LF
assert.strictEqual(stoolMetrics.casing_lf, 10.0, '3-side casing LF is 10.0');

// Test 6: Exterior door 1-side casing
const exteriorDoorPlan = {
  format: 'openplan3d',
  units: 'in',
  walls: stoolPlan.walls,
  rooms: stoolPlan.rooms,
  doors: [
    { id: 'd1', wallId: 'w1', position: 0.5, width: 36, height: 80, type: 'single', details: { casing_sides: 1, door_face: 'exterior' } }
  ]
};
const extDoorMetrics = bridge.layoutMetrics(exteriorDoorPlan, 'r1', { wall_height_ft: 8.0 });
// 1-side casing: 1 * (36 + 160) / 12 = 196 / 12 = 16.33 LF
assert.strictEqual(extDoorMetrics.casing_lf, 16.33, '1-side exterior door casing LF is 16.33');

// Test 7: Multi-room aggregate layoutMetrics with library items (cabinets and stairs)
const multiRoomPlan = {
  format: 'openplan3d',
  units: 'in',
  walls: [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 120, y: 0 }, thickness: 4.5, height: 96 },
    { id: 'w2', start: { x: 120, y: 0 }, end: { x: 120, y: 120 }, thickness: 4.5, height: 96 },
    { id: 'w3', start: { x: 120, y: 120 }, end: { x: 0, y: 120 }, thickness: 4.5, height: 96 },
    { id: 'w4', start: { x: 0, y: 120 }, end: { x: 0, y: 0 }, thickness: 4.5, height: 96 },
    { id: 'w5', start: { x: 120, y: 0 }, end: { x: 240, y: 0 }, thickness: 4.5, height: 96 },
    { id: 'w6', start: { x: 240, y: 0 }, end: { x: 240, y: 120 }, thickness: 4.5, height: 96 },
    { id: 'w7', start: { x: 240, y: 120 }, end: { x: 120, y: 120 }, thickness: 4.5, height: 96 }
  ],
  rooms: [
    { id: 'r1', name: 'Room 1', walls: ['w1', 'w2', 'w3', 'w4'] },
    { id: 'r2', name: 'Room 2', walls: ['w5', 'w6', 'w7', 'w2'] }
  ],
  furniture: [
    { catalogId: 'cabinet_base_36', width: 36, depth: 24 },
    { catalogId: 'cabinet_upper_30', width: 30, depth: 12 }
  ],
  stairs: [
    { stairType: 'straight', depth: 120, riserCount: 12 }
  ]
};

const multiMetrics = bridge.layoutMetrics(multiRoomPlan, null, { wall_height_ft: 8.0 });
assert.ok(multiMetrics.area_sqft > 0, 'Multi-room area computed without ReferenceError');
assert.strictEqual(multiMetrics.polygon_count, 2, 'Multi-room polygon count is 2');
assert.strictEqual(multiMetrics.cabinet_base_lf, 3.0, 'Base cabinet LF is 36 / 12 = 3.0');
assert.strictEqual(multiMetrics.cabinet_upper_lf, 2.5, 'Upper cabinet LF is 30 / 12 = 2.5');
assert.strictEqual(multiMetrics.stairs_count, 1, 'Stairs count is 1');
assert.strictEqual(multiMetrics.stairs_treads, 12, 'Stairs treads is 12');

// Test 8: L-shaped room plus 2 interior walls (one T-joined, one free-ended)
const cmPerFt = 30.48;
const lShapeWithPartitionsPlan = {
  unit: 'cm',
  floor: {
    walls: [
      { id: 'w1', start: { x: 0, y: 0 }, end: { x: 20 * cmPerFt, y: 0 }, thickness: 10, height: 8 * cmPerFt },
      { id: 'w2', start: { x: 20 * cmPerFt, y: 0 }, end: { x: 20 * cmPerFt, y: 10 * cmPerFt }, thickness: 10, height: 8 * cmPerFt },
      { id: 'w3', start: { x: 20 * cmPerFt, y: 10 * cmPerFt }, end: { x: 10 * cmPerFt, y: 10 * cmPerFt }, thickness: 10, height: 8 * cmPerFt },
      { id: 'w4', start: { x: 10 * cmPerFt, y: 10 * cmPerFt }, end: { x: 10 * cmPerFt, y: 20 * cmPerFt }, thickness: 10, height: 8 * cmPerFt },
      { id: 'w5', start: { x: 10 * cmPerFt, y: 20 * cmPerFt }, end: { x: 0, y: 20 * cmPerFt }, thickness: 10, height: 8 * cmPerFt },
      { id: 'w6', start: { x: 0, y: 20 * cmPerFt }, end: { x: 0, y: 0 }, thickness: 10, height: 8 * cmPerFt },
      // Partition 1: T-joined to w1 at (5, 0), extends up to (5, 6) (6 ft)
      { id: 'p1', start: { x: 5 * cmPerFt, y: 0 }, end: { x: 5 * cmPerFt, y: 6 * cmPerFt }, thickness: 10, height: 8 * cmPerFt },
      // Partition 2: T-joined to p1 at (5, 3), extends right to (11, 3) (6 ft, free-ended)
      { id: 'p2', start: { x: 5 * cmPerFt, y: 3 * cmPerFt }, end: { x: 11 * cmPerFt, y: 3 * cmPerFt }, thickness: 10, height: 8 * cmPerFt },
    ],
    rooms: []
  }
};

const lPartMetrics = bridge.layoutMetrics(lShapeWithPartitionsPlan, null, { wall_height_ft: 8.0 });
assert.ok(!lPartMetrics.geometry_unmeasurable, 'L-shape with partitions is not marked geometry_unmeasurable');
assert.strictEqual(lPartMetrics.area_sqft, 286.98, 'Interior L-shape floor area (~10 cm walls; was 300 centerline)');
assert.strictEqual(lPartMetrics.perimeter_lf, 78.69, 'Clear outer loop perimeter');
assert.strictEqual(lPartMetrics.net_wall_area_sqft, 811.03,
  'Net wall area with boundary + single-face partition butt overlap at T junctions');
assert.strictEqual(lPartMetrics.walls.length, 8, '8 walls total returned');
assert.strictEqual(lPartMetrics.partition_lf, 11.67, 'Partition framing stays full length (T butts do not shorten studs)');
(function lPartFinishBaseboardPin() {
  const finishes = {};
  const boundary = new Set((lPartMetrics.boundary_wall_ids || []).map(String));
  (lPartMetrics.walls || []).forEach(function (w) {
    finishes[w.id] = {
      drywall: true,
      baseboard: true,
      insulation: boundary.has(String(w.id)),
      studs: !boundary.has(String(w.id)),
    };
  });
  const finished = ext.applyToMetrics(
    lPartMetrics,
    Object.assign({}, lShapeWithPartitionsPlan, { zanini: { wallFinishes: finishes } }),
    {},
    cmPerFt
  );
  assert.strictEqual(finished.finish_baseboard_lf, 101.37,
    'Partition–partition T butt applies once to p1 baseboard (both faces), not twice');
})();

// Test 9: Self-intersecting stored polygon + two top-edge stub partitions (Jonas basement regression)
function basementStubRegressionPlan(stubYOffsetCm) {
  stubYOffsetCm = stubYOffsetCm || 0;
  const ft = cmPerFt;
  const walls = [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 30 * ft, y: 0 }, thickness: 10, height: 8 * ft },
    { id: 'w2', start: { x: 30 * ft, y: 0 }, end: { x: 30 * ft, y: 20 * ft }, thickness: 10, height: 8 * ft },
    { id: 'w3', start: { x: 30 * ft, y: 20 * ft }, end: { x: 0, y: 20 * ft }, thickness: 10, height: 8 * ft },
    { id: 'w4', start: { x: 0, y: 20 * ft }, end: { x: 0, y: 0 }, thickness: 10, height: 8 * ft },
    {
      id: 'p1',
      start: { x: 14 * ft, y: 20 * ft - stubYOffsetCm },
      end: { x: 14 * ft, y: 8 * ft },
      thickness: 10,
      height: 8 * ft
    },
    {
      id: 'p2',
      start: { x: 22 * ft, y: 20 * ft - stubYOffsetCm },
      end: { x: 22 * ft, y: 12 * ft },
      thickness: 10,
      height: 8 * ft
    }
  ];
  const badStoredPolygon = [
    { x: 0, y: 0 },
    { x: 30 * ft, y: 0 },
    { x: 0, y: 20 * ft },
    { x: 30 * ft, y: 20 * ft }
  ];
  return {
    unit: 'cm',
    floor: {
      walls: walls,
      rooms: [
        {
          id: 'r1',
          name: 'Basement',
          walls: ['w1', 'w2', 'w3', 'w4', 'p1', 'p2'],
          polygon: badStoredPolygon
        }
      ]
    }
  };
}

const basementPlan = basementStubRegressionPlan(0);
const basementMetrics = bridge.layoutMetrics(basementPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(!basementMetrics.geometry_unmeasurable, 'Basement stubs with bad stored polygon is measurable');
assert.strictEqual(basementMetrics.area_sqft, 583.7, 'Interior floor area from outer loop (30×20 ft shell inset; was 600 centerline)');
assert.strictEqual(basementMetrics.outside_area_sqft, 616.51, '30×20 exterior footprint without 1/16′ vertex rounding');
assert.strictEqual(basementMetrics.partition_lf, 19.67, 'Interior stub partitions total 19.67 lf (T-junction trim)');
const basementLoop = bridge.extractOuterLoopAndPartitions(basementPlan.floor.walls, 1.0);
assert.strictEqual(basementLoop.partition_walls.length, 2, 'Two interior stub partitions detected');
assert.ok(
  bridge.shouldReplaceRoomPolygonWithOuterLoop(
    basementPlan.floor.rooms[0].polygon,
    basementLoop,
    1.0,
    basementPlan.floor.rooms[0].walls
  ),
  'Self-intersecting stored polygon is replaced by outer loop'
);

const basementOffsetPlan = basementStubRegressionPlan(15);
const basementOffsetMetrics = bridge.layoutMetrics(basementOffsetPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(!basementOffsetMetrics.geometry_unmeasurable, 'Stub endpoints 15cm off perimeter still measurable');
assert.strictEqual(basementOffsetMetrics.area_sqft, 583.7, 'Offset stubs still yield interior shell floor area');
const offsetLoop = bridge.extractOuterLoopAndPartitions(basementOffsetPlan.floor.walls, 1.0);
assert.strictEqual(offsetLoop.partition_walls.length, 2, 'Near-miss T-junction stubs count as partitions');

// Test 10: Editor-drawn plan with float noise (hand0 regression)
const hand0Plan = JSON.parse(
  require('fs').readFileSync(require('path').join(__dirname, '../fixtures/openplan3d-hand0-drawn-plan.json'), 'utf8')
);
const hand0Metrics = bridge.layoutMetrics(hand0Plan, null, { wall_height_ft: 8.0 });
assert.strictEqual(hand0Metrics.area_sqft, 1307.52,
  'Drawn basement interior floor area (was ~1346 centerline / 1300–1360 range)');
assert.strictEqual(hand0Metrics.perimeter_lf, 158.79, 'Drawn basement clear perimeter (interior-face ring walk)');
(function partitionSpanFinishBaseboard() {
  var cm = 30.48;
  var plan = {
    format: 'openplan3d',
    units: 'cm',
    floor: {
      walls: [
        { id: 'w1', start: { x: 0, y: 0 }, end: { x: 20 * cm, y: 0 }, thickness: 11.43, height: 243.84 },
        { id: 'w2', start: { x: 20 * cm, y: 0 }, end: { x: 20 * cm, y: 10 * cm }, thickness: 11.43, height: 243.84 },
        { id: 'w3', start: { x: 20 * cm, y: 10 * cm }, end: { x: 0, y: 10 * cm }, thickness: 11.43, height: 243.84 },
        { id: 'w4', start: { x: 0, y: 10 * cm }, end: { x: 0, y: 0 }, thickness: 11.43, height: 243.84 },
        { id: 'p1', start: { x: 10 * cm, y: 0 }, end: { x: 10 * cm, y: 10 * cm }, thickness: 11.43, height: 243.84 },
      ],
      rooms: [],
    },
  };
  var metrics0 = bridge.layoutMetrics(plan, null, { wall_height_ft: 8.0 });
  var boundarySet = {};
  (metrics0.boundary_wall_ids || []).forEach(function (id) { boundarySet[String(id)] = true; });
  var wf = {};
  (metrics0.walls || []).forEach(function (w) {
    var isBoundary = !!boundarySet[String(w.id)];
    wf[w.id] = {
      drywall: true,
      baseboard: true,
      insulation: isBoundary,
      studs: !isBoundary,
    };
  });
  var finished = ext.applyToMetrics(metrics0, Object.assign({}, plan, { zanini: { wallFinishes: wf } }), {}, cm);
  assert.strictEqual(finished.finish_baseboard_lf, 77.0,
    '20x10 partition: boundary 57.75 lf (58.5 - 2x0.375 butt) + partition both faces 19.25 lf');
  assert.strictEqual(finished.finish_drywall_sqft, 616,
    '20x10 shell drywall (perimeter + partition both faces) × 8 ft');
  assert.strictEqual(finished.finish_insulation_sqft, 462,
    '20x10 shell insulation on boundary faces only');
  var twoRoomPlan = {
    format: 'openplan3d',
    units: 'cm',
    floor: {
      walls: plan.floor.walls,
      rooms: [
        { id: 'ra', name: 'West', walls: ['w1', 'p1', 'w4', 'w3'] },
        { id: 'rb', name: 'East', walls: ['w1', 'w2', 'w3', 'p1'] },
      ],
    },
  };
  var metrics2 = bridge.layoutMetrics(twoRoomPlan, null, { wall_height_ft: 8.0 });
  var finished2 = ext.applyToMetrics(metrics2, Object.assign({}, twoRoomPlan, { zanini: { wallFinishes: wf } }), {}, cm);
  assert.strictEqual(finished2.finish_drywall_sqft, 616, 'Two saved rooms: floor drywall dedupes per wall id');
  assert.strictEqual(finished2.finish_insulation_sqft, 462, 'Two saved rooms: floor insulation dedupes per wall id');
  assert.strictEqual(finished2.finish_baseboard_lf, 77, 'Two saved rooms: floor baseboard includes partition both faces');
})();

(function collinearNorthSplitLengths() {
  var cm = 30.48;
  var t = 11.43;
  var plan = {
    format: 'openplan3d',
    units: 'cm',
    floor: {
      walls: [
        { id: 'n1', start: { x: 0, y: 0 }, end: { x: 3 * cm, y: 0 }, thickness: t, height: 243.84 },
        { id: 'n2', start: { x: 3 * cm, y: 0 }, end: { x: 7 * cm, y: 0 }, thickness: t, height: 243.84 },
        { id: 'n3', start: { x: 7 * cm, y: 0 }, end: { x: 10 * cm, y: 0 }, thickness: t, height: 243.84 },
        { id: 'e', start: { x: 10 * cm, y: 0 }, end: { x: 10 * cm, y: 12 * cm }, thickness: t, height: 243.84 },
        { id: 's', start: { x: 10 * cm, y: 12 * cm }, end: { x: 0, y: 12 * cm }, thickness: t, height: 243.84 },
        { id: 'w', start: { x: 0, y: 12 * cm }, end: { x: 0, y: 0 }, thickness: t, height: 243.84 },
      ],
      rooms: [{ id: 'r1', walls: ['n1', 'n2', 'n3', 'e', 's', 'w'] }],
    },
  };
  var m = bridge.layoutMetrics(plan, 'r1', { wall_height_ft: 8.0 });
  var byId = {};
  (m.walls || []).forEach(function (w) { byId[w.id] = w.length_lf; });
  assert.strictEqual(byId.n1, 2.81, 'north 3 ft collinear end keeps remainder share');
  assert.strictEqual(byId.n2, 4.0, 'north 4 ft middle segment uses centerline lf');
  assert.strictEqual(byId.n3, 2.81, 'north 3 ft collinear end keeps remainder share');
  assert.strictEqual(m.perimeter_lf, 42.5, 'perimeter unchanged with collinear split');
})();

(function hand0FinishPins() {
  var boundarySet = {};
  (hand0Metrics.boundary_wall_ids || []).forEach(function (id) { boundarySet[String(id)] = true; });
  var wf = {};
  (hand0Metrics.walls || []).forEach(function (w) {
    var isBoundary = !!boundarySet[String(w.id)];
    wf[w.id] = {
      drywall: true,
      baseboard: true,
      insulation: isBoundary,
      studs: !isBoundary,
    };
  });
  var finished = ext.applyToMetrics(hand0Metrics, Object.assign({}, hand0Plan, { zanini: { wallFinishes: wf } }), {}, 30.48);
  assert.strictEqual(finished.finish_baseboard_lf, 199.53, 'hand0 finish_baseboard_lf with vertex + collinear-split butt rules');
  assert.strictEqual(finished.finish_drywall_sqft, 1596.22, 'hand0 finish_drywall_sqft with vertex + collinear-split butt rules');
  const interiorGeom = require('../../public/js/room-area-interior-geometry.js');
  const hand0Floor = hand0Plan.floors && hand0Plan.floors[0] ? hand0Plan.floors[0] : hand0Plan.floor;
  const hand0Walls = (hand0Floor && hand0Floor.walls) || [];
  const ed9Wall = hand0Walls.find(function (w) { return w.id === 'ed9b3bxc'; });
  const x83Wall = hand0Walls.find(function (w) { return w.id === 'x83nxa0q'; });
  assert.ok(ed9Wall && x83Wall, 'hand0 north collinear walls exist in fixture');
  const cmPer = bridge.cmPerUnitForPlan(hand0Plan);
  const cmPerFt = bridge.CM_PER_FOOT || 30.48;
  assert.ok(
    Math.abs(interiorGeom.wallPlanEdgeLengthCm(ed9Wall, hand0Walls, cmPer, true) / cmPerFt - 3.035) < 0.02,
    'hand0 top stub ed9b3bxc interior edge span ~3.035 lf'
  );
  assert.ok(
    Math.abs(interiorGeom.wallPlanEdgeLengthCm(x83Wall, hand0Walls, cmPer, true) / cmPerFt - 15.338) < 0.02,
    'hand0 top run x83nxa0q interior edge span ~15.338 lf'
  );
})();
assert.strictEqual(hand0Metrics.outside_area_sqft, 1386.63, 'Drawn basement exterior footprint (unrounded)');
assert.ok(!hand0Metrics.geometry_unmeasurable, 'Drawn plan must not be geometry_unmeasurable');

// Test 11: True bowtie polygon flags unmeasurable but keeps perimeter baseboard
const bowtieOnlyPlan = {
  unit: 'cm',
  floor: {
    rooms: [{ id: 'r1', polygon: [{ x: 0, y: 0 }, { x: 304.8, y: 304.8 }, { x: 304.8, y: 0 }, { x: 0, y: 304.8 }] }],
    walls: [
      { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 } },
      { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 304.8 } },
      { id: 'w3', start: { x: 304.8, y: 304.8 }, end: { x: 0, y: 304.8 } },
      { id: 'w4', start: { x: 0, y: 304.8 }, end: { x: 0, y: 0 } }
    ]
  }
};
const bowtieMetrics = bridge.layoutMetrics(bowtieOnlyPlan, 'r1', { wall_height_ft: 8.0 });
assert.strictEqual(bowtieMetrics.area_sqft, 92.64, '10×10 ft wall loop replaces bowtie polygon (92.64 sf interior; 107.64 sf exterior)');
assert.strictEqual(bowtieMetrics.outside_area_sqft, 107.64, '10×10 exterior footprint from interior ring');
assert.ok(!bowtieMetrics.geometry_unmeasurable, 'Bowtie stored polygon must not block measurement when walls form a closed loop');
assert.ok(bowtieMetrics.baseboard_lf > 0, 'Recovered loop should expose baseboard LF from outline');

// Test 12: Independent endpoint noise ±1.5–3 cm (hand0 drawn perimeter)
function jitterIndependentEndpoints(plan, maxOffsetCm) {
  var copy = JSON.parse(JSON.stringify(plan));
  var walls = copy.floor && copy.floor.walls ? copy.floor.walls : copy.walls;
  if (!walls) return copy;
  for (var wi = 0; wi < walls.length; wi++) {
    var w = walls[wi];
    if (!w || !w.start || !w.end) continue;
    w.start.x = Number(w.start.x || 0) + (Math.random() - 0.5) * 2 * maxOffsetCm;
    w.start.y = Number(w.start.y || 0) + (Math.random() - 0.5) * 2 * maxOffsetCm;
    w.end.x = Number(w.end.x || 0) + (Math.random() - 0.5) * 2 * maxOffsetCm;
    w.end.y = Number(w.end.y || 0) + (Math.random() - 0.5) * 2 * maxOffsetCm;
  }
  return copy;
}

var independentOffsetsCm = [1.5, 2.5, 3.0];
for (var oi = 0; oi < independentOffsetsCm.length; oi++) {
  var maxCm = independentOffsetsCm[oi];
  for (var trial = 0; trial < 8; trial++) {
    var noisyIndependent = jitterIndependentEndpoints(hand0Plan, maxCm);
    var independentMetrics = bridge.layoutMetrics(noisyIndependent, null, { wall_height_ft: 8.0 });
    assert.ok(
      independentMetrics.area_sqft > 1297 && independentMetrics.area_sqft < 1322 && !independentMetrics.geometry_unmeasurable,
      'Independent ±' + maxCm + ' cm jitter trial ' + trial + ' must stay measurable (~1307.52 sf interior), got ' + independentMetrics.area_sqft
    );
  }
}

// Test 13: Unmeasurable when outer loop cannot be recovered (never silent zero)
var brokenPlan = jitterIndependentEndpoints(hand0Plan, 25);
brokenPlan.floor.rooms = [];
delete brokenPlan.floors;
var brokenMetrics = bridge.layoutMetrics(brokenPlan, null, { wall_height_ft: 8.0 });
assert.strictEqual(brokenMetrics.geometry_unmeasurable, true, 'Extreme noise must flag geometry_unmeasurable');
assert.strictEqual(brokenMetrics.area_sqft, 0, 'Extreme noise must not report a false positive area');

// Test 14: Editor live plan uses plan.floors when plan.floor is stale/empty (Jonas live zero takeoffs)
var staleFloorPlan = JSON.parse(JSON.stringify(hand0Plan));
staleFloorPlan.floor.walls = [];
staleFloorPlan.floor.rooms = [{ id: 'r1', name: 'B', polygon: [] }];
var staleFloorMetrics = bridge.layoutMetrics(staleFloorPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(staleFloorMetrics.area_sqft > 1300, 'Stale empty plan.floor must not zero live area; got ' + staleFloorMetrics.area_sqft);
assert.ok(!staleFloorMetrics.geometry_unmeasurable, 'Stale plan.floor must not mark geometry unmeasurable');

// Test 15: Closed outer + two interior partitions stays measurable
var twoPartitionPlan = JSON.parse(JSON.stringify(hand0Plan));
assert.ok(twoPartitionPlan.floor.walls.length >= 18, 'hand0 fixture includes two interior partitions');
var twoPartMetrics = bridge.layoutMetrics(twoPartitionPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(twoPartMetrics.area_sqft > 1300, 'Two interior partitions must keep floor area > 0; got ' + twoPartMetrics.area_sqft);

// Test 16: Removing partitions with invalid stored polygon still uses outer loop (false zero save)
var removedPartitionPlan = JSON.parse(JSON.stringify(hand0Plan));
removedPartitionPlan.floor.walls = removedPartitionPlan.floor.walls.filter(function (w) {
  return w.id !== 'mclt5ix7';
});
removedPartitionPlan.floors[0].walls = removedPartitionPlan.floor.walls;
removedPartitionPlan.floor.rooms[0].polygon = [
  { x: 0, y: 0 }, { x: 100, y: 100 }, { x: 100, y: 0 }, { x: 0, y: 100 }
];
removedPartitionPlan.floors[0].rooms[0].polygon = removedPartitionPlan.floor.rooms[0].polygon;
var removedMetrics = bridge.layoutMetrics(removedPartitionPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(removedMetrics.area_sqft > 1300, 'Removing a partition must not zero area when outer loop is closed; got ' + removedMetrics.area_sqft);
assert.ok(!removedMetrics.geometry_unmeasurable, 'Removing a partition must stay measurable with bad stored polygon');

// Test 17: Editor live payload — walls only on plan.floor when plan.floors is absent
var editorSplitPlan = JSON.parse(JSON.stringify(hand0Plan));
editorSplitPlan.floor.walls = editorSplitPlan.floors[0].walls.slice();
delete editorSplitPlan.floors;
var splitMetrics = bridge.layoutMetrics(editorSplitPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(splitMetrics.area_sqft > 1300, 'Must use plan.floor when plan.floors is missing; got ' + splitMetrics.area_sqft);
assert.ok(splitMetrics.perimeter_lf > 0, 'Perimeter must stay non-zero when floor walls are on plan.floor');

// Test 20: All walls deleted on floors[0] must not resurrect stale plan.floor (R1)
var allDeletedPlan = JSON.parse(JSON.stringify(hand0Plan));
allDeletedPlan.floors[0].walls = [];
var allDeletedResolved = bridge.resolveFloor(allDeletedPlan);
assert.strictEqual(allDeletedResolved.walls.length, 0, 'empty active floors[0] must resolve to zero walls');
var allDeletedMetrics = bridge.layoutMetrics(allDeletedPlan, 'r1', { wall_height_ft: 8.0 });
assert.strictEqual(allDeletedMetrics.area_sqft, 0, 'all walls deleted must measure zero area for save guards');

// Test 21: New empty active f2 must not use stale plan.floor (R2)
var emptyActiveF2 = JSON.parse(JSON.stringify(hand0Plan));
emptyActiveF2.activeFloorId = 'f2';
emptyActiveF2.floors.push({ id: 'f2', walls: [], rooms: [{ id: 'r2', name: 'New', polygon: [] }] });
var emptyF2Metrics = bridge.layoutMetrics(emptyActiveF2, 'r2', { wall_height_ft: 8.0 });
assert.strictEqual(emptyF2Metrics.area_sqft, 0, 'empty active f2 must measure 0, not stale plan.floor');

// Test 18: Deleted wall on plan.floors[0] must win over stale extra wall on plan.floor (D1)
var deletedInFloors0 = JSON.parse(JSON.stringify(hand0Plan));
var deleteWallId = 'mclt5ix7';
deletedInFloors0.floors[0].walls = deletedInFloors0.floors[0].walls.filter(function (w) {
  return w.id !== deleteWallId;
});
assert.ok(
  deletedInFloors0.floor.walls.some(function (w) {
    return w.id === deleteWallId;
  }),
  'fixture: plan.floor stays stale with deleted wall still present'
);
var deletedResolved = bridge.resolveFloor(deletedInFloors0);
assert.strictEqual(
  deletedResolved.walls.length,
  deletedInFloors0.floors[0].walls.length,
  'resolveFloor must use live floors[0] after delete, not stale plan.floor'
);
assert.ok(
  !deletedResolved.walls.some(function (w) {
    return w.id === deleteWallId;
  }),
  'deleted wall must not resurrect from stale plan.floor'
);
var deletedMetrics = bridge.layoutMetrics(deletedInFloors0, 'r1', { wall_height_ft: 8.0 });
assert.ok(deletedMetrics.area_sqft > 1300, 'deleted interior wall on floors[0] must keep positive area');

// Test 19: activeFloorId f2 must beat stale plan.floor snapshot (D5)
var activeF2Plan = JSON.parse(JSON.stringify(hand0Plan));
activeF2Plan.activeFloorId = 'f2';
activeF2Plan.floors.push({
  id: 'f2',
  walls: [
    { id: 'a1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 } },
    { id: 'a2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 304.8 } },
    { id: 'a3', start: { x: 304.8, y: 304.8 }, end: { x: 0, y: 304.8 } },
    { id: 'a4', start: { x: 0, y: 304.8 }, end: { x: 0, y: 0 } }
  ],
  rooms: [{ id: 'r2', name: 'Upstairs', polygon: [] }]
});
var activeF2Metrics = bridge.layoutMetrics(activeF2Plan, 'r2', { wall_height_ft: 8.0 });
assert.strictEqual(activeF2Metrics.area_sqft, 92.64, '10×10 ft centerline upstairs room (92.64 sf interior; was ~100 centerline)');

console.log('All openPlan3D Measurement Bridge JavaScript parity tests PASSED!');

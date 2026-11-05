'use strict';

const assert = require('assert');
const lib = require('../../public/js/room-area-lib.js');
const bridge = require('../../public/js/openplan3d-measurement-bridge.js');

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
assert.strictEqual(rectMetrics.area_sqft, 120.0, '10x12 rectangle area is 120 sqft');
assert.strictEqual(rectMetrics.perimeter_lf, 44.0, '10x12 rectangle perimeter is 44 ft');
assert.strictEqual(rectMetrics.baseboard_lf, 44.0, 'No openings, baseboard equals perimeter');
assert.strictEqual(rectMetrics.wall_area_sqft, 352.0, '44 * 8 = 352 sqft gross/net wall area');
assert.strictEqual(rectMetrics.ceiling_area_sqft, 120.0);
assert.strictEqual(rectMetrics.inside_corners, 4);
assert.strictEqual(rectMetrics.outside_corners, 0);

// Test 3: Rectangle with door (32"x80") and window (36"x48", 4-side casing)
// Door: 32" = 81.28 cm, 80" = 203.2 cm
// Window: 36" = 91.44 cm, 48" = 121.92 cm
const rectWithOpenings = {
  format: 'openplan3d',
  units: 'cm',
  walls: rectPlan.walls,
  rooms: rectPlan.rooms,
  doors: [
    { id: 'd1', wallId: 'w1', position: 0.5, width: 81.28, height: 203.2, type: 'single', details: { casing_sides: 2 } }
  ],
  windows: [
    { id: 'win1', wallId: 'w2', position: 0.5, width: 91.44, height: 121.92, sillHeight: 91.44, type: 'standard', details: { casing_sides: 4 } }
  ]
};

const rectWithOpeningsMetrics = bridge.layoutMetrics(rectWithOpenings, 'r1', { wall_height_ft: 8.0 });
assert.strictEqual(rectWithOpeningsMetrics.door_count, 1);
assert.strictEqual(rectWithOpeningsMetrics.window_count, 1);
assert.strictEqual(rectWithOpeningsMetrics.drywall_openings, 2);
// Door casing: 2 * (32 + 2*80) / 12 = 2 * 192 / 12 = 32.0 LF
// Window casing: (2*36 + 2*48) / 12 = 168 / 12 = 14.0 LF
// Total casing: 46.0 LF
assert.strictEqual(rectWithOpeningsMetrics.casing_lf, 46.0, 'Total casing LF is 46.0');
// Baseboard: 44 - (32 / 12 = 2.67) = 41.33 LF
assert.strictEqual(rectWithOpeningsMetrics.baseboard_lf, 41.33, 'Baseboard is 41.33 LF');

// Door area: (32/12) * (80/12) = 17.78 sqft
// Window area: (36/12) * (48/12) = 12.00 sqft
// Net wall area: 352 - 17.78 - 12.00 = 322.22 sqft
assert.strictEqual(rectWithOpeningsMetrics.wall_area_sqft, 322.22, 'Net wall area is 322.22 sqft');

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
assert.strictEqual(lMetrics.area_sqft, 168.0, 'L shape area is 168 sqft');
assert.strictEqual(lMetrics.perimeter_lf, 56.0, 'L shape perimeter is 56 ft');
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
assert.strictEqual(lPartMetrics.area_sqft, 300.0, 'Floor area matches L-shape without partitions (300 sq ft)');
assert.strictEqual(lPartMetrics.perimeter_lf, 80.0, 'Perimeter matches outer loop (80 lf)');
assert.strictEqual(lPartMetrics.net_wall_area_sqft, 832.0, 'Net wall area includes both faces of partitions (832 sq ft)');
assert.strictEqual(lPartMetrics.walls.length, 8, '8 walls total returned');

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
assert.strictEqual(basementMetrics.area_sqft, 600.0, 'Floor area uses outer loop (30x20 = 600 sq ft)');
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
assert.strictEqual(basementOffsetMetrics.area_sqft, 600.0, 'Offset stubs still yield 600 sq ft floor area');
const offsetLoop = bridge.extractOuterLoopAndPartitions(basementOffsetPlan.floor.walls, 1.0);
assert.strictEqual(offsetLoop.partition_walls.length, 2, 'Near-miss T-junction stubs count as partitions');

// Test 10: Editor-drawn plan with float noise (hand0 regression)
const hand0Plan = JSON.parse(
  require('fs').readFileSync(require('path').join(__dirname, '../fixtures/openplan3d-hand0-drawn-plan.json'), 'utf8')
);
const hand0Metrics = bridge.layoutMetrics(hand0Plan, null, { wall_height_ft: 8.0 });
assert.ok(hand0Metrics.area_sqft > 1300 && hand0Metrics.area_sqft < 1360,
  'Drawn basement plan area ~1346 sq ft, got ' + hand0Metrics.area_sqft);
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
assert.strictEqual(bowtieMetrics.area_sqft, 100.0, 'Valid wall outer loop replaces bowtie stored polygon');
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
      independentMetrics.area_sqft > 1300 && independentMetrics.area_sqft < 1380 && !independentMetrics.geometry_unmeasurable,
      'Independent ±' + maxCm + ' cm jitter trial ' + trial + ' must stay measurable (~1346 sq ft), got ' + independentMetrics.area_sqft
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

// Test 17: Editor live payload — full plan.floor but stale empty plan.floors[0] (Jonas live zero)
var editorSplitPlan = JSON.parse(JSON.stringify(hand0Plan));
editorSplitPlan.floor.walls = editorSplitPlan.floors[0].walls.slice();
editorSplitPlan.floors[0].walls = [];
var splitMetrics = bridge.layoutMetrics(editorSplitPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(splitMetrics.area_sqft > 1300, 'Must use plan.floor when floors[0].walls is stale empty; got ' + splitMetrics.area_sqft);
assert.ok(splitMetrics.perimeter_lf > 0, 'Perimeter must stay non-zero when floor walls are on plan.floor');

console.log('All openPlan3D Measurement Bridge JavaScript parity tests PASSED!');

'use strict';

const assert = require('assert');
require('../../public/js/openplan3d-zanini-extensions.js');
const ext = require('../../public/js/openplan3d-zanini-extensions.js');
require('../../public/js/openplan3d-measurement-bridge.js');
const bridge = require('../../public/js/openplan3d-measurement-bridge.js');

console.log('openplan3d-zanini-extensions tests');

assert.strictEqual(typeof ext.rectsUnionAreaSqFt, 'function');
assert.strictEqual(typeof ext.zoneAreaSqFt, 'function');
assert.strictEqual(typeof ext.collectZoneMemberLegacyRects, 'function');

const ft = ext.CM_PER_FOOT;
const lShapeZone = {
  id: 'z-merge',
  x1: 0,
  y1: 0,
  x2: 15 * ft,
  y2: 15 * ft,
  rects: [
    { x1: 0, y1: 0, x2: 10 * ft, y2: 15 * ft },
    { x1: 10 * ft, y1: 10 * ft, x2: 15 * ft, y2: 15 * ft }
  ]
};
const lShapeArea = ext.zoneAreaSqFt(lShapeZone, [lShapeZone], ft);
assert.ok(Math.abs(lShapeArea - 175) < 0.05, 'merged zone union area must exclude bbox double-count (got ' + lShapeArea + ')');
assert.ok(lShapeArea < 225, 'union must be less than enclosing bbox area');

const union = ext.rectsUnionAreaSqFt([
  { x1: 0, y1: 0, x2: 100, y2: 100 },
  { x1: 50, y1: 50, x2: 150, y2: 150 },
], 1.0);
assert.ok(union > 0, 'union area should be positive');

const metrics = {
  area_sqft: 96.88,
  inside_area_sqft: 96.88,
  ceiling_area_sqft: 96.88,
  flooring_waste_percent: 0,
  flooring_box_sqft: 20,
  casing_lf: 12.5,
  boundary_wall_ids: ['w1', 'w2'],
  walls: [
    { id: 'w1', length_lf: 10, net_area_sqft: 80 },
    { id: 'w2', length_lf: 10, net_area_sqft: 80 },
    { id: 'w3', length_lf: 8, net_area_sqft: 128 },
  ],
};

const excludedPlan = {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    excluded_area_zones: [{ x1: 0, y1: 0, x2: 100, y2: 100 }],
  },
};

const excludedOut = ext.applyToMetrics(Object.assign({}, metrics), excludedPlan, {}, { unit: 'cm' });
assert.ok(excludedOut.excluded_area_sqft > 0);
assert.ok(excludedOut.area_sqft < metrics.area_sqft);

const bulkheadOut = ext.applyToMetrics(Object.assign({}, metrics), {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    bulkheads: [{
      rect: { x1: 0, y1: 0, x2: 304.8, y2: 30.48 },
      drop_height_ft: 2,
      width_ft: 1,
      against_wall: true,
    }],
  },
}, {}, { unit: 'cm' });

assert.strictEqual(bulkheadOut.bulkhead_framing_lf, 27);
assert.strictEqual(bulkheadOut.bulkhead_drywall_sqft, 30);
assert.strictEqual(bulkheadOut.bulkhead_corner_bead_lf, 10);

const mergedFinishes = ext.mergeWallFinishes(
  { w1: { studs: false, drywall: true } },
  {}
);
assert.strictEqual(mergedFinishes.w1.studs, false, 'empty incoming must not drop studs:false');

const ackMerge = ext.mergeZaniniSnapshots(
  { wallFinishes: { w1: { studs: false } } },
  { wallFinishes: {} }
);
assert.strictEqual(ackMerge.wallFinishes.w1.studs, false, 'editor scaffold zanini must not wipe saved finishes');

const corner = ext.bulkheadTakeoff(10, 2, 1, true);
assert.strictEqual(corner.drywall_sqft, 30);
assert.strictEqual(corner.corner_bead_lf, 10);

const measureOnly = ext.applyToMetrics(Object.assign({}, metrics), {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    zones: [{ id: 'z1', exclude: false, x1: 0, y1: 0, x2: 50, y2: 50 }],
  },
}, {}, { unit: 'cm' });
assert.strictEqual(measureOnly.area_sqft, metrics.area_sqft);
assert.strictEqual(measureOnly.excluded_area_sqft, 0);

const partitionBb = ext.applyToMetrics(Object.assign({}, {
  area_sqft: 96.88,
  casing_lf: 12.5,
  boundary_wall_ids: ['w1'],
  walls: [
    { id: 'w1', length_lf: 10, net_area_sqft: 80 },
    { id: 'w3', length_lf: 8, net_area_sqft: 128 },
  ],
}), {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    wallFinishes: { w3: { baseboard: true } },
  },
}, {}, 1.0);
assert.strictEqual(partitionBb.finish_baseboard_lf, 26);

const finishOut = ext.applyToMetrics(Object.assign({}, metrics, { baseboard_lf: 171.99 }), { format: 'openplan3d', units: 'cm' }, {}, 1.0);
assert.strictEqual(finishOut.finish_drywall_sqft, 288);
assert.strictEqual(finishOut.finish_insulation_sqft, 160);
assert.strictEqual(finishOut.finish_stud_lf, 8);
assert.strictEqual(finishOut.finish_baseboard_lf, 171.99);

const roomPoly = [
  { x: 0, y: 0 },
  { x: 304.8, y: 0 },
  { x: 304.8, y: 304.8 },
  { x: 0, y: 304.8 },
];
const tenFtRect = { x0: 0, y0: 0, x1: 304.8, y1: 304.8 };
assert.strictEqual(ext.rectInsidePolygonAreaSqFt(tenFtRect, roomPoly, 1.0), 100.0);

const overlapMetrics = Object.assign({}, metrics, {
  area_sqft: 200,
  room_polygon_cm: roomPoly,
});
const overlapOut = ext.applyToMetrics(overlapMetrics, {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    zones: [
      { id: 'a', exclude: true, x1: 0, y1: 0, x2: 152.4, y2: 304.8 },
      { id: 'b', exclude: true, x1: 76.2, y1: 0, x2: 228.6, y2: 304.8 },
    ],
  },
}, {}, 1.0);
assert.strictEqual(overlapOut.excluded_area_sqft, 75.0);

const mergedZoneMetrics = Object.assign({}, metrics, {
  area_sqft: 400,
  room_polygon_cm: [
    { x: 0, y: 0 },
    { x: 400, y: 0 },
    { x: 400, y: 400 },
    { x: 0, y: 400 },
  ],
});
const mergedOut = ext.applyToMetrics(mergedZoneMetrics, {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    zones: [
      { id: 't', exclude: true, x1: 0, y1: 0, x2: 100, y2: 200 },
      { id: 's', exclude: true, x1: 100, y1: 0, x2: 200, y2: 100, mergedInto: 't' },
    ],
  },
}, {}, 1.0);
const lShapeUnion = ext.rectangleUnionAreaSqFt([
  { x0: 0, y0: 0, x1: 100, y1: 200 },
  { x0: 100, y0: 0, x1: 200, y1: 100 },
], 1.0);
assert.strictEqual(mergedOut.excluded_area_sqft, lShapeUnion);

const zoneA = { x1: 20 * ft, y1: 20 * ft, x2: 30 * ft, y2: 30 * ft };
const zoneB = { x1: 25 * ft, y1: 25 * ft, x2: 35 * ft, y2: 35 * ft };
const bigRoomPoly = [
  { x: 0, y: 0 },
  { x: 50 * ft, y: 0 },
  { x: 50 * ft, y: 50 * ft },
  { x: 0, y: 50 * ft },
];
const merge175Metrics = Object.assign({}, metrics, {
  area_sqft: 2500,
  room_polygon_cm: bigRoomPoly,
});
const merge175Out = ext.applyToMetrics(merge175Metrics, {
  format: 'openplan3d',
  units: 'cm',
  zanini: {
    zones: [
      {
        id: 't',
        exclude: true,
        x1: zoneA.x1,
        y1: zoneA.y1,
        x2: zoneA.x2,
        y2: zoneA.y2,
        rects: [zoneA, zoneB],
      },
      { id: 's', exclude: true, mergedInto: 't', x1: zoneB.x1, y1: zoneB.y1, x2: zoneB.x2, y2: zoneB.y2 },
    ],
  },
}, {}, 1.0);
assert.strictEqual(merge175Out.excluded_area_sqft, 175.0);

const merged = ext.mergeZaniniIntoPlan({ floors: [{ id: 'f1' }] }, { zones: [] });
assert.deepStrictEqual(merged.zanini, { zones: [] });

const rectPlan = {
  format: 'openplan3d',
  units: 'cm',
  floors: [{
    id: 'f1',
    walls: [
      { id: 'wN', start: { x: 0, y: 0 }, end: { x: 400, y: 0 }, thickness: 11.43, height: 243.84 },
      { id: 'wE', start: { x: 400, y: 0 }, end: { x: 400, y: 300 }, thickness: 11.43, height: 243.84 },
      { id: 'wS', start: { x: 400, y: 300 }, end: { x: 0, y: 300 }, thickness: 11.43, height: 243.84 },
      { id: 'wW', start: { x: 0, y: 300 }, end: { x: 0, y: 0 }, thickness: 11.43, height: 243.84 },
      { id: 'wP', start: { x: 200, y: 0 }, end: { x: 200, y: 300 }, thickness: 11.43, height: 243.84 },
    ],
    rooms: [{
      id: 'r1',
      walls: ['wN', 'wE', 'wS', 'wW', 'wP'],
      polygon: [
        { x: 0, y: 0 },
        { x: 200, y: 0 },
        { x: 200, y: 300 },
        { x: 0, y: 300 },
      ],
    }],
  }],
  activeFloorId: 'f1',
};

const outerMetrics = bridge.layoutMetrics(rectPlan, 'r1', { wall_height_ft: 8 });
assert.ok(outerMetrics.area_sqft > 80, 'outer polygon should exceed partial stored polygon area');
assert.ok(Array.isArray(outerMetrics.boundary_wall_ids));

// Test: Zone with multiple rects stays as multiple members (not bbox)
const multiRectZone = {
  id: 'multi',
  name: 'L-Zone',
  x1: 0,
  y1: 0,
  x2: 150, // bbox x2
  y2: 150, // bbox y2
  rects: [
    { x1: 0, y1: 0, x2: 100, y2: 50 },   // top bar: 100x50 = 5000
    { x1: 0, y1: 50, x2: 50, y2: 150 },  // left stem: 50x100 = 5000
  ]
};

const multiRectMembers = ext.collectZoneMemberLegacyRects(multiRectZone, [multiRectZone]);
assert.strictEqual(multiRectMembers.length, 2, 'L-zone must have 2 member rects, not 1 bbox');
const multiRectArea = ext.zoneAreaSqFt(multiRectZone, [multiRectZone], 1.0);
const bboxArea = 150 * 150 / (ft * ft); // would be 22500 / 929.03 = ~24.22 sq ft
const expectedUnionArea = (100 * 50 + 50 * 100) / (ft * ft); // 10000 / 929.03 = ~10.76 sq ft
assert.ok(Math.abs(multiRectArea - expectedUnionArea) < 0.1,
  'L-zone area must be union (~10.76 sf), not bbox (~24.22 sf); got ' + multiRectArea);
assert.ok(multiRectArea < bboxArea * 0.6,
  'L-zone area must be significantly less than bbox area');

// Test: Overlapping rects are deduplicated properly
const overlappingZone = {
  id: 'overlap',
  rects: [
    { x1: 0, y1: 0, x2: 100, y2: 100 },   // 10000 cm²
    { x1: 50, y1: 50, x2: 150, y2: 150 }, // 10000 cm², 2500 overlap
  ]
};
const overlapMembers = ext.collectZoneMemberLegacyRects(overlappingZone, [overlappingZone]);
assert.strictEqual(overlapMembers.length, 2, 'Overlapping rects should both be collected');
const overlapArea = ext.zoneAreaSqFt(overlappingZone, [overlappingZone], 1.0);
const expectedOverlapUnion = (10000 + 10000 - 2500) / (ft * ft); // 17500 / 929.03 = ~18.84 sf
assert.ok(Math.abs(overlapArea - expectedOverlapUnion) < 0.1,
  'Overlapping zone area must account for overlap (~18.84 sf); got ' + overlapArea);

console.log('openplan3d-zanini-extensions: ok');

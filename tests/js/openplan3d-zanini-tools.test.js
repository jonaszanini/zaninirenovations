'use strict';

const assert = require('assert');

// Set env var to skip browser-only init
process.env.OPENPLAN3D_TOOLS_TEST = '1';

// Mock window and document for Node.js
global.window = {};
global.document = {
  readyState: 'complete',
  addEventListener: function() {},
  createElement: function() { return { style: {}, appendChild: function() {} }; },
  getElementById: function() { return null; },
  querySelector: function() { return null; },
  querySelectorAll: function() { return []; },
  body: { appendChild: function() {} },
  head: { appendChild: function() {} }
};

const tools = require('../../public/js/openplan3d-zanini-tools.js');

console.log('openplan3d-zanini-tools tests');

// Test axisRect normalization
const normalized = tools.axisRect({ x1: 100, y1: 50, x2: 0, y2: 150 });
assert.deepStrictEqual(normalized, { x1: 0, y1: 50, x2: 100, y2: 150 }, 'axisRect should normalize min/max');

// Test rectsOverlap
assert.strictEqual(tools.rectsOverlap(
  { x1: 0, y1: 0, x2: 100, y2: 100 },
  { x1: 50, y1: 50, x2: 150, y2: 150 }
), true, 'overlapping rects should be detected');

assert.strictEqual(tools.rectsOverlap(
  { x1: 0, y1: 0, x2: 100, y2: 100 },
  { x1: 200, y1: 200, x2: 300, y2: 300 }
), false, 'non-overlapping rects should not be detected');

// Test collectZoneMemberRects
const lZone = {
  id: 'l-zone',
  x1: 0, y1: 0, x2: 100, y2: 100,
  rects: [
    { x1: 0, y1: 0, x2: 100, y2: 50 },
    { x1: 0, y1: 50, x2: 50, y2: 150 }
  ]
};

const members = tools.collectZoneMemberRects(lZone, [lZone]);
assert.strictEqual(members.length, 2, 'L-zone should have 2 member rects');
assert.deepStrictEqual(members[0], { x1: 0, y1: 0, x2: 100, y2: 50 });
assert.deepStrictEqual(members[1], { x1: 0, y1: 50, x2: 50, y2: 150 });

// Test that legacy zone (no rects array) falls back to zone itself
const legacyZone = {
  id: 'legacy',
  x1: 10, y1: 20, x2: 110, y2: 120
};
const legacyMembers = tools.collectZoneMemberRects(legacyZone, [legacyZone]);
assert.strictEqual(legacyMembers.length, 1, 'Legacy zone should have 1 member rect');
assert.deepStrictEqual(legacyMembers[0], { x1: 10, y1: 20, x2: 110, y2: 120 });

// Test merged zones are collected
const parentZone = {
  id: 'parent',
  x1: 0, y1: 0, x2: 100, y2: 100
};
const childZone = {
  id: 'child',
  x1: 100, y1: 0, x2: 200, y2: 100,
  mergedInto: 'parent'
};
const allZones = [parentZone, childZone];
const mergedMembers = tools.collectZoneMemberRects(parentZone, allZones);
assert.strictEqual(mergedMembers.length, 2, 'Parent zone should include merged child');

// Test zoneUnionBounds still returns bbox (for hit testing)
const unionBounds = tools.zoneUnionBounds(lZone, [lZone]);
assert.strictEqual(unionBounds.x1, 0);
assert.strictEqual(unionBounds.y1, 0);
assert.strictEqual(unionBounds.x2, 100);
assert.strictEqual(unionBounds.y2, 150);

// Test findOverlappingZone
const zones = [
  { id: 'z1', x1: 0, y1: 0, x2: 100, y2: 100 },
  { id: 'z2', x1: 200, y1: 200, x2: 300, y2: 300 }
];
const overlap = tools.findOverlappingZone(zones, { x1: 50, y1: 50, x2: 150, y2: 150 });
assert.strictEqual(overlap.id, 'z1', 'Should find overlapping zone');
const noOverlap = tools.findOverlappingZone(zones, { x1: 400, y1: 400, x2: 500, y2: 500 });
assert.strictEqual(noOverlap, null, 'Should return null when no overlap');

const lZoneOverlap = {
  id: 'l1',
  x1: 0,
  y1: 0,
  x2: 100,
  y2: 150,
  rects: [
    { x1: 0, y1: 0, x2: 100, y2: 50 },
    { x1: 0, y1: 0, x2: 50, y2: 150 }
  ]
};
const notchRect = { x1: 80, y1: 80, x2: 90, y2: 90 };
const lOverlap = tools.findOverlappingZone([lZoneOverlap], notchRect);
assert.strictEqual(lOverlap, null, 'Rect inside L bbox but not touching member rects must not merge (D3)');

const ext = require('../../public/js/openplan3d-zanini-extensions.js');
global.window.OpenPlan3DZaniniExtensions = ext;
const ft = ext.CM_PER_FOOT;
const lZoneFt = {
  id: 'l-canvas',
  x1: 0,
  y1: 0,
  x2: 15 * ft,
  y2: 15 * ft,
  rects: [
    { x1: 0, y1: 0, x2: 10 * ft, y2: 15 * ft },
    { x1: 10 * ft, y1: 10 * ft, x2: 15 * ft, y2: 15 * ft }
  ]
};
const lAreaTools = tools.zoneAreaSqFt(lZoneFt, [lZoneFt], ft);
assert.ok(Math.abs(lAreaTools - 175) < 0.05, 'canvas label path must use L union area (~175 sf), got ' + lAreaTools);
assert.ok(lAreaTools < 225, 'must not use bbox area 225 sf for L-shaped zone');

// Test mergeRectIntoZone
const targetZone = {
  id: 'target',
  x1: 0, y1: 0, x2: 100, y2: 100
};
const zonesForMerge = [targetZone];
tools.mergeRectIntoZone(zonesForMerge, 'target', { x1: 50, y1: 50, x2: 150, y2: 150 });
assert.ok(Array.isArray(targetZone.rects), 'Target should have rects array after merge');
assert.strictEqual(targetZone.rects.length, 2, 'Target should have 2 rects after merge');
// Bbox should be updated for hit testing
assert.strictEqual(targetZone.x2, 150, 'Bbox should expand after merge');
assert.strictEqual(targetZone.y2, 150, 'Bbox should expand after merge');

console.log('openplan3d-zanini-tools: ok');

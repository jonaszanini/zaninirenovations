'use strict';

const assert = require('assert');
const bridge = require('../../public/js/openplan3d-measurement-bridge.js');
const fs = require('fs');
const path = require('path');

console.log('openplan3d-host-save-area tests');

function planHasDrawableWalls(plan) {
  var floor = bridge.resolveFloor(plan);
  var walls = floor && Array.isArray(floor.walls) ? floor.walls : [];
  var valid = 0;
  for (var i = 0; i < walls.length; i++) {
    var w = walls[i];
    if (w && w.start && w.end) valid++;
  }
  return valid >= 3;
}

function liveMetricsTrustworthy(metrics, plan) {
  if (!metrics || typeof metrics !== 'object') return false;
  if (metrics.geometry_unmeasurable) return false;
  if ((Number(metrics.area_sqft) || 0) > 0.01) return true;
  if (plan && planHasDrawableWalls(plan)) return false;
  return true;
}

function shouldConfirmReplaceStoredArea(storedArea, metrics, plan) {
  if (!(storedArea > 0)) return false;
  if (!liveMetricsTrustworthy(metrics, plan)) return false;
  var incomingArea = metrics && metrics.area_sqft != null ? Number(metrics.area_sqft) : 0;
  return !isFinite(incomingArea) || incomingArea <= 0 || incomingArea < storedArea * 0.5;
}

const hand0Plan = JSON.parse(
  fs.readFileSync(path.join(__dirname, '../fixtures/openplan3d-hand0-drawn-plan.json'), 'utf8')
);

const removedPartitionPlan = JSON.parse(JSON.stringify(hand0Plan));
removedPartitionPlan.floor.walls = removedPartitionPlan.floor.walls.filter(function (w) {
  return w.id !== 'mclt5ix7';
});
removedPartitionPlan.floors[0].walls = removedPartitionPlan.floor.walls;
removedPartitionPlan.floor.rooms[0].polygon = [
  { x: 0, y: 0 }, { x: 100, y: 100 }, { x: 100, y: 0 }, { x: 0, y: 100 }
];
removedPartitionPlan.floors[0].rooms[0].polygon = removedPartitionPlan.floor.rooms[0].polygon;

const removedMetrics = bridge.layoutMetrics(removedPartitionPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(removedMetrics.area_sqft > 1300, 'partition removal keeps positive area');
assert.strictEqual(
  shouldConfirmReplaceStoredArea(1515.42, removedMetrics, removedPartitionPlan),
  false,
  'must not confirm replace-with-zero when live area is still positive'
);

const staleFloorPlan = JSON.parse(JSON.stringify(hand0Plan));
staleFloorPlan.floor.walls = [];
const staleMetrics = bridge.layoutMetrics(staleFloorPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(staleMetrics.area_sqft > 1300, 'resolveFloor fix keeps stale floor from zeroing metrics');
assert.strictEqual(
  shouldConfirmReplaceStoredArea(1515.42, staleMetrics, staleFloorPlan),
  false,
  'stale floor must not trigger false zero replace prompt'
);

const editorSplitPlan = JSON.parse(JSON.stringify(hand0Plan));
editorSplitPlan.floor.walls = editorSplitPlan.floors[0].walls.slice();
editorSplitPlan.floors[0].walls = [];
const splitMetrics = bridge.layoutMetrics(editorSplitPlan, 'r1', { wall_height_ft: 8.0 });
assert.ok(splitMetrics.area_sqft > 1300, 'full plan.floor with empty floors[0] must not zero live area');
assert.strictEqual(
  shouldConfirmReplaceStoredArea(1515.42, splitMetrics, editorSplitPlan),
  false,
  'editor split payload must not trigger false zero replace prompt'
);

const emptyPlan = { unit: 'cm', floor: { walls: [], rooms: [] } };
const emptyMetrics = bridge.layoutMetrics(emptyPlan, null, { wall_height_ft: 8.0 });
assert.strictEqual(
  shouldConfirmReplaceStoredArea(1515.42, emptyMetrics, emptyPlan),
  true,
  'genuine empty drawing still requires confirm before replacing stored area with zero'
);

console.log('openplan3d-host-save-area: ok');

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const bridge = require('../../public/js/openplan3d-measurement-bridge.js');
const hand0 = JSON.parse(
  fs.readFileSync(path.join(__dirname, '../fixtures/openplan3d-hand0-drawn-plan.json'), 'utf8')
);

console.log('openplan3d-editor-room-fill tests');

assert.strictEqual(typeof bridge.syncEditorRoomBoundariesForCanvasFill, 'function');

function planWithAllWallsOnRoom(source) {
  const plan = JSON.parse(JSON.stringify(source));
  plan.activeFloorId = 'f1';
  plan.floors[0].id = 'f1';
  const ids = plan.floors[0].walls.map((w) => w.id);
  plan.floors[0].rooms = [{ id: 'r1', name: 'Basement', walls: ids.slice(), polygon: [] }];
  plan.floor = plan.floors[0];
  return plan;
}

function emptyRoomsPlan(source) {
  const plan = JSON.parse(JSON.stringify(source));
  plan.activeFloorId = 'f1';
  plan.floors[0].id = 'f1';
  plan.floors[0].rooms = [];
  plan.floor = JSON.parse(JSON.stringify(plan.floors[0]));
  plan.floor.rooms = [];
  return plan;
}

const loop = bridge.extractOuterLoopAndPartitions(hand0.floors[0].walls, 30.48);
assert.strictEqual(loop.partition_walls.length, 2, 'hand0 has two interior partitions');

const emptyTwoStubs = emptyRoomsPlan(hand0);
assert.strictEqual(emptyTwoStubs.floors[0].rooms.length, 0, 'repro: vendor leaves rooms empty while drawing');
const created = bridge.syncEditorRoomBoundariesForCanvasFill(emptyTwoStubs);
assert.strictEqual(created.changed, true, 'must persist explicit room when rooms[] is empty');
assert.strictEqual(created.plan.floors[0].rooms.length, 1, 'must create one persisted room');
assert.ok(
  created.plan.floors[0].rooms[0].walls.length >= loop.boundary_wall_ids.length,
  'persisted room.walls must include partition ids for vendor wall filter'
);
assert.ok(created.plan.floors[0].rooms[0].polygon.length >= 3, 'persisted room needs polygon for fill');
assert.ok(
  typeof created.plan.floors[0].rooms[0].area === 'number' && isFinite(created.plan.floors[0].rooms[0].area),
  'persisted room needs vendor area (m²)'
);
assert.strictEqual(created.plan.floors[0].rooms[0].floorTexture, 'hardwood');

const vendorStyle = planWithAllWallsOnRoom(hand0);
const beforeMetrics = bridge.layoutMetrics(vendorStyle, 'r1', { wall_height_ft: 8.0 });
assert.ok(beforeMetrics.area_sqft > 1300, 'takeoffs stay positive with vendor-style room.walls');

const synced = bridge.syncEditorRoomBoundariesForCanvasFill(vendorStyle);
assert.strictEqual(synced.changed, false, 'must not rewrite saved room id/name/walls on load');
assert.strictEqual(synced.plan.floors[0].rooms[0].id, 'r1');
assert.strictEqual(synced.plan.floors[0].rooms[0].name, 'Basement');
assert.strictEqual(
  synced.plan.floors[0].rooms[0].walls.length,
  vendorStyle.floors[0].walls.length,
  'saved room.walls must stay untouched'
);

const ephemeral = bridge.canvasFillRoomEntries(emptyTwoStubs);
assert.ok(ephemeral.length >= 1, 'live-edit canvas fill must expose polygons without persisting');
assert.ok(ephemeral[0].polygon.length >= 3);
assert.ok(typeof ephemeral[0].room.area === 'number' && isFinite(ephemeral[0].room.area));
assert.ok(ephemeral[0].room.walls.length >= emptyTwoStubs.floors[0].walls.length - 1);
assert.strictEqual(ephemeral[0].room.floorTexture, 'hardwood');
assert.strictEqual(bridge.canvasFillRoomEntries(vendorStyle).length, 0, 'skip ephemeral when rooms[] populated');

const again = bridge.syncEditorRoomBoundariesForCanvasFill(vendorStyle);
assert.strictEqual(again.changed, false, 'second sync must be idempotent');

const perimeterOnly = emptyRoomsPlan(hand0);
perimeterOnly.floors[0].walls = perimeterOnly.floors[0].walls.filter(
  (w) => w.id !== 'mclt5ix7' && w.id !== 'z9ajm00u'
);
perimeterOnly.floor.walls = perimeterOnly.floors[0].walls;
const perimeterSync = bridge.syncEditorRoomBoundariesForCanvasFill(perimeterOnly);
assert.strictEqual(perimeterSync.changed, true, 'closed perimeter with empty rooms[] should persist explicit room early');
assert.strictEqual(perimeterSync.plan.floors[0].rooms.length, 1);

const CM = bridge.CM_PER_FOOT;
const side = 40 * CM;
const splitPlan = {
  unit: 'cm',
  activeFloorId: 'f1',
  floors: [
    {
      id: 'f1',
      walls: [
        { id: 'w1', start: { x: 0, y: 0 }, end: { x: side, y: 0 } },
        { id: 'w2', start: { x: side, y: 0 }, end: { x: side, y: side } },
        { id: 'w3', start: { x: side, y: side }, end: { x: 0, y: side } },
        { id: 'w4', start: { x: 0, y: side }, end: { x: 0, y: 0 } },
        { id: 'p1', start: { x: side / 2, y: 0 }, end: { x: side / 2, y: side } }
      ],
      rooms: []
    }
  ]
};
splitPlan.floor = splitPlan.floors[0];
const splitSync = bridge.syncEditorRoomBoundariesForCanvasFill(splitPlan);
assert.strictEqual(splitSync.changed, true, 'spanning partition must persist multi-room topology');
assert.strictEqual(splitSync.plan.floors[0].rooms.length, 2, 'must not collapse to one outer room');
const areas = splitSync.plan.floors[0].rooms.map((r) =>
  bridge.layoutMetrics(splitSync.plan, r.id, { wall_height_ft: 8.0 }).area_sqft
);
assert.ok(Math.abs(areas[0] - 800) < 2 && Math.abs(areas[1] - 800) < 2, 'each closed half ~800 sf');

const syncedPerim = bridge.syncEditorRoomBoundariesForCanvasFill(perimeterOnly).plan;
const openPlan = JSON.parse(JSON.stringify(syncedPerim));
const dropId = loop.boundary_wall_ids[0];
openPlan.floors[0].walls = openPlan.floors[0].walls.filter((w) => w.id !== dropId);
openPlan.floor = openPlan.floors[0];
const openSync = bridge.syncEditorRoomBoundariesForCanvasFill(openPlan);
assert.strictEqual(openSync.changed, true, 'broken perimeter must invalidate persisted rooms');
assert.strictEqual(openSync.plan.floors[0].rooms.length, 0);

const deleteAll = JSON.parse(JSON.stringify(openPlan));
deleteAll.floors[0].walls = [];
deleteAll.floor = deleteAll.floors[0];
const deleteAllSync = bridge.syncEditorRoomBoundariesForCanvasFill(deleteAll);
assert.strictEqual(deleteAllSync.changed, true, 'delete-all must clear dead room objects');
assert.strictEqual(deleteAllSync.plan.floors[0].rooms.length, 0);

console.log('openplan3d-editor-room-fill: ok');

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
assert.deepStrictEqual(
  created.plan.floors[0].rooms[0].walls.slice().sort(),
  loop.boundary_wall_ids.slice().sort(),
  'persisted room.walls must be outer boundary only'
);
assert.ok(created.plan.floors[0].rooms[0].polygon.length >= 3, 'persisted room needs polygon for fill');

const vendorStyle = planWithAllWallsOnRoom(hand0);
const beforeMetrics = bridge.layoutMetrics(vendorStyle, 'r1', { wall_height_ft: 8.0 });
assert.ok(beforeMetrics.area_sqft > 1300, 'takeoffs stay positive with vendor-style room.walls');

const synced = bridge.syncEditorRoomBoundariesForCanvasFill(vendorStyle);
assert.strictEqual(synced.changed, true, 'must trim room.walls when partitions exist');
const roomWalls = synced.plan.floors[0].rooms[0].walls;
assert.ok(roomWalls.length < vendorStyle.floors[0].walls.length, 'room.walls must drop partition ids');
assert.deepStrictEqual(
  roomWalls.slice().sort(),
  loop.boundary_wall_ids.slice().sort(),
  'room.walls must match outer boundary ids for canvas Bo()'
);

const again = bridge.syncEditorRoomBoundariesForCanvasFill(synced.plan);
assert.strictEqual(again.changed, false, 'second sync must be idempotent');

const perimeterOnly = emptyRoomsPlan(hand0);
perimeterOnly.floors[0].walls = perimeterOnly.floors[0].walls.filter(
  (w) => w.id !== 'mclt5ix7' && w.id !== 'z9ajm00u'
);
perimeterOnly.floor.walls = perimeterOnly.floors[0].walls;
const perimeterSync = bridge.syncEditorRoomBoundariesForCanvasFill(perimeterOnly);
assert.strictEqual(perimeterSync.changed, true, 'closed perimeter with empty rooms[] should persist explicit room early');
assert.strictEqual(perimeterSync.plan.floors[0].rooms.length, 1);

console.log('openplan3d-editor-room-fill: ok');

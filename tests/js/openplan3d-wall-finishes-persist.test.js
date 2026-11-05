'use strict';

const assert = require('assert');
const ext = require('../../public/js/openplan3d-zanini-extensions.js');

console.log('openplan3d-wall-finishes-persist tests');

const savedFromApi = {
  id: 'p1',
  activeFloorId: 'f1',
  floors: [{ id: 'f1', walls: [{ id: 'mtn32xy9', start: { x: 0, y: 0 }, end: { x: 100, y: 0 } }], rooms: [] }],
  zanini: {
    version: 1,
    zones: [],
    bulkheads: [],
    wallFinishes: {
      mtn32xy9: { drywall: true, studs: false, baseboard: true, insulation: false },
      w2: { drywall: true, studs: false, baseboard: true, insulation: false },
    },
  },
};

const editorAckPlan = {
  id: 'p1',
  activeFloorId: 'f1',
  floors: savedFromApi.floors,
  zanini: { version: 1, zones: [], bulkheads: [], wallFinishes: {} },
};

const mergedZ = ext.mergeZaniniSnapshots(savedFromApi.zanini, editorAckPlan.zanini);
assert.strictEqual(mergedZ.wallFinishes.mtn32xy9.studs, false, 'LOAD_ACK empty editor wallFinishes must not restore studs default');
assert.strictEqual(mergedZ.wallFinishes.w2.studs, false, 'batch-saved perimeter walls keep studs off');

const editorNoZanini = JSON.parse(JSON.stringify(editorAckPlan));
delete editorNoZanini.zanini;
const mergedNoZ = ext.mergeZaniniSnapshots(savedFromApi.zanini, undefined);
assert.strictEqual(mergedNoZ.wallFinishes.mtn32xy9.studs, false, 'missing editor zanini must keep API wallFinishes');

const batchApply = ext.mergeWallFinishes(
  { a: { studs: true }, b: { studs: true } },
  { a: { studs: false }, b: { studs: false } }
);
assert.strictEqual(batchApply.a.studs, false);
assert.strictEqual(batchApply.b.studs, false);

const incomingOverride = ext.mergeWallFinishes(
  { a: { studs: false, drywall: true } },
  { a: { studs: true } }
);
assert.strictEqual(incomingOverride.a.studs, true, 'explicit incoming flag wins');
assert.strictEqual(incomingOverride.a.drywall, true, 'unspecified incoming keys keep previous');

console.log('openplan3d-wall-finishes-persist: ok');

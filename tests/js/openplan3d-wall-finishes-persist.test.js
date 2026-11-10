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

assert.strictEqual(ext.resolveBaseboardSides({ baseboardSides: 2 }, true), 1, 'perimeter ignores outside baseboard sides');
assert.strictEqual(ext.resolveBaseboardSides({ baseboardSides: 1 }, false, 2), 1);
assert.strictEqual(ext.resolveBaseboardSides({}, false, 0), 1, 'partition with <2 rooms counts one baseboard face');
assert.strictEqual(ext.resolveBaseboardSides({}, false, 2), 2, 'shared partition counts both baseboard faces by default');

const perimeterBb = ext.applyToMetrics(Object.assign({}, {
  boundary_wall_ids: ['w1'],
  walls: [{ id: 'w1', length_lf: 10, net_area_sqft: 80 }],
}), {
  format: 'openplan3d',
  units: 'cm',
  zanini: { wallFinishes: { w1: { baseboard: true, baseboardSides: 2 } } },
}, {}, 1.0);
assert.strictEqual(perimeterBb.finish_baseboard_lf, 10, 'stored baseboardSides:2 on perimeter must not double exterior');

const batchSides = ext.mergeWallFinishes(
  { p1: { baseboardSides: 2 }, p2: { baseboardSides: 1 } },
  { p1: { baseboardSides: 1 }, p2: { baseboardSides: 1 } }
);
assert.strictEqual(batchSides.p1.baseboardSides, 1);
assert.strictEqual(batchSides.p2.baseboardSides, 1);

const drywallFacesMerge = ext.mergeWallFinishes(
  { p1: { drywallFaces: 'both' } },
  { p1: { drywallFaces: 'a' } }
);
assert.strictEqual(drywallFacesMerge.p1.drywallFaces, 'a');

console.log('openplan3d-wall-finishes-persist: ok');

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const bridge = require('../../public/js/openplan3d-editor-bridge.js');
const host = require('../../public/js/openplan3d-host.js');
const measurement = require('../../public/js/openplan3d-measurement-bridge.js');

const INDEX = path.resolve(__dirname, '../../public/vendor/openplan3d/editor/index.html');
const HOST_JS = path.resolve(__dirname, '../../public/js/openplan3d-host.js');

function rectPlan(id, name, widthCm, lengthCm) {
  return {
    id: id,
    name: name,
    unit: 'cm',
    floor: {
      walls: [
        { id: 'w1', start: { x: 0, y: 0 }, end: { x: widthCm, y: 0 }, height: 243.84, thickness: 10 },
        { id: 'w2', start: { x: widthCm, y: 0 }, end: { x: widthCm, y: lengthCm }, height: 243.84, thickness: 10 },
        { id: 'w3', start: { x: widthCm, y: lengthCm }, end: { x: 0, y: lengthCm }, height: 243.84, thickness: 10 },
        { id: 'w4', start: { x: 0, y: lengthCm }, end: { x: 0, y: 0 }, height: 243.84, thickness: 10 },
      ],
      rooms: [
        {
          id: 'r1',
          name: 'Room',
          walls: ['w1', 'w2', 'w3', 'w4'],
          polygon: [
            { x: 0, y: 0 },
            { x: widthCm, y: 0 },
            { x: widthCm, y: lengthCm },
            { x: 0, y: lengthCm },
          ],
        },
      ],
    },
  };
}

function untitledPlan() {
  return { id: 'untitled', name: 'Untitled Project', floors: [{ id: 'f1', walls: [], rooms: [] }] };
}

console.log('Running openPlan3D editor-bridge unit tests...');

const html = fs.readFileSync(INDEX, 'utf8');
assert.match(html, /openplan3d-editor-bridge\.js/, 'editor index must load the host bridge');
assert.match(html, /OPENPLAN3D_LOAD_ACK|onEditorMounted/, 'editor must queue load until mounted');
assert.match(html, /installStorageLock/, 'editor must lock vendor storage');
assert.match(html, /zanini-hosted-openplan3d/, 'editor must mark hosted mode');
assert.match(html, /missing H\/c store exports/, 'editor must show a visible error if H/c are missing');
assert.doesNotMatch(
  html,
  /if \(window\.__openPlan3D && typeof window\.__openPlan3D\.setProject === 'function'\) \{\s*window\.__openPlan3D\.setProject\(data\.plan\);/,
  'raw setProject-on-LOAD_PLAN without the queue/ack session must not return'
);

const hostSrc = fs.readFileSync(HOST_JS, 'utf8');
assert.match(hostSrc, /resolveOpenPlan3DRoomId/, 'host must resolve an openPlan3D room id');
assert.match(hostSrc, /OPENPLAN3D_LOAD_ACK/, 'host must wait for LOAD_ACK');
assert.match(hostSrc, /e\.data\.ok === false/, 'host must treat ACK ok:false as a failed load');
assert.match(hostSrc, /plansMatch\(currentPlan,\s*loadedPlanBaseline\)/, 'Unsaved compares against the loaded plan');
assert.match(hostSrc, /confirm_zero_area/, 'host must send confirm_zero_area');
assert.doesNotMatch(
  hostSrc,
  /currentRoom\.id \|\| currentRoom\.name/,
  'host must not pass the database room id into layoutMetrics'
);
assert.match(hostSrc, /resolveOpenPlan3DRoomId\(plan,\s*selectedOpenPlan3DRoomId\)/);

assert.strictEqual(bridge.isBlockedStorageKey('floorplan_projects'), true);
assert.strictEqual(bridge.isBlockedStorageKey('floorplan_thumb_abc'), true);
assert.strictEqual(bridge.isBlockedStorageKey('vh_1'), true);
assert.strictEqual(bridge.isBlockedStorageKey('o3d_theme'), false);

assert.strictEqual(bridge.storeExportsOk({ H: function () {}, c: { subscribe: function () {} } }), true);
assert.strictEqual(bridge.storeExportsOk({ c: { subscribe: function () {} } }), false);
assert.strictEqual(bridge.storeExportsOk({ H: function () {} }), false);
assert.strictEqual(bridge.storeExportsOk(null), false);

const loaded = rectPlan('saved-1', 'Saved Room', 365.76, 457.2);
const blank = untitledPlan();
assert.strictEqual(bridge.plansMatch(loaded, loaded), true);
assert.strictEqual(bridge.plansMatch(loaded, blank), false);

const posted = [];
const store = {
  value: blank,
  subscribe: function (cb) {
    cb(store.value);
    return function () {};
  },
  set: function (v) { store.value = v; },
};
const storeModule = {
  H: function (project) { store.set(project); },
  c: store,
};

const timers = [];
const session = bridge.createSession({
  postToParent: function (msg) { posted.push(msg); },
  schedule: function (fn, ms) {
    timers.push(fn);
    return 0;
  },
  showError: function () {},
});

session.handleParentMessage({ data: { type: 'OPENPLAN3D_LOAD_PLAN', plan: loaded } });
const queued = session.getState().queuedPlan;
assert.ok(queued, 'LOAD_PLAN is queued before mount');
assert.ok(bridge.plansMatch(queued, loaded), 'queued plan keeps the saved geometry');
assert.ok(Array.isArray(queued.floors), 'queued plan is wrapped as an editor project');
assert.ok(Array.isArray(queued.floors[0].doors), 'floors include doors so the editor can render');
assert.ok(Array.isArray(queued.floors[0].windows), 'floors include windows so the editor can render');
assert.strictEqual(store.value.name, 'Untitled Project', 'queued load must not apply before mount');

session.attachStore(storeModule);
assert.ok(posted.some(function (m) { return m.type === 'OPENPLAN3D_READY'; }));
assert.strictEqual(store.value.name, 'Untitled Project', 'queued load must wait for editor mount');

session.onEditorMounted();
while (timers.length) {
  timers.shift()();
}
assert.strictEqual(store.value.id, 'saved-1', 'queued plan is applied after mount');
assert.ok(posted.some(function (m) { return m.type === 'OPENPLAN3D_LOAD_ACK' && m.ok === true; }));
assert.strictEqual(session.getState().loadAcked, true);

const beforeAckPosted = posted.length;
session.onEditorMounted();
session.handleParentMessage({ data: { type: 'OPENPLAN3D_LOAD_PLAN', plan: loaded } });
store.value = blank;
const forwardedBeforeAck = session.onStoreChange(blank, function () {
  posted.push({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: blank });
});
assert.strictEqual(forwardedBeforeAck, false, 'store changes are ignored until LOAD_ACK');
assert.ok(posted.slice(beforeAckPosted).every(function (m) { return m.type !== 'OPENPLAN3D_PLAN_CHANGED'; }));

while (timers.length) {
  timers.shift()();
}
const forwardedAfterAck = session.onStoreChange(loaded, function () {
  posted.push({ type: 'FORWARDED' });
});
assert.strictEqual(forwardedAfterAck, true, 'real edits after LOAD_ACK are forwarded');

const mismatchPosted = [];
const mismatchTimers = [];
const mismatchStore = {
  value: blank,
  subscribe: function (cb) {
    cb(mismatchStore.value);
    return function () {};
  },
  set: function () {},
};
const mismatchSession = bridge.createSession({
  postToParent: function (msg) { mismatchPosted.push(msg); },
  schedule: function (fn) {
    mismatchTimers.push(fn);
    return 0;
  },
  showError: function () {},
});
mismatchSession.handleParentMessage({ data: { type: 'OPENPLAN3D_LOAD_PLAN', plan: loaded } });
mismatchSession.attachStore({ H: function () { mismatchStore.set(); }, c: mismatchStore });
mismatchSession.onEditorMounted();
var safety = 0;
while (mismatchTimers.length && safety < 80) {
  safety++;
  mismatchTimers.shift()();
}
const failedAck = mismatchPosted.filter(function (m) { return m.type === 'OPENPLAN3D_LOAD_ACK'; }).pop();
assert.ok(failedAck, 'failed load must still ACK');
assert.strictEqual(failedAck.ok, false, 'ACK ok is false when getProject never matches');
assert.strictEqual(failedAck.plan, null, 'failed ACK must not send the editor current plan');
assert.strictEqual(mismatchSession.getState().loadAcked, false, 'failed ACK must not mark loadAcked');
assert.strictEqual(mismatchSession.getState().ignoreStoreChanges, true, 'failed ACK keeps ignoring store writes');
assert.strictEqual(
  mismatchSession.onStoreChange(blank, function () {}),
  false,
  'failed ACK must not forward the editor plan'
);

assert.strictEqual(host.plansMatch(loaded, loaded), true);
assert.strictEqual(host.plansMatch(loaded, blank), false);
const editorReady = bridge.toEditorProject(loaded);
assert.ok(Array.isArray(editorReady.floors) && editorReady.floors.length === 1, 'compact floor plans become editor floors');
assert.ok(bridge.plansMatch(editorReady, loaded), 'editor wrapping must not change the geometry fingerprint');
assert.deepStrictEqual(editorReady.floors[0].doors, []);
assert.deepStrictEqual(editorReady.floors[0].windows, []);

const twoRoomPlan = {
  unit: 'cm',
  floor: {
    rooms: [
      { id: 'r1', name: 'Kitchen', polygon: [{ x: 0, y: 0 }, { x: 304.8, y: 0 }, { x: 304.8, y: 304.8 }, { x: 0, y: 304.8 }] },
      { id: 'r2', name: 'Bath', polygon: [{ x: 304.8, y: 0 }, { x: 609.6, y: 0 }, { x: 609.6, y: 304.8 }, { x: 304.8, y: 304.8 }] },
    ],
    walls: [
      { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 } },
      { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 304.8 } },
      { id: 'w3', start: { x: 304.8, y: 304.8 }, end: { x: 0, y: 304.8 } },
      { id: 'w4', start: { x: 0, y: 304.8 }, end: { x: 0, y: 0 } },
      { id: 'w5', start: { x: 304.8, y: 0 }, end: { x: 609.6, y: 0 } },
      { id: 'w6', start: { x: 609.6, y: 0 }, end: { x: 609.6, y: 304.8 } },
      { id: 'w7', start: { x: 609.6, y: 304.8 }, end: { x: 304.8, y: 304.8 } },
    ],
  },
};

assert.strictEqual(host.plansMatch(twoRoomPlan, twoRoomPlan), true);
assert.strictEqual(host.resolveOpenPlan3DRoomId(twoRoomPlan, 10), null, 'database id 10 is not an openPlan3D room id');
assert.strictEqual(host.resolveOpenPlan3DRoomId(twoRoomPlan, 'r2'), 'r2');
assert.strictEqual(host.resolveOpenPlan3DRoomId(twoRoomPlan, null), null, 'whole plan when no openPlan3D room is chosen');

const metricsAll = host.computeMetrics(twoRoomPlan, { wall_height_ft: 8 }, null, measurement);
const metricsFirst = host.computeMetrics(twoRoomPlan, { wall_height_ft: 8 }, 'r1', measurement);
assert.ok(metricsAll && metricsAll.area_sqft === 200, 'whole-plan area is 200 sq ft, got ' + (metricsAll && metricsAll.area_sqft));
assert.ok(metricsFirst && metricsFirst.area_sqft === 100, 'selected openPlan3D room area is 100');
assert.notStrictEqual(metricsAll.area_sqft, metricsFirst.area_sqft);

console.log('All openPlan3D editor-bridge unit tests PASSED!');

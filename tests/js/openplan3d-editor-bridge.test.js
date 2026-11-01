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
assert.match(html, /\[data-zanini-hide-native-save\]/, 'vendor chrome hide must use the data attribute, not Tailwind layout selectors');
assert.doesNotMatch(
  html,
  /\.h-12\.bg-gradient-to-r\.from-slate-800/,
  'fragile Tailwind chrome-hide selectors must not remain in the editor CSS'
);
assert.doesNotMatch(
  html,
  /if \(window\.__openPlan3D && typeof window\.__openPlan3D\.setProject === 'function'\) \{\s*window\.__openPlan3D\.setProject\(data\.plan\);/,
  'raw setProject-on-LOAD_PLAN without the queue/ack session must not return'
);

const hostSrc = fs.readFileSync(HOST_JS, 'utf8');
assert.match(hostSrc, /resolveOpenPlan3DRoomId/, 'host must resolve an openPlan3D room id');
assert.match(hostSrc, /OPENPLAN3D_LOAD_ACK/, 'host must wait for LOAD_ACK');
assert.match(hostSrc, /e\.data\.ok === false/, 'host must treat ACK ok:false as a failed load');
assert.match(hostSrc, /confirm_zero_area/, 'host must send confirm_zero_area');
assert.match(hostSrc, /isTrustedEditorMessage/, 'host must check postMessage source and origin');
assert.doesNotMatch(
  hostSrc,
  /contentWindow\.postMessage\(payload,\s*'\*'\)/,
  'host must not post to the editor with a wildcard origin'
);
assert.match(hostSrc, /AbortController/, 'host save request must use AbortController');
assert.match(hostSrc, /Refusing postMessage: invalid or missing target origin/, 'host must refuse post without valid origin');

assert.doesNotMatch(
  hostSrc,
  /currentRoom\.id \|\| currentRoom\.name/,
  'host must not pass the database room id into layoutMetrics'
);
assert.match(hostSrc, /resolveOpenPlan3DRoomId\(plan,\s*selectedOpenPlan3DRoomId\)/);
assert.match(hostSrc, /function askConfirm/, 'native confirm must be replaced with an in-app modal');
assert.match(hostSrc, /save-destination/, 'Save must offer link vs standalone');
assert.match(hostSrc, /startBlankSession/, 'Room Area must boot a blank session by default');
assert.doesNotMatch(hostSrc, /\bwin\.confirm\b|\bwindow\.confirm\s*\(/);
assert.doesNotMatch(hostSrc, /\bwin\.alert\b|\bwindow\.alert\s*\(/);
assert.doesNotMatch(hostSrc, /\bwin\.prompt\b|\bwindow\.prompt\s*\(/);

assert.match(hostSrc, /function jsonPost[\s\S]*?AbortController/, 'jsonPost must use AbortController');
assert.match(hostSrc, /modalIsSaveFlow/, 'finishModal must track save flow');
assert.match(hostSrc, /modalOpenerEl/, 'modal opener must be tracked for focus return');

const bridgeSrc = fs.readFileSync(path.resolve(__dirname, '../../public/js/openplan3d-editor-bridge.js'), 'utf8');
assert.match(bridgeSrc, /style\.setProperty\('display', 'none'/, 'native chrome hide must set inline display');
assert.match(bridgeSrc, /__zaniniNativeSaveClickLocked/, 'hidden vendor chrome clicks must be captured');
assert.match(bridgeSrc, /vendor-top-bar/, 'hideNativeSaveUi must scope to vendor header container');

let headerQueried = false;
let bodyQueried = false;
const mockRoot = {
  querySelectorAll: function () {},
  body: {
    querySelector: function (sel) {
      if (sel.indexOf('.vendor-top-bar') !== -1) {
        return {
          querySelectorAll: function () {
            headerQueried = true;
            return [];
          }
        };
      }
      return null;
    },
    querySelectorAll: function () {
      bodyQueried = true;
      return [];
    }
  }
};
bridge.hideNativeSaveUi(mockRoot);
assert.strictEqual(headerQueried, true, 'hideNativeSaveUi queries inside header container');
assert.strictEqual(bodyQueried, false, 'hideNativeSaveUi must NOT query whole body');

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

const fakeIframe = { contentWindow: { id: 'editor' }, src: 'https://zanini.test/vendor/openplan3d/editor/index.html' };
const fakeWin = { location: { origin: 'https://zanini.test', href: 'https://zanini.test/admin' } };
assert.strictEqual(
  host.isTrustedEditorMessage({ source: fakeIframe.contentWindow, origin: 'https://zanini.test' }, fakeIframe, fakeWin),
  true,
  'same-origin iframe messages are accepted'
);
assert.strictEqual(
  host.isTrustedEditorMessage({ source: {}, origin: 'https://zanini.test' }, fakeIframe, fakeWin),
  false,
  'messages not from the editor iframe are rejected'
);
assert.strictEqual(
  host.isTrustedEditorMessage({ source: fakeIframe.contentWindow, origin: 'https://evil.test' }, fakeIframe, fakeWin),
  false,
  'cross-origin messages are rejected'
);
assert.strictEqual(
  host.isTrustedEditorMessage({ source: fakeIframe.contentWindow, origin: 'null' }, fakeIframe, fakeWin),
  false,
  'null origin messages are rejected'
);
assert.strictEqual(
  host.isTrustedEditorMessage({ source: fakeIframe.contentWindow, origin: '' }, fakeIframe, fakeWin),
  false,
  'empty origin messages are rejected'
);

const relIframe = {
  contentWindow: { id: 'editor2' },
  getAttribute: function (name) {
    return name === 'src' ? '/vendor/openplan3d/editor/index.html?v=1' : '';
  },
  src: 'http://127.0.0.1:8000/vendor/openplan3d/editor/index.html?v=1',
};
const relWin = { location: { origin: 'http://127.0.0.1:8000', href: 'http://127.0.0.1:8000/admin/utilities/room-area' } };
assert.strictEqual(
  host.editorExpectedOrigin(relIframe, relWin),
  'http://127.0.0.1:8000',
  'root-relative iframe src must resolve against the admin page origin'
);
assert.strictEqual(
  host.isTrustedEditorMessage({ source: relIframe.contentWindow, origin: 'http://127.0.0.1:8000' }, relIframe, relWin),
  true,
  'root-relative iframe postMessage uses the page origin'
);

function geometryPlan() {
  return {
    unit: 'cm',
    floor: {
      walls: [
        { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 }, height: 243.84, thickness: 10 },
        { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 304.8 }, height: 243.84, thickness: 10 },
        { id: 'w3', start: { x: 304.8, y: 304.8 }, end: { x: 0, y: 304.8 }, height: 243.84, thickness: 10 },
        { id: 'w4', start: { x: 0, y: 304.8 }, end: { x: 0, y: 0 }, height: 243.84, thickness: 10 },
      ],
      rooms: [
        {
          id: 'r1',
          name: 'Kitchen',
          walls: ['w1', 'w2', 'w3', 'w4'],
          polygon: [
            { x: 0, y: 0 },
            { x: 304.8, y: 0 },
            { x: 304.8, y: 304.8 },
            { x: 0, y: 304.8 },
          ],
        },
      ],
      doors: [{ id: 'd1', wallId: 'w1', position: 0.3, offset: 50, width: 91.44, height: 203.2 }],
      windows: [{ id: 'win1', wallId: 'w2', position: 0.4, offset: 80, width: 121.92, height: 91.44 }],
      furniture: [{ id: 'f1', position: { x: 40, y: 40 }, rotation: 0, width: 60, depth: 40 }],
      textAnnotations: [{ id: 't1', text: 'Kitchen', position: { x: 20, y: 20 } }],
    },
  };
}

function clonePlan(plan) {
  return JSON.parse(JSON.stringify(plan));
}

const geo = geometryPlan();
assert.strictEqual(host.plansMatch(geo, clonePlan(geo)), true, 'identical geometry matches');

const movedDoor = clonePlan(geo);
movedDoor.floor.doors[0].position = 0.7;
assert.strictEqual(host.plansMatch(geo, movedDoor), false, 'moving a door must change the fingerprint');

const thickWall = clonePlan(geo);
thickWall.floor.walls[0].thickness = 20;
assert.strictEqual(host.plansMatch(geo, thickWall), false, 'wall thickness must change the fingerprint');

const movedCorner = clonePlan(geo);
movedCorner.floor.rooms[0].polygon[1].x = 280;
assert.strictEqual(host.plansMatch(geo, movedCorner), false, 'moving a middle room corner must change the fingerprint');

const addedFurn = clonePlan(geo);
addedFurn.floor.furniture.push({ id: 'f2', position: { x: 80, y: 80 }, rotation: 0, width: 30, depth: 30 });
assert.strictEqual(host.plansMatch(geo, addedFurn), false, 'adding furniture must change the fingerprint');

const addedLabel = clonePlan(geo);
addedLabel.floor.textAnnotations.push({ id: 't2', text: 'Bath', position: { x: 90, y: 90 } });
assert.strictEqual(host.plansMatch(geo, addedLabel), false, 'adding a label must change the fingerprint');

const swungDoor = clonePlan(geo);
swungDoor.floor.doors[0].swing = 'left';
assert.strictEqual(host.plansMatch(geo, swungDoor), false, 'door swing must change the fingerprint');

const stairsPlan = clonePlan(geo);
stairsPlan.floor.stairs = [{ id: 's1', position: { x: 12, y: 12 }, width: 90, run: 250, treads: 13 }];
assert.strictEqual(host.plansMatch(geo, stairsPlan), false, 'stairs must change the fingerprint');

const typedFurn = clonePlan(geo);
typedFurn.floor.furniture[0].type = 'sofa';
typedFurn.floor.furniture[0].material = 'oak';
assert.strictEqual(host.plansMatch(geo, typedFurn), false, 'furniture type and material must change the fingerprint');

const coordNoise = clonePlan(geo);
coordNoise.floor.walls[0].start.x += 1e-8;
coordNoise.floor.rooms[0].polygon[1].y += 4e-7;
assert.strictEqual(host.plansMatch(geo, coordNoise), true, 'coordinates are compared at 1e-6');
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

// Verify hideNativeSaveUi targets div.bg-gradient-to-r.from-slate-800 first
const queryLog = [];
const mockScope = {
  matches: function (sel) {
    queryLog.push({ type: 'matches', sel: sel });
    return false;
  },
  querySelector: function (sel) {
    queryLog.push({ type: 'querySelector', sel: sel });
    return null;
  },
  querySelectorAll: function () { return []; }
};
bridge.hideNativeSaveUi(mockScope);
assert.strictEqual(queryLog[0].sel, 'div.bg-gradient-to-r.from-slate-800', 'hideNativeSaveUi targets div.bg-gradient-to-r.from-slate-800 first');

// Verify public/vendor/openplan3d/editor/index.html has CSS fallback restored
const indexHtml = fs.readFileSync(INDEX, 'utf8');
assert.ok(indexHtml.includes('html.zanini-hosted-openplan3d div.bg-gradient-to-r.from-slate-800 > button.bg-white.text-slate-800.font-semibold'), 'CSS fallback for Save button is restored');
assert.ok(indexHtml.includes('html.zanini-hosted-openplan3d div.bg-gradient-to-r.from-slate-800 > a.flex.items-center.gap-1'), 'CSS fallback for Projects link is restored');
assert.ok(indexHtml.includes('html.zanini-hosted-openplan3d div.bg-gradient-to-r.from-slate-800 > .relative:has(> button svg path[d^="M21 15v4"])'), 'CSS fallback for Export dropdown is restored');

// Verify openplan3d-host.js does not unlock isSaving in jsonPost
const hostJs = fs.readFileSync(HOST_JS, 'utf8');
const jsonPostSrc = hostJs.slice(hostJs.indexOf('function jsonPost('), hostJs.indexOf('function getFinishSettings('));
assert.ok(!jsonPostSrc.includes('isSaving'), 'jsonPost must not release isSaving');

console.log('All openPlan3D editor-bridge unit tests PASSED!');

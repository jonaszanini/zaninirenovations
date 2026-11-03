'use strict';

/**
 * Kr / jo inch snap: behavioral tests on extracted canvas Kr() plus negative vendor control.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { extractKrSource, makeKrRunner, onWholeInch, orthoLengthCm, INCH } = require('./openplan3d-kr-snap-logic');

const ROOT = path.resolve(__dirname, '../..');
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const vendorEditor = fs.readFileSync(
  path.join(ROOT, 'public/vendor/openplan3d/_app/immutable/chunks', patched.editorChunk),
  'utf8'
);
const h1Editor = fs.readFileSync(
  path.join(ROOT, 'public/vendor', patched.vendorSlug, '_app/immutable/chunks', patched.editorChunk),
  'utf8'
);

const PATCH_MARKERS = [
  'st.snappedToEndpoint||st.snappedToWall',
  'let H=K;t(nn)&&(H=cr(K))',
  'return t(nn)?{x:cr(st.x),y:cr(st.y)}:st',
];
for (const m of PATCH_MARKERS) {
  assert.match(h1Editor, new RegExp(m.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), 'h1 must contain Kr patch: ' + m);
}
assert.match(
  h1Editor,
  /let H=K;t\(ln\)&&t\(nn\)&&\(H=cr\(K\)\);st=\{x:t\(O\)\.x\+H\*Math\.cos\(Ut\)/,
  'h1 jo draw ortho must round K in imperial with snap on'
);

const patchedKr = extractKrSource(h1Editor);
const vendorKrOnly = extractKrSource(vendorEditor);
const patchedRun = makeKrRunner(patchedKr);
const vendorRun = makeKrRunner(vendorKrOnly);

const anchor = { x: 0, y: 0 };
const offGridLen = 125.1958 * INCH;
const orthoCases = [
  { name: '0°', st: { x: offGridLen, y: 0.8 } },
  {
    name: '45°',
    st: {
      x: anchor.x + offGridLen * Math.cos(Math.PI / 4),
      y: anchor.y + offGridLen * Math.sin(Math.PI / 4),
    },
  },
  { name: '90°', st: { x: -0.6, y: offGridLen } },
];

for (const c of orthoCases) {
  patchedRun.setSnap(true);
  patchedRun.setImperial(true);
  const out = patchedRun.Kr(anchor, c.st);
  const len = orthoLengthCm(anchor, out);
  assert.ok(onWholeInch(len), `patched Kr ortho ${c.name} length must be whole inches, got ${len / INCH} in`);

  vendorRun.setSnap(true);
  vendorRun.setImperial(true);
  const vOut = vendorRun.Kr(anchor, c.st);
  const vLen = orthoLengthCm(anchor, vOut);
  assert.ok(
    !onWholeInch(vLen),
    `negative control: vendor Kr ortho ${c.name} must stay off inch grid (got ${vLen / INCH} in)`
  );
}

const join = { x: 60.33, y: 40.12 };
patchedRun.setSnap(true);
patchedRun.setImperial(true);
const epSnap = patchedRun.Kr(anchor, { x: join.x, y: join.y, snappedToEndpoint: true });
assert.strictEqual(epSnap.x, join.x, 'Kr must not move Br endpoint snap X');
assert.strictEqual(epSnap.y, join.y, 'Kr must not move Br endpoint snap Y');
const wallSnap = patchedRun.Kr(anchor, { x: join.x, y: join.y, snappedToWall: true, snappedWallId: 'w1' });
assert.strictEqual(wallSnap.x, join.x, 'Kr must not move Br wall segment snap X');

patchedRun.setSnap(true);
patchedRun.setImperial(false);
const metricOut = patchedRun.Kr(anchor, { x: offGridLen, y: 0.5 });
assert.ok(Math.abs(metricOut.x - offGridLen) < 0.05, 'metric Kr must not round ortho length');

patchedRun.setSnap(false);
patchedRun.setImperial(true);
const snapOff = patchedRun.Kr(anchor, { x: 100.3, y: 200.7 });
assert.strictEqual(snapOff.x, 100.3);
assert.strictEqual(snapOff.y, 200.7);

patchedRun.setSnap(true);
patchedRun.setImperial(true);
const oblique = { x: 100.3, y: 200.7 };
const fb = patchedRun.Kr(anchor, oblique);
assert.ok(onWholeInch(fb.x) && onWholeInch(fb.y), 'grid fallback must inch-round free point');

console.log('openplan3d-kr-inch-snap: ok (editor=' + patched.editorChunk + ')');

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const {
  extractKrSource,
  extractJoSource,
  makeKrRunner,
  makeJoRunner,
  onWholeInch,
  orthoLengthCm,
  revertKrOrthoPatch,
  revertJoOrthoPatch,
  INCH,
} = require('./openplan3d-kr-snap-logic');

const ROOT = path.resolve(__dirname, '../..');
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const vendorChunk = patched.vendorEditorChunk || 'CgAQI-i0.js';
const vendorEditor = fs.readFileSync(
  path.join(ROOT, 'public/vendor/openplan3d/_app/immutable/chunks', vendorChunk),
  'utf8'
);
const h1Editor = fs.readFileSync(
  path.join(ROOT, 'public/vendor', patched.vendorSlug, '_app/immutable/chunks', patched.editorChunk),
  'utf8'
);

assert.match(h1Editor, /t\(Ce\)\.units==="imperial"&&\(H=cr\(K\)\)/, 'Kr ortho length must gate on Ce.units imperial');
assert.match(
  h1Editor,
  /t\(ln\)&&t\(Ce\)\.units==="imperial"&&\(H=cr\(K\)\)/,
  'jo ortho length must gate on Ce.units imperial'
);

const patchedKr = extractKrSource(h1Editor);
const patchedJo = extractJoSource(h1Editor);
const vendorKr = extractKrSource(vendorEditor);
const patchedRun = makeKrRunner(patchedKr);
const vendorRun = makeKrRunner(vendorKr);

const anchor = { x: 23.71, y: 31.43 };
const offGridLen = 125.1958 * INCH;
const orthoCases = [
  { name: '0°', st: { x: anchor.x + offGridLen, y: anchor.y + 0.8 } },
  {
    name: '45°',
    st: {
      x: anchor.x + offGridLen * Math.cos(Math.PI / 4),
      y: anchor.y + offGridLen * Math.sin(Math.PI / 4),
    },
  },
  { name: '90°', st: { x: anchor.x - 0.6, y: anchor.y + offGridLen } },
];

for (const c of orthoCases) {
  patchedRun.setSnap(true);
  patchedRun.setUnits('imperial');
  const len = orthoLengthCm(anchor, patchedRun.Kr(anchor, c.st));
  assert.ok(onWholeInch(len), `imperial Kr ortho ${c.name} length must be whole inches, got ${len / INCH} in`);

  vendorRun.setSnap(true);
  vendorRun.setUnits('imperial');
  assert.ok(!onWholeInch(orthoLengthCm(anchor, vendorRun.Kr(anchor, c.st))), `vendor negative ${c.name}`);
}

for (const c of orthoCases) {
  patchedRun.setSnap(true);
  patchedRun.setUnits('metric');
  vendorRun.setSnap(true);
  vendorRun.setUnits('metric');
  const pOut = patchedRun.Kr(anchor, c.st);
  const vOut = vendorRun.Kr(anchor, c.st);
  assert.ok(Math.abs(pOut.x - vOut.x) < 0.001 && Math.abs(pOut.y - vOut.y) < 0.001, `metric Kr ${c.name} must match vendor`);
}

const join = { x: 60.33, y: 40.12 };
patchedRun.setUnits('imperial');
patchedRun.setSnap(true);
assert.strictEqual(patchedRun.Kr(anchor, { ...join, snappedToEndpoint: true }).x, join.x);

const krReverted = makeKrRunner(revertKrOrthoPatch(patchedKr));
krReverted.setSnap(true);
krReverted.setUnits('imperial');
assert.ok(
  !onWholeInch(orthoLengthCm(anchor, krReverted.Kr(anchor, orthoCases[0].st))),
  'Kr-only revert must fail whole-inch ortho check'
);

const joRun = makeJoRunner(patchedJo, patchedRun);
joRun.setOrigin(anchor);
joRun.setSnap(true);
joRun.setUnits('imperial');
assert.ok(
  onWholeInch(orthoLengthCm(anchor, joRun.jo({ x: anchor.x + offGridLen, y: anchor.y + 1.1 }))),
  'jo must inch-round ortho length in imperial'
);

const joReverted = makeJoRunner(revertJoOrthoPatch(patchedJo), patchedRun);
joReverted.setOrigin(anchor);
joReverted.setSnap(true);
joReverted.setUnits('imperial');
assert.ok(
  !onWholeInch(orthoLengthCm(anchor, joReverted.jo({ x: anchor.x + offGridLen, y: anchor.y + 1.1 }))),
  'jo-only revert must fail whole-inch ortho check'
);

console.log('openplan3d-kr-inch-snap: ok (editor=' + patched.editorChunk + ')');

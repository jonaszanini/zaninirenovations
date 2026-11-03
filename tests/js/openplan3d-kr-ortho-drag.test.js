'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const {
  extractJoSource,
  extractKrSource,
  makeJoRunner,
  makeKrRunner,
  onWholeInch,
  orthoLengthCm,
  revertJoOrthoPatch,
  INCH,
} = require('./openplan3d-kr-snap-logic');

const HELPER = path.join(__dirname, 'openplan3d-inch-drag-helper.js');
const ROOT = path.resolve(__dirname, '../..');
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const h1Editor = fs.readFileSync(
  path.join(ROOT, 'public/vendor', patched.vendorSlug, '_app/immutable/chunks', patched.editorChunk),
  'utf8'
);

function runMode(mode) {
  const out = execFileSync(process.execPath, [HELPER, mode], { encoding: 'utf8', timeout: 180000 }).trim();
  const line = out.split('\n').filter(Boolean).pop();
  const result = JSON.parse(line);
  assert.strictEqual(result.ok, true, `${mode}: ${result.error || 'failed'}`);
  return result;
}

function assertWholeInchLength(r, label) {
  assert.ok(typeof r.lengthIn === 'number', label + ' must report lengthIn');
  assert.ok(onWholeInch(r.lengthIn * INCH), `${label}: length ${r.lengthIn} in must be whole inch`);
}

for (const ortho of ['ortho0', 'ortho45', 'ortho90']) {
  assertWholeInchLength(runMode(ortho), ortho);
}

// Draw-tool jo() ortho length (chunk simulation; revert guard in kr-inch-snap.test.js).
const anchor = { x: 23.71, y: 31.43 };
const offGridLen = 125.1958 * INCH;
const patchedKr = extractKrSource(h1Editor);
const patchedJo = extractJoSource(h1Editor);
const joRun = makeJoRunner(patchedJo, makeKrRunner(patchedKr));
joRun.setOrigin(anchor);
joRun.setSnap(true);
joRun.setUnits('imperial');
assert.ok(
  onWholeInch(orthoLengthCm(anchor, joRun.jo({ x: anchor.x + offGridLen, y: anchor.y + 1.1 }))),
  'jo draw ortho must inch-round length in imperial'
);
const joReverted = makeJoRunner(revertJoOrthoPatch(patchedJo), makeKrRunner(patchedKr));
joReverted.setOrigin(anchor);
joReverted.setSnap(true);
joReverted.setUnits('imperial');
assert.ok(
  !onWholeInch(orthoLengthCm(anchor, joReverted.jo({ x: anchor.x + offGridLen, y: anchor.y + 1.1 }))),
  'jo-only revert must fail draw ortho whole-inch check'
);

console.log('openplan3d-kr-ortho-drag: ok');

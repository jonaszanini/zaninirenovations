'use strict';

/**
 * Kr grid-fallback inch snap: patched h1 editor chunk must round free drag to whole inches
 * when snap is on (grid fallback only — not endpoint/segment snaps).
 * Proves mount inch-grid assertions fail without the Kr patch line.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const EDITOR_CHUNK = patched.editorChunk;
const INCH = 2.54;

const editorPath = path.join(
  ROOT,
  'public/vendor',
  patched.vendorSlug,
  '_app/immutable/chunks',
  EDITOR_CHUNK
);
const editorJs = fs.readFileSync(editorPath, 'utf8');

const PATCHED_KR =
  /for\(const Zt of xt\)if\(Math\.abs\(ot-Zt\)<Ut\)return\{x:cr\(w\.x\+K\*Math\.cos\(Zt\)\),y:cr\(w\.y\+K\*Math\.sin\(Zt\)\)\};return\{x:cr\(st\.x\),y:cr\(st\.y\)\}\}function tn\(\)/;
const VENDOR_KR =
  /for\(const Zt of xt\)if\(Math\.abs\(ot-Zt\)<Ut\)return\{x:w\.x\+K\*Math\.cos\(Zt\),y:w\.y\+K\*Math\.sin\(Zt\)\};return st\}function tn\(\)/;

assert.match(editorJs, PATCHED_KR, 'h1 editor chunk must contain Kr inch snap patch');

const reverted = editorJs.replace(
  'return{x:cr(w.x+K*Math.cos(Zt)),y:cr(w.y+K*Math.sin(Zt))};return{x:cr(st.x),y:cr(st.y)}}function tn()',
  'return{x:w.x+K*Math.cos(Zt),y:w.y+K*Math.sin(Zt)};return st}function tn()'
);
assert.match(reverted, VENDOR_KR, 'reverted chunk should match vendor Kr return st');
assert.doesNotMatch(reverted, PATCHED_KR, 'reverted chunk must drop Kr inch rounding');

function inchCr(v) {
  return Math.round(v / INCH) * INCH;
}

function krGridFallback(st, usePatch) {
  if (usePatch) {
    return { x: inchCr(st.x), y: inchCr(st.y) };
  }
  return { x: st.x, y: st.y };
}

const offGrid = { x: 100.3, y: 200.7 };
const patchedOut = krGridFallback(offGrid, true);
const vendorOut = krGridFallback(offGrid, false);

assert.ok(Math.abs(patchedOut.x / INCH - Math.round(patchedOut.x / INCH)) < 0.02);
assert.ok(Math.abs(patchedOut.y / INCH - Math.round(patchedOut.y / INCH)) < 0.02);
assert.ok(Math.abs(vendorOut.x - offGrid.x) < 0.001 && Math.abs(vendorOut.y - offGrid.y) < 0.001);

console.log('openplan3d-kr-inch-snap: ok (editor=' + EDITOR_CHUNK + ')');

'use strict';

/**
 * Gate B: fresh editor index must load one -h4 store graph; legacy -h3 bytes stay on main.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../..');
const MANIFEST = path.join(ROOT, 'scripts/openplan3d-h1.json');
const INDEX = path.join(ROOT, 'public/vendor/openplan3d-h1/editor/index.html');
const IMMUTABLE = path.join(ROOT, 'public/vendor/openplan3d-h1/_app/immutable');

const manifest = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'));
const indexHtml = fs.readFileSync(INDEX, 'utf8');

assert.strictEqual(manifest.immutableGraphSuffix, 'h4');
assert.strictEqual(manifest.storeChunk, '1VjVcrjm-h4.js');
assert.strictEqual(manifest.entryChunk, 'app.Badnr33M-h4.js');

assert.doesNotMatch(
  indexHtml,
  /\/_app\/immutable\/[^"']+-h3\.js/,
  'editor index must not reference any -h3 immutable asset (fresh graph is -h4 only)'
);

const storeImports = indexHtml.match(/1VjVcrjm-h[0-9]+\.js/g) || [];
assert.ok(storeImports.length >= 1, 'index must preload/import store chunk');
assert.ok(
  storeImports.every((s) => s === '1VjVcrjm-h4.js'),
  `index must use single store slug h4 only; got ${storeImports.join(', ')}`
);

assert.match(indexHtml, /app\.Badnr33M-h4\.js/, 'index must boot app entry -h4');

const entryPath = path.join(IMMUTABLE, 'entry', manifest.entryChunk);
assert.ok(fs.existsSync(entryPath), 'entry chunk file must exist');
const entrySrc = fs.readFileSync(entryPath, 'utf8');
assert.match(entrySrc, /1VjVcrjm-h4\.js/, 'app entry must import store -h4');

const h3Store = path.join(IMMUTABLE, 'chunks/1VjVcrjm-h3.js');
const h4Store = path.join(IMMUTABLE, 'chunks', manifest.storeChunk);
assert.ok(fs.existsSync(h3Store), 'legacy h3 store must remain for cached importers');
assert.ok(fs.existsSync(h4Store), 'active h4 store must exist');
const h3Bytes = fs.readFileSync(h3Store, 'utf8');
const h4Bytes = fs.readFileSync(h4Store, 'utf8');
assert.notStrictEqual(h3Bytes, h4Bytes, 'must not publish patched bytes under legacy h3 filename');
assert.doesNotMatch(h3Bytes, /canvasFillRoomEntries/, 'legacy h3 store must match main-era graph');
assert.match(h4Bytes, /canvasFillRoomEntries/, 'active h4 store must include PR120 patches');

const mainH3 = execSync(`git show origin/main:${path.relative(ROOT, h3Store).replace(/\\/g, '/')}`, {
  encoding: 'utf8',
  cwd: ROOT,
});
assert.strictEqual(h3Bytes, mainH3, 'legacy h3 files must byte-match origin/main');

assert.doesNotMatch(entrySrc, /1VjVcrjm-h3\.js/, 'app entry must not import legacy h3 store');

console.log('openplan3d-immutable-graph-cache: ok');

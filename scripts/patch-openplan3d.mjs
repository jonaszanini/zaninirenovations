#!/usr/bin/env node
/**
 * Apply Zanini hosted patches to vendored openPlan3D immutable bundles.
 *
 * Re-run steps (from repo root):
 *   1. Ensure `public/vendor/openplan3d/_app/immutable/` matches upstream (or prior patch manifest).
 *   2. `node scripts/patch-openplan3d.mjs`
 *   3. Commit changed files under `public/vendor/openplan3d/` and `scripts/openplan3d-patched-chunks.json`.
 *   4. Run: `node tests/js/openplan3d-editor-bridge.test.js`
 *          `node tests/js/openplan3d-editor-index-mount.test.js`
 *
 * Patches store (edge length vo/Br/ys/pa) and editor canvas snap (Br/Kr), then assigns every
 * changed immutable JS file a fresh content-hash filename (entry/nodes/chunks). Old filenames
 * are kept on disk so long-lived caches never 404.
 */
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const VENDOR = path.join(ROOT, 'public/vendor/openplan3d');
const IMMUTABLE = path.join(VENDOR, '_app/immutable');
const MANIFEST = path.join(__dirname, 'openplan3d-patched-chunks.json');
const EDITOR_INDEX = path.join(VENDOR, 'editor/index.html');

const require = createRequire(import.meta.url);
const bridge = require(path.join(ROOT, 'public/js/openplan3d-editor-bridge.js'));

function contentHash(content) {
  return crypto.createHash('sha256').update(content).digest('base64url').replace(/[-_]/g, 'x').slice(0, 8);
}

function read(rel) {
  return fs.readFileSync(path.join(IMMUTABLE, rel), 'utf8');
}

function patchStoreChunk(source) {
  let s = source;
  s = s.replace(/wallMeasureMode:"centerline"/g, 'wallMeasureMode:"edge"');

  const voOld =
    'function vo(e){if(!e.curvePoint)return Math.hypot(e.end.x-e.start.x,e.end.y-e.start.y);let t=0,n=e.start;for(let o=1;o<=20;o++){const i=o/20,a=1-i,s={x:a*a*e.start.x+2*a*i*e.curvePoint.x+i*i*e.end.x,y:a*a*e.start.y+2*a*i*e.curvePoint.y+i*i*e.end.y};t+=Math.hypot(s.x-n.x,s.y-n.y),n=s}return t}';
  const voNew =
    'function vo(e){let t;if(!e.curvePoint)t=Math.hypot(e.end.x-e.start.x,e.end.y-e.start.y);else{t=0;let n=e.start;for(let o=1;o<=20;o++){const i=o/20,a=1-i,s={x:a*a*e.start.x+2*a*i*e.curvePoint.x+i*i*e.end.x,y:a*a*e.start.y+2*a*i*e.curvePoint.y+i*i*e.end.y};t+=Math.hypot(s.x-n.x,s.y-n.y),n=s}}const i=L(ma),a=L(ee),r=a?.walls;if(i?.wallMeasureMode==="edge"&&r&&!e.curvePoint){const l=No(e,r);return Math.max(0,t-l.start-l.end)}return t}';
  if (s.includes(voOld)) {
    s = s.replace(voOld, voNew);
  } else if (s.includes('i?.wallMeasureMode==="edge"&&r&&!e.curvePoint')) {
    s = s.replace(
      /a\?\.floors\?\.find\(l=>l\.id===a\.activeFloorId\)\?\.walls/g,
      'a?.walls'
    );
    s = s.replace(
      /a\?\.floors\.find\(l=>l\.id===a\.activeFloorId\)\?\.walls/g,
      'a?.walls'
    );
  } else {
    throw new Error('store chunk: vo() pattern not found');
  }

  const ysVoNeedle =
    'const i=e.find(u=>u.id===t);if(!i)throw new Error("Select a wall to resize.");const a=vo(i);';
  const ysCenterNeedle =
    'const i=e.find(u=>u.id===t);if(!i)throw new Error("Select a wall to resize.");let a;if(!i.curvePoint)a=Math.hypot(i.end.x-i.start.x,i.end.y-i.start.y);else{a=0;let n=i.start;for(let o=1;o<=20;o++){const i2=o/20,s2=1-i2,r2={x:s2*s2*i.start.x+2*s2*i2*i.curvePoint.x+i2*i2*i.end.x,y:s2*s2*i.start.y+2*s2*i2*i.curvePoint.y+i2*i2*i.end.y};a+=Math.hypot(r2.x-n.x,r2.y-n.y),n=r2}}';
  if (s.includes(ysVoNeedle)) {
    s = s.replace(ysVoNeedle, ysCenterNeedle);
  } else if (!s.includes(ysCenterNeedle)) {
    throw new Error('store chunk: ys() centerline length pattern not found');
  }

  const brOld =
    'function Br(e,t,n="start"){const o=L(ee);if(!o)return"Select a floor first.";let i;try{i=ys(o.walls,e,t,n)}';
  const brNew =
    'function Br(e,t,n="start"){const o=L(ee);if(!o)return"Select a floor first.";let i,p=t;const h=L(ma),u=o.walls.find(w=>w.id===e);if(h?.wallMeasureMode==="edge"&&u&&!u.curvePoint){const W=No(u,o.walls);p=t+W.start+W.end}try{i=ys(o.walls,e,p,n)}';
  if (s.includes(brOld)) {
    s = s.replace(brOld, brNew);
  } else if (!s.includes('p=t+W.start+W.end}try{i=ys(o.walls,e,p,n)')) {
    throw new Error('store chunk: Br() resize pattern not found');
  }

  const paInject = bridge.buildVendorLengthParseSource();
  const hostedPaNeedle = 'function pa(e,t){return parseHostedLengthToCm(e,t);}';
  const hasHostedPa = s.includes(hostedPaNeedle);

  if (!hasHostedPa) {
    const injectMarker = 'var CM_PER_INCH=';
    let injectStart = s.indexOf(injectMarker);
    while (injectStart >= 0) {
      const injectEnd = s.indexOf(hostedPaNeedle, injectStart);
      if (injectEnd < 0) break;
      s = s.slice(0, injectStart) + s.slice(injectEnd + hostedPaNeedle.length);
      injectStart = s.indexOf(injectMarker);
    }

    const vendorPaStart = s.indexOf('function pa(');
    const ktAnchor = s.indexOf('const Kt=', vendorPaStart >= 0 ? vendorPaStart : 0);
    if (vendorPaStart < 0 || ktAnchor < 0) throw new Error('store chunk: vendor pa()/Kt anchor not found');
    s = s.slice(0, vendorPaStart) + paInject + '\n' + s.slice(ktAnchor);
  }

  if (!s.includes('/*__ZANINI_OPENPLAN3D_PATCH__*/')) {
    s = s.replace(
      'import{b as In}from"./A3oClE6y.js";',
      'import{b as In}from"./A3oClE6y.js";/*__ZANINI_OPENPLAN3D_PATCH__*/'
    );
  }
  return s;
}

function patchEditorCanvasChunk(source) {
  let s = source;

  const brPatchedReturn =
    'snappedToWall:!0,snappedWallId:xt.id})}}return{x:cr(Y.x),y:cr(Y.y),snappedToEndpoint:Y.snappedToEndpoint,snappedToWall:Y.snappedToWall,snappedWallId:Y.snappedWallId}}function Kr(w,st)';
  const brFixedReturn =
    'snappedToWall:!0,snappedWallId:xt.id})}}return Y.snappedToEndpoint||Y.snappedToWall?Y:{x:cr(Y.x),y:cr(Y.y),snappedToEndpoint:Y.snappedToEndpoint,snappedToWall:Y.snappedToWall,snappedWallId:Y.snappedWallId}}function Kr(w,st)';
  const brReturnOld =
    'snappedToWall:!0,snappedWallId:xt.id})}}return Y}function Kr(w,st)';

  if (s.includes(brPatchedReturn)) {
    s = s.replace(brPatchedReturn, brFixedReturn);
  } else if (s.includes(brReturnOld)) {
    s = s.replace(brReturnOld, brFixedReturn);
  } else if (!s.includes('Y.snappedToEndpoint||Y.snappedToWall?Y:{x:cr(Y.x)')) {
    throw new Error('editor chunk: Br snap return pattern not found');
  }

  const krPatched =
    'for(const Zt of xt)if(Math.abs(ot-Zt)<Ut){const sx=w.x+K*Math.cos(Zt),sy=w.y+K*Math.sin(Zt);return{x:cr(sx),y:cr(sy)}}return{x:cr(st.x),y:cr(st.y)}}function tn()';
  const krMain =
    'for(const Zt of xt)if(Math.abs(ot-Zt)<Ut)return{x:w.x+K*Math.cos(Zt),y:w.y+K*Math.sin(Zt)};return st}function tn()';
  if (s.includes(krPatched)) {
    s = s.replace(krPatched, krMain);
  } else if (!s.includes('return st}function tn()') && !s.includes(krMain.slice(0, 40))) {
    throw new Error('editor chunk: Kr ortho pattern not found');
  }

  if (!s.includes('/*__ZANINI_OPENPLAN3D_PATCH__*/')) {
    s = s.replace(
      'const __vite__mapDeps=',
      '/*__ZANINI_OPENPLAN3D_PATCH__*/const __vite__mapDeps='
    );
  }
  return s;
}

function listAllFiles(dir) {
  const out = [];
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) out.push(...listAllFiles(p));
    else out.push(p);
  }
  return out;
}

function cascadeRename(renames) {
  const roots = [
    IMMUTABLE,
    path.join(VENDOR, 'editor'),
    VENDOR,
    path.join(ROOT, 'tests'),
  ];
  const files = roots.flatMap((r) => (fs.existsSync(r) ? listAllFiles(r) : []));
  for (const file of files) {
    if (!/\.(js|html|css|json)$/.test(file)) continue;
    let text = fs.readFileSync(file, 'utf8');
    let changed = false;
    for (const [oldBase, newBase] of Object.entries(renames)) {
      if (text.includes(oldBase)) {
        text = text.split(oldBase).join(newBase);
        changed = true;
      }
    }
    if (changed) fs.writeFileSync(file, text);
  }
}

function expectedBasename(absPath, content) {
  const rel = path.relative(IMMUTABLE, absPath).replace(/\\/g, '/');
  const hash = contentHash(content);
  if (rel.startsWith('entry/')) {
    const prefix = path.basename(rel).split('.')[0];
    return `${prefix}.${hash}.js`;
  }
  if (rel.startsWith('nodes/')) {
    const prefix = path.basename(rel).split('.')[0];
    return `${prefix}.${hash}.js`;
  }
  if (rel.startsWith('chunks/')) {
    return `${hash}.js`;
  }
  return path.basename(rel);
}

function publishContentNamedFile(absPath, content) {
  const oldBase = path.basename(absPath);
  const newBase = expectedBasename(absPath, content);
  if (oldBase === newBase) {
    fs.writeFileSync(absPath, content);
    return { oldBase, newBase, renamed: false, absPath };
  }
  const absNew = path.join(path.dirname(absPath), newBase);
  fs.writeFileSync(absNew, content);
  return { oldBase, newBase, renamed: true, absPath: absNew };
}

function findCanonicalPatchedChunk(kind) {
  const chunksDir = path.join(IMMUTABLE, 'chunks');
  const candidates = fs.readdirSync(chunksDir).filter((f) => f.endsWith('.js'));
  for (const f of candidates) {
    if (f.replace(/\.js$/, '') !== contentHash(fs.readFileSync(path.join(chunksDir, f), 'utf8'))) {
      continue;
    }
    const c = fs.readFileSync(path.join(chunksDir, f), 'utf8');
    if (!c.includes('__ZANINI_OPENPLAN3D_PATCH__')) continue;
    if (kind === 'store' && c.includes('function pa(e,t){return parseHostedLengthToCm')) return f;
    if (kind === 'editor' && c.includes('function Br(w,st){') && c.includes('Y.snappedToEndpoint||Y.snappedToWall')) {
      return f;
    }
  }
  return null;
}

function rehashImmutableGraph() {
  const allRenames = {};
  let iterations = 0;
  let changed = true;
  while (changed && iterations < 40) {
    changed = false;
    iterations += 1;
    const jsFiles = listAllFiles(IMMUTABLE).filter((f) => f.endsWith('.js'));
    for (const absPath of jsFiles) {
      const content = fs.readFileSync(absPath, 'utf8');
      const newBase = expectedBasename(absPath, content);
      const oldBase = path.basename(absPath);
      if (oldBase === newBase) continue;
      const absNew = path.join(path.dirname(absPath), newBase);
      if (fs.existsSync(absNew) && fs.readFileSync(absNew, 'utf8') === content) {
        continue;
      }
      fs.writeFileSync(absNew, content);
      allRenames[oldBase] = newBase;
      cascadeRename({ [oldBase]: newBase });
      changed = true;
    }
  }
  if (iterations >= 40) throw new Error('rehashImmutableGraph: too many iterations');
  return allRenames;
}

function trimEditorIndexPreloads() {
  if (!fs.existsSync(EDITOR_INDEX)) return;
  let html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  const mapMatch = html.match(
    /"\/vendor\/openplan3d\/_app\/immutable\/chunks\/([A-Za-z0-9_-]+\.js)": "\/js\/openplan3d-hosted-locale\.js/
  );
  const localeChunk = mapMatch ? mapMatch[1] : 'NzBhsCJS.js';
  const redundant = new RegExp(
    `\\s*<link href="/vendor/openplan3d/_app/immutable/chunks/${localeChunk.replace('.', '\\.')}" rel="modulepreload">\\n?`
  );
  if (redundant.test(html)) {
    html = html.replace(redundant, '\n');
    fs.writeFileSync(EDITOR_INDEX, html);
  }
}

function loadManifestPaths() {
  let storeRel = 'chunks/1VjVcrjm.js';
  let editorRel = 'chunks/CgAQI-i0.js';
  if (fs.existsSync(MANIFEST)) {
    try {
      const m = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'));
      if (m.storeChunk) storeRel = `chunks/${m.storeChunk}`;
      if (m.editorChunk) editorRel = `chunks/${m.editorChunk}`;
    } catch (e) {
      /* defaults */
    }
  }
  return { storeRel, editorRel };
}

function findEntryBasename() {
  if (fs.existsSync(EDITOR_INDEX)) {
    const html = fs.readFileSync(EDITOR_INDEX, 'utf8');
    const app = html.match(/entry\/(app\.[A-Za-z0-9_-]+\.js)/)?.[1] || null;
    const start = html.match(/entry\/(start\.[A-Za-z0-9_-]+\.js)/)?.[1] || null;
    if (app || start) return { app, start };
  }
  const entryDir = path.join(IMMUTABLE, 'entry');
  const app = fs.readdirSync(entryDir).find((f) => /^app\.[A-Za-z0-9_-]+\.js$/.test(f));
  const start = fs.readdirSync(entryDir).find((f) => /^start\.[A-Za-z0-9_-]+\.js$/.test(f));
  return { app, start };
}

function storeChunkFromEditorIndex() {
  if (!fs.existsSync(EDITOR_INDEX)) return null;
  const html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  return (
    html.match(
      /import\("\/vendor\/openplan3d\/_app\/immutable\/chunks\/([A-Za-z0-9_-]+\.js)"\)\.then\(\(storeModule\)/
    )?.[1] || null
  );
}

function editorChunkFromEntry() {
  const { app } = findEntryBasename();
  if (!app) return null;
  const appPath = path.join(IMMUTABLE, 'entry', app);
  if (!fs.existsSync(appPath)) return null;
  const appJs = fs.readFileSync(appPath, 'utf8');
  const nodeMatch = appJs.match(/"\.\.\/nodes\/(3\.[A-Za-z0-9_-]+\.js)"/);
  if (!nodeMatch) return null;
  const nodePath = path.join(IMMUTABLE, 'nodes', nodeMatch[1]);
  if (!fs.existsSync(nodePath)) return null;
  const nodeJs = fs.readFileSync(nodePath, 'utf8');
  const chunkMatch = nodeJs.match(/from"\.\.\/chunks\/([A-Za-z0-9_-]+\.js)"/);
  return chunkMatch ? chunkMatch[1] : null;
}

function writeManifest(storeChunk, editorChunk, renames) {
  const { app, start } = findEntryBasename();
  const indexStore = storeChunkFromEditorIndex();
  const entryEditor = editorChunkFromEntry();
  const sortedRenames = Object.fromEntries(Object.entries(renames).sort(([a], [b]) => a.localeCompare(b)));
  const manifest = {
    storeChunk: indexStore || storeChunk,
    editorChunk: entryEditor || editorChunk,
    entryApp: app || null,
    entryStart: start || null,
    renames: sortedRenames,
  };
  fs.writeFileSync(MANIFEST, JSON.stringify(manifest, null, 2) + '\n');
  return manifest;
}

function main() {
  const { storeRel, editorRel } = loadManifestPaths();
  const allRenames = {};

  let storeAbs = path.join(IMMUTABLE, storeRel);
  if (!fs.existsSync(storeAbs)) {
    const canonical = findCanonicalPatchedChunk('store');
    if (canonical) storeAbs = path.join(IMMUTABLE, 'chunks', canonical);
  }
  const storePatched = patchStoreChunk(fs.readFileSync(storeAbs, 'utf8'));
  const storePub = publishContentNamedFile(storeAbs, storePatched);
  if (storePub.renamed) {
    allRenames[storePub.oldBase] = storePub.newBase;
    cascadeRename({ [storePub.oldBase]: storePub.newBase });
  }

  let editorAbs = path.join(IMMUTABLE, editorRel);
  if (!fs.existsSync(editorAbs)) {
    const canonical = findCanonicalPatchedChunk('editor');
    if (canonical) editorAbs = path.join(IMMUTABLE, 'chunks', canonical);
  }
  const editorPatched = patchEditorCanvasChunk(fs.readFileSync(editorAbs, 'utf8'));
  const editorPub = publishContentNamedFile(editorAbs, editorPatched);
  if (editorPub.renamed) {
    allRenames[editorPub.oldBase] = editorPub.newBase;
    cascadeRename({ [editorPub.oldBase]: editorPub.newBase });
  }

  if (storePub.renamed || editorPub.renamed || Object.keys(allRenames).length > 0) {
    Object.assign(allRenames, rehashImmutableGraph());
  }
  trimEditorIndexPreloads();

  const storeChunk =
    findCanonicalPatchedChunk('store') || path.basename(storePub.absPath);
  const editorChunk =
    findCanonicalPatchedChunk('editor') || path.basename(editorPub.absPath);

  const manifest = writeManifest(storeChunk, editorChunk, allRenames);
  console.log('patch-openplan3d: ok', manifest);
}

main();

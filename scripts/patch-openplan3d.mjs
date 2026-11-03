#!/usr/bin/env node
/**
 * Apply Zanini hosted patches to vendored openPlan3D immutable chunks, then rename
 * modified files to fresh content hashes and cascade imports through the bundle.
 *
 * Usage: node scripts/patch-openplan3d.mjs
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

const require = createRequire(import.meta.url);
const bridge = require(path.join(ROOT, 'public/js/openplan3d-editor-bridge.js'));

function chunkHash(content) {
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
  const brReturnOld =
    'snappedToWall:!0,snappedWallId:xt.id})}}return Y}function Kr(w,st)';
  const brReturnNew =
    'snappedToWall:!0,snappedWallId:xt.id})}}return{x:cr(Y.x),y:cr(Y.y),snappedToEndpoint:Y.snappedToEndpoint,snappedToWall:Y.snappedToWall,snappedWallId:Y.snappedWallId}}function Kr(w,st)';
  if (s.includes(brReturnOld)) {
    s = s.replace(brReturnOld, brReturnNew);
  } else if (!s.includes('return{x:cr(Y.x),y:cr(Y.y),snappedToEndpoint')) {
    throw new Error('editor chunk: Br snap return pattern not found');
  }

  const krOld =
    'for(const Zt of xt)if(Math.abs(ot-Zt)<Ut)return{x:w.x+K*Math.cos(Zt),y:w.y+K*Math.sin(Zt)};return st}function tn()';
  const krNew =
    'for(const Zt of xt)if(Math.abs(ot-Zt)<Ut){const sx=w.x+K*Math.cos(Zt),sy=w.y+K*Math.sin(Zt);return{x:cr(sx),y:cr(sy)}}return{x:cr(st.x),y:cr(st.y)}}function tn()';
  if (s.includes(krOld)) {
    s = s.replace(krOld, krNew);
  } else if (!s.includes('return{x:cr(st.x),y:cr(st.y)}}function tn()')) {
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

function renameChunk(relPath, newContent) {
  const absOld = path.join(IMMUTABLE, relPath);
  const oldBase = path.basename(relPath);
  const newBase = `${chunkHash(newContent)}.js`;
  if (oldBase === newBase) {
    fs.writeFileSync(absOld, newContent);
    return { oldBase, newBase: oldBase, changed: true, renamed: false };
  }
  const absNew = path.join(path.dirname(absOld), newBase);
  fs.writeFileSync(absNew, newContent);
  fs.unlinkSync(absOld);
  return { oldBase, newBase, changed: true, renamed: true };
}

function main() {
  let storeRel = 'chunks/1VjVcrjm.js';
  let editorRel = 'chunks/CgAQI-i0.js';
  if (fs.existsSync(MANIFEST)) {
    try {
      const m = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'));
      if (m.storeChunk) storeRel = `chunks/${m.storeChunk}`;
      if (m.editorChunk) editorRel = `chunks/${m.editorChunk}`;
    } catch (e) {
      /* use defaults */
    }
  }

  const storePatched = patchStoreChunk(read(storeRel));
  const storeInfo = renameChunk(storeRel, storePatched);
  const renames = { [storeInfo.oldBase]: storeInfo.newBase };

  if (storeInfo.renamed) {
    cascadeRename(renames);
  }

  const editorPatched = patchEditorCanvasChunk(read(editorRel));
  const editorInfo = renameChunk(editorRel, editorPatched);
  if (editorInfo.oldBase !== editorInfo.newBase) {
    renames[editorInfo.oldBase] = editorInfo.newBase;
    cascadeRename({ [editorInfo.oldBase]: editorInfo.newBase });
  } else {
    fs.writeFileSync(path.join(IMMUTABLE, editorRel), editorPatched);
  }

  const manifest = {
    patchedAt: new Date().toISOString(),
    storeChunk: storeInfo.newBase,
    editorChunk: editorInfo.newBase,
    renames,
  };
  fs.writeFileSync(MANIFEST, JSON.stringify(manifest, null, 2) + '\n');
  console.log('patch-openplan3d: ok', manifest);
}

main();

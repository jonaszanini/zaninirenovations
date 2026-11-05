#!/usr/bin/env node
/**
 * Build the hosted openPlan3D bundle under public/vendor/openplan3d-h1/.
 *
 * Re-run (idempotent — must produce zero git diff after the first commit):
 *   1. Keep public/vendor/openplan3d/ byte-identical to upstream main (never patch it).
 *   2. node scripts/patch-openplan3d.mjs
 *   3. git diff  # should be empty on second run
 *
 * Writes scripts/openplan3d-h1.json (store/editor chunk names for tests).
 */
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const PRISTINE = path.join(ROOT, 'public/vendor/openplan3d');
const OUT_SLUG = 'openplan3d-h1';
const OUT = path.join(ROOT, 'public/vendor', OUT_SLUG);
const MANIFEST = path.join(__dirname, 'openplan3d-h1.json');
const EDITOR_INDEX = path.join(OUT, 'editor/index.html');
/** Active immutable graph suffix (must not reuse previously served -h3.js bytes for patches). */
const IMMUTABLE_GRAPH_SUFFIX = 'h4';
const PATCHED_STORE_CHUNK_BASENAME = `1VjVcrjm-${IMMUTABLE_GRAPH_SUFFIX}.js`;
const PATCHED_EDITOR_CHUNK_BASENAME = `CgAQI-i0-${IMMUTABLE_GRAPH_SUFFIX}.js`;
const LEGACY_IMMUTABLE_SUFFIX = 'h3';

const require = createRequire(import.meta.url);
const bridge = require(path.join(ROOT, 'public/js/openplan3d-editor-bridge.js'));

const VENDOR_PREFIX = '/vendor/openplan3d';
const OUT_PREFIX = `/vendor/${OUT_SLUG}`;

function countMatches(haystack, needle) {
  if (!needle) return 0;
  let count = 0;
  let idx = 0;
  while ((idx = haystack.indexOf(needle, idx)) !== -1) {
    count += 1;
    idx += needle.length;
  }
  return count;
}

/** @returns {string} */
function replaceExpected(source, oldStr, newStr, label, expectedCount = 1) {
  const count = countMatches(source, oldStr);
  if (count !== expectedCount) {
    console.error(`patch-openplan3d: ${label}: expected ${expectedCount} match(es), found ${count}`);
    process.exit(1);
  }
  if (expectedCount === 0) return source;
  return source.replace(oldStr, newStr);
}

function replaceAllExpected(source, oldStr, newStr, label, expectedCount) {
  const count = countMatches(source, oldStr);
  if (count !== expectedCount) {
    console.error(`patch-openplan3d: ${label}: expected ${expectedCount} match(es), found ${count}`);
    process.exit(1);
  }
  if (expectedCount === 0) return source;
  return source.split(oldStr).join(newStr);
}

function listAllFiles(dir) {
  const out = [];
  if (!fs.existsSync(dir)) return out;
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) out.push(...listAllFiles(p));
    else out.push(p);
  }
  return out;
}

function publishPatchedStoreChunk(vendorBasename, patchedSource) {
  const outPath = path.join(OUT, '_app/immutable/chunks', PATCHED_STORE_CHUNK_BASENAME);
  fs.writeFileSync(outPath, patchedSource);
  return PATCHED_STORE_CHUNK_BASENAME;
}

function publishPatchedEditorChunk(vendorBasename, patchedSource) {
  const outPath = path.join(OUT, '_app/immutable/chunks', PATCHED_EDITOR_CHUNK_BASENAME);
  fs.writeFileSync(outPath, patchedSource);
  return PATCHED_EDITOR_CHUNK_BASENAME;
}

function propagateImmutableRenames(rootDir, initialRenames) {
  const renames = new Map(initialRenames);
  let changed = true;
  while (changed) {
    changed = false;
    const currentFiles = listAllFiles(rootDir);
    for (const f of currentFiles) {
      if (!/\.(html|js|css|json)$/.test(f)) continue;
      const base = path.basename(f);
      const fileText = fs.readFileSync(f, 'utf8');
      for (const [fromBase, toBase] of renames.entries()) {
        if (fileText.includes(fromBase)) {
          const norm = f.replace(/\\/g, '/');
          if (norm.includes('_app/immutable') && !renames.has(base)) {
            if (base.endsWith(`-${IMMUTABLE_GRAPH_SUFFIX}.js`)) {
              renames.set(base, base);
              continue;
            }
            if (base.endsWith(`-${LEGACY_IMMUTABLE_SUFFIX}.js`)) {
              renames.set(
                base,
                base.replace(`-${LEGACY_IMMUTABLE_SUFFIX}.js`, `-${IMMUTABLE_GRAPH_SUFFIX}.js`)
              );
              changed = true;
              continue;
            }
            const newBase = base.replace(/(-h[0-9]+)?\.js$/, `-${IMMUTABLE_GRAPH_SUFFIX}.js`);
            renames.set(base, newBase);
            changed = true;
          }
        }
      }
    }
  }

  // Copy each file that needs a new name to -h3.js (preserving original live file!)
  // Do not overwrite store or editor chunks which are published directly!
  for (const [fromBase, toBase] of renames) {
    if (fromBase === toBase) continue;
    if (toBase === PATCHED_STORE_CHUNK_BASENAME || toBase === PATCHED_EDITOR_CHUNK_BASENAME) continue;
    for (const f of listAllFiles(rootDir)) {
      if (path.basename(f) === fromBase) {
        const newPath = path.join(path.dirname(f), toBase);
        fs.copyFileSync(f, newPath);
      }
    }
  }

  // Rewrite references inside the new -h4.js files and HTML entry pages only.
  for (const f of listAllFiles(rootDir)) {
    if (!/\.(html|js|css|json)$/.test(f)) continue;
    const base = path.basename(f);
    const isNewOrHtml =
      base.endsWith(`-${IMMUTABLE_GRAPH_SUFFIX}.js`) || f.endsWith('.html');
    if (!isNewOrHtml) continue;

    let text = fs.readFileSync(f, 'utf8');
    let fileChanged = false;
    for (const [fromBase, toBase] of renames) {
      if (fromBase !== toBase && text.includes(fromBase)) {
        text = text.split(fromBase).join(toBase);
        fileChanged = true;
      }
    }
    if (fileChanged) {
      fs.writeFileSync(f, text);
    }
  }
  return renames;
}

function rewriteVendorUrls(rootDir) {
  for (const file of listAllFiles(rootDir)) {
    if (!/\.(html|js|css|json|svg)$/.test(file)) continue;
    let text = fs.readFileSync(file, 'utf8');
    if (!text.includes(VENDOR_PREFIX)) continue;
    text = text.split(VENDOR_PREFIX).join(OUT_PREFIX);
    fs.writeFileSync(file, text);
  }
}

function patchStoreChunk(source) {
  let s = source;
  const centerlineCount = countMatches(s, 'wallMeasureMode:"centerline"');
  if (centerlineCount > 0) {
    s = replaceAllExpected(
      s,
      'wallMeasureMode:"centerline"',
      'wallMeasureMode:"edge"',
      'store chunk: default wallMeasureMode centerline→edge',
      centerlineCount
    );
  } else if (!s.includes('wallMeasureMode:"edge"')) {
    console.error('patch-openplan3d: store chunk: expected centerline default or existing edge default');
    process.exit(1);
  }

  const voOld =
    'function vo(e){if(!e.curvePoint)return Math.hypot(e.end.x-e.start.x,e.end.y-e.start.y);let t=0,n=e.start;for(let o=1;o<=20;o++){const i=o/20,a=1-i,s={x:a*a*e.start.x+2*a*i*e.curvePoint.x+i*i*e.end.x,y:a*a*e.start.y+2*a*i*e.curvePoint.y+i*i*e.end.y};t+=Math.hypot(s.x-n.x,s.y-n.y),n=s}return t}';
  const voNew =
    'function vo(e){let t;if(!e.curvePoint)t=Math.hypot(e.end.x-e.start.x,e.end.y-e.start.y);else{t=0;let n=e.start;for(let o=1;o<=20;o++){const i=o/20,a=1-i,s={x:a*a*e.start.x+2*a*i*e.curvePoint.x+i*i*e.end.x,y:a*a*e.start.y+2*a*i*e.curvePoint.y+i*i*e.end.y};t+=Math.hypot(s.x-n.x,s.y-n.y),n=s}}const i=L(ma),a=L(ee),r=a?.walls;if(i?.wallMeasureMode==="edge"&&r&&!e.curvePoint){const l=No(e,r);return Math.max(0,t-l.start-l.end)}return t}';
  if (s.includes(voOld)) {
    s = replaceExpected(s, voOld, voNew, 'store chunk: vo() edge length', 1);
  } else if (!s.includes('i?.wallMeasureMode==="edge"&&r&&!e.curvePoint')) {
    console.error('patch-openplan3d: store chunk: vo() pattern not found');
    process.exit(1);
  }

  const ysVoNeedle =
    'const i=e.find(u=>u.id===t);if(!i)throw new Error("Select a wall to resize.");const a=vo(i);';
  const ysCenterNeedle =
    'const i=e.find(u=>u.id===t);if(!i)throw new Error("Select a wall to resize.");let a;if(!i.curvePoint)a=Math.hypot(i.end.x-i.start.x,i.end.y-i.start.y);else{a=0;let n=i.start;for(let o=1;o<=20;o++){const i2=o/20,s2=1-i2,r2={x:s2*s2*i.start.x+2*s2*i2*i.curvePoint.x+i2*i2*i.end.x,y:s2*s2*i.start.y+2*s2*i2*i.curvePoint.y+i2*i2*i.end.y};a+=Math.hypot(r2.x-n.x,r2.y-n.y),n=r2}}';
  if (s.includes(ysVoNeedle)) {
    s = replaceExpected(s, ysVoNeedle, ysCenterNeedle, 'store chunk: ys() centerline length', 1);
  } else if (!s.includes('ysCenterNeedle') && !s.includes('let a;if(!i.curvePoint)a=Math.hypot(i.end.x-i.start.x')) {
    console.error('patch-openplan3d: store chunk: ys() centerline length pattern not found');
    process.exit(1);
  }

  const brOld =
    'function Br(e,t,n="start"){const o=L(ee);if(!o)return"Select a floor first.";let i;try{i=ys(o.walls,e,t,n)}';
  const brNew =
    'function Br(e,t,n="start"){const o=L(ee);if(!o)return"Select a floor first.";let i,p=t;const h=L(ma),u=o.walls.find(w=>w.id===e);if(h?.wallMeasureMode==="edge"&&u&&!u.curvePoint){const W=No(u,o.walls);p=t+W.start+W.end}try{i=ys(o.walls,e,p,n)}';
  if (s.includes(brOld)) {
    s = replaceExpected(s, brOld, brNew, 'store chunk: Br() typed length', 1);
  } else if (!s.includes('p=t+W.start+W.end}try{i=ys(o.walls,e,p,n)')) {
    console.error('patch-openplan3d: store chunk: Br() resize pattern not found');
    process.exit(1);
  }

  const paInject = bridge.buildVendorLengthParseSource().replace(/\r\n/g, '\n');
  const hostedPaNeedle = 'function pa(e,t){return parseHostedLengthToCm(e,t);}';
  if (!s.includes(hostedPaNeedle)) {
    const vendorPaStart = s.indexOf('function pa(');
    const ktAnchor = s.indexOf('const Kt=', vendorPaStart >= 0 ? vendorPaStart : 0);
    if (vendorPaStart < 0 || ktAnchor < 0) {
      console.error('patch-openplan3d: store chunk: vendor pa()/Kt anchor not found');
      process.exit(1);
    }
    s = s.slice(0, vendorPaStart) + paInject + '\n' + s.slice(ktAnchor);
  }

  const oOld = 'o=(i,a)=>{if(a!==(e.get(i)??null))throw new Ue}';
  const oNew = 'o=(i,a)=>{if(typeof window!=="undefined"&&window.parent&&window.parent!==window)return;if(a!==(e.get(i)??null))throw new Ue}';
  if (s.includes(oOld)) {
    s = replaceExpected(s, oOld, oNew, 'store chunk: bypass o conflict check in hosted iframe', 1);
  } else if (!s.includes('if(typeof window!=="undefined"&&window.parent&&window.parent!==window)return;if(a!==(e.get(i)??null))throw new Ue')) {
    console.error('patch-openplan3d: store chunk: o() conflict check pattern not found');
    process.exit(1);
  }

  const saveOld = 'const d=c.objectStore("projects"),h=await V(d.get(a))??null;if(r!==n.get(a))throw new Ue;o(a,h),d.put(s,a)';
  const saveNew = 'const d=c.objectStore("projects");if(typeof window==="undefined"||!window.parent||window.parent===window){const h=await V(d.get(a))??null;if(r!==n.get(a))throw new Ue;o(a,h);}d.put(s,a)';
  if (s.includes(saveOld)) {
    s = replaceExpected(s, saveOld, saveNew, 'store chunk: bypass save conflict check in hosted iframe', 1);
  } else if (!s.includes('if(typeof window==="undefined"||!window.parent||window.parent===window)')) {
    console.error('patch-openplan3d: store chunk: save() conflict check pattern not found');
    process.exit(1);
  }

  const assertCurrentOld =
    'async assertCurrent(i){o(i,await Re("projects",i))}';
  const assertCurrentNew =
    'async assertCurrent(i){if(typeof window!=="undefined"&&window.parent&&window.parent!==window)return;o(i,await Re("projects",i))}';
  if (s.includes(assertCurrentOld)) {
    s = replaceExpected(s, assertCurrentOld, assertCurrentNew, 'store chunk: bypass assertCurrent in hosted iframe', 1);
  } else if (!s.includes('if(typeof window!=="undefined"&&window.parent&&window.parent!==window)return;o(i,await Re("projects",i))')) {
    console.error('patch-openplan3d: store chunk: assertCurrent pattern not found');
    process.exit(1);
  }

  const unOld = 'function un(e){ce.length=0,Ce.length=0,xe=0,_t=null,qe="",nt(),it(),I.set(e),Ye()}';
  const unPatched =
    'function un(e){if(typeof window!=="undefined"&&window.OpenPlan3DMeasurementBridge&&typeof window.OpenPlan3DMeasurementBridge.syncEditorRoomBoundariesForCanvasFill==="function"){const __zaniniRf=window.OpenPlan3DMeasurementBridge.syncEditorRoomBoundariesForCanvasFill(e);__zaniniRf&&__zaniniRf.changed&&__zaniniRf.plan&&(e=__zaniniRf.plan)}ce.length=0,Ce.length=0,xe=0,_t=null,qe="",nt(),it(),I.set(e),Ye()}';
  if (s.includes('__zaniniRf=window.OpenPlan3DMeasurementBridge.syncEditorRoomBoundariesForCanvasFill')) {
    s = replaceExpected(s, unPatched, unOld, 'store chunk: remove load-time room sync from un()', 1);
  }

  const vaOld =
    'function va(e,t=[]){const n=vt(e.walls);return Ro(e,t,n).map(o=>({room:o,polygon:Bo(o,n)}))}';
  const vaPatchedMerge =
    'function va(e,t=[]){const n=vt(e.walls);const __zaniniAuto=Ro(e,t,n).map(o=>({room:o,polygon:Bo(o,n)})).filter(x=>x.polygon.length>=3);const __zaniniSeen=new Set(__zaniniAuto.map(x=>x.room.id));for(const __zaniniRoom of e.rooms??[]){if(!__zaniniRoom||__zaniniSeen.has(__zaniniRoom.id))continue;let __zaniniPoly=__zaniniRoom.polygon&&__zaniniRoom.polygon.length>=3?__zaniniRoom.polygon.slice():Bo(__zaniniRoom,n);if(__zaniniPoly.length<3)continue;__zaniniAuto.push({room:__zaniniRoom,polygon:__zaniniPoly});__zaniniSeen.add(__zaniniRoom.id)}return __zaniniAuto}';
  const vaEphemeral =
    'function va(e,t=[]){const n=vt(e.walls);let __zaniniAuto=Ro(e,t,n).map(o=>({room:o,polygon:Bo(o,n)})).filter(x=>x.polygon&&x.polygon.length>=3);if(__zaniniAuto.length===0&&typeof window!=="undefined"&&window.OpenPlan3DMeasurementBridge&&typeof window.OpenPlan3DMeasurementBridge.canvasFillRoomEntries==="function"){const __zaniniEphemeral=window.OpenPlan3DMeasurementBridge.canvasFillRoomEntries({unit:e.unit||"cm",activeFloorId:e.id,floors:[e],floor:e})||[];if(__zaniniEphemeral.length>0)__zaniniAuto=__zaniniEphemeral;}if(__zaniniAuto.length===0){for(const __zaniniRoom of e.rooms??[]){if(!__zaniniRoom)continue;let __zaniniPoly=__zaniniRoom.polygon&&__zaniniRoom.polygon.length>=3?__zaniniRoom.polygon.slice():Bo(__zaniniRoom,n);if(__zaniniPoly.length<3)continue;__zaniniAuto.push({room:__zaniniRoom,polygon:__zaniniPoly});}}return __zaniniAuto}';
  const vaNew =
    'function va(e,t=[]){const n=vt(e.walls);let __zaniniAuto=Ro(e,t,n).map(o=>({room:o,polygon:Bo(o,n)})).filter(x=>x.polygon&&x.polygon.length>=3);if(__zaniniAuto.length===0&&typeof window!=="undefined"&&window.OpenPlan3DMeasurementBridge&&typeof window.OpenPlan3DMeasurementBridge.canvasFillRoomEntries==="function"){const __zaniniEphemeral=window.OpenPlan3DMeasurementBridge.canvasFillRoomEntries({unit:e.unit||"cm",activeFloorId:e.id,floors:[e],floor:e})||[];if(__zaniniEphemeral.length>0)__zaniniAuto=__zaniniEphemeral;}if(__zaniniAuto.length===0){for(const __zaniniRoom of e.rooms??[]){if(!__zaniniRoom)continue;let __zaniniPoly=__zaniniRoom.polygon&&__zaniniRoom.polygon.length>=3?__zaniniRoom.polygon.slice():Bo(__zaniniRoom,n);if(__zaniniPoly.length<3)continue;let __zaniniRoomUse=__zaniniRoom;if(typeof __zaniniRoom.area!=="number"||!Number.isFinite(__zaniniRoom.area)){let __zaniniPt=0;for(let __i=0;__i<__zaniniPoly.length;__i++){const __o=(__i+1)%__zaniniPoly.length;__zaniniPt+=__zaniniPoly[__i].x*__zaniniPoly[__o].y-__zaniniPoly[__o].x*__zaniniPoly[__i].y}__zaniniPt/=2;__zaniniRoomUse={...__zaniniRoom,area:Math.round(Math.abs(__zaniniPt)/100)/100,floorTexture:__zaniniRoom.floorTexture||"hardwood"};}__zaniniAuto.push({room:__zaniniRoomUse,polygon:__zaniniPoly});}}return __zaniniAuto}';
  if (s.includes(vaEphemeral) && !s.includes('__zaniniRoomUse=__zaniniRoom')) {
    s = replaceExpected(s, vaEphemeral, vaNew, 'store chunk: va() backfill area on persisted rooms', 1);
  } else if (s.includes(vaPatchedMerge)) {
    s = replaceExpected(s, vaPatchedMerge, vaNew, 'store chunk: va() ephemeral canvas fill without duplicate merge', 1);
  } else if (s.includes(vaOld)) {
    s = replaceExpected(s, vaOld, vaNew, 'store chunk: va() ephemeral canvas fill', 1);
  } else if (!s.includes('canvasFillRoomEntries')) {
    console.error('patch-openplan3d: store chunk: va() canvas-fill pattern not found');
    process.exit(1);
  }

  const boOld =
    'function Bo(e,t){const n=new Set(e.walls);let o=t.filter(c=>(c.wallIds??[c.wallId]).some(d=>n.has(d))),i=!0;for(;i&&o.length>=3;)i=!1,o=o.filter(c=>{const d=o.filter(u=>H(u.start,c.start)||H(u.end,c.start)).length,h=o.filter(u=>H(u.start,c.end)||H(u.end,c.end)).length;return d<2||h<2?(i=!0,!1):!0});if(o.length<3)return[];const a=new Set;let s=[],r=0,l=[];for(const c of o){if(a.has(c))continue;const d=[c.start];a.add(c);let h=c.end;for(;!H(h,d[0]);){d.push(h);const u=o.find(f=>!a.has(f)&&(H(f.start,h)||H(f.end,h)));if(!u)break;a.add(u),h=H(u.start,h)?u.end:u.start}if(H(h,d[0])){const u=Math.abs(pt(d));u>r&&(r=u,s=d)}else d.length>l.length&&(l=d)}return s.length>=3?s:l}';
  const boNew =
    'function Bo(e,t){const n=new Set(e.walls);let o=t.filter(c=>(c.wallIds??[c.wallId]).some(d=>n.has(d))),i=!0;for(;i&&o.length>=3;)i=!1,o=o.filter(c=>{const d=o.filter(u=>H(u.start,c.start)||H(u.end,c.start)).length,h=o.filter(u=>H(u.start,c.end)||H(u.end,c.end)).length;return d<2||h<2?(i=!0,!1):!0});if(o.length<3)return e.polygon&&e.polygon.length>=3?e.polygon.slice():[];const a=new Set;let s=[],r=0,l=[];for(const c of o){if(a.has(c))continue;const d=[c.start];a.add(c);let h=c.end;for(;!H(h,d[0]);){d.push(h);const u=o.find(f=>!a.has(f)&&(H(f.start,h)||H(f.end,h)));if(!u)break;a.add(u),h=H(u.start,h)?u.end:u.start}if(H(h,d[0])){const u=Math.abs(pt(d));u>r&&(r=u,s=d)}else d.length>l.length&&(l=d)}return s.length>=3?s:e.polygon&&e.polygon.length>=3?e.polygon.slice():l.length>=3?l:[]}';
  if (s.includes(boOld)) {
    s = replaceExpected(s, boOld, boNew, 'store chunk: Bo() use persisted room.polygon fallback', 1);
  } else if (!s.includes('return e.polygon&&e.polygon.length>=3?e.polygon.slice():[]')) {
    console.error('patch-openplan3d: store chunk: Bo() polygon fallback pattern not found');
    process.exit(1);
  }

  if (!s.includes('/*__ZANINI_OPENPLAN3D_PATCH__*/')) {
    s = replaceExpected(
      s,
      'import{b as In}from"./A3oClE6y.js";',
      'import{b as In}from"./A3oClE6y.js";/*__ZANINI_OPENPLAN3D_PATCH__*/',
      'store chunk: patch marker',
      1
    );
  }
  return s;
}

function patchEditorCanvasChunk(source) {
  let s = source;

  const brReturnOld =
    'snappedToWall:!0,snappedWallId:xt.id})}}return Y}function Kr(w,st)';
  const brReturnNew =
    'snappedToWall:!0,snappedWallId:xt.id})}}return Y.snappedToEndpoint||Y.snappedToWall?Y:{x:cr(Y.x),y:cr(Y.y),snappedToEndpoint:Y.snappedToEndpoint,snappedToWall:Y.snappedToWall,snappedWallId:Y.snappedWallId}}function Kr(w,st)';
  if (s.includes(brReturnOld)) {
    s = replaceExpected(s, brReturnOld, brReturnNew, 'editor chunk: Br snap return', 1);
  } else if (!s.includes('Y.snappedToEndpoint||Y.snappedToWall?Y:{x:cr(Y.x)')) {
    console.error('patch-openplan3d: editor chunk: Br snap return pattern not found');
    process.exit(1);
  }

  const krVendor =
    'function Kr(w,st){if(!t(ln))return st;const Y=st.x-w.x,mt=st.y-w.y,K=Math.hypot(Y,mt);if(K<5)return st;const ot=Math.atan2(mt,Y),xt=[0,Math.PI/4,Math.PI/2,3*Math.PI/4,Math.PI,-Math.PI,-3*Math.PI/4,-Math.PI/2,-Math.PI/4],Ut=Math.PI/18;for(const Zt of xt)if(Math.abs(ot-Zt)<Ut)return{x:w.x+K*Math.cos(Zt),y:w.y+K*Math.sin(Zt)};return st}function tn()';
  const krPatched =
    'function Kr(w,st){if(!t(ln))return st;if(st.snappedToEndpoint||st.snappedToWall)return st;const Y=st.x-w.x,mt=st.y-w.y,K=Math.hypot(Y,mt);if(K<5)return st;const ot=Math.atan2(mt,Y),xt=[0,Math.PI/4,Math.PI/2,3*Math.PI/4,Math.PI,-Math.PI,-3*Math.PI/4,-Math.PI/2,-Math.PI/4],Ut=Math.PI/18;for(const Zt of xt)if(Math.abs(ot-Zt)<Ut){let H=K;t(Ce).units==="imperial"&&(H=cr(K));return{x:w.x+H*Math.cos(Zt),y:w.y+H*Math.sin(Zt)}}return t(Ce).units==="imperial"?{x:cr(st.x),y:cr(st.y)}:st}function tn()';
  const krLegacyNn =
    'function Kr(w,st){if(!t(ln))return st;if(st.snappedToEndpoint||st.snappedToWall)return st;const Y=st.x-w.x,mt=st.y-w.y,K=Math.hypot(Y,mt);if(K<5)return st;const ot=Math.atan2(mt,Y),xt=[0,Math.PI/4,Math.PI/2,3*Math.PI/4,Math.PI,-Math.PI,-3*Math.PI/4,-Math.PI/2,-Math.PI/4],Ut=Math.PI/18;for(const Zt of xt)if(Math.abs(ot-Zt)<Ut){let H=K;t(nn)&&(H=cr(K));return{x:w.x+H*Math.cos(Zt),y:w.y+K*Math.sin(Zt)}}return t(nn)?{x:cr(st.x),y:cr(st.y)}:st}function tn()';
  if (s.includes(krVendor)) {
    s = replaceExpected(s, krVendor, krPatched, 'editor chunk: Kr imperial ortho + grid snap', 1);
  } else if (s.includes(krLegacyNn)) {
    s = replaceExpected(s, krLegacyNn, krPatched, 'editor chunk: Kr nn→Ce.units imperial gate', 1);
  } else if (!s.includes('t(Ce).units==="imperial"&&(H=cr(K))')) {
    console.error('patch-openplan3d: editor chunk: Kr() pattern not found');
    process.exit(1);
  }

  const joOrthoOld =
    'st={x:t(O).x+K*Math.cos(Ut),y:t(O).y+K*Math.sin(Ut)}}}else st=Kr(t(O),st);return st}function bi()';
  const joOrthoNew =
    'let H=K;t(ln)&&t(Ce).units==="imperial"&&(H=cr(K));st={x:t(O).x+H*Math.cos(Ut),y:t(O).y+H*Math.sin(Ut)}}}else st=Kr(t(O),st);return st}function bi()';
  const joLegacyNn =
    'let H=K;t(ln)&&t(nn)&&(H=cr(K));st={x:t(O).x+H*Math.cos(Ut),y:t(O).y+H*Math.sin(Ut)}}}else st=Kr(t(O),st);return st}function bi()';
  if (s.includes(joOrthoOld)) {
    s = replaceExpected(s, joOrthoOld, joOrthoNew, 'editor chunk: jo draw ortho inch length', 1);
  } else if (s.includes(joLegacyNn)) {
    s = replaceExpected(s, joLegacyNn, joOrthoNew, 'editor chunk: jo nn→Ce.units imperial gate', 1);
  } else if (!s.includes('t(ln)&&t(Ce).units==="imperial"&&(H=cr(K));st={x:t(O).x+H*Math.cos(Ut)')) {
    console.error('patch-openplan3d: editor chunk: jo() ortho pattern not found');
    process.exit(1);
  }

  if (!s.includes('/*__ZANINI_OPENPLAN3D_PATCH__*/')) {
    s = replaceExpected(
      s,
      'const __vite__mapDeps=',
      '/*__ZANINI_OPENPLAN3D_PATCH__*/const __vite__mapDeps=',
      'editor chunk: patch marker',
      1
    );
  }
  return s;
}

function fileContentVersion16(absPath) {
  const data = fs.readFileSync(absPath, 'utf8').replace(/\r\n/g, '\n');
  return crypto.createHash('sha256').update(data).digest('hex').slice(0, 16);
}

function patchHostedEditorIndexBoot() {
  let html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  const localeV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-hosted-locale.js'));
  const measureV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-measurement-bridge.js'));
  const bridgeV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-editor-bridge.js'));
  const extV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-zanini-extensions.js'));
  const toolsV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-zanini-tools.js'));

  html = html.replace(
    /openplan3d-editor-bridge\.js\?v=[A-Za-z0-9]+/,
    `openplan3d-editor-bridge.js?v=${bridgeV}`
  );

  const measureTag = `<script src="/js/openplan3d-measurement-bridge.js?v=${measureV}"></script>`;
  if (!html.includes('openplan3d-measurement-bridge.js')) {
    html = html.replace(
      `<script src="/js/openplan3d-editor-bridge.js?v=${bridgeV}"></script>`,
      `${measureTag}\n\t\t<script src="/js/openplan3d-editor-bridge.js?v=${bridgeV}"></script>`
    );
  } else {
    html = html.replace(/openplan3d-measurement-bridge\.js\?v=[A-Za-z0-9]+/, `openplan3d-measurement-bridge.js?v=${measureV}`);
  }

  const extTag = `<script src="/js/openplan3d-zanini-extensions.js?v=${extV}"></script>`;
  if (!html.includes('openplan3d-zanini-extensions.js')) {
    html = html.replace(
      `<script src="/js/openplan3d-editor-bridge.js?v=${bridgeV}"></script>`,
      `<script src="/js/openplan3d-editor-bridge.js?v=${bridgeV}"></script>\n\t\t${extTag}`
    );
  } else {
    html = html.replace(/openplan3d-zanini-extensions\.js\?v=[A-Za-z0-9]+/, `openplan3d-zanini-extensions.js?v=${extV}`);
  }

  const toolsTag = `<script src="/js/openplan3d-zanini-tools.js?v=${toolsV}"></script>`;
  if (!html.includes('openplan3d-zanini-tools.js')) {
    html = html.replace(
      `<script src="/js/openplan3d-editor-bridge.js?v=${bridgeV}"></script>`,
      `<script src="/js/openplan3d-editor-bridge.js?v=${bridgeV}"></script>\n\t\t${toolsTag}`
    );
  } else {
    html = html.replace(/openplan3d-zanini-tools\.js\?v=[A-Za-z0-9]+/, `openplan3d-zanini-tools.js?v=${toolsV}`);
  }

  if (!html.includes('type="importmap"')) {
    const importMap =
      `\n\t\t<script type="importmap">\n\t\t{\n\t\t\t"imports": {\n\t\t\t\t"${OUT_PREFIX}/_app/immutable/chunks/NzBhsCJS.js": "/js/openplan3d-hosted-locale.js?v=${localeV}"\n\t\t\t}\n\t\t}\n\t\t</script>`;
    html = html.replace('<title>open3dFloorplan</title>', `<title>open3dFloorplan</title>${importMap}`);
  } else {
    html = html.replace(
      /\/js\/openplan3d-hosted-locale\.js\?v=[A-Za-z0-9]+/,
      `/js/openplan3d-hosted-locale.js?v=${localeV}`
    );
  }

  if (!html.includes('installHostedWallMeasureSettings')) {
    const snapNeedle = /if\s*\(settingsStore\)\s*\{[\r\n\t\s]*bridge\.installHostedSnapGrid\(settingsStore\);[\r\n\t\s]*\}/;
    html = html.replace(
      snapNeedle,
      'if (settingsStore) {\r\n\t\t\t\t\t\t\tbridge.installHostedSnapGrid(settingsStore);\r\n\t\t\t\t\t\t\tif (typeof bridge.installHostedWallMeasureSettings === \'function\') {\r\n\t\t\t\t\t\t\t\tbridge.installHostedWallMeasureSettings(settingsStore);\r\n\t\t\t\t\t\t\t}\r\n\t\t\t\t\t\t}'
    );
  }
  fs.writeFileSync(EDITOR_INDEX, html);

  const embedPath = path.join(ROOT, 'tests/js/fixtures/openplan3d-editor-embed.html');
  if (fs.existsSync(embedPath)) {
    let embedHtml = fs.readFileSync(embedPath, 'utf8');
    embedHtml = embedHtml.replace(
      /openplan3d-editor-bridge\.js\?v=[A-Za-z0-9]+/,
      `openplan3d-editor-bridge.js?v=${bridgeV}`
    );
    embedHtml = embedHtml.replace(
      /openplan3d-measurement-bridge\.js\?v=[A-Za-z0-9]+/,
      `openplan3d-measurement-bridge.js?v=${measureV}`
    );
    embedHtml = embedHtml.replace(
      /openplan3d-zanini-extensions\.js\?v=[A-Za-z0-9]+/,
      `openplan3d-zanini-extensions.js?v=${extV}`
    );
    embedHtml = embedHtml.replace(
      /openplan3d-zanini-tools\.js\?v=[A-Za-z0-9]+/,
      `openplan3d-zanini-tools.js?v=${toolsV}`
    );
    fs.writeFileSync(embedPath, embedHtml);
  }
}

function trimRedundantLocaleModulepreload() {
  const localeChunk = 'NzBhsCJS.js';
  let html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  const redundant = new RegExp(
    `\\s*<link href="${OUT_PREFIX.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/_app/immutable/chunks/${localeChunk.replace('.', '\\.')}" rel="modulepreload">\\n?`
  );
  if (redundant.test(html)) {
    html = html.replace(redundant, '\n');
    fs.writeFileSync(EDITOR_INDEX, html);
  }
  return localeChunk;
}

function rewriteHtmlEntryChunkSlugs(rootDir, fromSuffix, toSuffix) {
  const htmlFiles = [
    path.join(rootDir, 'editor/index.html'),
    path.join(rootDir, 'index.html'),
    path.join(rootDir, 'editor.html'),
  ];
  for (const htmlFile of htmlFiles) {
    if (!fs.existsSync(htmlFile)) continue;
    let text = fs.readFileSync(htmlFile, 'utf8');
    if (!text.includes(fromSuffix)) continue;
    text = text.split(fromSuffix).join(toSuffix);
    fs.writeFileSync(htmlFile, text);
  }
}

/** Restore -h3 immutable files from origin/main so cached clients keep the prior graph bytes. */
function restoreLegacyImmutableGraphFromMain() {
  const immutableRoot = path.join(OUT, '_app/immutable');
  if (!fs.existsSync(immutableRoot)) return;
  for (const f of listAllFiles(immutableRoot)) {
    if (!f.endsWith(`-${LEGACY_IMMUTABLE_SUFFIX}.js`)) continue;
    const rel = path.relative(ROOT, f).replace(/\\/g, '/');
    try {
      const content = execSync(`git show origin/main:${rel}`, {
        encoding: 'utf8',
        cwd: ROOT,
        stdio: ['ignore', 'pipe', 'pipe'],
      });
      fs.writeFileSync(f, content);
    } catch (e) {
      console.warn(`patch-openplan3d: skip legacy restore (not on main): ${rel}`);
    }
  }
}

function syncEditorIndexStoreImport(storeChunkBasename) {
  let html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  const storeImport = `/vendor/openplan3d-h1/_app/immutable/chunks/${storeChunkBasename}`;
  html = html.replace(
    /import\("\/vendor\/openplan3d-h1\/_app\/immutable\/chunks\/1VjVcrjm-h[0-9]+\.js"\)/g,
    `import("${storeImport}")`
  );
  html = html.replace(
    /<link href="\/vendor\/openplan3d-h1\/_app\/immutable\/chunks\/1VjVcrjm-h[0-9]+\.js" rel="modulepreload">/g,
    `<link href="${storeImport}" rel="modulepreload">`
  );
  fs.writeFileSync(EDITOR_INDEX, html);
}

function main() {
  if (!fs.existsSync(PRISTINE)) {
    throw new Error('patch-openplan3d: missing pristine tree at public/vendor/openplan3d');
  }


  if (!fs.existsSync(OUT)) {
    fs.cpSync(PRISTINE, OUT, { recursive: true });
    rewriteVendorUrls(OUT);
  }

  const vendorStoreChunk = '1VjVcrjm.js';
  const vendorLiveEditorChunk = 'CgAQI-i0-h1.js';
  const vendorPristineEditorChunk = 'CgAQI-i0.js';

  const storeVendorPath = path.join(OUT, '_app/immutable/chunks', vendorStoreChunk);
  const editorVendorPath = fs.existsSync(path.join(OUT, '_app/immutable/chunks', vendorLiveEditorChunk))
    ? path.join(OUT, '_app/immutable/chunks', vendorLiveEditorChunk)
    : path.join(PRISTINE, '_app/immutable/chunks', vendorPristineEditorChunk);

  const storeChunk = publishPatchedStoreChunk(
    vendorStoreChunk,
    patchStoreChunk(fs.readFileSync(storeVendorPath, 'utf8'))
  );
  const editorChunk = publishPatchedEditorChunk(
    vendorLiveEditorChunk,
    patchEditorCanvasChunk(fs.readFileSync(editorVendorPath, 'utf8'))
  );

  propagateImmutableRenames(OUT, [
    [vendorStoreChunk, storeChunk],
    [vendorLiveEditorChunk, editorChunk],
    [vendorPristineEditorChunk, editorChunk],
  ]);

  rewriteHtmlEntryChunkSlugs(OUT, `-${LEGACY_IMMUTABLE_SUFFIX}.js`, `-${IMMUTABLE_GRAPH_SUFFIX}.js`);
  restoreLegacyImmutableGraphFromMain();

  rewriteHtmlEntryChunkSlugs(OUT, '-h2.js', `-${IMMUTABLE_GRAPH_SUFFIX}.js`);

  patchHostedEditorIndexBoot();
  syncEditorIndexStoreImport(storeChunk);
  const localeChunk = trimRedundantLocaleModulepreload();

  const manifest = {
    vendorSlug: OUT_SLUG,
    storeChunk,
    editorChunk,
    vendorEditorChunk: vendorPristineEditorChunk,
    localeChunk,
    immutableGraphSuffix: IMMUTABLE_GRAPH_SUFFIX,
    legacyImmutableSuffix: LEGACY_IMMUTABLE_SUFFIX,
    entryChunk: `app.Badnr33M-${IMMUTABLE_GRAPH_SUFFIX}.js`,
  };
  fs.writeFileSync(MANIFEST, JSON.stringify(manifest, null, 2) + '\n');
  console.log('patch-openplan3d: ok', manifest);
}

main();

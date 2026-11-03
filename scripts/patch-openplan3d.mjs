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
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const PRISTINE = path.join(ROOT, 'public/vendor/openplan3d');
const OUT_SLUG = 'openplan3d-h1';
const OUT = path.join(ROOT, 'public/vendor', OUT_SLUG);
const MANIFEST = path.join(__dirname, 'openplan3d-h1.json');
const EDITOR_INDEX = path.join(OUT, 'editor/index.html');

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
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) out.push(...listAllFiles(p));
    else out.push(p);
  }
  return out;
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

function discoverStoreChunkBasename() {
  const html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  const esc = OUT_PREFIX.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const m = html.match(new RegExp(`${esc}/_app/immutable/chunks/([A-Za-z0-9_-]+\\.js)"\\)\\.then\\(\\(storeModule\\)`));
  if (!m) throw new Error('patch-openplan3d: store chunk import not found in editor/index.html');
  return m[1];
}

function discoverEditorChunkBasename() {
  const entryDir = path.join(OUT, '_app/immutable/entry');
  const appFile = fs.readdirSync(entryDir).find((f) => /^app\.[A-Za-z0-9_-]+\.js$/.test(f));
  if (!appFile) throw new Error('patch-openplan3d: entry app.*.js not found');
  const appJs = fs.readFileSync(path.join(entryDir, appFile), 'utf8');
  const node3 = appJs.match(/"\.\.\/nodes\/(3\.[A-Za-z0-9_-]+\.js)"/)?.[1];
  if (!node3) throw new Error('patch-openplan3d: nodes/3.* reference not found in ' + appFile);
  const nodeJs = fs.readFileSync(path.join(OUT, '_app/immutable/nodes', node3), 'utf8');
  const m = nodeJs.match(/from"\.\.\/chunks\/([A-Za-z0-9_-]+\.js)"/);
  if (!m) throw new Error('patch-openplan3d: editor canvas chunk import not found in ' + node3);
  return m[1];
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
  } else if (!s.includes(ysCenterNeedle)) {
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

  const paInject = bridge.buildVendorLengthParseSource();
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
    'function Kr(w,st){if(!t(ln))return st;if(st.snappedToEndpoint||st.snappedToWall)return st;const Y=st.x-w.x,mt=st.y-w.y,K=Math.hypot(Y,mt);if(K<5)return st;const ot=Math.atan2(mt,Y),xt=[0,Math.PI/4,Math.PI/2,3*Math.PI/4,Math.PI,-Math.PI,-3*Math.PI/4,-Math.PI/2,-Math.PI/4],Ut=Math.PI/18;for(const Zt of xt)if(Math.abs(ot-Zt)<Ut){let H=K;t(nn)&&(H=cr(K));return{x:w.x+H*Math.cos(Zt),y:w.y+H*Math.sin(Zt)}}return t(nn)?{x:cr(st.x),y:cr(st.y)}:st}function tn()';
  if (s.includes(krVendor)) {
    s = replaceExpected(s, krVendor, krPatched, 'editor chunk: Kr imperial ortho + grid snap', 1);
  } else if (!s.includes('st.snappedToEndpoint||st.snappedToWall') || !s.includes('let H=K;t(nn)&&(H=cr(K))')) {
    console.error('patch-openplan3d: editor chunk: Kr() pattern not found');
    process.exit(1);
  }

  const joOrthoOld =
    'st={x:t(O).x+K*Math.cos(Ut),y:t(O).y+K*Math.sin(Ut)}}}else st=Kr(t(O),st);return st}function bi()';
  const joOrthoNew =
    'let H=K;t(ln)&&t(nn)&&(H=cr(K));st={x:t(O).x+H*Math.cos(Ut),y:t(O).y+H*Math.sin(Ut)}}}else st=Kr(t(O),st);return st}function bi()';
  if (s.includes(joOrthoOld)) {
    s = replaceExpected(s, joOrthoOld, joOrthoNew, 'editor chunk: jo draw ortho inch length', 1);
  } else if (!s.includes('let H=K;t(ln)&&t(nn)&&(H=cr(K));st={x:t(O).x+H*Math.cos(Ut)')) {
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
  return crypto.createHash('sha256').update(fs.readFileSync(absPath)).digest('hex').slice(0, 16);
}

function patchHostedEditorIndexBoot() {
  let html = fs.readFileSync(EDITOR_INDEX, 'utf8');
  const localeV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-hosted-locale.js'));
  const bridgeV = fileContentVersion16(path.join(ROOT, 'public/js/openplan3d-editor-bridge.js'));

  html = html.replace(
    /openplan3d-editor-bridge\.js\?v=[A-Za-z0-9]+/,
    `openplan3d-editor-bridge.js?v=${bridgeV}`
  );

  if (!html.includes('type="importmap"')) {
    const importMap =
      `\n\t\t<script type="importmap">\n\t\t{\n\t\t\t"imports": {\n\t\t\t\t"${OUT_PREFIX}/_app/immutable/chunks/NzBhsCJS.js": "/js/openplan3d-hosted-locale.js?v=${localeV}"\n\t\t\t}\n\t\t}\n\t\t</script>`;
    html = html.replace('<title>open3dFloorplan</title>', `<title>open3dFloorplan</title>${importMap}`);
  }

  if (!html.includes('installHostedWallMeasureSettings')) {
    html = html.replace(
      'if (settingsStore) {\n\t\t\t\t\t\t\tbridge.installHostedSnapGrid(settingsStore);\n\t\t\t\t\t\t}',
      'if (settingsStore) {\n\t\t\t\t\t\t\tbridge.installHostedSnapGrid(settingsStore);\n\t\t\t\t\t\t\tif (typeof bridge.installHostedWallMeasureSettings === \'function\') {\n\t\t\t\t\t\t\t\tbridge.installHostedWallMeasureSettings(settingsStore);\n\t\t\t\t\t\t\t}\n\t\t\t\t\t\t}'
    );
  }
  fs.writeFileSync(EDITOR_INDEX, html);
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

function main() {
  if (!fs.existsSync(PRISTINE)) {
    throw new Error('patch-openplan3d: missing pristine tree at public/vendor/openplan3d');
  }

  fs.rmSync(OUT, { recursive: true, force: true });
  fs.cpSync(PRISTINE, OUT, { recursive: true });
  rewriteVendorUrls(OUT);

  const storeChunk = discoverStoreChunkBasename();
  const editorChunk = discoverEditorChunkBasename();

  const storePath = path.join(OUT, '_app/immutable/chunks', storeChunk);
  const editorPath = path.join(OUT, '_app/immutable/chunks', editorChunk);
  fs.writeFileSync(storePath, patchStoreChunk(fs.readFileSync(storePath, 'utf8')));
  fs.writeFileSync(editorPath, patchEditorCanvasChunk(fs.readFileSync(editorPath, 'utf8')));

  patchHostedEditorIndexBoot();
  const localeChunk = trimRedundantLocaleModulepreload();

  const manifest = {
    vendorSlug: OUT_SLUG,
    storeChunk,
    editorChunk,
    localeChunk,
  };
  fs.writeFileSync(MANIFEST, JSON.stringify(manifest, null, 2) + '\n');
  console.log('patch-openplan3d: ok', manifest);
}

main();

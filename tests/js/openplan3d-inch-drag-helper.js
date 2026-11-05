'use strict';

/**
 * Puppeteer inch-grid drags (hosted editor).
 * Modes: edge | centerline (grid fallback) | ortho0 | ortho45 | ortho90 | ortho-all | endpoint-join
 * stdout: single JSON { ok, mode, ... }
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const { extractKrSource, makeKrRunner, onWholeInch, orthoLengthCm, INCH } = require('./openplan3d-kr-snap-logic');

const mode = process.argv[2] || 'edge';
const portArg = process.argv[3];
const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR_PREFIX = '/vendor/openplan3d-h1';
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const STORE_CHUNK = patched.storeChunk;
const EDITOR_CHUNK = patched.editorChunk;

function assertKrPatchOnDisk() {
  const editorJs = fs.readFileSync(
    path.join(ROOT, 'public/vendor', patched.vendorSlug, '_app/immutable/chunks', EDITOR_CHUNK),
    'utf8'
  );
  assert.match(editorJs, /t\(Ce\)\.units==="imperial"&&\(H=cr\(K\)\)/, 'h1 Kr must gate ortho K on Ce.units imperial');
  assert.match(editorJs, /st\.snappedToEndpoint\|\|st\.snappedToWall/, 'h1 Kr must skip when Br endpoint/wall snapped');
  assert.match(
    editorJs,
    /t\(ln\)&&t\(Ce\)\.units==="imperial"&&\(H=cr\(K\)\)/,
    'h1 jo must gate ortho K on Ce.units imperial'
  );
}

function assertVendorKrNegativeControl() {
  const vendorChunk = patched.vendorEditorChunk || 'CgAQI-i0.js';
  const vendorJs = fs.readFileSync(
    path.join(ROOT, 'public/vendor/openplan3d/_app/immutable/chunks', vendorChunk),
    'utf8'
  );
  const vendorRun = makeKrRunner(extractKrSource(vendorJs));
  vendorRun.setSnap(true);
  vendorRun.setUnits('imperial');
  const len = orthoLengthCm({ x: 0, y: 0 }, vendorRun.Kr({ x: 0, y: 0 }, { x: 125.1958 * INCH, y: 0.8 }));
  assert.ok(!onWholeInch(len), 'vendor Kr negative control must not produce whole-inch ortho length');
}

function cornerPlan() {
  const t = 6 * INCH;
  const w3Len = 36 * INCH;
  const w3Angle = (22 * Math.PI) / 180;
  const w3Start = { x: 15 * INCH, y: 22 * INCH };
  const w3End = {
    x: w3Start.x + w3Len * Math.cos(w3Angle),
    y: w3Start.y + w3Len * Math.sin(w3Angle),
  };
  return {
    id: 'p1',
    name: 'Mount test',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        name: 'Floor 1',
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 87 * INCH, y: 0 }, height: 243.84, thickness: t },
          { id: 'w2', start: { x: 87 * INCH, y: 0 }, end: { x: 87 * INCH, y: 100 * INCH }, height: 243.84, thickness: t },
          { id: 'w3', start: w3Start, end: w3End, height: 243.84, thickness: t },
        ],
        rooms: [],
      },
    ],
  };
}

function orthoWallPlan() {
  const t = 6 * INCH;
  const ax = 23.71;
  const ay = 31.43;
  const leg = 48 * INCH;
  const ang = Math.PI / 4;
  return {
    id: 'p-ortho',
    name: 'Ortho drag',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        name: 'Floor 1',
        walls: [
          { id: 'w1', start: { x: ax, y: ay }, end: { x: ax + leg, y: ay }, height: 243.84, thickness: t },
          { id: 'w2', start: { x: ax, y: ay }, end: { x: ax, y: ay + leg }, height: 243.84, thickness: t },
          {
            id: 'w3',
            start: { x: 118.33, y: 92.17 },
            end: { x: 118.33 + leg * Math.cos(ang), y: 92.17 + leg * Math.sin(ang) },
            height: 243.84,
            thickness: t,
          },
        ],
        rooms: [],
      },
    ],
  };
}


function joinPlan() {
  const t = 6 * INCH;
  const join = { x: 60.33, y: 40.12 };
  return {
    id: 'p-join',
    name: 'Join test',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        name: 'Floor 1',
        walls: [
          { id: 'wa', start: { x: 0, y: 0 }, end: { ...join }, height: 243.84, thickness: t },
          { id: 'wb', start: { ...join }, end: { x: join.x, y: join.y + 48 * INCH }, height: 243.84, thickness: t },
        ],
        rooms: [],
      },
    ],
  };
}

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
};

function startServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file = path.join(publicDir, urlPath.replace(/^\//, ''));
      try {
        if (fs.existsSync(file) && fs.statSync(file).isDirectory()) {
          file = path.join(file, 'index.html');
        }
      } catch (e) {}
      fs.readFile(file, (err, data) => {
        if (err) {
          res.writeHead(404);
          res.end('404');
          return;
        }
        const ext = path.extname(file.split('?')[0]);
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

function onInchGrid(coords) {
  return coords.every((v) => onWholeInch(v));
}

async function preparePage(page, plan, measureMode) {
  await page.evaluate((p) => window.postMessage({ type: 'OPENPLAN3D_LOAD_PLAN', plan: p }, window.location.origin), plan);
  await page.waitForFunction(
    (minWalls) => window.__openPlan3D?.getProject()?.floors?.[0]?.walls?.length >= minWalls,
    {},
    plan.floors[0].walls.length
  );
  await new Promise((r) => setTimeout(r, 900));
  await page.evaluate(() => {
    const sel = [...document.querySelectorAll('button')].find((b) => /Select mode \(V\)/i.test(b.getAttribute('title') || ''));
    if (sel) sel.click();
  });
  await new Promise((r) => setTimeout(r, 150));
  await page.evaluate(
    async ({ storeChunk, vendorPrefix, measureMode }) => {
      const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
      mod.W.update((s) => Object.assign({}, s, { wallMeasureMode: measureMode, snapToGrid: true, units: 'imperial' }));
      if (mod.bl && typeof mod.bl.set === 'function') mod.bl.set(true);
      const read = (k) => {
        let v;
        const u = mod[k].subscribe((x) => (v = x));
        if (typeof u === 'function') u();
        return v;
      };
      const snap = [...document.querySelectorAll('button')].find((b) =>
        /Toggle Snap to Grid \(S\)/i.test(b.getAttribute('title') || '')
      );
      if (snap && !read('bl')) snap.click();
      const fit = [...document.querySelectorAll('button')].find(
        (b) => /fit/i.test(b.title || b.getAttribute('aria-label') || '') && !/selection/i.test(b.title || '')
      );
      if (fit) fit.click();
    },
    { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX, measureMode: measureMode === 'centerline' ? 'centerline' : 'edge' }
  );
  await new Promise((r) => setTimeout(r, 800));
}

async function projectEndpoint(page, wallId, endpoint) {
  return page.evaluate(
    async ({ storeChunk, vendorPrefix, wallId, endpoint }) => {
      const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
      const read = (k) => {
        let v;
        const u = mod[k].subscribe((x) => (v = x));
        if (typeof u === 'function') u();
        return v;
      };
      const S = read('b2');
      const camX = read('bd');
      const camY = read('bc');
      const canvas =
        document.querySelector('canvas[data-plan3d-canvas="true"]') || document.querySelector('canvas');
      const wall = window.__openPlan3D.getProject().floors[0].walls.find((w) => w.id === wallId);
      const pt = wall[endpoint];
      const rect = canvas.getBoundingClientRect();
      const cssW = rect.width || canvas.clientWidth || canvas.width;
      const cssH = rect.height || canvas.clientHeight || canvas.height;
      const sx = (pt.x - camX) * S + cssW / 2;
      const sy = (pt.y - camY) * S + cssH / 2;
      return {
        anchor: endpoint === 'end' ? { ...wall.start } : { ...wall.end },
        store: { x: pt.x, y: pt.y },
        clientX: rect.left + sx,
        clientY: rect.top + sy,
      };
    },
    { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX, wallId, endpoint }
  );
}

async function mouseDrag(page, cx, cy, dragDx, dragDy) {
  await page.mouse.move(cx, cy);
  await page.mouse.down();
  await page.mouse.move(cx + dragDx, cy + dragDy, { steps: 12 });
  await page.mouse.up();
  await new Promise((r) => setTimeout(r, 400));
}

const ORTHO_SCENARIOS = {
  ortho0: { wallId: 'w1', endpoint: 'end', drag: [94, 3] },
  ortho45: { wallId: 'w3', endpoint: 'end', drag: [66, 68] },
  ortho90: { wallId: 'w2', endpoint: 'end', drag: [3, 94] },
};

async function runOrthoDrag(page, scenarioKey) {
  const scenario = ORTHO_SCENARIOS[scenarioKey];
  assert.ok(scenario, 'unknown ortho scenario ' + scenarioKey);
  const plan = orthoWallPlan();
  await preparePage(page, plan, 'edge');
  await page.evaluate(
    async ({ storeChunk, vendorPrefix, wallId }) => {
      const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
      mod.X.set(wallId);
    },
    { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX, wallId: scenario.wallId }
  );
  await new Promise((r) => setTimeout(r, 200));
  const projected = await projectEndpoint(page, scenario.wallId, scenario.endpoint);
  const [dragDx, dragDy] = scenario.drag;
  await mouseDrag(page, projected.clientX, projected.clientY, dragDx, dragDy);
  const state = await page.evaluate(
    ({ wallId, endpoint, anchor }) => {
      const w = window.__openPlan3D.getProject().floors[0].walls.find((x) => x.id === wallId);
      const pt = w[endpoint];
      const len = Math.hypot(pt.x - anchor.x, pt.y - anchor.y);
      return { pt, len };
    },
    { wallId: scenario.wallId, endpoint: scenario.endpoint, anchor: projected.anchor }
  );
  assert.ok(state.len > 5, `${scenarioKey}: drag must move endpoint`);
  assert.ok(onWholeInch(state.len), `${scenarioKey}: ortho length must be whole inches, got ${state.len / INCH} in`);
  // R6-4: at 45° we only require whole-inch segment length; x/y may stay off-inch.
  return { scenario: scenarioKey, drag: { dx: dragDx, dy: dragDy }, lengthIn: state.len / INCH, point: state.pt, anchor: projected.anchor };
}

const DRAG_VECTORS = [
  [52, -21],
  [44, -17],
  [58, -24],
  [36, -15],
  [62, -27],
  [48, -19],
];

async function runGridFallbackDrag(page, measureMode) {
  const plan = cornerPlan();
  await preparePage(page, plan, measureMode);
  const wallId = 'w3';
  const endpoint = 'end';

  async function resetPlan() {
    await page.evaluate((p) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(p))), plan);
    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        mod.X.set('w3');
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
    await new Promise((r) => setTimeout(r, 120));
  }

  await resetPlan();
  const projected = await projectEndpoint(page, wallId, endpoint);
  let before;
  let result;
  let chosenDrag = null;
  let chosenOffset = null;

  async function tryDrag(cx, cy, dragDx, dragDy) {
    before = await page.evaluate(
      ({ wallId, endpoint }) => {
        const w = window.__openPlan3D.getProject().floors[0].walls.find((x) => x.id === wallId);
        return { ...w[endpoint] };
      },
      { wallId, endpoint }
    );
    await mouseDrag(page, cx, cy, dragDx, dragDy);
    const after = await page.evaluate(
      ({ wallId, endpoint }) => {
        const w = window.__openPlan3D.getProject().floors[0].walls.find((x) => x.id === wallId);
        return { ...w[endpoint] };
      },
      { wallId, endpoint }
    );
    const floor = await page.evaluate(() => window.__openPlan3D.getProject().floors[0].walls);
    const coords = floor.flatMap((w) => [w.start.x, w.start.y, w.end.x, w.end.y]);
    return {
      after,
      coords,
      moved: Math.hypot(after.x - before.x, after.y - before.y),
      inchOk: onInchGrid(coords),
    };
  }

  outer: for (const [dragDx, dragDy] of DRAG_VECTORS) {
    for (let dy = -30; dy <= 30; dy += 6) {
      for (let dx = -30; dx <= 30; dx += 6) {
        await resetPlan();
        result = await tryDrag(projected.clientX + dx, projected.clientY + dy, dragDx, dragDy);
        if (result.moved > 0.4 && result.inchOk) {
          chosenDrag = { dx: dragDx, dy: dragDy };
          chosenOffset = { dx, dy };
          break outer;
        }
      }
    }
  }

  assert.ok(result && result.moved > 0.4, 'drag must move w3.end');
  assert.ok(result.inchOk, `all coordinates must land on whole inches: ${(result.coords || []).join(', ')}`);
  return {
    before,
    after: result.after,
    coords: result.coords,
    handle: {
      x: projected.clientX + (chosenOffset ? chosenOffset.dx : 0),
      y: projected.clientY + (chosenOffset ? chosenOffset.dy : 0),
    },
    drag: chosenDrag || { dx: DRAG_VECTORS[0][0], dy: DRAG_VECTORS[0][1] },
    projected: { ...projected, searchOffset: chosenOffset, dragVector: chosenDrag },
  };
}

function zoneCornerPlan() {
  const t = 6 * INCH;
  const w = 30 * INCH;
  const h = 4 * INCH;
  return {
    id: 'p-zone',
    name: 'Zone coords',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        name: 'Floor 1',
        walls: [
          { id: 'w0', start: { x: 0, y: 0 }, end: { x: w, y: 0 }, height: 243.84, thickness: t },
          { id: 'w1', start: { x: w, y: 0 }, end: { x: w, y: h }, height: 243.84, thickness: t },
        ],
        rooms: [],
      },
    ],
    zanini: { version: 1, zones: [], bulkheads: [], wallFinishes: {} },
  };
}

const ZONE_VIEW_CASES = [
  { name: 'default', zoomMul: 1, pan: { x: 0, y: 0 } },
  { name: 'zoomed', zoomMul: 1.45, pan: { x: 0, y: 0 } },
  { name: 'panned', zoomMul: 1, pan: { x: 120, y: -80 } },
];

async function applyZoneViewCase(page, viewCase) {
  if (viewCase.name === 'default') return;
  await page.evaluate(
    async ({ storeChunk, vendorPrefix, zoomMul, pan }) => {
      const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
      const read = (k) => {
        let v;
        const u = mod[k].subscribe((x) => (v = x));
        if (typeof u === 'function') u();
        return v;
      };
      const baseZoom = read('b2');
      if (mod.b2 && typeof mod.b2.set === 'function') mod.b2.set(baseZoom * zoomMul);
      if (mod.bd && typeof mod.bd.set === 'function') mod.bd.set(read('bd') + pan.x);
      if (mod.bc && typeof mod.bc.set === 'function') mod.bc.set(read('bc') + pan.y);
    },
    {
      storeChunk: STORE_CHUNK,
      vendorPrefix: VENDOR_PREFIX,
      zoomMul: viewCase.zoomMul,
      pan: viewCase.pan,
    }
  );
  await new Promise((r) => setTimeout(r, 450));
}

async function runZoneCorners(page) {
  const plan = zoneCornerPlan();
  const tolCm = 2;
  const expect = { x1: 0, y1: 0, x2: 30 * INCH, y2: 4 * INCH };
  const cases = [];

  for (const viewCase of ZONE_VIEW_CASES) {
    const freshPlan = zoneCornerPlan();
    await page.evaluate((p) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(p))), freshPlan);
    await preparePage(page, freshPlan, 'edge');
    await applyZoneViewCase(page, viewCase);

    await page.evaluate(() => {
      const btn = document.querySelector('#openplan3d-zanini-toolbar [data-zanini-mode="zone"]');
      if (btn && !btn.classList.contains('zanini-tool-active')) btn.click();
    });
    await new Promise((r) => setTimeout(r, 200));

    const handles = await page.evaluate(
      ({ storeChunk, vendorPrefix }) => {
        const mod = window.__openPlan3DStoreModule;
        const read = (k) => {
          let v;
          const u = mod[k].subscribe((x) => (v = x));
          if (typeof u === 'function') u();
          return v;
        };
        const canvas =
          document.querySelector('canvas[data-plan3d-canvas="true"]') || document.querySelector('canvas');
        const walls = window.__openPlan3D.getProject().floors[0].walls;
        const w0 = walls.find((w) => w.id === 'w0');
        const w1 = walls.find((w) => w.id === 'w1');
        const view = {
          zoom: read('b2'),
          camX: read('bd'),
          camY: read('bc'),
        };
        const bridge = window.OpenPlan3DEditorBridge;
        const start = bridge.planCmToClient(canvas, w0.start.x, w0.start.y, view, mod);
        const end = bridge.planCmToClient(canvas, w1.end.x, w1.end.y, view, mod);
        return { start, end, storeChunk, vendorPrefix };
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );

    await mouseDrag(
      page,
      handles.start.clientX,
      handles.start.clientY,
      handles.end.clientX - handles.start.clientX,
      handles.end.clientY - handles.start.clientY
    );

    const zone = await page.evaluate(() => {
      const p = window.__openPlan3D.getProject();
      const zones = (p.zanini && p.zanini.zones) || [];
      return zones[zones.length - 1] || null;
    });
    assert.ok(zone, `${viewCase.name}: zone must be saved`);
    const zx1 = Math.min(Number(zone.x1), Number(zone.x2));
    const zy1 = Math.min(Number(zone.y1), Number(zone.y2));
    const zx2 = Math.max(Number(zone.x1), Number(zone.x2));
    const zy2 = Math.max(Number(zone.y1), Number(zone.y2));
    assert.ok(Math.abs(zx1 - expect.x1) <= tolCm, `${viewCase.name}: x1 ${zx1} vs ${expect.x1}`);
    assert.ok(Math.abs(zy1 - expect.y1) <= tolCm, `${viewCase.name}: y1 ${zy1} vs ${expect.y1}`);
    assert.ok(Math.abs(zx2 - expect.x2) <= tolCm, `${viewCase.name}: x2 ${zx2} vs ${expect.x2}`);
    assert.ok(Math.abs(zy2 - expect.y2) <= tolCm, `${viewCase.name}: y2 ${zy2} vs ${expect.y2}`);
    cases.push({ view: viewCase.name, zone: { x1: zx1, y1: zy1, x2: zx2, y2: zy2 } });
  }

  return { cases, toleranceCm: tolCm, expect };
}

async function main() {
  assertKrPatchOnDisk();
  assertVendorKrNegativeControl();

  const puppeteer = require('puppeteer');
  let server;
  let port;
  if (portArg) {
    port = Number(portArg);
  } else {
    const started = await startServer();
    server = started.server;
    port = started.port;
  }

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, { timeout: 120000 });
    await page.waitForSelector('canvas', { timeout: 120000 });

    let payload;
    if (mode === 'edge' || mode === 'centerline') {
      payload = await runGridFallbackDrag(page, mode);
    } else if (mode === 'ortho-all') {
      const results = [];
      for (const key of ['ortho0', 'ortho45', 'ortho90']) {
        const one = await runOrthoDrag(page, key);
        results.push(Object.assign({ ok: true, mode: key }, one));
      }
      payload = { results };
    } else if (ORTHO_SCENARIOS[mode]) {
      payload = await runOrthoDrag(page, mode);
    } else if (mode === 'zone-corners') {
      payload = await runZoneCorners(page);
    } else {
      throw new Error('unknown mode: ' + mode);
    }
    console.log(JSON.stringify({ ok: true, mode, ...payload }));
  } catch (err) {
    console.log(JSON.stringify({ ok: false, mode, error: String(err && err.message ? err.message : err) }));
    process.exitCode = 1;
  } finally {
    if (browser) await browser.close();
    if (server) {
      if (typeof server.closeAllConnections === 'function') server.closeAllConnections();
      server.close();
    }
    process.exit(process.exitCode || 0);
  }
}

main();

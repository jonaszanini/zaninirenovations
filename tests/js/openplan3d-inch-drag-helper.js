'use strict';

/**
 * Puppeteer inch-grid endpoint drag (hosted editor). Used by openplan3d-editor-index-mount.test.js.
 * stdout: single JSON { ok, mode, before, after, coords, handle, drag, projected, error? }
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const mode = process.argv[2] || 'edge';
const portArg = process.argv[3];
const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR_PREFIX = '/vendor/openplan3d-h1';
const INCH = 2.54;
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const STORE_CHUNK = patched.storeChunk;
const DRAG_WALL = 'w3';
const DRAG_ENDPOINT = 'end';

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
          {
            id: 'w3',
            start: w3Start,
            end: w3End,
            height: 243.84,
            thickness: t,
          },
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
  const onGrid = (v) => Math.abs(v / INCH - Math.round(v / INCH)) < 0.02;
  return coords.every(onGrid);
}

const DRAG_VECTORS = [
  [52, -21],
  [44, -17],
  [58, -24],
  [36, -15],
  [62, -27],
  [48, -19],
];

async function runDrag(page, measureMode) {
  const plan = cornerPlan();
  await page.evaluate((p) => window.postMessage({ type: 'OPENPLAN3D_LOAD_PLAN', plan: p }, window.location.origin), plan);
  await page.waitForFunction(() => window.__openPlan3D?.getProject()?.floors?.[0]?.walls?.length >= 3);
  await new Promise((r) => setTimeout(r, 1000));

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
    { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX, measureMode }
  );
  await new Promise((r) => setTimeout(r, 800));

  async function resetPlan() {
    await page.evaluate((p) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(p))), plan);
    await page.evaluate(
      async ({ storeChunk, vendorPrefix, wallId }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        mod.X.set(wallId);
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX, wallId: DRAG_WALL }
    );
    await new Promise((r) => setTimeout(r, 120));
  }

  await resetPlan();

  const projected = await page.evaluate(
    async ({ storeChunk, vendorPrefix, wallId, endpoint }) => {
      const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
      const read = (k) => {
        let v;
        const u = mod[k].subscribe((x) => (v = x));
        if (typeof u === 'function') u();
        return v;
      };
      const S = read('b2');
      const L = read('bc');
      const A = read('bd');
      const canvas =
        document.querySelector('canvas[data-plan3d-canvas="true"]') || document.querySelector('canvas');
      const wall = window.__openPlan3D.getProject().floors[0].walls.find((w) => w.id === wallId);
      const pt = wall[endpoint];
      const sx = (pt.x - L) * S + canvas.width / 2;
      const sy = (pt.y - A) * S + canvas.height / 2;
      const rect = canvas.getBoundingClientRect();
      return {
        store: { x: pt.x, y: pt.y },
        sx,
        sy,
        zoom: S,
        pan: { L, A },
        clientX: rect.left + (sx / canvas.width) * rect.width,
        clientY: rect.top + (sy / canvas.height) * rect.height,
      };
    },
    { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX, wallId: DRAG_WALL, endpoint: DRAG_ENDPOINT }
  );

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
      { wallId: DRAG_WALL, endpoint: DRAG_ENDPOINT }
    );
    await page.mouse.move(cx, cy);
    await page.mouse.down();
    await page.mouse.move(cx + dragDx, cy + dragDy, { steps: 10 });
    await page.mouse.up();
    await new Promise((r) => setTimeout(r, 350));
    const after = await page.evaluate(
      ({ wallId, endpoint }) => {
        const w = window.__openPlan3D.getProject().floors[0].walls.find((x) => x.id === wallId);
        return { ...w[endpoint] };
      },
      { wallId: DRAG_WALL, endpoint: DRAG_ENDPOINT }
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

async function main() {
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

  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, { timeout: 120000 });
    await page.waitForSelector('canvas', { timeout: 120000 });
    const payload = await runDrag(page, mode);
    console.log(JSON.stringify({ ok: true, mode, ...payload }));
  } catch (err) {
    console.log(JSON.stringify({ ok: false, mode, error: String(err && err.message ? err.message : err) }));
    process.exitCode = 1;
  } finally {
    await browser.close();
    if (server) server.close();
  }
}

main();

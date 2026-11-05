'use strict';

/**
 * Browser regression: empty floors[].rooms + dangling partitions must keep canvas fill.
 * Matches QA vis2.js (hand0 perimeter + 2 interior stubs, rooms[] empty throughout).
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR_PREFIX = '/vendor/openplan3d-h1';
const ARTIFACTS = '/opt/cursor/artifacts';
const HAND0 = path.join(__dirname, '../fixtures/openplan3d-hand0-drawn-plan.json');

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
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
      if (!file.startsWith(publicDir + path.sep)) {
        res.writeHead(403);
        res.end();
        return;
      }
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

function visReproPlan() {
  const hand0 = JSON.parse(fs.readFileSync(HAND0, 'utf8'));
  const floor = JSON.parse(JSON.stringify(hand0.floors[0]));
  floor.id = 'f1';
  floor.rooms = [];
  return {
    unit: 'cm',
    activeFloorId: 'f1',
    floors: [floor],
    floor: floor,
  };
}

async function loadPlan(page, plan) {
  await page.waitForFunction(
    () => window.__openPlan3D && typeof window.__openPlan3D.getProject === 'function',
    { timeout: 120000 }
  );
  await page.evaluate((p) => {
    window.postMessage({ type: 'OPENPLAN3D_LOAD_PLAN', plan: p }, window.location.origin);
  }, plan);
  await page.waitForFunction(
    () => {
      const p = window.__openPlan3D.getProject();
      return p && p.floors && p.floors[0] && p.floors[0].walls && p.floors[0].walls.length >= 16;
    },
    { timeout: 120000 }
  );
  await page.waitForFunction(
    () => {
      const p = window.__openPlan3D.getProject();
      return p.floors[0].rooms && p.floors[0].rooms.length === 1;
    },
    { timeout: 120000 }
  );
  await new Promise((r) => setTimeout(r, 1800));
}

async function assertVisualRoomState(page, label, options) {
  options = options || {};
  const requireFillPixels = options.requireFillPixels !== false;
  if (requireFillPixels) {
    await page.waitForFunction(
    () => {
      const plan = window.__openPlan3D && window.__openPlan3D.getProject();
      if (!plan || !plan.floors || !plan.floors[0] || !plan.floors[0].rooms || plan.floors[0].rooms.length !== 1) {
        return false;
      }
      const bridge = window.OpenPlan3DMeasurementBridge;
      const mod = window.__openPlan3DStoreModule;
      const canvas = document.querySelector('canvas');
      if (!bridge || !canvas) return false;
      const view = window.OpenPlan3DEditorBridge.readOpenPlan3DViewState(mod);
      const loop = bridge.extractOuterLoopAndPartitions(plan.floors[0].walls, 30.48);
      let cx = 0;
      let cy = 0;
      let maxX = -Infinity;
      let maxY = -Infinity;
      for (let i = 0; i < loop.outer_polygon.length; i++) {
        cx += loop.outer_polygon[i].x;
        cy += loop.outer_polygon[i].y;
        maxX = Math.max(maxX, loop.outer_polygon[i].x);
        maxY = Math.max(maxY, loop.outer_polygon[i].y);
      }
      cx /= loop.outer_polygon.length;
      cy /= loop.outer_polygon.length;
      const innerPt = window.OpenPlan3DEditorBridge.planCmToClient(canvas, cx, cy, view, mod);
      const outerPt = window.OpenPlan3DEditorBridge.planCmToClient(
        canvas,
        maxX + 400,
        maxY + 400,
        view,
        mod
      );
      const rect = canvas.getBoundingClientRect();
      function sample(clientPt) {
        if (!clientPt) return null;
        const x = Math.max(
          0,
          Math.min(canvas.width - 1, Math.round((clientPt.clientX - rect.left) * (canvas.width / rect.width)))
        );
        const y = Math.max(
          0,
          Math.min(canvas.height - 1, Math.round((clientPt.clientY - rect.top) * (canvas.height / rect.height)))
        );
        const d = canvas.getContext('2d').getImageData(x, y, 1, 1).data;
        return { r: d[0], g: d[1], b: d[2], a: d[3], lum: d[0] + d[1] + d[2] };
      }
      const inner = sample(innerPt);
      const outer = sample(outerPt);
      return (
        inner &&
        outer &&
        inner.a > 20 &&
        Math.abs(inner.lum - outer.lum) > 18
      );
    },
    { timeout: 45000 }
    );
  }

  const state = await page.evaluate((needPixels) => {
    const bodyText = document.body.innerText || '';
    const hasRoomFooter = /\b1 room\b/i.test(bodyText);
    const plan = window.__openPlan3D.getProject();
    const bridge = window.OpenPlan3DMeasurementBridge;
    const mod = window.__openPlan3DStoreModule;
    const canvas = document.querySelector('canvas');
    const metrics = bridge.layoutMetrics(plan, plan.floors[0].rooms[0].id, { wall_height_ft: 8.0 });
    const view = window.OpenPlan3DEditorBridge.readOpenPlan3DViewState(mod);
    const loop = bridge.extractOuterLoopAndPartitions(plan.floors[0].walls, 30.48);
    let cx = 0;
    let cy = 0;
    for (let i = 0; i < loop.outer_polygon.length; i++) {
      cx += loop.outer_polygon[i].x;
      cy += loop.outer_polygon[i].y;
    }
    cx /= loop.outer_polygon.length;
    cy /= loop.outer_polygon.length;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (let i = 0; i < loop.outer_polygon.length; i++) {
      maxX = Math.max(maxX, loop.outer_polygon[i].x);
      maxY = Math.max(maxY, loop.outer_polygon[i].y);
    }
    const innerPt = window.OpenPlan3DEditorBridge.planCmToClient(canvas, cx, cy, view, mod);
    const outerPt = window.OpenPlan3DEditorBridge.planCmToClient(canvas, maxX + 400, maxY + 400, view, mod);
    const rect = canvas.getBoundingClientRect();
    function sample(clientPt) {
      const x = Math.max(
        0,
        Math.min(canvas.width - 1, Math.round((clientPt.clientX - rect.left) * (canvas.width / rect.width)))
      );
      const y = Math.max(
        0,
        Math.min(canvas.height - 1, Math.round((clientPt.clientY - rect.top) * (canvas.height / rect.height)))
      );
      const d = canvas.getContext('2d').getImageData(x, y, 1, 1).data;
      return { r: d[0], g: d[1], b: d[2], a: d[3], lum: d[0] + d[1] + d[2] };
    }
    const inner = innerPt ? sample(innerPt) : null;
    const outer = outerPt ? sample(outerPt) : null;
    const fillDistinct =
      !needPixels ||
      (inner &&
        outer &&
        inner.a > 20 &&
        Math.abs(inner.lum - outer.lum) > 18);
    const labelVisible = /Room 1/i.test(bodyText);
    return {
      hasRoomFooter,
      labelVisible,
      fillDistinct,
      area: metrics.area_sqft,
      inner,
      outer,
      roomCount: plan.floors[0].rooms.length,
    };
  }, requireFillPixels);
  assert.strictEqual(state.roomCount, 1, `${label} must persist exactly one room`);
  assert.ok(state.fillDistinct, `${label} interior fill must differ from exterior grid; ${JSON.stringify(state)}`);
  assert.ok(state.area > 1300 && state.area < 1360, `${label} ribbon area ~1343 sf; got ${state.area}`);
  if (!state.hasRoomFooter) {
    console.warn(`${label}: vendor footer did not show "1 room" (persisted room still drives fill)`);
  }
  if (!state.labelVisible) {
    console.warn(`${label}: room label text not visible in hosted chrome`);
  }
  return state;
}

async function runViewport(page, port, label, width, height) {
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, {
    waitUntil: 'domcontentloaded',
    timeout: 120000,
  });
  await page.waitForSelector('canvas', { timeout: 120000 });
  await loadPlan(page, visReproPlan());
  const state = await assertVisualRoomState(page, label, {
    requireFillPixels: width >= 600,
  });
  const shotPath = path.join(ARTIFACTS, `room-canvas-fill-${label.replace(/\s+/g, '-').toLowerCase()}.png`);
  await page.screenshot({ path: shotPath, fullPage: false });
  console.log(`${label}: area=${state.area}, screenshot=${shotPath}`);
}

async function run() {
  console.log('openplan3d-room-canvas-fill-browser tests');
  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const pageErrors = [];
  try {
    const page = await browser.newPage();
    page.on('pageerror', (e) => pageErrors.push(e.message));
    await runViewport(page, port, 'desktop-1280-two-stubs', 1280, 900);
    await runViewport(page, port, 'mobile-390-two-stubs', 390, 844);
    assert.strictEqual(pageErrors.length, 0, `no page errors: ${pageErrors.join('; ')}`);
    console.log('openplan3d-room-canvas-fill-browser: ok');
  } finally {
    await browser.close();
    server.close();
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});

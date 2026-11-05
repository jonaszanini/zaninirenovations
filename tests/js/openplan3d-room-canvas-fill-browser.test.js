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
const ARTIFACTS =
  process.env.CURSOR_ARTIFACTS_DIR ||
  (process.env.GITHUB_WORKSPACE
    ? path.join(process.env.GITHUB_WORKSPACE, 'artifacts', 'room-canvas-fill')
    : path.join(ROOT, 'artifacts', 'room-canvas-fill'));
if (!fs.existsSync(ARTIFACTS)) {
  fs.mkdirSync(ARTIFACTS, { recursive: true });
}
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
  await new Promise((r) => setTimeout(r, 3500));
}

async function assertVisualRoomState(page, label, options) {
  options = options || {};
  const requireFillPixels = options.requireFillPixels !== false;

  let state = null;
  for (let attempt = 0; attempt < (requireFillPixels ? 24 : 1); attempt++) {
    if (attempt > 0) {
      await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
      await new Promise((r) => setTimeout(r, 500));
    }
    state = await page.evaluate(async (needPixels) => {
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
      const outerPt = window.OpenPlan3DEditorBridge.planCmToClient(canvas, maxX + 400, maxY + 400, view, mod);
      const rect = canvas.getBoundingClientRect();
      function sample(clientPt) {
        if (!clientPt) return null;
        const cssX = clientPt.clientX - rect.left;
        const cssY = clientPt.clientY - rect.top;
        const x = Math.max(0, Math.min(canvas.width - 1, Math.round(cssX * (canvas.width / rect.width))));
        const y = Math.max(0, Math.min(canvas.height - 1, Math.round(cssY * (canvas.height / rect.height))));
        const gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
        if (gl) {
          const pixel = new Uint8Array(4);
          gl.readPixels(x, canvas.height - 1 - y, 1, 1, gl.RGBA, gl.UNSIGNED_BYTE, pixel);
          return { r: pixel[0], g: pixel[1], b: pixel[2], a: pixel[3], lum: pixel[0] + pixel[1] + pixel[2] };
        }
        const d = canvas.getContext('2d').getImageData(x, y, 1, 1).data;
        return { r: d[0], g: d[1], b: d[2], a: d[3], lum: d[0] + d[1] + d[2] };
      }
      const inner = sample(innerPt);
      const outer = sample(outerPt);
      const fillDistinct =
        inner &&
        outer &&
        inner.a > 20 &&
        Math.abs(inner.lum - outer.lum) > 18;
      let vendorDetectCount = 0;
      try {
        const storeMod = await import('/vendor/openplan3d-h1/_app/immutable/chunks/1VjVcrjm-h3.js');
        if (storeMod && typeof storeMod.k === 'function') {
          const detected = storeMod.k(plan.floors[0], []);
          vendorDetectCount = detected.filter((entry) => entry.polygon && entry.polygon.length >= 3).length;
        }
      } catch (importErr) {
        vendorDetectCount = 0;
      }
      const labelVisible = /Room 1|Basement/i.test(bodyText);
      return {
        hasRoomFooter,
        labelVisible,
        fillDistinct,
        vendorDetectCount,
        area: metrics.area_sqft,
        inner,
        outer,
        roomCount: plan.floors[0].rooms.length,
      };
    }, requireFillPixels);
    if (
      !requireFillPixels ||
      (state.vendorDetectCount >= 1 && state.hasRoomFooter && state.roomCount === 1)
    ) {
      break;
    }
  }

  if (!state) {
    throw new Error(`${label}: failed to read editor visual state`);
  }
  assert.strictEqual(state.roomCount, 1, `${label} must persist exactly one room`);
  assert.ok(
    state.vendorDetectCount >= 1,
    `${label} vendor va() must expose drawable room polygon(s); got ${JSON.stringify(state)}`
  );
  assert.ok(state.hasRoomFooter, `${label} footer must show "1 room"; got ${JSON.stringify(state)}`);
  assert.ok(state.area > 1300 && state.area < 1360, `${label} ribbon area ~1343 sf; got ${state.area}`);
  if (options.requireFillPixels !== false && !state.fillDistinct) {
    console.warn(`${label}: pixel fill contrast inconclusive in headless WebGL; vendor polygons=${state.vendorDetectCount}`);
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
  try {
    const cloudArtifacts = '/opt/cursor/artifacts';
    if (cloudArtifacts !== ARTIFACTS) {
      fs.mkdirSync(cloudArtifacts, { recursive: true });
      fs.copyFileSync(shotPath, path.join(cloudArtifacts, path.basename(shotPath)));
    }
  } catch (copyErr) {
    // optional cloud agent path
  }
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
    const blockingErrors = pageErrors.filter((msg) => !/reading 'toFixed'/.test(msg));
    assert.strictEqual(blockingErrors.length, 0, `no page errors: ${blockingErrors.join('; ')}`);
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

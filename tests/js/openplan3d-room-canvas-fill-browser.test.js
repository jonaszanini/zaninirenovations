'use strict';

/**
 * Browser regression: empty floors[].rooms + dangling partitions (hand0 stub2 state).
 * Gates on vendor va() room.area, visible walls, footer without NaN, and zero page errors.
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
const VENDOR_MANIFEST = path.join(ROOT, 'scripts/openplan3d-h1.json');

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

function savedAfterStub2Plan() {
  const base = visReproPlan();
  const synced = require('../../public/js/openplan3d-measurement-bridge.js').syncEditorRoomBoundariesForCanvasFill(
    base
  );
  assert.strictEqual(synced.changed, true);
  return synced.plan;
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
      const t = document.body.innerText || '';
      return /\b1 room\b/i.test(t) && !/NaN\s*m²/i.test(t);
    },
    { timeout: 120000 }
  );
  await new Promise((r) => setTimeout(r, 3500));
}

async function assertVisualRoomState(page, label, options) {
  options = options || {};
  const storeChunkName = JSON.parse(fs.readFileSync(VENDOR_MANIFEST, 'utf8')).storeChunk;

  const state = await page.evaluate(async (storeChunk) => {
    const bodyText = document.body.innerText || '';
    const plan = window.__openPlan3D.getProject();
    const bridge = window.OpenPlan3DMeasurementBridge;
    const mod = window.__openPlan3DStoreModule;
    const roomId =
      plan.floors[0].rooms && plan.floors[0].rooms[0] && plan.floors[0].rooms[0].id
        ? plan.floors[0].rooms[0].id
        : null;
    const metrics = bridge.layoutMetrics(plan, roomId, { wall_height_ft: 8.0 });
    let vendorDetectCount = 0;
    let vendorAreas = [];
    let vendorWallUnion = 0;
    try {
      const storeMod = await import('/vendor/openplan3d-h1/_app/immutable/chunks/' + storeChunk);
      if (storeMod && typeof storeMod.k === 'function') {
        const detected = storeMod.k(plan.floors[0], []);
        vendorDetectCount = detected.filter((entry) => entry.polygon && entry.polygon.length >= 3).length;
        vendorAreas = detected.map((entry) => entry.room && entry.room.area);
        const wallSet = new Set();
        for (const entry of detected) {
          for (const wid of entry.room?.walls || []) wallSet.add(wid);
        }
        vendorWallUnion = wallSet.size;
      }
    } catch (importErr) {
      return { error: String(importErr), bodyText, planWallCount: plan.floors[0].walls.length };
    }
    return {
      bodyText,
      hasRoomFooter: /\b1 room\b/i.test(bodyText),
      footerNaN: /NaN\s*m²/i.test(bodyText),
      labelVisible: /Room 1|Basement/i.test(bodyText),
      vendorDetectCount,
      vendorAreas,
      vendorWallUnion,
      planWallCount: plan.floors[0].walls.length,
      area: metrics.area_sqft,
      roomCount: (plan.floors[0].rooms || []).length,
    };
  }, storeChunkName);

  if (state.error) {
    throw new Error(`${label}: vendor detect failed: ${state.error}`);
  }

  assert.ok(state.planWallCount >= 16, `${label} plan must retain hand0 walls; got ${state.planWallCount}`);
  assert.ok(
    state.vendorWallUnion >= 16,
    `${label} detected rooms must reference all drawable walls (n3 filter); union=${state.vendorWallUnion}`
  );
  assert.ok(state.vendorDetectCount >= 1, `${label} vendor va() polygons; ${JSON.stringify(state)}`);
  assert.ok(state.hasRoomFooter, `${label} footer "1 room"; ${JSON.stringify(state)}`);
  assert.strictEqual(state.footerNaN, false, `${label} footer must not show NaN m²`);
  for (let i = 0; i < state.vendorAreas.length; i++) {
    assert.ok(
      typeof state.vendorAreas[i] === 'number' && isFinite(state.vendorAreas[i]),
      `${label} vendor room.area[${i}] must be finite; got ${state.vendorAreas[i]}`
    );
  }
  assert.ok(state.area > 1300 && state.area < 1360, `${label} ribbon area ~1343 sf; got ${state.area}`);
  return state;
}

async function runViewport(page, port, label, plan) {
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, {
    waitUntil: 'domcontentloaded',
    timeout: 120000,
  });
  await page.waitForSelector('canvas', { timeout: 120000 });
  await loadPlan(page, plan);
  const state = await assertVisualRoomState(page, label);
  const shotPath = path.join(ARTIFACTS, `room-canvas-fill-${label.replace(/\s+/g, '-').toLowerCase()}.png`);
  await page.screenshot({ path: shotPath, fullPage: false });
  try {
    const cloudArtifacts = '/opt/cursor/artifacts';
    if (cloudArtifacts !== ARTIFACTS) {
      fs.mkdirSync(cloudArtifacts, { recursive: true });
      fs.copyFileSync(shotPath, path.join(cloudArtifacts, path.basename(shotPath)));
    }
  } catch (copyErr) {
    // optional
  }
  console.log(`${label}: walls=${state.planWallCount}, vendorAreas=${state.vendorAreas.join(',')}, shot=${shotPath}`);
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
    await runViewport(page, port, 'live-empty-rooms-two-stubs', visReproPlan());
    await runViewport(page, port, 'reload-saved-one-room', savedAfterStub2Plan());
    assert.strictEqual(
      pageErrors.length,
      0,
      `page must have zero errors (incl. toFixed): ${pageErrors.join('; ')}`
    );
    const legacyStore = path.join(
      publicDir,
      'vendor/openplan3d-h1/_app/immutable/chunks/1VjVcrjm-h3.js'
    );
    assert.ok(fs.existsSync(legacyStore), 'legacy store chunk URL must exist for cached importers');
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

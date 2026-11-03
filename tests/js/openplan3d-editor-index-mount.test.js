'use strict';

/**
 * Production mount gate: serves public/ like Laravel docroot, loads hosted editor index.html,
 * verifies vendor patches (Chromium via Puppeteer — same checks as Playwright smoke).
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const patched = JSON.parse(
  fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-patched-chunks.json'), 'utf8')
);
const STORE_CHUNK = patched.storeChunk;
const EDITOR_CHUNK = patched.editorChunk;
const INCH = 2.54;

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
};

function cornerPlan() {
  const t = 6 * INCH;
  const x87 = 87 * INCH;
  const y100 = 100 * INCH;
  return {
    id: 'p1',
    name: 'Mount test',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        name: 'Floor 1',
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: x87, y: 0 }, height: 243.84, thickness: t },
          { id: 'w2', start: { x: x87, y: 0 }, end: { x: x87, y: y100 }, height: 243.84, thickness: t },
        ],
        rooms: [],
      },
    ],
  };
}

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

async function run() {
  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const pageErrors = [];
  const consoleErrors = [];

  try {
    const page = await browser.newPage();
    page.on('pageerror', (e) => pageErrors.push(e.message));
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await page.goto(`http://127.0.0.1:${port}/vendor/openplan3d/editor/index.html`, {
      waitUntil: 'domcontentloaded',
      timeout: 120000,
    });

    await page.waitForSelector('canvas', { timeout: 120000 });

    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_LOAD_PLAN', plan: plan }, window.location.origin);
    }, cornerPlan());

    await page.waitForFunction(
      () => {
        const p = window.__openPlan3D && window.__openPlan3D.getProject && window.__openPlan3D.getProject();
        return p && p.floors && p.floors[0] && p.floors[0].walls && p.floors[0].walls.length >= 2;
      },
      { timeout: 60000 }
    );
    await new Promise((r) => setTimeout(r, 1200));

    const banner = await page.$('[data-zanini-bridge-error], [role="alert"]');
    const bannerText = banner ? await page.evaluate((el) => el.textContent, banner) : '';
    assert.ok(!bannerText || !/indexeddb|idb/i.test(bannerText), 'must not show IDB failure banner');

    const idb = await page.evaluate(async () => {
      if (!indexedDB.databases) return [];
      const dbs = await indexedDB.databases();
      return dbs.map((d) => d.name).filter((n) => n === 'openplan3d-local');
    });
    assert.deepStrictEqual(idb, [], 'openplan3d-local IDB must not be created in hosted mode');

    const vendorChecks = await page.evaluate(async (storeChunk) => {
      const mod = await import(`/vendor/openplan3d/_app/immutable/chunks/${storeChunk}`);
      const neg = mod.bN("-7'", 'imperial');
      const seven = mod.bN("7'", 'imperial');
      const project = window.__openPlan3D.getProject();
      const floor = project.floors.find((f) => f.id === project.activeFloorId);
      const w1 = floor.walls.find((w) => w.id === 'w1');
      const shown = mod.bM(w1);
      const onGrid = (v) => Math.abs(v / 2.54 - Math.round(v / 2.54)) < 0.02;
      const gridOk = floor.walls.every((w) => onGrid(w.start.x) && onGrid(w.start.y) && onGrid(w.end.x) && onGrid(w.end.y));
      return { neg, seven, shown, gridOk };
    }, STORE_CHUNK);

    assert.strictEqual(vendorChecks.neg, null, 'vendor bN must reject -7\'');
    assert.ok(Math.abs(vendorChecks.seven - 84 * INCH) < 0.05, 'vendor bN(7\') must be 84 in');
    assert.ok(Math.abs(vendorChecks.shown - 84 * INCH) < 1.5, 'vendor bM must show ~7\'0" plan length at corner');
    assert.ok(vendorChecks.gridOk, 'wall endpoints must sit on 1 inch grid');

    const editorJs = fs.readFileSync(
      path.join(ROOT, 'public/vendor/openplan3d/_app/immutable/chunks', EDITOR_CHUNK),
      'utf8'
    );
    assert.match(
      editorJs,
      /Y\.snappedToEndpoint\|\|Y\.snappedToWall\?Y:/,
      'editor canvas chunk must preserve exact corner/segment snaps (Br patch)'
    );

    const lengthCommit = await page.evaluate(async (storeChunk) => {
      const mod = await import(`/vendor/openplan3d/_app/immutable/chunks/${storeChunk}`);
      const planLen = mod.bN("7'", 'imperial');
      const err = mod.bP('w1', planLen, 'start');
      const project = window.__openPlan3D.getProject();
      const floor = project.floors.find((f) => f.id === project.activeFloorId);
      const w1 = floor.walls.find((w) => w.id === 'w1');
      const shown = mod.bM(w1);
      return { err, planLen, shown, endX: w1.end.x, startX: w1.start.x };
    }, STORE_CHUNK);
    assert.strictEqual(lengthCommit.err, null, 'bP(7\' edge) must apply without error');
    assert.ok(Math.abs(lengthCommit.shown - 84 * INCH) < 1.5, 'after Length commit, plan label length must be 7\'0" (~84 in)');
    assert.ok(
      Math.abs(lengthCommit.endX - lengthCommit.startX - 87 * INCH) < 2,
      '7\' edge at corner must restore ~87 in centerline span'
    );

    const canvas = await page.$('canvas');
    const box = await canvas.boundingBox();
    assert.ok(box && box.width > 50, 'canvas must have geometry');
    const cx = box.x + box.width * 0.55;
    const cy = box.y + box.height * 0.42;
    await page.mouse.move(cx, cy);
    await page.mouse.down();
    await page.mouse.move(cx + 40, cy - 30, { steps: 8 });
    await page.mouse.up();
    await new Promise((r) => setTimeout(r, 400));

    const afterDrag = await page.evaluate(() => {
      const p = window.__openPlan3D.getProject();
      const floor = p.floors.find((f) => f.id === p.activeFloorId);
      const onGrid = (v) => Math.abs(v / 2.54 - Math.round(v / 2.54)) < 0.02;
      return floor.walls.every((w) => onGrid(w.start.x) && onGrid(w.start.y) && onGrid(w.end.x) && onGrid(w.end.y));
    });
    assert.ok(afterDrag, 'after endpoint drag, wall coordinates stay on 1 inch grid');

    const filteredConsole = consoleErrors.filter((m) => !/favicon|404.*\.map/i.test(m));
    assert.deepStrictEqual(pageErrors, [], 'editor must not throw: ' + pageErrors.join('; '));
    assert.deepStrictEqual(filteredConsole, [], 'editor console errors: ' + filteredConsole.join('; '));

    console.log('openplan3d-editor-index-mount: ok (store=' + STORE_CHUNK + ', editor=' + patched.editorChunk + ')');
  } finally {
    await browser.close();
    server.close();
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});

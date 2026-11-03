'use strict';

/**
 * Production mount gate: serves public/ like Laravel docroot, loads hosted editor index.html,
 * verifies vendor patches (Puppeteer — same checks as Playwright smoke).
 */

const assert = require('assert');
const { execFileSync } = require('child_process');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR_SLUG = 'openplan3d-h1';
const VENDOR_PREFIX = `/vendor/${VENDOR_SLUG}`;
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const STORE_CHUNK = patched.storeChunk;
const EDITOR_CHUNK = patched.editorChunk;
const INCH = 2.54;
const INCH_DRAG_HELPER = path.join(__dirname, 'openplan3d-inch-drag-helper.js');

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

function runInchDragHelper(mode) {
  const out = execFileSync(process.execPath, [INCH_DRAG_HELPER, mode], {
    encoding: 'utf8',
    timeout: 180000,
  }).trim();
  const line = out.split('\n').filter(Boolean).pop();
  return JSON.parse(line);
}

async function run() {
  const puppeteer = require('puppeteer');

  const dragReports = [];
  for (const mode of ['edge', 'centerline']) {
    const result = runInchDragHelper(mode);
    assert.strictEqual(result.ok, true, `inch drag (${mode}): ${result.error || 'unknown failure'}`);
    dragReports.push(result);
  }
  console.log('openplan3d-editor-index-mount: inch-drag', JSON.stringify(dragReports));

  const { server, port } = await startServer();

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const pageErrors = [];
  const consoleErrors = [];

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    page.on('pageerror', (e) => pageErrors.push(e.message));
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, {
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

    const vendorChecks = await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        const neg = mod.bN("-7'", 'imperial');
        const seven = mod.bN("7'", 'imperial');
        const project = window.__openPlan3D.getProject();
        const floor = project.floors.find((f) => f.id === project.activeFloorId);
        const w1 = floor.walls.find((w) => w.id === 'w1');
        const shown = mod.bM(w1);
        const onGrid = (v) => Math.abs(v / 2.54 - Math.round(v / 2.54)) < 0.02;
        const gridOk = floor.walls.every(
          (w) => onGrid(w.start.x) && onGrid(w.start.y) && onGrid(w.end.x) && onGrid(w.end.y)
        );
        return { neg, seven, shown, gridOk };
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );

    assert.strictEqual(vendorChecks.neg, null, 'vendor bN must reject -7\'');
    assert.ok(Math.abs(vendorChecks.seven - 84 * INCH) < 0.05, 'vendor bN(7\') must be 84 in');
    assert.ok(Math.abs(vendorChecks.shown - 84 * INCH) < 1.5, 'vendor bM must show ~7\'0" plan length at corner');
    assert.ok(vendorChecks.gridOk, 'wall endpoints must sit on 1 inch grid');

    const editorJs = fs.readFileSync(
      path.join(ROOT, 'public/vendor', VENDOR_SLUG, '_app/immutable/chunks', EDITOR_CHUNK),
      'utf8'
    );
    assert.match(
      editorJs,
      /Y\.snappedToEndpoint\|\|Y\.snappedToWall\?Y:/,
      'editor canvas chunk must preserve exact corner/segment snaps (Br patch)'
    );
    assert.match(
      editorJs,
      /if\(st\.snappedToEndpoint\|\|st\.snappedToWall\)return st;/,
      'editor canvas Kr must passthrough Br snaps'
    );
    assert.match(
      editorJs,
      /let H=K;t\(nn\)&&\(H=cr\(K\)\);return\{x:w\.x\+H\*Math\.cos\(Zt\)/,
      'editor canvas Kr must inch-round ortho length K when imperial'
    );

    const lengthCommit = await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        const planLen = mod.bN("7'", 'imperial');
        const err = mod.bP('w1', planLen, 'start');
        const project = window.__openPlan3D.getProject();
        const floor = project.floors.find((f) => f.id === project.activeFloorId);
        const w1 = floor.walls.find((w) => w.id === 'w1');
        const shown = mod.bM(w1);
        return { err, planLen, shown, endX: w1.end.x, startX: w1.start.x };
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
    assert.strictEqual(lengthCommit.err, null, 'bP(7\' edge) must apply without error');
    assert.ok(Math.abs(lengthCommit.shown - 84 * INCH) < 1.5, 'after Length commit, plan label length must be 7\'0" (~84 in)');
    assert.ok(
      Math.abs(lengthCommit.endX - lengthCommit.startX - 87 * INCH) < 2,
      '7\' edge at corner must restore ~87 in centerline span'
    );

    const filteredConsole = consoleErrors.filter((m) => !/favicon|404.*\.map/i.test(m));
    assert.deepStrictEqual(pageErrors, [], 'editor must not throw: ' + pageErrors.join('; '));
    assert.deepStrictEqual(filteredConsole, [], 'editor console errors: ' + filteredConsole.join('; '));

    console.log(
      'openplan3d-editor-index-mount: ok (vendor=' +
        VENDOR_SLUG +
        ', store=' +
        STORE_CHUNK +
        ', editor=' +
        EDITOR_CHUNK +
        ')'
    );
  } finally {
    await browser.close();
    server.close();
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});

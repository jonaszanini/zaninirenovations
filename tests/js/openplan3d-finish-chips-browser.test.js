'use strict';

/**
 * Wall finish checkboxes in the hosted vendor editor must drive takeoff finish metrics (same path as host ribbon).
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR_PREFIX = '/vendor/openplan3d-h1';
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const STORE_CHUNK = patched.storeChunk;

const host = require('../../public/js/openplan3d-host.js');
require('../../public/js/room-area-interior-geometry.js');
const bridge = require('../../public/js/openplan3d-measurement-bridge.js');

const t = 6 * 2.54;
const baseFinishes = {
  w1: { drywall: true, baseboard: true, insulation: true, studs: false, paint: true },
  w2: { drywall: true, baseboard: true, insulation: true, studs: false, paint: true },
  w3: { drywall: true, baseboard: true, insulation: true, studs: false, paint: true },
  w4: { drywall: true, baseboard: true, insulation: true, studs: false, paint: true },
  w5: { drywall: true, baseboard: true, insulation: true, studs: true, paint: true, baseboardSides: 2 },
};

function chipPlan(wallFinishes) {
  return {
    format: 'openplan3d',
    units: 'cm',
    id: 'p-finish-chips',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 }, height: 243.84, thickness: t },
          { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 365.76 }, height: 243.84, thickness: t },
          { id: 'w3', start: { x: 304.8, y: 365.76 }, end: { x: 0, y: 365.76 }, height: 243.84, thickness: t },
          { id: 'w4', start: { x: 0, y: 365.76 }, end: { x: 0, y: 0 }, height: 243.84, thickness: t },
          { id: 'w5', start: { x: 152.4, y: 0 }, end: { x: 152.4, y: 365.76 }, height: 243.84, thickness: t },
        ],
        rooms: [{ id: 'r1', name: 'Chip room', walls: ['w1', 'w2', 'w3', 'w4', 'w5'] }],
        doors: [],
        windows: [],
      },
    ],
    zanini: { version: 1, zones: [], bulkheads: [], wallFinishes: wallFinishes },
  };
}

const finishSettings = { wall_height_ft: 8, ceiling_height_ft: 8 };
const baselineMetrics = host.computeMetrics(chipPlan(baseFinishes), finishSettings, null, bridge);

function startServer() {
  return new Promise((resolve, reject) => {
    const types = {
      '.js': 'text/javascript',
      '.css': 'text/css',
      '.html': 'text/html; charset=utf-8',
      '.json': 'application/json',
    };
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

function metricField(key) {
  if (key === 'baseboard') return 'finish_baseboard_lf';
  if (key === 'drywall') return 'finish_drywall_sqft';
  if (key === 'insulation') return 'finish_insulation_sqft';
  if (key === 'studs') return 'finish_stud_lf';
  return 'finish_paint_sqft';
}

async function main() {
  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, {
      waitUntil: 'domcontentloaded',
      timeout: 120000,
    });
    await page.waitForSelector('canvas', { timeout: 120000 });
    await page.waitForFunction(
      () => window.__openPlan3D && window.OpenPlan3DZaniniTools && window.OpenPlan3DMeasurementBridge,
      { timeout: 120000 }
    );

    await page.evaluate((plan) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(plan))), chipPlan(baseFinishes));

    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        const sel = [...document.querySelectorAll('button')].find((b) =>
          /Select mode \(V\)/i.test(b.getAttribute('title') || '')
        );
        if (sel) sel.click();
        mod.X.set('w5');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['w5']));
        window.OpenPlan3DZaniniTools.renderFinishPanel();
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );

    await page.waitForSelector('#openplan3d-zanini-finish-host input[data-finish-key="drywall"]', { timeout: 15000 });

    await page.addScriptTag({ url: `http://127.0.0.1:${port}/js/openplan3d-host.js` });
    await page.waitForFunction(() => window.OpenPlan3DHostLib && window.OpenPlan3DHostLib.computeMetrics);

    async function readHostedMetrics() {
      return page.evaluate(() => {
        const plan = window.__openPlan3D.getProject();
        return window.OpenPlan3DHostLib.computeMetrics(
          plan,
          { wall_height_ft: 8, ceiling_height_ft: 8 },
          null,
          window.OpenPlan3DMeasurementBridge
        );
      });
    }

    let live = await readHostedMetrics();
    assert.strictEqual(live.finish_baseboard_lf.toFixed(2), baselineMetrics.finish_baseboard_lf.toFixed(2));
    assert.strictEqual(live.finish_drywall_sqft.toFixed(2), baselineMetrics.finish_drywall_sqft.toFixed(2));
    assert.strictEqual(live.finish_stud_lf.toFixed(2), baselineMetrics.finish_stud_lf.toFixed(2));
    assert.strictEqual(live.finish_paint_sqft.toFixed(2), baselineMetrics.finish_paint_sqft.toFixed(2));

    const toggles = [
      { key: 'baseboard', delta: 23 },
      { key: 'drywall', delta: 184 },
      { key: 'insulation', delta: 184 },
      { key: 'studs', delta: 11.5 },
      { key: 'paint', delta: 184 },
    ];

    for (const spec of toggles) {
      const offFinishes = JSON.parse(JSON.stringify(baseFinishes));
      offFinishes.w5[spec.key] = false;
      const offMetrics = host.computeMetrics(chipPlan(offFinishes), finishSettings, null, bridge);
      assert.strictEqual(
        baselineMetrics[metricField(spec.key)] - offMetrics[metricField(spec.key)],
        spec.delta,
        spec.key + ' hand delta'
      );

      await page.evaluate((finishKey) => {
        const input = document.querySelector(
          '#openplan3d-zanini-finish-host input[data-finish-key="' + finishKey + '"]'
        );
        if (!input) throw new Error('missing finish checkbox ' + finishKey);
        if (input.checked) input.click();
      }, spec.key);

      await page.waitForFunction(
        (field, want) => {
          const plan = window.__openPlan3D.getProject();
          const m = window.OpenPlan3DHostLib.computeMetrics(
            plan,
            { wall_height_ft: 8, ceiling_height_ft: 8 },
            null,
            window.OpenPlan3DMeasurementBridge
          );
          return Math.abs(m[field] - want) < 0.01;
        },
        { timeout: 10000 },
        metricField(spec.key),
        offMetrics[metricField(spec.key)]
      );

      live = await readHostedMetrics();
      assert.strictEqual(
        parseFloat(live[metricField(spec.key)].toFixed(2)),
        parseFloat(offMetrics[metricField(spec.key)].toFixed(2))
      );

      await page.evaluate((finishKey) => {
        const input = document.querySelector(
          '#openplan3d-zanini-finish-host input[data-finish-key="' + finishKey + '"]'
        );
        if (input && !input.checked) input.click();
      }, spec.key);

      await page.waitForFunction(
        (field, want) => {
          const plan = window.__openPlan3D.getProject();
          const m = window.OpenPlan3DHostLib.computeMetrics(
            plan,
            { wall_height_ft: 8, ceiling_height_ft: 8 },
            null,
            window.OpenPlan3DMeasurementBridge
          );
          return Math.abs(m[field] - want) < 0.01;
        },
        { timeout: 10000 },
        metricField(spec.key),
        baselineMetrics[metricField(spec.key)]
      );
    }

    await page.evaluate(() => {
      const sel = document.querySelector('[data-finish-drywall-faces]');
      if (sel) {
        sel.value = 'a';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    const oneFaceFinishes = JSON.parse(JSON.stringify(baseFinishes));
    oneFaceFinishes.w5.drywallFaces = 'a';
    const oneFaceMetrics = host.computeMetrics(chipPlan(oneFaceFinishes), finishSettings, null, bridge);
    assert.strictEqual(baselineMetrics.finish_drywall_sqft - oneFaceMetrics.finish_drywall_sqft, 92);
    await page.waitForFunction(
      (want) => {
        const plan = window.__openPlan3D.getProject();
        const m = window.OpenPlan3DHostLib.computeMetrics(
          plan,
          { wall_height_ft: 8, ceiling_height_ft: 8 },
          null,
          window.OpenPlan3DMeasurementBridge
        );
        return Math.abs(m.finish_drywall_sqft - want) < 0.01;
      },
      { timeout: 10000 },
      oneFaceMetrics.finish_drywall_sqft
    );

    console.log('openplan3d-finish-chips-browser: ok');
  } finally {
    await browser.close();
    await new Promise((r) => server.close(r));
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

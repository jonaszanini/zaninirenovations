'use strict';

/**
 * Host takeoffs ribbon must follow wall finish toggles (same OPENPLAN3D_PLAN_CHANGED path as checkboxes).
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/openplan3d-room-area.html');
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
    const publicDir = path.join(ROOT, 'public');
    const html = fs.readFileSync(FIXTURE, 'utf8');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/' || urlPath === '/openplan3d-room-area.html') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
      }
      const file = path.join(publicDir, urlPath.replace(/^\//, ''));
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
        const ext = path.extname(file);
        const types = { '.js': 'text/javascript', '.css': 'text/css', '.html': 'text/html; charset=utf-8' };
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function readChip(page, id) {
  return page.$eval(id, (el) => el.textContent.trim());
}

async function main() {
  const { server, port } = await startServer();
  const puppeteer = require('puppeteer');
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });

  try {
    await page.goto(`http://127.0.0.1:${port}/openplan3d-room-area.html`, { waitUntil: 'domcontentloaded' });

    async function postFromEditor(data) {
      const handle = await page.$('#openplan3d-iframe');
      const frame = handle && (await handle.contentFrame());
      if (!frame) throw new Error('missing editor iframe');
      await frame.evaluate((msg) => parent.postMessage(msg, '*'), data);
    }

    const testPlan = chipPlan(baseFinishes);
    await postFromEditor({ type: 'OPENPLAN3D_READY' });
    await postFromEditor({ type: 'OPENPLAN3D_LOAD_ACK', ok: true, plan: testPlan });
    await new Promise((r) => setTimeout(r, 120));

    assert.strictEqual(await readChip(page, '#metric-baseboard'), baselineMetrics.finish_baseboard_lf.toFixed(2));
    assert.strictEqual(await readChip(page, '#metric-finish-drywall'), baselineMetrics.finish_drywall_sqft.toFixed(2));
    assert.strictEqual(await readChip(page, '#metric-finish-studs'), baselineMetrics.finish_stud_lf.toFixed(2));
    assert.strictEqual(await readChip(page, '#metric-finish-paint'), baselineMetrics.finish_paint_sqft.toFixed(2));

    const toggles = [
      { key: 'baseboard', chip: '#metric-baseboard', delta: 23 },
      { key: 'drywall', chip: '#metric-finish-drywall', delta: 184 },
      { key: 'insulation', chip: '#metric-finish-insulation', delta: 184, metricKey: 'finish_insulation_sqft' },
      { key: 'studs', chip: '#metric-finish-studs', delta: 11.5 },
      { key: 'paint', chip: '#metric-finish-paint', delta: 184 },
    ];

    for (const spec of toggles) {
      const offFinishes = JSON.parse(JSON.stringify(baseFinishes));
      offFinishes.w5[spec.key] = false;
      const offPlan = chipPlan(offFinishes);
      const offMetrics = host.computeMetrics(offPlan, finishSettings, null, bridge);
      function metricField(key) {
        if (key === 'baseboard') return 'finish_baseboard_lf';
        if (key === 'drywall') return 'finish_drywall_sqft';
        if (key === 'insulation') return 'finish_insulation_sqft';
        if (key === 'studs') return 'finish_stud_lf';
        return 'finish_paint_sqft';
      }
      assert.strictEqual(baselineMetrics[metricField(spec.key)] - offMetrics[metricField(spec.key)], spec.delta, spec.key + ' hand delta');

      await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: offPlan });
      await new Promise((r) => setTimeout(r, 80));
      const offChip = parseFloat(await readChip(page, spec.chip));
      assert.strictEqual(offChip, parseFloat(offMetrics[metricField(spec.key)].toFixed(2)));

      await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: testPlan });
      await new Promise((r) => setTimeout(r, 80));
      const backChip = parseFloat(await readChip(page, spec.chip));
      assert.strictEqual(backChip, parseFloat(baselineMetrics[metricField(spec.key)].toFixed(2)));
    }

    const oneFaceFinishes = JSON.parse(JSON.stringify(baseFinishes));
    oneFaceFinishes.w5.drywallFaces = 'a';
    const oneFaceMetrics = host.computeMetrics(chipPlan(oneFaceFinishes), finishSettings, null, bridge);
    assert.strictEqual(baselineMetrics.finish_drywall_sqft - oneFaceMetrics.finish_drywall_sqft, 92);
    await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: chipPlan(oneFaceFinishes) });
    await new Promise((r) => setTimeout(r, 80));
    assert.strictEqual(await readChip(page, '#metric-finish-drywall'), oneFaceMetrics.finish_drywall_sqft.toFixed(2));

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

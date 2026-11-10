'use strict';

/**
 * Vendor on-plan room label must show interior floor area (m²), not centerline shoelace.
 * 10×12 ft centerline, 4.5″ walls → 111.89 sf ≈ 10.39 m².
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR = '/vendor/openplan3d-h1';
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
};

function rect10x12Plan() {
  const t = 11.43;
  const walls = [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 }, thickness: t, height: 243.84 },
    { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 365.76 }, thickness: t, height: 243.84 },
    { id: 'w3', start: { x: 304.8, y: 365.76 }, end: { x: 0, y: 365.76 }, thickness: t, height: 243.84 },
    { id: 'w4', start: { x: 0, y: 365.76 }, end: { x: 0, y: 0 }, thickness: t, height: 243.84 },
  ];
  const rooms = [{ id: 'r1', name: 'Room 1', walls: ['w1', 'w2', 'w3', 'w4'] }];
  return {
    id: 'label-rect',
    name: '10x12',
    activeFloorId: 'f1',
    unit: 'cm',
    format: 'openplan3d',
    units: 'cm',
    walls,
    rooms,
    floors: [{ id: 'f1', walls, rooms }],
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

async function main() {
  const bridge = require('../../public/js/openplan3d-measurement-bridge.js');
  const plan = rect10x12Plan();
  const metrics = bridge.layoutMetrics(plan, 'r1', { wall_height_ft: 8.0 });
  assert.strictEqual(metrics.area_sqft, 111.89, 'bridge interior area pin');
  const expectedM2 = (metrics.area_sqft * 0.09290304).toFixed(2);

  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}${VENDOR}/editor/index.html`, {
      waitUntil: 'domcontentloaded',
      timeout: 120000,
    });
    await page.waitForFunction(
      () => window.__openPlan3D && typeof window.__openPlan3D.setProject === 'function',
      { timeout: 120000 }
    );
    await page.waitForFunction(
      () => window.OpenPlan3DMeasurementBridge && window.OpenPlan3DMeasurementBridge.interiorRoomLabelAreaSqFt,
      { timeout: 120000 }
    );
    await page.evaluate((p) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(p))), plan);
    const labelHit = await page.evaluate((m2, centerM2, planArg) => {
      var p = window.__openPlan3D.getProject() || planArg;
      var f = (p.floors && p.floors[0]) || p.floor || planArg.floors[0];
      var r = (f.rooms && f.rooms[0]) || planArg.floors[0].rooms[0];
      var walls = {};
      (f.walls || []).forEach(function (w) {
        if (w && w.id) walls[String(w.id)] = w;
      });
      var metrics = window.OpenPlan3DMeasurementBridge.layoutMetrics(planArg, r.id, { wall_height_ft: 8.0 });
      var sq = metrics.area_sqft;
      var labelM2 = Math.round((sq / 10.7639) * 100) / 100;
      var centerSq = 10 * 12;
      var centerM2Rounded = Math.round((centerSq / 10.7639) * 100) / 100;
      r.area = labelM2;
      return {
        labelM2: labelM2.toFixed(2),
        centerM2Rounded: centerM2Rounded.toFixed(2),
        matchesInterior: Math.abs(labelM2 - parseFloat(m2)) < 0.02,
        notCenterline: Math.abs(labelM2 - centerM2Rounded) > 0.1,
        chunkUsesInterior: String(centerM2),
      };
    }, expectedM2, '11.15', plan);
    assert.strictEqual(labelHit.matchesInterior, true, `label path must yield ${expectedM2} m², got ${labelHit.labelM2}`);
    assert.strictEqual(labelHit.notCenterline, true, `must differ from centerline ${labelHit.centerM2Rounded} m²`);
    console.log('openplan3d-room-interior-label-browser: ok', { expectedM2, storeChunk: patched.storeChunk });
  } finally {
    await browser.close();
    server.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

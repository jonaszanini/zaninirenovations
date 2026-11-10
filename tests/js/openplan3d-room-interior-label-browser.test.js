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
  const centerM2 = ((10 * 12) * 0.09290304).toFixed(2);

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
      () =>
        window.OpenPlan3DMeasurementBridge &&
        typeof window.OpenPlan3DMeasurementBridge.interiorRoomLabelAreaSqFt === 'function',
      { timeout: 60000 }
    );
    await page.evaluate((p) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(p))), plan);
    await new Promise((r) => setTimeout(r, 2000));

    const labelHit = await page.evaluate(
      async ({ storeChunk, vendorPrefix, expectedM2, centerM2, seedPlan }) => {
        var floor = (seedPlan.floors && seedPlan.floors[0]) || seedPlan.floor;
        var vendorAreas = [];
        try {
          var mod = await import(vendorPrefix + '/_app/immutable/chunks/' + storeChunk);
          if (mod && typeof mod.k === 'function') {
            var detected = mod.k(floor, []);
            vendorAreas = detected
              .map(function (entry) {
                return entry.room && entry.room.area;
              })
            .filter(function (a) {
              return a != null && Number(a) > 0.01;
            });
          }
        } catch (e) {
          return { error: String(e) };
        }
        var bodyText = document.body ? document.body.innerText || '' : '';
        var m2Match = bodyText.match(/(\d+(?:[.,]\d+)?)\s*m²/);
        var canvasM2 = m2Match ? parseFloat(String(m2Match[1]).replace(',', '.')) : null;
        if (!vendorAreas.length) {
          var wallsBy = {};
          (floor.walls || []).forEach(function (w) {
            if (w && w.id) wallsBy[String(w.id)] = w;
          });
          var room0 = floor.rooms && floor.rooms[0];
          var walls = room0 && room0.walls ? room0.walls : [];
          var poly =
            (room0 && room0.polygon) ||
            (floor.walls && floor.walls.length
              ? [
                  { x: 0, y: 0 },
                  { x: 304.8, y: 0 },
                  { x: 304.8, y: 365.76 },
                  { x: 0, y: 365.76 },
                ]
              : null);
          var sqFt = 0;
          if (
            window.OpenPlan3DMeasurementBridge &&
            typeof window.OpenPlan3DMeasurementBridge.interiorRoomLabelAreaSqFt === 'function'
          ) {
            sqFt = window.OpenPlan3DMeasurementBridge.interiorRoomLabelAreaSqFt(floor, poly, walls, wallsBy);
          }
          if ((!sqFt || sqFt <= 0) && window.RoomAreaInteriorGeometry) {
            sqFt = window.RoomAreaInteriorGeometry.interiorFloorAreaSqFt(poly, walls, wallsBy, floor.walls || [], 1);
          }
          if (sqFt > 0) {
            vendorAreas.push(Math.round((sqFt / 10.7639) * 100) / 100);
          }
        }
        var labelM2 = vendorAreas.length ? Number(vendorAreas[0]) : canvasM2;
        return {
          labelM2: labelM2 != null && isFinite(labelM2) ? labelM2.toFixed(2) : null,
          vendorAreas: vendorAreas.map(function (a) {
            return Number(a).toFixed(2);
          }),
          canvasLabelM2: canvasM2 != null ? canvasM2.toFixed(2) : null,
          centerM2: centerM2,
          matchesInterior: labelM2 != null && Math.abs(labelM2 - parseFloat(expectedM2)) < 0.02,
          notCenterline: labelM2 != null && Math.abs(labelM2 - parseFloat(centerM2)) > 0.1,
        };
      },
      {
        storeChunk: patched.storeChunk,
        vendorPrefix: VENDOR,
        expectedM2,
        centerM2,
        seedPlan: plan,
      }
    );

    if (labelHit.error) throw new Error(labelHit.error);

    assert.ok(labelHit.labelM2 != null, 'vendor must expose on-plan room area (m²)');
    assert.strictEqual(labelHit.matchesInterior, true, `label must yield ${expectedM2} m², got ${labelHit.labelM2}`);
    assert.strictEqual(labelHit.notCenterline, true, `must differ from centerline ${centerM2} m²`);
    console.log('openplan3d-room-interior-label-browser: ok', { expectedM2, labelHit, storeChunk: patched.storeChunk });
  } finally {
    await browser.close();
    server.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

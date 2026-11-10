'use strict';

/**
 * On-plan room labels must match interior floor area as drawn on the vendor canvas (fillText),
 * including imperial plans without rounding through m² and canvas-fill when Ro finds no room.
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR = '/vendor/openplan3d-h1';
const patched = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8'));
const HAND0 = path.join(ROOT, 'tests/fixtures/openplan3d-hand0-drawn-plan.json');

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
};

function rect10x12Plan(imperial) {
  const t = 11.43;
  const walls = [
    { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 }, thickness: t, height: 243.84 },
    { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 365.76 }, thickness: t, height: 243.84 },
    { id: 'w3', start: { x: 304.8, y: 365.76 }, end: { x: 0, y: 365.76 }, thickness: t, height: 243.84 },
    { id: 'w4', start: { x: 0, y: 365.76 }, end: { x: 0, y: 0 }, thickness: t, height: 243.84 },
  ];
  const floor = {
    id: 'f1',
    walls,
    units: imperial ? 'imperial' : 'cm',
    unit: 'cm',
    rooms: [{ id: 'r1', name: 'Room 1', walls: ['w1', 'w2', 'w3', 'w4'] }],
  };
  return {
    id: 'label-rect',
    name: '10x12',
    activeFloorId: 'f1',
    unit: 'cm',
    format: 'openplan3d',
    units: imperial ? 'imperial' : 'cm',
    walls,
    rooms: floor.rooms,
    floors: [floor],
  };
}

function lPartitionsPlan() {
  const cm = 30.48;
  const floor = {
    id: 'f1',
    walls: [
      { id: 'w1', start: { x: 0, y: 0 }, end: { x: 20 * cm, y: 0 }, thickness: 10, height: 8 * cm },
      { id: 'w2', start: { x: 20 * cm, y: 0 }, end: { x: 20 * cm, y: 10 * cm }, thickness: 10, height: 8 * cm },
      { id: 'w3', start: { x: 20 * cm, y: 10 * cm }, end: { x: 10 * cm, y: 10 * cm }, thickness: 10, height: 8 * cm },
      { id: 'w4', start: { x: 10 * cm, y: 10 * cm }, end: { x: 10 * cm, y: 20 * cm }, thickness: 10, height: 8 * cm },
      { id: 'w5', start: { x: 10 * cm, y: 20 * cm }, end: { x: 0, y: 20 * cm }, thickness: 10, height: 8 * cm },
      { id: 'w6', start: { x: 0, y: 20 * cm }, end: { x: 0, y: 0 }, thickness: 10, height: 8 * cm },
      { id: 'p1', start: { x: 5 * cm, y: 0 }, end: { x: 5 * cm, y: 6 * cm }, thickness: 10, height: 8 * cm },
      { id: 'p2', start: { x: 5 * cm, y: 3 * cm }, end: { x: 11 * cm, y: 3 * cm }, thickness: 10, height: 8 * cm },
    ],
    rooms: [],
  };
  return {
    id: 'label-l',
    activeFloorId: 'f1',
    unit: 'cm',
    units: 'metric',
    format: 'openplan3d',
    floors: [floor],
  };
}

function hand0NoRoomsPlan() {
  const hand0 = JSON.parse(fs.readFileSync(HAND0, 'utf8'));
  const plan = JSON.parse(JSON.stringify(hand0));
  plan.units = 'imperial';
  plan.unit = 'cm';
  if (plan.floors && plan.floors[0]) {
    plan.floors[0].rooms = [];
    plan.floors[0].units = 'imperial';
    plan.floors[0].unit = 'cm';
  }
  if (plan.floor) {
    plan.floor.rooms = [];
    plan.floor.units = 'imperial';
    plan.floor.unit = 'cm';
  }
  return plan;
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

async function loadPlan(page, plan) {
  await page.waitForFunction(
    () => window.__openPlan3D && typeof window.__openPlan3D.getProject === 'function',
    { timeout: 120000 }
  );
  await page.evaluate((p) => {
    window.postMessage({ type: 'OPENPLAN3D_LOAD_PLAN', plan: p }, window.location.origin);
  }, plan);
  await page.waitForSelector('canvas', { timeout: 120000 });
  await new Promise((r) => setTimeout(r, 3500));
}

async function newLabeledPage(browser, port) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  await page.evaluateOnNewDocument(() => {
    window.__zaniniCanvasFillTexts = [];
    const origFill = CanvasRenderingContext2D.prototype.fillText;
    const origMeasure = CanvasRenderingContext2D.prototype.measureText;
    function capturePainted(text, x, y) {
      if (typeof text !== 'string' || !/\(\s*\d/.test(text)) return;
      if (!(text.includes('ft') || text.includes('m²') || text.includes('m\u00b2'))) return;
      window.__zaniniCanvasFillTexts.push({ text: text, x: Number(x) || 0, y: Number(y) || 0 });
    }
    CanvasRenderingContext2D.prototype.fillText = function (text, x, y) {
      capturePainted(text, x, y);
      return origFill.apply(this, arguments);
    };
    CanvasRenderingContext2D.prototype.measureText = function (text) {
      return origMeasure.apply(this, arguments);
    };
  });
  await page.goto(`http://127.0.0.1:${port}${VENDOR}/editor/index.html`, {
    waitUntil: 'domcontentloaded',
    timeout: 120000,
  });
  return page;
}

function areaFromLabelText(text) {
  const m = String(text).match(/(\d+(?:\.\d+)?)\s*(?:m²|m\u00b2|ft²|ft\u00b2)/);
  return m ? { value: parseFloat(m[1]), unit: text.includes('ft') ? 'sf' : 'm2' } : null;
}

async function main() {
  const bridge = require('../../public/js/openplan3d-measurement-bridge.js');
  const imperialPlan = rect10x12Plan(true);
  const metricPlan = rect10x12Plan(false);
  const metricsImperial = bridge.layoutMetrics(imperialPlan, 'r1', { wall_height_ft: 8.0 });
  assert.strictEqual(metricsImperial.area_sqft, 111.89, 'bridge interior 10×12 sf pin');

  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  try {
    async function readLabels(plan) {
      const page = await newLabeledPage(browser, port);
      try {
        await page.waitForFunction(
          () =>
            window.OpenPlan3DMeasurementBridge &&
            typeof window.OpenPlan3DMeasurementBridge.formatOnPlanRoomArea === 'function',
          { timeout: 60000 }
        );
        await loadPlan(page, plan);
        await page.waitForFunction(
          () => {
            const canvasTexts = (window.__zaniniCanvasFillTexts || []).length;
            const body = document.body.innerText || '';
            return canvasTexts > 0 || /(\d+\.\d+)\s*(?:ft²|ft\u00b2|m²|m\u00b2)/.test(body);
          },
          { timeout: 90000 }
        );
        return page.evaluate(() => {
          var painted = (window.__zaniniCanvasFillTexts || []).slice();
          var byText = {};
          for (var i = 0; i < painted.length; i++) {
            var entry = painted[i];
            var text = entry && entry.text ? entry.text : String(entry);
            if (!byText[text]) byText[text] = [];
            byText[text].push({ x: entry.x, y: entry.y });
          }
          var labels = Object.keys(byText);
          return { labels: labels, painted: byText };
        });
      } finally {
        await page.close();
      }
    }

    const imperialLabels = await readLabels(imperialPlan);
    assert.strictEqual(imperialLabels.labels.length, 1, 'one painted label per room on imperial rect');
    const parsedImperial = imperialLabels.labels.map(areaFromLabelText).filter(Boolean);
    assert.strictEqual(parsedImperial.length, 1);
    assert.ok(parsedImperial[0].unit === 'sf' && Math.abs(parsedImperial[0].value - 111.89) < 0.02,
      'imperial on-plan label must show 111.89 sf, got ' + JSON.stringify(imperialLabels.labels));

    const metricLabels = await readLabels(metricPlan);
    const parsedMetric = metricLabels.labels.map(areaFromLabelText).filter(Boolean);
    const expectedM2 = Math.round((111.89 / 10.7639) * 100) / 100;
    assert.ok(parsedMetric.some((p) => p.unit === 'm2' && Math.abs(p.value - expectedM2) < 0.02),
      'metric label must show interior m² ~10.39, got ' + JSON.stringify(metricLabels.labels));

    const hand0Plan = hand0NoRoomsPlan();
    const hand0Metrics = bridge.layoutMetrics(hand0Plan, null, { wall_height_ft: 8.0 });
    const hand0Labels = await readLabels(hand0Plan);
    const parsedHand0 = hand0Labels.labels.map(areaFromLabelText).filter(Boolean);
    assert.ok(
      parsedHand0.some((p) => p.unit === 'sf' && Math.abs(p.value - hand0Metrics.area_sqft) < 0.5),
      'hand0 canvas-fill label must show interior sf ~1307.52, got ' + JSON.stringify(hand0Labels.labels)
    );

    const lPlan = lPartitionsPlan();
    const lExpectedM2 = bridge.canvasFillRoomEntries(lPlan)[0].room.area;
    assert.ok(Math.abs(lExpectedM2 - 26.66) < 0.02, 'L partitions interior m² pin');
    const lLabels = await readLabels(lPlan);
    const parsedL = lLabels.labels.map(areaFromLabelText).filter(Boolean);
    assert.ok(
      parsedL.some((p) => p.unit === 'm2' && Math.abs(p.value - lExpectedM2) < 0.05),
      'L+partitions label must match canvas-fill interior m², got ' + JSON.stringify(lLabels.labels)
    );

    console.log('openplan3d-room-interior-label-browser: ok', {
      storeChunk: patched.storeChunk,
      imperial: imperialLabels.labels[0],
      hand0: hand0Labels.labels[0],
      lPart: lLabels.labels[0],
    });
  } finally {
    await browser.close();
    server.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

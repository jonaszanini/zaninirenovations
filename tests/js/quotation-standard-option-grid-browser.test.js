'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/quotation-standard-option-grid.html');
const publicDir = path.join(ROOT, 'public');
const shotsDir = path.join(__dirname, 'artifacts');
const ARTIFACT_DIRS = [shotsDir, '/opt/cursor/artifacts'];

function extractBladeStyles() {
  const files = [
    'resources/views/admin/quotations/_building.blade.php',
    'resources/views/admin/quotations/_commute_expense.blade.php',
    'resources/views/admin/quotations/_debris_removal.blade.php',
    'resources/views/admin/quotations/_payment_schedule.blade.php',
  ];
  return files.map((rel) => {
    const src = fs.readFileSync(path.join(ROOT, rel), 'utf8');
    return [...src.matchAll(/<style>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n');
  }).join('\n');
}

function pageHtml() {
  return fs.readFileSync(FIXTURE, 'utf8').replace(
    '<style id="blade-inline"></style>',
    `<style id="blade-inline">${extractBladeStyles()}</style>`
  );
}

function startServer() {
  return new Promise((resolve, reject) => {
    const html = pageHtml();
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/' || urlPath === '/quotation-standard-option-grid.html') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
      }
      const file = path.join(publicDir, urlPath.replace(/^\//, '').split('?')[0]);
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
        const types = {
          '.css': 'text/css',
          '.js': 'text/javascript',
          '.html': 'text/html',
          '.woff': 'font/woff',
          '.woff2': 'font/woff2',
          '.ttf': 'font/ttf',
          '.eot': 'application/vnd.ms-fontobject',
          '.svg': 'image/svg+xml',
        };
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function launch() {
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

function copyShot(fromPath, filename) {
  for (const dir of ARTIFACT_DIRS) {
    try {
      fs.mkdirSync(dir, { recursive: true });
      fs.copyFileSync(fromPath, path.join(dir, filename));
    } catch (err) {
      if (dir !== shotsDir) {
        continue;
      }
      throw err;
    }
  }
}

async function capture(width, height, filename, port) {
  const browser = await launch();
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.sq-option-grid', { timeout: 5000 });

  const layout = await page.evaluate(() => {
    const grid = document.querySelector('.sq-option-grid');
    const ids = ['q-building', 'q-commute-expense', 'q-debris-removal', 'q-payment-schedule'];
    const cards = {};
    ids.forEach((id) => {
      const el = document.getElementById(id);
      const r = el ? el.getBoundingClientRect() : null;
      cards[id] = r
        ? { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height), right: Math.round(r.right) }
        : null;
    });
    const fields = {
      includeBuilding: !!document.getElementById('q-building-include'),
      buildingPercent: !!document.getElementById('q-building-percent'),
      addedAmount: !!document.getElementById('q-building-added-amount'),
      commuteYes: !!document.querySelector('[data-cp-commute-include-yes]'),
      commutePercent: !!document.getElementById('q-commute-percent'),
      commuteTrips: !!document.getElementById('q-commute-trips'),
      roundTrip: !!document.querySelector('[data-cp-commute-round-trip]'),
      debrisAmount: !!document.getElementById('q-debris-amount'),
      payment: !!document.querySelector('input[name="payment_installments"]'),
      paymentPct: !!document.querySelector('input[name="payment_percentages[2][0]"]'),
    };
    return {
      overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      scrollWidth: document.documentElement.scrollWidth,
      innerWidth: window.innerWidth,
      gridCols: getComputedStyle(grid).gridTemplateColumns,
      tipCount: document.querySelectorAll('.sq-option-grid .admin-info-tip').length,
      fields,
      cards,
    };
  });

  assert.ok(layout.cards['q-building'], 'building card missing');
  assert.ok(layout.cards['q-commute-expense'], 'commute card missing');
  assert.ok(layout.cards['q-debris-removal'], 'debris card missing');
  assert.ok(layout.cards['q-payment-schedule'], 'payment card missing');
  Object.entries(layout.fields).forEach(([name, ok]) => {
    assert.ok(ok, `missing field hook ${name}`);
  });
  assert.ok(layout.tipCount >= 4, `expected clickable info icons, got ${layout.tipCount}`);
  assert.strictEqual(layout.overflow, false, `${width}px must not horizontal-overflow (scrollWidth=${layout.scrollWidth})`);
  assert.ok(layout.cards['q-building'].right <= width + 1, `${width}px building card overflows viewport`);
  assert.ok(layout.cards['q-payment-schedule'].right <= width + 1, `${width}px payment card overflows viewport`);

  if (width >= 1024) {
    const cols = layout.gridCols.trim().split(/\s+/);
    assert.strictEqual(cols.length, 2, `1024px+ grid must be 2 columns, got ${layout.gridCols}`);
    assert.ok(
      layout.cards['q-commute-expense'].x > layout.cards['q-building'].x + 80,
      'Building extra labour should sit beside Charge Commute expense'
    );
    assert.ok(
      Math.abs(layout.cards['q-building'].y - layout.cards['q-commute-expense'].y) < 24,
      'Building and Commute should share a row'
    );
    assert.ok(
      layout.cards['q-payment-schedule'].x > layout.cards['q-debris-removal'].x + 80,
      'Charge Debris removal should sit beside Payment schedule'
    );
    assert.ok(
      Math.abs(layout.cards['q-debris-removal'].y - layout.cards['q-payment-schedule'].y) < 24,
      'Debris and Payment schedule should share a row'
    );
  } else {
    const cols = layout.gridCols.trim().split(/\s+/);
    assert.strictEqual(cols.length, 1, `390px grid must be 1 column, got ${layout.gridCols}`);
    const xs = Object.values(layout.cards).map((c) => c.x);
    assert.ok(Math.max(...xs) - Math.min(...xs) <= 2, `390px cards must stack (x=${xs.join(',')})`);
    assert.ok(layout.cards['q-commute-expense'].y > layout.cards['q-building'].y);
    assert.ok(layout.cards['q-debris-removal'].y > layout.cards['q-commute-expense'].y);
    assert.ok(layout.cards['q-payment-schedule'].y > layout.cards['q-debris-removal'].y);
  }

  fs.mkdirSync(shotsDir, { recursive: true });
  const outPath = path.join(shotsDir, filename);
  const grid = await page.$('.sq-option-grid');
  assert.ok(grid, 'option grid missing for screenshot');
  await grid.screenshot({ path: outPath });
  copyShot(outPath, filename);

  const tip = await page.$('#q-building .admin-info-tip');
  assert.ok(tip, 'building help icon missing');
  const closed = await page.$eval('#q-building .admin-info-tip', (el) => el.getAttribute('aria-expanded') === 'true');
  assert.strictEqual(closed, false, 'help text stays closed until the info icon is clicked');
  await tip.click();
  const opened = await page.$eval('#q-building .admin-info-tip', (el) => el.getAttribute('aria-expanded') === 'true');
  assert.strictEqual(opened, true, 'help text stays behind a click-to-open info icon');

  await browser.close();
  return layout;
}

async function main() {
  const { server, port } = await startServer();
  const results = [];
  try {
    results.push(await capture(1280, 1100, 'standard-quotation-option-grid-1280.png', port));
    results.push(await capture(390, 1600, 'standard-quotation-option-grid-390.png', port));
  } finally {
    server.close();
  }
  console.log(JSON.stringify({ ok: true, results }, null, 2));
  console.log('quotation-standard-option-grid-browser: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

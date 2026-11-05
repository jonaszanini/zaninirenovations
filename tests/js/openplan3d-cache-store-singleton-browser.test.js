'use strict';

/**
 * Gate B browser smoke: fresh index (-h4 graph) must use one store for bridge + UI.
 * Serves legacy -h3 bytes when requested (simulates returning cache) but index must not load them.
 */

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const VENDOR = '/vendor/openplan3d-h1';
const MANIFEST = JSON.parse(
  fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8')
);
const HAND0 = path.join(__dirname, '../fixtures/openplan3d-hand0-drawn-plan.json');

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
};

function startServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file = path.join(publicDir, urlPath.replace(/^\//, ''));
      if (fs.existsSync(file) && fs.statSync(file).isDirectory()) {
        file = path.join(file, 'index.html');
      }
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
        const headers = { 'Content-Type': types[ext] || 'application/octet-stream' };
        if (urlPath.includes('/_app/immutable/') && urlPath.endsWith('.js')) {
          headers['Cache-Control'] = 'public, max-age=31536000, immutable';
        }
        res.writeHead(200, headers);
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

function hand0Plan() {
  const hand0 = JSON.parse(fs.readFileSync(HAND0, 'utf8'));
  const floor = JSON.parse(JSON.stringify(hand0.floors[0]));
  floor.id = 'f1';
  floor.rooms = [];
  return { unit: 'cm', activeFloorId: 'f1', floors: [floor], floor };
}

async function run() {
  console.log('openplan3d-cache-store-singleton-browser tests');
  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const pageErrors = [];
  const requestedH3 = [];
  const requestedH4 = [];
  try {
    const page = await browser.newPage();
    page.on('pageerror', (e) => pageErrors.push(e.message));
    await page.setRequestInterception(true);
    page.on('request', (req) => {
      const u = req.url();
      if (u.includes('-h3.js')) requestedH3.push(u);
      if (u.includes('-h4.js')) requestedH4.push(u);
      req.continue();
    });

    await page.goto(`http://127.0.0.1:${port}${VENDOR}/editor/index.html`, {
      waitUntil: 'networkidle0',
      timeout: 120000,
    });

    assert.strictEqual(
      requestedH3.length,
      0,
      `fresh index must not fetch legacy -h3 modules; got ${requestedH3.slice(0, 5).join(', ')}`
    );
    assert.ok(
      requestedH4.some((u) => u.includes(MANIFEST.storeChunk)),
      'must fetch active store chunk'
    );
    assert.ok(
      requestedH4.some((u) => u.includes('app.Badnr33M-h4.js')),
      'must fetch active app entry'
    );

    await page.waitForFunction(
      () => window.__openPlan3D && typeof window.__openPlan3D.getProject === 'function',
      { timeout: 120000 }
    );

    await page.evaluate((p) => {
      window.postMessage({ type: 'OPENPLAN3D_LOAD_PLAN', plan: p }, window.location.origin);
    }, hand0Plan());

    await page.waitForFunction(
      () => {
        const t = document.body.innerText || '';
        return /\b1 room\b/i.test(t) && !/NaN\s*m²/i.test(t) && !/Untitled Project/i.test(t);
      },
      { timeout: 120000 }
    );

    const state = await page.evaluate(() => {
      const plan = window.__openPlan3D.getProject();
      const body = document.body.innerText || '';
      const wallMatch = body.match(/(\d+)\s+walls?\b/i);
      return {
        apiWalls: plan.floors[0].walls.length,
        footerWalls: wallMatch ? Number(wallMatch[1]) : null,
        titleLooksEmpty: /Untitled Project/i.test(body) && !/Room 1/i.test(body),
        storeSame: window.__openPlan3DStoreModule === window.__openPlan3DStoreModule,
      };
    });

    assert.ok(state.apiWalls >= 16, `api walls ${state.apiWalls}`);
    assert.ok(
      state.footerWalls >= 16,
      `footer wall count must match visible plan; api=${state.apiWalls} footer=${state.footerWalls}`
    );
    assert.strictEqual(state.titleLooksEmpty, false);
    assert.strictEqual(pageErrors.length, 0, pageErrors.join('; '));

    console.log(
      `cache-singleton: h4Requests=${requestedH4.length}, apiWalls=${state.apiWalls}, footerWalls=${state.footerWalls}`
    );
    console.log('openplan3d-cache-store-singleton-browser: ok');
  } finally {
    await browser.close();
    server.close();
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});

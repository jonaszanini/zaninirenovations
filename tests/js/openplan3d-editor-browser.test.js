'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/openplan3d-editor-embed.html');

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/' || urlPath === '/openplan3d-editor-embed.html') {
        file = FIXTURE;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
        // SvelteKit replaceState drops /index.html; match Apache DirectoryIndex.
        try {
          if (fs.existsSync(file) && fs.statSync(file).isDirectory()) {
            file = path.join(file, 'index.html');
          }
        } catch (e) {}
      }
      const allowed = file === FIXTURE || file.startsWith(publicDir + path.sep);
      if (!allowed) {
        res.writeHead(403);
        res.end();
        return;
      }
      fs.readFile(file, (err, data) => {
        if (err) {
          res.writeHead(404);
          res.end('not found');
          return;
        }
        const ext = path.extname(file);
        const types = {
          '.css': 'text/css',
          '.html': 'text/html',
          '.js': 'text/javascript',
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
  const puppeteer = require('puppeteer');
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function blockedStorage(page) {
  return page.evaluate(async () => {
    const keys = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key === 'floorplan_projects' || (key && (key.startsWith('floorplan_thumb_') || key.startsWith('vh_')))) {
        keys.push(key);
      }
    }
    let idbNames = [];
    if (indexedDB.databases) {
      const dbs = await indexedDB.databases();
      idbNames = dbs.map((d) => d.name).filter((n) => n === 'openplan3d-local');
    }
    return { keys, idbNames };
  });
}

async function run() {
  console.log('Starting openPlan3D editor embed browser tests...');
  const { server, port } = await startServer();
  const browser = await launch();

  try {
    const page = await browser.newPage();
    await page.goto(`http://127.0.0.1:${port}/openplan3d-editor-embed.html`, { waitUntil: 'domcontentloaded' });

    const hidden = await page.$$eval('[data-zanini-hide-native-save]', (els) => els.map((el) => (el.textContent || '').replace(/\s+/g, ' ').trim()));
    assert.ok(hidden.some((t) => t.indexOf('Projects') !== -1), 'Projects control must be hidden');
    assert.ok(hidden.some((t) => t === 'Save' || t.indexOf('Save') === 0), 'Save control must be hidden');
    assert.ok(hidden.some((t) => t.indexOf('Saved') !== -1), 'Saved ✓ control must be hidden');
    assert.ok(hidden.some((t) => t.indexOf('Export') !== -1), 'Export control must be hidden');
    const hideStyle = await page.$$eval('[data-zanini-hide-native-save]', (els) => els.map((el) => ({
      display: getComputedStyle(el).display,
      pointerEvents: getComputedStyle(el).pointerEvents,
    })));
    assert.ok(hideStyle.length > 0, 'vendor chrome must be marked hidden');
    assert.ok(hideStyle.every((s) => s.display === 'none'), 'vendor chrome must be display:none, not only CSS class matching');
    assert.ok(hideStyle.every((s) => s.pointerEvents === 'none'), 'vendor chrome must ignore pointer events');

    await page.evaluate(() => {
      localStorage.setItem('floorplan_projects', '{"hack":true}');
      localStorage.setItem('floorplan_thumb_x', 'thumb');
      localStorage.setItem('vh_1', '1');
      localStorage.setItem('safe_other', 'ok');
    });
    const afterWrite = await page.evaluate(() => ({
      projects: localStorage.getItem('floorplan_projects'),
      thumb: localStorage.getItem('floorplan_thumb_x'),
      vh: localStorage.getItem('vh_1'),
      other: localStorage.getItem('safe_other'),
    }));
    assert.strictEqual(afterWrite.projects, null, 'floorplan_projects writes must be ignored');
    assert.strictEqual(afterWrite.thumb, null, 'floorplan_thumb_* writes must be ignored');
    assert.strictEqual(afterWrite.vh, null, 'vh_* writes must be ignored');
    assert.strictEqual(afterWrite.other, 'ok', 'unrelated localStorage must still work');

    const acks = [];
    page.on('console', () => {});
    await page.evaluate(() => {
      window.__acks = [];
      window.addEventListener('message', (e) => {
        if (e.data && e.data.type === 'OPENPLAN3D_LOAD_ACK') window.__acks.push(e.data);
      });
    });

    const loaded = {
      id: 'saved-room',
      name: 'Saved Room',
      floor: {
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 100, y: 0 } },
          { id: 'w2', start: { x: 100, y: 0 }, end: { x: 100, y: 100 } },
          { id: 'w3', start: { x: 100, y: 100 }, end: { x: 0, y: 100 } },
          { id: 'w4', start: { x: 0, y: 100 }, end: { x: 0, y: 0 } },
        ],
        rooms: [{ id: 'r1', name: 'R', walls: ['w1', 'w2', 'w3', 'w4'] }],
      },
    };

    await page.evaluate((plan) => {
      window.__openPlan3DSession.handleParentMessage({ data: { type: 'OPENPLAN3D_LOAD_PLAN', plan: plan } });
      window.__store.set({ id: 'untitled', name: 'Untitled Project', floors: [{ walls: [], rooms: [] }] });
      window.__openPlan3DSession.onEditorMounted();
    }, loaded);

    await page.waitForFunction(() => window.__acks && window.__acks.length > 0, { timeout: 3000 });
    const ack = await page.evaluate(() => window.__acks[window.__acks.length - 1]);
    assert.strictEqual(ack.ok, true, 'LOAD_ACK must confirm the loaded plan');
    const projectName = await page.evaluate(() => window.__openPlan3D.getProject().name);
    assert.strictEqual(projectName, 'Saved Room', 'getProject() must match the loaded plan after mount');

    const errorPage = await browser.newPage();
    await errorPage.goto(`http://127.0.0.1:${port}/openplan3d-editor-embed.html?missing=1`, { waitUntil: 'domcontentloaded' });
    const banner = await errorPage.$eval('#zanini-openplan3d-bridge-error', (el) => el.textContent);
    assert.match(banner, /missing H\/c store exports/, 'visible error when store exports are missing');

    const editorPage = await browser.newPage();
    editorPage.setDefaultNavigationTimeout(15000);
    await editorPage.goto(`http://127.0.0.1:${port}/vendor/openplan3d/editor/index.html`, { waitUntil: 'domcontentloaded' });
    const hostedAtLoad = await editorPage.evaluate(() => document.documentElement.classList.contains('zanini-hosted-openplan3d'));
    assert.strictEqual(hostedAtLoad, true, 'editor page must install the hosted bridge');
    await editorPage.evaluate(() => {
      localStorage.setItem('floorplan_projects', 'should-not-stick');
      localStorage.setItem('floorplan_thumb_z', 'nope');
      localStorage.setItem('vh_z', 'nope');
    });
    await editorPage.reload({ waitUntil: 'domcontentloaded' });
    await editorPage.waitForFunction(
      () => document.documentElement.classList.contains('zanini-hosted-openplan3d'),
      { timeout: 5000 }
    );
    const storage = await blockedStorage(editorPage);
    assert.deepStrictEqual(storage.keys, [], 'vendor localStorage keys stay empty after edit and reload');
    assert.deepStrictEqual(storage.idbNames, [], 'openplan3d-local IndexedDB stays empty after reload');
    const hosted = await editorPage.evaluate(() => document.documentElement.classList.contains('zanini-hosted-openplan3d'));
    assert.strictEqual(hosted, true);

    console.log('All openPlan3D editor embed browser tests PASSED successfully!');
  } finally {
    await browser.close();
    server.close();
  }
}

run().catch((err) => {
  console.error('Editor browser test FAILED:', err);
  process.exit(1);
});

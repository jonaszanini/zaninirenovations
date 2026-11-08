'use strict';

/**
 * Real hosted editor: wall finish panel must not hang the page (MutationObserver loop)
 * and must tolerate plans without plan.zanini.
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

function wallPlan(withZanini) {
  const t = 6 * 2.54;
  const plan = {
    id: 'p-finish',
    name: 'Finish test',
    activeFloorId: 'f1',
    floors: [
      {
        id: 'f1',
        name: 'Floor 1',
        walls: [
          { id: 'pw0', start: { x: 0, y: 0 }, end: { x: 120, y: 0 }, height: 243.84, thickness: t },
          { id: 'pw1', start: { x: 120, y: 0 }, end: { x: 120, y: 120 }, height: 243.84, thickness: t },
        ],
        rooms: [{ id: 'r1', walls: ['pw0', 'pw1'] }],
      },
    ],
  };
  if (withZanini) {
    plan.zanini = { version: 1, zones: [], bulkheads: [], wallFinishes: {} };
  }
  return plan;
}

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

async function main() {
  const puppeteer = require('puppeteer');
  const { server, port } = await startServer();
  const launchOpts = {
    headless: 'new',
    protocolTimeout: 120000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    launchOpts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  const browser = await puppeteer.launch(launchOpts);

  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(60000);
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}${VENDOR_PREFIX}/editor/index.html`, {
      waitUntil: 'domcontentloaded',
      timeout: 120000,
    });
    await page.waitForSelector('canvas', { timeout: 120000 });
    await page.waitForFunction(
      () => window.__openPlan3D && window.OpenPlan3DZaniniTools && typeof window.OpenPlan3DZaniniTools.renderFinishPanel === 'function',
      { timeout: 120000 }
    );

    await page.evaluate((plan) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(plan))), wallPlan(true));
    await page.waitForFunction(
      () => window.__openPlan3D.getProject().floors[0].walls.length >= 2,
      { timeout: 60000 }
    );

    await page.evaluate(() => {
      var panel = document.getElementById('qa-wall-properties-panel');
      if (!panel) {
        panel = document.createElement('div');
        panel.id = 'qa-wall-properties-panel';
        panel.style.cssText = 'position:fixed;right:8px;top:80px;width:220px;min-height:320px;z-index:5;background:#fff;padding:8px;';
        panel.innerHTML =
          '<button type="button" class="bg-blue-50 text-blue-700">Elevation</button>' +
          '<div>Wall thickness</div>';
        document.body.appendChild(panel);
      }
    });

    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        const sel = [...document.querySelectorAll('button')].find((b) =>
          /Select mode \(V\)/i.test(b.getAttribute('title') || '')
        );
        if (sel) sel.click();
        mod.X.set('pw0');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['pw0']));
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );

    await new Promise((r) => setTimeout(r, 200));

    const burstMs = await page.evaluate(() => {
      const t0 = performance.now();
      for (let i = 0; i < 8; i++) window.OpenPlan3DZaniniTools.renderFinishPanel();
      return performance.now() - t0;
    });
    assert.ok(burstMs < 2000, 'renderFinishPanel burst must finish quickly (got ' + burstMs + ' ms)');

    const panelOne = await page.evaluate(() => {
      const host = document.getElementById('openplan3d-zanini-finish-host');
      return host && !host.hidden && host.querySelector('input[data-finish-key="drywall"]') != null;
    });
    assert.strictEqual(panelOne, true, 'finish panel mounts for one selected wall');

    const paintStart = await page.evaluate(() => {
      const input = document.querySelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]');
      return input
        ? { exists: true, checked: !!input.checked, indeterminate: !!input.indeterminate }
        : { exists: false, checked: false, indeterminate: false };
    });
    assert.strictEqual(paintStart.exists, true, 'Paint finish checkbox is in the wall panel');
    assert.strictEqual(paintStart.checked, false, 'Paint starts unselected');
    assert.strictEqual(paintStart.indeterminate, true, 'new/legacy walls start with Paint unassigned (indeterminate)');

    const shotsDir = path.join(ROOT, 'storage/app/qa-screenshots');
    fs.mkdirSync(shotsDir, { recursive: true });
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.screenshot({ path: path.join(shotsDir, 'room-area-paint-ui-1280.png') });
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await page.screenshot({ path: path.join(shotsDir, 'room-area-paint-ui-390.png') });
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    try {
      fs.mkdirSync('/opt/cursor/artifacts', { recursive: true });
      fs.copyFileSync(path.join(shotsDir, 'room-area-paint-ui-1280.png'), '/opt/cursor/artifacts/room-area-paint-ui-1280.png');
      fs.copyFileSync(path.join(shotsDir, 'room-area-paint-ui-390.png'), '/opt/cursor/artifacts/room-area-paint-ui-390.png');
    } catch (e) { /* CI runners may not have /opt/cursor/artifacts */ }

    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        mod.X.set('pw0');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['pw0']));
        window.OpenPlan3DZaniniTools.renderFinishPanel();
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
    async function pumpFrames() {
      await page.evaluate(() => new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
      }));
    }

    async function setPaintCheckbox(wantOn) {
      await page.waitForSelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]', {
        timeout: 20000,
        visible: true,
      });
      await pumpFrames();
      await page.$eval('#openplan3d-zanini-finish-host input[data-finish-key="paint"]', (el, want) => {
        el.indeterminate = false;
        if (!!el.checked !== want) {
          el.click();
        }
        if (!!el.checked !== want) {
          el.checked = want;
          el.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }, wantOn);
      await pumpFrames();
      await page.waitForFunction((want) => {
        const plan = window.__openPlan3D.getProject();
        const flags = plan.zanini && plan.zanini.wallFinishes && plan.zanini.wallFinishes.pw0;
        if (!flags) return false;
        return want
          ? flags.paint === true
          : (Object.prototype.hasOwnProperty.call(flags, 'paint') && flags.paint === false);
      }, { timeout: 20000 }, wantOn);
    }

    await setPaintCheckbox(true);
    await setPaintCheckbox(false);

    await page.evaluate(() => {
      const mod = window.__openPlan3DStoreModule;
      if (mod) {
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set());
        if (mod.X && typeof mod.X.set === 'function') mod.X.set(null);
        if (mod.aB && typeof mod.aB.set === 'function') mod.aB.set(null);
      }
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    });
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => resolve())));
    const afterToggleClear = await page.evaluate(() => {
      const ids = window.OpenPlan3DZaniniTools.readSelectedWallIds();
      const host = document.getElementById('openplan3d-zanini-finish-host');
      const flags = window.__openPlan3D.getProject().zanini.wallFinishes.pw0;
      return {
        ids,
        hidden: !host || host.hidden || host.innerHTML === '',
        paintFalse: !!(flags && flags.paint === false),
      };
    });
    assert.deepStrictEqual(afterToggleClear.ids, []);
    assert.strictEqual(afterToggleClear.hidden, true, 'panel hides after deselect following a finish toggle');
    assert.strictEqual(afterToggleClear.paintFalse, true, 'paint=false persists while deselected');

    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        mod.X.set('pw0');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['pw0']));
        window.OpenPlan3DZaniniTools.renderFinishPanel();
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
    await page.waitForSelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]', { timeout: 20000 });
    const persisted = await page.evaluate(() => {
      const input = document.querySelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]');
      const flags = window.__openPlan3D.getProject().zanini.wallFinishes.pw0;
      return {
        visible: !!(input && !document.getElementById('openplan3d-zanini-finish-host').hidden),
        checked: !!(input && input.checked),
        paintFalse: !!(flags && flags.paint === false),
      };
    });
    assert.strictEqual(persisted.visible, true, 'finish panel reopens after deselect with paint persisted');
    assert.strictEqual(persisted.paintFalse, true, 'reopened wall keeps paint=false');
    assert.strictEqual(persisted.checked, false, 'reopened Paint checkbox stays unchecked');

    await page.evaluate((plan) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(plan))), wallPlan(false));
    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        mod.X.set('pw0');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['pw0', 'pw1']));
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );

    const multiOk = await page.evaluate(() => {
      try {
        window.OpenPlan3DZaniniTools.renderFinishPanel();
        const host = document.getElementById('openplan3d-zanini-finish-host');
        return !!(host && !host.hidden && /2 walls/.test(host.textContent || ''));
      } catch (e) {
        return String(e);
      }
    });
    assert.strictEqual(multiOk, true, 'multi-wall render without plan.zanini must not throw');

    async function readSettled() {
      return page.evaluate(() => {
        const ids = window.OpenPlan3DZaniniTools.readSelectedWallIds
          ? window.OpenPlan3DZaniniTools.readSelectedWallIds()
          : [];
        const host = document.getElementById('openplan3d-zanini-finish-host');
        const hidden = !host || host.hidden || host.innerHTML === '';
        return { ids, hidden };
      });
    }

    async function pumpFrame() {
      await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => resolve())));
    }

    async function clearWallSelectionOnce() {
      await page.evaluate(() => {
        const mod = window.__openPlan3DStoreModule;
        if (mod) {
          if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set());
          if (mod.X && typeof mod.X.set === 'function') mod.X.set(null);
          if (mod.aB && typeof mod.aB.set === 'function') mod.aB.set(null);
        }
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      });
    }

    async function assertHiddenAndEmpty(label) {
      const settled = await readSettled();
      assert.deepStrictEqual(settled.ids, [], label + ': ids');
      assert.strictEqual(settled.hidden, true, label);
    }

    // Premise: Escape/clear is one selection change. writePlan must not restore a
    // snapshot from an earlier finish toggle on a later animation frame.
    await clearWallSelectionOnce();
    await pumpFrame();
    await assertHiddenAndEmpty('finish panel hides when no wall selected');
    await page.evaluate(() => window.OpenPlan3DZaniniTools.renderFinishPanel());
    await pumpFrame();
    await assertHiddenAndEmpty('finish panel stays hidden after a later render/event cycle');

    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        mod.X.set('pw0');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['pw0']));
        window.OpenPlan3DZaniniTools.renderFinishPanel();
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
    await page.waitForSelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]', { timeout: 20000 });
    const reopened = await page.evaluate(() => {
      const host = document.getElementById('openplan3d-zanini-finish-host');
      return !!(host && !host.hidden && host.querySelector('input[data-finish-key="paint"]'));
    });
    assert.strictEqual(reopened, true, 'finish panel reopens when a wall is selected again');

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.screenshot({ path: path.join(shotsDir, 'room-area-finish-selected-1280.png') });
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await page.screenshot({ path: path.join(shotsDir, 'room-area-finish-selected-390.png') });
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });

    await clearWallSelectionOnce();
    await pumpFrame();
    await assertHiddenAndEmpty('reopened panel hides again after a later deselect');
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.screenshot({ path: path.join(shotsDir, 'room-area-finish-cleared-1280.png') });
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await page.screenshot({ path: path.join(shotsDir, 'room-area-finish-cleared-390.png') });
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    try {
      fs.mkdirSync('/opt/cursor/artifacts', { recursive: true });
      ['room-area-finish-selected-1280.png', 'room-area-finish-selected-390.png',
        'room-area-finish-cleared-1280.png', 'room-area-finish-cleared-390.png'].forEach((name) => {
        fs.copyFileSync(path.join(shotsDir, name), path.join('/opt/cursor/artifacts', name));
      });
    } catch (e) { /* CI runners may not have /opt/cursor/artifacts */ }

    console.log('openplan3d-wall-finish-browser: ok');
  } finally {
    await browser.close();
    if (typeof server.closeAllConnections === 'function') server.closeAllConnections();
    server.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

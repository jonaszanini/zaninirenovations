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

    const partitionPlan = (() => {
      const cm = 30.48;
      const t = 11.43;
      const W = 20 * cm;
      const H = 10 * cm;
      const M = 10 * cm;
      return {
        id: 'p-rooms',
        activeFloorId: 'f1',
        floors: [
          {
            id: 'f1',
            walls: [
              { id: 'w1', start: { x: 0, y: 0 }, end: { x: W, y: 0 }, thickness: t, height: 243.84 },
              { id: 'w2', start: { x: W, y: 0 }, end: { x: W, y: H }, thickness: t, height: 243.84 },
              { id: 'w3', start: { x: W, y: H }, end: { x: 0, y: H }, thickness: t, height: 243.84 },
              { id: 'w4', start: { x: 0, y: H }, end: { x: 0, y: 0 }, thickness: t, height: 243.84 },
              { id: 'p1', start: { x: M, y: 0 }, end: { x: M, y: H }, thickness: t, height: 243.84 },
            ],
            rooms: [
              { id: 'r-bed', name: 'Bedroom', walls: ['w4', 'w1', 'p1', 'w3'] },
              { id: 'r-off', name: 'Office', walls: ['p1', 'w2', 'w3', 'w1'] },
            ],
          },
        ],
        zanini: { version: 1, zones: [], bulkheads: [], wallFinishes: {} },
      };
    })();

    await page.evaluate((plan) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(plan))), partitionPlan);
    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        const sel = [...document.querySelectorAll('button')].find((b) =>
          /Select mode \(V\)/i.test(b.getAttribute('title') || '')
        );
        if (sel) sel.click();
        mod.X.set('p1');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['p1']));
        window.OpenPlan3DZaniniTools.renderFinishPanel();
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
    await page.waitForSelector('[data-finish-drywall-faces]', { timeout: 15000 });
    const drywallPanel = await page.evaluate(() => {
      const host = document.getElementById('openplan3d-zanini-finish-host');
      const heading = host ? (host.querySelector('div')?.textContent || '') : '';
      const sel = host ? host.querySelector('[data-finish-drywall-faces]') : null;
      return {
        heading,
        hasDrywallFaces: !!sel,
        options: sel ? [...sel.options].map((o) => o.textContent) : [],
      };
    });
    assert.ok(drywallPanel.hasDrywallFaces, 'partition between two rooms must show Drywall faces control');
    assert.ok(!/Wall finishes · p1/i.test(drywallPanel.heading), 'heading must not show raw wall id');
    assert.ok(drywallPanel.options.some((o) => /Bedroom side/i.test(o)), 'drywall face options name adjacent room');

    const finishHost = await page.$('#openplan3d-zanini-finish-host');
    assert.ok(finishHost, 'finish host for drywall screenshot');
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await finishHost.screenshot({ path: path.join(shotsDir, 'room-area-wall-finishes-drywall-1280.png') });
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    const mobileFinishTap = await page.evaluate(() => {
      const host = document.getElementById('openplan3d-zanini-finish-host');
      const cb = host && host.querySelector('input[type="checkbox"]');
      if (!cb) return { ok: false, reason: 'no checkbox' };
      const r = cb.getBoundingClientRect();
      const x = r.left + r.width / 2;
      const y = r.top + r.height / 2;
      const top = document.elementFromPoint(x, y);
      return {
        ok: !!(top && (top === cb || (host && host.contains(top)))),
        w: r.width,
        h: r.height,
        viewportW: window.innerWidth,
      };
    });
    assert.ok(mobileFinishTap.viewportW === 390, '390 drywall screenshot must use real 390px viewport');
    assert.ok(mobileFinishTap.ok, 'Wall finishes panel must be above properties sheet at 390');
    assert.ok(mobileFinishTap.w >= 44 && mobileFinishTap.h >= 44, 'finish checkboxes need >=44px tap targets at 390');
    await finishHost.screenshot({ path: path.join(shotsDir, 'room-area-wall-finishes-drywall-390.png') });
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    try {
      fs.mkdirSync('/opt/cursor/artifacts', { recursive: true });
      fs.copyFileSync(
        path.join(shotsDir, 'room-area-wall-finishes-drywall-1280.png'),
        '/opt/cursor/artifacts/room-area-wall-finishes-drywall-1280.png'
      );
      fs.copyFileSync(
        path.join(shotsDir, 'room-area-wall-finishes-drywall-390.png'),
        '/opt/cursor/artifacts/room-area-wall-finishes-drywall-390.png'
      );
    } catch (e) { /* optional artifacts dir */ }

    await page.evaluate((plan) => window.__openPlan3D.setProject(JSON.parse(JSON.stringify(plan))), wallPlan(true));
    await page.evaluate(
      async ({ storeChunk, vendorPrefix }) => {
        const mod = await import(`${vendorPrefix}/_app/immutable/chunks/${storeChunk}`);
        const sel = [...document.querySelectorAll('button')].find((b) =>
          /Select mode \(V\)/i.test(b.getAttribute('title') || '')
        );
        if (sel) sel.click();
        mod.X.set('pw0');
        if (mod.bg && typeof mod.bg.set === 'function') mod.bg.set(new Set(['pw0']));
        window.OpenPlan3DZaniniTools.renderFinishPanel();
      },
      { storeChunk: STORE_CHUNK, vendorPrefix: VENDOR_PREFIX }
    );
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
    const paintSel = '#openplan3d-zanini-finish-host input[data-finish-key="paint"]';

    async function waitOneRenderCycle() {
      await page.evaluate(() => new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
      }));
    }

    async function waitPaintSynced(wantOn) {
      await page.waitForFunction((want, sel) => {
        const input = document.querySelector(sel);
        if (!input || !input.isConnected || input.disabled || input.hidden) return false;
        const style = window.getComputedStyle(input);
        if (style.display === 'none' || style.visibility === 'hidden' || style.pointerEvents === 'none') return false;
        const flags = window.__openPlan3D.getProject().zanini
          && window.__openPlan3D.getProject().zanini.wallFinishes
          && window.__openPlan3D.getProject().zanini.wallFinishes.pw0;
        if (!flags) return false;
        const planOn = want
          ? flags.paint === true
          : (Object.prototype.hasOwnProperty.call(flags, 'paint') && flags.paint === false);
        if (!planOn) return false;
        if (input.indeterminate) return false;
        return !!input.checked === want;
      }, { timeout: 8000 }, wantOn, paintSel);
    }

    await page.waitForSelector(paintSel, { timeout: 8000, visible: true });
    await page.click(paintSel);
    await waitPaintSynced(true);
    await waitOneRenderCycle();
    await waitPaintSynced(true);

    await page.click(paintSel);
    await waitPaintSynced(false);
    await waitOneRenderCycle();
    await waitPaintSynced(false);

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
    await page.waitForSelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]', { timeout: 8000 });
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
    await page.waitForSelector('#openplan3d-zanini-finish-host input[data-finish-key="paint"]', { timeout: 8000 });
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

'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const CSS = fs.readFileSync(path.join(ROOT, 'public/css/admin-utilities.css'), 'utf8');
const KONVA_FILE = path.join(ROOT, 'node_modules/konva/konva.min.js');
const TEST_TIMEOUT_MS = 120000;

function assertCssGeometry() {
  assert.ok(
    CSS.includes('.util-ra--fullpage.is-more-open .util-ra-more-sheet .admin-btn.util-ra-icon-btn:not([data-ra-more-close])'),
    '100% width rule must exclude Close'
  );
  assert.ok(
    CSS.includes('.util-ra--fullpage.is-more-open .util-ra-more-sheet .util-ra-more-sheet__head .admin-btn.util-ra-icon-btn'),
    'Close override must beat the 5-class width:100% rule'
  );
  const headRule = CSS.slice(CSS.indexOf('.util-ra--fullpage.is-more-open .util-ra-more-sheet .util-ra-more-sheet__head .admin-btn.util-ra-icon-btn'));
  assert.ok(/width:\s*auto/.test(headRule.slice(0, 240)), 'Close override sets width:auto');
  assert.ok(
    CSS.includes('.util-ra--fullpage:not(.is-sheet-expanded):not(.is-dock-open) .util-ra-side'),
    'collapsed sheet sizes to content'
  );
  const collapsed = CSS.slice(CSS.indexOf('.util-ra--fullpage:not(.is-sheet-expanded):not(.is-dock-open) .util-ra-side'));
  assert.ok(/height:\s*auto/.test(collapsed.slice(0, 220)), 'collapsed sheet height is auto');
  const phoneFeedback = CSS.slice(CSS.lastIndexOf('.util-ra--fullpage .util-ra-feedback {'));
  const phoneFeedbackHead = phoneFeedback.slice(0, 420);
  assert.ok(/position:\s*absolute/.test(phoneFeedbackHead), '390 feedback overlays the canvas edge');
  assert.ok(/bottom:/.test(phoneFeedbackHead), '390 feedback sits on the canvas bottom edge');
  assert.ok(!/color:\s*var\(--admin-text\)/.test(phoneFeedbackHead), '390 feedback must not override status colours');
  assert.ok(
    CSS.includes('.util-ra--fullpage .util-ra-feedback.is-error { color: var(--admin-danger); }'),
    'phone CSS keeps error status red'
  );
  assert.ok(
    CSS.includes('.util-ra--fullpage .util-ra-feedback.is-ok { color: var(--admin-success, #2e7d32); }'),
    'phone CSS keeps success status green'
  );
}

function resolveKonvaFile() {
  if (fs.existsSync(KONVA_FILE) && fs.statSync(KONVA_FILE).size > 1000) {
    return Promise.resolve(KONVA_FILE);
  }
  return Promise.reject(new Error('Konva must be installed as a local devDependency (node_modules/konva). Tests do not download from a CDN.'));
}

function startStaticServer(konvaFile) {
  const publicDir = path.join(ROOT, 'public');
  const fixturesDir = path.join(__dirname, 'fixtures');
  const fixture = path.join(fixturesDir, 'room-area-phone-geometry.html');
  const savedRoomFixture = path.join(fixturesDir, 'room-area-saved-room-fit.html');
  const layoutChangeFixture = path.join(fixturesDir, 'room-area-layout-change-fit.html');
  const zoomPanFixture = path.join(fixturesDir, 'room-area-zoom-pan-embedded.html');
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/') {
        file = fixture;
      } else if (urlPath === '/saved-room' || urlPath === '/saved-room.html') {
        file = savedRoomFixture;
      } else if (urlPath === '/layout-change' || urlPath === '/layout-change.html') {
        file = layoutChangeFixture;
      } else if (urlPath === '/zoom-pan' || urlPath === '/zoom-pan.html') {
        file = zoomPanFixture;
      } else if (urlPath === '/vendor-test/konva.min.js') {
        file = konvaFile;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
      }
      const allowed = file === fixture
        || file === savedRoomFixture
        || file === layoutChangeFixture
        || file === zoomPanFixture
        || file === konvaFile
        || file.startsWith(publicDir + path.sep);
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
        const types = { '.css': 'text/css', '.html': 'text/html', '.js': 'text/javascript' };
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => {
      resolve({ server, port: server.address().port });
    });
    server.on('error', reject);
  });
}

function readCameraScript() {
  return () => {
    const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
    const wrap = wrapEl.getBoundingClientRect();
    const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
    const world = stage && stage.getLayers()[0] && stage.getLayers()[0].getChildren()[0];
    if (!wrapEl || !stage || !world) return null;
    const scale = world.scaleX();
    const pos = world.position();
    return {
      scale,
      zoomPercent: Math.round(scale * 100),
      x: pos.x,
      y: pos.y,
      worldCx: (wrap.width / 2 - pos.x) / scale,
      worldCy: (wrap.height / 2 - pos.y) / scale,
      wrapW: wrap.width,
      wrapH: wrap.height,
      stageW: stage.width(),
      stageH: stage.height(),
    };
  };
}

async function waitForEditingRoom(page) {
  await page.waitForFunction(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
      && window.Konva.stages[0].getLayers()[0]
      && window.Konva.stages[0].getLayers()[0].getChildren()[0];
    const feedback = document.querySelector('[data-ra-feedback]');
    return !!(wrap && wrap.getBoundingClientRect().width > 80 && world && world.scaleX() > 0
      && feedback && /Editing/.test(feedback.textContent || ''));
  }, { timeout: 15000 });
}

async function zoomAndPan(page) {
  const start = await page.evaluate(readCameraScript());
  assert.ok(start, 'camera exists before zoom');
  for (let i = 0; i < 8; i += 1) {
    await page.evaluate(() => {
      const btn = document.querySelector('[data-ra-zoom-out]');
      if (btn) btn.click();
    });
  }
  await page.waitForFunction((prev) => {
    const world = window.Konva.stages[0].getLayers()[0].getChildren()[0];
    return world && world.scaleX() < prev * 0.75;
  }, { timeout: 8000 }, start.scale);
  const afterZoom = await page.evaluate(readCameraScript());
  assert.ok(afterZoom && afterZoom.scale < start.scale * 0.75, 'zoom out lowered scale: ' + (afterZoom && afterZoom.scale) + ' from ' + start.scale);
  await page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    wrap.dispatchEvent(new WheelEvent('wheel', {
      deltaX: 90,
      deltaY: 0,
      bubbles: true,
      cancelable: true,
    }));
  });
  const afterX = await page.evaluate(readCameraScript());
  assert.ok(
    afterX && Math.abs(afterX.worldCx - afterZoom.worldCx) > 1,
    'horizontal wheel panned X: ' + JSON.stringify({ afterZoom, afterX })
  );
  await page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    wrap.dispatchEvent(new WheelEvent('wheel', {
      deltaX: 0,
      deltaY: 80,
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    }));
  });
  const after = await page.evaluate(readCameraScript());
  assert.ok(
    after && Math.abs(after.worldCy - afterX.worldCy) > 1,
    'shift+vertical wheel panned Y: ' + JSON.stringify({ afterX, after })
  );
  return after;
}

async function assertExtremePanClamped(page, label) {
  await page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    wrap.dispatchEvent(new WheelEvent('wheel', {
      deltaX: 8000,
      deltaY: 4000,
      bubbles: true,
      cancelable: true,
    }));
  });
  const clamped = await page.evaluate(readCameraScript());
  assert.ok(clamped, label + ' camera exists after extreme pan');
  assertRoomStaysOnScreen(clamped, label);
}

function assertRoomStaysOnScreen(cam, label) {
  const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
  const xs = verts.map((v) => cam.x + v.x * cam.scale);
  const ys = verts.map((v) => cam.y + v.y * cam.scale);
  const minX = Math.min.apply(null, xs);
  const maxX = Math.max.apply(null, xs);
  const minY = Math.min.apply(null, ys);
  const maxY = Math.max.apply(null, ys);
  assert.ok(
    maxX > 8 && minX < cam.wrapW - 8 && maxY > 8 && minY < cam.wrapH - 8,
    label + ' keeps part of the room on screen: ' + JSON.stringify({ cam, minX, maxX, minY, maxY })
  );
}

function assertCameraHeld(before, after, label) {
  assert.ok(after, label + ' camera exists');
  assert.ok(
    Math.abs(after.scale - before.scale) <= before.scale * 0.03,
    label + ' keeps zoom: before=' + before.scale + ' after=' + after.scale
  );
  assert.ok(
    Math.abs(after.zoomPercent - before.zoomPercent) <= 4,
    label + ' keeps zoom percent: before=' + before.zoomPercent + ' after=' + after.zoomPercent
  );
  assert.ok(
    Math.abs(after.worldCx - before.worldCx) <= 6 && Math.abs(after.worldCy - before.worldCy) <= 6,
    label + ' keeps centre: ' + JSON.stringify({ before, after })
  );
}

async function waitForStableCanvasFit(page, options) {
  const opts = options || {};
  await page.evaluate(() => {
    window.__raFitStable = { sig: '', n: 0 };
  });
  await page.waitForFunction((minW, maxW, needGrown, needFirstFitGone, needEditing) => {
    const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
    const canvas = wrapEl && wrapEl.querySelector('canvas');
    const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
      && window.Konva.stages[0].getLayers()[0]
      && window.Konva.stages[0].getLayers()[0].getChildren()[0];
    const feedback = document.querySelector('[data-ra-feedback]');
    if (!wrapEl || !canvas || !world) return false;
    const wrap = wrapEl.getBoundingClientRect();
    if (wrap.width <= minW || wrap.width >= maxW) return false;
    if (needGrown && !document.documentElement.classList.contains('is-geom-grown')) return false;
    if (needFirstFitGone && document.documentElement.classList.contains('is-geom-first-fit')) return false;
    if (needEditing && !(feedback && /Editing/.test(feedback.textContent || ''))) return false;
    const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
    const scale = world.scaleX();
    const pos = world.position();
    const screen = verts.map((v) => ({
      x: wrap.left + pos.x + v.x * scale,
      y: wrap.top + pos.y + v.y * scale,
    }));
    const minX = Math.min.apply(null, screen.map((p) => p.x));
    const maxX = Math.max.apply(null, screen.map((p) => p.x));
    const minY = Math.min.apply(null, screen.map((p) => p.y));
    const maxY = Math.max.apply(null, screen.map((p) => p.y));
    const inside = minX >= wrap.left - 2 && maxX <= wrap.right + 2
      && minY >= wrap.top - 2 && maxY <= wrap.bottom + 2;
    const signature = [
      wrap.width.toFixed(2),
      wrap.height.toFixed(2),
      Number(scale).toFixed(6),
      Number(pos.x).toFixed(2),
      Number(pos.y).toFixed(2),
    ].join('|');
    if (!inside || !(scale > 0)) {
      window.__raFitStable = { sig: '', n: 0 };
      return false;
    }
    const prev = window.__raFitStable || { sig: '', n: 0 };
    if (prev.sig === signature) prev.n += 1;
    else {
      prev.sig = signature;
      prev.n = 1;
    }
    window.__raFitStable = prev;
    return prev.n >= 8;
  }, { timeout: opts.timeout || 15000 }, opts.minWidth ?? 200, opts.maxWidth ?? 500, !!opts.grown, !!opts.firstFitGone, !!opts.editing);
}

async function measureViewports() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    throw new Error('puppeteer is required for room-area phone geometry measurements');
  }
  const konvaFile = await resolveKonvaFile();
  const { server, port } = await startStaticServer(konvaFile);
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  try {
    const page = await browser.newPage();
    for (const width of [320, 390, 414]) {
      await page.setViewport({ width, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
      await page.goto('http://127.0.0.1:' + port + '/', { waitUntil: 'load' });
      await page.waitForFunction(() => {
        const tabs = document.querySelector('[data-ra-side-tabs]');
        const close = document.querySelector('[data-ra-more-close]');
        const side = document.querySelector('[data-ra-side]');
        return !!(tabs && getComputedStyle(tabs).display === 'none'
          && close && close.getBoundingClientRect().width > 8
          && side && side.getBoundingClientRect().height > 64
          && side.getBoundingClientRect().height < 160);
      });
      const geom = await page.evaluate(() => {
        const close = document.querySelector('[data-ra-more-close]');
        const objects = document.querySelector('[data-ra-palette-toggle]');
        const walls = document.querySelector('[data-ra-walls-toggle]');
        const area = document.querySelector('[data-ra-sheet-total]');
        const side = document.querySelector('[data-ra-side]');
        const tabs = document.querySelector('[data-ra-side-tabs]');
        const body = document.querySelector('.util-ra-side__body');
        const box = (el) => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          const cs = getComputedStyle(el);
          return {
            left: r.left,
            right: r.right,
            top: r.top,
            bottom: r.bottom,
            width: r.width,
            height: r.height,
            cssWidth: cs.width,
            paddingLeft: parseFloat(cs.paddingLeft) || 0,
            paddingRight: parseFloat(cs.paddingRight) || 0,
            minWidth: cs.minWidth,
            icon: !!(el.querySelector && el.querySelector('i, .fa')),
          };
        };
        return {
          innerWidth: window.innerWidth,
          innerHeight: window.innerHeight,
          close: box(close),
          objects: box(objects),
          walls: box(walls),
          area: box(area),
          side: box(side),
          tabs: box(tabs),
          body: box(body),
        };
      });
      assert.ok(geom.close, 'Close exists at ' + width);
      assert.ok(geom.close.icon, 'Close includes the icon at ' + width);
      assert.ok(geom.close.width >= 44 && geom.close.width < width * 0.55, 'Close is padded auto-width at ' + width + ': ' + geom.close.width);
      assert.ok(geom.close.paddingLeft >= 8 && geom.close.paddingRight >= 8, 'Close has horizontal padding at ' + width + ': ' + geom.close.paddingLeft);
      assert.ok(geom.close.right <= geom.innerWidth + 0.5, 'Close stays inside viewport at ' + width + ': right=' + geom.close.right);
      assert.ok(geom.close.left >= -0.5, 'Close left edge is on-screen at ' + width);
      ['objects', 'walls', 'area'].forEach((key) => {
        const box = geom[key];
        assert.ok(box, key + ' exists at ' + width);
        assert.ok(box.top >= 0, key + ' top visible at ' + width + ': ' + box.top);
        assert.ok(box.bottom <= geom.innerHeight + 0.5, key + ' bottom visible at ' + width + ': ' + box.bottom + ' vs ' + geom.innerHeight);
        assert.ok(box.left >= -0.5 && box.right <= geom.innerWidth + 0.5, key + ' horizontally visible at ' + width);
      });
      assert.ok(geom.side && geom.side.height > 64 && geom.side.height < 160, 'collapsed sheet is handle+strip only at ' + width + ': ' + (geom.side && geom.side.height));
      assert.ok(!geom.tabs || geom.tabs.height < 2, 'collapsed sheet hides tabs at ' + width);
      assert.ok(!geom.body || geom.body.height < 2, 'collapsed sheet hides panel body at ' + width);
    }

    for (const width of [320, 390, 1280]) {
      await page.setViewport({
        width,
        height: width >= 1280 ? 900 : 720,
        deviceScaleFactor: 1,
        isMobile: width < 900,
        hasTouch: width < 900,
      });
      await page.goto('http://127.0.0.1:' + port + '/', { waitUntil: 'load' });
      await page.evaluate(() => {
        const root = document.querySelector('[data-room-area]');
        if (root) {
          root.classList.add('is-sheet-expanded');
          root.classList.remove('is-more-open');
        }
      });
      await page.waitForFunction(() => {
        const finish = document.querySelector('[data-ra-finish]');
        const grid = document.querySelector('.util-ra-finish__grid');
        return !!(finish && grid && getComputedStyle(finish).display !== 'none' && grid.getBoundingClientRect().width > 40);
      });
      const finish = await page.evaluate(() => {
        const grid = document.querySelector('.util-ra-finish__grid');
        const fields = Array.from(document.querySelectorAll('[data-ra-finish] .admin-input, [data-ra-finish] .admin-btn'));
        const help = document.querySelector('[data-ra-help-pop="finish"]');
        const box = (el) => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          const cs = getComputedStyle(el);
          return { width: r.width, height: r.height, left: r.left, right: r.right, columns: cs.gridTemplateColumns };
        };
        return {
          innerWidth: window.innerWidth,
          grid: box(grid),
          help: box(help),
          fields: fields.map((el) => {
            const r = el.getBoundingClientRect();
            return { height: r.height, right: r.right, overflow: r.right > window.innerWidth + 0.5 };
          }),
        };
      });
      assert.ok(finish.grid, 'finish grid exists at ' + width);
      assert.ok(finish.grid.width > 40 && finish.grid.right <= finish.innerWidth + 0.5, 'finish grid stays in viewport at ' + width);
      assert.ok((finish.grid.columns || '').split(' ').filter(Boolean).length >= 2, 'finish grid is two columns at ' + width + ': ' + finish.grid.columns);
      assert.ok(finish.help && finish.help.width >= 20, 'finish help tip is present at ' + width);
      finish.fields.forEach((field, i) => {
        assert.ok(field.height >= 40, 'finish control ' + i + ' meets touch height at ' + width + ': ' + field.height);
        assert.ok(!field.overflow, 'finish control ' + i + ' stays on screen at ' + width);
      });
    }

    await page.setViewport({ width: 390, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, {
      minWidth: 200,
      maxWidth: 500,
      firstFitGone: true,
      editing: true,
    });
    const fitted = await page.evaluate(() => {
      const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
      const wrap = wrapEl.getBoundingClientRect();
      const stage = window.Konva.stages[0];
      const world = stage.getLayers()[0].getChildren()[0];
      const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
      const scale = world ? world.scaleX() : stage.scaleX();
      const pos = world ? world.position() : stage.position();
      const screen = verts.map((v) => ({
        x: wrap.left + pos.x + v.x * scale,
        y: wrap.top + pos.y + v.y * scale,
      }));
      const minX = Math.min.apply(null, screen.map((p) => p.x));
      const maxX = Math.max.apply(null, screen.map((p) => p.x));
      const minY = Math.min.apply(null, screen.map((p) => p.y));
      const maxY = Math.max.apply(null, screen.map((p) => p.y));
      return {
        wrap: { left: wrap.left, right: wrap.right, top: wrap.top, bottom: wrap.bottom, width: wrap.width, height: wrap.height },
        room: { minX, maxX, minY, maxY, width: maxX - minX, height: maxY - minY },
        scale,
        pos,
        firstFit: document.documentElement.classList.contains('is-geom-first-fit'),
        feedback: (document.querySelector('[data-ra-feedback]') || {}).textContent || '',
      };
    });
    assert.ok(!fitted.firstFit, 'stale 900×640 first-fit wrap must be released before asserting');
    assert.ok(fitted.wrap.width >= 200 && fitted.wrap.width < 500, 'canvas wrap is the 390 layout: ' + fitted.wrap.width);
    assert.ok(fitted.scale > 1.2 && fitted.scale < 2.85, 'saved room was re-fitted after wrap shrink, scale=' + fitted.scale);
    assert.ok(
      fitted.room.minX >= fitted.wrap.left - 2 && fitted.room.maxX <= fitted.wrap.right + 2,
      'saved room stays inside wrap horizontally at 390: ' + JSON.stringify(fitted)
    );
    assert.ok(
      fitted.room.minY >= fitted.wrap.top - 2 && fitted.room.maxY <= fitted.wrap.bottom + 2,
      'saved room stays inside wrap vertically at 390: ' + JSON.stringify(fitted)
    );
    const wrapCx = (fitted.wrap.left + fitted.wrap.right) / 2;
    const roomCx = (fitted.room.minX + fitted.room.maxX) / 2;
    assert.ok(
      Math.abs(roomCx - wrapCx) <= 28,
      'saved room is centred at 390: roomCx=' + roomCx + ' wrapCx=' + wrapCx + ' scale=' + fitted.scale
    );
    assert.ok(
      fitted.room.width >= fitted.wrap.width * 0.72,
      'saved room fills most of the wrap width at 390: ' + fitted.room.width + ' vs ' + fitted.wrap.width
    );

    const layoutChangeHtml = fs.readFileSync(path.join(__dirname, 'fixtures/room-area-layout-change-fit.html'), 'utf8');
    assert.ok(!/width:\s*900px/.test(layoutChangeHtml), 'layout-change fixture must not force 900px');
    assert.ok(!/height:\s*640px/.test(layoutChangeHtml), 'layout-change fixture must not force 640px');
    assert.ok(!/!important/.test(layoutChangeHtml), 'layout-change fixture must not use !important sizes');
    assert.ok(!/requestAnimationFrame\s*=/.test(layoutChangeHtml), 'layout-change fixture must not shim requestAnimationFrame');
    assert.ok(/class="geom-host"/.test(layoutChangeHtml), 'layout-change fixture must use a real flex host');
    assert.ok(/class="geom-rail"/.test(layoutChangeHtml), 'layout-change fixture must change layout with a sibling rail');

    await page.setViewport({ width: 390, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/layout-change', { waitUntil: 'load' });
    try {
      await waitForStableCanvasFit(page, {
        minWidth: 300,
        maxWidth: 450,
        grown: true,
      });
    } catch (err) {
      const dump = await page.evaluate(() => {
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
          && window.Konva.stages[0].getLayers()[0]
          && window.Konva.stages[0].getLayers()[0].getChildren()[0];
        return {
          grown: document.documentElement.classList.contains('is-geom-grown'),
          wrapW: wrap ? wrap.getBoundingClientRect().width : null,
          scale: world ? world.scaleX() : null,
          stages: !!(window.Konva && window.Konva.stages && window.Konva.stages[0]),
          feedback: (document.querySelector('[data-ra-feedback]') || {}).textContent || '',
        };
      });
      throw new Error('layout-change wait failed: ' + JSON.stringify(dump) + ' — ' + err.message);
    }
    const relayout = await page.evaluate(() => {
      const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
      const wrap = wrapEl.getBoundingClientRect();
      const stage = window.Konva.stages[0];
      const world = stage.getLayers()[0].getChildren()[0];
      const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
      const scale = world ? world.scaleX() : stage.scaleX();
      const pos = world ? world.position() : stage.position();
      const screen = verts.map((v) => ({
        x: wrap.left + pos.x + v.x * scale,
        y: wrap.top + pos.y + v.y * scale,
      }));
      const minX = Math.min.apply(null, screen.map((p) => p.x));
      const maxX = Math.max.apply(null, screen.map((p) => p.x));
      const minY = Math.min.apply(null, screen.map((p) => p.y));
      const maxY = Math.max.apply(null, screen.map((p) => p.y));
      return {
        wrap: { left: wrap.left, right: wrap.right, top: wrap.top, bottom: wrap.bottom, width: wrap.width, height: wrap.height },
        room: { minX, maxX, minY, maxY, width: maxX - minX, height: maxY - minY },
        scale,
        grown: document.documentElement.classList.contains('is-geom-grown'),
      };
    });
    assert.ok(relayout.grown, 'layout-change wrap must grow after the first fallback fit');
    assert.ok(relayout.wrap.width >= 360 && relayout.wrap.width <= 420, 'layout-change wrap is a real 390 box: ' + relayout.wrap.width);
    assert.ok(relayout.scale > 1.2 && relayout.scale < 2.85, 'layout-change refit after wrap grew, scale=' + relayout.scale);
    assert.ok(
      relayout.room.minX >= relayout.wrap.left - 2 && relayout.room.maxX <= relayout.wrap.right + 2,
      'layout-change room stays inside wrap: ' + JSON.stringify(relayout)
    );
    const layoutCx = (relayout.wrap.left + relayout.wrap.right) / 2;
    const layoutRoomCx = (relayout.room.minX + relayout.room.maxX) / 2;
    assert.ok(
      Math.abs(layoutRoomCx - layoutCx) <= 28,
      'layout-change room is centred: roomCx=' + layoutRoomCx + ' wrapCx=' + layoutCx
    );

    const embedHtml = fs.readFileSync(path.join(__dirname, 'fixtures/room-area-zoom-pan-embedded.html'), 'utf8');
    assert.ok(!/data-ra-fullpage/.test(embedHtml), 'embedded fixture must not be full-page');
    assert.ok(!/util-ra--fullpage/.test(embedHtml), 'embedded fixture must not use the full-page class');

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.goto('http://127.0.0.1:' + port + '/zoom-pan', { waitUntil: 'load' });
    await waitForEditingRoom(page);
    const embedZoomed = await zoomAndPan(page);
    await page.evaluate(() => document.documentElement.classList.add('is-embed-rail'));
    try {
      await page.waitForFunction((prevW) => {
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const stage = window.Konva.stages[0];
        if (!wrap || !stage) return false;
        const wrapW = wrap.getBoundingClientRect().width;
        return wrapW < prevW - 40 && Math.abs(stage.width() - wrapW) < 2;
      }, { timeout: 8000 }, embedZoomed.wrapW);
    } catch (err) {
      const dump = await page.evaluate(readCameraScript());
      throw new Error('embedded rail wait failed: ' + JSON.stringify({ before: embedZoomed, after: dump }) + ' — ' + err.message);
    }
    const embedAfterRail = await page.evaluate(readCameraScript());
    assertCameraHeld(embedZoomed, embedAfterRail, 'embedded rail resize');
    assert.ok(embedAfterRail.stageW < embedZoomed.stageW - 20, 'embedded observer resized the stage');
    await assertExtremePanClamped(page, 'embedded extreme pan');

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 1600, firstFitGone: true, editing: true });
    const deskZoomed = await zoomAndPan(page);
    await page.setViewport({ width: 1240, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.waitForFunction((prevStageW) => {
      const wrap = document.querySelector('[data-ra-canvas-wrap]');
      const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
      if (window.innerWidth !== 1240 || !wrap || !stage) return false;
      const wrapW = wrap.getBoundingClientRect().width;
      return Math.abs(stage.width() - prevStageW) > 1 && Math.abs(stage.width() - wrapW) < 2;
    }, { timeout: 8000 }, deskZoomed.stageW);
    const deskAfterResize = await page.evaluate(readCameraScript());
    assertCameraHeld(deskZoomed, deskAfterResize, '1280 window resize');
    await page.evaluate(() => {
      const btn = document.querySelector('[data-ra-side-toggle]');
      if (btn) btn.click();
    });
    try {
      await page.waitForFunction((prevW) => {
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const root = document.querySelector('[data-room-area]');
        const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
        if (!root || !root.classList.contains('is-side-collapsed') || !wrap || !stage) return false;
        const wrapW = wrap.getBoundingClientRect().width;
        return Math.abs(wrapW - prevW) > 8 && Math.abs(stage.width() - wrapW) < 2;
      }, { timeout: 8000 }, deskAfterResize.wrapW);
    } catch (err) {
      const dump = await page.evaluate(readCameraScript());
      const flags = await page.evaluate(() => {
        const root = document.querySelector('[data-room-area]');
        return {
          collapsed: !!(root && root.classList.contains('is-side-collapsed')),
          hasToggle: !!document.querySelector('[data-ra-side-toggle]'),
        };
      });
      throw new Error('1280 panel wait failed: ' + JSON.stringify({ before: deskAfterResize, after: dump, flags }) + ' — ' + err.message);
    }
    const deskAfterPanel = await page.evaluate(readCameraScript());
    assertCameraHeld(deskZoomed, deskAfterPanel, '1280 panel toggle');
    await assertExtremePanClamped(page, '1280 extreme pan');

    await page.setViewport({ width: 390, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 500, firstFitGone: true, editing: true });
    const phoneZoomed = await zoomAndPan(page);
    await page.evaluate(() => {
      const handle = document.querySelector('[data-ra-sheet-handle]');
      if (handle) handle.click();
    });
    try {
      await page.waitForFunction((prevH) => {
        const root = document.querySelector('[data-room-area]');
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
        if (!root || !root.classList.contains('is-sheet-expanded') || !wrap || !stage) return false;
        const wrapH = wrap.getBoundingClientRect().height;
        const gutter = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ra-feedback-gutter')) || 22;
        return Math.abs(wrapH - prevH) > 8 && Math.abs(stage.height() - (wrapH - gutter)) < 3;
      }, { timeout: 8000 }, phoneZoomed.wrapH);
    } catch (err) {
      const dump = await page.evaluate(() => {
        const root = document.querySelector('[data-room-area]');
        const cam = (function () {
          const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
          const wrap = wrapEl ? wrapEl.getBoundingClientRect() : null;
          const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
          return {
            expanded: !!(root && root.classList.contains('is-sheet-expanded')),
            wrapH: wrap && wrap.height,
            stageH: stage && stage.height(),
          };
        })();
        return cam;
      });
      throw new Error('390 sheet wait failed: ' + JSON.stringify(dump) + ' — ' + err.message);
    }
    const phoneAfterSheet = await page.evaluate(readCameraScript());
    assertCameraHeld(phoneZoomed, phoneAfterSheet, '390 bottom sheet');
    await assertExtremePanClamped(page, '390 extreme pan');
  } finally {
    await browser.close();
    await new Promise((resolve) => server.close(resolve));
  }
}

assertCssGeometry();
const watchdog = setTimeout(function () {
  console.error('room-area phone geometry tests: timed out');
  process.exit(1);
}, TEST_TIMEOUT_MS);
measureViewports().then(function () {
  console.log('room-area phone geometry tests: ok');
  clearTimeout(watchdog);
  process.exit(0);
}).catch(function (err) {
  console.error(err);
  clearTimeout(watchdog);
  process.exit(1);
});

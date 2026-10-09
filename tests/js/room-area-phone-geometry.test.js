'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const CSS = fs.readFileSync(path.join(ROOT, 'public/css/admin-utilities.css'), 'utf8');
const KONVA_FILE = path.join(ROOT, 'node_modules/konva/konva.min.js');
const TEST_TIMEOUT_MS = 90000;

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
      } else if (urlPath === '/vendor-test/konva.min.js') {
        file = konvaFile;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
      }
      const allowed = file === fixture
        || file === savedRoomFixture
        || file === layoutChangeFixture
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

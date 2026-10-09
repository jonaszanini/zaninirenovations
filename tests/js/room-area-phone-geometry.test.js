'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const CSS = fs.readFileSync(path.join(ROOT, 'public/css/admin-utilities.css'), 'utf8');
const KONVA_FILE = path.join(ROOT, 'node_modules/konva/konva.min.js');
const KONVA_URL = 'https://unpkg.com/konva@9.3.22/konva.min.js';
const KONVA_CACHE = path.join('/tmp', 'room-area-konva.min.js');
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
  assert.ok(
    CSS.includes('.util-ra-first-tip[hidden]') && CSS.includes('.util-ra-first-tip:empty'),
    'first-tip collapses when hidden or empty'
  );
  const firstTipCollapse = CSS.slice(CSS.indexOf('.util-ra-first-tip[hidden]'));
  assert.ok(/height:\s*0/.test(firstTipCollapse.slice(0, 280)), 'hidden first-tip height is 0');
  assert.ok(
    /\.util-ra-first-tip\s*\{[^}]*flex:\s*0 0 auto/.test(CSS.replace(/\s+/g, ' ')),
    'first-tip must not grow with flex: 1 0 100%'
  );
}

function downloadFile(url, destPath) {
  return new Promise((resolve, reject) => {
    const dest = fs.createWriteStream(destPath);
    const client = url.startsWith('https') ? require('https') : http;
    const req = client.get(url, (res) => {
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        res.resume();
        dest.close();
        fs.unlink(destPath, () => {});
        const next = new URL(res.headers.location, url).toString();
        downloadFile(next, destPath).then(resolve, reject);
        return;
      }
      if (res.statusCode !== 200) {
        res.resume();
        dest.close();
        fs.unlink(destPath, () => {});
        reject(new Error('Could not download ' + url + ': HTTP ' + res.statusCode));
        return;
      }
      res.pipe(dest);
      dest.on('finish', () => dest.close(resolve));
    });
    req.on('error', (err) => {
      dest.close();
      fs.unlink(destPath, () => {});
      reject(err);
    });
  });
}

function resolveKonvaFile() {
  if (fs.existsSync(KONVA_FILE) && fs.statSync(KONVA_FILE).size > 1000) {
    return Promise.resolve(KONVA_FILE);
  }
  if (fs.existsSync(KONVA_CACHE) && fs.statSync(KONVA_CACHE).size > 1000) {
    return Promise.resolve(KONVA_CACHE);
  }
  return downloadFile(KONVA_URL, KONVA_CACHE).then(() => KONVA_CACHE);
}

function startStaticServer(konvaFile) {
  const publicDir = path.join(ROOT, 'public');
  const fixturesDir = path.join(__dirname, 'fixtures');
  const fixture = path.join(fixturesDir, 'room-area-phone-geometry.html');
  const savedRoomFixture = path.join(fixturesDir, 'room-area-saved-room-fit.html');
  const chromeFixture = path.join(fixturesDir, 'room-area-chrome-geometry.html');
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/') {
        file = fixture;
      } else if (urlPath === '/saved-room' || urlPath === '/saved-room.html') {
        file = savedRoomFixture;
      } else if (urlPath === '/chrome' || urlPath === '/chrome.html') {
        file = chromeFixture;
      } else if (urlPath === '/vendor-test/konva.min.js') {
        file = konvaFile;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
      }
      const allowed = file === fixture
        || file === savedRoomFixture
        || file === chromeFixture
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
    await page.waitForFunction(() => {
      const wrap = document.querySelector('[data-ra-canvas-wrap]');
      const canvas = wrap && wrap.querySelector('canvas');
      const feedback = document.querySelector('[data-ra-feedback]');
      const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
        && window.Konva.stages[0].getLayers()[0]
        && window.Konva.stages[0].getLayers()[0].getChildren()[0];
      return !!(wrap && wrap.getBoundingClientRect().width > 200
        && wrap.getBoundingClientRect().width < 500
        && canvas && canvas.width > 10
        && feedback && /Editing/.test(feedback.textContent || '')
        && world);
    }, { timeout: 15000 });
    await page.evaluate(() => new Promise((resolve) => {
      requestAnimationFrame(() => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    }));
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

    for (const width of [1024, 1280]) {
      await page.setViewport({ width, height: 800, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
      await page.goto('http://127.0.0.1:' + port + '/chrome', { waitUntil: 'load' });
      const strip = await page.evaluate(() => {
        const tools = document.querySelector('.util-ra-full-header__row--tools');
        const bar = document.querySelector('.util-ra-project-bar');
        const tip = document.querySelector('[data-ra-first-tip]');
        const feedback = document.querySelector('[data-ra-feedback]');
        if (!tools || !bar) return { missing: true };
        const t = tools.getBoundingClientRect();
        const b = bar.getBoundingClientRect();
        const empties = [];
        document.querySelectorAll('.util-ra *').forEach((el) => {
          if (el === tools || tools.contains(el) || el === bar || bar.contains(el)) return;
          if (el.classList && el.classList.contains('util-ra-full-header')) return;
          const r = el.getBoundingClientRect();
          if (r.height <= 4 || r.width < 40) return;
          if (r.bottom <= t.bottom + 0.5 || r.top >= b.top - 0.5) return;
          const cs = getComputedStyle(el);
          const text = (el.innerText || '').trim();
          const empty = el.hasAttribute('hidden') || cs.display === 'none' || !text;
          if (empty) {
            empties.push({
              className: String(el.className || el.tagName),
              height: Math.round(r.height * 10) / 10,
              display: cs.display,
            });
          }
        });
        const tipBox = tip ? tip.getBoundingClientRect() : { height: 0 };
        const tipCs = tip ? getComputedStyle(tip) : { display: 'none' };
        const feedbackBox = feedback ? feedback.getBoundingClientRect() : { height: 0 };
        return {
          empties,
          tipHeight: tipBox.height,
          tipDisplay: tipCs.display,
          tipHidden: !!(tip && tip.hasAttribute('hidden')),
          feedbackHeight: feedbackBox.height,
          gap: Math.round((b.top - t.bottom) * 10) / 10,
        };
      });
      assert.ok(!strip.missing, 'chrome fixture rendered at ' + width);
      assert.ok(strip.tipHidden, 'first-tip stays hidden when no status is shown at ' + width);
      assert.ok(strip.tipDisplay === 'none' && strip.tipHeight < 0.5, 'hidden first-tip takes no space at ' + width + ': ' + JSON.stringify(strip));
      assert.ok(strip.feedbackHeight < 0.5, 'hidden status line takes no space at ' + width + ': ' + strip.feedbackHeight);
      assert.strictEqual(
        strip.empties.length,
        0,
        'no empty block taller than 4px between toolbar and Project at ' + width + ': ' + JSON.stringify(strip)
      );
    }
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

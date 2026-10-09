'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const CSS = fs.readFileSync(path.join(ROOT, 'public/css/admin-utilities.css'), 'utf8');

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

function startStaticServer() {
  const publicDir = path.join(ROOT, 'public');
  const fixture = path.join(__dirname, 'fixtures/room-area-phone-geometry.html');
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file = urlPath === '/'
        ? fixture
        : path.join(publicDir, urlPath.replace(/^\//, ''));
      if (!file.startsWith(publicDir) && file !== fixture) {
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
  const { server, port } = await startStaticServer();
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
      assert.ok(geom.close.width > 8 && geom.close.width < width * 0.55, 'Close is auto-width at ' + width + ': ' + geom.close.width);
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
  } finally {
    await browser.close();
    await new Promise((resolve) => server.close(resolve));
  }
}

assertCssGeometry();
measureViewports().then(function () {
  console.log('room-area phone geometry tests: ok');
}).catch(function (err) {
  console.error(err);
  process.exit(1);
});

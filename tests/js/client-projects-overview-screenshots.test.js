'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../..');
const BEFORE = path.join(__dirname, '../fixtures/client-project-overview-before.html');
const AFTER = path.join(__dirname, '../fixtures/client-project-overview-after.html');
const publicDir = path.join(ROOT, 'public');
const outDir = process.env.QA_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
const artifactDir = '/opt/cursor/artifacts';
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.png': 'image/png',
};

function startServer(pages) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (pages[urlPath]) {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(pages[urlPath]);
        return;
      }
      let file = path.join(publicDir, urlPath.replace(/^\//, '').split('?')[0]);
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

function copyArtifact(src, name) {
  try {
    fs.mkdirSync(artifactDir, { recursive: true });
    fs.copyFileSync(src, path.join(artifactDir, name));
  } catch (err) {
    // Local CI runners may not have the Cursor artifacts mount.
  }
}

async function measure(page) {
  return page.evaluate(() => {
    const panel = document.querySelector('.cp-tab-panel[data-panel="overview"]');
    const travel = document.querySelector('.cp-travel-cost-card');
    const details = document.querySelector('.cp-overview-details-card')
      || document.querySelector('.cp-tab-panel[data-panel="overview"] > .admin-card:not(.cp-travel-cost-card)');
    const grid = document.querySelector('.cp-overview-grid');
    const visitForm = document.querySelector('#cp-overview-visit-form');
    function box(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: r.x, y: r.y, w: r.width, h: r.height, top: r.top, bottom: r.bottom, left: r.left, right: r.right };
    }
    return {
      viewport: { w: window.innerWidth, h: window.innerHeight },
      panel: box(panel),
      travel: box(travel),
      details: box(details),
      grid: box(grid),
      visitFormHidden: visitForm ? visitForm.hidden : null,
      twoColGrid: !!(grid && getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length >= 2),
    };
  });
}

async function shot(page, label) {
  fs.mkdirSync(outDir, { recursive: true });
  const dest = path.join(outDir, `${label}.png`);
  const root = await page.$('.admin-content');
  assert.ok(root, `${label} missing admin content`);
  await root.screenshot({ path: dest });
  copyArtifact(dest, `${label}.png`);
  return dest;
}

async function openPage(browser, port, route, width, height) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}${route}`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.cp-tab-panel[data-panel="overview"]', { timeout: 15000 });
  return page;
}

async function main() {
  const css = fs.readFileSync(path.join(publicDir, 'css/admin-client-projects.css'), 'utf8');
  assert.match(css, /\.cp-overview-grid\s*\{[^}]*grid-template-columns:\s*1fr/s);
  assert.match(
    css,
    /@media\s*\(min-width:\s*1024px\)\s*\{[^}]*\.cp-overview-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s
  );

  const beforeHtml = fs.readFileSync(BEFORE, 'utf8');
  const afterHtml = fs.readFileSync(AFTER, 'utf8');
  assert.ok(!beforeHtml.includes('cp-overview-grid'), 'before fixture must be the stacked layout');
  assert.ok(afterHtml.includes('cp-overview-grid'), 'after fixture must use the overview grid');
  assert.ok(afterHtml.includes('data-cp-overview-edit="visit"'), 'after fixture must include visit edit');
  assert.ok(afterHtml.includes('data-cp-overview-edit="schedule"'), 'after fixture must include schedule edit');
  assert.ok(afterHtml.includes('CA$6.84'), 'seeded travel total must use CA$');
  assert.ok(!afterHtml.includes('Required —'), 'after copy must not use em dashes');
  assert.ok(afterHtml.includes('Not set'), 'empty fields use Not set');

  const { server, port } = await startServer({
    '/before': beforeHtml,
    '/after': afterHtml,
  });

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const results = {};
  try {
    const beforeDesktop = await openPage(browser, port, '/before', 1280, 900);
    const beforeDesktopLayout = await measure(beforeDesktop);
    assert.ok(beforeDesktopLayout.travel && beforeDesktopLayout.details, 'before 1280 missing cards');
    assert.ok(
      beforeDesktopLayout.details.top >= beforeDesktopLayout.travel.bottom - 12,
      `before 1280 must stack cards, details.top=${beforeDesktopLayout.details.top} travel.bottom=${beforeDesktopLayout.travel.bottom}`
    );
    await shot(beforeDesktop, 'client-project-overview-before-1280');
    await beforeDesktop.close();

    const beforePhone = await openPage(browser, port, '/before', 390, 844);
    const beforePhoneLayout = await measure(beforePhone);
    assert.strictEqual(beforePhoneLayout.viewport.w, 390);
    assert.ok(
      beforePhoneLayout.details.top >= beforePhoneLayout.travel.bottom - 12,
      'before 390 must stack cards'
    );
    await shot(beforePhone, 'client-project-overview-before-390');
    await beforePhone.close();

    const afterDesktop = await openPage(browser, port, '/after', 1280, 900);
    const afterDesktopLayout = await measure(afterDesktop);
    assert.ok(afterDesktopLayout.twoColGrid, 'after 1280 grid must be two columns');
    assert.ok(
      afterDesktopLayout.details.left >= afterDesktopLayout.travel.right - 8,
      `after 1280 cards must sit side by side, travel.right=${afterDesktopLayout.travel.right} details.left=${afterDesktopLayout.details.left}`
    );
    assert.ok(
      Math.abs(afterDesktopLayout.details.top - afterDesktopLayout.travel.top) < 48,
      'after 1280 cards must share a row'
    );
    assert.ok(afterDesktopLayout.travel.w < 700, `after 1280 travel width ${afterDesktopLayout.travel.w} should be about half`);
    assert.ok(afterDesktopLayout.details.w < 700, `after 1280 details width ${afterDesktopLayout.details.w} should be about half`);
    await shot(afterDesktop, 'client-project-overview-after-1280');

    await afterDesktop.click('[data-cp-overview-edit="visit"]');
    await afterDesktop.waitForFunction(() => {
      const form = document.querySelector('#cp-overview-visit-form');
      return form && !form.hidden;
    }, { timeout: 5000 });
    const visitOpenDesktop = await measure(afterDesktop);
    assert.strictEqual(visitOpenDesktop.visitFormHidden, false);
    const dateWidth1280 = await afterDesktop.evaluate(() => {
      const el = document.querySelector('#cp-overview-visit-date');
      return el ? el.getBoundingClientRect().width : 0;
    });
    assert.ok(dateWidth1280 >= 200, `after 1280 visit date width ${dateWidth1280} clips the year`);
    await shot(afterDesktop, 'client-project-overview-visit-edit-1280');
    await afterDesktop.close();

    const after1024 = await openPage(browser, port, '/after', 1024, 900);
    await after1024.click('[data-cp-overview-edit="visit"]');
    await after1024.waitForFunction(() => {
      const form = document.querySelector('#cp-overview-visit-form');
      return form && !form.hidden;
    }, { timeout: 5000 });
    const dateWidth1024 = await after1024.evaluate(() => {
      const el = document.querySelector('#cp-overview-visit-date');
      return el ? el.getBoundingClientRect().width : 0;
    });
    assert.ok(dateWidth1024 >= 200, `after 1024 visit date width ${dateWidth1024} clips the year`);
    await after1024.close();

    const afterPhone = await openPage(browser, port, '/after', 390, 844);
    const afterPhoneLayout = await measure(afterPhone);
    assert.strictEqual(afterPhoneLayout.viewport.w, 390);
    assert.ok(!afterPhoneLayout.twoColGrid, 'after 390 grid must be one column');
    assert.ok(
      afterPhoneLayout.details.top >= afterPhoneLayout.travel.bottom - 12,
      `after 390 must stack cards, details.top=${afterPhoneLayout.details.top} travel.bottom=${afterPhoneLayout.travel.bottom}`
    );
    await shot(afterPhone, 'client-project-overview-after-390');

    await afterPhone.click('[data-cp-overview-edit="visit"]');
    await afterPhone.waitForFunction(() => {
      const form = document.querySelector('#cp-overview-visit-form');
      return form && !form.hidden;
    }, { timeout: 5000 });
    await shot(afterPhone, 'client-project-overview-visit-edit-390');
    await afterPhone.close();

    const afterDark = await openPage(browser, port, '/after', 1280, 900);
    await afterDark.evaluate(() => {
      document.documentElement.classList.add('admin-dark');
      document.body.classList.add('admin-dark');
    });
    await shot(afterDark, 'client-project-overview-after-1280-dark');
    await afterDark.close();

    results.ok = true;
    results.beforeDesktop = beforeDesktopLayout;
    results.afterDesktop = afterDesktopLayout;
    results.afterPhone = afterPhoneLayout;
  } finally {
    await browser.close();
    server.close();
  }

  console.log(JSON.stringify(results, null, 2));
  console.log('client-projects-overview-screenshots: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

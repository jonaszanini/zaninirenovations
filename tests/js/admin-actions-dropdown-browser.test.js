'use strict';

/**
 * Regression: quotation (and invoice) row ⋯ menus must open on a real tap
 * at 390/405 even when a <details> summary wrapper calls stopPropagation,
 * stay fully inside the viewport, run each action, and close on outside
 * tap / Escape. Desktop 1280 overflow ⋯ must keep working.
 */
const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const qaDir = process.env.QA_OUT || path.join(ROOT, 'storage/app/qa-screenshots');
const artifactDir = path.join(ROOT, 'tests/js/artifacts');
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.eot': 'application/vnd.ms-fontobject',
};

const VIEWPORTS = [
  { name: '390', width: 390, height: 824, isMobile: true, hasTouch: true },
  { name: '405', width: 405, height: 824, isMobile: true, hasTouch: true },
  { name: '1280', width: 1280, height: 800, isMobile: false, hasTouch: false },
];

function waitMs(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function fixtureHtml() {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quotation row actions menu</title>
  <link rel="stylesheet" href="/css/font-awesome.min.css">
  <link rel="stylesheet" href="/css/admin.css">
  <link rel="stylesheet" href="/css/admin-client-projects.css">
</head>
<body class="admin-body">
  <div class="admin-app" id="admin-app">
    <aside class="admin-sidebar" id="admin-sidebar" aria-hidden="true"></aside>
    <div class="admin-main">
      <div class="admin-content" style="padding:12px;">
        <nav class="cp-tabs" aria-label="Project tabs">
          <button type="button" class="cp-tab">Overview</button>
          <button type="button" class="cp-tab is-active">Quotation</button>
          <button type="button" class="cp-tab">Contract</button>
          <button type="button" class="cp-tab">Invoice</button>
          <button type="button" class="cp-tab">Financials</button>
          <button type="button" class="cp-tab">Schedule</button>
        </nav>
        <section class="cp-tab-panel is-active" data-panel="quotation">
          <div class="admin-card" style="overflow:hidden; padding:12px;">
            <details class="cp-quotation-accordion admin-card cp-contract-card" id="quote-row">
              <summary class="cp-quotation-accordion__summary">
                <span class="cp-quotation-accordion__chevron" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
                <div class="cp-quotation-accordion__core">
                  <div class="cp-quotation-accordion__title-row">
                    <span class="cp-quotation-accordion__title">Fence quotation</span>
                    <span class="cp-quotation-accordion__number">FQ-1</span>
                  </div>
                </div>
                <div class="cp-quotation-accordion__total-block">
                  <span class="cp-quotation-accordion__total">$1,200.00</span>
                </div>
                <div class="cp-quotation-accordion__actions" onclick="event.stopPropagation();">
                  <div class="cp-quotation-toolbar admin-table-actions">
                    <div class="cp-quotation-toolbar__desktop">
                      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm">View</button>
                      <a class="admin-btn admin-btn--ghost admin-btn--sm" href="#edit-desktop">Edit</a>
                      <div class="admin-actions-dropdown cp-quotation-toolbar__overflow">
                        <button type="button" class="admin-actions-dropdown__toggle" aria-label="More actions" aria-expanded="false" aria-haspopup="menu">
                          <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                        </button>
                        <div class="admin-actions-dropdown__menu">
                          <button type="button" class="admin-actions-dropdown__item" data-k="sms">SMS</button>
                          <button type="button" class="admin-actions-dropdown__item" data-k="link">Link</button>
                          <button type="button" class="admin-actions-dropdown__item" data-k="dup">Duplicate</button>
                          <button type="button" class="admin-actions-dropdown__item admin-actions-dropdown__item--danger" data-k="del">Delete</button>
                        </div>
                      </div>
                    </div>
                    <div class="admin-actions-dropdown cp-quotation-toolbar__mobile">
                      <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions menu" aria-expanded="false" aria-haspopup="menu">
                        <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                      </button>
                      <div class="admin-actions-dropdown__menu">
                        <button type="button" class="admin-actions-dropdown__item" data-k="view">View</button>
                        <a class="admin-actions-dropdown__item" href="#edit-mobile" data-k="edit">Edit</a>
                        <a class="admin-actions-dropdown__item" href="#pdf-mobile" data-k="pdf">PDF</a>
                        <button type="button" class="admin-actions-dropdown__item" data-k="send">Send</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="cp-quotation-accordion__badges" data-total="$1,200.00">
                  <span class="cp-badge">Draft</span>
                </div>
              </summary>
              <div class="cp-quotation-accordion__panel">Expanded preview</div>
            </details>
          </div>
        </section>
        <section class="cp-tab-panel is-active" data-panel="invoice" style="margin-top:16px;">
          <div class="admin-card" style="overflow:hidden; padding:12px;">
            <div class="admin-table-scroll">
              <table class="admin-table cp-invoices-table">
                <thead>
                  <tr>
                    <th>Description</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td data-label="Description">Installment 1</td>
                    <td data-label="Actions" class="admin-table-actions">
                      <a class="admin-btn admin-btn--ghost admin-btn--sm" href="#inv-edit">Edit</a>
                      <div class="admin-actions-dropdown" id="invoice-dd">
                        <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions menu" aria-expanded="false" aria-haspopup="menu">
                          <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                        </button>
                        <div class="admin-actions-dropdown__menu">
                          <button type="button" class="admin-actions-dropdown__item" data-k="inv-pdf">PDF</button>
                          <button type="button" class="admin-actions-dropdown__item" data-k="inv-send">Send</button>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
        <p id="outside-target" style="margin-top:24px;">Outside tap target</p>
      </div>
    </div>
  </div>
  <script src="/js/admin-actions-dropdown.js"></script>
</body>
</html>`;
}

function startServer() {
  const html = fixtureHtml();
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/' || urlPath === '/index.html') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
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
          res.end('not found');
          return;
        }
        const ext = path.extname(file);
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function launch() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    throw new Error('puppeteer is required for admin actions dropdown browser tests');
  }
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function tapSelector(page, selector, useTouch) {
  const box = await page.$eval(selector, (el) => {
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y, w: r.width, h: r.height, display: getComputedStyle(el).display };
  });
  assert.notStrictEqual(box.display, 'none', selector + ' must be visible');
  assert.ok(box.w > 0 && box.h > 0, selector + ' must have a hit box');
  const x = box.x + box.w / 2;
  const y = box.y + box.h / 2;
  if (useTouch) {
    await page.touchscreen.tap(x, y);
  } else {
    await page.mouse.click(x, y);
  }
  await waitMs(80);
}

async function menuState(page) {
  return page.evaluate(() => {
    const open = document.querySelector('.admin-actions-dropdown.is-open');
    const menu = document.querySelector('.admin-actions-dropdown__menu.is-portaled')
      || (open && open.querySelector('.admin-actions-dropdown__menu'));
    if (!open || !menu) {
      return { open: false };
    }
    const r = menu.getBoundingClientRect();
    const style = getComputedStyle(menu);
    const items = [...menu.querySelectorAll('.admin-actions-dropdown__item')].map((el) => ({
      text: (el.textContent || '').trim(),
      visible: getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 0,
    }));
    return {
      open: true,
      portaled: menu.classList.contains('is-portaled') && menu.parentNode === document.body,
      display: style.display,
      zIndex: style.zIndex,
      left: r.left,
      right: r.right,
      top: r.top,
      bottom: r.bottom,
      width: r.width,
      height: r.height,
      vw: window.innerWidth,
      vh: window.innerHeight,
      items,
      detailsOpen: !!document.getElementById('quote-row')?.open,
    };
  });
}

function assertMenuUsable(state, label) {
  assert.ok(state.open, label + ': menu must open');
  assert.ok(state.portaled, label + ': menu must be portaled to body');
  assert.notStrictEqual(state.display, 'none', label + ': menu must be displayed');
  assert.ok(state.width > 40 && state.height > 40, label + ': menu must have size');
  assert.ok(state.left >= -1, label + ': menu left ' + state.left + ' is off the left edge');
  assert.ok(state.top >= -1, label + ': menu top ' + state.top + ' is off the top edge');
  assert.ok(state.right <= state.vw + 1, label + ': menu right ' + state.right + ' exceeds viewport ' + state.vw);
  assert.ok(state.bottom <= state.vh + 1, label + ': menu bottom ' + state.bottom + ' exceeds viewport ' + state.vh);
  assert.ok(state.items.length > 0, label + ': menu must list actions');
  state.items.forEach((item) => {
    assert.ok(item.visible, label + ': action "' + item.text + '" must be visible');
  });
}

async function closeMenu(page) {
  await page.keyboard.press('Escape');
  await waitMs(40);
  const state = await menuState(page);
  assert.strictEqual(state.open, false, 'Escape must close the menu');
}

async function saveShot(page, name) {
  fs.mkdirSync(qaDir, { recursive: true });
  fs.mkdirSync(artifactDir, { recursive: true });
  const file = name + '.png';
  const qaPath = path.join(qaDir, file);
  const artPath = path.join(artifactDir, file);
  await page.screenshot({ path: qaPath, fullPage: false });
  fs.copyFileSync(qaPath, artPath);
  const extra = '/opt/cursor/artifacts';
  try {
    fs.mkdirSync(extra, { recursive: true });
    fs.copyFileSync(qaPath, path.join(extra, file));
  } catch (err) {
    // Agent artifact mount is optional outside the cloud runner.
  }
  return qaPath;
}

(async function main() {
  const { server, port } = await startServer();
  const browser = await launch();
  const url = 'http://127.0.0.1:' + port + '/';
  try {
    const page = await browser.newPage();
    await page.goto(url, { waitUntil: 'load' });

    for (const vp of VIEWPORTS) {
      await page.setViewport({
        width: vp.width,
        height: vp.height,
        isMobile: vp.isMobile,
        hasTouch: vp.hasTouch,
        deviceScaleFactor: 1,
      });
      await page.goto(url, { waitUntil: 'load' });
      const label = vp.name + 'px';
      const mobile = vp.width < 768;
      const quoteToggle = mobile
        ? '.cp-quotation-toolbar__mobile .admin-actions-dropdown__toggle'
        : '.cp-quotation-toolbar__overflow .admin-actions-dropdown__toggle';

      await page.waitForSelector(quoteToggle, { visible: true });
      await page.$eval('#quote-row', (el) => el.scrollIntoView({ block: 'start', inline: 'nearest' }));
      await waitMs(40);
      await tapSelector(page, quoteToggle, vp.hasTouch);
      let state = await menuState(page);
      assertMenuUsable(state, label + ' quotation');
      assert.strictEqual(state.detailsOpen, false, label + ': tapping ⋯ must not expand the accordion');

      if (vp.name === '390' || vp.name === '1280') {
        await saveShot(page, 'quotation-row-actions-menu-' + vp.name);
      }

      const firstKey = mobile ? 'view' : 'sms';
      const clicked = await page.evaluate((k) => {
        const item = document.querySelector('.admin-actions-dropdown__menu.is-portaled [data-k="' + k + '"]');
        if (!item) return { found: false };
        window.__lastAction = null;
        item.addEventListener('click', function () { window.__lastAction = k; }, { once: true });
        item.click();
        return { found: true, action: window.__lastAction };
      }, firstKey);
      assert.ok(clicked.found, label + ': first action node exists');
      assert.strictEqual(clicked.action, firstKey, label + ': first action must receive the click');

      await page.click('#outside-target');
      await waitMs(40);
      state = await menuState(page);
      assert.strictEqual(state.open, false, label + ': outside tap must close the menu');

      await tapSelector(page, quoteToggle, vp.hasTouch);
      await closeMenu(page);

      const invoiceToggle = '#invoice-dd .admin-actions-dropdown__toggle';
      const invoiceInfo = await page.$eval(invoiceToggle, (el) => {
        const dd = el.closest('.admin-actions-dropdown');
        const r = el.getBoundingClientRect();
        return {
          toggleDisplay: getComputedStyle(el).display,
          ddDisplay: dd ? getComputedStyle(dd).display : null,
          w: r.width,
          h: r.height,
        };
      });
      const invoiceVisible = invoiceInfo.ddDisplay !== 'none' && invoiceInfo.w > 0 && invoiceInfo.h > 0;
      if (mobile) {
        assert.ok(invoiceVisible, label + ' invoice ⋯ must be tappable: ' + JSON.stringify(invoiceInfo));
      }
      if (invoiceVisible) {
        await tapSelector(page, invoiceToggle, vp.hasTouch);
        state = await menuState(page);
        assertMenuUsable(state, label + ' invoice');
        const invClicked = await page.evaluate(() => {
          const item = document.querySelector('.admin-actions-dropdown__menu.is-portaled [data-k="inv-pdf"]');
          if (!item) return false;
          window.__lastAction = null;
          item.addEventListener('click', function () { window.__lastAction = 'inv-pdf'; }, { once: true });
          item.click();
          return window.__lastAction === 'inv-pdf';
        });
        assert.ok(invClicked, label + ': invoice PDF action must work');
        await closeMenu(page);
      }
    }
  } finally {
    await browser.close();
    server.close();
  }
  console.log('admin-actions-dropdown browser tests: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});

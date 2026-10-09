/**
 * Batch 1 review screenshots from real pointer/tap interaction.
 *
 *   BASE_URL=http://127.0.0.1:8000 EDITOR_PATH=/admin/client-projects/1/utilities/room-area \
 *     ADMIN_EMAIL=admin@zanini.ca ADMIN_PASSWORD=password \
 *     QA_OUT=/opt/cursor/artifacts/screenshots node scripts/room-area-batch1-shots.js
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const EDITOR_PATH = process.env.EDITOR_PATH || '/admin/client-projects/1/utilities/room-area';
const EMAIL = process.env.ADMIN_EMAIL || 'admin@zanini.ca';
const PASSWORD = process.env.ADMIN_PASSWORD || 'password';
const OUT = process.env.QA_OUT || '/opt/cursor/artifacts/screenshots';
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function gotoReady(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
}

async function login(page) {
  await gotoReady(page, `${BASE_URL}/admin/login`);
  await page.type('input[name="email"]', EMAIL);
  await page.type('input[name="password"]', PASSWORD);
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }),
  ]);
}

async function waitForEditor(page) {
  await page.waitForSelector('.util-ra--fullpage', { timeout: 20000 });
  await page.waitForFunction(() => typeof window.Konva !== 'undefined' && typeof window.RoomAreaLib !== 'undefined', { timeout: 20000 });
  await delay(400);
}

async function tap(page, selector) {
  await page.waitForSelector(selector, { timeout: 8000 });
  const handle = await page.$(selector);
  if (!handle) throw new Error('missing ' + selector);
  await page.evaluate((el) => el.scrollIntoView({ block: 'nearest', inline: 'nearest' }), handle);
  const box = await handle.boundingBox();
  if (!box) throw new Error('no box for ' + selector);
  const x = box.x + box.width / 2;
  const y = box.y + box.height / 2;
  const vp = page.viewport();
  if (vp && vp.width <= 500 && page.touchscreen) {
    await page.touchscreen.tap(x, y);
  } else {
    await page.mouse.click(x, y);
  }
}

async function measureCanvas(page) {
  return page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap], .util-ra-canvas-wrap');
    if (!wrap) return { width: 0, height: 0 };
    const r = wrap.getBoundingClientRect();
    return { width: Math.round(r.width), height: Math.round(r.height) };
  });
}

async function setTheme(page, mode) {
  await page.evaluate((theme) => {
    const root = document.documentElement;
    if (theme === 'dark') root.classList.add('admin-dark');
    else root.classList.remove('admin-dark');
    try { localStorage.setItem('admin-theme', theme === 'dark' ? 'dark' : 'light'); } catch (e) {}
  }, mode);
}

async function resetEditor(page) {
  await page.evaluate(() => {
    try {
      localStorage.removeItem('zr-room-area-guide-tip-v1');
    } catch (e) {}
    const tip = document.querySelector('[data-ra-first-tip]');
    if (tip) tip.hidden = true;
    const root = document.querySelector('[data-room-area]');
    if (root) {
      root.classList.remove('is-more-open');
      root.style.setProperty('--ra-sheet-h', window.matchMedia('(max-width: 900px)').matches ? '64px' : '');
    }
    const w = document.querySelector('[data-ra-type-rect-w]');
    const l = document.querySelector('[data-ra-type-rect-l]');
    if (w) w.value = '';
    if (l) l.value = '';
  });
}

async function applyLTemplate(page) {
  const phone = page.viewport() && page.viewport().width <= 500;
  if (phone) {
    const moreOpen = await page.evaluate(() => document.querySelector('[data-room-area]')?.classList.contains('is-more-open'));
    if (!moreOpen) await tap(page, '[data-ra-more-toggle]');
    await delay(200);
  }
  await tap(page, '[data-ra-template="l"]');
  await delay(250);
  await page.waitForFunction(() => {
    const undo = document.querySelector('[data-ra-undo-vertex]');
    return undo && !undo.disabled;
  }, { timeout: 8000 });
  if (phone) {
    await page.evaluate(() => {
      const root = document.querySelector('[data-room-area]');
      if (root) root.classList.remove('is-more-open');
    });
  }
}

async function prepareScene(page, opts) {
  opts = opts || {};
  await resetEditor(page);
  await applyLTemplate(page);
  if (opts.alcove) {
    const phone = page.viewport() && page.viewport().width <= 500;
    if (phone) await tap(page, '[data-ra-more-toggle]');
    await delay(150);
    await tap(page, '[data-ra-alcove]');
    await delay(200);
  }
  if (opts.dimMode) {
    await page.evaluate((mode) => {
      document.querySelectorAll('[data-ra-dimensions-mode]').forEach((sel) => {
        sel.value = mode;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }, opts.dimMode);
  }
  if (opts.metric) {
    await tap(page, '[data-ra-unit-system="metric"]');
    await delay(80);
    await page.evaluate(() => {
      const height = document.querySelector('[data-ra-wall-height]');
      if (height) {
        height.focus();
        height.value = '2.44';
        height.dispatchEvent(new Event('input', { bubbles: true }));
        height.dispatchEvent(new Event('change', { bubbles: true }));
        height.blur();
      }
    });
  }
  await delay(200);
}

async function shot(page, name) {
  const file = path.join(OUT, `${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  const canvas = await measureCanvas(page);
  console.log('wrote', file, 'canvas', canvas.width + 'x' + canvas.height);
  return canvas;
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1280,900'],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(30000);
  await login(page);
  const editorUrl = EDITOR_PATH.startsWith('http') ? EDITOR_PATH : `${BASE_URL}${EDITOR_PATH}`;
  await gotoReady(page, editorUrl);
  await waitForEditor(page);

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await setTheme(page, 'light');
  await page.evaluate(() => window.dispatchEvent(new Event('resize')));
  await delay(200);
  await prepareScene(page, { dimMode: 'all' });
  await shot(page, 'ra-b1-1280-light');

  await setTheme(page, 'dark');
  await delay(150);
  await shot(page, 'ra-b1-1280-dark');

  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
  await setTheme(page, 'light');
  await page.evaluate(() => window.dispatchEvent(new Event('resize')));
  await delay(250);
  await prepareScene(page, { dimMode: 'all' });
  const phoneLight = await shot(page, 'ra-b1-390-light');

  await setTheme(page, 'dark');
  await delay(150);
  const phoneDark = await shot(page, 'ra-b1-390-dark');

  await tap(page, '[data-ra-more-toggle]');
  await delay(250);
  await shot(page, 'ra-b1-390-more');
  await shot(page, 'ra-b1-390-templates');

  await page.evaluate(() => {
    const root = document.querySelector('[data-room-area]');
    if (root) {
      root.classList.remove('is-more-open');
      root.style.setProperty('--ra-sheet-h', '280px');
    }
    window.dispatchEvent(new Event('resize'));
  });
  await delay(250);
  await shot(page, 'ra-b1-390-sheet');

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
  await setTheme(page, 'light');
  await page.evaluate(() => {
    const root = document.querySelector('[data-room-area]');
    if (root) {
      root.classList.remove('is-more-open');
      root.style.removeProperty('--ra-sheet-h');
    }
    window.dispatchEvent(new Event('resize'));
  });
  await delay(200);
  await prepareScene(page, { dimMode: 'main' });
  await shot(page, 'ra-b1-1280-dims-main');
  await prepareScene(page, { dimMode: 'area' });
  await shot(page, 'ra-b1-1280-dims-area');
  await prepareScene(page, { metric: true });
  await shot(page, 'ra-b1-metric-wall-height');

  await browser.close();
  console.log('Batch 1 screenshots in', OUT);
  console.log('390 canvas light', phoneLight.height, 'dark', phoneDark.height);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

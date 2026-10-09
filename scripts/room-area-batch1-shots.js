/**
 * Batch 1 review screenshots from real interaction: 1280 + 390, light + dark,
 * plus metric wall-height conversion.
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

const shots = [
  { name: 'ra-b1-1280-light', width: 1280, height: 900, theme: 'light' },
  { name: 'ra-b1-1280-dark', width: 1280, height: 900, theme: 'dark' },
  { name: 'ra-b1-390-light', width: 390, height: 844, theme: 'light' },
  { name: 'ra-b1-390-dark', width: 390, height: 844, theme: 'dark' },
];

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
  await delay(500);
}

async function prepareScene(page, opts) {
  opts = opts || {};
  const dimMode = opts.dimMode || 'all';
  const templatesOpen = opts.templates !== false;
  await page.evaluate(() => {
    try {
      localStorage.removeItem('zr-room-area-guide-tip-v1');
      localStorage.removeItem('room-area-layers-locked');
      localStorage.removeItem('room-area-measure-unit');
      localStorage.removeItem('room-area-dimensions-mode');
    } catch (e) {}
    const tip = document.querySelector('[data-ra-first-tip]');
    if (tip) tip.hidden = true;
    const w = document.querySelector('[data-ra-type-rect-w]');
    const l = document.querySelector('[data-ra-type-rect-l]');
    if (w) w.value = '';
    if (l) l.value = '';
    const lock = document.querySelector('[data-ra-lock-layers]');
    if (lock && lock.checked) {
      lock.checked = false;
      lock.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await delay(80);
  await page.evaluate(() => {
    const l = document.querySelector('[data-ra-template="l"]');
    if (l) l.click();
  });
  await page.waitForFunction(() => {
    const undo = document.querySelector('[data-ra-undo-vertex]');
    return undo && !undo.disabled;
  }, { timeout: 8000 });
  await page.evaluate(() => {
    const alcove = document.querySelector('[data-ra-alcove]');
    if (alcove) alcove.click();
  });
  await delay(200);
  await page.evaluate(() => {
    const undo = document.querySelector('[data-ra-undo-vertex]');
    if (undo && !undo.disabled) undo.click();
  });
  await delay(120);
  await page.evaluate(() => {
    const redo = document.querySelector('[data-ra-redo]');
    if (redo && !redo.disabled) redo.click();
  });
  await delay(150);
  await page.evaluate((wantMetric, mode, openTpl) => {
    const dim = document.querySelector('[data-ra-dimensions-mode]');
    if (dim) {
      dim.value = mode;
      dim.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const metric = document.querySelector('[data-ra-unit-system="metric"]');
    if (wantMetric && metric) metric.click();
    const snap = document.querySelector('[data-ra-snap-toggle]');
    if (snap && snap.getAttribute('aria-pressed') !== 'true') snap.click();
    const lock = document.querySelector('[data-ra-lock-layers]');
    if (lock && !lock.checked) {
      lock.checked = true;
      lock.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const tpl = document.querySelector('[data-ra-templates-toggle]');
    const pop = document.querySelector('[data-ra-templates-popover]');
    const isOpen = pop && !pop.hidden;
    if (tpl && openTpl && !isOpen) tpl.click();
    if (tpl && !openTpl && isOpen) tpl.click();
    const mathTab = document.querySelector('[data-ra-side-tab="math"]');
    if (mathTab) mathTab.click();
  }, !!opts.metric, dimMode, templatesOpen);
  await delay(200);
  if (opts.metric) {
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
    await delay(200);
  }
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
  await page.waitForSelector('[data-ra-template="l"]', { timeout: 15000 });

  for (const shot of shots) {
    await page.setViewport({
      width: shot.width,
      height: shot.height,
      deviceScaleFactor: shot.width <= 400 ? 2 : 1,
    });
    await page.evaluate((mode) => {
      const root = document.documentElement;
      if (mode === 'dark') root.classList.add('admin-dark');
      else root.classList.remove('admin-dark');
      try { localStorage.setItem('admin-theme', mode === 'dark' ? 'dark' : 'light'); } catch (e) {}
    }, shot.theme);
    await page.evaluate(() => window.dispatchEvent(new Event('resize')));
    await delay(200);
    await prepareScene(page, {
      metric: false,
      templates: shot.width <= 400,
      dimMode: 'all',
    });
    const file = path.join(OUT, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: false });
    console.log('wrote', file);
  }

  for (const mode of ['main', 'area']) {
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.evaluate(() => {
      document.documentElement.classList.remove('admin-dark');
      try { localStorage.setItem('admin-theme', 'light'); } catch (e) {}
    });
    await prepareScene(page, { metric: false, templates: false, dimMode: mode });
    const dimFile = path.join(OUT, `ra-b1-1280-dims-${mode}.png`);
    await page.screenshot({ path: dimFile, fullPage: false });
    console.log('wrote', dimFile);
  }

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await prepareScene(page, { metric: false, templates: true, dimMode: 'all' });
  const tplFile = path.join(OUT, 'ra-b1-390-templates.png');
  await page.screenshot({ path: tplFile, fullPage: false });
  console.log('wrote', tplFile);

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.evaluate(() => {
    document.documentElement.classList.remove('admin-dark');
    try { localStorage.setItem('admin-theme', 'light'); } catch (e) {}
  });
  await prepareScene(page, { metric: true });
  const metricFile = path.join(OUT, 'ra-b1-metric-wall-height.png');
  await page.screenshot({ path: metricFile, fullPage: false });
  console.log('wrote', metricFile);

  await browser.close();
  console.log('Batch 1 screenshots in', OUT);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

/**
 * Batch 1 review screenshots: 1280 + 390, light + dark.
 * Places an L + alcove, enables undo/redo, snap guides, lock, All dims, metric.
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
  await page.waitForFunction(() => typeof window.Konva !== 'undefined', { timeout: 20000 });
  await delay(500);
}

async function prepareScene(page) {
  await page.evaluate(() => {
    try {
      localStorage.removeItem('zr-room-area-guide-tip-v1');
      localStorage.setItem('room-area-layers-locked', '0');
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
  await page.evaluate(() => {
    const dim = document.querySelector('[data-ra-dimensions-mode]');
    if (dim) {
      dim.value = 'all';
      dim.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const metric = document.querySelector('[data-ra-unit-system="metric"]');
    if (metric) metric.click();
    if (window.__roomAreaQA && window.__roomAreaQA.showSnapGuides) {
      window.__roomAreaQA.showSnapGuides();
    }
    const lock = document.querySelector('[data-ra-lock-layers]');
    if (lock && !lock.checked) {
      lock.checked = true;
      lock.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const roomTools = document.querySelector('.util-ra-tool-group--room-tools');
    const toolbar = document.querySelector('.util-ra-canvas-toolbar');
    const lockWrap = document.querySelector('[data-ra-lock-layers-wrap]');
    if (toolbar && roomTools) {
      const t = toolbar.getBoundingClientRect();
      const r = roomTools.getBoundingClientRect();
      toolbar.scrollLeft += (r.left - t.left - 8);
    }
    if (toolbar && lockWrap) {
      const t = toolbar.getBoundingClientRect();
      const l = lockWrap.getBoundingClientRect();
      if (l.right > t.right - 4) toolbar.scrollLeft += (l.right - t.right + 8);
    }
    const tpl = document.querySelector('[data-ra-templates-toggle]');
    if (tpl) tpl.click();
  });
  await delay(250);
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
    await prepareScene(page);
    const file = path.join(OUT, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: false });
    console.log('wrote', file);
  }

  await browser.close();
  console.log('Batch 1 screenshots in', OUT);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

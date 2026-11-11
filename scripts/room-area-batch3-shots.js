/**
 * Batch 3 review screenshots: Kitchen kits at 390/1280 light+dark.
 *
 *   BASE_URL=http://127.0.0.1:8000 \
 *     EDITOR_PATH=/admin/client-projects/1/utilities/room-area?ra_room=1 \
 *     ADMIN_EMAIL=admin@zanini.test ADMIN_PASSWORD=password \
 *     QA_OUT=/opt/cursor/artifacts/screenshots node scripts/room-area-batch3-shots.js
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const GIT_SHA = (process.env.GIT_SHA || execSync('git rev-parse --short HEAD', { cwd: path.resolve(__dirname, '..') }).toString().trim());

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const EDITOR_PATH = process.env.EDITOR_PATH || '/admin/client-projects/1/utilities/room-area?ra_room=1';
const EMAIL = process.env.ADMIN_EMAIL;
const PASSWORD = process.env.ADMIN_PASSWORD;
if (!EMAIL || !PASSWORD) {
  throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD must be set.');
}
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
  await delay(600);
  await page.evaluate(() => window.dispatchEvent(new Event('resize')));
  await delay(250);
}

async function setTheme(page, mode) {
  await page.evaluate((theme) => {
    if (window.AdminTheme && typeof window.AdminTheme.setDark === 'function') {
      window.AdminTheme.setDark(theme === 'dark');
      return;
    }
    const root = document.documentElement;
    if (theme === 'dark') root.classList.add('admin-dark');
    else root.classList.remove('admin-dark');
    try { localStorage.setItem('zanini-admin-theme', theme === 'dark' ? 'dark' : 'light'); } catch (e) {}
    document.dispatchEvent(new CustomEvent('admin-theme-change', { detail: { dark: theme === 'dark' } }));
  }, mode);
  await delay(200);
}

async function showKits(page, opts) {
  opts = opts || {};
  await page.evaluate((showTip) => {
    try { localStorage.removeItem('zr-room-area-guide-tip-v1'); } catch (e) {}
    const tip = document.querySelector('[data-ra-first-tip]');
    if (tip) tip.hidden = !showTip;
    const kits = document.querySelector('[data-ra-side-tab="kits"]');
    if (kits) kits.click();
    const root = document.querySelector('[data-room-area]');
    if (root && window.matchMedia('(max-width: 900px)').matches) {
      root.classList.add('is-sheet-expanded');
      root.style.setProperty('--ra-sheet-h', 'min(58vh, 420px)');
    }
  }, opts.showTip !== false);
  await page.waitForFunction(() => {
    const pane = document.querySelector('[data-ra-side-pane="kits"]');
    const line = document.querySelector('.util-ra-kit__line strong');
    return !!(pane && pane.getBoundingClientRect().height > 40 && line && /\d/.test(line.textContent || ''));
  }, { timeout: 12000 });
  await delay(250);
}

async function shot(page, name) {
  const file = path.join(OUT, `${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  console.log('wrote', file);
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

  const utilitiesUrl = `${BASE_URL}/admin/utilities/room-area?ra_project=1&ra_room=1`;
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await gotoReady(page, utilitiesUrl);
  await waitForEditor(page);
  await setTheme(page, 'light');
  await showKits(page, { showTip: true });
  await shot(page, 'ra-b3-1280-light');

  await setTheme(page, 'dark');
  await showKits(page, { showTip: true });
  await shot(page, 'ra-b3-1280-dark');

  const phone = await browser.newPage();
  phone.setDefaultTimeout(30000);
  await phone.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  const cookies = await page.cookies();
  if (cookies.length) await phone.setCookie(...cookies);
  await gotoReady(phone, editorUrl);
  await waitForEditor(phone);
  await setTheme(phone, 'light');
  await showKits(phone, { showTip: false });
  await shot(phone, `ra-b3-390-light-${GIT_SHA}`);

  await setTheme(phone, 'dark');
  await showKits(phone, { showTip: false });
  await shot(phone, `ra-b3-390-dark-${GIT_SHA}`);
  await phone.close();

  await browser.close();
  console.log('Batch 3 screenshots in', OUT);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

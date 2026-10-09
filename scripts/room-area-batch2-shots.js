/**
 * Batch 2 review screenshots: 390/1280 light+dark, plus the Math finish sheet.
 *
 *   BASE_URL=http://127.0.0.1:8000 EDITOR_PATH=/admin/client-projects/1/utilities/room-area \
 *     ADMIN_EMAIL=admin@zanini.ca ADMIN_PASSWORD=password \
 *     QA_OUT=/opt/cursor/artifacts/screenshots node scripts/room-area-batch2-shots.js
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
  await delay(600);
  await page.evaluate(() => window.dispatchEvent(new Event('resize')));
  await delay(250);
  await page.waitForFunction(() => {
    const wrap = document.querySelector('.util-ra-canvas-wrap');
    return wrap && wrap.getBoundingClientRect().height > 80;
  }, { timeout: 8000 });
}

async function visibleBox(page, selector) {
  return page.evaluate((sel) => {
    const el = Array.from(document.querySelectorAll(sel)).find((node) => {
      if (node.hidden) return false;
      const cs = getComputedStyle(node);
      if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) return false;
      const r = node.getBoundingClientRect();
      return r.width > 2 && r.height > 2;
    });
    if (!el) return null;
    el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y, width: r.width, height: r.height };
  }, selector);
}

async function tap(page, selector) {
  await page.waitForFunction((sel) => {
    const el = Array.from(document.querySelectorAll(sel)).find((node) => {
      if (node.hidden) return false;
      const cs = getComputedStyle(node);
      if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) return false;
      const r = node.getBoundingClientRect();
      return r.width > 2 && r.height > 2;
    });
    if (!el) return false;
    el.scrollIntoView({ block: 'center', inline: 'nearest' });
    const r = el.getBoundingClientRect();
    return r.width > 2 && r.height > 2 && r.top < window.innerHeight && r.bottom > 0;
  }, { timeout: 8000 }, selector);
  const box = await visibleBox(page, selector);
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

async function closeMore(page) {
  await page.evaluate(() => {
    const root = document.querySelector('[data-room-area]');
    const toggle = document.querySelector('[data-ra-more-toggle]');
    if (root && root.classList.contains('is-more-open') && toggle) toggle.click();
    if (root) root.classList.remove('is-more-open');
    const sheet = document.querySelector('[data-ra-more-sheet]');
    if (sheet) {
      sheet.style.removeProperty('display');
      sheet.scrollTop = 0;
    }
    const feedback = document.querySelector('[data-ra-feedback]');
    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
    }
  });
  await delay(150);
}

async function openMore(page) {
  await closeMore(page);
  await tap(page, '[data-ra-more-toggle]');
  await page.waitForFunction(() => {
    const view = document.querySelector('.util-ra-more-block--view');
    if (!view) return false;
    const r = view.getBoundingClientRect();
    return r.height > 20 && r.top < window.innerHeight && r.bottom > 80;
  }, { timeout: 8000 });
  await delay(150);
}

async function openTemplates(page) {
  await closeMore(page);
  await tap(page, '[data-ra-phone-bar] [data-ra-templates-toggle]');
  await page.waitForFunction(() => {
    const grid = document.querySelector('[data-ra-more-templates] .util-ra-templates-pop__grid');
    if (!grid) return false;
    const r = grid.getBoundingClientRect();
    return r.width > 40 && r.height > 40 && r.top < window.innerHeight && r.bottom > 80;
  }, { timeout: 8000 });
  await delay(150);
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
      root.style.setProperty('--ra-sheet-h', window.matchMedia('(max-width: 900px)').matches ? 'auto' : '');
    }
    const w = document.querySelector('[data-ra-type-rect-w]');
    const l = document.querySelector('[data-ra-type-rect-l]');
    if (w) w.value = '';
    if (l) l.value = '';
  });
}

async function applyLTemplate(page) {
  const phone = page.viewport() && page.viewport().width <= 500;
  await page.evaluate((isPhone) => {
    const root = document.querySelector('[data-room-area]');
    const sheet = document.querySelector('[data-ra-more-sheet]');
    const pop = document.querySelector('[data-ra-templates-popover]');
    if (isPhone && root) root.classList.add('is-more-open');
    if (isPhone && sheet) sheet.removeAttribute('hidden');
    if (pop) pop.removeAttribute('hidden');
    const btn = document.querySelector('[data-ra-template="l"]');
    if (!btn) return;
    btn.scrollIntoView({ block: 'center', inline: 'nearest' });
    btn.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true }));
    btn.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, cancelable: true }));
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
  }, !!phone);
  await delay(250);
  await page.waitForFunction(() => {
    const undo = document.querySelector('[data-ra-undo-vertex]');
    return undo && !undo.disabled;
  }, { timeout: 8000 });
  if (phone) await closeMore(page);
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
    await page.evaluate(() => {
      const btn = Array.from(document.querySelectorAll('[data-ra-unit-system="metric"]')).find((n) => n.getBoundingClientRect().width > 2) || document.querySelector('[data-ra-unit-system="metric"]');
      if (btn) btn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
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
  await page.evaluate(() => {
    const math = document.querySelector('[data-ra-side-tab="math"]');
    if (math) math.click();
  });
  await delay(200);
  await shot(page, 'ra-b2-1280-light');

  await setTheme(page, 'dark');
  await delay(150);
  await shot(page, 'ra-b2-1280-dark');

  const phone = await browser.newPage();
  phone.setDefaultTimeout(30000);
  await phone.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await gotoReady(phone, editorUrl);
  await waitForEditor(phone);
  await setTheme(phone, 'light');
  await phone.evaluate(() => window.dispatchEvent(new Event('resize')));
  await delay(250);
  await prepareScene(phone, { dimMode: 'all' });
  await closeMore(phone);
  const phoneLight = await shot(phone, 'ra-b2-390-light');

  await phone.evaluate(() => {
    const math = document.querySelector('[data-ra-side-tab="math"]');
    if (math) math.click();
    const root = document.querySelector('[data-room-area]');
    if (root) {
      root.classList.add('is-sheet-expanded');
      root.style.setProperty('--ra-sheet-h', 'min(52vh, 360px)');
    }
    window.dispatchEvent(new Event('resize'));
  });
  await delay(450);
  await phone.evaluate(() => {
    const finish = document.querySelector('[data-ra-finish]');
    if (finish) finish.scrollIntoView({ block: 'nearest' });
  });
  await delay(200);
  await shot(phone, 'ra-b2-390-sheet-light');

  await phone.evaluate(() => {
    const root = document.querySelector('[data-room-area]');
    if (root) {
      root.classList.remove('is-sheet-expanded');
      root.style.setProperty('--ra-sheet-h', 'auto');
    }
    const measure = document.querySelector('[data-ra-side-tab="measure"]');
    if (measure) measure.click();
  });
  await delay(200);
  await closeMore(phone);
  await setTheme(phone, 'dark');
  await delay(150);
  const phoneDark = await shot(phone, 'ra-b2-390-dark');

  await phone.evaluate(() => {
    const math = document.querySelector('[data-ra-side-tab="math"]');
    if (math) math.click();
    const root = document.querySelector('[data-room-area]');
    if (root) {
      root.classList.add('is-sheet-expanded');
      root.style.setProperty('--ra-sheet-h', 'min(52vh, 360px)');
    }
    window.dispatchEvent(new Event('resize'));
  });
  await delay(450);
  await phone.evaluate(() => {
    const finish = document.querySelector('[data-ra-finish]');
    if (finish) finish.scrollIntoView({ block: 'nearest' });
  });
  await delay(200);
  await shot(phone, 'ra-b2-390-sheet-dark');
  await phone.close();

  await browser.close();
  console.log('Batch 2 screenshots in', OUT);
  console.log('390 canvas light', phoneLight.height, 'dark', phoneDark.height);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

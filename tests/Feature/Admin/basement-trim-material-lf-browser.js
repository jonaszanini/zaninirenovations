'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_TRIM_EMAIL;
const password = process.env.BR_TRIM_PASSWORD;
const base = process.env.BR_TRIM_BASE;
const createPath = process.env.BR_TRIM_CREATE_PATH || '/admin/quotations/basement/create';
const shotsDir = process.env.BR_TRIM_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 90000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

async function login(page) {
  await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
  if (!page.url().includes('/admin/login')) return;
  await page.waitForSelector('input[name="email"]');
  await page.type('input[name="email"]', email);
  await page.type('input[name="password"]', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('button[type="submit"]'),
  ]);
}

async function gotoStep(page, key) {
  await page.waitForSelector(`[data-br-goto="${key}"]`, { timeout: 20000 });
  await page.click(`[data-br-goto="${key}"]`);
  await page.waitForFunction((step) => {
    const el = document.querySelector(`[data-br-step="${step}"]`);
    return el && el.classList.contains('is-active');
  }, { timeout: 15000 }, key);
  await new Promise((r) => setTimeout(r, 350));
}

async function waitGrandPositive(page) {
  await page.waitForFunction(() => {
    const el = document.getElementById('br-toolbar-grand-amount');
    if (!el) return false;
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 45000 });
}

async function typeField(page, selector, value) {
  await page.waitForSelector(selector, { timeout: 20000 });
  await page.click(selector, { clickCount: 3 });
  await page.keyboard.press('Backspace');
  await page.type(selector, String(value), { delay: 25 });
  await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (el) {
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }, selector);
}

async function waitBaseboardMaterial(page) {
  await page.waitForFunction(() => {
    const row = document.querySelector('tr[data-br-mat-key="baseboard"]');
    if (!row) return false;
    const label = String(row.querySelector('td')?.textContent || '');
    const qty = parseFloat(String(row.querySelector('[data-br-mat-qty]')?.textContent || '').replace(/[^\d.-]/g, ''));
    return label.toLowerCase().includes('per lin. ft') && isFinite(qty) && qty >= 99;
  }, { timeout: 90000 });
}

(async function main() {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultTimeout(45000);
  await login(page);
  await page.goto(base + createPath, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForSelector('#br-main-form', { timeout: 45000 });

  const report = { ok: false, materials: {}, shots: {} };

  await gotoStep(page, 'overview');
  await typeField(page, '#br-area', '400');
  await typeField(page, '#br-material-waste', '0');
  await waitGrandPositive(page);

  await page.evaluate(() => {
    const cb = document.querySelector('input[data-br-enabled-section="doors_trim"]');
    if (cb && !cb.checked) {
      cb.click();
    }
  });
  await gotoStep(page, 'doors_trim');
  await typeField(page, '#br-base', '100');
  await new Promise((r) => setTimeout(r, 1200));
  await waitBaseboardMaterial(page);

  await gotoStep(page, 'review');

  report.materials = await page.evaluate(() => {
    const row = document.querySelector('tr[data-br-mat-key="baseboard"]');
    const label = String(row?.querySelector('td')?.textContent || '').trim();
    const qtyText = String(row?.querySelector('[data-br-mat-qty]')?.textContent || '').trim();
    const priceText = String(row?.querySelector('[data-br-mat-price]')?.textContent || '').trim();
    const amtText = String(row?.querySelector('[data-br-mat-amt]')?.textContent || '').trim();
    const n = (t) => parseFloat(String(t).replace(/[^\d.-]/g, ''));
    return {
      label,
      qty: n(qtyText),
      unitPrice: n(priceText),
      amount: n(amtText),
      subtotal: !!document.querySelector('[data-br-section-subtotal="doors_trim"]'),
    };
  });

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  report.shots.desktop = path.join(shotsDir, 'basement-trim-material-lf-1280.png');
  await page.screenshot({ path: report.shots.desktop, fullPage: false });

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  report.shots.mobile = path.join(shotsDir, 'basement-trim-material-lf-390.png');
  await page.screenshot({ path: report.shots.mobile, fullPage: false });

  const m = report.materials;
  report.ok = m.label.toLowerCase().includes('per lin. ft')
    && Math.abs(m.qty - 100) < 0.05
    && Math.abs(m.unitPrice - 3) < 0.05
    && Math.abs(m.amount - 300) < 0.05
    && m.subtotal;

  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});

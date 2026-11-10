'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_CB_EMAIL;
const password = process.env.BR_CB_PASSWORD;
const base = process.env.BR_CB_BASE;
const createPath = process.env.BR_CB_CREATE_PATH || '/admin/quotations/basement/create';
const shotsDir = process.env.BR_CB_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 120000,
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

async function gotoDrywall(page) {
  const btn = await page.$('[data-br-goto="drywall"]');
  if (!btn) throw new Error('Missing wizard progress control for drywall');
  await btn.click();
  await page.waitForFunction(() => {
    const step = document.querySelector('[data-br-step="drywall"]');
    return step
      && step.classList.contains('is-active')
      && !!document.getElementById('br-dw-corners');
  }, { timeout: 45000 });
  await new Promise((r) => setTimeout(r, 400));
}

async function typeField(page, selector, value) {
  await page.waitForSelector(selector, { timeout: 20000 });
  await page.click(selector, { clickCount: 3 });
  await page.keyboard.press('Backspace');
  await page.type(selector, String(value), { delay: 15 });
  await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (el) {
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }, selector);
}

async function waitGrandPositive(page) {
  await page.waitForFunction(() => {
    const el = document.getElementById('br-toolbar-grand-amount');
    if (!el) return false;
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 60000 });
}

async function cornerBeadVisible(page) {
  return page.evaluate(() => {
    const rows = document.querySelectorAll('#br-mat-rows tr[data-br-mat-key]');
    for (const row of rows) {
      const key = row.getAttribute('data-br-mat-key') || '';
      const label = String(row.querySelector('td')?.textContent || '').toLowerCase();
      if (key.includes('corner_bead') || label.includes('corner bead')) {
        const qty = parseFloat(String(row.querySelectorAll('td')[1]?.textContent || '').replace(/[^\d.-]/g, ''));
        return { found: true, qty: isFinite(qty) ? qty : 0, label: String(row.querySelector('td')?.textContent || '').trim() };
      }
    }
    const corners = document.getElementById('br-dw-corners');
    const tip = document.querySelector('label[for="br-dw-corners"]')
      ?.closest('.admin-form-group')
      ?.querySelector('.admin-info-tip');
    return { found: false, cornersValue: corners ? corners.value : '', hasTip: !!tip };
  });
}

async function fillDrywallForPreview(page) {
  await typeField(page, '#br-area', '500');
  await typeField(page, '#br-dw-wall', '500');
  await typeField(page, '#br-dw-corners', '2');
  await waitGrandPositive(page);
  await new Promise((r) => setTimeout(r, 1200));
}

(async function main() {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultTimeout(60000);
  await login(page);
  await page.goto(base + createPath, { waitUntil: 'networkidle2', timeout: 90000 });
  await page.waitForSelector('#br-main-form', { timeout: 60000 });

  const report = { ok: false, labels: {}, shots: {}, live: {} };

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await gotoDrywall(page);
  await fillDrywallForPreview(page);
  report.live.desktop = await cornerBeadVisible(page);
  report.labels.desktop = await page.evaluate(() => {
    const tip = document.querySelector('label[for="br-dw-corners"]')
      ?.closest('.admin-form-group')
      ?.querySelector('.admin-info-tip');
    return {
      hasTip: !!tip,
      subtotal: !!document.querySelector('[data-br-section-subtotal="drywall"]'),
    };
  });
  report.shots.desktop = path.join(shotsDir, 'basement-corner-bead-drywall-1280.png');
  await page.screenshot({ path: report.shots.desktop, fullPage: false });

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 250));
  await gotoDrywall(page);
  report.live.mobile = await cornerBeadVisible(page);
  report.labels.mobile = await page.evaluate(() => {
    const wrap = document.getElementById('br-dw-corners')?.closest('.admin-form-group');
    const rect = wrap ? wrap.getBoundingClientRect() : null;
    return { visible: !!(rect && rect.height > 0 && rect.width > 0) };
  });
  report.shots.mobile = path.join(shotsDir, 'basement-corner-bead-drywall-390.png');
  await page.screenshot({ path: report.shots.mobile, fullPage: false });

  const beadOk = (report.live.desktop.found && report.live.desktop.qty > 0)
    || (report.live.mobile.found && report.live.mobile.qty > 0);

  report.ok = report.labels.desktop.hasTip
    && report.labels.desktop.subtotal
    && report.labels.mobile.visible
    && beadOk;

  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});

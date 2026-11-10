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
    protocolTimeout: 60000,
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
  await page.waitForSelector('[data-br-goto="drywall"]', { timeout: 20000 });
  await page.click('[data-br-goto="drywall"]');
  await page.waitForFunction(() => {
    const step = document.querySelector('[data-br-step="drywall"]');
    return step && step.classList.contains('is-active');
  }, { timeout: 15000 });
  await page.waitForSelector('#br-dw-corners', { timeout: 15000 });
}

(async function main() {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  await login(page);
  await page.goto(base + createPath, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForSelector('#br-main-form', { timeout: 45000 });

  const report = { ok: false, labels: {}, shots: {} };

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await gotoDrywall(page);
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
  await new Promise((r) => setTimeout(r, 200));
  await gotoDrywall(page);
  report.labels.mobile = await page.evaluate(() => {
    const wrap = document.getElementById('br-dw-corners')?.closest('.admin-form-group');
    const rect = wrap ? wrap.getBoundingClientRect() : null;
    return { visible: !!(rect && rect.height > 0 && rect.width > 0) };
  });
  report.shots.mobile = path.join(shotsDir, 'basement-corner-bead-drywall-390.png');
  await page.screenshot({ path: report.shots.mobile, fullPage: false });

  report.ok = report.labels.desktop.hasTip
    && report.labels.desktop.subtotal
    && report.labels.mobile.visible;

  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});

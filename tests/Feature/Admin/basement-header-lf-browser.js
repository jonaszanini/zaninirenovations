'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_HEADER_EMAIL;
const password = process.env.BR_HEADER_PASSWORD;
const base = process.env.BR_HEADER_BASE;
const createPath = process.env.BR_HEADER_CREATE_PATH || '/admin/quotations/basement/create';
const shotsDir = process.env.BR_HEADER_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');

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

async function gotoFraming(page) {
  await page.waitForSelector('[data-br-goto="framing"]', { timeout: 20000 });
  await page.click('[data-br-goto="framing"]');
  await page.waitForFunction(() => {
    const step = document.querySelector('[data-br-step="framing"]');
    return step && step.classList.contains('is-active');
  }, { timeout: 15000 });
  await page.waitForSelector('#br-headers', { timeout: 15000 });
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
  await gotoFraming(page);
  report.labels.desktop = await page.evaluate(() => {
    const label = document.querySelector('label[for="br-headers"]');
    const input = document.getElementById('br-headers');
    const tip = document.querySelector('label[for="br-headers"]')
      ?.closest('.admin-form-group')
      ?.querySelector('.admin-info-tip');
    return {
      label: label ? String(label.textContent || '').trim() : '',
      inputName: input ? input.getAttribute('name') : '',
      step: input ? String(input.getAttribute('step') || '') : '',
      hasTip: !!tip,
    };
  });
  report.shots.desktop = path.join(shotsDir, 'basement-header-lf-framing-1280.png');
  await page.screenshot({ path: report.shots.desktop, fullPage: false });

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  await gotoFraming(page);
  report.labels.mobile = await page.evaluate(() => {
    const wrap = document.getElementById('br-headers')?.closest('.admin-form-group');
    const label = document.querySelector('label[for="br-headers"]');
    const rect = wrap ? wrap.getBoundingClientRect() : null;
    return {
      label: label ? String(label.textContent || '').trim() : '',
      visible: !!(rect && rect.height > 0 && rect.width > 0),
    };
  });
  report.shots.mobile = path.join(shotsDir, 'basement-header-lf-framing-390.png');
  await page.screenshot({ path: report.shots.mobile, fullPage: false });

  report.ok = report.labels.desktop.label === 'Headers / beams (lin. ft)'
    && report.labels.desktop.inputName === 'header_lf'
    && report.labels.desktop.step === '0.01'
    && report.labels.desktop.hasTip
    && report.labels.mobile.label === 'Headers / beams (lin. ft)'
    && report.labels.mobile.visible;

  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});

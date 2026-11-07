'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.PQ_MARBLE_EMAIL;
const password = process.env.PQ_MARBLE_PASSWORD;
const base = process.env.PQ_MARBLE_BASE;
const outPath = process.env.PQ_MARBLE_OUT;
const shotsDir = process.env.PQ_MARBLE_SHOTS || path.join(process.cwd(), 'storage/app/pq-marble-shots');

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 30000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

async function shot(page, name) {
  const file = path.join(shotsDir, name + '.png');
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      await page.screenshot({ path: file, fullPage: false });
      return file;
    } catch (err) {
      if (attempt === 3) throw err;
      await new Promise((r) => setTimeout(r, 150 * (attempt + 1)));
    }
  }
  return file;
}

async function marbleState(page) {
  return page.evaluate(() => {
    const form = document.getElementById('pq-main-form');
    const enabled = document.getElementById('pq-marble-enabled');
    const fields = document.getElementById('pq-marble-fields');
    const area = document.getElementById('pq-marble-area');
    const typeSel = document.getElementById('pq-marble-type');
    const depth = document.getElementById('pq-marble-depth');
    const invalid = form ? form.querySelector(':invalid') : null;
    return {
      marbleChecked: !!(enabled && enabled.checked),
      fieldsHidden: !!(fields && fields.hidden),
      fieldsDisplay: fields ? window.getComputedStyle(fields).display : '',
      areaDisabled: !!(area && area.disabled),
      areaRequired: !!(area && area.required),
      areaMin: area ? String(area.getAttribute('min') || '') : '',
      areaValue: area ? String(area.value || '') : '',
      areaValid: area ? area.checkValidity() : false,
      typeDisabled: !!(typeSel && typeSel.disabled),
      depthDisabled: !!(depth && depth.disabled),
      formValid: form ? form.checkValidity() : false,
      firstInvalid: invalid
        ? {
            name: invalid.name || '',
            id: invalid.id || '',
            message: invalid.validationMessage || '',
            disabled: !!invalid.disabled,
            min: String(invalid.getAttribute('min') || ''),
            value: String(invalid.value || ''),
          }
        : null,
    };
  });
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(25000);
  page.setDefaultTimeout(25000);

  await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
  if (page.url().includes('/admin/login')) {
    await page.waitForSelector('input[name="email"]');
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
  }

  await page.goto(base + '/admin/quotations/paving/create', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#pq-main-form');
  await page.waitForSelector('#pq-marble-area');

  await page.type('#pq-client', 'Ordinary Paving Browser');
  await page.waitForSelector('.pq-stone-area-in');
  await page.click('.pq-stone-area-in', { clickCount: 3 });
  await page.type('.pq-stone-area-in', '80');
  await page.click('[data-cp-commute-include-no]');
  await page.click('[data-cp-debris-include-no]');
  await page.click('input[name="payment_installments"][value="3"]');
  const reply = await page.$('input[name="ask_reply_to_accept"][value="0"]');
  if (reply) {
    await reply.click();
  }

  const report = { shots: {} };
  report.off = await marbleState(page);

  for (const width of [1280, 390]) {
    await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
    await page.evaluate(() => {
      const el = document.getElementById('pq-marble-section');
      if (el) el.scrollIntoView({ block: 'center' });
    });
    await new Promise((r) => setTimeout(r, 150));
    report.shots['off-' + width] = await shot(page, 'paving_ordinary_no_marble_' + width);
  }

  await page.click('#pq-marble-enabled');
  await new Promise((r) => setTimeout(r, 80));
  report.enabledZero = await marbleState(page);

  await page.select('#pq-marble-type', await page.$eval('#pq-marble-type option:not([value=""])', (opt) => opt.value));
  await page.click('#pq-marble-area', { clickCount: 3 });
  await page.type('#pq-marble-area', '0.05');
  report.enabledTooSmall = await marbleState(page);

  await page.click('#pq-marble-area', { clickCount: 3 });
  await page.type('#pq-marble-area', '50');
  report.enabledValid = await marbleState(page);

  for (const width of [1280, 390]) {
    await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
    await page.evaluate(() => {
      const el = document.getElementById('pq-marble-section');
      if (el) el.scrollIntoView({ block: 'center' });
    });
    await new Promise((r) => setTimeout(r, 150));
    report.shots['on-' + width] = await shot(page, 'paving_enabled_marble_' + width);
  }

  await page.click('#pq-marble-enabled');
  await new Promise((r) => setTimeout(r, 80));
  report.offAfterToggle = await marbleState(page);

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#pq-btn-draft'),
  ]);
  report.afterSaveUrl = page.url();
  report.afterSaveMarbleChecked = await page.$eval('#pq-marble-enabled', (el) => !!el.checked);
  report.afterSaveAreaDisabled = await page.$eval('#pq-marble-area', (el) => !!el.disabled);

  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
})().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err));
  process.exit(1);
});

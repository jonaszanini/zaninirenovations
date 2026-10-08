const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/opt/cursor/artifacts';

const VIEWPORTS = [1280, 1024, 390];
const STEPS = [
  'overview', 'demolition', 'framing', 'electrical', 'plumbing', 'insulation',
  'ceiling', 'drywall', 'flooring', 'doors_trim', 'painting', 'bathroom',
  'finishing', 'review',
];

async function login(page) {
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'admin@zanini.ca');
  await page.fill('input[name="password"]', 'password');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 }),
    page.click('button[type="submit"]'),
  ]);
}

async function gotoStep(page, key) {
  const btn = page.locator(`.br-progress__item[data-br-goto="${key}"]`);
  if (await btn.count()) {
    await btn.click();
    await page.waitForTimeout(280);
  }
}

async function clearMoney(page, locator) {
  await locator.click();
  await locator.press('Control+A');
  await locator.press('Backspace');
}

async function typePermitDigits(page, digits) {
  const amt = page.locator('#br-permit-amount');
  await clearMoney(page, amt);
  await amt.pressSequentially(digits, { delay: 40 });
  return amt.inputValue();
}

async function enablePermit(page) {
  const box = page.locator('input.admin-checkbox__input[name="include_building_permit"]').first();
  if (await box.count() && !(await box.isChecked())) {
    await page.locator('label').filter({ hasText: 'Building permit' }).first().click({ force: true });
  }
}

async function waitPermitLine(page, amountRe) {
  await page.waitForFunction((reSrc) => {
    const re = new RegExp(reSrc);
    const body = document.getElementById('br-live-body');
    const text = body ? body.innerText : '';
    return /Permit/.test(text) && re.test(text);
  }, amountRe.source, { timeout: 8000 });
}

async function overflowReport(page, width, key) {
  return page.evaluate(({ width: w, key: k }) => {
    const el = document.documentElement;
    return {
      width: w,
      step: k,
      scrollWidth: el.scrollWidth,
      clientWidth: el.clientWidth,
      overflow: el.scrollWidth > el.clientWidth,
    };
  }, { width, key });
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await login(page);

  const overflow = [];
  const mask = {};

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
  const area = page.locator('#br-area');
  if (await area.count()) await area.fill('800');
  await page.waitForTimeout(400);
  await gotoStep(page, 'finishing');

  const permitHasDirect = await page.locator('#br-permit-amount').getAttribute('data-cp-money-entry');
  mask.directAttr = permitHasDirect;

  await enablePermit(page);
  mask.after175 = await typePermitDigits(page, '175');
  await waitPermitLine(page, /1\.75/);
  mask.after175Live = await page.locator('#br-live-body').innerText();

  mask.after17500 = await typePermitDigits(page, '17500');
  await waitPermitLine(page, /175\.00|175/);
  await page.waitForTimeout(350);
  mask.after17500Live = await page.locator('#br-live-body').innerText();

  await page.locator('#br-live-body').evaluate((el) => el.scrollIntoView({ block: 'nearest' }));
  const card = page.locator('details[data-br-step="finishing"]');
  if (await card.count()) {
    await card.evaluate((el) => el.scrollIntoView({ block: 'start' }));
  }
  await page.waitForTimeout(200);
  await page.screenshot({ path: path.join(OUT, 'bq5-finishing-1280.png'), fullPage: false });
  console.log('saved bq5-finishing-1280.png');

  for (const width of VIEWPORTS) {
    const height = width === 390 ? 844 : 900;
    await page.setViewportSize({ width, height });
    await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
    if (await page.locator('#br-area').count()) await page.locator('#br-area').fill('800');
    await page.waitForTimeout(350);

    for (const key of STEPS) {
      await gotoStep(page, key);
      if (key === 'electrical' && width === 1280) {
        await page.waitForTimeout(150);
        await page.screenshot({ path: path.join(OUT, 'bq5-bottom-bar-1280.png'), fullPage: false });
        console.log('saved bq5-bottom-bar-1280.png');
      }
      if (key === 'review' && width === 390) {
        const costs = page.locator('.q-additional-costs-card').first();
        if (await costs.count()) {
          await costs.evaluate((el) => el.scrollIntoView({ block: 'center' }));
          await page.waitForTimeout(200);
          await page.screenshot({ path: path.join(OUT, 'bq5-additional-costs-390.png'), fullPage: false });
          console.log('saved bq5-additional-costs-390.png');
        }
      }
      overflow.push(await overflowReport(page, width, key));
    }
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${BASE}/admin/quotations/millwork/create`, { waitUntil: 'networkidle' });
  const millReview = page.locator('.br-progress__item[data-mw-goto="review"]');
  if (await millReview.count()) {
    await millReview.click();
    await page.waitForTimeout(280);
  }
  const millCosts = page.locator('.q-additional-costs-card').first();
  if (await millCosts.count()) {
    await millCosts.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(200);
    await page.screenshot({ path: path.join(OUT, 'bq5-millwork-additional-costs-390.png'), fullPage: false });
    console.log('saved bq5-millwork-additional-costs-390.png');
  }

  await browser.close();

  const failures = overflow.filter((row) => row.overflow);
  const report = {
    passed: failures.length === 0
      && !mask.directAttr
      && mask.after175 === '1.75'
      && mask.after17500 === '175.00',
    mask,
    failures,
    all: overflow,
  };
  fs.writeFileSync(path.join(OUT, 'bq5-overflow.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    passed: report.passed,
    mask: {
      directAttr: mask.directAttr,
      after175: mask.after175,
      after17500: mask.after17500,
      liveHas175: /175/.test(mask.after17500Live || ''),
      liveHasPermit: /Permit/.test(mask.after17500Live || ''),
    },
    failures,
  }, null, 2));
  if (!report.passed) process.exitCode = 1;
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

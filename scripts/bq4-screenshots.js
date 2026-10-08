const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/opt/cursor/artifacts';
const ids = JSON.parse(fs.readFileSync('/workspace/storage/app/bq1-demo-ids.json', 'utf8'));

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

async function setPermit(page) {
  const permit = page.locator('label').filter({ hasText: 'Building permit' }).first();
  const box = page.locator('input.admin-checkbox__input[name="include_building_permit"]').first();
  if (await box.count() && !(await box.isChecked())) {
    await permit.click({ force: true });
  }
  const amt = page.locator('#br-permit-amount');
  await amt.click();
  await amt.fill('');
  await amt.type('175.00', { delay: 20 });
  await page.waitForFunction(() => {
    const body = document.getElementById('br-live-body');
    return !!(body && /Permit/.test(body.innerText) && /175/.test(body.innerText));
  }, null, { timeout: 8000 });
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

  for (const width of VIEWPORTS) {
    const height = width === 390 ? 844 : 900;
    await page.setViewportSize({ width, height });
    await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
    const area = page.locator('#br-area');
    if (await area.count()) await area.fill('800');
    await page.waitForTimeout(400);

    for (const key of STEPS) {
      await gotoStep(page, key);
      if (key === 'finishing' && (width === 1280 || width === 390)) {
        await setPermit(page);
        await page.locator('#br-live-body').evaluate((el) => {
          el.scrollIntoView({ block: 'nearest' });
        });
        const card = page.locator('details[data-br-step="finishing"]');
        if (await card.count()) {
          await card.evaluate((el) => el.scrollIntoView({ block: 'start' }));
        }
        await page.waitForTimeout(200);
        await page.screenshot({ path: path.join(OUT, `bq4-finishing-${width}.png`), fullPage: false });
        console.log('saved', `bq4-finishing-${width}.png`);
      }
      if (key === 'review' && (width === 1280 || width === 390)) {
        const costs = page.locator('.q-additional-costs-card').first();
        if (await costs.count()) {
          await costs.evaluate((el) => el.scrollIntoView({ block: 'center' }));
          await page.waitForTimeout(200);
          await page.screenshot({ path: path.join(OUT, `bq4-additional-costs-${width}.png`), fullPage: false });
          console.log('saved', `bq4-additional-costs-${width}.png`);
        }
        if (width === 1280) {
          await page.evaluate(() => {
            const root = document.scrollingElement || document.documentElement;
            root.scrollTop = root.scrollHeight;
          });
          await page.waitForTimeout(200);
          await page.screenshot({ path: path.join(OUT, 'bq4-review-bar-1280.png'), fullPage: false });
          console.log('saved bq4-review-bar-1280.png');
        }
      }
      overflow.push(await overflowReport(page, width, key));
    }
  }

  await browser.close();

  const failures = overflow.filter((row) => row.overflow);
  const report = { passed: failures.length === 0, failures, all: overflow };
  fs.writeFileSync(path.join(OUT, 'bq4-overflow.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ passed: report.passed, failures }, null, 2));
  if (failures.length) process.exitCode = 1;
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

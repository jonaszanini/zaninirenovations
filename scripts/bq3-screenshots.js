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
      if (key === 'finishing') {
        const permit = page.locator('label').filter({ hasText: 'Building permit' }).first();
        if (await permit.count()) {
          const checked = await page.locator('input[name="include_building_permit"]').first().isChecked();
          if (!checked) await permit.click({ force: true });
          const amt = page.locator('#br-permit-amount');
          if (await amt.count()) await amt.fill('175.00');
        }
        const finishCard = page.locator('details[data-br-step="finishing"]');
        if (await finishCard.count()) {
          await finishCard.evaluate((el) => el.scrollIntoView({ block: 'start' }));
        }
        await page.waitForTimeout(250);
        if (width === 1280 || width === 390) {
          await page.screenshot({ path: path.join(OUT, `bq3-finishing-${width}.png`), fullPage: false });
          console.log('saved', `bq3-finishing-${width}.png`);
        }
      }
      overflow.push(await overflowReport(page, width, key));
    }
  }

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(`${BASE}/admin/quotations/basement/${ids.quote_id}/edit`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  await page.evaluate(() => {
    const root = document.scrollingElement || document.documentElement;
    root.scrollTop = root.scrollHeight;
  });
  await page.waitForTimeout(200);
  await page.screenshot({ path: path.join(OUT, 'bq3-bottom-1280.png'), fullPage: false });
  console.log('saved bq3-bottom-1280.png');
  overflow.push(await overflowReport(page, 1280, 'edit-review'));

  await browser.close();

  const failures = overflow.filter((row) => row.overflow);
  const report = { passed: failures.length === 0, failures, all: overflow };
  fs.writeFileSync(path.join(OUT, 'bq3-overflow.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  if (failures.length) {
    process.exitCode = 1;
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

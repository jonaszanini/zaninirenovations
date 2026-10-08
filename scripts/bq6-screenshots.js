const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/opt/cursor/artifacts';

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

async function fillArea(page) {
  const area = page.locator('#br-area');
  if (await area.count()) await area.fill('800');
  await page.waitForTimeout(350);
}

async function setPermit(page) {
  const box = page.locator('input.admin-checkbox__input[name="include_building_permit"]').first();
  if (await box.count() && !(await box.isChecked())) {
    await page.locator('label').filter({ hasText: 'Building permit' }).first().click({ force: true });
  }
  const amt = page.locator('#br-permit-amount');
  await amt.click();
  await amt.press('Control+A');
  await amt.press('Backspace');
  await amt.pressSequentially('17500', { delay: 30 });
  await page.waitForFunction(() => {
    const body = document.getElementById('br-live-body');
    return !!(body && /Permit/.test(body.innerText) && /175/.test(body.innerText));
  }, null, { timeout: 8000 });
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await login(page);

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
  await fillArea(page);
  await gotoStep(page, 'electrical');
  const elec = page.locator('details[data-br-step="electrical"]');
  if (await elec.count()) await elec.evaluate((el) => el.scrollIntoView({ block: 'start' }));
  await page.waitForTimeout(200);
  await page.screenshot({ path: path.join(OUT, 'bq6-electrical-1280.png'), fullPage: false });
  console.log('saved bq6-electrical-1280.png');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
  await fillArea(page);
  await gotoStep(page, 'electrical');
  if (await elec.count()) await elec.evaluate((el) => el.scrollIntoView({ block: 'start' }));
  await page.waitForTimeout(200);
  await page.screenshot({ path: path.join(OUT, 'bq6-electrical-390.png'), fullPage: false });
  console.log('saved bq6-electrical-390.png');

  await gotoStep(page, 'review');
  const costs = page.locator('.q-additional-costs-card').first();
  if (await costs.count()) {
    await costs.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(200);
    await page.screenshot({ path: path.join(OUT, 'bq6-additional-costs-390.png'), fullPage: false });
    console.log('saved bq6-additional-costs-390.png');
  }

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
  await fillArea(page);
  await gotoStep(page, 'finishing');
  await setPermit(page);
  const finish = page.locator('details[data-br-step="finishing"]');
  if (await finish.count()) await finish.evaluate((el) => el.scrollIntoView({ block: 'start' }));
  await page.waitForTimeout(200);
  await page.screenshot({ path: path.join(OUT, 'bq6-finishing-1280.png'), fullPage: false });
  console.log('saved bq6-finishing-1280.png');

  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

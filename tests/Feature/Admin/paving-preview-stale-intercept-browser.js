'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.PQ_RACE_EMAIL;
const password = process.env.PQ_RACE_PASSWORD;
const base = process.env.PQ_RACE_BASE;
const outPath = process.env.PQ_RACE_OUT;
const shotsDir = process.env.PQ_RACE_SHOTS || path.join(process.cwd(), 'storage/app/pq-preview-race-shots');
const expect = JSON.parse(process.env.PQ_RACE_EXPECT || '{}');
const createPath = expect.createPath || '/admin/quotations/paving/create';
const poisonTotal = Number(expect.poisonTotal || 99999.99);

const POISON = {
  summary: {
    final_quote_total: poisonTotal,
    built_up_grand_total: poisonTotal,
    total_with_tax_mats_fixed_plus_labour: poisonTotal,
    profit_margin_amount: 0,
    profit_margin_rate: 0,
    reference_price_per_sq_ft: 0,
    working_days: 1,
    gross_profit: 0,
    margin_percent: 0,
  },
  materials: { with_tax: 0 },
  fixed_costs: { with_tax: 0 },
  labour: { total: 0 },
};

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

function moneyCents(text) {
  const n = parseFloat(String(text || '').replace(/[^0-9.]/g, ''));
  return Number.isFinite(n) ? Math.round(n * 100) : null;
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

function isPreview(req) {
  return req.method() === 'POST' && req.url().indexOf('/quotations/paving/preview') !== -1;
}

async function readTotals(page) {
  return page.evaluate(() => {
    const footer = document.querySelector('[data-qh-footer-amount]');
    const totalRow = document.querySelector('.pq-row--total strong');
    const missing = document.querySelector('.pq-preview-status');
    const root = document.getElementById('pq-live-root');
    function cents(text) {
      const n = parseFloat(String(text || '').replace(/[^0-9.]/g, ''));
      return isFinite(n) ? Math.round(n * 100) : null;
    }
    const footerText = footer ? String(footer.textContent || '').trim() : '';
    const totalText = totalRow ? String(totalRow.textContent || '').trim() : '';
    return {
      footer: footerText,
      totalQuote: totalText,
      footerCents: cents(footerText),
      totalCents: cents(totalText),
      missing: !!missing,
      pending: !!(root && root.getAttribute('data-pq-preview-state') === 'loading')
        || !!(footer && footer.getAttribute('data-qh-footer-pending') === '1'),
      unavailable: !!(footer && footer.getAttribute('data-qh-footer-unavailable') === '1'),
      state: root ? String(root.getAttribute('data-pq-preview-state') || '') : '',
      grand: typeof window.__quoteGrandTotal === 'number' ? window.__quoteGrandTotal : null,
      grandUnavailable: !!window.__quoteGrandTotalUnavailable,
      innerWidth: window.innerWidth,
    };
  });
}

async function waitSettled(page, expectedMoney) {
  const wantCents = expectedMoney != null && expectedMoney !== ''
    ? moneyCents(expectedMoney)
    : null;
  await page.waitForFunction((want) => {
    const missing = document.querySelector('.pq-preview-status');
    if (missing) return false;
    const root = document.getElementById('pq-live-root');
    if (root && root.getAttribute('data-pq-preview-state') !== 'ready') return false;
    const footer = document.querySelector('[data-qh-footer-amount]');
    const totalRow = document.querySelector('.pq-row--total strong');
    if (!footer || !totalRow) return false;
    if (footer.getAttribute('data-qh-footer-unavailable') === '1') return false;
    if (typeof window.__quoteGrandTotal !== 'number') return false;
    function cents(text) {
      const n = parseFloat(String(text || '').replace(/[^0-9.]/g, ''));
      return isFinite(n) ? Math.round(n * 100) : null;
    }
    const f = cents(footer.textContent);
    const t = cents(totalRow.textContent);
    if (f == null || t == null || f !== t || f <= 0) return false;
    if (want != null && f !== want) return false;
    return true;
  }, { timeout: 25000 }, wantCents);
  return readTotals(page);
}

async function waitUnavailable(page, states) {
  const allowed = states || ['missing', 'error', 'loading'];
  await page.waitForFunction((ok) => {
    const root = document.getElementById('pq-live-root');
    const footer = document.querySelector('[data-qh-footer-amount]');
    const state = root ? String(root.getAttribute('data-pq-preview-state') || '') : '';
    if (ok.indexOf(state) === -1) return false;
    if (typeof window.__quoteGrandTotal === 'number') return false;
    if (!footer || footer.getAttribute('data-qh-footer-unavailable') !== '1') return false;
    const t = String(footer.textContent || '').trim();
    if (/CA\$/.test(t) || /[0-9]/.test(t)) return false;
    if (document.querySelector('.pq-row--total')) return false;
    return true;
  }, { timeout: 15000 }, allowed);
  return readTotals(page);
}

async function setStoneArea(page, value) {
  const el = await page.waitForSelector('.pq-stone-area-in');
  await el.click({ clickCount: 3 });
  await page.keyboard.press('Backspace');
  await el.type(String(value), { delay: 15 });
}

async function fillBasics(page) {
  await page.waitForSelector('#pq-client');
  await page.click('#pq-client', { clickCount: 3 });
  await page.type('#pq-client', 'Paving Preview Race');
  await page.waitForSelector('.pq-stone-area-in');
  await setStoneArea(page, '80');
  const commuteNo = await page.$('[data-cp-commute-include-no]');
  if (commuteNo) await commuteNo.click();
  const debrisNo = await page.$('[data-cp-debris-include-no]');
  if (debrisNo) await debrisNo.click();
}

async function waitHeld(held, min) {
  const start = Date.now();
  while (held.length < min && Date.now() - start < 8000) {
    await new Promise((r) => setTimeout(r, 40));
  }
  if (held.length < min) {
    throw new Error('expected ' + min + ' held preview(s), got ' + held.length);
  }
}

async function resolveHeld(req, action) {
  try {
    if (typeof req.isInterceptResolutionHandled === 'function' && req.isInterceptResolutionHandled()) {
      return 'already-handled';
    }
    if (action === 'poison') {
      await req.respond({
        status: 200,
        contentType: 'application/json; charset=utf-8',
        body: JSON.stringify(POISON),
      });
      return 'poisoned';
    }
    if (action === 'error') {
      await req.respond({
        status: 500,
        contentType: 'application/json; charset=utf-8',
        body: JSON.stringify({ message: 'preview exploded' }),
      });
      return 'errored';
    }
    await req.continue();
    return 'continued';
  } catch (err) {
    return 'failed:' + String(err && err.message ? err.message : err);
  }
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  page.setDefaultTimeout(30000);

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

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.goto(base + createPath, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#pq-main-form');
  await fillBasics(page);
  const report = { shots: {}, expected: expect };

  report.settledOff = await waitSettled(page, expect.offMoney);

  const held = [];
  let interceptMode = 'hold';
  await page.setRequestInterception(true);
  page.on('request', (req) => {
    if (typeof req.isInterceptResolutionHandled === 'function' && req.isInterceptResolutionHandled()) {
      return;
    }
    if (!isPreview(req)) {
      req.continue().catch(() => {});
      return;
    }
    if (interceptMode === 'hold') {
      held.push(req);
      return;
    }
    if (interceptMode === 'error') {
      resolveHeld(req, 'error');
      return;
    }
    req.continue().catch(() => {});
  });

  await setStoneArea(page, '90');
  await waitHeld(held, 1);
  report.loadingAfterFirstHold = await waitUnavailable(page, ['loading']);
  await setStoneArea(page, '80');
  await waitHeld(held, 2);
  report.poisonOlder = await resolveHeld(held[0], 'poison');
  await new Promise((r) => setTimeout(r, 450));
  report.afterPoisonOlder = await readTotals(page);
  report.continueNewer = await resolveHeld(held[1], 'continue');
  report.afterNewestReady = await waitSettled(page, expect.offMoney);
  report.shots['ready-after-stale-1280'] = await shot(page, 'paving_preview_ready_after_stale_1280');
  await page.setViewport({ width: 390, height: 900, deviceScaleFactor: 1 });
  report.afterNewestReady390 = await waitSettled(page, expect.offMoney);
  report.shots['ready-after-stale-390'] = await shot(page, 'paving_preview_ready_after_stale_390');

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  const heldBeforeMissing = held.length;
  await setStoneArea(page, '90');
  await waitHeld(held, heldBeforeMissing + 1);
  report.loadingBeforeMissing = await waitUnavailable(page, ['loading']);
  await page.click('#pq-marble-enabled');
  report.validToMissing = await waitUnavailable(page, ['missing']);
  report.poisonAborted = await resolveHeld(held[held.length - 1], 'poison');
  await new Promise((r) => setTimeout(r, 450));
  report.afterAbortedPoison = await readTotals(page);
  report.shots['unavailable-1280'] = await shot(page, 'paving_preview_unavailable_1280');
  await page.setViewport({ width: 390, height: 900, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  report.validToMissing390 = await waitUnavailable(page, ['missing']);
  report.shots['unavailable-390'] = await shot(page, 'paving_preview_unavailable_390');

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await setStoneArea(page, '80');
  await waitUnavailable(page, ['missing']);
  interceptMode = 'error';
  const typeVal = await page.$eval('#pq-marble-type option:not([value=""])', (opt) => opt.value);
  await page.select('#pq-marble-type', typeVal);
  await page.evaluate(() => {
    const area = document.getElementById('pq-marble-area');
    area.value = '50';
    area.dispatchEvent(new Event('input', { bubbles: true }));
    document.getElementById('pq-main-form').dispatchEvent(new Event('input', { bubbles: true }));
  });
  report.afterError = await waitUnavailable(page, ['error', 'missing']);
  interceptMode = 'pass';
  await page.evaluate(() => {
    const depth = document.getElementById('pq-depth');
    if (depth) {
      depth.value = String(Number(depth.value || 12));
    }
    document.getElementById('pq-main-form').dispatchEvent(new Event('input', { bubbles: true }));
    document.getElementById('pq-main-form').dispatchEvent(new Event('change', { bubbles: true }));
  });
  report.afterErrorRecovered = await waitSettled(page, expect.onMoney);
  report.shots['recovered-1280'] = await shot(page, 'paving_preview_error_recovered_1280');
  await page.setViewport({ width: 390, height: 900, deviceScaleFactor: 1 });
  report.afterErrorRecovered390 = await waitSettled(page, expect.onMoney);
  report.shots['recovered-390'] = await shot(page, 'paving_preview_error_recovered_390');

  report.heldCount = held.length;
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
})().catch(async (err) => {
  console.error(err && err.stack ? err.stack : String(err));
  try {
    if (outPath) {
      fs.writeFileSync(outPath, JSON.stringify({ failed: String(err && err.message ? err.message : err) }, null, 2));
    }
  } catch (e) {}
  process.exit(1);
});
